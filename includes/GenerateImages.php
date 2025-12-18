<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Class GenerateImages
 *
 * This class is responsible for generating thumbnails.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator
 */
class GenerateImages {

	/**
	 * GenerateImages constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'save_post', array( $this, 'generate_thumbnails' ) );
	}

	/**
	 * Generate a thumbnail image using GD library while saving a post.
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @since 1.0.0
	 */
	public function generate_thumbnails( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		if ( 'post' === $post_type && 'yes' !== aimg_get_settings( 'is_post_thumbnail', 'yes' ) ) {
			return;
		}

		if ( 'page' === $post_type && 'yes' !== aimg_get_settings( 'is_page_thumbnail' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip auto-drafts.
		if ( 'auto-draft' === get_post_status( $post_id ) ) {
			return;
		}

		// Check if the post already has a thumbnail or not.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		$title = get_the_title( $post_id );

		// Check if the title is empty.
		if ( empty( $title ) ) {
			return;
		}

		$colors         = get_option( 'aimg_bg_colors', '#e74c3c,#2ecc71,#9b59b6' );
		$overlay_images = get_option( 'aimg_overlay_images', array() );

		// Make sure colors are split into an array if they are a string.
		if ( is_string( $colors ) ) {
			$colors = array_filter( array_map( 'trim', explode( ',', $colors ) ) );
		}

		// Get absolute paths of overlay images.
		$overlays = array();
		foreach ( $overlay_images as $id ) {
			$path = get_attached_file( $id );
			if ( $path && file_exists( $path ) ) {
				$overlays[] = $path;
			}
		}

		// Keep only single overlay if multiple are provided.
		if ( count( $overlays ) > 1 ) {
			$overlays = array( $overlays[ array_rand( $overlays ) ] );
		}

		$image_path = aimg_generate_thumbnail(
			array(
				'title'    => $title,
				'colors'   => $colors,
				'width'    => get_option( 'aimg_width', 1200 ),
				'height'   => get_option( 'aimg_height', 800 ),
				'overlays' => $overlays,
				'post_id'  => $post_id,
			)
		);

		if ( ! file_exists( $image_path ) ) {
			return;
		}

		// Prepare the attachment array.
		$attachment = array(
			'post_mime_type' => mime_content_type( $image_path ),
			'post_title'     => basename( $image_path ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $image_path, $post_id );

		// Include image handling functions.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $image_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set the post thumbnail.
		set_post_thumbnail( $post_id, $attachment_id );
	}
}
