<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Background generation of featured images.
 *
 * AI requests take up to a minute, so they run outside the request that saved
 * the post: through Action Scheduler when it is available (e.g. with
 * WooCommerce), otherwise through WP-Cron.
 *
 * @since 1.6.0
 * @package ArtificialImageGenerator
 */
class Queue {

	/**
	 * Action that runs a job.
	 *
	 * @var string
	 */
	const HOOK = 'aimg_generate_featured_image';

	/**
	 * Post meta holding the job status: queued, running, done or failed.
	 *
	 * @var string
	 */
	const STATUS_META = '_aimg_generation_status';

	/**
	 * Post meta holding the error message of a failed job.
	 *
	 * @var string
	 */
	const ERROR_META = '_aimg_generation_error';

	/**
	 * Post meta holding when the status last changed.
	 *
	 * @var string
	 */
	const TIME_META = '_aimg_generation_time';

	/**
	 * A queued or running job older than this is considered lost.
	 *
	 * @var int
	 */
	const STALE_AFTER = 15 * MINUTE_IN_SECONDS;

	/**
	 * Post meta holding the arguments of the pending job.
	 *
	 * @var string
	 */
	const JOB_META = '_aimg_generation_job';

	/**
	 * AJAX action that starts a queued job right away.
	 *
	 * @var string
	 */
	const KICK_ACTION = 'aimg_run_job';

