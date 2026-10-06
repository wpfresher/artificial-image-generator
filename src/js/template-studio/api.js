/**
 * Template REST calls.
 */
const apiFetch = wp.apiFetch;

export const saveTemplate = ( id, data ) =>
	apiFetch( {
		path: id ? `/aimg/v1/templates/${ id }` : '/aimg/v1/templates',
		method: id ? 'PUT' : 'POST',
		data,
	} );

export const previewDocument = ( document, title ) =>
	apiFetch( {
		path: '/aimg/v1/templates/preview',
		method: 'POST',
		data: { document, title },
	} );

const mediaCache = new Map();

/**
 * URL of an attachment, sized for the canvas.
 *
 * @param {number} id Attachment ID.
 * @return {Promise<string>} URL, or '' when it can't be found.
 */
export function attachmentUrl( id ) {
	if ( ! mediaCache.has( id ) ) {
		mediaCache.set(
			id,
			apiFetch( { path: `/wp/v2/media/${ id }?context=view` } )
				.then( ( media ) => {
					const sizes = ( media.media_details || {} ).sizes || {};
					return (
						( sizes.large || sizes.full || {} ).source_url ||
						media.source_url ||
						''
					);
				} )
				.catch( () => '' )
		);
	}
	return mediaCache.get( id );
}
