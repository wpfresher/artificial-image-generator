/**
 * Keyboard shortcuts, active while focus isn't in a form field:
 * Ctrl/⌘+Z undo, Ctrl/⌘+Shift+Z or Ctrl+Y redo, Ctrl/⌘+D duplicate,
 * Delete/Backspace remove, Ctrl/⌘+] and Ctrl/⌘+[ move up/down,
 * Escape deselect, Ctrl/⌘+S save (also inside fields).
 */
import { STORE } from './store';

const { useEffect, useRef } = wp.element;
const { useDispatch, select } = wp.data;

const inField = ( target ) =>
	!! target.closest(
		'input, textarea, select, [contenteditable="true"], .media-modal'
	);

export default function useShortcuts( onSave ) {
	const {
		undo,
		redo,
		duplicateLayer,
		removeLayer,
		moveLayer,
		select: pick,
	} = useDispatch( STORE );
	const save = useRef( onSave );
	save.current = onSave;

	useEffect( () => {
		const handle = ( event ) => {
			const mod = event.ctrlKey || event.metaKey;
			const key = event.key.toLowerCase();

			if ( mod && key === 's' ) {
				event.preventDefault();
				save.current();
				return;
			}

			if ( inField( event.target ) ) {
				return;
			}

			const selectedId = select( STORE ).getState().selectedId;
			let handled = true;

			if ( mod && key === 'z' && event.shiftKey ) {
				redo();
			} else if ( mod && key === 'z' ) {
				undo();
			} else if ( mod && key === 'y' ) {
				redo();
			} else if ( mod && key === 'd' && selectedId ) {
				duplicateLayer( selectedId );
			} else if ( mod && event.key === ']' && selectedId ) {
				moveLayer( selectedId, 1 );
			} else if ( mod && event.key === '[' && selectedId ) {
				moveLayer( selectedId, -1 );
			} else if (
				( event.key === 'Delete' || event.key === 'Backspace' ) &&
				selectedId
			) {
				removeLayer( selectedId );
			} else if ( event.key === 'Escape' && selectedId ) {
				pick( null );
			} else {
				handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		};

		window.addEventListener( 'keydown', handle );
		return () => window.removeEventListener( 'keydown', handle );
	}, [ undo, redo, duplicateLayer, removeLayer, moveLayer, pick ] );
}
