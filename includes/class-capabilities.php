<?php
/**
 * Permission checks.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps Snippet Studio actions onto WordPress and Woody capabilities.
 */
class Capabilities {

	/**
	 * Whether the file editor lock is enabled.
	 */
	public static function file_edit_disabled() {
		return ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			|| ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
	}

	/**
	 * Woody post type slug, without assuming the plugin constant exists.
	 */
	public static function post_type() {
		return defined( 'WINP_SNIPPETS_POST_TYPE' ) ? WINP_SNIPPETS_POST_TYPE : 'wbcr-snippets';
	}

	/**
	 * Capability Woody grants for editing its post type.
	 */
	public static function edit_cap() {
		return 'edit_' . self::post_type() . 's';
	}

	/**
	 * Whether Woody's post type is registered.
	 */
	public static function woody_ready() {
		return defined( 'WINP_SNIPPETS_POST_TYPE' ) && post_type_exists( self::post_type() );
	}

	/**
	 * Whether the user may open the editor and read snippets.
	 */
	public static function can_read() {
		if ( ! is_user_logged_in() || ! self::woody_ready() ) {
			return false;
		}

		return current_user_can( self::edit_cap() ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the user may change snippet code or execution settings.
	 *
	 * @param int $post_id Existing snippet, or 0 when creating.
	 */
	public static function can_write( $post_id = 0 ) {
		if ( ! self::can_read() || self::file_edit_disabled() ) {
			return false;
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}

		$post_id = (int) $post_id;
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( self::edit_cap() ) || current_user_can( 'manage_options' );
	}

	/**
	 * REST permission callback for read routes.
	 */
	public static function rest_read() {
		if ( self::can_read() ) {
			return true;
		}

		return new \WP_Error(
			'snippet_studio_forbidden',
			__( 'You do not have permission to view snippets.', 'snippet-studio' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * REST permission callback for write routes.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_write( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		if ( self::can_write( $post_id ) ) {
			return true;
		}

		if ( self::file_edit_disabled() ) {
			return new \WP_Error(
				'snippet_studio_locked',
				__( 'WordPress file editing is disabled on this site, so Snippet Studio cannot save changes.', 'snippet-studio' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return new \WP_Error(
				'snippet_studio_unfiltered',
				__( 'Your account cannot store unfiltered code.', 'snippet-studio' ),
				array( 'status' => 403 )
			);
		}

		return new \WP_Error(
			'snippet_studio_forbidden',
			__( 'You do not have permission to edit snippets.', 'snippet-studio' ),
			array( 'status' => 403 )
		);
	}
}
