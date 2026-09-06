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
 * @return mixed|null
 */
function aimg_get_settings( $option, $default_value = null ) {
	$options = get_option( 'aimg_settings', array() );

	return isset( $options[ $option ] ) ? $options[ $option ] : $default_value;
}

/**
 * Build the data passed to the generator JS (block editor + media library).
 *
 * Exposes the REST endpoints, a REST nonce, the settings link, whether an API
 * key is configured, and the Media Library URL used for post-generation
 * redirects.
 *
 * @since  1.4.0
 * @return array
 */
function aimg_get_js_data() {
	$has_api_key = ( defined( 'AIMG_API_KEY' ) && AIMG_API_KEY )
		|| ! empty( aimg_get_settings( 'api_key', '' ) );

	return array(
		'endpoints' => array(
			'generate'  => rest_url( 'aimg/v1/generate' ),
			'templates' => rest_url( 'aimg/v1/templates' ),
		),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'uploadUrl'  => admin_url( 'upload.php' ),
		'settings'   => array(
			'hasApiKey'   => (bool) $has_api_key,
			'settingsUrl' => admin_url( 'admin.php?page=aimg-settings' ),
		),
	);
}

/**
 * Generate a thumbnail image for preview on settings page.
 *
 * @param int    $post_id  Post ID for which the preview is being generated.
 * @param string $colors   Comma separated hex colors.
 * @param int    $width    Image width.
 * @param int    $height   Image height.
 * @param array  $overlays Array of attachment IDs for overlays.
 *
 * @return string|false Image URL or false on failure.
 */
function aimg_generate_preview( $post_id, $colors, $width, $height, $overlays = array() ) {
	if ( empty( $post_id ) || empty( $colors ) || empty( $width ) || empty( $height ) ) {
		return false;
	}

	if ( is_string( $colors ) ) {
		$colors = array_filter( array_map( 'trim', explode( ',', $colors ) ) );
	}

	// Get absolute paths of overlay images.
	$overlays_path = array();
	$overlays      = is_array( $overlays ) ? $overlays : array();
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
			'template_id' => $post_id,
			'title'       => get_the_title( $post_id ),
			'colors'      => $colors,
			'width'       => $width,
			'height'      => $height,
			'overlays'    => $overlays_path,
		)
	);

	if ( ! $filepath || ! file_exists( $filepath ) ) {
		return false;
	}

	// Get URL from filepath. Compare on normalized copies so a Windows upload path, which mixes separators, still matches.
	$upload_dir = wp_upload_dir();
	$basedir    = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
	$normalized = wp_normalize_path( $filepath );
	$baseurl    = trailingslashit( $upload_dir['baseurl'] );

	if ( strpos( $normalized, $basedir ) !== 0 ) {
		return false;
	}

	$url = $baseurl . ltrim( substr( $normalized, strlen( $basedir ) ), '/' );

	// Previews now get a unique file name, so drop the one this replaces.
	$previous = get_post_meta( $post_id, '_aimg_preview_image_url', true );
	if ( $previous && $previous !== $url ) {
		aimg_delete_upload_by_url( $previous );
	}

	return $url;
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

			$overlay_position = get_post_meta( $template_id, '_aimg_overlay_position', true );
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

/**
 * Normalize a checkbox setting to 'yes' or 'no'.
 *
 * Checkbox settings are stored as 'yes'/'no' but arrive from the settings form
 * as '1'/absent. Testing presence alone would turn a stored 'no' into 'yes' the
 * moment the option is saved back programmatically, so the value itself decides.
 *
 * @param mixed $value Raw value.
 *
 * @since 1.5.0
 * @return string 'yes' or 'no'.
 */
function aimg_sanitize_checkbox( $value ) {
	if ( is_string( $value ) && in_array( strtolower( trim( $value ) ), array( 'no', 'false', 'off', '0', '' ), true ) ) {
		return 'no';
	}

	return empty( $value ) ? 'no' : 'yes';
}

/**
 * Rewrite a path inside the uploads directory to match the separator style
 * WordPress itself uses.
 *
 * `wp_upload_dir()` derives its paths from ABSPATH, which on Windows mixes
 * separators (`C:\Sites\site/wp-content/uploads`). `_wp_relative_upload_path()`
 * detects a file as living inside uploads with a plain `strpos()` against that
 * value, so handing it a `wp_normalize_path()`ed path makes the check fail and
 * WordPress stores an absolute path in `_wp_attached_file`, which then never
 * resolves. Paths outside the uploads directory are returned untouched.
 *
 * @param string $path Absolute path to a file inside the uploads directory.
 *
 * @since 1.5.0
 * @return string
 */
function aimg_uploads_path( $path ) {
	if ( empty( $path ) || ! is_string( $path ) ) {
		return $path;
	}

	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		return $path;
	}

	$basedir  = trailingslashit( $upload_dir['basedir'] );
	$compare  = wp_normalize_path( $basedir );
	$normal   = wp_normalize_path( $path );

	if ( 0 !== strpos( $normal, $compare ) ) {
		return $path;
	}

	return $basedir . ltrim( substr( $normal, strlen( $compare ) ), '/' );
}

/**
 * Delete a file inside the uploads directory given its URL.
 *
 * Used to clear the previous template preview when a new one is rendered, so
 * repeated template saves do not litter the uploads folder. Paths outside the
 * uploads directory are ignored.
 *
 * @param string $url URL of a file inside the uploads directory.
 *
 * @since 1.5.0
 * @return bool True when a file was deleted.
 */
function aimg_delete_upload_by_url( $url ) {
	if ( empty( $url ) || ! is_string( $url ) ) {
		return false;
	}

	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		return false;
	}

	$basedir = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
	$baseurl = trailingslashit( $upload_dir['baseurl'] );

	if ( 0 !== strpos( $url, $baseurl ) ) {
		return false;
	}

	$relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
	$path     = wp_normalize_path( $basedir . $relative );

	// Refuse anything that climbed back out of the uploads directory.
	if ( 0 !== strpos( $path, $basedir ) || false !== strpos( $relative, '..' ) ) {
		return false;
	}

	if ( ! file_exists( $path ) ) {
		return false;
	}

	wp_delete_file( $path );

	return true;
}
