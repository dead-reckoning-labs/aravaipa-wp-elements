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
 * A date with exactly one race id is taken. A date with several is not:
 * that is a race that ran two or more distances that day, UltraRunning
 * numbers each of them separately, and nothing here can tell which one the
 * row means. Those are reported rather than guessed at.
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

// Every "race id ran on this date" pair the page lists. The toolbar the
// archive injects carries its own links, so the page proper is taken from
// after it.
function racesOn( html ) {
	const page = html.split( '<!-- END WAYBACK TOOLBAR INSERT -->' ).pop();
	const found = new Map();

	for ( const m of page.matchAll( /href="[^"]*?\/race\/(\d+)\/results"[^>]*>\s*(\d\d)\/(\d\d)\/(\d\d)\s*</g ) ) {
		const [ , id, mm, dd, yy ] = m;
		const iso = `20${ yy }-${ mm }-${ dd }`;

		if ( ! found.has( iso ) ) found.set( iso, new Set() );
		found.get( iso ).add( id );
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
	const report = { noPage: [], unsure: [], ambiguous: [], already: 0, matched: 0, noDate: [] };
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
			// distance that day, each numbered separately, and nothing here
			// can say which one this row is about. Reported, not guessed.
			if ( ids.size > 1 ) {
				report.ambiguous.push( `${ row.name } ${ row.iso }: ${ [ ...ids ].join( ', ' ) }` );
				continue;
			}

			const id = [ ...ids ][ 0 ];

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

	console.error( `${ report.ambiguous.length } editions ran more than one distance that day, left alone:` );
	for ( const a of report.ambiguous.slice( 0, 15 ) ) console.error( `  ${ a }` );
	if ( report.ambiguous.length > 15 ) console.error( `  ... and ${ report.ambiguous.length - 15 } more` );

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
