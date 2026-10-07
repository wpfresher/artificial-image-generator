/**
 * Inspector panels for each layer type.
 */
import {
	AdjustmentsField,
	ColorField,
	GradientField,
	MediaField,
	NumberField,
	Range,
	Select,
	Toggle,
	defaultGradient,
} from './fields';

import { FontField } from './FontField';

const { createElement: el, Fragment } = wp.element;
const { Button, PanelBody, TextareaControl } = wp.components;
const { __ } = wp.i18n;

const ANCHORS = {
	'top-left': __( 'Top left', 'artificial-image-generator' ),
	'top-center': __( 'Top center', 'artificial-image-generator' ),
	'top-right': __( 'Top right', 'artificial-image-generator' ),
	'left-center': __( 'Middle left', 'artificial-image-generator' ),
	'center-center': __( 'Center', 'artificial-image-generator' ),
	'right-center': __( 'Middle right', 'artificial-image-generator' ),
	'bottom-left': __( 'Bottom left', 'artificial-image-generator' ),
	'bottom-center': __( 'Bottom center', 'artificial-image-generator' ),
	'bottom-right': __( 'Bottom right', 'artificial-image-generator' ),
};

const panel = ( title, ...children ) =>
	el( PanelBody, { title, initialOpen: true }, ...children );

const imageSources = ( data, allowed ) => {
	const all = data.capabilities.imageSources;
	const out = {};
	allowed.forEach( ( key ) => {
		if ( all[ key ] ) {
			out[ key ] = all[ key ];
		}
	} );
	return out;
};

function BackgroundFields( { layer, update, data } ) {
	const fill = layer.fill;
	const swatches = [ data.settings.bgColor, data.settings.textColor ];
	const setFill = ( changes ) => update( { fill: { ...fill, ...changes } } );
	const changeKind = ( kind ) => {
		const color =
			fill.color || ( fill.colors || [] )[ 0 ] || data.settings.bgColor;
		if ( kind === 'solid' ) {
			update( { fill: { kind, color } } );
		} else if ( kind === 'palette' ) {
			update( {
				fill: { kind, colors: fill.colors || [ color, '#5f1e3a' ] },
			} );
		} else if ( kind === 'linear' || kind === 'radial' ) {
			update( {
				fill: {
					...defaultGradient( color ),
					...( fill.stops ? fill : {} ),
					kind,
				},
			} );
		} else {
			update( {
				fill: {
					kind: 'image',
					source: 'media',
					attachments: [],
					pick: 'random',
					fit: 'cover',
					focal: { x: 0.5, y: 0.5 },
					color,
				},
			} );
		}
	};

	return el(
		Fragment,
		null,
		panel(
			__( 'Fill', 'artificial-image-generator' ),
			el( Select, {
				label: __( 'Fill with', 'artificial-image-generator' ),
				value: fill.kind,
				choices: {
					solid: __( 'A color', 'artificial-image-generator' ),
					palette: __(
						'A random color from a palette',
						'artificial-image-generator'
					),
					linear: __( 'A gradient', 'artificial-image-generator' ),
					radial: __(
						'A radial gradient',
						'artificial-image-generator'
					),
					image: __( 'An image', 'artificial-image-generator' ),
				},
				onChange: changeKind,
			} ),
			fill.kind === 'solid' &&
				el( ColorField, {
					label: __( 'Color', 'artificial-image-generator' ),
					value: fill.color,
					swatches,
					onChange: ( color ) => setFill( { color } ),
				} ),
			fill.kind === 'palette' &&
				el(
					Fragment,
					null,
					el(
						'p',
						{ className: 'description' },
						__(
							'Each image gets one of these colors at random. The canvas shows the first.',
							'artificial-image-generator'
						)
					),
					fill.colors.map( ( color, index ) =>
						el(
							'div',
							{ key: index, className: 'aimg-studio__stop' },
							el( ColorField, {
								label:
									__(
										'Color',
										'artificial-image-generator'
									) +
									' ' +
									( index + 1 ),
								value: color,
								onChange: ( next ) =>
									setFill( {
										colors: fill.colors.map( ( c, i ) =>
											i === index ? next : c
										),
									} ),
							} ),
							fill.colors.length > 1 &&
								el(
									Button,
									{
										variant: 'link',
										isDestructive: true,
										onClick: () =>
											setFill( {
												colors: fill.colors.filter(
													( c, i ) => i !== index
												),
											} ),
									},
									__(
										'Remove color',
										'artificial-image-generator'
									)
								)
						)
					),
					fill.colors.length < 20 &&
						el(
							Button,
							{
								variant: 'secondary',
								onClick: () =>
									setFill( {
										colors: [ ...fill.colors, '#ffffff' ],
									} ),
							},
							__( 'Add color', 'artificial-image-generator' )
						)
				),
			( fill.kind === 'linear' || fill.kind === 'radial' ) &&
				el( GradientField, {
					value: fill,
					onChange: ( next ) => update( { fill: next } ),
				} ),
			fill.kind === 'image' &&
				el(
					Fragment,
					null,
					el( Select, {
						label: __( 'Image', 'artificial-image-generator' ),
						value: fill.source,
						choices: imageSources( data, [
							'media',
							'featured',
							'first',
							'site_logo',
							'site_icon',
						] ),
						onChange: ( source ) => setFill( { source } ),
					} ),
					fill.source === 'media' &&
						el( MediaField, {
							value: fill.attachments,
							onChange: ( attachments ) =>
								setFill( { attachments } ),
						} ),
					el( Select, {
						label: __( 'Fit', 'artificial-image-generator' ),
						value: fill.fit,
						choices: {
							cover: __(
								'Cover the canvas',
								'artificial-image-generator'
							),
							contain: __(
								'Show the whole image',
								'artificial-image-generator'
							),
							tile: __( 'Repeat', 'artificial-image-generator' ),
						},
						onChange: ( fit ) => setFill( { fit } ),
					} ),
					fill.fit === 'cover' &&
						el( Range, {
							label: __(
								'Focus (left to right)',
								'artificial-image-generator'
							),
							value: fill.focal.x,
							min: 0,
							max: 1,
							step: 0.05,
							onChange: ( x ) =>
								setFill( { focal: { ...fill.focal, x } } ),
						} ),
					fill.fit === 'cover' &&
						el( Range, {
							label: __(
								'Focus (top to bottom)',
								'artificial-image-generator'
							),
							value: fill.focal.y,
							min: 0,
							max: 1,
							step: 0.05,
							onChange: ( y ) =>
								setFill( { focal: { ...fill.focal, y } } ),
						} ),
					el( ColorField, {
						label: __(
							'Color behind the image',
							'artificial-image-generator'
						),
						value: fill.color,
						swatches,
						onChange: ( color ) => setFill( { color } ),
					} )
				)
		),
		panel(
			__( 'Adjustments', 'artificial-image-generator' ),
			el( AdjustmentsField, {
				value: layer.adjust,
				onChange: ( adjust ) => update( { adjust } ),
			} )
		)
	);
}

