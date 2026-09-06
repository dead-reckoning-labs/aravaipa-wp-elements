#!/usr/bin/env node
/**
 * A year-specific UltraRunning link for every edition that can have one.
 *
 * The UltraRunning map (arv_race_ultrarunning) is keyed by race, not by
 * edition, and every entry in it is a bare slug. A bare slug resolves to
 * the race's own index page: every year it has ever run, right there to
 * pick from, which is the honest answer when nothing knows which year this
 * row is. It is also the answer 512 of 560 rows were giving, because only
 * 48 carry a link of their own. Somebody reading the 2011 results and
 * clicking UltraRunning landed on a page about the race in general.
 *
 * UltraRunning numbers each running of each distance and puts them all on
 * one page per race, "/calendar/event/<slug>/race", each one dated. Match
 * those dates against the editions in the results store and a row can say
 * exactly which running it is.
 *
 * That page cannot be read directly: ultrarunning.com is behind Cloudflare
 * and answers a plain request with 403, a headless browser with a
 * challenge that never clears. The Wayback Machine has it for about half
 * the races, and an archived copy of a page listing race ids is as good as
 * a live one, because the ids do not change.
 *
 * A date with exactly one race id is taken as is. A date with several is a
 * race that ran two or more distances that day, UltraRunning numbering
 * each separately: the longest of them wins, going by the winning result's
 * own apparent length (see apparentLength() below), and every one of these
 * picks is reported so it can be checked rather than trusted blind.
 *
 *   node scripts/backfill-ultrarunning-years.mjs --map ur-map.json
 *   node scripts/backfill-ultrarunning-years.mjs --map ur-map.json --out patch.json
 *   node scripts/backfill-ultrarunning-years.mjs --map ur-map.json --post
 *
 * The map is a JSON object of race key => slug, as stored in the
 * arv_race_ultrarunning option.
 *
 * Credentials from ARAVAIPA_WP_URL / _ADMIN_USER / _ADMIN_APP_PASSWORD.
 */
import { writeFileSync, readFileSync } from 'node:fs';

const args = process.argv.slice( 2 );
const flag = ( n ) => args.includes( n );
const opt = ( n, d ) => { const i = args.indexOf( n ); return i === -1 ? d : args[ i + 1 ]; };

const WP = process.env.ARAVAIPA_WP_URL;
const USER = process.env.ARAVAIPA_WP_ADMIN_USER;
const PASS = process.env.ARAVAIPA_WP_ADMIN_APP_PASSWORD;

const auth = () => 'Basic ' + Buffer.from( `${ USER }:${ PASS }` ).toString( 'base64' );

const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

// The archive rate limits, and answers a burst with 503s and hung sockets
// rather than a refusal. Slow, with a retry, because a race skipped for
// being asked about too quickly looks exactly like a race with no archived
// page at all, and the difference matters: one is worth asking again later
// and the other never will be.
async function patient( url, tries = 3 ) {
	for ( let i = 0; i < tries; i++ ) {
		try {
			const r = await fetch( url, {
				headers: { 'User-Agent': 'aravaipa-results-backfill (one request per 1.5s)' },
				signal: AbortSignal.timeout( 45000 ),
			} );

			if ( r.ok ) return await r.text();
			if ( 404 === r.status ) return null;
		} catch ( e ) {
			// falls through to the retry
		}

		await sleep( 4000 * ( i + 1 ) );
	}

	return undefined; // undefined: could not tell. null: not there.
}

// The most recent capture of a race's event page, or null when there is
// none. Asked for as JSON so an empty result is an empty array rather than
// something to parse out of a page.
async function archivedEventPage( slug ) {
	const cdx = 'http://web.archive.org/cdx/search/cdx?url=' +
		encodeURIComponent( `ultrarunning.com/calendar/event/${ slug }/race` ) +
		'&output=json&filter=statuscode:200&limit=-1';

	const raw = await patient( cdx );
	if ( ! raw ) return raw === null ? null : undefined;

	let rows;
	try {
		rows = JSON.parse( raw );
	} catch ( e ) {
		return undefined;
	}

	if ( ! Array.isArray( rows ) || rows.length < 2 ) return null;

	const stamp = rows[ rows.length - 1 ][ 1 ];

	return await patient(
		`http://web.archive.org/web/${ stamp }/https://ultrarunning.com/calendar/event/${ slug }/race`
	);
}

// How long the winning result on this page's own scale says the race was.
// "Top Result (M)" is a finish time, "4:37:15", for a race scored on
// distance, and a number of miles, "100.142", for one scored on time: the
// same split every file on aravaiparunning.com forces, on a page this
// script cannot otherwise read at all. A longer finish time is treated as
// a longer race rather than a slower one, which holds for picking the
// longest of a day's several distances specifically: the pace gap between
// a 50K's winner and a 50 Miler's is nowhere near the distance gap between
// them, so the one who ran longest is reliably the one who ran farthest.
// A finish time in seconds, when the result is one: "4:37:15" for a race
// scored on distance. Never a fixed-time race's mileage, "100.142", which
// this deliberately leaves at 0 rather than trying to read as a time.
function apparentSeconds( result ) {
	const s = result.trim();

	if ( ! /^\d{1,3}:[0-5]\d(:[0-5]\d)?$/.test( s ) ) return 0;

	return s.split( ':' ).reduce( ( a, p ) => a * 60 + +p, 0 );
}

