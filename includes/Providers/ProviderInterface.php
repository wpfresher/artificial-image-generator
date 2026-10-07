<?php

namespace ArtificialImageGenerator\Providers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * An AI image service.
 *
 * Sizes and qualities use provider-neutral keys so settings survive switching
 * providers: sizes are `square`, `landscape` and `portrait`; qualities are
 * `auto`, `low`, `medium`, `high`, `xhigh` and `max`. Each provider maps them to its own values,
 * using its closest level when a model has no exact match.
 *
 * @since 1.6.0
 * @package ArtificialImageGenerator
 */
interface ProviderInterface {

	/**
	 * Unique provider ID, e.g. `openai`.
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
	 * Models the provider offers, as ID => label.
	 *
	 * @return array
	 */
	public function get_models();

	/**
	 * Model used when none is configured.
	 *
	 * @return string
	 */
	public function get_default_model();

	/**
	 * Most images one request can return for a model.
	 *
	 * @param string $model Model ID.
	 *
	 * @return int
	 */
	public function get_max_images( $model );

	/**
	 * Whether the provider has what it needs (e.g. an API key).
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Generate images.
	 *
	 * @param string $prompt Prompt.
	 * @param array  $args   {
	 *     Optional. Generation arguments.
	 *
	 *     @type string $model   Model ID.
	 *     @type string $size    square, landscape or portrait.
	 *     @type string $quality auto, low, medium, high, xhigh or max.
	 *     @type int    $n       Number of images.
	 * }
	 *
	 * @return Result[]|\WP_Error
	 */
	public function generate( $prompt, $args = array() );
}