function ImageFields( { layer, update, data } ) {
	return el(
		Fragment,
		null,
		panel(
			__( 'Image', 'artificial-image-generator' ),
			el( Select, {
				label: __( 'Image', 'artificial-image-generator' ),
				value: layer.source,
				choices: imageSources(
					data,
					Object.keys( data.capabilities.imageSources )
				),
				onChange: ( source ) => update( { source } ),
			} ),
			layer.source === 'media' &&
				el( MediaField, {
					value: layer.attachments,
					onChange: ( attachments ) => update( { attachments } ),
				} ),
			layer.source === 'author_avatar' &&
				el(
					'p',
					{ className: 'description' },
					__(
						'Uses avatars uploaded to this site only; images are never fetched from Gravatar.',
						'artificial-image-generator'
					)
				),
			el( Select, {
				label: __( 'Fit', 'artificial-image-generator' ),
				value: layer.fit,
				choices: {
					contain: __(
						'Show the whole image',
						'artificial-image-generator'
					),
					cover: __( 'Fill the box', 'artificial-image-generator' ),
					fill: __( 'Stretch', 'artificial-image-generator' ),
				},
				onChange: ( fit ) => update( { fit } ),
			} ),
			layer.fit === 'contain' &&
				el( Select, {
					label: __(
						'Position in the box',
						'artificial-image-generator'
					),
					value: layer.anchor,
					choices: ANCHORS,
					onChange: ( anchor ) => update( { anchor } ),
				} ),
			el( Range, {
				label: __( 'Opacity', 'artificial-image-generator' ),
				value: layer.opacity,
				min: 0,
				max: 1,
				step: 0.05,
				onChange: ( opacity ) => update( { opacity } ),
			} ),
			el( Select, {
				label: __( 'Shape', 'artificial-image-generator' ),
				value: layer.mask,
				choices: {
					none: __( 'Rectangle', 'artificial-image-generator' ),
					rounded: __(
						'Rounded corners',
						'artificial-image-generator'
					),
					circle: __( 'Circle', 'artificial-image-generator' ),
				},
				onChange: ( mask ) => update( { mask } ),
			} ),
			layer.mask === 'rounded' &&
				el( Range, {
					label: __( 'Corner radius', 'artificial-image-generator' ),
					value: layer.radius,
					min: 0,
					max: Math.round( Math.min( layer.box.w, layer.box.h ) / 2 ),
					onChange: ( radius ) => update( { radius } ),
				} )
		),
		panel(
			__( 'Adjustments', 'artificial-image-generator' ),
			el( AdjustmentsField, {
				value: layer.adjust,
				onChange: ( adjust ) => update( { adjust } ),
			} )
		)
	);
}

