const { __, sprintf } = wp.i18n;
const { useEffect, useRef, useState } = wp.element;
const apiFetch = wp.apiFetch;
const { Button, CheckboxControl, Notice, SelectControl, Spinner, TextControl } =
	wp.components;

const STEPS = [ 'source', 'keys', 'design', 'preview' ];

const DESCRIPTIONS = {
	template: __(
		'Your own design with the post title, drawn on your server. Free, instant, and nothing leaves your site.',
		'artificial-image-generator'
	),
	ai_template: __(
		'A unique AI image for each post, using your OpenAI key (paid per image). Your template is used when AI is not available.',
		'artificial-image-generator'
	),
	stock_template: __(
		'A free photo from Unsplash, Pexels or Pixabay picked from the post title, with credit. Your template is used when no photo is found.',
		'artificial-image-generator'
	),
};

function KeyField( { id, info, secret, value, onChange } ) {
	const [ test, setTest ] = useState( null );

	const runTest = () => {
		setTest( { status: 'busy' } );
		apiFetch( {
			path: `/aimg/v1/stock/${ info.test }/test`,
			method: 'POST',
			data: { key: value || '' },
		} )
			.then( ( response ) =>
				setTest( { status: 'success', message: response.message } )
			)
			.catch( ( error ) =>
				setTest( {
					status: 'error',
					message:
						error?.message ||
						__( 'The test failed.', 'artificial-image-generator' ),
				} )
			);
	};

	if ( secret?.constant ) {
		return (
			<p className="aimg-setup__ok">
				{ sprintf(
					/* translators: %s: service name */
					__(
						'Your %s key is set in wp-config.php.',
						'artificial-image-generator'
					),
					info.service
				) }
			</p>
		);
	}

	return (
		<div className="aimg-setup__key">
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				id={ `aimg-setup-${ id }` }
				type="password"
				autoComplete="new-password"
				label={ info.label }
				placeholder={ secret?.masked || '' }
				help={
					secret?.saved
						? __(
								'A key is saved. Leave this empty to keep it.',
								'artificial-image-generator'
						  )
						: sprintf(
								/* translators: %s: service name */
								__(
									'Get a key from %s. You can also add it later in Settings.',
									'artificial-image-generator'
								),
								info.service
						  )
				}
				value={ value || '' }
				onChange={ onChange }
			/>
			<div className="aimg-setup__key-actions">
				<Button
					variant="link"
					href={ info.signupUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ sprintf(
						/* translators: %s: service name */
						__( 'Get your %s key', 'artificial-image-generator' ),
						info.service
					) }
				</Button>
				{ info.test && (
					<Button
						variant="secondary"
						size="compact"
						onClick={ runTest }
						isBusy={ test?.status === 'busy' }
						disabled={ test?.status === 'busy' }
					>
						{ __(
							'Test Connection',
							'artificial-image-generator'
						) }
					</Button>
				) }
			</div>
			{ test?.message && (
				<p
					className={ `aimg-setup__test is-${ test.status }` }
					aria-live="polite"
				>
					{ test.message }
				</p>
			) }
		</div>
	);
}

