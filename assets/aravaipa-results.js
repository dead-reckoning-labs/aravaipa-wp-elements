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

		var count = root.querySelector( '[data-arv-results-count]' );
		var clear = root.querySelector( '[data-arv-results-clear]' );
		var yearButtons = root.querySelectorAll( '[data-arv-results-year]' );
		var panels = root.querySelectorAll( '[data-arv-results-year-panel]' );

		// The master page renders one full list per year, all but the
		// current one starting hidden; every other page (results-YYYY, and
		// the year attribute) has always rendered the single list it has.
		// Search stays scoped to whichever list is actually on screen. It
		// would be tempting to have a query reach across every year, but a
		// year panel is not a slice of one list, it is arv_results_filter_year
		// run fresh for that year, so a race that ran fifteen times exists as
		// fifteen separate cards, one per panel, each opening onto that
		// year's own "earlier editions". A search that crossed panels would
		// surface the same race up to fifteen times side by side, which is
		// the exact pile-up the disclosure exists to avoid, not a race
		// nobody could find.
		function activeList() {
			if ( 0 === panels.length ) {
				return root.querySelector( '[data-arv-results-list]' );
			}
			for ( var p = 0; p < panels.length; p++ ) {
				if ( ! panels[ p ].hidden ) {
					return panels[ p ].querySelector( '[data-arv-results-list]' );
				}
			}
			return null;
		}

		var list = activeList();
		if ( ! list ) {
			return;
		}

		var groups = list.querySelectorAll( '[data-arv-results-race]' );
		var months = list.querySelectorAll( '[data-arv-results-month]' );

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

			// A month heading with nothing under it reads as a month with no
			// races in it, which is a different and wrong claim. Hidden with
			// its races rather than left standing over the gap.
			for ( var m = 0; m < months.length; m++ ) {
				months[ m ].hidden = ! months[ m ].querySelector(
					'[data-arv-results-race]:not([hidden])'
				);
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

		function selectYear( year ) {
			for ( var p = 0; p < panels.length; p++ ) {
				panels[ p ].hidden = panels[ p ].getAttribute( 'data-arv-results-year-panel' ) !== year;
			}

			for ( var b = 0; b < yearButtons.length; b++ ) {
				var on = yearButtons[ b ].getAttribute( 'data-arv-results-year' ) === year;
				yearButtons[ b ].classList.toggle( 'is-on', on );
				yearButtons[ b ].setAttribute( 'aria-selected', on ? 'true' : 'false' );
			}

			// A query typed against 2026 means nothing once the reader has
			// switched to 2015; carrying it over would either hide a year that
			// has no reason to be empty or leave a stale count reading "3
			// races" under a list that was never searched.
			input.value = '';
			autoOpened.length = 0;

			// The closures above were built over the year that was active when
			// this input was first wired, so switching years has to repoint
			// every one of them at the panel that is visible now, not the one
			// that was visible on load.
			list = activeList();
			groups = list ? list.querySelectorAll( '[data-arv-results-race]' ) : [];
			months = list ? list.querySelectorAll( '[data-arv-results-month]' ) : [];

			apply();
		}

		for ( var y = 0; y < yearButtons.length; y++ ) {
			yearButtons[ y ].addEventListener( 'click', function () {
				selectYear( this.getAttribute( 'data-arv-results-year' ) );
			} );
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

		// The pulsing marker lives beside the race name, not in here, so the
		// clock has to look up to whatever row holds both. Two things use
		// this clock now: the race week block, whose row is a list item, and
		// the live page's bar, which is not a list of anything. The data
		// attribute is what they agree on; the class is kept as the fallback
		// so the archive keeps working whichever ships first.
		var row = root.closest( '[data-arv-results-row]' ) ||
			root.closest( '.arv-results__week-race' );
		var live = row ? row.querySelector( '[data-arv-results-live]' ) : null;

		function pad( n ) {
			return n < 10 ? '0' + n : String( n );
		}

		// The leading unit is written plainly and everything after it is
		// padded, the way a clock is read aloud: "7:58:12", not "07:58:12",
		// and "5:33" rather than "00:05:33" in the last hour. Padding exists
		// to keep the columns of a fixed-width field from jumping, which
		// applies to the minutes and seconds inside the number and never to
		// the first digit of it.
		function span( ms ) {
			var s = Math.floor( ms / 1000 );
			var d = Math.floor( s / 86400 );
			var h = Math.floor( ( s % 86400 ) / 3600 );
			var m = Math.floor( ( s % 3600 ) / 60 );

			if ( d > 0 ) {
				return d + 'd ' + h + ':' + pad( m ) + ':' + pad( s % 60 );
			}

			if ( h > 0 ) {
				return h + ':' + pad( m ) + ':' + pad( s % 60 );
			}

			return m + ':' + pad( s % 60 );
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
