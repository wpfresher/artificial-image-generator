<?php

namespace ArtificialImageGenerator\Admin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Handles the plugin admin notices, including promotional notices, offers,
 * important announcements and other admin notifications.
 *
 * Notices are shown on every admin screen (no screen limitation) to users who
 * can manage options, and never on the frontend. Promotional notices are hidden
 * while Image Generator Pro is active.
 *
 * Notice array structure:
 * - id (required)          : Unique identifier for the notice.
 * - type (optional)        : Notice type (promotional, info, warning, error).
 * - title (optional)       : Notice title/headline.
 * - description (optional) : Notice description/message.
 * - button_text (optional) : Primary button text.
 * - button_url (optional)  : Primary button URL.
 * - start_date (optional)  : Start date in Y-m-d format (site timezone).
 * - end_date (optional)    : End date in Y-m-d format (site timezone).
 * - remind_days (optional) : Days to remind later (default: 2).
 *
 * @since 1.8.2
 * @package ArtificialImageGenerator\Admin
 */
class Notices {

	/**
	 * Notice option name prefix.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'aimg_notice_';

	/**
	 * Constructor.
	 *
	 * @since 1.8.2
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'admin_init', array( $this, 'maybe_cleanup_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_aimg_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'wp_ajax_aimg_remind_later_notice', array( $this, 'ajax_remind_later_notice' ) );
	}

	/**
	 * Get all available notices.
	 *
	 * Add promotional notices and offers here, for example:
	 *
	 *     array(
	 *         'id'          => 'black_friday_sale_2026',
	 *         'type'        => 'promotional',
	 *         'title'       => __( 'Black Friday Sale', 'artificial-image-generator' ),
	 *         'description' => __( 'Get Image Generator Pro at a discount.', 'artificial-image-generator' ),
	 *         'button_text' => __( 'Claim the offer', 'artificial-image-generator' ),
	 *         'button_url'  => 'https://beautifulplugins.com/plugins/image-generator-pro/',
	 *         'start_date'  => '2026-11-20',
	 *         'end_date'    => '2026-12-03',
	 *         'remind_days' => 2,
	 *     ),
	 *
	 * @since 1.8.2
	 * @return array Array of notices.
	 */
	public function get_notices() {
		$notices = array(
			array(
				'id'          => 'halloween_sale_2026',
				'type'        => 'promotional',
				'title'       => __( '🎃 Halloween Sale - Flat 60% OFF Image Generator Pro!', 'artificial-image-generator' ),
				'description' => __( 'Get 60% off <strong>Image Generator Pro</strong> with code "<strong>SPOOKY60</strong>". Unlock premium template designs, rating, progress bar and badge layers, WooCommerce price, discount and rating tags, and more. Hurry, the offer ends on Halloween!', 'artificial-image-generator' ),
				'button_text' => __( 'Claim Flat 60% Off Now', 'artificial-image-generator' ),
				'button_url'  => 'https://beautifulplugins.com/plugins/image-generator-pro/?utm_source=plugin&utm_medium=notice&utm_campaign=halloween-sale&discount=SPOOKY60',
				'start_date'  => '2026-10-17',
				'end_date'    => '2026-10-31',
				'remind_days' => 2,
			),
		);

		/**
		 * Filters the available admin notices.
		 *
		 * @since 1.8.2
		 * @param array $notices Available notices.
		 */
		$notices = apply_filters( 'aimg_admin_notices', $notices );

		// Drop malformed notices so the rest of the class can rely on an ID.
		return array_values(
			array_filter(
				(array) $notices,
				function ( $notice ) {
					return is_array( $notice ) && ! empty( $notice['id'] );
				}
			)
		);
	}

	/**
	 * Get a single notice by its ID.
	 *
	 * @param string $notice_id Notice ID.
	 *
	 * @since 1.8.2
	 * @return array|null Notice data, or null when the notice does not exist.
	 */
	public function get_notice( $notice_id ) {
		foreach ( $this->get_notices() as $notice ) {
			if ( (string) $notice['id'] === $notice_id ) {
				return $notice;
			}
		}

		return null;
	}

