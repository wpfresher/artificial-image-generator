<?php

namespace ArtificialImageGenerator\Templates;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Reads and writes template documents.
 *
 * A template has a v2 document once it is saved in the Template Studio. Until
 * then its 1.x meta is the source, and stays untouched either way.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Repository {

	/**
	 * Post meta holding the v2 document as JSON.
	 *
	 * @var string
	 */
	const META = '_aimg_template_data';

	/**
	 * Whether a template has a v2 document.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return bool
	 */
	public static function has_document( $template_id ) {
		return '' !== (string) get_post_meta( $template_id, self::META, true );
	}

	/**
	 * A template's document: the stored v2 one, or one built from its 1.x meta.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return array Sanitized document.
	 */
	public static function get_document( $template_id ) {
		if ( self::has_document( $template_id ) ) {
			return Schema::sanitize( (string) get_post_meta( $template_id, self::META, true ) );
		}

		return Migration::from_template( $template_id );
	}

	/**
	 * Store a template's v2 document. The 1.x meta is kept for downgrades.
	 *
	 * @param int   $template_id Template ID.
	 * @param mixed $document    Document.
	 *
	 * @return array The sanitized document that was stored.
	 */
	public static function save_document( $template_id, $document ) {
		$document = Schema::sanitize( $document );

		update_post_meta( $template_id, self::META, wp_slash( wp_json_encode( $document ) ) );

		// The template list and the editor modal show these sizes.
		update_post_meta( $template_id, '_aimg_width', $document['canvas']['width'] );
		update_post_meta( $template_id, '_aimg_height', $document['canvas']['height'] );

		return $document;
	}

	/**
	 * Copy a template as a draft. A Studio template copies its document; a 1.x
	 * template copies its settings, so the copy stays editable in the classic form.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return int|\WP_Error New template ID.
	 */
	public static function duplicate( $template_id ) {
		$template = aimg_get_template( $template_id );

		if ( ! $template ) {
			return new \WP_Error( 'aimg_template_not_found', __( 'Template not found.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		$copy = wp_insert_post(
			array(
				'post_type'   => 'aimg_template',
				/* translators: %s: template title */
				'post_title'  => sprintf( __( '%s (copy)', 'artificial-image-generator' ), aimg_plain_text( $template->post_title ) ),
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $copy ) ) {
			return $copy;
		}

		if ( self::has_document( $template_id ) ) {
			self::save_document( $copy, self::get_document( $template_id ) );
		} else {
			foreach ( array( '_aimg_bg_colors', '_aimg_width', '_aimg_height', '_aimg_title_font_size', '_aimg_is_overlay_image', '_aimg_overlay_images', '_aimg_overlay_position' ) as $key ) {
				$value = get_post_meta( $template_id, $key, true );
				if ( '' !== $value ) {
					update_post_meta( $copy, $key, wp_slash( $value ) );
				}
			}
		}

		self::update_preview( $copy );

		return (int) $copy;
	}

	/**
	 * A template as a portable JSON-ready array.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return array { aimgTemplate: 2, title, document }
	 */
	public static function export( $template_id ) {
		return array(
			'aimgTemplate' => Schema::VERSION,
			'title'        => aimg_plain_text( get_post_field( 'post_title', $template_id ) ),
			'document'     => self::get_document( $template_id ),
		);
	}

	/**
	 * Render a template's preview image with its own title, replacing the previous one.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return string Preview URL, or '' when it could not be rendered.
	 */
	public static function update_preview( $template_id ) {
		$document = self::get_document( $template_id );
		$title    = aimg_get_plain_title( $template_id );
		$image    = \ArtificialImageGenerator\Rendering\GdRenderer::render( $document, MergeTags::values( 0, array( 'title' => $title ) ) );
		$previous = (string) get_post_meta( $template_id, '_aimg_preview_image_url', true );

		if ( ! $image ) {
			return '';
		}

		$slug = sanitize_title( $title );
		$path = \ArtificialImageGenerator\Rendering\GdRenderer::save( $image, $document, ( '' !== $slug ? $slug : 'aimg-template' ) . '-' . (int) $template_id );
		$url  = $path ? aimg_upload_url( $path ) : '';

		if ( '' === $url ) {
			return '';
		}

		if ( $previous && $previous !== $url ) {
			aimg_delete_upload_by_url( $previous );
		}

		update_post_meta( $template_id, '_aimg_preview_image_url', esc_url_raw( $url ) );

		return $url;
	}
}
