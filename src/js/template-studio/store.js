/**
 * The Template Studio store: the document being edited, the selection and the save state.
 */
const { createReduxStore, register } = wp.data;

export const STORE = 'aimg/template-studio';

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
};

const mapLayers = ( state, fn ) => ( {
	...state,
	dirty: true,
	document: { ...state.document, layers: fn( state.document.layers ) },
} );

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'LOAD':
			return {
				...state,
				...action.template,
				selectedId: null,
				dirty: false,
			};
		case 'SET_TITLE':
			return { ...state, title: action.title, dirty: true };
		case 'SET_STATUS':
			return { ...state, status: action.status, dirty: true };
		case 'UPDATE_CANVAS':
			return {
				...state,
				dirty: true,
				document: {
					...state.document,
					canvas: { ...state.document.canvas, ...action.changes },
				},
			};
		case 'UPDATE_OUTPUT':
			return {
				...state,
				dirty: true,
				document: {
					...state.document,
					output: { ...state.document.output, ...action.changes },
				},
			};
		case 'UPDATE_LAYER':
			return mapLayers( state, ( layers ) =>
				layers.map( ( layer ) =>
					layer.id === action.id
						? { ...layer, ...action.changes }
						: layer
				)
			);
		case 'ADD_LAYER':
			return {
				...mapLayers( state, ( layers ) => [
					...layers,
					action.layer,
				] ),
				selectedId: action.layer.id,
			};
		case 'REMOVE_LAYER':
			return {
				...mapLayers( state, ( layers ) =>
					layers.filter( ( layer ) => layer.id !== action.id )
				),
				selectedId:
					state.selectedId === action.id ? null : state.selectedId,
			};
		case 'MOVE_LAYER':
			return mapLayers( state, ( layers ) => {
				const from = layers.findIndex( ( l ) => l.id === action.id );
				const to = from + action.offset;
				if ( from < 0 || to < 0 || to >= layers.length ) {
					return layers;
				}
				const next = [ ...layers ];
				const [ moved ] = next.splice( from, 1 );
				next.splice( to, 0, moved );
				return next;
			} );
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'SET_SAVING':
			return { ...state, saving: action.saving };
		case 'SET_NOTICE':
			return { ...state, notice: action.notice };
		case 'SET_PREVIEW':
			return {
				...state,
				preview: { ...state.preview, ...action.preview },
			};
	}
	return state;
}

const actions = {
	load: ( template ) => ( { type: 'LOAD', template } ),
	setTitle: ( title ) => ( { type: 'SET_TITLE', title } ),
	setStatus: ( status ) => ( { type: 'SET_STATUS', status } ),
	updateCanvas: ( changes ) => ( { type: 'UPDATE_CANVAS', changes } ),
	updateOutput: ( changes ) => ( { type: 'UPDATE_OUTPUT', changes } ),
	updateLayer: ( id, changes ) => ( { type: 'UPDATE_LAYER', id, changes } ),
	addLayer: ( layer ) => ( { type: 'ADD_LAYER', layer } ),
	removeLayer: ( id ) => ( { type: 'REMOVE_LAYER', id } ),
	moveLayer: ( id, offset ) => ( { type: 'MOVE_LAYER', id, offset } ),
	select: ( id ) => ( { type: 'SELECT', id } ),
	setSaving: ( saving ) => ( { type: 'SET_SAVING', saving } ),
	setNotice: ( notice ) => ( { type: 'SET_NOTICE', notice } ),
	setPreview: ( preview ) => ( { type: 'SET_PREVIEW', preview } ),
};

const selectors = {
	getState: ( state ) => state,
	getDocument: ( state ) => state.document,
	getSelectedLayer: ( state ) =>
		state.document
			? state.document.layers.find(
					( l ) => l.id === state.selectedId
			  ) || null
			: null,
};

register( createReduxStore( STORE, { reducer, actions, selectors } ) );
