<?php
/**
 * Retain data by default; explicit site-owner opt-in removes plugin data only.
 *
 * @package EditorialScout
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
wp_clear_scheduled_hook( 'editorial_scout_check_feeds' );
if ( is_multisite() || ! defined( 'EDITORIAL_SCOUT_DELETE_DATA' ) || true !== EDITORIAL_SCOUT_DELETE_DATA ) {
	return;
}
global $wpdb;
foreach ( array( 'sources', 'items' ) as $kind ) {
	$table = $wpdb->prefix . 'editorial_scout_' . $kind;
	$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifiers.
}
delete_option( 'editorial_scout_schema' );
delete_option( 'editorial_scout_lock' );
