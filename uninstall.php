<?php
/**
 * Retain data by default; explicit site-owner opt-in removes plugin data only.
 *
 * @package EditorialScout
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
// WordPress owns all article content and media. Never delete posts, post meta,
// attachments/files, terms or relationships, even with operational cleanup enabled.
wp_unschedule_hook( 'editorial_scout_check_feeds' );
if ( is_multisite() || ! defined( 'EDITORIAL_SCOUT_DELETE_DATA' ) || true !== EDITORIAL_SCOUT_DELETE_DATA ) {
	return;
}
global $wpdb;
foreach ( array( 'writing', 'commissions', 'items', 'decisions', 'categories', 'sources' ) as $editorial_scout_kind ) {
	$editorial_scout_table = $wpdb->prefix . 'editorial_scout_' . $editorial_scout_kind;
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $editorial_scout_table ) );
}
delete_option( 'editorial_scout_schema' );
delete_option( 'editorial_scout_lock' );

delete_option( 'editorial_scout_scheduling' );
delete_metadata( 'user', 0, 'editorial_scout_manual_job', '', true );

foreach ( array( 'editorial_scout_profiles', 'editorial_scout_assessments', 'editorial_scout_openai', 'editorial_scout_ai_usage' ) as $editorial_scout_option ) {
	delete_option( $editorial_scout_option );
}
delete_metadata( 'user', 0, 'editorial_scout_learning', '', true );
delete_metadata( 'user', 0, 'editorial_scout_ai_preview', '', true );

delete_metadata( 'user', 0, 'editorial_scout_newsroom_ticket', '', true );
delete_metadata( 'user', 0, 'editorial_scout_newsroom_report', '', true );

delete_metadata( 'user', 0, 'editorial_scout_housekeeping', '', true );
