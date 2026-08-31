/**
 * Search and filter for the Aravaipa Season Calendar.
 *
 * Progressive enhancement, deliberately: the calendar is fully rendered
 * server side and every race is in the HTML before this file runs. This only
 * ever hides rows that are already there. With JavaScript off, or slow, or
 * broken, the page is still the complete list it is supposed to be, and
 * search engines index all of it regardless of what any filter is set to.
 *
 * No dependencies and no build step. It is a few hundred lines of DOM work
 * on a list that is already correct; a framework would be more code than the
 * thing it is filtering.
 */
( function () {
	'use strict';

	/**
	 * Empty month headings are hidden as their races are filtered out.
	 * Leaving "OCTOBER 2026" above nothing reads as a rendering failure
	 * rather than as a filter doing its job.
	 */
	function syncMonthHeadings( root ) {
		var months = root.querySelectorAll( '.arv-calendar__month' );

		Array.prototype.forEach.call( months, function ( month ) {
			var rows = month.querySelectorAll( '.arv-calendar__row' );
			var anyVisible = Array.prototype.some.call( rows, function ( row ) {
				return ! row.hasAttribute( 'hidden' );
			} );

			month.hidden = ! anyVisible;
		} );
	}

	function matches( row, query, state, month, series, openOnly, roadOnly ) {
		if ( openOnly && row.getAttribute( 'data-open' ) !== '1' ) {
			return false;
		}

		if ( roadOnly && row.getAttribute( 'data-terrain' ) !== 'road' ) {
			return false;
		}

		if ( state && row.getAttribute( 'data-state' ) !== state ) {
			return false;
		}

		if ( series && row.getAttribute( 'data-series' ) !== series ) {
			return false;
		}

		if ( month && row.getAttribute( 'data-month' ) !== month ) {
			return false;
		}

		if ( ! query ) {
			return true;
		}

		// Name, place and distance together: someone typing "50k" wants
		// every 50K, someone typing "tucson" wants everything near Tucson,
		// and neither should have to know which field they are searching.
		var haystack = ( row.getAttribute( 'data-name' ) || '' ) + ' ' +
			( row.getAttribute( 'data-where' ) || '' ) + ' ' +
			( row.getAttribute( 'data-distances' ) || '' );

		// Every word has to appear somewhere, in any order: "tucson 50k"
		// should find a 50K in Tucson, which a plain substring match on the
		// whole phrase would not.
		return query.split( /\s+/ ).every( function ( word ) {
			return haystack.indexOf( word ) !== -1;
		} );
	}

	function setUp( bar ) {
		var calendar = bar.closest( '.arv-calendar' );
		if ( ! calendar ) {
			return;
		}

		var list = calendar.querySelector( '[data-arv-list]' );
		if ( ! list ) {
			return;
		}

		var rows = list.querySelectorAll( '.arv-calendar__row' );
		var search = bar.querySelector( '[data-arv-search]' );
		var stateSel = bar.querySelector( '[data-arv-state]' );
		var monthSel = bar.querySelector( '[data-arv-month]' );
		var seriesSel = bar.querySelector( '[data-arv-series]' );
		var openBox = bar.querySelector( '[data-arv-open]' );
		var roadBox = bar.querySelector( '[data-arv-road]' );
		var count = bar.querySelector( '[data-arv-count]' );
		var total = rows.length;
		// The hiatus list sits outside the filtered list on purpose: those
		// races have no date, so month and state cannot apply to them. But
		// leaving it on screen during a search means a query for "tucson"
		// still shows two unrelated races underneath the results, which reads
		// as the filter having missed them. It is hidden whenever any filter
		// is active and comes back when they are cleared.
		var hiatus = calendar.querySelector( '.arv-calendar__hiatus' );

		function apply() {
			var query = search ? search.value.trim().toLowerCase() : '';
			var state = stateSel ? stateSel.value : '';
			var month = monthSel ? monthSel.value : '';
			var series = seriesSel ? seriesSel.value : '';
			var openOnly = openBox ? openBox.checked : false;
			var roadOnly = roadBox ? roadBox.checked : false;
			var shown = 0;

			Array.prototype.forEach.call( rows, function ( row ) {
				var ok = matches( row, query, state, month, series, openOnly, roadOnly );
				row.hidden = ! ok;
				if ( ok ) {
					shown++;
				}
			} );

			syncMonthHeadings( calendar );

			if ( hiatus ) {
				hiatus.hidden = !! ( query || state || month || series || openOnly || roadOnly );
			}

			if ( count ) {
				if ( shown === total ) {
					// Saying "84 of 84" on first load is noise: nothing has
					// been filtered yet and there is nothing to report.
					count.textContent = '';
				} else if ( shown === 0 ) {
					count.textContent = 'No races match. Try clearing a filter.';
				} else {
					count.textContent = 'Showing ' + shown + ' of ' + total + ' races';
				}
			}

			calendar.classList.toggle( 'is-empty', shown === 0 );
		}

		if ( search ) {
			search.addEventListener( 'input', apply );
		}
		if ( stateSel ) {
			stateSel.addEventListener( 'change', apply );
		}
		if ( monthSel ) {
			monthSel.addEventListener( 'change', apply );
		}
		if ( seriesSel ) {
			seriesSel.addEventListener( 'change', apply );
		}
		if ( openBox ) {
			openBox.addEventListener( 'change', apply );
		}
		if ( roadBox ) {
			roadBox.addEventListener( 'change', apply );
		}

		// The bar is hidden until this runs. A search box that does nothing
		// because its script has not loaded, or failed to, is worse than no
		// search box: it looks broken rather than absent.
		bar.classList.add( 'is-ready' );
	}

	function init() {
		var bars = document.querySelectorAll( '[data-arv-filters]' );
		Array.prototype.forEach.call( bars, setUp );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
