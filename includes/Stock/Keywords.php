<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Search terms for a post's stock photo.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Keywords {

	/**
	 * Most words in a query.
	 *
	 * @var int
	 */
	const MAX_WORDS = 3;

	/**
	 * Search terms from a post's title, falling back to its first tag or category.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function for_post( $post_id ) {
		$query = self::from_text( aimg_get_plain_title( $post_id ) );

		if ( '' === $query ) {
			foreach ( array( 'post_tag', 'category' ) as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( is_array( $terms ) && $terms && 'uncategorized' !== $terms[0]->slug ) {
					$query = aimg_plain_text( $terms[0]->name );
					break;
				}
			}
		}

		/**
		 * Filter the search terms for a post's stock photo.
		 *
		 * @param string $query   Search terms.
		 * @param int    $post_id Post ID.
		 *
		 * @since 1.8.0
		 */
		return trim( (string) apply_filters( 'aimg_stock_keywords', $query, $post_id ) );
	}

	/**
	 * Keywords from text: words of three or more letters, without stop words or numbers.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	public static function from_text( $text ) {
		$words = preg_split( '/[^\p{L}\p{N}\'’-]+/u', mb_strtolower( aimg_plain_text( $text ) ), -1, PREG_SPLIT_NO_EMPTY );
		$stop  = array_flip( self::stop_words() );
		$keep  = array();

		foreach ( (array) $words as $word ) {
			$word = trim( $word, "'’-" );

			if ( mb_strlen( $word ) < 3 || isset( $stop[ $word ] ) || is_numeric( $word ) || isset( $keep[ $word ] ) ) {
				continue;
			}

			$keep[ $word ] = true;

			if ( count( $keep ) >= self::MAX_WORDS ) {
				break;
			}
		}

		return implode( ' ', array_keys( $keep ) );
	}

	/**
	 * English stop words.
	 *
	 * @return string[]
	 */
	private static function stop_words() {
		$words = array( 'about', 'above', 'after', 'again', 'against', 'all', 'and', 'any', 'are', 'because', 'been', 'before', 'being', 'below', 'best', 'between', 'both', 'but', 'can', 'complete', 'could', 'did', 'does', 'doing', 'down', 'during', 'each', 'easy', 'every', 'few', 'for', 'from', 'further', 'get', 'guide', 'had', 'has', 'have', 'having', 'her', 'here', 'hers', 'him', 'his', 'how', 'into', 'its', 'just', 'know', 'make', 'more', 'most', 'need', 'new', 'not', 'now', 'off', 'once', 'only', 'other', 'our', 'ours', 'out', 'over', 'own', 'same', 'she', 'should', 'some', 'such', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'things', 'this', 'those', 'through', 'tips', 'top', 'under', 'until', 'use', 'using', 'very', 'want', 'was', 'way', 'ways', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'without', 'would', 'you', 'your', 'yours', 'yourself' );

		/**
		 * Filter the words left out of stock photo searches.
		 *
		 * @param string[] $words Lowercase words.
		 *
		 * @since 1.8.0
		 */
		return (array) apply_filters( 'aimg_stock_stop_words', $words );
	}
}
