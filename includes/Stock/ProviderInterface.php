<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A stock photo library.
 *
 * Search filters use provider-neutral values: orientation is `landscape`,
 * `portrait` or `square`; color is one of `Registry::colors()`. Download sizes
 * are `regular`, `large` and `original`.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
interface ProviderInterface {

	/**
	 * Unique provider ID, e.g. `unsplash`.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Where to get a free API key.
	 *
	 * @return string
	 */
	public function get_signup_url();

	/**
	 * Whether an API key is set.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Hosts images may be downloaded from.
	 *
	 * @return string[]
	 */
	public function get_hosts();

	/**
	 * Search photos.
	 *
	 * @param string $query Search terms.
	 * @param array  $args  {
	 *     Optional.
	 *
	 *     @type int    $page        Page, from 1.
	 *     @type int    $per_page    Results per page.
	 *     @type string $orientation landscape, portrait, square or ''.
	 *     @type string $color       Color name or ''.
	 * }
	 *
	 * @return array|\WP_Error { total: int, pages: int, photos: Photo[] }
	 */
	public function search( $query, $args = array() );

	/**
	 * One photo.
	 *
	 * @param string $id Photo ID.
	 *
	 * @return Photo|\WP_Error
	 */
	public function get_photo( $id );

	/**
	 * Tell the provider a photo was downloaded, when its terms ask for it.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return void
	 */
	public function track_download( Photo $photo );

	/**
	 * Credit line for a photo, as HTML with links.
	 *
	 * @param Photo $photo Photo.
	 *
	 * @return string
	 */
	public function get_attribution( Photo $photo );
}
