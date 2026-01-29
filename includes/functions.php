<?php

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Get template post object.
 *
 * @param mixed $data The data.
 *
 * @since 1.0.0
 * @return WP_Post|false The template object, or false if not found.
 */
function aimg_get_template( $data ) {

	if ( is_numeric( $data ) ) {
		$data = get_post( $data );
	}

	if ( $data instanceof WP_Post && 'aimg_template' === $data->post_type ) {
		return $data;
	}

	return false;
}

/**
 * Get templates.
 *
 * @param array $args The args.
 * @param bool  $count Whether to return a count.
 *
 * @since 1.0.0
 * @return array|int The templates.
 */
function aimg_get_templates( $args = array(), $count = false ) {
	$defaults = array(
		'post_type'      => 'aimg_template',
		'posts_per_page' => - 1,
		'orderby'        => 'date',
		'order'          => 'ASC',
	);

	$args  = wp_parse_args( $args, $defaults );
	$query = new WP_Query( $args );

	if ( $count ) {
		return $query->found_posts;
	}

	return array_map( 'aimg_get_template', $query->posts );
}

/**
 * Get settings option.
 *
 * @param string $option Option name.
 * @param mixed  $default_value Default value.
 *
 * @since 1.0.0
 * @retun mixed|null
 */
function aimg_get_settings( $option, $default_value = null ) {
	$options = get_option( 'aimg_settings', array() );

	return isset( $options[ $option ] ) ? $options[ $option ] : $default_value;
}

/**
 * Generate a thumbnail image for preview on settings page.
 *
 * @param string $title    The title text.
 * @param string $colors   Comma separated hex colors.
 * @param int    $width    Image width.
 * @param int    $height   Image height.
 * @param array  $overlays Array of attachment IDs for overlays.
 * @param int    $post_id  Post ID for unique naming.
 *
 * @return string|false Image URL or false on failure.
 */
function aimg_generate_preview( $title, $colors, $width, $height, $overlays = array(), $post_id = 0 ) {

	if ( is_string( $colors ) ) {
		$colors = array_filter( array_map( 'trim', explode( ',', $colors ) ) );
	}

	// Get absolute paths of overlay images.
	$overlays_path = array();
	foreach ( $overlays as $id ) {
		$path = get_attached_file( $id );
		if ( $path && file_exists( $path ) ) {
			$overlays_path[] = $path;
		}
	}

	// Keep only single overlay if multiple are provided.
	if ( count( $overlays_path ) > 1 ) {
		$overlays_path = array( $overlays_path[ array_rand( $overlays_path ) ] );
	}

	// Generate image.
	$filepath = aimg_generate_thumbnail(
		array(
			'title'    => $title,
			'colors'   => $colors,
			'width'    => $width,
			'height'   => $height,
			'overlays' => $overlays_path,
			'post_id'  => $post_id,
			'preview'  => true,
		)
	);

	if ( ! $filepath || ! file_exists( $filepath ) ) {
		return false;
	}

	// Get URL from filepath.
	$upload_dir = wp_upload_dir();
	$basedir    = trailingslashit( $upload_dir['basedir'] );
	$baseurl    = trailingslashit( $upload_dir['baseurl'] );

	if ( strpos( $filepath, $basedir ) === 0 ) {
		return $baseurl . ltrim( substr( $filepath, strlen( $basedir ) ), '/' );
	}

	return false;
}

/**
 * Generate thumbnail image
 *
 * This function creates a thumbnail image based on the provided arguments.
 *
 * @param array $args Array of arguments.
 *
 * @return string|false File path or false on failure.
 */
function aimg_generate_thumbnail( $args = array() ) {
	$default_args = array(
		'title'    => '',
		'colors'   => array(),
		'width'    => 1200,
		'height'   => 600,
		'overlays' => array(),
		'post_id'  => 0,
		'preview'  => false,
	);

	$args = wp_parse_args( $args, $default_args );

	// Extract arguments for easier access.
	$title    = isset( $args['title'] ) ? $args['title'] : '';
	$colors   = isset( $args['colors'] ) && is_array( $args['colors'] ) ? $args['colors'] : array();
	$width    = isset( $args['width'] ) ? absint( $args['width'] ) : 1200;
	$height   = isset( $args['height'] ) ? absint( $args['height'] ) : 600;
	$overlays = isset( $args['overlays'] ) && is_array( $args['overlays'] ) ? $args['overlays'] : array();
	$preview  = isset( $args['preview'] ) ? (bool) $args['preview'] : false;

	$font_size = get_option( 'aimg_title_font_size', 40 );
	$font_path = AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf';

	if ( ! file_exists( $font_path ) ) {
		return false;
	}

	// Create base image.
	$img = imagecreatetruecolor( $width, $height );
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

			$overlay_position = get_option( 'aimg_overlay_position', 'center-center' );
			$overlay          = imagecreatefrompng( $overlay_path );

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

	// Add semi-transparent overlay (0.7 alpha).
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
	$wrapped_lines = array();
	$words         = explode( ' ', $title );
	$line          = '';

	foreach ( $words as $word ) {
		$new_line   = $line ? $line . ' ' . $word : $word;
		$bbox       = imagettfbbox( $font_size, 0, $font_path, $new_line );
		$text_width = $bbox[2] - $bbox[0];

		if ( $text_width > ( $width - 80 ) ) {
			if ( $line ) {
				$wrapped_lines[] = $line;
			}
			$line = $word;
		} else {
			$line = $new_line;
		}
	}

	if ( $line ) {
		$wrapped_lines[] = $line;
	}

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

	// Generate filename.
	if ( $preview ) {
		$filename = 'aimg-preview-thumbnail-image.png';
	} else {
		$suffix   = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : wp_rand( 10000, 99999 );
		$filename = sanitize_title( $title ) . '-' . $suffix . '.png';
	}

	// Save image to uploads directory.
	$upload_dir = wp_upload_dir();
	$filepath   = trailingslashit( $upload_dir['path'] ) . $filename;

	imagepng( $img, $filepath );
	imagedestroy( $img );

	return $filepath;
}
