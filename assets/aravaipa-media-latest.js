/**
 * Type filter for the Latest feed.
 *
 * Progressive enhancement, same contract as every other filter in this
 * plugin: every card is already rendered server side, so a crawler and a
 * visitor with JavaScript off both see the complete, unfiltered feed. This
 * only ever hides cards that are already there.
 */
( function () {
	'use strict';

	var section = document.querySelector( '.arv-media-latest' );

	if ( ! section ) {
		return;
	}

	var bar = section.querySelector( '[data-arv-latest-filters]' );
	var grid = section.querySelector( '[data-arv-latest-grid]' );

	if ( ! bar || ! grid ) {
		return;
	}

	var buttons = bar.querySelectorAll( '[data-arv-latest-type]' );
	var cards = grid.querySelectorAll( '.arv-media-latest__card' );

	bar.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-arv-latest-type]' );

		if ( ! button ) {
			return;
		}

		var type = button.getAttribute( 'data-arv-latest-type' );

		Array.prototype.forEach.call( buttons, function ( b ) {
			b.classList.toggle( 'is-current', b === button );
		} );

		Array.prototype.forEach.call( cards, function ( card ) {
			card.hidden = '' !== type && card.getAttribute( 'data-arv-latest-type' ) !== type;
		} );
	} );
} )();
