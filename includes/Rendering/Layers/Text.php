<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Fonts;
use ArtificialImageGenerator\Rendering\Paint;
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
		$size      = isset( $layer['size'] ) && is_array( $layer['size'] ) ? $layer['size'] : array();
		$max       = Schema::number( isset( $size['max'] ) ? $size['max'] : AIMG_DEFAULT_FONT_SIZE, 1, 500 );
		$min       = Schema::number( isset( $size['min'] ) ? $size['min'] : TextLayout::MIN_WRAP_SIZE, 1, $max );
		$font      = isset( $layer['font'] ) ? sanitize_key( $layer['font'] ) : '';
		$stroke    = isset( $layer['stroke'] ) && is_array( $layer['stroke'] ) ? $layer['stroke'] : array();
		$shadow    = isset( $layer['shadow'] ) && is_array( $layer['shadow'] ) ? $layer['shadow'] : array();
		$highlight = isset( $layer['highlight'] ) && is_array( $layer['highlight'] ) ? $layer['highlight'] : array();
		$color     = function ( $value, $default_value ) {
			$value = Schema::color( $value );

			return $value ? $value : $default_value;
		};

		return array(
			'content'       => isset( $layer['content'] ) ? sanitize_textarea_field( (string) $layer['content'] ) : '{title}',
			'font'          => isset( Fonts::all()[ $font ] ) ? $font : Fonts::DEFAULT_FONT,
			'size'          => array(
				'max' => $max,
				'min' => $min,
			),
			'fit'           => Schema::choice( isset( $layer['fit'] ) ? $layer['fit'] : '', array( 'box', 'width' ) ),
			'box'           => Schema::box( isset( $layer['box'] ) ? $layer['box'] : array(), $canvas ),
			'align'         => Schema::choice( isset( $layer['align'] ) ? $layer['align'] : '', array( 'center', 'left', 'right' ) ),
			'valign'        => Schema::choice( isset( $layer['valign'] ) ? $layer['valign'] : '', array( 'middle', 'top', 'bottom' ) ),
			'color'         => $color( isset( $layer['color'] ) ? $layer['color'] : '', '#ffffff' ),
			'opacity'       => Schema::number( isset( $layer['opacity'] ) ? $layer['opacity'] : 1, 0, 1 ),
			'lineHeight'    => Schema::number( isset( $layer['lineHeight'] ) ? $layer['lineHeight'] : 1.4, 0.5, 4 ),
			'maxLines'      => (int) Schema::number( isset( $layer['maxLines'] ) ? $layer['maxLines'] : 0, 0, 20 ),
			'transform'     => Schema::choice( isset( $layer['transform'] ) ? $layer['transform'] : '', array( 'none', 'upper', 'lower', 'title' ) ),
			'letterSpacing' => Schema::number( isset( $layer['letterSpacing'] ) ? $layer['letterSpacing'] : 0, -10, 50 ),
			'stroke'        => array(
				'width'   => Schema::number( isset( $stroke['width'] ) ? $stroke['width'] : 0, 0, 20 ),
				'color'   => $color( isset( $stroke['color'] ) ? $stroke['color'] : '', '#000000' ),
				'opacity' => Schema::number( isset( $stroke['opacity'] ) ? $stroke['opacity'] : 1, 0, 1 ),
			),
			'shadow'        => array(
				'x'       => Schema::number( isset( $shadow['x'] ) ? $shadow['x'] : 0, -50, 50 ),
				'y'       => Schema::number( isset( $shadow['y'] ) ? $shadow['y'] : 0, -50, 50 ),
				'blur'    => (int) Schema::number( isset( $shadow['blur'] ) ? $shadow['blur'] : 0, 0, 10 ),
				'color'   => $color( isset( $shadow['color'] ) ? $shadow['color'] : '', '#000000' ),
				'opacity' => Schema::number( isset( $shadow['opacity'] ) ? $shadow['opacity'] : 0, 0, 1 ),
			),
			'highlight'     => array(
				'mode'    => Schema::choice( isset( $highlight['mode'] ) ? $highlight['mode'] : '', array( 'none', 'block', 'lines' ) ),
				'color'   => $color( isset( $highlight['color'] ) ? $highlight['color'] : '', '#000000' ),
				'opacity' => Schema::number( isset( $highlight['opacity'] ) ? $highlight['opacity'] : 1, 0, 1 ),
				'padding' => Schema::number( isset( $highlight['padding'] ) ? $highlight['padding'] : 12, 0, 200 ),
				'radius'  => Schema::number( isset( $highlight['radius'] ) ? $highlight['radius'] : 0, 0, 200 ),
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
		$text = self::transform( trim( $canvas->merge( $layer['content'] ) ), isset( $layer['transform'] ) ? $layer['transform'] : 'none' );

		if ( '' === $text ) {
			return;
		}

		$font    = Fonts::path( $layer['font'] );
		$box     = $layer['box'];
		$spacing = isset( $layer['letterSpacing'] ) ? (float) $layer['letterSpacing'] : 0.0;

		if ( 'width' === $layer['fit'] ) {
			$layout = TextLayout::wrap( $text, $layer['size']['max'], $font, $box['w'], $layer['size']['min'], $spacing );
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
					'spacing'     => $spacing,
				)
			);
		}

		$lines = self::position( $layout, $layer, $font, $spacing );

		if ( self::is_plain( $layer ) ) {
			$rgb   = Canvas::rgb( $layer['color'] );
			$rgb   = $rgb ? $rgb : array( 255, 255, 255 );
			$color = imagecolorallocate( $canvas->image, $rgb[0], $rgb[1], $rgb[2] );

			foreach ( $lines as $line ) {
				imagettftext( $canvas->image, $layout['font_size'], 0, (int) $line['x'], (int) $line['y'], $color, $font, $line['text'] );
			}

			return;
		}

		self::draw_styled( $canvas, $layer, $lines, $layout['font_size'], $font, $spacing );
	}

	/**
	 * Whether a layer uses none of the styling options (the 1.x drawing path).
	 *
	 * @param array $layer Layer.
	 *
	 * @return bool
	 */
	private static function is_plain( $layer ) {
		return empty( $layer['rotation'] )
			&& ( ! isset( $layer['opacity'] ) || 1.0 === (float) $layer['opacity'] )
			&& empty( $layer['letterSpacing'] )
			&& empty( $layer['stroke']['width'] )
			&& empty( $layer['shadow']['opacity'] )
			&& ( empty( $layer['highlight']['mode'] ) || 'none' === $layer['highlight']['mode'] );
	}

	/**
	 * Baseline position of every line, in canvas coordinates.
	 *
	 * @param array  $layout  Layout from TextLayout.
	 * @param array  $layer   Layer.
	 * @param string $font    Font path.
	 * @param float  $spacing Letter spacing.
	 *
	 * @return array[] { text, x, y, width }
	 */
	private static function position( $layout, $layer, $font, $spacing ) {
		$box         = $layer['box'];
		$size        = $layout['font_size'];
		$line_height = $size * $layer['lineHeight'];
		$total       = count( $layout['lines'] ) * $line_height;

		if ( 'top' === $layer['valign'] ) {
			$y = $box['y'] + $size;
		} elseif ( 'bottom' === $layer['valign'] ) {
			$y = $box['y'] + $box['h'] - $total + $size;
		} else {
			$y = $box['y'] + ( $box['h'] - $total ) / 2 + $size;
		}

		$lines = array();
		foreach ( $layout['lines'] as $text ) {
			$width = TextLayout::measure( $text, $size, $font, $spacing );

			if ( 'left' === $layer['align'] ) {
				$x = $box['x'];
			} elseif ( 'right' === $layer['align'] ) {
				$x = $box['x'] + $box['w'] - $width;
			} else {
				$x = $box['x'] + ( $box['w'] - $width ) / 2;
			}

			$lines[] = array(
				'text'  => $text,
				'x'     => $x,
				'y'     => $y,
				'width' => $width,
			);

			$y += $line_height;
		}

		return $lines;
	}

	/**
	 * Draw text with highlight, shadow, stroke, opacity, spacing or rotation.
	 *
	 * Everything is drawn on a separate image around the box, then composited.
	 *
	 * @param Canvas $canvas  Canvas.
	 * @param array  $layer   Layer.
	 * @param array  $lines   Positioned lines.
	 * @param float  $size    Font size.
	 * @param string $font    Font path.
	 * @param float  $spacing Letter spacing.
	 *
	 * @return void
	 */
	private static function draw_styled( Canvas $canvas, $layer, $lines, $size, $font, $spacing ) {
		$box       = $layer['box'];
		$stroke    = $layer['stroke'];
		$shadow    = $layer['shadow'];
		$highlight = $layer['highlight'];
		$extent    = imagettfbbox( $size, 0, $font, 'ÁHgy' );
		$ascent    = $extent ? -$extent[7] : $size;
		$descent   = $extent ? $extent[1] : 0;

		$top    = min( wp_list_pluck( $lines, 'y' ) ) - $ascent;
		$bottom = max( wp_list_pluck( $lines, 'y' ) ) + $descent;
		$left   = min( $box['x'], min( wp_list_pluck( $lines, 'x' ) ) );
		$right  = max( $box['x'] + $box['w'], self::right_edge( $lines ) );

		$margin      = (int) ceil( max( $stroke['width'], abs( $shadow['x'] ) + 3 * $shadow['blur'], abs( $shadow['y'] ) + 3 * $shadow['blur'], $highlight['padding'] ) ) + 2;
		$x0          = (int) floor( min( $left, $box['x'] ) ) - $margin;
		$y0          = (int) floor( min( $top, $box['y'] ) ) - $margin;
		$width       = (int) ceil( max( $right, $box['x'] + $box['w'] ) ) + $margin - $x0;
		$height      = (int) ceil( max( $bottom, $box['y'] + $box['h'] ) ) + $margin - $y0;
		$layer_image = Paint::transparent( $width, $height );

		if ( 'none' !== $highlight['mode'] ) {
			$rects = array();

			if ( 'block' === $highlight['mode'] ) {
				$rects[] = array( min( wp_list_pluck( $lines, 'x' ) ), $top, self::right_edge( $lines ), $bottom );
			} else {
				foreach ( $lines as $line ) {
					$rects[] = array( $line['x'], $line['y'] - $ascent, $line['x'] + $line['width'], $line['y'] + $descent );
				}
			}

			foreach ( $rects as $rect ) {
				$pad   = $highlight['padding'];
				$shape = Paint::shape(
					(int) round( $rect[2] - $rect[0] + 2 * $pad ),
					(int) round( $rect[3] - $rect[1] + 2 * $pad ),
					array(
						'kind'   => 'rect',
						'radius' => $highlight['radius'],
						'fill'   => array(
							'color'   => $highlight['color'],
							'opacity' => $highlight['opacity'],
						),
					)
				);
				imagecopy( $layer_image, $shape, (int) round( $rect[0] - $pad - $x0 ), (int) round( $rect[1] - $pad - $y0 ), 0, 0, imagesx( $shape ), imagesy( $shape ) );
				imagedestroy( $shape );
			}
		}

		if ( $shadow['opacity'] > 0 ) {
			$part = Paint::transparent( $width, $height );
			self::write( $part, $lines, $size, $font, $spacing, Paint::color( $part, $shadow['color'] ), $shadow['x'] - $x0, $shadow['y'] - $y0 );

			if ( $stroke['width'] > 0 ) {
				self::write_stroke( $part, $lines, $size, $font, $spacing, Paint::color( $part, $shadow['color'] ), $shadow['x'] - $x0, $shadow['y'] - $y0, $stroke['width'] );
			}

			for ( $i = 0; $i < $shadow['blur']; $i++ ) {
				imagefilter( $part, IMG_FILTER_GAUSSIAN_BLUR );
			}

			Paint::fade( $part, $shadow['opacity'] );
			imagecopy( $layer_image, $part, 0, 0, 0, 0, $width, $height );
			imagedestroy( $part );
		}

		if ( $stroke['width'] > 0 ) {
			$part = Paint::transparent( $width, $height );
			self::write_stroke( $part, $lines, $size, $font, $spacing, Paint::color( $part, $stroke['color'] ), -$x0, -$y0, $stroke['width'] );
			Paint::fade( $part, $stroke['opacity'] );
			imagecopy( $layer_image, $part, 0, 0, 0, 0, $width, $height );
			imagedestroy( $part );
		}

		self::write( $layer_image, $lines, $size, $font, $spacing, Paint::color( $layer_image, $layer['color'], $layer['opacity'] ), -$x0, -$y0 );

		Paint::composite( $canvas->image, $layer_image, $x0, $y0, isset( $layer['rotation'] ) ? $layer['rotation'] : 0 );
		imagedestroy( $layer_image );
	}

	/**
	 * Right edge of the widest-reaching line.
	 *
	 * @param array $lines Positioned lines.
	 *
	 * @return float
	 */
	private static function right_edge( $lines ) {
		$right = 0;
		foreach ( $lines as $line ) {
			$right = max( $right, $line['x'] + $line['width'] );
		}

		return $right;
	}

	/**
	 * Draw positioned lines, letter by letter when there is letter spacing.
	 *
	 * @param \GdImage|resource $image   Image.
	 * @param array             $lines   Positioned lines.
	 * @param float             $size    Font size.
	 * @param string            $font    Font path.
	 * @param float             $spacing Letter spacing.
	 * @param int               $color   Color.
	 * @param float             $dx      Horizontal offset.
	 * @param float             $dy      Vertical offset.
	 *
	 * @return void
	 */
	private static function write( $image, $lines, $size, $font, $spacing, $color, $dx, $dy ) {
		foreach ( $lines as $line ) {
			$x = $line['x'] + $dx;
			$y = (int) ( $line['y'] + $dy );

			if ( ! $spacing ) {
				imagettftext( $image, $size, 0, (int) $x, $y, $color, $font, $line['text'] );
				continue;
			}

			$chars = preg_split( '//u', $line['text'], -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $chars as $i => $char ) {
				$prefix = implode( '', array_slice( $chars, 0, $i ) );
				$offset = '' === $prefix ? 0 : TextLayout::measure( $prefix, $size, $font );
				imagettftext( $image, $size, 0, (int) round( $x + $offset + $i * $spacing ), $y, $color, $font, $char );
			}
		}
	}

	/**
	 * Draw an outline by drawing the text around a circle of `$width` pixels.
	 *
	 * @param \GdImage|resource $image   Image.
	 * @param array             $lines   Positioned lines.
	 * @param float             $size    Font size.
	 * @param string            $font    Font path.
	 * @param float             $spacing Letter spacing.
	 * @param int               $color   Color.
	 * @param float             $dx      Horizontal offset.
	 * @param float             $dy      Vertical offset.
	 * @param float             $width   Outline width.
	 *
	 * @return void
	 */
	private static function write_stroke( $image, $lines, $size, $font, $spacing, $color, $dx, $dy, $width ) {
		$r = (int) ceil( $width );

		for ( $ox = -$r; $ox <= $r; $ox++ ) {
			for ( $oy = -$r; $oy <= $r; $oy++ ) {
				if ( $ox * $ox + $oy * $oy <= $width * $width ) {
					self::write( $image, $lines, $size, $font, $spacing, $color, $dx + $ox, $dy + $oy );
				}
			}
		}
	}

	/**
	 * Change the case of a text.
	 *
	 * @param string $text      Text.
	 * @param string $transform none, upper, lower or title.
	 *
	 * @return string
	 */
	private static function transform( $text, $transform ) {
		switch ( $transform ) {
			case 'upper':
				return mb_strtoupper( $text, 'UTF-8' );
			case 'lower':
				return mb_strtolower( $text, 'UTF-8' );
			case 'title':
				return mb_convert_case( $text, MB_CASE_TITLE, 'UTF-8' );
		}

		return $text;
	}
}
