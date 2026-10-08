/**
 * Konva nodes for template layers, mirroring includes/Rendering/Layers/*.php.
 *
 * Boxed layers (image, text, shape) are groups placed at their box center, so
 * rotation and the transformer work around the center like the server.
 */
import Konva, { Filters } from './konva';
import { PX_PER_SIZE, fit, fontString, measure, wrap } from './text-layout';

const ANCHORS = {
	'top-left': [ 0, 0 ],
	'top-center': [ 0.5, 0 ],
	'top-right': [ 1, 0 ],
	'left-center': [ 0, 0.5 ],
	'center-center': [ 0.5, 0.5 ],
	'right-center': [ 1, 0.5 ],
	'bottom-left': [ 0, 1 ],
	'bottom-center': [ 0.5, 1 ],
	'bottom-right': [ 1, 1 ],
};

export function rgba( hex, opacity = 1 ) {
	const value = /^#[0-9a-f]{6}$/i.test( hex || '' ) ? hex : '#000000';
	const [ r, g, b ] = [ 1, 3, 5 ].map( ( i ) =>
		parseInt( value.slice( i, i + 2 ), 16 )
	);
	return `rgba(${ r },${ g },${ b },${ opacity })`;
}

/**
 * Konva gradient props for a w × h area, matching Paint::gradient().
 *
 * @param {Object} gradient Gradient { kind, angle, cx, cy, stops }.
 * @param {number} w        Width.
 * @param {number} h        Height.
 * @param {number} opacity  Multiplies every stop's opacity.
 * @return {Object} Konva fill props.
 */
export function gradientFill( gradient, w, h, opacity = 1 ) {
	const stops = [];
	[ ...gradient.stops ]
		.sort( ( a, b ) => a.pos - b.pos )
		.forEach( ( stop ) =>
			stops.push( stop.pos, rgba( stop.color, stop.opacity * opacity ) )
		);

	if ( gradient.kind === 'radial' ) {
		const cx = gradient.cx * w;
		const cy = gradient.cy * h;
		const reach = Math.max(
			Math.hypot( cx, cy ),
			Math.hypot( w - cx, cy ),
			Math.hypot( cx, h - cy ),
			Math.hypot( w - cx, h - cy )
		);
		return {
			fillRadialGradientStartPoint: { x: cx, y: cy },
			fillRadialGradientEndPoint: { x: cx, y: cy },
			fillRadialGradientStartRadius: 0,
			fillRadialGradientEndRadius: reach,
			fillRadialGradientColorStops: stops,
		};
	}

	const angle = ( gradient.angle * Math.PI ) / 180;
	const dx = Math.sin( angle );
	const dy = -Math.cos( angle );
	const half = ( Math.abs( w * dx ) + Math.abs( h * dy ) ) / 2;
	return {
		fillLinearGradientStartPoint: {
			x: w / 2 - dx * half,
			y: h / 2 - dy * half,
		},
		fillLinearGradientEndPoint: {
			x: w / 2 + dx * half,
			y: h / 2 + dy * half,
		},
		fillLinearGradientColorStops: stops,
	};
}

/**
 * Place an image in a box like Images::place().
 *
 * @param {HTMLImageElement} img    Image.
 * @param {number}           w      Box width.
 * @param {number}           h      Box height.
 * @param {string}           mode   contain, cover, fill or tile.
 * @param {string}           anchor Anchor for contain.
 * @param {Object}           focal  Focal point for cover.
 * @return {Object} Konva.Image props.
 */
