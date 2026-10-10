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
		$names = array(
			'title'        => __( 'Post title', 'artificial-image-generator' ),
			'excerpt'      => __( 'Excerpt', 'artificial-image-generator' ),
			'category'     => __( 'Categories', 'artificial-image-generator' ),
			'tags'         => __( 'Tags', 'artificial-image-generator' ),
			'author'       => __( 'Author', 'artificial-image-generator' ),
			'date'         => __( 'Date', 'artificial-image-generator' ),
			'site_name'    => __( 'Site name', 'artificial-image-generator' ),
			'reading_time' => __( 'Reading time', 'artificial-image-generator' ),
		);

		/**
		 * Filter the merge tags offered in the Template Studio. Give them values with `aimg_merge_tags`.
		 *
		 * @param array $names Tag names (a-z and _) as name => label.
		 *
		 * @since 1.8.0
		 */
		return (array) apply_filters( 'aimg_merge_tag_names', $names );
	}

	/**
	 * Sample values for previews without a post: the Studio canvas and its exact preview.
	 *
	 * @since 1.8.0
	 * @return array Values as tag name => text; custom fields as "custom_field:key".
	 */
	public static function samples() {
		$samples = array(
			'title'        => __( 'How to grow tomatoes on a small balcony', 'artificial-image-generator' ),
			'excerpt'      => __( 'A simple guide to pots, soil, sun and watering for a big summer harvest.', 'artificial-image-generator' ),
			'category'     => __( 'Gardening', 'artificial-image-generator' ),
			'tags'         => __( 'Tomatoes, Balcony', 'artificial-image-generator' ),
			'author'       => wp_get_current_user()->display_name,
			'date'         => wp_date( get_option( 'date_format' ) ),
			'site_name'    => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			/* translators: %d: minutes */
			'reading_time' => sprintf( _n( '%d min read', '%d min read', 4, 'artificial-image-generator' ), 4 ),
		);

		/**
		 * Filter the sample values the Template Studio shows for merge tags.
		 *
		 * @param array $tags Values as tag name => text; custom fields as "custom_field:key".
		 *
		 * @since 1.8.0
		 */
		return (array) apply_filters( 'aimg_studio_sample_tags', $samples );
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
	 * @param array  $values  Values as name => text; a "custom_field:key" value replaces the post meta.
	 * @param int    $post_id Post ID for `{custom_field:key}`, or 0.
	 *
	 * @return string
	 */
	public static function replace( $text, $values, $post_id = 0 ) {
		return preg_replace_callback(
			self::PATTERN,
			function ( $matches ) use ( $values, $post_id ) {
				if ( 'custom_field' === $matches[1] && ! empty( $matches[2] ) ) {
					if ( isset( $values[ 'custom_field:' . $matches[2] ] ) ) {
						return (string) $values[ 'custom_field:' . $matches[2] ];
					}

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
