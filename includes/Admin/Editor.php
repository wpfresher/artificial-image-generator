<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Class Editor.
 *
 * Wires up the block editor integration: enqueues the editor script/style and
 * exposes the data the JS needs (REST endpoints, nonce, settings link, and
 * whether an API key is configured).
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator\Admin
 */
class Editor {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'aimg-editor',
			AIMG_URL . 'assets/css/block-editor.css',
			array( 'wp-components' ),
			AIMG_VERSION
		);

		wp_enqueue_script(
			'aimg-editor',
			AIMG_URL . 'assets/js/block-editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-components',
				'wp-hooks',
				'wp-api-fetch',
				'wp-i18n',
				'wp-compose',
				'wp-block-editor',
				'wp-plugins',
				'wp-edit-post',
				'wp-data',
			),
			AIMG_VERSION,
			true
		);

		// Make the script translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'aimg-editor', 'artificial-image-generator' );
		}

		$has_api_key = ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY )
			|| ! empty( aimg_get_settings( 'api_key', '' ) );

		wp_localize_script(
			'aimg-editor',
			'aimgData',
			array(
				'endpoints' => array(
					'generate'  => rest_url( 'aimg/v1/generate' ),
					'templates' => rest_url( 'aimg/v1/templates' ),
				),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'settings'  => array(
					'hasApiKey'  => (bool) $has_api_key,
					'settingsUrl' => admin_url( 'admin.php?page=aimg-settings' ),
				),
			)
		);
	}
}
