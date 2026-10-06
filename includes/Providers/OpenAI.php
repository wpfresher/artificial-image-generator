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
	 * Pixel size for each size key; the standard sizes every GPT Image model supports.
	 *
	 * @var array
	 */
	const SIZES = array(
		'square'    => '1024x1024',
		'landscape' => '1536x1024',
		'portrait'  => '1024x1536',
	);

	/**
	 * Longest prompt the Images API accepts.
	 *
	 * @var int
	 */
	const PROMPT_LIMIT = 32000;

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
			'gpt-image-2.5-flare'    => __( 'GPT Image 2.5 Flare (recommended, fast)', 'artificial-image-generator' ),
			'gpt-image-2.5-sunburst' => __( 'GPT Image 2.5 Sunburst (most capable)', 'artificial-image-generator' ),
			'gpt-image-2'            => __( 'GPT Image 2', 'artificial-image-generator' ),
		);
	}

	/**
	 * Default model.
	 *
	 * @return string
	 */
	public function get_default_model() {
		return 'gpt-image-2.5-flare';
	}

	/**
	 * Most images per request.
	 *
	 * @param string $model Model ID.
	 *
	 * @return int
	 */
	public function get_max_images( $model ) {
		return 10;
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

		$body = array(
			'model'  => $model,
			'prompt' => mb_substr( $prompt, 0, self::PROMPT_LIMIT ),
			'n'      => max( 1, min( (int) $args['n'], $this->get_max_images( $model ) ) ),
			'size'   => isset( self::SIZES[ $args['size'] ] ) ? self::SIZES[ $args['size'] ] : self::SIZES['square'],
		);

		if ( in_array( $args['quality'], array( 'low', 'medium', 'high' ), true ) ) {
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
			$message = $response->get_error_message();

			if ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'cURL error 28' ) ) {
				$message = __( 'The AI service did not respond in time. Please try again.', 'artificial-image-generator' );
			}

			return new \WP_Error( 'aimg_api_error', $message, array( 'status' => 504 ) );
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
					/* translators: %s: model identifier, e.g. gpt-image-2. */
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
