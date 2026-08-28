#!/usr/bin/env node
/**
 * Finisher counts and winners for every event the timing board has carried.
 *
 * The board's own event endpoint, /api/v1/race_events/{id}?live, is public
 * and returns the full participant list with finish times and places. The
 * "?live" is load-bearing: without it the same URL is 401.
 *
 * There is no way to ask the board for an event by slug. The only listing
 * endpoint, race_events/live, returns just what is being timed right now and
 * ignores a ?slug= filter entirely, so it cannot reach the archive. Ids are
 * dense integers from 1, so this walks them. About 440 events exist inside
 * the first 540 ids and the whole walk takes eighty seconds, which is a
 * price worth paying once a week for a store that only grows.
 *
 * Usage:
 *   node scripts/fetch-stats.mjs                 # walk, print a summary
 *   node scripts/fetch-stats.mjs --out stats.json
 *   node scripts/fetch-stats.mjs --post --dry-run
 *   node scripts/fetch-stats.mjs --post
 *
 * Credentials for --post come from ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and
 * ARAVAIPA_WP_APP_PASSWORD, same as the other fetchers.
 */

import { writeFileSync } from 'node:fs';

const API = 'https://live.aravaiparunning.com/api/v1';

// The site's security plugin answers a default script User-Agent with a 406,
// so every request out of here carries a real browser string. This is not
// cleverness, it is the difference between working and not.
const UA =
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ' +
	'(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

const args = process.argv.slice( 2 );
const flag = ( name ) => args.includes( name );
const opt = ( name, fallback ) => {
	const i = args.indexOf( name );
	return i === -1 ? fallback : args[ i + 1 ];
};

// Walked to a ceiling rather than until the first miss: the id space has
// gaps in it (deleted test events), so stopping at a 404 would stop early.
const MAX_ID = Number( opt( '--max', '600' ) );
const WORKERS = Number( opt( '--workers', '8' ) );

/**
 * A winning time is only meaningful for a race with a finish line.
 *
 * In a fixed-time race everyone runs for the same six, twelve or twenty-four
 * hours and the winner is whoever covered the most ground, so "won in
 * 3:12:11" is not a slower or faster version of the truth, it is a category
 * error. The board's own participant stamps agree: subtracting them on a
 * timed event yields the last lap, which is why these came out as winning
 * times of about three minutes before this check existed.
 */
const isTimedRace = ( race ) => race.isTimed === true;

/**
 * The fastest a human can cover ground, in metres per second, rounded up
 * past anything real.
 *
 * A flat floor cannot work now that every distance is reported rather than
 * only the longest. Ten minutes was a safe minimum for a 50K and would
 * throw away the winner of a one mile fun run, who is expected to take
 * five. Scaling the floor by the distance instead keeps one rule for both:
 * seven metres per second is quicker than the mile world record, so
 * anything under it is the board's arithmetic rather than a runner.
 */
const IMPOSSIBLE_PACE_MS = 7;

/**
 * And an absolute floor underneath that, for the case the pace rule cannot
 * catch: a start and finish stamp that are the same instant subtract to
 * zero whatever the distance. One of these is live on the archive right
 * now, a 6K winner reading 0:00:00.
 */
const MIN_WIN_SECONDS = 60;
const MAX_WIN_SECONDS = 120 * 3600;

/**
 * The board's gender codes, in the order a results table reads them.
 *
 * X is a division Aravaipa actually scores, not a stray value: Javelina
 * 2025's Jackass 31K placed four nonbinary finishers first through fourth,
 * and Black Canyon has scored it twice. Nine events on the archive have a
 * nonbinary category winner, so dropping the code would quietly erase real
 * results from the flagship race.
 */
const DIVISIONS = [
	[ 'men', 'M' ],
	[ 'women', 'F' ],
	[ 'nonbinary', 'X' ],
];

