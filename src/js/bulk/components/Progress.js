const { __, _n, sprintf } = wp.i18n;
const { useState } = wp.element;
const apiFetch = wp.apiFetch;
const { Button, Notice } = wp.components;

import { PATH } from '../path';

function statusText( run ) {
	if ( run.status === 'paused' ) {
		return __( 'Paused', 'artificial-image-generator' );
	}
	if ( run.status === 'cancelled' ) {
		return __( 'Cancelled', 'artificial-image-generator' );
	}
	if ( run.status === 'done' ) {
		return __( 'Finished', 'artificial-image-generator' );
	}
	if ( run.waiting ) {
		return __( 'Waiting', 'artificial-image-generator' );
	}
	return __( 'Generating…', 'artificial-image-generator' );
}

export default function Progress( { run, onChange } ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const control = ( action ) => {
		setBusy( true );
		setError( null );
		apiFetch( { path: `${ PATH }/${ action }`, method: 'POST' } )
			.then( onChange )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setBusy( false ) );
	};

	const percent = run.total
		? Math.round( ( run.processed / run.total ) * 100 )
		: 0;
	const active = run.status === 'running' || run.status === 'paused';

	return (
		<div className="aimg-bulk__card">
			<div className="aimg-bulk__status">
				<strong>{ statusText( run ) }</strong>
				<span>
					{ sprintf(
						/* translators: 1: posts processed, 2: total posts */
						__(
							'%1$d of %2$d posts',
							'artificial-image-generator'
						),
						run.processed,
						run.total
					) }
				</span>
			</div>

			<div
				className={ `aimg-bulk__bar is-${ run.status }` }
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ 100 }
				aria-valuenow={ percent }
			>
				<span
					className="aimg-bulk__fill"
					style={ { width: `${ percent }%` } }
				/>
			</div>

			<ul className="aimg-bulk__stats">
				<li className="aimg-bulk__stat">
					<strong>{ run.done }</strong>
					{ __( 'Generated', 'artificial-image-generator' ) }
				</li>
				<li className="aimg-bulk__stat">
					<strong>{ run.skipped }</strong>
					{ __( 'Skipped', 'artificial-image-generator' ) }
				</li>
				<li
					className={ `aimg-bulk__stat${
						run.failed ? ' has-errors' : ''
					}` }
				>
					<strong>{ run.failed }</strong>
					{ __( 'Failed', 'artificial-image-generator' ) }
				</li>
				<li className="aimg-bulk__stat">
					<strong>{ run.remaining }</strong>
					{ __( 'Remaining', 'artificial-image-generator' ) }
				</li>
			</ul>

			{ run.waiting && run.message && (
				<Notice status="warning" isDismissible={ false }>
					{ run.message }{ ' ' }
					{ __(
						'The run continues automatically.',
						'artificial-image-generator'
					) }
				</Notice>
			) }

			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<div className="aimg-bulk__footer">
				{ run.status === 'running' && (
					<Button
						variant="secondary"
						onClick={ () => control( 'pause' ) }
						disabled={ busy }
						__next40pxDefaultSize
					>
						{ __( 'Pause', 'artificial-image-generator' ) }
					</Button>
				) }
				{ run.status === 'paused' && (
					<Button
						variant="primary"
						onClick={ () => control( 'resume' ) }
						disabled={ busy }
						__next40pxDefaultSize
					>
						{ __( 'Resume', 'artificial-image-generator' ) }
					</Button>
				) }
				{ active && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => control( 'cancel' ) }
						disabled={ busy }
						__next40pxDefaultSize
					>
						{ __( 'Cancel run', 'artificial-image-generator' ) }
					</Button>
				) }
				{ ! active && (
					<Button
						variant="primary"
						onClick={ () => control( 'clear' ) }
						disabled={ busy }
						__next40pxDefaultSize
					>
						{ __(
							'Start a new run',
							'artificial-image-generator'
						) }
					</Button>
				) }
			</div>

			{ run.recent.length > 0 && (
				<>
					<h2 className="aimg-bulk__heading">
						{ __( 'Latest images', 'artificial-image-generator' ) }
					</h2>
					<ul className="aimg-bulk__thumbs">
						{ run.recent.slice( 0, 12 ).map( ( item ) => (
							<li key={ item.id } className="aimg-bulk__thumb">
								<a href={ item.link }>
									{ item.image && (
										<img src={ item.image } alt="" />
									) }
									<span className="aimg-bulk__caption">
										{ item.title }
									</span>
								</a>
							</li>
						) ) }
					</ul>
				</>
			) }

			{ run.errors.length > 0 && (
				<>
					<h2 className="aimg-bulk__heading">
						{ sprintf(
							/* translators: %d: number of failed posts */
							_n(
								'%d post failed',
								'%d posts failed',
								run.failed,
								'artificial-image-generator'
							),
							run.failed
						) }
					</h2>
					<ul className="aimg-bulk__list aimg-bulk__errors">
						{ run.errors.map( ( item ) => (
							<li key={ item.id } className="aimg-bulk__row">
								<a href={ item.link }>{ item.title }</a>
								<span className="aimg-bulk__meta aimg-bulk__error">
									{ item.error }
								</span>
							</li>
						) ) }
					</ul>
				</>
			) }
		</div>
	);
}
