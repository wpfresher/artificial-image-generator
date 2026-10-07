<?php
/**
 * The 1.6.0 renderer, frozen as the reference for the golden-image tests.
 *
 * Do not change: the new renderer must produce the same bytes for v1 templates.
 *
 * @package ArtificialImageGenerator
 */

function aimg_legacy_generate_thumbnail( $args = array() ) {
	$default_args = array(
		'template_id' => 0,
		'title'       => '',
		'colors'      => array(),
		'width'       => 1200,
		'height'      => 600,
		'overlays'    => array(),
	);

	$args = wp_parse_args( $args, $default_args );

	// Back compat: `post_id` was the original name for what is always a template ID.
	if ( empty( $args['template_id'] ) && ! empty( $args['post_id'] ) ) {
		$args['template_id'] = $args['post_id'];
	}

	// Extract arguments for easier access.
	$template_id = absint( $args['template_id'] );
	$title       = isset( $args['title'] ) ? $args['title'] : '';
	$colors      = isset( $args['colors'] ) && is_array( $args['colors'] ) ? $args['colors'] : array();
	$width       = isset( $args['width'] ) ? absint( $args['width'] ) : 1200;
	$height      = isset( $args['height'] ) ? absint( $args['height'] ) : 600;
	$overlays    = isset( $args['overlays'] ) && is_array( $args['overlays'] ) ? $args['overlays'] : array();

	if ( empty( $template_id ) || empty( $title ) || empty( $width ) || empty( $height ) ) {
		return false;
	}

	// A template saved before the field was validated can hold an empty font size,
	// which GD rejects, so fall back to the same default the editor offers.
	$font_size = (float) get_post_meta( $template_id, '_aimg_title_font_size', true );
	if ( $font_size <= 0 ) {
		$font_size = AIMG_DEFAULT_FONT_SIZE;
	}

	$font_path = AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf';

	// Missing GD or FreeType would be a fatal error, breaking the post save.
	if ( ! file_exists( $font_path ) || ! aimg_can_render() ) {
		return false;
	}

	wp_raise_memory_limit( 'image' );

	// Create base image.
	$img = imagecreatetruecolor( $width, $height );

	if ( ! $img ) {
		return false;
	}
	imagealphablending( $img, true );
	imagesavealpha( $img, true );

	// Pick random BG color.
	$hex = $colors ? $colors[ array_rand( $colors ) ] : aimg_get_settings( 'default_bg_color', '#008000' );
	if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) {
		$hex = '#008000';
	}

	list( $r, $g, $b ) = sscanf( $hex, '#%02x%02x%02x' );
	$bg_color          = imagecolorallocate( $img, $r, $g, $b );
	imagefill( $img, 0, 0, $bg_color );

	// Overlay each transparent PNG overlay image centered and scaled.
	if ( ! empty( $overlays ) && is_array( $overlays ) ) {
		foreach ( $overlays as $overlay_path ) {
			if ( ! file_exists( $overlay_path ) ) {
				continue;
			}

			// Check the file type and Skip non-PNG images.
			if ( strtolower( pathinfo( $overlay_path, PATHINFO_EXTENSION ) ) !== 'png' ) {
				continue;
			}

			// A corrupt PNG makes imagecreatefrompng() return false and the next GD call throw.
			$info = @getimagesize( $overlay_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! $info || IMAGETYPE_PNG !== $info[2] ) {
				continue;
			}

			$overlay_position = get_post_meta( $template_id, '_aimg_overlay_position', true );
			$overlay          = @imagecreatefrompng( $overlay_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( ! $overlay ) {
				continue;
			}

			imagesavealpha( $overlay, true );

			$overlay_width  = imagesx( $overlay );
			$overlay_height = imagesy( $overlay );

			// Scale overlay to fit within canvas.
			$scale              = min( $width / $overlay_width, $height / $overlay_height );
			$new_overlay_width  = (int) ( $overlay_width * $scale );
			$new_overlay_height = (int) ( $overlay_height * $scale );

			// Resize the overlay image.
			$resized_overlay = imagecreatetruecolor( $new_overlay_width, $new_overlay_height );
			imagealphablending( $resized_overlay, false );
			imagesavealpha( $resized_overlay, true );

			imagecopyresampled(
				$resized_overlay,
				$overlay,
				0,
				0,
				0,
				0,
				$new_overlay_width,
				$new_overlay_height,
				$overlay_width,
				$overlay_height
			);

			// Calculate overlay position based on $overlay_position.
			switch ( $overlay_position ) {
				case 'top-left':
					$overlay_x = 0;
					$overlay_y = 0;
					break;
				case 'top-center':
					$overlay_x = ( $width - $new_overlay_width ) / 2;
					$overlay_y = 0;
					break;
				case 'top-right':
					$overlay_x = $width - $new_overlay_width;
					$overlay_y = 0;
					break;
				case 'left-center':
					$overlay_x = 0;
					$overlay_y = ( $height - $new_overlay_height ) / 2;
					break;
				case 'center-center':
					$overlay_x = ( $width - $new_overlay_width ) / 2;
					$overlay_y = ( $height - $new_overlay_height ) / 2;
					break;
				case 'right-center':
					$overlay_x = $width - $new_overlay_width;
					$overlay_y = ( $height - $new_overlay_height ) / 2;
					break;
				case 'bottom-left':
					$overlay_x = 0;
					$overlay_y = $height - $new_overlay_height;
					break;
				case 'bottom-center':
					$overlay_x = ( $width - $new_overlay_width ) / 2;
					$overlay_y = $height - $new_overlay_height;
					break;
				case 'bottom-right':
				default:
					$overlay_x = $width - $new_overlay_width;
					$overlay_y = $height - $new_overlay_height;
					break;
			}

			// Copy the overlay image to canvas.
			imagecopy(
				$img,
				$resized_overlay,
				(int) $overlay_x,
				(int) $overlay_y,
				0,
				0,
				$new_overlay_width,
				$new_overlay_height
			);

			imagedestroy( $resized_overlay );
			imagedestroy( $overlay );
		}
	}

	// Tint with the background colour at ~70% opacity (GD alpha 38 of 127).
	$overlay_color = imagecolorallocatealpha( $img, $r, $g, $b, absint( 127 * 0.3 ) );
	imagefilledrectangle( $img, 0, 0, $width, $height, $overlay_color );

	// Set text color (white).
	$text_color_hex = aimg_get_settings( 'default_text_color', '#ffffff' );
	if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $text_color_hex ) ) {
		$text_color_hex = '#ffffff';
	}

	list( $red, $green, $blue ) = sscanf( $text_color_hex, '#%02x%02x%02x' );
	$text_color                 = imagecolorallocate( $img, $red, $green, $blue );

	// Auto-wrap long title.
	$wrapped       = aimg_legacy_wrap_title( $title, $font_size, $font_path, $width - 80 );
	$font_size     = $wrapped['font_size'];
	$wrapped_lines = $wrapped['lines'];

	// Calculate total text height.
	$line_height  = $font_size * 1.4;
	$total_height = count( $wrapped_lines ) * $line_height;
	$y            = ( $height - $total_height ) / 2 + $font_size;

	// Draw text lines centered.
	foreach ( $wrapped_lines as $line_text ) {
		$bbox       = imagettfbbox( $font_size, 0, $font_path, $line_text );
		$text_width = $bbox[2] - $bbox[0];
		$x          = ( $width - $text_width ) / 2;

		imagettftext( $img, $font_size, 0, (int) $x, (int) $y, $text_color, $font_path, $line_text );
		$y += $line_height;
	}

	// Save image to uploads directory.
	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		imagedestroy( $img );

		return false;
	}

	$slug = sanitize_title( $title );
	if ( '' === $slug ) {
		$slug = 'aimg-image';
	}

	// Reusing a name would overwrite the file an existing attachment points at,
	// so let WordPress suffix it until it is unique within the upload folder.
	$filename = wp_unique_filename( $upload_dir['path'], $slug . '-' . $template_id . '.png' );
	$filepath = aimg_uploads_path( trailingslashit( $upload_dir['path'] ) . $filename );

	$saved = imagepng( $img, $filepath );
	imagedestroy( $img );

	if ( ! $saved ) {
		return false;
	}

	return $filepath;
}

