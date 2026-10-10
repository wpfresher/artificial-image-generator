<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Images;
use ArtificialImageGenerator\Rendering\Paint;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * An image placed in a box: picked from the Media Library (one at random per
 * render when several are set, the 1.x overlay behaviour) or a dynamic source.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Image implements LayerInterface {

	/**
	 * Anchors, in the order of the 1.x overlay positions.
	 *
	 * @return string[]
	 */
	public static function anchors() {
		return aimg_get_overlay_positions();
	}

	/**
	 * Clean the layer.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas ) {
		$ids = isset( $layer['attachments'] ) && is_array( $layer['attachments'] ) ? $layer['attachments'] : array();
		$ids = array_values(
			array_filter(
				array_unique( array_map( 'absint', array_slice( $ids, 0, 20 ) ) ),
				'wp_attachment_is_image'
			)
		);

		return self::remote_fields( $layer ) + array(
			'source'      => Schema::choice( isset( $layer['source'] ) ? $layer['source'] : '', array_keys( Images::sources() ) ),
			'attachments' => $ids,
			'pick'        => Schema::choice( isset( $layer['pick'] ) ? $layer['pick'] : '', array( 'random', 'first' ) ),
			'box'         => Schema::box( isset( $layer['box'] ) ? $layer['box'] : array(), $canvas ),
			'fit'         => Schema::choice( isset( $layer['fit'] ) ? $layer['fit'] : '', array( 'contain', 'cover', 'fill' ) ),
			'anchor'      => Schema::choice( isset( $layer['anchor'] ) ? $layer['anchor'] : '', self::anchors(), 'center-center' ),
			'focal'       => self::focal( isset( $layer['focal'] ) ? $layer['focal'] : array() ),
			'opacity'     => Schema::number( isset( $layer['opacity'] ) ? $layer['opacity'] : 1, 0, 1 ),
			'mask'        => Schema::choice( isset( $layer['mask'] ) ? $layer['mask'] : '', array( 'none', 'rounded', 'circle' ) ),
			'radius'      => Schema::number( isset( $layer['radius'] ) ? $layer['radius'] : 0, 0, 2000 ),
			'adjust'      => Schema::adjustments( isset( $layer['adjust'] ) ? $layer['adjust'] : array() ),
		);
	}

	/**
	 * Search terms and prompt of a stock or AI source, cleaned; empty for other sources.
	 *
	 * @param array $layer Raw layer or background fill.
	 *
	 * @return array { query, prompt } or array().
	 */
	public static function remote_fields( $layer ) {
		if ( ! \ArtificialImageGenerator\Rendering\Hybrid::is_remote( isset( $layer['source'] ) ? $layer['source'] : '' ) ) {
			return array();
		}

		return array(
			'query'  => isset( $layer['query'] ) ? mb_substr( sanitize_text_field( (string) $layer['query'] ), 0, 200 ) : '{title}',
			'prompt' => isset( $layer['prompt'] ) ? mb_substr( sanitize_textarea_field( (string) $layer['prompt'] ), 0, 1000 ) : '',
		);
	}

	/**
	 * Clean a focal point.
	 *
	 * @param mixed $focal Focal point { x, y }.
	 *
	 * @return array
	 */
	public static function focal( $focal ) {
		$focal = is_array( $focal ) ? $focal : array();

		return array(
			'x' => Schema::number( isset( $focal['x'] ) ? $focal['x'] : 0.5, 0, 1 ),
			'y' => Schema::number( isset( $focal['y'] ) ? $focal['y'] : 0.5, 0, 1 ),
		);
	}

	/**
	 * Draw the layer.
	 *
	 * Layers built internally may carry resolved file paths in `_files` instead
	 * of attachment IDs; the schema never lets them through from outside.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param array  $layer  Layer.
	 *
	 * @return void
	 */
	public static function draw( Canvas $canvas, $layer ) {
		$box   = $layer['box'];
		$files = isset( $layer['_files'] ) ? (array) $layer['_files'] : Images::files( $layer, $canvas->post_id, $canvas );

		foreach ( $files as $file ) {
			$source = Images::load( $file );

			if ( ! $source ) {
				continue;
			}

			$placed = Images::place( $source, $box['w'], $box['h'], $layer['fit'], $layer['anchor'], isset( $layer['focal'] ) ? $layer['focal'] : null );
			$image  = $placed['image'];
			imagedestroy( $source );

			if ( isset( $layer['adjust'] ) ) {
				Paint::adjust( $image, $layer['adjust'] );
			}

			if ( isset( $layer['mask'] ) && 'none' !== $layer['mask'] ) {
				Paint::clip( $image, 'circle' === $layer['mask'] ? 'ellipse' : 'rect', $layer['radius'] );
			}

			Paint::fade( $image, $layer['opacity'] );

			$x = $box['x'] + $placed['x'];
			$y = $box['y'] + $placed['y'];

			if ( ! empty( $layer['rotation'] ) ) {
				Paint::composite( $canvas->image, $image, $x, $y, $layer['rotation'] );
			} else {
				imagecopy( $canvas->image, $image, (int) $x, (int) $y, 0, 0, imagesx( $image ), imagesy( $image ) );
			}

			imagedestroy( $image );
		}
	}
}