/**
 * How much longer the longest distance has to be than the next one down
 * before it counts as the event's premier race.
 *
 * Ten per cent clears every real calendar pairing comfortably: the closest
 * two distances any event here offers are 100K and 50 Mile, which are
 * twenty-four per cent apart. It is only lap events, where every category
 * shares one loop, that fall inside it.
 */
const DISTINCT_LONGEST_RATIO = 1.1;

function hms( seconds ) {
	const s = Math.round( seconds );
	const h = Math.floor( s / 3600 );
	const m = Math.floor( ( s % 3600 ) / 60 );
	return `${ h }:${ String( m ).padStart( 2, '0' ) }:${ String( s % 60 ).padStart( 2, '0' ) }`;
}

async function getJson( url ) {
	const res = await fetch( url, { headers: { 'User-Agent': UA } } );
	if ( ! res.ok ) {
		return null;
	}
	return res.json();
}

/**
 * The first finisher of one division of one race, or null.
 *
 * Null rather than a best guess at every step. A place with no finish stamp,
 * a stamp pair that subtracts to something no race lasts, a participant with
 * no name: each of those is the board telling us it does not have this
 * result, and an archive row is better carrying no winner than a wrong one.
 *
 * @param {object} race
 * @param {Array}  field Finishers in that race, already filtered to one division.
 */
function winnerOf( race, field ) {
	const placed = field.filter( ( p ) => p.genderPlace );

	if ( ! placed.length ) {
		return null;
	}

	const first = placed.reduce( ( a, b ) =>
		a.genderPlace <= b.genderPlace ? a : b
	);

	// A participant's own start stamp where there is one, the gun where there
	// is not. Twelve events on the archive are gun-start races and record no
	// per-runner start at all, so requiring one threw away every winner they
	// had: Westminster, Waugoshance, Two Hearted, Whiskey Basin and the rest.
	// Wave start first of the two fallbacks, since a race that sets one is
	// saying this runner's gun was not the race's gun.
	const start = first.st || first.waveStartTime || race.startTime;

	if ( ! start || ! first.ft ) {
		return null;
	}

	const seconds = ( new Date( first.ft ) - new Date( start ) ) / 1000;
	const floor = Math.max(
		MIN_WIN_SECONDS,
		( race.distance || 0 ) / IMPOSSIBLE_PACE_MS
	);

	if ( ! ( seconds >= floor && seconds <= MAX_WIN_SECONDS ) ) {
		return null;
	}

	// Collapsed, not just trimmed: a trailing space inside firstName renders
	// as "Michael  Versteeg" with a visible gap otherwise.
	const name = `${ first.firstName || '' } ${ first.lastName || '' }`
		.replace( /\s+/g, ' ' )
		.trim();

	if ( ! name ) {
		return null;
	}

	return { name, time: hms( seconds ) };
}

/**
 * Every division's winner for one distance, or null if none resolved.
 *
 * @param {object} race
 * @param {Array}  field Everyone in that race.
 */
function winnersOf( race, field ) {
	const finished = field.filter( ( p ) => p.ft );
	const row = { distance: race.name || '' };
	let any = false;

	for ( const [ key, code ] of DIVISIONS ) {
		const winner = winnerOf(
			race,
			finished.filter( ( p ) => p.gender === code )
		);

		if ( winner ) {
			row[ key ] = winner;
			any = true;
		}
	}

	return any ? row : null;
}

/**
 * One event, reduced to the handful of facts the archive shows.
 */
