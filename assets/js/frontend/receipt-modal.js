/**
 * Business Builder Core — Inline receipt / invoice modal.
 *
 * Works on ANY page. A trigger with [data-bb-receipt-open] (optionally
 * carrying data-bb-receipt-ref) opens the receipt IN PLACE — the receipt is
 * fetched from admin-ajax (bb_receipt_inline, which reuses the same secure
 * public-reference validation as the receipt page) and rendered inside a
 * modal on the current screen. No navigation, no generic page.
 *
 * If a server-rendered receipt already exists on the page (the payment
 * success state), the same modal reveals it without a fetch.
 *
 * Accessibility: role=dialog + aria-modal, focus moved in/restored, Escape
 * and backdrop close, body scroll locked.
 *
 * @package BusinessBuilderCore
 */
( function () {
	'use strict';

	var cfg = window.BBReceipt || {};
	var ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
	var nonce = cfg.nonce || '';
	var action = cfg.action || 'bb_receipt_inline';

	var modal = null;
	var body = null;
	var lastFocus = null;

	/**
	 * Build the modal shell once (lazily).
	 *
	 * @return {HTMLElement}
	 */
	function ensureModal() {
		if ( modal ) {
			return modal;
	}

	modal = document.createElement( 'div' );
	modal.className = 'bb-receipt-modal';
	modal.id = 'bb-receipt-modal';
	modal.setAttribute( 'role', 'dialog' );
	modal.setAttribute( 'aria-modal', 'true' );
	modal.setAttribute( 'aria-label', cfg.label || 'Payment receipt' );
	modal.setAttribute( 'hidden', 'hidden' );

	modal.innerHTML =
			'<div class="bb-receipt-modal-backdrop" data-bb-receipt-close></div>' +
			'<div class="bb-receipt-modal-panel" role="document">' +
				'<button type="button" class="bb-receipt-modal-close" data-bb-receipt-close aria-label="Close">&times;</button>' +
				'<div class="bb-receipt-modal-body" data-bb-receipt-body></div>' +
			'</div>';

	document.body.appendChild( modal );

	body = modal.querySelector( '[data-bb-receipt-body]' );

	modal.querySelectorAll( '[data-bb-receipt-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', close );
	} );

	document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && ! modal.hasAttribute( 'hidden' )) {
			close();
			}
	} );

		return modal;
	}

	/**
	 * Open the modal with the given inner HTML.
	 *
	 * @param {string} html Receipt HTML.
	 */
	function openWith( html ) {
	ensureModal();

	body.innerHTML = html;
	lastFocus = document.activeElement;

	modal.removeAttribute( 'hidden' );
	document.body.classList.add( 'bb-receipt-open' );

	bindReceiptActions( body );

		var focusable = modal.querySelector( '[data-bb-receipt-print], button, a[href]' );

		if ( focusable ) {
			focusable.focus();
	}
	}

	/**
	 * Close the modal.
	 */
	function close() {
		if ( ! modal ) {
			return;
	}

	modal.setAttribute( 'hidden', 'hidden' );
	document.body.classList.remove( 'bb-receipt-open' );

		if ( lastFocus ) {
			lastFocus.focus();
	}
	}

	/**
	 * Bind Print / Save behaviour to a receipt fragment (idempotent).
	 *
	 * A fetched fragment's inline script does not run when injected, so the
	 * two buttons are re-bound here.
	 *
	 * @param {HTMLElement} root Container holding the receipt fragment.
	 */
	function bindReceiptActions( root ) {
		var printBtn = root.querySelector( '[data-bb-receipt-print]' );

		if ( printBtn && ! printBtn.getAttribute( 'data-bound' )) {
			printBtn.setAttribute( 'data-bound', '1' );
			printBtn.addEventListener( 'click', function () {
				window.print();
			} );
	}

		var dl = root.querySelector( '[data-bb-receipt-download]' );

		if ( dl && ! dl.getAttribute( 'data-bound' )) {
			dl.setAttribute( 'data-bound', '1' );

			dl.addEventListener( 'click', function () {
				var receipt = root.querySelector( '#bb-payment-receipt' ) || root;
				var ref = receipt.getAttribute( 'data-receipt-ref' ) || 'receipt';
				var css = document.querySelector( '.bb-receipt-style' );
				var style = css ? '<style>' + css.textContent + '</style>' : '';
				var clone = receipt.cloneNode( true );
				var actions = clone.querySelector( '.bb-receipt-actions' );

				if ( actions && actions.parentNode ) {
					actions.parentNode.removeChild( actions );
				}

				var doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt ' + ref + '</title>' + style + '</head><body>' + clone.outerHTML + '</body></html>';
				var blob = new Blob( [ doc ], { type: 'text/html' } );
				var a = document.createElement( 'a' );

				a.href = URL.createObjectURL( blob );
				a.download = 'receipt-' + ref + '.html';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( a.href );
			} );
	}
	}

	/**
	 * Open a receipt by public reference (fetches it inline).
	 *
	 * @param {string} reference Public reference.
	 */
	function openByRef( reference ) {
		if ( ! reference ) {
			return;
	}

	ensureModal();
	body.innerHTML = '<p class="bb-receipt-loading">' + ( cfg.loading || 'Loading receipt…' ) + '</p>';
	lastFocus = document.activeElement;
	modal.removeAttribute( 'hidden' );
	document.body.classList.add( 'bb-receipt-open' );

		var params = new URLSearchParams();
	params.append( 'action', action );
	params.append( 'nonce', nonce );
	params.append( 'reference', reference );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: params.toString()
	} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( payload ) {
				if ( payload && payload.success && payload.data && payload.data.html ) {
					openWith( payload.data.html );
					return;
				}

				body.innerHTML = '<p class="bb-receipt-loading">' + ( cfg.error || 'The receipt could not be loaded.' ) + '</p>';
			} )
			.catch( function () {
				body.innerHTML = '<p class="bb-receipt-loading">' + ( cfg.error || 'The receipt could not be loaded.' ) + '</p>';
			} );
	}

	/**
	 * Handle a click on any receipt opener — delegated, so it also works for
	 * buttons rendered LATER by other scripts (e.g. the status lookup result).
	 *
	 * @param {Event} event Click event.
	 */
	function onDocumentClick( event ) {
	var el = event.target.closest ? event.target.closest( '[data-bb-receipt-open]' ) : null;

	if ( ! el ) {
	return;
	}

		event.preventDefault();

	var ref = el.getAttribute( 'data-bb-receipt-ref' ) || '';
	var inline = document.querySelector( '#bb-payment-receipt' );

	if ( inline && ! ref ) {
	openWith( inline.outerHTML );
	return;
	}

	openByRef( ref );
	}

	/**
	 * Bind the delegated click handler (once).
	 */
	function bind() {
	if ( document.body.getAttribute( 'data-bb-receipt-delegated' )) {
	return;
	}

		document.body.setAttribute( 'data-bb-receipt-delegated', '1' );
		document.addEventListener( 'click', onDocumentClick );
	}

	window.BBReceiptOpen = openByRef;

	function init() {
		bind();

		/*
		 * Automatic post-payment opening. After a gateway return (or a manual
		 * submission) the ORIGINAL page loads with a success notice that
		 * carries [data-bb-receipt-auto] and the public reference. We open the
		 * receipt immediately — preferring an inline receipt already on the
		 * page, otherwise fetching it by reference. This is what makes the
		 * customer see their receipt WITHOUT navigating to a separate page.
		 *
		 * Guarded so it fires once per page load; the fetch is idempotent on
		 * the server (read-only), so a refresh simply re-opens the receipt.
		 */
		var auto = document.querySelector( '[data-bb-receipt-auto]' );

		if ( ! auto ) {
		return;
		}

		if ( auto.getAttribute( 'data-bb-receipt-auto-done' )) {
		return;
		}

			auto.setAttribute( 'data-bb-receipt-auto-done', '1' );

		var inline = document.querySelector( '#bb-payment-receipt' );

		if ( inline ) {
		openWith( inline.outerHTML );
		return;
		}

		var ref = auto.getAttribute( 'data-bb-receipt-ref' ) || '';

		if ( ref ) {
		openByRef( ref );
		}
		}

	if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
	} else {
	init();
	}
} )();