function OverlayFields( { layer, update, data } ) {
	return panel(
		__( 'Overlay', 'artificial-image-generator' ),
		el( Select, {
			label: __( 'Style', 'artificial-image-generator' ),
			value: layer.kind || 'solid',
			choices: {
				solid: __( 'Color', 'artificial-image-generator' ),
				gradient: __( 'Gradient fade', 'artificial-image-generator' ),
			},
			onChange: ( kind ) =>
				update(
					kind === 'gradient'
						? {
								kind,
								gradient: layer.gradient || {
									...defaultGradient( '#000000', '#000000' ),
									angle: 180,
									stops: [
										{
											color: '#000000',
											pos: 0,
											opacity: 0,
										},
										{
											color: '#000000',
											pos: 1,
											opacity: 1,
										},
									],
								},
						  }
						: { kind }
				),
		} ),
		( layer.kind || 'solid' ) === 'solid' &&
			el( Toggle, {
				label: __(
					'Use the background color',
					'artificial-image-generator'
				),
				checked: layer.color === 'background',
				onChange: ( on ) =>
					update( { color: on ? 'background' : '#000000' } ),
			} ),
		( layer.kind || 'solid' ) === 'solid' &&
			layer.color !== 'background' &&
			el( ColorField, {
				label: __( 'Color', 'artificial-image-generator' ),
				value: layer.color,
				swatches: [ data.settings.bgColor ],
				onChange: ( color ) => update( { color } ),
			} ),
		layer.kind === 'gradient' &&
			el( GradientField, {
				value: layer.gradient,
				onChange: ( gradient ) => update( { gradient } ),
			} ),
		el( Range, {
			label: __( 'Opacity', 'artificial-image-generator' ),
			value: layer.opacity,
			min: 0,
			max: 1,
			step: 0.05,
			onChange: ( opacity ) => update( { opacity } ),
		} )
	);
}

