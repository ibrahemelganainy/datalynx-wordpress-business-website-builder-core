/**
 * Business Builder Core — Status Lookup behaviour.
 *
 * Drives templates/status-lookup.php:
 *   1. Client-side validation (reference + phone both required).
 *   2. AJAX POST to admin-ajax (action + nonce).
 *   3. Renders the verified status card (table + timeline + receipt link).
 *
 * Every server value is escaped before insertion, so the backend response
 * cannot inject markup. No secrets are ever present here.
 *
 * @package BusinessBuilderCore
 */
( function () {
	'use strict';

	/**
	 * Escape a value for safe HTML text insertion.
	 *
	 * @param {*} value Raw value.
	 * @return {string}
	 */
	function esc( value ) {
		var text = ( value === null || value === undefined ) ? '' : String( value );
		return text
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/**
	 * One label/value table row (both escaped).
	 *
	 * @param {string} label Label.
	 * @param {string} value Value.
	 * @return {string}
	 */
	function row( label, value ) {
		return '<tr><th>' + esc( label ) + '</th><td>' + esc( value ) + '</td></tr>';
	}

	/**
	 * Render the lookup result card for a verified record.
	 *
	 * @param {Object}      data     Server data.
	 * @param {HTMLElement} target   Result container.
	 * @param {boolean}     showPay  Show the payment block.
	 * @param {boolean}     showRcpt Show the receipt link.
	 */
	function renderResult( data, target, showPay, showRcpt ) {
	var isConsult = ( 'appointment' !== data.type );
	var title     = isConsult ? 'Consultation Details' : 'Appointment Details';

	var cfg = window.BBLookup || {};
	var cfgLabelNewSearch = cfg.newSearch || 'New Search';
	var cfgLabelClose     = cfg.closeLabel || 'Close';

		var rows = '';

	rows += row( isConsult ? 'Consultation Number' : 'Appointment Number', data.reference );

		if ( data.customer ) {
			rows += row( 'Customer', data.customer );
	}

		if ( isConsult && data.practice_area ) {
			rows += row( 'Practice Area', data.practice_area );
	}

		if ( ! isConsult && data.lawyer ) {
			rows += row( 'Lawyer', data.lawyer );
	}

		if ( ! isConsult && data.date ) {
			rows += row( 'Date', data.date );
	}

		if ( ! isConsult && data.time ) {
			rows += row( 'Time', data.time );
	}

	rows += row( isConsult ? 'Consultation Status' : 'Appointment Status', data.status );

		if ( data.submitted ) {
			rows += row( 'Submitted', data.submitted );
	}

		var payment = data.payment || {};

		if ( showPay ) {
			if ( payment.status_label ) {
				rows += row( 'Payment Status', payment.status_label );
			}
			if ( payment.gateway ) {
				rows += row( 'Payment Method', payment.gateway );
			}
			if ( payment.payment_ref ) {
				rows += row( 'Payment Reference', payment.payment_ref );
			}
			if ( payment.gateway_ref ) {
				rows += row( 'Gateway Transaction', payment.gateway_ref );
			}
				if ( payment.amount ) {
			var amount = payment.currency ? ( payment.amount + String.fromCharCode( 32 ) + payment.currency ) : payment.amount;
			rows += row( 'Amount', amount );
			}
			if ( payment.date ) {
				rows += row( 'Payment Date', payment.date );
			}
	}

		var timeline = '';

		if ( data.timeline && data.timeline.length ) {
			timeline = '<ul class="bb-lookup-timeline">';
			for ( var i = 0; i < data.timeline.length; i++ ) {
				var step = data.timeline[ i ];
				var done = step.done ? ' is-done' : '';
				var tick = step.done ? '&#10003;' : '';
				timeline += '<li class="bb-lookup-step' + done + '">' +
					'<span class="bb-lookup-step-dot">' + tick + '</span>' +
					'<span>' + esc( step.label ) + '</span>' +
					'</li>';
			}
			timeline += '</ul>';
	}

			var receipt = '';
		var paid    = ( payment.is_paid === true );
		var refTxn  = payment.receipt_ref ? payment.receipt_ref : '';

		/*
		 * The receipt opens INLINE on this page (no navigation). The button
		 * carries the public reference; the shared receipt-modal script
		 * fetches and shows it in the modal on the same screen.
		 */
		if ( showRcpt && paid && refTxn ) {
			receipt = '<p class="bb-lookup-receipt"><button type="button" class="bb-primary-button" data-bb-receipt-open data-bb-receipt-ref="' +
			esc( refTxn ) + '">&#128196; View Receipt</button></p>';
		}

		var badgeClass = paid ? 'is-paid' : 'is-pending';
		var badgeText  = paid ? 'Paid' : ( data.status || '' );

			target.innerHTML =
		'<div class="bb-lookup-result-head">' +
		'<h3 class="bb-lookup-result-title">' + esc( title ) + '</h3>' +
			'<div class="bb-lookup-result-head-right">' +
		'<span class="bb-lookup-badge ' + badgeClass + '">' + esc( badgeText ) + '</span>' +
		'</div>' +
		'</div>' +
		'<table class="bb-lookup-table"><tbody>' + rows + '</tbody></table>' +
			timeline +
			receipt +
		'<div class="bb-lookup-result-actions">' +
		'<button type="button" class="bb-primary-button bb-lookup-new-search" data-bb-lookup-new>' + esc( cfgLabelNewSearch ) + '</button>' +
		'<button type="button" class="bb-lookup-result-close-text" data-bb-lookup-close>' + esc( cfgLabelClose ) + '</button>' +
		'</div>';

			target.removeAttribute( 'hidden' );
		}

	/**
	 * Wire one lookup widget.
	 *
	 * @param {HTMLElement} root Lookup container.
	 */
	function initLookup( root ) {
		var form    = root.querySelector( '[data-bb-lookup-form]' );
		var target  = root.querySelector( '[data-bb-lookup-result]' );
		var notice  = root.querySelector( '[data-bb-lookup-notice]' );
		var refIn   = root.querySelector( '[data-bb-lookup-reference]' );
		var phoneIn = root.querySelector( '[data-bb-lookup-phone]' );

		if ( ! form || ! target ) {
			return;
	}

		var button   = form.querySelector( '.bb-lookup-button' );
		var action   = root.getAttribute( 'data-bb-lookup-action' ) || 'bb_status_lookup';
		var nonce    = root.getAttribute( 'data-bb-lookup-nonce' ) || '';
		var showPay  = ( '1' === root.getAttribute( 'data-bb-show-payment' ) );
		var showRcpt = ( '1' === root.getAttribute( 'data-bb-show-receipt' ) );

	/**
		 * Set a field's inline error.
		 *
		 * @param {HTMLElement} input   Input.
		 * @param {string}      message Message.
		 */
		function setError( input, message ) {
			if ( ! input ) {
				return;
			}

			var key = ( input === refIn ) ? 'reference' : 'phone';
			var err = root.querySelector( '[data-bb-lookup-error="' + key + '"]' );

			if ( message ) {
				input.setAttribute( 'aria-invalid', 'true' );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}

			if ( err ) {
				err.textContent = message;
				err.classList.toggle( 'is-visible', '' !== message );
			}
	}

	/**
		 * Show a top-level notice.
		 *
		 * @param {string} message Message.
		 */
		function setNotice( message ) {
			if ( ! notice ) {
				return;
			}

			notice.textContent = message;
			notice.classList.toggle( 'is-error', '' !== message );

			if ( '' !== message ) {
				notice.removeAttribute( 'hidden' );
			} else {
				notice.setAttribute( 'hidden', 'hidden' );
			}
	}

		/*
	 * Result actions: a close (x) button hides the result and a "New
	 * Search" button resets the widget cleanly so the customer can run
	 * another lookup without reloading the page. Delegated on the result
	 * container so it works for every rendered result.
	 */
	function closeResult() {
		target.setAttribute( 'hidden', 'hidden' );
		target.innerHTML = '';
	}

	function startNewSearch() {
	closeResult();
		setNotice( '' );

	if ( refIn ) {
	refIn.value = '';
		setError( refIn, '' );
	}

	if ( phoneIn ) {
	phoneIn.value = '';
		setError( phoneIn, '' );
	}

	/*
	 * Bring the (now-empty) form back into view and focus the first
	 * field so a new query is one keystroke away.
	 */
	form.scrollIntoView( { behavior: 'smooth', block: 'center' } );

	if ( refIn ) {
	refIn.focus();
	}
	}

		target.addEventListener( 'click', function ( event ) {
	var el = event.target.closest ? event.target.closest( '[data-bb-lookup-close], [data-bb-lookup-new]' ) : null;

	if ( ! el ) {
	return;
	}

		event.preventDefault();

	if ( el.hasAttribute( 'data-bb-lookup-new' )) {
	startNewSearch();
	return;
	}

	closeResult();
	} );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		setNotice( '' );

			var reference = refIn ? refIn.value.trim() : '';
			var phone     = phoneIn ? phoneIn.value.trim() : '';

			var refOk   = ( '' !== reference );
			var phoneOk = ( '' !== phone );

			setError( refIn, refOk ? '' : 'Please enter your reference number.' );
			setError( phoneIn, phoneOk ? '' : 'Please enter your phone number.' );

			if ( ! refOk ) {
				refIn.focus();
				return;
			}

			if ( ! phoneOk ) {
				phoneIn.focus();
				return;
			}

			var type    = 'consultation';
			var checked = root.querySelector( 'input[name="bb_lookup_type"]:checked' );

			if ( checked ) {
				type = checked.value;
			}

			var params = new URLSearchParams();
			params.append( 'action', action );
			params.append( 'nonce', nonce );
			params.append( 'type', type );
			params.append( 'reference', reference );
			params.append( 'phone', phone );

			if ( button ) {
				button.classList.add( 'is-loading' );
				button.setAttribute( 'disabled', 'disabled' );
			}

			var endpoint = ( window.BBLookup && window.BBLookup.ajaxUrl ) ? window.BBLookup.ajaxUrl : '/wp-admin/admin-ajax.php';

			fetch( endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					if ( payload && payload.success && payload.data ) {
						target.setAttribute( 'hidden', 'hidden' );
						renderResult( payload.data, target, showPay, showRcpt );
						target.scrollIntoView( { behavior: 'smooth', block: 'center' } );
						return;
					}

					var message = ( payload && payload.data && payload.data.message )
						? payload.data.message
						: 'We could not process your request at this time. Please try again later.';

					setNotice( message );
				} )
				.catch( function () {
					setNotice( 'We could not process your request at this time. Please try again later.' );
				} )
				.then( function () {
					if ( button ) {
						button.classList.remove( 'is-loading' );
						button.removeAttribute( 'disabled' );
					}
				} );
	} );

	/* Clear the field error as soon as the user edits. */
		if ( refIn ) {
			refIn.addEventListener( 'input', function () {
				if ( 'true' === refIn.getAttribute( 'aria-invalid' ) ) {
					setError( refIn, '' );
				}
			} );
	}

		if ( phoneIn ) {
			phoneIn.addEventListener( 'input', function () {
				if ( 'true' === phoneIn.getAttribute( 'aria-invalid' ) ) {
					setError( phoneIn, '' );
				}
			} );
	}
	}

	/**
	 * Boot every lookup widget on the page.
	 */
	function init() {
		var nodes = document.querySelectorAll( '[data-bb-lookup]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			initLookup( nodes[ i ] );
	}
	}

	if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
	} else {
	init();
	}
} )();
