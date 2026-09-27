/**
 * Business Builder Core — Admin: meta box behaviour (Phase 22 §27)
 * ---------------------------------------------------------------
 * Drives the two interactive pieces of `Admin\MetaBoxRenderer`:
 *
 *   1. TABS — a `role="tablist"` needs real tab semantics (arrow keys, one
 *      focusable tab, `aria-selected` kept in sync), which CSS alone cannot
 *      provide. The panels are server-rendered with `hidden`, so with no
 *      JavaScript the FIRST panel is still shown by the fallback below rather
 *      than the whole box appearing blank.
 *   2. MEDIA — the WordPress media frame, opened from a real button, writing the
 *      chosen attachment id into the field's hidden input and rendering a
 *      thumbnail. Uses `wp.media`, which the meta box declares as a dependency,
 *      so no uploader is reimplemented.
 *
 * PROGRESSIVE ENHANCEMENT
 * -----------------------
 *   - If `wp.media` is missing (a heavily customised admin), the media button is
 *     left inert rather than throwing: the id input is still editable by hand, so
 *     the field never becomes unusable.
 *   - If this script never runs, one tab panel is revealed by the CSS fallback
 *     rule, so the editor sees their fields.
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------ tabs */
	function initTabs( root ) {
		var list = root.querySelector( '.bb-meta-tabs' );

		if ( ! list ) {
			return;
		}

		var tabs = Array.prototype.slice.call( list.querySelectorAll( '[data-bb-meta-tab]' ) );
		var panels = Array.prototype.slice.call( root.querySelectorAll( '.bb-meta-panel' ) );

		if ( tabs.length < 2 || ! panels.length ) {
			return;
		}

		function select( index ) {
			tabs.forEach( function ( tab, i ) {
				var isActive = i === index;
				var panel = panels[ i ];

				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );

				/* Only the active tab is in the tab order (roving tabindex). */
				tab.tabIndex = isActive ? 0 : -1;

				if ( panel ) {
					panel.hidden = ! isActive;
				}
			} );
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( i );
			} );

			/*
			 * Arrow keys move between tabs, which is what a `tablist` is expected
			 * to do. Home/End jump to the ends, matching the WAI-ARIA pattern.
			 */
			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;

				if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
					next = ( i + 1 ) % tabs.length;
				} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
					next = ( i - 1 + tabs.length ) % tabs.length;
				} else if ( 'Home' === event.key ) {
					next = 0;
				} else if ( 'End' === event.key ) {
					next = tabs.length - 1;
				}

				if ( null === next ) {
					return;
				}

				event.preventDefault();
				select( next );
				tabs[ next ].focus();
			} );
		} );

		/* Open the first tab, so the box is never blank. */
		select( 0 );
	}

	/* ----------------------------------------------------------------- media */
	function initMedia( root ) {

		if ( ! window.wp || ! window.wp.media ) {
			/* No uploader available: leave the id input as the manual path. */
			return;
		}

		var fields = root.querySelectorAll( '[data-bb-media]' );

		Array.prototype.forEach.call( fields, function ( field ) {

			var input    = field.querySelector( '[data-bb-media-input]' );
			var preview  = field.querySelector( '[data-bb-media-preview]' );
			var select   = field.querySelector( '[data-bb-media-select]' );
			var remove   = field.querySelector( '[data-bb-media-remove]' );

			if ( ! input || ! select ) {
				return;
			}

			var frame = null;

			select.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( ! frame ) {
					frame = window.wp.media( {
						title: select.textContent.trim(),
						library: { type: 'image' },
						multiple: false,
						button: { text: select.textContent.trim() }
					} );

					frame.on( 'select', function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();
						var url = attachment.sizes && attachment.sizes.thumbnail
							? attachment.sizes.thumbnail.url
							: attachment.url;

						input.value = String( attachment.id );

						if ( preview ) {
							preview.innerHTML = '';
							var img = document.createElement( 'img' );
							img.src = url;
							img.className = 'bb-meta-media-img';
							img.alt = attachment.alt || '';
							preview.appendChild( img );
						}

						if ( remove ) {
							remove.hidden = false;
						}
					} );
				}

				frame.open();
			} );

			if ( remove ) {
				remove.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					input.value = '';

					if ( preview ) {
						preview.innerHTML = '<span class="bb-meta-media-empty">' +
							( select.getAttribute( 'data-bb-empty-label' ) || '' ) + '</span>';
					}

					remove.hidden = true;
				} );
			}
		} );
	}

	/* ------------------------------------------------------------------ init */
	function init() {
		var boxes = document.querySelectorAll( '.bb-meta' );

		Array.prototype.forEach.call( boxes, function ( box ) {
			initTabs( box );
			initMedia( box );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );