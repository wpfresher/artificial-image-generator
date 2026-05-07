<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class Editor
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator\Admin
 */
class Editor {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @since 1.0.0
	 * return void
	 */
	public function enqueue_assets() {
		// Enqueue styles and scripts.
		wp_enqueue_style( 'aimg-editor', AIMG_URL . 'assets/css/block-editor.css', array(), AIMG_VERSION );
		wp_enqueue_script('aimg-editor', AIMG_URL . 'assets/js/block-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-hooks', 'wp-api-fetch', 'wp-i18n', 'wp-compose', 'wp-block-editor', 'wp-plugins', 'wp-edit-post', 'wp-data'), AIMG_VERSION, true );
		// Pass the REST endpoint base URL to JS
		wp_localize_script(
			'aimg-editor',
			'aimgData',
			array(
				'endpoint' => rest_url( 'aimg/v1/generate' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
