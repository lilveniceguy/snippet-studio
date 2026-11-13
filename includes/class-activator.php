<?php
/**
 * Activation and schema.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the independent revision table.
 */
class Activator {

	const DB_VERSION = '1';

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		self::install_table();
	}

	/**
	 * Create or upgrade the revision table.
	 */
	public static function install_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snippet_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_gmt datetime NOT NULL,
			title text NOT NULL,
			code longtext NOT NULL,
			meta_json longtext NOT NULL,
			note varchar(191) NOT NULL DEFAULT '',
			kind varchar(20) NOT NULL DEFAULT 'save',
			PRIMARY KEY  (id),
			KEY snippet_created (snippet_id, created_gmt)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'snippet_studio_db_version', self::DB_VERSION, false );
	}

	/**
	 * Revision table name, using the current site prefix.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'snippet_studio_revisions';
	}

	/**
	 * Install the table when the stored schema is older than this version.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'snippet_studio_db_version' );
		if ( self::DB_VERSION !== $installed ) {
			self::install_table();
		}
	}
}
