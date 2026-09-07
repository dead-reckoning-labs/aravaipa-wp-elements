#!/usr/bin/env node
/**
 * Real artwork for podcast episodes, matched off the YouTube uploads.
 *
 * Every one of Aravaipa's shows is recorded as video and published in both
 * places, and every episode has its own artwork: a still made for that
 * episode, visible in Spotify for Creators. None of it reaches the website,
 * so 49 episodes render under three show logos.
 *
 * Not for want of reading the feed. The feed does not have it:
 *
 *   Anchor writes the show's own square logo into every item's
 *   <itunes:image>, whether that episode has art of its own or not. Two of
 *   the 49 differ, and those two are already shown correctly today. For the
 *   other 47 the field is filled in and wrong, which is worse than empty:
 *   there is no version of "read the feed harder" that finds anything else
 *   in it.
 *
 *   The episode pages carry it, but only for whichever episodes happen to
 *   be in the related list beside the one being viewed, not for the episode
 *   the page is about. Spotify itself will not list a show's episodes at
 *   all to a logged-out reader, and this site holds no Spotify credential.
 *
 * The same videos are on the Aravaipa Running YouTube channel with those
 * thumbnails on them, and that channel is already read by this repo. So
 * the artwork is found there, matched to an episode, and written down.
 *
 * The matching is the whole risk, and is deliberately strict. A wrong
 * thumbnail on a real episode is worse than a logo, because it looks
 * completely fine: nobody scrolling a media page cross-references a
 * thumbnail against an episode title, so a mismatch would sit there
 * indefinitely. Three gates, all required:
 *
 *   published within 4 days   The strong one. Aravaipa releases an episode
 *                             and its video together, and does not publish
 *                             two different episodes about the same subject
 *                             in the same week.
 *   years agree               Where both titles name a year, it must be the
 *                             same year. Without this "Jigger Johnson
 *                             Ultras 2025 Preview" matches the 2026 video
 *                             at 0.67 on words alone, which is the exact
 *                             failure this is built to avoid: right race,
 *                             wrong year, and nobody would ever notice.
 *   0.7 of the shorter title  Aravaipa retitles for YouTube ("Nathan Brown
 *                             | Meet The Team" becomes "How Nathan Brown
 *                             Crushed a 13:59:23 100-Miler"), so this is
 *                             measured against the shorter of the two
 *                             rather than the union, and the date gate is
 *                             what stops that generosity mattering.
 *
 * An episode that fails any of them gets no entry and keeps its show logo,
 * which is what it has today. 42 of 49 match; the 7 that do not are
 * episodes with no video on the channel.
 *
 *   node scripts/fetch-podcast-art.mjs                 # match and report
 *   node scripts/fetch-podcast-art.mjs --show-misses
 *   node scripts/fetch-podcast-art.mjs --out art.json
 *   node scripts/fetch-podcast-art.mjs --post --dry-run
 *   node scripts/fetch-podcast-art.mjs --post
 *
 * YOUTUBE_API_KEY, from Mountain Outpost's .env.local, same as
 * fetch-youtube.mjs and for the same reason: the two are one Google
 * account, and the site never holds the credential.
 * ARAVAIPA_WP_URL / _USER / _APP_PASSWORD for --post.
 */
import { writeFileSync } from 'node:fs';

const args = process.argv.slice( 2 );
const flag = ( n ) => args.includes( n );
const opt = ( n, d ) => { const i = args.indexOf( n ); return i === -1 ? d : args[ i + 1 ]; };

const KEY = process.env.YOUTUBE_API_KEY;
const WP = process.env.ARAVAIPA_WP_URL;
const USER = process.env.ARAVAIPA_WP_USER;
const PASS = process.env.ARAVAIPA_WP_APP_PASSWORD;

const API = 'https://www.googleapis.com/youtube/v3';
const HANDLE = '@aravaiparunning';

const auth = () => 'Basic ' + Buffer.from( `${ USER }:${ PASS }` ).toString( 'base64' );

