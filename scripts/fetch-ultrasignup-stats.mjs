#!/usr/bin/env node
/**
 * Winners and finisher counts for the years that were scored on UltraSignup.
 *
 * There are two other scrapers beside this one and between them they were
 * meant to cover everything. fetch-stats.mjs walks the Momentum timing
 * board, which Aravaipa has run since about 2023. fetch-archive-stats.mjs
 * reads the static results files the old in-house scoring published, which
 * run from 2008 to about 2019. Neither covers 2020, 2021 or 2022, because
 * in those years Aravaipa had left the one and not yet arrived at the
 * other, and UltraSignup was the system of record.
 *
 * The hole that leaves is not subtle. Editions with winners on the results
 * page, by year:
 *
 *   2019   86%      2022   67%
 *   2020   33%      2023   93%
 *   2021   48%      2024  100%
 *
 * Every other year from 2008 on sits between 86% and 100%. 51 editions
 * rendered as a date and a pair of outbound links, and 45 of them had an
 * UltraSignup results link stored on the row the whole time, pointing at a
 * complete set of results nothing here had ever read.
 *
 * UltraSignup splits one race edition across several event ids, one per
 * distance, and the row in the results store only ever carries one of them.
 * The rest are found the way a reader finds them: the results page for any
 * one distance links to its siblings. So each row's stored id is a way in
 * rather than the whole event, and the distances come off that page.
 *
 *   node scripts/fetch-ultrasignup-stats.mjs                    # walk, report
 *   node scripts/fetch-ultrasignup-stats.mjs --out data/ultrasignup-stats.json
 *   node scripts/fetch-ultrasignup-stats.mjs --only "Pinal Peak"
 *   node scripts/fetch-ultrasignup-stats.mjs --since 2020 --until 2022
 *
 * Writes a file. Deliberately does not post: /stats/archive replaces its
 * option wholesale, and fetch-archive-stats.mjs owns that write. This
 * script's output is merged into that run, filling editions the static
 * files have nothing for and never overwriting one they do, the same way
 * data/archive-stats-manual.json already is.
 *
 * Credentials from ARAVAIPA_WP_URL / _USER / _APP_PASSWORD, read-only here.
 */
import { writeFileSync } from 'node:fs';
import { rankWinners } from './lib/distances.mjs';

const args = process.argv.slice( 2 );
const flag = ( n ) => args.includes( n );
const opt = ( n, d ) => { const i = args.indexOf( n ); return i === -1 ? d : args[ i + 1 ]; };

const WP = process.env.ARAVAIPA_WP_URL;
const USER = process.env.ARAVAIPA_WP_USER;
const PASS = process.env.ARAVAIPA_WP_APP_PASSWORD;

const UA =
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ' +
	'(KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const auth = () => 'Basic ' + Buffer.from( `${ USER }:${ PASS }` ).toString( 'base64' );

const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

