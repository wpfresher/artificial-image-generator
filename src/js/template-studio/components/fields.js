/**
 * Inspector controls shared by the layer types. They only use components that
 * exist in every supported WordPress version (6.0+).
 */
import { attachmentUrl } from '../api';

const { createElement: el, Fragment, useEffect, useState } = wp.element;
const {
	BaseControl,
	Button,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} = wp.components;
const { __, sprintf } = wp.i18n;

const HEX = /^#[0-9a-f]{6}$/i;

export const options = ( map ) =>
	Object.keys( map ).map( ( value ) => ( { value, label: map[ value ] } ) );

export function ColorField( { label, value, onChange, swatches = [] } ) {
	const [ draft, setDraft ] = useState( value );
	useEffect( () => setDraft( value ), [ value ] );

	return el(
		BaseControl,
		{
			label,
			className: 'aimg-studio__color',
			__nextHasNoMarginBottom: true,
		},
		el(
			'div',
			{ className: 'aimg-studio__color-row' },
			el( 'input', {
				type: 'color',
				value: HEX.test( value ) ? value : '#000000',
				'aria-label': label,
				onChange: ( event ) => onChange( event.target.value ),
			} ),
			el( 'input', {
				type: 'text',
				className: 'aimg-studio__hex',
				value: draft,
				maxLength: 7,
				'aria-label': sprintf(
					/* translators: %s: control label */
					__( '%s (hex)', 'artificial-image-generator' ),
					label
				),
				onChange: ( event ) => {
					setDraft( event.target.value );
					if ( HEX.test( event.target.value ) ) {
						onChange( event.target.value.toLowerCase() );
					}
				},
			} ),
			swatches
				.filter( ( swatch ) => HEX.test( swatch ) )
				.map( ( swatch ) =>
					el( 'button', {
						key: swatch,
						type: 'button',
						className: 'aimg-studio__swatch',
						style: { background: swatch },
						title: swatch,
						'aria-label': swatch,
						onClick: () => onChange( swatch ),
					} )
				)
		)
	);
}

export const Range = ( { label, value, onChange, min, max, step = 1, help } ) =>
	el( RangeControl, {
		label,
		help,
		value: Number( value ),
		min,
		max,
		step,
		onChange: ( next ) => onChange( next ?? min ),
		__nextHasNoMarginBottom: true,
	} );

export const Select = ( { label, value, onChange, choices, help } ) =>
	el( SelectControl, {
		label,
		help,
		value,
		options: Array.isArray( choices ) ? choices : options( choices ),
		onChange,
		__nextHasNoMarginBottom: true,
	} );

export const Toggle = ( { label, checked, onChange, help } ) =>
	el( ToggleControl, {
		label,
		help,
		checked: !! checked,
		onChange,
		__nextHasNoMarginBottom: true,
	} );

export const NumberField = ( { label, value, onChange, min = 0 } ) =>
	el( TextControl, {
		label,
		type: 'number',
		min,
		value: String( value ),
		onChange: ( next ) => {
			const number = parseFloat( next );
			if ( ! Number.isNaN( number ) ) {
				onChange( Math.max( min, number ) );
			}
		},
		__nextHasNoMarginBottom: true,
	} );

/**
 * Gradient: kind, angle or center, and up to eight color stops.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Gradient.
 * @param {Function} props.onChange Called with the new gradient.
 * @return {Element} Element.
 */
export function GradientField( { value, onChange } ) {
	const set = ( changes ) => onChange( { ...value, ...changes } );
	const setStop = ( index, changes ) =>
		set( {
			stops: value.stops.map( ( stop, i ) =>
				i === index ? { ...stop, ...changes } : stop
			),
		} );

	return el(
		Fragment,
		null,
		el( Select, {
			label: __( 'Gradient', 'artificial-image-generator' ),
			value: value.kind,
			choices: {
				linear: __( 'Linear', 'artificial-image-generator' ),
				radial: __( 'Radial', 'artificial-image-generator' ),
			},
			onChange: ( kind ) => set( { kind } ),
		} ),
		value.kind === 'radial'
			? el(
					Fragment,
					null,
					el( Range, {
						label: __(
							'Center (left to right)',
							'artificial-image-generator'
						),
						value: value.cx,
						min: 0,
						max: 1,
						step: 0.05,
						onChange: ( cx ) => set( { cx } ),
					} ),
					el( Range, {
						label: __(
							'Center (top to bottom)',
							'artificial-image-generator'
						),
						value: value.cy,
						min: 0,
						max: 1,
						step: 0.05,
						onChange: ( cy ) => set( { cy } ),
					} )
			  )
			: el( Range, {
					label: __( 'Angle', 'artificial-image-generator' ),
					value: value.angle,
					min: 0,
					max: 360,
					onChange: ( angle ) => set( { angle } ),
			  } ),
		value.stops.map( ( stop, index ) =>
			el(
				'div',
				{ key: index, className: 'aimg-studio__stop' },
				el( ColorField, {
					label: sprintf(
						/* translators: %d: color stop number */
						__( 'Color %d', 'artificial-image-generator' ),
						index + 1
					),
					value: stop.color,
					onChange: ( color ) => setStop( index, { color } ),
				} ),
				el( Range, {
					label: __( 'Position', 'artificial-image-generator' ),
					value: stop.pos,
					min: 0,
					max: 1,
					step: 0.05,
					onChange: ( pos ) => setStop( index, { pos } ),
				} ),
				el( Range, {
					label: __( 'Opacity', 'artificial-image-generator' ),
					value: stop.opacity,
					min: 0,
					max: 1,
					step: 0.05,
					onChange: ( opacity ) => setStop( index, { opacity } ),
				} ),
				value.stops.length > 2 &&
					el(
						Button,
						{
							variant: 'link',
							isDestructive: true,
							onClick: () =>
								set( {
									stops: value.stops.filter(
										( s, i ) => i !== index
									),
								} ),
						},
						__( 'Remove color', 'artificial-image-generator' )
					)
			)
		),
		value.stops.length < 8 &&
			el(
				Button,
				{
					variant: 'secondary',
					onClick: () =>
						set( {
							stops: [
								...value.stops,
								{ color: '#ffffff', pos: 1, opacity: 1 },
							],
						} ),
				},
				__( 'Add color', 'artificial-image-generator' )
			)
	);
}

