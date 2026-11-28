const { __, sprintf } = wp.i18n;
const { createInterpolateElement, useEffect, useRef, useState } = wp.element;
const apiFetch = wp.apiFetch;
const {
	BaseControl,
	Button,
	CheckboxControl,
	ColorIndicator,
	ComboboxControl,
	ColorPicker,
	Dropdown,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} = wp.components;

function ColorField( { field, value, onChange } ) {
	const id = `aimg-setting-${ field.id }`;

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ id }
			label={ field.label }
			help={ field.help }
		>
			<div className="aimg-settings__color">
				<Dropdown
					popoverProps={ { placement: 'bottom-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							className="aimg-settings__swatch"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							label={ __(
								'Choose a color',
								'artificial-image-generator'
							) }
						>
							<ColorIndicator
								colorValue={ value || field.placeholder }
							/>
						</Button>
					) }
					renderContent={ () => (
						<ColorPicker
							color={ value || field.placeholder }
							onChange={ onChange }
							enableAlpha={ false }
						/>
					) }
				/>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					id={ id }
					value={ value || '' }
					placeholder={ field.placeholder }
					onChange={ onChange }
				/>
			</div>
		</BaseControl>
	);
}

function SecretField( {
	field,
	value,
	secret = {},
	values,
	onChange,
	onUpdate,
} ) {
	const [ test, setTest ] = useState( null );
	const removeId = `remove_${ field.id }`;
	const removing = !! values[ removeId ];

	const runTest = () => {
		setTest( { status: 'busy' } );
		apiFetch( {
			path: `/aimg/v1/stock/${ field.test }/test`,
			method: 'POST',
			data: { key: secret.constant ? '' : value || '' },
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

	let help;
	if ( secret.constant ) {
		help = createInterpolateElement(
			sprintf(
				/* translators: %s: PHP constant name */
				__(
					'Defined with the <code>%s</code> constant in wp-config.php.',
					'artificial-image-generator'
				),
				field.constant
			),
			{ code: <code /> }
		);
	} else if ( secret.saved ) {
		help = createInterpolateElement(
			sprintf(
				/* translators: %s: service name */
				__(
					'A key is saved. Leave the field empty to keep it, or enter a new key from <a>%s</a> to replace it.',
					'artificial-image-generator'
				),
				field.service
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={ field.signupUrl }
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		);
	} else {
		help = createInterpolateElement(
			sprintf(
				/* translators: 1: service name, 2: PHP constant name */
				__(
					'Get a key from <a>%1$s</a>, or define the <code>%2$s</code> constant in wp-config.php.',
					'artificial-image-generator'
				),
				field.service,
				field.constant
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={ field.signupUrl }
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
				code: <code />,
			}
		);
	}

	return (
		<div className="aimg-settings__secret">
			<div className="aimg-settings__secret-row">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="password"
					autoComplete="new-password"
					label={
						<>
							{ field.label }
							{ ( secret.saved || secret.constant ) && (
								<span className="aimg-settings__status is-success">
									{ __(
										'Key saved',
										'artificial-image-generator'
									) }
								</span>
							) }
						</>
					}
					value={ value || '' }
					placeholder={ secret.masked || field.placeholder }
					disabled={ secret.constant || removing }
					onChange={ onChange }
				/>
				{ field.test && (
					<Button
						variant="secondary"
						onClick={ runTest }
						isBusy={ test?.status === 'busy' }
						disabled={ test?.status === 'busy' }
						__next40pxDefaultSize
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
					className={ `aimg-settings__test is-${ test.status }` }
					aria-live="polite"
				>
					{ test.message }
				</p>
			) }
			<p className="components-base-control__help">{ help }</p>
			{ secret.saved && ! secret.constant && (
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __(
						'Remove the saved API key',
						'artificial-image-generator'
					) }
					checked={ removing }
					onChange={ ( checked ) => onUpdate( removeId, checked ) }
				/>
			) }
		</div>
	);
}

// Methods that can draw a template, so a template choice matters.
const TEMPLATE_METHODS = [
	'template',
	'template_ai',
	'ai_template',
	'stock_template',
];

function PostTypesField( { field, value, values, onChange } ) {
	const rules = value || {};

	const update = ( postType, key, next ) =>
		onChange( {
			...rules,
			[ postType ]: { ...( rules[ postType ] || {} ), [ key ]: next },
		} );

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `aimg-setting-${ field.id }` }
			label={ field.label }
			help={ field.help }
		>
			<div className="aimg-settings__post-types">
				{ field.postTypes.map( ( type ) => {
					const rule = rules[ type.value ] || {};
					const method = rule.method || values.generation_method;

					return (
						<div
							key={ type.value }
							className="aimg-settings__post-type"
						>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ type.label }
								checked={ !! rule.enabled }
								onChange={ ( next ) =>
									update( type.value, 'enabled', next )
								}
							/>
							{ rule.enabled && (
								<div className="aimg-settings__post-type-options">
									<SelectControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __(
											'Method',
											'artificial-image-generator'
										) }
										hideLabelFromVision
										value={ rule.method || '' }
										options={ field.methods }
										onChange={ ( next ) =>
											update( type.value, 'method', next )
										}
									/>
									{ TEMPLATE_METHODS.includes( method ) && (
										<SelectControl
											__nextHasNoMarginBottom
											__next40pxDefaultSize
											label={ __(
												'Template',
												'artificial-image-generator'
											) }
											hideLabelFromVision
											value={ String(
												rule.template || '0'
											) }
											options={ field.templates }
											onChange={ ( next ) =>
												update(
													type.value,
													'template',
													next
												)
											}
										/>
									) }
								</div>
							) }
						</div>
					);
				} ) }
			</div>
		</BaseControl>
	);
}

