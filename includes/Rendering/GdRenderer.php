<?php

namespace ArtificialImageGenerator\Rendering;

use ArtificialImageGenerator\Templates\Schema;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Renders template documents with GD.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class GdRenderer {

	/**
	 * Draw a document.
	 *
	 * @param array $document Document; sanitized, or built internally.
	 * @param array $tags     Merge tag values as tag => value, e.g. array( 'title' => 'Hello' ).
	 *
	 * @return \GdImage|resource|false
	 */
	public static function render( $document, $tags = array() ) {
		if ( ! aimg_can_render() ) {
			return false;
		}

		$width  = (int) $document['canvas']['width'];
		$height = (int) $document['canvas']['height'];

		if ( $width < 1 || $height < 1 ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );

		$image = imagecreatetruecolor( $width, $height );

		if ( ! $image ) {
			return false;
		}

		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		$canvas = new Canvas( $image, $width, $height );

		foreach ( $tags as $tag => $value ) {
			$canvas->tags[ '{' . $tag . '}' ] = (string) $value;
		}

		$background = Canvas::rgb( $document['canvas']['background'] );
		if ( $background ) {
			$canvas->background = $background;
			imagefilledrectangle( $image, 0, 0, $width - 1, $height - 1, imagecolorallocate( $image, $background[0], $background[1], $background[2] ) );
		}

		$types = Schema::layer_types();

		foreach ( $document['layers'] as $layer ) {
			if ( empty( $layer['visible'] ) || ! isset( $types[ $layer['type'] ] ) ) {
				continue;
			}

			call_user_func( array( $types[ $layer['type'] ], 'draw' ), $canvas, $layer );
		}

		return $image;
	}

	/**
	 * Write a rendered image into the current uploads folder under a unique name.
	 *
	 * @param \GdImage|resource $image    Image; destroyed afterwards.
	 * @param array             $document Document, for the output format.
	 * @param string            $basename File name without extension.
	 *
	 * @return string|false Path, or false on failure.
	 */
	public static function save( $image, $document, $basename ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			imagedestroy( $image );

			return false;
		}

		$format    = isset( $document['output']['format'] ) ? $document['output']['format'] : 'png';
		$quality   = isset( $document['output']['quality'] ) ? (int) $document['output']['quality'] : 85;
		$extension = 'jpeg' === $format ? 'jpg' : $format;

		if ( ! isset( Schema::output_formats()[ $format ] ) ) {
			$format    = 'png';
			$extension = 'png';
		}

		// Reusing a name would overwrite the file an existing attachment points at.
		$filename = wp_unique_filename( $upload_dir['path'], $basename . '.' . $extension );
		$filepath = aimg_uploads_path( trailingslashit( $upload_dir['path'] ) . $filename );

		if ( 'jpeg' === $format ) {
			$saved = imagejpeg( $image, $filepath, $quality );
		} elseif ( 'webp' === $format ) {
			$saved = imagewebp( $image, $filepath, $quality );
		} else {
			$saved = imagepng( $image, $filepath );
		}

		imagedestroy( $image );

		return $saved ? $filepath : false;
	}
}
