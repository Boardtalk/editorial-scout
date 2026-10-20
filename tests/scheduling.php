<?php
/** Scheduling tests use the disposable database, real WP event APIs and fixed feeds. */
if ( ! isset( $checks ) || 'cli' !== PHP_SAPI ) { exit( 1 ); }
wp_set_current_user( 1 );
$option = Editorial_Scout_Scheduling::OPTION;
$before_decisions = $wpdb->get_results( "SELECT * FROM $decisions ORDER BY identity_hash", ARRAY_A );
$before_coverage = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" );
$wpdb->query( "UPDATE $sources SET enabled = 0" );
Editorial_Scout_Store::save( 0, 'Schedule fixture', 'https://feeds.example.org/rss', true );
$scheduled = Editorial_Scout_Store::sources()[0];
$id = (int) $scheduled->id;
wp_schedule_event( time() + 600, 'daily', 'scout_unrelated' );
delete_option( $option );
scout_assert( 'storage_failed' === Editorial_Scout_Scheduling::health()['code'], 'Failed or absent schedule initialization is not hidden by indefinite grace' );
do_action( Editorial_Scout_Runner::HOOK );
scout_assert( false === get_option( $option ) && ! Editorial_Scout_Store::source( $id )->last_checked, 'Uninitialized automatic callback fails closed without fetching or manufacturing a heartbeat' );
scout_assert( Editorial_Scout_Scheduling::ensure() && 'hourly' === Editorial_Scout_Scheduling::mode(), 'Upgrade defaults existing installations to hourly without table migration' );
scout_assert( $before_decisions === $wpdb->get_results( "SELECT * FROM $decisions ORDER BY identity_hash", ARRAY_A ) && $before_coverage === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" ), 'Scheduling upgrade preserves all editorial decisions and observations' );
scout_assert( Editorial_Scout_Scheduling::ensure(), 'Scheduling initialization is repeatable' );
$requests = 0;
$mock_schedule = static function() use ( &$requests, $rss ) { ++$requests; return array( 'headers' => array( 'content-type' => 'application/rss+xml' ), 'body' => $rss, 'response' => array( 'code' => 200 ), 'cookies' => array() ); };
add_filter( 'pre_http_request', $mock_schedule );
foreach ( array( 'hourly' => 3600, 'daily' => 86400, 'twice' => 302400, 'weekly' => 604800, 'manual' => 3600 ) as $mode => $seconds ) {
 scout_assert( true === Editorial_Scout_Scheduling::configure( $mode ) && $seconds === Editorial_Scout_Scheduling::interval(), 'Mode configured with documented interval: ' . $mode );
 scout_assert( ( 'manual' === $mode ) === ! (bool) wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Only Manual Only removes automatic worker: ' . $mode );
 $wpdb->update( $sources, array( 'last_checked' => null, 'manual_after' => null, 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $id ) );
 $count = $requests;
 do_action( Editorial_Scout_Runner::HOOK );
 if ( 'manual' === $mode ) {
  scout_assert( $count === $requests, 'Manual Only refuses even directly invoked automatic callback' );
 } else {
  $s = Editorial_Scout_Store::source( $id );
  scout_assert( $count + 1 === $requests && abs( strtotime( $s->next_check . ' UTC' ) - time() - $seconds ) < 5, 'Actual cron callback uses selected per-source interval: ' . $mode );
  do_action( Editorial_Scout_Runner::HOOK );
  scout_assert( $count + 1 === $requests, 'Five-minute server cron cannot refetch a source before its longer interval: ' . $mode );
 }
 $wpdb->update( $sources, array( 'last_checked' => null, 'manual_after' => null ), array( 'id' => $id ) );
 scout_assert( is_int( Editorial_Scout_Runner::check_now( $id ) ), 'Independent manual check works in mode: ' . $mode );
 scout_assert( scout_error( Editorial_Scout_Runner::check_now( $id ), 'cooldown' ), 'Persisted cooldown applies in mode: ' . $mode );
}
scout_assert( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, 'Real external-cron configuration is used in integration tests' );
scout_assert( 'manual' === Editorial_Scout_Scheduling::health()['code'], 'Manual Only is healthy without cron heartbeat' );
scout_assert( scout_error( Editorial_Scout_Scheduling::configure( 'evil-hourly' ), 'invalid_schedule' ), 'Unknown schedule cannot register arbitrary recurrence' );
Editorial_Scout_Scheduling::configure( 'hourly' );
$lease = Editorial_Scout_Runner::lock();
scout_assert( scout_error( Editorial_Scout_Scheduling::configure( 'daily' ), 'busy' ) && scout_error( Editorial_Scout_Scheduling::manual_batch(), 'busy' ), 'Configuration and batch share source/cron lease' );
$count = $requests; do_action( Editorial_Scout_Runner::HOOK );
scout_assert( $count === $requests, 'Held lease prevents automatic work' );
Editorial_Scout_Runner::unlock( $lease );
Editorial_Scout_Scheduling::configure( 'weekly' );
$last = Editorial_Scout_Store::source( $id )->last_checked;
Editorial_Scout_Scheduling::configure( 'daily' );
scout_assert( strtotime( Editorial_Scout_Store::source( $id )->next_check . ' UTC' ) === strtotime( $last . ' UTC' ) + DAY_IN_SECONDS, 'Rescheduling rebases due time from actual last check' );
$wpdb->update( $sources, array( 'last_checked' => null, 'manual_after' => null ), array( 'id' => $id ) );
$stale_state = Editorial_Scout_Scheduling::state(); $stale_state['mode'] = 'hourly';
wp_cache_set( $option, $stale_state, 'options' );
scout_assert( is_int( Editorial_Scout_Runner::check_now( $id ) ) && strtotime( Editorial_Scout_Store::source( $id )->next_check . ' UTC' ) >= time() + 86390, 'Manual path refreshes stale schedule cache after acquiring ownership' );
$due_before = Editorial_Scout_Store::source( $id )->next_check;
$fail_setting = static function( $sql ) { return str_starts_with( $sql, 'UPDATE' ) && str_contains( $sql, 'editorial_scout_scheduling' ) ? 'INTENTIONAL SCHEDULE SAVE FAILURE' : $sql; };
$wpdb->suppress_errors( true ); add_filter( 'query', $fail_setting );
scout_assert( scout_error( Editorial_Scout_Scheduling::configure( 'weekly' ), 'storage_failed' ), 'Failed configuration write reports database error' );
remove_filter( 'query', $fail_setting ); $wpdb->suppress_errors( false );
scout_assert( 'daily' === Editorial_Scout_Scheduling::mode() && $due_before === Editorial_Scout_Store::source( $id )->next_check, 'Failed configuration transaction restores mode, due dates and cache consistency' );
// Test the actual registered callback and verified TLS transport without HTTP mocks.
remove_filter( 'pre_http_request', $mock_schedule );
$trust_schedule = static function( $args ) { $args['sslcertificates'] = '/tmp/scout-cert.pem'; return $args; };
add_filter( 'http_request_args', $trust_schedule );
$wpdb->update( $sources, array( 'last_checked' => null, 'manual_after' => null, 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $id ) );
do_action( Editorial_Scout_Runner::HOOK );
scout_assert( 'ok' === Editorial_Scout_Store::source( $id )->status && ! empty( Editorial_Scout_Scheduling::state()['finished'] ), 'External cron callback performs real pinned TLS fetch and writes execution heartbeat' );
remove_filter( 'http_request_args', $trust_schedule );
wp_unschedule_hook( Editorial_Scout_Runner::HOOK );
wp_schedule_event( time() - 5, 'editorial_scout_five_minutes', Editorial_Scout_Runner::HOOK );
$wpdb->update( $sources, array( 'last_checked' => null, 'manual_after' => null, 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $id ) );
delete_transient( 'doing_cron' );
$spawn = proc_open( array( PHP_BINARY, __DIR__ . '/cron-spawn.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
$spawn_out = stream_get_contents( $pipes[1] ); $spawn_error = stream_get_contents( $pipes[2] );
fclose( $pipes[1] ); fclose( $pipes[2] );
scout_assert( 0 === proc_close( $spawn ) && '' === $spawn_error && str_contains( $spawn_out, 'PASS:' ), 'Standard WP-Cron with page triggering enabled spawns the generic nonblocking loopback' );
delete_transient( 'doing_cron' );
$dispatch = proc_open( array( PHP_BINARY, __DIR__ . '/cron-dispatch.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
$dispatch_out = stream_get_contents( $pipes[1] ); $dispatch_error = stream_get_contents( $pipes[2] );
fclose( $pipes[1] ); fclose( $pipes[2] );
scout_assert( 0 === proc_close( $dispatch ) && '' === $dispatch_error, 'Generic WordPress wp-cron.php entry point runs without a hosting panel' );
Editorial_Scout_Scheduling::fresh_state();
wp_cache_delete( 'cron', 'options' ); wp_cache_delete( 'alloptions', 'options' );
scout_assert( Editorial_Scout_Store::source( $id )->last_checked && 'ok' === Editorial_Scout_Store::source( $id )->status && wp_next_scheduled( Editorial_Scout_Runner::HOOK ) > time(), 'Real cron dispatcher executes due TLS feed work and reschedules its recurring event' );

add_filter( 'pre_http_request', $mock_schedule );
$now = time();
$state = Editorial_Scout_Scheduling::state();
$state['since'] = $now; unset( $state['finished'], $state['started'] ); $state['running'] = false; update_option( $option, $state, false );
scout_assert( 'grace' === Editorial_Scout_Scheduling::health( $now + 3590 )['code'], 'First automatic run receives a one-hour grace period' );
$wpdb->update( $sources, array( 'next_check' => gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS ) ), array( 'id' => $id ) );
scout_assert( 'healthy' === Editorial_Scout_Scheduling::health( $now + 3700 )['code'], 'Long source intervals are not overdue merely because there is no recent heartbeat' );
$wpdb->update( $sources, array( 'next_check' => gmdate( 'Y-m-d H:i:s', $now ) ), array( 'id' => $id ) );
scout_assert( 'overdue' === Editorial_Scout_Scheduling::health( $now + 3700 )['code'], 'Missing execution plus overdue source work is detected after traffic tolerance' );
$state['finished'] = $now + 3690; update_option( $option, $state, false );
scout_assert( 'backlog' === Editorial_Scout_Scheduling::health( $now + 3700 )['code'], 'Fresh heartbeat distinguishes backlog from absent cron execution' );
$state['started'] = $now + 3700; $state['running'] = true; update_option( $option, $state, false );
scout_assert( 'interrupted' === Editorial_Scout_Scheduling::health( $now + 4700 )['code'], 'Unfinished execution is detected after lease recovery window' );
$state['running'] = false; $state['since'] = $now - 7200; update_option( $option, $state, false );
wp_unschedule_hook( Editorial_Scout_Runner::HOOK );
scout_assert( 'missing' === Editorial_Scout_Scheduling::health()['code'], 'Missing worker event has actionable health state' );
scout_assert( Editorial_Scout_Scheduling::ensure() && (bool) wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Missing event is repaired without changing selected mode' );
$state['mode'] = 'manual'; update_option( $option, $state, false ); Editorial_Scout_Scheduling::ensure();
scout_assert( 'manual' === Editorial_Scout_Scheduling::health( $now + 999999 )['code'], 'Manual Only suppresses overdue and external-cron false alarms' );
// Bounded manual job snapshots enabled sources; explicit resumes run synchronously.
$job_ids = array( $id );
for ( $i = 0; $i < 6; ++$i ) { Editorial_Scout_Store::save( 0, 'Batch ' . $i, 'https://feeds.example.org/batch-' . $i, true ); $job_ids[] = (int) Editorial_Scout_Store::sources()[0]->id; }
$wpdb->query( "UPDATE $sources SET last_checked = NULL, manual_after = NULL WHERE enabled = 1" );
delete_user_meta( 1, Editorial_Scout_Scheduling::JOB );
$heartbeat_before = Editorial_Scout_Scheduling::state(); $count = $requests;
$job = Editorial_Scout_Scheduling::manual_batch();
scout_assert( is_array( $job ) && 1 === $job['cursor'] && 7 === count( $job['ids'] ) && $count + 1 === $requests, 'Check All processes exactly one source synchronously and snapshots enabled IDs' );
scout_assert( $heartbeat_before === Editorial_Scout_Scheduling::state() && ! wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Manual batch neither writes cron heartbeat nor enqueues cron continuation' );
scout_assert( scout_error( Editorial_Scout_Scheduling::manual_batch(), 'batch_pending' ) && scout_error( Editorial_Scout_Scheduling::manual_batch( 'stale-token' ), 'batch_expired' ), 'Concurrent starts and stale continuation tokens are rejected' );
$wpdb->update( $sources, array( 'enabled' => 0 ), array( 'id' => $job['ids'][1] ) );
$job = Editorial_Scout_Scheduling::manual_batch( $job['token'] );
scout_assert( 2 === $job['cursor'] && 1 === $job['skipped'], 'Disabled source in snapshot is skipped without request' );
Editorial_Scout_Runner::check_now( $job['ids'][2] );
$job = Editorial_Scout_Scheduling::manual_batch( $job['token'] );
scout_assert( 2 === $job['skipped'] && 'cooldown' === $job['last'], 'Manual batch respects individual Check Now cooldown' );
// Simulate process termination after checkpoint; the reserved attempt is not replayed.
$job['inflight'] = true; ++$job['cursor']; update_user_meta( 1, Editorial_Scout_Scheduling::JOB, $job );
$job = Editorial_Scout_Scheduling::manual_batch( $job['token'] );
scout_assert( 1 === $job['failed'] && 5 === $job['cursor'], 'Interrupted manual attempt is recorded and continuation advances without replay' );
while ( $job['cursor'] < count( $job['ids'] ) ) { $job = Editorial_Scout_Scheduling::manual_batch( $job['token'] ); }
$count = $requests; $job = Editorial_Scout_Scheduling::manual_batch( $job['token'] );
scout_assert( 7 === $job['cursor'] && $count === $requests, 'Completed batch replay is idempotent and performs no HTTP work' );
$job['expires'] = time() - 1; update_user_meta( 1, Editorial_Scout_Scheduling::JOB, $job );
scout_assert( scout_error( Editorial_Scout_Scheduling::manual_batch( $job['token'] ), 'batch_expired' ), 'Expired batches require a fresh administrator action' );
// A failed manual request is counted and sanitized, with no automatic continuation.
$wpdb->query( "UPDATE $sources SET last_checked = NULL, manual_after = NULL WHERE enabled = 1" );
remove_filter( 'pre_http_request', $mock_schedule );
$batch_failure = static function() { return new WP_Error( 'private_host_detail_must_not_escape' ); };
add_filter( 'pre_http_request', $batch_failure );
$failed_job = Editorial_Scout_Scheduling::manual_batch();
scout_assert( 1 === $failed_job['failed'] && 'fetch_failed' === $failed_job['last'] && ! wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Failed synchronous batch records safe outcome and schedules no retry' );
remove_filter( 'pre_http_request', $batch_failure ); add_filter( 'pre_http_request', $mock_schedule );
$failed_job['expires'] = time() - 1; update_user_meta( 1, Editorial_Scout_Scheduling::JOB, $failed_job );
// Permission/method/nonce regression requests run in independent PHP processes.
foreach ( array( 'schedule', 'batch' ) as $action ) {
 foreach ( array( 'anonymous', 'editor', 'nonce', 'get' ) as $case ) {
  $out = scout_workflow_request( $case, '0', $action );
  scout_assert( ! str_contains( $out, '|notice:' ) && str_contains( $out, '|no-request' ), 'Scheduling permission gate rejects ' . $action . '/' . $case );
 }
}
scout_assert( str_contains( scout_workflow_request( 'admin', '0', 'schedule' ), '|notice:saved|' ), 'Authorized schedule POST saves Manual Only' );
$out = scout_workflow_request( 'admin', '0', 'batch' );
scout_assert( str_contains( $out, '|notice:batch_updated|' ), 'Authorized Check All POST executes an administrator-driven batch' );
wp_cache_delete( 1, 'user_meta' );
$child_job = Editorial_Scout_Scheduling::job();
$out = scout_workflow_request( 'admin', $child_job['token'], 'batch' );
wp_cache_delete( 1, 'user_meta' );
scout_assert( str_contains( $out, '|notice:batch_updated|' ) && Editorial_Scout_Scheduling::job()['cursor'] === $child_job['cursor'] + 1, 'Valid manual continuation resumes across independent administrator processes' );
$diagnostic = Editorial_Scout_Scheduling::diagnostic();
scout_assert( ! preg_match( '/https?:|feeds\.example|\/var\/|93\.184|DB_PASSWORD|secret|scout-test-editor/i', $diagnostic ) && str_contains( $diagnostic, 'external cron supported' ), 'Diagnostic allowlist excludes URLs, paths, IPs, accounts and secrets while recognizing external cron' );

// Cron registration failures persist intent and recover on the next normal load.
Editorial_Scout_Scheduling::configure( 'manual' );
$block_cron = static function() { return new WP_Error( 'fixture_schedule_failure' ); };
add_filter( 'pre_schedule_event', $block_cron );
scout_assert( scout_error( Editorial_Scout_Scheduling::configure( 'hourly' ), 'schedule_failed' ) && 'hourly' === Editorial_Scout_Scheduling::mode(), 'Scheduling API failure reports actionable error without losing selected intent' );
remove_filter( 'pre_schedule_event', $block_cron );
scout_assert( Editorial_Scout_Scheduling::ensure() && (bool) wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Retry repairs scheduling after WordPress event API failure' );
// Automatic failure backoff must never increase cadence above the selected interval.
remove_filter( 'pre_http_request', $mock_schedule );
$failed_feed = static function() { return new WP_Error( 'unsafe_internal_fixture_detail' ); };
add_filter( 'pre_http_request', $failed_feed );
Editorial_Scout_Scheduling::configure( 'weekly' );
$wpdb->update( $sources, array( 'last_checked' => null, 'manual_after' => null, 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $id ) );
do_action( Editorial_Scout_Runner::HOOK );
scout_assert( strtotime( Editorial_Scout_Store::source( $id )->next_check . ' UTC' ) >= time() + 604790, 'Weekly failure retry never accelerates into hourly feed polling' );
remove_filter( 'pre_http_request', $failed_feed );
add_filter( 'pre_http_request', $mock_schedule );
// Safe query failures and pending reservations cannot manufacture successful work.
Editorial_Scout_Scheduling::configure( 'hourly' );
$wpdb->query( "UPDATE $sources SET enabled = 0" );
scout_assert( 'idle' === Editorial_Scout_Scheduling::health()['code'], 'Health with no enabled sources requests configuration rather than warning about cron' );
$wpdb->update( $sources, array( 'enabled' => 1, 'last_checked' => null, 'manual_after' => gmdate( 'Y-m-d H:i:s', time() + 300 ), 'next_check' => '2000-01-01 00:00:00' ), array( 'id' => $id ) );
$count = $requests; do_action( Editorial_Scout_Runner::HOOK );
scout_assert( $count === $requests, 'Automatic selector respects an interrupted manual cooldown reservation' );
$fail_due_query = static function( $sql ) { return str_starts_with( $sql, 'SELECT * FROM' ) && str_contains( $sql, 'next_check <=' ) ? 'INTENTIONAL DUE QUERY FAILURE' : $sql; };
$wpdb->suppress_errors( true ); add_filter( 'query', $fail_due_query );
do_action( Editorial_Scout_Runner::HOOK );
remove_filter( 'query', $fail_due_query ); $wpdb->suppress_errors( false );
scout_assert( 'storage_failed' === Editorial_Scout_Scheduling::state()['outcome'] && $count === $requests, 'Failed due query records storage failure and performs no feed request' );
scout_assert( 'storage_failed' === Editorial_Scout_Scheduling::health()['code'], 'Worker database failure takes precedence over generic backlog guidance' );
$wpdb->update( $sources, array( 'failures' => 0, 'next_check' => gmdate( 'Y-m-d H:i:s', time() - 3500 ) ), array( 'id' => $id ) );
$state = Editorial_Scout_Scheduling::state(); $state['since'] = time() - 7200; $state['finished'] = time() - 3500; $state['running'] = false; $state['outcome'] = 'ok'; update_option( $option, $state, false );
scout_assert( 'healthy' === Editorial_Scout_Scheduling::health()['code'], 'Low-traffic delay under one hour is tolerated after initial grace has ended' );
wp_schedule_single_event( time() + 400, Editorial_Scout_Runner::HOOK, array( 'obsolete' ) );
Editorial_Scout_Scheduling::configure( 'manual' );
scout_assert( ! wp_next_scheduled( Editorial_Scout_Runner::HOOK, array( 'obsolete' ) ) && (bool) wp_next_scheduled( 'scout_unrelated' ), 'Manual Only removes obsolete argument-bearing feed events without touching other jobs' );
// Activation/deactivation retain configuration and unrelated events.
Editorial_Scout_Scheduling::configure( 'weekly' );
editorial_scout_deactivate();
scout_assert( ! wp_next_scheduled( Editorial_Scout_Runner::HOOK ) && (bool) wp_next_scheduled( 'scout_unrelated' ), 'Deactivation removes only Editorial Scout automatic work' );
editorial_scout_activate();
scout_assert( 'weekly' === Editorial_Scout_Scheduling::mode() && (bool) wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Reactivation preserves weekly schedule and restores worker' );
Editorial_Scout_Scheduling::configure( 'manual' ); editorial_scout_deactivate(); editorial_scout_activate();
scout_assert( 'manual' === Editorial_Scout_Scheduling::mode() && ! wp_next_scheduled( Editorial_Scout_Runner::HOOK ), 'Manual Only survives activation and deactivation without scheduling' );
scout_assert( (bool) wp_next_scheduled( 'scout_unrelated' ), 'All lifecycle and mode transitions preserve unrelated events' );
wp_unschedule_hook( 'scout_unrelated' );
remove_filter( 'pre_http_request', $mock_schedule );
foreach ( $job_ids as $job_id ) { Editorial_Scout_Store::delete( $job_id ); }
delete_user_meta( 1, Editorial_Scout_Scheduling::JOB );
Editorial_Scout_Scheduling::configure( 'hourly' );
