/**
 * The Template Studio screen: toolbar, layers, canvas, inspector and exact preview.
 */
import { STORE } from '../store';
import { previewDocument, saveTemplate } from '../api';
import StudioCanvas from '../canvas/StudioCanvas';
import LayersPanel from './LayersPanel';
import Inspector from './Inspector';
import PreviewData from './PreviewData';
import useShortcuts from '../shortcuts';
import StarterGallery from './StarterGallery';
import TemplateMenu from './TemplateMenu';

const { createElement: el, useEffect, useMemo, useState } = wp.element;
const { Button, Notice, SelectControl, Spinner, TextControl } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { __, sprintf } = wp.i18n;

function Toolbar( { data, onSave, onPreview } ) {
	const document = useSelect(
		( select ) => select( STORE ).getDocument(),
		[]
	);
	const state = useSelect( ( select ) => select( STORE ).getState(), [] );
	const { setTitle, setStatus, undo, redo } = useDispatch( STORE );
	const { canUndo, canRedo } = useSelect(
		( select ) => ( {
			canUndo: select( STORE ).canUndo(),
			canRedo: select( STORE ).canRedo(),
		} ),
		[]
	);

	return el(
		'div',
		{ className: 'aimg-studio__toolbar' },
		el(
			'a',
			{ href: data.listUrl, className: 'aimg-studio__back' },
			'← ' + __( 'Templates', 'artificial-image-generator' )
		),
		el( TextControl, {
			className: 'aimg-studio__title',
			label: __( 'Template name', 'artificial-image-generator' ),
			hideLabelFromVision: true,
			placeholder: __( 'Template name', 'artificial-image-generator' ),
			value: state.title,
			onChange: setTitle,
			__nextHasNoMarginBottom: true,
		} ),
		el( SelectControl, {
			label: __( 'Status', 'artificial-image-generator' ),
			hideLabelFromVision: true,
			value: state.status,
			options: [
				{
					value: 'publish',
					label: __( 'Active', 'artificial-image-generator' ),
				},
				{
					value: 'draft',
					label: __(
						'Draft (not used)',
						'artificial-image-generator'
					),
				},
			],
			onChange: setStatus,
			__nextHasNoMarginBottom: true,
		} ),
		el( 'span', { className: 'aimg-studio__spacer' } ),
		el( Button, {
			icon: 'undo',
			label: __( 'Undo (Ctrl+Z)', 'artificial-image-generator' ),
			disabled: ! canUndo,
			onClick: undo,
		} ),
		el( Button, {
			icon: 'redo',
			label: __( 'Redo (Ctrl+Shift+Z)', 'artificial-image-generator' ),
			disabled: ! canRedo,
			onClick: redo,
		} ),
		el( TemplateMenu, { data, document } ),
		el(
			Button,
			{
				variant: 'secondary',
				onClick: onPreview,
				disabled: state.preview.loading,
			},
			__( 'Exact preview', 'artificial-image-generator' )
		),
		el(
			Button,
			{
				variant: 'primary',
				onClick: onSave,
				isBusy: state.saving,
				disabled: state.saving || ! state.title.trim(),
			},
			state.saving
				? __( 'Saving…', 'artificial-image-generator' )
				: __( 'Save', 'artificial-image-generator' )
		)
	);
}

function PreviewPanel() {
	const preview = useSelect(
		( select ) => select( STORE ).getState().preview,
		[]
	);
	if ( ! preview.image && ! preview.loading && ! preview.error ) {
		return null;
	}
	return el(
		'div',
		{ className: 'aimg-studio__preview' },
		el( 'h2', null, __( 'Exact preview', 'artificial-image-generator' ) ),
		preview.loading && el( Spinner ),
		preview.error &&
			el(
				Notice,
				{ status: 'error', isDismissible: false },
				preview.error
			),
		preview.image &&
			el( 'img', {
				src: preview.image,
				alt: __(
					'Image rendered by the server',
					'artificial-image-generator'
				),
			} ),
		preview.ms &&
			el(
				'p',
				{ className: 'description' },
				sprintf(
					/* translators: %d: milliseconds */
					__(
						'Rendered by your server in %d ms. This is exactly what posts get.',
						'artificial-image-generator'
					),
					preview.ms
				)
			)
	);
}

export default function App( { data } ) {
	const state = useSelect( ( select ) => select( STORE ).getState(), [] );
	const { load, select, updateLayer, setSaving, setNotice, setPreview } =
		useDispatch( STORE );

	const [ gallery, setGallery ] = useState(
		() => ! state.templateId && ( data.starters || [] ).length > 0
	);

	const tags = useMemo(
		() => state.sample.tags || data.sampleTags,
		[ state.sample.tags, data.sampleTags ]
	);
	const images = useMemo(
		() => ( { ...data.dynamicImages, ...state.sample.images } ),
		[ state.sample.images, data.dynamicImages ]
	);

	useEffect( () => {
		const warn = ( event ) => {
			if ( state.dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ state.dirty ] );

	const save = () => {
		setSaving( true );
		setNotice( null );
		const selected = state.selectedId;
		saveTemplate( state.templateId, {
			title: state.title,
			status: state.status,
			document: state.document,
		} )
			.then( ( template ) => {
				load( {
					templateId: template.id,
					title: template.title,
					status: template.status,
					document: template.document,
				} );
				select( selected );
				if ( ! state.templateId ) {
					window.history.replaceState(
						null,
						'',
						data.editUrl + template.id
					);
				}
				setNotice( {
					status: 'success',
					text: __( 'Template saved.', 'artificial-image-generator' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text:
						error.message ||
						__(
							'The template could not be saved.',
							'artificial-image-generator'
						),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	useShortcuts( () => {
		if ( ! state.saving && state.title.trim() ) {
			save();
		}
	} );

	const preview = () => {
		setPreview( { loading: true, error: '' } );
		previewDocument( state.document, tags.title, state.sample.postId )
			.then( ( result ) =>
				setPreview( {
					image: result.image,
					ms: result.ms,
					loading: false,
				} )
			)
			.catch( ( error ) =>
				setPreview( { error: error.message, loading: false } )
			);
	};

	return el(
		'div',
		{ className: 'aimg-studio' },
		el( Toolbar, {
			data,
			onSave: save,
			onPreview: preview,
		} ),
		state.notice &&
			el(
				Notice,
				{
					status: state.notice.status,
					onRemove: () => setNotice( null ),
				},
				state.notice.text
			),
		el(
			'div',
			{ className: 'aimg-studio__narrow' },
			__(
				'The Template Studio needs a wider window. Use a larger screen.',
				'artificial-image-generator'
			)
		),
		gallery &&
			el( StarterGallery, { data, onDone: () => setGallery( false ) } ),
		! gallery &&
			el(
				'div',
				{ className: 'aimg-studio__body' },
				el( LayersPanel, { data } ),
				el(
					'div',
					{ className: 'aimg-studio__canvas' },
					el( PreviewData, { data } ),
					el( StudioCanvas, {
						doc: state.document,
						selectedId: state.selectedId,
						onSelect: select,
						onChangeLayer: updateLayer,
						data,
						tags,
						images,
						fonts: state.fonts,
					} ),
					el(
						'p',
						{ className: 'description' },
						__(
							'The canvas closely matches the final image. Use Exact preview to see it rendered by your server.',
							'artificial-image-generator'
						)
					)
				),
				el(
					'div',
					{ className: 'aimg-studio__side' },
					el( Inspector, { data } ),
					el( PreviewPanel )
				)
			)
	);
}
