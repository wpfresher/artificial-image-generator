<?php

namespace ArtificialImageGenerator\Rendering;

use ArtificialImageGenerator\Templates\MergeTags;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The image being rendered and what layers share while drawing it.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Canvas {

	/**
	 * GD image.
	 *
	 * @var \GdImage|resource
	 */
	public $image;

	/**
	 * Width in pixels.
	 *
	 * @var int
	 */
	public $width;

	/**
	 * Height in pixels.
	 *
	 * @var int
	 */
	public $height;

	/**
	 * Background color picked for this render, as array( r, g, b ).
	 *
	 * @var int[]
	 */
	public $background = array( 0, 0, 0 );

	/**
	 * Merge tag values, as name => value.
	 *
	 * @var array
	 */
	public $tags = array();

	/**
	 * Post being rendered for, or 0.
	 *
	 * @var int
	 */
	public $post_id = 0;

	/**
	 * Constructor.
	 *
	 * @param \GdImage|resource $image  GD image.
	 * @param int               $width  Width.
	 * @param int               $height Height.
	 */
	public function __construct( $image, $width, $height ) {
		$this->image  = $image;
		$this->width  = $width;
		$this->height = $height;
	}

	/**
	 * Hex color as array( r, g, b ), or null when it isn't a 6-digit hex color.
	 *
	 * @param string $hex Color.
	 *
	 * @return int[]|null
	 */
	public static function rgb( $hex ) {
		if ( ! is_string( $hex ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) {
			return null;
		}

		return sscanf( $hex, '#%02x%02x%02x' );
	}

	/**
	 * Replace merge tags in a text.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	public function merge( $text ) {
		return MergeTags::replace( $text, $this->tags, $this->post_id );
	}
}
