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
	const SCHEMA = '2';

	public static function table( $kind ) {
		global $wpdb;
		return $wpdb->prefix . 'editorial_scout_' . ( 'sources' === $kind ? 'sources' : 'items' );
	}

	public static function install() {
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
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY due (enabled,next_check)
		) ENGINE=InnoDB $charset;"
		);
		dbDelta(
			"CREATE TABLE $items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			identity_hash char(64) NOT NULL,
			title varchar(500) NOT NULL,
			url text NOT NULL,
			published_at datetime DEFAULT NULL,
			discovered_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_identity (source_id,identity_hash),
			KEY discovered (discovered_at,id)
		) ENGINE=InnoDB $charset;"
		);
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
		$definitions      = array(
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
			),
			'items'   => array(
				'id'            => 'bigint unsigned',
				'source_id'     => 'bigint unsigned',
				'identity_hash' => 'char(64)',
				'title'         => 'varchar(500)',
				'url'           => 'text',
				'published_at'  => 'datetime',
				'discovered_at' => 'datetime',
			),
		);
		$required_indexes = array(
			'sources' => array(
				'PRIMARY'  => array( 'id' ),
				'url_hash' => array( 'url_hash' ),
				'due'      => array( 'enabled', 'next_check' ),
			),
			'items'   => array(
				'PRIMARY'         => array( 'id' ),
				'source_identity' => array( 'source_id', 'identity_hash' ),
				'discovered'      => array( 'discovered_at', 'id' ),
			),
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
				if ( in_array( $name, array( 'PRIMARY', 'url_hash', 'source_identity' ), true ) && 0 !== (int) $index['Non_unique'] ) {
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

	public static function sources() {
		global $wpdb;
		$table = self::table( 'sources' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 50', $table ) );
	}

	public static function source( $id ) {
		global $wpdb;
		$table = self::table( 'sources' );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) );
	}

	public static function save( $id, $name, $url, $enabled ) {
		global $wpdb;
		$old = $id ? self::source( $id ) : null;
		if ( ( $id && ! $old ) || ( ! $id && count( self::sources() ) >= 50 ) ) {
			return false;
		}
		// Avoid logging an expected duplicate error; the unique index remains authoritative.
		$table = self::table( 'sources' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s AND id <> %d', $table, hash( 'sha256', $url ), $id ) ) ) {
			return false;
		}
		$data = array(
			'name'       => $name,
			'url'        => $url,
			'url_hash'   => hash( 'sha256', $url ),
			'enabled'    => (int) $enabled,
			'next_check' => gmdate( 'Y-m-d H:i:s' ),
			'failures'   => 0,
			'status'     => 'pending',
		);
		if ( $id ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return false;
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
		if ( false === $wpdb->delete( self::table( 'items' ), array( 'source_id' => $id ), array( '%d' ) ) || false === $wpdb->delete( self::table( 'sources' ), array( 'id' => $id ), array( '%d' ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		return true;
	}

	public static function queue( $page ) {
		global $wpdb;
		$items   = self::table( 'items' );
		$sources = self::table( 'sources' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT i.*, s.name AS source_name FROM %i i INNER JOIN %i s ON s.id = i.source_id ORDER BY i.discovered_at DESC, i.id DESC LIMIT 51 OFFSET %d', $items, $sources, ( max( 1, min( 100, (int) $page ) ) - 1 ) * 50 ) );
	}

	public static function prune() {
		global $wpdb;
		$table = self::table( 'items' );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE discovered_at < %s', $table, gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		$cutoff = $wpdb->get_row( $wpdb->prepare( 'SELECT id, discovered_at FROM %i ORDER BY discovered_at DESC, id DESC LIMIT 1 OFFSET 4999', $table ) );
		if ( $cutoff ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE discovered_at < %s OR (discovered_at = %s AND id < %d)', $table, $cutoff->discovered_at, $cutoff->discovered_at, $cutoff->id ) );
		}
	}
}