// Miles covered, when the result is one: a bare number, "100.142", for a
// race scored on time rather than distance. Never a finish time, which
// parseFloat would otherwise read as just its leading digits, "4" out of
// "4:37:15", a number with no relationship to how far anyone ran.
function apparentMiles( result ) {
	const s = result.trim();

	if ( /:/.test( s ) ) return 0;

	const n = parseFloat( s );
	return Number.isFinite( n ) ? n : 0;
}

// Which of a day's several distances is the longest, in whichever unit its
// own winning result is reported in. A second and a mile are not the same
// axis, the same reason guessSeconds and guessMetres stay two functions in
// fetch-archive-stats.mjs rather than one: Jackpot Ultras 2024 pairs a real
// distance's finish time, "14:04:40" (50,680 seconds), against a fixed-time
// race's mileage, "197.478", and 50,680 is not a longer race than 197.478
// miles just because it is the bigger number. Where the day mixes the two
// shapes, the fixed-time one wins outright: it is the marquee distance at
// every Aravaipa event built this way (Desert Solstice's 24 Hour over its
// own 100 Mile, Juniperwood's 48 Hour over its 50 Mile), not a shorter race
// that happens to share a page with a longer one. Only when every result on
// the day is the same shape does the raw number decide between them.
function longestOf( ids ) {
	const timed = [ ...ids ].filter( ( r ) => r[ 1 ].miles > 0 );
	const pool = timed.length ? timed : [ ...ids ];
	const key = timed.length ? 'miles' : 'seconds';

	return [ ...pool ].sort( ( a, b ) => b[ 1 ][ key ] - a[ 1 ][ key ] );
}

function racesOn( html ) {
	const page = html.split( '<!-- END WAYBACK TOOLBAR INSERT -->' ).pop();
	const found = new Map();

	for ( const tr of page.matchAll( /<tr>\s*<td>\s*<a[^>]*href="[^"]*?\/race\/(\d+)\/results"[^>]*>\s*(\d\d)\/(\d\d)\/(\d\d)\s*<\/a>[\s\S]*?<\/tr>/g ) ) {
		const [ row, id, mm, dd, yy ] = tr;
		const iso = `20${ yy }-${ mm }-${ dd }`;

		// Finishers, then Top Result (M), then Top Result (F): the three
		// <td>s the row's own <a> is not inside.
		const cells = [ ...row.matchAll( /<td>\s*([^<]*?)\s*<\/td>/g ) ].map( ( c ) => c[ 1 ].trim() );
		const [ m, f ] = [ cells[ 1 ] || '', cells[ 2 ] || '' ];

		const length = {
			seconds: Math.max( apparentSeconds( m ), apparentSeconds( f ) ),
			miles: Math.max( apparentMiles( m ), apparentMiles( f ) ),
		};

		if ( ! found.has( iso ) ) found.set( iso, new Map() );
		found.get( iso ).set( id, length );
	}

	return found;
}

// The same collapsing the site itself does, so a slug found for "Black
// Canyon Ultras" is found for "Black Canyon 100K" too. Kept in step with
// arv_results_race_key() in includes/elements/results.php.
function raceKey( name ) {
	let s = String( name ).toLowerCase().replace( /[^a-z0-9]+/g, ' ' ).trim();
	s = s.replace( /\b(trail|trails|run|runs|race|races|ultra|ultras|endurance|the|presented by.*)\b/g, ' ' );
	s = s.replace( /\b\d+\s*(k|km|m|mi|mile|miler|hour|hr)?\b/g, ' ' );
	s = s.replace( /\s+/g, ' ' ).trim();

	return s
		.split( ' ' )
		.filter( Boolean )
		.map( ( w ) => ( w.length > 4 && w.endsWith( 's' ) ? w.slice( 0, -1 ) : w ) )
		.join( ' ' );
}