export function placeImage( img, w, h, mode, anchor, focal ) {
	const sw = img.naturalWidth || img.width;
	const sh = img.naturalHeight || img.height;

	if ( mode === 'fill' ) {
		return { image: img, x: 0, y: 0, width: w, height: h };
	}
	if ( mode === 'cover' ) {
		const scale = Math.max( w / sw, h / sh );
		const cw = Math.min( sw, Math.round( w / scale ) );
		const ch = Math.min( sh, Math.round( h / scale ) );
		const fx = focal ? focal.x : 0.5;
		const fy = focal ? focal.y : 0.5;
		return {
			image: img,
			x: 0,
			y: 0,
			width: w,
			height: h,
			crop: {
				x: Math.max(
					0,
					Math.min( sw - cw, Math.round( fx * sw - cw / 2 ) )
				),
				y: Math.max(
					0,
					Math.min( sh - ch, Math.round( fy * sh - ch / 2 ) )
				),
				width: cw,
				height: ch,
			},
		};
	}

	const scale = Math.min( w / sw, h / sh );
	const iw = Math.floor( sw * scale );
	const ih = Math.floor( sh * scale );
	const [ ax, ay ] = ANCHORS[ anchor ] || ANCHORS[ 'bottom-right' ];
	return {
		image: img,
		x: Math.floor( ( w - iw ) * ax ),
		y: Math.floor( ( h - ih ) * ay ),
		width: iw,
		height: ih,
	};
}

function duotoneFilter( dark, light ) {
	const d = [ 1, 3, 5 ].map( ( i ) =>
		parseInt( dark.slice( i, i + 2 ), 16 )
	);
	const l = [ 1, 3, 5 ].map( ( i ) =>
		parseInt( light.slice( i, i + 2 ), 16 )
	);
	return ( imageData ) => {
		const px = imageData.data;
		for ( let i = 0; i < px.length; i += 4 ) {
			const g =
				( 0.299 * px[ i ] +
					0.587 * px[ i + 1 ] +
					0.114 * px[ i + 2 ] ) /
				255;
			px[ i ] = d[ 0 ] + ( l[ 0 ] - d[ 0 ] ) * g;
			px[ i + 1 ] = d[ 1 ] + ( l[ 1 ] - d[ 1 ] ) * g;
			px[ i + 2 ] = d[ 2 ] + ( l[ 2 ] - d[ 2 ] ) * g;
		}
	};
}

function applyAdjustments( node, adjust ) {
	if ( ! adjust ) {
		return;
	}
	const filters = [];
	const props = {};
	if ( adjust.brightness ) {
		filters.push( Filters.Brighten );
		props.brightness = adjust.brightness / 100;
	}
	if ( adjust.contrast ) {
		filters.push( Filters.Contrast );
		props.contrast = adjust.contrast;
	}
	if ( adjust.blur ) {
		filters.push( Filters.Blur );
		props.blurRadius = adjust.blur * 2;
	}
	if ( adjust.grayscale && ! adjust.duotone ) {
		filters.push( Filters.Grayscale );
	}
	if ( adjust.duotone ) {
		filters.push(
			duotoneFilter( adjust.duotone.dark, adjust.duotone.light )
		);
	}
	if ( filters.length ) {
		node.setAttrs( props );
		node.filters( filters );
		node.cache();
	}
}

export function roundedPath( ctx, x, y, w, h, r ) {
	r = Math.max( 0, Math.min( r, w / 2, h / 2 ) );
	ctx.beginPath();
	ctx.moveTo( x + r, y );
	ctx.arcTo( x + w, y, x + w, y + h, r );
	ctx.arcTo( x + w, y + h, x, y + h, r );
	ctx.arcTo( x, y + h, x, y, r );
	ctx.arcTo( x, y, x + w, y, r );
	ctx.closePath();
}

export function placeholder( w, h, label ) {
	const group = new Konva.Group();
	group.add(
		new Konva.Rect( {
			width: w,
			height: h,
			fill: 'rgba(255,255,255,0.12)',
			stroke: 'rgba(255,255,255,0.6)',
			dash: [ 8, 6 ],
		} ),
		new Konva.Text( {
			width: w,
			height: h,
			text: label,
			align: 'center',
			verticalAlign: 'middle',
			fill: '#ffffff',
			fontSize: Math.max( 12, Math.min( 22, w / 12 ) ),
		} )
	);
	return group;
}

export function hitArea( w, h ) {
	return new Konva.Rect( { width: w, height: h, fill: 'rgba(0,0,0,0)' } );
}

