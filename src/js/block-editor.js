/**
 * Artificial Image Generator – block editor integration.
 *
 * Adds an "AI Generate" entry point to:
 *   1. core/image       (toolbar)         → sets url / id / alt
 *   2. core/media-text  (toolbar)         → sets mediaUrl / mediaId / mediaAlt
 *   3. Featured Image   (sidebar panel)   → sets featured_media (attachment ID)
 *
 * The shared modal exposes two ways to generate an image:
 *   • "Templates"     – pick a pre-built image template
 *   • "Custom Prompt" – describe the image and call the configured AI service
 *
 * The PHP REST endpoint sideloads the result into the Media Library so that
 * the Featured Image panel — which only accepts an attachment ID — works.
 */

( function () {
	const { addFilter }                  = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const {
		Fragment,
		createElement: el,
		useState,
		useEffect,
		useRef,
	} = wp.element;
	const { BlockControls } = wp.blockEditor;
	const {
		ToolbarGroup,
		ToolbarButton,
		Modal,
		TabPanel,
		TextareaControl,
		TextControl,
		Button,
		Spinner,
		Notice,
		ExternalLink,
		Placeholder,
	} = wp.components;
	const { __, sprintf }                = wp.i18n;
	const apiFetch                       = wp.apiFetch;
	const { registerPlugin }             = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect, useDispatch }     = wp.data;

	// ── Sparkle / wand icon ───────────────────────────────────────────────────
	const AIG_ICON = el(
		'svg',
		{
			xmlns:   'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24',
			width:   '20',
			height:  '20',
			fill:    'currentColor',
			'aria-hidden': 'true',
		},
		el( 'path', { d: 'M12 2l2.09 6.26L20 10l-5.91 1.74L12 18l-2.09-6.26L4 10l5.91-1.74L12 2z' } ),
		el( 'path', { d: 'M19 15l1.5 4.5L22 21l-1.5-1.5L19 15zm-14 0l-1.5 4.5L2 21l1.5-1.5L5 15z' } )
	);

	// ── Block → attribute mapping ─────────────────────────────────────────────
	const BLOCK_ATTR_MAP = {
		'core/image':      { urlAttr: 'url',      idAttr: 'id',      altAttr: 'alt'      },
		'core/media-text': { urlAttr: 'mediaUrl', idAttr: 'mediaId', altAttr: 'mediaAlt' },
	};

	// ── Shared API calls ──────────────────────────────────────────────────────
	function apiRequest( path, options ) {
		const opts = Object.assign(
			{ url: path, headers: { 'X-WP-Nonce': window.aimgData && window.aimgData.nonce } },
			options || {}
		);
		return apiFetch( opts );
	}

	function fetchTemplates() {
		return apiRequest( window.aimgData.endpoints.templates, { method: 'GET' } );
	}

	function generateImage( payload ) {
		return apiRequest( window.aimgData.endpoints.generate, {
			method: 'POST',
			data:   payload,
		} );
	}

	// ── Templates tab ─────────────────────────────────────────────────────────
	function TemplatesPanel( {
		selectedId,
		onSelect,
		titleText,
		onTitleChange,
		isLoading,
	} ) {
		const [ templates,  setTemplates ]  = useState( null );
		const [ fetchError, setFetchError ] = useState( '' );

		useEffect( () => {
			let cancelled = false;
			fetchTemplates()
				.then( ( data ) => {
					if ( cancelled ) return;
					setTemplates( Array.isArray( data ) ? data : [] );
				} )
				.catch( ( err ) => {
					if ( cancelled ) return;
					setFetchError(
						err?.message ||
							__( 'Failed to load image templates.', 'artificial-image-generator' )
					);
					setTemplates( [] );
				} );
			return () => { cancelled = true; };
		}, [] );

		if ( templates === null ) {
			return el(
				'div',
				{ className: 'aimg-modal__loading' },
				el( Spinner ),
				el( 'span', null, __( 'Loading templates…', 'artificial-image-generator' ) )
			);
		}

		if ( fetchError ) {
			return el( Notice, { status: 'error', isDismissible: false }, fetchError );
		}

		if ( templates.length === 0 ) {
			return el(
				Placeholder,
				{
					icon:  AIG_ICON,
					label: __( 'No templates available', 'artificial-image-generator' ),
					instructions: __(
						'Create at least one image template under Image Generator → Image Templates to use this option.',
						'artificial-image-generator'
					),
				}
			);
		}

		return el(
			Fragment,
			null,
			el( TextControl, {
				label:    __( 'Title text (optional)', 'artificial-image-generator' ),
				help:     __(
					'This text is rendered onto the generated image. Leave blank to use the post title.',
					'artificial-image-generator'
				),
				value:    titleText,
				onChange: onTitleChange,
				disabled: isLoading,
			} ),

			el(
				'div',
				{
					className: 'aimg-templates__grid',
					role:      'radiogroup',
					'aria-label': __( 'Image templates', 'artificial-image-generator' ),
				},
				templates.map( ( tpl ) => {
					const isSelected = selectedId === tpl.id;
					return el(
						'button',
						{
							key:        tpl.id,
							type:       'button',
							role:       'radio',
							'aria-checked': isSelected,
							className:  'aimg-template-card' + ( isSelected ? ' is-selected' : '' ),
							onClick:    () => onSelect( tpl.id ),
							disabled:   isLoading,
						},
						el(
							'div',
							{ className: 'aimg-template-card__preview' },
							tpl.preview
								? el( 'img', {
									src: tpl.preview,
									alt: tpl.title,
									loading: 'lazy',
								} )
								: el( 'span', { className: 'aimg-template-card__placeholder' }, AIG_ICON )
						),
						el(
							'div',
							{ className: 'aimg-template-card__meta' },
							el( 'span', { className: 'aimg-template-card__title' }, tpl.title ),
							tpl.width && tpl.height
								? el(
									'span',
									{ className: 'aimg-template-card__size' },
									tpl.width + ' × ' + tpl.height
								)
								: null
						)
					);
				} )
			)
		);
	}

	// ── Custom prompt tab ─────────────────────────────────────────────────────
	function PromptPanel( { value, onChange, onSubmit, isLoading } ) {
		const handleKeyDown = ( evt ) => {
			if ( ( evt.ctrlKey || evt.metaKey ) && evt.key === 'Enter' ) {
				evt.preventDefault();
				onSubmit();
			}
		};

		const hasApiKey = !! window.aimgData?.settings?.hasApiKey;

		return el(
			Fragment,
			null,
			! hasApiKey && el(
				Notice,
				{ status: 'warning', isDismissible: false },
				el(
					'span',
					null,
					__(
						'No AI API key is configured yet. Add one to enable prompt-based generation.',
						'artificial-image-generator'
					),
					' ',
					el(
						ExternalLink,
						{ href: window.aimgData.settings.settingsUrl },
						__( 'Open settings', 'artificial-image-generator' )
					)
				)
			),

			el( TextareaControl, {
				label:       __( 'Describe the image you want', 'artificial-image-generator' ),
				help:        __( 'Tip: press Ctrl + Enter (⌘ + Enter on Mac) to generate.', 'artificial-image-generator' ),
				value,
				onChange,
				onKeyDown:   handleKeyDown,
				rows:        5,
				placeholder: __(
					'e.g. A sunlit forest path in autumn, photorealistic, soft lighting',
					'artificial-image-generator'
				),
				disabled:    isLoading,
				autoFocus:   true,
			} )
		);
	}

	// ── Shared modal ──────────────────────────────────────────────────────────
	function AIMGModal( { onClose, onConfirm, isLoading, error, modalTitle } ) {
		const [ activeTab,    setActiveTab    ] = useState( 'templates' );
		const [ selectedId,   setSelectedId   ] = useState( 0 );
		const [ titleText,    setTitleText    ] = useState( '' );
		const [ prompt,       setPrompt       ] = useState( '' );

		// Pre-fill the title text with the current post title (if available).
		const postTitle = useSelect( ( select ) => {
			try {
				return select( 'core/editor' )?.getEditedPostAttribute( 'title' ) || '';
			} catch ( e ) {
				return '';
			}
		}, [] );

		useEffect( () => {
			if ( ! titleText && postTitle ) {
				setTitleText( postTitle );
			}
			// We only want to seed once — intentionally not depending on titleText.
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ postTitle ] );

		const handleConfirm = () => {
			if ( activeTab === 'templates' ) {
				if ( ! selectedId ) return;
				onConfirm( {
					mode:        'template',
					template_id: selectedId,
					title:       ( titleText || postTitle || '' ).trim(),
				} );
			} else {
				const trimmed = prompt.trim();
				if ( ! trimmed ) return;
				onConfirm( { mode: 'prompt', prompt: trimmed } );
			}
		};

		const isConfirmDisabled =
			isLoading ||
			( activeTab === 'templates' ? ! selectedId : ! prompt.trim() );

		const tabs = [
			{
				name:      'templates',
				title:     __( 'Templates', 'artificial-image-generator' ),
				className: 'aimg-tab aimg-tab--templates',
			},
			{
				name:      'prompt',
				title:     __( 'Custom Prompt', 'artificial-image-generator' ),
				className: 'aimg-tab aimg-tab--prompt',
			},
		];

		return el(
			Modal,
			{
				title:                     modalTitle || __( 'Generate Image with Image Generator & AI', 'artificial-image-generator' ),
				onRequestClose:            () => { if ( ! isLoading ) onClose(); },
				className:                 'aimg-modal',
				shouldCloseOnEsc:          ! isLoading,
				shouldCloseOnClickOutside: ! isLoading,
			},

			error && el(
				Notice,
				{ status: 'error', isDismissible: false, className: 'aimg-modal__notice' },
				error
			),

			el(
				TabPanel,
				{
					className:    'aimg-modal__tabs',
					activeClass:  'is-active',
					tabs,
					initialTabName: 'templates',
					onSelect:     ( tabName ) => setActiveTab( tabName ),
				},
				( tab ) => {
					if ( tab.name === 'templates' ) {
						return el( TemplatesPanel, {
							selectedId,
							onSelect:      setSelectedId,
							titleText,
							onTitleChange: setTitleText,
							isLoading,
						} );
					}
					return el( PromptPanel, {
						value:    prompt,
						onChange: setPrompt,
						onSubmit: handleConfirm,
						isLoading,
					} );
				}
			),

			el(
				'div',
				{ className: 'aimg-modal__actions' },
				el(
					Button,
					{
						variant:   'primary',
						onClick:   handleConfirm,
						disabled:  isConfirmDisabled,
						className: 'aimg-modal__generate-btn',
					},
					isLoading
						? el(
							Fragment,
							null,
							el( Spinner ),
							el( 'span', null, __( 'Generating…', 'artificial-image-generator' ) )
						)
						: __( 'Generate Image', 'artificial-image-generator' )
				),
				el(
					Button,
					{
						variant:  'tertiary',
						onClick:  () => { if ( ! isLoading ) onClose(); },
						disabled: isLoading,
					},
					__( 'Cancel', 'artificial-image-generator' )
				)
			),

			isLoading && el(
				'div',
				{ className: 'aimg-modal__overlay', 'aria-hidden': 'true' },
				el( Spinner ),
				el(
					'p',
					{ className: 'aimg-modal__overlay-text' },
					__( 'Generating image — this can take up to a minute…', 'artificial-image-generator' )
				)
			)
		);
	}

	// ── Hook used by every entry point ────────────────────────────────────────
	function useGenerator( { onSuccess } ) {
		const [ isModalOpen, setModalOpen ] = useState( false );
		const [ isLoading,   setLoading   ] = useState( false );
		const [ errorMsg,    setError     ] = useState( '' );
		const isMounted = useRef( true );

		useEffect( () => {
			isMounted.current = true;
			return () => { isMounted.current = false; };
		}, [] );

		const open  = () => { setError( '' ); setModalOpen( true ); };
		const close = () => { setError( '' ); setModalOpen( false ); };

		const confirm = async ( payload ) => {
			setLoading( true );
			setError( '' );

			try {
				const data = await generateImage( payload );

				if ( ! data?.url ) {
					throw new Error(
						__( 'No image URL returned by the API.', 'artificial-image-generator' )
					);
				}

				await onSuccess( data, payload );
				if ( isMounted.current ) {
					setModalOpen( false );
				}
			} catch ( err ) {
				if ( isMounted.current ) {
					setError(
						err?.message ||
							__( 'Something went wrong. Please try again.', 'artificial-image-generator' )
					);
				}
			} finally {
				if ( isMounted.current ) {
					setLoading( false );
				}
			}
		};

		return { isModalOpen, isLoading, errorMsg, open, close, confirm };
	}

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
						? __( 'Replace with Image generator & AI', 'artificial-image-generator' )
						: __( 'Generate with Image generator & AI', 'artificial-image-generator' )
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
