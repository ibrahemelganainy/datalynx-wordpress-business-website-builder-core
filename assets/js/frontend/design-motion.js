/**
 * Business Builder Core - Frontend: Design motion (Phase 21)
 * ----------------------------------------------------------
 * A single, lightweight reveal observer that drives the design's motion language.
 *
 * DESIGN PRINCIPLES (§14, §15, §34, §59, §60)
 * -------------------------------------------
 *   - PROGRESSIVE ENHANCEMENT: nothing is marked for reveal until this script runs, so a
 *     failure or a slow connection leaves every section fully visible. The CSS only animates
 *     elements that carry `.is-bb-revealed`, so content is never hidden by default.
 *   - CHEAP: one IntersectionObserver for the whole page (not one per section), which is
 *     exactly what §59 asks for. No animation library, no scroll listener, no layout reads.
 *   - TRANSFORM + OPACITY ONLY: the CSS animations avoid layout-changing properties, so there
 *     is no reflow and no layout thrash while scrolling.
 *   - ABOVE-THE-FOLD IS NOT DELAYED (§34): elements already in the viewport on the first frame
 *     are revealed immediately rather than waiting for a scroll event.
 *   - ACCESSIBLE: if `prefers-reduced-motion: reduce` is set, the observer is never installed
 *     and the CSS also disables the animation, so content is simply visible.
 *   - A single element is unobserved once revealed, so the observer empties itself.
 */
( function () {
	'use strict';

	/* Respect the user's motion preference before doing anything else. */
	var prefersReduced =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function init() {
		var template = document.querySelector( '.bb-template' );

		if ( ! template ) {
			return;
		}

		/*
		 * Mark the reveal targets. Done from JS (not server-side) so that a JS-less or
		 * JS-failed request renders the page with no reveal attributes at all — nothing can
		 * ever be stuck invisible.
		 *
		 * PHASE 22 (§23): the reveal KIND is no longer hardcoded here. The server already
		 * resolved the design's / the customer's chosen kind and emitted it as
		 * `data-bb-reveal="<kind>"` on the section element (see `bb_section_presentation_state()`),
		 * so this script only needs to fill in the targets the server did not annotate, and it
		 * uses the SECTION'S OWN kind rather than a fixed `fade-up`.
		 */
		var targets = [];

		/* Section-level reveals: the heading block and the section body of each section. */
		var sections = template.querySelectorAll( '.bb-section' );

		for ( var i = 0; i < sections.length; i++ ) {
			var section = sections[ i ];

			/*
			 * The section's own reveal kind, resolved server-side. `none` means the customer
			 * or the design switched motion OFF for this section, and that decision must be
			 * honoured — the section is left completely unanimated.
			 */
			var kind = section.getAttribute( 'data-bb-reveal' ) || '';

			if ( 'none' === kind || '1' === section.getAttribute( 'data-bb-reveal-off' ) ) {
				continue;
			}

			/* A section with no declared kind falls back to the pre-Phase-22 behaviour. */
			if ( '' === kind ) {
				kind = 'fade-up';
			}

			var heading = section.querySelector(
				'.bb-section-heading, .bb-section-header, .bb-section-title'
			);

			if ( heading && ! heading.hasAttribute( 'data-bb-reveal' ) ) {
				heading.setAttribute( 'data-bb-reveal', kind );
				targets.push( heading );
			}

			/*
			 * Grids get a staggered reveal so cards arrive in sequence (§14 "staggered cards").
			 * The grid itself is NOT revealed (that would hide the whole block); its children
			 * are, which keeps the section's height stable.
			 */
			var grids = section.querySelectorAll( '[class*="bb-grid-columns-"], .bb-grid' );

			for ( var g = 0; g < grids.length; g++ ) {
				if ( ! grids[ g ].hasAttribute( 'data-bb-stagger' ) ) {
					grids[ g ].setAttribute( 'data-bb-stagger', '1' );
				}

				var children = grids[ g ].children;

				for ( var c = 0; c < children.length; c++ ) {
					/* Publish the child's index so the CSS stagger delay can use it. */
					children[ c ].style.setProperty( '--bb-reveal-stagger-index', String( c ) );
					children[ c ].setAttribute( 'data-bb-reveal', kind );
					targets.push( children[ c ] );
				}
			}

			/* Card-like blocks that are not inside a grid still get a simple reveal. */
			var cards = section.querySelectorAll(
				'.bb-card, .bb-lawyer-card, .bb-service-card, .bb-practice-area-card,' +
				'.bb-testimonial-card, .bb-doctor-card, .bb-medical-service-card'
			);

			for ( var k = 0; k < cards.length; k++ ) {
				if ( cards[ k ].closest( '[data-bb-stagger]' ) ) {
					continue; /* Already handled by the grid stagger. */
				}

				if ( ! cards[ k ].hasAttribute( 'data-bb-reveal' ) ) {
					cards[ k ].setAttribute( 'data-bb-reveal', kind );
					targets.push( cards[ k ] );
				}
			}
		}

		if ( ! targets.length ) {
			return;
		}

		function reveal( element ) {
			element.classList.add( 'is-bb-revealed' );
		}

		/* Reduced motion (or no observer support): show everything immediately. */
		if ( prefersReduced || ! ( 'IntersectionObserver' in window ) ) {
			for ( var t = 0; t < targets.length; t++ ) {
				reveal( targets[ t ] );
			}

			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				for ( var e = 0; e < entries.length; e++ ) {
					if ( ! entries[ e ].isIntersecting ) {
						continue;
					}

					reveal( entries[ e ].target );

					/* One-shot: never observe this element again. */
					observer.unobserve( entries[ e ].target );
				}
			},
			{
				/*
				 * A generous root margin means an element begins revealing just before it enters
				 * the viewport, so the motion completes as the user arrives rather than starting
				 * late. Kept modest so animation never runs far off-screen.
				 */
				rootMargin: '0px 0px -8% 0px',
				threshold: 0.05
			}
		);

		for ( var o = 0; o < targets.length; o++ ) {
			observer.observe( targets[ o ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );