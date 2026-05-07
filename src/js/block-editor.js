/**
 * editor.js  (updated)
 *
 * Adds an "AI Generate" toolbar button to:
 *   1. core/image       → sets  url / id / alt
 *   2. core/media-text  → sets  mediaUrl / mediaId / mediaAlt
 *   3. Featured Image   → sets  featured_media  (via post meta dispatch)
 *
 * For the featured image the generated image MUST be sideloaded into the
 * Media Library (so WordPress has an attachment ID). The PHP endpoint
 * already has sideload_image() — just make sure it returns { url, id }.
 */

( function () {

	const { addFilter }                  = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { Fragment, createElement: el, useState } = wp.element;
	const { BlockControls }              = wp.blockEditor;
	const {
		ToolbarGroup, ToolbarButton,
		Modal, TextareaControl, Button, Spinner, Notice,
	}                                    = wp.components;
	const { __ }                         = wp.i18n;
	const apiFetch                       = wp.apiFetch;
	const { registerPlugin }             = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect, useDispatch }     = wp.data;

	// ── 1. Attribute map per block ────────────────────────────────────────────
	//
	//  Each key is the block name. The values tell the HOC which block
	//  attributes to write the generated image into:
	//
	//    urlAttr  → stores the image src URL
	//    idAttr   → stores the Media Library attachment ID (optional but preferred)
	//    altAttr  → stores the alt text
	//
	const BLOCK_ATTR_MAP = {
		'core/image': {
			urlAttr: 'url',
			idAttr:  'id',
			altAttr: 'alt',
		},
		'core/media-text': {
			urlAttr: 'mediaUrl',
			idAttr:  'mediaId',
			altAttr: 'mediaAlt',
		},
	};

	// ── 2. Shared API call ────────────────────────────────────────────────────
	async function generateImage( prompt ) {
		const data = await apiFetch( {
			url:     aimgData.endpoint,
			method:  'POST',
			headers: { 'X-WP-Nonce': aimgData.nonce },
			data:    { prompt: prompt.trim() },
		} );

		if ( ! data?.url ) {
			throw new Error( __( 'No image URL returned by the API.', 'artificial-image-generator' ) );
		}

		return data; // { url: '…', id: 123 }
	}

	// ── 3. Reusable modal ─────────────────────────────────────────────────────
	function AIModal( { onClose, onConfirm, isLoading, error } ) {
		const [ prompt, setPrompt ] = useState( '' );

		const handleKeyDown = ( evt ) => {
			if ( ( evt.ctrlKey || evt.metaKey ) && evt.key === 'Enter' ) {
				evt.preventDefault();
				onConfirm( prompt );
			}
		};

		return el(
			Modal,
			{
				title:                     __( 'Generate Image with AI', 'artificial-image-generator' ),
				onRequestClose:            () => { if ( ! isLoading ) onClose(); },
				className:                 'aimg-modal',
				shouldCloseOnEsc:          ! isLoading,
				shouldCloseOnClickOutside: ! isLoading,
			},

			error && el( Notice, {
				status:        'error',
				isDismissible: true,
				onRemove:      () => {},
			}, error ),

			el( TextareaControl, {
				label:       __( 'Describe the image you want', 'artificial-image-generator' ),
				help:        __( 'Ctrl + Enter to generate.', 'artificial-image-generator' ),
				value:       prompt,
				onChange:    setPrompt,
				onKeyDown:   handleKeyDown,
				rows:        4,
				placeholder: __( 'e.g. A sunlit forest path in autumn, photorealistic', 'artificial-image-generator' ),
				disabled:    isLoading,
				autoFocus:   true,
			} ),

			el( 'div', { className: 'aimg-modal__actions' },
				el( Button, {
						variant:   'primary',
						onClick:   () => onConfirm( prompt ),
						disabled:  isLoading || ! prompt.trim(),
						className: 'aimg-modal__generate-btn',
					},
					isLoading
						? el( Fragment, null, el( Spinner ), __( ' Generating…', 'artificial-image-generator' ) )
						: __( 'Generate Image', 'artificial-image-generator' )
				),
				el( Button, {
					variant:  'tertiary',
					onClick:  () => { if ( ! isLoading ) onClose(); },
					disabled: isLoading,
				}, __( 'Cancel', 'artificial-image-generator' ) )
			)
		);
	}

	// ── 4. HOC — handles core/image and core/media-text ───────────────────────
	const withAIGenerateButton = createHigherOrderComponent( ( BlockEdit ) => {
		return ( props ) => {
			const attrMap = BLOCK_ATTR_MAP[ props.name ];

			// Pass through any block we don't target
			if ( ! attrMap ) {
				return el( BlockEdit, props );
			}

			const [ isModalOpen,  setModalOpen  ] = useState( false );
			const [ isLoading,    setLoading     ] = useState( false );
			const [ errorMessage, setError        ] = useState( '' );

			const handleConfirm = async ( prompt ) => {
				if ( ! prompt.trim() ) {
					setError( __( 'Please enter a prompt.', 'artificial-image-generator' ) );
					return;
				}

				setLoading( true );
				setError( '' );

				try {
					const { url, id } = await generateImage( prompt );

					const newAttrs = {
						[ attrMap.urlAttr ]: url,
						[ attrMap.idAttr  ]: id ?? undefined,
						[ attrMap.altAttr ]: props.attributes[ attrMap.altAttr ] || prompt.trim(),
					};

					// core/media-text also needs mediaType set to 'image'
					if ( props.name === 'core/media-text' ) {
						newAttrs.mediaType = 'image';
					}

					props.setAttributes( newAttrs );
					setModalOpen( false );

				} catch ( err ) {
					setError( err?.message || __( 'Something went wrong. Please try again.', 'artificial-image-generator' ) );
				} finally {
					setLoading( false );
				}
			};

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),

				el( BlockControls, { group: 'other' },
					el( ToolbarGroup, null,
						el( ToolbarButton, {
							icon:      AIG_ICON,
							label:     __( 'Generate with AI', 'artificial-image-generator' ),
							onClick:   () => { setError( '' ); setModalOpen( true ); },
							className: 'aimg-toolbar-button',
						} )
					)
				),

				isModalOpen && el( AIModal, {
					onClose:   () => { setModalOpen( false ); setError( '' ); },
					onConfirm: handleConfirm,
					isLoading,
					error:     errorMessage,
				} )
			);
		};
	}, 'withAIGenerateButton' );

	addFilter(
		'editor.BlockEdit',
		'aimg/with-ai-generate-button',
		withAIGenerateButton
	);

	// ── 5. Featured Image (Thumbnail) sidebar panel ───────────────────────────
	//
	//  Uses PluginDocumentSettingPanel to add a collapsible section in the
	//  right sidebar under Document settings (same column as "Status &
	//  Visibility", "Categories", etc.).
	//
	//  IMPORTANT: The PHP endpoint MUST sideload the image and return an
	//  { id } field — WordPress featured_media only accepts attachment IDs,
	//  not bare URLs.
	//
	function FeaturedImageAIPanel() {
		const [ isModalOpen,  setModalOpen  ] = useState( false );
		const [ isLoading,    setLoading     ] = useState( false );
		const [ errorMessage, setError        ] = useState( '' );

		// Read the current featured image URL for the live preview
		const featuredImageUrl = useSelect( ( select ) => {
			const featuredId = select( 'core/editor' )
				.getEditedPostAttribute( 'featured_media' );

			if ( ! featuredId ) return null;

			const media = select( 'core' ).getMedia( featuredId );
			return media?.source_url ?? null;
		}, [] );

		const { editPost } = useDispatch( 'core/editor' );

		const handleConfirm = async ( prompt ) => {
			if ( ! prompt.trim() ) {
				setError( __( 'Please enter a prompt.', 'artificial-image-generator' ) );
				return;
			}

			setLoading( true );
			setError( '' );

			try {
				const { id } = await generateImage( prompt );

				if ( ! id ) {
					throw new Error(
						__( 'No attachment ID returned. Ensure the PHP endpoint sideloads the image.', 'artificial-image-generator' )
					);
				}

				// This is the correct WP API to programmatically set the featured image
				await editPost( { featured_media: id } );
				setModalOpen( false );

			} catch ( err ) {
				setError( err?.message || __( 'Something went wrong. Please try again.', 'artificial-image-generator' ) );
			} finally {
				setLoading( false );
			}
		};

		return el(
			Fragment,
			null,

			el( 'div', { className: 'aimg-featured__wrap' },

				// Live preview of current featured image
				featuredImageUrl && el( 'img', {
					src:       featuredImageUrl,
					alt:       __( 'Current featured image', 'artificial-image-generator' ),
					className: 'aimg-featured__preview',
				} ),

				el( Button, {
						variant:   'secondary',
						onClick:   () => { setError( '' ); setModalOpen( true ); },
						className: 'aimg-featured__btn',
						icon:      AIG_ICON,
					},
					featuredImageUrl
						? __( 'Replace with AI Image', 'artificial-image-generator' )
						: __( 'Generate with AI', 'artificial-image-generator' )
				)
			),

			isModalOpen && el( AIModal, {
				onClose:   () => { setModalOpen( false ); setError( '' ); },
				onConfirm: handleConfirm,
				isLoading,
				error:     errorMessage,
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

	// ── 6. Sparkle / wand icon ────────────────────────────────────────────────
	const AIG_ICON = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: '20', height: '20', fill: 'currentColor' },
		el( 'path', { d: 'M12 2l2.09 6.26L20 10l-5.91 1.74L12 18l-2.09-6.26L4 10l5.91-1.74L12 2z', fillRule: 'evenodd' } ),
		el( 'path', { d: 'M19 15l1.5 4.5L22 21l-1.5-1.5L19 15zm-14 0l-1.5 4.5L2 21l1.5-1.5L5 15z' } )
	);

} )();
