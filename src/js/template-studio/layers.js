/**
 * Layer types the Studio can add: labels and starting values. The server
 * sanitizes everything again, so these only need to be sensible.
 */
const { __ } = wp.i18n;

export const LABELS = {
	background: __( 'Background', 'artificial-image-generator' ),
	image: __( 'Image', 'artificial-image-generator' ),
	overlay: __( 'Overlay', 'artificial-image-generator' ),
	text: __( 'Text', 'artificial-image-generator' ),
	shape: __( 'Shape', 'artificial-image-generator' ),
	pattern: __( 'Pattern', 'artificial-image-generator' ),
	frame: __( 'Frame', 'artificial-image-generator' ),
};

let counter = 0;

export const newId = ( type ) =>
	`${ type }-${ Date.now().toString( 36 ) }${ ( counter++ ).toString( 36 ) }`;

/**
 * A new layer of a type, sized for the canvas.
 *
 * @param {string} type     Layer type.
 * @param {Object} canvas   Canvas { width, height }.
 * @param {Object} settings Site defaults { bgColor, textColor }.
 * @return {Object} Layer.
 */
export function createLayer( type, canvas, settings ) {
	const { width, height } = canvas;
	const centered = ( w, h ) => ( {
		x: Math.round( ( width - w ) / 2 ),
		y: Math.round( ( height - h ) / 2 ),
		w,
		h,
	} );
	const base = {
		id: newId( type ),
		type,
		name: '',
		visible: true,
		rotation: 0,
		showIf: '',
	};

	switch ( type ) {
		case 'background':
			return {
				...base,
				fill: { kind: 'solid', color: settings.bgColor || '#1e3a5f' },
			};
		case 'image':
			return {
				...base,
				source: 'media',
				attachments: [],
				pick: 'random',
				box: centered(
					Math.round( width / 3 ),
					Math.round( height / 3 )
				),
				fit: 'contain',
				anchor: 'center-center',
				focal: { x: 0.5, y: 0.5 },
				opacity: 1,
				mask: 'none',
				radius: 0,
				adjust: null,
			};
		case 'overlay':
			return { ...base, kind: 'solid', color: '#000000', opacity: 0.4 };
		case 'shape':
			return {
				...base,
				shape: 'rect',
				box: centered( 200, 120 ),
				fill: { kind: 'solid', color: '#ffffff', opacity: 1 },
				radius: 12,
				thickness: 4,
				border: { width: 0, color: '#000000', opacity: 1 },
			};
		case 'pattern':
			return {
				...base,
				pattern: 'dots',
				color: '#ffffff',
				opacity: 0.15,
				spacing: 24,
				size: 2,
			};
		case 'frame':
			return {
				...base,
				width: 16,
				color: '#ffffff',
				opacity: 1,
				inset: 0,
				radius: 0,
			};
		default:
			return {
				...base,
				type: 'text',
				content: '{title}',
				font: 'roboto-bold',
				size: { max: 64, min: 24 },
				fit: 'box',
				box: {
					x: 80,
					y: 80,
					w: Math.max( 40, width - 160 ),
					h: Math.max( 40, height - 160 ),
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
			};
	}
}

/**
 * A short description of a layer for the layers panel.
 *
 * @param {Object} layer Layer.
 * @return {string} Name.
 */
export function layerName( layer ) {
	if ( layer.name ) {
		return layer.name;
	}
	if ( layer.type === 'text' ) {
		return layer.content.slice( 0, 30 ) || LABELS.text;
	}
	return LABELS[ layer.type ] || layer.type;
}
