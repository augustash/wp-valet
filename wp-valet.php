<?php
/**
 * Plugin Name: WP Valet
 * Description: Extends the core command palette with a Shift+Space hotkey, front-end support and site-specific commands, mirroring the Valet module used on our Drupal sites.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: wp-valet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_VALET_VERSION', '1.0.0' );
define( 'WP_VALET_FILE', __FILE__ );
define( 'WP_VALET_DIR', plugin_dir_path( __FILE__ ) );

require_once WP_VALET_DIR . 'includes/menu.php';
require_once WP_VALET_DIR . 'includes/commands.php';
require_once WP_VALET_DIR . 'includes/assets.php';

/**
 * Capability required to use Valet. Filterable via `wp_valet_capability`.
 */
function wp_valet_capability(): string {
	return (string) apply_filters( 'wp_valet_capability', 'edit_posts' );
}

/**
 * Whether the current user may use Valet.
 */
function wp_valet_user_can(): bool {
	return is_user_logged_in() && current_user_can( wp_valet_capability() );
}