export default function SetupApp( { data } ) {
	const [ step, setStep ] = useState( 0 );
	const [ method, setMethod ] = useState( data.values.generation_method );
	const [ postTypes, setPostTypes ] = useState( () => {
		const rules = data.values.auto_post_types || {};
		const out = {};
		Object.keys( data.postTypes ).forEach( ( type ) => {
			out[ type ] = !! rules[ type ]?.enabled;
		} );
		return out;
	} );
	const [ stockProvider, setStockProvider ] = useState(
		data.values.stock_provider || data.stock[ 0 ]?.id || ''
	);
	const [ keys, setKeys ] = useState( {} );
	const [ template, setTemplate ] = useState(
		data.templates.find(
			( item ) => item.id === data.values.default_template_id
		)?.id ||
			data.templates[ 0 ]?.id ||
			''
	);
	const [ preview, setPreview ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ done, setDone ] = useState( false );
	const heading = useRef();

	useEffect( () => {
		heading.current?.focus();
	}, [ step, done ] );

	useEffect( () => {
		if ( STEPS[ step ] !== 'preview' || ! template ) {
			return;
		}
		setPreview( { status: 'busy' } );
		apiFetch( {
			path: `/aimg/v1/templates/${ template }/preview`,
			method: 'POST',
			data: { title: data.sampleTitle },
		} )
			.then( ( response ) =>
				setPreview( { status: 'done', image: response.image } )
			)
			.catch( ( err ) =>
				setPreview( {
					status: 'error',
					message:
						err?.message ||
						__(
							'The preview could not be drawn.',
							'artificial-image-generator'
						),
				} )
			);
	}, [ step, template, data.sampleTitle ] );

	let keyId = '';
	if ( method === 'ai_template' ) {
		keyId = 'api_key';
	} else if ( method === 'stock_template' && stockProvider ) {
		keyId = `${ stockProvider }_key`;
	}

	const finish = () => {
		setSaving( true );
		setError( '' );

		const rules = {};
		Object.keys( data.postTypes ).forEach( ( type ) => {
			rules[ type ] = {
				...( data.values.auto_post_types?.[ type ] || {} ),
				enabled: postTypes[ type ],
			};
		} );

		apiFetch( {
			path: '/aimg/v1/settings',
			method: 'POST',
			data: {
				values: {
					...keys,
					generation_method: method,
					stock_provider:
						method === 'stock_template'
							? stockProvider
							: data.values.stock_provider,
					default_template_id: template || '0',
					auto_post_types: rules,
				},
			},
		} )
			.then( () =>
				apiFetch( { path: '/aimg/v1/setup/done', method: 'POST' } )
			)
			.then( () => setDone( true ) )
			.catch( ( err ) =>
				setError(
					err?.message ||
						__(
							'The settings could not be saved.',
							'artificial-image-generator'
						)
				)
			)
			.finally( () => setSaving( false ) );
	};

	if ( done ) {
		return (
			<div className="aimg-setup">
				<div className="aimg-setup__card">
					<h1 ref={ heading } tabIndex="-1">
						{ __(
							'You are all set',
							'artificial-image-generator'
						) }
					</h1>
					<p>
						{ __(
							'New posts of the types you picked now get a featured image when they are saved without one.',
							'artificial-image-generator'
						) }
					</p>
					<div className="aimg-setup__next">
						<Button variant="primary" href={ data.urls.bulk }>
							{ __(
								'Add images to existing posts',
								'artificial-image-generator'
							) }
						</Button>
						<Button
							variant="secondary"
							href={ data.urls.templates }
						>
							{ __(
								'Design more templates',
								'artificial-image-generator'
							) }
						</Button>
						<Button variant="tertiary" href={ data.urls.settings }>
							{ __(
								'All settings',
								'artificial-image-generator'
							) }
						</Button>
					</div>
				</div>
			</div>
		);
	}

	const name = STEPS[ step ];
	const titles = {
		source: __(
			'Where should featured images come from?',
			'artificial-image-generator'
		),
		keys: __( 'Connect your service', 'artificial-image-generator' ),
		design: __( 'Pick a design', 'artificial-image-generator' ),
		preview: __( 'Here is how it looks', 'artificial-image-generator' ),
	};
	const isLast = step === STEPS.length - 1;

	return (
		<div className="aimg-setup">
			<ol
				className="aimg-setup__steps"
				aria-label={ __( 'Setup steps', 'artificial-image-generator' ) }
			>
				{ STEPS.map( ( id, index ) => (
					<li
						key={ id }
						className={ index === step ? 'is-current' : '' }
						aria-current={ index === step ? 'step' : undefined }
					>
						<span className="aimg-setup__step-number">
							{ index + 1 }
						</span>{ ' ' }
						{ titles[ id ] }
					</li>
				) ) }
			</ol>
			<div className="aimg-setup__card">
				<p className="aimg-setup__count">
					{ sprintf(
						/* translators: 1: current step, 2: number of steps */
						__( 'Step %1$d of %2$d', 'artificial-image-generator' ),
						step + 1,
						STEPS.length
					) }
				</p>
				<h1 ref={ heading } tabIndex="-1">
					{ titles[ name ] }
				</h1>

				{ name === 'source' && (
					<>
						<div
							className="aimg-setup__methods"
							role="radiogroup"
							aria-label={ titles.source }
						>
							{ data.methods.map( ( item ) => (
								<button
									key={ item.id }
									type="button"
									role="radio"
									aria-checked={ method === item.id }
									className={ `aimg-setup__method${
										method === item.id ? ' is-selected' : ''
									}` }
									onClick={ () => setMethod( item.id ) }
								>
									<strong>{ item.label }</strong>
									<span>{ DESCRIPTIONS[ item.id ] }</span>
								</button>
							) ) }
						</div>
						<fieldset className="aimg-setup__types">
							<legend>
								{ __(
									'Make images automatically for',
									'artificial-image-generator'
								) }
							</legend>
							{ Object.entries( data.postTypes ).map(
								( [ type, label ] ) => (
									<CheckboxControl
										key={ type }
										__nextHasNoMarginBottom
										label={ label }
										checked={ !! postTypes[ type ] }
										onChange={ ( checked ) =>
											setPostTypes( {
												...postTypes,
												[ type ]: checked,
											} )
										}
									/>
								)
							) }
						</fieldset>
					</>
				) }

				{ name === 'keys' && (
					<>
						{ ! keyId && (
							<p>
								{ __(
									'Templates need no account or key. Continue to pick a design.',
									'artificial-image-generator'
								) }
							</p>
						) }
						{ method === 'stock_template' && (
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Photo library',
									'artificial-image-generator'
								) }
								value={ stockProvider }
								options={ data.stock.map( ( item ) => ( {
									value: item.id,
									label: item.label,
								} ) ) }
								onChange={ setStockProvider }
							/>
						) }
						{ keyId && data.keys[ keyId ] && (
							<KeyField
								key={ keyId }
								id={ keyId }
								info={ data.keys[ keyId ] }
								secret={ data.secrets[ keyId ] }
								value={ keys[ keyId ] }
								onChange={ ( next ) =>
									setKeys( { ...keys, [ keyId ]: next } )
								}
							/>
						) }
						{ keyId && (
							<p className="aimg-setup__hint">
								{ __(
									'No key yet? You can skip this. Your template is used until a key is added.',
									'artificial-image-generator'
								) }
							</p>
						) }
					</>
				) }

				{ name === 'design' && (
					<>
						{ ! data.templates.length && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'There is no published template yet.',
									'artificial-image-generator'
								) }{ ' ' }
								<a href={ data.urls.newTemplate }>
									{ __(
										'Create one in the Template Studio',
										'artificial-image-generator'
									) }
								</a>
							</Notice>
						) }
						<div
							className="aimg-setup__templates"
							role="radiogroup"
							aria-label={ titles.design }
						>
							{ data.templates.map( ( item ) => (
								<button
									key={ item.id }
									type="button"
									role="radio"
									aria-checked={ template === item.id }
									className={ `aimg-setup__template${
										template === item.id
											? ' is-selected'
											: ''
									}` }
									onClick={ () => setTemplate( item.id ) }
								>
									{ item.preview ? (
										<img src={ item.preview } alt="" />
									) : (
										<span className="aimg-setup__no-preview" />
									) }
									<span>{ item.title }</span>
								</button>
							) ) }
						</div>
						<p className="aimg-setup__hint">
							<a
								href={ data.urls.newTemplate }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __(
									'Design your own in the Template Studio',
									'artificial-image-generator'
								) }
							</a>
						</p>
					</>
				) }

				{ name === 'preview' && (
					<div className="aimg-setup__preview" aria-live="polite">
						{ ! template && (
							<p>
								{ __(
									'Pick a design first.',
									'artificial-image-generator'
								) }
							</p>
						) }
						{ preview?.status === 'busy' && <Spinner /> }
						{ preview?.status === 'error' && (
							<Notice status="error" isDismissible={ false }>
								{ preview.message }
							</Notice>
						) }
						{ preview?.status === 'done' && (
							<img
								src={ preview.image }
								alt={ sprintf(
									/* translators: %s: post title */
									__(
										'Preview of a featured image for “%s”',
										'artificial-image-generator'
									),
									data.sampleTitle
								) }
							/>
						) }
						<p className="aimg-setup__hint">
							{ method === 'template'
								? sprintf(
										/* translators: %s: post title */
										__(
											'A post titled “%s” gets this image. Nothing is added to your posts until you finish.',
											'artificial-image-generator'
										),
										data.sampleTitle
								  )
								: __(
										'This design is used when an AI image or stock photo is not available. Nothing is added to your posts until you finish.',
										'artificial-image-generator'
								  ) }
						</p>
					</div>
				) }

				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				<div className="aimg-setup__nav">
					{ step > 0 && (
						<Button
							variant="tertiary"
							onClick={ () => setStep( step - 1 ) }
							disabled={ saving }
						>
							{ __( 'Back', 'artificial-image-generator' ) }
						</Button>
					) }
					<Button variant="link" href={ data.urls.settings }>
						{ __( 'Skip setup', 'artificial-image-generator' ) }
					</Button>
					{ isLast ? (
						<Button
							variant="primary"
							onClick={ finish }
							isBusy={ saving }
							disabled={ saving }
						>
							{ __(
								'Finish setup',
								'artificial-image-generator'
							) }
						</Button>
					) : (
						<Button
							variant="primary"
							onClick={ () => setStep( step + 1 ) }
						>
							{ __( 'Continue', 'artificial-image-generator' ) }
						</Button>
					) }
				</div>
			</div>
		</div>
	);
}
