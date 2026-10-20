<?php
/**
 * Bounded editorial offers and transactional administrator decision receipts.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Newsroom {
	const TICKET    = 'editorial_scout_newsroom_ticket';
	const REPORT    = 'editorial_scout_newsroom_report';
	const PAGE_SIZE = 10;

	public static function statuses() {
		return array(
			'new'          => __( 'New offers', 'editorial-scout' ),
			'shortlisted'  => __( 'Held for later', 'editorial-scout' ),
			'commissioned' => __( 'Commissioned — waiting for writing', 'editorial-scout' ),
			'dismissed'    => __( 'Not interested', 'editorial-scout' ),
		);
	}
	public static function filters( $input ) {
		$state   = Editorial_Scout_Intelligence::state();
		$profile = isset( $input['profile'] ) && is_string( $input['profile'] ) ? $input['profile'] : ( $state['default'] ?? '' );
		return array(
			'status'      => in_array( $input['status'] ?? 'new', array_merge( array( 'all' ), array_keys( self::statuses() ) ), true ) ? ( $input['status'] ?? 'new' ) : 'new',
			'source'      => is_scalar( $input['source'] ?? null ) ? absint( $input['source'] ) : 0,
			'category'    => is_scalar( $input['category'] ?? null ) ? absint( $input['category'] ) : 0,
			'profile'     => isset( $state['profiles'][ $profile ] ) ? $profile : '',
			'title_match' => '1' === ( $input['title_match'] ?? '' ) ? '1' : '',
			'paged'       => is_scalar( $input['paged'] ?? null ) ? max( 1, min( 1500, absint( $input['paged'] ) ) ) : 1,
		);
	}
	/** Literal, bounded title substrings, not semantic relevance or a confidence score. */
	private static function phrases( $text ) {
		$parts = preg_split( '/[,;\r\n]+/u', (string) $text );
		return array_slice(
			array_values(
				array_filter(
					array_map( 'trim', $parts ),
					static function ( $phrase ) {
						return mb_strlen( $phrase ) >= 2 && mb_strlen( $phrase ) <= 60;
					}
				)
			),
			0,
			8
		);
	}
	public static function offers( $filters ) {
		global $wpdb;
		$filters = self::filters( $filters );
		$profile = Editorial_Scout_Intelligence::state()['profiles'][ $filters['profile'] ] ?? array();
		$include = array();
		$exclude = array();
		foreach ( array( 'topics', 'excluded' ) as $key ) {
			$clauses = array();
			foreach ( self::phrases( $profile[ $key ] ?? '' ) as $phrase ) {
				$clauses[] = $wpdb->prepare( 'CONVERT(LOWER(i.title) USING utf8mb4) COLLATE utf8mb4_bin LIKE CONVERT(%s USING utf8mb4) COLLATE utf8mb4_bin', '%' . $wpdb->esc_like( mb_strtolower( $phrase ) ) . '%' );
			}
			if ( 'topics' === $key ) {
				$include = $clauses;
			} else {
				$exclude = $clauses;
			}
		}
		$fit   = $include ? '((' . implode( ' OR ', $include ) . ')' . ( $exclude ? ' AND NOT (' . implode( ' OR ', $exclude ) . ')' : '' ) . ')' : '0';
		$now   = gmdate( 'Y-m-d H:i:s' );
		$week  = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
		$month = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $fit is fixed SQL with individually prepared literal LIKE values.
		$sql = $wpdb->prepare(
			"SELECT i.*, g.first_seen,g.latest,g.feeds,g.observations,
			COALESCE(d.status,'new') AS editorial_status, COALESCE(d.revision,'') AS revision,
			CASE WHEN $fit THEN 1 ELSE 0 END AS profile_fit,
			CASE WHEN COALESCE(i.published_at,g.first_seen) > %s THEN 0
			WHEN COALESCE(i.published_at,g.first_seen) >= %s THEN 2
			WHEN COALESCE(i.published_at,g.first_seen) >= %s THEN 1 ELSE 0 END AS freshness
			FROM (SELECT MIN(a.id) AS representative, a.story_hash,MIN(a.discovered_at) AS first_seen,
			MAX(a.discovered_at) AS latest,COUNT(*) AS observations,COUNT(DISTINCT SHA2(s.url,256)) AS feeds
			FROM %i a LEFT JOIN %i s ON s.id = a.source_id GROUP BY a.story_hash) g
			INNER JOIN %i i ON i.id = g.representative LEFT JOIN %i d ON d.identity_hash = g.story_hash
			WHERE (%s = 'all' OR COALESCE(d.status,'new') = %s)
			AND (%d = 0 AND %d = 0 OR EXISTS(SELECT 1 FROM %i f INNER JOIN %i fs ON fs.id = f.source_id
			WHERE f.story_hash = g.story_hash AND (%d = 0 OR f.source_id = %d) AND (%d = 0 OR fs.category_id = %d)))
			AND (%s = '' OR $fit)
			ORDER BY profile_fit DESC, freshness DESC,g.latest DESC,i.id DESC LIMIT 11 OFFSET %d",
			$now,
			$week,
			$month,
			Editorial_Scout_Store::table( 'items' ),
			Editorial_Scout_Store::table( 'sources' ),
			Editorial_Scout_Store::table( 'items' ),
			Editorial_Scout_Store::table( 'decisions' ),
			$filters['status'],
			$filters['status'],
			$filters['source'],
			$filters['category'],
			Editorial_Scout_Store::table( 'items' ),
			Editorial_Scout_Store::table( 'sources' ),
			$filters['source'],
			$filters['source'],
			$filters['category'],
			$filters['category'],
			$filters['title_match'],
			( $filters['paged'] - 1 ) * self::PAGE_SIZE
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fully prepared immediately above.
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : $rows;
	}
	public static function counts() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT COALESCE(d.status,'new') AS status, COUNT(DISTINCT i.story_hash) AS total FROM %i i LEFT JOIN %i d ON d.identity_hash = i.story_hash GROUP BY COALESCE(d.status,'new')", Editorial_Scout_Store::table( 'items' ), Editorial_Scout_Store::table( 'decisions' ) ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		return array_map( 'intval', wp_list_pluck( $rows, 'total', 'status' ) );
	}
	/** Up to six attribution records per card; complete evidence stays in Discoveries. */
	public static function sources( $hash ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.id,s.name,s.url AS feed_url,s.archived,MIN(i.url) AS article_url,COUNT(*) AS observations FROM %i i LEFT JOIN %i s ON s.id = i.source_id WHERE i.story_hash = %s GROUP BY i.source_id,s.id,s.name,s.url,s.archived ORDER BY s.id LIMIT 7', Editorial_Scout_Store::table( 'items' ), Editorial_Scout_Store::table( 'sources' ), $hash ) );
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : $rows;
	}
	public static function version( $row ) {
		return hash( 'sha256', wp_json_encode( array( (int) $row->id, $row->editorial_status, $row->revision ) ) );
	}
	/** Shared legacy-view token; source filtering may choose a different representative. */
	public static function decision_version( $hash, $row = null ) {
		global $wpdb;
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
			return new WP_Error( 'invalid_decision' );
		}
		if ( null === $row ) {
			$row = self::current( $hash );
			if ( $wpdb->last_error || ! $row ) {
				return new WP_Error( 'stale_offer' );
			}
		}
		return hash( 'sha256', wp_json_encode( array( $hash, $row->editorial_status, $row->revision ) ) );
	}
	private static function current( $hash ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT i.id,i.title,COALESCE(d.status,'new') AS editorial_status,COALESCE(d.revision,'') AS revision FROM %i i LEFT JOIN %i d ON d.identity_hash = i.story_hash WHERE i.story_hash = %s ORDER BY i.id LIMIT 1", Editorial_Scout_Store::table( 'items' ), Editorial_Scout_Store::table( 'decisions' ), $hash ) );
	}
	/** One active ten-offer form per administrator; opening another replaces it. */
	public static function ticket( $rows, $filters ) {
		if ( ! current_user_can( 'manage_options' ) || ! $rows ) {
			return '';
		}
		$lock = Editorial_Scout_Runner::lock();
		if ( ! $lock ) {
			return '';
		}
		try {
			$manifest = array();
			foreach ( array_slice( $rows, 0, self::PAGE_SIZE ) as $row ) {
				$manifest[ $row->story_hash ] = array(
					'version' => self::version( $row ),
					'title'   => $row->title,
				);
			}
			$value = array(
				'token'   => wp_generate_uuid4(),
				'created' => time(),
				'state'   => 'pending',
				'rows'    => $manifest,
				'filters' => self::filters( $filters ),
			);
			return update_user_meta( get_current_user_id(), self::TICKET, $value ) ? $value['token'] : '';
		} finally {
			Editorial_Scout_Runner::unlock( $lock );
		}
	}
	public static function report() {
		$value = get_user_meta( get_current_user_id(), self::REPORT, true );
		return is_array( $value ) && isset( $value['created'], $value['token'], $value['rows'] ) ? $value : array();
	}
	/** Derive counts from the durable per-item outcomes, including legacy receipts. */
	public static function summary( $report ) {
		$counts = array_fill_keys( array( 'commissioned', 'shortlisted', 'dismissed', 'unchanged', 'failed' ), 0 );
		foreach ( $report['rows'] ?? array() as $row ) {
			$key = 'saved' === $row['code'] && isset( $counts[ $row['choice'] ] ) ? $row['choice'] : ( 'unchanged' === $row['code'] ? 'unchanged' : 'failed' );
			++$counts[ $key ];
		}
		return $counts;
	}

	/** Raw writes keep uncommitted receipt data out of persistent metadata caches. */
	private static function receipt_meta( $key, $value ) {
		global $wpdb;
		$uid    = get_current_user_id();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT umeta_id FROM %i WHERE user_id = %d AND meta_key = %s LIMIT 1', $wpdb->usermeta, $uid, $key ) );
		if ( $wpdb->last_error ) {
			return false;
		}
		if ( $exists ) {
			return false !== $wpdb->update(
				$wpdb->usermeta,
				array( 'meta_value' => maybe_serialize( $value ) ),
				array(
					'user_id'  => $uid,
					'meta_key' => $key,
				)
			);
		}
		return false !== $wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $uid,
				'meta_key'   => $key,
				'meta_value' => maybe_serialize( $value ),
			)
		);
	}
	/** Receipt and decisions commit together; no HTTP, posts or paid work. */
	public static function submit( $token, $choices, &$lock ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! is_string( $token ) || ! is_array( $choices ) || count( $choices ) > self::PAGE_SIZE ) {
			return new WP_Error( 'invalid_batch' );
		}
		wp_cache_delete( get_current_user_id(), 'user_meta' );
		$ticket = get_user_meta( get_current_user_id(), self::TICKET, true );
		if ( ! is_array( $ticket ) || ! hash_equals( $ticket['token'], $token ) || $ticket['created'] < time() - HOUR_IN_SECONDS ) {
			return new WP_Error( 'stale_batch' );
		}
		if ( 'pending' !== $ticket['state'] ) {
			return new WP_Error( 'used_batch' );
		}
		foreach ( $choices as $hash => $choice ) {
			if ( ! is_string( $hash ) || ! isset( $ticket['rows'][ $hash ] ) || ! is_string( $choice ) || ! in_array( $choice, array( '', 'commissioned', 'shortlisted', 'dismissed' ), true ) ) {
				return new WP_Error( 'invalid_batch' );
			}
		}
		foreach ( array( $wpdb->usermeta, $wpdb->options ) as $native_table ) {
			$engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $native_table ), ARRAY_A );
			if ( ! $engine || 'InnoDB' !== $engine['Engine'] ) {
				return new WP_Error( 'newsroom_engine' );
			}
		}
		if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
			return new WP_Error( 'lease_lost' );
		}
		$ticket['state'] = 'processing';
		if ( ! update_user_meta( get_current_user_id(), self::TICKET, $ticket ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'storage_failed' );
		}
		$report        = array(
			'created' => time(),
			'token'   => $token,
			'rows'    => array(),
			'filters' => $ticket['filters'],
		);
		$ok            = true;
		$committed     = false;
		$rollback_lock = $lock;
		try {
			foreach ( $ticket['rows'] as $hash => $saved ) {
				$choice = $choices[ $hash ] ?? '';
				$code   = 'unchanged';
				if ( '' !== $choice ) {
					$current = self::current( $hash );
					if ( $wpdb->last_error ) {
						$ok = false;
						break;
					}
					if ( ! $current || ! hash_equals( $saved['version'], self::version( $current ) ) ) {
						$code = 'stale_offer';
					} else {
						$result = Editorial_Scout_Store::decide( $hash, $choice, true, $ticket['filters']['profile'] );
						$code   = is_wp_error( $result ) ? $result->get_error_code() : ( $current->editorial_status === $choice ? 'unchanged' : 'saved' );
						if ( ! $result || in_array( $code, array( 'storage_failed', 'lease_lost' ), true ) ) {
							$ok = false;
							break;
						}
					}
				}
				$report['rows'][] = array(
					'title'  => $saved['title'],
					'code'   => $code,
					'choice' => $choice,
				);
				if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
					$ok = false;
					break;
				}
			}
			$ticket['state'] = 'complete';
			if ( ! $ok || ! self::receipt_meta( self::REPORT, $report ) || ! self::receipt_meta( self::TICKET, $ticket ) || ! Editorial_Scout_Runner::renew( $lock ) || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'newsroom_rollback' );
			}
			$committed = true;
			return $report;
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'newsroom_rollback' );
		} finally {
			if ( ! $committed ) {
				// InnoDB rolls back in-transaction lease renewals too. Unlock the
				// pre-transaction value, never a rolled-back expiry or another owner.
				$lock = $rollback_lock;
			}
			wp_cache_delete( get_current_user_id(), 'user_meta' );
		}
	}
}
