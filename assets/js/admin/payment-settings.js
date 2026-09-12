/**
 * Payment Settings behaviour.
 *
 * Progressive enhancement for the Payment Management screen:
 *   1. Live gateway toggles  - enabling/disabling a gateway updates its
 *      card state and status badge immediately, with NO page reload.
 *   2. Expandable config     - show/hide a gateway's config fields inline.
 *   3. Currency combobox     - enhances the currency select into a
 *      searchable combobox. The select stays the submitted field, so
 *      the form still works with JavaScript disabled.
 *   4. Unsaved-change guard  - warns before leaving with pending edits.
 *
 * No secrets are read, stored or transmitted by this script.
 *
 * @package BusinessBuilderCore
 */
( function () {
	'use strict';

	function init() {
		var root = document.querySelector( '.bb-payments' );

		if ( ! root ) {
			return;
	}

	initGatewayToggles( root );
	initConfigToggles( root );
	initCurrencyCombobox( root );
	initUnsavedGuard( root );
	}

	function initGatewayToggles( root ) {
		var toggles = root.querySelectorAll( '[data-bb-gateway-toggle]' );

		for ( var i = 0; i < toggles.length; i++ ) {
			applyCardState( toggles[ i ] );

			toggles[ i ].addEventListener( 'change', onChange );
	}

		function onChange( event ) {
			applyCardState( event.currentTarget );
	}
	}

	function applyCardState( input ) {
		var card = input.closest( '.bb-gateway-card' );
		var enabled = input.checked;

		if ( ! card ) {
			return;
	}

	card.classList.toggle( 'is-enabled', enabled );
	card.classList.toggle( 'is-disabled', ! enabled );

		var stateText = card.querySelector( '[data-bb-toggle-state]' );

		if ( stateText ) {
			stateText.textContent = enabled ? 'On' : 'Off';
	}

		var stateBadge = card.querySelector( '[data-bb-badge-state]' );

		if ( stateBadge ) {
			stateBadge.textContent = enabled
				? ( stateBadge.getAttribute( 'data-on' ) || 'Enabled' )
				: ( stateBadge.getAttribute( 'data-off' ) || 'Disabled' );

			stateBadge.classList.toggle( 'bb-gtag-success', enabled );
			stateBadge.classList.toggle( 'bb-gtag-muted', ! enabled );
	}
	}

	function initConfigToggles( root ) {
		var buttons = root.querySelectorAll( '[data-bb-config-toggle]' );

		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].addEventListener( 'click', onToggle );

			var card = buttons[ i ].closest( '.bb-gateway-card' );

			var readyUnconfigured = card && card.classList.contains( 'is-enabled' ) && card.classList.contains( 'is-unconfigured' );

	if ( readyUnconfigured ) {
				buttons[ i ].click();
			}
	}

		function onToggle( event ) {
			var button = event.currentTarget;
			var config = button.closest( '[data-bb-config]' );

			if ( ! config ) {
				return;
			}

			var fields = config.querySelector( '.bb-gateway-fields' );

			if ( ! fields ) {
				return;
			}

			if ( fields.hasAttribute( 'hidden' )) {
				fields.removeAttribute( 'hidden' );
				button.setAttribute( 'aria-expanded', 'true' );
				config.classList.add( 'is-open' );
			} else {
				fields.setAttribute( 'hidden', '' );
				button.setAttribute( 'aria-expanded', 'false' );
				config.classList.remove( 'is-open' );
			}
	}
	}

	function initCurrencyCombobox( root ) {
		var wrapper = root.querySelector( '[data-bb-combobox]' );

		if ( ! wrapper ) {
			return;
	}

		var select = wrapper.querySelector( 'select' );
		var input = wrapper.querySelector( '.bb-combobox-input' );

		if ( ! select || ! input ) {
			return;
	}

		wrapper.classList.add( 'is-enhanced' );

		syncInputFromSelect( select, input );

		input.addEventListener( 'input', function () {
			filterOptions( select, input.value );
	} );

		input.addEventListener( 'blur', function () {
			syncInputFromSelect( select, input );
	} );

	select.addEventListener( 'change', function () {
			syncInputFromSelect( select, input );
	} );
	}

	function filterOptions( select, query ) {
		var needle = String( query ).trim().toLowerCase();

		for ( var i = 0; i < select.options.length; i++ ) {
			var option = select.options[ i ];
			var haystack = ( option.textContent || '' ).toLowerCase();

			option.hidden = ( needle !== '' && haystack.indexOf( needle ) === -1 );
	}

		var direct = findOptionByCode( select, query );

		if ( direct ) {
			select.value = direct.value;
	}
	}

	function syncInputFromSelect( select, input ) {
		var option = select.options[ select.selectedIndex ];

		if ( option ) {
			input.value = option.textContent.trim();
	}
	}

	function findOptionByCode( select, code ) {
		var target = String( code ).trim().toUpperCase();

		for ( var i = 0; i < select.options.length; i++ ) {
			if ( select.options[ i ].value.toUpperCase() === target ) {
				return select.options[ i ];
			}
	}

		return null;
	}

	function initUnsavedGuard( root ) {
		var form = root.querySelector( '.bb-payments-form' );
		var dirty = false;

		if ( ! form ) {
			return;
	}

	form.addEventListener( 'change', function () {
			dirty = true;
	} );

	form.addEventListener( 'submit', function () {
			dirty = false;
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! dirty ) {
				return;
			}

			var message = ( window.BBPaymentSettings && window.BBPaymentSettings.unsavedNotice )
				? window.BBPaymentSettings.unsavedNotice
				: '';

			if ( message ) {
				event.preventDefault();
				event.returnValue = message;
				return message;
			}
	} );
	}

	if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
	} else {
	init();
	}
} )();
