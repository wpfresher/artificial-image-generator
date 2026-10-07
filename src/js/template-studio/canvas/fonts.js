/**
 * Loading the template fonts into the browser, so the canvas draws with the
 * same files the server uses.
 */
const loading = new Map();

/**
 * CSS font family name for a font ID.
 *
 * @param {string} id Font ID.
 * @return {string} Family name.
 */
export const fontFamily = ( id ) => `aimg-${ id }`;

/**
 * Load a font once.
 *
 * @param {Object} font Font { id, url }.
 * @return {Promise<boolean>} Whether it loaded.
 */
export function loadFont( font ) {
	if ( ! font || ! font.url ) {
		return Promise.resolve( false );
	}
	if ( ! loading.has( font.id ) ) {
		loading.set(
			font.id,
			new window.FontFace( fontFamily( font.id ), `url(${ font.url })` )
				.load()
				.then( ( loaded ) => {
					document.fonts.add( loaded );
					return true;
				} )
				.catch( () => false )
		);
	}
	return loading.get( font.id );
}

/**
 * Start loading a font and call back when it is ready (only the first time).
 *
 * @param {Object}   font     Font { id, url }.
 * @param {Function} onLoaded Called once the font can be drawn.
 */
export function ensureFont( font, onLoaded ) {
	if ( font && ! loading.has( font.id ) ) {
		loadFont( font ).then( ( ok ) => ok && onLoaded() );
	}
}
