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
			var panel = el.querySelector( '[data-arv-results-editions-panel]' );
			var btn = el.querySelector( '[data-arv-results-editions]' );
			if ( ! panel ) {
				return;
			}
			if ( open ) {
				if ( panel.hidden ) {
					panel.hidden = false;
					if ( btn ) {
						btn.setAttribute( 'aria-expanded', 'true' );
					}
					autoOpened.push( el );
				}
				return;
			}
			var at = autoOpened.indexOf( el );
			if ( at !== -1 ) {
				panel.hidden = true;
				if ( btn ) {
					btn.setAttribute( 'aria-expanded', 'false' );
				}
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

		// Lives above the year pills rather than inside a panel, so the loop
		// below has never touched it.
		var weekBlock = root.querySelector( '.arv-results__week' );

		// A race that is running right now, rather than one that is merely in
		// this week's block. The server writes the state class at render, and
		// the clock rewrites the marker every second after that, so a page
		// cached before the gun still answers this correctly once it is open.
		function raceIsLive() {
			if ( ! weekBlock ) {
				return false;
			}

			return !! weekBlock.querySelector( '.arv-results__week-race--live' ) ||
				!! weekBlock.querySelector( '[data-arv-results-live]:not([hidden])' );
		}

		// "Race week" above a list of 2008 results answers a question the
		// reader did not ask, and pushes the year they did ask for down the
		// page. Shown on the view the page opens on, where someone arriving
		// mid weekend wants exactly that, and dropped once they have chosen
		// a past year on purpose.
		//
		// Except while a race is actually underway. A live board is worth
		// interrupting an archive visit for in a way that "here is what
		// finished last Saturday" is not, and the reader who came to look up
		// 2014 during Javelina is exactly the reader who wants to know.
		function syncWeekBlock( year ) {
			if ( weekBlock && defaultYear ) {
				weekBlock.hidden = ( year !== defaultYear ) && ! raceIsLive();
			}
		}

		// The masthead heading and the line under it, swapped on the year
		// switch: the page never reloads, so the <h1> would otherwise still
		// read "2026 Results" over a list of 2010 races. Both strings are
		// written server side and carried on the year button, so the browser
		// only moves them and never composes them.
		var titleEl = root.querySelector( '[data-arv-results-title]' );
		var metaEl = root.querySelector( '[data-arv-results-meta]' );

		function syncMasthead( button ) {
			if ( ! button ) {
				return;
			}

			var title = button.getAttribute( 'data-arv-results-year-title' );
			var meta = button.getAttribute( 'data-arv-results-year-meta' );

			if ( titleEl && title ) {
				titleEl.textContent = title;
			}

			if ( metaEl ) {
				metaEl.textContent = meta || '';
				metaEl.hidden = ! meta;
			}
		}

		function selectYear( year ) {
			syncWeekBlock( year );

			for ( var p = 0; p < panels.length; p++ ) {
				panels[ p ].hidden = panels[ p ].getAttribute( 'data-arv-results-year-panel' ) !== year;
			}

			for ( var b = 0; b < yearButtons.length; b++ ) {
				var on = yearButtons[ b ].getAttribute( 'data-arv-results-year' ) === year;
				yearButtons[ b ].classList.toggle( 'is-on', on );
				yearButtons[ b ].setAttribute( 'aria-selected', on ? 'true' : 'false' );

				if ( on ) {
					syncMasthead( yearButtons[ b ] );
				}
			}

			// A query typed against 2026 means nothing once the reader has
			// switched to 2015; carrying it over would either hide a year that
			// has no reason to be empty or leave a stale count reading "3
			// races" under a list that was never searched.
			input.value = '';
			autoOpened.length = 0;
			for ( var bb = 0; bb < panels.length; bb++ ) {
				var pb = panels[ bb ].querySelector( '[data-arv-results-back]' );
				if ( pb ) {
					pb.hidden = true;
				}
			}

			// The closures above were built over the year that was active when
			// this input was first wired, so switching years has to repoint
			// every one of them at the panel that is visible now, not the one
			// that was visible on load.
			list = activeList();
			groups = list ? list.querySelectorAll( '[data-arv-results-race]' ) : [];
			months = list ? list.querySelectorAll( '[data-arv-results-month]' ) : [];

			apply();
		}

		// Opening one race filters the year down to it. The editions are the
		// page at that point rather than a nested list inside a row of other
		// races, which is the whole difference between "expand a footnote"
		// and "read this race's history".
		// Resolved per call, not once: there is one back bar per year panel, so
		// caching the first one found meant a race opened in 2015 unhid
		// 2026's bar, inside a panel nobody can see, and the way out of the
		// filtered view simply never appeared.
		function activeBackBar() {
			var list = activeList();
			var panel = list ? list.closest( '[data-arv-results-year-panel]' ) : null;
			return ( panel || root ).querySelector( '[data-arv-results-back]' );
		}

		function openRace( group ) {
			var panel = group.querySelector( '[data-arv-results-editions-panel]' );
			var btn = group.querySelector( '[data-arv-results-editions]' );

			for ( var i = 0; i < groups.length; i++ ) {
				groups[ i ].hidden = groups[ i ] !== group;
			}
			for ( var m = 0; m < months.length; m++ ) {
				months[ m ].hidden = ! months[ m ].querySelector(
					'[data-arv-results-race]:not([hidden])'
				);
			}
			if ( panel ) {
				panel.hidden = false;
			}
			if ( btn ) {
				btn.setAttribute( 'aria-expanded', 'true' );
			}
			var bar = activeBackBar();
			if ( bar ) {
				bar.hidden = false;
			}
			if ( count ) {
				count.textContent = '';
			}
		}

		function closeRace() {
			for ( var i = 0; i < groups.length; i++ ) {
				var p = groups[ i ].querySelector( '[data-arv-results-editions-panel]' );
				var b = groups[ i ].querySelector( '[data-arv-results-editions]' );
				if ( p ) {
					p.hidden = true;
				}
				if ( b ) {
					b.setAttribute( 'aria-expanded', 'false' );
				}
			}
			var bar = activeBackBar();
			if ( bar ) {
				bar.hidden = true;
			}
			autoOpened.length = 0;
			apply();
		}

		root.addEventListener( 'click', function ( ev ) {
			var btn = ev.target.closest ? ev.target.closest( '[data-arv-results-editions]' ) : null;
			if ( btn ) {
				var group = btn.closest( '[data-arv-results-race]' );
				if ( group ) {
					// Taking it a second time puts the year back, so the
					// button is a toggle rather than a one-way door that
					// only the back bar can undo.
					if ( 'true' === btn.getAttribute( 'aria-expanded' ) ) {
						closeRace();
					} else {
						openRace( group );
					}
				}
				return;
			}
			if ( ev.target.closest && ev.target.closest( '.arv-results__back' ) ) {
				closeRace();
			}
		} );

		// Matches ARV_RESULTS_YEAR_VAR in includes/elements/results.php, where
		// the full note lives: not "year", which WordPress reserves for date
		// archives and which 404d every year but the newest.
		var YEAR_VAR = 'race_year';

		// The newest year, PHP's own default when the URL names none. Kept
		// so a click back to it can drop the parameter rather than write it
		// out, which is what keeps /results/ itself as the canonical address
		// for the year everyone actually lands on.
		var defaultYear = yearButtons.length ? yearButtons[ 0 ].getAttribute( 'data-arv-results-year' ) : null;

		function yearUrl( year ) {
			var url = new URL( window.location.href );
			if ( year === defaultYear ) {
				url.searchParams.delete( YEAR_VAR );
			} else {
				url.searchParams.set( YEAR_VAR, year );
			}
			return url.pathname + url.search + url.hash;
		}

		// The server renders the right panel for ?race_year=2015 without any
		// of this running, so the block has to be squared up on load too and
		// not only on a click.
		syncWeekBlock( new URLSearchParams( window.location.search ).get( YEAR_VAR ) || defaultYear );

		for ( var y = 0; y < yearButtons.length; y++ ) {
			yearButtons[ y ].addEventListener( 'click', function () {
				var year = this.getAttribute( 'data-arv-results-year' );
				selectYear( year );
				if ( window.history && window.history.pushState ) {
					window.history.pushState( { arvResultsYear: year }, '', yearUrl( year ) );
				}
			} );
		}

		// Only a URL change moves the reader here, a year switch never
		// reloads the page, so the browser's own back/forward would
		// otherwise do nothing: the address bar updates but the panel on
		// screen does not follow it.
		if ( panels.length && window.history ) {
			window.addEventListener( 'popstate', function () {
				var year = new URLSearchParams( window.location.search ).get( YEAR_VAR ) || defaultYear;
				if ( year ) {
					selectYear( year );
				}
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
		//
		// Two days out or more, hours are the finest unit worth showing.
		// "3d 4:22:10" was ticking a second nobody was watching and updating
		// a race in the calendar every second whether anyone had the tab
		// open or not; "3d 4h" resolves faster anyway and only changes once
		// an hour. One day out keeps the fine form: "tomorrow, at what time"
		// is a real question in the last 24 hours in a way it is not at 3.
		function span( ms ) {
			var s = Math.floor( ms / 1000 );
			var d = Math.floor( s / 86400 );
			var h = Math.floor( ( s % 86400 ) / 3600 );
			var m = Math.floor( ( s % 3600 ) / 60 );

			if ( d >= 2 ) {
				return d + 'd ' + h + 'h';
			}

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
