<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Unsplash (https://unsplash.com/documentation).
 *
 * Follows the API guidelines: results hotlink the returned image URLs, every
 * import calls the photo's download_location, and credits link the photographer
 * and Unsplash with UTM parameters.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Unsplash extends Provider {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const API = 'https://api.unsplash.com';

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'unsplash';
	}

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Unsplash';
	}

	/**
	 * Where to get a free API key.
	 *
	 * @return string
	 */
	public function get_signup_url() {
		return 'https://unsplash.com/oauth/applications';
	}

	/**
	 * Hosts images may be downloaded from.
	 *
	 * @return string[]
	 */
	public function get_hosts() {
		return array( 'images.unsplash.com' );
	}

	/**
	 * Search photos.
	 *
	 * @param string $query Search terms.
	 * @param array  $args  Search arguments.
	 *
	 * @return array|\WP_Error
	 */
	public function search( $query, $args = array() ) {
		list( $page, $per_page ) = $this->paging( $args );

		$params = array(
			'query'          => $query,
			'page'           => $page,
			'per_page'       => $per_page,
			'content_filter' => 'high',
		);

		$orientations = array(
			'landscape' => 'landscape',
			'portrait'  => 'portrait',
			'square'    => 'squarish',
		);
		if ( ! empty( $args['orientation'] ) && isset( $orientations[ $args['orientation'] ] ) ) {
			$params['orientation'] = $orientations[ $args['orientation'] ];
		}

		if ( ! empty( $args['color'] ) && isset( Registry::colors()[ $args['color'] ] ) ) {
			$params['color'] = $args['color'];
		}

		$data = $this->request( '/search/photos', $params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$photos = array();
		foreach ( isset( $data['results'] ) ? (array) $data['results'] : array() as $item ) {
			if ( is_array( $item ) && empty( $item['premium'] ) ) {
				$photos[] = $this->photo( $item );
			}
		}

		return array(
			'total'  => isset( $data['total'] ) ? (int) $data['total'] : 0,
			'pages'  => isset( $data['total_pages'] ) ? (int) $data['total_pages'] : 0,
			'photos' => $photos,
		);
	}

	/**
	 * One photo.
	 *
	 * @param string $id Photo ID.
	 *
	 * @return Photo|\WP_Error
	 */
	public function get_photo( $id ) {
		$data = $this->request( '/photos/' . rawurlencode( (string) $id ), array() );

		return is_wp_error( $data ) ? $data : $this->photo( $data );
	}

	/**
	 * Call the photo's download_location, as the API guidelines require.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return void
	 */
	public function track_download( Photo $photo ) {
		if ( empty( $photo->extra['download_location'] ) || 0 !== strpos( $photo->extra['download_location'], self::API . '/' ) ) {
			return;
		}

		wp_remote_get(
			$photo->extra['download_location'],
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => $this->headers(),
			)
		);
	}

	/**
	 * Credit line with the UTM parameters Unsplash asks for.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return string
	 */
	public function get_attribution( Photo $photo ) {
		$photo                   = clone $photo;
		$photo->photographer_url = $photo->photographer_url ? $this->utm( $photo->photographer_url ) : '';

		return $this->credit( $photo, $this->utm( 'https://unsplash.com/' ) );
	}

	/**
	 * GET an API path.
	 *
	 * @param string $path   Path.
	 * @param array  $params Query parameters.
	 *
	 * @return array|\WP_Error
	 */
	private function request( $path, $params ) {
		return $this->get_json( add_query_arg( array_map( 'rawurlencode', $params ), self::API . $path ), $this->headers(), array( $path, $params ) );
	}

	/**
	 * Request headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization'  => 'Client-ID ' . $this->get_key(),
			'Accept-Version' => 'v1',
		);
	}

	/**
	 * Add the referral parameters to an unsplash.com link.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function utm( $url ) {
		/**
		 * Filter the application name sent to Unsplash in credit links (utm_source).
		 *
		 * @param string $name Application name.
		 *
		 * @since 1.8.0
		 */
		$app = sanitize_title( (string) apply_filters( 'aimg_unsplash_app_name', 'image_generator' ) );

		return add_query_arg(
			array(
				'utm_source' => $app,
				'utm_medium' => 'referral',
			),
			$url
		);
	}

	/**
	 * Photo from an API item.
	 *
	 * @param array $item API item.
	 *
	 * @return Photo
	 */
	private function photo( $item ) {
		$urls = isset( $item['urls'] ) ? (array) $item['urls'] : array();
		$user = isset( $item['user'] ) ? (array) $item['user'] : array();
		$raw  = isset( $urls['raw'] ) ? $urls['raw'] : '';

		return new Photo(
			array(
				'id'               => isset( $item['id'] ) ? $item['id'] : '',
				'provider'         => $this->get_id(),
				'width'            => isset( $item['width'] ) ? $item['width'] : 0,
				'height'           => isset( $item['height'] ) ? $item['height'] : 0,
				'thumb'            => isset( $urls['small'] ) ? $urls['small'] : '',
				'page_url'         => isset( $item['links']['html'] ) ? $this->utm( $item['links']['html'] ) : '',
				'photographer'     => isset( $user['name'] ) ? (string) $user['name'] : '',
				'photographer_url' => isset( $user['links']['html'] ) ? $user['links']['html'] : '',
				'description'      => self::text( isset( $item['alt_description'] ) && $item['alt_description'] ? $item['alt_description'] : ( isset( $item['description'] ) ? $item['description'] : '' ) ),
				'color'            => isset( $item['color'] ) ? (string) $item['color'] : '',
				'urls'             => array(
					'regular'  => isset( $urls['regular'] ) ? $urls['regular'] : '',
					'large'    => $raw ? add_query_arg(
						array(
							'w'   => 2400,
							'q'   => 85,
							'fm'  => 'jpg',
							'fit' => 'max',
						),
						$raw
					) : '',
					'original' => isset( $urls['full'] ) ? $urls['full'] : '',
				),
				'extra'            => array(
					'download_location' => isset( $item['links']['download_location'] ) ? $item['links']['download_location'] : '',
				),
			)
		);
	}
}