	/**
	 * Check if a notice should be displayed.
	 *
	 * @param array $notice Notice data.
	 *
	 * @since 1.8.2
	 * @return bool
	 */
	public function should_display_notice( $notice ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( 'promotional' === ( isset( $notice['type'] ) ? $notice['type'] : '' ) && defined( 'AIMG_PRO_VERSION' ) ) {
			return false;
		}

		// Dismissed permanently.
		if ( get_option( self::OPTION_PREFIX . $notice['id'] . '_dismissed', false ) ) {
			return false;
		}

		// Snoozed with "Remind me later".
		$reminded_until = (int) get_option( self::OPTION_PREFIX . $notice['id'] . '_reminded_until', 0 );
		if ( $reminded_until && time() < $reminded_until ) {
			return false;
		}

		// Outside the date range.
		if ( ! empty( $notice['start_date'] ) && time() < $this->date_to_timestamp( $notice['start_date'] . ' 00:00:00' ) ) {
			return false;
		}

		if ( ! empty( $notice['end_date'] ) && time() > $this->date_to_timestamp( $notice['end_date'] . ' 23:59:59' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the notices that should be displayed to the current user.
	 *
	 * @since 1.8.2
	 * @return array Array of notices.
	 */
	public function get_displayable_notices() {
		return array_filter( $this->get_notices(), array( $this, 'should_display_notice' ) );
	}

	/**
	 * Display the admin notices.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function display_notices() {
		foreach ( $this->get_displayable_notices() as $notice ) {
			$this->render_notice( $notice );
		}
	}

	/**
	 * Render a notice.
	 *
	 * @param array $notice Notice data.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function render_notice( $notice ) {
		$notice_id   = (string) $notice['id'];
		$notice_type = ! empty( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'info';
		$title       = isset( $notice['title'] ) ? $notice['title'] : '';
		$description = isset( $notice['description'] ) ? $notice['description'] : '';
		$button_text = ! empty( $notice['button_text'] ) ? $notice['button_text'] : __( 'Learn More', 'artificial-image-generator' );
		$button_url  = isset( $notice['button_url'] ) ? $notice['button_url'] : '';
		?>
		<div class="notice notice-info is-dismissible aimg-admin-notice aimg-notice-<?php echo esc_attr( $notice_type ); ?>" data-notice-id="<?php echo esc_attr( $notice_id ); ?>">
			<div class="aimg-notice-content">
				<?php if ( ! empty( $title ) ) : ?>
					<h3 class="aimg-notice-title"><?php echo wp_kses_post( $title ); ?></h3>
				<?php endif; ?>

				<?php if ( ! empty( $description ) ) : ?>
					<p class="aimg-notice-description"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>

				<div class="aimg-notice-actions">
					<?php if ( ! empty( $button_url ) ) : ?>
						<a href="<?php echo esc_url( $button_url ); ?>" class="button button-primary aimg-notice-button" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $button_text ); ?>
						</a>
					<?php endif; ?>

					<button type="button" class="button aimg-notice-remind-later" data-notice-id="<?php echo esc_attr( $notice_id ); ?>">
						<?php esc_html_e( 'Remind Me Later', 'artificial-image-generator' ); ?>
					</button>

					<button type="button" class="button aimg-notice-dismiss" data-notice-id="<?php echo esc_attr( $notice_id ); ?>">
						<?php esc_html_e( 'Dismiss (Never Show Again)', 'artificial-image-generator' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Remove the stored state of notices that no longer exist.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function maybe_cleanup_notices() {
		if ( false !== get_transient( 'aimg_notices_last_cleanup' ) ) {
			return;
		}

		$active_notice_ids = array_map( 'strval', wp_list_pluck( $this->get_notices(), 'id' ) );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			)
		);

		foreach ( (array) $options as $option_name ) {
			$notice_id = substr( $option_name, strlen( self::OPTION_PREFIX ) );
			$notice_id = preg_replace( '/_(dismissed|reminded_until)$/', '', $notice_id );

			if ( ! in_array( $notice_id, $active_notice_ids, true ) ) {
				delete_option( $option_name );
			}
		}

		/**
		 * Filters how often the stale notice state is cleaned up.
		 *
		 * @since 1.8.2
		 * @param int $interval Interval in seconds.
		 */
		$interval = (int) apply_filters( 'aimg_notices_cleanup_interval', 30 * DAY_IN_SECONDS );
		set_transient( 'aimg_notices_last_cleanup', time(), $interval );
	}

	/**
	 * Enqueue the notice assets on every admin screen that shows a notice.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( empty( $this->get_displayable_notices() ) ) {
			return;
		}

		wp_enqueue_script( 'aimg-notices', AIMG_ASSETS_URL . 'js/notices.js', array(), AIMG_VERSION, true );

		wp_localize_script(
			'aimg-notices',
			'aimgNotices',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aimg_notices_nonce' ),
				'error'   => __( 'Something went wrong. Please try again.', 'artificial-image-generator' ),
			)
		);

		wp_add_inline_style( 'common', $this->get_notice_styles() );
	}

	/**
	 * Get the notice styles.
	 *
	 * @since 1.8.2
	 * @return string
	 */
	public function get_notice_styles() {
		return '
			.aimg-admin-notice {
				border-left-color: #39b54a;
				padding-top: 0.8rem !important;
				padding-bottom: 0.8rem !important;
			}
			.aimg-admin-notice .aimg-notice-title {
				margin: 0 0 10px 0;
			}
			.aimg-admin-notice .aimg-notice-description {
				margin: 0 0 15px 0;
			}
			.aimg-admin-notice .aimg-notice-actions {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
			}
			.aimg-notice-promotional {
				border-left-color: #d63638;
			}
			.aimg-notice-promotional .aimg-notice-title {
				color: #d63638;
			}
			.aimg-notice-warning {
				border-left-color: #dba617;
			}
			.aimg-notice-error {
				border-left-color: #d63638;
			}
		';
	}

	/**
	 * AJAX handler for dismissing a notice permanently.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function ajax_dismiss_notice() {
		$notice = $this->get_requested_notice();

		update_option( self::OPTION_PREFIX . $notice['id'] . '_dismissed', 'yes', false );
		delete_option( self::OPTION_PREFIX . $notice['id'] . '_reminded_until' );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed successfully.', 'artificial-image-generator' ) ) );
	}

	/**
	 * AJAX handler for "Remind me later".
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function ajax_remind_later_notice() {
		$notice = $this->get_requested_notice();

		$remind_days  = isset( $notice['remind_days'] ) ? absint( $notice['remind_days'] ) : 2;
		$remind_until = time() + ( max( 1, $remind_days ) * DAY_IN_SECONDS );

		update_option( self::OPTION_PREFIX . $notice['id'] . '_reminded_until', $remind_until, false );

		wp_send_json_success( array( 'message' => __( 'You will be reminded later.', 'artificial-image-generator' ) ) );
	}

	/**
	 * Verify an AJAX notice request and return the notice it targets.
	 *
	 * Only registered notices are accepted, so a request can never write an
	 * arbitrary option name.
	 *
	 * @since 1.8.2
	 * @return array Notice data. Sends a JSON error and exits on failure.
	 */
	protected function get_requested_notice() {
		check_ajax_referer( 'aimg_notices_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'artificial-image-generator' ) ), 403 );
		}

		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_id'] ) ) : '';
		$notice    = '' !== $notice_id ? $this->get_notice( $notice_id ) : null;

		if ( ! $notice ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notice ID.', 'artificial-image-generator' ) ), 400 );
		}

		return $notice;
	}

	/**
	 * Convert a Y-m-d H:i:s date in the site timezone to a timestamp.
	 *
	 * @param string $date Date string.
	 *
	 * @since 1.8.2
	 * @return int Timestamp, or 0 when the date is invalid.
	 */
	protected function date_to_timestamp( $date ) {
		$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $date, wp_timezone() );

		return $datetime ? $datetime->getTimestamp() : 0;
	}
}
