<?php
/**
 * Plugin Name:       Image Generator
 * Plugin URI:        https://beautifulplugins.com/plugins/image-generator-pro/
 * Description:       Generate AI-powered images automatically across your WordPress site. Create stunning visuals for posts, pages, and more with ease.
 * Version:           1.5.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            BeautifulPlugins
 * Author URI:        https://beautifulplugins.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       artificial-image-generator
 * Domain Path:       /languages
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Plugin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

// A development checkout has no vendor/ until `composer install` runs.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'Image Generator is missing its dependencies. Run "composer install" in the plugin folder, or install the plugin from WordPress.org.', 'artificial-image-generator' )
				);
			}
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Get the plugin instance.
 *
 * @since 1.0.0
 * @return Plugin The plugin instance.
 */
function artificial_image_generator() {
	return Plugin::create( __FILE__, '1.5.4' );
}

// Initialize the plugin.
artificial_image_generator();
