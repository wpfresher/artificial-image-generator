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
		add_action( 'admin_post_aimg_update_template', array( __CLASS__, 'update_template' ) );
	}

	/**
	 * Add product tabs data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function update_template() {
		check_admin_referer( 'aimg_update_template' );
		$referer = wp_get_referer();

		if ( ! current_user_can( 'manage_options' ) ) {
			artificial_image_generator()->flash_notice( __( 'You do not have permission to process this action', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $referer );
			exit;
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'publish';

		// Create or Update Product Tab.
		$post_args = array(
			'post_type'    => 'aimg_template',
			'post_title'   => wp_strip_all_tags( $title ),
			'post_name'    => sanitize_title( $title ),
			'post_content' => '',
			'post_status'  => $status,
		);

		if ( $template_id ) {
			$post_args['ID'] = $template_id;
		}

		// Create or update the post.
		$post = wp_insert_post( $post_args );

		if ( is_wp_error( $post ) ) {
			artificial_image_generator()->flash_notice( $post->get_error_message(), 'error' );
			wp_safe_redirect( $referer );
			exit;
		}

		// Save meta fields.
		$bg_colors        = isset( $_POST['bg_colors'] ) ? sanitize_text_field( wp_unslash( $_POST['bg_colors'] ) ) : '';
		$width            = isset( $_POST['width'] ) ? absint( $_POST['width'] ) : 1200;
		$height           = isset( $_POST['height'] ) ? absint( $_POST['height'] ) : 800;
		$title_font_size  = isset( $_POST['title_font_size'] ) ? absint( $_POST['title_font_size'] ) : 40;
		$is_overlay_image = isset( $_POST['is_overlay_image'] ) ? 'yes' : 'no';
		$overlay_images   = isset( $_POST['overlay_images'] ) ? sanitize_text_field( wp_unslash( $_POST['overlay_images'] ) ) : '';
		$overlay_position = isset( $_POST['overlay_position'] ) ? sanitize_text_field( wp_unslash( $_POST['overlay_position'] ) ) : 'center-center';

		update_post_meta( $post, '_aimg_bg_colors', $bg_colors );
		update_post_meta( $post, '_aimg_width', $width );
		update_post_meta( $post, '_aimg_height', $height );
		update_post_meta( $post, '_aimg_title_font_size', $title_font_size );
		update_post_meta( $post, '_aimg_is_overlay_image', $is_overlay_image );
		update_post_meta( $post, '_aimg_overlay_images', $overlay_images );
		update_post_meta( $post, '_aimg_overlay_position', $overlay_position );

		// Flash success message and redirect.
		if ( $template_id ) {
			// Generate the thumbnail image if the settings are saved.
			$colors            = empty( $bg_colors ) ? '#e74c3c,#2ecc71,#9b59b6' : $bg_colors;
			$overlay_images    = json_decode( $overlay_images );
			$preview_image_url = aimg_generate_preview( 'Dynamic Post Title Will be Available Here', $colors, $width, $height, $overlay_images, $post );

			// Update the preview image URL as post meta.
			update_post_meta( $post, '_aimg_preview_image_url', $preview_image_url );

			artificial_image_generator()->flash_notice( __( 'Image template updated successfully.', 'artificial-image-generator' ) );
		} else {
			artificial_image_generator()->flash_notice( __( 'Image template added successfully.', 'artificial-image-generator' ) );
		}

		$referer = add_query_arg(
			array( 'edit' => absint( $post ) ),
			remove_query_arg( 'add', $referer )
		);

		wp_safe_redirect( $referer );
		exit;
	}
}
