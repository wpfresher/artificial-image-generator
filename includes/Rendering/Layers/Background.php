<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Images;
use ArtificialImageGenerator\Rendering\Paint;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Background: a solid color, a palette (one color picked at random per render),
 * a gradient, or an image (picked, or the post's own).
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
		$kind   = isset( $fill['kind'] ) ? $fill['kind'] : 'solid';
		$color  = isset( $fill['color'] ) ? Schema::color( $fill['color'] ) : '';
		$color  = $color ? $color : self::FALLBACK;
		$adjust = Schema::adjustments( isset( $layer['adjust'] ) ? $layer['adjust'] : array() );

		if ( 'palette' === $kind ) {
			$colors = isset( $fill['colors'] ) && is_array( $fill['colors'] ) ? $fill['colors'] : array();
			$colors = array_values( array_filter( array_map( array( Schema::class, 'color' ), array_slice( $colors, 0, 20 ) ) ) );

			if ( $colors ) {
				return array(
					'fill'   => array(
						'kind'   => 'palette',
						'colors' => $colors,
					),
					'adjust' => $adjust,
				);
			}
		}

		if ( in_array( $kind, array( 'linear', 'radial' ), true ) ) {
			return array(
				'fill'   => Schema::gradient( $fill ),
				'adjust' => $adjust,
			);
		}

		if ( 'image' === $kind ) {
			$image = Image::sanitize( $fill, $canvas );

			return array(
				'fill'   => array(
					'kind'        => 'image',
					'source'      => $image['source'],
					'attachments' => $image['attachments'],
					'pick'        => $image['pick'],
					'fit'         => Schema::choice( isset( $fill['fit'] ) ? $fill['fit'] : '', array( 'cover', 'contain', 'tile' ) ),
					'focal'       => $image['focal'],
					'color'       => $color,
				),
				'adjust' => $adjust,
			);
		}

		return array(
			'fill'   => array(
				'kind'  => 'solid',
				'color' => $color,
			),
			'adjust' => $adjust,
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

		if ( 'linear' === $fill['kind'] || 'radial' === $fill['kind'] ) {
			$canvas->background = Canvas::rgb( $fill['stops'][0]['color'] );
			$gradient           = Paint::gradient( $canvas->width, $canvas->height, $fill );
			imagecopy( $canvas->image, $gradient, 0, 0, 0, 0, $canvas->width, $canvas->height );
			imagedestroy( $gradient );
		} elseif ( 'image' === $fill['kind'] ) {
			self::fill( $canvas, $fill['color'] );
			self::image( $canvas, $fill );
		} else {
			self::fill( $canvas, 'palette' === $fill['kind'] ? $fill['colors'][ array_rand( $fill['colors'] ) ] : $fill['color'] );
		}

		if ( isset( $layer['adjust'] ) ) {
			Paint::adjust( $canvas->image, $layer['adjust'] );
		}
	}

	/**
	 * Fill the canvas with one color.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param string $hex    Color; invalid colors use FALLBACK.
	 *
	 * @return void
	 */
	private static function fill( Canvas $canvas, $hex ) {
		$rgb = Canvas::rgb( $hex );

		if ( ! $rgb ) {
			$rgb = Canvas::rgb( self::FALLBACK );
		}

		$canvas->background = $rgb;
		imagefill( $canvas->image, 0, 0, imagecolorallocate( $canvas->image, $rgb[0], $rgb[1], $rgb[2] ) );
	}

	/**
	 * Draw a background image over the whole canvas.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param array  $fill   Image fill.
	 *
	 * @return void
	 */
	private static function image( Canvas $canvas, $fill ) {
		$files = Images::files( $fill, $canvas->post_id );
		$image = $files ? Images::load( $files[0] ) : false;

		if ( ! $image ) {
			return;
		}

		$placed = Images::place( $image, $canvas->width, $canvas->height, $fill['fit'], 'center-center', $fill['focal'] );
		imagedestroy( $image );

		imagecopy( $canvas->image, $placed['image'], (int) $placed['x'], (int) $placed['y'], 0, 0, imagesx( $placed['image'] ), imagesy( $placed['image'] ) );
		imagedestroy( $placed['image'] );
	}
}
