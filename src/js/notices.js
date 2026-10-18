/**
 * Image Generator — admin notice dismissal.
 */

( function ( $ ) {
	'use strict';

	var settings = window.aimgNotices || {};

	/**
	 * Send a notice action and remove the notice on success.
	 *
	 * @param {jQuery} $button The clicked button.
	 * @param {string} action  The AJAX action.
	 */
	function sendNoticeAction( $button, action ) {
		var $notice = $button.closest( '.aimg-admin-notice' );
		var errorMessage =
			settings.error || 'Something went wrong. Please try again.';

		$notice.find( 'button' ).prop( 'disabled', true );

		$.post( settings.ajaxUrl, {
			action: action,
			nonce: settings.nonce,
			notice_id: $button.data( 'notice-id' ),
		} )
			.done( function ( response ) {
				if ( response && response.success ) {
					$notice.fadeOut( 300, function () {
						$( this ).remove();
					} );
					return;
				}

				window.alert(
					( response && response.data && response.data.message ) ||
						errorMessage
				);
				$notice.find( 'button' ).prop( 'disabled', false );
			} )
			.fail( function ( xhr ) {
				var response = xhr && xhr.responseJSON;

				window.alert(
					( response && response.data && response.data.message ) ||
						errorMessage
				);
				$notice.find( 'button' ).prop( 'disabled', false );
			} );
	}

	$( function () {
		// Dismiss a notice permanently.
		$( document ).on( 'click', '.aimg-notice-dismiss', function ( event ) {
			event.preventDefault();
			sendNoticeAction( $( this ), 'aimg_dismiss_notice' );
		} );

		// Hide a notice for a few days.
		$( document ).on(
			'click',
			'.aimg-notice-remind-later',
			function ( event ) {
				event.preventDefault();
				sendNoticeAction( $( this ), 'aimg_remind_later_notice' );
			}
		);

		// The core "X" button hides the notice itself, so just snooze it.
		$( document ).on(
			'click',
			'.aimg-admin-notice .notice-dismiss',
			function () {
				$.post( settings.ajaxUrl, {
					action: 'aimg_remind_later_notice',
					nonce: settings.nonce,
					notice_id: $( this )
						.closest( '.aimg-admin-notice' )
						.data( 'notice-id' ),
				} );
			}
		);
	} );
} )( window.jQuery );
