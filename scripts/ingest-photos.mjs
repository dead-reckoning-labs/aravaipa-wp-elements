#!/usr/bin/env node
/**
 * Nightly, unattended, APPEND-ONLY photo ingest for /photos/.
 *
 * Walks the SmugMug accounts discover-smugmug.mjs walks, for this year and
 * last (late uploads), keeps only galleries that match one Aravaipa race
 * with photographs in them and no password, diffs them against the LIVE
 * photo store by URL, and appends the new ones. It never modifies or
 * deletes a row. Anything it is not sure about goes to a review list
 * instead (see scripts/lib/photo-ingest.mjs for the rules).
 *
 * Written because on 2026-10-10 a hand audit found Bear Chase, Grand
 * Island and Waugoshance 2026 missing from the store weeks after they were
 * posted. Nothing was wrong with the galleries; nobody had run the import.
 *
 * Talks to WordPress only through scripts/photos-ingest.php, run by a
 * command you supply (it needs SSH to the host, which this repo does not
 * hold credentials for):
 *
 *   <wp-eval> <php-file> [payload-file]
 *     with ARV_INGEST_MODE=context|apply and ARV_INGEST_COMMIT=0|1 in env,
 *     printing the PHP's output on stdout.
 *
 *   SMUGMUG_API_KEY=... node scripts/ingest-photos.mjs --wp-eval ./wp-eval.sh --out-dir ./out --dry-run
 *   SMUGMUG_API_KEY=... node scripts/ingest-photos.mjs --wp-eval ./wp-eval.sh --out-dir ./out
 *
 * Options:
 *   --dry-run          plan and preview against live, write nothing to WordPress
 *   --years 2025,2026  years to ingest (default: this year and last)
 *   --max 15           most rows one run may add; over it, none are added
 *   --verify-wait 90   seconds to wait before re-checking the rows survived
 *                      the hourly cover cron (which rewrites the option)
 *   --context f.json   use a saved context instead of reading WordPress (with --dry-run)
 *   --discovered f.json  use a saved discovery (runs/<stamp>-discovered.json)
 *                      instead of walking SmugMug
 *
 * Writes <out-dir>/last-run.json, <out-dir>/runs/<stamp>.json and one line
 * to <out-dir>/runs.log. The last line on stdout is the run summary as JSON.
 */

