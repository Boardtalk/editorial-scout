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

	/** Invalidate compatibility reads; ownership itself is always read from SQL. */
	private static function clear_lock_cache() {
		wp_cache_delete( 'editorial_scout_lock', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/** Acquire without add_option's cache-dependent, duplicate-key update behavior. */
	public static function lock() {
		global $wpdb;
		$token    = array(
			'token'   => wp_generate_uuid4(),
			'expires' => time() + 900,
		);
		$value    = maybe_serialize( $token );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $wpdb->options (option_name, option_value, autoload) VALUES (%s, %s, 'no')", 'editorial_scout_lock', $value ) );
		if ( 1 === $inserted ) {
			self::clear_lock_cache();
			return $token;
		}
		if ( false === $inserted ) {
			return false;
		}
		$previous = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", 'editorial_scout_lock' ) );
		// Do not deserialize arbitrary objects from a corrupted option.
		$lease = is_serialized( $previous ) ? @unserialize( $previous, array( 'allowed_classes' => false ) ) : false; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- Plugin-owned serialized scalar lease, classes forbidden.
		if ( ! is_array( $lease ) || empty( $lease['token'] ) || ! isset( $lease['expires'] ) || ! is_int( $lease['expires'] ) || $lease['expires'] >= time() ) {
			return false;
		}
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s", $value, 'editorial_scout_lock', $previous ) );
		self::clear_lock_cache();
		return 1 === $claimed ? $token : false;
	}

	/** Extend a live lease; a stale worker must stop after losing ownership. */
	public static function renew( &$token ) {
		global $wpdb;
		if ( ! is_array( $token ) || ( $token['expires'] ?? 0 ) <= time() ) {
			return false;
		}
		$next            = $token;
		$next['expires'] = max( time() + 900, $token['expires'] + 1 );
		$renewed         = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", maybe_serialize( $next ), 'editorial_scout_lock', maybe_serialize( $token ) ) );
		self::clear_lock_cache();
		if ( 1 !== $renewed ) {
			return false;
		}
		$token = $next;
		return true;
	}

	public static function unlock( $token ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s AND BINARY option_value = BINARY %s", 'editorial_scout_lock', maybe_serialize( $token ) ) );
		self::clear_lock_cache();
	}

	public static function run() {
		global $wpdb;
		if ( is_multisite() || Editorial_Scout_Store::SCHEMA !== get_option( 'editorial_scout_schema' ) ) {
			return;
		}
		$lock = self::lock();
		if ( ! $lock ) {
			return;
		}
		try {
			$table   = Editorial_Scout_Store::table( 'sources' );
			$sources = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE enabled = 1 AND archived = 0 AND next_check <= %s ORDER BY next_check, id LIMIT 5', $table, gmdate( 'Y-m-d H:i:s' ) ) );
			foreach ( $sources as $source ) {
				if ( ! self::renew( $lock ) ) {
					return;
				}
				self::check_source( $source, $lock );
			}
			if ( self::renew( $lock ) ) {
				Editorial_Scout_Store::prune();
			}
		} finally {
			self::unlock( $lock );
		}
	}
	private static function check_source( $source, &$lock ) {
		global $wpdb;
		try {
			$result = Editorial_Scout_Feed::fetch( $source->url );
		} catch ( \Throwable $error ) {
			// One unexpected parser/transport failure must not starve other feeds.
			$result = new WP_Error( 'processing_failed' );
		}
		if ( ! self::renew( $lock ) ) {
			return new WP_Error( 'lease_lost' );
		}
		$added = 0;
		if ( ! is_wp_error( $result ) ) {
			$added = Editorial_Scout_Store::ingest( $source->id, $result );
			if ( is_wp_error( $added ) ) {
				$result = $added;
			}
		}
		$failed   = is_wp_error( $result );
		$failures = $failed ? min( 10, (int) $source->failures + 1 ) : 0;
		$updated  = $wpdb->update(
			Editorial_Scout_Store::table( 'sources' ),
			array(
				'last_checked' => gmdate( 'Y-m-d H:i:s' ),
				'failures'     => $failures,
				'next_check'   => gmdate( 'Y-m-d H:i:s', time() + ( $failed ? min( DAY_IN_SECONDS, HOUR_IN_SECONDS * ( 2 ** $failures ) ) : HOUR_IN_SECONDS ) ),
				'status'       => $failed ? sanitize_key( $result->get_error_code() ) : 'ok',
			),
			array( 'id' => $source->id )
		);
		if ( false === $updated ) {
			return new WP_Error( 'storage_failed' );
		}
		return $failed ? $result : $added;
	}

	/** A persisted reservation makes retries/cross-request cooldown fail closed. */
	public static function check_now( $id ) {
		global $wpdb;
		if ( is_multisite() || Editorial_Scout_Store::SCHEMA !== get_option( 'editorial_scout_schema' ) ) {
			return new WP_Error( 'upgrade_required' );
		}
		$lock = self::lock();
		if ( ! $lock ) {
			return new WP_Error( 'busy' );
		}
		try {
			$source = Editorial_Scout_Store::source( $id );
			if ( ! $source || $source->archived ) {
				return new WP_Error( 'not_found' );
			}
			if ( ! $source->enabled ) {
				return new WP_Error( 'disabled' );
			}
			$after = max( $source->manual_after ? strtotime( $source->manual_after . ' UTC' ) : 0, $source->last_checked ? strtotime( $source->last_checked . ' UTC' ) + 300 : 0 );
			if ( $after > time() ) {
				return new WP_Error( 'cooldown' );
			}
			if ( false === $wpdb->update( Editorial_Scout_Store::table( 'sources' ), array( 'manual_after' => gmdate( 'Y-m-d H:i:s', time() + 300 ) ), array( 'id' => $id ) ) ) {
				return new WP_Error( 'storage_failed' );
			}
			$result = self::check_source( $source, $lock );
			if ( self::renew( $lock ) ) {
				Editorial_Scout_Store::prune();
			}
			return $result;
		} finally {
			self::unlock( $lock );
		}
	}
}
