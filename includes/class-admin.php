<?php
/**
 * WordPress admin screen.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the Snippet Studio admin page and loads the built application.
 */
class Admin {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_notices', array( $this, 'woody_notice' ) );
	}

	/**
	 * Menu items.
	 */
	public function menu() {
		add_menu_page(
			__( 'Snippet Studio', 'snippet-studio' ),
			__( 'Snippet Studio', 'snippet-studio' ),
			$this->menu_cap(),
			'snippet-studio',
			array( $this, 'render' ),
			'dashicons-editor-code',
			65
		);

		if ( post_type_exists( Capabilities::post_type() ) ) {
			add_submenu_page(
				'edit.php?post_type=' . Capabilities::post_type(),
				__( 'Snippet Studio', 'snippet-studio' ),
				__( 'Snippet Studio', 'snippet-studio' ),
				$this->menu_cap(),
				'snippet-studio',
				array( $this, 'render' )
			);
		}
	}

	/**
	 * Page markup. WordPress still renders its own admin menu and toolbar.
	 */
	public function render() {
		if ( ! Capabilities::can_read() ) {
			wp_die( esc_html__( 'You do not have permission to open Snippet Studio.', 'snippet-studio' ) );
		}

		echo '<div id="snippet-studio-app"></div>';
	}

	/**
	 * Enqueue the production build on this screen only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		if ( ! $this->is_screen( $hook ) ) {
			return;
		}

		$script = SNIPPET_STUDIO_DIR . 'build/index.js';
		if ( ! file_exists( $script ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Snippet Studio is installed, but its editor bundle has not been built.', 'snippet-studio' ) . '</p></div>';
				}
			);
			return;
		}

		$asset_path = SNIPPET_STUDIO_DIR . 'build/index.asset.php';
		$asset      = file_exists( $asset_path ) ? include $asset_path : array(
			'dependencies' => array( 'wp-element', 'wp-api-fetch' ),
			'version'      => SNIPPET_STUDIO_VERSION,
		);

		wp_enqueue_script(
			'snippet-studio',
			SNIPPET_STUDIO_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$style = is_rtl() && file_exists( SNIPPET_STUDIO_DIR . 'build/index-rtl.css' ) ? 'index-rtl.css' : 'index.css';
		if ( ! file_exists( SNIPPET_STUDIO_DIR . 'build/' . $style ) ) {
			$style = 'style-index.css';
		}
		if ( file_exists( SNIPPET_STUDIO_DIR . 'build/' . $style ) ) {
			wp_enqueue_style(
				'snippet-studio',
				SNIPPET_STUDIO_URL . 'build/' . $style,
				array(),
				$asset['version']
			);
		}

		wp_add_inline_style(
			'snippet-studio',
			$this->screen_css()
		);
	}

	/**
	 * Body class so the screen CSS can reach WordPress admin containers.
	 *
	 * @param string $classes Classes.
	 */
	public function body_class( $classes ) {
		if ( isset( $_GET['page'] ) && 'snippet-studio' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$classes .= ' sst-screen';
		}
		return $classes;
	}

	/**
	 * Notice when Woody is missing. Shown on the Studio screen only.
	 */
	public function woody_notice() {
		if ( ! isset( $_GET['page'] ) || 'snippet-studio' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( Capabilities::woody_ready() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Snippet Studio needs Woody Code Snippets to be active. The original Woody plugin was not found.', 'snippet-studio' ) . '</p></div>';
	}

	/**
	 * Whether the current admin page is Snippet Studio.
	 *
	 * @param string $hook Hook suffix.
	 */
	private function is_screen( $hook ) {
		return false !== strpos( (string) $hook, 'snippet-studio' );
	}

	/**
	 * Capability used for the menu.
	 */
	private function menu_cap() {
		if ( current_user_can( Capabilities::edit_cap() ) ) {
			return Capabilities::edit_cap();
		}
		return 'manage_options';
	}

	/**
	 * Layout CSS that only applies on this admin screen.
	 */
	private function screen_css() {
		return '
			body.sst-screen #wpcontent { padding-left: 0; }
			body.sst-screen #wpbody-content { padding-bottom: 0; }
			body.sst-screen #wpfooter { display: none; }
			body.sst-screen #wpbody-content > .notice,
			body.sst-screen #wpbody-content > .update-nag { margin: 8px 12px 0; }
			#snippet-studio-app { height: calc(100vh - 32px); min-height: 640px; }
			@media (max-width: 782px) {
				#snippet-studio-app { height: calc(100vh - 46px); }
			}
		';
	}
}