function RuleItemPicker( { condition, rule, onChange } ) {
	const selected = rule.item
		? [ { value: rule.item, label: rule.itemLabel || `#${ rule.item }` } ]
		: [];
	const [ found, setFound ] = useState( [] );
	const timer = useRef();

	const search = ( query ) => {
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			apiFetch( {
				path: `${ condition.path }?search=${ encodeURIComponent(
					query || ''
				) }&per_page=20&_fields=id,name`,
			} )
				.then( ( items ) =>
					setFound(
						items.map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) )
					)
				)
				.catch( () => setFound( [] ) );
		}, 250 );
	};

	useEffect( () => {
		search( '' );
		return () => clearTimeout( timer.current );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ condition.path ] );

	const options = [
		...selected,
		...found.filter( ( option ) => option.value !== rule.item ),
	];

	return (
		<ComboboxControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ condition.label }
			hideLabelFromVision
			placeholder={ sprintf(
				/* translators: %s: taxonomy or "Author" */
				__( 'Search %s…', 'artificial-image-generator' ),
				condition.label.toLowerCase()
			) }
			value={ rule.item || null }
			options={ options }
			onFilterValueChange={ search }
			onChange={ ( value ) =>
				onChange( {
					item: value || '',
					itemLabel:
						options.find( ( option ) => option.value === value )
							?.label || '',
				} )
			}
		/>
	);
}

