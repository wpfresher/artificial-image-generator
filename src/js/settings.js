import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

domReady( () => {
	document.querySelectorAll( '.aimg-test-stock' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const provider = button.dataset.provider;
			const input = document.getElementById(
				`aimg_settings_${ provider }_key`
			);
			const result = button.nextElementSibling;

			button.disabled = true;
			result.className = 'aimg-test-stock-result';
			result.textContent = __( 'Testing…', 'artificial-image-generator' );

			apiFetch( {
				path: `/aimg/v1/stock/${ provider }/test`,
				method: 'POST',
				data: { key: input && ! input.disabled ? input.value : '' },
			} )
				.then( ( response ) => {
					result.classList.add( 'is-success' );
					result.textContent = response.message;
				} )
				.catch( ( error ) => {
					result.classList.add( 'is-error' );
					result.textContent =
						error?.message ||
						__( 'The test failed.', 'artificial-image-generator' );
				} )
				.finally( () => {
					button.disabled = false;
				} );
		} );
	} );
} );
