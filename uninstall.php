<?php
/**
 * Remove Snippet Studio data. Woody snippets are left untouched.
 *
 * @package Snippet_Studio
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'snippet_studio_revisions';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'snippet_studio_db_version' );

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
		'snippet_studio_prefs'
	)
);