const decode = ( s ) =>
	s
		.replace( /&nbsp;/g, ' ' )
		.replace( /&amp;/g, '&' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;/g, "'" )
		.replace( /&#(\d+);/g, ( _, d ) => String.fromCharCode( +d ) );

/**
 * One request, retried on the failures that are worth retrying.
 *
 * UltraSignup answers a burst with a 429 and the occasional 503, and a
 * single dropped distance is not a visibly broken run: it is one missing
 * row in a table of five, which reads as "that race only had four
 * distances" and would never be questioned. So a failure that could be
 * transient is retried rather than shrugged at, and one that survives the
 * retries is counted and reported at the end rather than swallowed.
 */
async function get( url, { json = false, tries = 4 } = {} ) {
	let last = null;

	for ( let i = 0; i < tries; i++ ) {
		try {
			const res = await fetch( url, { headers: { 'User-Agent': UA, Accept: json ? 'application/json' : 'text/html' } } );

			if ( res.status === 429 || res.status >= 500 ) {
				last = `HTTP ${ res.status }`;
				await sleep( 800 * ( i + 1 ) );
				continue;
			}

			if ( ! res.ok ) return { error: `HTTP ${ res.status }` };

			const body = await res.text();

			if ( ! json ) return { body };

			try {
				return { body: JSON.parse( body ) };
			} catch ( e ) {
				// A results id that exists but holds nothing answers with an
				// HTML error page under a 200, which is not a transient
				// failure and must not be retried into one.
				return { error: 'not json' };
			}
		} catch ( e ) {
			last = e.message;
			await sleep( 800 * ( i + 1 ) );
		}
	}

	return { error: last || 'failed' };
}

/**
 * The distance ids that make up one UltraSignup event, from any one of them.
 *
 * The page links to two different things in the same shape: its sibling
 * distances ("91K", "60K", "1/2 Marathon") and the same race in other years
 * ("2026", "2025", "2024"). Telling them apart by label is the only thing
 * available and it is enough, because no Aravaipa distance is named for a
 * bare four-digit number and no year is named anything else.
 *
 * @param {string} html The results_event.aspx page for one distance.
 * @param {number|string} did The id that page was fetched for.
 * @return {Array<{did: string, label: string}>}
 */
export function parseDistances( html, did ) {
	const out = [];
	const seen = new Set();

	const re = /<a[^>]*results_event\.aspx\?did=(\d+)[^>]*>([^<]{1,40})<\/a>/g;
	let m;

	while ( ( m = re.exec( html ) ) !== null ) {
		const id = m[ 1 ];
		const label = decode( m[ 2 ] ).replace( /\s+/g, ' ' ).trim();

		if ( ! label || /^(19|20)\d\d$/.test( label ) ) continue;
		if ( seen.has( id ) ) continue;

		seen.add( id );
		out.push( { did: id, label: normalizeDistance( label ) } );
	}

	// A single-distance race links to no siblings at all, so the id that got
	// us here is the whole event and the page still has to yield it.
	if ( ! out.length ) out.push( { did: String( did ), label: '' } );

	return out;
}

/**
 * A distance label spelled the way the rest of the site spells it.
 *
 * UltraSignup puts a space between the number and the unit, "50 K" and "100
 * Mile", where every other source on the site and every label already in
 * the archive store writes "50K". Left alone the same race would read "50K"
 * in the years off a timing file and "50 K" in the years off here, on the
 * same page, in the same table.
 *
 * A multi-day race also signs its distances with the session they run in,
 * "105K Saturday", "10K-Sat-8pm", "15.5 miler-8:30pm". That is registration
 * information, not a distance, and it stops the label being read as one:
 * "105K Saturday" measures 0 metres to guessMetres, so a Mogollon Monster
 * with a 105K and a 10K would rank them equal and lead with neither.
 *
 * @param {string} label
 * @return {string}
 */
export function normalizeDistance( label ) {
	let s = String( label || '' ).replace( /\s+/g, ' ' ).trim();

	// Session markers come off before the unit is read, since they are what
	// stops it being readable.
	s = s.replace( /[\s-]+\d{1,2}(:\d{2})?\s*[ap]\.?m\.?$/i, '' );
	s = s.replace( /[\s-]+(sat|sun|mon|tue|wed|thu|fri)(urday|day|nday|esday|dnesday|rsday)?\.?$/i, '' );
	s = s.replace( /\s*-\s*$/, '' ).trim();

	s = s.replace( /^([\d.]+)\s*([KkMm])$/, ( _, n, u ) => n + u.toUpperCase() );
	s = s.replace( /^([\d.]+)\s*(kilometers?|kilometres?)$/i, ( _, n ) => `${ n }K` );
	s = s.replace( /^([\d.]+)\s*(mile|miler|miles)$/i, ( _, n ) => `${ n } Mile` );
	s = s.replace( /^([\d.]+)\s*(hour|hours|hr|hrs)$/i, ( _, n ) => `${ n } Hour` );
	s = s.replace( /^([\d.]+)\s*(day|days)$/i, ( _, n ) => `${ n } Day` );

	return s;
}

/**
 * Whether a label is something other than a distance this race was run at.
 *
 * UltraSignup lists three kinds of thing beside the real distances, and all
 * three arrive through the same sibling links:
 *
 * VIRTUAL entries are the 2020 and 2021 stay-at-home editions, run wherever
 * the entrant lived and self-reported. They are not the race. Read as
 * distances they doubled the size of every night-run table and handed the
 * same three or four names a win at every distance: Nate McBride won all
 * five virtual Adrenaline distances and Nicolas Betancourt all five virtual
 * Vertigo ones, because almost nobody entered them.
 *
 * INSOMNIAC entries are the season-long series category. An entrant is
 * scored into both it and the distance they actually ran, so keeping it
 * lists the same race twice and counts the same finishers twice.
 *
 * Pace waves are the third, and they are not this script's to solve:
 * fetch-stats.mjs already merges Race the Cog's Elite, Intermediate and
 * Chill back into the two real distances they are run at, off board data
 * that says which is which. UltraSignup publishes only the waves, so the
 * honest answer here is to read no distances from it and let the board,
 * which covers every year Race the Cog has existed, be the record.
 *
 * @param {string} label A normalized distance label.
 * @return {boolean}
 */
export function isNotADistance( label ) {
	const s = String( label || '' ).trim();

	if ( ! s ) return false;
	if ( /\bvirtual\b/i.test( s ) ) return true;
	if ( /\binsomniac\b/i.test( s ) ) return true;
	if ( /\bwave\b/i.test( s ) ) return true;
	if ( /^roundtripper\s*-/i.test( s ) ) return true;

	return false;
}

const isRide = ( label ) => /\bbikes?\b/i.test( String( label || '' ) );

/**
 * The distances worth reading, out of everything the event page linked to.
 *
 * Bikes are dropped from an event that also ran on foot, which is the rule
 * fetch-stats.mjs settled on for Royal Gorge Groove: a bike division inside
 * a running race is not one of that race's distances. An event where every
 * division is a bike race is a bike race, not a running race with nothing
 * left in it, and Tonto Mountain 2020 is one. Dropping its four divisions
 * for being what the whole event was would leave the edition emptier than
 * it started, and the archive already carries "3hr Ride" and "6hr Ride"
 * from the years those were scored on file.
 *
 * @param {Array<{did: string, label: string}>} distances
 * @return {Array<{did: string, label: string}>}
 */
export function usableDistances( distances ) {
	const kept = ( distances || [] ).filter( ( d ) => ! isNotADistance( d.label ) );

	if ( kept.some( ( d ) => isRide( d.label ) ) && ! kept.every( ( d ) => isRide( d.label ) ) ) {
		return kept.filter( ( d ) => ! isRide( d.label ) );
	}

	return kept;
}

/**
 * Winners and a finisher count for one distance's result rows.
 *
 * UltraSignup marks a finisher with status 1 and a DNF with status 2, and
 * gives a DNF place 0, gender_place 0 and the string "0" for a time. So the
 * winner is not simply the first row: sorting by place would put every DNF
 * ahead of the field, and taking gender_place 1 without checking status
 * would work only for as long as no race ever had an all-DNF gender.
 *
 * @param {Array<object>} rows Parsed results JSON for one distance.
 * @return {{finishers: number, starters: number, men: object|null, women: object|null}}
 */
export function winnersOf( rows ) {
	const list = Array.isArray( rows ) ? rows : [];
	const finishedTimed = list.filter( ( r ) => Number( r.status ) === 1 && isTime( r.formattime ) );

	// Last Person Standing has no finish line: everybody runs the same
	// lap until they can't, and the result is how far they got, not how
	// long it took. UltraSignup reports that in the same formattime field
	// a normal race puts a clock into, "129.3" instead of "8:04:52", which
	// isTime() rejects outright, so Lone Cactus 2020 read as 82 starters
	// and zero finishers.
	//
	// Distance mode only engages where a division's own times fail to
	// parse as clocks at all: a real race's formattime is always a clock,
	// so finishedTimed already caught it and this never overrides it.
	const isDistanceDivision = ! finishedTimed.length
		&& list.some( ( r ) => Number( r.status ) === 1 && isMileage( r.formattime ) );

	const finished = isDistanceDivision
		? list.filter( ( r ) => Number( r.status ) === 1 && isMileage( r.formattime ) )
		: finishedTimed;

	const pick = ( g ) => {
		const own = finished.filter( ( r ) => String( r.gender || '' ).toUpperCase() === g );
		if ( ! own.length ) return null;

		// gender_place is the field to trust where it is filled in, and it
		// is not always: some older editions carry every place as 0. Sorting
		// by the clock, or by ground covered in distance mode, is the same
		// answer wherever both are present and the only answer where one is
		// not.
		own.sort( ( a, b ) => isDistanceDivision
			? Number( b.formattime ) - Number( a.formattime )
			: seconds( a.formattime ) - seconds( b.formattime )
		);

		const w = own[ 0 ];
		const name = `${ ( w.firstname || '' ).trim() } ${ ( w.lastname || '' ).trim() }`.trim();

		if ( ! name ) return null;

		// "129.3 mi", the same shape the archive already stores a fixed-time
		// result in, so nothing downstream has to learn a second one.
		return { name, time: isDistanceDivision ? `${ Number( w.formattime ) } mi` : String( w.formattime ).trim() };
	};

	return {
		finishers: finished.length,
		starters: list.length,
		men: pick( 'M' ),
		women: pick( 'F' ),
	};
}

const isTime = ( v ) => /^\d{1,3}:[0-5]\d(:[0-5]\d)?(\.\d+)?$/.test( String( v || '' ).trim() );
const isMileage = ( v ) => /^\d+(?:\.\d+)?$/.test( String( v || '' ).trim() ) && Number( v ) > 0;

const seconds = ( v ) => {
	const p = String( v || '' ).trim().split( ':' ).map( Number );
	if ( p.some( ( n ) => ! Number.isFinite( n ) ) ) return Infinity;
	if ( p.length === 3 ) return p[ 0 ] * 3600 + p[ 1 ] * 60 + p[ 2 ];
	if ( p.length === 2 ) return p[ 0 ] * 60 + p[ 1 ];
	return Infinity;
};

/**
 * The id in a stored UltraSignup results link, or null when there isn't one.
 *
 * @param {string} url
 * @return {string|null}
 */
export function didOf( url ) {
	const m = String( url || '' ).match( /[?&]did=(\d+)/i );
	return m ? m[ 1 ] : null;
}

async function main() {
	if ( ! WP ) {
		console.error( 'ARAVAIPA_WP_URL is not set' );
		process.exit( 1 );
	}

	const res = await fetch( `${ WP }/wp-json/aravaipa/v1/results`, {
		headers: { Authorization: auth() },
	} );

	if ( ! res.ok ) {
		console.error( `results store: HTTP ${ res.status }` );
		process.exit( 1 );
	}

	const rows = ( await res.json() ).rows || [];
	const since = +opt( '--since', 0 ) || 0;
	const until = +opt( '--until', 9999 ) || 9999;
	const only = opt( '--only', '' ).toLowerCase();

	const targets = rows.filter( ( r ) => {
		if ( ! didOf( r.ultrasignup ) ) return false;

		// A row with a live link is already scored, by the source that
		// actually timed it: arv_stats_for_row() reads the board first and
		// only falls back to this file's output when there is none. Walking
		// it anyway does not change what a visitor sees, but it is not what
		// this script is for, and every one of these read a mileage figure
		// out of a fixed-time division's own UltraSignup row the same way
		// Lone Cactus does, which surfaced the day that reading was fixed:
		// Fat Ox, Jackpot, Desert Solstice and eight others all gained
		// divisions UltraSignup had been silently dropping. Real
		// improvements, and none of them for a row this script exists to
		// cover, so left for the board to keep owning rather than folded
		// into the archive as a fallback nobody asked to widen today.
		if ( r.live ) return false;

		const year = +String( r.iso || '' ).slice( 0, 4 );
		if ( year < since || year > until ) return false;

		return ! only || String( r.name || '' ).toLowerCase().includes( only );
	} );

	console.error( `${ targets.length } edition(s) with an UltraSignup link\n` );

	const events = [];
	const failed = [];
	let done = 0;

	// Four at a time, and the walk is short enough that this is politeness
	// rather than throughput: 65 editions is a few hundred requests, and
	// UltraSignup starts answering 429 well before anything here would
	// notice a speed-up.
	const queue = targets.slice();

	const worker = async () => {
		while ( queue.length ) {
			const row = queue.shift();
			const did = didOf( row.ultrasignup );

			const page = await get( `https://ultrasignup.com/results_event.aspx?did=${ did }` );

			if ( page.error ) {
				failed.push( `${ row.name } ${ row.iso }: event page ${ page.error }` );
				continue;
			}

			const distances = usableDistances( parseDistances( page.body, did ) );
			const winners = [];
			let finishers = 0;
			let starters = 0;
			let rowsMax = 0;

			for ( const d of distances ) {
				const data = await get(
					`https://ultrasignup.com/service/events.svc/results/${ d.did }/1/json`,
					{ json: true }
				);

				if ( data.error ) {
					failed.push( `${ row.name } ${ row.iso } ${ d.label || d.did }: ${ data.error }` );
					continue;
				}

				const got = winnersOf( data.body );

				finishers += got.finishers;
				starters += got.starters;
				rowsMax = Math.max( rowsMax, got.finishers );

				if ( got.men || got.women ) {
					const entry = { distance: d.label || String( row.name ) };
					if ( got.men ) entry.men = got.men;
					if ( got.women ) entry.women = got.women;
					winners.push( entry );
				}

				await sleep( 120 );
			}

			if ( winners.length || finishers ) {
				events.push( {
					name: row.name,
					iso: row.iso,
					starters,
					finishers,
					rows: rowsMax,
					headline: rankWinners( winners ),
					winners,
					source: `ultrasignup did=${ did }`,
				} );
			} else {
				failed.push( `${ row.name } ${ row.iso }: nothing readable` );
			}

			if ( ++done % 10 === 0 ) console.error( `  ${ done }/${ targets.length }` );
		}
	};

	await Promise.all( Array.from( { length: 4 }, worker ) );

	events.sort( ( a, b ) => ( a.iso < b.iso ? 1 : a.iso > b.iso ? -1 : a.name.localeCompare( b.name ) ) );

	console.error( `\n${ events.length } edition(s) read, ${ failed.length } problem(s)` );
	for ( const f of failed.slice( 0, 20 ) ) console.error( `  ${ f }` );
	if ( failed.length > 20 ) console.error( `  ... and ${ failed.length - 20 } more` );

	if ( flag( '--list' ) ) {
		for ( const e of events ) {
			const lead = e.winners[ 0 ];
			const who = lead && lead.men ? `${ lead.men.name } ${ lead.men.time }` : '';
			console.error(
				`  ${ e.iso }  ${ e.name.padEnd( 32 ) } ${ String( e.finishers ).padStart( 4 ) } fin  ` +
				`${ e.winners.length } dist  ${ who }`
			);
		}
	}

	const out = opt( '--out' );

	if ( out ) {
		writeFileSync( out, JSON.stringify( { events }, null, 1 ) + '\n' );
		console.error( `\nwrote ${ out }` );
	} else {
		console.error( '\nnothing written. pass --out to save.' );
	}
}

// Importable for the tests beside it without walking UltraSignup on import.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'fetch-ultrasignup-stats.mjs' ) ) {
	main().catch( ( e ) => { console.error( e ); process.exit( 1 ); } );
}
