/**
 * Bulk Generate app. The mount point holds a server-rendered message that
 * stays visible if the app cannot start.
 */
import BulkPage from './components/BulkPage';

const { createRoot } = wp.element;

wp.domReady( () => {
	const root = document.getElementById( 'aimg-bulk' );

	if ( root ) {
		createRoot( root ).render( <BulkPage /> );
	}
} );
