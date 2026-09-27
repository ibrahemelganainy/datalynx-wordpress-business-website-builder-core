/**
 * Business Builder Core - Admin: Design Studio live preview (Phase 21 §43)
 * -----------------------------------------------------------------------
 * Updates the Studio's preview IMMEDIATELY as the customer changes a setting, without a page
 * reload and without a network request.
 *
 * HOW IT WORKS — AND WHY THIS IS SAFE
 * -----------------------------------
 * The preview is a real, server-rendered design preview (see DesignPreviewRenderer). Its scope
 * element carries the design's `--bb-*` tokens as inline custom properties. Changing a control
 * therefore needs only ONE write:
 *
 *     previewScope.style.setProperty( '--bb-color-primary', '#123456' )
 *
 * The browser then repaints every element that consumes that token. This is why the live preview
 * needs no new architecture (§43): the preview already renders from the design system, so
 * updating a token IS updating the preview.
 *
 * SAFETY (§43 "do not compromise security")
 * ----------------------------------------
 *   - Nothing here is persisted. The preview is a local visual only; saving happens through the
 *     normal form POST, which the server validates with the Theme's own sanitizer.
 *   - Values are taken from CONTROLS THE SERVER RENDERED (a palette tile's data attribute, a
 *     radio's value, a colour input's value), never from free text, so a script cannot inject an
 *     arbitrary declaration.
 *   - Colour values are re-validated here as hex before being written.
 *   - Token names are taken from the server-rendered `data-bb-token` attributes.
 *
 * ACCESSIBILITY (§60)
 * -------------------
 * The preview is decorative-but-informative: it carries a `role="img"` and an accessible label
 * from the server. This script only changes custom properties, so it never alters semantics.
 */
