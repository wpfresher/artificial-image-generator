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
	$provider = \ArtificialImageGenerator\Providers\Registry::get();
	$model    = (string) aimg_get_settings( 'api_model', '' );
	$method   = \ArtificialImageGenerator\Generator::get_method();
	$methods  = \ArtificialImageGenerator\Generator::get_methods();
	$runnable = \ArtificialImageGenerator\Generator::get_runnable_method( $method );

	return array(
		'endpoints' => array(
			'generate'  => rest_url( 'aimg/v1/generate' ),
			'templates' => rest_url( 'aimg/v1/templates' ),
			'prompt'    => rest_url( 'aimg/v1/prompt' ),
			'status'    => rest_url( 'aimg/v1/status/' ),
			'featured'  => rest_url( 'aimg/v1/featured/' ),
			'media'     => rest_url( 'wp/v2/media/' ),
		),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'uploadUrl' => admin_url( 'upload.php' ),
		'settings'  => array(
			'hasApiKey'   => $provider && $provider->is_configured(),
			'canUseAi'    => aimg_user_can_use_ai(),
			'canUpload'   => current_user_can( 'upload_files' ),
			'settingsUrl' => admin_url( 'admin.php?page=aimg-settings' ),
			'size'        => (string) aimg_get_settings( 'ai_size', 'square' ),
			'quality'     => (string) aimg_get_settings( 'ai_quality', 'auto' ),
			'maxImages'   => $provider ? min( 4, $provider->get_max_images( $model ) ) : 1,
			'method'      => $method,
			'methodLabel' => isset( $methods[ $method ] ) ? $methods[ $method ] : '',
			'methodIsAi'  => '' === $runnable || \ArtificialImageGenerator\Generator::method_starts_with_ai( $runnable ),
		),
		'options'   => array(
			'sizes'     => \ArtificialImageGenerator\Admin\Settings::get_sizes(),
			'qualities' => \ArtificialImageGenerator\Admin\Settings::get_qualities(),
			'styles'    => wp_list_pluck( \ArtificialImageGenerator\PromptBuilder::get_styles(), 0 ),
		),
	);
}

/**
 * URL of a file inside the uploads directory.
 *
 * Paths are compared on normalized copies, so a Windows upload path, which mixes
 * separators, still matches.
 *
 * @param string $path Absolute path.
 *
 * @since 1.7.0
 * @return string URL, or '' when the file is outside uploads.
 */
function aimg_upload_url( $path ) {
	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		return '';
	}

	$basedir    = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
	$normalized = wp_normalize_path( $path );

	if ( 0 !== strpos( $normalized, $basedir ) ) {
		return '';
	}

	return trailingslashit( $upload_dir['baseurl'] ) . ltrim( substr( $normalized, strlen( $basedir ) ), '/' );
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

	// Missing GD or FreeType would be a fatal error, breaking the post save.
	if ( ! file_exists( AIMG_ASSETS_PATH . 'fonts/Roboto-Bold.ttf' ) || ! aimg_can_render() ) {
		return false;
	}

	$args['template_id'] = $template_id;
	$args['colors']      = $colors;
	$args['width']       = $width;
	$args['height']      = $height;
	$args['overlays']    = $overlays;

	$document = \ArtificialImageGenerator\Templates\Migration::from_render_args( $args );
	$image    = \ArtificialImageGenerator\Rendering\GdRenderer::render( $document, array( 'title' => $title ) );

	if ( ! $image ) {
		return false;
	}

	$slug = sanitize_title( $title );
	if ( '' === $slug ) {
		$slug = 'aimg-image';
	}

	return \ArtificialImageGenerator\Rendering\GdRenderer::save( $image, $document, $slug . '-' . $template_id );
}

/**
 * A post title as plain text, for drawing onto an image and for alt text.
 *
 * @param int $post_id Post ID.
 *
 * @since 1.5.4
 * @return string
 */
function aimg_get_plain_title( $post_id ) {
	return aimg_plain_text( get_post_field( 'post_title', $post_id ) );
}

/**
 * Text without HTML entities or tags, for drawing onto an image and for alt text.
 *
 * @param string $text Text, possibly entity-encoded.
 *
 * @since 1.6.0
 * @return string
 */
function aimg_plain_text( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return trim( wp_strip_all_tags( $text ) );
}

/**
 * Roles allowed to generate AI images, mapped to the capability that gates them.
 *
 * @since 1.5.4
 * @return array
 */
function aimg_get_ai_access_levels() {
	return array(
		'authors' => 'upload_files',
		'editors' => 'edit_others_posts',
		'admins'  => 'manage_options',
	);
}

