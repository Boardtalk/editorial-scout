<?php
/** A configured WordPress proxy must fail closed before sending feed traffic. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
define( 'WP_PROXY_HOST', '127.0.0.1' );
define( 'WP_PROXY_PORT', 9 );
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
$result = Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' );
if ( ! is_wp_error( $result ) || 'proxy_unsupported' !== $result->get_error_code() ) { exit( 1 ); }
echo "PASS: configured WordPress proxy fails closed\n";
