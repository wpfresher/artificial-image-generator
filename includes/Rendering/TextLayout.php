<?php

namespace ArtificialImageGenerator\Rendering;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Line wrapping and font fitting for text layers.
 *
 * Sizes are GD font sizes, the unit of the template font size field.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class TextLayout {

	/**
	 * Smallest size `wrap()` shrinks to.
	 *
	 * @var int
	 */
	const MIN_WRAP_SIZE = 12;

	/**
	 * Width of a text in pixels.
	 *
	 * @param string $text      Text.
	 * @param float  $size      Font size.
	 * @param string $font_path TrueType font path.
	 *
	 * @return int
	 */
	public static function measure( $text, $size, $font_path ) {
		$bbox = imagettfbbox( $size, 0, $font_path, $text );

		return $bbox ? $bbox[2] - $bbox[0] : 0;
	}

	/**
	 * Split a text into lines that fit a width, shrinking the font only when a
	 * single word is wider than the line. Words that still don't fit are split.
	 *
	 * @param string $text      Text.
	 * @param float  $font_size Requested font size.
	 * @param string $font_path TrueType font path.
	 * @param int    $max_width Maximum line width in pixels.
	 * @param float  $min_size  Smallest size to shrink to. Defaults to MIN_WRAP_SIZE.
	 *
	 * @return array { @type float $font_size, @type string[] $lines }
	 */
	public static function wrap( $text, $font_size, $font_path, $max_width, $min_size = null ) {
		$words    = preg_split( '/\s+/u', trim( $text ) );
		$min_size = min( $font_size, null === $min_size ? self::MIN_WRAP_SIZE : $min_size );

		$widest = 0;
		foreach ( $words as $word ) {
			$widest = max( $widest, self::measure( $word, $font_size, $font_path ) );
		}

		if ( $widest > $max_width ) {
			$font_size = max( $min_size, floor( $font_size * $max_width / $widest ) );
		}

		return array(
			'font_size' => $font_size,
			'lines'     => self::break_lines( $words, $font_size, $font_path, $max_width ),
		);
	}

	/**
	 * Lay a text out in a box: shrink from `$max_size` until the lines fit the
	 * box height and `$max_lines`; at `$min_size` cut the last line with an ellipsis.
	 *
	 * @param string $text      Text.
	 * @param string $font_path TrueType font path.
	 * @param array  $args      {
	 *     Layout arguments.
	 *
	 *     @type float $max_size    Largest size.
	 *     @type float $min_size    Smallest size.
	 *     @type int   $width       Box width.
	 *     @type int   $height      Box height.
	 *     @type float $line_height Line height as a multiple of the size.
	 *     @type int   $max_lines   Most lines, 0 for no limit.
	 * }
	 *
	 * @return array { @type float $font_size, @type string[] $lines }
	 */
	public static function fit( $text, $font_path, $args ) {
		$words = preg_split( '/\s+/u', trim( $text ) );
		$size  = (float) $args['max_size'];
		$min   = min( $size, (float) $args['min_size'] );

		$try = function ( $size ) use ( $words, $font_path, $args ) {
			$lines = self::break_lines( $words, $size, $font_path, $args['width'] );
			$fits  = count( $lines ) * $size * $args['line_height'] <= $args['height']
				&& ( ! $args['max_lines'] || count( $lines ) <= $args['max_lines'] );

			return array( $fits, $lines );
		};

		list( $fits, $lines ) = $try( $size );

		if ( ! $fits ) {
			// Largest whole size that fits, assuming smaller text never needs more room.
			$low   = (int) ceil( $min );
			$high  = (int) ceil( $size ) - 1;
			$size  = $min;
			$lines = $try( $min )[1];

			while ( $low <= $high ) {
				$mid                    = intdiv( $low + $high, 2 );
				list( $ok, $mid_lines ) = $try( $mid );

				if ( $ok ) {
					$size  = $mid;
					$lines = $mid_lines;
					$low   = $mid + 1;
				} else {
					$high = $mid - 1;
				}
			}
		}

		$max_lines = (int) $args['max_lines'];
		$by_height = max( 1, (int) floor( $args['height'] / ( $size * $args['line_height'] ) ) );
		$max_lines = $max_lines ? min( $max_lines, $by_height ) : $by_height;

		if ( count( $lines ) > $max_lines ) {
			$lines = array_slice( $lines, 0, $max_lines );
			$last  = array_pop( $lines );

			while ( '' !== $last && self::measure( $last . '…', $size, $font_path ) > $args['width'] ) {
				$last = rtrim( mb_substr( $last, 0, -1 ) );
			}

			$lines[] = $last . '…';
		}

		return array(
			'font_size' => $size,
			'lines'     => $lines,
		);
	}

	/**
	 * Break words into lines no wider than `$max_width`, splitting words that are wider.
	 *
	 * @param string[] $words     Words.
	 * @param float    $font_size Font size.
	 * @param string   $font_path TrueType font path.
	 * @param int      $max_width Maximum line width.
	 *
	 * @return string[]
	 */
	private static function break_lines( $words, $font_size, $font_path, $max_width ) {
		$pieces = array();
		foreach ( $words as $word ) {
			if ( self::measure( $word, $font_size, $font_path ) <= $max_width ) {
				$pieces[] = $word;
				continue;
			}

			$chunk = '';
			foreach ( preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
				if ( '' !== $chunk && self::measure( $chunk . $char, $font_size, $font_path ) > $max_width ) {
					$pieces[] = $chunk;
					$chunk    = '';
				}
				$chunk .= $char;
			}
			$pieces[] = $chunk;
		}

		$lines = array();
		$line  = '';
		foreach ( $pieces as $piece ) {
			$new_line = '' !== $line ? $line . ' ' . $piece : $piece;

			if ( '' !== $line && self::measure( $new_line, $font_size, $font_path ) > $max_width ) {
				$lines[] = $line;
				$line    = $piece;
			} else {
				$line = $new_line;
			}
		}

		if ( '' !== $line ) {
			$lines[] = $line;
		}

		return $lines;
	}
}
