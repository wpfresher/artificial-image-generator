<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Paint;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A border around the canvas, optionally inset and rounded.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Frame implements LayerInterface {

	/**
	 * Clean the layer.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas ) {
		$color = Schema::color( isset( $layer['color'] ) ? $layer['color'] : '' );

		return array(
			'width'   => Schema::number( isset( $layer['width'] ) ? $layer['width'] : 16, 1, 400 ),
			'color'   => $color ? $color : '#ffffff',
			'opacity' => Schema::number( isset( $layer['opacity'] ) ? $layer['opacity'] : 1, 0, 1 ),
			'inset'   => Schema::number( isset( $layer['inset'] ) ? $layer['inset'] : 0, 0, 400 ),
			'radius'  => Schema::number( isset( $layer['radius'] ) ? $layer['radius'] : 0, 0, 2000 ),
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
		$inset = (int) round( $layer['inset'] );
		$w     = $canvas->width - 2 * $inset;
		$h     = $canvas->height - 2 * $inset;

		if ( $w < 1 || $h < 1 ) {
			return;
		}

		$ring = Paint::shape(
			$w,
			$h,
			array(
				'kind'   => 'rect',
				'radius' => $layer['radius'],
				'fill'   => null,
				'border' => array(
					'width'   => $layer['width'],
					'color'   => $layer['color'],
					'opacity' => $layer['opacity'],
				),
			)
		);

		imagecopy( $canvas->image, $ring, $inset, $inset, 0, 0, $w, $h );
		imagedestroy( $ring );
	}
}
