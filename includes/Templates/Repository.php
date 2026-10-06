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

		return $document;
	}
}
