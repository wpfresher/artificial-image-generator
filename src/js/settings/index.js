/**
 * Settings app. The mount point holds a server-rendered message that stays
 * visible if the app cannot start. Mounting waits for domReady so scripts
 * loaded after this one can still add `aimg.settings.tab` filters.
 */
import SettingsPage from './components/SettingsPage';
import Sidebar from './components/Sidebar';

const { createRoot } = wp.element;
const domReady = wp.domReady;

const config = window.aimgSettings || {};

domReady( () => {
	const root = document.getElementById( 'aimg-settings' );

	if ( root ) {
		createRoot( root ).render(
			<div className="aimg-settings__layout">
				<div className="aimg-settings__content">
					<SettingsPage />
				</div>
				<Sidebar config={ config } />
			</div>
		);
	}
} );
