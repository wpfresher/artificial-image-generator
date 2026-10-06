<?php

namespace ArtificialImageGenerator\Templates;

use ArtificialImageGenerator\Rendering\Fonts;
use ArtificialImageGenerator\Rendering\Layers\Overlay;
use ArtificialImageGenerator\Rendering\TextLayout;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Builds v2 documents from 1.x templates. Nothing here writes to the database.
 *
 * Both builders reproduce the 1.x look exactly: palette background, PNG overlay
 * scaled to fit the canvas at the chosen position, the background color over
 * everything at 70% opacity, and the title centered with 40 px side margins.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Migration {

	/**
	 * Opacity of the 1.x background tint (GD alpha 38 of 127).
	 *
	 * @var float
	 */
	const TINT_OPACITY = 0.7;

	/**
	 * Document for a 1.x template, from its meta and the current settings.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return array Sanitized document.
	 */
	public static function from_template( $template_id ) {
		$colors   = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $template_id, '_aimg_bg_colors', true ) ) ) );
		$overlays = array();

		if ( 'yes' === get_post_meta( $template_id, '_aimg_is_overlay_image', true ) ) {
			$overlays = json_decode( (string) get_post_meta( $template_id, '_aimg_overlay_images', true ), true );
			$overlays = is_array( $overlays ) ? $overlays : array();
		}

		$width  = (int) get_post_meta( $template_id, '_aimg_width', true );
		$height = (int) get_post_meta( $template_id, '_aimg_height', true );

		$document = self::build(
			$width > 0 ? $width : 1200,
			$height > 0 ? $height : 600,
			$colors ? array(
				'kind'   => 'palette',
				'colors' => array_values( $colors ),
			) : array(
				'kind'  => 'solid',
				'color' => aimg_get_settings( 'default_bg_color', '#008000' ),
			),
			array(
				'attachments' => array_map( 'absint', $overlays ),
				'pick'        => 'random',
			),
			self::font_size( $template_id ),
			(string) get_post_meta( $template_id, '_aimg_overlay_position', true )
		);

		if ( ! $overlays ) {
			$document['layers'] = array_values(
				array_filter(
					$document['layers'],
					function ( $layer ) {
						return 'image' !== $layer['type'];
					}
				)
			);
		}

		return Schema::sanitize( $document );
	}

	/**
	 * Document for the arguments of `aimg_generate_thumbnail()`.
	 *
	 * Not sanitized: the colors and overlay files are used as given, exactly as 1.x did.
	 *
	 * @param array $args Render arguments: template_id, colors, width, height and overlays (PNG paths).
	 *
	 * @return array
	 */
	public static function from_render_args( $args ) {
		$template_id = absint( $args['template_id'] );
		$colors      = is_array( $args['colors'] ) ? $args['colors'] : array();
		$files       = array();

		foreach ( (array) $args['overlays'] as $file ) {
			if ( ! is_string( $file ) || ! file_exists( $file ) || 'png' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $info && IMAGETYPE_PNG === $info[2] ) {
				$files[] = $file;
			}
		}

		$document = self::build(
			absint( $args['width'] ),
			absint( $args['height'] ),
			$colors ? array(
				'kind'   => 'palette',
				'colors' => $colors,
			) : array(
				'kind'  => 'solid',
				'color' => aimg_get_settings( 'default_bg_color', '#008000' ),
			),
			array( '_files' => $files ),
			self::font_size( $template_id ),
			(string) get_post_meta( $template_id, '_aimg_overlay_position', true )
		);

		return $document;
	}

	/**
	 * The 1.x layer stack.
	 *
	 * @param int    $width     Canvas width.
	 * @param int    $height    Canvas height.
	 * @param array  $fill      Background fill.
	 * @param array  $image     Image layer sources.
	 * @param float  $font_size Title font size.
	 * @param string $position  Overlay position.
	 *
	 * @return array
	 */
	private static function build( $width, $height, $fill, $image, $font_size, $position ) {
		$text_color = aimg_get_settings( 'default_text_color', '#ffffff' );

		if ( ! is_string( $text_color ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $text_color ) ) {
			$text_color = '#ffffff';
		}

		$canvas = array(
			'x' => 0,
			'y' => 0,
			'w' => $width,
			'h' => $height,
		);

		return array(
			'version' => Schema::VERSION,
			'canvas'  => array(
				'width'      => $width,
				'height'     => $height,
				'background' => '#000000',
			),
			'output'  => array(
				'format'  => 'png',
				'quality' => 85,
			),
			'layers'  => array(
				array(
					'id'      => 'background',
					'type'    => 'background',
					'name'    => '',
					'visible' => true,
					'fill'    => $fill,
				),
				array_merge(
					array(
						'id'          => 'overlay-image',
						'type'        => 'image',
						'name'        => '',
						'visible'     => true,
						'attachments' => array(),
						'pick'        => 'random',
						'box'         => $canvas,
						'fit'         => 'contain',
						'anchor'      => $position,
						'opacity'     => 1,
					),
					$image
				),
				array(
					'id'      => 'tint',
					'type'    => 'overlay',
					'name'    => '',
					'visible' => true,
					'color'   => Overlay::BACKGROUND,
					'opacity' => self::TINT_OPACITY,
				),
				array(
					'id'         => 'title',
					'type'       => 'text',
					'name'       => '',
					'visible'    => true,
					'content'    => '{title}',
					'font'       => Fonts::DEFAULT_FONT,
					'size'       => array(
						'max' => $font_size,
						'min' => TextLayout::MIN_WRAP_SIZE,
					),
					'fit'        => 'width',
					'box'        => array(
						'x' => 40,
						'y' => 0,
						'w' => $width - 80,
						'h' => $height,
					),
					'align'      => 'center',
					'valign'     => 'middle',
					'color'      => strtolower( $text_color ),
					'lineHeight' => 1.4,
					'maxLines'   => 0,
				),
			),
		);
	}

	/**
	 * A template's title font size; an empty or invalid value uses the default, as in 1.x.
	 *
	 * @param int $template_id Template ID.
	 *
	 * @return float
	 */
	private static function font_size( $template_id ) {
		$size = (float) get_post_meta( $template_id, '_aimg_title_font_size', true );

		return $size > 0 ? $size : (float) AIMG_DEFAULT_FONT_SIZE;
	}
}
