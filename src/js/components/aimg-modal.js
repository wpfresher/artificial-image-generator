/**
 * Artificial Image Generator – shared modal module.
 *
 * Holds the reusable pieces that power every "Generate Image" entry point:
 *   • AIG_ICON     – the sparkle/wand icon
 *   • useGenerator – state + REST call orchestration hook
 *   • AIMGModal    – the Templates / Custom Prompt modal UI
 *
 * Both the block editor integration (`block-editor.js`) and the Media Library
 * integration (`media-library.js`) import from here so the popup behaves
 * identically everywhere. The PHP REST endpoint sideloads the result into the
 * Media Library and returns an attachment id, so the modal is context-agnostic.
 */

const { Fragment, createElement: el, useState, useEffect, useRef } = wp.element;
const {
	Modal,
	TabPanel,
	TextareaControl,
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
const { useSelect } = wp.data;

// ── Sparkle / wand icon ───────────────────────────────────────────────────────
export const AIG_ICON = el(
	'svg',
	{
		xmlns: 'http://www.w3.org/2000/svg',
		viewBox: '0 0 24 24',
		width: '20',
		height: '20',
		fill: 'currentColor',
		'aria-hidden': 'true',
	},
	el( 'path', {
		d: 'M12 2l2.09 6.26L20 10l-5.91 1.74L12 18l-2.09-6.26L4 10l5.91-1.74L12 2z',
	} ),
	el( 'path', {
		d: 'M19 15l1.5 4.5L22 21l-1.5-1.5L19 15zm-14 0l-1.5 4.5L2 21l1.5-1.5L5 15z',
	} )
);

const data = () => window.aimgData || {};

// ── Shared API calls ──────────────────────────────────────────────────────────
export function apiRequest( path, options ) {
	const opts = Object.assign(
		{
			url: path,
			headers: { 'X-WP-Nonce': data().nonce },
		},
		options || {}
	);
	return apiFetch( opts );
}

function fetchTemplates() {
	return apiRequest( data().endpoints.templates, { method: 'GET' } );
}

function previewTemplate( id, title ) {
	return apiRequest( data().endpoints.templates + '/' + id + '/preview', {
		method: 'POST',
		data: { title },
	} );
}

function buildPrompt( context ) {
	return apiRequest( data().endpoints.prompt, {
		method: 'POST',
		data: {
			post_id: context.postId,
			title: context.title,
			excerpt: context.excerpt,
		},
	} );
}

function deleteMedia( id ) {
	return apiRequest( data().endpoints.media + id + '?force=true', {
		method: 'DELETE',
	} );
}

export function generateImage( payload ) {
	return apiRequest( data().endpoints.generate, {
		method: 'POST',
		data: payload,
	} );
}

const toOptions = ( map ) =>
	Object.keys( map || {} ).map( ( value ) => ( {
		value,
		label: map[ value ],
	} ) );

// Unsaved title and excerpt of the post being edited; null outside the editor.
function usePostContext() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		if ( ! editor || ! editor.getCurrentPostId ) {
			return null;
		}
		return {
			postId: editor.getCurrentPostId(),
			title: editor.getEditedPostAttribute( 'title' ) || '',
			excerpt: editor.getEditedPostAttribute( 'excerpt' ) || '',
		};
	}, [] );
}

// ── Templates tab ───────────────────────────────────────────────────────────
function TemplatePreview( { templateId, title } ) {
	const [ image, setImage ] = useState( '' );
	const [ isRendering, setRendering ] = useState( false );

	useEffect( () => {
		if ( ! templateId ) {
			return;
		}
		let cancelled = false;
		setRendering( true );
		const timer = setTimeout( () => {
			previewTemplate( templateId, title )
				.then( ( res ) => ! cancelled && setImage( res?.image || '' ) )
				.catch( () => ! cancelled && setImage( '' ) )
				.finally( () => ! cancelled && setRendering( false ) );
		}, 400 );
		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ templateId, title ] );

	if ( ! templateId ) {
		return null;
	}

	return el(
		'div',
		{
			className:
				'aimg-template-preview' + ( isRendering ? ' is-busy' : '' ),
		},
		image &&
			el( 'img', {
				src: image,
				alt: __( 'Preview of the image', 'artificial-image-generator' ),
				className: 'aimg-template-preview__image',
			} ),
		isRendering &&
			el(
				'span',
				{ className: 'aimg-template-preview__spinner' },
				el( Spinner )
			)
	);
}

