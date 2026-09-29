/**
 * Business Builder Core - Admin: Font Awesome icon picker (Phase 23 §18)
 * ----------------------------------------------------------------------
 * Upgrades any `<select>` carrying an icon option list into a searchable, visual
 * picker.
 *
 * WHY IT IS AN ENHANCEMENT AND NOT A FIELD TYPE
 * ---------------------------------------------
 * The icon field is a normal `select`, so it already works in the builder AND in
 * repeaters with no change to `page-admin.js`. This file only ADDS a nicer way to
 * choose:
 *
 *   - the `<select>` stays in the DOM as the single value holder, so saving is
 *     unchanged and the control still works with JavaScript disabled;
 *   - the grid writes through to the select and fires `change`, so existing
 *     listeners keep working;
 *   - it re-scans after DOM mutations, so a repeater row added later is upgraded
 *     too.
 *
 * Dependency-free by design: no jQuery, no build step, no framework.
 */
( function () {
	'use strict';

	var config = window.BBIconPicker || {};
	var labels = config.labels || {};
	var names = config.names || {};

	/**
	 * The Font Awesome name for a stored slug.
	 *
	 * A slug such as `chart-bar` renders as `chart-column`, so the preview MUST use
	 * the map the server publishes rather than the slug itself; falling back to the
	 * slug keeps an unmapped (pack-added) icon working.
	 *
	 * @param {string} slug Icon slug.
	 * @return {string} Font Awesome icon name.
	 */
	function faName( slug ) {
		return names[ slug ] || slug;
	}

	/**
	 * Read an icon select's options, grouped: category => [ { value, label } ].
	 *
	 * @param {HTMLSelectElement} select The icon select.
	 * @return {Object} Grouped options.
	 */
	function readGroups( select ) {
		var groups = {};
		var options = select.options;

		for ( var i = 0; i < options.length; i++ ) {
			var option = options[ i ];

			if ( '' === option.value ) {
				continue;
			}

			/* The server labels each option "Category — Label". */
			var parts = String( option.textContent || '' ).split( '\u2014' );
			var category = parts.length > 1 ? parts[ 0 ].trim() : 'General';
			var label = parts.length > 1 ? parts.slice( 1 ).join( '\u2014' ).trim() : option.value;

			if ( ! groups[ category ] ) {
				groups[ category ] = [];
			}

			groups[ category ].push( { value: option.value, label: label } );
		}

		return groups;
	}

	/**
	 * Repaint the selection state of a grid.
	 *
	 * @param {HTMLElement} grid  Tile container.
	 * @param {string}      value Selected icon slug.
	 */
	function paint( grid, value ) {
		var tiles = grid.querySelectorAll( '.bb-icon-picker-tile' );

		for ( var i = 0; i < tiles.length; i++ ) {
			var chosen = tiles[ i ].dataset.bbIconValue === value;

			tiles[ i ].classList.toggle( 'is-selected', chosen );

			if ( chosen ) {
				tiles[ i ].setAttribute( 'aria-selected', 'true' );
			} else {
				tiles[ i ].removeAttribute( 'aria-selected' );
			}
		}
	}

	/**
	 * Write a value into the select and notify existing listeners.
	 *
	 * @param {HTMLSelectElement} select The icon select.
	 * @param {string}            value  Icon slug ('' clears it).
	 * @param {HTMLElement}       grid   Tile container.
	 */
	function choose( select, value, grid ) {
		select.value = value;

		/* `change` is dispatched so any existing handler sees the update. */
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		paint( grid, value );
	}

	/**
	 * Build the picker UI for one select.
	 *
	 * @param {HTMLSelectElement} select The icon select.
	 */
	function enhance( select ) {
		if ( '1' === select.dataset.bbIconEnhanced ) {
			return;
		}

		select.dataset.bbIconEnhanced = '1';

		var groups = readGroups( select );

		var panel = document.createElement( 'div' );
		panel.className = 'bb-icon-picker';

		var search = document.createElement( 'input' );
		search.type = 'search';
		search.className = 'bb-icon-picker-search';
		search.placeholder = labels.search || 'Search icons…';
		search.setAttribute( 'aria-label', labels.search || 'Search icons' );

		var grid = document.createElement( 'div' );
		grid.className = 'bb-icon-picker-grid';
		grid.setAttribute( 'role', 'listbox' );
		grid.setAttribute( 'aria-label', labels.search || 'Icons' );

		var empty = document.createElement( 'p' );
		empty.className = 'bb-icon-picker-empty';
		empty.hidden = true;
		empty.textContent = labels.empty || 'No icon matches that search.';

		/**
		 * Create one icon tile.
		 *
		 * @param {Object} icon { value, label }.
		 * @return {HTMLButtonElement} Tile.
		 */
		function makeTile( icon ) {
			var tile = document.createElement( 'button' );
			tile.type = 'button';
			tile.className = 'bb-icon-picker-tile';
			tile.title = icon.label;
			tile.dataset.bbIconValue = icon.value;
			tile.setAttribute( 'role', 'option' );

			if ( icon.value === select.value ) {
				tile.classList.add( 'is-selected' );
				tile.setAttribute( 'aria-selected', 'true' );
			}

			var glyph = document.createElement( 'i' );
			glyph.className = 'fa-solid fa-' + faName( icon.value );
			glyph.setAttribute( 'aria-hidden', 'true' );

			var name = document.createElement( 'span' );
			name.textContent = icon.label;

			tile.appendChild( glyph );
			tile.appendChild( name );

			tile.addEventListener( 'click', function () {
				choose( select, icon.value, grid );
			} );

			return tile;
		}

		/**
		 * Render the tile grid, optionally filtered.
		 *
		 * @param {string} term Search term.
		 */
		function render( term ) {
			grid.textContent = '';

			var needle = String( term || '' ).toLowerCase();
			var shown = 0;

			Object.keys( groups ).forEach( function ( category ) {
				var matches = groups[ category ].filter( function ( icon ) {
					if ( '' === needle ) {
						return true;
					}

					return icon.value.indexOf( needle ) !== -1
						|| icon.label.toLowerCase().indexOf( needle ) !== -1
						|| category.toLowerCase().indexOf( needle ) !== -1;
				} );

				if ( ! matches.length ) {
					return;
				}

				var heading = document.createElement( 'span' );
				heading.className = 'bb-icon-picker-category';
				heading.textContent = category;
				grid.appendChild( heading );

				matches.forEach( function ( icon ) {
					grid.appendChild( makeTile( icon ) );
					shown++;
				} );
			} );

			empty.hidden = shown > 0;
		}

		/* A "no icon" tile, so a choice can be cleared from the grid. */
		var clear = document.createElement( 'button' );
		clear.type = 'button';
		clear.className = 'bb-icon-picker-tile bb-icon-picker-none';
		clear.textContent = labels.none || 'No icon';

		clear.addEventListener( 'click', function () {
			choose( select, '', grid );
		} );

		search.addEventListener( 'input', function () {
			render( search.value );
		} );

		panel.appendChild( search );
		panel.appendChild( clear );
		panel.appendChild( grid );
		panel.appendChild( empty );

		/*
		 * The select is hidden but KEPT: it is the value holder, the save path and
		 * the no-JavaScript fallback.
		 */
		select.classList.add( 'bb-icon-picker-source' );
		select.parentNode.insertBefore( panel, select.nextSibling );

		render( '' );
	}

	/**
	 * Find and enhance every icon select in a subtree.
	 *
	 * @param {ParentNode} scope Root node.
	 */
	function scan( scope ) {
		var selects = scope.querySelectorAll( 'select[data-bb-icon-picker]' );

		for ( var i = 0; i < selects.length; i++ ) {
			enhance( selects[ i ] );
		}
	}

	function init() {
		scan( document );

		/*
		 * The builder rebuilds repeater rows as the user adds them, so a newly
		 * inserted select must be upgraded too. The observer does no work unless a
		 * node is actually added.
		 */
		if ( ! ( 'MutationObserver' in window ) ) {
			return;
		}

		var observer = new MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				var added = mutations[ i ].addedNodes;

				for ( var j = 0; j < added.length; j++ ) {
					var node = added[ j ];

					if ( 1 !== node.nodeType ) {
						continue;
					}

					if ( node.matches && node.matches( 'select[data-bb-icon-picker]' ) ) {
						enhance( node );
					}

					scan( node );
				}
			}
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
