#!/usr/bin/env node
/**
 * Find Aravaipa race galleries on Zenfolio, which is where 2009 to 2019 is.
 *
 * The photos store reaches back to 2018 and thins out badly before 2024,
 * because its only source is SmugMug and SmugMug does not have the older
 * years: Let's Wander's account starts at 2015 and its 2015, 2016 and 2017
 * folders are empty shells, and Aravaipa's own account has no folder older
 * than 2019. Aravaipa's pre-SmugMug archive lives on Zenfolio instead, at
 * photos.aravaiparunning.com, with a folder per year from 2009 to 2025 and
 * a gallery per race inside each one.
 *
 * Zenfolio renders its grids with JavaScript, which looked like it would
 * need a browser and does not: the anchors are in the served HTML with the
 * gallery's own name on a title attribute, and only the thumbnails are
 * filled in later. So this is a plain fetch and a regex, like every other
 * scraper here, rather than a headless Chrome.
 *
 * Same rule as the SmugMug walker, and the same matcher, now shared in
 * scripts/lib/race-match.mjs: match-or-reject, never include-by-default.
 * A gallery is only accepted if its name matches a race Aravaipa actually
 * puts on, everything rejected is printed with its reason so the filter
 * can be audited rather than trusted, and rides are dropped because
 * Aravaipa Rides is its own brand on its own site.
 *
 *   node scripts/discover-zenfolio.mjs --races races.txt        # report
 *   node scripts/discover-zenfolio.mjs --races races.txt --json # emit rows
 *   node scripts/discover-zenfolio.mjs --races races.txt --merge current.json
 *
 * Read-only. It never writes to Zenfolio and never writes to WordPress:
 * feed --json into scripts/import-photos.mjs --from, so discovery's output
 * still passes that script's 20%-drop guard rather than posting from a
 * script that only ever reads.
 */
import { readFileSync } from 'node:fs';
import { raceMatcher, yearFrom, IS_RIDE } from './lib/race-match.mjs';

const args = Object.fromEntries(
	process.argv.slice( 2 ).flatMap( ( a, i, all ) =>
		a.startsWith( '--' )
			? [ [ a.slice( 2 ), all[ i + 1 ]?.startsWith( '--' ) === false ? all[ i + 1 ] : true ] ]
			: []
	)
);

const BASE = 'https://photos.aravaiparunning.com';
const INDEX = `${ BASE }/events`;
const BY = 'Aravaipa Photo Gallery';

/**
 * Deliberately not a browser's user agent, which is the opposite of what
 * every other scraper in this directory does and the opposite of what the
 * next person will reach for.
 *
 * Zenfolio switches its own rendering on the user agent and says so in a
 * comment at the top of the page it serves: "UA Code: chrome" gets a
 * JavaScript shell with one anchor in it and the grid drawn client side,
 * "UA Code: lynx" gets the whole grid server-rendered with every gallery's
 * name on a title attribute. So a realistic Chrome string, which is the
 * usual way past a host that dislikes scripts, is the one thing that makes
 * this page unreadable without a browser. Anything that is not a browser
 * gets the good version, so this asks as itself.
 */
const UA = 'Mozilla/5.0 (compatible; aravaipa-elements)';

const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

/**
 * The race list, read when the script runs rather than when it is imported.
 *
 * At module scope this exited the process on --races being absent, which is
 * right for a command and wrong for a module: the tests beside this import
 * galleriesOn() and yearFoldersOn() and got an exit code instead.
 *
 * @return {(name: string) => string|null}
 */
function loadMatcher() {
	if ( ! args.races ) {
		console.error( '--races <file> is required: one Aravaipa race name per line' );
		process.exit( 1 );
	}

	return raceMatcher(
		String( readFileSync( args.races, 'utf8' ) )
			.split( '\n' )
			.map( ( s ) => s.trim() )
			.filter( Boolean )
	);
}

/**
 * One page, retried on anything that might be temporary.
 *
 * 429 is explicitly among those, which it was not on the first pass: it is
 * under 500, so it fell through the "client errors are final" branch and
 * returned null immediately. Zenfolio does throttle a run of eighteen
 * requests, and the way that surfaced was a year folder quietly coming back
 * with nothing while every other year worked. A dropped year in a scraper
 * that reports its own totals looks exactly like a year with no galleries
 * in it, which is the kind of wrong that gets believed.
 */
async function page( url ) {
	let last = '';

	// Long, because Zenfolio's throttle window is measured in minutes, not
	// seconds: eighteen pages in quick succession earns a 429 that a
	// fifteen-second retry ladder never outlasts. 5s, 20s, 45s, 80s, 125s.
	for ( let i = 0; i < 5; i++ ) {
		try {
			const res = await fetch( url, { headers: { 'User-Agent': UA } } );

			if ( res.ok ) return await res.text();

			last = `HTTP ${ res.status }`;

			// A real 404 is an answer. Everything else here is worth asking
			// again for: 429 because that is the throttle, 5xx because it is
			// their end having a moment.
			if ( 404 === res.status ) return null;
		} catch ( e ) {
			last = e.message;
		}

		await sleep( 5000 * ( i + 1 ) * ( i + 1 ) );
	}

	console.error( `  giving up on ${ url }: ${ last }` );

	return null;
}

