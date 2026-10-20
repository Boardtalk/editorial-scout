<?php
/**
 * Feed-only schedule configuration, execution evidence and manual request batches.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Scheduling {
	const OPTION = 'editorial_scout_scheduling';
	const JOB    = 'editorial_scout_manual_job';

	public static function modes() {
		return array(
			'manual' => __( 'Manual Only', 'editorial-scout' ),
			'hourly' => __( 'Hourly', 'editorial-scout' ),
			'daily'  => __( 'Daily', 'editorial-scout' ),
			'twice'  => __( 'Twice Weekly', 'editorial-scout' ),
			'weekly' => __( 'Weekly', 'editorial-scout' ),
		);
	}

	public static function state() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** Refresh after acquiring the SQL lease, including persistent object caches. */
	public static function fresh_state() {
		wp_cache_delete( self::OPTION, 'options' );
		return self::state();
	}

	public static function mode() {
		$mode = self::state()['mode'] ?? '';
		return is_string( $mode ) && in_array( $mode, array( 'manual', 'hourly', 'daily', 'twice', 'weekly' ), true ) ? $mode : 'hourly';
	}

	public static function interval() {
		$intervals = array(
			'manual' => HOUR_IN_SECONDS,
			'hourly' => HOUR_IN_SECONDS,
			'daily'  => DAY_IN_SECONDS,
			'twice'  => 7 * DAY_IN_SECONDS / 2,
			'weekly' => 7 * DAY_IN_SECONDS,
		);
		return (int) $intervals[ self::mode() ];
	}

	/** Lazy, restartable option initialization preserves schema-3 data and due times. */
	public static function ensure() {
		$state = self::state();
		if ( (int) ( $state['version'] ?? 0 ) > 1 ) {
			return false;
		}
		$event = wp_get_scheduled_event( Editorial_Scout_Runner::HOOK );
		if ( 1 === ( $state['version'] ?? 0 ) && ( 'manual' === self::mode() ? ! $event : ( $event && 'editorial_scout_five_minutes' === $event->schedule ) ) ) {
			return true;
		}
		$lock = Editorial_Scout_Runner::lock();
		if ( ! $lock ) {
			return false;
		}
		try {
			$state = self::fresh_state();
			if ( 1 !== ( $state['version'] ?? 0 ) ) {
				$state = array(
					'version' => 1,
					'mode'    => 'hourly',
					'since'   => time(),
				);
				if ( ! update_option( self::OPTION, $state, false ) ) {
					return false;
				}
			}
			return self::event();
		} finally {
			Editorial_Scout_Runner::unlock( $lock );
		}
	}

	/** Own only our feed hook, including obsolete argument-bearing instances. */
	private static function event() {
		$event = wp_get_scheduled_event( Editorial_Scout_Runner::HOOK );
		if ( 'manual' === self::mode() || ( $event && 'editorial_scout_five_minutes' !== $event->schedule ) ) {
			if ( is_wp_error( wp_unschedule_hook( Editorial_Scout_Runner::HOOK, true ) ) ) {
				return false;
			}
			$event = false;
		}
		if ( 'manual' === self::mode() || $event ) {
			return true;
		}
		return true === wp_schedule_event( time() + 60, 'editorial_scout_five_minutes', Editorial_Scout_Runner::HOOK, array(), true );
	}

	/** Caller-independent serialization with cron, manual work and source edits. */
	public static function configure( $mode ) {
		global $wpdb;
		if ( ! is_string( $mode ) || ! array_key_exists( $mode, self::modes() ) ) {
			return new WP_Error( 'invalid_schedule' );
		}
		if ( Editorial_Scout_Store::SCHEMA !== get_option( 'editorial_scout_schema' ) ) {
			return new WP_Error( 'upgrade_required' );
		}
		$lock = Editorial_Scout_Runner::lock();
		if ( ! $lock ) {
			return new WP_Error( 'busy' );
		}
		$transaction = false;
		try {
			$state = self::fresh_state();
			if ( (int) ( $state['version'] ?? 0 ) > 1 ) {
				return new WP_Error( 'upgrade_required' );
			}
			$previous_mode = self::mode();
			if ( $mode !== $previous_mode ) {
				$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->options ) );
				if ( 'InnoDB' !== $engine ) {
					return new WP_Error( 'storage_failed' );
				}
				if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
					return new WP_Error( 'storage_failed' );
				}
				$transaction = true;
				// Rebase from actual check time, never from page views. New feeds stay due.
				$seconds = array(
					'manual' => 3600,
					'hourly' => 3600,
					'daily'  => 86400,
					'twice'  => 302400,
					'weekly' => 604800,
				);
				$updated = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET next_check = DATE_ADD(last_checked, INTERVAL %d SECOND) WHERE archived = 0 AND last_checked IS NOT NULL', Editorial_Scout_Store::table( 'sources' ), $seconds[ $mode ] ) );
				if ( false === $updated ) {
					return new WP_Error( 'storage_failed' );
				}
				$state['version'] = 1;
				$state['mode']    = $mode;
				$state['since']   = time();
				// Publish cache changes only after COMMIT; readers cannot see uncommitted mode.
				if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s, autoload = 'no' WHERE option_name = %s", maybe_serialize( $state ), self::OPTION ) ) ) {
					return new WP_Error( 'storage_failed' );
				}
				if ( false === $wpdb->query( 'COMMIT' ) ) {
					return new WP_Error( 'storage_failed' );
				}
				$transaction = false;
				self::fresh_state();
			}
			return self::event() ? true : new WP_Error( 'schedule_failed' );
		} finally {
			if ( $transaction ) {
				$wpdb->query( 'ROLLBACK' );
				wp_cache_delete( self::OPTION, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
			}
			Editorial_Scout_Runner::unlock( $lock );
		}
	}

	/** Only the automatic callback writes this evidence, while holding its lease. */
	public static function heartbeat( $outcome, $checked = 0, $failed = 0 ) {
		$state = self::state();
		if ( 'started' === $outcome ) {
			$state['started'] = time();
			$state['running'] = true;
		} else {
			$state['finished'] = time();
			$state['running']  = false;
			$state['outcome']  = in_array( $outcome, array( 'ok', 'feed_failures', 'storage_failed' ), true ) ? $outcome : 'storage_failed';
			$state['checked']  = min( 5, max( 0, (int) $checked ) );
			$state['failed']   = min( 5, max( 0, (int) $failed ) );
		}
		return update_option( self::OPTION, $state, false );
	}

	/** Read-only health uses due work plus a one-hour low-traffic/startup tolerance. */
	public static function health( $now = null ) {
		global $wpdb;
		$now    = $now ?? time();
		$state  = self::state();
		$table  = Editorial_Scout_Store::table( 'sources' );
		$counts = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS enabled, MIN(next_check) AS due, SUM(failures > 0) AS failing FROM %i WHERE enabled = 1 AND archived = 0', $table ) );
		$error  = $wpdb->last_error || ! $counts;
		$code   = 'healthy';
		if ( 'manual' === self::mode() ) {
			$code = 'manual';
		} elseif ( 1 !== ( $state['version'] ?? 0 ) || $error ) {
			$code = 'storage_failed';
		} elseif ( ! (int) $counts->enabled ) {
			$code = 'idle';
		} elseif ( $now - (int) ( $state['since'] ?? $now ) < HOUR_IN_SECONDS && (int) ( $state['finished'] ?? 0 ) < (int) ( $state['since'] ?? $now ) ) {
			$code = 'grace';
		} elseif ( ! wp_next_scheduled( Editorial_Scout_Runner::HOOK ) ) {
			$code = 'missing';
		} elseif ( ! empty( $state['running'] ) && $now - $state['started'] > 900 ) {
			$code = 'interrupted';
		} elseif ( 'storage_failed' === ( $state['outcome'] ?? '' ) ) {
			$code = 'storage_failed';
		} elseif ( $counts->due && strtotime( $counts->due . ' UTC' ) < $now - HOUR_IN_SECONDS ) {
			$code = $now - max( (int) ( $state['finished'] ?? 0 ), (int) ( $state['since'] ?? $now ) ) > HOUR_IN_SECONDS ? 'overdue' : 'backlog';
		} elseif ( (int) $counts->failing ) {
			$code = 'feed_failures';
		}
		return array(
			'code'     => $code,
			'enabled'  => $error ? 0 : (int) $counts->enabled,
			'failing'  => $error ? 0 : (int) $counts->failing,
			'due'      => $error ? null : $counts->due,
			'finished' => (int) ( $state['finished'] ?? 0 ),
		);
	}

	/** Allowlisted scalar fields only; no URLs, hostnames, paths, IPs or user data. */
	public static function diagnostic() {
		global $wp_version;
		$health = self::health();
		return implode(
			"\n",
			array(
				'Editorial Scout: ' . EDITORIAL_SCOUT_VERSION,
				'WordPress: ' . preg_replace( '/[^0-9.a-z-]/i', '', $wp_version ),
				'PHP: ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
				'Feed schedule: ' . self::mode(),
				'Health: ' . $health['code'],
				'Enabled sources: ' . $health['enabled'],
				'Sources reporting failure: ' . $health['failing'],
				'Last automatic completion UTC: ' . ( $health['finished'] ? gmdate( 'Y-m-d H:i:s', $health['finished'] ) : 'none' ),
				'Next worker UTC: ' . ( wp_next_scheduled( Editorial_Scout_Runner::HOOK ) ? gmdate( 'Y-m-d H:i:s', wp_next_scheduled( Editorial_Scout_Runner::HOOK ) ) : 'none' ),
				'Page-triggered WP-Cron: ' . ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'disabled (external cron supported)' : 'enabled' ),
				'No source URLs, credentials or infrastructure identifiers included.',
			)
		);
	}

	public static function job() {
		$job = get_user_meta( get_current_user_id(), self::JOB, true );
		return is_array( $job ) && ( $job['expires'] ?? 0 ) > time() ? $job : array();
	}

	/** One source per explicit administrator POST. Never queues a cron continuation. */
	public static function manual_batch( $token = '' ) {
		global $wpdb;
		if ( Editorial_Scout_Store::SCHEMA !== get_option( 'editorial_scout_schema' ) ) {
			return new WP_Error( 'upgrade_required' );
		}
		$lock = Editorial_Scout_Runner::lock();
		if ( ! $lock ) {
			return new WP_Error( 'busy' );
		}
		try {
			wp_cache_delete( get_current_user_id(), 'user_meta' );
			$job = self::job();
			if ( '' === $token ) {
				if ( $job && ( $job['cursor'] < count( $job['ids'] ) || ! empty( $job['inflight'] ) ) ) {
					return new WP_Error( 'batch_pending' );
				}
				$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE enabled = 1 AND archived = 0 ORDER BY id LIMIT 50', Editorial_Scout_Store::table( 'sources' ) ) );
				if ( $wpdb->last_error ) {
					return new WP_Error( 'storage_failed' );
				}
				$job = array(
					'token'   => wp_generate_uuid4(),
					'ids'     => array_map( 'intval', $ids ),
					'cursor'  => 0,
					'checked' => 0,
					'failed'  => 0,
					'skipped' => 0,
					'added'   => 0,
					'expires' => time() + HOUR_IN_SECONDS,
				);
			} elseif ( ! $job || ! hash_equals( $job['token'], $token ) ) {
				return new WP_Error( 'batch_expired' );
			}
			if ( ! empty( $job['inflight'] ) ) {
				++$job['failed'];
				$job['last'] = 'interrupted';
			}
			if ( $job['cursor'] >= count( $job['ids'] ) ) {
				$job['inflight'] = false;
				update_user_meta( get_current_user_id(), self::JOB, $job );
				return $job;
			}
			$id              = $job['ids'][ $job['cursor']++ ];
			$job['inflight'] = true;
			// Checkpoint before HTTP; interrupted attempts are never silently replayed.
			if ( ! update_user_meta( get_current_user_id(), self::JOB, $job ) ) {
				return new WP_Error( 'storage_failed' );
			}
			$result = Editorial_Scout_Runner::manual_source( Editorial_Scout_Store::source( $id ), $lock );
			if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
				return new WP_Error( 'lease_lost' );
			}
			Editorial_Scout_Store::prune();
			$job['inflight'] = false;
			$job['last']     = is_wp_error( $result ) ? sanitize_key( $result->get_error_code() ) : 'ok';
			if ( is_wp_error( $result ) ) {
				$key = in_array( $job['last'], array( 'disabled', 'not_found', 'cooldown' ), true ) ? 'skipped' : 'failed';
				++$job[ $key ];
			} else {
				++$job['checked'];
				$job['added'] += $result;
			}
			if ( ! update_user_meta( get_current_user_id(), self::JOB, $job ) ) {
				return new WP_Error( 'storage_failed' );
			}
			return $job;
		} finally {
			Editorial_Scout_Runner::unlock( $lock );
		}
	}
}
