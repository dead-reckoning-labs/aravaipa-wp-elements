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
 *
 * A timed race is not skipped, though. It is scored the way it is actually
 * run: by how far someone got, in timedWinnersOf() below.
 */
const isTimedRace = ( race ) => race.isTimed === true;

/**
 * Whether a race is a team or relay entry rather than an individual one.
 *
 * A relay's duration is not the same fact about the day as a solo race's:
 * Chase the Moon's "12HR 3-Per Relay" and "12HR 5-Per Relay" run the same
 * twelve hours as its "12HR Solo" by the board's own bookkeeping, since a
 * relay with no stated duration of its own inherits the event's cutoff the
 * same way a solo race without one would. That tie is real on paper and
 * wrong in what it is claiming: three unrelated things measuring twelve
 * hours is not the same as the runner-up genuinely being ten per cent
 * behind the winner, which is what the headline check two paragraphs down
 * is actually asking. Kept out of that one comparison; still listed in the
 * winners table below it, since "who won the 5 Per Relay" is a real answer
 * this event owes a real answer to.
 *
 * Both words, not just "team": the board calls Chase the Moon's own
 * divisions "Relay" and never "Team" at all, which this matched on
 * exclusively until the day that was checked against the actual event it
 * was written for and matched nothing, silently, because a tied headline
 * fails the same way an absent one does.
 */
const isTeamRace = ( race ) => /\b(?:teams?|relays?)\b/i.test( race.name || '' );

/**
 * How long a timed race runs, in hours.
 *
 * Read from the race's own name rather than its cutoff. The cutoff is the
 * moment the course closes, which is not the same number: Chase the Moon's
 * "12HR Solo" carries a cutoff thirteen hours after its start, because the
 * board leaves an hour for a runner already out on a loop to bring it home.
 * Ranking on that would put a twelve hour race above a real thirteen hour
 * one, and the name is the thing the race is actually called.
 *
 * Every spelling the board uses across the archive is one number followed
 * by hr/hrs/hour/hours in any case: "24 Hour", "12HR Solo", "6hrs",
 * "48hrs", "USATF 24HR". The cutoff is the fallback for anything that
 * names no duration at all.
 */
function timedHours( race ) {
	const named = String( race.name || '' ).match( /(\d+)\s*(?:hr|hrs|hour|hours)\b/i );

	if ( named ) {
		return Number( named[ 1 ] );
	}

	if ( race.cutoff && race.startTime ) {
		return ( new Date( race.cutoff ) - new Date( race.startTime ) ) / 3600000;
	}

	return 0;
}

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
 * One entrant's own result, as seconds rather than a formatted string, or
 * null.
 *
 * Extracted from what was the whole of winnerOf(), so a merged multi-wave
 * distance (raceOfWaves() below) can compare several entrants' raw seconds
 * against each other before formatting only the one that wins. Formatting
 * early would have meant parsing "1:59:43" back apart to compare it.
 *
 * Null rather than a best guess at every step. A place with no finish
 * stamp, a stamp pair that subtracts to something no race lasts, a
 * participant with no name: each of those is the board telling us it does
 * not have this result, and an archive row is better carrying no winner
 * than a wrong one.
 *
 * @param {object} race
 * @param {object} entrant
 * @return {object|null}
 */
