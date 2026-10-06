<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Background: a solid color, or a palette with one color picked at random per render.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Background implements LayerInterface {

	/**
	 * Color used when the picked one is invalid, as in 1.x.
	 *
	 * @var string
	 */
	const FALLBACK = '#008000';

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
		$colors = isset( $fill['colors'] ) && is_array( $fill['colors'] ) ? $fill['colors'] : array();
		$colors = array_values( array_filter( array_map( array( Schema::class, 'color' ), array_slice( $colors, 0, 20 ) ) ) );

		if ( isset( $fill['kind'] ) && 'palette' === $fill['kind'] && $colors ) {
			return array(
				'fill' => array(
					'kind'   => 'palette',
					'colors' => $colors,
				),
			);
		}

		$color = isset( $fill['color'] ) ? Schema::color( $fill['color'] ) : '';

		return array(
			'fill' => array(
				'kind'  => 'solid',
				'color' => $color ? $color : self::FALLBACK,
			),
		);
	}

	/**
	 * Fill the canvas and remember the color for layers that match it.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param array  $layer  Layer.
	 *
	 * @return void
	 */
	public static function draw( Canvas $canvas, $layer ) {
		$fill = $layer['fill'];
		$hex  = 'palette' === $fill['kind'] ? $fill['colors'][ array_rand( $fill['colors'] ) ] : $fill['color'];
		$rgb  = Canvas::rgb( $hex );

		if ( ! $rgb ) {
			$rgb = Canvas::rgb( self::FALLBACK );
		}

		$canvas->background = $rgb;
		imagefill( $canvas->image, 0, 0, imagecolorallocate( $canvas->image, $rgb[0], $rgb[1], $rgb[2] ) );
	}
}
