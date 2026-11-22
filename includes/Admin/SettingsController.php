<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * REST routes of the settings app: `GET /settings` (schema, values, API key state) and
 * `POST /settings` (save).
 *
 * @since 1.9.0
 * @package ArtificialImageGenerator\Admin
 */
class SettingsController {

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
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'values' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Only administrators change the settings.
	 *
	 * @return true|\WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to change the settings.', 'artificial-image-generator' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Schema, values and API key state.
	 *
	 * @return \WP_REST_Response
	 */
	public function get() {
		return rest_ensure_response( self::payload() );
	}

	/**
	 * Save the settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update( $request ) {
		Settings::save( (array) $request->get_param( 'values' ) );

		return rest_ensure_response( self::payload() );
	}

	/**
	 * What the settings app needs.
	 *
	 * @return array
	 */
	private static function payload() {
		return array_merge(
			Settings::schema(),
			array(
				'values'  => Settings::values(),
				'secrets' => Settings::secrets(),
			)
		);
	}
}
