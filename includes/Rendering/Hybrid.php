<?php

namespace ArtificialImageGenerator\Rendering;

use ArtificialImageGenerator\Generator;
use ArtificialImageGenerator\PromptBuilder;
use ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Remote image sources for template layers: a stock photo or an AI image per post.
 *
 * Remote calls happen only while an image is generated (`fetching()`); previews
 * use the image a post already got, or a sample photo, so designing costs nothing.
 * Each post's image is saved and reused when the post's image is generated again.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Hybrid {

	/**
	 * Post meta: images fetched for the post, as cache key => attachment ID.
	 *
	 * @var string
	 */
	const META = '_aimg_hybrid_images';

	/**
	 * Whether remote calls are allowed now.
	 *
	 * @var bool
	 */
	private static $fetching = false;

	/**
	 * Remote sources, as source => label.
	 *
	 * @return array
	 */
	public static function sources() {
		return array(
			'stock' => __( 'Stock photo for the post (Unsplash, Pexels or Pixabay)', 'artificial-image-generator' ),
			'ai'    => __( 'AI image for the post', 'artificial-image-generator' ),
		);
	}

	/**
	 * Whether a source is remote.
	 *
	 * @param string $source Source.
	 *
	 * @return bool
	 */
	public static function is_remote( $source ) {
		return isset( self::sources()[ $source ] );
	}

	/**
	 * Run a callback with remote calls allowed.
	 *
	 * @param callable $callback Callback.
	 *
	 * @return mixed The callback's return value.
	 */
	public static function fetching( $callback ) {
		$previous       = self::$fetching;
		self::$fetching = true;

		try {
			return call_user_func( $callback );
		} finally {
			self::$fetching = $previous;
		}
	}

	/**
	 * Whether a document has layers with a remote source.
	 *
	 * @param array $document Document.
	 *
	 * @return bool
	 */
	public static function document_is_remote( $document ) {
		foreach ( isset( $document['layers'] ) ? (array) $document['layers'] : array() as $layer ) {
			if ( empty( $layer['visible'] ) ) {
				continue;
			}

			$source = isset( $layer['source'] ) ? $layer['source'] : ( isset( $layer['fill']['source'] ) && 'image' === $layer['fill']['kind'] ? $layer['fill']['source'] : '' );

			if ( self::is_remote( $source ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Image file for a layer with a remote source.
	 *
	 * @param array       $layer   Layer or background fill, with `source`, `query` and `prompt`.
	 * @param int         $post_id Post the image is for; 0 for previews.
	 * @param Canvas|null $canvas  Canvas, for merge tags and the image shape.
	 *
	 * @return string Path, or '' to draw the layer's fallback.
	 */
	public static function file( $layer, $post_id, $canvas = null ) {
		if ( ! $post_id ) {
			return self::sample();
		}

		$source = $layer['source'];
		$text   = 'stock' === $source ? self::query( $layer, $post_id, $canvas ) : ( isset( $layer['prompt'] ) ? (string) $layer['prompt'] : '' );
		$key    = $source . ':' . md5( $text );
		$cached = get_post_meta( $post_id, self::META, true );
		$cached = is_array( $cached ) ? $cached : array();

		if ( ! empty( $cached[ $key ] ) ) {
			$file = get_attached_file( (int) $cached[ $key ] );
			if ( $file && file_exists( $file ) ) {
				return $file;
			}
		}

		if ( ! self::$fetching ) {
			return self::sample();
		}

		$shape = self::shape( $canvas );
		$id    = 'stock' === $source ? self::stock( $text, $post_id, $shape ) : self::ai( $text, $post_id, $shape );

		if ( is_wp_error( $id ) || ! $id ) {
			return '';
		}

		$cached[ $key ] = (int) $id;
		update_post_meta( $post_id, self::META, $cached );

		$file = get_attached_file( (int) $id );

		return $file && file_exists( $file ) ? $file : '';
	}

	/**
	 * Path of the sample photo shown while designing, made once with GD.
	 *
	 * @return string
	 */
	public static function sample() {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) || ! aimg_can_render() ) {
			return '';
		}

		$file = aimg_uploads_path( trailingslashit( $upload_dir['basedir'] ) . 'aimg-cache/hybrid-sample.jpg' );

		if ( file_exists( $file ) ) {
			return $file;
		}

		wp_mkdir_p( dirname( $file ) );

		$width  = 1600;
		$height = 1000;
		$image  = Paint::gradient(
			$width,
			$height,
			array(
				'kind'  => 'linear',
				'angle' => 180,
				'stops' => array(
					array(
						'color' => '#f6c48a',
						'pos'   => 0,
					),
					array(
						'color' => '#8fb6d9',
						'pos'   => 1,
					),
				),
			)
		);

		if ( ! $image ) {
			return '';
		}

		imagealphablending( $image, true );

		$hills = array(
			array( '#5d7f6b', 0.62, 0.08 ),
			array( '#3f5f4f', 0.74, 0.11 ),
			array( '#2b4338', 0.86, 0.06 ),
		);

		foreach ( $hills as $index => $hill ) {
			$rgb    = Canvas::rgb( $hill[0] );
			$color  = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
			$points = array( 0, $height );

			for ( $x = 0; $x <= $width; $x += 40 ) {
				$points[] = $x;
				$points[] = (int) ( $height * ( $hill[1] - $hill[2] * sin( ( $x / $width ) * M_PI * ( 1.5 + $index ) + $index ) ) );
			}

			$points[] = $width;
			$points[] = $height;

			if ( PHP_VERSION_ID >= 80000 ) {
				imagefilledpolygon( $image, $points, $color );
			} else {
				imagefilledpolygon( $image, $points, count( $points ) / 2, $color ); // phpcs:ignore PHPCompatibility.FunctionUse.OptionalToRequiredFunctionParameters
			}
		}

		$sun = imagecolorallocatealpha( $image, 255, 244, 214, 30 );
		imagefilledellipse( $image, (int) ( $width * 0.72 ), (int) ( $height * 0.3 ), 180, 180, $sun );

		$saved = imagejpeg( $image, $file, 82 );
		imagedestroy( $image );

		return $saved ? $file : '';
	}

	/**
	 * URL of the sample photo.
	 *
	 * @return string
	 */
	public static function sample_url() {
		$file = self::sample();

		return '' !== $file ? aimg_upload_url( $file ) : '';
	}

	/**
	 * Stock search terms for a layer: its merged `query`, or keywords from the post.
	 *
	 * @param array       $layer   Layer.
	 * @param int         $post_id Post ID.
	 * @param Canvas|null $canvas  Canvas.
	 *
	 * @return string
	 */
	private static function query( $layer, $post_id, $canvas ) {
		$query = isset( $layer['query'] ) ? (string) $layer['query'] : '';
		$query = $canvas && '' !== $query ? $canvas->merge( $query ) : $query;
		$query = Stock\Keywords::from_text( $query );

		return '' !== $query ? $query : Stock\Keywords::for_post( $post_id );
	}

	/**
	 * Shape closest to the canvas.
	 *
	 * @param Canvas|null $canvas Canvas.
	 *
	 * @return string landscape, portrait or square.
	 */
	private static function shape( $canvas ) {
		if ( ! $canvas || $canvas->width > $canvas->height * 1.2 ) {
			return 'landscape';
		}

		return $canvas->height > $canvas->width * 1.2 ? 'portrait' : 'square';
	}

	/**
	 * Import a stock photo not used yet on the site.
	 *
	 * @param string $query   Search terms.
	 * @param int    $post_id Post ID.
	 * @param string $shape   Shape.
	 *
	 * @return int|\WP_Error Attachment ID.
	 */
	private static function stock( $query, $post_id, $shape ) {
		$provider = Stock\Registry::get_default();

		if ( ! $provider || '' === $query ) {
			return new \WP_Error( 'aimg_stock_unavailable', __( 'No stock photo library or search terms.', 'artificial-image-generator' ) );
		}

		$result = $provider->search(
			$query,
			array(
				'per_page'    => 10,
				'orientation' => $shape,
			)
		);

		if ( is_wp_error( $result ) || empty( $result['photos'] ) ) {
			return is_wp_error( $result ) ? $result : new \WP_Error( 'aimg_stock_no_results', __( 'No photos found.', 'artificial-image-generator' ) );
		}

		$photo = $result['photos'][0];
		foreach ( $result['photos'] as $candidate ) {
			if ( ! Stock\Importer::find( $provider->get_id(), $candidate->id ) ) {
				$photo = $candidate;
				break;
			}
		}

		return Stock\Importer::import(
			$provider->get_id(),
			$photo->id,
			array(
				'post_id' => $post_id,
				'query'   => $query,
			)
		);
	}

	/**
	 * Generate an AI image, counted against the post author's hourly limit.
	 *
	 * @param string $prompt  Prompt template; '' uses the AI Prompt setting.
	 * @param int    $post_id Post ID.
	 * @param string $shape   Shape.
	 *
	 * @return int|\WP_Error Attachment ID.
	 */
	private static function ai( $prompt, $post_id, $shape ) {
		$provider = \ArtificialImageGenerator\Providers\Registry::get();

		if ( ! $provider || ! $provider->is_configured() ) {
			return new \WP_Error( 'aimg_no_api_key', __( 'No AI service is configured.', 'artificial-image-generator' ) );
		}

		$quota = aimg_consume_ai_quota( (int) get_post_field( 'post_author', $post_id ) );

		if ( is_wp_error( $quota ) ) {
			return $quota;
		}

		$args = array();
		if ( '' !== trim( $prompt ) ) {
			$args['template'] = $prompt;
		}

		$ids = Generator::generate_ai(
			PromptBuilder::build( $post_id, $args ),
			array(
				'size'   => $shape,
				'title'  => aimg_get_plain_title( $post_id ),
				'parent' => $post_id,
				'source' => 'auto',
			)
		);

		return is_wp_error( $ids ) ? $ids : (int) reset( $ids );
	}
}
