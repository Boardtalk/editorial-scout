<?php
if ( ! isset( $checks ) || 'cli' !== PHP_SAPI ) { exit( 1 ); }
$decisions = Editorial_Scout_Store::table( 'decisions' );
$categories = Editorial_Scout_Store::table( 'categories' );
$wpdb->query( "ALTER TABLE $sources DROP INDEX category, DROP INDEX live, DROP COLUMN category_id, DROP COLUMN archived, DROP COLUMN manual_after" );
$wpdb->query( "ALTER TABLE $items DROP INDEX story, DROP COLUMN story_hash" );
$wpdb->query( "DROP TABLE $decisions, $categories" );
update_option( 'editorial_scout_schema', '2', false );
$wpdb->insert( $sources, array( 'name' => 'Prior staging source', 'url' => 'https://feeds.example.org/legacy', 'url_hash' => hash( 'sha256', 'legacy' ), 'enabled' => 1, 'next_check' => '2026-10-20 00:00:00' ) );
$legacy_id = $wpdb->insert_id;
$legacy_url = 'https://feeds.example.org/legacy-story?utm_source=old';
$wpdb->insert( $items, array( 'source_id' => $legacy_id, 'identity_hash' => hash( 'sha256', $legacy_url ), 'title' => 'Existing source evidence', 'url' => $legacy_url, 'discovered_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$legacy_item = $wpdb->insert_id;
for ( $i = 0; $i < 405; ++$i ) {
 $url = 'https://feeds.example.org/backfill-' . $i;
 $wpdb->insert( $items, array( 'source_id' => $legacy_id, 'identity_hash' => hash( 'sha256', $url ), 'title' => 'Legacy ' . $i, 'url' => $url, 'discovered_at' => gmdate( 'Y-m-d H:i:s' ) ) );
}
$break_backfill = static function( $sql ) { return str_contains( $sql, 'SET story_hash = CASE id' ) ? 'INTENTIONAL SCOUT BACKFILL FAILURE' : $sql; };
$wpdb->suppress_errors( true );
add_filter( 'query', $break_backfill );
scout_assert( ! Editorial_Scout_Store::install() && '2' === get_option( 'editorial_scout_schema' ), 'Interrupted canonical backfill cannot advance the migration marker' );
remove_filter( 'query', $break_backfill );
$wpdb->suppress_errors( false );
$batch_queries = 0;
$track_batches = static function( $sql ) use ( &$batch_queries ) { if ( str_contains( $sql, 'SET story_hash = CASE id' ) ) { ++$batch_queries; } return $sql; };
add_filter( 'query', $track_batches );
scout_assert( Editorial_Scout_Store::install(), 'Actual schema-2 shape upgrades to the current schema' );
remove_filter( 'query', $track_batches );
scout_assert( 3 === $batch_queries, '406 legacy observations backfill in three bounded SQL batches' );
scout_assert( 'Prior staging source' === Editorial_Scout_Store::source( $legacy_id )->name && '2026-10-20 00:00:00' === Editorial_Scout_Store::source( $legacy_id )->next_check, 'Migration preserves source identity and existing cron due time' );
$backfilled = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $items WHERE id = %d", $legacy_item ) );
scout_assert( $backfilled && 'Existing source evidence' === $backfilled->title && $backfilled->url === $legacy_url && Editorial_Scout_Store::story_hash( $legacy_url ) === $backfilled->story_hash, 'Migration preserves metadata and backfills canonical identity without fetching' );
scout_assert( Editorial_Scout_Store::install() && 406 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $items" ), 'Additive migration is repeatable without duplicate observations' );
Editorial_Scout_Store::delete( $legacy_id );
