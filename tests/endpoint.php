<?php
/** Execute one admin-post mutation in an isolated process (handler exits on redirect). */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
wp_set_current_user( 1 );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'source_id' => $argv[2] ?? 0, 'mode' => 'save', 'name' => '<b>Endpoint source</b>', 'url' => 'https://feeds.example.org/endpoint', 'enabled' => '1', '_wpnonce' => wp_create_nonce( 'editorial_scout_source' ) );
switch ( $argv[1] ?? '' ) {
	case 'create': break;
	case 'edit': $_POST['name'] = 'Edited source'; break;
	case 'invalid': $_POST['url'] = 'https://127.0.0.1/'; break;
	case 'array': $_POST['url'] = array( 'https://feeds.example.org/' ); break;
	case 'toggle': $_POST['mode'] = 'toggle'; break;
	case 'delete': $_POST['mode'] = 'delete'; break;
	case 'editor':
		wp_set_current_user( get_user_by( 'login', 'scout-test-editor' )->ID );
		$_POST['mode'] = 'delete';
		$_POST['_wpnonce'] = wp_create_nonce( 'editorial_scout_source' );
		break;
	default: exit( 2 );
}
$_REQUEST = $_POST;
// A successful handler reaches wp_safe_redirect; authorization and nonce failures do not.
add_filter( 'wp_redirect', function ( $location ) { echo wp_parse_url( $location, PHP_URL_QUERY ); return false; } );
Editorial_Scout_Admin::handle_source();
