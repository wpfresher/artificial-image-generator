/**
 * "Start from" gallery shown when creating a template.
 */
import { STORE } from '../store';
import { renderThumbnail } from '../canvas/thumbnail';

const { createElement: el, useEffect, useState } = wp.element;
const { Button, Spinner } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;

export default function StarterGallery( { data, onDone } ) {
	const fonts = useSelect( ( select ) => select( STORE ).getFonts(), [] );
	const title = useSelect(
		( select ) => select( STORE ).getState().title,
		[]
	);
	const { setDocument, setTitle, setNotice } = useDispatch( STORE );
	const [ thumbs, setThumbs ] = useState( {} );

	useEffect( () => {
		let cancelled = false;
		const items = [
			{ id: 'blank', document: data.starter },
			...data.starters,
		];
		items.reduce(
			( chain, item ) =>
				chain.then( () =>
					renderThumbnail(
						item.document,
						data.sampleTags,
						fonts
					).then( ( url ) => {
						if ( ! cancelled ) {
							setThumbs( ( current ) => ( {
								...current,
								[ item.id ]: url,
							} ) );
						}
					} )
				),
			Promise.resolve()
		);
		return () => {
			cancelled = true;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const choose = ( item ) => {
		setDocument( JSON.parse( JSON.stringify( item.document ) ) );
		if ( ! title.trim() && item.label ) {
			setTitle( item.label );
		}
		setNotice( null );
		onDone();
	};

	const cards = [
		{
			id: 'blank',
			label: __( 'Simple', 'artificial-image-generator' ),
			document: data.starter,
		},
		...data.starters,
	];

	return el(
		'div',
		{ className: 'aimg-studio__gallery' },
		el(
			'div',
			{ className: 'aimg-studio__panel-head' },
			el(
				'h2',
				null,
				__( 'Start from a design', 'artificial-image-generator' )
			),
			el(
				Button,
				{ variant: 'tertiary', onClick: onDone },
				__( 'Skip', 'artificial-image-generator' )
			)
		),
		el(
			'ul',
			{ className: 'aimg-studio__gallery-grid' },
			cards.map( ( item ) =>
				el(
					'li',
					{ key: item.id },
					el(
						'button',
						{
							type: 'button',
							className: 'aimg-studio__starter',
							onClick: () => choose( item ),
						},
						thumbs[ item.id ]
							? el( 'img', { src: thumbs[ item.id ], alt: '' } )
							: el(
									'span',
									{ className: 'aimg-studio__starter-wait' },
									el( Spinner )
							  ),
						el( 'span', null, item.label )
					)
				)
			)
		)
	);
}
