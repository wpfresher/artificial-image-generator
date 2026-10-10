<?php

namespace ArtificialImageGenerator\Stock;

use ArtificialImageGenerator\Generator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Imports stock photos into the Media Library with their credit.
 *
 * Importing the same photo again returns the attachment made the first time.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Importer {

	/**
	 * Attachment meta identifying the photo, as `provider:id`.
	 *
	 * @var string
	 */
	const KEY_META = '_aimg_stock_key';

	/**
	 * Attachment meta with the photo's details.
	 *
	 * @var string
	 */
	const DATA_META = '_aimg_stock_data';

	/**
	 * Import a photo.
	 *
	 * @param string $provider_id Provider ID.
	 * @param string $photo_id    Photo ID.
	 * @param array  $args        {
	 *     Optional.
	 *
	 *     @type int    $post_id Post the photo is for; it becomes the attachment's parent.
	 *     @type string $size    regular, large or original. Defaults to the `stock_size` setting.
	 *     @type string $query   Search terms, for alt text when the photo has no description.
	 * }
	 *
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function import( $provider_id, $photo_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_id' => 0,
				'size'    => (string) aimg_get_settings( 'stock_size', 'large' ),
				'query'   => '',
			)
		);

		$provider = Registry::get( $provider_id );

		if ( ! $provider ) {
			return new \WP_Error( 'aimg_stock_unknown_provider', __( 'Unknown stock photo library.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$existing = self::find( $provider_id, $photo_id );

		if ( $existing ) {
			return $existing;
		}

		$photo = $provider->get_photo( $photo_id );

		if ( is_wp_error( $photo ) ) {
			return $photo;
		}

		$size = isset( Registry::sizes()[ $args['size'] ] ) ? $args['size'] : 'large';
		$url  = ! empty( $photo->urls[ $size ] ) ? $photo->urls[ $size ] : ( isset( $photo->urls['regular'] ) ? $photo->urls['regular'] : '' );

		if ( ! self::allowed_url( $url, $provider->get_hosts() ) ) {
			return new \WP_Error( 'aimg_stock_bad_url', __( 'The photo is not hosted where this library serves its images.', 'artificial-image-generator' ), array( 'status' => 502 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 60 );

		if ( is_wp_error( $tmp ) ) {
			return new \WP_Error( 'aimg_stock_download_failed', $tmp->get_error_message(), array( 'status' => 502 ) );
		}

		$checked = self::check_file( $tmp );

		if ( is_wp_error( $checked ) ) {
			wp_delete_file( $tmp );

			return $checked;
		}

		$provider->track_download( $photo );

		$alt  = '' !== $photo->description ? $photo->description : aimg_plain_text( $args['query'] );
		$slug = sanitize_title( mb_substr( '' !== $alt ? $alt : $provider->get_label(), 0, 60 ) );
		$post = array();

		if ( 'yes' === aimg_get_settings( 'stock_attribution', 'yes' ) ) {
			$post['post_excerpt'] = $provider->get_attribution( $photo );
		}

		$id = media_handle_sideload(
			array(
				'name'     => ( '' !== $slug ? $slug : 'photo' ) . '-' . $provider_id . '-' . sanitize_file_name( $photo->id ) . '.' . $checked,
				'tmp_name' => $tmp,
			),
			absint( $args['post_id'] ),
			'' !== $alt ? $alt : null,
			$post
		);

		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}

			return $id;
		}

		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		update_post_meta( $id, self::KEY_META, $provider_id . ':' . $photo->id );
		update_post_meta(
			$id,
			self::DATA_META,
			array(
				'provider'         => $provider_id,
				'id'               => $photo->id,
				'photographer'     => $photo->photographer,
				'photographer_url' => esc_url_raw( $photo->photographer_url ),
				'page_url'         => esc_url_raw( $photo->page_url ),
				'size'             => $size,
				'attribution'      => wp_kses( $provider->get_attribution( $photo ), array( 'a' => array( 'href' => true ) ) ),
			)
		);

		Generator::mark_generated(
			$id,
			array(
				'source'   => $provider_id,
				'provider' => $provider_id,
				'prompt'   => (string) $args['query'],
			)
		);

		return (int) $id;
	}

	/**
	 * The attachment already imported for a photo.
	 *
	 * @param string $provider_id Provider ID.
	 * @param string $photo_id    Photo ID.
	 *
	 * @return int Attachment ID, or 0.
	 */
	public static function find( $provider_id, $photo_id ) {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
				'meta_key'         => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $provider_id . ':' . $photo_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$id = $ids ? (int) $ids[0] : 0;

		if ( ! $id ) {
			return 0;
		}

		$file = get_attached_file( $id );

		return $file && file_exists( $file ) ? $id : 0;
	}

	/**
	 * Whether a URL is an https URL on one of a provider's image hosts.
	 *
	 * @param string   $url   URL.
	 * @param string[] $hosts Allowed hosts.
	 *
	 * @return bool
	 */
	public static function allowed_url( $url, $hosts ) {
		$parts = wp_parse_url( (string) $url );

		return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && 'https' === strtolower( $parts['scheme'] ) && in_array( strtolower( $parts['host'] ), $hosts, true );
	}

	/**
	 * Check a downloaded file's size and type.
	 *
	 * @param string $file Path.
	 *
	 * @return string|\WP_Error File extension.
	 */
	private static function check_file( $file ) {
		/**
		 * Filter the largest stock photo file that is imported, in bytes.
		 *
		 * @param int $bytes Bytes. Default 25 MB.
		 *
		 * @since 1.8.0
		 */
		$max = (int) apply_filters( 'aimg_stock_max_bytes', 25 * MB_IN_BYTES );

		if ( filesize( $file ) > $max ) {
			return new \WP_Error( 'aimg_stock_too_large', __( 'The photo file is too large. Choose a smaller import size.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$types = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		$mime  = wp_get_image_mime( $file );

		if ( ! $mime || ! isset( $types[ $mime ] ) ) {
			return new \WP_Error( 'aimg_stock_bad_type', __( 'The downloaded file is not a JPEG, PNG or WebP image.', 'artificial-image-generator' ), array( 'status' => 502 ) );
		}

		return $types[ $mime ];
	}
}
