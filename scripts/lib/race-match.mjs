/**
 * Matching a photo gallery's name to an Aravaipa race, and only to one.
 *
 * Lived in discover-smugmug.mjs until discover-zenfolio.mjs needed to ask
 * exactly the same question of a different host. Two copies would drift,
 * and the drift would be the expensive kind: both would still accept and
 * reject galleries, just not the same ones, so the same race would appear
 * under one name from SmugMug and another from Zenfolio, or a stranger's
 * race would be published as Aravaipa's on one host and not the other.
 *
 * The rules are deliberately the same as arv_films_race_for() in
 * includes/films-store.php, which reads the race off a film's own title:
 * longest matching phrase across every known race wins, and a single word
 * only counts if it is neither short nor generic landscape vocabulary.
 * That rule exists because "Tushars Mountain Runs 2021" happily matched
 * "Mountain Ridge Trail Race" on the bare word "mountain" until it was
 * stopped.
 */

const GENERIC = new Set(
	( 'mountain mountains canyon valley ridge creek desert lake park springs river peak peaks ' +
	  'trail trails endurance festival classic series marathon ultras ultra night runs run running ' +
	  'race races events event photos photo gallery half' ).split( ' ' )
);

/**
 * Aravaipa Rides is the mountain-bike brand, on its own site at
 * aravaiparides.com, and its galleries sit in the same folders as the
 * running ones ("Sinister Night Rides 2026" beside "Sinister Night Run").
 * Same call as leaving the Aravaipa Rides podcast off aravaiparunning.com's
 * podcasts page, and the same call Jamil made for the results archive: it
 * is a distinct brand, not a distinct format of the same one.
 *
 * Matched on the word rather than the race, because the race name is
 * shared: it is the gallery that is a ride, not the event.
 */
export const IS_RIDE = /\b(rides?|bike|mtb)\b/i;

export const normalise = ( s ) =>
	String( s )
		.toLowerCase()
		// "Mountain 2 Fountain" is how Let's Wander writes "Mountain to
		// Fountain", and "50K & 27K" is how everyone writes "and". Folded
		// here rather than added to the race list, because it is how the
		// name was typed, not another name for the race.
		.replace( /\b2\b/g, ' to ' )
		.replace( /&/g, ' and ' )
		.replace( /[^a-z0-9]+/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

export const phrasesFor = ( race ) => {
	const words = normalise( race ).split( ' ' );
	const out = [];

	for ( let n = words.length; n > 0; n-- ) {
		const phrase = words.slice( 0, n ).join( ' ' );
		if ( phrase.length < 5 ) continue;
		// Seven, not six. Six was tried, to let "Bobcat" name Bobcat Trail
		// Runs, and it immediately matched Spring Velvet's "Copper Mtn Cross
		// Country Meet" to Copper Corridor on the word "copper". One folder
		// gained is not worth one other promoter's race published as
		// Aravaipa's, and the Bobcat gallery is covered by the store's
		// existing row anyway.
		if ( 1 === n && ( phrase.length < 7 || GENERIC.has( phrase ) ) ) continue;
		out.push( phrase );
	}

	return out;
};

/**
 * Build a matcher over a list of race names.
 *
 * @param {string[]} raceNames
 * @return {(name: string) => string|null}
 */
export function raceMatcher( raceNames ) {
	const table = raceNames.map( ( race ) => ( { race, phrases: phrasesFor( race ) } ) );

	/**
	 * The Aravaipa race a folder is for, or null.
	 *
	 * Longest matching phrase across every race wins, not the first race
	 * that matches anything.
	 */
	return ( name ) => {
		const hay = ` ${ normalise( name ) } `;
		let best = null;
		let bestLen = 0;

		for ( const { race, phrases } of table ) {
			for ( const phrase of phrases ) {
				if ( phrase.length <= bestLen ) continue;
				if ( hay.includes( ` ${ phrase } ` ) ) {
					best = race;
					bestLen = phrase.length;
				}
			}
		}

		return best;
	};
}

/**
 * A year written anywhere in a name, its own or an ancestor's.
 *
 * 2009 and up, not 2010 and up as the SmugMug walker had it: nothing on
 * SmugMug predates 2015, so the narrower pattern cost nothing there, and
 * Zenfolio's archive opens with a folder literally called "2009 Races".
 *
 * @param {...string} names
 * @return {number} 0 when none of them says.
 */
export const yearFrom = ( ...names ) => {
	for ( const n of names ) {
		const m = String( n ).match( /\b(20[0-2]\d)\b/ );
		if ( m ) return Number( m[ 1 ] );
	}

	return 0;
};
