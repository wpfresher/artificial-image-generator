<?php

namespace ArtificialImageGenerator\Templates;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Starter designs offered when creating a template in the Template Studio.
 *
 * @since 1.7.0
 * @package ArtificialImageGenerator
 */
class Starters {

	/**
	 * Starters as ID => array( label, sanitized document ).
	 *
	 * @return array
	 */
	public static function all() {
		$text = function ( $content, $box, $props = array() ) {
			return array_merge(
				array(
					'type'    => 'text',
					'content' => $content,
					'box'     => array_combine( array( 'x', 'y', 'w', 'h' ), $box ),
					'color'   => '#ffffff',
				),
				$props
			);
		};

		$gradient = function ( $from, $to, $angle = 135 ) {
			return array(
				'kind'  => 'linear',
				'angle' => $angle,
				'stops' => array(
					array(
						'color' => $from,
						'pos'   => 0,
					),
					array(
						'color' => $to,
						'pos'   => 1,
					),
				),
			);
		};

		$pill = array(
			'mode'    => 'block',
			'color'   => '#ffffff',
			'opacity' => 1,
			'padding' => 12,
			'radius'  => 20,
		);

		$starters = array(
			'bold-headline'   => array(
				__( 'Bold headline', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => $gradient( '#3a1c71', '#d76d77' ),
					),
					array(
						'type'    => 'pattern',
						'pattern' => 'diagonal',
						'color'   => '#ffffff',
						'opacity' => 0.06,
						'spacing' => 26,
						'size'    => 2,
					),
					$text(
						'{category}',
						array( 80, 80, 600, 50 ),
						array(
							'font'          => 'inter-bold',
							'size'          => array(
								'max' => 16,
								'min' => 10,
							),
							'align'         => 'left',
							'transform'     => 'upper',
							'letterSpacing' => 3,
							'color'         => '#3a1c71',
							'highlight'     => $pill,
							'showIf'        => 'category',
						)
					),
					$text(
						'{title}',
						array( 80, 160, 1040, 330 ),
						array(
							'font'       => 'inter-bold',
							'size'       => array(
								'max' => 56,
								'min' => 26,
							),
							'align'      => 'left',
							'valign'     => 'top',
							'lineHeight' => 1.2,
							'maxLines'   => 4,
						)
					),
					$text(
						'{site_name}',
						array( 80, 520, 1040, 40 ),
						array(
							'font'    => 'inter-regular',
							'size'    => array(
								'max' => 18,
								'min' => 10,
							),
							'align'   => 'left',
							'opacity' => 0.8,
						)
					),
				),
			),
			'classic'         => array(
				__( 'Classic centered', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'   => 'palette',
							'colors' => array( '#1e3a5f', '#2e7d32', '#6a1b9a', '#c62828' ),
						),
					),
					array(
						'type'    => 'pattern',
						'pattern' => 'dots',
						'color'   => '#ffffff',
						'opacity' => 0.12,
						'spacing' => 30,
						'size'    => 2,
					),
					$text(
						'{title}',
						array( 100, 90, 1000, 450 ),
						array(
							'font' => 'roboto-bold',
							'size' => array(
								'max' => 54,
								'min' => 24,
							),
						)
					),
				),
			),
			'photo-fade'      => array(
				__( 'Photo with fade', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'   => 'image',
							'source' => 'featured',
							'fit'    => 'cover',
							'color'  => '#263238',
						),
					),
					array(
						'type'     => 'overlay',
						'kind'     => 'gradient',
						'opacity'  => 1,
						'gradient' => array(
							'kind'  => 'linear',
							'angle' => 180,
							'stops' => array(
								array(
									'color'   => '#000000',
									'pos'     => 0.2,
									'opacity' => 0,
								),
								array(
									'color'   => '#000000',
									'pos'     => 1,
									'opacity' => 0.85,
								),
							),
						),
					),
					$text(
						'{title}',
						array( 70, 300, 1060, 270 ),
						array(
							'font'       => 'poppins-bold',
							'size'       => array(
								'max' => 50,
								'min' => 24,
							),
							'align'      => 'left',
							'valign'     => 'bottom',
							'lineHeight' => 1.2,
							'maxLines'   => 3,
						)
					),
				),
			),
			'magazine'        => array(
				__( 'Magazine serif', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'  => 'solid',
							'color' => '#f5efe6',
						),
					),
					$text(
						'{title}',
						array( 110, 110, 980, 300 ),
						array(
							'font'       => 'playfair-display-bold',
							'size'       => array(
								'max' => 56,
								'min' => 26,
							),
							'color'      => '#2b2118',
							'lineHeight' => 1.25,
							'maxLines'   => 4,
						)
					),
					array(
						'type'      => 'shape',
						'shape'     => 'line',
						'box'       => array(
							'x' => 540,
							'y' => 440,
							'w' => 120,
							'h' => 10,
						),
						'thickness' => 3,
						'fill'      => array(
							'kind'  => 'solid',
							'color' => '#b08d57',
						),
					),
					$text(
						'{author} · {date}',
						array( 110, 470, 980, 40 ),
						array(
							'font'  => 'noto-sans-regular',
							'size'  => array(
								'max' => 16,
								'min' => 10,
							),
							'color' => '#6d5c4b',
						)
					),
				),
			),
			'minimal-frame'   => array(
				__( 'Minimal frame', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'  => 'solid',
							'color' => '#ffffff',
						),
					),
					array(
						'type'   => 'frame',
						'width'  => 4,
						'inset'  => 40,
						'radius' => 0,
						'color'  => '#111111',
					),
					$text(
						'{title}',
						array( 120, 120, 960, 330 ),
						array(
							'font'     => 'montserrat-extrabold',
							'size'     => array(
								'max' => 52,
								'min' => 24,
							),
							'color'    => '#111111',
							'maxLines' => 4,
						)
					),
					$text(
						'{site_name}',
						array( 120, 490, 960, 40 ),
						array(
							'font'          => 'montserrat-regular',
							'size'          => array(
								'max' => 15,
								'min' => 10,
							),
							'color'         => '#555555',
							'transform'     => 'upper',
							'letterSpacing' => 4,
						)
					),
				),
			),
			'highlight-lines' => array(
				__( 'Highlighted lines', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'  => 'solid',
							'color' => '#0b132b',
						),
					),
					array(
						'type'    => 'pattern',
						'pattern' => 'grid',
						'color'   => '#ffffff',
						'opacity' => 0.05,
						'spacing' => 40,
						'size'    => 1,
					),
					$text(
						'{title}',
						array( 90, 100, 1020, 430 ),
						array(
							'font'       => 'poppins-bold',
							'size'       => array(
								'max' => 46,
								'min' => 22,
							),
							'align'      => 'left',
							'color'      => '#0b132b',
							'lineHeight' => 1.5,
							'maxLines'   => 4,
							'highlight'  => array(
								'mode'    => 'lines',
								'color'   => '#ffd166',
								'opacity' => 1,
								'padding' => 8,
								'radius'  => 4,
							),
						)
					),
				),
			),
			'split'           => array(
				__( 'Split with image', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'  => 'solid',
							'color' => '#e9ecef',
						),
					),
					array(
						'type'   => 'image',
						'source' => 'featured',
						'box'    => array(
							'x' => 600,
							'y' => 0,
							'w' => 600,
							'h' => 630,
						),
						'fit'    => 'cover',
					),
					array(
						'type' => 'shape',
						'box'  => array(
							'x' => 0,
							'y' => 0,
							'w' => 600,
							'h' => 630,
						),
						'fill' => array(
							'kind'  => 'solid',
							'color' => '#14213d',
						),
					),
					$text(
						'{title}',
						array( 60, 90, 480, 450 ),
						array(
							'font'       => 'inter-bold',
							'size'       => array(
								'max' => 44,
								'min' => 20,
							),
							'align'      => 'left',
							'lineHeight' => 1.25,
							'maxLines'   => 6,
						)
					),
				),
			),
			'quote'           => array(
				__( 'Quote card', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'  => 'radial',
							'cx'    => 0.3,
							'cy'    => 0.3,
							'stops' => array(
								array(
									'color' => '#43cea2',
									'pos'   => 0,
								),
								array(
									'color' => '#185a9d',
									'pos'   => 1,
								),
							),
						),
					),
					$text(
						'“',
						array( 80, 40, 200, 200 ),
						array(
							'font'    => 'playfair-display-bold',
							'size'    => array(
								'max' => 150,
								'min' => 60,
							),
							'align'   => 'left',
							'valign'  => 'top',
							'opacity' => 0.5,
						)
					),
					$text(
						'{excerpt}',
						array( 120, 170, 960, 300 ),
						array(
							'font'     => 'noto-sans-bold',
							'size'     => array(
								'max' => 34,
								'min' => 18,
							),
							'align'    => 'left',
							'maxLines' => 5,
						)
					),
					$text(
						'— {title}',
						array( 120, 500, 960, 50 ),
						array(
							'font'    => 'noto-sans-regular',
							'size'    => array(
								'max' => 18,
								'min' => 10,
							),
							'align'   => 'left',
							'opacity' => 0.85,
						)
					),
				),
			),
			'photo-headline'  => array(
				__( 'Stock photo with headline', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'   => 'image',
							'source' => 'stock',
							'query'  => '{title}',
							'fit'    => 'cover',
							'color'  => '#1f2933',
						),
					),
					array(
						'type'     => 'overlay',
						'kind'     => 'gradient',
						'opacity'  => 1,
						'gradient' => array(
							'kind'  => 'linear',
							'angle' => 180,
							'stops' => array(
								array(
									'color'   => '#000000',
									'pos'     => 0.15,
									'opacity' => 0,
								),
								array(
									'color'   => '#000000',
									'pos'     => 1,
									'opacity' => 0.85,
								),
							),
						),
					),
					$text(
						'{category}',
						array( 70, 250, 600, 46 ),
						array(
							'font'          => 'inter-bold',
							'size'          => array(
								'max' => 14,
								'min' => 10,
							),
							'align'         => 'left',
							'transform'     => 'upper',
							'letterSpacing' => 3,
							'color'         => '#111111',
							'highlight'     => $pill,
							'showIf'        => 'category',
						)
					),
					$text(
						'{title}',
						array( 70, 320, 1060, 250 ),
						array(
							'font'       => 'inter-bold',
							'size'       => array(
								'max' => 50,
								'min' => 24,
							),
							'align'      => 'left',
							'valign'     => 'bottom',
							'lineHeight' => 1.2,
							'maxLines'   => 3,
						)
					),
				),
			),
			'photo-card'      => array(
				__( 'Stock photo with card', 'artificial-image-generator' ),
				array(
					array(
						'type' => 'background',
						'fill' => array(
							'kind'   => 'image',
							'source' => 'stock',
							'query'  => '{title}',
							'fit'    => 'cover',
							'color'  => '#33475b',
						),
					),
					array(
						'type'   => 'shape',
						'shape'  => 'rect',
						'box'    => array(
							'x' => 80,
							'y' => 140,
							'w' => 640,
							'h' => 350,
						),
						'radius' => 18,
						'fill'   => array(
							'kind'  => 'solid',
							'color' => '#ffffff',
						),
					),
					$text(
						'{title}',
						array( 120, 180, 560, 230 ),
						array(
							'font'       => 'poppins-bold',
							'size'       => array(
								'max' => 40,
								'min' => 20,
							),
							'align'      => 'left',
							'valign'     => 'top',
							'lineHeight' => 1.25,
							'maxLines'   => 4,
							'color'      => '#1f2933',
						)
					),
					$text(
						'{site_name}',
						array( 120, 430, 560, 36 ),
						array(
							'font'  => 'poppins-regular',
							'size'  => array(
								'max' => 16,
								'min' => 10,
							),
							'align' => 'left',
							'color' => '#52606d',
						)
					),
				),
			),
		);

		$out = array();
		foreach ( $starters as $id => $starter ) {
			$out[ $id ] = array(
				$starter[0],
				Schema::sanitize(
					array(
						'canvas' => array(
							'width'  => 1200,
							'height' => 630,
						),
						'layers' => $starter[1],
					)
				),
			);
		}

		/**
		 * Filter the starter designs offered for new templates.
		 *
		 * @param array $starters Starters as ID => array( label, document ).
		 *
		 * @since 1.7.0
		 */
		return (array) apply_filters( 'aimg_template_starters', $out );
	}
}