export function boxedGroup( layer ) {
	const { x, y, w, h } = layer.box;
	return new Konva.Group( {
		x: x + w / 2,
		y: y + h / 2,
		offsetX: w / 2,
		offsetY: h / 2,
		rotation: layer.rotation || 0,
	} );
}

function drawBackground( layer, ctx ) {
	const { width, height } = ctx.canvas;
	const fill = layer.fill;
	const group = new Konva.Group();

	if ( fill.kind === 'linear' || fill.kind === 'radial' ) {
		group.add(
			new Konva.Rect( {
				width,
				height,
				...gradientFill( fill, width, height ),
			} )
		);
	} else if ( fill.kind === 'image' ) {
		group.add( new Konva.Rect( { width, height, fill: fill.color } ) );
		const img = ctx.imageFor( fill );
		if ( img && fill.fit === 'tile' ) {
			group.add(
				new Konva.Rect( { width, height, fillPatternImage: img } )
			);
		} else if ( img ) {
			group.add(
				new Konva.Image(
					placeImage(
						img,
						width,
						height,
						fill.fit,
						'center-center',
						fill.focal
					)
				)
			);
		} else {
			group.add( placeholder( width, height, ctx.sourceLabel( fill ) ) );
		}
	} else {
		const color = fill.kind === 'palette' ? fill.colors[ 0 ] : fill.color;
		group.add( new Konva.Rect( { width, height, fill: color } ) );
	}

	if ( layer.adjust && Object.values( layer.adjust ).some( Boolean ) ) {
		applyAdjustments( group, layer.adjust );
	}
	return group;
}

function drawOverlay( layer, ctx ) {
	const { width, height } = ctx.canvas;
	if ( layer.kind === 'gradient' ) {
		return new Konva.Rect( {
			width,
			height,
			listening: false,
			...gradientFill( layer.gradient, width, height, layer.opacity ),
		} );
	}
	const color =
		layer.color === 'background' ? ctx.backgroundColor() : layer.color;
	return new Konva.Rect( {
		width,
		height,
		fill: rgba( color, layer.opacity ),
		listening: false,
	} );
}

function drawImage( layer, ctx ) {
	const { w, h } = layer.box;
	const group = boxedGroup( layer );
	const img = ctx.imageFor( layer );

	if ( ! img ) {
		group.add( placeholder( w, h, ctx.sourceLabel( layer ) ) );
		return group;
	}

	const node = new Konva.Image(
		placeImage( img, w, h, layer.fit, layer.anchor, layer.focal )
	);
	node.opacity( layer.opacity );
	applyAdjustments( node, layer.adjust );

	if ( layer.mask && layer.mask !== 'none' ) {
		const clip = new Konva.Group( {
			clipFunc: ( c ) => {
				if ( layer.mask === 'circle' ) {
					c.beginPath();
					c.ellipse( w / 2, h / 2, w / 2, h / 2, 0, 0, Math.PI * 2 );
				} else {
					roundedPath( c, 0, 0, w, h, layer.radius );
				}
			},
		} );
		clip.add( node );
		group.add( clip );
	} else {
		group.add( node );
	}

	group.add( hitArea( w, h ) );
	return group;
}

function drawShape( layer ) {
	const { w, h } = layer.box;
	const group = boxedGroup( layer );
	const fill = layer.fill.kind === 'none' ? null : layer.fill;
	const isLine = layer.shape === 'line';
	const height = isLine ? Math.max( 1, Math.round( layer.thickness ) ) : h;
	const top = isLine ? ( h - height ) / 2 : 0;
	const border = ! isLine && layer.border.width > 0 ? layer.border : null;
	const inset = border ? border.width / 2 : 0;

	const fillProps = ( fw, fh ) => {
		if ( ! fill ) {
			return {};
		}
		if ( fill.kind === 'linear' || fill.kind === 'radial' ) {
			return gradientFill( fill, fw, fh );
		}
		return { fill: rgba( fill.color, fill.opacity ) };
	};

	const common = {
		...fillProps( w, height ),
		stroke: border ? rgba( border.color, border.opacity ) : undefined,
		strokeWidth: border ? border.width : 0,
	};

	if ( layer.shape === 'ellipse' ) {
		group.add(
			new Konva.Ellipse( {
				x: w / 2,
				y: h / 2,
				radiusX: Math.max( 0, w / 2 - inset ),
				radiusY: Math.max( 0, h / 2 - inset ),
				...common,
			} )
		);
	} else {
		group.add(
			new Konva.Rect( {
				x: inset,
				y: top + inset,
				width: Math.max( 0, w - 2 * inset ),
				height: Math.max( 0, height - 2 * inset ),
				cornerRadius: Math.max( 0, layer.radius - inset ),
				...common,
			} )
		);
	}

	group.add( hitArea( w, h ) );
	return group;
}

