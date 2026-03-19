<?php
/*
Plugin Name: Nächster Ereignis-Shortcode
Description: Erzeugt einen formatierbaren Shortcode, der die Zeit des nächsten bevorstehenden Ereignisses anzeigt, das noch nicht gestartet wurde 
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 0.3
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Minimale Nutzung: [next_event] <br />EErweiterte Nutzung: [next_event format="H:i T l" class="next-event-class" add="-120" expired="Too late!"] <br /><b>format</b> ist das Zeitformat. Details siehe <a href="http://php.net/manual/en/function.date.php" target="_blank">PHP Date Function</a>.<br /><b>class</b> ist der Name der CSS-Klasse, die auf den Wrapper angewendet wird.<br /><b>add</b> ist die Anzahl der Minuten, die der Zeit hinzugefügt werden. Es kann negativ sein. Das Hinzufügen hat keinen Einfluss auf die Ablaufzeit der Veranstaltung.<br /><b>expired:</b> Du kannst einen Text eingeben, der angezeigt wird, wenn der Countdown abläuft. Standard ist "Geschlossen".

Explanation:
@format is the time format of the output as defined in http://php.net/manual/en/function.date.php
e.g. "H:i T l", which is the default, will give something like 16:30 EST Mittwoch
Note: You may need to use date_default_timezone_set function if your timezone are not displayed correctly.
Tip: If you want to make a special format using your own characters, place \\ in front of them.
For example: "H:i \\B\\l\\a l", will give something like 16:30 Bla Mittwoch

@class is the name of css class that will be applied to the output. Default is null.

@add: How many minutes to add to the displayed time. Default is naturally zero. It can take negative values.
For example, if you have a "Doors open time" of 2 hours before the event, enter -120 (=>2 hours) here.

*/

class Eab_Events_NextEventShortcode {

	private $_data;

	/**
	 * Constructor
	 */	
	private function __construct () {
	}

	/**
	 * Run the Addon
	 *
	 */	
	public static function serve () {
		$me = new Eab_Events_NextEventShortcode;
		$me->_add_hooks();
	}

	/**
	 * Hooks 
	 *
	 */	
	private function _add_hooks () {
		add_shortcode( 'next_event', array($this, 'shortcode') );
	}

	public function shortcode ($args=array(), $content='') {
		$original_arguments = $args;
		$codec = new Eab_Codec_ArgumentsCodec;
		$args = $codec->parse_arguments($args, array(
			'format'=> 'H:i T l',
			'class'	=> '',
			'add' => 0,
			'expired'	=> __('Geschlossen', 'eab'),
			'legacy' => false,
			'category' => false,
			'categories' => false,
		));

		if (!empty($args['legacy'])) return $this->_legacy_shortcode($original_arguments);
		$class = !empty($args['class'])
			? 'class="' . sanitize_html_class($args['class']) . '"'
			: ''
		;
		$format = "<div {$class}>%s</div>";
		$output = '';

		$additional = 0;
		if (!empty($args['add']) && (int)$args['add']) {
			$additional = (int)$args['add'] * 60;
		}

		$query = $codec->get_query_args($args);

		$now = eab_current_time() + $additional;
		$events = Eab_CollectionFactory::get_upcoming_events($now, $query);
		$ret = array();
		foreach ($events as $event) {
			$ts = $event->get_start_timestamp();
			if ($ts < $now) continue;
			$ret[$ts] = $event;
		}
		ksort($ret);
		$next = reset($ret);
		if ($next) {
			$output = date_i18n($args['format'], $next->get_start_timestamp());
		} else {
			$output = !empty($args['expired'])
				? esc_html($args['expired'])
				: $content
			;
		}

		return sprintf($format, $output);
	}

	/**
	 * Generate shortcode
	 *
	 */	
	private function _legacy_shortcode( $atts ) {

		$atts    = shortcode_atts( array(
			'format'  => 'H:i T l',
			'class'   => '',
			'add'     => 0,
			'expired' => __('Geschlossen', 'eab'),
		), $atts );
		$format  = sanitize_text_field( $atts['format'] );
		$class   = sanitize_html_class( $atts['class'] );
		$add     = intval( $atts['add'] );
		$expired = sanitize_text_field( $atts['expired'] );
		
		if ( $class )
			$class = " class='".$class."'";
		
		global $wpdb;
		
		$result = $wpdb->get_row(
			"SELECT estart.*
			FROM $wpdb->posts wposts, $wpdb->postmeta estart, $wpdb->postmeta eend, $wpdb->postmeta estatus
			WHERE 
			wposts.ID=estart.post_id AND wposts.ID=eend.post_id AND wposts.ID=estatus.post_id 
			AND estart.meta_key='psource_event_start' AND estart.meta_value > DATE_ADD(UTC_TIMESTAMP(),INTERVAL ". ( current_time('timestamp') - time() ). " SECOND)
			AND eend.meta_key='psource_event_end' AND eend.meta_value > estart.meta_value
			AND estatus.meta_key='psource_event_status' AND estatus.meta_value <> 'closed'
			AND wposts.post_type='psource_event' AND wposts.post_status='publish'
			ORDER BY estart.meta_value ASC
			LIMIT 1
			");
		
		$secs = 0;
		if ( $add )
			$secs = 60 * (int)$add;
		
		if ( $result != null )
			$out = '<div'.$class.'>'. date( $format, strtotime( $result->meta_value, current_time('timestamp') ) + $secs ) . "</div>";
		else
			$out = '<div'.$class.'>'. $expired . "</div>";
			
		return $out;
	}
}

Eab_Events_NextEventShortcode::serve();