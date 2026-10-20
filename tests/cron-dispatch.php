<?php
/** Execute WordPress's real cron entry point, independent of any hosting panel. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
add_filter( 'http_request_args', static function( $args ) { $args['sslcertificates'] = '/tmp/scout-cert.pem'; return $args; } );
require ABSPATH . 'wp-cron.php';
