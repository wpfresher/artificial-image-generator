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

		// Get a random image template ID.
		$template_id = Generator::get_random_template_id();

		if ( ! $template_id ) {
			return;
		}

		$image_path = Generator::render( $template_id, $title );

		if ( ! $image_path ) {
			return;
		}

		$attachment_id = Generator::create_attachment(
			$image_path,
			array(
				'title'      => $title,
				'alt'        => $title,
				'parent'     => $post_id,
				'provenance' => array(
					'source'      => 'template',
					'template_id' => $template_id,
				),
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		// Set the post thumbnail.
		set_post_thumbnail( $post_id, $attachment_id );
	}
}
