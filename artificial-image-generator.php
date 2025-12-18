<?php
/**
 * Plugin Name:       Auto Image Generator
 * Plugin URI:        https://urldev.com/auto-image-generator
 * Description:       Automatically generate eye-catching featured images and thumbnails for your posts and pages. Boost your site's visual appeal and SEO effortlessly with dynamic, customizable image generation.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            UrlDev
 * Author URI:        https://urldev.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       auto-image-generator
 * Domain Path:       /languages
 * Tested up to:      6.8
 *
 * @package AutoImageGenerator
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

use AutoImageGenerator\Plugin;

defined( 'ABSPATH' ) || exit; // Prevent direct access.

/**
 * Optimized autoload classes.
 *
 * @since 1.0.0
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Get the plugin instance.
 *
 * @since 1.0.0
 * @return Plugin
 */
function aimg_auto_image_generator() {
	return Plugin::create( __FILE__, '1.0.0' );
}

// Initialize the plugin.
aimg_auto_image_generator();
