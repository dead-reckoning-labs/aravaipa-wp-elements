/**
 * Search for the Aravaipa Results element.
 *
 * Filters the race list as you type. Everything is already in the HTML, so
 * this only ever hides and shows: nothing is fetched, and a search engine
 * sees the whole archive regardless of what anyone has typed.
 *
 * Matches on the race's own names only, not on the dates or the link labels,
 * because every race carries the same three link labels and "results" would
 * otherwise match all seventy-five of them. Names plural: a race renamed
 * between editions answers to every spelling it has had, so looking one up
 * by what it was called at the time finds it.
 *
 * Browsing is one year at a time, searching is not: a query is asked of the
 * whole archive and answered with every running that matches it, each under
 * the year it happened in.
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
		//
		// A query reaches across every year of them. Browsing is a year at a
		// time, but searching is not: typing a race name while 2009 happened
		// to be showing answered "no races match that" about a race with
		// fifteen runnings, which is the archive telling a reader something
		// false about its own contents.
		//
		// A race that ran fifteen times has fifteen cards, one in each year's
		// panel, and they are not copies of each other: a panel holds only
		// the runnings that happened in its own year, so each card is that
		// year's race day with its own date, finishers and winners. A search
		// shows all of them. Collapsing them to the newest one, which this
		// did for a few releases, answered "does this race exist" when the
		// question a search asks of an archive is "when did it run".
		var activePanel = null;

		for ( var ap = 0; ap < panels.length; ap++ ) {
			if ( ! panels[ ap ].hidden ) {
				activePanel = panels[ ap ];
				break;
			}
		}

		function activeList() {
			if ( 0 === panels.length ) {
				return root.querySelector( '[data-arv-results-list]' );
			}

			return activePanel ? activePanel.querySelector( '[data-arv-results-list]' ) : null;
		}

		var list = activeList();
		if ( ! list ) {
			return;
		}

		// Every card on the page, panel by panel in the order they were
		// rendered, resolved once. Which cards exist never changes; only
		// which ones are showing does.
		var everyCard = [];

		for ( var ep = 0; ep < panels.length; ep++ ) {
			var cards = panels[ ep ].querySelectorAll( '[data-arv-results-race]' );

			for ( var ec = 0; ec < cards.length; ec++ ) {
				everyCard.push( {
					el: cards[ ec ],
					panel: panels[ ep ],
					names: cards[ ec ].getAttribute( 'data-arv-results-race' ) || ''
				} );
			}
		}

		var everyMonth = panels.length
			? root.querySelectorAll( '[data-arv-results-year-panel] [data-arv-results-month]' )
			: root.querySelectorAll( '[data-arv-results-month]' );

		var groups = list.querySelectorAll( '[data-arv-results-race]' );
		var months = list.querySelectorAll( '[data-arv-results-month]' );

		// The masthead heading and the line under it, swapped as the reader
		// moves: the page never reloads, so the <h1> would otherwise still
		// read "2026 Results" over a list of 2010 races. Every string is
		// written server side, so the browser only moves them and never
		// composes one.
		var masthead = root.querySelector( '.arv-results__masthead' );
		var titleEl = root.querySelector( '[data-arv-results-title]' );
		var metaEl = root.querySelector( '[data-arv-results-meta]' );
		var allTitle = masthead ? masthead.getAttribute( 'data-arv-results-all-title' ) : '';

		function syncMasthead( button, searching ) {
			if ( titleEl ) {
				if ( searching && allTitle ) {
					titleEl.textContent = allTitle;
				} else if ( ! searching && button ) {
					titleEl.textContent = button.getAttribute( 'data-arv-results-year-title' ) ||
						titleEl.textContent;
				}
			}

			// A count of one year's races under a heading that is no longer
			// about one year would be answering a question nobody asked, and
			// the match count beside the search box is already the answer to
			// the one they did.
			if ( metaEl ) {
				var meta = ( ! searching && button )
					? button.getAttribute( 'data-arv-results-year-meta' )
					: '';

				metaEl.textContent = meta || '';
				metaEl.hidden = ! meta;
			}
		}

		// The year row scrolls sideways and no longer draws a scrollbar to
		// say so: on macOS that bar is painted over the content rather than
		// beside it, and it landed across the bottom edge of the buttons.
		// The row fades at whichever end still has years behind it instead,
		// which unlike the scrollbar says so to everyone rather than only to
		// readers who have set their scrollbars to always show.
		var yearStrip = root.querySelector( '.arv-results__years' );

		function syncYearFade() {
			if ( ! yearStrip ) {
				return;
			}

			// A pixel of slack at each end: a fractional scroll position is
			// enough to leave an edge faded over nothing.
			yearStrip.classList.toggle( 'is-cut-left', yearStrip.scrollLeft > 1 );
			yearStrip.classList.toggle(
				'is-cut-right',
				yearStrip.scrollLeft + yearStrip.clientWidth < yearStrip.scrollWidth - 1
			);
		}

		// The selected year, dragged into view. A deep link to
		// ?race_year=2008 opens on the oldest year the archive has, which is
		// the far end of a row that starts at this year: the panel below was
		// right and the button for it was off the edge of the screen.
		function showSelectedYear( button, behavior ) {
			if ( ! button || ! yearStrip || ! button.scrollIntoView ) {
				return;
			}

			// Measured off the rendered boxes rather than offsetLeft, which
			// is relative to whichever ancestor happens to be positioned and
			// is not the scrolling row: read that way, a year sitting in
			// plain sight can measure as out of view and drag the whole row
			// under the reader for nothing.
			var row = yearStrip.getBoundingClientRect();
			var pill = button.getBoundingClientRect();

			if ( pill.left < row.left || pill.right > row.right ) {
				button.scrollIntoView( { block: 'nearest', inline: 'center', behavior: behavior || 'auto' } );
			}
		}

		// While a query is up, the page is not showing a year: it is showing
		// what matched, out of all of them. No year button is the selected
		// one for as long as that is true, because leaving 2009 lit while the
		// answer came out of 2016 is the confusion this whole change is
		// about. Put back the moment the query clears.
		function syncYearPills( searching ) {
			var year = activePanel
				? activePanel.getAttribute( 'data-arv-results-year-panel' )
				: null;
			var selected = null;

			for ( var b = 0; b < yearButtons.length; b++ ) {
				var mine = yearButtons[ b ].getAttribute( 'data-arv-results-year' ) === year;
				var on = mine && ! searching;

				yearButtons[ b ].classList.toggle( 'is-on', on );
				yearButtons[ b ].setAttribute( 'aria-selected', on ? 'true' : 'false' );

				if ( mine ) {
					selected = yearButtons[ b ];
				}
			}

			syncMasthead( selected, searching );
			syncYearFade();
		}

		function apply() {
			var q = input.value.trim().toLowerCase();
			var searching = '' !== q;
			var shown = 0;
			var i;
			var m;

			if ( panels.length ) {
				// Every running that matches, not one card per race. A year
				// panel holds only the runnings that happened in that year,
				// so the fifteen Javelina Jundred cards a search crosses are
				// fifteen different races days, correctly dated, each under
				// its own year: the race's history, which is what somebody
				// searching an archive for it is looking for. Collapsing
				// them to the newest one answered "does this race exist"
				// when the question was "when did it run".
				for ( i = 0; i < everyCard.length; i++ ) {
					var card = everyCard[ i ];
					var hit;

					if ( searching ) {
						hit = card.names.indexOf( q ) !== -1;

						if ( hit ) {
							shown++;
						}
					} else {
						hit = ( card.panel === activePanel );
					}

					card.el.hidden = ! hit;
				}

				// A year with no match in it is not a year with no races. It
				// goes away entirely rather than standing as an empty panel.
				for ( var p = 0; p < panels.length; p++ ) {
					panels[ p ].hidden = searching
						? ! panels[ p ].querySelector( '[data-arv-results-race]:not([hidden])' )
						: ( panels[ p ] !== activePanel );
				}
			} else {
				for ( i = 0; i < groups.length; i++ ) {
					var name = groups[ i ].getAttribute( 'data-arv-results-race' ) || '';
					var single = ! searching || name.indexOf( q ) !== -1;
					groups[ i ].hidden = ! single;

					if ( single && searching ) {
						shown++;
					}
				}
			}

			// A month heading with nothing under it reads as a month with no
			// races in it, which is a different and wrong claim. Hidden with
			// its races rather than left standing over the gap. Across every
			// panel, not only the open one: a search shows months out of any
			// year and has to be able to put them back.
			for ( m = 0; m < everyMonth.length; m++ ) {
				everyMonth[ m ].hidden = ! everyMonth[ m ].querySelector(
					'[data-arv-results-race]:not([hidden])'
				);
			}

			syncYearPills( searching );

			if ( clear ) {
				clear.hidden = '' === input.value;
			}

			if ( ! count ) {
				return;
			}

			// Says which haystack it looked in, because the year buttons are
			// still on screen and the honest reading of "no races match that"
			// under a selected year is that it only looked at that one.
			// Counted as results rather than races once it crosses years,
			// because eighteen of them can be one race.
			var everywhere = panels.length > 1;

			if ( ! searching ) {
				// Nothing has been asked yet, so there is nothing to report.
				count.textContent = '';
			} else if ( 0 === shown ) {
				count.textContent = everywhere
					? 'No races match that, in any year.'
					: 'No races match that.';
			} else if ( everywhere ) {
				count.textContent = shown + ( 1 === shown ? ' result' : ' results' ) + ', all years';
			} else {
				count.textContent = shown + ( 1 === shown ? ' race' : ' races' );
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

		function selectYear( year ) {
			syncWeekBlock( year );

			for ( var p = 0; p < panels.length; p++ ) {
				if ( panels[ p ].getAttribute( 'data-arv-results-year-panel' ) === year ) {
					activePanel = panels[ p ];
				}
			}

			// Which panels show, which pills are lit and what the masthead
			// says all follow from activePanel, and apply() at the foot of
			// this sets every one of them: a year switch is the same state
			// with a different year in it, not a second way to arrange the
			// page.

			// A query typed against 2026 means nothing once the reader has
			// switched to 2015; carrying it over would either hide a year that
			// has no reason to be empty or leave a stale count reading "3
			// races" under a list that was never searched.
			input.value = '';
			// The closures above were built over the year that was active when
			// this input was first wired, so switching years has to repoint
			// every one of them at the panel that is visible now, not the one
			// that was visible on load.
			list = activeList();
			groups = list ? list.querySelectorAll( '[data-arv-results-race]' ) : [];
			months = list ? list.querySelectorAll( '[data-arv-results-month]' ) : [];

			apply();
		}

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

		// The fade follows the row, and the row is a different width on every
		// screen and after every resize.
		if ( yearStrip ) {
			yearStrip.addEventListener( 'scroll', syncYearFade, { passive: true } );
			window.addEventListener( 'resize', syncYearFade );
		}

		syncYearFade();

		// The year the page opened on, brought into view without animating
		// it: on load there is nothing to follow, only a row that should
		// already be showing the year underneath it.
		for ( var sel = 0; sel < yearButtons.length; sel++ ) {
			if ( yearButtons[ sel ].classList.contains( 'is-on' ) ) {
				showSelectedYear( yearButtons[ sel ] );
				break;
			}
		}

		for ( var y = 0; y < yearButtons.length; y++ ) {
			yearButtons[ y ].addEventListener( 'click', function () {
				var year = this.getAttribute( 'data-arv-results-year' );
				selectYear( year );
				showSelectedYear( this, 'smooth' );
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