import { execFileSync } from 'node:child_process';
import { appendFileSync, existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { raceMatcherWithTies } from './lib/race-match.mjs';
import { ACCOUNTS, smugmugApi, walkAccount } from './lib/smugmug.mjs';
import { matchNames, planIngest, urlKey, urlPresent } from './lib/photo-ingest.mjs';

const HERE = dirname( fileURLToPath( import.meta.url ) );

const args = Object.fromEntries(
	process.argv.slice( 2 ).flatMap( ( a, i, all ) =>
		a.startsWith( '--' ) ? [ [ a.slice( 2 ), all[ i + 1 ]?.startsWith( '--' ) === false ? all[ i + 1 ] : true ] ] : []
	)
);

const DRY = Boolean( args[ 'dry-run' ] );
const MAX = Number( args.max ?? 15 );
const WAIT = Number( args[ 'verify-wait' ] ?? 90 );
const OUT = resolve( String( args[ 'out-dir' ] ?? '.' ) );
const PHP = resolve( HERE, 'photos-ingest.php' );
const NOW = new Date();
const THIS_YEAR = NOW.getFullYear();
const YEARS = args.years ? String( args.years ).split( ',' ).map( Number ) : [ THIS_YEAR, THIS_YEAR - 1 ];
const STAMP = NOW.toISOString().replace( /[^0-9]/g, '' ).slice( 0, 14 );

const KEY = process.env.SMUGMUG_API_KEY;

if ( ! KEY ) fail( 'SMUGMUG_API_KEY is required' );
if ( ! args[ 'wp-eval' ] && ! ( DRY && args.context ) ) fail( '--wp-eval <command> is required (or --dry-run --context <file>)' );

mkdirSync( join( OUT, 'runs' ), { recursive: true } );

function fail( msg ) {
	console.error( msg );
	process.exit( 2 );
}

/**
 * Run photos-ingest.php through the supplied command and decode its line.
 */
function wp( mode, payload = null, commit = false ) {
	let file = '';

	if ( payload ) {
		file = join( OUT, `.payload-${ process.pid }.json` );
		writeFileSync( file, JSON.stringify( payload ) );
	}

	try {
		const out = execFileSync( resolve( String( args[ 'wp-eval' ] ) ), file ? [ PHP, file ] : [ PHP ], {
			env: { ...process.env, ARV_INGEST_MODE: mode, ARV_INGEST_COMMIT: commit ? '1' : '0' },
			encoding: 'utf8',
			maxBuffer: 64 * 1024 * 1024,
			timeout: 180_000,
		} );
		const line = out.split( '\n' ).find( ( l ) => l.startsWith( 'ARV_INGEST_JSON ' ) );

		if ( ! line ) throw new Error( `no result line from photos-ingest.php (${ mode }): ${ out.slice( 0, 300 ) }` );

		const res = JSON.parse( Buffer.from( line.slice( 16 ), 'base64' ).toString( 'utf8' ) );

		if ( ! res.ok ) throw new Error( `photos-ingest.php ${ mode }: ${ res.error }` );

		return res;
	} finally {
		if ( file && existsSync( file ) ) unlinkSync( file );
	}
}

const summary = {
	stamp: STAMP,
	dry: DRY,
	years: YEARS,
	added: [],
	review: [],
	reviewNew: [],
	errors: [],
	discovered: 0,
	present: 0,
	agreement: null,
	store: null,
};

try {
	// 1. What is live right now.
	const context = args.context ? JSON.parse( readFileSync( String( args.context ), 'utf8' ) ) : wp( 'context' );
	summary.store = { before: context.rows.length };

	// 2. What is on SmugMug.
	const { names } = matchNames( context );
	const match = raceMatcherWithTies( names );
	const api = smugmugApi( KEY );
	const discovered = [];
	const rejected = [];

	for ( const account of args.discovered ? [] : ACCOUNTS ) {
		try {
			const res = await walkAccount( { api, account, match, yearOk: ( y ) => YEARS.includes( y ), requirePublic: true } );
			discovered.push( ...res.accepted );
			rejected.push( ...res.rejected );
		} catch ( e ) {
			summary.errors.push( String( e.message ?? e ) );
		}
	}

	if ( args.discovered ) discovered.push( ...JSON.parse( readFileSync( String( args.discovered ), 'utf8' ) ) );

	summary.discovered = discovered.length;
	writeFileSync( join( OUT, `runs/${ STAMP }-discovered.json` ), JSON.stringify( discovered, null, 1 ) );

	// 3. The plan.
	const plan = planIngest( { discovered, context, max: MAX } );
	summary.present = plan.present.length;
	summary.review = plan.review;
	summary.agreement = { agree: plan.agreement.agree, disagree: plan.agreement.disagree };

	// 4. The write (or, dry, the server's preview of it: which rows it would
	//    add and the dates it would put on them).
	const rows = plan.add.map( ( { race, year, by, url } ) => ( { race, year, by, url } ) );

	if ( rows.length && ! args.context ) {
		const res = wp( 'apply', { rows, max: MAX, keep: 7, stamp: STAMP }, ! DRY );
		summary.added = res.added;
		summary.store = { before: res.before, after: res.after, backup: res.backup ?? null, pruned: res.pruned ?? [] };

		// The hourly cover cron reads the whole option, spends up to 45s
		// fetching covers, then writes the whole option back. A write of
		// ours landing inside that window is overwritten. Re-check after the
		// window, and append again (it is idempotent) if anything is gone.
		if ( ! DRY && res.added.length && WAIT > 0 ) {
			await new Promise( ( r ) => setTimeout( r, WAIT * 1000 ) );
			const after = wp( 'context' ).rows.map( ( r ) => urlKey( r.url ) );
			const lost = res.added.filter( ( r ) => ! urlPresent( r.url, after ) );

			if ( lost.length ) {
				const again = wp( 'apply', { rows: lost, max: MAX, keep: 7, stamp: STAMP.slice( 0, 12 ) + '99' }, true );
				summary.reapplied = again.added.length;
				summary.store.after = again.after;
			}
		}
	} else if ( rows.length ) {
		summary.added = rows;
	}

	// Only review items not reported before are worth a message: the same
	// empty-card question every night is noise.
	const seenFile = join( OUT, 'review-seen.json' );
	const seen = new Set( existsSync( seenFile ) ? JSON.parse( readFileSync( seenFile, 'utf8' ) ) : [] );
	summary.reviewNew = plan.review.filter( ( r ) => ! seen.has( urlKey( r.url ) ) );

	if ( ! DRY ) {
		for ( const r of plan.review ) seen.add( urlKey( r.url ) );
		writeFileSync( seenFile, JSON.stringify( [ ...seen ].sort(), null, 1 ) );
	}

	writeFileSync( join( OUT, `runs/${ STAMP }-rejected.json` ), JSON.stringify( rejected, null, 1 ) );
} catch ( e ) {
	summary.errors.push( String( e.message ?? e ) );
}

summary.ok = 0 === summary.errors.length;

writeFileSync( join( OUT, 'last-run.json' ), JSON.stringify( summary, null, 1 ) );
writeFileSync( join( OUT, `runs/${ STAMP }.json` ), JSON.stringify( summary, null, 1 ) );

const line = [
	NOW.toISOString(),
	DRY ? 'dry' : 'live',
	`years=${ YEARS.join( ',' ) }`,
	`discovered=${ summary.discovered }`,
	`present=${ summary.present }`,
	`added=${ summary.added.length }`,
	`review=${ summary.review.length }`,
	`review_new=${ summary.reviewNew.length }`,
	`agree=${ summary.agreement?.agree ?? 0 }/${ ( summary.agreement?.agree ?? 0 ) + ( summary.agreement?.disagree.length ?? 0 ) }`,
	`store=${ summary.store?.before ?? '?' }->${ summary.store?.after ?? summary.store?.before ?? '?' }`,
	summary.errors.length ? `errors=${ JSON.stringify( summary.errors ) }` : 'ok',
].join( ' ' );

appendFileSync( join( OUT, 'runs.log' ), line + '\n' );
console.error( line );
console.log( JSON.stringify( { ok: summary.ok, dry: DRY, added: summary.added.length, review: summary.review.length, reviewNew: summary.reviewNew.length, file: join( OUT, `runs/${ STAMP }.json` ) } ) );

process.exit( summary.ok ? 0 : 1 );
