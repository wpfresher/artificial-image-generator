/**
 * Stock photo tab: search one library and pick a photo to import.
 *
 * Results hotlink the libraries' own thumbnails, as their terms require; the
 * photo is only downloaded into the Media Library when it is inserted.
 */

const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;
const {
	TextControl,
	SelectControl,
	Button,
	Spinner,
	Notice,
	ExternalLink,
	Placeholder,
} = wp.components;
const { __, sprintf } = wp.i18n;
const apiFetch = wp.apiFetch;

const data = () => window.aimgData || {};

const request = ( path, params ) => {
	const query = new URLSearchParams( params || {} ).toString();

	return apiFetch( {
		url: data().endpoints.stock + path + ( query ? '?' + query : '' ),
		headers: { 'X-WP-Nonce': data().nonce },
	} );
};

const toOptions = ( map, emptyLabel ) =>
	[ { value: '', label: emptyLabel } ].concat(
		Object.keys( map || {} ).map( ( value ) => ( {
			value,
			label: map[ value ],
		} ) )
	);

function NoKey( { provider } ) {
	const settings = data().settings || {};

	return el( Placeholder, {
		label: sprintf(
			/* translators: %s: stock photo library, e.g. Unsplash */
			__( 'Search free photos from %s', 'artificial-image-generator' ),
			provider.label
		),
		instructions: __(
			'This library needs a free API key. Create one, then add it under Image Generator → Settings → Stock Photos.',
			'artificial-image-generator'
		),
		children: el(
			'div',
			{ className: 'aimg-stock__nokey' },
			el(
				ExternalLink,
				{ href: provider.signupUrl },
				sprintf(
					/* translators: %s: stock photo library */
					__( 'Get a free %s key', 'artificial-image-generator' ),
					provider.label
				)
			),
			settings.settingsUrl &&
				el(
					ExternalLink,
					{ href: settings.settingsUrl },
					__( 'Open settings', 'artificial-image-generator' )
				)
		),
	} );
}

