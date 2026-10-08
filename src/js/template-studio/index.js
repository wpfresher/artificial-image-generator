/**
 * Template Studio entry. The mount point holds a server-rendered message that
 * stays visible if the Studio cannot start. Mounting waits for domReady so
 * scripts loaded after this one can still add layer types (see registry.js).
 */
import { STORE } from './store';
import App from './components/App';
import ErrorBoundary from './components/ErrorBoundary';

function mount() {
	const root = document.getElementById( 'aimg-template-studio' );

	if ( ! root || ! window.aimgStudio || ! wp.element || ! wp.components ) {
		return;
	}

	const data = window.aimgStudio;

	const { createElement: el } = wp.element;
	const template = data.template;

	wp.data.dispatch( STORE ).setFonts( data.capabilities.fonts );
	wp.data.dispatch( STORE ).load( {
		templateId: template ? template.id : 0,
		title: template ? template.title : '',
		status: template ? template.status : 'publish',
		document: template ? template.document : data.starter,
	} );

	const app = el( ErrorBoundary, null, el( App, { data } ) );

	if ( wp.element.createRoot ) {
		wp.element.createRoot( root ).render( app );
	} else {
		wp.element.render( app, root );
	}
}

if ( window.wp && wp.domReady ) {
	wp.domReady( mount );
}