function TemplatesPanel( {
	templates,
	fetchError,
	selectedId,
	onSelect,
	titleText,
	onTitleChange,
	isLoading,
} ) {
	if ( templates === null ) {
		return el(
			'div',
			{ className: 'aimg-modal__loading' },
			el( Spinner ),
			el(
				'span',
				null,
				__( 'Loading templates…', 'artificial-image-generator' )
			)
		);
	}

	if ( fetchError ) {
		return el(
			Notice,
			{ status: 'error', isDismissible: false },
			fetchError
		);
	}

	if ( templates.length === 0 ) {
		return el( Placeholder, {
			icon: AIG_ICON,
			label: __( 'No templates available', 'artificial-image-generator' ),
			instructions: __(
				'Create at least one image template under Image Generator → Image Templates to use this option.',
				'artificial-image-generator'
			),
		} );
	}

	const selected = templates.find( ( tpl ) => tpl.id === selectedId );

	return el(
		Fragment,
		null,
		el( TextControl, {
			label: __( 'Title text (optional)', 'artificial-image-generator' ),
			help: __(
				'This text is rendered onto the generated image. Leave blank to use the default.',
				'artificial-image-generator'
			),
			value: titleText,
			onChange: onTitleChange,
			disabled: isLoading,
		} ),

		el( TemplatePreview, {
			templateId: selectedId,
			title: titleText || ( selected ? selected.title : '' ),
		} ),

		el(
			'div',
			{
				className: 'aimg-templates__grid',
				role: 'radiogroup',
				'aria-label': __(
					'Image templates',
					'artificial-image-generator'
				),
			},
			templates.map( ( tpl ) => {
				const isSelected = selectedId === tpl.id;
				return el(
					'button',
					{
						key: tpl.id,
						type: 'button',
						role: 'radio',
						'aria-checked': isSelected,
						className:
							'aimg-template-card' +
							( isSelected ? ' is-selected' : '' ),
						onClick: () => onSelect( tpl.id ),
						disabled: isLoading,
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
							: el(
									'span',
									{
										className:
											'aimg-template-card__placeholder',
									},
									AIG_ICON
							  )
					),
					el(
						'div',
						{ className: 'aimg-template-card__meta' },
						el(
							'span',
							{ className: 'aimg-template-card__title' },
							tpl.title
						),
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

// ── Custom prompt tab ─────────────────────────────────────────────────────────
function PromptPanel( {
	value,
	onChange,
	onSubmit,
	isLoading,
	options,
	onOptionsChange,
	postContext,
} ) {
	const [ isBuilding, setBuilding ] = useState( false );
	const [ buildError, setBuildError ] = useState( '' );

	const handleKeyDown = ( evt ) => {
		if ( ( evt.ctrlKey || evt.metaKey ) && evt.key === 'Enter' ) {
			evt.preventDefault();
			onSubmit();
		}
	};

	const settings = data().settings || {};
	const choices = data().options || {};
	const hasApiKey = !! settings.hasApiKey;
	const canUseAi = settings.canUseAi !== false;
	const isDisabled = isLoading || ! canUseAi || ! hasApiKey;
	const maxImages = Math.max( 1, settings.maxImages || 1 );
	const setOption = ( key ) => ( val ) =>
		onOptionsChange( Object.assign( {}, options, { [ key ]: val } ) );

	const fillFromPost = () => {
		setBuilding( true );
		setBuildError( '' );
		buildPrompt( postContext )
			.then( ( res ) => onChange( res?.prompt || '' ) )
			.catch( ( err ) =>
				setBuildError(
					err?.message ||
						__(
							'Could not build a prompt from the post.',
							'artificial-image-generator'
						)
				)
			)
			.finally( () => setBuilding( false ) );
	};

	return el(
		Fragment,
		null,
		! canUseAi &&
			el(
				Notice,
				{ status: 'warning', isDismissible: false },
				__(
					'Your site administrator has limited AI image generation to other roles. You can still generate images from templates.',
					'artificial-image-generator'
				)
			),
		canUseAi &&
			! hasApiKey &&
			el(
				Notice,
				{ status: 'warning', isDismissible: false },
				el(
					'span',
					null,
					__(
						'No AI API key is configured yet. Add one to enable prompt-based generation, or use the Templates tab, which needs no key.',
						'artificial-image-generator'
					),
					' ',
					el(
						ExternalLink,
						{ href: settings.settingsUrl },
						__( 'Open settings', 'artificial-image-generator' )
					)
				)
			),
		buildError &&
			el( Notice, { status: 'error', isDismissible: false }, buildError ),

		el( TextareaControl, {
			label: __(
				'Describe the image you want',
				'artificial-image-generator'
			),
			help: __(
				'Tip: press Ctrl + Enter (⌘ + Enter on Mac) to generate.',
				'artificial-image-generator'
			),
			value,
			onChange,
			onKeyDown: handleKeyDown,
			rows: 4,
			placeholder: __(
				'e.g. A sunlit forest path in autumn, photorealistic, soft lighting',
				'artificial-image-generator'
			),
			disabled: isDisabled,
			autoFocus: true,
		} ),

		postContext &&
			postContext.postId &&
			el(
				Button,
				{
					variant: 'link',
					onClick: fillFromPost,
					disabled: isDisabled || isBuilding,
					className: 'aimg-modal__build-prompt',
				},
				isBuilding
					? __( 'Writing prompt…', 'artificial-image-generator' )
					: __(
							'Write a prompt from this post',
							'artificial-image-generator'
					  )
			),

		el(
			'div',
			{ className: 'aimg-modal__options' },
			el( SelectControl, {
				label: __( 'Shape', 'artificial-image-generator' ),
				value: options.size,
				options: toOptions( choices.sizes ),
				onChange: setOption( 'size' ),
				disabled: isDisabled,
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'Style', 'artificial-image-generator' ),
				value: options.style,
				options: toOptions( choices.styles ),
				onChange: setOption( 'style' ),
				disabled: isDisabled,
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'Quality', 'artificial-image-generator' ),
				value: options.quality,
				options: toOptions( choices.qualities ),
				onChange: setOption( 'quality' ),
				disabled: isDisabled,
				__nextHasNoMarginBottom: true,
			} ),
			maxImages > 1 &&
				el( SelectControl, {
					label: __( 'Variations', 'artificial-image-generator' ),
					value: String( options.n ),
					options: Array.from( { length: maxImages }, ( v, i ) => ( {
						value: String( i + 1 ),
						label: String( i + 1 ),
					} ) ),
					onChange: ( val ) =>
						setOption( 'n' )( parseInt( val, 10 ) ),
					disabled: isDisabled,
					__nextHasNoMarginBottom: true,
				} )
		)
	);
}

// ── Variation picker ──────────────────────────────────────────────────────────
function VariationPicker( { images, onPick, isLoading } ) {
	return el(
		Fragment,
		null,
		el(
			'p',
			{ className: 'aimg-variations__intro' },
			__(
				'Pick the image to use. The others will be removed from the Media Library.',
				'artificial-image-generator'
			)
		),
		el(
			'div',
			{ className: 'aimg-variations' },
			images.map( ( image, index ) =>
				el(
					'button',
					{
						key: image.id,
						type: 'button',
						className: 'aimg-variations__item',
						onClick: () => onPick( image ),
						disabled: isLoading,
					},
					el( 'img', {
						src: image.url,
						className: 'aimg-variations__image',
						alt: sprintf(
							/* translators: %d: variation number */
							__( 'Variation %d', 'artificial-image-generator' ),
							index + 1
						),
					} )
				)
			)
		)
	);
}

// ── Shared modal ──────────────────────────────────────────────────────────────
export function AIMGModal( {
	onClose,
	onConfirm,
	isLoading,
	error,
	modalTitle,
	choices,
	onPick,
} ) {
	const settings = data().settings || {};
	const [ activeTab, setActiveTab ] = useState( 'templates' );
	const [ selectedId, setSelectedId ] = useState( 0 );
	const [ titleText, setTitleText ] = useState( '' );
	const [ prompt, setPrompt ] = useState( '' );
	const [ templates, setTemplates ] = useState( null );
	const [ fetchError, setFetchError ] = useState( '' );
	const [ options, setOptions ] = useState( {
		size: settings.size || 'square',
		quality: settings.quality || 'auto',
		style: 'none',
		n: 1,
	} );

	const postContext = usePostContext();
	const postTitle = postContext ? postContext.title : '';

	// Fetch once per modal, so switching tabs doesn't reload the list.
	useEffect( () => {
		let cancelled = false;
		fetchTemplates()
			.then( ( res ) => {
				if ( cancelled ) {
					return;
				}
				const list = Array.isArray( res ) ? res : [];
				setTemplates( list );
				if ( list.length === 1 ) {
					setSelectedId( list[ 0 ].id );
				}
			} )
			.catch( ( err ) => {
				if ( cancelled ) {
					return;
				}
				setFetchError(
					err?.message ||
						__(
							'Failed to load image templates.',
							'artificial-image-generator'
						)
				);
				setTemplates( [] );
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	// Pre-fill the title text with the current post title (if available).
	useEffect( () => {
		if ( ! titleText && postTitle ) {
			setTitleText( postTitle );
		}
		// We only want to seed once — intentionally not depending on titleText.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ postTitle ] );

	const handleConfirm = () => {
		if ( activeTab === 'templates' ) {
			if ( ! selectedId ) {
				return;
			}
			onConfirm( {
				mode: 'template',
				template_id: selectedId,
				title: ( titleText || postTitle || '' ).trim(),
			} );
		} else {
			const trimmed = prompt.trim();
			if ( ! trimmed ) {
				return;
			}
			onConfirm( {
				mode: 'prompt',
				prompt: trimmed,
				size: options.size,
				quality: options.quality,
				style: options.style,
				n: options.n,
				post_id: postContext ? postContext.postId : 0,
			} );
		}
	};

	const isConfirmDisabled =
		isLoading ||
		( activeTab === 'templates'
			? ! selectedId
			: ! prompt.trim() ||
			  ! settings.hasApiKey ||
			  settings.canUseAi === false );

	const tabs = [
		{
			name: 'templates',
			title: __( 'Templates', 'artificial-image-generator' ),
			className: 'aimg-tab aimg-tab--templates',
		},
		{
			name: 'prompt',
			title: __( 'Custom Prompt', 'artificial-image-generator' ),
			className: 'aimg-tab aimg-tab--prompt',
		},
	];

	const hasChoices = Array.isArray( choices ) && choices.length > 0;

	return el(
		Modal,
		{
			title:
				modalTitle ||
				__(
					'Generate Image with Image Generator & AI',
					'artificial-image-generator'
				),
			onRequestClose: () => {
				if ( ! isLoading ) {
					onClose();
				}
			},
			className: 'aimg-modal',
			shouldCloseOnEsc: ! isLoading,
			shouldCloseOnClickOutside: ! isLoading,
		},

		error &&
			el(
				Notice,
				{
					status: 'error',
					isDismissible: false,
					className: 'aimg-modal__notice',
				},
				error
			),

		hasChoices &&
			el( VariationPicker, { images: choices, onPick, isLoading } ),

		! hasChoices &&
			el(
				TabPanel,
				{
					className: 'aimg-modal__tabs',
					activeClass: 'is-active',
					tabs,
					initialTabName: 'templates',
					onSelect: ( tabName ) => setActiveTab( tabName ),
				},
				( tab ) => {
					if ( tab.name === 'templates' ) {
						return el( TemplatesPanel, {
							templates,
							fetchError,
							selectedId,
							onSelect: setSelectedId,
							titleText,
							onTitleChange: setTitleText,
							isLoading,
						} );
					}
					return el( PromptPanel, {
						value: prompt,
						onChange: setPrompt,
						onSubmit: handleConfirm,
						isLoading,
						options,
						onOptionsChange: setOptions,
						postContext,
					} );
				}
			),

		! hasChoices &&
			el(
				'div',
				{ className: 'aimg-modal__actions' },
				el(
					Button,
					{
						variant: 'primary',
						onClick: handleConfirm,
						disabled: isConfirmDisabled,
						className: 'aimg-modal__generate-btn',
					},
					isLoading
						? el(
								Fragment,
								null,
								el( Spinner ),
								el(
									'span',
									null,
									__(
										'Generating…',
										'artificial-image-generator'
									)
								)
						  )
						: __( 'Generate Image', 'artificial-image-generator' )
				),
				el(
					Button,
					{
						variant: 'tertiary',
						onClick: () => {
							if ( ! isLoading ) {
								onClose();
							}
						},
						disabled: isLoading,
					},
					__( 'Cancel', 'artificial-image-generator' )
				)
			),

		isLoading &&
			el(
				'div',
				{ className: 'aimg-modal__overlay', 'aria-hidden': 'true' },
				el( Spinner ),
				el(
					'p',
					{ className: 'aimg-modal__overlay-text' },
					__(
						'Generating image — this can take up to a minute…',
						'artificial-image-generator'
					)
				)
			)
	);
}

// ── Hook used by every entry point ──────────────────────────────────────────────
export function useGenerator( { onSuccess } ) {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const [ isLoading, setLoading ] = useState( false );
	const [ errorMsg, setError ] = useState( '' );
	const [ choices, setChoices ] = useState( null );
	const [ lastPayload, setLastPayload ] = useState( null );
	const isMounted = useRef( true );

	useEffect( () => {
		isMounted.current = true;
		return () => {
			isMounted.current = false;
		};
	}, [] );

	const open = () => {
		setError( '' );
		setChoices( null );
		setModalOpen( true );
	};

	const fail = ( err ) => {
		if ( isMounted.current ) {
			setError(
				err?.message ||
					__(
						'Something went wrong. Please try again.',
						'artificial-image-generator'
					)
			);
		}
	};

	const finish = async ( image, payload, discard ) => {
		await onSuccess( image, payload );
		// Wait, so a page that navigates right after doesn't cancel the requests.
		await Promise.all(
			discard.map( ( id ) => deleteMedia( id ).catch( () => {} ) )
		);
		if ( isMounted.current ) {
			setChoices( null );
			setModalOpen( false );
		}
	};

	const close = () => {
		// Closing without picking discards every variation.
		if ( choices ) {
			choices.forEach( ( image ) =>
				deleteMedia( image.id ).catch( () => {} )
			);
		}
		setError( '' );
		setChoices( null );
		setModalOpen( false );
	};

	const confirm = async ( payload ) => {
		setLoading( true );
		setError( '' );

		try {
			const res = await generateImage( payload );

			if ( ! res?.url ) {
				throw new Error(
					__(
						'No image URL returned by the API.',
						'artificial-image-generator'
					)
				);
			}

			if ( Array.isArray( res.images ) && res.images.length > 1 ) {
				setLastPayload( payload );
				setChoices(
					res.images.map( ( image ) =>
						Object.assign( {}, image, { source: res.source } )
					)
				);
				return;
			}

			await finish( res, payload, [] );
		} catch ( err ) {
			fail( err );
		} finally {
			if ( isMounted.current ) {
				setLoading( false );
			}
		}
	};

	const pick = async ( image ) => {
		setLoading( true );
		setError( '' );

		try {
			await finish(
				image,
				lastPayload,
				choices
					.filter( ( choice ) => choice.id !== image.id )
					.map( ( choice ) => choice.id )
			);
		} catch ( err ) {
			fail( err );
		} finally {
			if ( isMounted.current ) {
				setLoading( false );
			}
		}
	};

	return {
		isModalOpen,
		isLoading,
		errorMsg,
		open,
		close,
		confirm,
		choices,
		pick,
	};
}
