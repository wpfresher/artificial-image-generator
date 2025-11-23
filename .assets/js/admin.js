/**
 * Auto Image Generator Admin JS
 * https://urldev.com/
 *
 * Copyright (c) 2025 UrlDev
 * Licensed under the GPLv2+ license.
 */
jQuery(function ($) {
	'use strict';
	var aimg_dmin = {
		init: function () {
			this.bindEvents();
		},
		bindEvents: function () {
			$('#upload_overlay_images').on('click', this.handleSelectOverlayImages);
			$(document).on('click', '.remove-overlay', this.handleRemoveOverlayImage);
		},
		handleSelectOverlayImages: function (e) {
			e.preventDefault();

			// Get currently displayed image IDs in the UI.
			var existingImageIds = [];
			$('#overlay-image-list .aimg-overlay-images__item').each(function () {
				existingImageIds.push(parseInt($(this).data('id')));
			});

			// Open the media uploader
			var mediaUploader = wp.media({
				title: 'Select Overlay Images',
				button: {
					text: 'Select Images'
				},
				multiple: true,
				library: {
					type: 'image/png' // Restrict to PNG files only.
				}
			});

			// Pre-select existing images.
			mediaUploader.on('open', function () {
				var selection = mediaUploader.state().get('selection');
				selection.reset();

				existingImageIds.forEach(function (id) {
					var attachment = wp.media.attachment(id);
					attachment.fetch();
					selection.add(attachment);
				});
			});

			// When images are selected, process them.
			mediaUploader.on('select', function () {
				var attachments = mediaUploader.state().get('selection').toJSON();
				var overlayImages = [];

				// Loop through selected images and prepare the data.
				$.each(attachments, function (index, attachment) {
					overlayImages.push({
						id: attachment.id,
						url: attachment.url,
						title: attachment.title
					});
				});

				// Send the data to the server via AJAX
				$.ajax({
					url: aimg_object.ajax_url,
					type: 'POST',
					data: {
						action: 'aimg_save_overlay_images',
						overlay_images: overlayImages,
						nonce: aimg_object.nonce
					},
					success: function (response) {
						if (response.success) {
							// Loop through overlayImages and append only new ones.
							$.each(overlayImages, function (index, image) {
								if (existingImageIds.indexOf(image.id) === -1) {
									var container = $('<div class="aimg-overlay-images__item" data-id="' + image.id + '"></div>');
									container.append('<img src="' + image.url + '" alt="' + image.title + '" style="width:60px;height:60px;" />');
									container.append('<input type="hidden" name="aimg_overlay_image_ids[]" value="' + image.id + '">');
									container.append('<button type="button" class="remove-overlay button button-secondary">X</button>');

									$('#overlay-image-list').append(container);
								}
							});
						} else {
							alert(response.data.message || 'Failed to save images.');
						}
					},
					error: function () {
						alert('An error occurred while uploading images.');
					}
				});
			});

			mediaUploader.open();
		},
		handleRemoveOverlayImage: function (e) {
			e.preventDefault();

			// Remove the overlay image from the UI.
			var $item = $(this).closest('.aimg-overlay-images__item');
			var imageId = $item.data('id');

			// Send AJAX request to remove the image from the server.
			$.ajax({
				url: aimg_object.ajax_url,
				type: 'POST',
				data: {
					action: 'aimg_remove_overlay_image',
					image_id: imageId,
					nonce: aimg_object.nonce
				},
				success: function (response) {
					if (response.success) {
						$item.remove();
					} else {
						alert(response.data.message || 'Failed to remove image.');
					}
				},
				error: function () {
					alert('An error occurred while removing the image.');
				}
			});
		}
	};

	// Initializing the modules.
	aimg_dmin.init();
});
