/**
 * Solo Estate front end: polygon tooltips, currency switch, floor navigation, lightbox,
 * plan / photo tabs, virtual tour, lead form.
 * Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	var config = window.soloEstateFront || {};
	var STORAGE_KEY = 'soloEstateCurrency';

	function each( list, fn ) {
		Array.prototype.forEach.call( list, fn );
	}

	/* ---------- Currency ---------- */

	function storedCurrency() {
		try {
			return window.localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	}

	function setCurrency( cur ) {
		each( document.querySelectorAll( '.solo-estate-app' ), function ( app ) {
			app.setAttribute( 'data-currency', cur );
		} );
		each( document.querySelectorAll( '.solo-estate-currency [data-set-cur]' ), function ( btn ) {
			btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-set-cur' ) === cur ? 'true' : 'false' );
		} );
		try {
			window.localStorage.setItem( STORAGE_KEY, cur );
		} catch ( e ) {}
	}

	/* ---------- Tooltips ---------- */

	function initStage( stage ) {
		var tip = stage.querySelector( '.solo-estate-tip' );
		var active = null;
		if ( ! tip ) {
			return;
		}

		function fill( shape ) {
			var tpl = document.getElementById( shape.getAttribute( 'data-tip' ) );
			tip.innerHTML = tpl ? tpl.innerHTML : '';
			// Touch screens have no "pointer leaves" moment, so the tooltip gets a close button.
			var close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'solo-estate-tip__close';
			close.setAttribute( 'aria-label', tip.getAttribute( 'data-close' ) || 'Close' );
			close.innerHTML = '&times;';
			tip.insertBefore( close, tip.firstChild );
			window.clearTimeout( hideTimer );
			window.clearTimeout( hiddenTimer );
			tip.hidden = false;
			active = shape;
			each( stage.querySelectorAll( '.solo-estate-shape.is-active' ), function ( s ) {
				s.classList.remove( 'is-active' );
			} );
			shape.classList.add( 'is-active' );
			// Next frame, so the fade-in transition runs.
			window.requestAnimationFrame( function () {
				tip.classList.add( 'is-visible' );
			} );
		}

		// Above the pointer when it fits in the window, otherwise below; if neither fits (short
		// window), beside the pointer and kept inside the window. The arrow follows the pointer.
		function place( x, y ) {
			var rect = stage.getBoundingClientRect();
			var w = tip.offsetWidth;
			var h = tip.offsetHeight;
			var vy = rect.top + y;
			var above = vy - h - 18 >= 8;
			var below = ! above && vy + h + 22 <= window.innerHeight - 8;
			var side = ! above && ! below;
			var left = Math.max( 4, Math.min( rect.width - w - 4, x - w / 2 ) );
			var top = above ? y - h - 16 : y + 22;
			if ( side ) {
				left = x + w + 24 <= rect.width ? x + 20 : Math.max( 4, x - w - 20 );
				top = Math.max( 8, Math.min( window.innerHeight - h - 8, vy - h / 2 ) ) - rect.top;
			}
			tip.classList.toggle( 'is-below', below );
			tip.classList.toggle( 'is-side', side );
			tip.style.left = left + 'px';
			tip.style.top = top + 'px';
			tip.style.setProperty( '--solo-estate-arrow', Math.max( 14, Math.min( w - 14, x - left ) ) + 'px' );
		}

		var hideTimer = null;
		var hiddenTimer = null;
		// Closed with Esc: stays closed until the pointer has left that polygon.
		var dismissed = null;
		function dismiss() {
			dismissed = active;
			hide();
		}
		function hide() {
			window.clearTimeout( hideTimer );
			tip.classList.remove( 'is-visible' );
			if ( active ) {
				active.classList.remove( 'is-active' );
			}
			active = null;
			// After the fade, out of the accessibility tree and the tab order.
			window.clearTimeout( hiddenTimer );
			hiddenTimer = window.setTimeout( function () {
				if ( ! tip.classList.contains( 'is-visible' ) ) {
					tip.hidden = true;
				}
			}, 200 );
		}
		// Leaving a polygon with the mouse closes its tooltip after a short grace time, so the
		// pointer can move onto the tooltip (hoverable content, WCAG 1.4.13).
		function hideSoon() {
			window.clearTimeout( hideTimer );
			hideTimer = window.setTimeout( hide, 250 );
		}
		tip.addEventListener( 'pointerenter', function ( e ) {
			if ( 'mouse' === e.pointerType ) {
				window.clearTimeout( hideTimer );
			}
		} );
		tip.addEventListener( 'pointerleave', function ( e ) {
			if ( 'mouse' === e.pointerType ) {
				hideSoon();
			}
		} );

		function centerOf( shape ) {
			var box = shape.getBoundingClientRect();
			var rect = stage.getBoundingClientRect();
			return [ box.left - rect.left + box.width / 2, box.top - rect.top + box.height / 2 ];
		}

		each( stage.querySelectorAll( '.solo-estate-shape' ), function ( shape ) {
			shape.addEventListener( 'pointerenter', function ( e ) {
				if ( 'mouse' !== e.pointerType || dismissed === shape ) {
					return;
				}
				fill( shape );
			} );
			shape.addEventListener( 'pointermove', function ( e ) {
				if ( 'mouse' !== e.pointerType || active !== shape ) {
					return;
				}
				var rect = stage.getBoundingClientRect();
				place( e.clientX - rect.left, e.clientY - rect.top );
			} );
			shape.addEventListener( 'pointerleave', function ( e ) {
				if ( 'mouse' === e.pointerType ) {
					if ( dismissed === shape ) {
						dismissed = null;
					}
					hideSoon();
				}
			} );
			// Touch: first tap shows the tooltip, second tap follows the link. Whether this
			// polygon's tooltip was already open is read on pointerdown: the tap also focuses the
			// polygon before the click, and the focus handler already switches the tooltip over.
			var wasOpen = false;
			shape.addEventListener( 'pointerdown', function () {
				wasOpen = active === shape && tip.classList.contains( 'is-visible' );
			} );
			shape.addEventListener( 'click', function ( e ) {
				var open = wasOpen;
				wasOpen = false;
				if ( open && shape.classList.contains( 'is-link' ) && stage.getAttribute( 'data-touch' ) ) {
					return;
				}
				if ( stage.getAttribute( 'data-touch' ) || ! shape.classList.contains( 'is-link' ) ) {
					e.preventDefault();
					fill( shape );
					var c = centerOf( shape );
					place( c[ 0 ], c[ 1 ] );
				}
			} );
			// Keyboard users get the tooltip on focus.
			shape.addEventListener( 'focus', function () {
				fill( shape );
				var c = centerOf( shape );
				place( c[ 0 ], c[ 1 ] );
			} );
			// Leaving the polygon by keyboard closes the tooltip — but not when focus moves into the
			// tooltip itself, and not on touch screens: there, tapping the "Details" button blurs
			// the polygon first, and hiding the tooltip at that moment swallowed the tap.
			shape.addEventListener( 'blur', function ( e ) {
				if ( stage.getAttribute( 'data-touch' ) || ( e.relatedTarget && tip.contains( e.relatedTarget ) ) ) {
					return;
				}
				hide();
			} );
		} );

		// Lists next to or below the image (floor list, apartment table, cards): pointing at a
		// row shows the same tooltip on the image and highlights the matching polygon.
		var app = stage.closest( '.solo-estate-app' ) || stage.parentNode;
		each( app.querySelectorAll( '[data-solo-estate-for]' ), function ( item ) {
			var shape = stage.querySelector( '[data-tip="solo-estate-tip-' + item.getAttribute( 'data-solo-estate-for' ) + '"]' );
			if ( ! shape ) {
				return;
			}
			var show = function () {
				fill( shape );
				var box = shape.getBoundingClientRect();
				var rect = stage.getBoundingClientRect();
				// Above the polygon (or below it near the top), pointing at its middle.
				place( box.left - rect.left + box.width / 2, box.top - rect.top + Math.min( 12, box.height / 2 ) );
			};
			item.addEventListener( 'pointerenter', function ( e ) {
				if ( 'mouse' === e.pointerType ) {
					show();
				}
			} );
			item.addEventListener( 'pointerleave', function ( e ) {
				if ( 'mouse' === e.pointerType && active === shape ) {
					hide();
				}
			} );
			item.addEventListener( 'focusin', show );
			item.addEventListener( 'focusout', function () {
				if ( active === shape ) {
					hide();
				}
			} );
		} );

		tip.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.solo-estate-tip__close' ) ) {
				e.preventDefault();
				hide();
			}
		} );

		stage.addEventListener( 'pointerdown', function ( e ) {
			if ( 'mouse' === e.pointerType ) {
				stage.removeAttribute( 'data-touch' );
			} else {
				stage.setAttribute( 'data-touch', '1' );
			}
		} );
		// Back on the keyboard after a tap (touch laptops, tablets with keyboards): Enter on a
		// polygon must follow its link again, not just show the tooltip.
		stage.addEventListener( 'keydown', function ( e ) {
			stage.removeAttribute( 'data-touch' );
			if ( 'Escape' === e.key && tip.classList.contains( 'is-visible' ) ) {
				e.stopPropagation();
				dismiss();
			}
		} );
		// Esc closes the tooltip wherever the focus is (it is dismissible, WCAG 1.4.13).
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && tip.classList.contains( 'is-visible' ) && ! document.querySelector( '.solo-estate-lightbox' ) ) {
				dismiss();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! stage.contains( e.target ) ) {
				hide();
			}
		} );
	}

	/* ---------- Full screen (image with polygons) ---------- */

	function fullscreenElement() {
		return document.fullscreenElement || document.webkitFullscreenElement || null;
	}

	// Where windows opened from the image go: everything outside the full-screen element is
	// hidden while it is on screen.
	function overlayHost() {
		return fullscreenElement() || document.querySelector( '.solo-estate-stage.is-fullscreen-css' ) || document.body;
	}

	// css: no Fullscreen API (iPhone), the image covers the window instead.
	function setFullscreen( stage, on, css ) {
		var wasCss = stage.classList.contains( 'is-fullscreen-css' );
		stage.classList.toggle( 'is-fullscreen', on );
		stage.classList.toggle( 'is-fullscreen-css', !! ( on && css ) );
		if ( on && css ) {
			document.documentElement.classList.add( 'solo-estate-no-scroll' );
		} else if ( wasCss ) {
			document.documentElement.classList.remove( 'solo-estate-no-scroll' );
		}
		// The image changes size: an open tooltip would point at the old place.
		each( stage.querySelectorAll( '.solo-estate-tip.is-visible' ), function ( tip ) {
			tip.classList.remove( 'is-visible' );
		} );
		each( stage.querySelectorAll( '.solo-estate-shape.is-active' ), function ( s ) {
			s.classList.remove( 'is-active' );
		} );
	}

	// A window opened from the covering image keeps the page locked when it closes.
	function releaseScroll() {
		if ( ! document.querySelector( '.solo-estate-stage.is-fullscreen-css' ) ) {
			document.documentElement.classList.remove( 'solo-estate-no-scroll' );
		}
	}

	function toggleFullscreen( stage ) {
		if ( stage.classList.contains( 'is-fullscreen' ) ) {
			if ( fullscreenElement() === stage ) {
				( document.exitFullscreen || document.webkitExitFullscreen ).call( document );
			} else {
				setFullscreen( stage, false );
			}
			return;
		}
		var request = stage.requestFullscreen || stage.webkitRequestFullscreen;
		var result = request ? request.call( stage ) : null;
		if ( ! request ) {
			setFullscreen( stage, true, true );
		} else if ( result && result.then ) {
			result.then( function () {
				// A wide image on a phone held upright: turn the screen (Android; elsewhere the
				// visitor turns the phone and the image fits again).
				var ratio = parseFloat( stage.style.getPropertyValue( '--solo-estate-ratio' ) ) || 0;
				if ( ratio > 1.2 && window.innerHeight > window.innerWidth && window.screen.orientation && window.screen.orientation.lock ) {
					window.screen.orientation.lock( 'landscape' ).catch( function () {} );
				}
			}, function () {
				setFullscreen( stage, true, true );
			} );
		}
	}

	function onFullscreenChange() {
		var current = fullscreenElement();
		each( document.querySelectorAll( '.solo-estate-stage.is-fullscreen:not(.is-fullscreen-css)' ), function ( stage ) {
			if ( stage !== current ) {
				setFullscreen( stage, false );
			}
		} );
		if ( current && current.classList.contains( 'solo-estate-stage' ) ) {
			setFullscreen( current, true );
		} else if ( ! current && window.screen.orientation && window.screen.orientation.unlock ) {
			try {
				window.screen.orientation.unlock();
			} catch ( err ) {
				// Not locked.
			}
		}
	}

	/* ---------- Dialogs (photos, tour, info window) ---------- */

	function t( key, fallback ) {
		return ( config.i18n && config.i18n[ key ] ) || fallback;
	}

	// Makes an overlay a proper modal: the rest of the page is inert (not focusable, hidden
	// from screen readers), Tab stays inside, and closing gives the focus back to the opener.
	// Returns the function that undoes it.
	function trapDialog( overlay ) {
		var made = [];
		var node = overlay;
		while ( node && node.parentNode && node !== document.body ) {
			each( node.parentNode.children, function ( sibling ) {
				if ( sibling !== node && ! sibling.inert && 'SCRIPT' !== sibling.tagName && 'TEMPLATE' !== sibling.tagName ) {
					sibling.inert = true;
					made.push( sibling );
				}
			} );
			node = node.parentNode;
		}
		function onTab( e ) {
			// A dialog opened on top of this one (a photo from the info window) handles Tab.
			if ( 'Tab' !== e.key || overlay.closest( '[inert]' ) ) {
				return;
			}
			var items = Array.prototype.filter.call( overlay.querySelectorAll( 'a[href], button, iframe, input, select, textarea, [tabindex]:not([tabindex="-1"])' ), function ( el ) {
				return ! el.disabled && el.offsetParent !== null;
			} );
			if ( ! items.length ) {
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( e.shiftKey && ( document.activeElement === first || ! overlay.contains( document.activeElement ) ) ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && ( document.activeElement === last || ! overlay.contains( document.activeElement ) ) ) {
				e.preventDefault();
				first.focus();
			}
		}
		overlay.addEventListener( 'keydown', onTab );
		document.addEventListener( 'keydown', onTab );
		return function () {
			each( made, function ( el ) {
				el.inert = false;
			} );
			overlay.removeEventListener( 'keydown', onTab );
			document.removeEventListener( 'keydown', onTab );
		};
	}

	/* ---------- Lightbox ---------- */

	// Images of the same group (data-solo-estate-lightbox="group") can be browsed with the
	// arrows, the keyboard or a swipe.
	function openLightbox( link ) {
		var group = link.getAttribute( 'data-solo-estate-lightbox' );
		var items = group ? Array.prototype.slice.call( document.querySelectorAll( '[data-solo-estate-lightbox="' + group + '"]' ) ) : [ link ];
		var index = Math.max( 0, items.indexOf( link ) );

		var overlay = document.createElement( 'div' );
		overlay.className = 'solo-estate-lightbox';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', t( 'photos', 'Photos' ) );
		overlay.innerHTML = '<button type="button" class="solo-estate-lightbox__close">×</button>';
		overlay.firstChild.setAttribute( 'aria-label', t( 'close', 'Close' ) );
		var img = document.createElement( 'img' );
		overlay.appendChild( img );
		if ( items.length > 1 ) {
			overlay.insertAdjacentHTML( 'beforeend', '<button type="button" class="solo-estate-lightbox__nav is-prev">‹</button><button type="button" class="solo-estate-lightbox__nav is-next">›</button><span class="solo-estate-lightbox__count" aria-live="polite"></span>' );
			overlay.querySelector( '.is-prev' ).setAttribute( 'aria-label', t( 'previous', 'Previous' ) );
			overlay.querySelector( '.is-next' ).setAttribute( 'aria-label', t( 'next', 'Next' ) );
		}
		overlayHost().appendChild( overlay );
		document.documentElement.classList.add( 'solo-estate-no-scroll' );
		var release = trapDialog( overlay );

		function showItem( i ) {
			index = ( i + items.length ) % items.length;
			var thumb = items[ index ].querySelector( 'img' );
			img.src = items[ index ].getAttribute( 'href' );
			img.alt = ( thumb && thumb.alt ) || items[ index ].getAttribute( 'aria-label' ) || '';
			var count = overlay.querySelector( '.solo-estate-lightbox__count' );
			if ( count ) {
				count.textContent = ( index + 1 ) + ' / ' + items.length;
			}
		}
		function close() {
			release();
			overlay.remove();
			releaseScroll();
			document.removeEventListener( 'keydown', onKey );
			link.focus();
		}
		function onKey( e ) {
			if ( 'Escape' === e.key ) {
				e.stopPropagation();
				close();
			} else if ( 'ArrowLeft' === e.key && items.length > 1 ) {
				showItem( index - 1 );
			} else if ( 'ArrowRight' === e.key && items.length > 1 ) {
				showItem( index + 1 );
			}
		}
		overlay.addEventListener( 'click', function ( e ) {
			var nav = e.target.closest( '.solo-estate-lightbox__nav' );
			if ( nav ) {
				showItem( index + ( nav.classList.contains( 'is-prev' ) ? -1 : 1 ) );
			} else if ( e.target !== img ) {
				close();
			}
		} );
		var startX = null;
		overlay.addEventListener( 'touchstart', function ( e ) {
			startX = e.touches[ 0 ].clientX;
		}, { passive: true } );
		overlay.addEventListener( 'touchend', function ( e ) {
			if ( null !== startX && items.length > 1 ) {
				var dx = e.changedTouches[ 0 ].clientX - startX;
				if ( Math.abs( dx ) > 40 ) {
					showItem( index + ( dx < 0 ? 1 : -1 ) );
				}
			}
			startX = null;
		} );
		document.addEventListener( 'keydown', onKey );
		showItem( index );
		overlay.querySelector( '.solo-estate-lightbox__close' ).focus();
	}

	/* ---------- Virtual tour ---------- */

	// Loaded only when asked for: tours are heavy and set their own cookies.
	function openTour( button ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'solo-estate-lightbox solo-estate-lightbox--tour';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', button.getAttribute( 'data-title' ) || t( 'tour', 'Virtual tour' ) );
		overlay.innerHTML = '<button type="button" class="solo-estate-lightbox__close">×</button>';
		overlay.firstChild.setAttribute( 'aria-label', t( 'close', 'Close' ) );
		var frame = document.createElement( 'iframe' );
		frame.src = button.getAttribute( 'data-solo-estate-tour' );
		frame.title = button.getAttribute( 'data-title' ) || '';
		frame.setAttribute( 'allow', 'fullscreen; xr-spatial-tracking; gyroscope; accelerometer' );
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		overlay.appendChild( frame );
		document.body.appendChild( overlay );
		document.documentElement.classList.add( 'solo-estate-no-scroll' );
		// Keys typed inside the tour (another site) never reach this page, so Esc cannot work
		// there: the close button stays first in the dialog and Tab cycles back to it.
		var release = trapDialog( overlay );

		function close() {
			release();
			overlay.remove();
			releaseScroll();
			document.removeEventListener( 'keydown', onKey );
			button.focus();
		}
		function onKey( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
		}
		overlay.querySelector( 'button' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', onKey );
		overlay.querySelector( 'button' ).focus();
	}

	/* ---------- Info window (boulevard, sports field…) ---------- */

	// Items without a page of their own link to "#solo-estate-info-{id}"; the matching
	// <template> holds their photos and description.
	function openInfo( link ) {
		var tpl = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
		var app = link.closest( '.solo-estate-app' );
		if ( ! tpl ) {
			return false;
		}
		var overlay = document.createElement( 'div' );
		overlay.className = 'solo-estate-lightbox solo-estate-info';
		// Inside an app-classed panel so the style settings (colours, font) apply.
		var panel = document.createElement( 'div' );
		panel.className = 'solo-estate-app solo-estate-info__panel';
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-modal', 'true' );
		panel.innerHTML = tpl.innerHTML;
		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'solo-estate-info__close';
		close.setAttribute( 'aria-label', t( 'close', ( app && app.querySelector( '.solo-estate-tip' ) && app.querySelector( '.solo-estate-tip' ).getAttribute( 'data-close' ) ) || 'Close' ) );
		close.innerHTML = '&times;';
		panel.insertBefore( close, panel.firstChild );
		var title = panel.querySelector( '.solo-estate-info__title' );
		if ( title ) {
			title.id = 'solo-estate-info-title';
			panel.setAttribute( 'aria-labelledby', title.id );
		}
		overlay.appendChild( panel );
		overlayHost().appendChild( overlay );
		document.documentElement.classList.add( 'solo-estate-no-scroll' );
		var release = trapDialog( overlay );

		function shut() {
			release();
			overlay.remove();
			releaseScroll();
			document.removeEventListener( 'keydown', onKey );
			if ( link.focus ) {
				link.focus( { preventScroll: true } );
			}
		}
		function onKey( e ) {
			// Esc closes a photo opened from the window first.
			if ( 'Escape' === e.key && ! document.querySelector( '.solo-estate-lightbox:not(.solo-estate-info)' ) ) {
				shut();
			}
		}
		close.addEventListener( 'click', shut );
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				shut();
			}
		} );
		document.addEventListener( 'keydown', onKey );
		close.focus();
		return true;
	}

	/* ---------- Tabs (plans / photos, villa floors) ---------- */

	function initSwitch( root ) {
		var tabs = root.querySelector( '.solo-estate-switch__tabs' );
		if ( ! tabs ) {
			return;
		}
		tabs.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-switch-to]' );
			if ( ! btn ) {
				return;
			}
			var key = btn.getAttribute( 'data-switch-to' );
			each( tabs.querySelectorAll( '[data-switch-to]' ), function ( b ) {
				b.classList.toggle( 'is-active', b === btn );
				b.setAttribute( 'aria-pressed', b === btn ? 'true' : 'false' );
			} );
			each( root.querySelectorAll( '[data-switch-pane]' ), function ( pane ) {
				// Only this switch's own panes, not those of a nested one.
				if ( pane.closest( '[data-solo-estate-switch]' ) === root ) {
					pane.hidden = pane.getAttribute( 'data-switch-pane' ) !== key;
				}
			} );
		} );
	}

	/* ---------- Lead form ---------- */

	function initLead( form ) {
		var message = form.querySelector( '.solo-estate-lead__message' );
		var button = form.querySelector( 'button[type="submit"]' );
		var sending = false;
		// The script validates and shows its own messages; without it the browser's checks apply.
		form.noValidate = true;

		// Marks a field (in)valid: aria-invalid, its own error text, and the red frame.
		function mark( input, bad ) {
			input.classList.toggle( 'is-invalid', bad );
			input.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
			var error = document.getElementById( input.getAttribute( 'aria-describedby' ) || '' );
			if ( error ) {
				error.hidden = ! bad;
			}
		}
		function invalidFields() {
			var bad = [];
			each( form.querySelectorAll( 'input[required]' ), function ( input ) {
				var value = input.value.trim();
				var wrong = '' === value || ( 'phone' === input.name && value.replace( /\D/g, '' ).length < 6 );
				mark( input, wrong );
				if ( wrong ) {
					bad.push( input );
				}
			} );
			return bad;
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( sending ) {
				return;
			}
			var bad = invalidFields();
			if ( bad.length ) {
				show( config.i18n && config.i18n.error, true );
				bad[ 0 ].focus();
				return;
			}

			var data = new FormData( form );
			data.set( 'action', 'solo_estate_lead' );
			data.set( 'lang', config.lang || data.get( 'lang' ) || '' );
			data.set( 'page_url', window.location.href );
			// Where the visitor came from, remembered by the small script on every page.
			if ( config.attribution ) {
				try {
					var source = window.localStorage.getItem( 'solo_estate_attribution' );
					if ( source ) {
						data.set( 'attribution', source );
					}
				} catch ( err ) {}
			}

			// Not "disabled": a disabled button loses the keyboard focus.
			sending = true;
			button.setAttribute( 'aria-disabled', 'true' );
			form.classList.add( 'is-loading' );
			form.setAttribute( 'aria-busy', 'true' );

			fetch( config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json().catch( function () {
						return { success: false, data: {} };
					} );
				} )
				.then( function ( json ) {
					var payload = json.data || {};
					if ( json.success ) {
						form.reset();
						form.classList.add( 'is-sent' );
						show( payload.message || ( config.i18n && config.i18n.success ), false );
						// The fields are gone: the focus goes to the message instead of the page top.
						message.focus();
						track( form );
					} else {
						var first = null;
						( payload.fields || [] ).forEach( function ( name ) {
							var input = form.querySelector( '[name="' + name + '"]' );
							if ( input ) {
								mark( input, true );
								first = first || input;
							}
						} );
						show( payload.message || ( config.i18n && config.i18n.error ), true );
						if ( first ) {
							first.focus();
						}
					}
				} )
				.catch( function () {
					show( config.i18n && config.i18n.error, true );
				} )
				.then( function () {
					sending = false;
					button.removeAttribute( 'aria-disabled' );
					form.classList.remove( 'is-loading' );
					form.removeAttribute( 'aria-busy' );
				} );
		} );

		form.addEventListener( 'input', function ( e ) {
			if ( e.target.classList.contains( 'is-invalid' ) ) {
				mark( e.target, false );
			}
		} );

		// Errors are announced assertively; the text is set a moment after the role, so screen
		// readers notice the change.
		function show( text, isError ) {
			message.setAttribute( 'role', isError ? 'alert' : 'status' );
			message.classList.toggle( 'is-error', !! isError );
			message.textContent = '';
			window.setTimeout( function () {
				message.textContent = text || '';
			}, 50 );
		}
	}

	function track( form ) {
		// The apartment the request is about, from the form (price only where prices are shown).
		var item = {
			id: form.getAttribute( 'data-item-id' ) || ( form.querySelector( '[name="node_id"]' ) || {} ).value || '',
			name: form.getAttribute( 'data-item-name' ) || '',
			category: form.getAttribute( 'data-item-category' ) || '',
			value: parseFloat( form.getAttribute( 'data-value' ) ) || 0,
			currency: form.getAttribute( 'data-currency' ) || ''
		};
		var detail = { nodeId: item.id, name: item.name, project: item.category, value: item.value, currency: item.currency };
		document.dispatchEvent( new CustomEvent( 'solo-estate:lead', { detail: detail } ) );
		if ( ! config.tracking ) {
			return;
		}
		var params = { event_category: 'solo-estate' };
		if ( item.id && '0' !== item.id ) {
			params.items = [ { item_id: String( item.id ), item_name: item.name, item_category: item.category, price: item.value || undefined, quantity: 1 } ];
		}
		if ( item.value && item.currency ) {
			params.value = item.value;
			params.currency = item.currency;
		}
		// gtag.js, or else Google Tag Manager's dataLayer (never both: the lead would count twice).
		if ( 'function' === typeof window.gtag ) {
			window.gtag( 'event', 'generate_lead', params );
		} else if ( window.dataLayer && 'function' === typeof window.dataLayer.push ) {
			window.dataLayer.push( { ecommerce: null } );
			window.dataLayer.push( { event: 'generate_lead', ecommerce: { value: params.value, currency: params.currency, items: params.items }, solo_estate: detail } );
		}
		if ( 'function' === typeof window.fbq ) {
			var meta = { content_name: item.name, content_category: item.category };
			if ( item.id && '0' !== item.id ) {
				meta.content_ids = [ String( item.id ) ];
				meta.content_type = 'product';
			}
			if ( item.value && item.currency ) {
				meta.value = item.value;
				meta.currency = item.currency;
			}
			window.fbq( 'track', 'Lead', meta );
		}
	}

	/* ---------- Boot ---------- */

	function init() {
		var cur = storedCurrency();
		if ( 'base' === cur || 'alt' === cur ) {
			setCurrency( cur );
		}

		each( document.querySelectorAll( '.solo-estate-stage' ), initStage );
		// Breadcrumbs that scroll sideways on phones start at their end (the current page).
		each( document.querySelectorAll( '.solo-estate-crumbs' ), function ( nav ) {
			nav.scrollLeft = nav.scrollWidth;
		} );
		each( document.querySelectorAll( '[data-solo-estate-lead]' ), initLead );
		each( document.querySelectorAll( '[data-solo-estate-switch]' ), initSwitch );

		document.addEventListener( 'fullscreenchange', onFullscreenChange );
		document.addEventListener( 'webkitfullscreenchange', onFullscreenChange );
		// Esc leaves the covering image (real full screen handles Esc itself); a photo opened
		// from it closes first.
		document.addEventListener( 'keydown', function ( e ) {
			var stage = document.querySelector( '.solo-estate-stage.is-fullscreen-css' );
			if ( 'Escape' === e.key && stage && ! stage.querySelector( '.solo-estate-lightbox' ) ) {
				setFullscreen( stage, false );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			var fsButton = e.target.closest && e.target.closest( '[data-solo-estate-fullscreen]' );
			if ( fsButton ) {
				toggleFullscreen( fsButton.closest( '.solo-estate-stage' ) );
				return;
			}
			var curBtn = e.target.closest && e.target.closest( '[data-set-cur]' );
			if ( curBtn ) {
				setCurrency( curBtn.getAttribute( 'data-set-cur' ) );
				return;
			}
			// A first tap on a polygon (touch) only shows its tooltip and has prevented the click.
			var info = ! e.defaultPrevented && e.target.closest && e.target.closest( 'a[href^="#solo-estate-info-"]' );
			if ( info && openInfo( info ) ) {
				e.preventDefault();
				return;
			}
			var row = e.target.closest && e.target.closest( 'tr[data-href]' );
			if ( row && ! e.target.closest( 'a' ) ) {
				window.location.href = row.getAttribute( 'data-href' );
				return;
			}
			var lb = e.target.closest && e.target.closest( '[data-solo-estate-lightbox]' );
			if ( lb ) {
				e.preventDefault();
				openLightbox( lb );
				return;
			}
			var tour = e.target.closest && e.target.closest( '[data-solo-estate-tour]' );
			if ( tour ) {
				openTour( tour );
				return;
			}
			// "Request a call" on the unit page: glide to the form and put the cursor in it.
			var jump = e.target.closest && e.target.closest( '[data-solo-estate-scroll]' );
			if ( jump ) {
				var target = document.getElementById( jump.getAttribute( 'href' ).slice( 1 ) );
				if ( target ) {
					e.preventDefault();
					target.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					var input = target.querySelector( 'input[name="name"]' );
					if ( input ) {
						window.setTimeout( function () {
							input.focus( { preventScroll: true } );
						}, 400 );
					}
				}
			}
		} );

		// Project status tabs on the catalog (All / Ongoing / Completed…).
		each( document.querySelectorAll( '[data-solo-estate-tabs]' ), function ( tabs ) {
			var list = tabs.parentNode.querySelector( '.solo-estate-projects' );
			tabs.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( 'button[data-tab]' );
				if ( ! btn || ! list ) {
					return;
				}
				var tab = btn.getAttribute( 'data-tab' );
				each( tabs.querySelectorAll( 'button' ), function ( b ) {
					b.classList.toggle( 'is-active', b === btn );
					b.setAttribute( 'aria-pressed', b === btn ? 'true' : 'false' );
				} );
				each( list.children, function ( item ) {
					var status = item.getAttribute( 'data-status' );
					// Projects without a status show under every tab.
					item.hidden = 'all' !== tab && '0' !== status && status !== tab;
				} );
			} );
		} );

		// Filter: drop empty fields so result URLs stay short and shareable.
		each( document.querySelectorAll( '[data-solo-estate-filter]' ), function ( form ) {
			form.addEventListener( 'submit', function () {
				each( form.elements, function ( field ) {
					if ( field.name && 'se_q' !== field.name && ( 'text' === field.type || 'SELECT' === field.tagName ) && '' === field.value ) {
						field.disabled = true;
					}
					if ( 'radio' === field.type && field.checked && '' === field.value ) {
						field.disabled = true;
					}
					if ( 'se_sort' === field.name && 'default' === field.value ) {
						field.disabled = true;
					}
				} );
			} );
		} );

		// Filter: only offer buildings of the chosen project.
		each( document.querySelectorAll( '[data-solo-estate-project-select]' ), function ( select ) {
			var form = select.form;
			var buildings = form && form.querySelector( '[data-solo-estate-building-select]' );
			if ( ! buildings ) {
				return;
			}
			var sync = function () {
				each( buildings.options, function ( opt ) {
					var p = opt.getAttribute( 'data-project' );
					opt.hidden = !! ( p && select.value && p !== select.value );
				} );
				if ( buildings.selectedOptions[ 0 ] && buildings.selectedOptions[ 0 ].hidden ) {
					buildings.value = '';
				}
			};
			select.addEventListener( 'change', sync );
			sync();
		} );

		// Pages behind a select: a choice made with the mouse or a touch picker opens at once,
		// but arrow keys on a closed select (which change it step by step in some browsers)
		// only move the choice; Enter or the "Show" button then opens it.
		each( document.querySelectorAll( '[data-solo-estate-nav]' ), function ( select ) {
			var start = select.value;
			var keyed = false;
			var go = select.parentNode.querySelector( '[data-solo-estate-go]' );
			var open = function () {
				if ( select.value && select.value !== start ) {
					window.location.href = select.value;
				}
			};
			var isOpen = function () {
				try {
					return select.matches( ':open' );
				} catch ( err ) {
					return false;
				}
			};
			select.addEventListener( 'keydown', function ( e ) {
				// Keys inside an open list (a styled select moves focus to its options) choose as
				// usual, and the choice opens.
				if ( e.target !== select || isOpen() ) {
					keyed = false;
					return;
				}
				if ( 'Enter' === e.key ) {
					e.preventDefault();
					open();
				} else if ( 'Tab' !== e.key && 'Escape' !== e.key && 'Shift' !== e.key ) {
					keyed = true;
				}
			} );
			select.addEventListener( 'pointerdown', function () {
				keyed = false;
			} );
			select.addEventListener( 'change', function () {
				if ( ! keyed ) {
					open();
					return;
				}
				if ( go ) {
					go.hidden = select.value === start;
				}
			} );
			if ( go ) {
				go.addEventListener( 'click', open );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
