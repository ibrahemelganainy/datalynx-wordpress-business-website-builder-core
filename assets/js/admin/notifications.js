/**
 * Business Builder Core — Notification centre live behaviour.
 *
 * Lightweight polling: every 20s it asks admin-ajax.php for the current
 * unread count plus any notifications newer than the last seen timestamp.
 * Only the badge and any NEW items are updated — the dashboard is never
 * fully reloaded. All data comes back already escaped by the server and is
 * inserted via a safe builder (no innerHTML of untrusted strings).
 *
 * @package BusinessBuilderCore
 */
( function () {
	'use strict';

	var cfg = window.BBNotifications || {};
	var POLL_MS = 20000;

	if ( ! cfg.ajaxUrl || ! cfg.action || ! cfg.nonce ) {
		return;
	}

	var badge = document.querySelector( '[data-bb-nc-count]' );
	var timeline = document.querySelector( '[data-bb-nc-timeline]' );

	if ( ! badge || ! timeline ) {
		return;
	}

	var since = parseInt( timeline.getAttribute( 'data-bb-nc-since' ), 10 ) || 0;

	/**
	 * Build a notification element safely (textContent, never innerHTML).
	 *
	 * @param {Object} item Notification payload.
	 * @return {HTMLElement}
	 */
	function buildItem( item ) {
		var article = document.createElement( 'article' );
	article.className = 'bb-nc-item bb-nc-' + ( item.category || 'system' ) + ' is-unread';
	article.setAttribute( 'data-bb-nc-item', item.id || '' );

		var body = document.createElement( 'div' );
	body.className = 'bb-nc-item-body';

		var head = document.createElement( 'div' );
	head.className = 'bb-nc-item-head';

		var title = document.createElement( 'strong' );
	title.className = 'bb-nc-item-title';
	title.textContent = item.subject || '';
	head.appendChild( title );

		var dot = document.createElement( 'span' );
	dot.className = 'bb-nc-dot';
	head.appendChild( dot );

		var time = document.createElement( 'time' );
	time.className = 'bb-nc-item-time';
	time.textContent = item.timeago || '';
	head.appendChild( time );

	body.appendChild( head );

		if ( item.message ) {
			var msg = document.createElement( 'p' );
			msg.className = 'bb-nc-item-msg';
			msg.textContent = item.message;
			body.appendChild( msg );
	}

		var meta = document.createElement( 'div' );
	meta.className = 'bb-nc-item-meta';

		if ( item.reference ) {
			var ref = document.createElement( 'code' );
			ref.className = 'bb-nc-ref';
			ref.textContent = item.reference;
			meta.appendChild( ref );
	}

	body.appendChild( meta );

		var actions = document.createElement( 'div' );
	actions.className = 'bb-nc-item-actions';

		if ( item.url ) {
			var link = document.createElement( 'a' );
			link.className = 'bb-btn bb-btn-primary';
			link.href = item.url;
			link.textContent = 'View';
			actions.appendChild( link );
	}

	body.appendChild( actions );
	article.appendChild( body );

		return article;
	}

	/**
	 * Update the unread badge.
	 *
	 * @param {number} count Unread count.
	 */
	function setBadge( count ) {
	badge.textContent = String( count );

	badge.classList.toggle( 'is-active', count > 0 );
	}

	/**
	 * Poll the server for updates.
	 */
	function poll() {
			var params = [
		'action=' + encodeURIComponent( cfg.action ),
		'nonce=' + encodeURIComponent( cfg.nonce ),
		'since=' + encodeURIComponent( String( since ) ),
		'_=' + Date.now()
		];

		var url = cfg.ajaxUrl + '?' + params.join( '&' );

		fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}

				var data = payload.data;

				if ( typeof data.unread === 'number' ) {
					setBadge( data.unread );
				}

				if ( typeof data.server === 'number' ) {
					since = data.server;
				}

				if ( Array.isArray( data.items ) && data.items.length > 0 ) {
					data.items.forEach( function ( item ) {
						timeline.insertBefore( buildItem( item ), timeline.firstChild );
					} );
				}
			} )
			.catch( function () {
				/* Network hiccup: keep the current state and retry next cycle. */
			} );
	}

	window.setInterval( poll, POLL_MS );
} )();
