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

export const previewDocument = ( document, title, postId ) =>
	apiFetch( {
		path: '/aimg/v1/templates/preview',
		method: 'POST',
		data: { document, title, post_id: postId || 0 },
	} );

export const searchPosts = ( search ) =>
	apiFetch( {
		path: `/wp/v2/search?type=post&per_page=8&search=${ encodeURIComponent(
			search
		) }`,
	} );

export const postTags = ( postId ) =>
	apiFetch( { path: `/aimg/v1/merge-tags/${ postId }` } );

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
