<?php

namespace ArtificialImageGenerator\Admin;

use ArtificialImageGenerator\Admin\ListTables\TemplatesTable;

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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'set-screen-option', array( $this, 'screen_option' ), 10, 3 );
		add_action( 'load-toplevel_page_image-generator', array( $this, 'handle_list_table_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Image Generator', 'artificial-image-generator' ),
			__( 'Image Generator', 'artificial-image-generator' ),
			'manage_options',
			'image-generator',
			null,
			'dashicons-format-image',
			26
		);

		$load = add_submenu_page(
			'image-generator',
			__( 'Image Templates', 'artificial-image-generator' ),
			__( 'Image Templates', 'artificial-image-generator' ),
			'manage_options',
			'image-generator',
			array( $this, 'img_templates_page' ),
		);

		// Load screen options.
		add_action( 'load-' . $load, array( __CLASS__, 'load_pages' ) );
	}

	/**
	 * Set screen option.
	 *
	 * @param mixed  $status Screen option value. Default false.
	 * @param string $option Option name.
	 * @param mixed  $value New option value.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function screen_option( $status, $option, $value ) {
		$options = apply_filters(
			'aimg_set_screen_options',
			array(
				'aimg_img_templates_per_page',
			)
		);
		if ( in_array( $option, $options, true ) ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Load pages & set screen options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function load_pages() {
		$screen = get_current_screen();

		if ( 'toplevel_page_image-generator' === $screen->id ) {
			add_screen_option(
				'per_page',
				array(
					'label'   => __( 'Image templates per page', 'artificial-image-generator' ),
					'default' => 20,
					'option'  => 'aimg_img_templates_per_page',
				)
			);
		}
	}

	/**
	 * Render image templates page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function img_templates_page() {
		$edit     = self::is_edit_screen();
		$template = ! empty( $edit ) ? aimg_get_template( $edit ) : '';

		if ( ! empty( $edit ) && empty( $template ) ) {
			wp_safe_redirect( remove_query_arg( 'edit' ) );
			exit();
		}

		if ( self::is_add_screen() ) {
			include __DIR__ . '/views/add-img-template.php';
		} elseif ( $edit ) {
			include __DIR__ . '/views/edit-img-template.php';
		} else {
			$list_table = new TemplatesTable();
			$list_table->prepare_items();
			include __DIR__ . '/views/img-templates.php';
		}
	}

	/**
	 * Check whether current page is add screen or not.
	 *
	 * @since 1.0.0
	 * @return bool True if add screen, false otherwise.
	 */
	public static function is_add_screen() {
		return filter_input( INPUT_GET, 'add' ) !== null;
	}

	/**
	 * Check whether current page is edit screen or not.
	 *
	 * @since 1.0.0
	 * @return false|int The ID if edit screen, false otherwise.
	 */
	public static function is_edit_screen() {
		return filter_input( INPUT_GET, 'edit', FILTER_VALIDATE_INT );
	}

	/**
	 * Handle list table actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_list_table_actions() {

		if ( ! current_user_can( 'manage_options' ) ) {
			artificial_image_generator()->flash_notice( esc_html__( 'You do not have permission to perform this action.', 'artificial-image-generator' ), 'error' );
			$redirect_url = remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$list_table = new TemplatesTable();
		$list_table->process_bulk_action();

		if ( 'delete' === $list_table->current_action() ) {
			check_admin_referer( 'bulk-templates' );

			$ids       = isset( $_GET['ids'] ) ? map_deep( wp_unslash( $_GET['ids'] ), 'intval' ) : array();
			$ids       = wp_parse_id_list( $ids );
			$performed = 0;

			foreach ( $ids as $id ) {
				$template = aimg_get_template( $id );
				if ( $template && wp_delete_post( $template->ID, true ) ) {
					++$performed;
				}
			}

			if ( ! empty( $performed ) ) {
				// translators: %s: number of accounts.
				artificial_image_generator()->flash_notice( sprintf( esc_html__( '%s item(s) deleted successfully.', 'artificial-image-generator' ), number_format_i18n( $performed ) ) );
			}

			if ( ! headers_sent() ) {
				// Redirect to avoid resubmission.
				$redirect_url = remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ) );
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_image-generator' !== $hook ) {
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
