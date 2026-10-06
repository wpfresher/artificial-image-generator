<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Fonts;
use ArtificialImageGenerator\Rendering\TextLayout;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Text with merge tags, wrapped and fitted into a box.
 *
 * `fit: box` shrinks the text until it fits the box (and `maxLines`); `fit: width`
 * only shrinks when a single word is too wide, as 1.x titles did.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Text implements LayerInterface {

	/**
	 * Clean the layer.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas ) {
		$size = isset( $layer['size'] ) && is_array( $layer['size'] ) ? $layer['size'] : array();
		$max  = Schema::number( isset( $size['max'] ) ? $size['max'] : AIMG_DEFAULT_FONT_SIZE, 1, 500 );
		$min  = Schema::number( isset( $size['min'] ) ? $size['min'] : TextLayout::MIN_WRAP_SIZE, 1, $max );
		$font = isset( $layer['font'] ) ? sanitize_key( $layer['font'] ) : '';

		return array(
			'content'    => isset( $layer['content'] ) ? sanitize_textarea_field( (string) $layer['content'] ) : '{title}',
			'font'       => isset( Fonts::all()[ $font ] ) ? $font : Fonts::DEFAULT_FONT,
			'size'       => array(
				'max' => $max,
				'min' => $min,
			),
			'fit'        => Schema::choice( isset( $layer['fit'] ) ? $layer['fit'] : '', array( 'box', 'width' ) ),
			'box'        => Schema::box( isset( $layer['box'] ) ? $layer['box'] : array(), $canvas ),
			'align'      => Schema::choice( isset( $layer['align'] ) ? $layer['align'] : '', array( 'center', 'left', 'right' ) ),
			'valign'     => Schema::choice( isset( $layer['valign'] ) ? $layer['valign'] : '', array( 'middle', 'top', 'bottom' ) ),
			'color'      => Schema::color( isset( $layer['color'] ) ? $layer['color'] : '' ) ? Schema::color( $layer['color'] ) : '#ffffff',
			'lineHeight' => Schema::number( isset( $layer['lineHeight'] ) ? $layer['lineHeight'] : 1.4, 0.5, 4 ),
			'maxLines'   => (int) Schema::number( isset( $layer['maxLines'] ) ? $layer['maxLines'] : 0, 0, 20 ),
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
		$text = trim( $canvas->merge( $layer['content'] ) );

		if ( '' === $text ) {
			return;
		}

		$font = Fonts::path( $layer['font'] );
		$box  = $layer['box'];

		if ( 'width' === $layer['fit'] ) {
			$layout = TextLayout::wrap( $text, $layer['size']['max'], $font, $box['w'], $layer['size']['min'] );
		} else {
			$layout = TextLayout::fit(
				$text,
				$font,
				array(
					'max_size'    => $layer['size']['max'],
					'min_size'    => $layer['size']['min'],
					'width'       => $box['w'],
					'height'      => $box['h'],
					'line_height' => $layer['lineHeight'],
					'max_lines'   => $layer['maxLines'],
				)
			);
		}

		$size  = $layout['font_size'];
		$lines = $layout['lines'];

		$rgb   = Canvas::rgb( $layer['color'] );
		$rgb   = $rgb ? $rgb : array( 255, 255, 255 );
		$color = imagecolorallocate( $canvas->image, $rgb[0], $rgb[1], $rgb[2] );

		$line_height = $size * $layer['lineHeight'];
		$total       = count( $lines ) * $line_height;

		if ( 'top' === $layer['valign'] ) {
			$y = $box['y'] + $size;
		} elseif ( 'bottom' === $layer['valign'] ) {
			$y = $box['y'] + $box['h'] - $total + $size;
		} else {
			$y = $box['y'] + ( $box['h'] - $total ) / 2 + $size;
		}

		foreach ( $lines as $line ) {
			$width = TextLayout::measure( $line, $size, $font );

			if ( 'left' === $layer['align'] ) {
				$x = $box['x'];
			} elseif ( 'right' === $layer['align'] ) {
				$x = $box['x'] + $box['w'] - $width;
			} else {
				$x = $box['x'] + ( $box['w'] - $width ) / 2;
			}

			imagettftext( $canvas->image, $size, 0, (int) $x, (int) $y, $color, $font, $line );
			$y += $line_height;
		}
	}
}
