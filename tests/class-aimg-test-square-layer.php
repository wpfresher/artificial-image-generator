<?php
/**
 * A layer type registered the way another plugin would, for tests.
 *
 * @package ArtificialImageGenerator
 */

use ArtificialImageGenerator\Rendering\Canvas;
use ArtificialImageGenerator\Rendering\Layers\LayerInterface;
use ArtificialImageGenerator\Templates\Schema;

/**
 * Fills a square at the canvas origin.
 */
class AIMG_Test_Square_Layer implements LayerInterface {

	/**
	 * Clean the layer.
	 *
	 * @param array $layer  Raw layer.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function sanitize( $layer, $canvas ) {
		$color = Schema::color( isset( $layer['color'] ) ? $layer['color'] : '' );

		return array(
			'size'  => (int) Schema::number( isset( $layer['size'] ) ? $layer['size'] : 10, 1, $canvas['width'] ),
			'color' => $color ? $color : '#ffffff',
		);
	}

	/**
	 * Draw the layer.
	 *
	 * @param Canvas $canvas Canvas.
	 * @param array  $layer  Layer.
	 *
	 * @return void
	 */
	public static function draw( Canvas $canvas, $layer ) {
		$rgb = Canvas::rgb( $layer['color'] );
		imagefilledrectangle( $canvas->image, 0, 0, $layer['size'] - 1, $layer['size'] - 1, imagecolorallocate( $canvas->image, $rgb[0], $rgb[1], $rgb[2] ) );
	}
}
