const { __ } = wp.i18n;
const { useEffect, useState } = wp.element;
const apiFetch = wp.apiFetch;
const { Notice, Spinner } = wp.components;

import Filters from './Filters';
import Progress from './Progress';

import { PATH } from '../path';

const ACTIVE = [ 'running', 'paused' ];

export default function BulkPage() {
	const [ data, setData ] = useState( null );
	const [ run, setRun ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( ( response ) => {
				setData( response );
				setRun( response.run );
			} )
			.catch( ( e ) => setError( e.message ) );
	}, [] );

	const active = run && ACTIVE.includes( run.status );

	useEffect( () => {
		if ( ! active ) {
			return;
		}

		const timer = setInterval( () => {
			apiFetch( { path: PATH } )
				.then( ( response ) => setRun( response.run ) )
				.catch( () => {} );
		}, 3000 );

		return () => clearInterval( timer );
	}, [ active ] );

	let body;

	if ( ! data ) {
		body = error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<div className="aimg-bulk__loading">
				<Spinner />
			</div>
		);
	} else if ( run && run.status !== 'idle' ) {
		body = <Progress run={ run } onChange={ setRun } />;
	} else {
		body = <Filters data={ data } onStart={ setRun } />;
	}

	return (
		<div className="aimg-bulk">
			<div className="aimg-page__header">
				<div>
					<h1 className="aimg-page__title">
						{ __( 'Bulk Generate', 'artificial-image-generator' ) }
					</h1>
					<p className="aimg-page__subtitle">
						{ __(
							'Create featured images for posts you already have. Runs in the background; you can leave this page.',
							'artificial-image-generator'
						) }
					</p>
				</div>
			</div>
			{ body }
		</div>
	);
}
