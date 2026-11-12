<?php
/**
 * Database tables: readers, book codes, sessions, saved game progress.
 * Readers are kept apart from WordPress users on purpose:
 * a book code never gives access to the rest of the site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTSI_DB {

	const SCHEMA_VERSION = '3';

	public static function readers() {
		global $wpdb;
		return $wpdb->prefix . 'htsi_readers';
	}

	public static function codes() {
		global $wpdb;
		return $wpdb->prefix . 'htsi_codes';
	}

	public static function sessions() {
		global $wpdb;
		return $wpdb->prefix . 'htsi_sessions';
	}

	/** Saved results, one row per reader and game (for example THE FINAL TEST rounds). */
	public static function progress() {
		global $wpdb;
		return $wpdb->prefix . 'htsi_progress';
	}

	public static function maybe_upgrade() {
		if ( get_option( 'htsi_schema_version' ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . self::readers() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			name varchar(100) NOT NULL DEFAULT '',
			news_consent tinyint(1) NOT NULL DEFAULT 0,
			consent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			last_seen datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) $charset;"
		);

		dbDelta(
			'CREATE TABLE ' . self::codes() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(20) NOT NULL,
			volume tinyint(3) unsigned NOT NULL,
			batch varchar(60) NOT NULL DEFAULT '',
			reader_id bigint(20) unsigned DEFAULT NULL,
			claimed_at datetime DEFAULT NULL,
			given_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY reader_id (reader_id)
		) $charset;"
		);

		dbDelta(
			'CREATE TABLE ' . self::sessions() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reader_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			last_used datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY reader_id (reader_id)
		) $charset;"
		);

		dbDelta(
			'CREATE TABLE ' . self::progress() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reader_id bigint(20) unsigned NOT NULL,
			game varchar(40) NOT NULL,
			data longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reader_game (reader_id,game)
		) $charset;"
		);

		update_option( 'htsi_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function now() {
		return current_time( 'mysql', true );
	}
}
