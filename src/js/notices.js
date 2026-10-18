/**
 * Image Generator — admin notice dismissal.
 */

const settings = window.aimgNotices || {};

/**
 * Send a notice action for a notice.
 *
 * @param {string} action   The AJAX action.
 * @param {string} noticeId The notice ID.
 * @return {Promise<Object>} The JSON response.
 */
const sendNoticeAction = ( action, noticeId ) =>
	window
		.fetch( settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new window.URLSearchParams( {
				action,
				nonce: settings.nonce,
				notice_id: noticeId,
			} ),
		} )
		.then( ( response ) => response.json() );

/**
 * Run a notice button's action and remove the notice on success.
 *
 * @param {HTMLElement} button The clicked button.
 * @param {string}      action The AJAX action.
 */
const handleButton = ( button, action ) => {
	const notice = button.closest( '.aimg-admin-notice' );
	const buttons = notice.querySelectorAll( 'button' );
	const fallback =
		settings.error || 'Something went wrong. Please try again.';

	buttons.forEach( ( item ) => ( item.disabled = true ) );

	sendNoticeAction( action, button.dataset.noticeId )
		.then( ( response ) => {
			if ( ! response?.success ) {
				throw new Error( response?.data?.message || fallback );
			}

			notice.remove();
		} )
		.catch( ( error ) => {
			let message = notice.querySelector( '.aimg-notice-error' );

			if ( ! message ) {
				message = document.createElement( 'p' );
				message.className = 'aimg-notice-error';
				notice
					.querySelector( '.aimg-notice-content' )
					.append( message );
			}

			message.textContent =
				error instanceof SyntaxError ? fallback : error.message;
			buttons.forEach( ( item ) => ( item.disabled = false ) );
		} );
};

document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( 'button' );
	const notice = button?.closest( '.aimg-admin-notice' );

	if ( ! notice ) {
		return;
	}

	if ( button.classList.contains( 'aimg-notice-dismiss' ) ) {
		event.preventDefault();
		handleButton( button, 'aimg_dismiss_notice' );
	} else if ( button.classList.contains( 'aimg-notice-remind-later' ) ) {
		event.preventDefault();
		handleButton( button, 'aimg_remind_later_notice' );
	} else if ( button.classList.contains( 'notice-dismiss' ) ) {
		// The core "X" button hides the notice itself, so just snooze it.
		sendNoticeAction(
			'aimg_remind_later_notice',
			notice.dataset.noticeId
		).catch( () => {} );
	}
} );
