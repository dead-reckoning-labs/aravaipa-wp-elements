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

		var clocks = document.querySelectorAll( '[data-arv-results-clock]' );

		for ( var k = 0; k < clocks.length; k++ ) {
			clock( clocks[ k ] );
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
		var clear = root.querySelector( '[data-arv-results-clear]' );
		if ( ! list ) {
			return;
		}

		var groups = list.querySelectorAll( '[data-arv-results-race]' );

		// Search narrow enough that every match can be opened without
		// burying the page. Someone who typed a race name wants that race's
		// history, and leaving it behind a second click is asking them to
		// say what they want twice. Above this many matches the query is
		// still a browse, and opening them all would be pages of it.
		var AUTO_OPEN_MAX = 5;

		// Only ever re-closes what this opened. Anything the reader opened
		// by hand is theirs and survives a search and a clear.
		var autoOpened = [];

		function setOpen( el, open ) {
			var details = el.querySelector( 'details' );
			if ( ! details ) {
				return;
			}
			if ( open ) {
				if ( ! details.open ) {
					details.open = true;
					autoOpened.push( details );
				}
				return;
			}
			var at = autoOpened.indexOf( details );
			if ( at !== -1 ) {
				details.open = false;
				autoOpened.splice( at, 1 );
			}
		}

		function apply() {
			var q = input.value.trim().toLowerCase();
			var shown = 0;
			var hits = [];

			for ( var i = 0; i < groups.length; i++ ) {
				var name = groups[ i ].getAttribute( 'data-arv-results-race' ) || '';
				var hit = '' === q || name.indexOf( q ) !== -1;
				groups[ i ].hidden = ! hit;
				if ( hit ) {
					shown++;
					hits.push( groups[ i ] );
				}
			}

			var expand = '' !== q && shown > 0 && shown <= AUTO_OPEN_MAX;

			for ( var k = 0; k < groups.length; k++ ) {
				setOpen( groups[ k ], expand && ! groups[ k ].hidden );
			}

			if ( clear ) {
				clear.hidden = '' === input.value;
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
				count.textContent = shown + ( 1 === shown ? ' race' : ' races' )
					+ ( expand ? ', every edition shown' : '' );
			}
		}

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				input.value = '';
				apply();
				input.focus();
			} );
		}

		input.addEventListener( 'input', apply );

		// A browser restoring a typed value on back/forward would otherwise
		// show the box filled in and the list unfiltered.
		if ( '' !== input.value ) {
			apply();
		}
	}

	/**
	 * One race's own clock: counting down, then counting up, then done.
	 *
	 * Every state is already in the page, rendered by PHP for whenever the
	 * page was built. This only ever changes which one is showing and keeps
	 * the numbers moving, so a page left open across a start or a cutoff
	 * becomes correct without a reload, and a reader with no JavaScript
	 * still sees the right state for when they loaded it.
	 */
	function clock( root ) {
		var start = Date.parse( root.getAttribute( 'data-arv-start' ) );
		var cutoff = Date.parse( root.getAttribute( 'data-arv-cutoff' ) );

		if ( isNaN( start ) ) {
			return;
		}

		var soon = root.querySelector( '[data-arv-results-countdown]' );
		var soonValue = root.querySelector( '[data-arv-results-countdown-value]' );
		var elapsed = root.querySelector( '[data-arv-results-elapsed]' );
		var elapsedValue = root.querySelector( '[data-arv-results-elapsed-value]' );
		var done = root.querySelector( '.arv-results__done' );

		// The pulsing marker lives beside the race name, not in here.
		var row = root.closest( '.arv-results__week-race' );
		var live = row ? row.querySelector( '[data-arv-results-live]' ) : null;

		function pad( n ) {
			return n < 10 ? '0' + n : String( n );
		}

		function span( ms ) {
			var s = Math.floor( ms / 1000 );
			var d = Math.floor( s / 86400 );
			var h = Math.floor( ( s % 86400 ) / 3600 );
			var m = Math.floor( ( s % 3600 ) / 60 );
			return ( d > 0 ? d + 'd ' : '' ) + pad( h ) + ':' + pad( m ) + ':' + pad( s % 60 );
		}

		function show( which ) {
			if ( soon ) {
				soon.hidden = 'soon' !== which;
			}
			if ( elapsed ) {
				elapsed.hidden = 'live' !== which;
			}
			if ( done ) {
				done.hidden = 'done' !== which;
			}
			if ( live ) {
				live.hidden = 'live' !== which;
			}
		}

		function tick() {
			var now = Date.now();

			if ( ! isNaN( cutoff ) && now >= cutoff ) {
				show( 'done' );
				window.clearInterval( timer );
				return;
			}

			if ( now >= start ) {
				show( 'live' );
				if ( elapsedValue ) {
					elapsedValue.textContent = span( now - start );
				}
				return;
			}

			show( 'soon' );
			if ( soonValue ) {
				soonValue.textContent = span( start - now );
			}
		}

		tick();
		// Every second: both a countdown and an elapsed clock are the kind
		// of thing that looks broken if it does not move.
		var timer = window.setInterval( tick, 1000 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
