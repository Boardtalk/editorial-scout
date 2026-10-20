<?php
/**
 * Site-local discovery storage.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Store {
	const SCHEMA         = '5';
	const DECISION_LIMIT = 1000;
	const EVIDENCE_LIMIT = 10000;

	public static function table( $kind ) {
		global $wpdb;
		return $wpdb->prefix . 'editorial_scout_' . ( in_array( $kind, array( 'sources', 'items', 'decisions', 'categories', 'commissions' ), true ) ? $kind : 'items' );
	}

	public static function install() {
		if ( (int) get_option( 'editorial_scout_schema', 0 ) > (int) self::SCHEMA ) {
			return false;
		}
		$lock = Editorial_Scout_Runner::lock();
		if ( ! $lock ) {
			return false;
		}
		try {
			return self::migrate( $lock );
		} finally {
			Editorial_Scout_Runner::unlock( $lock );
		}
	}

	private static function migrate( &$lock ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$sources = self::table( 'sources' );
		$items   = self::table( 'items' );
		dbDelta(
			"CREATE TABLE $sources (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			url text NOT NULL,
			url_hash char(64) NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			next_check datetime NOT NULL,
			last_checked datetime DEFAULT NULL,
			failures smallint unsigned NOT NULL DEFAULT 0,
			status varchar(40) NOT NULL DEFAULT 'pending',
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			archived tinyint(1) NOT NULL DEFAULT 0,
			manual_after datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY due (enabled,next_check),
			KEY category (category_id,archived),
			KEY live (archived,id)
		) ENGINE=InnoDB $charset;"
		);
		dbDelta(
			"CREATE TABLE $items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			identity_hash char(64) NOT NULL,
			story_hash char(64) NOT NULL DEFAULT '',
			title varchar(500) NOT NULL,
			url text NOT NULL,
			published_at datetime DEFAULT NULL,
			discovered_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_identity (source_id,identity_hash),
			KEY discovered (discovered_at,id),
			KEY story (story_hash,discovered_at)
		) ENGINE=InnoDB $charset;"
		);
		$decisions  = self::table( 'decisions' );
		$categories = self::table( 'categories' );
		dbDelta(
			"CREATE TABLE $decisions (
			identity_hash char(64) NOT NULL,
			status varchar(12) NOT NULL,
			revision char(36) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL,
			PRIMARY KEY  (identity_hash),
			KEY status (status,updated_at)
		) ENGINE=InnoDB $charset;"
		);
		dbDelta(
			"CREATE TABLE $categories (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(80) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB $charset;"
		);
		$commissions = self::table( 'commissions' );
		dbDelta(
			"CREATE TABLE $commissions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_hash char(64) NOT NULL,
			profile_id char(36) NOT NULL DEFAULT '',
			profile_name varchar(120) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY story_hash (story_hash),
			KEY status (status,updated_at)
		) ENGINE=InnoDB $charset;"
		);
		if ( ! self::schema_ready() ) {
			delete_option( 'editorial_scout_schema' );
			return false;
		}
		// Bounded local backfill; no feed requests or deletion during migration.
		do {
			if ( ! Editorial_Scout_Runner::renew( $lock ) ) {
				return false;
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, url FROM %i WHERE story_hash = '' ORDER BY id LIMIT 200", $items ) );
			if ( null === $rows ) {
				return false;
			}
			$cases = array();
			$ids   = array();
			foreach ( $rows as $row ) {
				$cases[] = $wpdb->prepare( 'WHEN %d THEN %s', $row->id, self::story_hash( $row->url ) );
				$ids[]   = (int) $row->id;
			}
			if ( $cases ) {
				$case_sql = implode( ' ', $cases );
				$id_sql   = implode( ',', $ids );
				if ( false === $wpdb->query( $wpdb->prepare( "UPDATE %i SET story_hash = CASE id $case_sql END WHERE id IN ($id_sql)", $items ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared CASE values and integer-only IDs.
					return false;
				}
			}
			$batch_count = count( $rows );
		} while ( 200 === $batch_count );

		if ( self::schema_ready() ) {
			update_option( 'editorial_scout_schema', self::SCHEMA, false );
			return true;
		}
		delete_option( 'editorial_scout_schema' );
		return false;
	}

	/** Verify migration results, not merely the existence of two tables. */
	public static function schema_ready() {
		global $wpdb;
		$definitions                          = array(
			'sources' => array(
				'id'           => 'bigint unsigned',
				'name'         => 'varchar(200)',
				'url'          => 'text',
				'url_hash'     => 'char(64)',
				'enabled'      => 'tinyint',
				'next_check'   => 'datetime',
				'last_checked' => 'datetime',
				'failures'     => 'smallint unsigned',
				'status'       => 'varchar(40)',
				'category_id'  => 'bigint unsigned',
				'archived'     => 'tinyint',
				'manual_after' => 'datetime',
			),
			'items'   => array(
				'id'            => 'bigint unsigned',
				'source_id'     => 'bigint unsigned',
				'identity_hash' => 'char(64)',
				'story_hash'    => 'char(64)',
				'title'         => 'varchar(500)',
				'url'           => 'text',
				'published_at'  => 'datetime',
				'discovered_at' => 'datetime',
			),
		);
		$definitions['decisions']             = array(
			'identity_hash' => 'char(64)',
			'status'        => 'varchar(12)',
			'updated_at'    => 'datetime',
		);
		$definitions['decisions']['revision'] = 'char(36)';
		$definitions['commissions']           = array(
			'profile_id'   => 'char(36)',
			'profile_name' => 'varchar(120)',
			'id'           => 'bigint unsigned',
			'story_hash'   => 'char(64)',
			'status'       => 'varchar(12)',
			'created_at'   => 'datetime',
			'updated_at'   => 'datetime',
		);
		$definitions['categories']            = array(
			'id'   => 'bigint unsigned',
			'name' => 'varchar(80)',
		);
		$required_indexes                     = array(
			'sources' => array(
				'PRIMARY'  => array( 'id' ),
				'url_hash' => array( 'url_hash' ),
				'due'      => array( 'enabled', 'next_check' ),
				'category' => array( 'category_id', 'archived' ),
			),
			'items'   => array(
				'PRIMARY'         => array( 'id' ),
				'source_identity' => array( 'source_id', 'identity_hash' ),
				'discovered'      => array( 'discovered_at', 'id' ),
				'story'           => array( 'story_hash', 'discovered_at' ),
			),
		);
		$required_indexes['sources']['live']  = array( 'archived', 'id' );
		$required_indexes['decisions']        = array(
			'PRIMARY' => array( 'identity_hash' ),
			'status'  => array( 'status', 'updated_at' ),
		);
		$required_indexes['commissions']      = array(
			'PRIMARY'    => array( 'id' ),
			'story_hash' => array( 'story_hash' ),
			'status'     => array( 'status', 'updated_at' ),
		);
		$required_indexes['categories']       = array(
			'PRIMARY' => array( 'id' ),
			'name'    => array( 'name' ),
		);
		foreach ( $definitions as $kind => $required ) {
			$table  = self::table( $kind );
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! $status || 'InnoDB' !== $status['Engine'] ) {
				return false;
			}
			$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), ARRAY_A );
			$actual  = array();
			foreach ( $columns as $column ) {
				$actual[ $column['Field'] ] = preg_replace( '/(bigint|smallint|tinyint)\(\d+\)/', '$1', strtolower( $column['Type'] ) );
				if ( 'id' === $column['Field'] && 'auto_increment' !== $column['Extra'] ) {
					return false;
				}
			}
			foreach ( $required as $field => $type ) {
				if ( ( $actual[ $field ] ?? '' ) !== $type ) {
					return false;
				}
			}
			$indexes = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ), ARRAY_A );
			$actual  = array();
			foreach ( $indexes as $index ) {
				$name = $index['Key_name'];
				if ( in_array( $name, array( 'PRIMARY', 'url_hash', 'source_identity', 'name', 'story_hash' ), true ) && 0 !== (int) $index['Non_unique'] ) {
					return false;
				}
				$actual[ $name ][ (int) $index['Seq_in_index'] - 1 ] = $index['Column_name'];
			}
			foreach ( $required_indexes[ $kind ] as $name => $fields ) {
				$parts = $actual[ $name ] ?? array();
				ksort( $parts );
				if ( array_values( $parts ) !== $fields ) {
					return false;
				}
			}
		}
		return true;
	}

	public static function sources( $include_archived = false ) {
		global $wpdb;
		$table = self::table( 'sources' );
		if ( $include_archived ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY archived, id DESC LIMIT 100', $table ) );
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE archived = 0 ORDER BY id DESC LIMIT 50', $table ) );
	}

	public static function source( $id ) {
		global $wpdb;
		$table = self::table( 'sources' );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) );
	}

	public static function save( $id, $name, $url, $enabled, $category_id = 0 ) {
		global $wpdb;
		$old = $id ? self::source( $id ) : null;
		if ( ( $id && ( ! $old || $old->archived ) ) || ( ! $id && count( self::sources() ) >= 50 ) ) {
			return false;
		}
		// Avoid logging an expected duplicate error; the unique index remains authoritative.
		$table = self::table( 'sources' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s AND id <> %d', $table, hash( 'sha256', $url ), $id ) ) ) {
			return false;
		}
		if ( $category_id && ! self::category( $category_id ) ) {
			return false;
		}
		$data = array(
			'category_id' => $category_id,
			'name'        => $name,
			'url'         => $url,
			'url_hash'    => hash( 'sha256', $url ),
			'enabled'     => (int) $enabled,
			'next_check'  => gmdate( 'Y-m-d H:i:s' ),
			'failures'    => 0,
			'status'      => 'pending',
		);
		if ( $id ) {
			if ( $old->url === $url ) {
				unset( $data['next_check'], $data['failures'], $data['status'] );
			}
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$reviewed = self::has_decisions( $id );
			if ( is_wp_error( $reviewed ) ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
			if ( $old->url !== $url && $reviewed ) {
				if ( ! self::remove_source( $id ) || false === $wpdb->insert( $table, $data ) ) {
					$wpdb->query( 'ROLLBACK' );
					return false;
				}
				if ( false === $wpdb->query( 'COMMIT' ) ) {
					$wpdb->query( 'ROLLBACK' );
					return false;
				}
				return true;
			}
			$result = $wpdb->update( self::table( 'sources' ), $data, array( 'id' => $id ) );
			if ( false === $result || ( $old->url !== $url && false === $wpdb->delete( self::table( 'items' ), array( 'source_id' => $id ), array( '%d' ) ) ) ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
			return true;
		}
		return false !== $wpdb->insert( self::table( 'sources' ), $data );
	}

	public static function delete( $id ) {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}
		if ( ! self::remove_source( $id ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		return true;
	}

	/** Conservative URL identity: discard known tracking keys, preserve meaningful query order. */
	public static function story_hash( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return hash( 'sha256', $url );
		}
		$query = array();
		foreach ( explode( '&', $parts['query'] ?? '' ) as $pair ) {
			$key = strtolower( rawurldecode( explode( '=', $pair, 2 )[0] ) );
			if ( '' !== $pair && ! str_starts_with( $key, 'utm_' ) && ! in_array( $key, array( 'fbclid', 'gclid', 'mc_cid', 'mc_eid' ), true ) ) {
				$query[] = $pair;
			}
		}
		$canonical = strtolower( $parts['scheme'] ?? 'https' ) . '://' . strtolower( $parts['host'] ) . ( $parts['path'] ?? '/' ) . ( $query ? '?' . implode( '&', $query ) : '' );
		return hash( 'sha256', $canonical );
	}

	public static function categories() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name LIMIT 20', self::table( 'categories' ) ) );
	}

	public static function category( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table( 'categories' ), $id ) );
	}

	/** Caller holds the shared mutation lease. */
	public static function save_category( $id, $name ) {
		global $wpdb;
		$name = sanitize_text_field( $name );
		if ( '' === $name || mb_strlen( $name ) > 80 || ( $id && ! self::category( $id ) ) || ( ! $id && count( self::categories() ) >= 20 ) ) {
			return false;
		}
		$table = self::table( 'categories' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE name = %s AND id <> %d', $table, $name, $id ) ) ) {
			return false;
		}
		return false !== ( $id ? $wpdb->update( $table, array( 'name' => $name ), array( 'id' => $id ) ) : $wpdb->insert( $table, array( 'name' => $name ) ) );
	}

	public static function delete_category( $id ) {
		global $wpdb;
		if ( ! self::category( $id ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}
		if ( false === $wpdb->update( self::table( 'sources' ), array( 'category_id' => 0 ), array( 'category_id' => $id ) ) || false === $wpdb->delete( self::table( 'categories' ), array( 'id' => $id ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		return true;
	}

	private static function has_decisions( $id ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SELECT i.id FROM %i i INNER JOIN %i d ON d.identity_hash = i.story_hash WHERE i.source_id = %d LIMIT 1', self::table( 'items' ), self::table( 'decisions' ), $id ) );
		return $wpdb->last_error ? new WP_Error( 'storage_failed' ) : (bool) $found;
	}

	/** Preserve editorial evidence when removing/changing a source; caller owns transaction. */
	private static function remove_source( $id ) {
		global $wpdb;
		$reviewed = self::has_decisions( $id );
		if ( is_wp_error( $reviewed ) ) {
			return false;
		}
		if ( ! $reviewed ) {
			return false !== $wpdb->delete( self::table( 'items' ), array( 'source_id' => $id ) ) && false !== $wpdb->delete( self::table( 'sources' ), array( 'id' => $id ) );
		}
		$removed = $wpdb->query( $wpdb->prepare( 'DELETE i FROM %i i LEFT JOIN %i d ON d.identity_hash = i.story_hash WHERE i.source_id = %d AND d.identity_hash IS NULL', self::table( 'items' ), self::table( 'decisions' ), $id ) );
		return false !== $removed && false !== $wpdb->update(
			self::table( 'sources' ),
			array(
				'archived' => 1,
				'enabled'  => 0,
				'url_hash' => hash( 'sha256', 'archived:' . $id ),
			),
			array( 'id' => $id )
		);
	}

	/** Shared story decision; ordinary pruning never removes a decision or its observations. */
	public static function decide( $hash, $status, $in_transaction = false, $profile_id = '' ) {
		global $wpdb;
		if ( ! $in_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'storage_failed' );
		}
		$result = self::write_decision( $hash, $status, $profile_id );
		if ( $in_transaction ) {
			return $result;
		}
		if ( is_wp_error( $result ) || ! $result || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return is_wp_error( $result ) ? $result : new WP_Error( 'storage_failed' );
		}
		return true;
	}

	private static function write_decision( $hash, $status, $profile_id ) {
		global $wpdb;
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) || ! in_array( $status, array( 'new', 'shortlisted', 'dismissed', 'commissioned' ), true ) ) {
			return new WP_Error( 'invalid_decision' );
		}
		$table = self::table( 'decisions' );
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE story_hash = %s LIMIT 1', self::table( 'items' ), $hash ) ) ) {
			return new WP_Error( 'not_found' );
		}
		$commission = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE story_hash = %s FOR UPDATE', self::table( 'commissions' ), $hash ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		if ( 'new' === $status && ! $commission ) {
			return false !== $wpdb->delete( $table, array( 'identity_hash' => $hash ) );
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT identity_hash FROM %i WHERE identity_hash = %s', $table, $hash ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		if ( ! $exists ) {
			$total = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'storage_failed' );
			}
			if ( self::DECISION_LIMIT <= (int) $total ) {
				return new WP_Error( 'decision_limit' );
			}
			$protected = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i i INNER JOIN %i d ON d.identity_hash = i.story_hash', self::table( 'items' ), $table ) );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'storage_failed' );
			}
			$additional = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE story_hash = %s', self::table( 'items' ), $hash ) );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'storage_failed' );
			}
			if ( (int) $protected + (int) $additional > self::EVIDENCE_LIMIT ) {
				return new WP_Error( 'evidence_limit' );
			}
		}
		$current = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE identity_hash = %s FOR UPDATE', $table, $hash ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		if ( $current === $status ) {
			return true;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (identity_hash,status,updated_at,revision) VALUES (%s,%s,%s,%s) ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at), revision = VALUES(revision)', $table, $hash, $status, $now, wp_generate_uuid4() ) ) ) {
			return new WP_Error( 'storage_failed' );
		}
		if ( 'commissioned' === $status || $commission ) {
			$state   = array(
				'commissioned' => 'waiting',
				'shortlisted'  => 'held',
				'dismissed'    => 'dismissed',
				'new'          => 'paused',
			)[ $status ];
			$profile = is_string( $profile_id ) ? ( Editorial_Scout_Intelligence::state()['profiles'][ $profile_id ] ?? array() ) : array();
			if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (story_hash,status,created_at,updated_at,profile_id,profile_name) VALUES (%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)', self::table( 'commissions' ), $hash, $state, $now, $now, $profile ? $profile_id : '', $profile ? mb_substr( $profile['name'], 0, 120 ) : '' ) ) ) {
				return new WP_Error( 'storage_failed' );
			}
		}
		return true;
	}

	/** Feed-size-bounded ingestion; preserve immutable original observations and choices. */
	public static function ingest( $source_id, $rows ) {
		global $wpdb;
		$items     = self::table( 'items' );
		$decisions = self::table( 'decisions' );
		$protected = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i i INNER JOIN %i d ON d.identity_hash = i.story_hash', $items, $decisions ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		$count = 0;
		$rows  = array_slice( $rows, 0, 50 );
		if ( ! $rows ) {
			return 0;
		}
		$identities   = array_column( $rows, 'identity_hash' );
		$stories      = array_map( array( __CLASS__, 'story_hash' ), array_column( $rows, 'url' ) );
		$placeholders = implode( ',', array_fill( 0, count( $rows ), '%s' ) );
		$existing     = $wpdb->get_col( $wpdb->prepare( "SELECT identity_hash FROM %i WHERE source_id = %d AND identity_hash IN ($placeholders)", array_merge( array( $items, $source_id ), $identities ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed generated placeholders and an expanded prepared argument array.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		$reviewed_hashes = $wpdb->get_col( $wpdb->prepare( "SELECT identity_hash FROM %i WHERE identity_hash IN ($placeholders)", array_merge( array( $decisions ), $stories ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed generated placeholders and an expanded prepared argument array.
		if ( $wpdb->last_error ) {
			return new WP_Error( 'storage_failed' );
		}
		foreach ( $rows as $row ) {
			$story = self::story_hash( $row['url'] );
			if ( in_array( $row['identity_hash'], $existing, true ) ) {
				continue;
			}
			$reviewed = in_array( $story, $reviewed_hashes, true );
			if ( $reviewed && $protected >= self::EVIDENCE_LIMIT ) {
				return new WP_Error( 'evidence_limit' );
			}
			$result = $wpdb->insert(
				$items,
				array(
					'source_id'     => $source_id,
					'identity_hash' => $row['identity_hash'],
					'story_hash'    => $story,
					'title'         => $row['title'],
					'url'           => $row['url'],
					'published_at'  => $row['published_at'],
					'discovered_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			if ( false === $result ) {
				return new WP_Error( 'storage_failed' );
			}
			$existing[] = $row['identity_hash'];
			++$count;
			$protected += (int) (bool) $reviewed;
		}
		return $count;
	}

	/** One bounded queue query; on-demand coverage is separately paginated. */
	public static function queue( $page, $filters = array() ) {
		global $wpdb;
		$items     = self::table( 'items' );
		$sources   = self::table( 'sources' );
		$decisions = self::table( 'decisions' );
		$source    = absint( $filters['source'] ?? 0 );
		$category  = 'uncategorized' === ( $filters['category'] ?? '' ) ? -1 : absint( $filters['category'] ?? 0 );
		$status    = in_array( $filters['status'] ?? '', array( 'new', 'shortlisted', 'dismissed', 'commissioned' ), true ) ? $filters['status'] : '';
		$dates     = array(
			'from' => '',
			'to'   => '',
		);
		foreach ( $dates as $field => $unused ) {
			$value = is_string( $filters[ $field ] ?? null ) ? $filters[ $field ] : '';
			$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
			if ( $date && $date->format( 'Y-m-d' ) === $value ) {
				$dates[ $field ] = $value . ( 'from' === $field ? ' 00:00:00' : ' 23:59:59' );
			}
		}
		// Fixed SQL and prepared values only; MySQL folds unused filter branches to constants.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, g.story_key AS story_hash, s.name AS source_name,
			COALESCE(d.status,'new') AS editorial_status, COALESCE(d.revision,'') AS revision, g.latest, g.coverage
			FROM (SELECT MIN(i.id) AS representative,
			COALESCE(NULLIF(i.story_hash,''),i.identity_hash) AS story_key,
			MAX(i.discovered_at) AS latest, COUNT(DISTINCT i.source_id) AS coverage
			FROM %i i INNER JOIN %i s ON s.id = i.source_id
			LEFT JOIN %i d ON d.identity_hash = i.story_hash
			WHERE (%d = 0 OR i.source_id = %d)
			AND (%d = 0 OR (%d = -1 AND s.category_id = 0) OR s.category_id = %d)
			AND (%s = '' OR COALESCE(d.status,'new') = %s)
			AND (%s = '' OR i.discovered_at >= %s)
			AND (%s = '' OR i.discovered_at <= %s)
			GROUP BY COALESCE(NULLIF(i.story_hash,''),i.identity_hash)
			ORDER BY latest DESC, representative DESC LIMIT 51 OFFSET %d) g
			INNER JOIN %i i ON i.id = g.representative
			INNER JOIN %i s ON s.id = i.source_id
			LEFT JOIN %i d ON d.identity_hash = g.story_key
			ORDER BY g.latest DESC, g.representative DESC",
				$items,
				$sources,
				$decisions,
				$source,
				$source,
				$category,
				$category,
				$category,
				$status,
				$status,
				$dates['from'],
				$dates['from'],
				$dates['to'],
				$dates['to'],
				( max( 1, min( 300, (int) $page ) ) - 1 ) * 50,
				$items,
				$sources,
				$decisions
			)
		);
	}

	public static function coverage( $hash, $page = 1 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT i.*, s.name AS source_name, s.archived FROM %i i INNER JOIN %i s ON s.id = i.source_id WHERE i.story_hash = %s ORDER BY i.discovered_at DESC, i.id DESC LIMIT 51 OFFSET %d', self::table( 'items' ), self::table( 'sources' ), $hash, ( max( 1, min( 200, (int) $page ) ) - 1 ) * 50 ) );
	}

	public static function prune() {
		global $wpdb;
		$items     = self::table( 'items' );
		$decisions = self::table( 'decisions' );
		$wpdb->query( $wpdb->prepare( 'DELETE i FROM %i i LEFT JOIN %i d ON d.identity_hash = i.story_hash WHERE d.identity_hash IS NULL AND i.discovered_at < %s', $items, $decisions, gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		$cutoff = $wpdb->get_row( $wpdb->prepare( 'SELECT i.id, i.discovered_at FROM %i i LEFT JOIN %i d ON d.identity_hash = i.story_hash WHERE d.identity_hash IS NULL ORDER BY i.discovered_at DESC, i.id DESC LIMIT 1 OFFSET 4999', $items, $decisions ) );
		if ( $cutoff ) {
			$wpdb->query( $wpdb->prepare( 'DELETE i FROM %i i LEFT JOIN %i d ON d.identity_hash = i.story_hash WHERE d.identity_hash IS NULL AND (i.discovered_at < %s OR (i.discovered_at = %s AND i.id < %d))', $items, $decisions, $cutoff->discovered_at, $cutoff->discovered_at, $cutoff->id ) );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s LEFT JOIN %i i ON i.source_id = s.id WHERE s.archived = 1 AND i.id IS NULL', self::table( 'sources' ), $items ) );
	}
}
