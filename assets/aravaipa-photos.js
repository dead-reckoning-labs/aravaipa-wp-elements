/**
 * Search and photographer filter for the Photos grid.
 *
 * Same contract as aravaipa-films.js: every card is already rendered
 * server side and this only hides what is there. The year filter is
 * deliberately not here, because those are real links to real URLs so a
 * year stays shareable and crawlable.
 */
( function () {
	'use strict';

	var section = document.querySelector( '.arv-photos' );

	if ( ! section ) {
		return;
	}

	var search = section.querySelector( '[data-arv-photos-search]' );
	var by = section.querySelector( '[data-arv-photos-by]' );
	var count = section.querySelector( '[data-arv-photos-count]' );
	var grid = section.querySelector( '[data-arv-photos-grid]' );

	if ( ! grid || ( ! search && ! by ) ) {
		return;
	}

	var cards = Array.prototype.slice.call( grid.children );

	function apply() {
		var q = search ? search.value.trim().toLowerCase() : '';
		var wantBy = by ? by.value : '';
		var shown = 0;

		for ( var i = 0; i < cards.length; i++ ) {
			var card = cards[ i ];
			var race = card.getAttribute( 'data-arv-photos-race' ) || '';
			var shooters = card.getAttribute( 'data-arv-photos-by' ) || '';

			// Search covers the photographer too, so typing a name finds
			// their races without touching the dropdown.
			var hit = ( '' === q || race.indexOf( q ) !== -1 || shooters.indexOf( q ) !== -1 ) &&
				( '' === wantBy || shooters.indexOf( wantBy ) !== -1 );

			card.hidden = ! hit;

			if ( hit ) {
				shown++;
			}
		}

		if ( count ) {
			count.textContent = ( '' === q && '' === wantBy )
				? ''
				: shown + ( 1 === shown ? ' race' : ' races' );
		}
	}

	if ( search ) {
		search.addEventListener( 'input', apply );
	}

	if ( by ) {
		by.addEventListener( 'change', apply );
	}
} )();
