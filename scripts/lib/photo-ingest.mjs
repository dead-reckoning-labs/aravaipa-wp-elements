import { normalise } from './race-match.mjs';

/**
 * Deciding which discovered galleries the nightly ingest may add to the
 * photo store on its own, and which a person has to look at.
 *
 * Pure: no network, no WordPress. ingest-photos.mjs does the fetching and
 * the writing, this only plans, so every rule here is covered by
 * scripts/test/photo-ingest.test.mjs against fixtures.
 *
 * The bar for "add without asking" is deliberately high. A gallery is only
 * added when all of these hold:
 *
 *   - its URL (or its folder, or an album inside it) is not already in the
 *     store. The store is keyed on URL, and this is what makes the job
 *     idempotent: a row that was lost gets re-added the next night, a row
 *     that is there is never touched.
 *   - its name resolves to exactly one card already on /photos/ (see
 *     resolver() below), so the row lands under the card's own name rather
 *     than a new spelling. A tie between two cards goes to review, never to
 *     whichever was first in the list, and so does a race new to the page
 *     (an acquisition, a first edition).
 *   - the card does not already hold a gallery from the same photographer
 *     for the same year. That is usually a second folder for one race
 *     (a 2024 100K and a 2024 half, say), which is a judgement call.
 *
 * And if more than `max` rows pass, none are added: a run that suddenly
 * finds twenty new galleries is more likely a matcher or account change
 * than twenty races posted overnight.
 */

/**
 * One URL per gallery however it was typed: no scheme, no query, no
 * trailing slash, lower case. Same as arv_ingest_url_key() in
 * scripts/photos-ingest.php, which makes the same check at write time.
 */
