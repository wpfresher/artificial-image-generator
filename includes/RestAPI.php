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
						'sanitize_callback' => 'aimg_plain_text',
					),
					'size'        => array(
						'type'     => 'string',
						'required' => false,
						'enum'     => array( 'square', 'landscape', 'portrait' ),
					),
					'quality'     => array(
						'type'     => 'string',
						'required' => false,
						'enum'     => array( 'auto', 'low', 'medium', 'high' ),
					),
					'n'           => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 1,
						'minimum'  => 1,
						'maximum'  => 4,
					),
					'style'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
					'post_id'     => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/templates/(?P<id>\d+)/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_template_preview' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => array(
					'title' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'aimg_plain_text',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/prompt',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_build_prompt' ),
				'permission_callback' => array( $this, 'check_post_permission' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'title'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'aimg_plain_text',
					),
					'excerpt' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/status/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_status' ),
				'permission_callback' => array( $this, 'check_post_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/featured/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_featured' ),
				'permission_callback' => array( $this, 'check_featured_permission' ),
			)
		);
	}

	/**
	 * Permission callback for endpoints about one post.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.6.0
	 * @return bool|\WP_Error
	 */
	public function check_post_permission( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$allowed = $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );

		if ( ! $allowed ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this post.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Permission callback for generating a post's featured image.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.6.0
	 * @return bool|\WP_Error
	 */
	public function check_featured_permission( \WP_REST_Request $request ) {
		$allowed = $this->check_post_permission( $request );

		if ( true !== $allowed ) {
			return $allowed;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to generate images.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( Generator::method_starts_with_ai( Generator::get_runnable_method() ) && ! aimg_user_can_use_ai() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to generate AI images.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Render a template with a title, without adding it to the Media Library.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.6.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_template_preview( \WP_REST_Request $request ) {
		$path = Generator::render( absint( $request['id'] ), trim( (string) $request->get_param( 'title' ) ) );

		if ( ! $path ) {
			return new \WP_Error( 'aimg_invalid_template', __( 'Invalid template ID.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file we just rendered.
		wp_delete_file( $path );

		return rest_ensure_response(
			array(
				'image' => 'data:image/png;base64,' . base64_encode( (string) $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Inline preview image.
			)
		);
	}

	/**
	 * Build the AI prompt for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.6.0
	 * @return \WP_REST_Response
	 */
	public function handle_build_prompt( \WP_REST_Request $request ) {
		$prompt = PromptBuilder::build(
			absint( $request->get_param( 'post_id' ) ),
			array(
				'style'  => 'none',
				'values' => array(
					'title'   => (string) $request->get_param( 'title' ),
					'excerpt' => (string) $request->get_param( 'excerpt' ),
				),
			)
		);

		return rest_ensure_response( array( 'prompt' => $prompt ) );
	}

	/**
	 * A post's background generation status.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.6.0
	 * @return \WP_REST_Response
	 */
	public function handle_status( \WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );

		return rest_ensure_response( $this->status_response( $post_id ) );
	}

	/**
	 * Generate a post's featured image with the configured method, replacing the current one.
	 *
	 * Template images are created right away; AI images are queued.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @since 1.6.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_featured( \WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		$method  = Generator::get_runnable_method();

		if ( '' === $method ) {
			return new \WP_Error( 'aimg_no_api_key', __( 'No API key configured. Please add your API key on the Image Generator settings page.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		if ( '' === aimg_get_plain_title( $post_id ) ) {
			return new \WP_Error( 'aimg_no_title', __( 'Add a title to the post first; it is used to generate the image.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		if ( Generator::method_starts_with_ai( $method ) ) {
			Queue::enqueue( $post_id, $method, true );

			return rest_ensure_response( $this->status_response( $post_id ) );
		}

		$attachment_id = Generator::generate_for_post( $post_id, $method );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		Queue::set_status( $post_id, 'done' );

		return rest_ensure_response( $this->status_response( $post_id ) );
	}

	/**
	 * Status payload for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private function status_response( $post_id ) {
		$status       = Queue::get_status( $post_id );
		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		return array(
			'status'        => $status['status'],
			'error'         => $status['error'],
			'attachment_id' => $thumbnail_id,
			'url'           => $thumbnail_id ? (string) wp_get_attachment_url( $thumbnail_id ) : '',
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

		if ( 0 === $template_id && ! aimg_user_can_use_ai() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to generate AI images.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this post.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
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
				'title'   => aimg_plain_text( $template->post_title ),
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
			return $this->generate_from_prompt( $prompt, $request );
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
		$args = Generator::get_render_args( $template_id, $title );

		if ( ! $args ) {
			return new \WP_Error(
				'aimg_invalid_template',
				__( 'Invalid template ID.', 'artificial-image-generator' ),
				array( 'status' => 400 )
			);
		}

		$render_title = $args['title'];
		$image_path   = aimg_generate_thumbnail( $args );

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return new \WP_Error(
				'aimg_generation_failed',
				__( 'Failed to generate image from template.', 'artificial-image-generator' ),
				array( 'status' => 500 )
			);
		}

		$attachment_id = Generator::create_attachment(
			$image_path,
			array(
				'title'      => $render_title,
				'alt'        => $render_title,
				'provenance' => array(
					'source'      => 'template',
					'template_id' => $template_id,
				),
			)
		);

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
	 * Generate images from a prompt with the configured AI provider and import them.
	 *
	 * @param string                $prompt  User prompt.
	 * @param \WP_REST_Request|null $request Request, for size, quality, number of images and style.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function generate_from_prompt( $prompt, $request = null ) {
		$provider = Providers\Registry::get();

		if ( ! $provider || ! $provider->is_configured() ) {
			return new \WP_Error(
				'aimg_no_api_key',
				__( 'No API key configured. Please add your API key on the Image Generator settings page.', 'artificial-image-generator' ),
				array( 'status' => 400 )
			);
		}

		$n     = $request ? max( 1, min( 4, (int) $request->get_param( 'n' ) ) ) : 1;
		$quota = aimg_consume_ai_quota( 0, $n );

		if ( is_wp_error( $quota ) ) {
			return $quota;
		}

		$args = array(
			'n'      => $n,
			'parent' => $request ? absint( $request->get_param( 'post_id' ) ) : 0,
		);

		foreach ( array( 'size', 'quality' ) as $key ) {
			if ( $request && $request->get_param( $key ) ) {
				$args[ $key ] = (string) $request->get_param( $key );
			}
		}

		$full_prompt = $prompt;
		$styles      = PromptBuilder::get_styles();
		$style       = $request ? (string) $request->get_param( 'style' ) : '';

		if ( isset( $styles[ $style ][1] ) && '' !== $styles[ $style ][1] ) {
			$full_prompt .= ' ' . $styles[ $style ][1];
		}

		$args['title'] = $prompt;
		$ids           = Generator::generate_ai( $full_prompt, $args );

		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$images = array();
		foreach ( $ids as $id ) {
			$images[] = array(
				'id'  => (int) $id,
				'url' => (string) wp_get_attachment_url( $id ),
				'alt' => $prompt,
			);
		}

		return rest_ensure_response(
			array(
				'url'    => $images[0]['url'],
				'id'     => $images[0]['id'],
				'alt'    => $prompt,
				'source' => 'prompt',
				'images' => $images,
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
		return ( new Providers\OpenAI() )->get_api_key();
	}

	/**
	 * Resolve the configured image generation model.
	 *
	 * @since 1.4.3
	 * @return string
	 */
	protected function get_model() {
		$provider = new Providers\OpenAI();
		$model    = (string) aimg_get_settings( 'api_model', '' );

		return isset( $provider->get_models()[ $model ] ) ? $model : $provider->get_default_model();
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
		return Generator::sideload_url( $url, $title );
	}

	/**
	 * Decode a base64 encoded image and add it to the Media Library.
	 *
	 * @param string $b64   Base64 encoded image contents.
	 * @param string $title Optional title for the media item.
	 *
	 * @since 1.4.3
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function sideload_base64_image( $b64, $title = '' ) {
		return Generator::sideload_bytes( (string) base64_decode( $b64, true ), $title ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Image payload returned by the API.
	}
}
