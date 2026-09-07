/**
 * Regression coverage for the podcast-to-video matcher in
 * scripts/fetch-podcast-art.mjs.
 *
 * The point of these is the near misses, not the hits. A wrong thumbnail on
 * a real episode looks completely fine and would never get reported, so
 * every case below that asserts *no* match is a real pairing this scraper
 * saw on the live channel and had to be stopped from making.
 *
 *   bun scripts/test/podcast-art.test.mjs
 */
import { titleTokens, yearsIn, overlap, matchEpisode } from '../fetch-podcast-art.mjs';

let pass = 0, fail = 0;
const t = ( name, cond ) => {
	if ( cond ) { pass++; console.log( '  ok   ' + name ); }
	else { fail++; console.log( '  FAIL ' + name ); }
};

const day = ( s ) => new Date( `${ s }T12:00:00Z` );
const vid = ( title, date, image = 'https://i.ytimg.com/x.jpg' ) => ( { title, date: day( date ), image } );

console.log( '\ntitle tokens:' );
t( 'the show name after a pipe is dropped',
	! titleTokens( 'Chocorua 2026 Preview | White Mountain Endurance Podcast' ).has( 'mountain' ) );
t( 'filler words are dropped', ! titleTokens( 'A Preview of the Show' ).has( 'preview' ) );
t( 'the words that identify it are kept',
	titleTokens( 'Jigger Johnson Ultras 2025' ).has( 'jigger' ) );
t( 'punctuation does not split a name',
	titleTokens( "Larsen Ojala's Appalachian Effort" ).has( 'ojala' ) );

console.log( '\nyears:' );
t( 'a year is found', yearsIn( '2025 Whiskey Basin Race Brief' ).has( '2025' ) );
t( 'two years are both found', yearsIn( '2025 and 2026' ).size === 2 );
t( 'a bare number is not a year', yearsIn( 'Jigger Johnson 100' ).size === 0 );

console.log( '\noverlap is measured against the shorter title:' );
// Aravaipa retitles for YouTube, so the episode title is often a subset of
// the video's. Measured against the union those score badly and every
// retitled episode loses its art.
t( 'a subset scores full marks',
	overlap( titleTokens( '2026 Mogollon Monster Race Brief' ),
	         titleTokens( '2026 Mogollon Monster Race Brief: 100 Mile & 42K' ) ) === 1 );
t( 'nothing shared scores zero',
	overlap( titleTokens( 'Whiskey Basin' ), titleTokens( 'Black Canyon' ) ) === 0 );
t( 'an empty title scores zero', overlap( titleTokens( '' ), titleTokens( 'anything' ) ) === 0 );

console.log( '\nthe match, and what it refuses:' );
const videos = [
	vid( 'Jigger Johnson Ultras Race 2026 Preview | White Mountain', '2026-08-13', 'https://i.ytimg.com/2026.jpg' ),
	vid( 'Jigger Johnson Ultras Race 2025 Preview | White Mountain', '2025-08-13', 'https://i.ytimg.com/2025.jpg' ),
	vid( '2025 Whiskey Basin Trail Runs Race Brief', '2025-04-10', 'https://i.ytimg.com/wb.jpg' ),
	vid( 'THE DESERT DECIDES - The 2025 Black Canyon Ultras', '2025-05-16', 'https://i.ytimg.com/bc.jpg' ),
	vid( 'How Stella Springer Became a 100-Mile National Champion', '2025-03-13', 'https://i.ytimg.com/ss.jpg' ),
];

const got2025 = matchEpisode( { title: 'Jigger Johnson Ultras 2025 Preview | White Mountain Endurance Podcast', date: day( '2025-08-13' ) }, videos );
t( 'the right year wins', got2025 && got2025.image === 'https://i.ytimg.com/2025.jpg' );

// The failure this whole design exists for. On words alone the 2026 video
// scores 0.67 against the 2025 episode: right race, wrong year, a thumbnail
// nobody would ever check.
const wrongYear = matchEpisode( { title: 'Jigger Johnson Ultras 2025 Preview', date: day( '2026-08-13' ) }, [ videos[ 0 ] ] );
t( 'a year mismatch is refused even on the same day', wrongYear === null );

// Published five days apart is a different episode, however alike they read.
const stale = matchEpisode( { title: '2025 Whiskey Basin Trail Runs Race Brief', date: day( '2025-04-20' ) }, videos );
t( 'outside the date window is refused', stale === null );

// Real pairing off the live channel: a Tushars episode and a Black Canyon
// film published the next day share almost nothing but sit close in time.
const unrelated = matchEpisode( { title: 'Tushars Mountain Runs 2025 Course Updates!', date: day( '2025-05-15' ) }, videos );
t( 'a near-date but unrelated video is refused', unrelated === null );

// Retitled for YouTube, same person, same day: this one should match.
const retitled = matchEpisode( { title: 'Stella Springer | Meet The Team | Aravaipa Racing', date: day( '2025-03-13' ) }, videos );
t( 'a retitled episode still matches', retitled && retitled.image === 'https://i.ytimg.com/ss.jpg' );

t( 'no videos at all is not a crash', matchEpisode( { title: 'anything', date: day( '2025-01-01' ) }, [] ) === null );
t( 'an episode with no title matches nothing',
	matchEpisode( { title: '', date: day( '2025-04-10' ) }, videos ) === null );

console.log( `\n${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
