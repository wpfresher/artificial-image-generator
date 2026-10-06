<?php

namespace ArtificialImageGenerator\Templates;

use ArtificialImageGenerator\PromptBuilder;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Merge tags for text in templates: `{title}`, `{excerpt}`, `{category}`, `{tags}`,
 * `{author}`, `{date}`, `{site_name}`, `{reading_time}` and `{custom_field:key}`.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class MergeTags {

	/**
	 * Pattern matching a tag; group 1 is the name, group 2 a custom field key.
	 *
	 * @var string
	 */
	const PATTERN = '/\{([a-z_]+)(?::([A-Za-z0-9_\-]+))?\}/';

	/**
	 * Tag names, as name => label.
	 *
	 * @return array
	 */
	public static function names() {
		return array(
			'title'        => __( 'Post title', 'artificial-image-generator' ),
			'excerpt'      => __( 'Excerpt', 'artificial-image-generator' ),
			'category'     => __( 'Categories', 'artificial-image-generator' ),
			'tags'         => __( 'Tags', 'artificial-image-generator' ),
			'author'       => __( 'Author', 'artificial-image-generator' ),
			'date'         => __( 'Date', 'artificial-image-generator' ),
			'site_name'    => __( 'Site name', 'artificial-image-generator' ),
			'reading_time' => __( 'Reading time', 'artificial-image-generator' ),
		);
	}

	/**
	 * Tag values for a post, as name => value.
	 *
	 * @param int   $post_id   Post ID, or 0 for none.
	 * @param array $overrides Values to use instead, e.g. array( 'title' => '…' ).
	 *
	 * @return array
	 */
	public static function values( $post_id, $overrides = array() ) {
		$post   = $post_id ? get_post( $post_id ) : null;
		$values = PromptBuilder::get_tag_values( $post ? $post->ID : 0 );

		$values['author']       = '';
		$values['date']         = '';
		$values['reading_time'] = '';

		if ( $post ) {
			$values['author'] = html_entity_decode( get_the_author_meta( 'display_name', $post->post_author ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$values['date']   = get_the_date( '', $post );

			$minutes                = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $post->post_content ) ) / 200 ) );
			$values['reading_time'] = sprintf(
				/* translators: %d: minutes needed to read the post */
				_n( '%d min read', '%d min read', $minutes, 'artificial-image-generator' ),
				$minutes
			);
		}

		foreach ( $overrides as $name => $value ) {
			$values[ $name ] = (string) $value;
		}

		/**
		 * Filter the merge tag values used in template text.
		 *
		 * @param array $values  Values as tag name => text.
		 * @param int   $post_id Post ID, or 0.
		 *
		 * @since 1.7.0
		 */
		return (array) apply_filters( 'aimg_merge_tags', $values, $post ? $post->ID : 0 );
	}

	/**
	 * Replace the tags in a text in one pass; values are never parsed again.
	 * Unknown tags are removed.
	 *
	 * @param string $text    Text.
	 * @param array  $values  Values as name => text.
	 * @param int    $post_id Post ID for `{custom_field:key}`, or 0.
	 *
	 * @return string
	 */
	public static function replace( $text, $values, $post_id = 0 ) {
		return preg_replace_callback(
			self::PATTERN,
			function ( $matches ) use ( $values, $post_id ) {
				if ( 'custom_field' === $matches[1] && ! empty( $matches[2] ) ) {
					$value = $post_id && ! is_protected_meta( $matches[2], 'post' ) ? get_post_meta( $post_id, $matches[2], true ) : '';

					return is_scalar( $value ) ? wp_strip_all_tags( (string) $value ) : '';
				}

				return isset( $values[ $matches[1] ] ) ? (string) $values[ $matches[1] ] : '';
			},
			(string) $text
		);
	}

	/**
	 * Clean a `showIf` value: a tag name or `custom_field:key`, else ''.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	public static function sanitize_condition( $value ) {
		return is_string( $value ) && preg_match( '/^[a-z_]+(:[A-Za-z0-9_\-]+)?$/', $value ) ? $value : '';
	}
}
