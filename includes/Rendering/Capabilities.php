<?php

namespace ArtificialImageGenerator\Rendering;

use ArtificialImageGenerator\Templates\MergeTags;
use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * What this server can render, for the template editor and Site Health.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Capabilities {

	/**
	 * Renderer features as feature => available.
	 *
	 * @return array
	 */
	public static function features() {
		$gd = function_exists( 'gd_info' ) ? gd_info() : array();

		return array(
			'gd'       => function_exists( 'imagecreatetruecolor' ),
			'freetype' => ! empty( $gd['FreeType Support'] ) && function_exists( 'imagettftext' ),
			'png'      => function_exists( 'imagepng' ),
			'jpeg'     => function_exists( 'imagejpeg' ),
			'webp'     => function_exists( 'imagewebp' ),
			'avif'     => function_exists( 'imageavif' ),
			'filters'  => function_exists( 'imagefilter' ),
			'rotate'   => function_exists( 'imagerotate' ),
			'imagick'  => extension_loaded( 'imagick' ),
		);
	}

	/**
	 * Everything the editor needs to offer only what renders here.
	 *
	 * @return array
	 */
	public static function all() {
		$fonts = array();
		foreach ( Fonts::all() as $id => $font ) {
			if ( ! is_array( $font ) || ! isset( $font[0], $font[1] ) ) {
				continue;
			}

			$fonts[] = array(
				'id'    => $id,
				'label' => $font[0],
				'url'   => self::font_url( $font[1] ),
			);
		}

		return array(
			'canRender'     => aimg_can_render(),
			'features'      => self::features(),
			'layerTypes'    => array_keys( Schema::layer_types() ),
			'outputFormats' => Schema::output_formats(),
			'fonts'         => $fonts,
			'mergeTags'     => MergeTags::names(),
			'imageSources'  => Images::sources(),
			'limits'        => array(
				'maxLayers' => Schema::MAX_LAYERS,
				'maxSize'   => Schema::MAX_SIZE,
			),
		);
	}

	/**
	 * URL of a font file inside the plugin or uploads, so the editor canvas can
	 * load the same file GD draws with; '' when it has no public URL.
	 *
	 * @param string $path Font path.
	 *
	 * @return string
	 */
	private static function font_url( $path ) {
		$path    = wp_normalize_path( $path );
		$plugin  = wp_normalize_path( AIMG_PATH );
		$uploads = wp_upload_dir();
		$basedir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );

		if ( 0 === strpos( $path, $plugin ) ) {
			return AIMG_URL . ltrim( substr( $path, strlen( $plugin ) ), '/' );
		}

		if ( empty( $uploads['error'] ) && 0 === strpos( $path, $basedir ) ) {
			return trailingslashit( $uploads['baseurl'] ) . ltrim( substr( $path, strlen( $basedir ) ), '/' );
		}

		return '';
	}
}