function entrantResult( race, entrant, gunIsSound ) {
	if ( ! entrant.ft ) {
		return null;
	}

	// Collapsed, not just trimmed: a trailing space inside firstName renders
	// as "Michael  Versteeg" with a visible gap otherwise.
	const name = `${ entrant.firstName || '' } ${ entrant.lastName || '' }`
		.replace( /\s+/g, ' ' )
		.trim();

	if ( ! name ) {
		return null;
	}

	const floor = Math.max(
		MIN_WIN_SECONDS,
		( race.distance || 0 ) / IMPOSSIBLE_PACE_MS
	);

	// A participant's own start stamp first, then a wave's, then the gun,
	// and the first of the three that yields a possible race is the one
	// taken rather than the first that merely exists.
	//
	// It used to be the first that existed, full stop, and a single bad
	// stamp then cost the whole result. Rock Hawk 2025's 50K men's winner
	// carries an st of 13:55 against a race that started at 12:00, an hour
	// and fifty-five minutes of it already run, so subtracting it read as
	// a 1:53 fifty kilometres, quicker than anyone has covered the
	// distance, and the check below correctly threw it out. His actual
	// 3:49:13 was sitting right there in the gun time the whole while:
	// place two ran 4:42, so he did win, and the row said nobody had.
	//
	// Wave start ahead of the gun, since a race that sets one is saying
	// this runner's gun was not the race's gun.
	const candidates = [ entrant.st, entrant.waveStartTime ];

	// The gun, on two different footings depending on what this runner has
	// of their own.
	//
	// A runner with no start stamp at all is a gun start and the gun is
	// simply their start: twelve events on the archive record none, and
	// Royal Gorge's 36 Mile Duo has half a field like it. Nothing is being
	// overridden there, so nothing needs to justify it.
	//
	// A runner who has a stamp that produced an impossible race is a
	// different question, because using the gun then means overruling what
	// the board recorded for them. Worth doing where the rest of the race
	// times cleanly and this is one bad stamp, which is Rock Hawk 2025's
	// 50K. Not worth doing where nothing in the race times cleanly, which
	// is Fat Ox: it scores 50K as a milestone inside a continuous
	// multi-day loop, so every finish stamp on it is a lap scan minutes
	// after its own start, and measuring one of those from the gun gives
	// 21:55:38, a number that clears every bound below while describing
	// nothing that happened.
	if ( ! entrant.st || gunIsSound ) {
		candidates.push( race.startTime );
	}

	for ( const start of candidates ) {
		if ( ! start ) {
			continue;
		}

		const seconds = ( new Date( entrant.ft ) - new Date( start ) ) / 1000;

		if ( seconds >= floor && seconds <= MAX_WIN_SECONDS ) {
			return { name, seconds };
		}
	}

	return null;
}

/**
 * Whether a race's gun time can stand in for a runner's own start stamp.
 *
 * Yes in two cases, and they are different from each other. A race that
 * records no per-runner start at all is a gun start and the gun is simply
 * the right answer: twelve events on the archive are this, Westminster,
 * Waugoshance, Two Hearted, Whiskey Basin among them, and requiring a
 * stamp they never had threw away every winner they scored. A race that
 * does record starts and gets a possible race out of at least one of them
 * is a race whose timing works, where one runner's bad stamp is one bad
 * stamp rather than the shape of the data.
 *
 * No in the third case, which is the one this exists for: a race that
 * records starts and gets nothing possible out of any of them is not
 * timed the way it appears to be. Fat Ox's distance races are milestones
 * inside a continuous loop, so every finish stamp on them sits minutes
 * after its own start, and a gun-time fallback would turn each into a
 * plausible-looking number that is not a finishing time.
 *
 * @param {object} race
 * @param {Array}  field Everyone in that race.
 * @return {boolean}
 */
function startsAreSound( race, field ) {
	const stamped = field.filter( ( p ) => p.st && p.ft );

	if ( ! stamped.length ) {
		return true;
	}

	const floor = Math.max(
		MIN_WIN_SECONDS,
		( race.distance || 0 ) / IMPOSSIBLE_PACE_MS
	);

	return stamped.some( ( p ) => {
		const seconds = ( new Date( p.ft ) - new Date( p.st ) ) / 1000;
		return seconds >= floor && seconds <= MAX_WIN_SECONDS;
	} );
}

/**
 * The first finisher of one division of one race, or null.
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

	const result = entrantResult( race, first, startsAreSound( race, field ) );

	return result && { name: result.name, time: hms( result.seconds ) };
}

/**
 * Whether a board slug is one year's running of a named event.
 *
 * A plain startsWith() was tried first and matched too much: the slug for
 * Royal Gorge Groove's own separate Rides-branded event,
 * "royal_gorge_groove_rides-2022", starts with "royal_gorge_groove" too, so
 * a config entry meant for the mixed run-and-ride event silently reached
 * into an unrelated one and stripped it down to whatever divisions were
 * left. Requiring the dash the year always follows is the fix: no event
 * name on the board contains one of its own.
 *
 * @param {string} slug
 * @param {string} event
 * @return {boolean}
 */
function sameEvent( slug, event ) {
	return String( slug || '' ).startsWith( `${ event }-` );
}

