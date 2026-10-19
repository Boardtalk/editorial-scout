<?php
/** Isolated form requests; test-only mock feed does not contact the Internet. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) ) { exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
wp_set_current_user( 1 );
$_SERVER['REQUEST_METHOD'] = 'POST';
$case = $argv[1];
$id = $argv[2] ?? '0';
$action = $argv[3] ?? 'check';
$kind = in_array( $action, array( 'decision', 'category' ), true ) ? $action : 'source';
$nonce_action = 'editorial_scout_' . $kind;
$_POST = array( 'source_id' => $id, 'mode' => 'check', 'story_hash' => $id, 'status' => 'shortlisted', 'name' => 'Endpoint category', 'category_id' => 0, '_wpnonce' => wp_create_nonce( $nonce_action ) );
if ( 'category' === $kind ) { $_POST['mode'] = 'save'; }
if ( 'editor' === $case ) { wp_set_current_user( get_user_by( 'login', 'scout-test-editor' )->ID ); $_POST['_wpnonce'] = wp_create_nonce( $nonce_action ); }
if ( 'anonymous' === $case ) { wp_set_current_user( 0 ); }
if ( 'nonce' === $case ) { $_POST['_wpnonce'] = 'invalid'; }
if ( 'get' === $case ) { $_SERVER['REQUEST_METHOD'] = 'GET'; }
$_REQUEST = $_POST;
$requested = false;
add_filter( 'pre_http_request', static function() use ( &$requested ) { $requested = true; return new WP_Error( 'fixture_transport_failure' ); } );
register_shutdown_function( static function() use ( &$requested ) { echo $requested ? '|requested' : '|no-request'; } );
add_filter( 'wp_redirect', static function( $location ) {
	$n = get_transient( 'editorial_scout_notice_' . get_current_user_id() );
	echo '|notice:' . ( $n['code'] ?? '' ) . '|';
	return false;
} );
call_user_func( array( 'Editorial_Scout_Admin', 'handle_' . $kind ) );