function TextFields( { layer, update, data } ) {
	const set = ( key, changes ) =>
		update( { [ key ]: { ...layer[ key ], ...changes } } );
	const tags = Object.keys( data.capabilities.mergeTags )
		.map( ( tag ) => `{${ tag }}` )
		.join( ' ' );

	return el(
		Fragment,
		null,
		panel(
			__( 'Text', 'artificial-image-generator' ),
			el( TextareaControl, {
				label: __( 'Text', 'artificial-image-generator' ),
				help:
					__(
						'Tags are replaced for each post:',
						'artificial-image-generator'
					) +
					' ' +
					tags +
					' {custom_field:key}',
				value: layer.content,
				onChange: ( content ) => update( { content } ),
				__nextHasNoMarginBottom: true,
			} ),
			el( FontField, {
				value: layer.font,
				onChange: ( font ) => update( { font } ),
			} ),
			el( ColorField, {
				label: __( 'Color', 'artificial-image-generator' ),
				value: layer.color,
				swatches: [ data.settings.textColor, '#ffffff', '#000000' ],
				onChange: ( color ) => update( { color } ),
			} ),
			el( Range, {
				label: __( 'Opacity', 'artificial-image-generator' ),
				value: layer.opacity,
				min: 0,
				max: 1,
				step: 0.05,
				onChange: ( opacity ) => update( { opacity } ),
			} ),
			el( Select, {
				label: __( 'Letter case', 'artificial-image-generator' ),
				value: layer.transform,
				choices: {
					none: __( 'As written', 'artificial-image-generator' ),
					upper: __( 'UPPERCASE', 'artificial-image-generator' ),
					lower: __( 'lowercase', 'artificial-image-generator' ),
					title: __( 'Title Case', 'artificial-image-generator' ),
				},
				onChange: ( transform ) => update( { transform } ),
			} )
		),
		panel(
			__( 'Size and layout', 'artificial-image-generator' ),
			el(
				'div',
				{ className: 'aimg-studio__row' },
				el( NumberField, {
					label: __( 'Largest size', 'artificial-image-generator' ),
					value: layer.size.max,
					min: 1,
					onChange: ( max ) =>
						update( {
							size: { max, min: Math.min( layer.size.min, max ) },
						} ),
				} ),
				el( NumberField, {
					label: __( 'Smallest size', 'artificial-image-generator' ),
					value: layer.size.min,
					min: 1,
					onChange: ( min ) =>
						update( {
							size: {
								...layer.size,
								min: Math.min( min, layer.size.max ),
							},
						} ),
				} )
			),
			el( Select, {
				label: __(
					'When the text is long',
					'artificial-image-generator'
				),
				value: layer.fit,
				choices: {
					box: __(
						'Shrink it to fit the box',
						'artificial-image-generator'
					),
					width: __(
						'Only shrink words that are too wide',
						'artificial-image-generator'
					),
				},
				onChange: ( fit ) => update( { fit } ),
			} ),
			el( NumberField, {
				label: __(
					'Most lines (0 for no limit)',
					'artificial-image-generator'
				),
				value: layer.maxLines,
				onChange: ( maxLines ) =>
					update( { maxLines: Math.round( maxLines ) } ),
			} ),
			el(
				'div',
				{ className: 'aimg-studio__row' },
				el( Select, {
					label: __( 'Align', 'artificial-image-generator' ),
					value: layer.align,
					choices: {
						left: __( 'Left', 'artificial-image-generator' ),
						center: __( 'Center', 'artificial-image-generator' ),
						right: __( 'Right', 'artificial-image-generator' ),
					},
					onChange: ( align ) => update( { align } ),
				} ),
				el( Select, {
					label: __( 'Vertical', 'artificial-image-generator' ),
					value: layer.valign,
					choices: {
						top: __( 'Top', 'artificial-image-generator' ),
						middle: __( 'Middle', 'artificial-image-generator' ),
						bottom: __( 'Bottom', 'artificial-image-generator' ),
					},
					onChange: ( valign ) => update( { valign } ),
				} )
			),
			el( Range, {
				label: __( 'Line height', 'artificial-image-generator' ),
				value: layer.lineHeight,
				min: 0.5,
				max: 4,
				step: 0.05,
				onChange: ( lineHeight ) => update( { lineHeight } ),
			} ),
			el( Range, {
				label: __( 'Letter spacing', 'artificial-image-generator' ),
				value: layer.letterSpacing,
				min: -10,
				max: 50,
				onChange: ( letterSpacing ) => update( { letterSpacing } ),
			} )
		),
		panel(
			__( 'Outline and shadow', 'artificial-image-generator' ),
			el( Range, {
				label: __( 'Outline width', 'artificial-image-generator' ),
				value: layer.stroke.width,
				min: 0,
				max: 20,
				onChange: ( width ) => set( 'stroke', { width } ),
			} ),
			layer.stroke.width > 0 &&
				el( ColorField, {
					label: __( 'Outline color', 'artificial-image-generator' ),
					value: layer.stroke.color,
					onChange: ( color ) => set( 'stroke', { color } ),
				} ),
			el( Range, {
				label: __( 'Shadow opacity', 'artificial-image-generator' ),
				value: layer.shadow.opacity,
				min: 0,
				max: 1,
				step: 0.05,
				onChange: ( opacity ) => set( 'shadow', { opacity } ),
			} ),
			layer.shadow.opacity > 0 &&
				el(
					Fragment,
					null,
					el(
						'div',
						{ className: 'aimg-studio__row' },
						el( NumberField, {
							label: __(
								'Shadow X',
								'artificial-image-generator'
							),
							value: layer.shadow.x,
							min: -50,
							onChange: ( x ) => set( 'shadow', { x } ),
						} ),
						el( NumberField, {
							label: __(
								'Shadow Y',
								'artificial-image-generator'
							),
							value: layer.shadow.y,
							min: -50,
							onChange: ( y ) => set( 'shadow', { y } ),
						} )
					),
					el( Range, {
						label: __(
							'Shadow blur',
							'artificial-image-generator'
						),
						value: layer.shadow.blur,
						min: 0,
						max: 10,
						onChange: ( blur ) => set( 'shadow', { blur } ),
					} ),
					el( ColorField, {
						label: __(
							'Shadow color',
							'artificial-image-generator'
						),
						value: layer.shadow.color,
						onChange: ( color ) => set( 'shadow', { color } ),
					} )
				)
		),
		panel(
			__( 'Highlight', 'artificial-image-generator' ),
			el( Select, {
				label: __(
					'Box behind the text',
					'artificial-image-generator'
				),
				value: layer.highlight.mode,
				choices: {
					none: __( 'None', 'artificial-image-generator' ),
					block: __(
						'Around all lines',
						'artificial-image-generator'
					),
					lines: __(
						'Behind each line',
						'artificial-image-generator'
					),
				},
				onChange: ( mode ) => set( 'highlight', { mode } ),
			} ),
			layer.highlight.mode !== 'none' &&
				el(
					Fragment,
					null,
					el( ColorField, {
						label: __( 'Box color', 'artificial-image-generator' ),
						value: layer.highlight.color,
						onChange: ( color ) => set( 'highlight', { color } ),
					} ),
					el( Range, {
						label: __(
							'Box opacity',
							'artificial-image-generator'
						),
						value: layer.highlight.opacity,
						min: 0,
						max: 1,
						step: 0.05,
						onChange: ( opacity ) =>
							set( 'highlight', { opacity } ),
					} ),
					el( Range, {
						label: __( 'Padding', 'artificial-image-generator' ),
						value: layer.highlight.padding,
						min: 0,
						max: 100,
						onChange: ( padding ) =>
							set( 'highlight', { padding } ),
					} ),
					el( Range, {
						label: __(
							'Corner radius',
							'artificial-image-generator'
						),
						value: layer.highlight.radius,
						min: 0,
						max: 100,
						onChange: ( radius ) => set( 'highlight', { radius } ),
					} )
				)
		)
	);
}

