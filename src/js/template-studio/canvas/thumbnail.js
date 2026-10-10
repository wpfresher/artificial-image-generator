/**
 * Small images of documents, e.g. for the starter gallery.
 */
import Konva from './konva';
import { drawLayer } from '../registry';
import { fontFamily, loadFont } from './fonts';

/**
 * Render a document to a data URL.
 *
 * @param {Object} doc    Document.
 * @param {Object} tags   Merge tag values.
 * @param {Array}  fonts  Fonts { id, url }.
 * @param {number} width  Image width.
 * @param {Object} images Image URLs by source, e.g. { stock: url }.
 * @return {Promise<string>} PNG data URL.
 */
export async function renderThumbnail(
	doc,
	tags,
	fonts,
	width = 360,
	images = {}
) {
	const used = new Set(
		doc.layers.map( ( layer ) => layer.font ).filter( Boolean )
	);
	await Promise.all(
		fonts.filter( ( font ) => used.has( font.id ) ).map( loadFont )
	);

	const sourceOf = ( item ) => item.source || item.fill?.source || '';
	const loaded = {};
	await Promise.all(
		doc.layers
			.map( sourceOf )
			.filter( ( source ) => images[ source ] )
			.map(
				( source ) =>
					new Promise( ( resolve ) => {
						const img = new window.Image();
						img.onload = () => {
							loaded[ source ] = img;
							resolve();
						};
						img.onerror = resolve;
						img.src = images[ source ];
					} )
			)
	);

	const scale = width / doc.canvas.width;
	const container = document.createElement( 'div' );
	const stage = new Konva.Stage( {
		container,
		width,
		height: Math.round( doc.canvas.height * scale ),
		scale: { x: scale, y: scale },
	} );
	const layer = new Konva.Layer( { listening: false } );
	stage.add( layer );
	layer.add(
		new Konva.Rect( {
			width: doc.canvas.width,
			height: doc.canvas.height,
			fill: doc.canvas.background,
		} )
	);

	let background = doc.canvas.background;
	const ctx = {
		canvas: doc.canvas,
		merge: ( text ) =>
			String( text ).replace(
				/\{([a-z_]+)(?::([A-Za-z0-9_\-]+))?\}/g,
				( match, name ) => tags[ name ] ?? ''
			),
		fontFamily,
		backgroundColor: () => background,
		sourceLabel: () => '',
		imageFor: ( item ) => loaded[ item.source ] || null,
	};

	doc.layers.forEach( ( item ) => {
		if ( item.type === 'background' && item.fill ) {
			background =
				item.fill.color ||
				( item.fill.colors || [] )[ 0 ] ||
				( item.fill.stops || [ {} ] )[ 0 ].color ||
				background;
		}
		try {
			const node = drawLayer( item, ctx );
			if ( node ) {
				layer.add( node );
			}
		} catch {
			// A thumbnail without one layer is still useful.
		}
	} );

	layer.draw();
	const url = stage.toDataURL( { pixelRatio: 1 } );
	stage.destroy();
	return url;
}
