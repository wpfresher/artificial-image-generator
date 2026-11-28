<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The Bulk Generate page.
 *
 * @since 1.9.1
 * @package ArtificialImageGenerator\Admin
 */
class Bulk {

	/**
	 * Screen ID of the page.
	 *
	 * @var string
	 */
	const HOOK = 'image-generator_page_aimg-bulk';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add the page under the plugin menu.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'image-generator',
			__( 'Bulk Generate', 'artificial-image-generator' ),
			__( 'Bulk Generate', 'artificial-image-generator' ),
			/** This filter is documented in includes/Bulk/RestController.php */
			apply_filters( 'aimg_bulk_capability', 'manage_options' ),
			'aimg-bulk',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue the app on the page.
	 *
	 * @param string $hook Page hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( self::HOOK !== $hook || ! file_exists( AIMG_ASSETS_PATH . 'js/bulk.asset.php' ) ) {
			return;
		}

		$asset = include AIMG_ASSETS_PATH . 'js/bulk.asset.php';

		wp_enqueue_script( 'aimg-bulk', AIMG_ASSETS_URL . 'js/bulk.js', array_merge( $asset['dependencies'], array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready' ) ), $asset['version'], true );
		wp_set_script_translations( 'aimg-bulk', 'artificial-image-generator', AIMG_PATH . 'languages' );
		wp_enqueue_style( 'aimg-bulk', AIMG_ASSETS_URL . 'css/bulk.css', array( 'wp-components' ), $asset['version'] );
		wp_enqueue_style( 'aimg-admin', AIMG_ASSETS_URL . 'css/admin.css', array(), AIMG_VERSION );

		wp_add_inline_script(
			'aimg-bulk',
			'window.aimgBulk = ' . wp_json_encode( array( 'settingsUrl' => admin_url( 'admin.php?page=aimg-settings' ) ) ) . ';',
			'before'
		);
	}

	/**
	 * Render the app root.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap aimg-page">
			<div id="aimg-bulk">
				<h1><?php esc_html_e( 'Bulk Generate', 'artificial-image-generator' ); ?></h1>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Loading… If this message does not go away, make sure JavaScript is enabled and reload the page.', 'artificial-image-generator' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
