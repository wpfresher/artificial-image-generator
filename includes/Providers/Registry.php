<?php

namespace ArtificialImageGenerator\Providers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Available AI providers.
 *
 * @since 1.6.0
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
			$openai = new OpenAI();

			/**
			 * Filter the AI image providers.
			 *
			 * @param ProviderInterface[] $providers Providers keyed by ID.
			 *
			 * @since 1.6.0
			 */
			$providers = apply_filters( 'aimg_providers', array( $openai->get_id() => $openai ) );

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
	 * A provider by ID, or the configured one.
	 *
	 * @param string $id Provider ID. Defaults to the `ai_provider` setting.
	 *
	 * @return ProviderInterface|null
	 */
	public static function get( $id = '' ) {
		$providers = self::all();
		$id        = '' !== $id ? $id : (string) aimg_get_settings( 'ai_provider', 'openai' );

		if ( isset( $providers[ $id ] ) ) {
			return $providers[ $id ];
		}

		return isset( $providers['openai'] ) ? $providers['openai'] : null;
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
