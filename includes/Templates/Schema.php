<?php

namespace ArtificialImageGenerator\Templates;

use ArtificialImageGenerator\Rendering\Layers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * The template document (schema v2) and its sanitizer.
 *
 * A document is `{ version, canvas: { width, height, background }, output: { format,
 * quality }, layers: [ { id, name, type, visible, ...type props } ] }`. Stored and
 * submitted documents always go through `sanitize()`; the server never trusts them.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Schema {

	/**
	 * Schema version.
	 *
	 * @var int
	 */
	const VERSION = 2;

	/**
	 * Most layers in a document.
	 *
	 * @var int
	 */
	const MAX_LAYERS = 50;

	/**
	 * Largest canvas edge.
	 *
	 * @var int
	 */
	const MAX_SIZE = 4000;

	/**
	 * Layer types as type => class implementing Layers\LayerInterface.
	 *
	 * @return array
	 */
	public static function layer_types() {
		$types = array(
			'background' => Layers\Background::class,
			'image'      => Layers\Image::class,
			'overlay'    => Layers\Overlay::class,
			'text'       => Layers\Text::class,
			'shape'      => Layers\Shape::class,
			'pattern'    => Layers\Pattern::class,
			'frame'      => Layers\Frame::class,
		);

		/**
		 * Filter the template layer types.
		 *
		 * @param array $types Types as type => class name implementing
		 *                     \ArtificialImageGenerator\Rendering\Layers\LayerInterface.
		 *
		 * @since 1.7.0
		 */
		$types = (array) apply_filters( 'aimg_template_layers', $types );

		return array_filter(
			$types,
			function ( $class_name ) {
				return is_string( $class_name ) && is_subclass_of( $class_name, Layers\LayerInterface::class );
			}
		);
	}

	/**
	 * Output formats the server can write, as format => label.
	 *
	 * @return array
	 */
	public static function output_formats() {
		$formats = array( 'png' => 'PNG' );

		if ( function_exists( 'imagejpeg' ) ) {
			$formats['jpeg'] = 'JPEG';
		}
		if ( function_exists( 'imagewebp' ) ) {
			$formats['webp'] = 'WebP';
		}

		return $formats;
	}

	/**
	 * The document a new template starts with: the site's default colors and a centered title.
	 *
	 * @return array Sanitized document.
	 */
	public static function starter() {
		return self::sanitize(
			array(
				'canvas' => array(
					'width'  => 1200,
					'height' => 630,
				),
				'layers' => array(
					array(
						'id'   => 'background',
						'type' => 'background',
						'fill' => array(
							'kind'  => 'solid',
							'color' => aimg_get_settings( 'default_bg_color', '#008000' ),
						),
					),
					array(
						'id'      => 'title',
						'type'    => 'text',
						'content' => '{title}',
						'box'     => array(
							'x' => 80,
							'y' => 80,
							'w' => 1040,
							'h' => 470,
						),
						'size'    => array(
							'max' => 64,
							'min' => 24,
						),
						'color'   => aimg_get_settings( 'default_text_color', '#ffffff' ),
					),
				),
			)
		);
	}

	/**
	 * Clean a document.
	 *
	 * @param mixed $document Document, as an array or a JSON string.
	 *
	 * @return array
	 */
	public static function sanitize( $document ) {
		if ( is_string( $document ) ) {
			$document = json_decode( $document, true );
		}

		$document = is_array( $document ) ? $document : array();
		$canvas   = isset( $document['canvas'] ) && is_array( $document['canvas'] ) ? $document['canvas'] : array();
		$output   = isset( $document['output'] ) && is_array( $document['output'] ) ? $document['output'] : array();

		$clean = array(
			'version' => self::VERSION,
			'canvas'  => array(
				'width'      => (int) self::number( isset( $canvas['width'] ) ? $canvas['width'] : 1200, 16, self::MAX_SIZE ),
				'height'     => (int) self::number( isset( $canvas['height'] ) ? $canvas['height'] : 630, 16, self::MAX_SIZE ),
				'background' => self::color( isset( $canvas['background'] ) ? $canvas['background'] : '' ) ? self::color( $canvas['background'] ) : '#000000',
			),
			'output'  => array(
				'format'  => self::choice( isset( $output['format'] ) ? $output['format'] : '', array_keys( self::output_formats() ) ),
				'quality' => (int) self::number( isset( $output['quality'] ) ? $output['quality'] : 85, 1, 100 ),
			),
			'layers'  => array(),
		);

		$types  = self::layer_types();
		$layers = isset( $document['layers'] ) && is_array( $document['layers'] ) ? $document['layers'] : array();
		$ids    = array();

		foreach ( array_slice( $layers, 0, self::MAX_LAYERS ) as $index => $layer ) {
			if ( ! is_array( $layer ) || ! isset( $layer['type'], $types[ $layer['type'] ] ) ) {
				continue;
			}

			$id = isset( $layer['id'] ) ? sanitize_key( $layer['id'] ) : '';
			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = $layer['type'] . '-' . $index;
			}
			$ids[ $id ] = true;

			$clean['layers'][] = array_merge(
				array(
					'id'       => $id,
					'type'     => $layer['type'],
					'name'     => isset( $layer['name'] ) ? sanitize_text_field( (string) $layer['name'] ) : '',
					'visible'  => ! isset( $layer['visible'] ) || (bool) $layer['visible'],
					'rotation' => self::number( isset( $layer['rotation'] ) ? $layer['rotation'] : 0, -360, 360 ),
					'showIf'   => MergeTags::sanitize_condition( isset( $layer['showIf'] ) ? $layer['showIf'] : '' ),
				),
				call_user_func( array( $types[ $layer['type'] ], 'sanitize' ), $layer, $clean['canvas'] )
			);
		}

		return $clean;
	}

	/**
	 * A 6-digit hex color, or '' when the value isn't one. 3-digit colors are expanded.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	public static function color( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( trim( $value ) );

		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m ) ) {
			$value = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * A number clamped to a range.
	 *
	 * @param mixed $value Value.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 *
	 * @return float
	 */
	public static function number( $value, $min, $max ) {
		$value = is_numeric( $value ) ? (float) $value : $min;

		return (float) max( $min, min( $max, $value ) );
	}

	/**
	 * One of the allowed values, or the default (the first allowed value).
	 *
	 * @param mixed  $value         Value.
	 * @param array  $allowed       Allowed values.
	 * @param string $default_value Default; the first allowed value when empty.
	 *
	 * @return string
	 */
	public static function choice( $value, $allowed, $default_value = '' ) {
		if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return '' !== $default_value ? $default_value : reset( $allowed );
	}

	/**
	 * A linear or radial gradient: { kind, angle, cx, cy, stops: [ { color, pos, opacity } ] }.
	 *
	 * @param mixed $gradient Gradient.
	 *
	 * @return array
	 */
	public static function gradient( $gradient ) {
		$gradient = is_array( $gradient ) ? $gradient : array();
		$stops    = array();

		foreach ( array_slice( isset( $gradient['stops'] ) && is_array( $gradient['stops'] ) ? $gradient['stops'] : array(), 0, 8 ) as $stop ) {
			$color = is_array( $stop ) ? self::color( isset( $stop['color'] ) ? $stop['color'] : '' ) : '';

			if ( $color ) {
				$stops[] = array(
					'color'   => $color,
					'pos'     => self::number( isset( $stop['pos'] ) ? $stop['pos'] : 0, 0, 1 ),
					'opacity' => self::number( isset( $stop['opacity'] ) ? $stop['opacity'] : 1, 0, 1 ),
				);
			}
		}

		if ( count( $stops ) < 2 ) {
			$stops = array(
				array(
					'color'   => '#000000',
					'pos'     => 0.0,
					'opacity' => 1.0,
				),
				array(
					'color'   => '#ffffff',
					'pos'     => 1.0,
					'opacity' => 1.0,
				),
			);
		}

		return array(
			'kind'  => self::choice( isset( $gradient['kind'] ) ? $gradient['kind'] : '', array( 'linear', 'radial' ) ),
			'angle' => self::number( isset( $gradient['angle'] ) ? $gradient['angle'] : 180, -360, 360 ),
			'cx'    => self::number( isset( $gradient['cx'] ) ? $gradient['cx'] : 0.5, 0, 1 ),
			'cy'    => self::number( isset( $gradient['cy'] ) ? $gradient['cy'] : 0.5, 0, 1 ),
			'stops' => $stops,
		);
	}

	/**
	 * Image adjustments: { brightness, contrast (-100–100), blur (0–10), grayscale, duotone }.
	 *
	 * @param mixed $adjust Adjustments.
	 *
	 * @return array
	 */
	public static function adjustments( $adjust ) {
		$adjust  = is_array( $adjust ) ? $adjust : array();
		$duotone = isset( $adjust['duotone'] ) && is_array( $adjust['duotone'] ) ? $adjust['duotone'] : array();
		$dark    = self::color( isset( $duotone['dark'] ) ? $duotone['dark'] : '' );
		$light   = self::color( isset( $duotone['light'] ) ? $duotone['light'] : '' );

		return array(
			'brightness' => self::number( isset( $adjust['brightness'] ) ? $adjust['brightness'] : 0, -100, 100 ),
			'contrast'   => self::number( isset( $adjust['contrast'] ) ? $adjust['contrast'] : 0, -100, 100 ),
			'blur'       => (int) self::number( isset( $adjust['blur'] ) ? $adjust['blur'] : 0, 0, 10 ),
			'grayscale'  => ! empty( $adjust['grayscale'] ),
			'duotone'    => $dark && $light ? array(
				'dark'  => $dark,
				'light' => $light,
			) : null,
		);
	}

	/**
	 * A box inside the canvas; the whole canvas by default.
	 *
	 * @param mixed $box    Box as { x, y, w, h }.
	 * @param array $canvas Sanitized canvas.
	 *
	 * @return array
	 */
	public static function box( $box, $canvas ) {
		$box = is_array( $box ) ? $box : array();
		$w   = $canvas['width'];
		$h   = $canvas['height'];

		$clean = array(
			'x' => (int) self::number( isset( $box['x'] ) ? $box['x'] : 0, -$w, $w ),
			'y' => (int) self::number( isset( $box['y'] ) ? $box['y'] : 0, -$h, $h ),
		);

		$clean['w'] = (int) self::number( isset( $box['w'] ) ? $box['w'] : $w, 1, 2 * $w );
		$clean['h'] = (int) self::number( isset( $box['h'] ) ? $box['h'] : $h, 1, 2 * $h );

		return $clean;
	}
}
