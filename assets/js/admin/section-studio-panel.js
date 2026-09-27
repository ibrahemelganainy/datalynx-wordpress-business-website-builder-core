/**
 * Business Builder Core - Admin: Section Studio behaviour (Phase 22 §14-§19)
 * -------------------------------------------------------------------------
 * Drives the per-section panels: the jump chips, the live preview updates, the
 * colour "inherit vs. override" switch and the per-section reset.
 *
 * SAFETY (same contract as design-studio-panel.js)
 * -----------------------------------------------
 *   - Nothing here is persisted. Saving happens through the normal form POST,
 *     which the server validates per-token.
 *   - Token names come from server-rendered `data-bb-section-*` attributes.
 *   - Colour values are re-validated here as hex before being written to the
 *     preview, so a script cannot inject an arbitrary declaration.
 *   - Choice values are taken from the radio's own server-rendered value.
 *
 * The live preview is the SAME preview element the global Studio updates, so a
 * section change repaints the real design preview rather than a fake one (§26).
 */
( function () {
	'use strict';

	var HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

	/* ------------------------------------------------------------ preview scope */
	function scope() {
		return document.querySelector( '[data-bb-studio-preview] .bb-design-preview-scope' );
	}

	function setToken( name, value ) {
		if ( ! name || 0 !== name.indexOf( '--bb-' ) ) {
			return;
		}

		var target = scope();

		if ( ! target ) {
			return;
		}

		target.style.setProperty( name, value );
	}

	function markDirty() {
		var note = document.querySelector( '[data-bb-studio-note]' );

		if ( note ) {
			note.hidden = false;
		}
	}

	/* ------------------------------------------------------------ section picker */
	function bindPicker() {
		var chips = document.querySelectorAll( '[data-bb-section-jump]' );
		var panels = document.querySelectorAll( '[data-bb-section-panel]' );

		function show( type ) {
			for ( var p = 0; p < panels.length; p++ ) {
				panels[ p ].hidden = panels[ p ].getAttribute( 'data-bb-section-panel' ) !== type;
			}

			for ( var c = 0; c < chips.length; c++ ) {
				chips[ c ].classList.toggle(
					'is-active',
					chips[ c ].getAttribute( 'data-bb-section-jump' ) === type
				);
			}
		}

		for ( var i = 0; i < chips.length; i++ ) {
			chips[ i ].addEventListener( 'click', function () {
				show( this.getAttribute( 'data-bb-section-jump' ) );
			} );
		}

		/* The first chip opens by default, so the panel is never a blank area. */
		if ( chips.length ) {
			show( chips[ 0 ].getAttribute( 'data-bb-section-jump' ) );
		}
	}

	/* ------------------------------------------------------------ colours */
	function applyColor( input ) {
		var token = input.getAttribute( 'data-bb-section-color' );
		var value = input.value;

		if ( ! HEX.test( value ) ) {
			return;
		}

		setToken( token, value );

		var hex = document.querySelector( '[data-bb-section-hex="' + token + '"]' );

		if ( hex ) {
			hex.textContent = value.toUpperCase();
		}

		/* Choosing a colour is what makes it an override, so untick "inherit". */
		var inherit = document.querySelector( '[data-bb-section-inherit="' + token + '"]' );

		if ( inherit ) {
			inherit.checked = false;
		}

		markChanged( input.closest( '.bb-section-control' ) );
	}

	function bindColors() {
		var inputs = document.querySelectorAll( '[data-bb-section-color]' );

		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].addEventListener( 'input', function () {
				applyColor( this );
			} );
		}

		/*
		 * "Use the design colour" clears the override. The colour input cannot
		 * express "unset" on its own, so the checkbox submits the empty string -
		 * which the server reads as "stop overriding this token".
		 */
		var inheritBoxes = document.querySelectorAll( '[data-bb-section-inherit]' );

		for ( var b = 0; b < inheritBoxes.length; b++ ) {
			inheritBoxes[ b ].addEventListener( 'change', function () {
				var token = this.getAttribute( 'data-bb-section-inherit' );

				if ( ! this.checked ) {
					return;
				}

				var input = document.querySelector( '[data-bb-section-color="' + token + '"]' );
				var hex = document.querySelector( '[data-bb-section-hex="' + token + '"]' );

				if ( hex ) {
					hex.textContent = 'Inherited';
				}

				if ( input ) {
					markChanged( input.closest( '.bb-section-control' ) );
				}

				/*
				 * The preview cannot show an "unset" custom property, so the token
				 * is simply removed - which is exactly what happens on the frontend
				 * when the override disappears from the emitted block.
				 */
				var target = scope();

				if ( target && token ) {
					target.style.removeProperty( token );
				}

				markDirty();
			} );
		}
	}

	/* ------------------------------------------------------------ named choices */
	function bindChoices() {
		var radios = document.querySelectorAll( '[data-bb-section-choice]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', function () {
				var group = this.closest( '.bb-choice-options' );

				if ( group ) {
					var options = group.querySelectorAll( '.bb-choice-option' );

					for ( var o = 0; o < options.length; o++ ) {
						var input = options[ o ].querySelector( 'input' );

						options[ o ].classList.toggle( 'is-selected', !! ( input && input.checked ) );
					}
				}

				markChanged( this.closest( '.bb-section-control' ) );
				markDirty();
			} );
		}
	}

	/* ------------------------------------------------------------ ranges */
	function bindRanges() {
		var inputs = document.querySelectorAll( '[data-bb-section-range]' );

		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].addEventListener( 'input', function () {
				var token = this.getAttribute( 'data-bb-section-range' );
				var unit = this.getAttribute( 'data-bb-section-unit' ) || '';
				var value = this.value + unit;

				var label = document.querySelector( '[data-bb-section-range-value="' + token + '"]' );

				if ( label ) {
					label.textContent = value;
				}

				/*
				 * The preview token is set from the SLIDER's own value plus the
				 * server-declared unit, so no CSS unit is ever invented here.
				 */
				if ( unit === 'em' ) {
					setToken( token, this.value + 'em' );
				} else if ( unit === 'ch' ) {
					setToken( token, this.value + 'ch' );
				} else if ( unit === 's' ) {
					setToken( token, this.value + 's' );
				} else if ( unit === 'px' ) {
					setToken( token, this.value + 'px' );
				} else {
					/* Unitless (a ratio or an opacity): used verbatim. */
					setToken( token, this.value );
				}

				markChanged( this.closest( '.bb-section-control' ) );
				markDirty();
			} );
		}
	}

	function markChanged( control ) {
		if ( control ) {
			control.classList.add( 'is-changed' );
		}

		markDirty();
	}

	/* ------------------------------------------------------------ reset */
	function bindReset() {
		var buttons = document.querySelectorAll( '[data-bb-section-reset]' );

		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].addEventListener( 'click', function () {
				var type = this.getAttribute( 'data-bb-section-reset' );

				if ( ! type ) {
					return;
				}

				/*
				 * Resetting a section returns it to the design/global state. It is
				 * done through the normal POST (so the server owns the write and the
				 * nonce), which is why this builds a form rather than clearing
				 * fields locally: a local clear would only LOOK reset until save.
				 */
				var form = document.createElement( 'form' );
				form.method = 'post';
				form.action = ( window.BBSectionStudio && window.BBSectionStudio.action ) || '';

				if ( ! form.action ) {
					return;
				}

				var fields = {
					action: ( window.BBSectionStudio && window.BBSectionStudio.resetAction ) || '',
					section: type,
					bb_return: ( window.BBSectionStudio && window.BBSectionStudio.returnUrl ) || '',
					_ajax_nonce: null
				};

				Object.keys( fields ).forEach( function ( name ) {
					if ( ! fields[ name ] ) {
						return;
					}

					var input = document.createElement( 'input' );
					input.type = 'hidden';
					input.name = name;
					input.value = fields[ name ];
					form.appendChild( input );
				} );

				/* The nonce is read from the page's own reset form, never invented. */
				var nonceSource = document.querySelector( '.bb-studio-reset input[name="_wpnonce"]' );

				if ( nonceSource ) {
					var nonce = document.createElement( 'input' );
					nonce.type = 'hidden';
					nonce.name = '_wpnonce';
					nonce.value = nonceSource.value;
					form.appendChild( nonce );
				}

				document.body.appendChild( form );
				form.submit();
			} );
		}
	}

	/* ------------------------------------------------------------ init */
	function init() {
		if ( ! document.querySelector( '.bb-section-studio' ) ) {
			return;
		}

		bindPicker();
		bindColors();
		bindChoices();
		bindRanges();
		bindReset();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );