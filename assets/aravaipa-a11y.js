/**
 * Keeps collapsed Cornerstone accordion panels and inactive tab panels in the
 * accessibility tree.
 *
 * The X theme hides a closed panel with display:none and aria-hidden="true",
 * which removes it from the accessibility tree entirely. Screen readers and
 * AI agents that read only that tree (Meta's assistant, for one) then see a
 * race page with no cutoffs, aid stations or schedule, because all of that
 * lives in accordions. The stylesheet (aravaipa-elements.css, "Collapsed
 * panels stay readable") swaps display:none for the standard visually hidden
 * clip, so nothing changes on screen; this removes the aria-hidden the theme
 * sets, and puts it back to removed every time the theme toggles a panel.
 *
 * Links inside a closed panel are taken out of the tab order while it is
 * closed, so a keyboard user never lands on something they cannot see, and
 * get their tab order back when it opens.
 */
( function () {
	'use strict';

	var SELECTOR = '.x-acc-item > [data-x-toggle-collapse], .x-tabs-panels > .x-tabs-panel';
	var FOCUSABLE = 'a[href], button, input, select, textarea, iframe, [tabindex]';

	function isClosed( panel ) {
		if ( panel.classList.contains( 'x-tabs-panel' ) ) {
			return ! panel.classList.contains( 'x-active' );
		}
		return panel.classList.contains( 'x-collapsed' );
	}

	function sync( panel ) {
		if ( panel.getAttribute( 'aria-hidden' ) === 'true' ) {
			panel.removeAttribute( 'aria-hidden' );
		}

		var closed = isClosed( panel );
		var nodes = panel.querySelectorAll( FOCUSABLE );

		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			if ( closed ) {
				if ( ! node.hasAttribute( 'data-arv-tabindex' ) ) {
					node.setAttribute( 'data-arv-tabindex', node.hasAttribute( 'tabindex' ) ? node.getAttribute( 'tabindex' ) : '' );
					node.setAttribute( 'tabindex', '-1' );
				}
			} else if ( node.hasAttribute( 'data-arv-tabindex' ) ) {
				var was = node.getAttribute( 'data-arv-tabindex' );
				node.removeAttribute( 'data-arv-tabindex' );
				if ( was === '' ) {
					node.removeAttribute( 'tabindex' );
				} else {
					node.setAttribute( 'tabindex', was );
				}
			}
		}
	}

	function init() {
		var panels = document.querySelectorAll( SELECTOR );
		if ( ! panels.length ) {
			return;
		}

		var observer = typeof window.MutationObserver !== 'undefined'
			? new window.MutationObserver( function ( records ) {
				for ( var i = 0; i < records.length; i++ ) {
					sync( records[ i ].target );
				}
			} )
			: null;

		for ( var i = 0; i < panels.length; i++ ) {
			sync( panels[ i ] );
			if ( observer ) {
				observer.observe( panels[ i ], { attributes: true, attributeFilter: [ 'aria-hidden', 'class' ] } );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
