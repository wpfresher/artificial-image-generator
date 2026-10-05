/**
 * Image Generator Admin JS
 * https://beautifulplugins.com/
 *
 * Copyright (c) 2026 BeautifulPlugins
 * Licensed under the GPLv2+ license.
 */
/* global jQuery */
jQuery( function ( $ ) {
	'use strict';
	const overlayPicker = {
		init() {
			this.bindEvents();
		},
		bindEvents() {
			$( '#upload_overlay_images' ).on(
				'click',
				this.handleSelectOverlayImages
			);
			$( document ).on(
				'click',
				'.remove-overlay',
				this.handleRemoveOverlayImage
			);
		},
		handleSelectOverlayImages( e ) {
			e.preventDefault();

			// Get currently displayed image IDs in the UI.
			const existingImageIds = [];
			$( '#overlay-image-list .aimg-overlay-images__item' ).each(
				function () {
					existingImageIds.push( parseInt( $( this ).data( 'id' ) ) );
				}
			);

			// Open the media uploader
			const mediaUploader = wp.media( {
				title: 'Select Overlay Images',
				button: {
					text: 'Select Images',
				},
				multiple: true,
				library: {
					type: 'image/png', // Restrict to PNG files only.
				},
			} );

			// Pre-select existing images.
			mediaUploader.on( 'open', function () {
				const selection = mediaUploader.state().get( 'selection' );
				selection.reset();

				existingImageIds.forEach( function ( id ) {
					const attachment = wp.media.attachment( id );
					attachment.fetch();
					selection.add( attachment );
				} );
			} );

			// When images are selected, process them.
			mediaUploader.on( 'select', function () {
				const attachments = mediaUploader
					.state()
					.get( 'selection' )
					.toJSON();
				const overlayImages = [];

				// Loop through selected images and prepare the data.
				$.each( attachments, function ( index, attachment ) {
					overlayImages.push( {
						id: attachment.id,
						url: attachment.url,
						title: attachment.title,
					} );
				} );

				// Loop through overlayImages and append only new ones.
				$.each( overlayImages, function ( index, image ) {
					if ( existingImageIds.indexOf( image.id ) === -1 ) {
						// Build with attr() so attachment titles/URLs are never parsed as HTML.
						const container = $(
							'<div class="aimg-overlay-images__item"></div>'
						).attr( 'data-id', image.id );
						container.append(
							$( '<img style="width:60px;height:60px;" />' ).attr(
								{ src: image.url, alt: image.title }
							)
						);
						container.append(
							'<button type="button" class="remove-overlay button button-secondary">X</button>'
						);

						$( '#overlay-image-list' ).append( container );
					}
				} );

				// Update the hidden input as araay of images ids only.
				const currentIds = $( '#overlay_images' ).val();
				const overlayImageIds = currentIds
					? JSON.parse( currentIds )
					: [];
				$.each( overlayImages, function ( index, image ) {
					if ( overlayImageIds.indexOf( image.id ) === -1 ) {
						overlayImageIds.push( image.id );
					}
				} );

				// Save the updated IDs back to the hidden input.
				$( '#overlay_images' ).val( JSON.stringify( overlayImageIds ) );
			} );

			mediaUploader.open();
		},
		handleRemoveOverlayImage( e ) {
			e.preventDefault();

			// Remove the overlay image from the UI.
			const $item = $( this ).closest( '.aimg-overlay-images__item' );
			const imageId = $item.data( 'id' );

			// Update the hidden input by removing the image ID.
			const currentIds = $( '#overlay_images' ).val();
			const overlayImageIds = currentIds ? JSON.parse( currentIds ) : [];
			const index = overlayImageIds.indexOf( imageId );
			if ( index !== -1 ) {
				overlayImageIds.splice( index, 1 );
			}

			// Save the updated IDs back to the hidden input.
			$( '#overlay_images' ).val( JSON.stringify( overlayImageIds ) );

			// Remove the item from the UI immediately.
			$item.remove();
		},
	};

	// Initializing the modules.
	overlayPicker.init();
} );