export const urlKey = ( url ) =>
	String( url ?? '' )
		.trim()
		.toLowerCase()
		.replace( /^https?:\/\//, '' )
		.replace( /[?#].*$/, '' )
		.replace( /\/+$/, '' );

/**
 * Whether the store already shows this gallery: same URL, or one level up
 * or down from it (old rows sometimes link an album inside the race folder).
 *
 * @param {string}   url
 * @param {string[]} keys urlKey() of every stored row.
 */
export const urlPresent = ( url, keys ) => {
	const want = urlKey( url );
	return keys.some( ( have ) => have === want || have.startsWith( want + '/' ) || want.startsWith( have + '/' ) );
};

/**
 * Names to match gallery folders against: every card on /photos/, plus
 * every race the calendar or results archive knows.
 *
 * @param {{rows: object[], names: object[]}} context From photos-ingest.php.
 * @return {{names: string[], keyOf: Map<string,string>}}
 */
export function matchNames( context ) {
	const keyOf = new Map();

	for ( const r of context.rows ) keyOf.set( r.display, r.key );
	for ( const n of context.names ?? [] ) if ( ! keyOf.has( n.name ) ) keyOf.set( n.name, n.key );

	return { names: [ ...keyOf.keys() ], keyOf };
}

/**
 * The card a race key belongs to, named the way the store already names it.
 *
 * The newest year's stored name wins, and within that year the commonest,
 * so a race renamed in 2025 is added under its 2025 name.
 *
 * @return {string|null}
 */
export function cardName( rows ) {
	if ( ! rows.length ) return null;

	const newest = Math.max( ...rows.map( ( r ) => r.year ) );
	const counts = new Map();

	for ( const r of rows ) if ( r.year === newest ) counts.set( r.race, ( counts.get( r.race ) ?? 0 ) + 1 );

	return [ ...counts.entries() ].sort( ( a, b ) => b[ 1 ] - a[ 1 ] || a[ 0 ].localeCompare( b[ 0 ] ) )[ 0 ][ 0 ];
}

/**
 * A folder's name with its years taken out, so "Coldwater Rumble Trail Runs
 * 2025" and "... 2026" are recognised as the same recurring folder.
 */
export const folderSignature = ( name ) =>
	normalise( name ).replace( /\b20\d\d\b/g, ' ' ).replace( /\s+/g, ' ' ).trim();

/**
 * Which card a matched gallery belongs on, or why that cannot be said.
 *
 * In order, and the first that has an answer decides:
 *
 *   1. History by folder: galleries already in the store whose folder has
 *      the same name bar the year. "McDowell Mountain Frenzy Mayhem" is on
 *      the Mayhem card although the name only matches Frenzy; next year's
 *      folder of the same name goes where this year's went.
 *   2. The card whose race key is the matched name's own key. This comes
 *      before name history: Let's Wander's "McDowell Mt Frenzy 2025" is a
 *      Frenzy gallery, even though the only other folder that matched the
 *      Frenzy name ("McDowell Mountain Frenzy Mayhem") sits on Mayhem.
 *   3. History by matched race, for a name with no card of its own:
 *      "Coldwater Rumble" galleries live on the card called Coldwater
 *      Hundred.
 *   4. The one card whose key's words contain, or are contained in, the
 *      matched name's ("Big Pine" and "Flagstaff Extreme Big Pine"). Same
 *      relationship arv_photos_race_date() accepts, and like it, only when
 *      exactly one card answers.
 *
 * History that disagrees with itself is not outvoted, it is a reason to
 * ask. A tie between two matched races is settled only if every tied name
 * resolves to the same card.
 *
 * @return {{key: string}|{why: string}}
 */
function resolver( { context, keyOf, history } ) {
	const cardKeys = [ ...new Set( context.rows.map( ( r ) => r.key ) ) ];

	const ownCard = ( name ) => {
		const key = keyOf.get( name ) ?? null;
		return key && cardKeys.includes( key ) ? key : null;
	};

	const containingCard = ( name ) => {
		const words = ( keyOf.get( name ) ?? normalise( name ) ).split( ' ' ).filter( Boolean );
		if ( ! words.length ) return null;

		const hits = cardKeys.filter( ( k ) => {
			const kw = k.split( ' ' );
			return words.every( ( w ) => kw.includes( w ) ) || kw.every( ( w ) => words.includes( w ) );
		} );

		return 1 === hits.length ? hits[ 0 ] : null;
	};

	const fromHistory = ( list ) => {
		const keys = [ ...new Set( list.map( ( h ) => h.key ) ) ];
		if ( ! list.length ) return null;
		return 1 === keys.length ? { key: keys[ 0 ] } : { why: `earlier galleries like this are on different cards (${ keys.join( ', ' ) })` };
	};

	return ( g, exceptUrl = null ) => {
		const past = history.filter( ( h ) => h.url !== exceptUrl );

		const sig = folderSignature( g.name ?? '' );
		const bySig = sig ? fromHistory( past.filter( ( h ) => h.sig === sig ) ) : null;
		if ( bySig ) return bySig;

		const names = [ g.race, ...( g.tied ?? [] ) ];
		const keys = new Set();

		for ( const name of names ) {
			let key = ownCard( name );

			if ( ! key ) {
				const viaHistory = fromHistory( past.filter( ( h ) => h.race === name ) );
				if ( viaHistory?.why ) return viaHistory;
				key = viaHistory?.key ?? containingCard( name );
			}

			keys.add( key );
		}

		if ( keys.size > 1 ) return { why: `ambiguous: matches ${ names.join( ' / ' ) }` };

		const [ key ] = keys;
		return key ? { key } : { why: 'race has no card on /photos/ yet' };
	};
}

/**
 * Plan a run.
 *
 * @param {object}   args
 * @param {object[]} args.discovered From walkAccount(): race, tied, year, by, url, name, account, photos.
 * @param {object}   args.context    From photos-ingest.php context mode.
 * @param {number}   [args.max]      Most rows one run may add.
 * @return {{add: object[], review: object[], present: object[], agreement: {agree: number, disagree: object[]}}}
 */
export function planIngest( { discovered, context, max = 15 } ) {
	const { keyOf } = matchNames( context );
	const storeKeys = context.rows.map( ( r ) => urlKey( r.url ) );

	const cardsByKey = new Map();
	for ( const r of context.rows ) {
		if ( ! cardsByKey.has( r.key ) ) cardsByKey.set( r.key, [] );
		cardsByKey.get( r.key ).push( r );
	}

	// What the store already did with galleries discovery can see.
	const history = [];
	for ( const g of discovered ) {
		const exact = context.rows.find( ( r ) => urlKey( r.url ) === urlKey( g.url ) );
		if ( exact ) history.push( { url: urlKey( g.url ), sig: folderSignature( g.name ?? '' ), race: g.race, key: exact.key } );
	}

	const resolve = resolver( { context, keyOf, history } );

	const add = [];
	const review = [];
	const present = [];
	const agreement = { agree: 0, disagree: [] };
	const seen = new Set();

	for ( const g of discovered ) {
		const where = { url: g.url, folder: g.name, account: g.account, photos: g.photos };

		if ( seen.has( urlKey( g.url ) ) ) continue;
		seen.add( urlKey( g.url ) );

		if ( urlPresent( g.url, storeKeys ) ) {
			present.push( { race: g.race, year: g.year, url: g.url } );

			// Free accuracy check on every run: for each gallery already in the
			// store, would this have filed it on the card it is on, with its own
			// row left out of the history? A wrong answer here is a wrong row
			// next year, so it is logged on every run.
			const exact = context.rows.find( ( r ) => urlKey( r.url ) === urlKey( g.url ) );
			if ( exact ) {
				const r = resolve( g, urlKey( g.url ) );
				if ( r.key === exact.key && exact.year === g.year ) agreement.agree++;
				else if ( r.key || exact.year !== g.year ) agreement.disagree.push( { url: g.url, store: `${ exact.display } ${ exact.year }`, would: `${ r.key ? cardName( cardsByKey.get( r.key ) ) : '?' } ${ g.year }` } );
				else agreement.disagree.push( { url: g.url, store: `${ exact.display } ${ exact.year }`, would: `review: ${ r.why }`, safe: true } );
			}
			continue;
		}

		const r = resolve( g );

		if ( ! r.key ) {
			review.push( { ...where, race: g.race, year: g.year, by: g.by, why: r.why } );
			continue;
		}

		const cards = cardsByKey.get( r.key );
		const name = cardName( cards );

		if ( cards.some( ( c ) => c.year === g.year && c.by === g.by ) ) {
			review.push( { ...where, race: name, year: g.year, by: g.by, why: `card already has a ${ g.by } gallery for ${ g.year }` } );
			continue;
		}

		add.push( { race: name, year: g.year, by: g.by, url: g.url, folder: g.name, account: g.account, photos: g.photos } );
	}

	// Two new galleries for one card, year and photographer in the same run
	// is the same judgement call as above.
	const groups = new Map();
	for ( const r of add ) {
		const k = `${ r.race }|${ r.year }|${ r.by }`;
		groups.set( k, ( groups.get( k ) ?? 0 ) + 1 );
	}
	const clean = [];
	for ( const r of add ) {
		if ( groups.get( `${ r.race }|${ r.year }|${ r.by }` ) > 1 ) {
			review.push( { url: r.url, folder: r.folder, account: r.account, photos: r.photos, race: r.race, year: r.year, by: r.by, why: 'two new galleries for one card, year and photographer' } );
		} else {
			clean.push( r );
		}
	}

	if ( clean.length > max ) {
		for ( const r of clean ) review.push( { url: r.url, folder: r.folder, account: r.account, photos: r.photos, race: r.race, year: r.year, by: r.by, why: `run found ${ clean.length } new galleries, over the cap of ${ max }; nothing added` } );
		return { add: [], review, present, agreement };
	}

	return { add: clean, review, present, agreement };
}