export const defaultGradient = ( from = '#1e3a5f', to = '#5f1e3a' ) => ( {
	kind: 'linear',
	angle: 135,
	cx: 0.5,
	cy: 0.5,
	stops: [
		{ color: from, pos: 0, opacity: 1 },
		{ color: to, pos: 1, opacity: 1 },
	],
} );

function Thumb( { id } ) {
	const [ url, setUrl ] = useState( '' );
	useEffect( () => {
		attachmentUrl( id ).then( setUrl );
	}, [ id ] );
	return url ? el( 'img', { src: url, alt: '' } ) : el( 'span', null, '…' );
}

/**
 * Pick images from the Media Library.
 *
 * @param {Object}   props          Props.
 * @param {number[]} props.value    Attachment IDs.
 * @param {Function} props.onChange Called with the new IDs.
 * @param {boolean}  props.multiple Allow several images.
 * @return {Element} Element.
 */
export function MediaField( { value, onChange, multiple = true } ) {
	const open = () => {
		if ( ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( {
			title: __( 'Choose images', 'artificial-image-generator' ),
			multiple: multiple ? 'add' : false,
			library: { type: 'image' },
		} );
		frame.on( 'open', () => {
			const selection = frame.state().get( 'selection' );
			value.forEach( ( id ) => {
				const attachment = window.wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment );
			} );
		} );
		frame.on( 'select', () =>
			onChange(
				frame
					.state()
					.get( 'selection' )
					.map( ( attachment ) => attachment.id )
			)
		);
		frame.open();
	};

	return el(
		BaseControl,
		{
			label: multiple
				? __( 'Images', 'artificial-image-generator' )
				: __( 'Image', 'artificial-image-generator' ),
			__nextHasNoMarginBottom: true,
		},
		el(
			'div',
			{ className: 'aimg-studio__thumbs' },
			value.map( ( id ) => el( Thumb, { key: id, id } ) )
		),
		el(
			Button,
			{ variant: 'secondary', onClick: open },
			value.length
				? __( 'Change images', 'artificial-image-generator' )
				: __( 'Choose images', 'artificial-image-generator' )
		),
		value.length > 1 &&
			el(
				'p',
				{ className: 'description' },
				__(
					'One of these is picked at random for each image.',
					'artificial-image-generator'
				)
			)
	);
}

export const EMPTY_ADJUST = {
	brightness: 0,
	contrast: 0,
	blur: 0,
	grayscale: false,
	duotone: null,
};

export function AdjustmentsField( { value, onChange } ) {
	const adjust = { ...EMPTY_ADJUST, ...( value || {} ) };
	const set = ( changes ) => onChange( { ...adjust, ...changes } );

	return el(
		Fragment,
		null,
		el( Range, {
			label: __( 'Brightness', 'artificial-image-generator' ),
			value: adjust.brightness,
			min: -100,
			max: 100,
			onChange: ( brightness ) => set( { brightness } ),
		} ),
		el( Range, {
			label: __( 'Contrast', 'artificial-image-generator' ),
			value: adjust.contrast,
			min: -100,
			max: 100,
			onChange: ( contrast ) => set( { contrast } ),
		} ),
		el( Range, {
			label: __( 'Blur', 'artificial-image-generator' ),
			value: adjust.blur,
			min: 0,
			max: 10,
			onChange: ( blur ) => set( { blur } ),
		} ),
		el( Toggle, {
			label: __( 'Black and white', 'artificial-image-generator' ),
			checked: adjust.grayscale,
			onChange: ( grayscale ) => set( { grayscale } ),
		} ),
		el( Toggle, {
			label: __( 'Duotone', 'artificial-image-generator' ),
			checked: !! adjust.duotone,
			onChange: ( on ) =>
				set( {
					duotone: on ? { dark: '#1b1b3a', light: '#ffcc66' } : null,
				} ),
		} ),
		adjust.duotone &&
			el(
				Fragment,
				null,
				el( ColorField, {
					label: __( 'Shadows', 'artificial-image-generator' ),
					value: adjust.duotone.dark,
					onChange: ( dark ) =>
						set( { duotone: { ...adjust.duotone, dark } } ),
				} ),
				el( ColorField, {
					label: __( 'Highlights', 'artificial-image-generator' ),
					value: adjust.duotone.light,
					onChange: ( light ) =>
						set( { duotone: { ...adjust.duotone, light } } ),
				} )
			)
	);
}

export function ShowIfField( { value, onChange, mergeTags } ) {
	return el( Select, {
		label: __( 'Show only when', 'artificial-image-generator' ),
		help: __(
			'Hide this layer when the post has no value for the tag.',
			'artificial-image-generator'
		),
		value: value || '',
		choices: [
			{
				value: '',
				label: __( 'Always show', 'artificial-image-generator' ),
			},
			...Object.keys( mergeTags ).map( ( tag ) => ( {
				value: tag,
				label: sprintf(
					/* translators: %s: merge tag label, e.g. "Categories" */
					__( '%s is not empty', 'artificial-image-generator' ),
					mergeTags[ tag ]
				),
			} ) ),
		],
		onChange,
	} );
}
