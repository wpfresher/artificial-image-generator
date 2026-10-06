/**
 * The Template Studio store: the document being edited, the selection, undo
 * history and the save state.
 */
import { newId } from './layers';

const { createReduxStore, register } = wp.data;

export const STORE = 'aimg/template-studio';

const HISTORY_LIMIT = 100;

// Changes to the same thing this close together are one undo step (typing, sliders).
const MERGE_WINDOW = 1000;

const DEFAULT_STATE = {
	templateId: 0,
	title: '',
	status: 'publish',
	hasDocument: false,
	document: null,
	selectedId: null,
	dirty: false,
	saving: false,
	notice: null,
	preview: { image: '', loading: false, error: '' },
	sample: { key: 'sample', tags: null, postId: 0, images: {} },
	history: { past: [], future: [], lastKey: '', lastTime: 0 },
};

const withLayers = ( document, layers ) => ( { ...document, layers } );

/**
 * Apply a document change.
 *
 * @param {Object} state  State.
 * @param {Object} action Action.
 * @return {Object|null} { document, selectedId }, or null when the action isn't a document change.
 */
function change( state, action ) {
	const doc = state.document;
	const layers = doc ? doc.layers : [];

	switch ( action.type ) {
		case 'SET_DOCUMENT':
			return { document: action.document, selectedId: null };
		case 'UPDATE_CANVAS':
			return {
				document: {
					...doc,
					canvas: { ...doc.canvas, ...action.changes },
				},
				selectedId: state.selectedId,
			};
		case 'UPDATE_OUTPUT':
			return {
				document: {
					...doc,
					output: { ...doc.output, ...action.changes },
				},
				selectedId: state.selectedId,
			};
		case 'UPDATE_LAYER':
			return {
				document: withLayers(
					doc,
					layers.map( ( layer ) =>
						layer.id === action.id
							? { ...layer, ...action.changes }
							: layer
					)
				),
				selectedId: state.selectedId,
			};
		case 'ADD_LAYER':
			return {
				document: withLayers( doc, [ ...layers, action.layer ] ),
				selectedId: action.layer.id,
			};
		case 'DUPLICATE_LAYER': {
			const index = layers.findIndex( ( l ) => l.id === action.id );
			if ( index < 0 ) {
				return null;
			}
			const copy = JSON.parse( JSON.stringify( layers[ index ] ) );
			copy.id = newId( copy.type );
			if ( copy.box ) {
				copy.box = {
					...copy.box,
					x: copy.box.x + 20,
					y: copy.box.y + 20,
				};
			}
			const next = [ ...layers ];
			next.splice( index + 1, 0, copy );
			return { document: withLayers( doc, next ), selectedId: copy.id };
		}
		case 'REMOVE_LAYER':
			return {
				document: withLayers(
					doc,
					layers.filter( ( layer ) => layer.id !== action.id )
				),
				selectedId:
					state.selectedId === action.id ? null : state.selectedId,
			};
		case 'MOVE_LAYER': {
			const from = layers.findIndex( ( l ) => l.id === action.id );
			const to = from + action.offset;
			if ( from < 0 || to < 0 || to >= layers.length ) {
				return null;
			}
			const next = [ ...layers ];
			const [ moved ] = next.splice( from, 1 );
			next.splice( to, 0, moved );
			return {
				document: withLayers( doc, next ),
				selectedId: state.selectedId,
			};
		}
	}
	return undefined;
}

const exists = ( document, id ) =>
	!! id && !! document && document.layers.some( ( l ) => l.id === id );

