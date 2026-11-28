<?php

namespace ArtificialImageGenerator\Bulk;

use ArtificialImageGenerator\Generator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * REST routes of the Bulk Generate page: `GET /bulk` (run state and filter options),
 * `POST /bulk/preview` (dry run), `POST /bulk` (start) and `POST /bulk/{action}`
 * (pause, resume, cancel, clear).
 *
 * @since 1.9.1
 * @package ArtificialImageGenerator\Bulk
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
		$args = array(
			'args' => array(
				'type'    => 'object',
				'default' => array(),
			),
		);

		register_rest_route(
			$namespace_name,
			'/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => $args,
				),
			)
		);

		register_rest_route(
			$namespace_name,
			'/bulk/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => $args,
			)
		);

		register_rest_route(
			$namespace_name,
			'/bulk/(?P<action>pause|resume|cancel|clear)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'control' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Only administrators run bulk generation.
	 *
	 * @return true|\WP_Error
	 */
	public function can_manage() {
		/**
		 * Filter the capability needed to run bulk generation.
		 *
		 * @param string $capability Capability.
		 *
		 * @since 1.9.1
		 */
		if ( current_user_can( apply_filters( 'aimg_bulk_capability', 'manage_options' ) ) ) {
			return true;
		}

		return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to generate images in bulk.', 'artificial-image-generator' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Run state and the options for the filters. Also starts the next batch while the page
	 * polls, so a run moves on even when WP-Cron is slow.
	 *
	 * @return \WP_REST_Response
	 */
	public function get() {
		Runner::kick();

		$methods = array();
		foreach ( Generator::get_methods() as $id => $label ) {
			$methods[] = array(
				'value'    => $id,
				'label'    => $label,
				'runnable' => Generator::get_runnable_method( $id ) === $id,
			);
		}

		$categories = array();
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $term ) {
			$categories[] = array(
				'value' => $term->term_id,
				'label' => $term->name,
			);
		}

		return rest_ensure_response(
			array(
				'run'      => Runner::state(),
				'defaults' => Runner::sanitize_args( array() ),
				'options'  => array(
					'post_types' => self::options( Runner::post_types() ),
					'statuses'   => self::options( Runner::statuses() ),
					'methods'    => $methods,
					'categories' => $categories,
				),
			)
		);
	}

	/**
	 * Dry run.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( $request ) {
		return rest_ensure_response( Runner::preview( (array) $request['args'] ) );
	}

	/**
	 * Start a run.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( $request ) {
		return rest_ensure_response( Runner::start( (array) $request['args'] ) );
	}

	/**
	 * Pause, resume, cancel or clear the run.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function control( $request ) {
		return rest_ensure_response( Runner::control( (string) $request['action'] ) );
	}

	/**
	 * A value => label map as a list for SelectControl.
	 *
	 * @param array $map Values and labels.
	 *
	 * @return array
	 */
	private static function options( $map ) {
		$list = array();

		foreach ( $map as $value => $label ) {
			$list[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return $list;
	}
}
