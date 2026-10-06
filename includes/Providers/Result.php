<?php

namespace ArtificialImageGenerator\Providers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * One image returned by a provider: either raw bytes or a URL to download.
 *
 * @since 1.6.0
 * @package ArtificialImageGenerator
 */
class Result {

	/**
	 * Image bytes.
	 *
	 * @var string
	 */
	public $data = '';

	/**
	 * URL to download the image from.
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Prompt the provider actually used, when it rewrote ours.
	 *
	 * @var string
	 */
	public $revised_prompt = '';

	/**
	 * Constructor.
	 *
	 * @param array $props Properties to set.
	 */
	public function __construct( $props = array() ) {
		foreach ( $props as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = (string) $value;
			}
		}
	}
}
