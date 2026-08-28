/**
 * Search for the Aravaipa Results element.
 *
 * Filters the race list as you type. Everything is already in the HTML, so
 * this only ever hides and shows: nothing is fetched, and a search engine
 * sees the whole archive regardless of what anyone has typed.
 *
 * Matches on the race's own name only, not on the dates or the link labels,
 * because every race carries the same three link labels and "results" would
 * otherwise match all seventy-five of them.
 *
 * No dependencies, and it no-ops on any page without the element.
 */
( function () {
	'use strict';

	function init() {
		var inputs = document.querySelectorAll( '[data-arv-results-search]' );

		for ( var i = 0; i < inputs.length; i++ ) {
			wire( inputs[ i ] );
		}
	}

	function wire( input ) {
		// Scoped to this element's own wrapper, so two Results blocks on one
		// page filter themselves rather than each other.
		var root = input.closest( '.arv-results' );
		if ( ! root ) {
			return;
		}

		var list = root.querySelector( '[data-arv-results-list]' );
		var count = root.querySelector( '[data-arv-results-count]' );
		if ( ! list ) {
			return;
		}

		var groups = list.querySelectorAll( '[data-arv-results-race]' );

		function apply() {
			var q = input.value.trim().toLowerCase();
			var shown = 0;

			for ( var i = 0; i < groups.length; i++ ) {
				var name = groups[ i ].getAttribute( 'data-arv-results-race' ) || '';
				var hit = '' === q || name.indexOf( q ) !== -1;
				groups[ i ].hidden = ! hit;
				if ( hit ) {
					shown++;
				}
			}

			if ( ! count ) {
				return;
			}

			if ( '' === q ) {
				// Nothing has been asked yet, so there is nothing to report.
				count.textContent = '';
			} else if ( 0 === shown ) {
				count.textContent = 'No races match that.';
			} else {
				count.textContent = shown + ( 1 === shown ? ' race' : ' races' );
			}
		}

		input.addEventListener( 'input', apply );

		// A browser restoring a typed value on back/forward would otherwise
		// show the box filled in and the list unfiltered.
		if ( '' !== input.value ) {
			apply();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
