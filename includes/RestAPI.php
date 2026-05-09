<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Handles REST API endpoints for the plugin.
 * This class is responsible for registering REST routes and their callbacks.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator
 */
class RestAPI {

	/**
	 * REST namespace.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	const REST_NAMESPACE = 'aimg/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_generate_image' ),
				'permission_callback' => array( $this, 'check_generate_permission' ),
				'args'                => array(
					'prompt'      => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'template_id' => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'title'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/templates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_list_templates' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);
	}

	/**
	 * Permission callback for read endpoints.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error
	 */
	public function check_read_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access image templates.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Permission callback for the image generation endpoint.
	 *
	 * Allows requests that supply EITHER a prompt OR a template_id.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error
	 */
	public function check_generate_permission( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to generate images.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$prompt      = trim( (string) $request->get_param( 'prompt' ) );
		$template_id = absint( $request->get_param( 'template_id' ) );

		if ( '' === $prompt && 0 === $template_id ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'You must supply either a prompt or a template_id.', 'artificial-image-generator' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * List templates available for the block editor picker.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response
	 */
	public function handle_list_templates() {
		$templates = aimg_get_templates(
			array(
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$data = array();
		foreach ( (array) $templates as $template ) {
			if ( ! $template ) {
				continue;
			}

			$preview = get_post_meta( $template->ID, '_aimg_preview_image_url', true );
			$width   = (int) get_post_meta( $template->ID, '_aimg_width', true );
			$height  = (int) get_post_meta( $template->ID, '_aimg_height', true );

			$data[] = array(
				'id'      => (int) $template->ID,
				'title'   => $template->post_title,
				'preview' => $preview ? esc_url_raw( $preview ) : '',
				'width'   => $width,
				'height'  => $height,
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Handle the image generation request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response|\WP_Error The REST response or error.
	 */
	public function handle_generate_image( \WP_REST_Request $request ) {
		$template_id = absint( $request->get_param( 'template_id' ) );
		$prompt      = trim( (string) $request->get_param( 'prompt' ) );
		$title       = trim( (string) $request->get_param( 'title' ) );

		if ( $template_id ) {
			return $this->generate_from_template( $template_id, $title );
		}

		if ( '' !== $prompt ) {
			return $this->generate_from_prompt( $prompt );
		}

		return new \WP_Error(
			'rest_invalid_param',
			__( 'No prompt or template_id provided.', 'artificial-image-generator' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Generate an image from a stored template and import it into the Media Library.
	 *
	 * @param int    $template_id Template post ID.
	 * @param string $title       Optional title text rendered onto the image.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function generate_from_template( $template_id, $title = '' ) {
		$template = aimg_get_template( $template_id );
		if ( ! $template ) {
			return new \WP_Error(
				'aimg_invalid_template',
				__( 'Invalid template ID.', 'artificial-image-generator' ),
				array( 'status' => 400 )
			);
		}

		$colors = get_post_meta( $template_id, '_aimg_bg_colors', true );
		if ( is_string( $colors ) ) {
			$colors = array_filter( array_map( 'trim', explode( ',', $colors ) ) );
		}

		// Resolve overlay images to absolute paths.
		$overlays = array();
		if ( 'yes' === get_post_meta( $template_id, '_aimg_is_overlay_image', true ) ) {
			$overlay_images = json_decode( (string) get_post_meta( $template_id, '_aimg_overlay_images', true ) );

			if ( is_array( $overlay_images ) ) {
				foreach ( $overlay_images as $id ) {
					$path = get_attached_file( (int) $id );
					if ( $path && file_exists( $path ) ) {
						$overlays[] = $path;
					}
				}
			}

			if ( count( $overlays ) > 1 ) {
				$overlays = array( $overlays[ array_rand( $overlays ) ] );
			}
		}

		// Use the supplied title, fall back to the template title.
		$render_title = '' !== $title ? $title : $template->post_title;

		$image_path = aimg_generate_thumbnail(
			array(
				'post_id'  => $template_id,
				'title'    => $render_title,
				'colors'   => $colors,
				'width'    => (int) get_post_meta( $template_id, '_aimg_width', true ),
				'height'   => (int) get_post_meta( $template_id, '_aimg_height', true ),
				'overlays' => $overlays,
			)
		);

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return new \WP_Error(
				'aimg_generation_failed',
				__( 'Failed to generate image from template.', 'artificial-image-generator' ),
				array( 'status' => 500 )
			);
		}

		$attachment_id = $this->import_local_file( $image_path, $render_title );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return rest_ensure_response(
			array(
				'url'    => wp_get_attachment_url( $attachment_id ),
				'id'     => (int) $attachment_id,
				'alt'    => $render_title,
				'source' => 'template',
			)
		);
	}

	/**
	 * Generate an image by calling an external AI service with a prompt and import the result.
	 *
	 * @param string $prompt User prompt.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function generate_from_prompt( $prompt ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'aimg_no_api_key',
				__( 'No API key configured. Please add your API key on the Image Generator settings page.', 'artificial-image-generator' ),
				array( 'status' => 400 )
			);
		}

		/**
		 * Filter the request body sent to the image generation API.
		 *
		 * Defaults to OpenAI DALL·E 3 parameters.
		 *
		 * @param array  $body   Request body.
		 * @param string $prompt User prompt.
		 *
		 * @since 1.0.0
		 */
		$body = apply_filters(
			'aimg_generate_request_body',
			array(
				'model'   => 'dall-e-3',
				'prompt'  => $prompt,
				'n'       => 1,
				'size'    => '1024x1024',
				'quality' => 'standard',
			),
			$prompt
		);

		/**
		 * Filter the endpoint used to generate images from a prompt.
		 *
		 * @param string $endpoint API endpoint URL.
		 * @param string $prompt   User prompt.
		 *
		 * @since 1.0.0
		 */
		$endpoint = apply_filters(
			'aimg_generate_endpoint',
			'https://api.openai.com/v1/images/generations',
			$prompt
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'aimg_api_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 ) {
			$message = isset( $decoded['error']['message'] )
				? (string) $decoded['error']['message']
				: __( 'The image generation API returned an error.', 'artificial-image-generator' );

			return new \WP_Error(
				'aimg_api_error',
				$message,
				array( 'status' => 502 )
			);
		}

		$image_url = isset( $decoded['data'][0]['url'] ) ? esc_url_raw( $decoded['data'][0]['url'] ) : '';

		if ( empty( $image_url ) ) {
			return new \WP_Error(
				'aimg_no_image',
				__( 'No image returned by the API.', 'artificial-image-generator' ),
				array( 'status' => 502 )
			);
		}

		$attachment_id = $this->sideload_image( $image_url, $prompt );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return rest_ensure_response(
			array(
				'url'    => wp_get_attachment_url( $attachment_id ),
				'id'     => (int) $attachment_id,
				'alt'    => $prompt,
				'source' => 'prompt',
			)
		);
	}

	/**
	 * Resolve the configured API key, preferring the AIMG_API_KEY constant.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_api_key() {
		if ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY ) {
			return (string) AIMG_API_KEY;
		}

		return (string) aimg_get_settings( 'api_key', '' );
	}

	/**
	 * Import a locally generated PNG into the Media Library.
	 *
	 * @param string $filepath Absolute path to a PNG file inside wp-content/uploads.
	 * @param string $title    Title used for the attachment.
	 *
	 * @since 1.0.0
	 * @return int|\WP_Error Attachment ID on success.
	 */
	protected function import_local_file( $filepath, $title = '' ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$mime = function_exists( 'mime_content_type' ) ? mime_content_type( $filepath ) : 'image/png';

		$attachment = array(
			'post_mime_type' => $mime ? $mime : 'image/png',
			'post_title'     => $title ? $title : basename( $filepath ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $filepath, 0, true );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Download a remote image and add it to the Media Library.
	 *
	 * @param string $url   The URL of the image to sideload.
	 * @param string $title Optional title for the media item.
	 *
	 * @since 1.0.0
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function sideload_image( $url, $title = '' ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Detect the extension from the temp file (download_url stores it without one).
		$type = wp_check_filetype( $tmp );
		if ( empty( $type['ext'] ) ) {
			$mime = function_exists( 'mime_content_type' ) ? mime_content_type( $tmp ) : '';
			$ext  = 'png';
			if ( 'image/jpeg' === $mime ) {
				$ext = 'jpg';
			} elseif ( 'image/webp' === $mime ) {
				$ext = 'webp';
			}
		} else {
			$ext = $type['ext'];
		}

		$slug = sanitize_title( $title );
		if ( '' === $slug ) {
			$slug = 'ai-generated';
		}

		$file_array = array(
			'name'     => $slug . '-' . wp_generate_password( 6, false, false ) . '.' . $ext,
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, 0, $title );
		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return $id;
		}

		// Save the prompt as alt text for accessibility.
		if ( $title ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
		}

		return $id;
	}
}
