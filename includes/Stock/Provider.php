<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Shared parts of the stock providers: API keys, cached requests and errors.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
abstract class Provider implements ProviderInterface {

	/**
	 * Seconds a search result is cached.
	 *
	 * @var int
	 */
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Key used instead of the saved one, e.g. to test a key before saving it.
	 *
	 * @var string|null
	 */
	private $key = null;

	/**
	 * The API key: the `AIMG_{ID}_KEY` constant, or the `{id}_key` setting.
	 *
	 * @return string
	 */
	public function get_key() {
		if ( null !== $this->key ) {
			return $this->key;
		}

		$constant = 'AIMG_' . strtoupper( $this->get_id() ) . '_KEY';

		if ( defined( $constant ) && constant( $constant ) ) {
			return (string) constant( $constant );
		}

		return (string) aimg_get_settings( $this->get_id() . '_key', '' );
	}

	/**
	 * Use a key instead of the saved one.
	 *
	 * @param string $key API key.
	 *
	 * @return void
	 */
	public function set_key( $key ) {
		$this->key = (string) $key;
	}

	/**
	 * Whether an API key is set.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->get_key();
	}

	/**
	 * Most a search returns per page.
	 *
	 * @return int
	 */
	protected function max_per_page() {
		return 30;
	}

	/**
	 * Downloads need no tracking unless a provider says so.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return void
	 */
	public function track_download( Photo $photo ) {}

	/**
	 * GET a JSON API, cached.
	 *
	 * @param string $url       URL, without the API key when it goes in a header.
	 * @param array  $headers   Request headers.
	 * @param array  $cache_for Values the cache key is built from; never the API key.
	 * @param int    $ttl       Seconds to cache; 0 to skip the cache.
	 *
	 * @return array|\WP_Error Decoded JSON.
	 */
	protected function get_json( $url, $headers, $cache_for, $ttl = self::CACHE_TTL ) {
		if ( ! $this->is_configured() ) {
			/* translators: %s: provider name, e.g. Unsplash */
			return new \WP_Error( 'aimg_stock_no_key', sprintf( __( 'Add your %s API key on the Image Generator settings page.', 'artificial-image-generator' ), $this->get_label() ), array( 'status' => 400 ) );
		}

		$cache_key = 'aimg_stock_' . md5( $this->get_id() . wp_json_encode( $cache_for ) . md5( $this->get_key() ) );

		if ( $ttl ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'aimg_stock_http', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			/* translators: %s: provider name */
			return new \WP_Error( 'aimg_stock_auth', sprintf( __( '%s did not accept the API key. Check it on the Image Generator settings page.', 'artificial-image-generator' ), $this->get_label() ), array( 'status' => 400 ) );
		}

		if ( 429 === $code ) {
			/* translators: %s: provider name */
			return new \WP_Error( 'aimg_stock_rate_limited', sprintf( __( '%s has had too many requests from this site. Please try again later.', 'artificial-image-generator' ), $this->get_label() ), array( 'status' => 429 ) );
		}

		if ( 404 === $code ) {
			return new \WP_Error( 'aimg_stock_not_found', __( 'The photo was not found.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		if ( 200 !== $code || ! is_array( $data ) ) {
			/* translators: 1: provider name, 2: HTTP status code */
			return new \WP_Error( 'aimg_stock_http', sprintf( __( '%1$s returned an error (HTTP %2$d).', 'artificial-image-generator' ), $this->get_label(), $code ), array( 'status' => 502 ) );
		}

		if ( $ttl ) {
			set_transient( $cache_key, $data, $ttl );
		}

		return $data;
	}

	/**
	 * Page and page size from search arguments.
	 *
	 * @param array $args Search arguments.
	 *
	 * @return int[] Page and per page.
	 */
	protected function paging( $args ) {
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 24;

		return array( $page, max( 3, min( $this->max_per_page(), $per_page ) ) );
	}

	/**
	 * Credit line: "Photo by {photographer} on {provider}", both linked.
	 *
	 * @param Photo  $photo        Photo.
	 * @param string $provider_url Provider home URL.
	 *
	 * @return string
	 */
	protected function credit( Photo $photo, $provider_url ) {
		$provider = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $provider_url ), esc_html( $this->get_label() ) );

		if ( '' === $photo->photographer ) {
			/* translators: %s: provider name, linked */
			return sprintf( __( 'Photo from %s', 'artificial-image-generator' ), $provider );
		}

		$person = $photo->photographer_url ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $photo->photographer_url ), esc_html( $photo->photographer ) ) : esc_html( $photo->photographer );

		/* translators: 1: photographer name, 2: provider name, both linked */
		return sprintf( __( 'Photo by %1$s on %2$s', 'artificial-image-generator' ), $person, $provider );
	}

	/**
	 * A description as one line of plain text.
	 *
	 * @param mixed $text Text.
	 *
	 * @return string
	 */
	protected static function text( $text ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	}
}
