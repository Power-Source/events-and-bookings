<?php

// Run with: php tests/events-map-integration.php
function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}

function add_filter( $hook, $callback ) {
	$GLOBALS['filters'][ $hook ][] = $callback;
}

function remove_filter( $hook, $callback ) {
	$GLOBALS['filters'][ $hook ] = array_values( array_filter(
		$GLOBALS['filters'][ $hook ],
		function ( $registered ) use ( $callback ) { return $registered !== $callback; }
	) );
}

function apply_filters( $hook, $value ) {
	foreach ( isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ] : array() as $callback ) {
		$value = $callback( $value );
	}
	return $value;
}

class AgmMapModel {
	public function get_custom_maps( $query ) {
		return array();
	}
}

class AgmMarkerReplacer {
	public function create_overlay_tag( $maps, $args ) {
		check( $maps[0]['markers'][0]['title'] === 'Testveranstaltung', 'The event title must reach the map marker.' );
		return 'rendered-map';
	}
}

class TestEvent {
	public function is_open() { return true; }
	public function get_title() { return 'Testveranstaltung'; }
	public function get_raw_map() { return array( 'markers' => array( array() ) ); }
}

class Eab_Template {
	public static function util_apply_shortcode_template( $event, $args ) {
		return 'event-details';
	}
}

class Eab_CollectionFactory {
	public static $observed;

	private static function record_filters() {
		self::$observed = array(
			apply_filters( 'eab-collection-date_ordering_direction', 'default-order' ),
			apply_filters( 'eab-collection-upcoming_weeks-week_number', 'default-weeks' ),
		);
	}

	public static function get_upcoming_weeks_events( $date, $query ) {
		self::record_filters();
		return array( new TestEvent() );
	}

	public static function get_upcoming_weeks( $date, $query ) {
		self::record_filters();
		return (object) array( 'query' => array() );
	}

	public static function get_upcoming_events( $date, $query ) {
		self::record_filters();
		return array( new TestEvent() );
	}
}

require_once dirname( __DIR__ ) . '/lib/class_eab_codec.php';
require_once dirname( __DIR__ ) . '/lib/class_eab_shortcodes.php';

$shortcodes = new Eab_Shortcodes();
foreach ( array( false, true ) as $legacy ) {
	$GLOBALS['filters'] = array();
	$result = $shortcodes->process_events_map_shortcode(
		array( 'lookahead' => 'yes', 'weeks' => '3', 'order' => 'desc', 'legacy' => $legacy ),
		'fallback'
	);
	check( Eab_CollectionFactory::$observed === array( 'DESC', 3 ), 'Order and week filters must receive the requested values.' );
	check( empty( $GLOBALS['filters']['eab-collection-date_ordering_direction'] ), 'The order filter must be removed.' );
	check( empty( $GLOBALS['filters']['eab-collection-upcoming_weeks-week_number'] ), 'The week filter must be removed.' );
	check( $result === ( $legacy ? 'fallback' : '<div >rendered-map</div>' ), 'The map or fallback must be returned.' );
}

$GLOBALS['filters'] = array();
$shortcodes->process_events_map_shortcode( array(), 'fallback' );
check( Eab_CollectionFactory::$observed === array( 'default-order', 'default-weeks' ), 'Default queries must not add overrides.' );
echo "Events map integration: OK\n";
