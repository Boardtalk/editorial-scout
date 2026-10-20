<?php
/** Execute only against the disposable WordPress test installation. */
if ( '1' !== getenv( 'EDITORIAL_SCOUT_TESTS' ) || 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$completed = false;
register_shutdown_function( function () use ( &$completed ) { if ( ! $completed ) { exit( 1 ); } } );
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$checks = 0;
function scout_assert( $condition, $message ) {
	global $checks;
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo "PASS: $message\n";
}
function scout_error( $value, $code ) {
	return is_wp_error( $value ) && $code === $value->get_error_code();
}
$result = activate_plugin( 'editorial-scout/editorial-scout.php' );
scout_assert( ! is_wp_error( $result ), 'Plugin activates' );
// The plugin may have just been loaded after plugins_loaded.
Editorial_Scout_Admin::init();
global $wpdb;
$sources = Editorial_Scout_Store::table( 'sources' );
$items = Editorial_Scout_Store::table( 'items' );
$wpdb->query( "DELETE FROM $items" );
$wpdb->query( "DELETE FROM $sources" );
delete_option( 'editorial_scout_lock' );
require __DIR__ . '/workflow-upgrade.php';
scout_assert( Editorial_Scout_Store::SCHEMA === get_option( 'editorial_scout_schema' ), 'Schema installed' );
scout_assert( (bool) wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Cron scheduled' );
Editorial_Scout_Store::install();
scout_assert( Editorial_Scout_Store::SCHEMA === get_option( 'editorial_scout_schema' ), 'Schema upgrade is repeatable' );
foreach ( array( 'http://wordpress.org/feed/', 'https://127.0.0.1/', 'https://[::1]/', 'https://2130706433/', 'https://localhost/', 'https://a.local/', 'https://user:pass@wordpress.org/', 'https://wordpress.org:8443/', 'https://wordpress.org/#x', "https://wordpress.org/\r\nx", 'https://wordpress.org\\@127.0.0.1/' ) as $url ) {
	scout_assert( is_wp_error( Editorial_Scout_Feed::url( $url, false ) ), "Unsafe URL rejected: " . str_replace( array( "\r", "\n" ), '', $url ) );
}
foreach ( array( '127.0.0.1', '10.0.0.1', '192.168.1.1', '172.16.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '224.0.0.1', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1', '2002:7f00:1::' ) as $ip ) {
	scout_assert( ! Editorial_Scout_Feed::public_ip( $ip ), "Nonpublic/transition IP rejected: $ip" );
}
scout_assert( Editorial_Scout_Feed::public_ip( '8.8.8.8' ), 'Public IPv4 accepted' );
scout_assert( Editorial_Scout_Feed::public_ip( '2606:4700:4700::1111' ), 'Public IPv6 accepted' );
$rss = '<rss version="2.0"><channel><title>Science</title><link>https://wordpress.org/</link><description>Research</description><item><title>&lt;img src=x onerror=alert(1)&gt;Safe &amp; sound</title><link>https://wordpress.org/article</link><guid>id-1</guid><pubDate>Mon, 01 Jan 2024 12:00:00 GMT</pubDate></item><item><title>Unsafe</title><link>javascript:alert(1)</link></item></channel></rss>';
$parsed = Editorial_Scout_Feed::parse( $rss );
scout_assert( is_array( $parsed ) && 1 === count( $parsed ), 'RSS parsed and unsafe article link excluded' );
scout_assert( ! str_contains( $parsed[0]['title'], '<' ) && 'Safe & sound' === $parsed[0]['title'], 'Feed title reduced to plain text' );
scout_assert( '2024-01-01 12:00:00' === $parsed[0]['published_at'], 'Publication time normalized to UTC' );
$atom = '<feed xmlns="http://www.w3.org/2005/Atom"><title>Arts</title><id>urn:arts</id><updated>2024-01-01T00:00:00Z</updated><entry><title>Exhibition</title><id>urn:exhibition</id><updated>2024-01-01T00:00:00Z</updated><link href="https://wordpress.org/exhibition"/></entry></feed>';
scout_assert( 1 === count( Editorial_Scout_Feed::parse( $atom ) ), 'Atom parsed' );
foreach ( array( '', '<html><body>Hello</body></html>', '<rss><broken>', '<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss>&x;</rss>', str_repeat( 'x', Editorial_Scout_Feed::MAX_BYTES + 1 ), "\xff\xfe<\0r\0s\0s\0" ) as $xml ) {
	scout_assert( is_wp_error( Editorial_Scout_Feed::parse( $xml ) ), 'Malformed/non-feed/DTD/oversized/UTF-16 XML rejected' );
}
// Feed transport responses are deterministic; no external service dependency.
$http_code = 200;
$http_type = 'application/rss+xml; charset=UTF-8';
$http_body = $rss;
$encoding = '';
$requests = 0;
$mock = function ( $pre, $args, $url ) use ( &$http_code, &$http_type, &$http_body, &$encoding, &$requests ) {
	++$requests;
	scout_assert( 0 === $args['redirection'] && 10 === $args['timeout'] && $args['reject_unsafe_urls'] && $args['sslverify'] && Editorial_Scout_Feed::MAX_BYTES + 1 === $args['limit_response_size'], 'Safe bounded HTTP arguments' );
	return array( 'headers' => array( 'content-type' => $http_type, 'content-encoding' => $encoding, 'location' => 'http://169.254.169.254/' ), 'body' => $http_body, 'response' => array( 'code' => $http_code, 'message' => '' ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $mock, 10, 3 );
scout_assert( is_array( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' ) ), 'Validated public feed accepted' );
foreach ( array( 301, 302, 307, 308, 404, 500 ) as $code ) {
	$http_code = $code;
	scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' ), 'http_status' ), 'Redirect/error HTTP status rejected: ' . $code );
}
$http_code = 200;
$http_type = 'text/html';
scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' ), 'content_type' ), 'HTML response rejected' );
$http_type = array( 'application/rss+xml', 'text/html' );
scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' ), 'content_type' ), 'Ambiguous array content type rejected' );
$http_type = 'application/rss+xml';
$encoding = 'gzip';
scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' ), 'content_type' ), 'Encoded response rejected' );
$encoding = '';
scout_assert( ! has_action( 'http_api_curl' ) && ! has_action( 'requests-requests.before_request' ), 'Request-specific security hooks cleaned up' );
foreach ( array( 'https://private.example.org/rss', 'https://mixed.example.org/rss', 'https://unknown.example.org/rss' ) as $blocked ) {
	scout_assert( scout_error( Editorial_Scout_Feed::fetch( $blocked ), 'unsafe_host' ), 'Private, mixed public/private, or unresolved DNS rejected' );
}
$before = $requests;
scout_assert( is_wp_error( Editorial_Scout_Feed::fetch( 'https://127.0.0.1/' ) ) && $before === $requests, 'Unsafe destination rejected before HTTP' );
scout_assert( Editorial_Scout_Store::save( 0, 'Science', 'https://feeds.example.org/rss', true ), 'Source created' );
$source = Editorial_Scout_Store::sources()[0];
$wpdb->suppress_errors( true );
scout_assert( ! Editorial_Scout_Store::save( 0, 'Duplicate', $source->url, true ), 'Duplicate source rejected' );
$wpdb->suppress_errors( false );
$post_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
Editorial_Scout_Runner::run();
scout_assert( 1 === count( Editorial_Scout_Store::queue( 1 ) ), 'Cron discovers metadata' );
scout_assert( 'ok' === Editorial_Scout_Store::source( $source->id )->status, 'Successful fetch status saved' );
$wpdb->update( $sources, array( 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $source->id ) );
Editorial_Scout_Runner::run();
scout_assert( 1 === count( Editorial_Scout_Store::queue( 1 ) ), 'Repeated checks deduplicate article links' );
scout_assert( $post_count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ), 'Feed checks do not create posts' );
$http_code = 500;
$wpdb->update( $sources, array( 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $source->id ) );
Editorial_Scout_Runner::run();
$failed = Editorial_Scout_Store::source( $source->id );
scout_assert( 'http_status' === $failed->status && 1 === (int) $failed->failures && strtotime( $failed->next_check . ' UTC' ) >= time() + 7100, 'Failed feed backs off with bounded status code' );
$http_code = 200;
$wpdb->update( $sources, array( 'enabled' => 0, 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $source->id ) );
$before = $requests;
Editorial_Scout_Runner::run();
scout_assert( $before === $requests, 'Disabled source is not fetched' );
$lock = Editorial_Scout_Runner::lock();
scout_assert( $lock && ! Editorial_Scout_Runner::lock(), 'Worker lock excludes concurrent mutation' );
Editorial_Scout_Runner::unlock( array( 'token' => 'wrong', 'expires' => 0 ) );
scout_assert( ! Editorial_Scout_Runner::lock(), 'Wrong owner cannot release lock' );
Editorial_Scout_Runner::unlock( $lock );
update_option( 'editorial_scout_lock', array( 'token' => 'expired', 'expires' => time() - 1 ), false );
$lock = Editorial_Scout_Runner::lock();
scout_assert( (bool) $lock, 'Expired lock recovered' );
Editorial_Scout_Runner::unlock( $lock );
$wpdb->update( $items, array( 'discovered_at' => '2000-01-01 00:00:00' ), array( 'source_id' => $source->id ) );
Editorial_Scout_Store::prune();
scout_assert( 0 === count( Editorial_Scout_Store::queue( 1 ) ), 'Old discoveries pruned' );
scout_assert( Editorial_Scout_Store::save( $source->id, 'Renamed', 'https://feeds.example.org/rss', false ), 'Source edited' );
wp_set_current_user( 1 );
ob_start();
$_GET['tab'] = 'discoveries';
Editorial_Scout_Admin::render_page();
$_GET['tab'] = 'sources';
Editorial_Scout_Admin::render_page();
$html = ob_get_clean();
unset( $_GET['tab'] );
scout_assert( str_contains( $html, 'Source Manager' ) && str_contains( $html, 'Discovery queue' ) && str_contains( $html, '_wpnonce' ), 'Admin renders manager, queue and nonce-protected forms' );
// Convert wp_die into an exception to test authorization without terminating the runner.
$die = function () {
	return function ( $message ) { throw new RuntimeException( 'denied' ); };
};
add_filter( 'wp_die_handler', $die );
wp_set_current_user( 0 );
$denied = false;
try { Editorial_Scout_Admin::handle_source(); } catch ( RuntimeException $e ) { $denied = 'denied' === $e->getMessage(); }
scout_assert( $denied, 'Unauthenticated mutation denied' );
$user = wp_insert_user( array( 'user_login' => 'scout-test-editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
if ( is_wp_error( $user ) ) { $user = get_user_by( 'login', 'scout-test-editor' )->ID; }
wp_set_current_user( $user );
$denied = false;
try { Editorial_Scout_Admin::render_page(); } catch ( RuntimeException $e ) { $denied = true; }
scout_assert( $denied, 'Editor cannot view queue' );
wp_set_current_user( 1 );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'mode' => 'delete', 'source_id' => $source->id );
$_REQUEST = $_POST;
$denied = false;
try { Editorial_Scout_Admin::handle_source(); } catch ( RuntimeException $e ) { $denied = true; }
scout_assert( $denied && Editorial_Scout_Store::source( $source->id ), 'Administrator mutation without nonce rejected' );
remove_filter( 'wp_die_handler', $die );
function scout_endpoint( $mode, $id = 0 ) {
	$pipes = array();
	$process = proc_open( array( PHP_BINARY, __DIR__ . '/endpoint.php', $mode, (string) $id ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	$code = proc_close( $process );
	scout_assert( 0 === $code && '' === $err, 'Endpoint process completed: ' . $mode );
	return $out;
}
scout_assert( str_contains( scout_endpoint( 'create' ), 'result=saved' ), 'Nonce-protected administrator source creation succeeds' );
$endpoint = Editorial_Scout_Store::sources()[0];
scout_assert( 'Endpoint source' === $endpoint->name, 'Endpoint sanitizes source name' );
scout_assert( str_contains( scout_endpoint( 'edit', $endpoint->id ), 'result=saved' ) && 'Edited source' === Editorial_Scout_Store::source( $endpoint->id )->name, 'Nonce-protected editing succeeds' );
scout_assert( str_contains( scout_endpoint( 'invalid', $endpoint->id ), 'result=failed' ) && 'https://feeds.example.org/endpoint' === Editorial_Scout_Store::source( $endpoint->id )->url, 'Unsafe URL edit leaves existing source intact' );
scout_assert( str_contains( scout_endpoint( 'array', $endpoint->id ), 'result=failed' ), 'Malformed array input rejected without PHP errors' );
scout_assert( str_contains( scout_endpoint( 'toggle', $endpoint->id ), 'result=saved' ) && ! Editorial_Scout_Store::source( $endpoint->id )->enabled, 'Disable endpoint succeeds' );
scout_assert( ! str_contains( scout_endpoint( 'editor', $endpoint->id ), 'result=saved' ) && Editorial_Scout_Store::source( $endpoint->id ), 'Editor mutation with a valid nonce is denied' );
scout_assert( str_contains( scout_endpoint( 'delete', $endpoint->id ), 'result=saved' ) && ! Editorial_Scout_Store::source( $endpoint->id ), 'Delete endpoint succeeds' );
// Real database limits, batch size, bounded per-feed ingestion, and paging.
$wpdb->update( $sources, array( 'enabled' => 1 ), array( 'id' => $source->id ) );
for ( $i = 0; $i < 49; ++$i ) {
	scout_assert( Editorial_Scout_Store::save( 0, 'Bounded source ' . $i, 'https://feeds.example.org/feed-' . $i, true ), 'Source accepted within cap' );
}
scout_assert( ! Editorial_Scout_Store::save( 0, 'Overflow', 'https://feeds.example.org/overflow', true ), '51st source rejected' );
$wpdb->query( "UPDATE $sources SET enabled = 1, next_check = '2000-01-01 00:00:00'" );
$before = $requests;
Editorial_Scout_Runner::run();
scout_assert( 5 === $requests - $before, 'At most five due sources fetched per cron invocation' );
$many = '<rss version="2.0"><channel><title>Many</title><link>https://feeds.example.org/</link><description>Many</description>';
for ( $i = 0; $i < 60; ++$i ) { $many .= '<item><title>Item ' . $i . '</title><link>https://feeds.example.org/article-' . $i . '</link></item>'; }
$many .= '</channel></rss>';
scout_assert( 50 === count( Editorial_Scout_Feed::parse( $many ) ), 'Only 50 items extracted per feed' );
$wpdb->query( "DELETE FROM $items" );
// Bulk SQL fixture keeps the retention test fast while exercising the real unique index.
for ( $batch = 0; $batch < 51; ++$batch ) {
	$values = array();
	for ( $i = 0; $i < 100; ++$i ) {
		$n = $batch * 100 + $i;
		$values[] = $wpdb->prepare( '(%d,%s,%s,%s,%s)', $source->id, hash( 'sha256', (string) $n ), 'Item ' . $n, 'https://feeds.example.org/item-' . $n, gmdate( 'Y-m-d H:i:s' ) );
	}
	$wpdb->query( "INSERT INTO $items (source_id,identity_hash,title,url,discovered_at) VALUES " . implode( ',', $values ) );
}
Editorial_Scout_Store::prune();
scout_assert( 5000 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" ), 'Queue retention capped at 5,000 rows' );
scout_assert( 51 === count( Editorial_Scout_Store::queue( 1 ) ) && 50 === count( Editorial_Scout_Store::queue( 100 ) ), 'Queue paginates with a next-page sentinel' );
$wpdb->update( $sources, array( 'name' => '<script>alert(1)</script>' ), array( 'id' => $source->id ) );
$wpdb->query( "UPDATE $items SET title = '<script>alert(1)</script>'" );
wp_set_current_user( 1 );
ob_start(); $_GET['tab'] = 'sources'; Editorial_Scout_Admin::render_page(); $_GET['tab'] = 'discoveries'; Editorial_Scout_Admin::render_page(); $escaped = ob_get_clean(); unset( $_GET['tab'] );
scout_assert( ! str_contains( $escaped, '<script>' ) && str_contains( $escaped, '&lt;script&gt;' ), 'Source names and article titles escaped at output' );
Editorial_Scout_Store::delete( $source->id );
scout_assert( ! Editorial_Scout_Store::source( $source->id ) && ! Editorial_Scout_Store::queue( 1 ), 'Source deletion removes its history' );
remove_filter( 'pre_http_request', $mock );
if ( file_exists( '/tmp/scout-cert.pem' ) ) {
	scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' ), 'fetch_failed' ), 'Untrusted TLS certificate rejected before test CA is configured' );
	$trust = function ( $args, $url ) {
		if ( str_starts_with( $url, 'https://feeds.example.org/' ) ) { $args['sslcertificates'] = '/tmp/scout-cert.pem'; }
		return $args;
	};
	add_filter( 'http_request_args', $trust, 10, 2 );
	$live = Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' );
	scout_assert( is_array( $live ) && 'Pinned TLS feed' === $live[0]['title'], 'Real WordPress HTTPS request reaches pinned fixture with TLS verification' );
	scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/redirect' ), 'http_status' ), 'Real transport rejects redirect to loopback' );
	$rebind = function ( &$url ) {
		if ( 'https://feeds.example.org/rss' === $url ) { touch( '/tmp/scout-dns-private' ); }
	};
	add_action( 'requests-requests.before_request', $rebind, 1, 1 );
	try {
		$live = Editorial_Scout_Feed::fetch( 'https://feeds.example.org/rss' );
		scout_assert( is_array( $live ) && 'Pinned TLS feed' === $live[0]['title'], 'DNS rebinding after validation cannot change the pinned connection address' );
	} finally {
		remove_action( 'requests-requests.before_request', $rebind, 1 );
		@unlink( '/tmp/scout-dns-private' );
	}
	foreach ( array( '/oversized' => 'invalid_xml', '/chunked' => 'invalid_xml', '/compressed' => 'content_type', '/html' => 'content_type', '/redirect-public' => 'http_status' ) as $path => $code ) {
		scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org' . $path ), $code ), 'Real TLS transport rejects ' . $path );
	}
	scout_assert( ! str_contains( file_get_contents( '/tmp/scout-tls-requests' ), '/sink' ), 'Neither public nor private redirect destination receives a request' );
	// A public AAAA answer must not make an otherwise working dual-stack feed depend on IPv6.
	$trust_dual = function ( $args ) { $args['sslcertificates'] = '/tmp/scout-cert.pem'; return $args; };
	add_filter( 'http_request_args', $trust_dual );
	scout_assert( is_array( Editorial_Scout_Feed::fetch( 'https://dual.example.org/rss' ) ), 'Dual-stack feed selects usable pinned IPv4 despite an unreachable public AAAA address' );
	remove_filter( 'http_request_args', $trust_dual );
	$started = microtime( true );
	scout_assert( scout_error( Editorial_Scout_Feed::fetch( 'https://feeds.example.org/slow' ), 'fetch_failed' ) && microtime( true ) - $started < 12, 'Slow real HTTPS response is bounded by the ten-second transport deadline' );
	remove_filter( 'http_request_args', $trust );
} else {
	throw new RuntimeException( 'TLS fixture is required; run tests/run.sh' );
}
require __DIR__ . '/hardening.php';
require __DIR__ . '/workflow.php';
require __DIR__ . '/scheduling.php';
$deactivation_lease = Editorial_Scout_Runner::lock();
deactivate_plugins( 'editorial-scout/editorial-scout.php' );
scout_assert( ! Editorial_Scout_Runner::renew( $deactivation_lease ), 'Deactivation invalidates an in-flight worker lease before its next write' );
scout_assert( ! wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Deactivation clears cron' );
scout_assert( Editorial_Scout_Store::SCHEMA === get_option( 'editorial_scout_schema' ), 'Deactivation retains data' );
uninstall_plugin( 'editorial-scout/editorial-scout.php' );
scout_assert( Editorial_Scout_Store::SCHEMA === get_option( 'editorial_scout_schema' ), 'Uninstall retains data by default' );
scout_assert( 'dismissed' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE identity_hash = %s', Editorial_Scout_Store::table( 'decisions' ), $workflow_retained_hash ) ), 'Default uninstall retains editorial decisions' );
define( 'EDITORIAL_SCOUT_DELETE_DATA', true );
// Run the opt-in path in this disposable installation only.
include plugin_dir_path( EDITORIAL_SCOUT_PLUGIN_FILE ) . 'uninstall.php';
scout_assert( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sources ) ) ) && false === get_option( 'editorial_scout_schema' ), 'Opt-in uninstall removes plugin tables and options' );
foreach ( array( 'decisions', 'categories' ) as $kind ) {
 scout_assert( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( Editorial_Scout_Store::table( $kind ) ) ) ), 'Explicit opt-in removes workflow table: ' . $kind );
}
scout_assert( false === get_option( Editorial_Scout_Scheduling::OPTION ), 'Opt-in uninstall removes scheduling configuration' );
scout_assert( '' === get_user_meta( 1, Editorial_Scout_Scheduling::JOB, true ), 'Opt-in uninstall removes manual progress' );
$completed = true;
echo "Completed $checks assertions.\n";
