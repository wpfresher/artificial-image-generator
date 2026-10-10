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
 * The sidebar panel also shows automatic generation running in the background
 * and can (re)generate the featured image with the configured method.
 */

import {
	AIG_ICON,
	AIMGModal,
	apiRequest,
	useGenerator,
} from './components/aimg-modal';

( function () {
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const {
		Fragment,
		createElement: el,
		useState,
		useEffect,
		useRef,
	} = wp.element;
	const { BlockControls } = wp.blockEditor;
	const { ToolbarGroup, ToolbarButton, Button, Spinner, Notice } =
		wp.components;
	const { __, sprintf } = wp.i18n;
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect, useDispatch, select } = wp.data;

	const POLL_INTERVAL = 3000;
	const POLL_LIMIT = 60;

	// Every generate endpoint needs upload_files; don't offer what would be refused.
	const CAN_UPLOAD =
		! window.aimgData ||
		! window.aimgData.settings ||
		window.aimgData.settings.canUpload !== false;

	// ── Block → attribute mapping ─────────────────────────────────────────────
	const BLOCK_ATTR_MAP = {
		'core/image': { urlAttr: 'url', idAttr: 'id', altAttr: 'alt' },
		'core/media-text': {
			urlAttr: 'mediaUrl',
			idAttr: 'mediaId',
			altAttr: 'mediaAlt',
		},
	};

	// ── HOC: core/image and core/media-text toolbar buttons ───────────────────
	const withAIGenerateButton = createHigherOrderComponent( ( BlockEdit ) => {
		return ( props ) => {
			const attrMap = BLOCK_ATTR_MAP[ props.name ];
			if ( ! attrMap || ! CAN_UPLOAD ) {
				return el( BlockEdit, props );
			}

			const generator = useGenerator( {
				onSuccess: async ( image, payload ) => {
					const altFallback =
						payload.mode === 'prompt'
							? payload.prompt
							: payload.title || image.alt || '';

					const newAttrs = {
						[ attrMap.urlAttr ]: image.url,
						[ attrMap.idAttr ]: image.id || undefined,
						[ attrMap.altAttr ]:
							props.attributes[ attrMap.altAttr ] || altFallback,
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
							icon: AIG_ICON,
							label: __(
								'Generate with Image generator & AI',
								'artificial-image-generator'
							),
							onClick: generator.open,
							className: 'aimg-toolbar-button',
						} )
					)
				),

				generator.isModalOpen &&
					el( AIMGModal, {
						onClose: generator.close,
						onConfirm: generator.confirm,
						isLoading: generator.isLoading,
						error: generator.errorMsg,
						choices: generator.choices,
						onPick: generator.pick,
						modalTitle: __(
							'Generate Image with Image generator & AI',
							'artificial-image-generator'
						),
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

	// Point the stored post at a new featured image without marking it as edited.
	function receiveFeaturedImage( postType, postId, attachmentId ) {
		const record = select( 'core' ).getEntityRecord(
			'postType',
			postType,
			postId
		);
		if ( record && record.featured_media !== attachmentId ) {
			wp.data.dispatch( 'core' ).receiveEntityRecords(
				'postType',
				postType,
				Object.assign( {}, record, {
					featured_media: attachmentId,
				} )
			);
		}
	}

	// Tracks the post's background job. It lives outside the panel so it keeps
	// working while the panel is collapsed.
	function useFeaturedJob( isEnabled ) {
		const endpoints =
			( window.aimgData && window.aimgData.endpoints ) || {};

		const post = useSelect( ( sel ) => {
			const editor = sel( 'core/editor' );
			const featuredId =
				editor.getEditedPostAttribute( 'featured_media' );
			const media = featuredId
				? sel( 'core' ).getMedia( featuredId )
				: null;
			return {
				id: editor.getCurrentPostId(),
				type: editor.getCurrentPostType(),
				featuredId,
				featuredUrl: media?.source_url ?? null,
				isDirty: editor.isEditedPostDirty(),
				isSaving: editor.isSavingPost() && ! editor.isAutosavingPost(),
				didSave: editor.didPostSaveRequestSucceed(),
			};
		}, [] );

		const [ job, setJob ] = useState( { status: '', error: '' } );
		const [ isStarting, setStarting ] = useState( false );
		const [ requestError, setRequestError ] = useState( '' );
		const timer = useRef( null );
		const wasSaving = useRef( false );

		const stopPolling = () => {
			if ( timer.current ) {
				clearTimeout( timer.current );
				timer.current = null;
			}
		};

		const applyStatus = ( res ) => {
			setJob( { status: res.status || '', error: res.error || '' } );
			if ( res.status === 'done' && res.attachment_id ) {
				receiveFeaturedImage( post.type, post.id, res.attachment_id );
			}
			return res.status === 'queued' || res.status === 'running';
		};

		const poll = ( attempt = 0, isInitial = false ) => {
			stopPolling();
			apiRequest( endpoints.status + post.id, { method: 'GET' } )
				.then( ( res ) => {
					// A finished job from an earlier visit only matters while the post has no image.
					if (
						isInitial &&
						post.featuredId &&
						res.status !== 'queued' &&
						res.status !== 'running'
					) {
						return;
					}
					if ( applyStatus( res ) && attempt < POLL_LIMIT ) {
						timer.current = setTimeout(
							() => poll( attempt + 1 ),
							POLL_INTERVAL
						);
					}
				} )
				.catch( () => {} );
		};

		// Pick up a job queued earlier.
		useEffect( () => {
			if ( isEnabled && post.id ) {
				poll( 0, true );
			}
			return stopPolling;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ isEnabled, post.id ] );

		// Saving can queue a job (e.g. publishing with the AI method).
		useEffect( () => {
			if (
				isEnabled &&
				wasSaving.current &&
				! post.isSaving &&
				post.didSave
			) {
				poll();
			}
			wasSaving.current = post.isSaving;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ post.isSaving ] );

		const generateNow = () => {
			setRequestError( '' );
			setStarting( true );
			apiRequest( endpoints.featured + post.id, { method: 'POST' } )
				.then( ( res ) => {
					if ( applyStatus( res ) ) {
						poll();
					}
				} )
				.catch( ( err ) =>
					setRequestError(
						err?.message ||
							__(
								'Something went wrong. Please try again.',
								'artificial-image-generator'
							)
					)
				)
				.finally( () => setStarting( false ) );
		};

		return { post, job, isStarting, requestError, generateNow };
	}

	function FeaturedImageAIPanel( {
		featured: { post, job, isStarting, requestError, generateNow },
	} ) {
		const settings = ( window.aimgData && window.aimgData.settings ) || {};
		const { editPost } = useDispatch( 'core/editor' );

		const generator = useGenerator( {
			onSuccess: async ( image ) => {
				if ( ! image.id ) {
					throw new Error(
						__(
							'The generated image could not be added to the Media Library — a featured image needs an attachment ID.',
							'artificial-image-generator'
						)
					);
				}
				await editPost( { featured_media: image.id } );
			},
		} );

		const isWorking =
			isStarting || job.status === 'queued' || job.status === 'running';

		let buttonLabel = __( 'Generate', 'artificial-image-generator' );
		if ( job.status === 'failed' ) {
			buttonLabel = __( 'Try again', 'artificial-image-generator' );
		} else if ( post.featuredId ) {
			buttonLabel = __( 'Regenerate', 'artificial-image-generator' );
		}
		const canGenerate =
			! settings.methodIsAi ||
			( settings.canUseAi !== false && settings.hasApiKey );

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'aimg-featured__wrap' },
				post.featuredUrl &&
					el( 'img', {
						src: post.featuredUrl,
						alt: __(
							'Current featured image',
							'artificial-image-generator'
						),
						className: 'aimg-featured__preview',
					} ),

				isWorking &&
					el(
						'p',
						{ className: 'aimg-featured__status' },
						el( Spinner ),
						job.status === 'queued'
							? __(
									'Featured image queued…',
									'artificial-image-generator'
							  )
							: __(
									'Generating featured image…',
									'artificial-image-generator'
							  )
					),

				job.status === 'failed' &&
					el(
						Notice,
						{ status: 'error', isDismissible: false },
						sprintf(
							/* translators: %s: error message */
							__(
								'The featured image could not be generated: %s',
								'artificial-image-generator'
							),
							job.error
						)
					),

				requestError &&
					el(
						Notice,
						{ status: 'error', isDismissible: false },
						requestError
					),

				canGenerate &&
					el(
						Button,
						{
							variant: 'secondary',
							onClick: generateNow,
							disabled: isWorking || post.isDirty,
							className: 'aimg-featured__btn',
							icon: AIG_ICON,
						},
						buttonLabel
					),

				canGenerate &&
					el(
						'p',
						{ className: 'aimg-featured__help' },
						post.isDirty
							? __(
									'Save the post first; the saved title is used.',
									'artificial-image-generator'
							  )
							: sprintf(
									/* translators: %s: generation method, e.g. "Image template" */
									__(
										'Uses the method set in Image Generator → Settings: %s.',
										'artificial-image-generator'
									),
									settings.methodLabel
							  )
					),

				el(
					Button,
					{
						variant: 'link',
						onClick: generator.open,
						disabled: isWorking,
						className: 'aimg-featured__more',
					},
					__(
						'Choose a template, a stock photo or a prompt…',
						'artificial-image-generator'
					)
				)
			),

			generator.isModalOpen &&
				el( AIMGModal, {
					onClose: generator.close,
					onConfirm: generator.confirm,
					isLoading: generator.isLoading,
					error: generator.errorMsg,
					choices: generator.choices,
					onPick: generator.pick,
					modalTitle: __(
						'Generate Featured Image',
						'artificial-image-generator'
					),
				} )
		);
	}

	function FeaturedImagePanelSlot() {
		const isAvailable = useSelect( ( sel ) => {
			const type = sel( 'core' ).getPostType(
				sel( 'core/editor' ).getCurrentPostType()
			);
			return CAN_UPLOAD && !! type?.supports?.thumbnail;
		}, [] );
		const featured = useFeaturedJob( isAvailable );

		if ( ! isAvailable ) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aimg-featured-image',
				title: __( 'AI Featured Image', 'artificial-image-generator' ),
				icon: AIG_ICON,
			},
			el( FeaturedImageAIPanel, { featured } )
		);
	}

	registerPlugin( 'aimg-featured-image-panel', {
		render: FeaturedImagePanelSlot,
	} );
} )();
