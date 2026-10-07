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

		function hide() {
			tip.classList.remove( 'is-visible' );
			if ( active ) {
				active.classList.remove( 'is-active' );
			}
			active = null;
		}

		function centerOf( shape ) {
			var box = shape.getBoundingClientRect();
			var rect = stage.getBoundingClientRect();
			return [ box.left - rect.left + box.width / 2, box.top - rect.top + box.height / 2 ];
		}

		each( stage.querySelectorAll( '.solo-estate-shape' ), function ( shape ) {
			shape.addEventListener( 'pointerenter', function ( e ) {
				if ( 'mouse' !== e.pointerType ) {
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
					hide();
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
		overlay.innerHTML = '<button type="button" class="solo-estate-lightbox__close" aria-label="Close">×</button>';
		var img = document.createElement( 'img' );
		overlay.appendChild( img );
		if ( items.length > 1 ) {
			overlay.insertAdjacentHTML( 'beforeend', '<button type="button" class="solo-estate-lightbox__nav is-prev" aria-label="Previous">‹</button><button type="button" class="solo-estate-lightbox__nav is-next" aria-label="Next">›</button><span class="solo-estate-lightbox__count"></span>' );
		}
		overlayHost().appendChild( overlay );
		document.documentElement.classList.add( 'solo-estate-no-scroll' );

		function showItem( i ) {
			index = ( i + items.length ) % items.length;
			var thumb = items[ index ].querySelector( 'img' );
			img.src = items[ index ].getAttribute( 'href' );
			img.alt = thumb ? thumb.alt : '';
			var count = overlay.querySelector( '.solo-estate-lightbox__count' );
			if ( count ) {
				count.textContent = ( index + 1 ) + ' / ' + items.length;
			}
		}
		function close() {
			overlay.remove();
			releaseScroll();
			document.removeEventListener( 'keydown', onKey );
			link.focus();
		}
		function onKey( e ) {
			if ( 'Escape' === e.key ) {
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
		overlay.innerHTML = '<button type="button" class="solo-estate-lightbox__close" aria-label="Close">×</button>';
		var frame = document.createElement( 'iframe' );
		frame.src = button.getAttribute( 'data-solo-estate-tour' );
		frame.title = button.getAttribute( 'data-title' ) || '';
		frame.setAttribute( 'allow', 'fullscreen; xr-spatial-tracking; gyroscope; accelerometer' );
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		overlay.appendChild( frame );
		document.body.appendChild( overlay );
		document.documentElement.classList.add( 'solo-estate-no-scroll' );

		function close() {
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
		close.setAttribute( 'aria-label', ( app && app.querySelector( '.solo-estate-tip' ) && app.querySelector( '.solo-estate-tip' ).getAttribute( 'data-close' ) ) || 'Close' );
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

		function shut() {
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
				b.setAttribute( 'aria-selected', b === btn ? 'true' : 'false' );
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

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var invalid = false;
			each( form.querySelectorAll( 'input[required]' ), function ( input ) {
				var bad = '' === input.value.trim();
				input.classList.toggle( 'is-invalid', bad );
				invalid = invalid || bad;
			} );
			if ( invalid ) {
				show( config.i18n && config.i18n.error, true );
				return;
			}

			var data = new FormData( form );
			data.append( 'action', 'solo_estate_lead' );
			data.append( 'lang', config.lang || '' );
			data.append( 'page_url', window.location.href );

			button.disabled = true;
			form.classList.add( 'is-loading' );

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
						track( form );
					} else {
						( payload.fields || [] ).forEach( function ( name ) {
							var input = form.querySelector( '[name="' + name + '"]' );
							if ( input ) {
								input.classList.add( 'is-invalid' );
							}
						} );
						show( payload.message || ( config.i18n && config.i18n.error ), true );
					}
				} )
				.catch( function () {
					show( config.i18n && config.i18n.error, true );
				} )
				.then( function () {
					button.disabled = false;
					form.classList.remove( 'is-loading' );
				} );
		} );

		form.addEventListener( 'input', function ( e ) {
			e.target.classList.remove( 'is-invalid' );
		} );

		function show( text, isError ) {
			message.textContent = text || '';
			message.hidden = ! text;
			message.classList.toggle( 'is-error', !! isError );
		}
	}

	function track( form ) {
		var detail = { nodeId: ( form.querySelector( '[name="node_id"]' ) || {} ).value };
		document.dispatchEvent( new CustomEvent( 'solo-estate:lead', { detail: detail } ) );
		if ( ! config.tracking ) {
			return;
		}
		if ( 'function' === typeof window.gtag ) {
			window.gtag( 'event', 'generate_lead', { event_category: 'solo-estate' } );
		}
		if ( 'function' === typeof window.fbq ) {
			window.fbq( 'track', 'Lead' );
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

		each( document.querySelectorAll( '[data-solo-estate-nav]' ), function ( select ) {
			select.addEventListener( 'change', function () {
				if ( select.value ) {
					window.location.href = select.value;
				}
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