async function main() {
	if ( ! WP || ! USER || ! PASS ) {
		console.error( 'ARAVAIPA_WP_URL, ARAVAIPA_WP_ADMIN_USER and ARAVAIPA_WP_ADMIN_APP_PASSWORD are required.' );
		process.exit( 1 );
	}

	const mapPath = opt( '--map' );
	if ( ! mapPath ) {
		console.error( 'pass --map <file>, a JSON object of race key => ultrarunning slug.' );
		process.exit( 1 );
	}

	const map = JSON.parse( readFileSync( mapPath, 'utf8' ) );

	const res = await fetch( `${ WP }/wp-json/aravaipa/v1/results`, { headers: { Authorization: auth() } } );
	if ( ! res.ok ) throw new Error( `results read ${ res.status }` );
	const rows = ( await res.json() ).rows;

	// One slug can be reached by several race keys ("black canyon",
	// "black canyon ultra"), and the editions behind those keys are the
	// same race: gathered per slug so each event page is fetched once.
	const bySlug = new Map();

	for ( const row of rows ) {
		const slug = map[ raceKey( row.name ) ];
		if ( ! slug ) continue;

		if ( ! bySlug.has( slug ) ) bySlug.set( slug, [] );
		bySlug.get( slug ).push( row );
	}

	console.error( `${ bySlug.size } races on the map, ${ [ ...bySlug.values() ].flat().length } editions behind them\n` );

	const patch = [];
	const report = { noPage: [], unsure: [], multi: [], already: 0, matched: 0, noDate: [] };
	let done = 0;

	for ( const [ slug, editions ] of bySlug ) {
		const html = await archivedEventPage( slug );
		await sleep( 1500 );

		if ( ++done % 10 === 0 ) console.error( `  ${ done }/${ bySlug.size }` );

		if ( undefined === html ) { report.unsure.push( slug ); continue; }
		if ( null === html ) { report.noPage.push( slug ); continue; }

		const races = racesOn( html );

		if ( ! races.size ) { report.noPage.push( slug ); continue; }

		for ( const row of editions ) {
			// A link already on the row came from the page itself and is the
			// more specific answer, the same rule the render side follows.
			// "none" is an answer too: the edition was checked and has none.
			const has = String( row.ultrarunning || '' ).trim().toLowerCase();
			if ( '' !== has && 'none' !== has && 'http://none' !== has ) { report.already++; continue; }

			const ids = races.get( row.iso );

			if ( ! ids ) { report.noDate.push( `${ row.name } ${ row.iso }` ); continue; }

			// Several ids on one date is a race that ran more than one
			// distance that day, each numbered separately. The longest of
			// them wins, by longestOf()'s reading of each one's own winning
			// result. Reported either way, so a pairing that turned on a tie
			// or a missing result is visible rather than silent.
			let id;

			if ( 1 === ids.size ) {
				id = [ ...ids.keys() ][ 0 ];
			} else {
				const ranked = longestOf( ids );
				const show = ( r ) => `${ r[ 0 ] } (${ r[ 1 ].miles || r[ 1 ].seconds } ${ r[ 1 ].miles ? 'mi' : 's' })`;
				id = ranked[ 0 ][ 0 ];

				const rest = [ ...ids ].filter( ( r ) => r[ 0 ] !== id );
				const key = ranked[ 0 ][ 1 ].miles ? 'miles' : 'seconds';
				const tie = ranked.filter( ( r ) => r[ 1 ][ key ] === ranked[ 0 ][ 1 ][ key ] ).length > 1;
				report.multi.push(
					`${ row.name } ${ row.iso }: took ${ show( ranked[ 0 ] ) } over ${
						rest.map( show ).join( ', ' )
					}${ tie ? ' -- TIE, check this one' : '' }`
				);
			}

			patch.push( {
				name: row.name,
				iso: row.iso,
				ultrarunning: `https://ultrarunning.com/calendar/event/${ slug }/race/${ id }/results`,
			} );
			report.matched++;
		}
	}

	console.error( `\n${ report.matched } editions matched to their own year` );
	console.error( `${ report.already } already had a link of their own` );
	console.error( `${ report.noPage.length } races have no archived event page: ${ report.noPage.join( ', ' ) }` );

	if ( report.unsure.length ) {
		console.error( `${ report.unsure.length } could not be reached, worth another run: ${ report.unsure.join( ', ' ) }` );
	}

	console.error( `${ report.multi.length } editions ran more than one distance that day, longest taken:` );
	for ( const a of report.multi.slice( 0, 15 ) ) console.error( `  ${ a }` );
	if ( report.multi.length > 15 ) console.error( `  ... and ${ report.multi.length - 15 } more` );

	console.error( `${ report.noDate.length } editions are on no archived page for their race` );

	if ( opt( '--out' ) ) {
		writeFileSync( opt( '--out' ), JSON.stringify( { patch, report }, null, 1 ) );
		console.error( `\nwrote ${ opt( '--out' ) }` );
	}

	if ( ! flag( '--post' ) ) {
		console.error( '\nnothing sent. pass --post to write.' );
		return;
	}

	// Merged into the rows as they stand rather than posted alone: the
	// import route replaces the store wholesale, so anything not sent is
	// gone.
	const byKey = new Map( patch.map( ( p ) => [ `${ p.name }|${ p.iso }`, p.ultrarunning ] ) );
	const merged = rows.map( ( row ) => {
		const url = byKey.get( `${ row.name }|${ row.iso }` );
		return url ? { ...row, ultrarunning: url } : row;
	} );

	for ( const dry of [ true, false ] ) {
		const r = await fetch( `${ WP }/wp-json/aravaipa/v1/results/import`, {
			method: 'POST',
			headers: { Authorization: auth(), 'Content-Type': 'application/json' },
			body: JSON.stringify( { rows: merged, dry_run: dry } ),
		} );

		console.error( ( dry ? 'dry:  ' : 'post: ' ) + await r.text() );
	}
}

main().catch( ( e ) => { console.error( e ); process.exit( 1 ); } );
