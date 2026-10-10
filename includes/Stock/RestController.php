<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Stock photo REST endpoints: list libraries, search, import, test a key.
 *
 * API keys never leave the server; the browser only sees results.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class RestController {

	/**
	 * Register the routes.
	 *
	 * @param string $namespace_name REST namespace.
	 *
	 * @return void
	 */
	public function register_routes( $namespace_name ) {
		register_rest_route(
			$namespace_name,
			'/stock',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_providers' ),
				'permission_callback' => array( $this, 'can_upload' ),
			)
		);

		register_rest_route(
			$namespace_name,
			'/stock/(?P<provider>[a-z0-9_-]+)/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_upload' ),
				'args'                => array(
					'query'       => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 100,
						'sanitize_callback' => 'aimg_plain_text',
					),
					'page'        => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
						'maximum' => 100,
					),
					'per_page'    => array(
						'type'    => 'integer',
						'default' => 24,
						'minimum' => 3,
						'maximum' => 30,
					),
					'orientation' => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array_merge( array( '' ), array_keys( Registry::orientations() ) ),
					),
					'color'       => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array_merge( array( '' ), array_keys( Registry::colors() ) ),
					),
				),
			)
		);

		register_rest_route(
			$namespace_name,
			'/stock/(?P<provider>[a-z0-9_-]+)/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_import' ),
				'args'                => array(
					'id'           => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^[A-Za-z0-9_-]{1,64}$',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id'      => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'set_featured' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'size'         => array(
						'type' => 'string',
						'enum' => array_keys( Registry::sizes() ),
					),
					'query'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'aimg_plain_text',
					),
				),
			)
		);

		register_rest_route(
			$namespace_name,
			'/stock/(?P<provider>[a-z0-9_-]+)/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'key' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Whether the user may search and import photos.
	 *
	 * @return bool|\WP_Error
	 */
	public function can_upload() {
		if ( current_user_can( 'upload_files' ) ) {
			return true;
		}

		return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to add photos to the Media Library.', 'artificial-image-generator' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Whether the user may import a photo, for the given post when there is one.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return bool|\WP_Error
	 */
	public function can_import( \WP_REST_Request $request ) {
		$allowed = $this->can_upload();
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( true === $allowed && $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to edit this post.', 'artificial-image-generator' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return $allowed;
	}

	/**
	 * Whether the user may manage settings.
	 *
	 * @return bool|\WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to change the settings.', 'artificial-image-generator' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * The libraries and whether each has a key.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_providers() {
		$data = array();

		foreach ( Registry::all() as $provider ) {
			$data[] = self::describe( $provider );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Search a library.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( \WP_REST_Request $request ) {
		$provider = $this->provider( $request );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$page   = (int) $request->get_param( 'page' );
		$result = $provider->search(
			(string) $request->get_param( 'query' ),
			array(
				'page'        => $page,
				'per_page'    => (int) $request->get_param( 'per_page' ),
				'orientation' => (string) $request->get_param( 'orientation' ),
				'color'       => (string) $request->get_param( 'color' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$photos = array();
		foreach ( $result['photos'] as $photo ) {
			$photos[] = $photo->to_array() + array(
				'attribution' => wp_kses( $provider->get_attribution( $photo ), array( 'a' => array( 'href' => true ) ) ),
				'imported'    => Importer::find( $provider->get_id(), $photo->id ),
			);
		}

		return rest_ensure_response(
			array(
				'total'  => (int) $result['total'],
				'pages'  => (int) $result['pages'],
				'page'   => $page,
				'photos' => $photos,
			)
		);
	}

	/**
	 * Import a photo, optionally as the post's featured image.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( \WP_REST_Request $request ) {
		$provider = $this->provider( $request );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		$args    = array(
			'post_id' => $post_id,
			'query'   => (string) $request->get_param( 'query' ),
		);

		if ( $request->get_param( 'size' ) ) {
			$args['size'] = (string) $request->get_param( 'size' );
		}

		$id = Importer::import( $provider->get_id(), (string) $request->get_param( 'id' ), $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( $post_id && $request->get_param( 'set_featured' ) ) {
			set_post_thumbnail( $post_id, $id );
		}

		return rest_ensure_response(
			array(
				'id'     => (int) $id,
				'url'    => (string) wp_get_attachment_url( $id ),
				'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'source' => $provider->get_id(),
			)
		);
	}

	/**
	 * Test an API key: the given one, or the saved one.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test( \WP_REST_Request $request ) {
		$provider = Registry::get( (string) $request['provider'] );

		if ( ! $provider ) {
			return new \WP_Error( 'aimg_stock_unknown_provider', __( 'Unknown stock photo library.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		$key = trim( (string) $request->get_param( 'key' ) );

		if ( '' !== $key && $provider instanceof Provider ) {
			$provider = clone $provider;
			$provider->set_key( $key );
		}

		$result = $provider->search( 'nature', array( 'per_page' => 3 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/* translators: %s: provider name */
		return rest_ensure_response( array( 'message' => sprintf( __( 'Connected to %s.', 'artificial-image-generator' ), $provider->get_label() ) ) );
	}

	/**
	 * A provider for the REST client.
	 *
	 * @param ProviderInterface $provider Provider.
	 *
	 * @return array
	 */
	public static function describe( ProviderInterface $provider ) {
		return array(
			'id'         => $provider->get_id(),
			'label'      => $provider->get_label(),
			'configured' => $provider->is_configured(),
			'signupUrl'  => esc_url_raw( $provider->get_signup_url() ),
		);
	}

	/**
	 * The configured provider a request names.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return ProviderInterface|\WP_Error
	 */
	private function provider( \WP_REST_Request $request ) {
		$provider = Registry::get( (string) $request['provider'] );

		if ( ! $provider ) {
			return new \WP_Error( 'aimg_stock_unknown_provider', __( 'Unknown stock photo library.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		if ( ! $provider->is_configured() ) {
			/* translators: %s: provider name */
			return new \WP_Error( 'aimg_stock_no_key', sprintf( __( 'Add your %s API key on the Image Generator settings page.', 'artificial-image-generator' ), $provider->get_label() ), array( 'status' => 400 ) );
		}

		return $provider;
	}
}
