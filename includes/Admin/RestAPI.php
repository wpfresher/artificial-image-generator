<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Handles REST API endpoints for the plugin.
 * This class is responsible for registering REST routes and their callbacks.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator\Admin
 */
class RestAPI {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.0.0
	 * return void
	 */
	public function register_routes() {
		register_rest_route(
			'aimg/v1',
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_generate_image' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission callback for the image generation endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error
	 */
	public function check_permission( $request ) {
		$prompt = sanitize_text_field( $request->get_param( 'prompt' ) );

		if ( ! current_user_can( 'upload_files' ) ) {
			return new \WP_Error( 'rest_forbidden', 'You do not have permission to generate images.', array( 'status' => 403 ) );
		}

		if ( ! $prompt ) {
			return new \WP_Error( 'rest_forbidden', 'Missing prompt parameter.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle the image generation request
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response|\WP_Error The REST response or error.
	 */
	public function handle_generate_image( \WP_REST_Request $request ) {
		$prompt  = $request->get_param( 'prompt' );
		$api_key = defined( 'AIMG_API_KEY' ) ? AIMG_API_KEY : get_option( 'aimg_api_key', '' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', 'No API key configured.', [ 'status' => 500 ] );
		}

		// ── Example: OpenAI DALL-E 3 ─────────────────────────────────────────────
		$response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
			'timeout' => 60,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'   => 'dall-e-3',
				'prompt'  => $prompt,
				'n'       => 1,
				'size'    => '1024x1024',
				'quality' => 'standard',
			] ),
		] );
		// ─────────────────────────────────────────────────────────────────────────

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_error', $response->get_error_message(), [ 'status' => 502 ] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'][0]['url'] ) ) {
			return new \WP_Error( 'no_image', 'No image returned by the API.', [ 'status' => 502 ] );
		}

		$image_url = $body['data'][0]['url'];

		// Sideload into the Media Library so we can return an attachment ID.
		// This is required for the Featured Image panel (featured_media needs an ID).
		// For core/image and core/media-text the ID is used if available but not required.
		$attachment_id = $this->sideload_image( $image_url, $prompt );
		if ( ! is_wp_error( $attachment_id ) ) {
			$image_url = wp_get_attachment_url( $attachment_id );
		} else {
			$attachment_id = null; // sideload failed — return URL only
		}

		return rest_ensure_response( [
			'url' => $image_url,
			'id'  => $attachment_id,   // null if sideload was skipped or failed
		] );
	}

	/**
	 * Utility: download a remote image and add it to the Media Library.
	 * Uncomment the call above if you want persistent storage.
	 *
	 * @param string $url The URL of the image to sideload.
	 * @param string $title Optional title for the media item.
	 *
	 * @since 1.0.0
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function sideload_image( string $url, string $title = '' ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp  = download_url( $url );
		if ( is_wp_error( $tmp ) ) return $tmp;

		$file_array = [
			'name'     => sanitize_file_name( $title ?: 'ai-generated' ) . '.png',
			'tmp_name' => $tmp,
		];

		$id = media_handle_sideload( $file_array, 0, $title );
		if ( is_wp_error( $id ) ) @unlink( $tmp );

		return $id;
	}
}
