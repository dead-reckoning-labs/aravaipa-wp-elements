#!/usr/bin/env node
/**
 * The Aravaipa Running YouTube channel: its counts and its latest uploads.
 *
 * Two calls to the YouTube Data API. channels?forHandle gives the
 * subscriber and video totals plus the id of the channel's "uploads"
 * playlist, which is a real playlist containing every public video in
 * reverse-chronological order; playlistItems on that id is the newest
 * first. There is no "latest videos" endpoint, and search?order=date
 * would be the obvious alternative but costs 100 quota units against the
 * uploads playlist's 1, for a result that is less reliable (search
 * indexes lag, and it silently omits some videos).
 *
 * The key is Mountain Outpost's, in its .env.local, because Aravaipa
 * Running and Mountain Outpost are the same Google account. It stays
 * there and on a laptop: this posts the finished numbers to WordPress so
 * the site never holds a Google credential.
 *
 * Subscriber counts come back from the API already rounded to three
 * significant figures for any channel over a thousand, which is Google's
 * own policy and not something to work around: 39500 is what the channel
 * page itself says.
 *
 * Usage:
 *   node scripts/fetch-youtube.mjs                 # print the payload
 *   node scripts/fetch-youtube.mjs --post --dry-run
 *   node scripts/fetch-youtube.mjs --post
 *
 * YOUTUBE_API_KEY, plus ARAVAIPA_WP_URL / _USER / _APP_PASSWORD for
 * --post, same as the other fetchers.
 */

const HANDLE = '@aravaiparunning';
const API = 'https://www.googleapis.com/youtube/v3';

const args = process.argv.slice( 2 );
const flag = ( name ) => args.includes( name );
const opt = ( name, fallback ) => {
	const i = args.indexOf( name );
	return -1 === i ? fallback : args[ i + 1 ];
};

const COUNT = Number( opt( '--count', '6' ) );

async function getJson( url ) {
	const res = await fetch( url );
	const body = await res.json();

	if ( ! res.ok || body.error ) {
		throw new Error(
			`${ res.status }: ${ body.error ? body.error.message : 'request failed' }`
		);
	}

	return body;
}

/**
 * The widest thumbnail YouTube actually generated for a video.
 *
 * Not simply "maxres": it only exists for videos uploaded above 1280
 * wide, so older uploads have no such key and asking for one by name
 * yields undefined rather than a smaller image. Walked widest first
 * instead, so every video gets the best it has.
 */
function thumbOf( snippet ) {
	const t = snippet.thumbnails || {};

	for ( const size of [ 'maxres', 'standard', 'high', 'medium', 'default' ] ) {
		if ( t[ size ] && t[ size ].url ) {
			return t[ size ].url;
		}
	}

	return '';
}

async function channel( key ) {
	const body = await getJson(
		`${ API }/channels?part=snippet,statistics,contentDetails` +
			`&forHandle=${ encodeURIComponent( HANDLE ) }&key=${ key }`
	);

	const found = ( body.items || [] )[ 0 ];

	if ( ! found ) {
		throw new Error( `no channel for ${ HANDLE }` );
	}

	return found;
}

async function uploads( key, playlistId, count ) {
	const body = await getJson(
		`${ API }/playlistItems?part=snippet,contentDetails` +
			`&playlistId=${ playlistId }&maxResults=${ count }&key=${ key }`
	);

	return ( body.items || [] )
		.map( ( item ) => ( {
			id: item.contentDetails.videoId,
			title: item.snippet.title,
			date: item.contentDetails.videoPublishedAt || '',
			thumb: thumbOf( item.snippet ),
		} ) )
		// A private or deleted video stays in the uploads playlist as an
		// item with no videoId and the title "Private video", which would
		// otherwise be shown as a card linking to watch?v=undefined.
		.filter( ( v ) => v.id && 'Private video' !== v.title && 'Deleted video' !== v.title );
}

async function post( payload ) {
	const base = process.env.ARAVAIPA_WP_URL;
	const user = process.env.ARAVAIPA_WP_USER;
	const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;

	if ( ! base || ! user || ! pass ) {
		throw new Error(
			'ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD must be set'
		);
	}

	const res = await fetch( `${ base }/wp-json/aravaipa/v1/youtube`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Authorization:
				'Basic ' + Buffer.from( `${ user }:${ pass }` ).toString( 'base64' ),
		},
		body: JSON.stringify( { channel: payload, dry_run: flag( '--dry-run' ) } ),
	} );

	const text = await res.text();

	if ( ! res.ok ) {
		throw new Error( `post failed: ${ res.status } ${ text.slice( 0, 300 ) }` );
	}

	return JSON.parse( text );
}

const key = process.env.YOUTUBE_API_KEY;

if ( ! key ) {
	console.error( 'YOUTUBE_API_KEY must be set.' );
	process.exit( 1 );
}

const found = await channel( key );

const payload = {
	title: found.snippet.title,
	subscribers: Number( found.statistics.subscriberCount || 0 ),
	videoCount: Number( found.statistics.videoCount || 0 ),
	views: Number( found.statistics.viewCount || 0 ),
	videos: await uploads(
		key,
		found.contentDetails.relatedPlaylists.uploads,
		COUNT
	),
};

console.error(
	`${ payload.title }: ${ payload.subscribers } subscribers, ` +
		`${ payload.videoCount } videos, ${ payload.videos.length } latest read\n`
);

if ( flag( '--post' ) ) {
	console.log( JSON.stringify( await post( payload ), null, '\t' ) );
} else {
	console.log( JSON.stringify( payload, null, '\t' ) );
}
