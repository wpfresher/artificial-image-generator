<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * An image from the Media Library, placed in a box. With several images, one is
 * picked at random per render (the 1.x overlay behaviour).
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

		return array(
			'attachments' => $ids,
			'pick'        => Schema::choice( isset( $layer['pick'] ) ? $layer['pick'] : '', array( 'random', 'first' ) ),
			'box'         => Schema::box( isset( $layer['box'] ) ? $layer['box'] : array(), $canvas ),
			'fit'         => Schema::choice( isset( $layer['fit'] ) ? $layer['fit'] : '', array( 'contain', 'cover', 'fill' ) ),
			'anchor'      => Schema::choice( isset( $layer['anchor'] ) ? $layer['anchor'] : '', self::anchors(), 'center-center' ),
			'opacity'     => Schema::number( isset( $layer['opacity'] ) ? $layer['opacity'] : 1, 0, 1 ),
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
		foreach ( self::files( $layer ) as $file ) {
			$source = self::load( $file );

			if ( ! $source ) {
				continue;
			}

			$placed = self::place( $source, $layer );
			imagedestroy( $source );

			if ( 1 > $layer['opacity'] ) {
				self::fade( $placed['image'], $layer['opacity'] );
			}

			imagecopy( $canvas->image, $placed['image'], (int) $placed['x'], (int) $placed['y'], 0, 0, imagesx( $placed['image'] ), imagesy( $placed['image'] ) );
			imagedestroy( $placed['image'] );
		}
	}

	/**
	 * Files to draw.
	 *
	 * @param array $layer Layer.
	 *
	 * @return string[]
	 */
	private static function files( $layer ) {
		if ( isset( $layer['_files'] ) ) {
			return (array) $layer['_files'];
		}

		$files = array();
		foreach ( $layer['attachments'] as $id ) {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$files[] = $file;
			}
		}

		if ( count( $files ) > 1 ) {
			$files = array( 'random' === $layer['pick'] ? $files[ array_rand( $files ) ] : $files[0] );
		}

		return $files;
	}

	/**
	 * Open a PNG, JPEG or WebP file.
	 *
	 * @param string $file Path.
	 *
	 * @return \GdImage|resource|false
	 */
	private static function load( $file ) {
		// A corrupt file makes the imagecreatefrom*() call fail; never let that break a render.
		$info = file_exists( $file ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $info ) {
			return false;
		}

		switch ( $info[2] ) {
			case IMAGETYPE_PNG:
				$image = @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case IMAGETYPE_WEBP:
				$image = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			default:
				$image = false;
		}

		if ( $image ) {
			imagesavealpha( $image, true );
		}

		return $image;
	}

	/**
	 * Scale an image into the layer's box and work out where it goes.
	 *
	 * @param \GdImage|resource $source Image.
	 * @param array             $layer  Layer.
	 *
	 * @return array { @type \GdImage|resource $image, @type float $x, @type float $y }
	 */
	private static function place( $source, $layer ) {
		$box  = $layer['box'];
		$sw   = imagesx( $source );
		$sh   = imagesy( $source );
		$crop = array( 0, 0, $sw, $sh );

		if ( 'fill' === $layer['fit'] ) {
			$w = $box['w'];
			$h = $box['h'];
		} elseif ( 'cover' === $layer['fit'] ) {
			$w     = $box['w'];
			$h     = $box['h'];
			$scale = max( $w / $sw, $h / $sh );
			$cw    = (int) round( $w / $scale );
			$ch    = (int) round( $h / $scale );
			$crop  = array( (int) ( ( $sw - $cw ) / 2 ), (int) ( ( $sh - $ch ) / 2 ), $cw, $ch );
		} else {
			$scale = min( $box['w'] / $sw, $box['h'] / $sh );
			$w     = (int) ( $sw * $scale );
			$h     = (int) ( $sh * $scale );
		}

		$image = imagecreatetruecolor( $w, $h );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagecopyresampled( $image, $source, 0, 0, $crop[0], $crop[1], $w, $h, $crop[2], $crop[3] );

		list( $x, $y ) = self::anchor( $layer['anchor'], $box, $w, $h );

		return array(
			'image' => $image,
			'x'     => $x,
			'y'     => $y,
		);
	}

	/**
	 * Top-left corner of a `$w` × `$h` image anchored in a box.
	 *
	 * @param string $anchor Anchor.
	 * @param array  $box    Box.
	 * @param int    $w      Width.
	 * @param int    $h      Height.
	 *
	 * @return float[]
	 */
	private static function anchor( $anchor, $box, $w, $h ) {
		$left   = $box['x'];
		$center = $box['x'] + ( $box['w'] - $w ) / 2;
		$right  = $box['x'] + $box['w'] - $w;
		$top    = $box['y'];
		$middle = $box['y'] + ( $box['h'] - $h ) / 2;
		$bottom = $box['y'] + $box['h'] - $h;

		switch ( $anchor ) {
			case 'top-left':
				return array( $left, $top );
			case 'top-center':
				return array( $center, $top );
			case 'top-right':
				return array( $right, $top );
			case 'left-center':
				return array( $left, $middle );
			case 'center-center':
				return array( $center, $middle );
			case 'right-center':
				return array( $right, $middle );
			case 'bottom-left':
				return array( $left, $bottom );
			case 'bottom-center':
				return array( $center, $bottom );
			default:
				return array( $right, $bottom );
		}
	}

	/**
	 * Multiply every pixel's opacity.
	 *
	 * @param \GdImage|resource $image   Image with alpha blending off.
	 * @param float             $opacity Opacity, 0–1.
	 *
	 * @return void
	 */
	private static function fade( $image, $opacity ) {
		$w = imagesx( $image );
		$h = imagesy( $image );

		for ( $x = 0; $x < $w; $x++ ) {
			for ( $y = 0; $y < $h; $y++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = 127 - (int) round( ( 127 - ( ( $rgba >> 24 ) & 0x7F ) ) * $opacity );
				imagesetpixel( $image, $x, $y, ( $rgba & 0xFFFFFF ) | ( $alpha << 24 ) );
			}
		}
	}
}
