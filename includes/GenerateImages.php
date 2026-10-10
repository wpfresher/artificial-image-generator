<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Class GenerateImages
 *
 * This class is responsible for generating thumbnails.
 *
 * @since 1.0.0
 * @package ArtificialImageGenerator
 */
class GenerateImages {

	/**
	 * Post IDs currently being deleted.
	 *
	 * @since 1.6.0
	 * @var array
	 */
	protected $deleting = array();

	/**
	 * GenerateImages constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Runs after the post's meta (including a featured image chosen in the
		// block editor) has been saved. On save_post the REST controller has not
		// set the featured image yet, so has_post_thumbnail() would be false.
		add_action( 'wp_after_insert_post', array( $this, 'generate_thumbnails' ) );
		add_action( 'before_delete_post', array( $this, 'mark_deleting' ) );
		add_action( 'delete_post_meta', array( $this, 'maybe_disable_auto_generation' ), 10, 3 );
	}

	/**
	 * Remember that a post is being deleted, so removing its meta isn't read as
	 * the author removing the featured image.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function mark_deleting( $post_id ) {
		$this->deleting[ $post_id ] = true;
	}

	/**
	 * Turn off automatic generation for a post when its author removes a
	 * generated featured image; otherwise the next save would create a new one.
	 *
	 * @param array  $meta_ids  Meta IDs being deleted.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function maybe_disable_auto_generation( $meta_ids, $object_id, $meta_key ) {
		if ( '_thumbnail_id' !== $meta_key || isset( $this->deleting[ $object_id ] ) ) {
			return;
		}

		$thumbnail_id = (int) get_post_meta( $object_id, '_thumbnail_id', true );

		if ( $thumbnail_id && '1' === get_post_meta( $thumbnail_id, '_aimg_generated', true ) ) {
			update_post_meta( $object_id, '_aimg_disable_auto', '1' );
		}
	}

	/**
	 * Generate a thumbnail image using GD library after a post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @since 1.0.0
	 */
	public function generate_thumbnails( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		if ( 'post' === $post_type && 'yes' !== aimg_get_settings( 'is_post_thumbnail', 'yes' ) ) {
			return;
		}

		if ( 'page' === $post_type && 'yes' !== aimg_get_settings( 'is_page_thumbnail' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip auto-drafts.
		if ( 'auto-draft' === get_post_status( $post_id ) ) {
			return;
		}

		// Check if the post already has a thumbnail or not.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		// The author removed a generated image from this post; respect that.
		if ( '1' === get_post_meta( $post_id, '_aimg_disable_auto', true ) ) {
			return;
		}

		if ( '' === aimg_get_plain_title( $post_id ) ) {
			return;
		}

		$method = Generator::get_runnable_method();

		if ( '' === $method ) {
			return;
		}

		if ( Generator::runs_in_background_for_post( $method, $post_id ) ) {
			if ( self::can_use_ai( $post_id ) && ! self::last_job_failed( $post_id ) ) {
				Queue::enqueue( $post_id, $method );
			}

			return;
		}

		$attachment_id = Generator::generate_template_for_post( $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			if ( Generator::METHOD_TEMPLATE_AI === $method && self::can_use_ai( $post_id ) && ! self::last_job_failed( $post_id ) ) {
				Queue::enqueue( $post_id, Generator::METHOD_AI );
			}

			return;
		}

		set_post_thumbnail( $post_id, $attachment_id );
	}

	/**
	 * Whether the post's last background job failed. Saving doesn't retry it, so a
	 * failing service isn't called again on every save; "Try again" in the editor does.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public static function last_job_failed( $post_id ) {
		return 'failed' === Queue::get_status( $post_id )['status'];
	}

	/**
	 * Whether a post may get an AI featured image automatically.
	 *
	 * AI images cost money, so drafts don't get one until they are published or
	 * scheduled; authors can still generate one from the editor.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public static function can_use_ai( $post_id ) {
		/**
		 * Filter the post statuses that get an AI featured image automatically.
		 *
		 * @param string[] $statuses Post statuses.
		 * @param int      $post_id  Post ID.
		 *
		 * @since 1.6.0
		 */
		$statuses = (array) apply_filters( 'aimg_auto_generate_statuses', array( 'publish', 'future', 'private' ), $post_id );

		return in_array( get_post_status( $post_id ), $statuses, true );
	}
}
