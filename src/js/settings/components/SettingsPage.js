const { __ } = wp.i18n;
const { useEffect, useState } = wp.element;
const apiFetch = wp.apiFetch;
const { addQueryArgs, getQueryArg } = wp.url;
const { applyFilters } = wp.hooks;
const { Button, Notice, Spinner, TabPanel } = wp.components;

import Field from './Field';

const PATH = '/aimg/v1/settings';

// A field with `show_if` ({ settingId: [ values ] }) shows only while every listed setting matches.
function isShown( field, values ) {
	return Object.entries( field.show_if || {} ).every( ( [ id, allowed ] ) =>
		allowed.includes( values[ id ] )
	);
}

export default function SettingsPage() {
	const [ data, setData ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ dirty, setDirty ] = useState( false );

	const load = ( response ) => {
		setData( response );
		setValues( response.values );
		setDirty( false );
	};

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( load )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			);
	}, [] );

	useEffect( () => {
		const warn = ( event ) => {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const update = ( id, value ) => {
		setValues( ( current ) => ( { ...current, [ id ]: value } ) );
		setDirty( true );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: PATH, method: 'POST', data: { values } } )
			.then( ( response ) => {
				load( response );
				setNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'artificial-image-generator'
					),
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			)
			.finally( () => setSaving( false ) );
	};

	if ( ! data ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : (
			<div className="aimg-settings__loading">
				<Spinner />
			</div>
		);
	}

	const tabIds = data.tabs.map( ( tab ) => tab.id );
	const requested = getQueryArg( window.location.href, 'tab' );
	const initialTab = tabIds.includes( requested ) ? requested : tabIds[ 0 ];

	return (
		<>
			<div className="aimg-page__header">
				<div>
					<h1 className="aimg-page__title">
						{ __( 'Settings', 'artificial-image-generator' ) }
					</h1>
					<p className="aimg-page__subtitle">
						{ __(
							'Automatic featured images, the AI service and stock photo libraries.',
							'artificial-image-generator'
						) }
					</p>
				</div>
				{ window.aimgSettings?.setupUrl && (
					<Button
						variant="secondary"
						href={ window.aimgSettings.setupUrl }
					>
						{ __( 'Run setup', 'artificial-image-generator' ) }
					</Button>
				) }
			</div>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<div className="aimg-settings__card">
				<TabPanel
					className="aimg-settings__tabs"
					tabs={ data.tabs.map( ( tab ) => ( {
						name: tab.id,
						title: tab.title,
					} ) ) }
					initialTabName={ initialTab }
					onSelect={ ( tab ) =>
						window.history.replaceState(
							{},
							'',
							addQueryArgs( window.location.href, { tab } )
						)
					}
				>
					{ ( tab ) => (
						<div className="aimg-settings__panel">
							{ data.fields
								.filter(
									( field ) =>
										field.tab === tab.name &&
										isShown( field, values )
								)
								.map( ( field ) => (
									<div
										key={ field.id }
										className="aimg-settings__setting"
									>
										<Field
											field={ field }
											value={ values[ field.id ] }
											secret={
												data.secrets?.[ field.id ]
											}
											values={ values }
											onChange={ ( value ) =>
												update( field.id, value )
											}
											onUpdate={ update }
										/>
									</div>
								) ) }
							{ applyFilters(
								'aimg.settings.tab',
								null,
								tab.name,
								values,
								update
							) }
							<div className="aimg-settings__footer">
								{ dirty && (
									<span className="aimg-settings__unsaved">
										{ __(
											'You have unsaved changes.',
											'artificial-image-generator'
										) }
									</span>
								) }
								<Button
									variant="primary"
									onClick={ save }
									isBusy={ saving }
									disabled={ saving || ! dirty }
									__next40pxDefaultSize
								>
									{ __(
										'Save settings',
										'artificial-image-generator'
									) }
								</Button>
							</div>
						</div>
					) }
				</TabPanel>
			</div>
		</>
	);
}