function ShapeFields( { layer, update } ) {
	const fill = layer.fill;
	const gradient = fill.kind === 'linear' || fill.kind === 'radial';

	return panel(
		__( 'Shape', 'artificial-image-generator' ),
		el( Select, {
			label: __( 'Shape', 'artificial-image-generator' ),
			value: layer.shape,
			choices: {
				rect: __( 'Rectangle', 'artificial-image-generator' ),
				ellipse: __( 'Ellipse', 'artificial-image-generator' ),
				line: __( 'Line', 'artificial-image-generator' ),
			},
			onChange: ( shape ) => update( { shape } ),
		} ),
		el( Select, {
			label: __( 'Fill', 'artificial-image-generator' ),
			value: fill.kind,
			choices: {
				solid: __( 'Color', 'artificial-image-generator' ),
				linear: __( 'Gradient', 'artificial-image-generator' ),
				radial: __( 'Radial gradient', 'artificial-image-generator' ),
				none: __( 'None', 'artificial-image-generator' ),
			},
			onChange: ( kind ) => {
				if ( kind === 'linear' || kind === 'radial' ) {
					update( {
						fill: {
							...defaultGradient(
								fill.color || '#ffffff',
								'#f09819'
							),
							...( gradient ? fill : {} ),
							kind,
						},
					} );
				} else {
					update( {
						fill: {
							kind,
							color: fill.color || '#ffffff',
							opacity: fill.opacity ?? 1,
						},
					} );
				}
			},
		} ),
		fill.kind === 'solid' &&
			el( ColorField, {
				label: __( 'Color', 'artificial-image-generator' ),
				value: fill.color,
				onChange: ( color ) => update( { fill: { ...fill, color } } ),
			} ),
		fill.kind === 'solid' &&
			el( Range, {
				label: __( 'Opacity', 'artificial-image-generator' ),
				value: fill.opacity,
				min: 0,
				max: 1,
				step: 0.05,
				onChange: ( opacity ) =>
					update( { fill: { ...fill, opacity } } ),
			} ),
		gradient &&
			el( GradientField, {
				value: fill,
				onChange: ( next ) => update( { fill: next } ),
			} ),
		layer.shape === 'line'
			? el( Range, {
					label: __( 'Thickness', 'artificial-image-generator' ),
					value: layer.thickness,
					min: 1,
					max: 100,
					onChange: ( thickness ) => update( { thickness } ),
			  } )
			: el(
					Fragment,
					null,
					layer.shape === 'rect' &&
						el( Range, {
							label: __(
								'Corner radius',
								'artificial-image-generator'
							),
							value: layer.radius,
							min: 0,
							max: Math.round(
								Math.min( layer.box.w, layer.box.h ) / 2
							),
							onChange: ( radius ) => update( { radius } ),
						} ),
					el( Range, {
						label: __(
							'Border width',
							'artificial-image-generator'
						),
						value: layer.border.width,
						min: 0,
						max: 50,
						onChange: ( width ) =>
							update( { border: { ...layer.border, width } } ),
					} ),
					layer.border.width > 0 &&
						el( ColorField, {
							label: __(
								'Border color',
								'artificial-image-generator'
							),
							value: layer.border.color,
							onChange: ( color ) =>
								update( {
									border: { ...layer.border, color },
								} ),
						} )
			  )
	);
}

