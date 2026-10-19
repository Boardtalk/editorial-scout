<?php
/** Browser fixture and ephemeral cookies: values are consumed in memory, never logged. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$base = getenv( 'SCOUT_BROWSER_URL' ) ?: 'http://127.0.0.1:8099';
$_SERVER['HTTP_HOST'] = parse_url( $base, PHP_URL_HOST );
define( 'COOKIEHASH', md5( $base ) );
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin( 'editorial-scout/editorial-scout.php' );
update_option( 'siteurl', $base );
update_option( 'home', $base );
global $wpdb;
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Editorial_Scout_Store::table( 'items' ) ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Editorial_Scout_Store::table( 'sources' ) ) );
Editorial_Scout_Store::save( 0, 'Browser source', 'https://feeds.example.org/rss?' . str_repeat( 'long', 200 ), true );
$source = Editorial_Scout_Store::sources()[0];
global $wpdb;
$wpdb->insert( Editorial_Scout_Store::table( 'items' ), array( 'source_id' => $source->id, 'identity_hash' => hash( 'sha256', 'browser' ), 'title' => '<script>unsafe()</script> Original research', 'url' => 'https://feeds.example.org/article', 'discovered_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$cookies = array();
foreach ( array( AUTH_COOKIE => 'auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
	$cookies[] = array( 'name' => $name, 'value' => wp_generate_auth_cookie( 1, time() + 1800, $scheme ), 'url' => $base, 'httpOnly' => true, 'sameSite' => 'Lax' );
}
echo wp_json_encode( $cookies );
