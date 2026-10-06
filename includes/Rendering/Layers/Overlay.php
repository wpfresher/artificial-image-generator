<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A see-through color over the whole canvas, e.g. to keep text readable.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Overlay implements LayerInterface {

	/**
	 * Color value meaning "the background color picked for this render".
	 *
	 * @var string
	 */
	const BACKGROUND = 'background';

	/**
	 * Clean the layer.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas ) {
		$color = isset( $layer['color'] ) ? $layer['color'] : '';

		return array(
			'color'   => self::BACKGROUND === $color ? self::BACKGROUND : ( Schema::color( $color ) ? Schema::color( $color ) : '#000000' ),
			'opacity' => Schema::number( isset( $layer['opacity'] ) ? $layer['opacity'] : 0.5, 0, 1 ),
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
		$rgb = self::BACKGROUND === $layer['color'] ? $canvas->background : Canvas::rgb( $layer['color'] );

		if ( ! $rgb ) {
			return;
		}

		$alpha = (int) round( 127 * ( 1 - $layer['opacity'] ) );
		$color = imagecolorallocatealpha( $canvas->image, $rgb[0], $rgb[1], $rgb[2], $alpha );

		imagefilledrectangle( $canvas->image, 0, 0, $canvas->width, $canvas->height, $color );
	}
}
