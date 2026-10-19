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
	const SCHEMA = '1';

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
		) $charset;"
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
		) $charset;"
		);
		if ( $sources === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sources ) ) ) && $items === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $items ) ) ) ) {
			update_option( 'editorial_scout_schema', self::SCHEMA, false );
		}
	}

	public static function sources() {
		global $wpdb;
		$table = self::table( 'sources' );
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifier.
	}

	public static function source( $id ) {
		global $wpdb;
		$table = self::table( 'sources' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifier.
	}

	public static function save( $id, $name, $url, $enabled ) {
		global $wpdb;
		$old = $id ? self::source( $id ) : null;
		if ( ( $id && ! $old ) || ( ! $id && count( self::sources() ) >= 50 ) ) {
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
			$result = $wpdb->update( self::table( 'sources' ), $data, array( 'id' => $id ) );
			if ( false !== $result && $old->url !== $url ) {
				$wpdb->delete( self::table( 'items' ), array( 'source_id' => $id ), array( '%d' ) );
			}
			return false !== $result;
		}
		return false !== $wpdb->insert( self::table( 'sources' ), $data );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'items' ), array( 'source_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::table( 'sources' ), array( 'id' => $id ), array( '%d' ) );
	}

	public static function queue( $page ) {
		global $wpdb;
		$items   = self::table( 'items' );
		$sources = self::table( 'sources' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT i.*, s.name AS source_name FROM $items i INNER JOIN $sources s ON s.id = i.source_id ORDER BY i.discovered_at DESC, i.id DESC LIMIT 51 OFFSET %d", ( $page - 1 ) * 50 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifiers.
	}

	public static function prune() {
		global $wpdb;
		$table = self::table( 'items' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE discovered_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifier.
		$cutoff = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1 OFFSET 4999" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifier.
		if ( $cutoff ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id < %d", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal identifier.
		}
	}
}
