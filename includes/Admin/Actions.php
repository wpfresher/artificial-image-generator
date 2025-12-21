<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The admin Actions class.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator/Admin
 */
class Actions {

	/**
	 * Actions constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_post_aimg_artificial_image_generator', array( $this, 'save_artificial_image_generator_settings' ) );
		add_action( 'wp_ajax_aimg_save_overlay_images', array( $this, 'save_overlay_images' ) );
		add_action( 'wp_ajax_aimg_remove_overlay_image', array( $this, 'remove_overlay_image' ) );
	}

	/**
	 * Save auto image generator settings.
	 *
	 * @since 1.0.0
	 */
	public function save_artificial_image_generator_settings() {
		check_admin_referer( 'aimg_artificial_image_generator' );
		$referer = wp_get_referer();

		if ( ! current_user_can( 'manage_options' ) ) {
			artificial_image_generator()->flash_notice( esc_html__( 'You do not have permission to perform this action.', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $referer );
			exit();
		}

		$bg_colors        = isset( $_POST['bg_colors'] ) ? sanitize_text_field( wp_unslash( $_POST['bg_colors'] ) ) : '';
		$width            = isset( $_POST['width'] ) ? absint( $_POST['width'] ) : 1200;
		$height           = isset( $_POST['height'] ) ? absint( $_POST['height'] ) : 800;
		$title_font_size  = isset( $_POST['title_font_size'] ) ? absint( $_POST['title_font_size'] ) : 40;
		$is_overlay_image = isset( $_POST['is_overlay_image'] ) ? 'yes' : 'no';
		$overlay_position = isset( $_POST['overlay_position'] ) ? sanitize_text_field( wp_unslash( $_POST['overlay_position'] ) ) : 'center-center';

		update_option( 'aimg_bg_colors', $bg_colors );
		update_option( 'aimg_width', $width );
		update_option( 'aimg_height', $height );
		update_option( 'aimg_title_font_size', $title_font_size );
		update_option( 'aimg_is_overlay_image', $is_overlay_image );
		update_option( 'aimg_overlay_position', $overlay_position );

		// Generate the thumbnail image if the settings are saved.
		$colors            = empty( $bg_colors ) ? '#e74c3c,#2ecc71,#9b59b6' : $bg_colors;
		$overlay_images    = get_option( 'aimg_overlay_images', array() );
		$preview_image_url = aimg_generate_preview( 'Dynamic Post Title Will be Available Here', $colors, $width, $height, $overlay_images );

		// Save the preview image URL in the options.
		update_option( 'aimg_preview_image_url', $preview_image_url );

		artificial_image_generator()->flash_notice( esc_html__( 'Settings saved successfully.', 'artificial-image-generator' ), 'success' );
		wp_safe_redirect( $referer );
		exit();
	}

	/**
	 * Save overlay images via AJAX.
	 *
	 * @since 1.0.0
	 */
	public function save_overlay_images() {
		check_ajax_referer( 'aimg_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'artificial-image-generator' ) );
		}

		$images = isset( $_POST['overlay_images'] ) ? map_deep( wp_unslash( $_POST['overlay_images'] ), 'sanitize_text_field' ) : array();

		// Check if overlay images are set and is an array. Then check the images id and save them and send a success response with the ids.
		if ( is_array( $images ) && ! empty( $images ) ) {
			$image_ids = array_map(
				function ( $image ) {
					return absint( $image['id'] ?? 0 );
				},
				$images
			);

			// Get the existing overlay images from the database.
			$existing_image_ids = get_option( 'aimg_overlay_images', array() );

			// Merge existing and new image IDs, ensuring uniqueness.
			$image_ids = array_unique( array_merge( $existing_image_ids, $image_ids ) );

			// Update the option with the new image IDs.
			update_option( 'aimg_overlay_images', $image_ids );

			wp_send_json_success(
				array( 'message' => __( 'Overlay images saved successfully.', 'artificial-image-generator' ) )
			);
		}

		wp_send_json_error( __( 'No overlay images provided.', 'artificial-image-generator' ) );
	}

	/**
	 * Remove an overlay image via AJAX.
	 *
	 * @since 1.0.0
	 */
	public function remove_overlay_image() {
		check_ajax_referer( 'aimg_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'artificial-image-generator' ) );
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : null;

		if ( $image_id ) {
			$existing_images = get_option( 'aimg_overlay_images', array() );

			if ( in_array( $image_id, $existing_images, true ) ) {
				$existing_images = array_diff( $existing_images, array( $image_id ) );
				update_option( 'aimg_overlay_images', $existing_images );

				wp_send_json_success(
					array( 'message' => __( 'Overlay image removed successfully.', 'artificial-image-generator' ) )
				);
			} else {
				wp_send_json_error( __( 'Overlay image not found.', 'artificial-image-generator' ) );
			}
		}

		wp_send_json_error( __( 'Invalid overlay image ID.', 'artificial-image-generator' ) );
	}
}
