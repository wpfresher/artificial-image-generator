/**
 * Font picker with the scripts each font covers, font upload, and a list of
 * uploaded fonts that can be removed.
 */
import { STORE } from '../store';
import { deleteFont, uploadFont } from '../api';

const { createElement: el, Fragment, useRef, useState } = wp.element;
const { BaseControl, Button, Notice, SelectControl, Spinner } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { __, sprintf } = wp.i18n;

const SCRIPTS = {
	latin: __( 'Latin', 'artificial-image-generator' ),
	cyrillic: __( 'Cyrillic', 'artificial-image-generator' ),
	greek: __( 'Greek', 'artificial-image-generator' ),
	vietnamese: __( 'Vietnamese', 'artificial-image-generator' ),
	devanagari: __( 'Devanagari', 'artificial-image-generator' ),
};

const describe = ( font ) => {
	if ( font.uploaded ) {
		return sprintf(
			/* translators: %s: font name */
			__( '%s (uploaded)', 'artificial-image-generator' ),
			font.label
		);
	}
	const scripts = ( font.scripts || [] )
		.map( ( script ) => SCRIPTS[ script ] || script )
		.join( ', ' );
	return scripts ? `${ font.label } — ${ scripts }` : font.label;
};

export function FontField( { value, onChange } ) {
	const fonts = useSelect( ( select ) => select( STORE ).getFonts(), [] );
	const { setFonts } = useDispatch( STORE );
	const input = useRef( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const upload = ( event ) => {
		const file = event.target.files[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		setBusy( true );
		setError( '' );
		uploadFont( file )
			.then( ( result ) => {
				setFonts( result.fonts );
				onChange( result.id );
			} )
			.catch( ( err ) => setError( err.message ) )
			.finally( () => setBusy( false ) );
	};

	return el(
		Fragment,
		null,
		el( SelectControl, {
			label: __( 'Font', 'artificial-image-generator' ),
			value,
			options: fonts.map( ( font ) => ( {
				value: font.id,
				label: describe( font ),
			} ) ),
			onChange,
			__nextHasNoMarginBottom: true,
		} ),
		el(
			BaseControl,
			{
				help: __(
					'Use a .ttf or .otf file you are allowed to use. Web fonts (.woff, .woff2) cannot draw images.',
					'artificial-image-generator'
				),
				__nextHasNoMarginBottom: true,
			},
			el( 'input', {
				ref: input,
				type: 'file',
				accept: '.ttf,.otf',
				hidden: true,
				onChange: upload,
			} ),
			el(
				Button,
				{
					variant: 'secondary',
					disabled: busy,
					onClick: () => input.current.click(),
				},
				__( 'Upload a font', 'artificial-image-generator' )
			),
			busy && el( Spinner )
		),
		error &&
			el(
				Notice,
				{ status: 'error', onRemove: () => setError( '' ) },
				error
			)
	);
}

export function UploadedFonts() {
	const fonts = useSelect( ( select ) => select( STORE ).getFonts(), [] );
	const { setFonts } = useDispatch( STORE );
	const [ busy, setBusy ] = useState( '' );
	const uploaded = fonts.filter( ( font ) => font.uploaded );

	if ( ! uploaded.length ) {
		return null;
	}

	const remove = ( id ) => {
		setBusy( id );
		deleteFont( id )
			.then( ( result ) => setFonts( result.fonts ) )
			.finally( () => setBusy( '' ) );
	};

	return el(
		BaseControl,
		{
			label: __( 'Uploaded fonts', 'artificial-image-generator' ),
			help: __(
				'Text using a deleted font switches to Roboto Bold.',
				'artificial-image-generator'
			),
			__nextHasNoMarginBottom: true,
		},
		el(
			'ul',
			{ className: 'aimg-studio__font-list' },
			uploaded.map( ( font ) =>
				el(
					'li',
					{ key: font.id, className: 'aimg-studio__font' },
					el( 'span', null, font.label ),
					el(
						Button,
						{
							variant: 'link',
							isDestructive: true,
							disabled: busy === font.id,
							onClick: () => remove( font.id ),
						},
						__( 'Delete', 'artificial-image-generator' )
					)
				)
			)
		)
	);
}