/**
 * Every gallery link on a Zenfolio grid page, name and all.
 *
 * The name comes off the anchor's title attribute rather than the visible
 * label, which is written in later by the script that also loads the
 * thumbnails. Both say the same thing; only one of them is in the HTML
 * this fetches.
 *
 * @param {string} html
 * @return {Array<{href: string, name: string}>}
 */
export function galleriesOn( html ) {
	const out = [];
	const seen = new Set();
	const re = /<a[^>]*class="pv-inner"[^>]*href="([^"]+)"[^>]*title="([^"]*)"/g;
	let m;

	while ( ( m = re.exec( html ) ) !== null ) {
		const href = m[ 1 ];
		const name = m[ 2 ].replace( /\s+/g, ' ' ).trim();

		if ( ! href || ! name || seen.has( href ) ) continue;

		seen.add( href );
		out.push( { href, name } );
	}

	return out;
}

/**
 * The year folders on the index, newest first.
 *
 * "Course Photos" sits among them with no year on it and is not an
 * edition of anything, so it goes the same way any unmatched folder does.
 *
 * @param {string} html
 * @return {Array<{href: string, name: string, year: number}>}
 */
export function yearFoldersOn( html ) {
	return galleriesOn( html )
		.map( ( g ) => ( { ...g, year: yearFrom( g.name ) } ) )
		.filter( ( g ) => g.year > 0 )
		.sort( ( a, b ) => b.year - a.year );
}

const abs = ( href ) => ( href.startsWith( 'http' ) ? href : `${ BASE }${ href }` );

async function main() {
	const raceFor = loadMatcher();

	const index = await page( INDEX );

	if ( ! index ) {
		console.error( `could not read ${ INDEX }` );
		process.exit( 1 );
	}

	const years = yearFoldersOn( index );
	console.error( `${ years.length } year folders on Zenfolio: ${ years.map( ( y ) => y.year ).join( ', ' ) }\n` );

	const accepted = [];
	const rejected = [];

	for ( const folder of years ) {
		const html = await page( abs( folder.href ) );

		if ( ! html ) {
			console.error( `  ${ folder.year }: could not read ${ folder.href }` );
			continue;
		}

		const galleries = galleriesOn( html );
		let took = 0;

		for ( const g of galleries ) {
			// A year folder links back to its siblings in the nav, so the
			// other years turn up inside every one of them.
			if ( yearFrom( g.name ) && /^\s*20\d\d\s+races\s*$/i.test( g.name ) ) continue;

			if ( IS_RIDE.test( g.name ) ) {
				rejected.push( `${ folder.year }  ${ g.name }  (ride)` );
				continue;
			}

			const race = raceFor( g.name );

			if ( ! race ) {
				rejected.push( `${ folder.year }  ${ g.name }  (no race matched)` );
				continue;
			}

			// The gallery's own name wins where it carries a year, since
			// Across the Years straddles two of them and its gallery says
			// which one it belongs to.
			const year = yearFrom( g.name ) || folder.year;

			accepted.push( { race, year, by: BY, url: abs( g.href ) } );
			took++;
		}

		console.error( `  ${ folder.year }  ${ String( galleries.length ).padStart( 3 ) } links, ${ took } matched` );
		await sleep( 2500 );
	}

	// One race can only have one gallery per year on this host, and a year
	// folder occasionally links the same gallery twice.
	const bySlug = new Map();
	for ( const row of accepted ) bySlug.set( `${ row.race }|${ row.year }`, row );
	const rows = [ ...bySlug.values() ].sort( ( a, b ) => b.year - a.year || a.race.localeCompare( b.race ) );

	console.error( `\n${ rows.length } galleries matched, ${ rejected.length } rejected` );

	if ( args.verbose ) {
		for ( const r of rejected ) console.error( `   ${ r }` );
	} else {
		console.error( '   (pass --verbose to list the rejections)' );
	}

	if ( args.merge ) {
		const existing = JSON.parse( readFileSync( args.merge, 'utf8' ) );
		const current = Array.isArray( existing ) ? existing : existing.rows || [];

		// Anything already in the store wins: SmugMug and the hand-built
		// pages know who actually shot a race, and this host credits every
		// gallery to Aravaipa's own account because that is whose Zenfolio
		// it is.
		const have = new Set( current.map( ( r ) => `${ r.race }|${ r.year }` ) );
		const added = rows.filter( ( r ) => ! have.has( `${ r.race }|${ r.year }` ) );

		console.error( `merge: ${ current.length } in the store + ${ added.length } new from Zenfolio` );

		const merged = [ ...current, ...added ].sort(
			( a, b ) => b.year - a.year || a.race.localeCompare( b.race )
		);

		console.error( `       ${ merged.length } rows total` );

		if ( args.json ) console.log( JSON.stringify( merged, null, 1 ) );
		return;
	}

	if ( args.json ) console.log( JSON.stringify( rows, null, 1 ) );
}

if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'discover-zenfolio.mjs' ) ) {
	main().catch( ( e ) => { console.error( e ); process.exit( 1 ); } );
}
