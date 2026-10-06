/**
 * The design canvas: draws the document with Konva and turns drag, resize and
 * rotate on the selected layer into store updates.
 */
import Konva from './konva';
import { BOXED, drawLayer } from './draw';
import { attachmentUrl } from '../api';

const { createElement: el, useEffect, useRef, useState } = wp.element;
const { __ } = wp.i18n;

const fontFamily = ( id ) => `aimg-${ id }`;
const loadedFonts = new Map();

function loadFonts( fonts ) {
	return Promise.all(
		( fonts || [] )
			.filter( ( font ) => font.url && ! loadedFonts.has( font.id ) )
			.map( ( font ) => {
				const face = new window.FontFace(
					fontFamily( font.id ),
					`url(${ font.url })`
				);
				loadedFonts.set( font.id, face );
				return face
					.load()
					.then( ( loaded ) => document.fonts.add( loaded ) )
					.catch( () => {} );
			} )
	);
}

export default function StudioCanvas( {
	doc,
	selectedId,
	onSelect,
	onChangeLayer,
	data,
	tags,
	images: sources,
} ) {
	const container = useRef( null );
	const stage = useRef( null );
	const content = useRef( null );
	const ui = useRef( null );
	const transformer = useRef( null );
	const images = useRef( new Map() );
	const [ width, setWidth ] = useState( 0 );
	const [ version, setVersion ] = useState( 0 );
	const refresh = () => setVersion( ( v ) => v + 1 );
	const guides = useRef( [] );

	const clearGuides = () => {
		guides.current.forEach( ( line ) => line.destroy() );
		guides.current = [];
		if ( ui.current ) {
			ui.current.batchDraw();
		}
	};

	// Snap the dragged layer's edges and center to the canvas and other layers.
	const snap = ( node, layer, current, scale, disabled ) => {
		clearGuides();
		if ( disabled ) {
			return;
		}
		const { width: cw, height: ch } = current.canvas;
		const threshold = 6 / scale;
		const targets = { x: [ 0, cw / 2, cw ], y: [ 0, ch / 2, ch ] };
		current.layers.forEach( ( other ) => {
			if ( other.id !== layer.id && other.visible && other.box ) {
				const ob = other.box;
				targets.x.push( ob.x, ob.x + ob.w / 2, ob.x + ob.w );
				targets.y.push( ob.y, ob.y + ob.h / 2, ob.y + ob.h );
			}
		} );

		const { w, h } = layer.box;
		const axis = ( center, half, list ) => {
			let best = null;
			[ -half, 0, half ].forEach( ( offset ) =>
				list.forEach( ( target ) => {
					const delta = target - ( center + offset );
					if (
						Math.abs( delta ) <= threshold &&
						( ! best || Math.abs( delta ) < Math.abs( best.delta ) )
					) {
						best = { delta, target };
					}
				} )
			);
			return best;
		};

		const sx = axis( node.x(), w / 2, targets.x );
		const sy = axis( node.y(), h / 2, targets.y );
		if ( sx ) {
			node.x( node.x() + sx.delta );
		}
		if ( sy ) {
			node.y( node.y() + sy.delta );
		}

		const style = {
			stroke: '#e0457b',
			strokeWidth: 1 / scale,
			dash: [ 4 / scale, 4 / scale ],
			listening: false,
		};
		if ( sx ) {
			guides.current.push(
				new Konva.Line( {
					points: [ sx.target, 0, sx.target, ch ],
					...style,
				} )
			);
		}
		if ( sy ) {
			guides.current.push(
				new Konva.Line( {
					points: [ 0, sy.target, cw, sy.target ],
					...style,
				} )
			);
		}
		guides.current.forEach( ( line ) => ui.current.add( line ) );
		ui.current.batchDraw();
	};

	useEffect( () => {
		stage.current = new Konva.Stage( {
			container: container.current,
			width: 1,
			height: 1,
		} );
		content.current = new Konva.Layer();
		ui.current = new Konva.Layer();
		transformer.current = new Konva.Transformer( {
			rotateEnabled: true,
			keepRatio: false,
			rotationSnaps: [ 0, 90, 180, 270 ],
			borderStroke: '#3858e9',
			anchorStroke: '#3858e9',
			anchorSize: 9,
		} );
		ui.current.add( transformer.current );
		stage.current.add( content.current, ui.current );

		stage.current.on( 'mousedown touchstart', ( event ) => {
			if ( event.target === stage.current ) {
				onSelect( null );
			}
		} );

		const observer = new window.ResizeObserver( ( entries ) =>
			setWidth( Math.floor( entries[ 0 ].contentRect.width ) )
		);
		observer.observe( container.current );

		loadFonts( data.capabilities.fonts ).then( refresh );

		return () => {
			observer.disconnect();
			stage.current.destroy();
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		if ( ! doc || ! width ) {
			return;
		}

		const { canvas } = doc;
		const scale = Math.min( 1, width / canvas.width );
		stage.current.size( {
			width: Math.round( canvas.width * scale ),
			height: Math.round( canvas.height * scale ),
		} );
		stage.current.scale( { x: scale, y: scale } );

		let background = canvas.background;
		const ctx = {
			canvas,
			merge: ( text ) =>
				String( text ).replace(
					/\{([a-z_]+)(?::([A-Za-z0-9_\-]+))?\}/g,
					( match, name, key ) =>
						name === 'custom_field' && key
							? `[${ key }]`
							: tags[ name ] ?? ''
				),
			fontFamily,
			backgroundColor: () => background,
			sourceLabel: ( layer ) =>
				( data.capabilities.imageSources || {} )[
					layer.source || 'media'
				] || __( 'Image', 'artificial-image-generator' ),
			imageFor: ( layer ) => {
				const source = layer.source || 'media';
				let key = '';
				let url = null;
				if ( source === 'media' ) {
					key = ( layer.attachments || [] )[ 0 ];
				} else if ( sources && sources[ source ] ) {
					url = sources[ source ];
					key = url;
				}
				if ( ! key ) {
					return null;
				}
				const cached = images.current.get( key );
				if ( cached ) {
					return cached === 'loading' || cached === 'missing'
						? null
						: cached;
				}
				images.current.set( key, 'loading' );
				Promise.resolve( url || attachmentUrl( key ) ).then(
					( src ) => {
						if ( ! src ) {
							images.current.set( key, 'missing' );
							return;
						}
						const img = new window.Image();
						img.onload = () => {
							images.current.set( key, img );
							refresh();
						};
						img.onerror = () =>
							images.current.set( key, 'missing' );
						img.src = src;
					}
				);
				return null;
			},
		};

		content.current.destroyChildren();
		content.current.add(
			new Konva.Rect( {
				width: canvas.width,
				height: canvas.height,
				fill: canvas.background,
				listening: false,
			} )
		);

		let selectedNode = null;
		doc.layers.forEach( ( layer ) => {
			if ( layer.type === 'background' && layer.fill ) {
				background =
					layer.fill.kind === 'palette'
						? layer.fill.colors[ 0 ]
						: layer.fill.color ||
						  ( layer.fill.stops || [ {} ] )[ 0 ].color ||
						  background;
			}

			let node = null;
			try {
				node = drawLayer( layer, ctx );
			} catch ( error ) {
				// One broken layer must not take the editor down.
				window.console.warn(
					'Template Studio: could not draw layer',
					layer.id,
					error
				);
			}
			if ( ! node ) {
				return;
			}

			const boxed = BOXED.includes( layer.type );
			node.on( 'mousedown touchstart', ( event ) => {
				event.cancelBubble = true;
				onSelect( layer.id );
			} );

			if ( boxed && layer.id === selectedId ) {
				selectedNode = node;
				node.draggable( true );
				node.on( 'dragmove', ( event ) =>
					snap( node, layer, doc, scale, event.evt.altKey )
				);
				node.on( 'dragend', () => {
					clearGuides();
					onChangeLayer( layer.id, {
						box: {
							...layer.box,
							x: Math.round( node.x() - layer.box.w / 2 ),
							y: Math.round( node.y() - layer.box.h / 2 ),
						},
					} );
				} );
				node.on( 'transformend', () => {
					const w = Math.max(
						1,
						Math.round( layer.box.w * Math.abs( node.scaleX() ) )
					);
					const h = Math.max(
						1,
						Math.round( layer.box.h * Math.abs( node.scaleY() ) )
					);
					onChangeLayer( layer.id, {
						rotation: Math.round( node.rotation() * 10 ) / 10,
						box: {
							x: Math.round( node.x() - w / 2 ),
							y: Math.round( node.y() - h / 2 ),
							w,
							h,
						},
					} );
				} );
			}

			content.current.add( node );
		} );

		transformer.current.nodes( selectedNode ? [ selectedNode ] : [] );
		stage.current.batchDraw();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ doc, selectedId, width, version, tags, sources ] );

	// Arrow keys nudge the selected layer; Shift moves 10 px.
	const latest = useRef( {} );
	latest.current = { doc, selectedId, onChangeLayer };
	useEffect( () => {
		const nudge = ( event ) => {
			const steps = {
				ArrowLeft: [ -1, 0 ],
				ArrowRight: [ 1, 0 ],
				ArrowUp: [ 0, -1 ],
				ArrowDown: [ 0, 1 ],
			};
			const target = event.target;
			if (
				! steps[ event.key ] ||
				target.closest(
					'input, textarea, select, [contenteditable="true"]'
				)
			) {
				return;
			}
			const current = latest.current;
			const layer =
				current.doc &&
				current.doc.layers.find( ( l ) => l.id === current.selectedId );
			if ( ! layer || ! BOXED.includes( layer.type ) ) {
				return;
			}
			event.preventDefault();
			const [ dx, dy ] = steps[ event.key ];
			const amount = event.shiftKey ? 10 : 1;
			current.onChangeLayer( layer.id, {
				box: {
					...layer.box,
					x: layer.box.x + dx * amount,
					y: layer.box.y + dy * amount,
				},
			} );
		};
		window.addEventListener( 'keydown', nudge );
		return () => window.removeEventListener( 'keydown', nudge );
	}, [] );

	return el( 'div', { className: 'aimg-studio__stage', ref: container } );
}
