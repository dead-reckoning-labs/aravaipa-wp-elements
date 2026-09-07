#!/usr/bin/env node
/**
 * Full-archive link and winners audit for the /results/ system.
 *
 * Crawls every /race-results/<race>/ page from the sitemap (checking each
 * one loads), then walks every edition in the results store checking that
 * every link it carries (UltraSignup, the local aravaiparunning.com static
 * archive, RaceResult/RunSignUp/Ultracast, timing.aravaiparunning.com)
 * actually resolves, and cross-references against arv_race_archive_stats
 * to flag any edition showing zero winners despite having a results link.
 *
 * ultrarunning.com is excluded from live HTTP checks: it 403s every plain
 * request regardless of validity (Cloudflare), confirmed repeatedly in an
 * earlier pass of this same audit. Those links are instead checked for
 * *shape* only: does the URL point at a specific race id
 * (/calendar/event/<slug>/race/<id>/results), which is the only thing this
 * script can tell about them without a browser.
 *
 * Usage:
 *   node scripts/audit-results-links.mjs [--out report.json]
 *
 * Credentials from ARAVAIPA_WP_URL / _ADMIN_USER / _ADMIN_APP_PASSWORD.
 */

import { writeFileSync } from 'node:fs';

const WP = process.env.ARAVAIPA_WP_URL;
const USER = process.env.ARAVAIPA_WP_ADMIN_USER;
const PASS = process.env.ARAVAIPA_WP_ADMIN_APP_PASSWORD;

function auth() {
	return 'Basic ' + Buffer.from( `${ USER }:${ PASS }` ).toString( 'base64' );
}

function opt( name ) {
	const i = process.argv.indexOf( name );
	return -1 === i ? null : process.argv[ i + 1 ];
}

function sleep( ms ) {
	return new Promise( ( r ) => setTimeout( r, ms ) );
}

// A small concurrency-limited map, since 1000+ individual link checks run
// serially would take forever and running them all at once gets rate
// limited or just floods the box that's hosting the pages being checked.
async function pool( items, limit, fn ) {
	const results = new Array( items.length );
	let next = 0;

	async function worker() {
		while ( next < items.length ) {
			const i = next++;
			results[ i ] = await fn( items[ i ], i );
		}
	}

	await Promise.all( Array.from( { length: limit }, worker ) );
	return results;
}

async function checkUrl( url ) {
	if ( /ultrarunning\.com/i.test( url ) ) {
		return {
			url,
			skipped: true,
			reason: 'ultrarunning.com blocks all automated requests (Cloudflare), shape-checked instead',
			yearSpecific: /\/race\/\d+\/results/.test( url ),
		};
	}

	try {
		const controller = new AbortController();
		const timer = setTimeout( () => controller.abort(), 15000 );
		let res;
		try {
			res = await fetch( url, { method: 'HEAD', redirect: 'follow', signal: controller.signal } );
			// Some hosts (timing SPAs, a few CDNs) don't implement HEAD sanely.
			if ( 405 === res.status || 501 === res.status ) {
				res = await fetch( url, { method: 'GET', redirect: 'follow', signal: controller.signal } );
			}
		} finally {
			clearTimeout( timer );
		}
		return { url, status: res.status, ok: res.ok };
	} catch ( err ) {
		return { url, error: String( err.message || err ) };
	}
}

