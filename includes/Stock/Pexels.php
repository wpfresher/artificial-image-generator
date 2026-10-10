<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Pexels (https://www.pexels.com/api/documentation/).
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Pexels extends Provider {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const API = 'https://api.pexels.com/v1';

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'pexels';
	}

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Pexels';
	}

	/**
	 * Where to get a free API key.
	 *
	 * @return string
	 */
	public function get_signup_url() {
		return 'https://www.pexels.com/api/new/';
	}

	/**
	 * Hosts images may be downloaded from.
	 *
	 * @return string[]
	 */
	public function get_hosts() {
		return array( 'images.pexels.com' );
	}

	/**
	 * Most a search returns per page.
	 *
	 * @return int
	 */
	protected function max_per_page() {
		return 80;
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
			'query'    => $query,
			'page'     => $page,
			'per_page' => $per_page,
		);

		if ( ! empty( $args['orientation'] ) && in_array( $args['orientation'], array( 'landscape', 'portrait', 'square' ), true ) ) {
			$params['orientation'] = $args['orientation'];
		}

		if ( ! empty( $args['color'] ) && isset( Registry::colors()[ $args['color'] ] ) ) {
			$params['color'] = $args['color'];
		}

		$data = $this->request( '/search', $params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$photos = array();
		foreach ( isset( $data['photos'] ) ? (array) $data['photos'] : array() as $item ) {
			if ( is_array( $item ) ) {
				$photos[] = $this->photo( $item );
			}
		}

		$total = isset( $data['total_results'] ) ? (int) $data['total_results'] : 0;

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
		$data = $this->request( '/photos/' . rawurlencode( (string) $id ), array() );

		return is_wp_error( $data ) ? $data : $this->photo( $data );
	}

	/**
	 * Credit line: the photographer and a link to Pexels.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return string
	 */
	public function get_attribution( Photo $photo ) {
		return $this->credit( $photo, 'https://www.pexels.com/' );
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
		return $this->get_json(
			add_query_arg( array_map( 'rawurlencode', $params ), self::API . $path ),
			array( 'Authorization' => $this->get_key() ),
			array( $path, $params )
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
		$src      = isset( $item['src'] ) ? (array) $item['src'] : array();
		$original = isset( $src['original'] ) ? $src['original'] : '';

		return new Photo(
			array(
				'id'               => isset( $item['id'] ) ? $item['id'] : '',
				'provider'         => $this->get_id(),
				'width'            => isset( $item['width'] ) ? $item['width'] : 0,
				'height'           => isset( $item['height'] ) ? $item['height'] : 0,
				'thumb'            => isset( $src['medium'] ) ? $src['medium'] : '',
				'page_url'         => isset( $item['url'] ) ? $item['url'] : '',
				'photographer'     => isset( $item['photographer'] ) ? (string) $item['photographer'] : '',
				'photographer_url' => isset( $item['photographer_url'] ) ? $item['photographer_url'] : '',
				'description'      => self::text( isset( $item['alt'] ) ? $item['alt'] : '' ),
				'color'            => isset( $item['avg_color'] ) ? (string) $item['avg_color'] : '',
				'urls'             => array(
					'regular'  => isset( $src['large2x'] ) ? $src['large2x'] : '',
					'large'    => $original ? add_query_arg(
						array(
							'auto' => 'compress',
							'cs'   => 'tinysrgb',
							'w'    => 2400,
						),
						$original
					) : '',
					'original' => $original,
				),
			)
		);
	}
}
