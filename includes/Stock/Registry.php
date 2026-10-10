<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Available stock photo libraries.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Registry {

	/**
	 * Providers keyed by ID.
	 *
	 * @var ProviderInterface[]|null
	 */
	private static $providers = null;

	/**
	 * All providers.
	 *
	 * @return ProviderInterface[]
	 */
	public static function all() {
		if ( null === self::$providers ) {
			$providers = array();
			foreach ( array( new Unsplash(), new Pexels(), new Pixabay() ) as $provider ) {
				$providers[ $provider->get_id() ] = $provider;
			}

			/**
			 * Filter the stock photo providers.
			 *
			 * @param ProviderInterface[] $providers Providers keyed by ID.
			 *
			 * @since 1.8.0
			 */
			$providers = apply_filters( 'aimg_stock_providers', $providers );

			self::$providers = array_filter(
				(array) $providers,
				function ( $provider ) {
					return $provider instanceof ProviderInterface;
				}
			);
		}

		return self::$providers;
	}

	/**
	 * A provider by ID.
	 *
	 * @param string $id Provider ID.
	 *
	 * @return ProviderInterface|null
	 */
	public static function get( $id ) {
		$providers = self::all();

		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}

	/**
	 * Providers with an API key.
	 *
	 * @return ProviderInterface[]
	 */
	public static function configured() {
		return array_filter(
			self::all(),
			function ( $provider ) {
				return $provider->is_configured();
			}
		);
	}

	/**
	 * Provider for automatic featured images: the `stock_provider` setting when it
	 * has a key, otherwise the first one that has.
	 *
	 * @return ProviderInterface|null
	 */
	public static function get_default() {
		$configured = self::configured();
		$id         = (string) aimg_get_settings( 'stock_provider', '' );

		if ( isset( $configured[ $id ] ) ) {
			return $configured[ $id ];
		}

		return $configured ? reset( $configured ) : null;
	}

	/**
	 * Search orientations, as key => label.
	 *
	 * @return array
	 */
	public static function orientations() {
		return array(
			'landscape' => __( 'Landscape', 'artificial-image-generator' ),
			'portrait'  => __( 'Portrait', 'artificial-image-generator' ),
			'square'    => __( 'Square', 'artificial-image-generator' ),
		);
	}

	/**
	 * Colors every provider can filter by, as key => label.
	 *
	 * @return array
	 */
	public static function colors() {
		return array(
			'black'  => __( 'Black', 'artificial-image-generator' ),
			'white'  => __( 'White', 'artificial-image-generator' ),
			'red'    => __( 'Red', 'artificial-image-generator' ),
			'orange' => __( 'Orange', 'artificial-image-generator' ),
			'yellow' => __( 'Yellow', 'artificial-image-generator' ),
			'green'  => __( 'Green', 'artificial-image-generator' ),
			'blue'   => __( 'Blue', 'artificial-image-generator' ),
		);
	}

	/**
	 * Import sizes, as key => label.
	 *
	 * @return array
	 */
	public static function sizes() {
		return array(
			'regular'  => __( 'Regular (about 1080–1900 px wide)', 'artificial-image-generator' ),
			'large'    => __( 'Large (up to 2400 px wide)', 'artificial-image-generator' ),
			'original' => __( 'Original (largest file)', 'artificial-image-generator' ),
		);
	}

	/**
	 * Forget the cached providers, so the filter runs again.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$providers = null;
	}
}