function TemplateRulesField( { field, value, onChange } ) {
	const rules = Array.isArray( value ) ? value : [];
	const first = field.conditions[ 0 ]?.value || 'author';
	const firstTemplate = field.templates[ 0 ]?.value || '';

	const update = ( index, patch ) =>
		onChange(
			rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...patch } : rule
			)
		);
	const move = ( index, by ) => {
		const next = [ ...rules ];
		const [ rule ] = next.splice( index, 1 );
		next.splice( index + by, 0, rule );
		onChange( next );
	};

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `aimg-setting-${ field.id }` }
			label={ field.label }
			help={ field.help }
		>
			<div className="aimg-settings__rules">
				{ ! field.templates.length && (
					<p className="aimg-settings__rules-empty">
						{ __(
							'Publish a template first to add rules.',
							'artificial-image-generator'
						) }
					</p>
				) }
				{ rules.map( ( rule, index ) => {
					const condition =
						field.conditions.find(
							( option ) => option.value === rule.condition
						) || field.conditions[ 0 ];

					return (
						<div
							key={ index }
							className="aimg-settings__rule"
							role="group"
							aria-label={ sprintf(
								/* translators: %d: rule number */
								__( 'Rule %d', 'artificial-image-generator' ),
								index + 1
							) }
						>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Match',
									'artificial-image-generator'
								) }
								hideLabelFromVision
								value={ condition.value }
								options={ field.conditions.map(
									( { value: v, label } ) => ( {
										value: v,
										label,
									} )
								) }
								onChange={ ( next ) =>
									update( index, {
										condition: next,
										item: '',
										itemLabel: '',
									} )
								}
							/>
							<RuleItemPicker
								condition={ condition }
								rule={ rule }
								onChange={ ( patch ) => update( index, patch ) }
							/>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Template',
									'artificial-image-generator'
								) }
								hideLabelFromVision
								value={ String( rule.template || '' ) }
								options={ field.templates }
								onChange={ ( next ) =>
									update( index, { template: next } )
								}
							/>
							<div className="aimg-settings__rule-actions">
								<Button
									icon="arrow-up-alt2"
									size="small"
									label={ __(
										'Move up',
										'artificial-image-generator'
									) }
									disabled={ 0 === index }
									onClick={ () => move( index, -1 ) }
								/>
								<Button
									icon="arrow-down-alt2"
									size="small"
									label={ __(
										'Move down',
										'artificial-image-generator'
									) }
									disabled={ rules.length - 1 === index }
									onClick={ () => move( index, 1 ) }
								/>
								<Button
									icon="trash"
									size="small"
									isDestructive
									label={ __(
										'Remove rule',
										'artificial-image-generator'
									) }
									onClick={ () =>
										onChange(
											rules.filter(
												( r, i ) => i !== index
											)
										)
									}
								/>
							</div>
						</div>
					);
				} ) }
				{ field.templates.length > 0 && (
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () =>
							onChange( [
								...rules,
								{
									condition: first,
									item: '',
									itemLabel: '',
									template: firstTemplate,
								},
							] )
						}
					>
						{ __( 'Add rule', 'artificial-image-generator' ) }
					</Button>
				) }
			</div>
		</BaseControl>
	);
}

export default function Field( props ) {
	const { field, value, onChange } = props;
	const common = {
		__nextHasNoMarginBottom: true,
		label: field.label,
		help: field.help,
	};

	switch ( field.type ) {
		case 'toggle':
			return (
				<ToggleControl
					{ ...common }
					checked={ !! value }
					onChange={ onChange }
				/>
			);
		case 'textarea':
			return (
				<TextareaControl
					{ ...common }
					rows={ 3 }
					value={ value || '' }
					placeholder={ field.placeholder }
					onChange={ onChange }
				/>
			);
		case 'select':
			return (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					value={ value }
					options={ field.options || [] }
					onChange={ onChange }
				/>
			);
		case 'number':
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					type="number"
					min={ field.min }
					max={ field.max }
					value={ value }
					onChange={ ( next ) =>
						onChange( next === '' ? '' : Number( next ) )
					}
				/>
			);
		case 'color':
			return <ColorField { ...props } />;
		case 'secret':
			return <SecretField { ...props } />;
		case 'post_types':
			return <PostTypesField { ...props } />;
		case 'template_rules':
			return <TemplateRulesField { ...props } />;
		default:
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					value={ value || '' }
					placeholder={ field.placeholder }
					onChange={ onChange }
				/>
			);
	}
}
