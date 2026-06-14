/**
 * Artificial Image Generator – block editor integration.
 *
 * Adds an "AI Generate" entry point to:
 *   1. core/image       (toolbar)         → sets url / id / alt
 *   2. core/media-text  (toolbar)         → sets mediaUrl / mediaId / mediaAlt
 *   3. Featured Image   (sidebar panel)   → sets featured_media (attachment ID)
 *
 * The shared modal (see ./components/aimg-modal.js) exposes two ways to generate
 * an image:
 *   • "Templates"     – pick a pre-built image template
 *   • "Custom Prompt" – describe the image and call the configured AI service
 *
 * The PHP REST endpoint sideloads the result into the Media Library so that
 * the Featured Image panel — which only accepts an attachment ID — works.
 */

import { AIG_ICON, AIMGModal, useGenerator } from './components/aimg-modal';

( function () {
	const { addFilter }                  = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { Fragment, createElement: el } = wp.element;
	const { BlockControls }              = wp.blockEditor;
	const { ToolbarGroup, ToolbarButton, Button } = wp.components;
	const { __ }                         = wp.i18n;
	const { registerPlugin }             = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect, useDispatch }     = wp.data;

	// ── Block → attribute mapping ─────────────────────────────────────────────
	const BLOCK_ATTR_MAP = {
		'core/image':      { urlAttr: 'url',      idAttr: 'id',      altAttr: 'alt'      },
		'core/media-text': { urlAttr: 'mediaUrl', idAttr: 'mediaId', altAttr: 'mediaAlt' },
	};

	// ── HOC: core/image and core/media-text toolbar buttons ───────────────────
	const withAIGenerateButton = createHigherOrderComponent( ( BlockEdit ) => {
		return ( props ) => {
			const attrMap = BLOCK_ATTR_MAP[ props.name ];
			if ( ! attrMap ) {
				return el( BlockEdit, props );
			}

			const { isModalOpen, isLoading, errorMsg, open, close, confirm } = useGenerator( {
				onSuccess: async ( data, payload ) => {
					const altFallback =
						payload.mode === 'prompt'
							? payload.prompt
							: ( payload.title || data.alt || '' );

					const newAttrs = {
						[ attrMap.urlAttr ]: data.url,
						[ attrMap.idAttr  ]: data.id || undefined,
						[ attrMap.altAttr ]: props.attributes[ attrMap.altAttr ] || altFallback,
					};

					if ( props.name === 'core/media-text' ) {
						newAttrs.mediaType = 'image';
					}

					props.setAttributes( newAttrs );
				},
			} );

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),

				el(
					BlockControls,
					{ group: 'other' },
					el(
						ToolbarGroup,
						null,
						el( ToolbarButton, {
							icon:      AIG_ICON,
							label:     __( 'Generate with Image generator & AI', 'artificial-image-generator' ),
							onClick:   open,
							className: 'aimg-toolbar-button',
						} )
					)
				),

				isModalOpen && el( AIMGModal, {
					onClose:    close,
					onConfirm:  confirm,
					isLoading,
					error:      errorMsg,
					modalTitle: __( 'Generate Image with Image generator & AI', 'artificial-image-generator' ),
				} )
			);
		};
	}, 'withAIGenerateButton' );

	addFilter(
		'editor.BlockEdit',
		'aimg/with-ai-generate-button',
		withAIGenerateButton
	);

	// ── Featured Image sidebar panel ──────────────────────────────────────────
	function FeaturedImageAIPanel() {
		const featuredImageUrl = useSelect( ( select ) => {
			const featuredId = select( 'core/editor' )?.getEditedPostAttribute( 'featured_media' );
			if ( ! featuredId ) return null;
			const media = select( 'core' ).getMedia( featuredId );
			return media?.source_url ?? null;
		}, [] );

		const { editPost } = useDispatch( 'core/editor' );

		const { isModalOpen, isLoading, errorMsg, open, close, confirm } = useGenerator( {
			onSuccess: async ( data ) => {
				if ( ! data.id ) {
					throw new Error(
						__(
							'The generated image could not be added to the Media Library — a featured image needs an attachment ID.',
							'artificial-image-generator'
						)
					);
				}
				await editPost( { featured_media: data.id } );
			},
		} );

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'aimg-featured__wrap' },
				featuredImageUrl && el( 'img', {
					src:       featuredImageUrl,
					alt:       __( 'Current featured image', 'artificial-image-generator' ),
					className: 'aimg-featured__preview',
				} ),
				el(
					Button,
					{
						variant:   'secondary',
						onClick:   open,
						className: 'aimg-featured__btn',
						icon:      AIG_ICON,
					},
					featuredImageUrl
						? __( 'Replace & Re-generate Image', 'artificial-image-generator' )
						: __( 'Generate Featured Image', 'artificial-image-generator' )
				)
			),

			isModalOpen && el( AIMGModal, {
				onClose:    close,
				onConfirm:  confirm,
				isLoading,
				error:      errorMsg,
				modalTitle: __( 'Generate Featured Image', 'artificial-image-generator' ),
			} )
		);
	}

	registerPlugin( 'aimg-featured-image-panel', {
		render: () => el(
			PluginDocumentSettingPanel,
			{
				name:  'aimg-featured-image',
				title: __( 'AI Featured Image', 'artificial-image-generator' ),
				icon:  AIG_ICON,
			},
			el( FeaturedImageAIPanel )
		),
	} );
} )();
