/**
 * Inspector: the selected layer's settings, or the canvas settings when nothing is selected.
 */
import { STORE } from '../store';
import { LABELS } from '../layers';
import { BOXED } from '../canvas/draw';
import { TYPE_FIELDS } from './LayerFields';
import { ShowIfField } from './fields';
import { UploadedFonts } from './FontField';

const { createElement: el, Fragment } = wp.element;
const { PanelBody, TextControl, SelectControl, ToggleControl, RangeControl } =
	wp.components;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;

export const SIZE_PRESETS = {
	'1200x800': __(
		'Featured image (1200 × 800)',
		'artificial-image-generator'
	),
	'1200x630': __( 'Open Graph (1200 × 630)', 'artificial-image-generator' ),
	'1920x1080': __( '16:9 (1920 × 1080)', 'artificial-image-generator' ),
	'1080x1080': __( 'Square (1080 × 1080)', 'artificial-image-generator' ),
	'1000x1500': __( 'Pinterest (1000 × 1500)', 'artificial-image-generator' ),
	'1080x1920': __( 'Story (1080 × 1920)', 'artificial-image-generator' ),
};

export const numberControl = ( label, value, onChange, props = {} ) =>
	el( TextControl, {
		label,
		type: 'number',
		value: String( value ),
		onChange: ( next ) => {
			const number = parseFloat( next );
			if ( ! Number.isNaN( number ) ) {
				onChange( number );
			}
		},
		__nextHasNoMarginBottom: true,
		...props,
	} );

function CanvasSettings( { data } ) {
	const doc = useSelect( ( select ) => select( STORE ).getDocument(), [] );
	const { updateCanvas, updateOutput } = useDispatch( STORE );
	const { canvas, output } = doc;
	const preset = `${ canvas.width }x${ canvas.height }`;

	return el(
		PanelBody,
		{
			title: __( 'Canvas', 'artificial-image-generator' ),
			initialOpen: true,
		},
		el( SelectControl, {
			label: __( 'Size', 'artificial-image-generator' ),
			value: SIZE_PRESETS[ preset ] ? preset : 'custom',
			options: [
				...Object.keys( SIZE_PRESETS ).map( ( value ) => ( {
					value,
					label: SIZE_PRESETS[ value ],
				} ) ),
				{
					value: 'custom',
					label: __( 'Custom', 'artificial-image-generator' ),
				},
			],
			onChange: ( value ) => {
				if ( value !== 'custom' ) {
					const [ width, height ] = value.split( 'x' ).map( Number );
					updateCanvas( { width, height } );
				}
			},
			__nextHasNoMarginBottom: true,
		} ),
		el(
			'div',
			{ className: 'aimg-studio__row' },
			numberControl(
				__( 'Width', 'artificial-image-generator' ),
				canvas.width,
				( width ) => updateCanvas( { width: Math.round( width ) } )
			),
			numberControl(
				__( 'Height', 'artificial-image-generator' ),
				canvas.height,
				( height ) => updateCanvas( { height: Math.round( height ) } )
			)
		),
		el( TextControl, {
			label: __( 'Canvas color', 'artificial-image-generator' ),
			value: canvas.background,
			onChange: ( background ) => updateCanvas( { background } ),
			__nextHasNoMarginBottom: true,
		} ),
		el( SelectControl, {
			label: __( 'File format', 'artificial-image-generator' ),
			value: output.format,
			options: Object.keys( data.capabilities.outputFormats ).map(
				( value ) => ( {
					value,
					label: data.capabilities.outputFormats[ value ],
				} )
			),
			onChange: ( format ) => updateOutput( { format } ),
			__nextHasNoMarginBottom: true,
		} ),
		output.format !== 'png' &&
			el( RangeControl, {
				label: __( 'Quality', 'artificial-image-generator' ),
				value: output.quality,
				min: 1,
				max: 100,
				onChange: ( quality ) => updateOutput( { quality } ),
				__nextHasNoMarginBottom: true,
			} ),
		el( UploadedFonts )
	);
}

function LayerSettings( { layer, data } ) {
	const { updateLayer } = useDispatch( STORE );
	const update = ( changes ) => updateLayer( layer.id, changes );
	const box = layer.box;

	return el(
		Fragment,
		null,
		el(
			PanelBody,
			{
				title: LABELS[ layer.type ] || layer.type,
				initialOpen: true,
			},
			el( TextControl, {
				label: __( 'Layer name', 'artificial-image-generator' ),
				value: layer.name,
				onChange: ( name ) => update( { name } ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ToggleControl, {
				label: __( 'Visible', 'artificial-image-generator' ),
				checked: layer.visible,
				onChange: ( visible ) => update( { visible } ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ShowIfField, {
				value: layer.showIf,
				mergeTags: data.capabilities.mergeTags,
				onChange: ( showIf ) => update( { showIf } ),
			} )
		),
		TYPE_FIELDS[ layer.type ] &&
			el( TYPE_FIELDS[ layer.type ], { layer, update, data } ),
		BOXED.includes( layer.type ) &&
			box &&
			el(
				PanelBody,
				{
					title: __( 'Position', 'artificial-image-generator' ),
					initialOpen: true,
				},
				el(
					'div',
					{ className: 'aimg-studio__row' },
					numberControl( 'X', box.x, ( x ) =>
						update( { box: { ...box, x: Math.round( x ) } } )
					),
					numberControl( 'Y', box.y, ( y ) =>
						update( { box: { ...box, y: Math.round( y ) } } )
					)
				),
				el(
					'div',
					{ className: 'aimg-studio__row' },
					numberControl(
						__( 'Width', 'artificial-image-generator' ),
						box.w,
						( w ) =>
							update( {
								box: {
									...box,
									w: Math.max( 1, Math.round( w ) ),
								},
							} )
					),
					numberControl(
						__( 'Height', 'artificial-image-generator' ),
						box.h,
						( h ) =>
							update( {
								box: {
									...box,
									h: Math.max( 1, Math.round( h ) ),
								},
							} )
					)
				),
				el( RangeControl, {
					label: __( 'Rotation', 'artificial-image-generator' ),
					value: layer.rotation || 0,
					min: -180,
					max: 180,
					onChange: ( rotation ) => update( { rotation } ),
					__nextHasNoMarginBottom: true,
				} )
			)
	);
}

export default function Inspector( { data } ) {
	const layer = useSelect(
		( select ) => select( STORE ).getSelectedLayer(),
		[]
	);

	return el(
		'div',
		{ className: 'aimg-studio__inspector' },
		layer
			? el( LayerSettings, { layer, data } )
			: el( CanvasSettings, { data } )
	);
}