function drawPattern( layer, ctx ) {
	const { width, height } = ctx.canvas;
	const spacing = Math.max( 4, Math.round( layer.spacing ) );
	const size = Math.max( 1, Math.round( layer.size ) );

	return new Konva.Shape( {
		listening: false,
		sceneFunc: ( c ) => {
			c.fillStyle = rgba( layer.color, layer.opacity );
			c.strokeStyle = rgba( layer.color, layer.opacity );
			c.lineWidth = size;
			const start = Math.floor( spacing / 2 );
			if ( layer.pattern === 'dots' ) {
				for ( let y = start; y < height; y += spacing ) {
					for ( let x = start; x < width; x += spacing ) {
						c.beginPath();
						c.arc( x, y, size, 0, Math.PI * 2 );
						c.fill();
					}
				}
			} else if ( layer.pattern === 'lines' ) {
				for ( let y = start; y < height; y += spacing ) {
					c.fillRect( 0, y, width, size );
				}
			} else if ( layer.pattern === 'grid' ) {
				for ( let y = 0; y < height; y += spacing ) {
					c.fillRect( 0, y, width, size );
				}
				for ( let x = 0; x < width; x += spacing ) {
					c.fillRect( x, 0, size, height );
				}
			} else if ( layer.pattern === 'diagonal' ) {
				c.beginPath();
				for ( let o = -height; o < width; o += spacing ) {
					c.moveTo( o, height );
					c.lineTo( o + height, 0 );
				}
				c.stroke();
			} else if ( layer.pattern === 'noise' ) {
				let seed = 42;
				const random = () => {
					seed = ( seed * 16807 ) % 2147483647;
					return seed / 2147483647;
				};
				const count = Math.floor(
					width * height * Math.min( 0.5, layer.size / 20 )
				);
				for ( let i = 0; i < count; i++ ) {
					c.fillRect(
						Math.floor( random() * width ),
						Math.floor( random() * height ),
						1,
						1
					);
				}
			}
		},
	} );
}

function drawFrame( layer, ctx ) {
	const { width, height } = ctx.canvas;
	const half = layer.width / 2;
	return new Konva.Rect( {
		x: layer.inset + half,
		y: layer.inset + half,
		width: Math.max( 0, width - 2 * layer.inset - layer.width ),
		height: Math.max( 0, height - 2 * layer.inset - layer.width ),
		cornerRadius: Math.max( 0, layer.radius - half ),
		stroke: rgba( layer.color, layer.opacity ),
		strokeWidth: layer.width,
		listening: false,
	} );
}

function transform( text, mode ) {
	if ( mode === 'upper' ) {
		return text.toUpperCase();
	}
	if ( mode === 'lower' ) {
		return text.toLowerCase();
	}
	if ( mode === 'title' ) {
		return text
			.toLowerCase()
			.replace( /(^|\s)(\S)/gu, ( m, s, c ) => s + c.toUpperCase() );
	}
	return text;
}

