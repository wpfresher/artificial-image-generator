<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Asks administrators for a WordPress.org review once the plugin has made a few images.
 *
 * @since 1.7.1
 * @package ArtificialImageGenerator/Admin
 */
class ReviewNotice {

	/**
	 * User meta holding 'never' or the timestamp until which the notice is snoozed.
	 */
	const META_KEY = 'aimg_review_notice';

	/**
	 * Generated images needed before the notice shows.
	 */
	const THRESHOLD = 5;

	/**
	 * Days "Maybe later" hides the notice for.
	 */
	const SNOOZE_DAYS = 30;

	/**
	 * Reviews page on WordPress.org.
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/artificial-image-generator/reviews/#new-post';

	/**
	 * ReviewNotice constructor.
	 *
	 * @since 1.7.1
	 */
	public function __construct() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_aimg_review_notice', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Whether the current user should see the notice on the current screen.
	 *
	 * @since 1.7.1
	 * @return bool
	 */
	public static function should_show() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen = $screen ? $screen->id : '';

		// The templates list and Settings only, never above the Studio.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_image-generator' === $screen && ( isset( $_GET['add'] ) || isset( $_GET['edit'] ) ) ) {
			return false;
		}

		if ( ! in_array( $screen, array( 'toplevel_page_image-generator', 'image-generator_page_aimg-settings' ), true ) ) {
			return false;
		}

		$state = get_user_meta( get_current_user_id(), self::META_KEY, true );
		if ( 'never' === $state || ( is_numeric( $state ) && time() < (int) $state ) ) {
			return false;
		}

		$generated = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_aimg_generated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => self::THRESHOLD,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return count( $generated ) >= self::THRESHOLD;
	}

	/**
	 * URL that records the user's answer.
	 *
	 * @param string $answer review, later or never.
	 *
	 * @since 1.7.1
	 * @return string
	 */
	public static function answer_url( $answer ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'aimg_review_notice',
					'answer' => $answer,
				),
				admin_url( 'admin-post.php' )
			),
			'aimg_review_notice'
		);
	}

	/**
	 * Print the notice.
	 *
	 * @since 1.7.1
	 * @return void
	 */
	public static function render() {
		if ( ! self::should_show() ) {
			return;
		}

		?>
		<div class="notice notice-info aimg-review-notice">
			<p>
				<?php esc_html_e( 'You have made several images with Image Generator. If it saves you time, would you leave a short review on WordPress.org? It helps other people find the plugin.', 'artificial-image-generator' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( self::answer_url( 'review' ) ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'artificial-image-generator' ); ?></a>
				<a href="<?php echo esc_url( self::answer_url( 'later' ) ); ?>" class="button"><?php esc_html_e( 'Maybe later', 'artificial-image-generator' ); ?></a>
				<a href="<?php echo esc_url( self::answer_url( 'never' ) ); ?>" class="button-link"><?php esc_html_e( "Don't ask again", 'artificial-image-generator' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Record the user's answer and redirect.
	 *
	 * @since 1.7.1
	 * @return void
	 */
	public static function handle() {
		check_admin_referer( 'aimg_review_notice' );

		$answer  = isset( $_GET['answer'] ) ? sanitize_key( wp_unslash( $_GET['answer'] ) ) : '';
		$user_id = get_current_user_id();

		if ( 'later' === $answer ) {
			update_user_meta( $user_id, self::META_KEY, time() + self::SNOOZE_DAYS * DAY_IN_SECONDS );
		} elseif ( in_array( $answer, array( 'review', 'never' ), true ) ) {
			update_user_meta( $user_id, self::META_KEY, 'never' );
		}

		if ( 'review' === $answer ) {
			wp_redirect( self::REVIEW_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=image-generator' ) );
		exit;
	}
}
