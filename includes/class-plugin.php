<?php
/**
 * Plugin bootstrap.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires admin and REST pieces.
 */
class Plugin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'on_init' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		( new Admin() )->register();
	}

	/**
	 * Schema check after WordPress is ready.
	 */
	public function on_init() {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			Activator::maybe_upgrade();
		}
	}

	/**
	 * REST routes.
	 */
	public function register_rest() {
		( new Rest_Controller( new Repository() ) )->register();
	}
}
