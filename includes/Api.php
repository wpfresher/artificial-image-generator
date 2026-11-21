<?php
/**
 * Integration API.
 *
 * @package ArtificialImageGenerator
 */

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The stable interface other plugins use to make images.
 *
 * This is the contract for integrations such as the Sokket MCP connector and
 * AI Content Writer. The rest of the plugin is internal and may change between
 * releases; this class changes only together with AIMG_API_VERSION (Api::VERSION).
 *
 * Guarantee: templates(), summary(), render() and import() make no outbound HTTP
 * request. Templates are drawn locally with GD, and templates with an `ai` or
 * `stock` layer are refused rather than drawn with a placeholder. sources() is
 * offline too. Only generate() reaches the AI and stock-photo services.
 *
 * Callers do their own capability checks. The procedural wrappers in
 * functions.php (`aimg_render_template()` and friends) call this class.
 *
 * @since 1.8.5
 */
final class Api {

	/**
	 * Contract version. Raise it only when a method or return shape changes.
	 */
	const VERSION = 1;

	/**
	 * Most merge-tag values accepted in one render.
	 */
	const MAX_TAGS = 50;

	/**
	 * Longest merge-tag value accepted, in characters.
	 */
	const MAX_TAG_LENGTH = 500;

	/**
	 * Summaries of every published template, ordered by title.
	 *
	 * @since 1.8.5
	 * @return array[] See summary().
	 */
	public static function templates() {
		$templates = aimg_get_templates(
			array(
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$summaries = array();

		foreach ( (array) $templates as $template ) {
			if ( $template ) {
				$summaries[] = self::summary( $template->ID );
			}
		}

		return $summaries;
	}

	/**
	 * What a template draws and what it needs, without rendering it.
	 *
	 * @param int $template_id Template post ID.
	 *
	 * @since 1.8.5
	 * @return array|\WP_Error {
	 *     @type int      $id            Template ID.
	 *     @type string   $title         Template title, plain text.
	 *     @type string   $status        Post status; only `publish` templates render.
	 *     @type int      $width         Canvas width in pixels.
	 *     @type int      $height        Canvas height in pixels.
	 *     @type string   $aspect        `landscape`, `portrait` or `square`.
	 *     @type string   $format        Output format: png, jpeg or webp.
	 *     @type bool     $is_default    Whether it is the default template in Settings.
	 *     @type bool     $remote_layers Whether a visible layer uses a stock or AI image. Such templates do not render here.
	 *     @type bool     $has_document  Whether it was saved in the Template Studio (v2); 1.x templates only use `{title}`.
	 *     @type string[] $merge_tags    Tags used by visible text layers and `showIf` conditions, e.g. `title`, `custom_field:price`.
	 *     @type string[] $layer_types   Types of the visible layers, in drawing order.
	 *     @type array[]  $text_layers   Visible text layers: `id`, `name`, `content`, `box` (x, y, w, h), `max_lines` (0 = no limit), `font_size` (max), `show_if`.
	 *     @type string   $preview_url   URL of the list preview, or ''.
	 * }
	 */
	public static function summary( $template_id ) {
		$template = aimg_get_template( absint( $template_id ) );

		if ( ! $template ) {
			return new \WP_Error( 'aimg_invalid_template', __( 'Image template not found.', 'artificial-image-generator' ) );
		}

		$document = Templates\Repository::get_document( $template->ID );
		$width    = (int) $document['canvas']['width'];
		$height   = (int) $document['canvas']['height'];
		$ratio    = $height > 0 ? $width / $height : 1;
		$tags     = array();
		$types    = array();
		$texts    = array();

		foreach ( $document['layers'] as $layer ) {
			if ( empty( $layer['visible'] ) ) {
				continue;
			}

			$types[] = $layer['type'];

			if ( '' !== $layer['showIf'] ) {
				$tags[] = $layer['showIf'];
			}

			if ( 'text' !== $layer['type'] || ! isset( $layer['content'] ) ) {
				continue;
			}

			$tags = array_merge( $tags, self::tags_in( $layer['content'] ) );

			$texts[] = array(
				'id'        => $layer['id'],
				'name'      => $layer['name'],
				'content'   => $layer['content'],
				'box'       => isset( $layer['box'] ) ? $layer['box'] : array(),
				'max_lines' => isset( $layer['maxLines'] ) ? (int) $layer['maxLines'] : 0,
				'font_size' => isset( $layer['size']['max'] ) ? (float) $layer['size']['max'] : 0,
				'show_if'   => $layer['showIf'],
			);
		}

		if ( $ratio > 1.05 ) {
			$aspect = 'landscape';
		} elseif ( $ratio < 0.95 ) {
			$aspect = 'portrait';
		} else {
			$aspect = 'square';
		}

		$preview = (string) get_post_meta( $template->ID, '_aimg_preview_image_url', true );

		return array(
			'id'            => (int) $template->ID,
			'title'         => aimg_plain_text( $template->post_title ),
			'status'        => $template->post_status,
			'width'         => $width,
			'height'        => $height,
			'aspect'        => $aspect,
			'format'        => $document['output']['format'],
			'is_default'    => absint( aimg_get_settings( 'default_template_id', 0 ) ) === (int) $template->ID,
			'remote_layers' => Rendering\Hybrid::document_is_remote( $document ),
			'has_document'  => Templates\Repository::has_document( $template->ID ),
			'merge_tags'    => array_values( array_unique( $tags ) ),
			'layer_types'   => $types,
			'text_layers'   => $texts,
			'preview_url'   => '' !== $preview ? esc_url_raw( $preview ) : '',
		);
	}

	/**
	 * Render a template to a file in the uploads directory.
	 *
	 * The file is not added to the Media Library; pass the path to
	 * import() for that. Nothing is fetched from the network.
	 *
	 * @param int   $template_id Template post ID, or 0 for the template the plugin would pick for the post (default, else random).
	 * @param array $args        {
	 *     Optional. Render arguments.
	 *
	 *     @type int   $post_id Post the image is for, or 0. Supplies values for the tags not given.
	 *     @type array $tags    Merge-tag values as name => text, e.g. array( 'title' => 'Step 1', 'custom_field:price' => '$9' ).
	 *                          With no `title`, the post title is used, then the template title.
	 * }
	 *
	 * @since 1.8.5
	 * @return array|\WP_Error {
	 *     @type string $path        Absolute path of the rendered file.
	 *     @type int    $template_id Template that was rendered.
	 * }
	 */
	public static function render( $template_id, $args = array() ) {
		$args    = is_array( $args ) ? $args : array();
		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;

		if ( ! aimg_can_render() ) {
			return new \WP_Error( 'aimg_cannot_render', __( 'This server cannot render images: the GD extension with FreeType support is required.', 'artificial-image-generator' ) );
		}

		if ( $post_id && ! get_post( $post_id ) ) {
			return new \WP_Error( 'aimg_invalid_post', __( 'The post the image is for does not exist.', 'artificial-image-generator' ) );
		}

		$tags = self::sanitize_tags( isset( $args['tags'] ) ? $args['tags'] : array() );

		if ( is_wp_error( $tags ) ) {
			return $tags;
		}

		$template_id = absint( $template_id );

		if ( ! $template_id ) {
			$template_id = Generator::get_template_id_for_post( $post_id );

			if ( ! $template_id ) {
				return new \WP_Error( 'aimg_no_template', __( 'There is no published image template to use.', 'artificial-image-generator' ) );
			}
		}

		$template = aimg_get_template( $template_id );

		if ( ! $template || 'publish' !== $template->post_status ) {
			return new \WP_Error( 'aimg_invalid_template', __( 'Image template not found or not published.', 'artificial-image-generator' ) );
		}

		if ( Rendering\Hybrid::document_is_remote( Templates\Repository::get_document( $template_id ) ) ) {
			return new \WP_Error( 'aimg_remote_layers', __( 'This template uses a stock photo or AI image layer, which needs an online service. Use a template without those layers.', 'artificial-image-generator' ) );
		}

		if ( ! isset( $tags['title'] ) && $post_id ) {
			$tags['title'] = aimg_get_plain_title( $post_id );
		}

		$path = Generator::render_template( $template_id, $tags, $post_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return new \WP_Error( 'aimg_generation_failed', __( 'Failed to generate image from template.', 'artificial-image-generator' ) );
		}

		return array(
			'path'        => $path,
			'template_id' => $template_id,
		);
	}

	/**
	 * Add a rendered file to the Media Library, stamped as a generated image.
	 *
	 * @param string $path Absolute path returned by render().
	 * @param array  $args {
	 *     Optional. Attachment arguments.
	 *
	 *     @type string $title       Attachment title. Defaults to the file name.
	 *     @type string $alt         Alt text. Defaults to the title.
	 *     @type string $caption     Caption. Default ''.
	 *     @type int    $parent      Parent post ID. Default 0.
	 *     @type int    $template_id Template that was rendered, for the provenance record.
	 *     @type string $source      Who asked for the image, for the provenance record. Default 'template'.
	 * }
	 *
	 * @since 1.8.5
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function import( $path, $args = array() ) {
		$args = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'title'       => '',
				'alt'         => '',
				'caption'     => '',
				'parent'      => 0,
				'template_id' => 0,
				'source'      => 'template',
			)
		);

		// Only files this plugin rendered: a path inside uploads, never one a caller made up.
		$uploads = wp_upload_dir();
		$real    = $path ? realpath( $path ) : false;
		$base    = realpath( $uploads['basedir'] );

		if ( ! $real || ! $base || 0 !== strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $base ) ) ) ) {
			return new \WP_Error( 'aimg_missing_file', __( 'The generated image could not be found in the uploads directory.', 'artificial-image-generator' ) );
		}

		$attachment_id = Generator::create_attachment(
			$path,
			array(
				'title'      => aimg_plain_text( $args['title'] ),
				'alt'        => aimg_plain_text( $args['alt'] ),
				'parent'     => absint( $args['parent'] ),
				'provenance' => array(
					'source'      => sanitize_key( $args['source'] ),
					'template_id' => absint( $args['template_id'] ),
				),
			)
		);

		if ( ! is_wp_error( $attachment_id ) && '' !== trim( (string) $args['caption'] ) ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => sanitize_textarea_field( (string) $args['caption'] ),
				)
			);
		}

		return $attachment_id;
	}

	/**
	 * Which image methods are ready, from the settings and saved keys. Makes no request.
	 *
	 * @since 1.9.0
	 * @return array {
	 *     @type bool   $ai       An AI provider has a key.
	 *     @type bool   $stock    A stock photo service has a key.
	 *     @type bool   $template A published template exists and the server can render it.
	 *     @type string $method   The configured method for featured images, e.g. `template_ai`.
	 *     @type string $label    That method's label.
	 * }
	 */
	public static function sources() {
		$provider = Providers\Registry::get();
		$method   = Generator::get_method();

		return array(
			'ai'       => $provider && $provider->is_configured(),
			'stock'    => (bool) Stock\Registry::get_default(),
			'template' => aimg_can_render() && (bool) Generator::get_random_template_id(),
			'method'   => $method,
			'label'    => Generator::get_methods()[ $method ],
		);
	}

	/**
	 * Make one image for a post and add it to the Media Library, using the configured method.
	 *
	 * May call the AI provider or a stock photo service, so it can take up to a few minutes.
	 * AI images count against the post author's hourly AI limit. In-content images skip
	 * templates, which are designed for featured images. Does not set the featured image.
	 *
	 * @param array $args {
	 *     @type string $context  `featured` (default) or `inline`.
	 *     @type int    $post_id  Post the image is for. Required.
	 *     @type string $prompt   AI prompt; defaults to the prompt template in the settings.
	 *     @type string $keywords Stock search terms; defaults to keywords from the post title.
	 *     @type string $alt      Alt text; defaults to the post title.
	 *     @type string $aspect   `landscape` (default), `square` or `portrait`, for AI images.
	 * }
	 *
	 * @since 1.9.0
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function generate( $args = array() ) {
		$args    = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'context'  => 'featured',
				'post_id'  => 0,
				'prompt'   => '',
				'keywords' => '',
				'alt'      => '',
				'aspect'   => 'landscape',
			)
		);
		$post_id = absint( $args['post_id'] );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'aimg_invalid_post', __( 'The post the image is for does not exist.', 'artificial-image-generator' ) );
		}

		$sources = self::sources();
		$steps   = array();

		foreach ( self::method_steps( $sources['method'] ) as $step ) {
			if ( ! empty( $sources[ $step ] ) && ( 'template' !== $step || 'inline' !== $args['context'] ) ) {
				$steps[] = $step;
			}
		}

		if ( empty( $steps ) && 'inline' === $args['context'] ) {
			$steps = array_keys( array_filter( array( 'ai' => $sources['ai'], 'stock' => $sources['stock'] ) ) );
		}

		if ( empty( $steps ) ) {
			return new \WP_Error( 'aimg_no_method', __( 'Image Generator has no ready image method: add an AI or stock photo API key, or publish an image template.', 'artificial-image-generator' ) );
		}

		$result = null;

		foreach ( $steps as $step ) {
			$result = self::generate_step( $step, $post_id, $args );

			if ( ! is_wp_error( $result ) ) {
				break;
			}
		}

		if ( ! is_wp_error( $result ) && '' !== trim( (string) $args['alt'] ) ) {
			update_post_meta( $result, '_wp_attachment_image_alt', aimg_plain_text( $args['alt'] ) );
		}

		return $result;
	}

	/**
	 * The image methods to try, in order, for a configured generation method.
	 *
	 * @param string $method Generation method.
	 *
	 * @return string[] `ai`, `stock` and `template`.
	 */
	private static function method_steps( $method ) {
		$steps = array(
			Generator::METHOD_AI             => array( 'ai' ),
			Generator::METHOD_TEMPLATE_AI    => array( 'template', 'ai' ),
			Generator::METHOD_AI_TEMPLATE    => array( 'ai', 'template' ),
			Generator::METHOD_STOCK          => array( 'stock' ),
			Generator::METHOD_STOCK_TEMPLATE => array( 'stock', 'template' ),
		);

		return isset( $steps[ $method ] ) ? $steps[ $method ] : array( 'template' );
	}

	/**
	 * Make the image with one method.
	 *
	 * @param string $step    `ai`, `stock` or `template`.
	 * @param int    $post_id Post ID.
	 * @param array  $args    See generate().
	 *
	 * @return int|\WP_Error
	 */
	private static function generate_step( $step, $post_id, $args ) {
		if ( 'template' === $step ) {
			return Generator::generate_template_for_post( $post_id );
		}

		if ( 'stock' === $step ) {
			return Generator::generate_stock_for_post( $post_id, (string) $args['keywords'] );
		}

		$quota = aimg_consume_ai_quota( (int) get_post_field( 'post_author', $post_id ) );

		if ( is_wp_error( $quota ) ) {
			return $quota;
		}

		$prompt = '' !== trim( (string) $args['prompt'] ) ? aimg_plain_text( $args['prompt'] ) : PromptBuilder::build( $post_id );
		$ids    = Generator::generate_ai(
			$prompt,
			array(
				'size'   => in_array( $args['aspect'], array( 'square', 'landscape', 'portrait' ), true ) ? $args['aspect'] : 'landscape',
				'title'  => aimg_get_plain_title( $post_id ),
				'parent' => $post_id,
				'source' => 'api',
			)
		);

		return is_wp_error( $ids ) ? $ids : (int) reset( $ids );
	}

	/**
	 * Clean caller-supplied merge-tag values.
	 *
	 * @param mixed $tags Values as name => text.
	 *
	 * @since 1.8.5
	 * @return array|\WP_Error
	 */
	public static function sanitize_tags( $tags ) {
		if ( empty( $tags ) ) {
			return array();
		}

		if ( ! is_array( $tags ) ) {
			return new \WP_Error( 'aimg_invalid_tags', __( 'Merge tags must be an object of name => text.', 'artificial-image-generator' ) );
		}

		if ( count( $tags ) > self::MAX_TAGS ) {
			/* translators: %d: maximum number of merge tags */
			return new \WP_Error( 'aimg_invalid_tags', sprintf( __( 'At most %d merge tags can be given.', 'artificial-image-generator' ), self::MAX_TAGS ) );
		}

		$clean = array();

		foreach ( $tags as $name => $value ) {
			$name = (string) $name;

			if ( '' === Templates\MergeTags::sanitize_condition( $name ) ) {
				/* translators: %s: merge tag name */
				return new \WP_Error( 'aimg_invalid_tags', sprintf( __( 'Invalid merge tag name "%s". Use a-z and _, or custom_field:key.', 'artificial-image-generator' ), sanitize_text_field( $name ) ) );
			}

			if ( ! is_scalar( $value ) && null !== $value ) {
				/* translators: %s: merge tag name */
				return new \WP_Error( 'aimg_invalid_tags', sprintf( __( 'The value of merge tag "%s" must be text.', 'artificial-image-generator' ), $name ) );
			}

			$value = aimg_plain_text( (string) $value );

			if ( function_exists( 'mb_substr' ) ) {
				$value = mb_substr( $value, 0, self::MAX_TAG_LENGTH );
			} else {
				$value = substr( $value, 0, self::MAX_TAG_LENGTH );
			}

			$clean[ $name ] = $value;
		}

		return $clean;
	}

	/**
	 * Merge tags used in a text, as `name` or `custom_field:key`.
	 *
	 * @param string $text Text.
	 *
	 * @return string[]
	 */
	private static function tags_in( $text ) {
		if ( ! preg_match_all( Templates\MergeTags::PATTERN, (string) $text, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$tags = array();

		foreach ( $matches as $match ) {
			$tags[] = 'custom_field' === $match[1] && ! empty( $match[2] ) ? 'custom_field:' . $match[2] : $match[1];
		}

		return $tags;
	}
}
