#!/usr/bin/env node
/**
 * Winners and finisher counts for the years before the timing board.
 *
 * Everything from 2008 to about 2020 was scored on Aravaipa's own site and
 * published as one static file per distance, still served from /results/ to
 * this day. The board-walking scraper beside this one (fetch-stats.mjs)
 * cannot see any of it: those editions have no board, which is exactly why
 * they have files. So 265 editions rendered as a date and a link while a
 * modern one rendered winners, finisher counts and course records, and the
 * "Course Records" table on a 23-edition race was quietly computing from
 * the five years it could see.
 *
 * These files are readable. The header carries the race, the distance, the
 * date and usually "Starters: 18 Finishers: 14", and the body is a real
 * table. What they do not carry is a consistent shape: the column set
 * changes across the years, so nothing here counts columns by position.
 * Every column is resolved by reading the header row.
 *
 *   place | first name | lastname | age | m/f      | time        (2008)
 *   place | first name | last name | age | gender | ... | finish time (2012)
 *   place | first name | last name | bib | age | gender | gender place
 *         | city | state | country | gun time | mile 6.2 | finish time (2015)
 *
 * Gun time is not finish time and is present in some years alongside it.
 * Preferring the wrong one would silently overstate every winner in those
 * years by the length of the start wave, so "finish time" wins wherever
 * both exist and a bare "time" is only used when there is no better column.
 *
 * A fixed-time race has no finishing time to report at all. Across the
 * Years, Desert Solstice and the 24 hour events are won on distance, every
 * finisher stops at the same moment, and the shortest clock in the file
 * belongs to whoever quit earliest. Those files are read for miles instead,
 * and the winner's result reads "127.02 mi". Nothing downstream mistakes it
 * for a time: the course-record table parses times and skips what will not
 * parse, which is the right answer, because the record for a 24 hour is a
 * distance to beat and not a clock to get under.
 *
 *   node scripts/fetch-archive-stats.mjs                 # parse, report
 *   node scripts/fetch-archive-stats.mjs --out out.json
 *   node scripts/fetch-archive-stats.mjs --post --dry-run
 *   node scripts/fetch-archive-stats.mjs --post
 *
 * Credentials from ARAVAIPA_WP_URL / _ADMIN_USER / _ADMIN_APP_PASSWORD.
 */
