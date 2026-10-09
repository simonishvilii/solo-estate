/**
 * Solo Estate admin screens: language tabs, media pickers, colour pickers, confirmations, sorting.
 */
( function ( $ ) {
	'use strict';

	var t = ( window.soloEstateAdmin && soloEstateAdmin.i18n ) || {};

	// Language tabs inside translatable fields.
	$( document ).on( 'click', '.solo-estate-i18n__tab', function () {
		var $tab = $( this );
		var $root = $tab.closest( '[data-solo-estate-i18n]' );
		var lang = $tab.data( 'lang' );
		$root.find( '.solo-estate-i18n__tab' ).removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		$tab.addClass( 'is-active' ).attr( 'aria-selected', 'true' );
		$root.find( '.solo-estate-i18n__pane' ).removeClass( 'is-active' ).filter( '[data-lang="' + lang + '"]' ).addClass( 'is-active' );
	} );

	// Keep the "filled" marker on tabs up to date.
	$( document ).on( 'input', '.solo-estate-i18n__pane input, .solo-estate-i18n__pane textarea', function () {
		var $pane = $( this ).closest( '.solo-estate-i18n__pane' );
		$pane.closest( '[data-solo-estate-i18n]' ).find( '.solo-estate-i18n__tab[data-lang="' + $pane.data( 'lang' ) + '"]' ).toggleClass( 'is-filled', '' !== $.trim( this.value ) );
	} );

	// Settings tabs.
	$( document ).on( 'click', '[data-solo-estate-tabs] .nav-tab', function ( e ) {
		e.preventDefault();
		var tab = $( this ).data( 'tab' );
		$( this ).addClass( 'nav-tab-active' ).siblings().removeClass( 'nav-tab-active' );
		$( '.solo-estate-tab-panel' ).removeClass( 'is-active' ).filter( '[data-panel="' + tab + '"]' ).addClass( 'is-active' );
		// Localization saves with its own button.
		$( '.solo-estate-settings-submit' ).prop( 'hidden', 'localization' === tab );
		if ( window.history && history.replaceState ) {
			history.replaceState( null, '', '#' + tab );
		}
	} );
	$( function () {
		var hash = window.location.hash.replace( '#', '' );
		if ( hash ) {
			$( '[data-solo-estate-tabs] .nav-tab[data-tab="' + hash + '"]' ).trigger( 'click' );
		}
	} );

	// Media picker.
	$( document ).on( 'click', '[data-solo-estate-image-pick]', function ( e ) {
		e.preventDefault();
		var $root = $( this ).closest( '[data-solo-estate-image]' );
		var frame = wp.media( {
			title: t.chooseImage || 'Choose image',
			button: { text: t.useImage || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();
			var url = ( file.sizes && file.sizes.medium ) ? file.sizes.medium.url : file.url;
			$root.find( '[data-solo-estate-image-id]' ).val( file.id );
			$root.find( '.solo-estate-image-field__preview' ).prop( 'hidden', false ).find( 'img' ).attr( 'src', url );
			$root.find( '[data-solo-estate-image-remove]' ).prop( 'hidden', false );
		} );
		frame.open();
	} );
	$( document ).on( 'click', '[data-solo-estate-image-remove]', function ( e ) {
		e.preventDefault();
		var $root = $( this ).closest( '[data-solo-estate-image]' );
		$root.find( '[data-solo-estate-image-id]' ).val( '' );
		$root.find( '.solo-estate-image-field__preview' ).prop( 'hidden', true );
		$( this ).prop( 'hidden', true );
	} );

	// Gallery: pick several images, drag to reorder, × to remove.
	function syncGallery( $root ) {
		var ids = $root.find( '.solo-estate-gallery-field__list li' ).map( function () {
			return $( this ).data( 'id' );
		} ).get();
		$root.find( '[data-solo-estate-gallery-ids]' ).val( ids.join( ',' ) );
	}
	$( document ).on( 'click', '[data-solo-estate-gallery-add]', function ( e ) {
		e.preventDefault();
		var $root = $( this ).closest( '[data-solo-estate-gallery]' );
		var frame = wp.media( {
			title: t.chooseImages || 'Choose images',
			button: { text: t.addImages || 'Add to gallery' },
			library: { type: 'image' },
			multiple: 'add'
		} );
		frame.on( 'select', function () {
			var $list = $root.find( '.solo-estate-gallery-field__list' );
			frame.state().get( 'selection' ).each( function ( item ) {
				var file = item.toJSON();
				if ( $list.find( 'li[data-id="' + file.id + '"]' ).length ) {
					return;
				}
				var url = ( file.sizes && file.sizes.thumbnail ) ? file.sizes.thumbnail.url : file.url;
				var $li = $( '<li>' ).attr( 'data-id', file.id );
				$( '<img alt="">' ).attr( 'src', url ).attr( 'alt', file.title || '' ).appendTo( $li );
				$( '<button type="button" class="solo-estate-gallery-field__remove">×</button>' ).attr( 'aria-label', t.remove || 'Remove' ).appendTo( $li );
				$( '<button type="button" class="solo-estate-gallery-field__move solo-estate-gallery-field__move--prev" data-solo-estate-move="-1">‹</button>' ).attr( 'aria-label', t.moveEarlier || 'Move earlier' ).appendTo( $li );
				$( '<button type="button" class="solo-estate-gallery-field__move solo-estate-gallery-field__move--next" data-solo-estate-move="1">›</button>' ).attr( 'aria-label', t.moveLater || 'Move later' ).appendTo( $li );
				$list.append( $li );
			} );
			syncGallery( $root );
		} );
		frame.open();
	} );
	$( document ).on( 'click', '.solo-estate-gallery-field__remove', function () {
		var $root = $( this ).closest( '[data-solo-estate-gallery]' );
		$( this ).closest( 'li' ).remove();
		syncGallery( $root );
	} );

	// Reordering without dragging: the arrow buttons of gallery images and specification
	// fields move their item one place; the focus stays on the button and the new position is
	// announced.
	$( document ).on( 'click', '[data-solo-estate-move]', function () {
		var $btn = $( this );
		var $item = $btn.closest( 'li, tr' );
		var $items = $item.parent().children( $item.is( 'tr' ) ? 'tr:not(.solo-estate-new-row)' : 'li' );
		var index = $items.index( $item );
		var to = index + Number( $btn.data( 'solo-estate-move' ) );
		if ( to < 0 || to >= $items.length ) {
			return;
		}
		if ( to < index ) {
			$item.insertBefore( $items.eq( to ) );
		} else {
			$item.insertAfter( $items.eq( to ) );
		}
		if ( $item.is( 'tr' ) ) {
			$item.parent().children( 'tr:not(.solo-estate-new-row)' ).each( function ( i ) {
				$( this ).find( '[data-solo-estate-sort]' ).val( i + 1 );
			} );
		} else {
			syncGallery( $item.closest( '[data-solo-estate-gallery]' ) );
		}
		$btn.trigger( 'focus' );
		if ( window.wp && wp.a11y && t.moved ) {
			wp.a11y.speak( t.moved.replace( '%1$d', to + 1 ).replace( '%2$d', $items.length ) );
		}
	} );

	// New rooms / sections typed right on the unit.
	var specRow = 0;
	$( document ).on( 'click', '[data-solo-estate-add-spec]', function () {
		var $root = $( this ).closest( '[data-solo-estate-new-specs]' );
		var html = $root.find( 'template[data-solo-estate-new-spec-template]' ).html().replace( /__i__/g, String( specRow++ ) );
		var $row = $( html ).appendTo( $root.find( '[data-solo-estate-new-specs-rows]' ) );
		$row.find( 'input' ).first().trigger( 'focus' );
	} );
	$( document ).on( 'click', '[data-solo-estate-remove-row]', function () {
		$( this ).closest( 'tr' ).remove();
	} );

	// Total price preview: total area × price per m², while the total price is empty.
	// Total price = total area × price per m², written into the field while either changes.
	// A total typed by hand is kept; clearing it switches back to the calculation.
	function updateCalc( e ) {
		var $form = $( '.solo-estate-node-form' );
		var $total = $form.find( '[data-solo-estate-calc="total"]' );
		if ( ! $total.length ) {
			return;
		}
		var num = function ( name ) {
			return parseFloat( String( $form.find( '[data-solo-estate-calc="' + name + '"]' ).val() || '' ).replace( ',', '.' ) );
		};
		var area = num( 'area' );
		var sqm = num( 'sqm' );
		var total = num( 'total' );
		var calc = area > 0 && sqm > 0 ? Math.round( area * sqm * 100 ) / 100 : null;
		if ( ! e ) {
			// On load: a stored total that differs from the calculation was typed by hand.
			$total.data( 'manual', ! isNaN( total ) && total > 0 && ( null === calc || Math.abs( total - calc ) > 0.01 ) );
		} else if ( e.target === $total[ 0 ] ) {
			$total.data( 'manual', ! isNaN( total ) && total > 0 );
			return;
		}
		if ( ! $total.data( 'manual' ) && null !== calc ) {
			$total.val( calc );
		}
	}
	$( document ).on( 'input', '[data-solo-estate-calc]', updateCalc );
	$( function () {
		updateCalc();
	} );

	// Confirm destructive links and bulk deletes.
	// data-solo-estate-confirm="trash": the item goes to the trash (can be restored).
	$( document ).on( 'click', '[data-solo-estate-confirm]', function ( e ) {
		var message = 'trash' === $( this ).attr( 'data-solo-estate-confirm' ) ? t.confirmTrash : t.confirmDelete;
		if ( ! window.confirm( message || 'Delete?' ) ) {
			e.preventDefault();
		}
	} );
	$( document ).on( 'click', '[data-solo-estate-bulk-confirm]', function ( e ) {
		var value = $( this ).closest( 'form' ).find( 'select[name="bulk"]' ).val();
		var message = 'trash' === $( this ).attr( 'data-solo-estate-bulk-confirm' ) ? t.confirmTrash : t.confirmDelete;
		if ( 'delete' === value && ! window.confirm( message || 'Delete?' ) ) {
			e.preventDefault();
		}
	} );

	// Select all.
	$( document ).on( 'change', '[data-solo-estate-check-all]', function () {
		$( this ).closest( 'table' ).find( 'tbody input[type="checkbox"]' ).prop( 'checked', this.checked );
	} );

	// Check-all for a named group of checkboxes.
	$( document ).on( 'change', '[data-solo-estate-check-group]', function () {
		$( '[data-group="' + $( this ).data( 'solo-estate-check-group' ) + '"]' ).prop( 'checked', this.checked );
	} );

	// Copy coordinates from the children table.
	$( document ).on( 'click', '.solo-estate-copy-coords', function () {
		var $btn = $( this );
		var text = $btn.data( 'coords' );
		var done = function () {
			var label = $btn.text();
			$btn.text( '✓' );
			setTimeout( function () { $btn.text( label ); }, 1200 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( String( text ) ).then( done );
		} else {
			var $tmp = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
			document.execCommand( 'copy' );
			$tmp.remove();
			done();
		}
	} );

	// Random secret for the webhook signature.
	$( document ).on( 'click', '[data-solo-estate-generate]', function () {
		var bytes = new Uint8Array( 24 );
		window.crypto.getRandomValues( bytes );
		var secret = Array.prototype.map.call( bytes, function ( b ) {
			return ( '0' + b.toString( 16 ) ).slice( -2 );
		} ).join( '' );
		$( 'input[name="' + $( this ).data( 'solo-estate-generate' ) + '"]' ).val( secret );
	} );

	// Copy shortcode.
	$( document ).on( 'click', '.solo-estate-copy', function () {
		var $el = $( this );
		var text = $el.text();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( function () {
				$el.addClass( 'is-copied' );
				setTimeout( function () { $el.removeClass( 'is-copied' ); }, 1200 );
			} );
		}
	} );

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.solo-estate-color' ).wpColorPicker();
		}

		// Drag to reorder gallery images.
		if ( $.fn.sortable ) {
			$( '.solo-estate-gallery-field__list' ).sortable( {
				update: function () {
					syncGallery( $( this ).closest( '[data-solo-estate-gallery]' ) );
				}
			} );
		}

		// Drag to reorder specification fields.
		var $sortable = $( '[data-solo-estate-sortable]' );
		if ( $sortable.length && $.fn.sortable ) {
			$sortable.sortable( {
				handle: '.solo-estate-handle',
				items: 'tr:not(.solo-estate-new-row)',
				axis: 'y',
				update: function () {
					$sortable.find( 'tr:not(.solo-estate-new-row)' ).each( function ( i ) {
						$( this ).find( '[data-solo-estate-sort]' ).val( i + 1 );
					} );
				}
			} );
		}
	} );
}( jQuery ) );
