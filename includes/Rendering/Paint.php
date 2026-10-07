<?php

namespace ArtificialImageGenerator\Rendering;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Drawing helpers GD doesn't have: gradients, anti-aliased shapes, opacity and
 * rotated compositing.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Paint {

	/**
	 * Shapes are drawn this many times larger, then scaled down, to smooth their edges.
	 *
	 * @var int
	 */
	const SUPERSAMPLE = 3;

	/**
	 * A fully transparent image, ready to draw on.
	 *
	 * @param int $width  Width.
	 * @param int $height Height.
	 *
	 * @return \GdImage|resource
	 */
	public static function transparent( $width, $height ) {
		$image = imagecreatetruecolor( max( 1, (int) $width ), max( 1, (int) $height ) );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefilledrectangle( $image, 0, 0, imagesx( $image ) - 1, imagesy( $image ) - 1, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
		imagealphablending( $image, true );

		return $image;
	}

	/**
	 * Allocate a hex color with an opacity.
	 *
	 * @param \GdImage|resource $image   Image.
	 * @param string            $hex     Color.
	 * @param float             $opacity Opacity, 0–1.
	 *
	 * @return int
	 */
	public static function color( $image, $hex, $opacity = 1.0 ) {
		$rgb = Canvas::rgb( $hex );
		$rgb = $rgb ? $rgb : array( 0, 0, 0 );

		return imagecolorallocatealpha( $image, $rgb[0], $rgb[1], $rgb[2], self::alpha( $opacity ) );
	}

	/**
	 * GD alpha (0 opaque – 127 transparent) for an opacity.
	 *
	 * @param float $opacity Opacity, 0–1.
	 *
	 * @return int
	 */
	public static function alpha( $opacity ) {
		return (int) round( 127 * ( 1 - max( 0, min( 1, (float) $opacity ) ) ) );
	}

	/**
	 * A gradient image.
	 *
	 * Gradients are smooth, so they're computed at a lower resolution and scaled up.
	 *
	 * @param int   $width    Width.
	 * @param int   $height   Height.
	 * @param array $gradient Gradient from Schema::gradient(): kind (linear|radial), angle (degrees,
	 *                        0 = to top, 90 = to right), cx and cy (radial center, 0–1) and stops.
	 *
	 * @return \GdImage|resource
	 */
	public static function gradient( $width, $height, $gradient ) {
		$width  = max( 1, (int) $width );
		$height = max( 1, (int) $height );
		$scale  = max( 1, (int) ceil( max( $width, $height ) / 320 ) );
		$w      = max( 2, (int) ceil( $width / $scale ) );
		$h      = max( 2, (int) ceil( $height / $scale ) );
		$stops  = self::stops( $gradient['stops'] );
		$small  = self::transparent( $w, $h );

		imagealphablending( $small, false );

		$radial = 'radial' === $gradient['kind'];
		$angle  = deg2rad( isset( $gradient['angle'] ) ? (float) $gradient['angle'] : 180 );
		$dx     = sin( $angle );
		$dy     = -cos( $angle );
		$length = abs( $w * $dx ) + abs( $h * $dy );
		$cx     = ( isset( $gradient['cx'] ) ? (float) $gradient['cx'] : 0.5 ) * $w;
		$cy     = ( isset( $gradient['cy'] ) ? (float) $gradient['cy'] : 0.5 ) * $h;
		$reach  = max( hypot( $cx, $cy ), hypot( $w - $cx, $cy ), hypot( $cx, $h - $cy ), hypot( $w - $cx, $h - $cy ) );
		$cache  = array();

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$px = $x + 0.5;
				$py = $y + 0.5;
				$t  = $radial
					? hypot( $px - $cx, $py - $cy ) / max( 1, $reach )
					: ( ( $px - $w / 2 ) * $dx + ( $py - $h / 2 ) * $dy ) / max( 1, $length ) + 0.5;
				$k  = (int) round( max( 0, min( 1, $t ) ) * 255 );

				if ( ! isset( $cache[ $k ] ) ) {
					$cache[ $k ] = self::sample( $stops, $k / 255 );
				}

				imagesetpixel( $small, $x, $y, $cache[ $k ] );
			}
		}

		if ( 1 === $scale ) {
			imagealphablending( $small, true );

			return $small;
		}

		$image = self::transparent( $width, $height );
		imagealphablending( $image, false );
		imagecopyresampled( $image, $small, 0, 0, 0, 0, $width, $height, $w, $h );
		imagealphablending( $image, true );
		imagedestroy( $small );

		return $image;
	}

	/**
	 * A filled shape with smooth edges.
	 *
	 * @param int   $width  Width.
	 * @param int   $height Height.
	 * @param array $shape  Shape: kind (rect|ellipse), radius, fill ({ color, opacity }, a gradient
	 *                      or null) and border ({ width, color, opacity } or null).
	 *
	 * @return \GdImage|resource
	 */
	public static function shape( $width, $height, $shape ) {
		$width  = max( 1, (int) $width );
		$height = max( 1, (int) $height );
		$kind   = $shape['kind'];
		$radius = isset( $shape['radius'] ) ? (float) $shape['radius'] : 0;
		$border = isset( $shape['border'] ) ? $shape['border'] : null;
		$bw     = $border ? (float) $border['width'] : 0;
		$fill   = isset( $shape['fill'] ) ? $shape['fill'] : null;
		$image  = self::transparent( $width, $height );

		if ( $fill ) {
			$inner = function ( $big, $s ) use ( $kind, $width, $height, $radius, $bw, $fill ) {
				$color = isset( $fill['stops'] ) ? imagecolorallocatealpha( $big, 0, 0, 0, 0 ) : self::color( $big, $fill['color'], $fill['opacity'] );
				self::fill_shape( $big, $kind, $bw * $s, $bw * $s, ( $width - $bw ) * $s, ( $height - $bw ) * $s, max( 0, $radius - $bw ) * $s, $color );
			};
			$part  = self::supersampled( $width, $height, $inner );

			if ( isset( $fill['stops'] ) ) {
				$gradient = self::gradient( $width, $height, $fill );
				self::mask( $gradient, $part );
				imagedestroy( $part );
				$part = $gradient;
			}

			imagecopy( $image, $part, 0, 0, 0, 0, $width, $height );
			imagedestroy( $part );
		}

		if ( $bw > 0 ) {
			$ring = function ( $big, $s ) use ( $kind, $width, $height, $radius, $bw, $border ) {
				self::fill_shape( $big, $kind, 0, 0, $width * $s, $height * $s, $radius * $s, self::color( $big, $border['color'], $border['opacity'] ) );
				self::fill_shape( $big, $kind, $bw * $s, $bw * $s, ( $width - $bw ) * $s, ( $height - $bw ) * $s, max( 0, $radius - $bw ) * $s, imagecolorallocatealpha( $big, 0, 0, 0, 127 ) );
			};
			$part = self::supersampled( $width, $height, $ring );

			imagecopy( $image, $part, 0, 0, 0, 0, $width, $height );
			imagedestroy( $part );
		}

		return $image;
	}

	/**
	 * Draw at SUPERSAMPLE times the size, then scale down for smooth edges.
	 *
	 * @param int      $width  Width.
	 * @param int      $height Height.
	 * @param callable $draw   Called with the large image (alpha blending off) and the scale.
	 *
	 * @return \GdImage|resource
	 */
	private static function supersampled( $width, $height, $draw ) {
		$s   = self::SUPERSAMPLE;
		$big = self::transparent( $width * $s, $height * $s );

		imagealphablending( $big, false );
		$draw( $big, $s );

		$image = self::transparent( $width, $height );
		imagealphablending( $image, false );
		imagecopyresampled( $image, $big, 0, 0, 0, 0, $width, $height, $width * $s, $height * $s );
		imagealphablending( $image, true );
		imagedestroy( $big );

		return $image;
	}

	/**
	 * Cut an image to a shape (rounded corners or a circle) with smooth edges.
	 *
	 * @param \GdImage|resource $image  Image; changed in place.
	 * @param string            $kind   rect or ellipse.
	 * @param float             $radius Corner radius for rect.
	 *
	 * @return void
	 */
	public static function clip( $image, $kind, $radius ) {
		$w    = imagesx( $image );
		$h    = imagesy( $image );
		$mask = self::shape(
			$w,
			$h,
			array(
				'kind'   => $kind,
				'radius' => $radius,
				'fill'   => array(
					'color'   => '#000000',
					'opacity' => 1,
				),
			)
		);

		self::mask( $image, $mask );
		imagedestroy( $mask );
	}

	/**
	 * Multiply an image's alpha by a mask's alpha.
	 *
	 * @param \GdImage|resource $image Image; changed in place.
	 * @param \GdImage|resource $mask  Mask of the same size.
	 *
	 * @return void
	 */
	public static function mask( $image, $mask ) {
		$w = imagesx( $image );
		$h = imagesy( $image );

		imagealphablending( $image, false );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$keep = 127 - ( ( imagecolorat( $mask, $x, $y ) >> 24 ) & 0x7F );

				if ( 127 === $keep ) {
					continue;
				}

				$rgba    = imagecolorat( $image, $x, $y );
				$opacity = 127 - ( ( $rgba >> 24 ) & 0x7F );
				$alpha   = 127 - (int) round( $opacity * $keep / 127 );
				imagesetpixel( $image, $x, $y, ( $rgba & 0xFFFFFF ) | ( $alpha << 24 ) );
			}
		}

		imagealphablending( $image, true );
	}

	/**
	 * Multiply every pixel's opacity.
	 *
	 * @param \GdImage|resource $image   Image; changed in place.
	 * @param float             $opacity Opacity, 0–1.
	 *
	 * @return void
	 */
	public static function fade( $image, $opacity ) {
		if ( $opacity >= 1 ) {
			return;
		}

		$w = imagesx( $image );
		$h = imagesy( $image );

		imagealphablending( $image, false );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;

				if ( 127 === $alpha ) {
					continue;
				}

				$alpha = 127 - (int) round( ( 127 - $alpha ) * $opacity );
				imagesetpixel( $image, $x, $y, ( $rgba & 0xFFFFFF ) | ( $alpha << 24 ) );
			}
		}

		imagealphablending( $image, true );
	}

	/**
	 * Apply sanitized image adjustments in place.
	 *
	 * @param \GdImage|resource $image  Image.
	 * @param array|null        $adjust Adjustments from Schema::adjustments().
	 *
	 * @return void
	 */
	public static function adjust( $image, $adjust ) {
		if ( ! $adjust || ! function_exists( 'imagefilter' ) ) {
			return;
		}

		if ( $adjust['brightness'] ) {
			imagefilter( $image, IMG_FILTER_BRIGHTNESS, (int) round( $adjust['brightness'] * 2.55 ) );
		}

		// GD's contrast runs the other way: negative values increase it.
		if ( $adjust['contrast'] ) {
			imagefilter( $image, IMG_FILTER_CONTRAST, (int) round( -$adjust['contrast'] ) );
		}

		for ( $i = 0; $i < $adjust['blur']; $i++ ) {
			imagefilter( $image, IMG_FILTER_GAUSSIAN_BLUR );
		}

		if ( $adjust['grayscale'] || $adjust['duotone'] ) {
			imagefilter( $image, IMG_FILTER_GRAYSCALE );
		}

		if ( $adjust['duotone'] ) {
			$dark  = Canvas::rgb( $adjust['duotone']['dark'] );
			$light = Canvas::rgb( $adjust['duotone']['light'] );
			$map   = array();

			for ( $i = 0; $i < 256; $i++ ) {
				$map[ $i ] = ( (int) round( $dark[0] + ( $light[0] - $dark[0] ) * $i / 255 ) << 16 )
					| ( (int) round( $dark[1] + ( $light[1] - $dark[1] ) * $i / 255 ) << 8 )
					| (int) round( $dark[2] + ( $light[2] - $dark[2] ) * $i / 255 );
			}

			$w = imagesx( $image );
			$h = imagesy( $image );

			imagealphablending( $image, false );

			for ( $y = 0; $y < $h; $y++ ) {
				for ( $x = 0; $x < $w; $x++ ) {
					$rgba = imagecolorat( $image, $x, $y );
					imagesetpixel( $image, $x, $y, ( $rgba & 0x7F000000 ) | $map[ $rgba & 0xFF ] );
				}
			}

			imagealphablending( $image, true );
		}
	}

	/**
	 * Draw an image onto another, rotated clockwise around its center.
	 *
	 * @param \GdImage|resource $target   Target.
	 * @param \GdImage|resource $source   Source.
	 * @param float             $x        Left edge of the unrotated source.
	 * @param float             $y        Top edge of the unrotated source.
	 * @param float             $rotation Degrees clockwise.
	 *
	 * @return void
	 */
	public static function composite( $target, $source, $x, $y, $rotation = 0.0 ) {
		$w = imagesx( $source );
		$h = imagesy( $source );

		if ( 0.0 !== (float) fmod( (float) $rotation, 360 ) ) {
			$rotated = imagerotate( $source, -$rotation, imagecolorallocatealpha( $source, 0, 0, 0, 127 ) );

			if ( $rotated ) {
				imagesavealpha( $rotated, true );
				$x     += ( $w - imagesx( $rotated ) ) / 2;
				$y     += ( $h - imagesy( $rotated ) ) / 2;
				$source = $rotated;
			}
		}

		imagealphablending( $target, true );
		imagecopy( $target, $source, (int) round( $x ), (int) round( $y ), 0, 0, imagesx( $source ), imagesy( $source ) );

		if ( isset( $rotated ) && $rotated ) {
			imagedestroy( $rotated );
		}
	}

	/**
	 * Fill a rectangle (optionally rounded) or an ellipse.
	 *
	 * @param \GdImage|resource $image  Image.
	 * @param string            $kind   rect or ellipse.
	 * @param float             $x1     Left.
	 * @param float             $y1     Top.
	 * @param float             $x2     Right (exclusive).
	 * @param float             $y2     Bottom (exclusive).
	 * @param float             $radius Corner radius.
	 * @param int               $color  Color.
	 *
	 * @return void
	 */
	private static function fill_shape( $image, $kind, $x1, $y1, $x2, $y2, $radius, $color ) {
		$x1 = (int) round( $x1 );
		$y1 = (int) round( $y1 );
		$x2 = (int) round( $x2 ) - 1;
		$y2 = (int) round( $y2 ) - 1;

		if ( $x2 < $x1 || $y2 < $y1 ) {
			return;
		}

		if ( 'ellipse' === $kind ) {
			imagefilledellipse( $image, (int) ( ( $x1 + $x2 ) / 2 ), (int) ( ( $y1 + $y2 ) / 2 ), $x2 - $x1 + 1, $y2 - $y1 + 1, $color );

			return;
		}

		$r = (int) min( $radius, ( $x2 - $x1 + 1 ) / 2, ( $y2 - $y1 + 1 ) / 2 );

		if ( $r <= 0 ) {
			imagefilledrectangle( $image, $x1, $y1, $x2, $y2, $color );

			return;
		}

		imagefilledrectangle( $image, $x1 + $r, $y1, $x2 - $r, $y2, $color );
		imagefilledrectangle( $image, $x1, $y1 + $r, $x2, $y2 - $r, $color );

		foreach ( array( array( $x1 + $r, $y1 + $r ), array( $x2 - $r, $y1 + $r ), array( $x1 + $r, $y2 - $r ), array( $x2 - $r, $y2 - $r ) ) as $corner ) {
			imagefilledellipse( $image, $corner[0], $corner[1], 2 * $r, 2 * $r, $color );
		}
	}

	/**
	 * Sorted gradient stops with allocated-free RGBA values.
	 *
	 * @param array $stops Stops.
	 *
	 * @return array
	 */
	private static function stops( $stops ) {
		$clean = array();

		foreach ( (array) $stops as $stop ) {
			$rgb = Canvas::rgb( isset( $stop['color'] ) ? $stop['color'] : '' );
			if ( $rgb ) {
				$clean[] = array(
					'pos'   => isset( $stop['pos'] ) ? (float) $stop['pos'] : 0,
					'rgb'   => $rgb,
					'alpha' => 127 - (int) round( 127 * ( isset( $stop['opacity'] ) ? (float) $stop['opacity'] : 1 ) ),
				);
			}
		}

		if ( ! $clean ) {
			$clean[] = array(
				'pos'   => 0,
				'rgb'   => array( 0, 0, 0 ),
				'alpha' => 0,
			);
		}

		usort(
			$clean,
			function ( $a, $b ) {
				return $a['pos'] <=> $b['pos'];
			}
		);

		return $clean;
	}

	/**
	 * Color at a position along the stops, as a GD truecolor value with alpha.
	 *
	 * @param array $stops Sorted stops.
	 * @param float $t     Position, 0–1.
	 *
	 * @return int
	 */
	private static function sample( $stops, $t ) {
		$last = end( $stops );

		if ( $t <= $stops[0]['pos'] ) {
			$a = $stops[0];
			$b = $stops[0];
			$f = 0;
		} elseif ( $t >= $last['pos'] ) {
			$a = $last;
			$b = $last;
			$f = 0;
		} else {
			$count = count( $stops );
			for ( $i = 1; $i < $count; $i++ ) {
				if ( $t <= $stops[ $i ]['pos'] ) {
					$a = $stops[ $i - 1 ];
					$b = $stops[ $i ];
					break;
				}
			}
			$f = ( $t - $a['pos'] ) / max( 0.0001, $b['pos'] - $a['pos'] );
		}

		$mix = function ( $from, $to ) use ( $f ) {
			return (int) round( $from + ( $to - $from ) * $f );
		};

		return ( $mix( $a['alpha'], $b['alpha'] ) << 24 )
			| ( $mix( $a['rgb'][0], $b['rgb'][0] ) << 16 )
			| ( $mix( $a['rgb'][1], $b['rgb'][1] ) << 8 )
			| $mix( $a['rgb'][2], $b['rgb'][2] );
	}
}
