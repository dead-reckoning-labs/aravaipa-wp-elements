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

		var counters = document.querySelectorAll( '[data-arv-results-countdown]' );

		for ( var k = 0; k < counters.length; k++ ) {
			countdown( counters[ k ] );
		}
	}

	/**
	 * Tick down to the first race of the week, then hand over to the live
	 * marker sitting hidden beside it.
	 *
	 * Both states are already in the page, rendered by PHP, so this only
	 * ever swaps which one is hidden. Nothing here decides whether a race
	 * is live: it only notices that the moment PHP named has arrived, which
	 * means a page left open overnight becomes correct on its own.
	 */
	function countdown( el ) {
		var target = Date.parse( el.getAttribute( 'data-arv-results-countdown' ) );
		if ( isNaN( target ) ) {
			return;
		}

		var value = el.querySelector( '[data-arv-results-countdown-value]' );
		var live = el.parentNode ? el.parentNode.querySelector( '[data-arv-results-live]' ) : null;
		var timer = null;

		function tick() {
			var left = target - Date.now();

			if ( left <= 0 ) {
				el.hidden = true;
				if ( live ) {
					live.hidden = false;
				}
				if ( timer ) {
					window.clearInterval( timer );
				}
				return;
			}

			if ( ! value ) {
				return;
			}

			var s = Math.floor( left / 1000 );
			var d = Math.floor( s / 86400 );
			var h = Math.floor( ( s % 86400 ) / 3600 );
			var m = Math.floor( ( s % 3600 ) / 60 );

			// Days and hours until the last day, then hours and minutes.
			// Seconds are noise at this range and would redraw the line
			// sixty times a minute to say nothing.
			value.textContent = d > 0
				? d + ( 1 === d ? ' day ' : ' days ' ) + h + ( 1 === h ? ' hour' : ' hours' )
				: h + ( 1 === h ? ' hour ' : ' hours ' ) + m + ( 1 === m ? ' minute' : ' minutes' );
		}

		tick();
		timer = window.setInterval( tick, 30000 );
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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
