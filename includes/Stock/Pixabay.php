<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Pixabay (https://pixabay.com/api/docs/).
 *
 * Pixabay asks for responses to be cached for 24 hours and allows its image URLs
 * only for showing search results, so imports always download the file.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Pixabay extends Provider {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	const API = 'https://pixabay.com/api/';

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'pixabay';
	}

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Pixabay';
	}

	/**
	 * Where to get a free API key.
	 *
	 * @return string
	 */
	public function get_signup_url() {
		return 'https://pixabay.com/api/docs/';
	}

	/**
	 * Hosts images may be downloaded from.
	 *
	 * @return string[]
	 */
	public function get_hosts() {
		return array( 'pixabay.com', 'cdn.pixabay.com' );
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
			'q'          => mb_substr( $query, 0, 100 ),
			'image_type' => 'photo',
			'safesearch' => 'true',
			'page'       => $page,
			'per_page'   => $per_page,
		);

		$orientations = array(
			'landscape' => 'horizontal',
			'portrait'  => 'vertical',
		);
		if ( ! empty( $args['orientation'] ) && isset( $orientations[ $args['orientation'] ] ) ) {
			$params['orientation'] = $orientations[ $args['orientation'] ];
		}

		if ( ! empty( $args['color'] ) && isset( Registry::colors()[ $args['color'] ] ) ) {
			$params['colors'] = $args['color'];
		}

		$data = $this->request( $params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$photos = array();
		foreach ( isset( $data['hits'] ) ? (array) $data['hits'] : array() as $item ) {
			if ( is_array( $item ) ) {
				$photos[] = $this->photo( $item );
			}
		}

		// The API returns at most 500 results per query.
		$total = isset( $data['totalHits'] ) ? min( 500, (int) $data['totalHits'] ) : 0;

		return array(
			'total'  => $total,
			'pages'  => (int) ceil( $total / $per_page ),
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
		$data = $this->request( array( 'id' => (string) $id ) );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['hits'][0] ) || ! is_array( $data['hits'][0] ) ) {
			return new \WP_Error( 'aimg_stock_not_found', __( 'The photo was not found.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		return $this->photo( $data['hits'][0] );
	}

	/**
	 * Credit line: the photographer and a link to Pixabay.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return string
	 */
	public function get_attribution( Photo $photo ) {
		return $this->credit( $photo, 'https://pixabay.com/' );
	}

	/**
	 * GET the API, cached for 24 hours as Pixabay requires.
	 *
	 * @param array $params Query parameters, without the key.
	 *
	 * @return array|\WP_Error
	 */
	private function request( $params ) {
		return $this->get_json(
			add_query_arg( array_map( 'rawurlencode', array( 'key' => $this->get_key() ) + $params ), self::API ),
			array(),
			$params,
			DAY_IN_SECONDS
		);
	}

	/**
	 * Photo from an API hit.
	 *
	 * @param array $item API hit.
	 *
	 * @return Photo
	 */
	private function photo( $item ) {
		$large = isset( $item['largeImageURL'] ) ? $item['largeImageURL'] : '';
		$full  = ! empty( $item['fullHDURL'] ) ? $item['fullHDURL'] : $large;
		$user  = isset( $item['user'] ) ? (string) $item['user'] : '';

		return new Photo(
			array(
				'id'               => isset( $item['id'] ) ? $item['id'] : '',
				'provider'         => $this->get_id(),
				'width'            => isset( $item['imageWidth'] ) ? $item['imageWidth'] : 0,
				'height'           => isset( $item['imageHeight'] ) ? $item['imageHeight'] : 0,
				'thumb'            => isset( $item['webformatURL'] ) ? $item['webformatURL'] : '',
				'page_url'         => isset( $item['pageURL'] ) ? $item['pageURL'] : '',
				'photographer'     => $user,
				'photographer_url' => '' !== $user && ! empty( $item['user_id'] ) ? 'https://pixabay.com/users/' . rawurlencode( $user ) . '-' . (int) $item['user_id'] . '/' : '',
				'description'      => self::text( isset( $item['tags'] ) ? $item['tags'] : '' ),
				'urls'             => array(
					'regular'  => $large,
					'large'    => $full,
					'original' => ! empty( $item['imageURL'] ) ? $item['imageURL'] : $full,
				),
			)
		);
	}
}
