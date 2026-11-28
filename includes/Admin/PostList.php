<?php

namespace ArtificialImageGenerator\Admin;

use ArtificialImageGenerator\Bulk\Runner;
use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\GenerateImages;
use ArtificialImageGenerator\Queue;
use ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Post list integration: an "Image" column, a "Regenerate image" row action and a
 * "Regenerate featured images" bulk action that starts a Bulk Generate run.
 *
 * @since 1.9.1
 * @package ArtificialImageGenerator\Admin
 */
class PostList {

	/**
	 * Admin-post action of the row action.
	 *
	 * @var string
	 */
	const ACTION = 'aimg_regenerate';

	/**
	 * Bulk action name.
	 *
	 * @var string
	 */
	const BULK_ACTION = 'aimg_regenerate';

	/**
	 * Column name.
	 *
	 * @var string
	 */
	const COLUMN = 'aimg_image';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'load-edit.php', array( $this, 'register' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_regenerate' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}

	/**
	 * Drop the result flags from the address bar once the notice is shown.
	 *
	 * @param string[] $args Query args.
	 *
	 * @return string[]
	 */
	public function removable_query_args( $args ) {
		return array_merge( $args, array( 'aimg_regenerated', 'aimg_error' ) );
	}

	/**
	 * Hook into the list of the current post type when it has featured images.
	 *
	 * @return void
	 */
	public function register() {
		$screen    = get_current_screen();
		$post_type = $screen ? $screen->post_type : '';

		if ( ! $post_type || ! isset( Runner::post_types()[ $post_type ] ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
		add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		add_filter( is_post_type_hierarchical( $post_type ) ? 'page_row_actions' : 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'styles' ) );

		/** This filter is documented in includes/Bulk/RestController.php */
		if ( current_user_can( apply_filters( 'aimg_bulk_capability', 'manage_options' ) ) ) {
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Add the column after the title.
	 *
	 * @param array $columns Columns.
	 *
	 * @return array
	 */
	public function add_column( $columns ) {
		$added = array();

		foreach ( $columns as $key => $label ) {
			$added[ $key ] = $label;

			if ( 'title' === $key ) {
				$added[ self::COLUMN ] = __( 'Image', 'artificial-image-generator' );
			}
		}

		if ( ! isset( $added[ self::COLUMN ] ) ) {
			$added[ self::COLUMN ] = __( 'Image', 'artificial-image-generator' );
		}

		return $added;
	}

	/**
	 * The column: thumbnail, where a generated image came from, and a pending or failed job.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 *
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
		$status       = Queue::get_status( $post_id );

		echo '<div class="aimg-col">';

		if ( $thumbnail_id ) {
			echo wp_get_attachment_image( $thumbnail_id, array( 60, 60 ), false, array( 'class' => 'aimg-col__thumb' ) );
		} else {
			echo '<span class="aimg-col__none" aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No featured image', 'artificial-image-generator' ) . '</span>';
		}

		if ( in_array( $status['status'], array( 'queued', 'running' ), true ) ) {
			echo '<span class="aimg-col__badge is-pending">' . esc_html__( 'Generating…', 'artificial-image-generator' ) . '</span>';
		} elseif ( 'failed' === $status['status'] && ! $thumbnail_id ) {
			echo '<span class="aimg-col__badge is-failed" title="' . esc_attr( $status['error'] ) . '">' . esc_html__( 'Failed', 'artificial-image-generator' ) . '</span>';
		} elseif ( $thumbnail_id && '1' === get_post_meta( $thumbnail_id, Generator::GENERATED_META, true ) ) {
			echo '<span class="aimg-col__badge">' . esc_html( self::source_label( $thumbnail_id ) ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Where a generated image came from: Template, AI or Stock.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	public static function source_label( $attachment_id ) {
		$data = (array) get_post_meta( $attachment_id, Generator::GENERATED_DATA_META, true );

		if ( ! empty( $data['provider'] ) ) {
			return __( 'AI', 'artificial-image-generator' );
		}

		if ( isset( $data['source'] ) && Stock\Registry::get( (string) $data['source'] ) ) {
			return __( 'Stock', 'artificial-image-generator' );
		}

		return __( 'Template', 'artificial-image-generator' );
	}

	/**
	 * Add "Generate image" / "Regenerate image" to a post's row actions.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post.
	 *
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) || 'trash' === $post->post_status || '' === Generator::get_runnable_method() ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $post->ID
		);

		$actions[ self::ACTION ] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			has_post_thumbnail( $post ) ? esc_html__( 'Regenerate image', 'artificial-image-generator' ) : esc_html__( 'Generate image', 'artificial-image-generator' )
		);

		return $actions;
	}

	/**
	 * Generate one post's featured image, replacing the current one.
	 *
	 * @return void
	 */
	public function handle_regenerate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( self::ACTION . '_' . $post_id );

		if ( ! $post_id || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to generate an image for this post.', 'artificial-image-generator' ), 403 );
		}

		$result = self::regenerate( $post_id );
		$back   = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . get_post_type( $post_id ) );
		$back   = remove_query_arg( array( 'aimg_regenerated', 'aimg_error', 'aimg_bulk' ), $back );

		if ( is_wp_error( $result ) ) {
			set_transient( 'aimg_regenerate_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
			$back = add_query_arg( 'aimg_error', 1, $back );
		} else {
			$back = add_query_arg( 'aimg_regenerated', $result, $back );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Generate a post's featured image with the configured method: right away for templates,
	 * as a background job when the method or template calls a remote service.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string|\WP_Error 'done' or 'queued'.
	 */
	public static function regenerate( $post_id ) {
		$method = Generator::get_runnable_method();

		if ( '' === $method ) {
			return new \WP_Error( 'aimg_no_method', __( 'The image method in Settings needs an API key.', 'artificial-image-generator' ) );
		}

		if ( '' === aimg_get_plain_title( $post_id ) ) {
			return new \WP_Error( 'aimg_no_title', __( 'Add a title to the post first.', 'artificial-image-generator' ) );
		}

		if ( Generator::runs_in_background_for_post( $method, $post_id ) ) {
			if ( Generator::method_starts_with_ai( $method ) && ! aimg_user_can_use_ai() ) {
				return new \WP_Error( 'aimg_ai_forbidden', __( 'You are not allowed to generate AI images.', 'artificial-image-generator' ) );
			}

			if ( ! Queue::enqueue( $post_id, $method, true ) ) {
				return new \WP_Error( 'aimg_pending', __( 'An image for this post is already being generated.', 'artificial-image-generator' ) );
			}

			return 'queued';
		}

		$attachment_id = Generator::generate_for_post( $post_id, $method );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		delete_post_meta( $post_id, '_aimg_disable_auto' );

		return 'done';
	}

	/**
	 * Add the bulk action.
	 *
	 * @param array $actions Bulk actions.
	 *
	 * @return array
	 */
	public function bulk_actions( $actions ) {
		if ( '' !== Generator::get_runnable_method() ) {
			$actions[ self::BULK_ACTION ] = __( 'Regenerate featured images', 'artificial-image-generator' );
		}

		return $actions;
	}

	/**
	 * Start a Bulk Generate run for the selected posts and show its progress.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Selected post IDs.
	 *
	 * @return string
	 */
	public function handle_bulk_action( $redirect, $action, $ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $redirect;
		}

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ), array( __CLASS__, 'can_edit' ) ) );
		$run = Runner::start(
			array(
				'ids'        => $ids,
				'post_types' => array( get_current_screen()->post_type ),
				'statuses'   => array_keys( Runner::statuses() ),
				'replace'    => true,
			)
		);

		if ( is_wp_error( $run ) ) {
			set_transient( 'aimg_regenerate_error_' . get_current_user_id(), $run->get_error_message(), MINUTE_IN_SECONDS );

			return add_query_arg( 'aimg_error', 1, remove_query_arg( 'aimg_error', $redirect ) );
		}

		return admin_url( 'admin.php?page=aimg-bulk' );
	}

	/**
	 * Whether the current user can edit a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	public static function can_edit( $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Result notices on the post list.
	 *
	 * @return void
	 */
	public function notices() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only flags set by our own redirect.
		$done  = isset( $_GET['aimg_regenerated'] ) ? sanitize_key( $_GET['aimg_regenerated'] ) : '';
		$error = isset( $_GET['aimg_error'] );
		// phpcs:enable

		if ( 'done' === $done ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'The featured image was generated.', 'artificial-image-generator' ) );
		} elseif ( 'queued' === $done ) {
			printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html__( 'The featured image is being generated in the background. Reload the page in a minute to see it.', 'artificial-image-generator' ) );
		} elseif ( $error ) {
			$key     = 'aimg_regenerate_error_' . get_current_user_id();
			$message = (string) get_transient( $key );
			delete_transient( $key );

			if ( '' !== $message ) {
				printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
			}
		}
	}

	/**
	 * Column styles (small enough not to need a stylesheet).
	 *
	 * @return void
	 */
	public function styles() {
		?>
		<style>
			.column-aimg_image { width: 90px; }
			.aimg-col { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
			.aimg-col__thumb { width: 60px; height: 40px; object-fit: cover; border-radius: 3px; }
			.aimg-col__none { color: #a7aaad; }
			.aimg-col__badge { padding: 1px 6px; border-radius: 9px; background: #efe9fe; color: #4b25b8; font-size: 11px; line-height: 18px; }
			.aimg-col__badge.is-pending { background: #fcf9e8; color: #8a6d00; }
			.aimg-col__badge.is-failed { background: #fcf0f1; color: #b32d2e; }
		</style>
		<?php
	}
}
