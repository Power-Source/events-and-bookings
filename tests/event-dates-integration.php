<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes on a development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
add_filter( 'pre_schedule_event', '__return_false' );
require_once dirname( __DIR__ ) . '/events-and-bookings.php';
remove_filter( 'pre_schedule_event', '__return_false' );
( new Eab_Taxonomies() )->register_post_type();
$check = static function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$id = wp_insert_post( array( 'post_type' => Eab_EventModel::POST_TYPE, 'post_status' => 'draft', 'post_title' => 'Event date cache regression' ), true );
if ( is_wp_error( $id ) || ! $id ) { WP_CLI::error( 'Could not create event fixture.' ); }
try {
	add_post_meta( $id, 'psource_event_start', '2035-04-10 18:00:00' );
	add_post_meta( $id, 'psource_event_end', '2035-04-10 20:00:00' );
	$event = new Eab_EventModel( get_post( $id ) );
	$check( '2035-04-10 18:00:00' === $event->get_start_date(), 'Initial native date read failed.' );
	update_post_meta( $id, 'psource_event_start', '2035-04-11 19:00:00' );
	update_post_meta( $id, 'psource_event_end', '2035-04-11 21:00:00' );
	$event = new Eab_EventModel( get_post( $id ) );
	$check( '2035-04-11 19:00:00' === $event->get_start_date() && '2035-04-11 21:00:00' === $event->get_end_date(),
		'Native model returned stale dates after an update.' );
	add_post_meta( $id, 'psource_event_start', '2035-04-12 19:00:00' );
	add_post_meta( $id, 'psource_event_end', '2035-04-12 21:00:00' );
	$event = new Eab_EventModel( get_post( $id ) );
	$check( array( '2035-04-11 19:00:00', '2035-04-12 19:00:00' ) === $event->get_start_dates() &&
		'2035-04-12 21:00:00' === $event->get_end_date( 1 ), 'Added slots were missing or lost their metadata order.' );
	delete_post_meta( $id, 'psource_event_start', '2035-04-11 19:00:00' );
	delete_post_meta( $id, 'psource_event_end', '2035-04-11 21:00:00' );
	$event = new Eab_EventModel( get_post( $id ) );
	$check( array( '2035-04-12 19:00:00' ) === $event->get_start_dates() &&
		array( '2035-04-12 21:00:00' ) === $event->get_end_dates(), 'Deleted slots remained cached.' );
	delete_post_meta( $id, 'psource_event_start' );
	$check( array() === ( new Eab_EventModel( get_post( $id ) ) )->get_start_dates(), 'Removing the last date left a phantom slot.' );
} finally {
	wp_delete_post( $id, true );
}
WP_CLI::success( 'Event dates: native model reflects updated, added and deleted slots in metadata order.' );
