<?php
/**
 * Plugin Name:       Image Generator
 * Plugin URI:        https://beautifulplugins.com/image-generator/
 * Description:       Generate AI-powered images automatically across your WordPress site. Create stunning visuals for posts, pages, and more with ease.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.9
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

// Autoload optimized classes.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Get the plugin instance.
 *
 * @since 1.0.0
 * @return Plugin The plugin instance.
 */
function artificial_image_generator() {
	return Plugin::create( __FILE__, '1.3.0' );
}

// Initialize the plugin.
artificial_image_generator();