// The shows, as podcasts-store.php has them. Duplicated rather than read
// from the plugin because this is a Node script and that is PHP; the feeds
// change about once never, and a mismatch shows up as an entire show
// missing from the report rather than as anything subtle.
const FEEDS = {
	'inside-aravaipa': 'https://anchor.fm/s/1017c24d0/podcast/rss',
	'white-mountain': 'https://anchor.fm/s/10172af54/podcast/rss',
	'race-briefings': 'https://anchor.fm/s/10208075c/podcast/rss',
};

const GAP_DAYS = Number( opt( '--gap', '4' ) );
const OVERLAP = Number( opt( '--overlap', '0.7' ) );

// Words that say nothing about which episode this is. "Podcast", "preview"
// and "episode" are in half the titles on both sides.
const STOP = new Set( [
	'the', 'a', 'an', 'and', 'of', 'w', 'with', 'for', 'to', 'on', 'in', 'is',
	'vs', 'ep', 'episode', 'podcast', 'show', 'preview', 'presented', 'by',
] );

/**
 * A title reduced to the words that identify it.
 *
 * Everything after a pipe goes first: both sides use it for the show's own
 * name ("... | White Mountain Endurance Podcast"), which is identical
 * across every episode of that show and would inflate every comparison
 * within it.
 *
 * @param {string} s
 * @return {Set<string>}
 */
export function titleTokens( s ) {
	const t = String( s || '' )
		.toLowerCase()
		.replace( /\|.*$/, ' ' )
		.replace( /[^a-z0-9 ]/g, ' ' );

	return new Set( t.split( /\s+/ ).filter( ( w ) => w && ! STOP.has( w ) ) );
}

export const yearsIn = ( s ) => new Set( String( s || '' ).match( /20\d\d/g ) || [] );

/**
 * How much of the shorter title the two share.
 *
 * @param {Set<string>} a
 * @param {Set<string>} b
 * @return {number} 0 to 1.
 */
export function overlap( a, b ) {
	if ( ! a.size || ! b.size ) return 0;

	let hits = 0;
	for ( const w of a ) if ( b.has( w ) ) hits++;

	return hits / Math.min( a.size, b.size );
}

/**
 * The best video for one episode, or null when nothing clears every gate.
 *
 * @param {{title: string, date: Date}} episode
 * @param {Array}                       videos
 * @return {object|null}
 */
export function matchEpisode( episode, videos ) {
	const et = titleTokens( episode.title );
	const ey = yearsIn( episode.title );

	let best = null;
	let score = 0;

	for ( const v of videos ) {
		const gap = Math.abs( ( v.date - episode.date ) / 86400000 );
		if ( gap > GAP_DAYS ) continue;

		const vy = yearsIn( v.title );
		if ( ey.size && vy.size ) {
			let shared = false;
			for ( const y of ey ) if ( vy.has( y ) ) shared = true;
			if ( ! shared ) continue;
		}

		const s = overlap( et, titleTokens( v.title ) );

		if ( s > score ) {
			score = s;
			best = v;
		}
	}

	return score >= OVERLAP ? { ...best, score } : null;
}

