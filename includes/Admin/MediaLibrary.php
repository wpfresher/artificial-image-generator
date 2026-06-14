<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Class MediaLibrary.
 *
 * Wires up the Media Library integration: enqueues the generator script/style
 * on the Media Library (upload.php) and Add New Media (media-new.php) screens so
 * the same "Generate Image" modal used in the block editor is available there.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator\Admin
 */
class MediaLibrary {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets on the Media Library and Add New Media screens.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'upload.php', 'media-new.php' ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		wp_enqueue_style(
			'aimg-media-library',
			AIMG_URL . 'assets/css/media-library.css',
			array( 'wp-components' ),
			AIMG_VERSION
		);

		wp_enqueue_script(
			'aimg-media-library',
			AIMG_URL . 'assets/js/media-library.js',
			array(
				'wp-element',
				'wp-components',
				'wp-api-fetch',
				'wp-i18n',
				'wp-data',
			),
			AIMG_VERSION,
			true
		);

		// Make the script translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'aimg-media-library', 'artificial-image-generator' );
		}

		wp_localize_script( 'aimg-media-library', 'aimgData', aimg_get_js_data() );
	}
}
