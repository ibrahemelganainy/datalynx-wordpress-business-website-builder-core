/**
 * Business Builder Core — Manual payment instructions behaviour.
 *
 * Drives templates/partials/manual-payment-instructions.php:
 *
 *   1. Reveal exactly one gateway's manual instructions while that gateway
 *      is the selected radio option (and hide all others).
 *   2. Show the "This is a manual payment" context only when relevant.
 *   3. Require a transaction reference on submit for the active manual
 *      gateway, with an accessible inline error.
 *   4. Copy-to-clipboard for the configured wallet number / account / IBAN.
 *
 * Works with the consultation and booking forms simultaneously (each block
 * is scoped to its own form). Degrades gracefully: with JS disabled the
 * blocks stay hidden and the server still handles the submit safely.
 *
 * No secrets are ever read or transmitted by this script — it only mirrors
 * the public, administrator-configured values already printed in the DOM.
 *
 * @package BusinessBuilderCore
 */
( function () {
	'use strict';

	var RADIO_NAME = 'bb_payment_gateway';

	/**
	 * Wire copy buttons inside one instructions block.
	 *
	 * @param {HTMLElement} block Instructions block.
	 */
	function initCopy( block ) {
	block.querySelectorAll( '[data-bb-copy]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var value = button.getAttribute( 'data-bb-copy' ) || '';
				var label = button.getAttribute( 'data-bb-copy-label' ) || 'Copied';
				var original = button.textContent;

				function done() {
					button.textContent = label;
					window.setTimeout( function () {
						button.textContent = original;
					}, 1600 );
				}

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( value ).then( done, done );
				} else {
					done();
				}
			} );
	} );
	}

	/**
	 * Wire one instructions block + the form that contains it.
	 *
	 * @param {HTMLElement} block Instructions block.
	 */
	function initBlock( block ) {
		var form = block.closest( 'form' );

		if ( ! form ) {
			return;
	}

		var gatewayId = block.getAttribute( 'data-bb-manual-gateway' ) || '';

		if ( '' === gatewayId ) {
			return;
	}

	initCopy( block );

		var referenceInput = block.querySelector( '[data-bb-manual-field]' );
		var errorEl = block.querySelector( '[data-bb-manual-error]' );

		function selectedGateway() {
			var checked = form.querySelector( 'input[name="' + RADIO_NAME + '"]:checked' );

			return checked ? checked.value : '';
	}

		function isActive() {
			return selectedGateway() === gatewayId;
	}

		function setVisible( visible ) {
			if ( visible ) {
				block.removeAttribute( 'hidden' );
				block.setAttribute( 'aria-hidden', 'false' );
			} else {
				block.setAttribute( 'hidden', 'hidden' );
				block.setAttribute( 'aria-hidden', 'true' );

				/* Clear the error so a hidden block never blocks submit. */
				if ( errorEl ) {
					errorEl.textContent = '';
				}

				if ( referenceInput ) {
					referenceInput.removeAttribute( 'aria-invalid' );
				}
			}
	}

		function apply() {
			setVisible( isActive() );
	}

	form.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.name === RADIO_NAME ) {
				apply();
			}
	} );

		if ( referenceInput ) {
			referenceInput.addEventListener( 'input', function () {
				if ( referenceInput.getAttribute( 'aria-invalid' ) === 'true' && referenceInput.value.trim() !== '' ) {
					referenceInput.removeAttribute( 'aria-invalid' );
					if ( errorEl ) {
						errorEl.textContent = '';
					}
				}
			} );
	}

	form.addEventListener( 'submit', function ( event ) {
			if ( ! isActive() ) {
				return;
			}

			if ( ! referenceInput ) {
				return;
			}

			if ( referenceInput.value.trim() === '' ) {
				event.preventDefault();
				referenceInput.setAttribute( 'aria-invalid', 'true' );

				if ( errorEl ) {
					errorEl.textContent = 'Please enter the transaction reference for this manual payment.';
				}

				referenceInput.focus();
				referenceInput.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
	} );

	apply();
	}

	/**
	 * Boot every manual instructions block on the page.
	 */
	function init() {
	document.querySelectorAll( '[data-bb-manual]' ).forEach( initBlock );
	}

	if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
	} else {
	init();
	}
} )();
