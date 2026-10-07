/**
 * Template Studio entry: mounts the editor over the classic template form.
 *
 * The classic form stays in the page and is only hidden once the Studio has
 * mounted, so it remains usable if anything here fails to load.
 */
import { STORE } from './store';
import App from './components/App';
import ErrorBoundary from './components/ErrorBoundary';

( function () {
	const root = document.getElementById( 'aimg-template-studio' );

	if (
		! root ||
		! window.aimgStudio ||
		! window.wp ||
		! wp.element ||
		! wp.components
	) {
		return;
	}

	const classic = document.getElementById( 'aimg-classic-form' );
	const data = window.aimgStudio;

	const { createElement: el } = wp.element;
	const template = data.template;

	wp.data.dispatch( STORE ).setFonts( data.capabilities.fonts );
	wp.data.dispatch( STORE ).load( {
		templateId: template ? template.id : 0,
		title: template ? template.title : '',
		status: template ? template.status : 'publish',
		hasDocument: template ? template.hasDocument : false,
		document: template ? template.document : data.starter,
	} );

	const showClassic = () => {
		root.hidden = true;
		if ( classic ) {
			classic.hidden = false;
		}
	};

	const onClassic = classic ? showClassic : null;
	const app = el(
		ErrorBoundary,
		{ onClassic },
		el( App, { data, onClassic } )
	);

	try {
		if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( app );
		} else {
			wp.element.render( app, root );
		}
		root.hidden = false;
		if ( classic ) {
			classic.hidden = true;
		}
	} catch {
		showClassic();
	}
} )();
