<?php

namespace ArtificialImageGenerator\Bulk;

use ArtificialImageGenerator\Generator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Bulk generation of featured images for existing posts.
 *
 * One run at a time, stored in an option. Batches run through Action Scheduler or WP-Cron,
 * and while the Bulk Generate page is open a loopback request starts the next batch at once.
 * A run waits when the AI hourly limit is reached and can be paused, resumed or cancelled.
 *
 * @since 1.9.1
 * @package ArtificialImageGenerator\Bulk
 */
class Runner {

	/**
	 * Action that runs a batch.
	 *
	 * @var string
	 */
	const HOOK = 'aimg_bulk_batch';

	/**
	 * Option holding the run.
	 *
	 * @var string
	 */
	const OPTION = 'aimg_bulk_run';

	/**
	 * Option used as a lock while a batch runs.
	 *
	 * @var string
	 */
	const LOCK = 'aimg_bulk_lock';

	/**
	 * A lock older than this belongs to a batch that died.
	 *
	 * @var int
	 */
	const LOCK_TIMEOUT = 5 * MINUTE_IN_SECONDS;

	/**
	 * A batch starts no new post after this many seconds.
	 *
	 * @var int
	 */
	const TIME_LIMIT = 20;

	/**
	 * How long a run waits when the AI hourly limit is reached.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WAIT = 5 * MINUTE_IN_SECONDS;

	/**
	 * Most posts one run takes.
	 *
	 * @var int
	 */
	const MAX_POSTS = 10000;

	/**
	 * AJAX action of the loopback request.
	 *
	 * @var string
	 */
	const KICK_ACTION = 'aimg_bulk_run';

