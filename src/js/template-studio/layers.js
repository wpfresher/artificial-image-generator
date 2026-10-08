/**
 * The core layer types: labels and starting values. The server sanitizes
 * everything again, so these only need to be sensible.
 */
const { __ } = wp.i18n;

let counter = 0;

export const newId = ( type ) =>
	`${ type }-${ Date.now().toString( 36 ) }${ ( counter++ ).toString( 36 ) }`;

/**
 * A box of a size, centered on the canvas.
 *
 * @param {Object} canvas Canvas { width, height }.
 * @param {number} w      Width.
 * @param {number} h      Height.
 * @return {Object} Box { x, y, w, h }.
 */
export const centeredBox = ( canvas, w, h ) => ( {
	x: Math.round( ( canvas.width - w ) / 2 ),
	y: Math.round( ( canvas.height - h ) / 2 ),
	w,
	h,
} );

export const CORE_LAYERS = {
	background: {
		label: __( 'Background', 'artificial-image-generator' ),
		create: ( canvas, settings ) => ( {
			fill: { kind: 'solid', color: settings.bgColor || '#1e3a5f' },
		} ),
	},
	image: {
		label: __( 'Image', 'artificial-image-generator' ),
		boxed: true,
		create: ( canvas ) => ( {
			source: 'media',
			attachments: [],
			pick: 'random',
			box: centeredBox(
				canvas,
				Math.round( canvas.width / 3 ),
				Math.round( canvas.height / 3 )
			),
			fit: 'contain',
			anchor: 'center-center',
			focal: { x: 0.5, y: 0.5 },
			opacity: 1,
			mask: 'none',
			radius: 0,
			adjust: null,
		} ),
	},
	overlay: {
		label: __( 'Overlay', 'artificial-image-generator' ),
		create: () => ( { kind: 'solid', color: '#000000', opacity: 0.4 } ),
	},
	text: {
		label: __( 'Text', 'artificial-image-generator' ),
		boxed: true,
		create: ( canvas, settings ) => ( {
			content: '{title}',
			font: 'roboto-bold',
			size: { max: 64, min: 24 },
			fit: 'box',
			box: {
				x: 80,
				y: 80,
				w: Math.max( 40, canvas.width - 160 ),
				h: Math.max( 40, canvas.height - 160 ),
			},
			align: 'center',
			valign: 'middle',
			color: settings.textColor || '#ffffff',
			opacity: 1,
			lineHeight: 1.4,
			maxLines: 0,
			transform: 'none',
			letterSpacing: 0,
			stroke: { width: 0, color: '#000000', opacity: 1 },
			shadow: { x: 0, y: 0, blur: 0, color: '#000000', opacity: 0 },
			highlight: {
				mode: 'none',
				color: '#000000',
				opacity: 1,
				padding: 12,
				radius: 0,
			},
		} ),
	},
	shape: {
		label: __( 'Shape', 'artificial-image-generator' ),
		boxed: true,
		create: ( canvas ) => ( {
			shape: 'rect',
			box: centeredBox( canvas, 200, 120 ),
			fill: { kind: 'solid', color: '#ffffff', opacity: 1 },
			radius: 12,
			thickness: 4,
			border: { width: 0, color: '#000000', opacity: 1 },
		} ),
	},
	pattern: {
		label: __( 'Pattern', 'artificial-image-generator' ),
		create: () => ( {
			pattern: 'dots',
			color: '#ffffff',
			opacity: 0.15,
			spacing: 24,
			size: 2,
		} ),
	},
	frame: {
		label: __( 'Frame', 'artificial-image-generator' ),
		create: () => ( {
			width: 16,
			color: '#ffffff',
			opacity: 1,
			inset: 0,
			radius: 0,
		} ),
	},
};
