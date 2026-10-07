<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Paint;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A repeating pattern over the whole canvas: dots, lines, diagonal lines, a grid or noise.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Pattern implements LayerInterface {

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
			'pattern' => Schema::choice( isset( $layer['pattern'] ) ? $layer['pattern'] : '', array( 'dots', 'lines', 'diagonal', 'grid', 'noise' ) ),
			'color'   => $color ? $color : '#ffffff',
			'opacity' => Schema::number( isset( $layer['opacity'] ) ? $layer['opacity'] : 0.15, 0, 1 ),
			'spacing' => Schema::number( isset( $layer['spacing'] ) ? $layer['spacing'] : 24, 4, 400 ),
			'size'    => Schema::number( isset( $layer['size'] ) ? $layer['size'] : 2, 1, 100 ),
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
		$image   = $canvas->image;
		$w       = $canvas->width;
		$h       = $canvas->height;
		$color   = Paint::color( $image, $layer['color'], $layer['opacity'] );
		$spacing = (int) round( $layer['spacing'] );
		$size    = (int) round( $layer['size'] );

		imagesetthickness( $image, $size );

		switch ( $layer['pattern'] ) {
			case 'dots':
				$start = (int) ( $spacing / 2 );
				for ( $row = 0; $start + $row * $spacing < $h; $row++ ) {
					for ( $col = 0; $start + $col * $spacing < $w; $col++ ) {
						imagefilledellipse( $image, $start + $col * $spacing, $start + $row * $spacing, 2 * $size, 2 * $size, $color );
					}
				}
				break;

			case 'lines':
				for ( $y = (int) ( $spacing / 2 ); $y < $h; $y += $spacing ) {
					imagefilledrectangle( $image, 0, $y, $w - 1, $y + $size - 1, $color );
				}
				break;

			case 'grid':
				for ( $y = 0; $y < $h; $y += $spacing ) {
					imagefilledrectangle( $image, 0, $y, $w - 1, $y + $size - 1, $color );
				}
				for ( $x = 0; $x < $w; $x += $spacing ) {
					imagefilledrectangle( $image, $x, 0, $x + $size - 1, $h - 1, $color );
				}
				break;

			case 'diagonal':
				for ( $offset = -$h; $offset < $w; $offset += $spacing ) {
					imageline( $image, $offset, $h, $offset + $h, 0, $color );
				}
				break;

			case 'noise':
				$count = (int) ( $w * $h * min( 0.5, $layer['size'] / 20 ) );
				for ( $i = 0; $i < $count; $i++ ) {
					imagesetpixel( $image, mt_rand( 0, $w - 1 ), mt_rand( 0, $h - 1 ), $color ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Visual noise, not security.
				}
				break;
		}

		imagesetthickness( $image, 1 );
	}
}