const decode = ( s ) =>
	String( s || '' )
		.replace( /&amp;/g, '&' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;/g, "'" )
		.replace( /&#(\d+);/g, ( _, d ) => String.fromCharCode( +d ) );

const tag = ( xml, name ) => {
	const m = xml.match( new RegExp( `<${ name }[^>]*>(?:<!\\[CDATA\\[)?([\\s\\S]*?)(?:\\]\\]>)?</${ name }>` ) );
	return m ? decode( m[ 1 ] ).replace( /\s+/g, ' ' ).trim() : '';
};

/**
 * Every public video on the channel, newest first.
 *
 * The uploads playlist rather than search, for the reason fetch-youtube.mjs
 * already gives: one quota unit a page against search's hundred, and search
 * silently omits videos.
 */
async function uploads() {
	const chan = await ( await fetch( `${ API }/channels?part=contentDetails&forHandle=${ HANDLE }&key=${ KEY }` ) ).json();
	const list = chan?.items?.[ 0 ]?.contentDetails?.relatedPlaylists?.uploads;

	if ( ! list ) throw new Error( 'no uploads playlist; is YOUTUBE_API_KEY set and valid?' );

	const out = [];
	let token = '';

	do {
		const url = `${ API }/playlistItems?part=snippet&maxResults=50&playlistId=${ list }&key=${ KEY }`
			+ ( token ? `&pageToken=${ token }` : '' );
		const page = await ( await fetch( url ) ).json();

		for ( const item of page.items || [] ) {
			const s = item.snippet || {};
			const th = s.thumbnails || {};
			const image = ( th.maxres || th.standard || th.high || th.medium || th.default || {} ).url;

			if ( ! image || ! s.title ) continue;

			out.push( { id: s.resourceId?.videoId, title: s.title, date: new Date( s.publishedAt ), image } );
		}

		token = page.nextPageToken || '';
	} while ( token );

	return out;
}

async function main() {
	if ( ! KEY ) {
		console.error( 'YOUTUBE_API_KEY is not set' );
		process.exit( 1 );
	}

	const videos = await uploads();
	console.error( `${ videos.length } videos on the channel\n` );

	const art = {};
	const misses = [];
	let episodes = 0;

	for ( const [ key, feed ] of Object.entries( FEEDS ) ) {
		const res = await fetch( feed, { headers: { 'User-Agent': 'aravaipa-elements' } } );

		if ( ! res.ok ) {
			console.error( `${ key }: HTTP ${ res.status }, skipped` );
			continue;
		}

		const xml = await res.text();
		const items = xml.match( /<item>[\s\S]*?<\/item>/g ) || [];
		const cover = ( xml.match( /<itunes:image href="([^"]+)"/ ) || [] )[ 1 ] || '';

		let hit = 0;

		for ( const item of items ) {
			const title = tag( item, 'title' );
			const guid = tag( item, 'guid' );
			const pub = tag( item, 'pubDate' );
			const own = ( item.match( /<itunes:image href="([^"]+)"/ ) || [] )[ 1 ] || '';

			if ( ! title || ! guid || ! pub ) continue;

			episodes++;

			// Already has real art of its own in the feed, which outranks
			// anything matched here and needs no entry: podcasts-store.php
			// prefers it directly and never looks this up for those.
			if ( own && own !== cover ) continue;

			const found = matchEpisode( { title, date: new Date( pub ) }, videos );

			if ( ! found ) {
				misses.push( `${ key }  ${ pub.slice( 5, 16 ) }  ${ title }` );
				continue;
			}

			art[ guid ] = found.image;
			hit++;

			if ( flag( '--verbose' ) ) {
				console.error( `  ${ found.score.toFixed( 2 ) }  ${ title.slice( 0, 44 ).padEnd( 46 ) } -> ${ found.title.slice( 0, 44 ) }` );
			}
		}

		console.error( `${ key.padEnd( 18 ) } ${ String( items.length ).padStart( 3 ) } episodes, ${ hit } matched` );
	}

	console.error( `\n${ Object.keys( art ).length } of ${ episodes } episodes matched to a video` );

	if ( misses.length ) {
		console.error( `${ misses.length } with no match, keeping the show logo:` );
		if ( flag( '--show-misses' ) ) for ( const m of misses ) console.error( `  ${ m }` );
		else console.error( '  (pass --show-misses to list them)' );
	}

	if ( opt( '--out' ) ) {
		writeFileSync( opt( '--out' ), JSON.stringify( { art }, null, 1 ) + '\n' );
		console.error( `\nwrote ${ opt( '--out' ) }` );
	}

	if ( ! flag( '--post' ) ) {
		console.error( '\nnothing sent. pass --post to write.' );
		return;
	}

	const post = await fetch( `${ WP }/wp-json/aravaipa/v1/podcasts/art`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json', Authorization: auth() },
		body: JSON.stringify( { art, dry_run: flag( '--dry-run' ) } ),
	} );

	console.error( `HTTP ${ post.status }` );
	console.log( await post.text() );
}

if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'fetch-podcast-art.mjs' ) ) {
	main().catch( ( e ) => { console.error( e ); process.exit( 1 ); } );
}