function aimg_legacy_wrap_title( $title, $font_size, $font_path, $max_width ) {
	$measure = function ( $text, $size ) use ( $font_path ) {
		$bbox = imagettfbbox( $size, 0, $font_path, $text );

		return $bbox ? $bbox[2] - $bbox[0] : 0;
	};

	$words    = preg_split( '/\s+/u', trim( $title ) );
	$min_size = min( $font_size, 12 );

	$widest = 0;
	foreach ( $words as $word ) {
		$widest = max( $widest, $measure( $word, $font_size ) );
	}

	if ( $widest > $max_width ) {
		$font_size = max( $min_size, floor( $font_size * $max_width / $widest ) );
	}

	$pieces = array();
	foreach ( $words as $word ) {
		if ( $measure( $word, $font_size ) <= $max_width ) {
			$pieces[] = $word;
			continue;
		}

		$chunk = '';
		foreach ( preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
			if ( '' !== $chunk && $measure( $chunk . $char, $font_size ) > $max_width ) {
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

		if ( '' !== $line && $measure( $new_line, $font_size ) > $max_width ) {
			$lines[] = $line;
			$line    = $piece;
		} else {
			$line = $new_line;
		}
	}

	if ( '' !== $line ) {
		$lines[] = $line;
	}

	return array(
		'font_size' => $font_size,
		'lines'     => $lines,
	);
}
