/**
 * Footer link columns, collapsed to an accordion on a phone.
 *
 * The footer is a single Custom HTML widget baked into the theme, not
 * anything this plugin owns or can safely rewrite: four columns of up to
 * five links each, stacked to one column on a phone, come to roughly
 * 1,100px, more scrolling to reach the copyright than most pages have
 * content above the fold, and worse on a live-results page where a reader
 * has already scrolled past a whole board of entrants to get there.
 *
 * Enhanced rather than templated, because the fallback if this never runs
 * (WP Rocket delays scripts until the visitor's first interaction) is every
 * link visible and readable exactly as it is today, only long: never fewer
 * links, never a broken one. <details>/<summary> rather than a hand-rolled
 * toggle, so keyboard and screen-reader behaviour come from the browser
 * rather than from this script. Left open to more than one section at once
 * on purpose: four short lists do not need an exclusive accordion, and not
 * closing sibling sections is one thing this script does not have to get
 * right.
 *
 * No-ops off a phone and where the footer is not the shape this expects, so
 * a future edit to the widget degrades to "footer looks like it used to"
 * rather than to a broken page.
 */
( function () {
	'use strict';

	// Matches the breakpoint the widget's own inline styles already use for
	// its two- and one-column layouts, not this plugin's own 619px: this is
	// that widget's breakpoint, not this plugin's.
	if ( ! window.matchMedia( '(max-width: 767px)' ).matches ) {
		return;
	}

	var grid = document.querySelector( '.custom-footer-grid' );

	if ( ! grid ) {
		return;
	}

	var columns = grid.querySelectorAll( ':scope > div' );

	for ( var i = 0; i < columns.length; i++ ) {
		enhance( columns[ i ] );
	}

	function enhance( column ) {
		var heading = column.firstElementChild;

		if ( ! heading ) {
			return;
		}

		var label = ( heading.textContent || '' ).trim();

		if ( '' === label ) {
			return;
		}

		// Text only, not the heading element itself: one of the four
		// columns links its heading to its own landing page and the other
		// three do not, and a <summary> is already the row's whole tap
		// target, so an <a> nested inside it would be invalid markup and an
		// ambiguous tap besides. The links below the heading still work
		// either way.
		heading.remove();

		var details = document.createElement( 'details' );
		var summary = document.createElement( 'summary' );

		summary.textContent = label;
		details.className = 'arv-footer-accordion';
		details.appendChild( summary );

		while ( column.firstChild ) {
			details.appendChild( column.firstChild );
		}

		column.appendChild( details );
	}
} )();