function PatternFields( { layer, update } ) {
	return panel(
		__( 'Pattern', 'artificial-image-generator' ),
		el( Select, {
			label: __( 'Pattern', 'artificial-image-generator' ),
			value: layer.pattern,
			choices: {
				dots: __( 'Dots', 'artificial-image-generator' ),
				lines: __( 'Lines', 'artificial-image-generator' ),
				diagonal: __( 'Diagonal lines', 'artificial-image-generator' ),
				grid: __( 'Grid', 'artificial-image-generator' ),
				noise: __( 'Noise', 'artificial-image-generator' ),
			},
			onChange: ( pattern ) => update( { pattern } ),
		} ),
		el( ColorField, {
			label: __( 'Color', 'artificial-image-generator' ),
			value: layer.color,
			onChange: ( color ) => update( { color } ),
		} ),
		el( Range, {
			label: __( 'Opacity', 'artificial-image-generator' ),
			value: layer.opacity,
			min: 0,
			max: 1,
			step: 0.05,
			onChange: ( opacity ) => update( { opacity } ),
		} ),
		layer.pattern !== 'noise' &&
			el( Range, {
				label: __( 'Spacing', 'artificial-image-generator' ),
				value: layer.spacing,
				min: 4,
				max: 200,
				onChange: ( spacing ) => update( { spacing } ),
			} ),
		el( Range, {
			label:
				layer.pattern === 'noise'
					? __( 'Amount', 'artificial-image-generator' )
					: __( 'Size', 'artificial-image-generator' ),
			value: layer.size,
			min: 1,
			max: layer.pattern === 'noise' ? 10 : 30,
			onChange: ( size ) => update( { size } ),
		} )
	);
}

function FrameFields( { layer, update } ) {
	return panel(
		__( 'Frame', 'artificial-image-generator' ),
		el( Range, {
			label: __( 'Width', 'artificial-image-generator' ),
			value: layer.width,
			min: 1,
			max: 200,
			onChange: ( width ) => update( { width } ),
		} ),
		el( ColorField, {
			label: __( 'Color', 'artificial-image-generator' ),
			value: layer.color,
			onChange: ( color ) => update( { color } ),
		} ),
		el( Range, {
			label: __( 'Opacity', 'artificial-image-generator' ),
			value: layer.opacity,
			min: 0,
			max: 1,
			step: 0.05,
			onChange: ( opacity ) => update( { opacity } ),
		} ),
		el( Range, {
			label: __( 'Distance from the edge', 'artificial-image-generator' ),
			value: layer.inset,
			min: 0,
			max: 200,
			onChange: ( inset ) => update( { inset } ),
		} ),
		el( Range, {
			label: __( 'Corner radius', 'artificial-image-generator' ),
			value: layer.radius,
			min: 0,
			max: 200,
			onChange: ( radius ) => update( { radius } ),
		} )
	);
}

export const TYPE_FIELDS = {
	background: BackgroundFields,
	image: ImageFields,
	overlay: OverlayFields,
	text: TextFields,
	shape: ShapeFields,
	pattern: PatternFields,
	frame: FrameFields,
};
