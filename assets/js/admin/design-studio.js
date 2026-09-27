/**
 * Business Builder Core - Admin: Design preview modal (Phase 21 §19, §37, §60)
 * --------------------------------------------------------------------------
 * Opens the design-preview modal from a catalogue card, and drives the device frames and the
 * RTL/LTR switch inside it.
 *
 * DESIGN DECISIONS
 * ----------------
 *   - VANILLA JS, NO DEPENDENCIES, NO BUILD STEP (§10, §59). The whole screen is a few hundred
 *     bytes of behaviour; a framework would be pure overhead.
 *   - PROGRESSIVE ENHANCEMENT: every design is also applied through a real form and previewed
 *     through a real link, so if this script fails the screen still works.
 *   - ACCESSIBLE (§60): the modal is a real dialog with `aria-modal`, focus is moved into it on
 *     open and returned to the trigger on close, Escape closes it, and the backdrop is
 *     clickable. Device buttons expose their state via `aria-pressed`.
 *   - The previews are rendered SERVER-SIDE (see DesignPreviewRenderer), so opening the modal
 *     costs no network request and the preview cannot drift from the design (§58).
 */
( function () {
	'use strict';

	var lastTrigger = null;

	function openModal( modal, trigger ) {
		lastTrigger = trigger || null;

		modal.hidden = false;

		/* Move focus to the close button so keyboard users land inside the dialog. */
		var close = modal.querySelector( '.bb-design-modal-close' );

		if ( close ) {
			close.focus();
		}

		document.body.classList.add( 'bb-design-modal-open' );
	}

	function closeModal( modal ) {
		modal.hidden = true;
		document.body.classList.remove( 'bb-design-modal-open' );

		if ( lastTrigger && typeof lastTrigger.focus === 'function' ) {
			lastTrigger.focus();
		}

		lastTrigger = null;
	}

	function setDevice( modal, device ) {
		var frames = modal.querySelectorAll( '[data-bb-device-frame]' );
		var buttons = modal.querySelectorAll( '[data-bb-device]' );

		for ( var i = 0; i < frames.length; i++ ) {
			var isMatch = frames[ i ].getAttribute( 'data-bb-device-frame' ) === device;
			frames[ i ].classList.toggle( 'is-active', isMatch );
		}

		for ( var b = 0; b < buttons.length; b++ ) {
			var on = buttons[ b ].getAttribute( 'data-bb-device' ) === device;
			buttons[ b ].classList.toggle( 'is-active', on );
			buttons[ b ].setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		}
	}

	function toggleDirection( modal, button ) {
		var toRtl = button.getAttribute( 'aria-pressed' ) !== 'true';

		/*
		 * Both directions are pre-rendered per device frame; this toggle simply reveals the
		 * matching one (see the .is-rtl rules in design-preview.css). That keeps the switch
		 * instant and means the RTL preview is the REAL design in a real RTL document, not a
		 * CSS mirror.
		 */
		modal.classList.toggle( 'is-rtl', toRtl );

		button.setAttribute( 'aria-pressed', toRtl ? 'true' : 'false' );
	}

	function init() {
		var triggers = document.querySelectorAll( '[data-bb-preview-design]' );

		for ( var i = 0; i < triggers.length; i++ ) {

			triggers[ i ].addEventListener( 'click', function ( event ) {
				var slug = this.getAttribute( 'data-bb-preview-design' );
				var modal = document.querySelector( '[data-bb-design-modal="' + slug + '"]' );

				if ( ! modal ) {
					return;
				}

				event.preventDefault();

				/* Always open on the desktop frame. */
				setDevice( modal, 'desktop' );

				openModal( modal, this );
			} );
		}

		/* Close controls. */
		var closers = document.querySelectorAll( '[data-bb-modal-close]' );

		for ( var c = 0; c < closers.length; c++ ) {
			closers[ c ].addEventListener( 'click', function () {
				var modal = this.closest( '.bb-design-modal' );

				if ( modal ) {
					closeModal( modal );
				}
			} );
		}

		/* Device switches. */
		var devices = document.querySelectorAll( '[data-bb-device]' );

		for ( var d = 0; d < devices.length; d++ ) {
			devices[ d ].addEventListener( 'click', function () {
				var modal = this.closest( '.bb-design-modal' );

				if ( modal ) {
					setDevice( modal, this.getAttribute( 'data-bb-device' ) );
				}
			} );
		}

		/* Direction switch. */
		var dirs = document.querySelectorAll( '[data-bb-direction]' );

		for ( var r = 0; r < dirs.length; r++ ) {
			dirs[ r ].addEventListener( 'click', function () {
				var modal = this.closest( '.bb-design-modal' );

				if ( modal ) {
					toggleDirection( modal, this );
				}
			} );
		}

		/* Escape closes the open modal. */
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}

			var open = document.querySelector( '.bb-design-modal:not([hidden])' );

			if ( open ) {
				closeModal( open );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );