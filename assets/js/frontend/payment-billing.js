/**
 * Business Builder Core — Dynamic billing form behaviour.
 *
 * Drives the billing-details block rendered by templates/partials/billing-form.php:
 *
 *   1. Show/hide the block based on the selected payment gateway — only
 *      gateways whose id matches the block's data-bb-billing-gateway (Paymob)
 *      reveal it. Every other gateway is unaffected.
 *   2. Toggle the `required` constraint only while the block is visible, so
 *      other gateways never block on empty hidden fields.
 *   3. Validate on submit (and live on input) with accessible inline errors
 *      and red borders — no browser alerts. Submission is prevented until
 *      the visible billing fields are valid.
 *
 * Works with any number of forms on the page (consultation + booking) and
 * degrades gracefully: with JS disabled the block stays hidden and the
 * backend still validates/rejects incomplete Paymob data.
 *
 * @package BusinessBuilderCore
 */
( function () {
	'use strict';

	var GATEWAY_NAME = 'bb_payment_gateway';

	/**
	 * Cache the input/error elements for one billing block.
	 *
	 * @param {HTMLElement} block Billing container.
	 * @return {Object}
	 */
	function collectFields( block ) {
		var inputs = {};
		var errors = {};

	block.querySelectorAll( '[data-bb-billing-field]' ).forEach( function ( input ) {
			inputs[ input.getAttribute( 'data-bb-billing-field' ) ] = input;
	} );

	block.querySelectorAll( '[data-bb-billing-error]' ).forEach( function ( el ) {
			errors[ el.getAttribute( 'data-bb-billing-error' ) ] = el;
	} );

		return { inputs: inputs, errors: errors };
	}

	/**
	 * Whether a value is a syntactically valid email.
	 *
	 * @param {string} value Value.
	 * @return {boolean}
	 */
	function isValidEmail( value ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value ).trim() );
	}

	/**
	 * Whether a value is digits only (spaces/dashes/+ normalised away first).
	 *
	 * @param {string} value Value.
	 * @return {boolean}
	 */
	function isValidPhone( value ) {
		var digits = String( value ).replace( /[^0-9]/g, '' );

		return digits.length >= 6 && /^[0-9]+$/.test( digits );
	}

	/**
	 * Per-field validation rules. Returns '' when valid.
	 *
	 * @param {string} field Field key.
	 * @param {string} value Value.
	 * @return {string} Error message or ''.
	 */
	function fieldError( field, value ) {
		var trimmed = String( value ).trim();

		if ( 'email' === field ) {
			if ( '' === trimmed ) {
				return 'Please enter your email address.';
			}
			if ( ! isValidEmail( trimmed ) ) {
				return 'Please enter a valid email address.';
			}
			return '';
	}

		if ( 'phone' === field ) {
			if ( '' === trimmed ) {
				return 'Please enter your phone number.';
			}
			if ( ! isValidPhone( trimmed ) ) {
				return 'Phone number must contain digits only.';
			}
			return '';
	}

		if ( '' === trimmed ) {
			return 'This field is required.';
	}

		return '';
	}

	/**
	 * Set/clear the error state for one field.
	 *
	 * @param {HTMLElement} input Input element.
	 * @param {HTMLElement} error Error element (may be undefined).
	 * @param {string}      message Error message ('' clears it).
	 */
	function setFieldError( input, error, message ) {
		if ( ! input ) {
			return;
	}

		if ( '' === message ) {
			input.removeAttribute( 'aria-invalid' );
	} else {
			input.setAttribute( 'aria-invalid', 'true' );
	}

		if ( error ) {
			error.textContent = message;
			error.classList.toggle( 'is-visible', '' !== message );
	}
	}

	/**
	 * Wire one billing block + the form that contains it.
	 *
	 * @param {HTMLElement} block Billing container.
	 */
	function initBlock( block ) {
		var form = block.closest( 'form' );

		if ( ! form ) {
			return;
	}

		       var gatewayId = block.getAttribute( 'data-bb-billing-gateway' ) || 'paymob';
		var cached = collectFields( block );

		var radios = form.querySelectorAll( 'input[name="' + GATEWAY_NAME + '"]' );

		/*
		 * On the dedicated payment step page there are no gateway radios —
		 * the billing form IS the page. In that case it must always be shown
		 * and validated, so we skip the gateway-driven show/hide entirely.
		 */
		var isStepPage = ( radios.length === 0 );

		if ( isStepPage ) {
		block.removeAttribute( 'hidden' );
		block.setAttribute( 'aria-hidden', 'false' );

		block.querySelectorAll( '[data-bb-billing-field]' ).forEach( function ( input ) {
		input.setAttribute( 'required', 'required' );
		} );

		initStepValidation( form, cached );

		return;
		}

	/**
		 * Whether the billing block should currently be visible.
		 *
		 * @return {boolean}
		 */
		function isActive() {
			var selected = form.querySelector( 'input[name="' + GATEWAY_NAME + '"]:checked' );

			return !! selected && selected.value === gatewayId;
	}

	/**
		 * Show or hide the block and manage required/validation state.
		 *
		 * @param {boolean} show Show it.
		 */
		function apply( show ) {
			if ( show ) {
				block.removeAttribute( 'hidden' );
				block.setAttribute( 'aria-hidden', 'false' );
				block.classList.add( 'is-revealing' );

				window.setTimeout( function () {
					block.classList.remove( 'is-revealing' );
				}, 320 );

				block.querySelectorAll( '[data-bb-billing-field]' ).forEach( function ( input ) {
					input.setAttribute( 'required', 'required' );
				} );
			} else {
				block.classList.remove( 'is-revealing' );
				block.classList.add( 'is-hiding' );

				/* Clear any errors so a hidden block never blocks submit. */
				Object.keys( cached.inputs ).forEach( function ( key ) {
					setFieldError( cached.inputs[ key ], cached.errors[ key ], '' );
				} );

				block.querySelectorAll( '[data-bb-billing-field]' ).forEach( function ( input ) {
					input.removeAttribute( 'required' );
				} );

				window.setTimeout( function () {
					block.classList.remove( 'is-hiding' );
					block.setAttribute( 'hidden', 'hidden' );
					block.setAttribute( 'aria-hidden', 'true' );
				}, 200 );
			}
	}

	/* React to gateway changes. */
	radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( isActive() ) {
					apply( true );
				} else {
					apply( false );
				}
			} );
	} );

	/* Live re-validation once the user has interacted. */
	Object.keys( cached.inputs ).forEach( function ( key ) {
			var input = cached.inputs[ key ];

			input.addEventListener( 'blur', function () {
				if ( ! isActive() ) {
					return;
				}
				setFieldError( input, cached.errors[ key ], fieldError( key, input.value ) );
			} );

			input.addEventListener( 'input', function () {
				if ( 'true' === input.getAttribute( 'aria-invalid' ) && isActive() ) {
					setFieldError( input, cached.errors[ key ], fieldError( key, input.value ) );
				}
			} );
	} );

	/* Validate on submit; block when the active billing fields are invalid. */
	form.addEventListener( 'submit', function ( event ) {
			if ( ! isActive() ) {
				return;
			}

			var firstInvalid = null;

			Object.keys( cached.inputs ).forEach( function ( key ) {
				var input = cached.inputs[ key ];
				var message = fieldError( key, input.value );

				setFieldError( input, cached.errors[ key ], message );

				if ( '' !== message && ! firstInvalid ) {
					firstInvalid = input;
				}
			} );

			if ( firstInvalid ) {
				event.preventDefault();
				firstInvalid.focus();
				firstInvalid.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
	} );

	/* Apply the correct initial state (in case a gateway is pre-selected). */
	apply( false );

		if ( isActive() ) {
			apply( true );
	}
	}

	   /**
	 * Validate a billing block that is always visible (payment step page).
	 *
	 * @param {HTMLElement} form   Owning form.
	 * @param {Object}      cached { inputs, errors }.
	 */
	function initStepValidation( form, cached ) {
	Object.keys( cached.inputs ).forEach( function ( key ) {
	var input = cached.inputs[ key ];

	input.addEventListener( 'blur', function () {
		setFieldError( input, cached.errors[ key ], fieldError( key, input.value ));
	} );

	input.addEventListener( 'input', function () {
	if ( 'true' === input.getAttribute( 'aria-invalid' ) ) {
		setFieldError( input, cached.errors[ key ], fieldError( key, input.value ));
	}
	} );
	} );

	form.addEventListener( 'submit', function ( event ) {
	var firstInvalid = null;

	Object.keys( cached.inputs ).forEach( function ( key ) {
	var input = cached.inputs[ key ];
	var message = fieldError( key, input.value );

		setFieldError( input, cached.errors[ key ], message );

	if ( '' !== message && ! firstInvalid ) {
	firstInvalid = input;
	}
	} );

	if ( firstInvalid ) {
		event.preventDefault();
	firstInvalid.focus();
	firstInvalid.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}
	} );
	}

	/**
	 * Boot every billing block on the page.
	 */
	function init() {
	document.querySelectorAll( '[data-bb-billing]' ).forEach( initBlock );
	}

	if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
	} else {
	init();
	}
} )();