/**
 * Divisions that are bike races, which this site does not report.
 *
 * Aravaipa Rides and Aravaipa Running are separate things and the running
 * archive should not carry the riding, but the timing board does not
 * separate them: Royal Gorge Groove times its ride and its run under one
 * event, so its 2026 board carries "36 Mile Solo" and "36 Mile Duo" beside
 * the 50K and the 30K.
 *
 * Named per event rather than guessed at. "Solo" is not a bike word here:
 * Chase the Moon's "12HR Solo" and "6HR Solo" are foot races, and a rule
 * keying on it would silently drop them. The board gives nothing else to
 * separate the two at Royal Gorge, so the four are listed, from Jamil.
 *
 * A division that says "Bike" is one wherever it appears, which needs no
 * list.
 */
const RIDE_DIVISIONS = {
	'royal_gorge_groove': [
		'36 Mile Solo',
		'36 Mile Duo',
		'18 Mile Solo',
		'12 Mile Solo',
	],
};

/**
 * Whether one division of one event is a bike race.
 *
 * @param {string} slug  The event slug, which carries its year.
 * @param {string} name  The division name.
 * @return {boolean}
 */
function isRide( slug, name ) {
	if ( /\bbikes?\b/i.test( String( name || '' ) ) ) {
		return true;
	}

	// Matched on the event rather than the exact slug so a list written
	// once covers every year of that race, which is how these are set up
	// year after year.
	for ( const [ race, divisions ] of Object.entries( RIDE_DIVISIONS ) ) {
		if ( sameEvent( slug, race ) && divisions.includes( name ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Events the board splits into pace waves rather than into distances.
 *
 * Race the Cog starts its riders in Elite, Intermediate and Chill waves
 * per distance rather than all at once, so the board carries three race
 * objects per distance instead of one: "Elite wave", "Intermediate wave"
 * and "Chill pace wave" for the 2.75 mile Summit Climb, a "Roundtripper"
 * three of the same for the 5.5 mile Devil's Shingle. None of the six is
 * named after the distance and none alone is the event's real result, so
 * scored as six separate races this reported six imaginary distances and
 * the site had no name to call any of them, which is why it had shown no
 * winners for this race at all.
 *
 * Matched in order, first one wins, so a label naming the more specific
 * pattern belongs above a broader one. "Roundtripper" appears in every
 * spelling the board has used across four years ("Roundtripper - Chill",
 * "Devil's Shingle Roundtripper - Chill Wave"); the fallback below it
 * catches the other distance's three waves by requiring the one word every
 * spelling of them has shared just as consistently, "wave", so a division
 * this race adds later that names neither would be left alone rather than
 * folded into a group it was never part of.
 */
const WAVE_GROUPS = {
	race_the_cog: [
		{ label: "Devil's Shingle Roundtripper", match: ( n ) => /roundtripper/i.test( n ) },
		{ label: 'Summit Climb', match: ( n ) => /wave/i.test( n ) },
	],
};

/**
 * Collapse an event's pace-wave races into one race object per real
 * distance, each carrying every wave's field combined.
 *
 * A merged race keeps no single startTime of its own: the three waves it
 * replaces started at three different times, which is the entire reason
 * they exist, and entrantResult() already reads each participant's own
 * start stamp before ever falling back to the race's. Distance is the
 * largest a member wave reports, so a wave recorded a few metres short by
 * GPS noise cannot make the whole group read shorter than it is.
 *
 * Races this event runs that match no group (there are none today, but a
 * future added distance would) pass through unchanged.
 *
 * @param {string} slug
 * @param {Array}  races
 * @param {Map}    byRace
 * @return {{races: Array, byRace: Map}}
 */
function groupWaves( slug, races, byRace ) {
	const groups = Object.entries( WAVE_GROUPS ).find( ( [ race ] ) =>
		sameEvent( slug, race )
	);

	if ( ! groups ) {
		return { races, byRace };
	}

	const merged = [];
	const mergedByRace = new Map( byRace );
	const grouped = new Set();

	for ( const { label, match } of groups[ 1 ] ) {
		const members = races.filter(
			( r ) => ! grouped.has( r.id ) && match( r.name || '' )
		);

		if ( ! members.length ) {
			continue;
		}

		members.forEach( ( r ) => grouped.add( r.id ) );

		const id = `wave-group:${ label }`;
		const field = members.flatMap( ( r ) => byRace.get( r.id ) || [] );

		mergedByRace.set( id, field );
		merged.push( {
			id,
			name: label,
			distance: Math.max( ...members.map( ( r ) => r.distance || 0 ) ),
			isTimed: false,
			// Each member's own entrants already carry the fastest possible
			// per-wave winner in genderPlace 1; raceOfWaves() below compares
			// those against each other rather than re-deriving a place
			// across the merged field, which the board never ranked as one.
			waveMembers: members,
		} );
	}

	const untouched = races.filter( ( r ) => ! grouped.has( r.id ) );

	return { races: [ ...untouched, ...merged ], byRace: mergedByRace };
}

/**
 * Every division's winner for one merged pace-wave distance, or null.
 *
 * Each member wave's own genderPlace 1 is that wave's fastest, by the
 * board's own scoring; this only has to pick the fastest of those two or
 * three against each other, which entrantResult()'s seconds make a plain
 * comparison rather than a re-derivation of anything the board already
 * decided.
 *
 * @param {object} mergedRace A race object from groupWaves(), carrying waveMembers.
 * @param {Map}    byRace
 * @return {object|null}
 */
function waveGroupWinnersOf( mergedRace, byRace ) {
	const row = { distance: mergedRace.name };
	let any = false;

	for ( const [ key, code ] of DIVISIONS ) {
		let best = null;

		for ( const wave of mergedRace.waveMembers ) {
			const field = ( byRace.get( wave.id ) || [] ).filter(
				( p ) => p.gender === code && 1 === p.genderPlace
			);

			if ( ! field.length ) {
				continue;
			}

			const result = entrantResult(
				wave,
				field[ 0 ],
				startsAreSound( wave, byRace.get( wave.id ) || [] )
			);

			if ( result && ( ! best || result.seconds < best.seconds ) ) {
				best = result;
			}
		}

		if ( best ) {
			row[ key ] = { name: best.name, time: hms( best.seconds ) };
			any = true;
		}
	}

	return any ? row : null;
}

/**
 * Whether a race's name states how far it is rather than how long it runs.
 *
 * "100 Mile", "Sat 60K", "Sun 33K" are distances. "24 Hour", "6hrs",
 * "6 Day", "12H" are durations, and a duration written with a unit that
 * starts with an H is why the two are tested together rather than
 * separately: "12H" would otherwise read as a distance of 12 somethings.
 *
 * Names carrying no number at all fall through as durations, which is the
 * safe side of the line: "Last Person Standing" and "Loop" are both scored
 * on ground covered.
 *
 * @param {string} name
 * @return {boolean}
 */
function statesADistance( name ) {
	const s = String( name || '' );

	if ( /\d\s*(?:h|hr|hrs|hour|hours|day|days|min|mins)\b/i.test( s ) ) {
		return false;
	}

	return /\d\s*(?:mile|miler|miles|km|k)\b/i.test( s );
}

/**
 * The winner of one division of a timed race: the one who covered most.
 *
 * A timed race has no finish line to subtract stamps across. Everyone runs
 * the same six or twenty-four hours and the result is ground covered, so
 * this reports a distance where winnerOf() reports a time. The archive
 * already stores fixed-time results that way, "63.6 mi" in the same field
 * a real distance's race puts "4:37:15" into, so nothing downstream has to
 * learn a new shape.
 *
 * Ranked on the board's own genderPlace rather than on lapCount here.
 * Checked against every timed race on the archive and the two agree on
 * every one of them, and where they ever disagree the board is the side
 * holding the tie rules, the DNF flags and the partial-lap decisions that
 * this has no way to see.
 *
 * race.distance on a timed race is the lap, not the total: its splits run
 * 0 to distance around one loop. Total covered is that many laps of it.
 *
 * @param {object} race
 * @param {Array}  field Finishers in that race, already filtered to one division.
 */
function timedWinnerOf( race, field ) {
	// A race whose name states a distance is a distance race, whatever the
	// board's isTimed flag says. Several are lap-counted because they are
	// run on a loop, and Fat Ox's "100 Mile" is one: 101 laps of its 1590m
	// loop comes to 99.8, so reporting ground covered would print "99.8 mi"
	// beside a race called 100 Mile and read as finishing a fifth of a mile
	// short. What that race wants is a winning time, which the board does
	// not hold for it, and no answer beats a wrong one.
	if ( statesADistance( race.name ) ) {
		return null;
	}

	// genderPlace is the field to trust where it is filled in, and it is not
	// always. Fat Ox 2021's 48hrs has 26 entrants and exactly one of them
	// carries a gender place at all, the outright winner; every other
	// starter in the division is a 0, all eleven women included. Filtering
	// on 1 alone found no woman to report and the row published a men's
	// winner beside an empty cell, which reads as "no woman finished" rather
	// than as "this board never scored the division".
	//
	// Ground covered is the same answer wherever both are present, and the
	// only answer where the place is not: a timed race is won by whoever
	// went furthest, which is what lapCount already says. Deborah
	// Huntzinger's 472 laps are four places clear of the next woman's 411.
	const placed = field.filter( ( p ) => 1 === p.genderPlace )[ 0 ];
	const furthest = [ ...field ].sort(
		( a, b ) => Number( b.lapCount || 0 ) - Number( a.lapCount || 0 )
	)[ 0 ];
	const first = placed || furthest;

	if ( ! first || ! race.distance ) {
		return null;
	}

	const laps = Number( first.lapCount || 0 );

	// Nobody completed a lap, so there is no distance to report. Rare, but
	// a 3 Hour with a long loop can end this way, and "0.0 mi" beside a
	// name reads as a timing fault rather than as what happened.
	if ( laps < 1 ) {
		return null;
	}

	const name = `${ first.firstName || '' } ${ first.lastName || '' }`
		.replace( /\s+/g, ' ' )
		.trim();

	if ( ! name ) {
		return null;
	}

	// One decimal, the same precision the archive's existing fixed-time
	// results carry. A tenth of a mile is inside one lap on every loop the
	// board runs, so more digits would be false precision.
	return { name, time: `${ ( ( laps * race.distance ) / 1609.344 ).toFixed( 1 ) } mi` };
}

/**
 * Every division's winner for one timed race, or null if none resolved.
 *
 * @param {object} race
 * @param {Array}  field Everyone in that race.
 */
function timedWinnersOf( race, field ) {
	const row = { distance: race.name || '' };
	let any = false;

	for ( const [ key, code ] of DIVISIONS ) {
		const winner = timedWinnerOf(
			race,
			field.filter( ( p ) => p.gender === code )
		);

		if ( winner ) {
			row[ key ] = winner;
			any = true;
		}
	}

	return any ? row : null;
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

	const byRaceRaw = new Map();
	for ( const p of participants ) {
		if ( ! byRaceRaw.has( p.raceId ) ) {
			byRaceRaw.set( p.raceId, [] );
		}
		byRaceRaw.get( p.raceId ).push( p );
	}

	// A handful of events (Race the Cog today) split into pace waves rather
	// than into distances, so this stands in for event.races and byRaceRaw
	// everywhere below: an event with no wave groups gets both back
	// unchanged.
	const { races, byRace } = groupWaves( event.slug, event.races || [], byRaceRaw );

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
	// Bike divisions are dropped before anything else touches them, not
	// just before the winners list. Royal Gorge Groove 2026's board runs
	// two ride distances at the identical GPS-measured length, "36 Mile
	// Solo" and "36 Mile Duo", both longer than every real running
	// distance on the card. Left in scored below, those two tie for
	// longest against each other, the ratio check two paragraphs down
	// reads that tie as "no distinct premier distance," and the row lost
	// its name preview despite 50K clearly being the real headline once
	// the rides are gone. The bug this fixes and the fix from earlier
	// today share a cause: a filter applied to the output is one filter
	// too late for anything the output's own ranking still depends on.
	const divisions = races.filter( ( r ) => ! isRide( event.slug, r.name ) );

	const scored = [ ...divisions ]
		.filter( ( r ) => r.distance && ! isTimedRace( r ) )
		.sort( ( a, b ) => b.distance - a.distance );

	// Timed races, longest duration first. Sorting these by r.distance
	// would sort by lap length, which says nothing: Hotfoot Hamster runs
	// its 6, 12 and 24 hour races over the same 500m loop, so all three
	// would tie at 500 and the order would fall to whatever the board
	// happened to list first.
	const timed = [ ...divisions ]
		.filter( ( r ) => r.distance && isTimedRace( r ) )
		.sort( ( a, b ) => timedHours( b ) - timedHours( a ) );

	// Timed above real-distance where an event runs both. This is the same
	// call scripts/backfill-ultrarunning-years.mjs documents for the same
	// pairing: at every Aravaipa event built this way the fixed-time race
	// is the marquee, not a shorter race sharing the page with a longer
	// one. Desert Solstice bills its 24 Hour over its own 100 Mile, and
	// Fat Ox its 48 Hour over its 100 Mile.
	//
	// The two cannot be sorted against each other on any shared number:
	// one is measured in hours and the other in metres, and 24 is not
	// smaller than 160934. Ordering them by kind is the only honest
	// answer, and it happens to be the right one.
	const ranked = [ ...timed, ...scored ];

	const winners = ranked
		.map( ( race ) =>
			race.waveMembers
				? waveGroupWinnersOf( race, byRace )
				: isTimedRace( race )
				? timedWinnersOf( race, byRace.get( race.id ) || [] )
				: winnersOf( race, byRace.get( race.id ) || [] )
		)
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
	// Measured within whichever kind leads, since the ratio only means
	// something between two numbers on the same axis. An event whose
	// longest is a timed race compares durations, which are named in whole
	// hours and never land inside ten per cent of each other, so a 24 Hour
	// over a 12 Hour is a headline and a lone 6 Hour is too.
	const lead = ranked[ 0 ];
	const sameKind = lead && isTimedRace( lead ) ? timed : scored;
	const measure = lead && isTimedRace( lead ) ? timedHours : ( r ) => r.distance;

	// Team races excluded from deciding the gate, not from sameKind itself:
	// an event that is only ever run as a relay, with no individual entry
	// at all, still deserves the ordinary one-race-means-headline rule
	// rather than being forced through a pool that filtering would leave
	// empty.
	const solo = sameKind.filter( ( r ) => ! isTeamRace( r ) );
	const gate = solo.length ? solo : sameKind;

	const headline =
		1 === gate.length ||
		( gate.length > 1 &&
			measure( gate[ 0 ] ) >= measure( gate[ 1 ] ) * DISTINCT_LONGEST_RATIO );

	// How many entrants the board lists on arrival, which is what the embedded
	// frame is sized to. It shows one distance at a time and opens on the
	// longest, the same one scored[0] is, so this is that race's field rather
	// than the event's.
	//
	// The largest field would be the safer number, since the reader can
	// switch distance and a shorter frame then scrolls inside itself again.
	// It is not the one used: Black Bear's longest distance is 64 entrants
	// against 87 in its biggest, so sizing to the biggest hangs a thousand
	// pixels of empty board under every page on arrival to spare a nested
	// scrollbar on a deliberate second action. Switching distance is no worse
	// than it is today; the view everyone lands on is fixed.
	const listed = ranked.length ? ranked[ 0 ] : divisions[ 0 ];
	const rows = listed ? ( byRace.get( listed.id ) || [] ).length : 0;

	return {
		slug: event.slug,
		name: event.name,
		starters: participants.length,
		finishers,
		rows,
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

	// Two ids can carry the same slug. Ram Party 2024 is board id 321, with
	// 379 participants and every winner intact, and also id 335, empty,
	// nobody's registration or a duplicate never cleaned up. Posting keys
	// its store by slug, so both cannot survive, and which one did was
	// decided by which worker happened to finish last: this ran clean
	// three times in a row and then, without the archive or this script
	// changing at all, posted Ram Party 2024 as zero finishers, because
	// that time 335 finished after 321 instead of before it. Kept here by
	// finishers rather than by arrival order, which is the one rule that
	// gives the same answer regardless of which worker gets there first.
	const bySlug = new Map();

	for ( const event of found ) {
		const kept = bySlug.get( event.slug );

		if ( ! kept || event.finishers > kept.finishers ) {
			bySlug.set( event.slug, event );
		}
	}

	return [ ...bySlug.values() ].sort( ( a, b ) => a.slug.localeCompare( b.slug ) );
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
