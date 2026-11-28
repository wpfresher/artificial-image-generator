/**
 * First-run setup app. The mount point holds a server-rendered message that
 * stays visible if the app cannot start.
 */
import SetupApp from './components/SetupApp';

const { createRoot } = wp.element;

wp.domReady( () => {
	const root = document.getElementById( 'aimg-setup' );

	if ( root && window.aimgSetup ) {
		createRoot( root ).render( <SetupApp data={ window.aimgSetup } /> );
	}
} );