async function main() {
	if ( ! WP || ! USER || ! PASS ) {
		console.error( 'ARAVAIPA_WP_URL, ARAVAIPA_WP_ADMIN_USER and ARAVAIPA_WP_ADMIN_APP_PASSWORD are required.' );
		process.exit( 1 );
	}

	const [ rowsRes, statsRes, sitemapRes ] = await Promise.all( [
		fetch( `${ WP }/wp-json/aravaipa/v1/results`, { headers: { Authorization: auth() } } ),
		fetch( `${ WP }/wp-json/aravaipa/v1/stats/archive`, { headers: { Authorization: auth() } } ),
		fetch( 'https://aravaiparunning.com/race-results-sitemap.xml', { redirect: 'follow' } ),
	] );

	const rows = ( await rowsRes.json() ).rows;
	const stats = ( await statsRes.json() ).events;
	const sitemapXml = await sitemapRes.text();
	const pageUrls = [ ...sitemapXml.matchAll( /<loc>([^<]+)<\/loc>/g ) ].map( ( m ) => m[ 1 ] );

	console.error( `${ rows.length } editions, ${ Object.keys( stats ).length } stats entries, ${ pageUrls.length } race pages in the sitemap\n` );

	// 1. Every race page itself loads.
	console.error( 'checking race pages...' );
	const pageResults = await pool( pageUrls, 10, checkUrl );
	const brokenPages = pageResults.filter( ( r ) => r.error || ( r.status && r.status >= 400 ) );

	// 2. Every link on every edition, deduped (the same UltraSignup/timing
	// URL often appears twice: once as the row's own field, once inside
	// archive[]).
	const linkToEditions = new Map();
	for ( const row of rows ) {
		const urls = new Set();
		if ( row.live ) urls.add( row.live );
		if ( row.ultrasignup ) urls.add( row.ultrasignup );
		if ( row.ultrarunning ) urls.add( row.ultrarunning );
		for ( const a of row.archive || [] ) if ( a.url ) urls.add( a.url );

		for ( const url of urls ) {
			if ( ! linkToEditions.has( url ) ) linkToEditions.set( url, [] );
			linkToEditions.get( url ).push( `${ row.name } ${ row.iso }` );
		}
	}

	const allLinks = [ ...linkToEditions.keys() ];
	console.error( `checking ${ allLinks.length } distinct links across all editions...` );
	const linkResults = await pool( allLinks, 15, checkUrl );

	const brokenLinks = linkResults.filter( ( r ) => r.error || ( r.status && r.status >= 400 ) );
	const notYearSpecific = linkResults.filter( ( r ) => r.skipped && ! r.yearSpecific );

	// 3. Winners cross-check: an edition with a results link of some kind
	// but no archive-stats entry, or one with an entry but an empty
	// winners array, is a page that renders a "results" button leading
	// nowhere useful and no winner names shown.
	function archiveKey( name, iso ) {
		return `${ name.toLowerCase().trim() }|${ iso }`;
	}

	const noWinners = [];
	for ( const row of rows ) {
		const hasLink = !! ( row.live || row.ultrasignup || row.ultrarunning || ( row.archive || [] ).length );
		if ( ! hasLink ) continue; // nothing promised, nothing to check

		const entry = stats[ archiveKey( row.name, row.iso ) ];
		if ( ! entry || ! entry.winners || ! entry.winners.length ) {
			noWinners.push( `${ row.name } ${ row.iso }` );
		}
	}

	console.error( `\n${ brokenPages.length } of ${ pageUrls.length } race pages are broken` );
	for ( const p of brokenPages ) console.error( `  ${ p.url } -> ${ p.error || p.status }` );

	console.error( `\n${ brokenLinks.length } of ${ allLinks.length } links are broken` );
	for ( const l of brokenLinks.slice( 0, 40 ) ) {
		console.error( `  ${ l.url } -> ${ l.error || l.status }  (${ linkToEditions.get( l.url ).join( ', ' ) })` );
	}
	if ( brokenLinks.length > 40 ) console.error( `  ... and ${ brokenLinks.length - 40 } more` );

	console.error( `\n${ notYearSpecific.length } ultrarunning.com links are not year-specific (point at the race index, not a result)` );
	for ( const l of notYearSpecific.slice( 0, 30 ) ) {
		console.error( `  ${ l.url }  (${ linkToEditions.get( l.url ).join( ', ' ) })` );
	}
	if ( notYearSpecific.length > 30 ) console.error( `  ... and ${ notYearSpecific.length - 30 } more` );

	console.error( `\n${ noWinners.length } editions have a results link but show no winners` );
	for ( const n of noWinners.slice( 0, 40 ) ) console.error( `  ${ n }` );
	if ( noWinners.length > 40 ) console.error( `  ... and ${ noWinners.length - 40 } more` );

	const report = { brokenPages, brokenLinks, notYearSpecific, noWinners, totals: {
		editions: rows.length, pages: pageUrls.length, links: allLinks.length,
	} };

	if ( opt( '--out' ) ) {
		writeFileSync( opt( '--out' ), JSON.stringify( report, null, 1 ) );
		console.error( `\nwrote ${ opt( '--out' ) }` );
	}
}

main().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
