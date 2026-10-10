<?php

namespace ArtificialImageGenerator\Templates;

use ArtificialImageGenerator\Rendering\GdRenderer;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Gives every 1.x template a v2 document, in the background.
 *
 * A template is migrated only when its v2 document draws exactly the bytes its 1.x
 * meta draws, for every background color and overlay it can pick. Others are skipped,
 * keep rendering from their 1.x meta, and are listed in Site Health. The 1.x meta is
 * never changed or deleted.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Migrator {

	/**
	 * Background job hook.
	 *
	 * @var string
	 */
	const HOOK = 'aimg_migrate_templates';

	/**
	 * Option holding the migration state.
	 *
	 * @var string
	 */
	const OPTION = 'aimg_templates_migrated';

	/**
	 * Templates checked per job.
	 *
	 * @var int
	 */
	const BATCH = 5;

	/**
	 * Seconds after which a job stops and leaves the rest to the next one.
	 *
	 * @var int
	 */
	const TIME_LIMIT = 20;

	/**
	 * Migrator constructor.
	 */
	public function __construct() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'site_status_tests' ) );
	}

	/**
	 * The migration state.
	 *
	 * @return array { version, done, migrated, skipped: template ID => error code }
	 */
	public static function state() {
		$state = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'version'  => '',
				'done'     => false,
				'migrated' => 0,
				'skipped'  => array(),
			)
		);
	}

	/**
	 * Schedule the migration once per plugin version until it is done.
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		$state = self::state();

		if ( ( $state['done'] && AIMG_VERSION === $state['version'] ) || self::is_scheduled() ) {
			return;
		}

		if ( AIMG_VERSION !== $state['version'] ) {
			$state['version'] = AIMG_VERSION;
			$state['done']    = false;
			$state['skipped'] = array();
			update_option( self::OPTION, $state );
		}

		self::schedule();
	}

	/**
	 * Migrate the next batch, then schedule the next one or mark the migration done.
	 *
	 * @return void
	 */
	public static function run() {
		$state = self::state();
		$start = microtime( true );

		foreach ( self::pending( array_keys( $state['skipped'] ) ) as $template_id ) {
			$result = self::migrate( $template_id );

			if ( is_wp_error( $result ) ) {
				$state['skipped'][ $template_id ] = $result->get_error_code();
			} else {
				++$state['migrated'];
			}

			if ( microtime( true ) - $start > self::TIME_LIMIT ) {
				break;
			}
		}

		$state['version'] = AIMG_VERSION;
		$state['done']    = ! self::pending( array_keys( $state['skipped'] ), 1 );
		update_option( self::OPTION, $state );

		if ( ! $state['done'] ) {
			self::schedule();
		}
	}

	/**
	 * Give one template a v2 document when it draws the same as its 1.x meta.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return true|\WP_Error
	 */
	public static function migrate( $template_id ) {
		if ( Repository::has_document( $template_id ) ) {
			return true;
		}

		$document = Migration::from_template( $template_id );
		$verified = self::verify( $template_id, $document );

		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		update_post_meta( $template_id, Repository::META, wp_slash( wp_json_encode( $document ) ) );

		return true;
	}

	/**
	 * Whether a document draws the same bytes as the template's 1.x meta.
	 *
	 * Each background color and overlay is drawn on its own, so random picks don't
	 * have to happen in the same order.
	 *
	 * @param int   $template_id Template ID.
	 * @param array $document    Document built from the 1.x meta.
	 *
	 * @return true|\WP_Error
	 */
	public static function verify( $template_id, $document ) {
		if ( ! aimg_can_render() ) {
			return new \WP_Error( 'aimg_cannot_render', __( 'This server cannot draw images (GD with FreeType is missing).', 'artificial-image-generator' ) );
		}

		if ( has_filter( 'aimg_template_render_args' ) ) {
			return new \WP_Error( 'aimg_render_args_filtered', __( 'A plugin or theme changes how this template is drawn (filter aimg_template_render_args).', 'artificial-image-generator' ) );
		}

		$colors = array_values( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $template_id, '_aimg_bg_colors', true ) ) ) ) );
		$fill   = self::layer( $document, 'background' );

		if ( $colors && ( 'palette' !== $fill['fill']['kind'] || count( $colors ) !== count( $fill['fill']['colors'] ) ) ) {
			return new \WP_Error( 'aimg_invalid_colors', __( 'The template has background colors the Template Studio cannot read.', 'artificial-image-generator' ) );
		}

		$overlays = array();
		if ( 'yes' === get_post_meta( $template_id, '_aimg_is_overlay_image', true ) ) {
			foreach ( (array) json_decode( (string) get_post_meta( $template_id, '_aimg_overlay_images', true ) ) as $id ) {
				$path = get_attached_file( (int) $id );
				if ( $path && file_exists( $path ) ) {
					$overlays[ (int) $id ] = $path;
				}
			}
		}

		$titles = array_unique( array_filter( array( aimg_get_plain_title( $template_id ), __( 'How to grow tomatoes on a small balcony: ten tips for a big summer harvest', 'artificial-image-generator' ) ) ) );
		$base   = array(
			'template_id' => (int) $template_id,
			'width'       => (int) get_post_meta( $template_id, '_aimg_width', true ),
			'height'      => (int) get_post_meta( $template_id, '_aimg_height', true ),
		);

		foreach ( $colors ? array_keys( $colors ) : array( null ) as $index ) {
			foreach ( $overlays ? array_keys( $overlays ) : array( null ) as $overlay ) {
				$v1 = $base + array(
					'colors'   => null === $index ? array() : array( $colors[ $index ] ),
					'overlays' => null === $overlay ? array() : array( $overlays[ $overlay ] ),
				);
				$v2 = self::pick( $document, $index, $overlay );

				foreach ( $titles as $title ) {
					$old = self::png( GdRenderer::render( Migration::from_render_args( $v1 ), array( 'title' => $title ) ) );
					$new = self::png( GdRenderer::render( $v2, array( 'title' => $title ) ) );

					if ( '' === $old || $old !== $new ) {
						return new \WP_Error( 'aimg_render_mismatch', __( 'The template would look different in the Template Studio format.', 'artificial-image-generator' ) );
					}
				}
			}
		}

		return true;
	}

	/**
	 * Site Health tests.
	 *
	 * @param array $tests Tests.
	 *
	 * @return array
	 */
	public static function site_status_tests( $tests ) {
		$tests['direct']['aimg_templates_migrated'] = array(
			'label' => __( 'Image templates use the Template Studio format', 'artificial-image-generator' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Site Health test result.
	 *
	 * @return array
	 */
	public static function site_health_test() {
		$state  = self::state();
		$result = array(
			'label'       => __( 'All image templates use the Template Studio format', 'artificial-image-generator' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Image Generator', 'artificial-image-generator' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Every image template is stored in the format the Template Studio uses.', 'artificial-image-generator' ) . '</p>',
			'actions'     => '',
			'test'        => 'aimg_templates_migrated',
		);

		if ( ! $state['done'] ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Image templates are being updated to the Template Studio format', 'artificial-image-generator' );
			$result['description'] = '<p>' . esc_html__( 'Image Generator is updating older templates in the background. Their images do not change.', 'artificial-image-generator' ) . '</p>';
		} elseif ( $state['skipped'] ) {
			$items = '';
			foreach ( $state['skipped'] as $template_id => $code ) {
				$items .= sprintf(
					'<li><a href="%1$s">%2$s</a>: %3$s</li>',
					esc_url( admin_url( 'admin.php?page=image-generator&edit=' . (int) $template_id ) ),
					esc_html( aimg_get_plain_title( $template_id ) ),
					esc_html( self::reason( $code ) )
				);
			}

			$result['status']      = 'recommended';
			$result['label']       = __( 'Some image templates still use the old format', 'artificial-image-generator' );
			$result['description'] = '<p>' . esc_html__( 'These templates keep working as before. Open each one in the Template Studio, check it and save it to update it.', 'artificial-image-generator' ) . '</p><ul>' . $items . '</ul>';
		}

		return $result;
	}

	/**
	 * Why a template was skipped.
	 *
	 * @param string $code Error code.
	 *
	 * @return string
	 */
	private static function reason( $code ) {
		$reasons = array(
			'aimg_cannot_render'        => __( 'this server cannot draw images', 'artificial-image-generator' ),
			'aimg_render_args_filtered' => __( 'a plugin or theme changes how it is drawn', 'artificial-image-generator' ),
			'aimg_invalid_colors'       => __( 'it has invalid background colors', 'artificial-image-generator' ),
			'aimg_render_mismatch'      => __( 'it would look different', 'artificial-image-generator' ),
		);

		return isset( $reasons[ $code ] ) ? $reasons[ $code ] : $code;
	}

	/**
	 * Templates without a v2 document.
	 *
	 * @param int[] $exclude Template IDs to leave out.
	 * @param int   $limit   Most IDs to return.
	 *
	 * @return int[]
	 */
	private static function pending( $exclude, $limit = self::BATCH ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => 'aimg_template',
					'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page'   => $limit,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'post__not_in'     => array_map( 'intval', $exclude ),
					'suppress_filters' => true,
					'no_found_rows'    => true,
					'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => Repository::META,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);
	}

	/**
	 * The document with one background color and one overlay.
	 *
	 * @param array    $document Document.
	 * @param int|null $color    Palette index, or null to keep the fill.
	 * @param int|null $overlay  Overlay attachment ID, or null to keep the image layer.
	 *
	 * @return array
	 */
	private static function pick( $document, $color, $overlay ) {
		foreach ( $document['layers'] as $i => $layer ) {
			if ( null !== $color && 'background' === $layer['type'] ) {
				$document['layers'][ $i ]['fill']['colors'] = array( $layer['fill']['colors'][ $color ] );
			} elseif ( null !== $overlay && 'image' === $layer['type'] ) {
				$document['layers'][ $i ]['attachments'] = array( $overlay );
			}
		}

		return $document;
	}

	/**
	 * First layer of a type.
	 *
	 * @param array  $document Document.
	 * @param string $type     Layer type.
	 *
	 * @return array
	 */
	private static function layer( $document, $type ) {
		foreach ( $document['layers'] as $layer ) {
			if ( $type === $layer['type'] ) {
				return $layer;
			}
		}

		return array();
	}

	/**
	 * PNG bytes of an image.
	 *
	 * @param \GdImage|resource|false $image Image; destroyed afterwards.
	 *
	 * @return string '' when there is no image.
	 */
	private static function png( $image ) {
		if ( ! $image ) {
			return '';
		}

		ob_start();
		imagepng( $image );
		imagedestroy( $image );

		return (string) ob_get_clean();
	}

	/**
	 * Whether a migration job is pending.
	 *
	 * @return bool
	 */
	private static function is_scheduled() {
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK ) ) {
			return true;
		}

		return (bool) wp_next_scheduled( self::HOOK );
	}

	/**
	 * Schedule the next batch.
	 *
	 * @return void
	 */
	private static function schedule() {
		if ( function_exists( 'as_enqueue_async_action' ) && apply_filters( 'aimg_use_action_scheduler', true ) ) {
			as_enqueue_async_action( self::HOOK, array(), 'aimg' );
		} else {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}
}
