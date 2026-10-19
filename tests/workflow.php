<?php
/** New workflow assertions run inside the isolated integration harness. */
if ( ! isset( $checks ) || 'cli' !== PHP_SAPI ) { exit( 1 ); }
$decisions = Editorial_Scout_Store::table( 'decisions' );
$categories = Editorial_Scout_Store::table( 'categories' );
$wpdb->query( "DELETE FROM $items" );
$wpdb->query( "DELETE FROM $sources" );
$wpdb->query( "DELETE FROM $decisions" );
$wpdb->query( "DELETE FROM $categories" );
scout_assert( Editorial_Scout_Store::save_category( 0, 'Science & Research' ), 'Category creation succeeds' );
$cat = Editorial_Scout_Store::categories()[0];
scout_assert( ! Editorial_Scout_Store::save_category( 0, 'Science & Research' ), 'Duplicate category rejected' );
scout_assert( Editorial_Scout_Store::save_category( $cat->id, 'Research' ), 'Category renamed with stable identity' );
scout_assert( Editorial_Scout_Store::save( 0, 'Source A', 'https://feeds.example.org/a', true, $cat->id ), 'Source category assignment succeeds' );
$a = Editorial_Scout_Store::sources()[0];
scout_assert( ! Editorial_Scout_Store::save( 0, 'Bad category', 'https://feeds.example.org/bad', true, 999999 ), 'Unknown category cannot be assigned' );
Editorial_Scout_Store::save( 0, 'Source B', 'https://feeds.example.org/b', true );
$b = Editorial_Scout_Store::sources()[0];
$make = static function( $url, $title = 'Research story' ) { return array( 'url' => $url, 'title' => $title, 'identity_hash' => hash( 'sha256', $url ), 'published_at' => null ); };
$link_a = 'https://feeds.example.org/article?item=1&utm_source=a';
$link_b = 'https://feeds.example.org/article?item=1&fbclid=b';
$hash = Editorial_Scout_Store::story_hash( $link_a );
scout_assert( $hash === Editorial_Scout_Store::story_hash( $link_b ), 'Known tracking parameters do not split cross-source story identity' );
scout_assert( $hash !== Editorial_Scout_Store::story_hash( 'https://feeds.example.org/article?item=2' ), 'Meaningful query parameters remain distinct' );
scout_assert( 1 === Editorial_Scout_Store::ingest( $a->id, array( $make( $link_a ) ) ) && 1 === Editorial_Scout_Store::ingest( $b->id, array( $make( $link_b ) ) ), 'Both source observations are stored' );
scout_assert( 1 === count( Editorial_Scout_Store::queue( 1 ) ) && 2 === count( Editorial_Scout_Store::coverage( $hash ) ), 'Cross-source queue groups the story without losing coverage' );
scout_assert( 1 === count( Editorial_Scout_Store::queue( 1, array( 'category' => $cat->id ) ) ) && ! Editorial_Scout_Store::queue( 1, array( 'source' => 999999 ) ), 'Category and source filters work' );
scout_assert( ! Editorial_Scout_Store::queue( 1, array( 'from' => '2099-01-01' ) ) && ! Editorial_Scout_Store::queue( 1, array( 'to' => '2000-01-01' ) ), 'Inclusive UTC discovery date filters constrain results' );
scout_assert( 1 === count( Editorial_Scout_Store::queue( 1, array( 'from' => '2026-99-99', 'status' => "x' OR 1=1" ) ) ), 'Invalid filters cannot inject SQL or crash date parsing' );
scout_assert( true === Editorial_Scout_Store::decide( $hash, 'shortlisted' ), 'Story can be shortlisted' );
scout_assert( 1 === count( Editorial_Scout_Store::queue( 1, array( 'category' => 'uncategorized' ) ) ), 'Uncategorized source filter is distinct from All categories' );
scout_assert( ! Editorial_Scout_Store::queue( 1, array( 'status' => 'new' ) ) && 1 === count( Editorial_Scout_Store::queue( 1, array( 'status' => 'shortlisted' ) ) ), 'Editorial status filter uses the shared story decision' );
$wpdb->query( "UPDATE $items SET discovered_at = '2000-01-01 00:00:00'" );
Editorial_Scout_Store::prune();
scout_assert( 2 === count( Editorial_Scout_Store::coverage( $hash ) ), 'Pruning never removes old shortlisted coverage' );
Editorial_Scout_Store::ingest( $a->id, array( $make( $link_a, 'Changed title' ) ) );
scout_assert( 'shortlisted' === Editorial_Scout_Store::queue( 1 )[0]->editorial_status, 'Routine rechecks preserve editorial choice' );
scout_assert( Editorial_Scout_Store::decide( $hash, 'dismissed' ), 'Story can be dismissed' );
Editorial_Scout_Store::prune();
scout_assert( 2 === count( Editorial_Scout_Store::coverage( $hash ) ) && 'dismissed' === Editorial_Scout_Store::queue( 1 )[0]->editorial_status, 'Dismissed decisions and source evidence survive pruning' );
$fail_probe = static function( $sql ) { return str_starts_with( $sql, 'SELECT i.id FROM' ) ? 'INTENTIONAL DECISION PROBE FAILURE' : $sql; };
$wpdb->suppress_errors( true );
add_filter( 'query', $fail_probe );
scout_assert( ! Editorial_Scout_Store::delete( $a->id ), 'Failed decision lookup cannot turn protected evidence into an ordinary deletion' );
remove_filter( 'query', $fail_probe );
$wpdb->suppress_errors( false );
scout_assert( 2 === count( Editorial_Scout_Store::coverage( $hash ) ), 'Decision probe failure leaves all source evidence intact' );
scout_assert( Editorial_Scout_Store::delete( $a->id ) && Editorial_Scout_Store::source( $a->id )->archived, 'Removing a source archives it when editorial evidence exists' );
scout_assert( 2 === count( Editorial_Scout_Store::coverage( $hash ) ), 'Source removal preserves all curated attribution' );
scout_assert( Editorial_Scout_Store::save( $b->id, 'Source B updated', 'https://feeds.example.org/new-b', true ), 'Changing a curated source URL starts a new source version' );
scout_assert( Editorial_Scout_Store::source( $b->id )->archived && 2 === count( Editorial_Scout_Store::coverage( $hash ) ), 'Old feed identity and decisions survive a URL change' );
scout_assert( Editorial_Scout_Store::delete_category( $cat->id ) && 0 === (int) Editorial_Scout_Store::source( $a->id )->category_id, 'Category deletion reassigns archived and active sources without dropping decisions' );
scout_assert( Editorial_Scout_Store::decide( $hash, 'new' ), 'Explicit return to New releases protection' );
Editorial_Scout_Store::prune();
scout_assert( ! Editorial_Scout_Store::coverage( $hash ) && ! Editorial_Scout_Store::source( $a->id ), 'Unreviewed expired history and empty archives are cleaned up' );
scout_assert( scout_error( Editorial_Scout_Store::decide( str_repeat( 'a', 64 ), 'shortlisted' ), 'not_found' ), 'Decisions require an existing story' );
scout_assert( scout_error( Editorial_Scout_Store::decide( "' OR 1=1", 'shortlisted' ), 'invalid_decision' ), 'Malformed story hashes are rejected' );
// Manual checking is isolated, serialized, and limited on the server, not by JavaScript.
$manual_source = Editorial_Scout_Store::sources()[0];
$manual_requests = 0;
$manual_mock = static function() use ( &$manual_requests, $rss ) { ++$manual_requests; return array( 'headers' => array( 'content-type' => 'application/rss+xml' ), 'body' => $rss, 'response' => array( 'code' => 200 ), 'cookies' => array() ); };
add_filter( 'pre_http_request', $manual_mock );
scout_assert( is_int( Editorial_Scout_Runner::check_now( $manual_source->id ) ), 'Check Now fetches an enabled source' );
scout_assert( scout_error( Editorial_Scout_Runner::check_now( $manual_source->id ), 'cooldown' ) && 1 === $manual_requests, 'Repeated Check Now cannot bypass persisted cooldown' );
Editorial_Scout_Store::save( $manual_source->id, 'Renamed after check', $manual_source->url, true );
scout_assert( scout_error( Editorial_Scout_Runner::check_now( $manual_source->id ), 'cooldown' ), 'Source name edits do not reset cooldown' );
$lock = Editorial_Scout_Runner::lock();
scout_assert( scout_error( Editorial_Scout_Runner::check_now( $manual_source->id ), 'busy' ), 'Manual checks respect the same cron/mutation lease' );
Editorial_Scout_Runner::unlock( $lock );
$wpdb->update( $sources, array( 'enabled' => 0 ), array( 'id' => $manual_source->id ) );
scout_assert( scout_error( Editorial_Scout_Runner::check_now( $manual_source->id ), 'disabled' ) && 1 === $manual_requests, 'Disabled sources cannot be manually fetched' );
remove_filter( 'pre_http_request', $manual_mock );
scout_assert( scout_error( Editorial_Scout_Runner::check_now( 999999 ), 'not_found' ), 'Missing source manual check fails safely' );
function scout_workflow_request( $case, $id, $action ) {
	$pipes = array();
	$process = proc_open( array( PHP_BINARY, __DIR__ . '/workflow-endpoint.php', $case, (string) $id, $action ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	scout_assert( 0 === proc_close( $process ) && '' === $error, 'Workflow request completed: ' . $action . '/' . $case );
	return $output;
}
foreach ( array( 'check', 'decision', 'category' ) as $action ) {
	foreach ( array( 'anonymous', 'editor', 'nonce', 'get' ) as $case ) {
		$out = scout_workflow_request( $case, $manual_source->id, $action );
		scout_assert( ! str_contains( $out, '|notice:' ) && str_contains( $out, '|no-request' ), 'Capability/nonce/method gate rejects ' . $action . '/' . $case );
	}
}
$wpdb->update( $sources, array( 'enabled' => 1, 'manual_after' => null, 'last_checked' => null ), array( 'id' => $manual_source->id ) );
$out = scout_workflow_request( 'admin', $manual_source->id, 'check' );
scout_assert( str_contains( $out, '|notice:fetch_failed|' ) && str_contains( $out, '|requested' ), 'Authorized Check Now request displays a safe transport failure outcome' );
$out = scout_workflow_request( 'admin', $manual_source->id, 'check' );
scout_assert( str_contains( $out, '|notice:cooldown|' ) && str_contains( $out, '|no-request' ), 'Cooldown persists across independent administrator POST processes' );
scout_assert( str_contains( scout_workflow_request( 'admin', '0', 'category' ), '|notice:saved|' ), 'Authorized category POST succeeds' );
$decision_url = 'https://feeds.example.org/decision-endpoint';
Editorial_Scout_Store::ingest( $manual_source->id, array( $make( $decision_url ) ) );
scout_assert( str_contains( scout_workflow_request( 'admin', Editorial_Scout_Store::story_hash( $decision_url ), 'decision' ), '|notice:saved|' ), 'Authorized shortlist POST succeeds' );
// The decision quota never evicts an existing decision to admit a new one.
$wpdb->query( "DELETE FROM $decisions" );
for ( $batch = 0; $batch < 10; ++$batch ) {
	$values = array();
	for ( $i = 0; $i < 100; ++$i ) { $values[] = $wpdb->prepare( '(%s,%s,%s)', hash( 'sha256', 'quota-' . ( $batch * 100 + $i ) ), 'dismissed', gmdate( 'Y-m-d H:i:s' ) ); }
	$wpdb->query( "INSERT INTO $decisions (identity_hash,status,updated_at) VALUES " . implode( ',', $values ) );
}
scout_assert( scout_error( Editorial_Scout_Store::decide( Editorial_Scout_Store::story_hash( $decision_url ), 'shortlisted' ), 'decision_limit' ), 'Editorial quota fails closed instead of evicting a previous decision' );
scout_assert( 1000 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $decisions" ), 'Quota failure retains all existing choices' );
$wpdb->query( "DELETE FROM $decisions" );
// SQL for a queue page is bounded and independent of row count (no attribution N+1).
$before_queries = $wpdb->num_queries;
Editorial_Scout_Store::queue( 1 );
scout_assert( $wpdb->num_queries - $before_queries <= 2, 'Queue rendering data requires a bounded query count' );

$workflow_retained_hash = Editorial_Scout_Store::story_hash( $decision_url );
scout_assert( Editorial_Scout_Store::decide( $workflow_retained_hash, 'dismissed' ), 'Decision created for evidence-cap regression' );

scout_assert( Editorial_Scout_Store::decide( $workflow_retained_hash, 'dismissed' ), 'Decision fixture retained for lifecycle verification' );

for ( $batch = 0; $batch < 100; ++$batch ) {
 $values = array();
 for ( $i = 0; $i < 100 && $batch * 100 + $i < 9999; ++$i ) {
  $url = $decision_url . '?utm_source=evidence-' . ( $batch * 100 + $i );
  $values[] = $wpdb->prepare( '(%d,%s,%s,%s,%s,%s)', $manual_source->id, hash( 'sha256', $url ), $workflow_retained_hash, 'Coverage evidence', $url, gmdate( 'Y-m-d H:i:s' ) );
 }
 $wpdb->query( "INSERT INTO $items (source_id,identity_hash,story_hash,title,url,discovered_at) VALUES " . implode( ',', $values ) );
}
scout_assert( scout_error( Editorial_Scout_Store::ingest( $manual_source->id, array( $make( $decision_url . '?utm_source=overflow' ) ) ), 'evidence_limit' ), 'Retained evidence quota rejects growth without evicting old coverage' );
scout_assert( 10000 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $items WHERE story_hash = %s", $workflow_retained_hash ) ), 'All 10,000 protected observations remain after quota rejection' );
Editorial_Scout_Store::decide( $workflow_retained_hash, 'new' );
Editorial_Scout_Store::prune();
scout_assert( 5000 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" ), 'Explicitly releasing protection restores the ordinary retention bound' );
scout_assert( Editorial_Scout_Store::decide( $workflow_retained_hash, 'dismissed' ), 'Remaining evidence can be retained again after releasing capacity' );
