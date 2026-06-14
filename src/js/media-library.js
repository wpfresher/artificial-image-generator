/**
 * Artificial Image Generator – Media Library integration.
 *
 * Adds a "Generate Image" button next to the page title on:
 *   1. Media Library   (upload.php)     – list & grid views
 *   2. Add New Media   (media-new.php)
 *
 * Clicking the button opens the exact same modal used in the block editor
 * (see ./components/aimg-modal.js). On success the image is already sideloaded
 * into the Media Library by the REST endpoint, so we redirect to the library
 * with the new attachment opened for review.
 */

import { AIMGModal, useGenerator } from './components/aimg-modal';

( function () {
	const { createElement: el, useEffect, useState, render, createRoot } = wp.element;
	const { __ } = wp.i18n;

	/**
	 * React app: owns the modal state and exposes an `open()` handle so the
	 * plain DOM button (rendered next to the page title) can trigger it.
	 */
	function MediaLibraryApp( { onReady } ) {
		const [ successUrl, setSuccessUrl ] = useState( '' );

		const { isModalOpen, isLoading, errorMsg, open, close, confirm } = useGenerator( {
			onSuccess: async ( data ) => {
				// The image is now in the Media Library. Remember where to go so
				// the redirect happens after the modal has closed.
				const base = ( window.aimgData && window.aimgData.uploadUrl ) || 'upload.php';
				setSuccessUrl( data.id ? base + '?item=' + data.id : base );
			},
		} );

		// Expose the opener to the external button once mounted.
		useEffect( () => {
			if ( typeof onReady === 'function' ) {
				onReady( open );
			}
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [] );

		// After a successful generation the modal closes itself; navigate then.
		useEffect( () => {
			if ( successUrl && ! isModalOpen ) {
				window.location.href = successUrl;
			}
		}, [ successUrl, isModalOpen ] );

		if ( ! isModalOpen ) {
			return null;
		}

		return el( AIMGModal, {
			onClose:    close,
			onConfirm:  confirm,
			isLoading,
			error:      errorMsg,
			modalTitle: __( 'Generate Image with Image Generator & AI', 'artificial-image-generator' ),
		} );
	}

	/**
	 * Insert the "Generate Image" button next to the page title.
	 *
	 * @param {Function} onClick Handler invoked when the button is clicked.
	 */
	function injectButton( onClick ) {
		// Avoid double-inserting.
		if ( document.querySelector( '.aimg-generate-action' ) ) {
			return;
		}

		const headerEnd = document.querySelector( '.wp-header-end' );
		const heading   = document.querySelector( '.wp-heading-inline' );
		if ( ! headerEnd && ! heading ) {
			return;
		}

		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'page-title-action aimg-generate-action';
		button.textContent = __( 'Generate Image', 'artificial-image-generator' );
		button.addEventListener( 'click', function ( evt ) {
			evt.preventDefault();
			onClick();
		} );

		if ( headerEnd ) {
			headerEnd.parentNode.insertBefore( button, headerEnd );
		} else {
			heading.parentNode.insertBefore( button, heading.nextSibling );
		}
	}

	function init() {
		const root = document.createElement( 'div' );
		root.className = 'aimg-media-library-root';
		document.body.appendChild( root );

		const app = el( MediaLibraryApp, {
			onReady: ( open ) => injectButton( open ),
		} );

		if ( typeof createRoot === 'function' ) {
			createRoot( root ).render( app );
		} else {
			render( app, root );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
