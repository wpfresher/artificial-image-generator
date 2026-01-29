/**
 * Image Generator Admin JS
 * https://beautifulplugins.com/
 *
 * Copyright (c) 2026 BeautifulPlugins
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

				// Update the hidden input as araay of images ids only.
				var currentIds = $('#overlay_images').val();
				var overlayImageIds = currentIds ? JSON.parse(currentIds) : [];
				$.each(overlayImages, function (index, image) {
					if (overlayImageIds.indexOf(image.id) === -1) {
						overlayImageIds.push(image.id);
					}
				});

				// Save the updated IDs back to the hidden input.
				$('#overlay_images').val(JSON.stringify(overlayImageIds));
			});

			mediaUploader.open();
		},
		handleRemoveOverlayImage: function (e) {
			e.preventDefault();

			// Remove the overlay image from the UI.
			var $item = $(this).closest('.aimg-overlay-images__item');
			var imageId = $item.data('id');

			// Update the hidden input by removing the image ID.
			var currentIds = $('#overlay_images').val();
			var overlayImageIds = currentIds ? JSON.parse(currentIds) : [];
			var index = overlayImageIds.indexOf(imageId);
			if (index !== -1) {
				overlayImageIds.splice(index, 1);
			}

			// Save the updated IDs back to the hidden input.
			$('#overlay_images').val(JSON.stringify(overlayImageIds));

			// Remove the item from the UI immediately.
			$item.remove();
		}
	};

	// Initializing the modules.
	aimg_dmin.init();
});
