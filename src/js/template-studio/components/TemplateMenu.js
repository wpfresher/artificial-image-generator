/**
 * Toolbar menu: export the design as JSON, or import one.
 */
import { STORE } from '../store';
import { sanitizeDocument } from '../api';

const { createElement: el, Fragment, useRef } = wp.element;
const { DropdownMenu } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;

export default function TemplateMenu( { document: doc } ) {
	const title = useSelect(
		( select ) => select( STORE ).getState().title,
		[]
	);
	const { setDocument, setTitle, setNotice } = useDispatch( STORE );
	const input = useRef( null );

	const exportJson = () => {
		const data = { aimgTemplate: 2, title, document: doc };
		const blob = new window.Blob( [ JSON.stringify( data, null, 2 ) ], {
			type: 'application/json',
		} );
		const link = window.document.createElement( 'a' );
		link.href = window.URL.createObjectURL( blob );
		link.download =
			( title || 'template' )
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-|-$/g, '' ) + '.json';
		link.click();
		window.URL.revokeObjectURL( link.href );
	};

	const importJson = ( event ) => {
		const file = event.target.files[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		const fail = () =>
			setNotice( {
				status: 'error',
				text: __(
					'This file is not an exported template.',
					'artificial-image-generator'
				),
			} );
		file.text()
			.then( ( text ) => {
				const data = JSON.parse( text );
				const imported = data && data.document ? data.document : data;
				if ( ! imported || ! Array.isArray( imported.layers ) ) {
					throw new Error( 'invalid' );
				}
				return sanitizeDocument( imported ).then( ( result ) => {
					setDocument( result.document );
					if ( ! title.trim() && data.title ) {
						setTitle( data.title );
					}
					setNotice( {
						status: 'success',
						text: __(
							'Design imported. Save to keep it.',
							'artificial-image-generator'
						),
					} );
				} );
			} )
			.catch( fail );
	};

	return el(
		Fragment,
		null,
		el( 'input', {
			ref: input,
			type: 'file',
			accept: '.json,application/json',
			hidden: true,
			onChange: importJson,
		} ),
		el( DropdownMenu, {
			icon: 'ellipsis',
			label: __( 'More', 'artificial-image-generator' ),
			controls: [
				{
					title: __(
						'Export design (JSON)',
						'artificial-image-generator'
					),
					onClick: exportJson,
				},
				{
					title: __(
						'Import design (JSON)',
						'artificial-image-generator'
					),
					onClick: () => input.current.click(),
				},
			],
		} )
	);
}
