const { __, _n, sprintf } = wp.i18n;
const { useState } = wp.element;
const apiFetch = wp.apiFetch;
const {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} = wp.components;

import { PATH } from '../path';

function toggle( list, value, checked ) {
	return checked
		? [ ...list, value ]
		: list.filter( ( item ) => item !== value );
}

export default function Filters( { data, onStart } ) {
	const { options } = data;
	const [ args, setArgs ] = useState( data.defaults );
	const [ preview, setPreview ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ confirmed, setConfirmed ] = useState( false );

	const update = ( key, value ) => {
		setArgs( ( current ) => ( { ...current, [ key ]: value } ) );
		setPreview( null );
		setConfirmed( false );
	};

	const call = ( path, then ) => {
		setBusy( true );
		setError( null );
		apiFetch( { path, method: 'POST', data: { args } } )
			.then( then )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setBusy( false ) );
	};

	const settingsUrl = window.aimgBulk?.settingsUrl;
	const needsConfirm = preview && preview.ai_requests > 0;

	return (
		<div className="aimg-bulk__card">
			<div className="aimg-bulk__grid">
				<fieldset className="aimg-bulk__field">
					<legend>
						{ __( 'Post types', 'artificial-image-generator' ) }
					</legend>
					{ options.post_types.map( ( type ) => (
						<CheckboxControl
							key={ type.value }
							label={ type.label }
							checked={ args.post_types.includes( type.value ) }
							onChange={ ( checked ) =>
								update(
									'post_types',
									toggle(
										args.post_types,
										type.value,
										checked
									)
								)
							}
							__nextHasNoMarginBottom
						/>
					) ) }
				</fieldset>

				<fieldset className="aimg-bulk__field">
					<legend>
						{ __( 'Post status', 'artificial-image-generator' ) }
					</legend>
					{ options.statuses.map( ( status ) => (
						<CheckboxControl
							key={ status.value }
							label={ status.label }
							checked={ args.statuses.includes( status.value ) }
							onChange={ ( checked ) =>
								update(
									'statuses',
									toggle(
										args.statuses,
										status.value,
										checked
									)
								)
							}
							__nextHasNoMarginBottom
						/>
					) ) }
				</fieldset>
			</div>

			<div className="aimg-bulk__grid">
				<SelectControl
					label={ __( 'Method', 'artificial-image-generator' ) }
					value={ args.method }
					options={ options.methods.map( ( method ) => ( {
						value: method.value,
						label: method.runnable
							? method.label
							: sprintf(
									/* translators: %s: generation method */
									__(
										'%s (needs an API key)',
										'artificial-image-generator'
									),
									method.label
							  ),
						disabled: ! method.runnable,
					} ) ) }
					onChange={ ( value ) => update( 'method', value ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>

				{ args.post_types.includes( 'post' ) &&
					options.categories.length > 0 && (
						<SelectControl
							label={ __(
								'Category',
								'artificial-image-generator'
							) }
							help={
								args.post_types.length > 1
									? __(
											'Only posts have categories; other post types are left out while one is chosen.',
											'artificial-image-generator'
									  )
									: undefined
							}
							value={ String( args.category ) }
							options={ [
								{
									value: '0',
									label: __(
										'All categories',
										'artificial-image-generator'
									),
								},
								...options.categories.map( ( term ) => ( {
									value: String( term.value ),
									label: term.label,
								} ) ),
							] }
							onChange={ ( value ) =>
								update( 'category', Number( value ) )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					) }
			</div>

			<div className="aimg-bulk__grid">
				<TextControl
					type="date"
					label={ __(
						'Published after',
						'artificial-image-generator'
					) }
					value={ args.after }
					onChange={ ( value ) => update( 'after', value ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="date"
					label={ __(
						'Published before',
						'artificial-image-generator'
					) }
					value={ args.before }
					onChange={ ( value ) => update( 'before', value ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</div>

			<ToggleControl
				label={ __(
					'Replace existing featured images',
					'artificial-image-generator'
				) }
				help={
					args.replace
						? __(
								'Every matching post gets a new image. The old images stay in the Media Library.',
								'artificial-image-generator'
						  )
						: __(
								'Only posts without a featured image. Posts where an author removed a generated image are left out.',
								'artificial-image-generator'
						  )
				}
				checked={ args.replace }
				onChange={ ( value ) => update( 'replace', value ) }
				__nextHasNoMarginBottom
			/>

			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			{ preview && (
				<div className="aimg-bulk__preview">
					<p className="aimg-bulk__count">
						{ preview.count
							? sprintf(
									/* translators: %d: number of posts */
									_n(
										'%d post will get a new featured image.',
										'%d posts will get a new featured image.',
										preview.count,
										'artificial-image-generator'
									),
									preview.count
							  )
							: __(
									'No posts match these filters.',
									'artificial-image-generator'
							  ) }
					</p>
					{ preview.more && (
						<p>
							{ __(
								'Only the newest 10,000 are included in one run; start another run afterwards for the rest.',
								'artificial-image-generator'
							) }
						</p>
					) }
					{ preview.ai_requests > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: 1: number of AI requests, 2: hourly limit */
								__(
									"This makes up to %1$d paid requests to your AI service. Each post counts against its author's limit of %2$d AI images per hour; the run waits when a limit is reached.",
									'artificial-image-generator'
								),
								preview.ai_requests,
								preview.hourly
							) }{ ' ' }
							{ settingsUrl && (
								<a href={ `${ settingsUrl }&tab=ai` }>
									{ __(
										'Change the limit',
										'artificial-image-generator'
									) }
								</a>
							) }
						</Notice>
					) }
					{ preview.posts.length > 0 && (
						<ul className="aimg-bulk__list">
							{ preview.posts.map( ( post ) => (
								<li key={ post.id } className="aimg-bulk__row">
									<a href={ post.link }>
										{ post.title ||
											__(
												'(no title)',
												'artificial-image-generator'
											) }
									</a>
									<span className="aimg-bulk__meta">
										{ post.type } · { post.date }
									</span>
								</li>
							) ) }
							{ preview.count > preview.posts.length && (
								<li className="aimg-bulk__row aimg-bulk__more">
									{ sprintf(
										/* translators: %d: number of posts not listed */
										__(
											'… and %d more',
											'artificial-image-generator'
										),
										preview.count - preview.posts.length
									) }
								</li>
							) }
						</ul>
					) }
					{ needsConfirm && preview.count > 0 && (
						<CheckboxControl
							label={ __(
								'I understand this uses my AI service credits.',
								'artificial-image-generator'
							) }
							checked={ confirmed }
							onChange={ setConfirmed }
							__nextHasNoMarginBottom
						/>
					) }
				</div>
			) }

			<div className="aimg-bulk__footer">
				<Button
					variant={ preview ? 'secondary' : 'primary' }
					onClick={ () =>
						call( `${ PATH }/preview`, ( response ) => {
							setArgs( response.args );
							setPreview( response );
						} )
					}
					isBusy={ busy && ! preview }
					disabled={
						busy ||
						! args.post_types.length ||
						! args.statuses.length
					}
					__next40pxDefaultSize
				>
					{ __( 'Preview', 'artificial-image-generator' ) }
				</Button>
				{ preview && preview.count > 0 && (
					<Button
						variant="primary"
						onClick={ () => call( PATH, onStart ) }
						isBusy={ busy }
						disabled={ busy || ( needsConfirm && ! confirmed ) }
						__next40pxDefaultSize
					>
						{ sprintf(
							/* translators: %d: number of images */
							_n(
								'Generate %d image',
								'Generate %d images',
								preview.count,
								'artificial-image-generator'
							),
							preview.count
						) }
					</Button>
				) }
			</div>
		</div>
	);
}