export function StockPanel( {
	provider,
	postTitle,
	selected,
	onSelect,
	isLoading,
} ) {
	const choices = data().options || {};
	const [ query, setQuery ] = useState( '' );
	const [ filters, setFilters ] = useState( {
		orientation: '',
		color: '',
	} );
	const [ results, setResults ] = useState( null );
	const [ page, setPage ] = useState( 1 );
	const [ pages, setPages ] = useState( 0 );
	const [ isSearching, setSearching ] = useState( false );
	const [ error, setError ] = useState( '' );
	const searchId = useRef( 0 );
	const sentinel = useRef( null );

	const search = ( terms, nextPage, nextFilters ) => {
		const text = ( terms || '' ).trim();
		if ( ! text ) {
			return;
		}

		const id = ++searchId.current;
		setSearching( true );
		setError( '' );

		request(
			'/' + provider.id + '/search',
			Object.assign( { query: text, page: nextPage }, nextFilters )
		)
			.then( ( res ) => {
				if ( id !== searchId.current ) {
					return;
				}
				const photos = Array.isArray( res?.photos ) ? res.photos : [];
				setResults( ( current ) =>
					nextPage > 1 && current ? current.concat( photos ) : photos
				);
				setPage( nextPage );
				setPages( res?.pages || 0 );
			} )
			.catch( ( err ) => {
				if ( id !== searchId.current ) {
					return;
				}
				setError(
					err?.message ||
						__( 'The search failed.', 'artificial-image-generator' )
				);
				if ( nextPage === 1 ) {
					setResults( [] );
				}
			} )
			.finally( () => id === searchId.current && setSearching( false ) );
	};

	// Start with keywords from the post title, as automatic stock photos do.
	useEffect( () => {
		if ( ! provider.configured || ! postTitle ) {
			return;
		}
		request( '/keywords', { title: postTitle } )
			.then( ( res ) => {
				const terms = res?.query || '';
				if ( terms ) {
					setQuery( terms );
					search( terms, 1, filters );
				}
			} )
			.catch( () => {} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ provider.id ] );

	// Infinite scroll: load the next page when the end of the grid shows.
	useEffect( () => {
		const node = sentinel.current;
		if ( ! node || ! window.IntersectionObserver ) {
			return;
		}
		const observer = new window.IntersectionObserver( ( entries ) => {
			if (
				entries[ 0 ].isIntersecting &&
				! isSearching &&
				page < pages
			) {
				search( query, page + 1, filters );
			}
		} );
		observer.observe( node );
		return () => observer.disconnect();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ results, isSearching, page, pages ] );

	if ( ! provider.configured ) {
		return el( NoKey, { provider } );
	}

	const setFilter = ( key ) => ( value ) => {
		const next = Object.assign( {}, filters, { [ key ]: value } );
		setFilters( next );
		search( query, 1, next );
	};

	return el(
		Fragment,
		null,
		el(
			'form',
			{
				className: 'aimg-stock__search',
				onSubmit: ( evt ) => {
					evt.preventDefault();
					search( query, 1, filters );
				},
			},
			el( TextControl, {
				label: sprintf(
					/* translators: %s: stock photo library */
					__( 'Search %s', 'artificial-image-generator' ),
					provider.label
				),
				value: query,
				onChange: setQuery,
				disabled: isLoading,
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'Orientation', 'artificial-image-generator' ),
				value: filters.orientation,
				options: toOptions(
					choices.orientations,
					__( 'Any', 'artificial-image-generator' )
				),
				onChange: setFilter( 'orientation' ),
				disabled: isLoading,
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'Color', 'artificial-image-generator' ),
				value: filters.color,
				options: toOptions(
					choices.colors,
					__( 'Any', 'artificial-image-generator' )
				),
				onChange: setFilter( 'color' ),
				disabled: isLoading,
				__nextHasNoMarginBottom: true,
			} ),
			el(
				Button,
				{
					variant: 'secondary',
					type: 'submit',
					disabled: isLoading || isSearching || ! query.trim(),
				},
				__( 'Search', 'artificial-image-generator' )
			)
		),

		error && el( Notice, { status: 'error', isDismissible: false }, error ),

		results &&
			results.length === 0 &&
			! isSearching &&
			! error &&
			el(
				'p',
				{ className: 'aimg-stock__empty' },
				__(
					'No photos found. Try other words.',
					'artificial-image-generator'
				)
			),

		results &&
			results.length > 0 &&
			el(
				'div',
				{
					className: 'aimg-stock__grid',
					role: 'radiogroup',
					'aria-label': sprintf(
						/* translators: %s: stock photo library */
						__( 'Photos from %s', 'artificial-image-generator' ),
						provider.label
					),
				},
				results.map( ( photo ) => {
					const isSelected = selected && selected.id === photo.id;
					return el(
						'button',
						{
							key: photo.id,
							type: 'button',
							role: 'radio',
							'aria-checked': isSelected,
							className:
								'aimg-stock__photo' +
								( isSelected ? ' is-selected' : '' ),
							style: { backgroundColor: photo.color || '#ddd' },
							onClick: () =>
								onSelect( Object.assign( { query }, photo ) ),
							disabled: isLoading,
						},
						el( 'img', {
							className: 'aimg-stock__image',
							src: photo.thumb,
							alt: photo.description || '',
							loading: 'lazy',
						} ),
						el(
							'span',
							{ className: 'aimg-stock__credit' },
							photo.photographer
								? sprintf(
										/* translators: %s: photographer name */
										__(
											'Photo by %s',
											'artificial-image-generator'
										),
										photo.photographer
								  )
								: provider.label
						),
						photo.imported > 0 &&
							el(
								'span',
								{ className: 'aimg-stock__badge' },
								__(
									'In Media Library',
									'artificial-image-generator'
								)
							)
					);
				} ),
				isSearching &&
					el(
						'div',
						{ className: 'aimg-stock__more' },
						el( Spinner )
					),
				el( 'div', {
					ref: sentinel,
					className: 'aimg-stock__sentinel',
				} )
			),

		isSearching &&
			! ( results && results.length ) &&
			el( 'div', { className: 'aimg-modal__loading' }, el( Spinner ) ),

		! isSearching &&
			page < pages &&
			! window.IntersectionObserver &&
			el(
				Button,
				{
					variant: 'secondary',
					onClick: () => search( query, page + 1, filters ),
				},
				__( 'Load more', 'artificial-image-generator' )
			),

		selected &&
			el( 'p', {
				className: 'aimg-stock__attribution',
				dangerouslySetInnerHTML: { __html: selected.attribution },
			} )
	);
}
