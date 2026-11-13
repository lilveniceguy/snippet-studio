<?php
/**
 * Plugin Name: Snippet Studio
 * Description: A VS Code-inspired editor for Woody Code Snippets. Runs beside Woody and does not replace it.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Jose Lopez
 * Author URI: https://joselopez.co
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snippet-studio
 *
 * @package Snippet_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SNIPPET_STUDIO_VERSION', '1.0.0' );
define( 'SNIPPET_STUDIO_FILE', __FILE__ );
define( 'SNIPPET_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNIPPET_STUDIO_URL', plugin_dir_url( __FILE__ ) );

require_once SNIPPET_STUDIO_DIR . 'includes/class-activator.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-capabilities.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-woody-adapter.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-revisions.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-validator.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-repository.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-rest-controller.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-admin.php';
require_once SNIPPET_STUDIO_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Snippet_Studio\\Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Snippet_Studio\Plugin::instance()->register();
	}
);