function reducer( state = DEFAULT_STATE, action ) {
	const changed = change( state, action );

	if ( changed === null ) {
		return state;
	}

	if ( changed ) {
		const { history } = state;
		const key = action.mergeKey || '';
		const merge =
			key !== '' &&
			key === history.lastKey &&
			action.time - history.lastTime < MERGE_WINDOW;

		return {
			...state,
			...changed,
			dirty: true,
			history: {
				past: merge
					? history.past
					: [
							...history.past,
							{
								document: state.document,
								selectedId: state.selectedId,
							},
					  ].slice( -HISTORY_LIMIT ),
				future: [],
				lastKey: key,
				lastTime: action.time || 0,
			},
		};
	}

	switch ( action.type ) {
		case 'LOAD':
			return {
				...state,
				...action.template,
				selectedId: null,
				dirty: false,
				history: DEFAULT_STATE.history,
			};
		case 'UNDO':
		case 'REDO': {
			const { past, future } = state.history;
			const from = action.type === 'UNDO' ? past : future;
			if ( ! from.length ) {
				return state;
			}
			const entry = from[ from.length - 1 ];
			const current = {
				document: state.document,
				selectedId: state.selectedId,
			};
			return {
				...state,
				document: entry.document,
				selectedId: exists( entry.document, entry.selectedId )
					? entry.selectedId
					: null,
				dirty: true,
				history: {
					past:
						action.type === 'UNDO'
							? past.slice( 0, -1 )
							: [ ...past, current ],
					future:
						action.type === 'UNDO'
							? [ ...future, current ]
							: future.slice( 0, -1 ),
					lastKey: '',
					lastTime: 0,
				},
			};
		}
		case 'SET_TITLE':
			return { ...state, title: action.title, dirty: true };
		case 'SET_STATUS':
			return { ...state, status: action.status, dirty: true };
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'SET_SAVING':
			return { ...state, saving: action.saving };
		case 'SET_NOTICE':
			return { ...state, notice: action.notice };
		case 'SET_SAMPLE':
			return {
				...state,
				sample: { ...DEFAULT_STATE.sample, ...action.sample },
			};
		case 'SET_PREVIEW':
			return {
				...state,
				preview: { ...state.preview, ...action.preview },
			};
	}
	return state;
}

const now = () => Date.now();

const actions = {
	load: ( template ) => ( { type: 'LOAD', template } ),
	setTitle: ( title ) => ( { type: 'SET_TITLE', title } ),
	setStatus: ( status ) => ( { type: 'SET_STATUS', status } ),
	setDocument: ( document ) => ( {
		type: 'SET_DOCUMENT',
		document,
		time: now(),
	} ),
	updateCanvas: ( changes ) => ( {
		type: 'UPDATE_CANVAS',
		changes,
		time: now(),
		mergeKey: 'canvas:' + Object.keys( changes ).join( ',' ),
	} ),
	updateOutput: ( changes ) => ( {
		type: 'UPDATE_OUTPUT',
		changes,
		time: now(),
		mergeKey: 'output:' + Object.keys( changes ).join( ',' ),
	} ),
	updateLayer: ( id, changes ) => ( {
		type: 'UPDATE_LAYER',
		id,
		changes,
		time: now(),
		mergeKey: `layer:${ id }:` + Object.keys( changes ).join( ',' ),
	} ),
	addLayer: ( layer ) => ( { type: 'ADD_LAYER', layer, time: now() } ),
	duplicateLayer: ( id ) => ( { type: 'DUPLICATE_LAYER', id, time: now() } ),
	removeLayer: ( id ) => ( { type: 'REMOVE_LAYER', id, time: now() } ),
	moveLayer: ( id, offset ) => ( {
		type: 'MOVE_LAYER',
		id,
		offset,
		time: now(),
	} ),
	undo: () => ( { type: 'UNDO' } ),
	redo: () => ( { type: 'REDO' } ),
	select: ( id ) => ( { type: 'SELECT', id } ),
	setSaving: ( saving ) => ( { type: 'SET_SAVING', saving } ),
	setNotice: ( notice ) => ( { type: 'SET_NOTICE', notice } ),
	setPreview: ( preview ) => ( { type: 'SET_PREVIEW', preview } ),
	setSample: ( sample ) => ( { type: 'SET_SAMPLE', sample } ),
};

const selectors = {
	getState: ( state ) => state,
	getDocument: ( state ) => state.document,
	canUndo: ( state ) => state.history.past.length > 0,
	canRedo: ( state ) => state.history.future.length > 0,
	getSelectedLayer: ( state ) =>
		state.document
			? state.document.layers.find(
					( l ) => l.id === state.selectedId
			  ) || null
			: null,
};

register( createReduxStore( STORE, { reducer, actions, selectors } ) );
