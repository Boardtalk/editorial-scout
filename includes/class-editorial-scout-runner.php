<?php
/**
 * Bounded cron worker and atomic cross-request lock.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Runner {
	const HOOK = 'editorial_scout_check_feeds';

	public static function schedules( $schedules ) {
		$schedules['editorial_scout_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes (Editorial Scout)', 'editorial-scout' ),
		);
		return $schedules;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'editorial_scout_five_minutes', self::HOOK );
		}
	}

	public static function lock() {
		global $wpdb;
		$previous = get_option( 'editorial_scout_lock' );
		if ( is_array( $previous ) && ( $previous['expires'] ?? PHP_INT_MAX ) < time() ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s", 'editorial_scout_lock', maybe_serialize( $previous ) ) );
			wp_cache_delete( 'editorial_scout_lock', 'options' );
		}
		$token = array(
			'token'   => wp_generate_uuid4(),
			'expires' => time() + 900,
		);
		return add_option( 'editorial_scout_lock', $token, '', false ) ? $token : false;
	}

	public static function unlock( $token ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s", 'editorial_scout_lock', maybe_serialize( $token ) ) );
		wp_cache_delete( 'editorial_scout_lock', 'options' );
	}

	public static function run() {
		global $wpdb;
		if ( is_multisite() ) {
			return;
		}
		$lock = self::lock();
		if ( ! $lock ) {
			return;
		}
		try {
			$table   = Editorial_Scout_Store::table( 'sources' );
			$sources = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE enabled = 1 AND next_check <= %s ORDER BY next_check, id LIMIT 5", gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifier.
			foreach ( $sources as $source ) {
				$result = Editorial_Scout_Feed::fetch( $source->url );
				if ( ! is_wp_error( $result ) ) {
					foreach ( $result as $item ) {
						$item['source_id']     = $source->id;
						$item['discovered_at'] = gmdate( 'Y-m-d H:i:s' );
						// Ignore duplicate-key races; never overwrite editorial history.
						$stored = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . Editorial_Scout_Store::table( 'items' ) . ' (source_id,identity_hash,title,url,published_at,discovered_at) VALUES (%d,%s,%s,%s,NULLIF(%s,\'\'),%s)', $item['source_id'], $item['identity_hash'], $item['title'], $item['url'], $item['published_at'] ?? '', $item['discovered_at'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared values and internal identifier.
						if ( false === $stored ) {
							$result = new WP_Error( 'storage_failed' );
							break;
						}
					}
				}
				$failed   = is_wp_error( $result );
				$failures = $failed ? min( 10, (int) $source->failures + 1 ) : 0;
				$wpdb->update(
					$table,
					array(
						'last_checked' => gmdate( 'Y-m-d H:i:s' ),
						'failures'     => $failures,
						'next_check'   => gmdate( 'Y-m-d H:i:s', time() + ( $failed ? min( DAY_IN_SECONDS, HOUR_IN_SECONDS * ( 2 ** $failures ) ) : HOUR_IN_SECONDS ) ),
						'status'       => $failed ? sanitize_key( $result->get_error_code() ) : 'ok',
					),
					array( 'id' => $source->id )
				);
			}
			Editorial_Scout_Store::prune();
		} finally {
			self::unlock( $lock );
		}
	}
}
