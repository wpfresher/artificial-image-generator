<?php

namespace ArtificialImageGenerator\Stock;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A photo from a stock library.
 *
 * @since 1.8.0
 * @package ArtificialImageGenerator
 */
class Photo {

	/**
	 * Photo ID at the provider.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Provider ID.
	 *
	 * @var string
	 */
	public $provider = '';

	/**
	 * Width of the original, in pixels.
	 *
	 * @var int
	 */
	public $width = 0;

	/**
	 * Height of the original, in pixels.
	 *
	 * @var int
	 */
	public $height = 0;

	/**
	 * Small image for result grids (hotlinked, as the providers require).
	 *
	 * @var string
	 */
	public $thumb = '';

	/**
	 * Photo page at the provider.
	 *
	 * @var string
	 */
	public $page_url = '';

	/**
	 * Photographer name.
	 *
	 * @var string
	 */
	public $photographer = '';

	/**
	 * Photographer profile URL.
	 *
	 * @var string
	 */
	public $photographer_url = '';

	/**
	 * Description, used as alt text.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Average color, as #rrggbb, or ''.
	 *
	 * @var string
	 */
	public $color = '';

	/**
	 * Download URLs as size => URL; sizes are regular, large and original.
	 *
	 * @var array
	 */
	public $urls = array();

	/**
	 * Provider-specific data needed later, e.g. a download tracking URL.
	 *
	 * @var array
	 */
	public $extra = array();

	/**
	 * Build a photo from an array of properties.
	 *
	 * @param array $props Properties.
	 */
	public function __construct( $props = array() ) {
		foreach ( $props as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}

		$this->id     = (string) $this->id;
		$this->width  = (int) $this->width;
		$this->height = (int) $this->height;
	}

	/**
	 * The photo for REST responses.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'              => $this->id,
			'provider'        => $this->provider,
			'width'           => $this->width,
			'height'          => $this->height,
			'thumb'           => esc_url_raw( $this->thumb ),
			'pageUrl'         => esc_url_raw( $this->page_url ),
			'photographer'    => $this->photographer,
			'photographerUrl' => esc_url_raw( $this->photographer_url ),
			'description'     => $this->description,
			'color'           => $this->color,
		);
	}
}
