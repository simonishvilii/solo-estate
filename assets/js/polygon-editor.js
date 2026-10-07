/**
 * Visual polygon editor: draws an item's outline on its parent image.
 *
 * Coordinates are stored in the image's natural pixel space as "x1,y1,x2,y2,…",
 * the same format the front end and the legacy system use. The outline is always closed.
 *
 * - Click on the image: add a point (at the end of the outline).
 * - Drag a point: move it. Click a point: select it; Delete / Backspace removes it.
 * - Point at an edge: a "+" handle appears; press and drag to insert a point there.
 * - Drag inside the outline: move the whole outline.
 * - Drag outside the outline: pan the zoomed image. Mouse wheel: zoom around the pointer.
 * - "Clean up points": removes points that sit on top of each other.
 */
( function () {
	'use strict';

	var SVG_NS = 'http://www.w3.org/2000/svg';

	/** Screen pixels: how close counts as "on" an edge, and when a press becomes a drag. */
	var EDGE_HIT = 9;
	var DRAG_START = 3;

	function el( name, attrs ) {
		var node = document.createElementNS( SVG_NS, name );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		return node;
	}

	function parse( value ) {
		var nums = ( value || '' ).match( /-?\d+(?:\.\d+)?/g ) || [];
		var points = [];
		for ( var i = 0; i + 1 < nums.length; i += 2 ) {
			points.push( [ parseFloat( nums[ i ] ), parseFloat( nums[ i + 1 ] ) ] );
		}
		return points;
	}

	function serialize( points ) {
		return points.map( function ( p ) {
			return Math.round( p[ 0 ] * 10 ) / 10 + ',' + Math.round( p[ 1 ] * 10 ) / 10;
		} ).join( ',' );
	}

	/** Closest point to p on segment a–b, and its distance. */
	function project( p, a, b ) {
		var dx = b[ 0 ] - a[ 0 ];
		var dy = b[ 1 ] - a[ 1 ];
		var len = dx * dx + dy * dy;
		var t = len ? ( ( p[ 0 ] - a[ 0 ] ) * dx + ( p[ 1 ] - a[ 1 ] ) * dy ) / len : 0;
		t = Math.max( 0, Math.min( 1, t ) );
		var q = [ a[ 0 ] + t * dx, a[ 1 ] + t * dy ];
		return { point: q, dist: Math.hypot( p[ 0 ] - q[ 0 ], p[ 1 ] - q[ 1 ] ), t: t };
	}

	function PolygonEditor( root ) {
		this.root = root;
		this.input = root.querySelector( '[data-poly-input]' );
		this.viewport = root.querySelector( '.solo-estate-poly__viewport' );
		this.canvas = root.querySelector( '.solo-estate-poly__canvas' );
		this.points = parse( this.input.value );
		this.history = [];
		this.zoom = 1;
		this.selected = null;
		this.drag = null;
		this.siblings = [];
		try {
			this.siblings = JSON.parse( root.getAttribute( 'data-siblings' ) || '[]' );
		} catch ( e ) {}

		this.img = document.createElement( 'img' );
		this.img.alt = '';
		this.img.draggable = false;
		this.img.addEventListener( 'load', this.setup.bind( this ) );
		this.img.src = root.getAttribute( 'data-src' );
		this.canvas.appendChild( this.img );
	}

	PolygonEditor.prototype.setup = function () {
		var self = this;
		// Coordinates live in the frame the front end uses (attachment metadata size), not in
		// the pixels of the loaded file, which may have been resized since.
		this.w = parseInt( this.root.getAttribute( 'data-width' ), 10 ) || this.img.naturalWidth;
		this.h = parseInt( this.root.getAttribute( 'data-height' ), 10 ) || this.img.naturalHeight;

		this.svg = el( 'svg', { viewBox: '0 0 ' + this.w + ' ' + this.h, preserveAspectRatio: 'none', class: 'solo-estate-poly__svg', tabindex: '0' } );
		this.siblingLayer = el( 'g', { class: 'solo-estate-poly__siblings' } );
		this.shape = el( 'polygon', { class: 'solo-estate-poly__shape' } );
		this.edgeLayer = el( 'g', { class: 'solo-estate-poly__edges' } );
		this.ghost = el( 'circle', { class: 'solo-estate-poly__ghost', r: 0 } );
		this.handleLayer = el( 'g', { class: 'solo-estate-poly__handles' } );
		this.svg.appendChild( this.siblingLayer );
		this.svg.appendChild( this.shape );
		this.svg.appendChild( this.edgeLayer );
		this.svg.appendChild( this.ghost );
		this.svg.appendChild( this.handleLayer );
		this.canvas.appendChild( this.svg );

		this.siblings.forEach( function ( item ) {
			var poly = el( 'polygon', { points: item.points.map( function ( p ) { return p.join( ',' ); } ).join( ' ' ) } );
			var title = el( 'title' );
			title.textContent = item.label;
			poly.appendChild( title );
			self.siblingLayer.appendChild( poly );
		} );

		this.svg.addEventListener( 'pointerdown', this.onDown.bind( this ) );
		this.svg.addEventListener( 'pointermove', this.onMove.bind( this ) );
		this.svg.addEventListener( 'pointerup', this.onUp.bind( this ) );
		this.svg.addEventListener( 'pointerleave', function () {
			self.hideGhost();
		} );
		this.svg.addEventListener( 'dblclick', this.onDblClick.bind( this ) );
		this.svg.addEventListener( 'keydown', this.onKey.bind( this ) );

		this.input.addEventListener( 'input', function () {
			self.points = parse( self.input.value );
			self.selected = null;
			self.draw( false );
		} );

		var copy = this.root.querySelector( '[data-poly-copy]' );
		if ( copy ) {
			copy.addEventListener( 'click', function () {
				var done = function () {
					var label = copy.textContent;
					copy.textContent = '✓';
					setTimeout( function () { copy.textContent = label; }, 1200 );
				};
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( self.input.value ).then( done );
				} else {
					self.input.select();
					document.execCommand( 'copy' );
					done();
				}
			} );
		}

		var undo = this.root.querySelector( '[data-poly-undo]' );
		var clear = this.root.querySelector( '[data-poly-clear]' );
		var clean = this.root.querySelector( '[data-poly-clean]' );
		var remove = this.root.querySelector( '[data-poly-delete]' );
		var toggle = this.root.querySelector( '[data-poly-siblings]' );

		undo.addEventListener( 'click', function () {
			if ( self.history.length ) {
				self.points = self.history.pop();
			} else {
				self.points.pop();
			}
			self.selected = null;
			self.draw( true );
		} );
		clear.addEventListener( 'click', function () {
			if ( ! self.points.length || window.confirm( ( window.soloEstateAdmin && soloEstateAdmin.i18n.confirmClear ) || 'Clear?' ) ) {
				self.remember();
				self.points = [];
				self.selected = null;
				self.draw( true );
			}
		} );
		if ( clean ) {
			clean.addEventListener( 'click', function () {
				self.cleanUp();
			} );
		}
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				self.deleteSelected();
			} );
		}
		toggle.addEventListener( 'change', function () {
			self.siblingLayer.style.display = toggle.checked ? '' : 'none';
		} );
		Array.prototype.forEach.call( this.root.querySelectorAll( '[data-poly-zoom]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var step = parseInt( button.getAttribute( 'data-poly-zoom' ), 10 );
				var rect = self.viewport.getBoundingClientRect();
				self.setZoom( self.zoom * ( step > 0 ? 1.5 : 1 / 1.5 ), rect.left + rect.width / 2, rect.top + rect.height / 2 );
			} );
		} );
		// Mouse wheel / trackpad pinch: zoom around the pointer.
		this.viewport.addEventListener( 'wheel', function ( event ) {
			event.preventDefault();
			var factor = Math.pow( 1.0015, -event.deltaY * ( 1 === event.deltaMode ? 30 : 1 ) );
			self.setZoom( self.zoom * factor, event.clientX, event.clientY );
		}, { passive: false } );
		window.addEventListener( 'resize', function () {
			self.draw( false );
		} );

		this.draw( false );
	};

	/**
	 * Zooms to a level (1 = fit, up to 8×), keeping the image point under (cx, cy) in place.
	 *
	 * @param {number} level Zoom level.
	 * @param {number} cx    Screen x.
	 * @param {number} cy    Screen y.
	 */
	PolygonEditor.prototype.setZoom = function ( level, cx, cy ) {
		level = Math.min( 8, Math.max( 1, level ) );
		if ( Math.abs( level - this.zoom ) < 0.001 ) {
			return;
		}
		var view = this.viewport.getBoundingClientRect();
		var box = this.canvas.getBoundingClientRect();
		var fx = box.width ? ( cx - box.left ) / box.width : 0.5;
		var fy = box.height ? ( cy - box.top ) / box.height : 0.5;
		this.zoom = level;
		this.canvas.style.width = level * 100 + '%';
		var next = this.canvas.getBoundingClientRect();
		this.viewport.scrollLeft = fx * next.width - ( cx - view.left );
		this.viewport.scrollTop = fy * next.height - ( cy - view.top );
		this.root.classList.toggle( 'is-zoomed', level > 1 );
		var label = this.root.querySelector( '[data-poly-zoom-level]' );
		if ( label ) {
			label.textContent = Math.round( level * 100 ) + '%';
		}
		this.draw( false );
	};

	/** Image pixels per screen pixel. */
	PolygonEditor.prototype.scale = function () {
		var rect = this.svg.getBoundingClientRect();
		return rect.width ? this.w / rect.width : 1;
	};

	PolygonEditor.prototype.toImage = function ( event ) {
		var rect = this.svg.getBoundingClientRect();
		var x = ( event.clientX - rect.left ) * ( this.w / rect.width );
		var y = ( event.clientY - rect.top ) * ( this.h / rect.height );
		return [ Math.max( 0, Math.min( this.w, x ) ), Math.max( 0, Math.min( this.h, y ) ) ];
	};

	PolygonEditor.prototype.remember = function () {
		this.history.push( this.points.map( function ( p ) { return p.slice(); } ) );
		if ( this.history.length > 100 ) {
			this.history.shift();
		}
	};

	PolygonEditor.prototype.draw = function ( sync ) {
		var self = this;
		if ( ! this.svg ) {
			return;
		}
		var s = this.scale();

		this.shape.setAttribute( 'points', this.points.map( function ( p ) { return p.join( ',' ); } ).join( ' ' ) );
		this.shape.style.strokeWidth = 2 * s;
		this.shape.classList.toggle( 'is-movable', this.points.length >= 3 );
		this.siblingLayer.style.strokeWidth = 1 * s;

		// Invisible wide lines along every edge (including the closing one) to insert points.
		while ( this.edgeLayer.firstChild ) {
			this.edgeLayer.removeChild( this.edgeLayer.firstChild );
		}
		if ( this.points.length >= 2 ) {
			this.points.forEach( function ( a, i ) {
				var b = self.points[ ( i + 1 ) % self.points.length ];
				if ( self.points.length < 3 && i === self.points.length - 1 ) {
					return;
				}
				var line = el( 'line', { x1: a[ 0 ], y1: a[ 1 ], x2: b[ 0 ], y2: b[ 1 ], class: 'solo-estate-poly__edge', 'data-edge': i } );
				line.style.strokeWidth = EDGE_HIT * 2 * s;
				self.edgeLayer.appendChild( line );
			} );
		}

		while ( this.handleLayer.firstChild ) {
			this.handleLayer.removeChild( this.handleLayer.firstChild );
		}
		this.points.forEach( function ( p, i ) {
			var cls = 'solo-estate-poly__handle' + ( 0 === i ? ' is-first' : '' ) + ( self.selected === i ? ' is-selected' : '' );
			var handle = el( 'circle', { cx: p[ 0 ], cy: p[ 1 ], r: ( self.selected === i ? 8 : 6 ) * s, class: cls, 'data-index': i } );
			handle.style.strokeWidth = 2 * s;
			self.handleLayer.appendChild( handle );
		} );

		var remove = this.root.querySelector( '[data-poly-delete]' );
		if ( remove ) {
			remove.disabled = null === this.selected;
		}

		if ( sync ) {
			this.input.value = serialize( this.points );
			this.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	};

	PolygonEditor.prototype.showGhost = function ( point ) {
		var s = this.scale();
		this.ghost.setAttribute( 'cx', point[ 0 ] );
		this.ghost.setAttribute( 'cy', point[ 1 ] );
		this.ghost.setAttribute( 'r', 6 * s );
		this.ghost.style.strokeWidth = 2 * s;
	};

	PolygonEditor.prototype.hideGhost = function () {
		this.ghost.setAttribute( 'r', 0 );
	};

	/** The edge under the pointer: { index, point } or null. */
	PolygonEditor.prototype.edgeAt = function ( pos ) {
		var best = null;
		var limit = EDGE_HIT * this.scale();
		var n = this.points.length;
		if ( n < 2 ) {
			return null;
		}
		for ( var i = 0; i < n; i++ ) {
			if ( n < 3 && i === n - 1 ) {
				break;
			}
			var hit = project( pos, this.points[ i ], this.points[ ( i + 1 ) % n ] );
			if ( hit.dist <= limit && hit.t > 0.02 && hit.t < 0.98 && ( ! best || hit.dist < best.dist ) ) {
				best = { index: i, point: hit.point, dist: hit.dist };
			}
		}
		return best;
	};

	PolygonEditor.prototype.onDown = function ( event ) {
		if ( 0 !== event.button ) {
			return;
		}
		this.svg.focus( { preventScroll: true } );
		var pos = this.toImage( event );
		var index = event.target.getAttribute && event.target.getAttribute( 'data-index' );

		// A point: select it, and drag it if the pointer moves.
		if ( null !== index && undefined !== index ) {
			index = parseInt( index, 10 );
			if ( event.altKey ) {
				this.selected = index;
				this.deleteSelected();
				return;
			}
			this.remember();
			this.selected = index;
			this.drag = { type: 'point', index: index, moved: false };
			this.svg.setPointerCapture( event.pointerId );
			this.draw( false );
			event.preventDefault();
			return;
		}

		// An edge: insert a point there and drag it.
		var edge = this.edgeAt( pos );
		if ( edge ) {
			this.remember();
			this.points.splice( edge.index + 1, 0, edge.point );
			this.selected = edge.index + 1;
			this.drag = { type: 'point', index: edge.index + 1, moved: true };
			this.svg.setPointerCapture( event.pointerId );
			this.hideGhost();
			this.draw( false );
			event.preventDefault();
			return;
		}

		// Inside the outline: move the whole outline once the pointer really moves;
		// a plain click still adds a point, so concave shapes can be drawn.
		if ( event.target === this.shape && this.points.length >= 3 ) {
			this.drag = { type: 'shape', start: pos, origin: this.points.map( function ( p ) { return p.slice(); } ), moved: false, screen: [ event.clientX, event.clientY ] };
			this.svg.setPointerCapture( event.pointerId );
			event.preventDefault();
			return;
		}

		// Outside the outline: pan the zoomed image if the pointer moves, otherwise add a point.
		this.drag = { type: 'pan', moved: false, screen: [ event.clientX, event.clientY ], scroll: [ this.viewport.scrollLeft, this.viewport.scrollTop ] };
		this.svg.setPointerCapture( event.pointerId );
		event.preventDefault();
	};

	PolygonEditor.prototype.onMove = function ( event ) {
		var pos = this.toImage( event );
		if ( ! this.drag ) {
			// Hover: show where a new point would go on an edge.
			var edge = event.target.classList && ( event.target.classList.contains( 'solo-estate-poly__handle' ) ) ? null : this.edgeAt( pos );
			if ( edge ) {
				this.showGhost( edge.point );
				this.svg.classList.add( 'is-on-edge' );
			} else {
				this.hideGhost();
				this.svg.classList.remove( 'is-on-edge' );
			}
			return;
		}
		if ( 'point' === this.drag.type ) {
			this.drag.moved = true;
			this.points[ this.drag.index ] = pos;
			this.draw( false );
			return;
		}
		if ( 'pan' === this.drag.type ) {
			var mx = event.clientX - this.drag.screen[ 0 ];
			var my = event.clientY - this.drag.screen[ 1 ];
			if ( ! this.drag.moved && Math.hypot( mx, my ) < DRAG_START ) {
				return;
			}
			this.drag.moved = true;
			this.svg.classList.add( 'is-panning' );
			this.viewport.scrollLeft = this.drag.scroll[ 0 ] - mx;
			this.viewport.scrollTop = this.drag.scroll[ 1 ] - my;
			return;
		}
		// Whole outline, kept inside the image.
		if ( ! this.drag.moved && Math.hypot( event.clientX - this.drag.screen[ 0 ], event.clientY - this.drag.screen[ 1 ] ) < DRAG_START ) {
			return;
		}
		if ( ! this.drag.moved ) {
			this.remember();
			this.drag.moved = true;
			this.svg.classList.add( 'is-moving' );
		}
		var dx = pos[ 0 ] - this.drag.start[ 0 ];
		var dy = pos[ 1 ] - this.drag.start[ 1 ];
		var xs = this.drag.origin.map( function ( p ) { return p[ 0 ]; } );
		var ys = this.drag.origin.map( function ( p ) { return p[ 1 ]; } );
		dx = Math.max( -Math.min.apply( null, xs ), Math.min( this.w - Math.max.apply( null, xs ), dx ) );
		dy = Math.max( -Math.min.apply( null, ys ), Math.min( this.h - Math.max.apply( null, ys ), dy ) );
		this.points = this.drag.origin.map( function ( p ) { return [ p[ 0 ] + dx, p[ 1 ] + dy ]; } );
		this.draw( false );
	};

	PolygonEditor.prototype.onUp = function ( event ) {
		var drag = this.drag;
		if ( ! drag ) {
			return;
		}
		this.drag = null;
		this.svg.classList.remove( 'is-moving' );
		this.svg.classList.remove( 'is-panning' );
		if ( 'pan' === drag.type ) {
			if ( ! drag.moved ) {
				this.remember();
				this.points.push( this.toImage( event ) );
				this.selected = this.points.length - 1;
				this.draw( true );
			}
			return;
		}
		if ( 'point' === drag.type && ! drag.moved ) {
			this.history.pop(); // Only selected, nothing changed.
			this.draw( false );
			return;
		}
		if ( 'shape' === drag.type && ! drag.moved ) {
			// A click inside the outline adds a point, like anywhere else.
			this.remember();
			this.points.push( this.toImage( event ) );
			this.selected = this.points.length - 1;
		}
		this.draw( true );
	};

	PolygonEditor.prototype.onKey = function ( event ) {
		if ( ( 'Delete' === event.key || 'Backspace' === event.key ) && null !== this.selected ) {
			event.preventDefault();
			this.deleteSelected();
		} else if ( 'Escape' === event.key ) {
			this.selected = null;
			this.draw( false );
		} else if ( ( event.ctrlKey || event.metaKey ) && 'z' === event.key.toLowerCase() && this.history.length ) {
			event.preventDefault();
			this.points = this.history.pop();
			this.selected = null;
			this.draw( true );
		}
	};

	/** Removes the selected point; the outline closes over the remaining ones. */
	PolygonEditor.prototype.deleteSelected = function () {
		if ( null === this.selected || ! this.points[ this.selected ] ) {
			return;
		}
		this.remember();
		this.points.splice( this.selected, 1 );
		this.selected = this.points.length ? Math.min( this.selected, this.points.length - 1 ) : null;
		this.draw( true );
	};

	/** Drops points that sit on (or right next to) the previous one — common in imported outlines. */
	PolygonEditor.prototype.cleanUp = function () {
		var limit = 3 * this.scale();
		var before = this.points.length;
		var kept = [];
		this.points.forEach( function ( p ) {
			var last = kept[ kept.length - 1 ];
			if ( ! last || Math.hypot( p[ 0 ] - last[ 0 ], p[ 1 ] - last[ 1 ] ) > limit ) {
				kept.push( p );
			}
		} );
		if ( kept.length > 2 && Math.hypot( kept[ 0 ][ 0 ] - kept[ kept.length - 1 ][ 0 ], kept[ 0 ][ 1 ] - kept[ kept.length - 1 ][ 1 ] ) <= limit ) {
			kept.pop();
		}
		if ( kept.length !== this.points.length ) {
			this.remember();
			this.points = kept;
			this.selected = null;
			this.draw( true );
		}
		var clean = this.root.querySelector( '[data-poly-clean]' );
		if ( clean ) {
			var removed = before - kept.length;
			var label = clean.getAttribute( 'data-label' ) || clean.textContent;
			clean.setAttribute( 'data-label', label );
			clean.textContent = '✓ −' + removed;
			setTimeout( function () { clean.textContent = label; }, 1500 );
		}
	};

	PolygonEditor.prototype.onDblClick = function ( event ) {
		var index = event.target.getAttribute && event.target.getAttribute( 'data-index' );
		if ( null === index || undefined === index ) {
			return;
		}
		this.selected = parseInt( index, 10 );
		this.deleteSelected();
	};

	window.SoloEstatePolygonEditor = PolygonEditor;

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-solo-estate-poly]' ), function ( root ) {
			new PolygonEditor( root );
		} );
	} );
}() );
