<?php
/**
 * Plugin Name:       BlogWriter
 * Plugin URI:        https://github.com/karun99/proto-collection
 * Description:       Automated AI blog content generation for WordPress using OpenRouter BYOK (Bring Your Own Key). Batch posts, scheduled publishing, OCR document extraction, AES-256 encryption and STRiX-compliant security.
 * Version:           1.0.0
 * Author:            Guiding Key Technologies
 * Author URI:        https://guidingkey.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       blogwriter
 * Requires at least: 5.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BLOGWRITER_VERSION', '1.0.0' );
define( 'BLOGWRITER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOGWRITER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BLOGWRITER_PLUGIN_FILE', __FILE__ );
define( 'BLOGWRITER_MIN_OPENROUTER_KEY', 50 );

require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-database.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-encryption.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-validator.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-logger.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-security.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-api-handler.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-cron-manager.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-ocr-handler.php';
require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-main.php';

register_activation_hook( __FILE__, array( 'BlogWriter_Database', 'install' ) );
register_deactivation_hook( __FILE__, array( 'BlogWriter_Cron_Manager', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'BlogWriter_Database', 'uninstall' ) );

/**
 * Boot the plugin.
 */
function blogwriter_boot() {
	if ( class_exists( 'BlogWriter_Main' ) ) {
		BlogWriter_Main::instance();
	}
}
add_action( 'plugins_loaded', 'blogwriter_boot' );