/**
 * Whether a user may generate images from an AI prompt (which spends the site's API credit).
 *
 * @param int $user_id User ID. Defaults to the current user.
 *
 * @since 1.5.4
 * @return bool
 */
function aimg_user_can_use_ai( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$levels  = aimg_get_ai_access_levels();
	$access  = aimg_get_settings( 'ai_access', 'authors' );
	$cap     = isset( $levels[ $access ] ) ? $levels[ $access ] : $levels['authors'];

	/**
	 * Filter whether a user may generate images from an AI prompt.
	 *
	 * @param bool $allowed Whether the user may generate.
	 * @param int  $user_id User ID.
	 *
	 * @since 1.5.4
	 */
	return (bool) apply_filters( 'aimg_can_generate_from_prompt', user_can( $user_id, $cap ), $user_id );
}

/**
 * Count AI images against the user's hourly limit.
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @param int $count   Number of images. Default 1.
 *
 * @since 1.5.4
 * @since 1.6.0 Added `$count`.
 * @return true|WP_Error True when allowed, an error once the limit is reached.
 */
function aimg_consume_ai_quota( $user_id = 0, $count = 1 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$count   = max( 1, (int) $count );

	/**
	 * Filter how many AI images a user may generate per hour. 0 means no limit.
	 *
	 * @param int $limit   Hourly limit from the settings.
	 * @param int $user_id User ID.
	 *
	 * @since 1.5.4
	 */
	$limit = (int) apply_filters( 'aimg_ai_hourly_limit', absint( aimg_get_settings( 'ai_hourly_limit', 20 ) ), $user_id );

	if ( $limit <= 0 ) {
		return true;
	}

	$key   = 'aimg_ai_usage_' . $user_id;
	$usage = get_transient( $key );
	$now   = time();

	if ( ! is_array( $usage ) || empty( $usage['start'] ) || $now - (int) $usage['start'] >= HOUR_IN_SECONDS ) {
		$usage = array(
			'start' => $now,
			'count' => 0,
		);
	}

	if ( (int) $usage['count'] + $count > $limit ) {
		$minutes   = max( 1, (int) ceil( ( (int) $usage['start'] + HOUR_IN_SECONDS - $now ) / MINUTE_IN_SECONDS ) );
		$remaining = $limit - (int) $usage['count'];

		if ( $remaining > 0 ) {
			return new WP_Error(
				'aimg_rate_limited',
				sprintf(
					/* translators: %d: number of AI images the user can still generate this hour */
					_n(
						'You can generate %d more AI image this hour. Request fewer images.',
						'You can generate %d more AI images this hour. Request fewer images.',
						$remaining,
						'artificial-image-generator'
					),
					$remaining
				),
				array( 'status' => 429 )
			);
		}

		return new WP_Error(
			'aimg_rate_limited',
			sprintf(
				/* translators: 1: hourly limit, 2: minutes until the limit resets */
				_n(
					'You have reached the limit of %1$d AI image per hour. Try again in %2$d minutes.',
					'You have reached the limit of %1$d AI images per hour. Try again in %2$d minutes.',
					$limit,
					'artificial-image-generator'
				),
				$limit,
				$minutes
			),
			array( 'status' => 429 )
		);
	}

	$usage['count'] += $count;
	set_transient( $key, $usage, HOUR_IN_SECONDS );

	return true;
}

/**
 * Overlay positions a template can use.
 *
 * @since 1.5.4
 * @return string[]
 */
function aimg_get_overlay_positions() {
	return array( 'top-left', 'top-center', 'top-right', 'left-center', 'center-center', 'right-center', 'bottom-left', 'bottom-center', 'bottom-right' );
}

/**
 * Whether the server can render template images: GD with FreeType support.
 *
 * @since 1.5.4
 * @return bool
 */
function aimg_can_render() {
	return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettfbbox' ) && function_exists( 'imagepng' );
}

/**
 * Split a title into lines that fit a width, shrinking the font when a single
 * word is wider than the line.
 *
 * @param string $title     Title text.
 * @param float  $font_size Requested font size.
 * @param string $font_path TrueType font path.
 * @param int    $max_width Maximum line width in pixels.
 *
 * @since 1.5.4
 * @return array { @type float $font_size, @type string[] $lines }
 */
function aimg_wrap_title( $title, $font_size, $font_path, $max_width ) {
	return \ArtificialImageGenerator\Rendering\TextLayout::wrap( $title, $font_size, $font_path, $max_width );
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

	$basedir = trailingslashit( $upload_dir['basedir'] );
	$compare = wp_normalize_path( $basedir );
	$normal  = wp_normalize_path( $path );

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
