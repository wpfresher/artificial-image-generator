<?php

namespace ArtificialImageGenerator\Rendering;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Fonts text layers can use.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Fonts {

	/**
	 * Font used when a layer names an unknown one.
	 *
	 * @var string
	 */
	const DEFAULT_FONT = 'roboto-bold';

	/**
	 * Fonts as ID => array( label, file path ).
	 *
	 * @return array
	 */
	public static function all() {
		$fonts = array(
			'roboto-bold' => array( 'Roboto Bold', AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf' ),
		);

		/**
		 * Filter the fonts text layers can use.
		 *
		 * @param array $fonts Fonts as ID => array( label, absolute path to a .ttf or .otf file ).
		 *
		 * @since 1.7.0
		 */
		return (array) apply_filters( 'aimg_fonts', $fonts );
	}

	/**
	 * File path of a font, falling back to the default font.
	 *
	 * @param string $id Font ID.
	 *
	 * @return string
	 */
	public static function path( $id ) {
		$fonts = self::all();

		if ( isset( $fonts[ $id ][1] ) && file_exists( $fonts[ $id ][1] ) ) {
			return $fonts[ $id ][1];
		}

		return AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf';
	}
}
