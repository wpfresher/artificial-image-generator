<?php

namespace ArtificialImageGenerator\Rendering;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Fonts text layers can use: bundled open-licensed fonts and fonts uploaded to
 * the site (`.ttf` / `.otf` in uploads/aimg-fonts/).
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
	 * Option holding uploaded fonts as ID => array( label, file relative to uploads ).
	 *
	 * @var string
	 */
	const OPTION = 'aimg_uploaded_fonts';

	/**
	 * Uploads subfolder for fonts.
	 *
	 * @var string
	 */
	const FOLDER = 'aimg-fonts';

	/**
	 * Largest font upload in bytes.
	 *
	 * @var int
	 */
	const MAX_UPLOAD = 10485760;

	/**
	 * Bundled fonts as ID => array( label, file in assets/fonts, scripts ).
	 *
	 * @return array
	 */
	public static function bundled() {
		$wide   = array( 'latin', 'cyrillic', 'greek', 'vietnamese' );
		$no_gr  = array( 'latin', 'cyrillic', 'vietnamese' );
		$poppin = array( 'latin', 'devanagari' );

		return array(
			'roboto-bold'           => array( 'Roboto Bold', 'Roboto-Bold.ttf', $wide ),
			'roboto-regular'        => array( 'Roboto', 'Roboto-Regular.ttf', $wide ),
			'inter-bold'            => array( 'Inter Bold', 'Inter-Bold.ttf', $wide ),
			'inter-regular'         => array( 'Inter', 'Inter-Regular.ttf', $wide ),
			'montserrat-extrabold'  => array( 'Montserrat ExtraBold', 'Montserrat-ExtraBold.ttf', $no_gr ),
			'montserrat-regular'    => array( 'Montserrat', 'Montserrat-Regular.ttf', $no_gr ),
			'poppins-bold'          => array( 'Poppins Bold', 'Poppins-Bold.ttf', $poppin ),
			'poppins-regular'       => array( 'Poppins', 'Poppins-Regular.ttf', $poppin ),
			'playfair-display-bold' => array( 'Playfair Display Bold', 'PlayfairDisplay-Bold.ttf', $no_gr ),
			'noto-sans-bold'        => array( 'Noto Sans Bold', 'NotoSans-Bold.ttf', $wide ),
			'noto-sans-regular'     => array( 'Noto Sans', 'NotoSans-Regular.ttf', $wide ),
		);
	}

	/**
	 * Uploaded fonts as ID => array( label, absolute path ).
	 *
	 * @return array
	 */
	public static function uploaded() {
		$uploads = wp_upload_dir();
		$fonts   = array();

		if ( ! empty( $uploads['error'] ) ) {
			return $fonts;
		}

		foreach ( (array) get_option( self::OPTION, array() ) as $id => $font ) {
			if ( is_array( $font ) && isset( $font[0], $font[1] ) ) {
				$fonts[ $id ] = array( $font[0], trailingslashit( $uploads['basedir'] ) . ltrim( $font[1], '/' ) );
			}
		}

		return $fonts;
	}

	/**
	 * Fonts as ID => array( label, file path ).
	 *
	 * @return array
	 */
	public static function all() {
		$fonts = array();

		foreach ( self::bundled() as $id => $font ) {
			$fonts[ $id ] = array( $font[0], AIMG_ASSETS_PATH . 'fonts/' . $font[1] );
		}

		$fonts += self::uploaded();

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

	/**
	 * Scripts a bundled font covers, or an empty list when unknown.
	 *
	 * @param string $id Font ID.
	 *
	 * @return string[]
	 */
	public static function scripts( $id ) {
		$bundled = self::bundled();

		return isset( $bundled[ $id ][2] ) ? $bundled[ $id ][2] : array();
	}

	/**
	 * Add an uploaded font file after checking that it is a TrueType or OpenType font GD can draw.
	 *
	 * @param string $tmp_file  Uploaded file path.
	 * @param string $file_name Original file name.
	 *
	 * @return string|\WP_Error New font ID.
	 */
	public static function add_upload( $tmp_file, $file_name ) {
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$size      = is_readable( $tmp_file ) ? (int) filesize( $tmp_file ) : 0;

		if ( ! in_array( $extension, array( 'ttf', 'otf' ), true ) ) {
			return new \WP_Error( 'aimg_font_type', __( 'Upload a .ttf or .otf font file. Web fonts (.woff, .woff2) cannot be used to draw images.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		if ( $size < 12 || $size > self::MAX_UPLOAD ) {
			return new \WP_Error( 'aimg_font_size', __( 'The font file is empty or larger than 10 MB.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$magic = (string) file_get_contents( $tmp_file, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the file signature.

		if ( ! in_array( $magic, array( "\x00\x01\x00\x00", 'true', 'OTTO' ), true ) ) {
			return new \WP_Error( 'aimg_font_invalid', __( 'This file is not a TrueType or OpenType font.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		// FreeType rejects some fonts; finding out now beats a failed render later.
		if ( ! function_exists( 'imagettfbbox' ) || ! @imagettfbbox( 20, 0, $tmp_file, 'Hg' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new \WP_Error( 'aimg_font_unreadable', __( 'This server cannot draw text with this font.', 'artificial-image-generator' ), array( 'status' => 400 ) );
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'aimg_font_uploads', $uploads['error'], array( 'status' => 500 ) );
		}

		$folder = trailingslashit( $uploads['basedir'] ) . self::FOLDER;
		if ( ! wp_mkdir_p( $folder ) ) {
			return new \WP_Error( 'aimg_font_folder', __( 'The fonts folder could not be created.', 'artificial-image-generator' ), array( 'status' => 500 ) );
		}

		$label = sanitize_text_field( pathinfo( $file_name, PATHINFO_FILENAME ) );
		$slug  = sanitize_title( $label );
		$slug  = '' !== $slug ? $slug : 'font';
		$name  = wp_unique_filename( $folder, $slug . '.' . $extension );

		if ( ! @copy( $tmp_file, trailingslashit( $folder ) . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new \WP_Error( 'aimg_font_copy', __( 'The font could not be saved.', 'artificial-image-generator' ), array( 'status' => 500 ) );
		}

		$fonts = (array) get_option( self::OPTION, array() );
		$id    = 'custom-' . sanitize_key( pathinfo( $name, PATHINFO_FILENAME ) );

		$fonts[ $id ] = array( '' !== $label ? $label : $name, self::FOLDER . '/' . $name );
		update_option( self::OPTION, $fonts, false );

		return $id;
	}

	/**
	 * Delete an uploaded font. Layers using it fall back to the default font.
	 *
	 * @param string $id Font ID.
	 *
	 * @return bool Whether it was an uploaded font and is gone now.
	 */
	public static function delete_upload( $id ) {
		$fonts    = (array) get_option( self::OPTION, array() );
		$uploaded = self::uploaded();

		if ( ! isset( $fonts[ $id ], $uploaded[ $id ] ) ) {
			return false;
		}

		$path    = $uploaded[ $id ][1];
		$uploads = wp_upload_dir();
		$folder  = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . self::FOLDER . '/' );

		if ( 0 === strpos( wp_normalize_path( $path ), $folder ) && file_exists( $path ) ) {
			wp_delete_file( $path );
		}

		unset( $fonts[ $id ] );
		update_option( self::OPTION, $fonts, false );

		return true;
	}
}
