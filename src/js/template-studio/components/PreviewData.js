/**
 * Preview data switcher: design against short, long and awkward titles, or a real post.
 */
import { STORE } from '../store';
import { postTags, searchPosts } from '../api';

const { createElement: el, useEffect, useState } = wp.element;
const { Button, SelectControl, Spinner, TextControl } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;

const decode = ( html ) => {
	const area = document.createElement( 'textarea' );
	area.innerHTML = html;
	return area.value;
};

export const SAMPLE_TITLES = {
	short: __( 'Hello world', 'artificial-image-generator' ),
	long: __(
		'A complete beginner’s guide to growing juicy tomatoes on a small city balcony this summer',
		'artificial-image-generator'
	),
	veryLong: __(
		'Everything you ever wanted to know about growing, pruning, feeding, watering and harvesting tomatoes on a tiny city balcony, explained step by step with photos, a printable checklist and answers to the questions readers ask most',
		'artificial-image-generator'
	),
	unbroken:
		'Supercalifragilisticexpialidocious-https://example.com/a/very/long/url',
};

export default function PreviewData( { data } ) {
	const sample = useSelect(
		( select ) => select( STORE ).getState().sample,
		[]
	);
	const { setSample } = useDispatch( STORE );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ picking, setPicking ] = useState( false );

	useEffect( () => {
		if ( sample.key !== 'post' || search.trim().length < 2 ) {
			setResults( null );
			return;
		}
		let cancelled = false;
		setLoading( true );
		const timer = setTimeout( () => {
			searchPosts( search.trim() )
				.then( ( found ) => ! cancelled && setResults( found ) )
				.catch( () => ! cancelled && setResults( [] ) )
				.finally( () => ! cancelled && setLoading( false ) );
		}, 300 );
		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ search, sample.key ] );

	const choose = ( key ) => {
		if ( key === 'post' ) {
			setSample( { key: 'post' } );
		} else if ( SAMPLE_TITLES[ key ] ) {
			setSample( {
				key,
				tags: { ...data.sampleTags, title: SAMPLE_TITLES[ key ] },
			} );
		} else {
			setSample( { key: 'sample' } );
		}
	};

	const pickPost = ( post ) => {
		setPicking( true );
		setSearch( '' );
		postTags( post.id )
			.then( ( result ) =>
				setSample( {
					key: 'post',
					tags: result.tags,
					postId: result.postId,
					images: result.images,
					label: decode( post.title ),
				} )
			)
			.finally( () => setPicking( false ) );
	};

	return el(
		'div',
		{ className: 'aimg-studio__preview-data' },
		el( SelectControl, {
			label: __( 'Preview with', 'artificial-image-generator' ),
			value: sample.key,
			options: [
				{
					value: 'sample',
					label: __( 'Sample post', 'artificial-image-generator' ),
				},
				{
					value: 'short',
					label: __( 'A short title', 'artificial-image-generator' ),
				},
				{
					value: 'long',
					label: __( 'A long title', 'artificial-image-generator' ),
				},
				{
					value: 'veryLong',
					label: __(
						'A very long title',
						'artificial-image-generator'
					),
				},
				{
					value: 'unbroken',
					label: __(
						'An unbreakable word',
						'artificial-image-generator'
					),
				},
				{
					value: 'post',
					label: __( 'A real post…', 'artificial-image-generator' ),
				},
			],
			onChange: choose,
			__nextHasNoMarginBottom: true,
		} ),
		sample.key === 'post' &&
			el(
				'div',
				{ className: 'aimg-studio__post-search' },
				sample.postId > 0 &&
					el(
						'p',
						{ className: 'description' },
						__( 'Previewing with:', 'artificial-image-generator' ) +
							' ' +
							( sample.label || sample.tags.title )
					),
				el( TextControl, {
					label: __( 'Search posts', 'artificial-image-generator' ),
					value: search,
					onChange: setSearch,
					__nextHasNoMarginBottom: true,
				} ),
				( loading || picking ) && el( Spinner ),
				results &&
					el(
						'ul',
						null,
						results.length === 0 &&
							el(
								'li',
								null,
								__(
									'No posts found.',
									'artificial-image-generator'
								)
							),
						results.map( ( post ) =>
							el(
								'li',
								{ key: post.id },
								el(
									Button,
									{
										variant: 'link',
										onClick: () => pickPost( post ),
									},
									decode( post.title ) || '#' + post.id
								)
							)
						)
					)
			)
	);
}
