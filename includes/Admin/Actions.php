<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The admin Actions class.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator/Admin
 */
class Actions {

	/**
	 * Actions constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_post_aimg_template_action', array( __CLASS__, 'template_action' ) );
	}

	/**
	 * URL of a template action from the templates list.
	 *
	 * @param string $action      duplicate, default, undefault, export or delete.
	 * @param int    $template_id Template ID.
	 *
	 * @since 1.7.0
	 * @return string
	 */
	public static function action_url( $action, $template_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'aimg_template_action',
					'do'       => $action,
					'template' => (int) $template_id,
				),
				admin_url( 'admin-post.php' )
			),
			'aimg_template_' . $action . '_' . (int) $template_id
		);
	}

	/**
	 * Run a template action from the templates list.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function template_action() {
		$action      = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$template_id = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;
		$list_url    = admin_url( 'admin.php?page=image-generator' );

		check_admin_referer( 'aimg_template_' . $action . '_' . $template_id );

		if ( ! current_user_can( 'manage_options' ) || ! aimg_get_template( $template_id ) ) {
			artificial_image_generator()->flash_notice( __( 'You do not have permission to process this action', 'artificial-image-generator' ), 'error' );
			wp_safe_redirect( $list_url );
			exit;
		}

		switch ( $action ) {
			case 'duplicate':
				$copy = \ArtificialImageGenerator\Templates\Repository::duplicate( $template_id );
				if ( is_wp_error( $copy ) ) {
					artificial_image_generator()->flash_notice( $copy->get_error_message(), 'error' );
				} else {
					artificial_image_generator()->flash_notice( __( 'Template duplicated as a draft.', 'artificial-image-generator' ) );
				}
				break;

			case 'default':
			case 'undefault':
				$settings                        = (array) get_option( 'aimg_settings', array() );
				$settings['default_template_id'] = 'default' === $action ? $template_id : 0;
				update_option( 'aimg_settings', $settings );
				artificial_image_generator()->flash_notice(
					'default' === $action
						? __( 'Automatic featured images now use this template.', 'artificial-image-generator' )
						: __( 'Automatic featured images now use a random template.', 'artificial-image-generator' )
				);
				break;

			case 'export':
				$data = \ArtificialImageGenerator\Templates\Repository::export( $template_id );
				$name = sanitize_file_name( ( '' !== $data['title'] ? sanitize_title( $data['title'] ) : 'template' ) . '.json' );

				nocache_headers();
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $name . '"' );
				echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				exit;

			case 'delete':
				wp_delete_post( $template_id, true );
				artificial_image_generator()->flash_notice( __( 'Template deleted.', 'artificial-image-generator' ) );
				break;
		}

		wp_safe_redirect( $list_url );
		exit;
	}
}