	/**
	 * Errors and results kept for the progress screen.
	 *
	 * @var int
	 */
	const KEEP_ITEMS = 50;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'wp_ajax_' . self::KICK_ACTION, array( __CLASS__, 'handle_kick' ) );
		add_action( 'wp_ajax_nopriv_' . self::KICK_ACTION, array( __CLASS__, 'handle_kick' ) );
	}

	/**
	 * Post types with featured images, as name => label.
	 *
	 * @return array
	 */
	public static function post_types() {
		$types = Generator::get_post_types();

		/**
		 * Filter the post types offered for bulk generation.
		 *
		 * @param array $types Post types as name => label.
		 *
		 * @since 1.9.1
		 */
		return (array) apply_filters( 'aimg_bulk_post_types', $types );
	}

	/**
	 * Post statuses offered for bulk generation, as status => label.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'publish' => __( 'Published', 'artificial-image-generator' ),
			'future'  => __( 'Scheduled', 'artificial-image-generator' ),
			'private' => __( 'Private', 'artificial-image-generator' ),
			'draft'   => __( 'Draft', 'artificial-image-generator' ),
			'pending' => __( 'Pending', 'artificial-image-generator' ),
		);
	}

	/**
	 * Clean the run arguments.
	 *
	 * @param array $args Raw arguments.
	 *
	 * @return array
	 */
	public static function sanitize_args( $args ) {
		$args       = is_array( $args ) ? $args : array();
		$post_types = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $args['post_types'] ?? array() ) ), array_keys( self::post_types() ) ) );
		$statuses   = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $args['statuses'] ?? array() ) ), array_keys( self::statuses() ) ) );
		$method     = sanitize_key( $args['method'] ?? '' );
		$date       = function ( $value ) {
			$value = sanitize_text_field( (string) $value );

			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		};

		return array(
			'post_types' => $post_types ? $post_types : array( 'post' ),
			'statuses'   => $statuses ? $statuses : array( 'publish' ),
			'method'     => isset( Generator::get_methods()[ $method ] ) ? $method : Generator::get_method(),
			'replace'    => ! empty( $args['replace'] ),
			'category'   => absint( $args['category'] ?? 0 ),
			'after'      => $date( $args['after'] ?? '' ),
			'before'     => $date( $args['before'] ?? '' ),
			'ids'        => array_slice( array_values( array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) ) ), 0, self::MAX_POSTS ),
		);
	}

	/**
	 * Query arguments for the posts a run covers.
	 *
	 * Without "replace", posts that have a featured image are left out, and so are posts whose
	 * author removed a generated one (`_aimg_disable_auto`).
	 * With `ids` (the post list's bulk action), only those posts.
	 *
	 * @param array $args Sanitized run arguments.
	 *
	 * @return array
	 */
	public static function query_args( $args ) {
		$query = array(
			'post_type'              => $args['post_types'],
			'post_status'            => $args['statuses'],
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		);

		if ( $args['ids'] ) {
			$query['post__in'] = $args['ids'];
		}

		if ( ! $args['replace'] ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One query per preview or run.
				'relation' => 'AND',
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_aimg_disable_auto',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		if ( $args['category'] ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One query per preview or run.
				array(
					'taxonomy' => 'category',
					'terms'    => $args['category'],
				),
			);
		}

		$range = array_filter(
			array(
				'after'     => $args['after'],
				'before'    => $args['before'],
				'inclusive' => true,
			)
		);

		if ( count( $range ) > 1 ) {
			$query['date_query'] = array( $range );
		}

		/**
		 * Filter the query for the posts of a bulk run.
		 *
		 * @param array $query WP_Query arguments.
		 * @param array $args  Run arguments.
		 *
		 * @since 1.9.1
		 */
		return (array) apply_filters( 'aimg_bulk_query_args', $query, $args );
	}

	/**
	 * What a run with these arguments would do, without doing it.
	 *
	 * @param array $args Raw run arguments.
	 *
	 * @return array|\WP_Error
	 */
	public static function preview( $args ) {
		$args   = self::sanitize_args( $args );
		$method = Generator::get_runnable_method( $args['method'] );

		if ( '' === $method ) {
			return self::method_error();
		}

		$query = new \WP_Query( array_merge( self::query_args( $args ), array( 'posts_per_page' => 10 ) ) );
		$count = min( (int) $query->found_posts, self::MAX_POSTS );
		$posts = array();

		foreach ( $query->posts as $post_id ) {
			$posts[] = self::item( $post_id );
		}

		return array(
			'args'        => $args,
			'method'      => $method,
			'count'       => $count,
			'more'        => (int) $query->found_posts > self::MAX_POSTS,
			'ai_requests' => self::uses_ai( $method ) ? $count : 0,
			'hourly'      => absint( aimg_get_settings( 'ai_hourly_limit', 20 ) ),
			'posts'       => $posts,
		);
	}

	/**
	 * Start a run.
	 *
	 * @param array $args Raw run arguments.
	 *
	 * @return array|\WP_Error The run.
	 */
	public static function start( $args ) {
		if ( self::is_active( self::get() ) ) {
			return new \WP_Error( 'aimg_bulk_running', __( 'A bulk run is already in progress.', 'artificial-image-generator' ), array( 'status' => 409 ) );
		}

		$args   = self::sanitize_args( $args );
		$method = Generator::get_runnable_method( $args['method'] );

		if ( '' === $method ) {
			return self::method_error();
		}

		$ids = get_posts( array_merge( self::query_args( $args ), array( 'posts_per_page' => self::MAX_POSTS ) ) );

		if ( ! $ids ) {
			return new \WP_Error( 'aimg_bulk_empty', __( 'No posts match these filters.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$run = array(
			'id'         => wp_generate_password( 12, false ),
			'status'     => 'running',
			'args'       => array_merge( $args, array( 'method' => $method ) ),
			'queue'      => array_map( 'intval', $ids ),
			'total'      => count( $ids ),
			'done'       => 0,
			'skipped'    => 0,
			'failed'     => 0,
			'errors'     => array(),
			'recent'     => array(),
			'user'       => get_current_user_id(),
			'started'    => time(),
			'finished'   => 0,
			'wait_until' => 0,
			'message'    => '',
		);

		self::save( $run );
		self::schedule();
		self::kick();

		return self::state();
	}

	/**
	 * Pause, resume or cancel the run.
	 *
	 * @param string $action pause, resume or cancel.
	 *
	 * @return array|\WP_Error The run.
	 */
	public static function control( $action ) {
		$run = self::get();

		if ( ! $run ) {
			return new \WP_Error( 'aimg_bulk_none', __( 'There is no bulk run.', 'artificial-image-generator' ), array( 'status' => 404 ) );
		}

		if ( 'resume' === $action && 'paused' === $run['status'] ) {
			$run['status']     = 'running';
			$run['wait_until'] = 0;
			$run['message']    = '';
			self::save( $run );
			self::schedule();
			self::kick();
		} elseif ( 'pause' === $action && 'running' === $run['status'] ) {
			$run['status'] = 'paused';
			self::save( $run );
		} elseif ( 'cancel' === $action && self::is_active( $run ) ) {
			$run['status']   = 'cancelled';
			$run['finished'] = time();
			$run['queue']    = array();
			self::save( $run );
			self::unschedule();
		} elseif ( 'clear' === $action && ! self::is_active( $run ) ) {
			delete_option( self::OPTION );
		}

		return self::state();
	}

	/**
	 * The run for the progress screen (without the queue).
	 *
	 * @return array
	 */
	public static function state() {
		$run = self::get();

		if ( ! $run ) {
			return array( 'status' => 'idle' );
		}

		$state              = $run;
		$state['remaining'] = count( $run['queue'] );
		$state['processed'] = $run['done'] + $run['skipped'] + $run['failed'];
		$state['waiting']   = 'running' === $run['status'] && $run['wait_until'] > time();
		$state['busy']      = (bool) get_option( self::LOCK );
		unset( $state['queue'] );

		return $state;
	}

	/**
	 * Run the next batch of posts.
	 *
	 * @return void
	 */
	public static function run_batch() {
		$run = self::get();

		if ( ! $run || 'running' !== $run['status'] ) {
			return;
		}

		if ( $run['wait_until'] > time() ) {
			self::schedule( $run['wait_until'] - time() );

			return;
		}

		if ( ! self::lock() ) {
			return;
		}

		ignore_user_abort( true );
		$start  = time();
		$run_id = $run['id'];

		while ( time() - $start < self::TIME_LIMIT ) {
			$run = self::get();

			if ( ! $run || 'running' !== $run['status'] || ! $run['queue'] ) {
				break;
			}

			$post_id = (int) array_shift( $run['queue'] );
			self::save( $run );

			$result = self::process( $post_id, $run['args'] );
			$run    = self::get();

			if ( ! $run || $run['id'] !== $run_id ) {
				break;
			}

			if ( is_wp_error( $result ) && 'aimg_rate_limited' === $result->get_error_code() && 'running' === $run['status'] ) {
				array_unshift( $run['queue'], $post_id );
				$run['wait_until'] = time() + self::RATE_LIMIT_WAIT;
				$run['message']    = $result->get_error_message();
				self::save( $run );
				break;
			}

			$run['wait_until'] = 0;
			$run['message']    = '';

			if ( is_wp_error( $result ) ) {
				++$run['failed'];
				$run['errors'] = self::push( $run['errors'], array_merge( self::item( $post_id ), array( 'error' => $result->get_error_message() ) ) );
			} elseif ( 'skipped' === $result ) {
				++$run['skipped'];
			} else {
				++$run['done'];
				$run['recent'] = self::push( $run['recent'], array_merge( self::item( $post_id ), array( 'image' => (string) wp_get_attachment_image_url( $result, 'medium' ) ) ) );
			}

			self::save( $run );
		}

		self::unlock();

		$run = self::get();

		if ( ! $run || 'running' !== $run['status'] ) {
			return;
		}

		if ( ! $run['queue'] ) {
			$run['status']   = 'done';
			$run['finished'] = time();
			self::save( $run );

			/**
			 * Fires when a bulk run has gone through all its posts.
			 *
			 * @param array $run The run.
			 *
			 * @since 1.9.1
			 */
			do_action( 'aimg_bulk_finished', $run );

			return;
		}

		self::schedule( max( 0, $run['wait_until'] - time() ) );
	}

	/**
	 * Generate and set the featured image of one post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Run arguments.
	 *
	 * @return int|string|\WP_Error Attachment ID, 'skipped', or an error.
	 */
	public static function process( $post_id, $args ) {
		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_status, $args['statuses'], true ) || '' === aimg_get_plain_title( $post_id ) ) {
			return 'skipped';
		}

		if ( ! $args['replace'] && has_post_thumbnail( $post_id ) ) {
			return 'skipped';
		}

		// Act as the post's author, so the image is uploaded by them and counts against their AI limit.
		$user_id = get_current_user_id();
		wp_set_current_user( (int) $post->post_author );

		$attachment_id = Generator::generate_for_post( $post_id, $args['method'] );

		wp_set_current_user( $user_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );

		return (int) $attachment_id;
	}

	/**
	 * AJAX handler for the loopback request sent by `kick()`.
	 *
	 * @return void
	 */
	public static function handle_kick() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Authenticated by the single-use token; the request comes from the server itself, without cookies.
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$expected = get_transient( self::KICK_ACTION );

		if ( ! is_string( $expected ) || '' === $token || ! hash_equals( $expected, $token ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}

		delete_transient( self::KICK_ACTION );
		self::run_batch();

		wp_die( '', '', array( 'response' => 200 ) );
	}

	/**
	 * Start the next batch right away with a non-blocking request to this site, when the run
	 * is waiting for one. The scheduled action stays as a backup.
	 *
	 * @return void
	 */
	public static function kick() {
		$run = self::get();

		if ( ! $run || 'running' !== $run['status'] || $run['wait_until'] > time() || get_option( self::LOCK ) || get_transient( self::KICK_ACTION ) ) {
			return;
		}

		/** This filter is documented in includes/Queue.php */
		if ( ! apply_filters( 'aimg_kick_queue', true, 0 ) ) {
			return;
		}

		$token = wp_generate_password( 32, false );
		set_transient( self::KICK_ACTION, $token, MINUTE_IN_SECONDS );

		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action' => self::KICK_ACTION,
					'token'  => $token,
				),
			)
		);
	}

	/**
	 * The stored run, or null.
	 *
	 * @return array|null
	 */
	public static function get() {
		$run = get_option( self::OPTION );

		return is_array( $run ) && isset( $run['id'], $run['queue'] ) ? $run : null;
	}

	/**
	 * Whether a run still has work to do.
	 *
	 * @param array|null $run Run.
	 *
	 * @return bool
	 */
	public static function is_active( $run ) {
		return $run && in_array( $run['status'], array( 'running', 'paused' ), true );
	}

	/**
	 * Whether a method calls the AI provider for every post.
	 *
	 * @param string $method Method.
	 *
	 * @return bool
	 */
	private static function uses_ai( $method ) {
		return Generator::method_starts_with_ai( $method ) || ( Generator::METHOD_TEMPLATE_AI === $method && ! Generator::get_random_template_id() );
	}

	/**
	 * Error for a method that needs a missing API key.
	 *
	 * @return \WP_Error
	 */
	private static function method_error() {
		return new \WP_Error( 'aimg_bulk_method', __( 'This method needs an API key. Add one in Settings, or choose another method.', 'artificial-image-generator' ), array( 'status' => 400 ) );
	}

	/**
	 * A post as shown on the progress screen.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private static function item( $post_id ) {
		return array(
			'id'    => (int) $post_id,
			'title' => aimg_get_plain_title( $post_id ),
			'type'  => (string) get_post_type( $post_id ),
			'date'  => (string) get_the_date( '', $post_id ),
			'link'  => (string) get_edit_post_link( $post_id, 'raw' ),
			'thumb' => has_post_thumbnail( $post_id ),
		);
	}

	/**
	 * Add an item to the front of a list, keeping the newest ones.
	 *
	 * @param array $items List.
	 * @param array $item  Item.
	 *
	 * @return array
	 */
	private static function push( $items, $item ) {
		array_unshift( $items, $item );

		return array_slice( $items, 0, self::KEEP_ITEMS );
	}

	/**
	 * Store the run.
	 *
	 * @param array $run Run.
	 *
	 * @return void
	 */
	private static function save( $run ) {
		update_option( self::OPTION, $run, false );
	}

	/**
	 * Take the batch lock.
	 *
	 * @return bool Whether this process got it.
	 */
	private static function lock() {
		if ( add_option( self::LOCK, time(), '', false ) ) {
			return true;
		}

		if ( time() - (int) get_option( self::LOCK ) > self::LOCK_TIMEOUT ) {
			delete_option( self::LOCK );

			return add_option( self::LOCK, time(), '', false );
		}

		return false;
	}

	/**
	 * Release the batch lock.
	 *
	 * @return void
	 */
	private static function unlock() {
		delete_option( self::LOCK );
	}

	/**
	 * Schedule the next batch, unless one is scheduled.
	 *
	 * @param int $delay Seconds from now.
	 *
	 * @return void
	 */
	private static function schedule( $delay = 0 ) {
		if ( function_exists( 'as_schedule_single_action' ) && apply_filters( 'aimg_use_action_scheduler', true ) ) {
			// Replaces a pending batch; the one running now is not pending, so it does not count.
			as_unschedule_all_actions( self::HOOK );
			as_schedule_single_action( time() + $delay, self::HOOK, array(), 'aimg' );

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}

	/**
	 * Remove the scheduled batch.
	 *
	 * @return void
	 */
	private static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK );
		}

		wp_clear_scheduled_hook( self::HOOK );
	}
}
