<?php
/** Additional regressions; loaded by the gated integration runner. */
if ( ! isset( $checks ) || 'cli' !== PHP_SAPI ) { exit( 1 ); }
foreach ( array( '192.0.2.1', '198.18.0.1', '203.0.113.5', '255.255.255.255', '64:ff9b::7f00:1', '2001::1', '2001:20::1', '2001:db8::1', '3fff::1', '::ffff:8.8.8.8' ) as $ip ) {
	scout_assert( ! Editorial_Scout_Feed::public_ip( $ip ), 'Special/documentation/translation address rejected: ' . $ip );
}
scout_assert( Editorial_Scout_Feed::public_ip( '2001:4860:4860::8888' ), 'Ordinary public 2001 IPv6 is allowed' );
// Stale negative and positive caches must never steal an existing database lease.
$lease = Editorial_Scout_Runner::lock();
wp_cache_set( 'notoptions', array( 'editorial_scout_lock' => true ), 'options' );
scout_assert( ! Editorial_Scout_Runner::lock(), 'Negative option cache cannot overwrite a live lease' );
wp_cache_set( 'editorial_scout_lock', array( 'token' => 'cached-stale', 'expires' => 1 ), 'options' );
scout_assert( ! Editorial_Scout_Runner::lock(), 'Expired cached lease cannot displace live SQL ownership' );
scout_assert( Editorial_Scout_Runner::renew( $lease ), 'Owner can renew live lease' );
$old = $lease;
$expired = $lease;
$expired['expires'] = time() - 2;
$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $expired ) ), array( 'option_name' => 'editorial_scout_lock' ) );
$replacement = Editorial_Scout_Runner::lock();
scout_assert( $replacement && $replacement['token'] !== $old['token'], 'Expired SQL lease is atomically replaced' );
scout_assert( ! Editorial_Scout_Runner::renew( $old ), 'Previous owner cannot renew replacement lease' );
Editorial_Scout_Runner::unlock( $old );
scout_assert( ! Editorial_Scout_Runner::lock(), 'Previous owner cannot release replacement lease' );
Editorial_Scout_Runner::unlock( $replacement );
// Start independent PHP processes together; caches and DB connections are distinct.
@unlink( '/tmp/scout-release-contenders' );
$workers = array();
for ( $i = 0; $i < 8; ++$i ) {
	$pipes = array();
	$process = proc_open( array( PHP_BINARY, __DIR__ . '/lock-contender.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$workers[] = array( $process, $pipes );
}
// Each contender reports before the winner releases: all attempts overlap.
$wins = 0;
foreach ( $workers as $worker ) {
	$output = trim( fgets( $worker[1][1] ) );
	$worker_outputs[] = $output;
}
touch( '/tmp/scout-release-contenders' );
foreach ( $workers as $index => $worker ) {
	$error = stream_get_contents( $worker[1][2] );
	fclose( $worker[1][1] ); fclose( $worker[1][2] );
	scout_assert( 0 === proc_close( $worker[0] ) && '' === $error, 'Independent lock contender completed' );
	$wins += (int) ( 'acquired' === $worker_outputs[ $index ] );
}
scout_assert( 1 === $wins, 'Exactly one of eight simultaneous cache-stale contenders owns the lock' );
// Verify interrupted/failed migration does not claim success, and can be retried.
$wpdb->query( "ALTER TABLE $items DROP INDEX source_identity" );
scout_assert( ! Editorial_Scout_Store::schema_ready(), 'Missing unique index invalidates schema readiness' );
$block_migration = function ( $sql ) use ( $items ) {
	return str_starts_with( $sql, "ALTER TABLE $items ADD UNIQUE" ) ? 'INTENTIONAL SCOUT TEST SQL FAILURE' : $sql;
};
$wpdb->suppress_errors( true );
add_filter( 'query', $block_migration );
scout_assert( ! Editorial_Scout_Store::install() && false === get_option( 'editorial_scout_schema' ), 'Failed index migration does not advance schema version' );
remove_filter( 'query', $block_migration );
$wpdb->suppress_errors( false );
scout_assert( Editorial_Scout_Store::install() && Editorial_Scout_Store::schema_ready(), 'Retry repairs the index and validates schema' );
$wpdb->query( "ALTER TABLE $sources DROP COLUMN status" );
update_option( 'editorial_scout_schema', '1', false );
scout_assert( Editorial_Scout_Store::install(), 'Migration repairs a missing column from schema 1' );
scout_assert( Editorial_Scout_Store::save( 0, 'Atomic test', 'https://feeds.example.org/atomic', true ), 'Atomic-storage test source created' );
$atomic = Editorial_Scout_Store::sources()[0];
$wpdb->insert( $items, array( 'source_id' => $atomic->id, 'identity_hash' => hash( 'sha256', 'atomic' ), 'title' => 'Existing history', 'url' => 'https://feeds.example.org/atomic-article', 'discovered_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$break_delete = function ( $sql ) use ( $items ) { return str_starts_with( $sql, "DELETE FROM `$items`" ) ? 'INTENTIONAL SCOUT TEST SQL FAILURE' : $sql; };
$wpdb->suppress_errors( true );
add_filter( 'query', $break_delete );
scout_assert( ! Editorial_Scout_Store::save( $atomic->id, 'Changed', 'https://feeds.example.org/changed', true ), 'History deletion failure makes source URL change fail' );
remove_filter( 'query', $break_delete );
$wpdb->suppress_errors( false );
scout_assert( $atomic->url === Editorial_Scout_Store::source( $atomic->id )->url && 1 === count( Editorial_Scout_Store::queue( 1 ) ), 'Failed source URL change rolls back source and history' );
$break_source_delete = function ( $sql ) use ( $sources ) { return str_starts_with( $sql, "DELETE FROM `$sources`" ) ? 'INTENTIONAL SCOUT TEST SQL FAILURE' : $sql; };
$wpdb->suppress_errors( true );
add_filter( 'query', $break_source_delete );
scout_assert( ! Editorial_Scout_Store::delete( $atomic->id ), 'Source deletion failure is reported' );
remove_filter( 'query', $break_source_delete );
$wpdb->suppress_errors( false );
scout_assert( Editorial_Scout_Store::source( $atomic->id ) && 1 === count( Editorial_Scout_Store::queue( 1 ) ), 'Failed source deletion restores previously deleted history' );
// Retention follows discovery time, including backfilled timestamps, rather than insertion IDs.
for ( $batch = 0; $batch < 50; ++$batch ) {
	$values = array();
	for ( $i = 0; $i < 100; ++$i ) {
		$key = 'backfilled-' . ( $batch * 100 + $i );
		$values[] = $wpdb->prepare( '(%d,%s,%s,%s,%s)', $atomic->id, hash( 'sha256', $key ), $key, 'https://feeds.example.org/' . $key, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
	}
	$wpdb->query( "INSERT INTO $items (source_id,identity_hash,title,url,discovered_at) VALUES " . implode( ',', $values ) );
}
Editorial_Scout_Store::prune();
scout_assert( 5000 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" ) && 'Existing history' === Editorial_Scout_Store::queue( 1 )[0]->title, 'Retention preserves newest discovery even when it has the lowest insertion ID' );
scout_assert( Editorial_Scout_Store::delete( $atomic->id ), 'Atomic source deletion succeeds after recovery' );
// Simulate a crashed/unexpected transport and then a lost lease mid-request.
$wpdb->query( "DELETE FROM $sources" );
Editorial_Scout_Store::save( 0, 'Failure recovery', 'https://feeds.example.org/recovery', true );
$recovery = Editorial_Scout_Store::sources()[0];
$crash = function () { throw new RuntimeException( 'Deliberate transport failure' ); };
add_filter( 'pre_http_request', $crash );
Editorial_Scout_Runner::run();
remove_filter( 'pre_http_request', $crash );
scout_assert( 'processing_failed' === Editorial_Scout_Store::source( $recovery->id )->status, 'Unexpected transport exception records backoff without killing worker' );
$lease = Editorial_Scout_Runner::lock();
scout_assert( (bool) $lease, 'Exception recovery releases worker lease' );
Editorial_Scout_Runner::unlock( $lease );
$wpdb->update( $sources, array( 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $recovery->id ) );
$steal = function () use ( $wpdb, $rss ) {
	$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( array( 'token' => 'new-owner', 'expires' => time() + 900 ) ) ), array( 'option_name' => 'editorial_scout_lock' ) );
	return array( 'headers' => array( 'content-type' => 'application/rss+xml' ), 'body' => $rss, 'response' => array( 'code' => 200 ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $steal );
Editorial_Scout_Runner::run();
remove_filter( 'pre_http_request', $steal );
scout_assert( ! Editorial_Scout_Store::queue( 1 ) && 'processing_failed' === Editorial_Scout_Store::source( $recovery->id )->status, 'Worker which loses its lease during fetch writes neither discoveries nor status' );
scout_assert( ! Editorial_Scout_Runner::lock(), 'Lost worker does not remove its successor lease' );
delete_option( 'editorial_scout_lock' );
Editorial_Scout_Store::delete( $recovery->id );

$pipes = array();
$process = proc_open( array( PHP_BINARY, __DIR__ . '/proxy.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
$output = stream_get_contents( $pipes[1] );
$error = stream_get_contents( $pipes[2] );
fclose( $pipes[1] ); fclose( $pipes[2] );
scout_assert( 0 === proc_close( $process ) && '' === $error && str_contains( $output, 'PASS:' ), 'Configured WordPress HTTP proxy fails closed in a fresh process' );
