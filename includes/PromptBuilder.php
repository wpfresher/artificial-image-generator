<?php

namespace ArtificialImageGenerator;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Builds AI prompts from posts.
 *
 * @since 1.6.0
 * @package ArtificialImageGenerator
 */
class PromptBuilder {

	/**
	 * Default prompt template.
	 *
	 * @return string
	 */
	public static function get_default_template() {
		return __( 'A featured image for an article titled "{title}". The article is about: {excerpt}', 'artificial-image-generator' );
	}

	/**
	 * Default instructions appended to every prompt.
	 *
	 * @return string
	 */
	public static function get_default_negative() {
		return __( 'Do not include any text, letters, words, logos or watermarks in the image.', 'artificial-image-generator' );
	}

	/**
	 * Style presets, as ID => array( label, prompt suffix ).
	 *
	 * @return array
	 */
	public static function get_styles() {
		$styles = array(
			'none'         => array( __( 'None', 'artificial-image-generator' ), '' ),
			'photo'        => array( __( 'Photorealistic', 'artificial-image-generator' ), 'Photorealistic photograph, natural light, high detail.' ),
			'illustration' => array( __( 'Flat illustration', 'artificial-image-generator' ), 'Flat vector illustration, clean shapes, limited color palette.' ),
			'3d'           => array( __( '3D render', 'artificial-image-generator' ), 'Soft 3D render, studio lighting, smooth materials.' ),
			'watercolor'   => array( __( 'Watercolor', 'artificial-image-generator' ), 'Watercolor painting, soft edges, paper texture.' ),
			'line-art'     => array( __( 'Minimal line art', 'artificial-image-generator' ), 'Minimal line art, thin strokes, lots of white space.' ),
			'cinematic'    => array( __( 'Cinematic', 'artificial-image-generator' ), 'Cinematic still, dramatic lighting, shallow depth of field.' ),
			'isometric'    => array( __( 'Isometric', 'artificial-image-generator' ), 'Isometric illustration, clean geometry, bright colors.' ),
		);

		/**
		 * Filter the AI style presets.
		 *
		 * @param array $styles Presets as ID => array( label, prompt suffix ).
		 *
		 * @since 1.6.0
		 */
		return (array) apply_filters( 'aimg_style_presets', $styles );
	}

	/**
	 * Values for the merge tags of a post.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $overrides Values to use instead, e.g. unsaved `title` and `excerpt` from the editor.
	 *
	 * @return array Tag (without braces) => value.
	 */
	public static function get_tag_values( $post_id, $overrides = array() ) {
		$post = $post_id ? get_post( $post_id ) : null;

		$values = array(
			'title'     => $post ? aimg_get_plain_title( $post->ID ) : '',
			'excerpt'   => $post ? self::get_excerpt( $post ) : '',
			'category'  => '',
			'tags'      => '',
			'site_name' => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		);

		if ( $post ) {
			$categories = get_the_terms( $post, 'category' );
			if ( is_array( $categories ) ) {
				$values['category'] = implode( ', ', wp_list_pluck( $categories, 'name' ) );
			}

			$tags = get_the_terms( $post, 'post_tag' );
			if ( is_array( $tags ) ) {
				$values['tags'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
			}
		}

		foreach ( array( 'title', 'excerpt' ) as $key ) {
			if ( isset( $overrides[ $key ] ) && '' !== trim( (string) $overrides[ $key ] ) ) {
				$values[ $key ] = 'excerpt' === $key ? self::trim_words( $overrides[ $key ] ) : trim( wp_strip_all_tags( $overrides[ $key ] ) );
			}
		}

		return $values;
	}

	/**
	 * Build the prompt for a post.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $args      {
	 *     Optional.
	 *
	 *     @type string $template Prompt template. Defaults to the `ai_prompt_template` setting.
	 *     @type string $style    Style preset ID. Defaults to the `ai_style` setting.
	 *     @type array  $values   Overrides for tag values (`title`, `excerpt`).
	 * }
	 *
	 * @return string
	 */
	public static function build( $post_id, $args = array() ) {
		$template = isset( $args['template'] ) ? (string) $args['template'] : (string) aimg_get_settings( 'ai_prompt_template', '' );
		$template = '' !== trim( $template ) ? $template : self::get_default_template();
		$style    = isset( $args['style'] ) ? (string) $args['style'] : (string) aimg_get_settings( 'ai_style', 'photo' );
		$values   = self::get_tag_values( $post_id, isset( $args['values'] ) ? (array) $args['values'] : array() );

		$prompt = preg_replace_callback(
			'/\{(custom_field:([A-Za-z0-9_\-]+)|[a-z_]+)\}/',
			function ( $matches ) use ( $values, $post_id ) {
				if ( ! empty( $matches[2] ) ) {
					$value = $post_id ? get_post_meta( $post_id, $matches[2], true ) : '';

					return is_scalar( $value ) ? wp_strip_all_tags( (string) $value ) : '';
				}

				return isset( $values[ $matches[1] ] ) ? $values[ $matches[1] ] : $matches[0];
			},
			$template
		);

		$styles = self::get_styles();
		$parts  = array( rtrim( trim( $prompt ), ':' ) );

		if ( isset( $styles[ $style ][1] ) && '' !== $styles[ $style ][1] ) {
			$parts[] = $styles[ $style ][1];
		}

		$negative = (string) aimg_get_settings( 'ai_negative_prompt', self::get_default_negative() );
		if ( '' !== trim( $negative ) ) {
			$parts[] = trim( $negative );
		}

		$prompt = trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $parts ) ) ) );

		/**
		 * Filter the prompt built for a post.
		 *
		 * @param string $prompt  Prompt.
		 * @param int    $post_id Post ID.
		 *
		 * @since 1.6.0
		 */
		return (string) apply_filters( 'aimg_post_prompt', $prompt, $post_id );
	}

	/**
	 * The post excerpt, or the start of its content.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return string
	 */
	private static function get_excerpt( $post ) {
		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );

		return self::trim_words( $text );
	}

	/**
	 * Plain text, cut to 40 words.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	private static function trim_words( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( excerpt_remove_blocks( (string) $text ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return wp_trim_words( $text, 40, '…' );
	}
}
