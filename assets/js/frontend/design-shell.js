/**
 * Business Builder Core - Frontend: Shell state (Phase 22 §21)
 * ------------------------------------------------------------
 * Drives the header's SCROLLED state, which is what makes the Studio's
 * "top of page" / "after scrolling" colour controls a real frontend behaviour
 * instead of a saved-but-inert setting.
 *
 * WHY THIS IS NOT CSS-ONLY
 * ------------------------
 * The header must change only once the page has actually moved. A pure CSS
 * `position: sticky` trick cannot express "after 24px of scroll" portably, and
 * a scroll listener that reads layout on every event would be exactly the kind
 * of per-frame cost §34 forbids. So:
 *
 *   - ONE passive scroll listener, throttled with requestAnimationFrame;
 *   - it only TOGGLES A CLASS, so all the visual work happens in CSS;
 *   - it reads `window.scrollY` (no layout read, no reflow);
 *   - it stops touching the DOM once the class matches the state.
 *
 * PROGRESSIVE ENHANCEMENT (§34, §35)
 * ----------------------------------
 *   - If the script never runs, the header simply stays in its initial state,
 *     which is the pre-Phase-22 behaviour. Nothing is hidden, nothing breaks.
 *   - Under `prefers-reduced-motion: reduce` the state still changes (it is a
 *     legibility feature, not decoration) but the CSS transition is removed by
 *     the stylesheet, so the change is instantaneous rather than animated.
 */
( function () {
	'use strict';

	/**
	 * How far the page must move before the header switches state.
	 *
	 * Read from the header's own `data-bb-scroll-offset` when the Theme provides
	 * one, so a tall header can switch later without editing this script.
	 */
	var DEFAULT_OFFSET = 24;

	function init() {
		var header = document.querySelector( '.bb-site-header' );

		if ( ! header ) {
			return;
		}

		var offset = parseInt( header.getAttribute( 'data-bb-scroll-offset' ) || '', 10 );

		if ( isNaN( offset ) || offset < 0 ) {
			offset = DEFAULT_OFFSET;
		}

		var ticking = false;

		function apply() {
			ticking = false;

			var scrolled = ( window.pageYOffset || window.scrollY || 0 ) > offset;

			/*
			 * Only write when the state actually changes: `classList.toggle` with
			 * the force flag is idempotent, and skipping the call entirely when the
			 * class already matches avoids any DOM mutation during a scroll.
			 */
			if ( header.classList.contains( 'is-bb-scrolled' ) === scrolled ) {
				return;
			}

			header.classList.toggle( 'is-bb-scrolled', scrolled );
		}

		function onScroll() {
			if ( ticking ) {
				return;
			}

			ticking = true;

			/*
			 * rAF-throttled: the listener itself does no work beyond scheduling,
			 * so a fast scroll cannot queue up dozens of state writes.
			 */
			if ( window.requestAnimationFrame ) {
				window.requestAnimationFrame( apply );
			} else {
				apply();
			}
		}

		/* Apply the correct state immediately (a reload mid-page must not be wrong). */
		apply();

		/*
		 * `passive: true` tells the browser this listener will never call
		 * preventDefault(), so scrolling is never blocked waiting for it.
		 */
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );