<?php

namespace ArtificialImageGenerator\Templates;

use ArtificialImageGenerator\Rendering\Capabilities;
use ArtificialImageGenerator\Rendering\GdRenderer;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * REST endpoints for managing templates as v2 documents:
 *
 * - `POST aimg/v1/templates` create
 * - `GET|PUT|PATCH|DELETE aimg/v1/templates/{id}` read, update, delete
 * - `POST aimg/v1/templates/preview` render an unsaved document
 * - `GET aimg/v1/capabilities` what this server can render
 *
 * `GET aimg/v1/templates` (the list the editor modal uses) stays in RestAPI, unchanged.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class RestController {

	/**
	 * Previews a user may render per minute.
	 *
	 * @var int
	 */
	const PREVIEWS_PER_MINUTE = 60;

	/**
	 * Register the routes.
	 *
	 * @param string $route_namespace REST namespace.
	 *
	 * @return void
	 */
	public function register_routes( $route_namespace ) {
		$document = array(
			'type'        => array( 'object', 'string' ),
			'required'    => false,
			'description' => __( 'Template document (schema v2), as an object or JSON.', 'artificial-image-generator' ),
		);

		register_rest_route(
			$route_namespace,
			'/templates',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'args'                => array(
					'title'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'   => array(
						'type'    => 'string',
						'enum'    => array( 'publish', 'draft' ),
						'default' => 'publish',
					),
					'document' => $document,
				),
			)
		);

		register_rest_route(
			$route_namespace,
			'/templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
					'args'                => array(
						'title'    => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'   => array(
							'type'     => 'string',
							'enum'     => array( 'publish', 'draft' ),
							'required' => false,
						),
						'document' => $document,
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$route_namespace,
			'/templates/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'check_preview_permission' ),
				'args'                => array(
					'document' => array_merge( $document, array( 'required' => true ) ),
					'title'    => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'aimg_plain_text',
					),
					'post_id'  => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$route_namespace,
			'/capabilities',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'capabilities' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$route_namespace,
			'/merge-tags/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'merge_tags' ),
				'permission_callback' => array( $this, 'check_preview_permission' ),
			)
		);
	}

	/**
	 * Merge tag values and post images for a post, for previewing a template with it.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function merge_tags( \WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );

		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'aimg_post_not_found', __( 'Post not found.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		$images = array();
		foreach ( array( 'featured', 'first', 'author_avatar' ) as $source ) {
			$id                = \ArtificialImageGenerator\Rendering\Images::dynamic( $source, $post_id );
			$images[ $source ] = $id ? (string) wp_get_attachment_image_url( $id, 'large' ) : '';
		}

		return rest_ensure_response(
			array(
				'postId' => $post_id,
				'tags'   => MergeTags::values( $post_id ),
				'images' => $images,
			)
		);
	}

	/**
	 * Capability needed to manage templates.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability needed to create, edit and delete image templates.
		 *
		 * @param string $capability Capability. Default 'manage_options', as for the template screens.
		 *
		 * @since 1.7.0
		 */
		return (string) apply_filters( 'aimg_manage_templates_capability', 'manage_options' );
	}

	/**
	 * Permission callback for managing templates.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_manage_permission() {
		if ( current_user_can( self::capability() ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to manage image templates.', 'artificial-image-generator' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Permission callback for previews: managing templates, and editing the post used for merge tags.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_preview_permission( \WP_REST_Request $request ) {
		$allowed = $this->check_manage_permission();
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( true === $allowed && $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this post.', 'artificial-image-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $allowed;
	}

	/**
	 * Create a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( \WP_REST_Request $request ) {
		$title = trim( (string) $request->get_param( 'title' ) );

		if ( '' === $title ) {
			return new \WP_Error( 'aimg_template_title', __( 'Give the template a title.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'aimg_template',
				'post_title'  => $title,
				'post_status' => (string) $request->get_param( 'status' ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$document = $request->get_param( 'document' );
		Repository::save_document( $id, null === $document ? Schema::starter() : $document );
		Repository::update_preview( $id );

		$response = rest_ensure_response( $this->prepare( get_post( $id ) ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Read a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$template = $this->find( $request );

		return is_wp_error( $template ) ? $template : rest_ensure_response( $this->prepare( $template ) );
	}

	/**
	 * Update a template's title, status or document.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( \WP_REST_Request $request ) {
		$template = $this->find( $request );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$changes = array( 'ID' => $template->ID );

		if ( null !== $request->get_param( 'title' ) ) {
			$changes['post_title'] = trim( (string) $request->get_param( 'title' ) );

			if ( '' === $changes['post_title'] ) {
				return new \WP_Error( 'aimg_template_title', __( 'Give the template a title.', 'artificial-image-generator' ), array( 'status' => 400 ) );
			}
		}

		if ( null !== $request->get_param( 'status' ) ) {
			$changes['post_status'] = (string) $request->get_param( 'status' );
		}

		if ( count( $changes ) > 1 ) {
			$result = wp_update_post( $changes, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( null !== $request->get_param( 'document' ) ) {
			Repository::save_document( $template->ID, $request->get_param( 'document' ) );
		}

		Repository::update_preview( $template->ID );

		return rest_ensure_response( $this->prepare( get_post( $template->ID ) ) );
	}

	/**
	 * Delete a template permanently, with its preview image. Generated images stay.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( \WP_REST_Request $request ) {
		$template = $this->find( $request );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$previous = $this->prepare( $template );

		if ( ! wp_delete_post( $template->ID, true ) ) {
			return new \WP_Error( 'aimg_template_delete', __( 'The template could not be deleted.', 'artificial-image-generator' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Render an unsaved document and return it as a data URI. Nothing is written to disk.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( \WP_REST_Request $request ) {
		$allowed = $this->consume_preview();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$post_id  = absint( $request->get_param( 'post_id' ) );
		$title    = trim( (string) $request->get_param( 'title' ) );
		$document = Schema::sanitize( $request->get_param( 'document' ) );
		$started  = microtime( true );

		if ( '' === $title && ! $post_id ) {
			$title = __( 'Your post title appears here', 'artificial-image-generator' );
		}

		$values = MergeTags::values( $post_id, '' !== $title ? array( 'title' => $title ) : array() );
		$image  = GdRenderer::render( $document, $values, $post_id );

		if ( ! $image ) {
			return new \WP_Error( 'aimg_cannot_render', __( 'This server cannot render images (GD with FreeType is required).', 'artificial-image-generator' ), array( 'status' => 500 ) );
		}

		$format = $document['output']['format'];

		ob_start();
		if ( 'jpeg' === $format ) {
			imagejpeg( $image, null, $document['output']['quality'] );
		} elseif ( 'webp' === $format ) {
			imagewebp( $image, null, $document['output']['quality'] );
		} else {
			imagepng( $image );
		}
		$bytes = (string) ob_get_clean();
		imagedestroy( $image );

		return rest_ensure_response(
			array(
				'image'  => 'data:image/' . $format . ';base64,' . base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Inline preview image.
				'width'  => $document['canvas']['width'],
				'height' => $document['canvas']['height'],
				'format' => $format,
				'bytes'  => strlen( $bytes ),
				'ms'     => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
	}

	/**
	 * What this server can render.
	 *
	 * @return \WP_REST_Response
	 */
	public function capabilities() {
		return rest_ensure_response( Capabilities::all() );
	}

	/**
	 * The template a request is about.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_Post|\WP_Error
	 */
	private function find( \WP_REST_Request $request ) {
		$template = aimg_get_template( absint( $request['id'] ) );

		if ( ! $template || 'trash' === $template->post_status ) {
			return new \WP_Error( 'aimg_template_not_found', __( 'Template not found.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		return $template;
	}

	/**
	 * Response data for a template.
	 *
	 * @param \WP_Post $template Template.
	 *
	 * @return array
	 */
	private function prepare( $template ) {
		$document = Repository::get_document( $template->ID );

		return array(
			'id'          => (int) $template->ID,
			'title'       => aimg_plain_text( $template->post_title ),
			'status'      => $template->post_status,
			'hasDocument' => Repository::has_document( $template->ID ),
			'document'    => $document,
			'preview'     => esc_url_raw( (string) get_post_meta( $template->ID, '_aimg_preview_image_url', true ) ),
			'width'       => $document['canvas']['width'],
			'height'      => $document['canvas']['height'],
			'modified'    => mysql_to_rfc3339( $template->post_modified_gmt ),
		);
	}

	/**
	 * Count a preview against the user's per-minute limit.
	 *
	 * @return true|\WP_Error
	 */
	private function consume_preview() {
		$key   = 'aimg_previews_' . get_current_user_id();
		$usage = get_transient( $key );
		$now   = time();

		if ( ! is_array( $usage ) || $now - (int) $usage['start'] >= MINUTE_IN_SECONDS ) {
			$usage = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( $usage['count'] >= self::PREVIEWS_PER_MINUTE ) {
			return new \WP_Error( 'aimg_preview_rate_limited', __( 'Too many previews. Please wait a moment.', 'artificial-image-generator' ), array( 'status' => 429 ) );
		}

		++$usage['count'];
		set_transient( $key, $usage, MINUTE_IN_SECONDS );

		return true;
	}
}
