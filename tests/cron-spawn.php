<?php
/** Verify ordinary traffic-triggered WP-Cron uses the standard loopback contract. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
define( 'DISABLE_WP_CRON', false );
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
delete_transient( 'doing_cron' );
$requested = false;
add_filter( 'pre_http_request', static function( $pre, $args, $url ) use ( &$requested ) {
 if ( str_contains( $url, '/wp-cron.php?doing_wp_cron=' ) ) {
  $requested = ! $args['blocking'];
  return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200 ), 'cookies' => array() );
 }
 return new WP_Error( 'fixture_no_external_requests' );
}, 10, 3 );
$result = _wp_cron();
if ( 1 !== $result || ! $requested ) { exit( 1 ); }
echo 'PASS: Standard traffic-triggered WP-Cron spawns its nonblocking loopback request.';
