<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Paint;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A rectangle (optionally rounded), ellipse or line, filled with a color or gradient.
 *
 * A line runs across the middle of its box; rotate the layer for other angles.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Shape implements LayerInterface {

	/**
	 * Clean the layer.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas ) {
		$fill   = isset( $layer['fill'] ) && is_array( $layer['fill'] ) ? $layer['fill'] : array();
		$border = isset( $layer['border'] ) && is_array( $layer['border'] ) ? $layer['border'] : array();
		$kind   = Schema::choice( isset( $fill['kind'] ) ? $fill['kind'] : '', array( 'solid', 'linear', 'radial', 'none' ) );
		$color  = Schema::color( isset( $fill['color'] ) ? $fill['color'] : '' );
		$bcolor = Schema::color( isset( $border['color'] ) ? $border['color'] : '' );

		if ( in_array( $kind, array( 'linear', 'radial' ), true ) ) {
			$fill = Schema::gradient( $fill );
		} else {
			$fill = array(
				'kind'    => $kind,
				'color'   => $color ? $color : '#ffffff',
				'opacity' => Schema::number( isset( $fill['opacity'] ) ? $fill['opacity'] : 1, 0, 1 ),
			);
		}

		return array(
			'shape'     => Schema::choice( isset( $layer['shape'] ) ? $layer['shape'] : '', array( 'rect', 'ellipse', 'line' ) ),
			'box'       => Schema::box( isset( $layer['box'] ) ? $layer['box'] : array(), $canvas ),
			'fill'      => $fill,
			'radius'    => Schema::number( isset( $layer['radius'] ) ? $layer['radius'] : 0, 0, 2000 ),
			'thickness' => Schema::number( isset( $layer['thickness'] ) ? $layer['thickness'] : 4, 1, 200 ),
			'border'    => array(
				'width'   => Schema::number( isset( $border['width'] ) ? $border['width'] : 0, 0, 200 ),
				'color'   => $bcolor ? $bcolor : '#000000',
				'opacity' => Schema::number( isset( $border['opacity'] ) ? $border['opacity'] : 1, 0, 1 ),
			),
		);
	}

	/**
	 * Draw the layer.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param array  $layer  Layer.
	 *
	 * @return void
	 */
	public static function draw( Canvas $canvas, $layer ) {
		$box  = $layer['box'];
		$fill = 'none' === $layer['fill']['kind'] ? null : $layer['fill'];
		$x    = $box['x'];
		$y    = $box['y'];
		$w    = $box['w'];
		$h    = $box['h'];

		if ( 'line' === $layer['shape'] ) {
			$h      = (int) max( 1, round( $layer['thickness'] ) );
			$y      = $box['y'] + ( $box['h'] - $h ) / 2;
			$radius = $layer['radius'];
			$border = null;
		} else {
			$radius = $layer['radius'];
			$border = $layer['border']['width'] > 0 ? $layer['border'] : null;
		}

		if ( ! $fill && ! $border ) {
			return;
		}

		$image = Paint::shape(
			$w,
			$h,
			array(
				'kind'   => 'ellipse' === $layer['shape'] ? 'ellipse' : 'rect',
				'radius' => $radius,
				'fill'   => $fill,
				'border' => $border,
			)
		);

		Paint::composite( $canvas->image, $image, $x, $y, $layer['rotation'] );
		imagedestroy( $image );
	}
}