function drawText( layer, ctx ) {
	const { x: bx, y: by, w, h } = layer.box;
	const group = boxedGroup( layer );
	group.add( hitArea( w, h ) );

	const text = transform(
		ctx.merge( layer.content ).trim(),
		layer.transform
	);
	if ( ! text ) {
		return group;
	}

	const family = ctx.fontFamily( layer.font );
	const spacing = layer.letterSpacing || 0;
	const layout =
		layer.fit === 'width'
			? wrap( text, layer.size.max, family, w, layer.size.min, spacing )
			: fit( text, family, {
					maxSize: layer.size.max,
					minSize: layer.size.min,
					width: w,
					height: h,
					lineHeight: layer.lineHeight,
					maxLines: layer.maxLines,
					spacing,
			  } );

	const size = layout.fontSize;
	const lineHeight = size * layer.lineHeight;
	const total = layout.lines.length * lineHeight;
	let y = by + size;
	if ( layer.valign === 'bottom' ) {
		y = by + h - total + size;
	} else if ( layer.valign !== 'top' ) {
		y = by + ( h - total ) / 2 + size;
	}

	const lines = layout.lines.map( ( line ) => {
		const width = measure( line, size, family, spacing );
		let x = bx + ( w - width ) / 2;
		if ( layer.align === 'left' ) {
			x = bx;
		} else if ( layer.align === 'right' ) {
			x = bx + w - width;
		}
		const out = {
			text: line,
			x: Math.trunc( x ) - bx,
			y: Math.trunc( y ) - by,
			width,
		};
		y += lineHeight;
		return out;
	} );

	const px = size * PX_PER_SIZE;
	const font = fontString( family, size );

	group.add(
		new Konva.Shape( {
			listening: false,
			sceneFunc: ( konvaContext ) => {
				const c = konvaContext._context;
				c.save();
				c.font = font;
				c.textBaseline = 'alphabetic';

				const write = ( fn, dx = 0, dy = 0 ) =>
					lines.forEach( ( line ) => {
						if ( ! spacing ) {
							fn( line.text, line.x + dx, line.y + dy );
							return;
						}
						const chars = [ ...line.text ];
						chars.forEach( ( char, i ) => {
							const prefix = chars.slice( 0, i ).join( '' );
							const offset = prefix
								? measure( prefix, size, family )
								: 0;
							fn(
								char,
								line.x + offset + i * spacing + dx,
								line.y + dy
							);
						} );
					} );

				const hl = layer.highlight;
				if ( hl && hl.mode !== 'none' ) {
					c.fillStyle = rgba( hl.color, hl.opacity );
					const rects =
						hl.mode === 'block'
							? [
									[
										Math.min(
											...lines.map( ( l ) => l.x )
										),
										lines[ 0 ].y - px * 0.8,
										Math.max(
											...lines.map(
												( l ) => l.x + l.width
											)
										),
										lines[ lines.length - 1 ].y + px * 0.25,
									],
							  ]
							: lines.map( ( l ) => [
									l.x,
									l.y - px * 0.8,
									l.x + l.width,
									l.y + px * 0.25,
							  ] );
					rects.forEach( ( r ) => {
						roundedPath(
							c,
							r[ 0 ] - hl.padding,
							r[ 1 ] - hl.padding,
							r[ 2 ] - r[ 0 ] + 2 * hl.padding,
							r[ 3 ] - r[ 1 ] + 2 * hl.padding,
							hl.radius
						);
						c.fill();
					} );
				}

				const sh = layer.shadow;
				if ( sh && sh.opacity > 0 ) {
					c.save();
					c.fillStyle = rgba( sh.color, sh.opacity );
					c.filter = sh.blur ? `blur(${ sh.blur }px)` : 'none';
					write( ( t, x, yy ) => c.fillText( t, x, yy ), sh.x, sh.y );
					c.restore();
				}

				const st = layer.stroke;
				if ( st && st.width > 0 ) {
					c.save();
					c.strokeStyle = rgba( st.color, st.opacity );
					c.lineWidth = st.width * 2;
					c.lineJoin = 'round';
					write( ( t, x, yy ) => c.strokeText( t, x, yy ) );
					c.restore();
				}

				c.fillStyle = rgba( layer.color, layer.opacity );
				write( ( t, x, yy ) => c.fillText( t, x, yy ) );
				c.restore();
			},
		} )
	);

	return group;
}

export const DRAWERS = {
	background: drawBackground,
	overlay: drawOverlay,
	image: drawImage,
	shape: drawShape,
	pattern: drawPattern,
	frame: drawFrame,
	text: drawText,
};
