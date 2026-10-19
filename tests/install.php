<?php
/** Install only the disposable database created by tests/compose.yaml. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$completed = false;
register_shutdown_function( function () use ( &$completed ) { if ( ! $completed ) { exit( 1 ); } } );
$_SERVER['HTTP_HOST'] = 'localhost';
define( 'WP_INSTALLING', true );
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
	wp_install( 'Editorial Scout tests', 'scout', 'scout@example.org', false, '', wp_generate_password( 32 ) );
}
$completed = is_blog_installed();
echo $completed ? "Test WordPress installed.\n" : "Installation failed.\n";
