<?php

namespace ArtificialImageGenerator\Rendering\Layers;

use ArtificialImageGenerator\Rendering\Canvas;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * A layer type: how to sanitize it and how to draw it with GD.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
interface LayerInterface {

	/**
	 * Clean a layer's type-specific props. The common props are handled by the schema.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas, for clamping boxes.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas );

	/**
	 * Draw a sanitized layer.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param array  $layer  Layer.
	 *
	 * @return void
	 */
	public static function draw( Canvas $canvas, $layer );
}