( function () {
	'use strict';

	var HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

	function scope() {
		return document.querySelector( '[data-bb-studio-preview] .bb-design-preview-scope' );
	}

	function setToken( name, value ) {
		if ( ! name || 0 !== name.indexOf( '--bb-' ) ) {
			return false;
		}

		var target = scope();

		if ( ! target ) {
			return false;
		}

		target.style.setProperty( name, value );

		return true;
	}

	/* ---------------------------------------------------------------- dirty state */
	function markDirty() {
		var note = document.querySelector( '[data-bb-studio-note]' );

		if ( note ) {
			note.hidden = false;
		}
	}

	/* ---------------------------------------------------------------- palettes */
	function bindPalettes() {
		var tiles = document.querySelectorAll( '[data-bb-palette]' );

		for ( var i = 0; i < tiles.length; i++ ) {
			tiles[ i ].addEventListener( 'click', function () {
				var raw = this.getAttribute( 'data-bb-palette-colors' );
				var colors;

				try {
					colors = JSON.parse( raw );
				} catch ( e ) {
					return;
				}

				if ( ! colors ) {
					return;
				}

				/*
				 * Apply each colour to BOTH the preview token and the matching colour input, so the
				 * form the customer submits matches what they see.
				 */
				Object.keys( colors ).forEach( function ( key ) {
					var value = String( colors[ key ] );

					if ( ! HEX.test( value ) ) {
						return;
					}

					var input = document.querySelector( '[data-bb-color="' + key + '"]' );

					if ( input ) {
						input.value = value;
						applyColor( input );
					}
				} );

				/* Reflect the selection state. */
				var all = document.querySelectorAll( '[data-bb-palette]' );

				for ( var a = 0; a < all.length; a++ ) {
					all[ a ].classList.remove( 'is-selected' );
				}

				this.classList.add( 'is-selected' );

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- colours */
	function applyColor( input ) {
		var key   = input.getAttribute( 'data-bb-color' );
		var token = input.getAttribute( 'data-bb-token' );
		var value = input.value;

		if ( ! HEX.test( value ) ) {
			return;
		}

		setToken( token, value );

		var hex = document.querySelector( '[data-bb-color-hex="' + key + '"]' );

		if ( hex ) {
			hex.textContent = value.toUpperCase();
		}

		var control = input.closest( '.bb-color-control' );

		if ( control ) {
			control.classList.add( 'is-overridden' );
		}
	}

	function bindColors() {
		var inputs = document.querySelectorAll( '[data-bb-color]' );

		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].addEventListener( 'input', function () {
				applyColor( this );
				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- fonts */
	function bindFonts() {
		var radios = document.querySelectorAll( '[data-bb-font]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', function () {
				var token = this.getAttribute( 'data-bb-font' );

				/*
				 * The value is a SLUG; the STACK is read from the option's own rendered
				 * `font-family`, which the server produced from the curated catalog. That keeps
				 * the preview honest without duplicating the catalog in JavaScript.
				 */
				var option = this.closest( '.bb-font-option' );
				var stack  = option ? option.style.fontFamily : '';

				if ( stack ) {
					setToken( token, stack );
				}

				/* Reflect selection across the group. */
				var group = this.closest( '.bb-font-options' );

				if ( group ) {
					var siblings = group.querySelectorAll( '.bb-font-option' );

					for ( var s = 0; s < siblings.length; s++ ) {
						siblings[ s ].classList.remove( 'is-selected' );
					}
				}

				if ( option ) {
					option.classList.add( 'is-selected' );
				}

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- gradients */
	function bindGradients() {
		var radios = document.querySelectorAll( '[data-bb-gradient]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', function () {
				var token = this.getAttribute( 'data-bb-gradient' );
				var css   = this.getAttribute( 'data-bb-gradient-css' );

				/*
				 * Gradient CSS comes from the server-rendered attribute (the schema's own library),
				 * so no gradient syntax is ever assembled here.
				 */
				if ( css ) {
					setToken( token, css );
				}

				var group = this.closest( '.bb-gradient-options' );

				if ( group ) {
					var siblings = group.querySelectorAll( '.bb-gradient-tile' );

					for ( var s = 0; s < siblings.length; s++ ) {
						siblings[ s ].classList.remove( 'is-selected' );
					}
				}

				var tile = this.closest( '.bb-gradient-tile' );

				if ( tile ) {
					tile.classList.add( 'is-selected' );
				}

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- named scales */
	function bindScales() {
		var radios = document.querySelectorAll( '[data-bb-value]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', function () {
				var token = this.getAttribute( 'data-bb-token' );
				var value = this.getAttribute( 'data-bb-value' );

				if ( token && value ) {
					setToken( token, value );
				}

				var group = this.closest( '.bb-scale-options' );

				if ( group ) {
					var siblings = group.querySelectorAll( '.bb-scale-option' );

					for ( var s = 0; s < siblings.length; s++ ) {
						siblings[ s ].classList.remove( 'is-selected' );
					}
				}

				var option = this.closest( '.bb-scale-option' );

				if ( option ) {
					option.classList.add( 'is-selected' );
				}

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- segmented choices */
	function bindChoices() {
		var radios = document.querySelectorAll( '.bb-choice-option input[type="radio"]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', function () {
				var group = this.closest( '.bb-choice-options' );

				if ( ! group ) {
					return;
				}

				var options = group.querySelectorAll( '.bb-choice-option' );

				for ( var o = 0; o < options.length; o++ ) {
					var input = options[ o ].querySelector( 'input' );

					options[ o ].classList.toggle( 'is-selected', !! ( input && input.checked ) );
				}

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- ranges */
	function bindRanges() {
		var inputs = document.querySelectorAll( '[data-bb-range]' );

		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].addEventListener( 'input', function () {
				var key   = this.getAttribute( 'data-bb-range' );
				var unit  = this.getAttribute( 'data-bb-unit' ) || '';
				var value = this.value;

				var label = document.querySelector( '[data-bb-range-value="' + key + '"]' );

				if ( label ) {
					label.textContent = value + unit;
				}

				/*
				 * PHASE 22 FIX: the token is now actually written to the preview.
				 *
				 * MEASURED FAILURE (Phase 22 audit)
				 * ---------------------------------
				 * This handler used to update ONLY the numeric label next to the slider
				 * and never called `setToken()`, so dragging any spacing / radius / motion
				 * slider changed a number on screen while the preview stayed identical.
				 * That is precisely the "a control that does not change anything is not a
				 * completed feature" case, and it is now fixed by writing the same value
				 * the form will submit.
				 *
				 * The UNIT comes from the server-rendered `data-bb-unit`, and only the
				 * units a schema control can actually declare are accepted, so no arbitrary
				 * CSS unit can be introduced here.
				 */
				var token = this.getAttribute( 'data-bb-token' );

				if ( token && /^(px|rem|em|%|ch|s|)$/.test( unit ) ) {
					setToken( token, value + unit );
				}

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- schema selects */
	/*
	 * A Phase 22 `select` control (Background style, Glass level, Reveal style, Header
	 * scroll values, …) renders as a segmented choice. The token it writes is on the
	 * radio's own `data-bb-token`, and the value it writes is resolved SERVER-SIDE into
	 * the option's `data-bb-css` attribute, so no CSS keyword is ever assembled in
	 * JavaScript.
	 */
	function bindSchemaChoices() {
		var radios = document.querySelectorAll( '[data-bb-schema-select]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', function () {
				var token = this.getAttribute( 'data-bb-token' );
				var css   = this.getAttribute( 'data-bb-css' );

				if ( token && css ) {
					setToken( token, css );
				}

				var group = this.closest( '.bb-choice-options' );

				if ( group ) {
					var options = group.querySelectorAll( '.bb-choice-option' );

					for ( var o = 0; o < options.length; o++ ) {
						var input = options[ o ].querySelector( 'input' );

						options[ o ].classList.toggle( 'is-selected', !! ( input && input.checked ) );
					}
				}

				markDirty();
			} );
		}
	}

	/* ---------------------------------------------------------------- init */
	function init() {
		if ( ! document.querySelector( '.bb-studio' ) ) {
			return;
		}

		bindPalettes();
		bindColors();
		bindFonts();
		bindGradients();
		bindScales();
		bindChoices();
		bindRanges();
		bindSchemaChoices();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );