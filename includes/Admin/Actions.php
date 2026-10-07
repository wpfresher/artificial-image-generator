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
		add_action( 'admin_post_aimg_template_action', array( __CLASS__, 'template_action' ) );
	}

	/**
	 * URL of a template action from the templates list.
	 *
	 * @param string $action      duplicate, default, undefault, export or delete.
	 * @param int    $template_id Template ID.
	 *
	 * @since 1.7.0
	 * @return string
	 */
	public static function action_url( $action, $template_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'aimg_template_action',
					'do'       => $action,
					'template' => (int) $template_id,
				),
				admin_url( 'admin-post.php' )
			),
			'aimg_template_' . $action . '_' . (int) $template_id
		);
	}

	/**
	 * Run a template action from the templates list.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function template_action() {
		$action      = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$template_id = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;
		$list_url    = admin_url( 'admin.php?page=image-generator' );

		check_admin_referer( 'aimg_template_' . $action . '_' . $template_id );

		if ( ! current_user_can( 'manage_options' ) || ! aimg_get_template( $template_id ) ) {
			artificial_image_generator()->flash_notice( __( 'You do not have permission to process this action', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $list_url );
			exit;
		}

		switch ( $action ) {
			case 'duplicate':
				$copy = \ArtificialImageGenerator\Templates\Repository::duplicate( $template_id );
				if ( is_wp_error( $copy ) ) {
					artificial_image_generator()->flash_notice( $copy->get_error_message(), 'error' );
				} else {
					artificial_image_generator()->flash_notice( __( 'Template duplicated as a draft.', 'artificial-image-generator' ) );
				}
				break;

			case 'default':
			case 'undefault':
				$settings                        = (array) get_option( 'aimg_settings', array() );
				$settings['default_template_id'] = 'default' === $action ? $template_id : 0;
				update_option( 'aimg_settings', $settings );
				artificial_image_generator()->flash_notice(
					'default' === $action
						? __( 'Automatic featured images now use this template.', 'artificial-image-generator' )
						: __( 'Automatic featured images now use a random template.', 'artificial-image-generator' )
				);
				break;

			case 'export':
				$data = \ArtificialImageGenerator\Templates\Repository::export( $template_id );
				$name = sanitize_file_name( ( '' !== $data['title'] ? sanitize_title( $data['title'] ) : 'template' ) . '.json' );

				nocache_headers();
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $name . '"' );
				echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				exit;

			case 'delete':
				wp_delete_post( $template_id, true );
				artificial_image_generator()->flash_notice( __( 'Template deleted.', 'artificial-image-generator' ) );
				break;
		}

		wp_safe_redirect( $list_url );
		exit;
	}

	/**
	 * Update or create a new image template.
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
		$status      = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'publish';
		$status      = in_array( $status, array( 'publish', 'draft' ), true ) ? $status : 'publish';

		// Only ever update an existing template; any other post ID would be
		// converted into a template and lose its content.
		if ( $template_id && ! aimg_get_template( $template_id ) ) {
			artificial_image_generator()->flash_notice( __( 'The image template you are trying to update does not exist.', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $referer );
			exit;
		}

		// This form only knows the 1.x fields; saving would leave the design and its preview out of step.
		if ( $template_id && \ArtificialImageGenerator\Templates\Repository::has_document( $template_id ) ) {
			artificial_image_generator()->flash_notice( __( 'This template was designed in the Template Studio and cannot be edited with this form.', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $referer );
			exit;
		}

		if ( empty( $title ) ) {
			artificial_image_generator()->flash_notice( __( 'The title field is required.', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $referer );
			exit;
		}

		// Create or Update image template.
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
		$width            = isset( $_POST['width'] ) ? absint( $_POST['width'] ) : 0;
		$height           = isset( $_POST['height'] ) ? absint( $_POST['height'] ) : 0;
		$title_font_size  = isset( $_POST['title_font_size'] ) ? absint( $_POST['title_font_size'] ) : 0;
		$is_overlay_image = isset( $_POST['is_overlay_image'] ) ? 'yes' : 'no';
		$width            = $width > 0 ? min( $width, 5000 ) : 1200;
		$height           = $height > 0 ? min( $height, 5000 ) : 800;
		$title_font_size  = $title_font_size > 0 ? min( $title_font_size, 500 ) : AIMG_DEFAULT_FONT_SIZE;
		$overlay_images   = isset( $_POST['overlay_images'] ) ? self::sanitize_overlay_images( sanitize_text_field( wp_unslash( $_POST['overlay_images'] ) ) ) : '[]';
		$overlay_position = isset( $_POST['overlay_position'] ) ? sanitize_key( wp_unslash( $_POST['overlay_position'] ) ) : '';
		$overlay_position = in_array( $overlay_position, aimg_get_overlay_positions(), true ) ? $overlay_position : 'center-center';

		update_post_meta( $post, '_aimg_bg_colors', $bg_colors );
		update_post_meta( $post, '_aimg_width', $width );
		update_post_meta( $post, '_aimg_height', $height );
		update_post_meta( $post, '_aimg_title_font_size', $title_font_size );
		update_post_meta( $post, '_aimg_is_overlay_image', $is_overlay_image );
		update_post_meta( $post, '_aimg_overlay_images', $overlay_images );
		update_post_meta( $post, '_aimg_overlay_position', $overlay_position );

		// Generate the thumbnail image if the settings are saved successfully.
		if ( $post ) {
			$colors            = empty( $bg_colors ) ? '#e74c3c,#2ecc71,#9b59b6' : $bg_colors;
			$overlay_images    = 'yes' === $is_overlay_image ? json_decode( $overlay_images ) : array();
			$preview_image_url = aimg_generate_preview( $post, $colors, $width, $height, $overlay_images );

			// Update the preview image URL as post meta.
			update_post_meta( $post, '_aimg_preview_image_url', $preview_image_url );
		}

		// Flash success message and redirect.
		if ( $template_id ) {
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

	/**
	 * Reduce the submitted overlay list to a JSON array of image attachment IDs.
	 *
	 * @param string $raw JSON array of attachment IDs from the form.
	 *
	 * @since 1.5.4
	 * @return string JSON encoded array of attachment IDs.
	 */
	protected static function sanitize_overlay_images( $raw ) {
		$ids = json_decode( $raw, true );
		$ids = is_array( $ids ) ? array_unique( array_filter( array_map( 'absint', $ids ) ) ) : array();
		$ids = array_filter( $ids, 'wp_attachment_is_image' );

		return wp_json_encode( array_values( $ids ) );
	}
}
