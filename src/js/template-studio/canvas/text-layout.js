/**
 * Text wrapping and fitting, mirroring includes/Rendering/TextLayout.php so the
 * canvas breaks lines where the server does.
 *
 * Sizes are GD font sizes (the template font size unit). GD draws them at 96 DPI,
 * so a size is PX_PER_SIZE CSS pixels on the canvas.
 */

export const PX_PER_SIZE = 96 / 72;
export const MIN_WRAP_SIZE = 12;

const measureCanvas = document.createElement( 'canvas' ).getContext( '2d' );

export function fontString( family, size ) {
	return `${ size * PX_PER_SIZE }px "${ family }"`;
}

/**
 * Ink width of a text, like imagettfbbox()'s width, plus letter spacing.
 *
 * @param {string} text    Text.
 * @param {number} size    GD size.
 * @param {string} family  Font family loaded with FontFace.
 * @param {number} spacing Extra pixels between letters.
 * @return {number} Width in pixels.
 */
export function measure( text, size, family, spacing = 0 ) {
	measureCanvas.font = fontString( family, size );
	const metrics = measureCanvas.measureText( text );
	const ink =
		metrics.actualBoundingBoxLeft !== undefined
			? metrics.actualBoundingBoxLeft + metrics.actualBoundingBoxRight
			: metrics.width;

	return (
		Math.round( ink ) +
		( spacing ? spacing * Math.max( 0, [ ...text ].length - 1 ) : 0 )
	);
}

function breakLines( words, size, family, maxWidth, spacing ) {
	const pieces = [];
	words.forEach( ( word ) => {
		if ( measure( word, size, family, spacing ) <= maxWidth ) {
			pieces.push( word );
			return;
		}
		let chunk = '';
		[ ...word ].forEach( ( char ) => {
			if (
				chunk !== '' &&
				measure( chunk + char, size, family, spacing ) > maxWidth
			) {
				pieces.push( chunk );
				chunk = '';
			}
			chunk += char;
		} );
		pieces.push( chunk );
	} );

	const lines = [];
	let line = '';
	pieces.forEach( ( piece ) => {
		const next = line !== '' ? line + ' ' + piece : piece;
		if (
			line !== '' &&
			measure( next, size, family, spacing ) > maxWidth
		) {
			lines.push( line );
			line = piece;
		} else {
			line = next;
		}
	} );
	if ( line !== '' ) {
		lines.push( line );
	}
	return lines;
}

const splitWords = ( text ) => text.trim().split( /\s+/u );

/**
 * The 1.x title wrapping: shrink only when one word is too wide.
 *
 * @param {string} text     Text.
 * @param {number} size     Requested size.
 * @param {string} family   Font family.
 * @param {number} maxWidth Line width.
 * @param {number} minSize  Smallest size.
 * @param {number} spacing  Letter spacing.
 * @return {{fontSize: number, lines: string[]}} Layout.
 */
export function wrap( text, size, family, maxWidth, minSize, spacing = 0 ) {
	const words = splitWords( text );
	const min = Math.min( size, minSize ?? MIN_WRAP_SIZE );
	let widest = 0;
	words.forEach( ( word ) => {
		widest = Math.max( widest, measure( word, size, family, spacing ) );
	} );
	if ( widest > maxWidth ) {
		size = Math.max( min, Math.floor( ( size * maxWidth ) / widest ) );
	}
	return {
		fontSize: size,
		lines: breakLines( words, size, family, maxWidth, spacing ),
	};
}

/**
 * Shrink into a box (height and max lines), cutting with an ellipsis at the minimum.
 *
 * @param {string} text   Text.
 * @param {string} family Font family.
 * @param {Object} args   { maxSize, minSize, width, height, lineHeight, maxLines, spacing }.
 * @return {{fontSize: number, lines: string[]}} Layout.
 */
export function fit( text, family, args ) {
	const words = splitWords( text );
	const spacing = args.spacing || 0;
	const min = Math.min( args.maxSize, args.minSize );
	const attempt = ( size ) => {
		const lines = breakLines( words, size, family, args.width, spacing );
		const fits =
			lines.length * size * args.lineHeight <= args.height &&
			( ! args.maxLines || lines.length <= args.maxLines );
		return [ fits, lines ];
	};

	let size = args.maxSize;
	let [ fits, lines ] = attempt( size );

	if ( ! fits ) {
		let low = Math.ceil( min );
		let high = Math.ceil( size ) - 1;
		size = min;
		lines = attempt( min )[ 1 ];
		while ( low <= high ) {
			const mid = Math.floor( ( low + high ) / 2 );
			const [ ok, midLines ] = attempt( mid );
			if ( ok ) {
				size = mid;
				lines = midLines;
				low = mid + 1;
			} else {
				high = mid - 1;
			}
		}
	}

	let maxLines = args.maxLines || 0;
	const byHeight = Math.max(
		1,
		Math.floor( args.height / ( size * args.lineHeight ) )
	);
	maxLines = maxLines ? Math.min( maxLines, byHeight ) : byHeight;

	if ( lines.length > maxLines ) {
		lines = lines.slice( 0, maxLines );
		let last = lines.pop();
		while (
			last !== '' &&
			measure( last + '…', size, family, spacing ) > args.width
		) {
			last = [ ...last ].slice( 0, -1 ).join( '' ).trimEnd();
		}
		lines.push( last + '…' );
	}

	return { fontSize: size, lines };
}
