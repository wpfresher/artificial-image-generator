<?php

namespace ArtificialImageGenerator\Providers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * OpenAI Images API.
 *
 * @since 1.6.0
 * @package ArtificialImageGenerator
 */
class OpenAI implements ProviderInterface {

	/**
	 * Pixel sizes per model for each size key.
	 *
	 * @var array
	 */
	const SIZES = array(
		'gpt-image-1'      => array(
			'square'    => '1024x1024',
			'landscape' => '1536x1024',
			'portrait'  => '1024x1536',
		),
		'gpt-image-1-mini' => array(
			'square'    => '1024x1024',
			'landscape' => '1536x1024',
			'portrait'  => '1024x1536',
		),
		'dall-e-3'         => array(
			'square'    => '1024x1024',
			'landscape' => '1792x1024',
			'portrait'  => '1024x1792',
		),
		'dall-e-2'         => array(
			'square'    => '1024x1024',
			'landscape' => '1024x1024',
			'portrait'  => '1024x1024',
		),
	);

	/**
	 * Longest prompt each model accepts.
	 *
	 * @var array
	 */
	const PROMPT_LIMITS = array(
		'dall-e-2' => 1000,
		'dall-e-3' => 4000,
	);

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'openai';
	}

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'OpenAI', 'artificial-image-generator' );
	}

	/**
	 * Models, as ID => label.
	 *
	 * @return array
	 */
	public function get_models() {
		return array(
			'gpt-image-1'      => __( 'GPT Image 1 (recommended)', 'artificial-image-generator' ),
			'gpt-image-1-mini' => __( 'GPT Image 1 Mini (lower cost)', 'artificial-image-generator' ),
			'dall-e-3'         => __( 'DALL·E 3 (legacy)', 'artificial-image-generator' ),
			'dall-e-2'         => __( 'DALL·E 2 (legacy)', 'artificial-image-generator' ),
		);
	}

	/**
	 * Default model.
	 *
	 * @return string
	 */
	public function get_default_model() {
		return 'gpt-image-1';
	}

	/**
	 * Most images per request.
	 *
	 * @param string $model Model ID.
	 *
	 * @return int
	 */
	public function get_max_images( $model ) {
		return 'dall-e-3' === $model ? 1 : 10;
	}

	/**
	 * Whether an API key is available.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->get_api_key();
	}

	/**
	 * API key, preferring the AIMG_API_KEY constant.
	 *
	 * @return string
	 */
	public function get_api_key() {
		if ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY ) {
			return (string) AIMG_API_KEY;
		}

		return (string) aimg_get_settings( 'api_key', '' );
	}

	/**
	 * Build the request body.
	 *
	 * @param string $prompt Prompt.
	 * @param array  $args   Generation arguments.
	 *
	 * @return array
	 */
	public function get_request_body( $prompt, $args ) {
		$model = $args['model'];
		$sizes = isset( self::SIZES[ $model ] ) ? self::SIZES[ $model ] : self::SIZES['gpt-image-1'];
		$size  = isset( $sizes[ $args['size'] ] ) ? $sizes[ $args['size'] ] : $sizes['square'];

		if ( isset( self::PROMPT_LIMITS[ $model ] ) ) {
			$prompt = mb_substr( $prompt, 0, self::PROMPT_LIMITS[ $model ] );
		}

		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => max( 1, min( (int) $args['n'], $this->get_max_images( $model ) ) ),
			'size'   => $size,
		);

		if ( 'dall-e-3' === $model ) {
			$body['quality'] = 'high' === $args['quality'] ? 'hd' : 'standard';
		} elseif ( 0 === strpos( $model, 'gpt-image' ) && in_array( $args['quality'], array( 'low', 'medium', 'high' ), true ) ) {
			$body['quality'] = $args['quality'];
		}

		return $body;
	}

	/**
	 * Generate images.
	 *
	 * @param string $prompt Prompt.
	 * @param array  $args   Generation arguments.
	 *
	 * @return Result[]|\WP_Error
	 */
	public function generate( $prompt, $args = array() ) {
		$api_key = $this->get_api_key();

		if ( '' === $api_key ) {
			return new \WP_Error(
				'aimg_no_api_key',
				__( 'No API key configured. Please add your API key on the Image Generator settings page.', 'artificial-image-generator' ),
				array( 'status' => 400 )
			);
		}

		$args = wp_parse_args(
			$args,
			array(
				'model'   => (string) aimg_get_settings( 'api_model', '' ),
				'size'    => 'square',
				'quality' => 'auto',
				'n'       => 1,
			)
		);

		if ( ! isset( $this->get_models()[ $args['model'] ] ) ) {
			$args['model'] = $this->get_default_model();
		}

		$model = $args['model'];

		/**
		 * Filter the request body sent to the image generation API.
		 *
		 * @param array  $body   Request body.
		 * @param string $prompt User prompt.
		 *
		 * @since 1.0.0
		 */
		$body = apply_filters( 'aimg_generate_request_body', $this->get_request_body( $prompt, $args ), $prompt );

		/**
		 * Filter the endpoint used to generate images from a prompt.
		 *
		 * @param string $endpoint API endpoint URL.
		 * @param string $prompt   User prompt.
		 *
		 * @since 1.0.0
		 */
		$endpoint = apply_filters( 'aimg_generate_endpoint', 'https://api.openai.com/v1/images/generations', $prompt );

		$response = wp_remote_post(
			$endpoint,
			array(
				/**
				 * Filter the timeout, in seconds, for the image generation request.
				 *
				 * @param int $timeout Timeout in seconds.
				 *
				 * @since 1.5.4
				 */
				'timeout' => (int) apply_filters( 'aimg_generate_timeout', 120 ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'aimg_api_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 ) {
			$message = isset( $decoded['error']['message'] )
				? (string) $decoded['error']['message']
				: __( 'The image generation API returned an error.', 'artificial-image-generator' );

			// A model-access error is almost always fixable by picking another model.
			$error_code = isset( $decoded['error']['code'] ) ? (string) $decoded['error']['code'] : '';
			if ( 'model_not_found' === $error_code || false !== stripos( $message, 'does not exist' ) ) {
				$message .= ' ' . sprintf(
					/* translators: %s: model identifier, e.g. dall-e-3. */
					__( 'Your API account may not have access to the "%s" model. Try selecting a different model under Image Generator → Settings → AI Service.', 'artificial-image-generator' ),
					$model
				);
			}

			return new \WP_Error( 'aimg_api_error', $message, array( 'status' => 502 ) );
		}

		$results = array();
		foreach ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array() as $item ) {
			$result = new Result(
				array(
					'url'            => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
					'revised_prompt' => isset( $item['revised_prompt'] ) ? $item['revised_prompt'] : '',
				)
			);

			if ( ! empty( $item['b64_json'] ) ) {
				$result->data = (string) base64_decode( (string) $item['b64_json'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Image payload returned by the API.
			}

			if ( '' !== $result->data || '' !== $result->url ) {
				$results[] = $result;
			}
		}

		if ( empty( $results ) ) {
			return new \WP_Error( 'aimg_no_image', __( 'No image returned by the API.', 'artificial-image-generator' ), array( 'status' => 502 ) );
		}

		return $results;
	}
}
