/**
 * Layer type registry. Other plugins add types with the
 * `aimg.studio.layerTypes` filter (wp.hooks), from a script enqueued on the
 * `aimg_enqueue_template_studio` action:
 *
 *     wp.hooks.addFilter( 'aimg.studio.layerTypes', 'my-plugin', ( types, api ) => ( {
 *         ...types,
 *         badge: {
 *             label: 'Badge',
 *             boxed: true,                        // Has a box { x, y, w, h }: drag, resize, rotate.
 *             create: ( canvas, settings ) => ( { box: { x: 0, y: 0, w: 200, h: 80 }, color: '#ffffff' } ),
 *             draw: ( layer, ctx ) => { const group = api.helpers.boxedGroup( layer ); … return group; },
 *             Fields: ( { layer, update, data } ) => …, // Inspector panels (optional).
 *         },
 *     } ) );
 *
 * The same type must be registered on the server (`aimg_template_layers`),
 * which sanitizes and draws it. Layers whose type is missing on either side
 * are kept in the document but not drawn.
 */
import Konva, { Filters } from './canvas/konva';
import {
	DRAWERS,
	boxedGroup,
	gradientFill,
	hitArea,
	placeImage,
	placeholder,
	rgba,
	roundedPath,
} from './canvas/draw';
import { TYPE_FIELDS } from './components/LayerFields';
import {
	AdjustmentsField,
	ColorField,
	GradientField,
	MediaField,
	NumberField,
	Range,
	Select,
	Toggle,
} from './components/fields';
import { CORE_LAYERS, centeredBox, newId } from './layers';

const { __, sprintf } = wp.i18n;

const API = {
	Konva,
	Filters,
	helpers: {
		boxedGroup,
		centeredBox,
		gradientFill,
		hitArea,
		placeImage,
		placeholder,
		rgba,
		roundedPath,
	},
	fields: {
		AdjustmentsField,
		ColorField,
		GradientField,
		MediaField,
		NumberField,
		Range,
		Select,
		Toggle,
	},
};

let registry = null;

/**
 * All layer types, core and added, as type => definition. Built on first use,
 * after every script on the page has had the chance to add its filter.
 *
 * @return {Object} Types.
 */
export function layerTypes() {
	if ( registry ) {
		return registry;
	}

	const core = {};
	Object.keys( CORE_LAYERS ).forEach( ( type ) => {
		core[ type ] = {
			...CORE_LAYERS[ type ],
			draw: DRAWERS[ type ],
			Fields: TYPE_FIELDS[ type ],
		};
	} );

	const types =
		window.wp && wp.hooks
			? wp.hooks.applyFilters( 'aimg.studio.layerTypes', core, API )
			: core;

	registry = {};
	Object.keys( types || {} ).forEach( ( type ) => {
		const def = types[ type ];
		if (
			/^[a-z0-9_-]+$/.test( type ) &&
			def &&
			typeof def.label === 'string' &&
			typeof def.create === 'function' &&
			typeof def.draw === 'function'
		) {
			registry[ type ] = { ...def, boxed: !! def.boxed };
		} else {
			window.console.warn(
				'Template Studio: ignoring invalid layer type',
				type
			);
		}
	} );

	return registry;
}

/**
 * A layer type's definition, or null when no active plugin provides it.
 *
 * @param {string} type Layer type.
 * @return {Object|null} Definition.
 */
export const layerType = ( type ) => layerTypes()[ type ] || null;

export const isBoxed = ( type ) => !! layerType( type )?.boxed;

/**
 * The label shown for a layer type.
 *
 * @param {string} type Layer type.
 * @return {string} Label.
 */
export function typeLabel( type ) {
	const def = layerType( type );
	return def
		? def.label
		: sprintf(
				/* translators: %s: layer type */
				__( '%s (unavailable)', 'artificial-image-generator' ),
				type
		  );
}

/**
 * Types that can be added: known here and on the server.
 *
 * @param {string[]} serverTypes Types the server can sanitize and draw.
 * @return {string[]} Types.
 */
export const addableTypes = ( serverTypes ) =>
	serverTypes.filter( ( type ) => layerType( type ) );

/**
 * A new layer of a type, sized for the canvas.
 *
 * @param {string} type     Layer type.
 * @param {Object} canvas   Canvas { width, height }.
 * @param {Object} settings Site defaults { bgColor, textColor }.
 * @return {Object} Layer.
 */
export function createLayer( type, canvas, settings ) {
	return {
		...layerType( type ).create( canvas, settings ),
		id: newId( type ),
		type,
		name: '',
		visible: true,
		rotation: 0,
		showIf: '',
	};
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
		return layer.content.slice( 0, 30 ) || typeLabel( 'text' );
	}
	return typeLabel( layer.type );
}

/**
 * Build the node for a layer.
 *
 * @param {Object} layer Layer.
 * @param {Object} ctx   { canvas, merge, fontFamily, imageFor, sourceLabel, backgroundColor }.
 * @return {Konva.Node|null} Node.
 */
export function drawLayer( layer, ctx ) {
	const def = layerType( layer.type );
	if ( ! def || ! layer.visible ) {
		return null;
	}
	if ( layer.showIf && ctx.merge( '{' + layer.showIf + '}' ).trim() === '' ) {
		return null;
	}
	const node = def.draw( layer, ctx );
	if ( node ) {
		node.setAttr( 'layerId', layer.id );
	}
	return node || null;
}