import { writeFileSync, readFileSync, existsSync } from 'node:fs';
import { inflateRawSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const args = process.argv.slice( 2 );
const flag = ( n ) => args.includes( n );
const opt = ( n, d ) => { const i = args.indexOf( n ); return i === -1 ? d : args[ i + 1 ]; };

const WP = process.env.ARAVAIPA_WP_URL;
const USER = process.env.ARAVAIPA_WP_ADMIN_USER;
const PASS = process.env.ARAVAIPA_WP_ADMIN_APP_PASSWORD;

const UA =
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ' +
	'(KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const auth = () => 'Basic ' + Buffer.from( `${ USER }:${ PASS }` ).toString( 'base64' );

const decode = ( s ) =>
	s
		.replace( /&nbsp;/g, ' ' )
		.replace( /&amp;/g, '&' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;/g, "'" )
		.replace( /&#(\d+);/g, ( _, d ) => String.fromCharCode( +d ) );

const text = ( html ) => decode( html.replace( /<[^>]+>/g, ' ' ) ).replace( /\s+/g, ' ' ).trim();

// A finish time, not a lap count and not an age. Accepts H:MM:SS, HH:MM:SS
// and MM:SS, which is what a 5K prints.
const isTime = ( v ) => /^\d{1,3}:[0-5]\d(:[0-5]\d)?$/.test( ( v || '' ).trim() );

const isPlaceHead = ( h ) => h.includes( 'place' ) || [ 'pos', 'position', 'rank', 'pic' ].includes( h );
const isNameHead = ( h ) =>
	h.includes( 'name' ) || [ 'first', 'last', 'runner', 'athlete', 'participant' ].includes( h );
const isResultHead = ( h ) =>
	/time|finish|miles|kilometers|laps|distance/.test( h ) || [ 'km', 'total tm' ].includes( h );

// Header detection and row collection, given rows already split into
// cells. Shared by every source: an HTML <tr>, a Word table row and a line
// of a plain-text file are three different things to extract a row of
// cells FROM, but identical once they are one, and a results file written
// by hand does not spell its header the same way twice no matter which of
// the three it happens to be saved as.
function parseCellRows( cellRows ) {
	const rows = [];
	let header = null;

	for ( const cells of cellRows ) {
		if ( ! cells.length ) continue;

		const lower = cells.map( ( c ) => c.toLowerCase() );

		// A header row is one that names a finishing position and names a
		// runner. Both halves are matched loosely on purpose, because both
		// were written by hand and neither is spelled the same way twice:
		// the 2010 files lead with Bib and call the position "Overall
		// Place", and Hotfoot Hamster heads its name columns "First" and
		// "Last" with no "name" in either. Requiring the exact words found
		// no header in those files at all, and a file with no header is a
		// file this reads nothing out of.
		if ( ! header && lower.some( isNameHead ) && ( lower.some( isPlaceHead ) || lower.some( isResultHead ) ) ) {
			header = lower;
			continue;
		}

		// Everything after the header is offered as a finisher. Not only
		// the rows that look like one: the Tushars files pad their header
		// out to twenty-five cells and their finishers to ten, so a row
		// had to be as wide as the header to count and none of them were.
		// Deciding here costs a file. Deciding in winnersFrom, where a row
		// has to hold a real name and a real result in the columns the
		// header pointed at, costs nothing and throws the junk out anyway.
		if ( header && cells.length > 2 ) {
			rows.push( cells );
		}
	}

	return { header, rows };
}

function htmlCellRows( html ) {
	return ( html.match( /<tr[\s\S]*?<\/tr>/gi ) || [] ).map(
		( tr ) => ( tr.match( /<t[dh][\s\S]*?<\/t[dh]>/gi ) || [] ).map( ( c ) => text( c ) )
	);
}

function parseTable( html ) {
	return parseCellRows( htmlCellRows( html ) );
}

// A row that names a distance and nothing else, rather than a header or a
// finisher: exactly one non-empty cell, and that cell reads as a course
// length. Only a source that bundles every distance of a race into one
// document ever produces one. Silverton Alpine Marathon 2009 is a single
// .docx with the Marathon's table and the 50K's table one below the other,
// each led by a row like this ("Marathon - 7am August 29, 2009") that is
// otherwise indistinguishable from a stray blank row.
function isDistanceHeading( cells ) {
	const only = cells.map( ( c ) => c.trim() ).filter( Boolean );

	return 1 === only.length && /^\d+(?:\.\d+)?\s?(?:miles?|km?|kilometers?)\b|^marathon\b/i.test( only[ 0 ] );
}

// Cuts a flat, in-order list of rows into one group per distance heading
// found in them. A source with no heading in it at all, which is every
// source but the two above, produces the one section everything falls
// into, label '': the caller already has a label for that case, from the
// archive entry the whole file was read from.
function sections( cellRows ) {
	const out = [ { label: '', rows: [] } ];

	for ( const cells of cellRows ) {
		if ( isDistanceHeading( cells ) ) {
			out.push( { label: cells.find( Boolean ).trim().replace( /\s*[-\u2013].*$/, '' ), rows: [] } );
			continue;
		}

		out[ out.length - 1 ].rows.push( cells );
	}

	return out.filter( ( s ) => s.rows.length );
}

// Which column holds what, by name rather than position.
function columns( header ) {
	// Every test is tried against the header cell as written and against
	// it with the spaces taken out, because two of these files were saved
	// from a spreadsheet that wrapped its own headings and they say
	// "gend er" and "stat e".
	const find = ( ...tests ) => {
		for ( const test of tests ) {
			const i = header.findIndex( ( h ) => test( h ) || test( h.replace( /\s+/g, '' ) ) );
			if ( i !== -1 ) return i;
		}
		return -1;
	};

	const last = find( ( h ) => h === 'last name' || h === 'lastname', ( h ) => h === 'last' );

	// Every index the header calls "First" or "First Name", not only the
	// first one. Whiskey Man & Woman 2017 heads its unlabelled place
	// column "First" by mistake instead of "Place": "First, First, Last,
	// Gender, Age, ...", first name in the second column and nothing
	// naming the first at all. find()'s first-match-wins reads the place
	// number as somebody's first name and nameOf() prints "1 Muchna". A
	// real file never repeats this label, so there is only ever one
	// candidate to choose from and this changes nothing for it; picking
	// whichever candidate sits immediately before Last, which is where a
	// first name actually sits relative to a last name, is what tells the
	// genuine column apart from the typo on the one file that has both.
	const firstCandidates = [];
	header.forEach( ( h, i ) => {
		if ( 'first name' === h || 'first' === h || 'firstname' === h.replace( /\s+/g, '' ) ) {
			firstCandidates.push( i );
		}
	} );
	const first = firstCandidates.length > 1 && -1 !== last
		? ( firstCandidates.find( ( i ) => i === last - 1 ) ?? firstCandidates[ 0 ] )
		: ( firstCandidates.length ? firstCandidates[ 0 ] : -1 );

	return {
		first,
		last,
		// Some years print one column instead of two, and the lap
		// scoreboards print it surname first.
		name: find( ( h ) => h === 'name' || h === 'runner' || h === 'athlete' ),
		gender: find( ( h ) => h === 'gender' || h === 'm/f' || h === 'sex' ),
		// Finish time first, always. Gun time is a different number and
		// several years carry both.
		time: find(
			( h ) => h === 'finish time',
			( h ) => h === 'time' || h === 'finish',
			( h ) => h === 'total tm' || h === 'total time',
			// The 2020 virtual files, whose column is headed with its own
			// clarification: "time (running time)".
			( h ) => h.startsWith( 'time (' ),
			( h ) => h.includes( 'time' ) && ! /gun|start|cut|pace|lap|split|of day|chip/.test( h ),
			// The Javelina lap boards head every column with where it was
			// taken and never write "time" at all: "Lap 5 Mile 77.4",
			// "Finish Mile 101.4", "100k Split". The last of them is the
			// finish, and the ones before it are splits, so only the last
			// column qualifies.
			( h ) => h === header[ header.length - 1 ] && /^(finish|100k split|mile |lap )/.test( h )
		),
		// What a fixed-time race is won on. There is no finishing time to
		// report for a 24 hour: everybody who is still there at the end
		// stops at the same moment, and the shortest clock in the file
		// belongs to whoever quit earliest.
		// A substring fallback after the exact names, for a header a
		// spreadsheet export wrote out in full: "Official finish distance
		// in miles" is what the Across the Years text exports actually
		// carry, present in the file next to a Net Time column that reads
		// 24:00:00 for every single finisher of a 24 hour race alike.
		// Without a miles column to prefer instead, scoredOnDistance() had
		// no distance column to fall back to either, and correctly-ranked
		// winners were reported as if they had each run exactly one clock.
		miles: find(
			( h ) => h === 'miles' || h === 'finish miles' || h === 'distance',
			( h ) => h.includes( 'mile' )
		),
		km: find(
			( h ) => h === 'km' || h === 'finish km' || h === 'kilometers',
			( h ) => h.includes( 'kilometer' )
		),
		laps: find( ( h ) => h === 'laps' ),
		// The overall finishing position. Never the per-division ones:
		// "gender place" reads 1 for the first woman as well as the first
		// man, so ranking on it would call four different people the winner.
		place: find(
			( h ) => h === 'place' || h === 'overall place' || h === 'pos' || h === 'position',
			( h ) => h.includes( 'place' ) && ! h.includes( 'gender' ) && ! h.includes( 'age' ) && ! h.includes( 'division' )
		),
	};
}

// "1/62" in the 2010 files, "1." in the oldest, "1" everywhere else.
function placeOf( cell ) {
	const m = ( cell || '' ).match( /^\s*(\d+)/ );
	return m ? +m[ 1 ] : null;
}

// One column or two, and surname first where it is one: the lap
// scoreboards write "James, Dave" for the runner everyone else calls
// Dave James.
function nameOf( cells, col ) {
	if ( col.first !== -1 ) {
		return [ cells[ col.first ], col.last === -1 ? '' : cells[ col.last ] ]
			.map( ( p ) => ( p || '' ).trim() )
			.filter( Boolean )
			.join( ' ' );
	}

	const whole = ( cells[ col.name ] || '' ).trim();
	const comma = whole.match( /^([^,]+),\s*(.+)$/ );

	return comma ? `${ comma[ 2 ].trim() } ${ comma[ 1 ].trim() }` : whole;
}

// Whether this file's own ordering says it is not scored on time.
//
// Some fixed-time races print a time column anyway: Silverton 24 Hour lists
// laps, miles and a time, where the time is when the winner's last lap
// closed and not what he is being ranked on. There is no need to guess from
// the race's name, because the file gives itself away. If the finishers are
// listed in an order the times do not agree with, the times are not what
// put them in that order.
//
// One inversion is a typo in a results file from 2013. A run of them is a
// different sport.
//
// A spreadsheet export of a fixed-time race gives itself away a second
// way an out-of-order finish list cannot: the Across the Years text
// exports carry a "Net time" column that is not a finishing time at all,
// it is the event's own duration, printed identically for all 46 finishers
// of the 24 hour because that is literally how long the race ran. Flat
// rather than out of order, so the inversion count above is zero and finds
// nothing wrong with it, and every winner was reported as having run
// exactly one clock. A real finishing time varies runner to runner; one
// that does not is the same tell in a different shape.
function scoredOnDistance( rows, col ) {
	if ( col.time === -1 ) return true;
	if ( col.miles === -1 && col.km === -1 && col.laps === -1 ) return false;

	const secs = rows
		.map( ( c ) => ( c[ col.time ] || '' ).trim() )
		.filter( isTime )
		.map( ( t ) => t.split( ':' ).reduce( ( a, p ) => a * 60 + +p, 0 ) );

	if ( secs.length < 4 ) return false;

	if ( new Set( secs ).size === 1 ) return true;

	let inversions = 0;
	for ( let i = 1; i < secs.length; i++ ) if ( secs[ i ] < secs[ i - 1 ] ) inversions++;

	return inversions > Math.max( 2, secs.length * 0.05 );
}

// What this runner is reported to have done: a finishing time where the
// race has one, and otherwise the distance it was won on.
function resultOf( cells, col ) {
	if ( col.time !== -1 && ! col.byDistance ) {
		const time = ( cells[ col.time ] || '' ).trim();
		return isTime( time ) ? time : '';
	}

	for ( const [ i, unit ] of [ [ col.miles, 'mi' ], [ col.km, 'km' ], [ col.laps, 'laps' ] ] ) {
		if ( i === -1 ) continue;

		const n = parseFloat( ( cells[ i ] || '' ).replace( /,/g, '' ) );
		if ( n > 0 ) return `${ +n.toFixed( 2 ) } ${ unit }`;
	}

	return '';
}

const GENDERS = {
	m: 'men', male: 'men', men: 'men',
	f: 'women', female: 'women', women: 'women', w: 'women',
	x: 'nonbinary', nb: 'nonbinary', nonbinary: 'nonbinary', 'non-binary': 'nonbinary',
};

function winnersFrom( html ) {
	const { header, rows } = parseTable( html );

	return winnersFromRows( header, rows );
}

// The core of the above, given rows already split into cells rather than
// HTML. Split out so a Word table and a plain-text file can be scored the
// same way an HTML one always has been, without either of them pretending
// to be markup first.
function winnersFromRows( header, rows ) {
	if ( ! header || ! rows.length ) return null;

	const col = columns( header );
	if ( col.first === -1 && col.name === -1 ) return null;
	if ( col.time === -1 && col.miles === -1 && col.km === -1 && col.laps === -1 ) return null;

	col.byDistance = scoredOnDistance( rows, col );

	const best = {};
	let seen = 0;

	rows.forEach( ( cells, i ) => {
		const g = GENDERS[ ( cells[ col.gender ] || '' ).trim().toLowerCase() ];
		const name = nameOf( cells, col );
		const time = resultOf( cells, col );

		if ( ! name || ! time ) return;

		seen++;

		if ( ! g ) return;

		// Ranked by the file's own finishing place where it states one, and
		// by document order only where it does not. Not by fastest time:
		// a fixed-time race ranks on distance covered, so every finisher of
		// a 24 hour shows about 24 hours and the quickest clock belongs to
		// somebody who stopped early.
		const rank = col.place === -1 ? i : ( placeOf( cells[ col.place ] ) ?? i );

		if ( ! best[ g ] || rank < best[ g ].rank ) best[ g ] = { name, time, rank };
	} );

	for ( const g of Object.keys( best ) ) delete best[ g ].rank;

	return { winners: best, finishers: seen };
}

/* ------------------------------------------------------------------ *
 * Plain text (.txt)
 *
 * Five files, two shapes. Across the Years exports a tab-separated table
 * per distance straight from a spreadsheet, header included. Buckeye
 * Endurance Runs is one file for the whole race, fixed-width columns lined
 * up with spaces and a dashed rule under the header, four distances one
 * after another with nothing but a bare heading line between them.
 *
 * Neither is markup, so parseTable never saw either: this reads the same
 * rows-of-cells shape out of lines instead of <tr> tags, then everything
 * from parseCellRows on is identical to an HTML file.
 *
 * Not every .txt in the archive is one of these two. Copper Basin Fatass
 * 50K is a hand-typed placing list, "1. Brian Tinder          5:57", with
 * no header row naming a runner or a finishing position at all. That is
 * not a defect in this reader: parseCellRows correctly finds no header,
 * because the file does not have one, and the real gap is that the file
 * never recorded gender for anyone in it.
 * ------------------------------------------------------------------ */

// A divider row under a fixed-width header, "----- --------- ------": not
// a header, not a finisher, and if split like every other line it produces
// a row of hyphens that happens to be exactly as wide as the header beside
// it, which is a finisher this file did not have.
const isRule = ( line ) => /^-{2,}(?:\s+-{2,})*$/.test( line );

function textCellRows( txt ) {
	return txt
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.filter( ( line ) => ! isRule( line ) )
		.map( ( line ) =>
			line.includes( '\t' )
				? line.split( '\t' ).map( ( c ) => c.trim() )
				: line.split( /\s{2,}/ ).map( ( c ) => c.trim() )
		);
}

/* ------------------------------------------------------------------ *
 * Word (.docx)
 *
 * One file. Silverton Alpine Marathon 2009 was scored with a Word document
 * instead of a web page, which nothing here could open at all: not a
 * results file so much as a results file shaped like a spreadsheet shaped
 * like a document, two tables of it, Marathon then 50K, one below the
 * other with a bare heading row between them the same way Buckeye's text
 * file separates its distances.
 *
 * A .docx is a zip archive with the document's own text at
 * word/document.xml inside it, deflate-compressed the way every entry in a
 * zip ordinarily is. Node's zlib can inflate that once the archive is
 * found in the file, so this reads the zip directory by hand rather than
 * add a dependency for the one file in the whole corpus that needs one.
 * ------------------------------------------------------------------ */

// The end-of-central-directory record, searched from the tail because a
// real archive can carry a comment of unknown length after it and the
// signature is the only fixed point.
function zipEntries( buf ) {
	const EOCD = 0x06054b50;
	let eocd = -1;

	for ( let i = buf.length - 22; i >= 0 && i >= buf.length - 22 - 65536; i-- ) {
		if ( buf.readUInt32LE( i ) === EOCD ) { eocd = i; break; }
	}

	if ( -1 === eocd ) return [];

	const count = buf.readUInt16LE( eocd + 10 );
	let offset = buf.readUInt32LE( eocd + 16 );
	const entries = [];

	for ( let i = 0; i < count; i++ ) {
		if ( buf.readUInt32LE( offset ) !== 0x02014b50 ) break;

		const method = buf.readUInt16LE( offset + 10 );
		const compSize = buf.readUInt32LE( offset + 20 );
		const nameLen = buf.readUInt16LE( offset + 28 );
		const extraLen = buf.readUInt16LE( offset + 30 );
		const commentLen = buf.readUInt16LE( offset + 32 );
		const localOffset = buf.readUInt32LE( offset + 42 );
		const name = buf.toString( 'utf8', offset + 46, offset + 46 + nameLen );

		entries.push( { name, method, compSize, localOffset } );
		offset += 46 + nameLen + extraLen + commentLen;
	}

	return entries;
}

function zipRead( buf, entry ) {
	const nameLen = buf.readUInt16LE( entry.localOffset + 26 );
	const extraLen = buf.readUInt16LE( entry.localOffset + 28 );
	const dataStart = entry.localOffset + 30 + nameLen + extraLen;
	const data = buf.subarray( dataStart, dataStart + entry.compSize );

	// Method 0 is stored, uncompressed; 8 is deflate, which is what every
	// zip tool defaults to and the only other method worth handling for one
	// file. inflateRawSync because a zip entry's deflate stream carries
	// none of the zlib header gzip and zlib both add of their own.
	return 0 === entry.method ? data : inflateRawSync( data );
}

function docxRows( buf ) {
	const entries = zipEntries( buf );
	const doc = entries.find( ( e ) => 'word/document.xml' === e.name );
	if ( ! doc ) return [];

	const xml = zipRead( buf, doc ).toString( 'utf8' );

	// Every row in the document, table cells and all, regardless of how
	// deeply one table happens to be nested in another: Word lays this
	// particular file out with a table-within-a-table for its header
	// banner, and reading only top-level rows would mean bracket-matching
	// <w:tbl> correctly around that nesting for no reason, since a row's
	// own cells say everything the row means on their own.
	return ( xml.match( /<w:tr[ >][\s\S]*?<\/w:tr>/g ) || [] ).map( ( tr ) =>
		( tr.match( /<w:tc[\s\S]*?<\/w:tc>/g ) || [] ).map( ( tc ) => {
			const runs = tc.match( /<w:t[^>]*>([\s\S]*?)<\/w:t>/g ) || [];
			return decode( runs.map( ( r ) => r.replace( /<[^>]+>/g, '' ) ).join( '' ) ).trim();
		} )
	);
}

/* ------------------------------------------------------------------ *
 * Ultracast (.clax)
 *
 * 70 editions, every one of them 2017 to 2020, list a single "Ultracast"
 * link and nothing else. That is the whole gap between the static files,
 * which stop around 2016, and the timing board, which starts in 2021, and
 * it swallows three years of Javelina among 38 other races.
 *
 * They were passed over here as "a JS viewer loading a binary", which was
 * a guess and a wrong one. The viewer is a viewer; the .clax behind it is
 * plain XML and serves fine on its own. It is written by French timing
 * software, so the tag names are French: Epreuve is the event, Engages the
 * entrants, Resultats the results, Parcours the courses.
 *
 *   <E d="1" n="Reagan Patrick" a="1987" x="M" p="100 Miler" ... />
 *   <R d="1" t="13h11'48" ... />
 *
 * <E> is one entrant keyed by bib, <R> one result keyed by the same bib,
 * and neither is in finishing order, so the two are joined on the bib and
 * ranked here.
 * ------------------------------------------------------------------ */

// The viewer's own URL is what got stored: the file is the f= parameter
// hanging off it. 38 of the 70 were stored through something that encoded
// the percent signs too, so "%20" arrives as "%2520" and needs unwrapping
// twice before it is a path.
function claxUrl( viewer ) {
	const m = viewer.match( /[?&]f=([^&]+)/ );

	if ( ! m ) return null;

	let f = m[ 1 ];

	for ( let i = 0; i < 2 && /%25/.test( f ); i++ ) f = decodeURIComponent( f );

	f = decodeURIComponent( f ).replace( /^\.?\//, '' );

	return viewer.replace( /\/?\?.*$/, '' ).replace( /\/$/, '' )
		+ '/' + f.split( '/' ).map( encodeURIComponent ).join( '/' );
}

const claxAttrs = ( tag ) =>
	Object.fromEntries( [ ...tag.matchAll( /([\w:.-]+)="([^"]*)"/g ) ].map( ( m ) => [ m[ 1 ], m[ 2 ] ] ) );

// "13h11'48" and "58'22".
function claxSeconds( t ) {
	const m = ( t || '' ).match( /^(?:(\d+)h)?(\d+)'(\d+)/ );
	return m ? ( +( m[ 1 ] || 0 ) ) * 3600 + ( +m[ 2 ] ) * 60 + ( +m[ 3 ] ) : null;
}

function claxTime( t ) {
	const n = claxSeconds( t );

	if ( null === n ) return '';

	const h = Math.floor( n / 3600 );
	const mm = Math.floor( ( n % 3600 ) / 60 );
	const ss = n % 60;

	return h
		? `${ h }:${ String( mm ).padStart( 2, '0' ) }:${ String( ss ).padStart( 2, '0' ) }`
		: `${ mm }:${ String( ss ).padStart( 2, '0' ) }`;
}

// The name arrives surname first and in one field, so where the surname
// ends can only be guessed. The last word is taken as the given name,
// which is right for "Reagan Patrick" and right again for the compound
// surnames that make up most of the longer ones: "Del Conte Joe",
// "St. Louis Jackie", "Perez Colon Francisco", "Strach III Walter".
//
// It is wrong for somebody entered under two given names, "Ahern Ann
// Marie", who comes out as "Marie Ahern Ann". 24 of 1212 entrants in the
// file this was measured on have three words at all, and roughly two
// thirds of those are the compound-surname kind, so the rule is right for
// about 99% of names and there is nothing in the file that would settle
// the rest.
function claxName( whole ) {
	const parts = ( whole || '' ).trim().split( /\s+/ ).filter( Boolean );

	if ( ! parts.length ) return '';

	const flipped = parts.length < 2
		? parts
		: [ parts[ parts.length - 1 ], ...parts.slice( 0, -1 ) ];

	return recase( flipped );
}

const SHOUTED = ( t ) => t.length > 2 && t === t.toUpperCase() && /[A-Z]/.test( t );

// 2018 and much of 2019 were entered with the surname in capitals, so a
// fifth of these read "Patrick REAGAN" and "Tim TOLLEFSON" next to a board
// result reading "Cody Lind". The capitals are a data-entry convention and
// not how anybody spells their name, so they come back down.
//
// Only where the record is already shouting. A two-letter run of capitals
// is initials ("AJ", "CJ") on a name that was typed normally, and only
// worth touching once a longer word in the same name has proved the whole
// record was typed in caps: that is what tells "Isaac ST MARTIN" apart
// from "AJ Degraw". The given name is left alone either way, since after
// the flip it leads and initials live there.
function recase( parts ) {
	if ( ! parts.some( SHOUTED ) ) return parts.join( ' ' );

	return parts
		.map( ( t, i ) => ( SHOUTED( t ) || ( i > 0 && t === t.toUpperCase() && /[A-Z]/.test( t ) ) ? title( t ) : t ) )
		.join( ' ' );
}

// Capitals after a hyphen or an apostrophe as well, so SMITH-JONES and
// O'BRIEN come back as themselves. Mc is the one prefix worth knowing:
// MCKEE is McKee to everyone including the board, which spells her that
// way on the years it covers. Mac is deliberately not in here, because
// Macdonald and MacDonald are both real spellings and the file cannot say
// which one this is.
function title( t ) {
	let out = t.charAt( 0 ).toUpperCase() + t.slice( 1 ).toLowerCase();

	out = out.replace( /([-'\u2019])([a-z])/g, ( _, sep, c ) => sep + c.toUpperCase() );

	return out.replace( /^Mc([a-z])/, ( _, c ) => 'Mc' + c.toUpperCase() );
}

// What a course name says it is, in metres, for the two files that
// declare every one of their courses as distance="0". Javelina 2017 is
// one of them, and with nothing to sort on its 100K led its 100 Mile.
// Only ever a fallback: where the file states a length, the file wins.
function guessMetres( name ) {
	const n = name.toLowerCase();

	if ( /^(1\/2|half)\s*marathon$/.test( n ) ) return 21098;
	if ( /^marathon$/.test( n ) ) return 42195;

	const mi = n.match( /^([\d.]+)\s*(?:m|mi|miles?|miler)$/ );
	if ( mi ) return Math.round( +mi[ 1 ] * 1609.344 );

	const km = n.match( /^([\d.]+)\s*k(?:m|ilometers?)?$/ );
	if ( km ) return Math.round( +km[ 1 ] * 1000 );

	return 0;
}

function parseClax( xml ) {
	const entrants = new Map();

	for ( const m of xml.matchAll( /<E\b[^>]*\/>/g ) ) {
		const a = claxAttrs( m[ 0 ] );
		if ( a.d ) entrants.set( a.d, a );
	}

	// The course list carries each one's real length, which is what says
	// whether an event has a premier distance or is one loop everybody
	// runs. Same question the board answers with its own ordering.
	const lengths = {};

	for ( const m of xml.matchAll( /<Pcs\b[^>]*\/>/g ) ) {
		const a = claxAttrs( m[ 0 ] );
		if ( a.nom ) {
			const c = courseName( a.nom );
			lengths[ c ] = ( +a.distance || 0 ) || guessMetres( c );
		}
	}

	const byCourse = {};
	let seen = 0;

	for ( const m of xml.matchAll( /<R\b[^>]*\/>/g ) ) {
		const a = claxAttrs( m[ 0 ] );
		const e = entrants.get( a.d );

		if ( ! e ) continue;

		const secs = claxSeconds( a.t );

		if ( ! secs ) continue;

		const name = claxName( e.n );

		if ( ! name ) continue;

		seen++;

		const course = courseName( e.p || 'Results' );

		( byCourse[ course ] ||= [] ).push( {
			name,
			sex: ( e.x || '' ).toUpperCase(),
			secs,
			time: claxTime( a.t ),
			// Metres covered, on the events that count laps rather than
			// finishes. Absent on everything else.
			metres: a.ds ? +a.ds : null,
		} );
	}

	const winners = [];

	for ( const [ course, rows ] of Object.entries( byCourse ) ) {
		// A team category is not a person. Fat Ox scores a "24Hr-2 Person"
		// alongside its solo races, and its entrants are teams entered
		// under a team name with a sex field that means nothing, so the
		// winners came out as "GOGG MEN OF" and "FEET HAPPY".
		if ( /\b(\d+\s*person|relay|team|duo|pairs?)\b/i.test( course ) ) continue;

		// A fixed-time race says so in its own numbers: everybody stopped
		// at the same moment having covered a different distance, so the
		// metres vary and the clock does not rank anybody. Where every
		// finisher covered the same course, the clock is the whole result.
		const spread = new Set( rows.map( ( r ) => r.metres ) );
		const byDistance = ! spread.has( null ) && spread.size > 1;

		rows.sort( ( a, b ) => ( byDistance ? b.metres - a.metres : a.secs - b.secs ) );

		const row = { distance: course };

		for ( const r of rows ) {
			const g = GENDERS[ r.sex.toLowerCase() ];

			if ( ! g || row[ g ] ) continue;

			row[ g ] = {
				name: r.name,
				time: byDistance ? `${ +( r.metres / 1609.344 ).toFixed( 2 ) } mi` : r.time,
			};
		}

		if ( Object.keys( row ).length > 1 ) {
			winners.push( { row, length: lengths[ course ] || 0, count: rows.length } );
		}
	}

	if ( ! winners.length ) return null;

	winners.sort( ( a, b ) => b.length - a.length );

	// Premier only where one course is strictly the longest. A lap event
	// runs every category over the same loop, so none of them is a top
	// result to feature over the others.
	const longest = winners[ 0 ].length;
	const headline = winners.length === 1
		|| ( longest > 0 && winners.filter( ( w ) => w.length === longest ).length === 1 );

	return {
		winners: winners.map( ( w ) => w.row ),
		finishers: seen,
		rows: Math.max( ...winners.map( ( w ) => w.count ) ),
		headline,
	};
}

// Waves are the same race started twice, not two races: Javelina's
// "Jackass 31K Wave 2" is the only one in the archive, and left alone it
// would stand as its own course record beside "Jackass 31K".
const courseName = ( n ) => n.replace( /\s*wave\s*\d+\s*$/i, '' ).trim();

// "Starters: 18 Finishers: 14 Finish Rate: 77.8%", where a file has it.
function counts( html ) {
	const t = text( html ).slice( 0, 600 );
	const s = t.match( /Starters:\s*(\d+)/i );
	const f = t.match( /Finishers:\s*(\d+)/i );
	return { starters: s ? +s[ 1 ] : 0, finishers: f ? +f[ 1 ] : 0 };
}

async function main() {
	if ( ! WP || ! USER || ! PASS ) {
		console.error( 'ARAVAIPA_WP_URL, ARAVAIPA_WP_ADMIN_USER and ARAVAIPA_WP_ADMIN_APP_PASSWORD are required.' );
		process.exit( 1 );
	}

	const res = await fetch( `${ WP }/wp-json/aravaipa/v1/results`, { headers: { Authorization: auth() } } );
	if ( ! res.ok ) throw new Error( `results read ${ res.status }` );
	const rows = ( await res.json() ).rows;

	// Only the editions the board cannot answer for. A race with a board is
	// already covered and its board is the better record.
	const targets = rows.filter( ( r ) => ! r.live && ( r.archive || [] ).length );

	console.error( `${ targets.length } editions with files and no board\n` );

	const events = [];
	const skipped = [];
	let done = 0;

	for ( const row of targets ) {
		const winners = [];
		let starters = 0;
		let finishers = 0;
		let rowsMax = 0;

		let headline = null;

		// The same file, fetched twice under two URLs. Cave Creek Thriller
		// 2015 lists every one of its four distances once from /results/,
		// the original host, and once more from /wp-content/uploads/,
		// where the same race day was uploaded a second time through the
		// media library. Checked once a file is in hand rather than by
		// URL, which a media-library duplicate does not share with its
		// original at all.
		const seenBodies = new Set();

		// The same distance, from two files that disagree rather than
		// match. Two of Cave Creek Thriller's four duplicate pairs are the
		// same file byte for byte and seenBodies above catches those; the
		// 24K pair is not, the /results/ copy has 75 finishers and the
		// uploads copy 73, missing a runner mid-pack the other one has.
		// Neither file says it is the draft, so this cannot know which one
		// is the real result, only that a file which is complete claims
		// more finishers than one that is missing some of them: kept per
		// distance here rather than summed across every file that offers
		// one, so the same race is not counted once as itself and once
		// again as an incomplete copy of itself.
		const byDistance = new Map();

		function keep( label, groupFinishers, groupWinners ) {
			const prev = byDistance.get( label );
			if ( ! prev || groupFinishers > prev.finishers ) {
				byDistance.set( label, { finishers: groupFinishers, winners: groupWinners } );
			}
		}

		for ( const file of row.archive ) {
			const clax = /ultracast/i.test( file.url );
			const url = clax ? claxUrl( file.url ) : file.url;

			if ( ! url || /\.pdf$/i.test( url ) ) continue;
			if ( ! clax && ! /aravaiparunning\.com/i.test( url ) ) continue;

			// A .docx is a zip and has to come back as bytes, or the fetch
			// would attempt to decode it as text and hand the zip's own
			// binary layout to code that reads XML out of it.
			const isDocx = /\.docx$/i.test( url );

			let body;
			try {
				const r = await fetch( url, { headers: { 'User-Agent': UA } } );
				if ( ! r.ok ) { skipped.push( `${ row.name } ${ row.iso }: ${ file.label } HTTP ${ r.status }` ); continue; }
				body = isDocx ? Buffer.from( await r.arrayBuffer() ) : await r.text();
			} catch ( e ) {
				skipped.push( `${ row.name } ${ row.iso }: ${ file.label } ${ e.message }` );
				continue;
			}

			const bodySig = isDocx ? body.toString( 'base64' ) : body.replace( /\r/g, '' ).trim();
			if ( seenBodies.has( bodySig ) ) continue;
			seenBodies.add( bodySig );

			// One Ultracast file is the whole event, every distance in it,
			// where a static file is one distance and an edition has five.
			// So it answers the premier-distance question itself, off the
			// course lengths it carries, instead of the caller guessing it
			// from how many files happened to be listed.
			if ( clax ) {
				const got = parseClax( body );
				if ( ! got ) { skipped.push( `${ row.name } ${ row.iso }: ${ file.label } unparseable` ); continue; }

				// One clax file is the whole event, every distance in it,
				// so there is nothing else within this row it could be a
				// duplicate of: pushed straight in rather than through
				// byDistance, which exists for the static-file branch
				// below finding the same distance in more than one file.
				finishers += got.finishers;
				rowsMax = Math.max( rowsMax, got.rows );
				winners.push( ...got.winners );
				headline = got.headline;
				continue;
			}

			// Almost every file here is one distance, and sections() gives
			// it back exactly that: the one group everything falls into,
			// label ''. Silverton Alpine's .docx and Buckeye's .txt are the
			// two exceptions this exists for, each one file naming every
			// distance of the race in it, so this is run whether or not a
			// file turns out to need it rather than special-cased to the
			// two that do.
			const cellRows = isDocx ? docxRows( body ) : /\.txt$/i.test( url ) ? textCellRows( body ) : null;

			// Every group but a bundled file's is { label: '', rows: <raw> },
			// one entry, everything in it: parseCellRows still has to run to
			// find that group's own header, an HTML file's header included,
			// so this is the one place that call happens regardless of
			// source.
			const groups = ( cellRows ? sections( cellRows ) : [ { label: '', rows: htmlCellRows( body ) } ] ).map(
				( group ) => ( { label: group.label, ...parseCellRows( group.rows ) } )
			);

			// "Starters: 18 Finishers: 14" is a phrase Aravaipa's own
			// generated scoreboard pages print in their first few hundred
			// characters, an HTML convention and not one a hand-typed .txt
			// or a Word document ever carries, so it is only worth asking
			// an HTML file for it, and only once per file rather than once
			// per distance group inside it: nothing here bundles more than
			// one distance behind that phrase.
			const c = cellRows ? { starters: 0, finishers: 0 } : counts( body );
			let fileHadAnything = false;

			for ( const group of groups ) {
				const got = winnersFromRows( group.header, group.rows );
				if ( ! got ) continue;

				fileHadAnything = true;
				rowsMax = Math.max( rowsMax, got.finishers );

				const label = group.label || file.label || 'Results';

				// The file's own stated total where the groups did not
				// already split it out label by label: a bundled file
				// (Silverton Alpine's .docx, Buckeye's .txt) reports one
				// count per group already, and asking the whole file's
				// header for that count as well would credit it to
				// whichever of the two groups happened to run last.
				const groupFinishers = groups.length > 1 ? got.finishers : ( c.finishers || got.finishers );

				keep( label, groupFinishers, got.winners );
			}

			if ( ! fileHadAnything ) { skipped.push( `${ row.name } ${ row.iso }: ${ file.label } unparseable` ); continue; }

starters += c.starters;
		}

		// Flattened once every file has been read, rather than added to as
		// each one comes in: keep() may still be replacing a worse entry
		// for a distance seen earlier in the same row's file list right up
		// until the last file is read.
		for ( const [ label, entry ] of byDistance ) {
			finishers += entry.finishers;
			if ( Object.keys( entry.winners ).length ) {
				winners.push( { distance: label, ...entry.winners } );
			}
		}

		// Winners where the file names them, and a finisher count either
		// way: the lap scoreboards carry no gender column, so they can say
		// how many finished without saying who won, and a count is still
		// more than the nothing these editions show today.
		if ( winners.length || finishers ) {
			// A clax file already answered which distance leads, off the
			// real course lengths it carries. Static files are one
			// distance each with no ordering between them, so where clax
			// took no part the same question is answered the same way:
			// guess each label's length from its own name and rank by it.
			//
			// 2017 Mogollon Monster is the file this fixes. Three files,
			// "100M", "105k", "35k", 113 finishers between them, and the
			// old rule ("headline only when there is exactly one file")
			// called that three-way tie and showed none of them, a plain
			// "Winners, 3 distances" where every other year on the page
			// leads with its 100 Mile result. The distance a file is
			// named for says which one that is here as plainly as a
			// clax file's own Pcs table does.
			if ( null === headline && winners.length > 1 ) {
				winners.forEach( ( w ) => {
					w._m = guessMetres( w.distance || '' );
				} );
				winners.sort( ( a, b ) => b._m - a._m );

				const longest = winners[ 0 ]._m;
				headline = longest > 0 && winners.filter( ( w ) => w._m === longest ).length === 1;

				winners.forEach( ( w ) => {
					delete w._m;
				} );
			}

			events.push( {
				name: row.name,
				iso: row.iso,
				starters,
				finishers,
				rows: rowsMax,
				headline: null === headline ? winners.length === 1 : headline,
				winners,
			} );
		}

		if ( ++done % 25 === 0 ) console.error( `  ${ done }/${ targets.length }` );
	}

	console.error( `\n${ events.length } editions parsed, ${ skipped.length } files skipped` );
	for ( const s of skipped.slice( 0, 12 ) ) console.error( `  ${ s }` );
	if ( skipped.length > 12 ) console.error( `  ... and ${ skipped.length - 12 } more` );

	// Editions this script cannot discover on its own: no local file, no
	// Ultracast entry, nothing here to walk to. /stats/archive replaces its
	// option wholesale, on purpose, the same reason this script's own run
	// replaces the whole thing rather than merging: absence means gone. A
	// plain re-run of this script is exactly that kind of absence for a
	// hand-researched year, and it cost the 2012 and 2013 Mogollon Monster
	// entries twice in one session before this file existed. Merged in
	// every run, after parsing and before posting, so a re-run can never
	// drop them again.
	const __dir = dirname( fileURLToPath( import.meta.url ) );
	const manualPath = join( __dir, '..', 'data', 'archive-stats-manual.json' );

	if ( existsSync( manualPath ) ) {
		const manual = JSON.parse( readFileSync( manualPath, 'utf8' ) ).events || [];
		const seen = new Set( events.map( ( e ) => `${ e.name.trim().toLowerCase() }|${ e.iso }` ) );
		let added = 0;

		for ( const e of manual ) {
			const key = `${ e.name.trim().toLowerCase() }|${ e.iso }`;
			if ( ! seen.has( key ) ) {
				events.push( e );
				added++;
			}
		}

		console.error( `${ added } hand-researched edition(s) merged in from data/archive-stats-manual.json` );
	}

	if ( opt( '--out' ) ) {
		writeFileSync( opt( '--out' ), JSON.stringify( events, null, 1 ) );
		console.error( `wrote ${ opt( '--out' ) }` );
	}

	if ( ! flag( '--post' ) ) {
		console.error( '\nnothing sent. pass --post to write.' );
		return;
	}

	const post = await fetch( `${ WP }/wp-json/aravaipa/v1/stats/archive`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json', Authorization: auth() },
		body: JSON.stringify( { events, dry_run: flag( '--dry-run' ) } ),
	} );

	console.error( `HTTP ${ post.status }` );
	console.log( await post.text() );
}

main().catch( ( e ) => { console.error( e ); process.exit( 1 ); } );
