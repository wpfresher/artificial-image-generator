<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The main admin class.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator/Admin
 */
class Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add menu page.
		add_action( 'admin_menu', array( $this, 'add_menu' ) );

		// Add admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Artificial Image Generator', 'artificial-image-generator' ),
			__( 'Artificial Image Generator', 'artificial-image-generator' ),
			'manage_options',
			'artificial-image-generator',
			array( $this, 'admin_page' ),
			'dashicons-format-image',
			80
		);
	}

	/**
	 * Process email submission.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_page() {
		include __DIR__ . '/views/admin-page.php';
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_scripts( $hook ) {
		if ( 'toplevel_page_artificial-image-generator' !== $hook ) {
			return;
		}

		// Enqueue styles and scripts.
		wp_enqueue_style( 'aimg-admin', AIMG_URL . 'assets/css/admin.css', array(), AIMG_VERSION );

		// Enqueue media uploader scripts.
		wp_enqueue_media();
		wp_enqueue_script( 'aimg-admin', AIMG_URL . 'assets/js/admin.js', array( 'jquery' ), AIMG_VERSION, true );

		// Localization for admin scripts.
		wp_localize_script(
			'aimg-admin',
			'aimg_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aimg_nonce' ),
			)
		);
	}
}