	/**
	 * Whether this request already started a job right away.
	 *
	 * @var bool
	 */
	private static $kicked = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 3 );
		add_action( 'wp_ajax_' . self::KICK_ACTION, array( __CLASS__, 'handle_kick' ) );
		add_action( 'wp_ajax_nopriv_' . self::KICK_ACTION, array( __CLASS__, 'handle_kick' ) );
	}

	/**
	 * Queue a featured image for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $method  Generation method.
	 * @param bool   $replace Replace an existing featured image.
	 *
	 * @return bool False when a job for the post is already pending.
	 */
	public static function enqueue( $post_id, $method, $replace = false ) {
		$status = self::get_status( $post_id );

		if ( in_array( $status['status'], array( 'queued', 'running' ), true ) ) {
			return false;
		}

		self::set_status( $post_id, 'queued' );

		$args = array( (int) $post_id, (string) $method, (bool) $replace );
		update_post_meta( $post_id, self::JOB_META, $args );

		if ( self::use_action_scheduler() ) {
			as_enqueue_async_action( self::HOOK, $args, 'aimg' );
		} else {
			wp_schedule_single_event( time(), self::HOOK, $args );
		}

		self::kick( $post_id );

		return true;
	}

	/**
	 * Start a queued job right away with a non-blocking request to this site.
	 *
	 * Action Scheduler only starts work on admin page loads, at most once a
	 * minute, and WP-Cron waits for the next page view. The scheduled action
	 * stays as a backup in case the request can't reach the site.
	 *
	 * Only the first job queued in a request is started this way, so a bulk
	 * publish doesn't start a PHP process per post; the rest run in the background.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private static function kick( $post_id ) {
		if ( self::$kicked ) {
			return;
		}

		/**
		 * Filter whether a queued job is started right away with a request to this site.
		 *
		 * @param bool $kick    Whether to start it right away.
		 * @param int  $post_id Post ID.
		 *
		 * @since 1.6.0
		 */
		if ( ! apply_filters( 'aimg_kick_queue', true, $post_id ) ) {
			return;
		}

		self::$kicked = true;
		$token        = wp_generate_password( 32, false );
		set_transient( 'aimg_kick_' . $post_id, $token, self::STALE_AFTER );

		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action'  => self::KICK_ACTION,
					'post_id' => $post_id,
					'token'   => $token,
				),
			)
		);
	}

	/**
	 * AJAX handler for the request sent by `kick()`.
	 *
	 * @return void
	 */
	public static function handle_kick() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Authenticated by the single-use token instead; the request comes from the server itself, without cookies.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable

		if ( self::run_kicked( $post_id, $token ) ) {
			wp_die( '', '', array( 'response' => 200 ) );
		}

		wp_die( '', '', array( 'response' => 403 ) );
	}

	/**
	 * Run a job started by `kick()`, if the token matches.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Token sent with the request.
	 *
	 * @return bool Whether the token was valid.
	 */
	public static function run_kicked( $post_id, $token ) {
		$expected = get_transient( 'aimg_kick_' . $post_id );

		if ( ! $post_id || ! is_string( $expected ) || '' === $token || ! hash_equals( $expected, $token ) ) {
			return false;
		}

		delete_transient( 'aimg_kick_' . $post_id );

		$args = get_post_meta( $post_id, self::JOB_META, true );
		if ( ! is_array( $args ) || 3 !== count( $args ) ) {
			return true;
		}

		ignore_user_abort( true );
		self::run( $args[0], $args[1], $args[2] );

		return true;
	}

	/**
	 * Move a queued job to running, so only one process gets to run it.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool Whether this process claimed the job.
	 */
	private static function claim( $post_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- An atomic compare-and-set; the meta API can't express one.
		$claimed = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = 'running' WHERE post_id = %d AND meta_key = %s AND meta_value = 'queued'",
				$post_id,
				self::STATUS_META
			)
		);

		wp_cache_delete( $post_id, 'post_meta' );

		if ( $claimed ) {
			update_post_meta( $post_id, self::TIME_META, time() );
		}

		return $claimed > 0;
	}

	/**
	 * Run a job.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $method  Generation method.
	 * @param bool   $replace Replace an existing featured image.
	 *
	 * @return void
	 */
	public static function run( $post_id, $method = '', $replace = false ) {
		$post_id = (int) $post_id;

		if ( ! get_post( $post_id ) || ! self::claim( $post_id ) ) {
			return;
		}

		delete_post_meta( $post_id, self::JOB_META );

		// Act as the post's author, so the image is uploaded by them.
		$user_id = get_current_user_id();
		wp_set_current_user( (int) get_post_field( 'post_author', $post_id ) );

		$attachment_id = Generator::generate_for_post( $post_id, $method );

		wp_set_current_user( $user_id );

		if ( is_wp_error( $attachment_id ) ) {
			self::set_status( $post_id, 'failed', $attachment_id->get_error_message() );

			return;
		}

		// The author chose an image while we were working; theirs wins.
		if ( ! $replace && has_post_thumbnail( $post_id ) ) {
			// A stock photo may be one imported earlier and used elsewhere.
			if ( ! get_post_meta( $attachment_id, Stock\Importer::KEY_META, true ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		} else {
			set_post_thumbnail( $post_id, $attachment_id );
		}

		self::set_status( $post_id, 'done' );
	}

	/**
	 * A post's job status.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array { @type string $status, @type string $error }
	 */
	public static function get_status( $post_id ) {
		$status = (string) get_post_meta( $post_id, self::STATUS_META, true );
		$error  = (string) get_post_meta( $post_id, self::ERROR_META, true );
		$time   = (int) get_post_meta( $post_id, self::TIME_META, true );

		if ( in_array( $status, array( 'queued', 'running' ), true ) && time() - $time > self::STALE_AFTER ) {
			$status = 'failed';
			$error  = __( 'The image was not generated in time. Please try again.', 'artificial-image-generator' );
		}

		return array(
			'status' => $status,
			'error'  => $error,
		);
	}

	/**
	 * Update a post's job status.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  queued, running, done or failed.
	 * @param string $error   Error message for a failed job.
	 *
	 * @return void
	 */
	public static function set_status( $post_id, $status, $error = '' ) {
		update_post_meta( $post_id, self::STATUS_META, $status );
		update_post_meta( $post_id, self::TIME_META, time() );

		if ( '' !== $error ) {
			update_post_meta( $post_id, self::ERROR_META, $error );
		} else {
			delete_post_meta( $post_id, self::ERROR_META );
		}
	}

	/**
	 * Whether jobs go through Action Scheduler.
	 *
	 * @return bool
	 */
	private static function use_action_scheduler() {
		/**
		 * Filter whether background jobs use Action Scheduler when it is available.
		 *
		 * @param bool $use Whether to use Action Scheduler.
		 *
		 * @since 1.6.0
		 */
		return function_exists( 'as_enqueue_async_action' ) && apply_filters( 'aimg_use_action_scheduler', true );
	}
}