function summarise( event ) {
	const participants = event.participants || [];
	const races = event.races || [];

	const byRace = new Map();
	for ( const p of participants ) {
		if ( ! byRace.has( p.raceId ) ) {
			byRace.set( p.raceId, [] );
		}
		byRace.get( p.raceId ).push( p );
	}

	const finishers = participants.filter( ( p ) => p.ft ).length;

	// Longest first, which is both how a race lists its own distances and
	// which one the board itself defaults to showing. Races with no distance
	// recorded are dropped rather than sorted to the bottom: a junior loop
	// with a null distance was otherwise leading some events outright, purely
	// by being the only number the sort could see.
	//
	// Fixed-time races are dropped too. Everyone runs the same twelve or
	// twenty-four hours and the winner is whoever covered most ground, so
	// subtracting stamps gives the last lap: these read as winning times of
	// about three minutes before this existed.
	const scored = [ ...races ]
		.filter( ( r ) => r.distance && ! isTimedRace( r ) )
		.sort( ( a, b ) => b.distance - a.distance );

	const winners = scored
		.map( ( race ) => winnersOf( race, byRace.get( race.id ) || [] ) )
		.filter( Boolean );

	// Whether the longest distance is the event's premier race, or just the
	// first of several that are all the same length. A lap event runs every
	// category over one loop, so its distances come back within metres of
	// each other and "longest" measures GPS noise: it made the junior loop
	// Adrenaline Night Rides' headline race by twelve metres.
	//
	// This only gates the headline. The table below it still lists every
	// distance, because "who won the six hour solo" is a real answer even
	// where "who won the event" is not.
	const headline =
		scored.length === 1 ||
		( scored.length > 1 &&
			scored[ 0 ].distance >=
				scored[ 1 ].distance * DISTINCT_LONGEST_RATIO );

	return {
		slug: event.slug,
		name: event.name,
		starters: participants.length,
		finishers,
		headline,
		...( winners.length ? { winners } : {} ),
	};
}

async function walk() {
	const ids = [];
	for ( let i = 1; i <= MAX_ID; i++ ) {
		ids.push( i );
	}

	const found = [];
	let next = 0;

	async function worker() {
		while ( next < ids.length ) {
			const id = ids[ next++ ];
			try {
				const event = await getJson( `${ API }/race_events/${ id }?live` );
				if ( event && event.slug ) {
					found.push( summarise( event ) );
				}
			} catch ( e ) {
				// A single unreachable event is not a reason to abandon the
				// walk; the importer's own drop guardrail is what catches a
				// run that lost enough of them to matter.
				process.stderr.write( `id ${ id }: ${ e.message }\n` );
			}
		}
	}

	await Promise.all( Array.from( { length: WORKERS }, worker ) );

	found.sort( ( a, b ) => a.slug.localeCompare( b.slug ) );
	return found;
}

async function post( events ) {
	const base = process.env.ARAVAIPA_WP_URL;
	const user = process.env.ARAVAIPA_WP_USER;
	const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;

	if ( ! base || ! user || ! pass ) {
		throw new Error(
			'ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD must be set'
		);
	}

	const res = await fetch( `${ base }/wp-json/aravaipa/v1/stats/import`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'User-Agent': UA,
			Authorization:
				'Basic ' + Buffer.from( `${ user }:${ pass }` ).toString( 'base64' ),
		},
		body: JSON.stringify( {
			events,
			dry_run: flag( '--dry-run' ),
			force: flag( '--force' ),
		} ),
	} );

	const text = await res.text();
	if ( ! res.ok ) {
		throw new Error( `import failed: ${ res.status } ${ text.slice( 0, 300 ) }` );
	}

	return JSON.parse( text );
}

const started = Date.now();
const events = await walk();

process.stderr.write(
	`${ events.length } events in ${ ( ( Date.now() - started ) / 1000 ).toFixed( 1 ) }s, ` +
		`${ events.filter( ( e ) => e.finishers > 0 ).length } with finishers, ` +
		`${ events.filter( ( e ) => e.winners ).length } with winners, ` +
		`${ events.reduce( ( n, e ) => n + ( e.winners || [] ).length, 0 ) } distances scored\n`
);

const out = opt( '--out', '' );
if ( out ) {
	writeFileSync( out, JSON.stringify( events, null, '\t' ) );
	process.stderr.write( `wrote ${ out }\n` );
}

if ( flag( '--post' ) ) {
	console.log( JSON.stringify( await post( events ), null, '\t' ) );
} else if ( ! out ) {
	console.log( JSON.stringify( events, null, '\t' ) );
}
