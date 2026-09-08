/**
 * Regression coverage for the Zenfolio walker and the race matcher it now
 * shares with the SmugMug one.
 *
 * The matcher tests are the ones that matter. This scraper's failure mode
 * is not a crash, it is publishing somebody else's race as Aravaipa's, or
 * quietly dropping one of Aravaipa's own, and neither announces itself.
 *
 *   bun scripts/test/zenfolio.test.mjs
 */
import { galleriesOn, yearFoldersOn } from '../discover-zenfolio.mjs';
import { raceMatcher, yearFrom, normalise, phrasesFor, IS_RIDE } from '../lib/race-match.mjs';

let pass = 0, fail = 0;
const t = ( name, cond ) => {
	if ( cond ) { pass++; console.log( '  ok   ' + name ); }
	else { fail++; console.log( '  FAIL ' + name ); }
};

// The markup Zenfolio actually serves to a non-browser user agent: the
// anchor carries the gallery's name on a title attribute, and the visible
// label is written in later by the script that loads the thumbnails.
const GRID = `
<span class="pv"><a class="pv-inner" href="/2012acrosstheyears" title="2012 Across The Years" style="left:0px"><div class="pv-img"></div></a></span>
<span class="pv"><a class="pv-inner" href="/2012javelinajundred" title="2012 Javelina Jundred"><div class="pv-img"></div></a></span>
<span class="pv"><a class="pv-inner" href="/f464103643" title="2013 Races"><div class="pv-img"></div></a></span>
<span class="pv"><a class="pv-inner" href="/2012acrosstheyears" title="2012 Across The Years"><div class="pv-img"></div></a></span>
`;

console.log( '\nreading a Zenfolio grid:' );
const found = galleriesOn( GRID );
t( 'every gallery on the page',       3 === found.length );
t( 'the name comes off the title',    '2012 Across The Years' === found[ 0 ].name );
t( 'and the link off the href',       '/2012acrosstheyears' === found[ 0 ].href );
t( 'the same gallery twice is once',  1 === found.filter( ( g ) => g.href === '/2012acrosstheyears' ).length );
t( 'nothing in an empty page',        0 === galleriesOn( '' ).length );

// The index page, which is the only page yearFoldersOn() is ever given:
// every tile on it is a year folder, plus one that is not an edition of
// anything and has no year to give.
const INDEX = `
<a class="pv-inner" href="/f926130322" title="2025 Races"></a>
<a class="pv-inner" href="/2009races" title="2009 Races"></a>
<a class="pv-inner" href="/f852750860" title="2023 Races"></a>
<a class="pv-inner" href="/coursephotos" title="Course Photos"></a>
`;
const folders = yearFoldersOn( INDEX );
t( 'every year folder on the index',  3 === folders.length );
t( 'newest first',                    2025 === folders[ 0 ].year );
t( 'back to the oldest Zenfolio has', 2009 === folders[ folders.length - 1 ].year );
t( 'and Course Photos is not a year', ! folders.some( ( f ) => /course/i.test( f.name ) ) );

console.log( '\nyears, including the one SmugMug never needed:' );
t( '2009 is a year',                  2009 === yearFrom( '2009 Races' ) );
t( 'so is 2012',                      2012 === yearFrom( '2012 Across The Years' ) );
t( 'and 2026',                        2026 === yearFrom( '2026 Events' ) );
t( 'a distance is not a year',        0 === yearFrom( 'Jackass 31K' ) );
t( 'nor is nothing at all',           0 === yearFrom( 'Course Photos' ) );

console.log( '\nnormalising the way both hosts type things:' );
t( '"Mountain 2 Fountain" is "to"',   normalise( 'Mountain 2 Fountain' ).includes( ' to ' ) );
t( '"50K & 27K" is "and"',            normalise( '50K & 27K' ).includes( ' and ' ) );
t( 'punctuation goes',                'coldwater rumble' === normalise( "Coldwater  Rumble!" ) );

console.log( '\nrides belong to the other brand:' );
t( 'a ride is a ride',                IS_RIDE.test( 'Sinister Night Rides' ) );
t( 'singular too',                    IS_RIDE.test( 'Jangover Night Ride' ) );
t( 'and a bike',                      IS_RIDE.test( 'Tonto Mtn Bike' ) );
t( 'a run is not',                    ! IS_RIDE.test( 'Sinister Night Runs' ) );

console.log( '\nmatching a gallery to a race, and only to one:' );
const races = [
	'Coldwater Rumble', 'Across The Years', 'Javelina Jundred', 'Mountain Ridge Trail Race',
	'Tushars Mountain Runs', 'Copper Corridor', 'Silverton Alpine Marathon', 'Mayhem Night Runs',
];
const raceFor = raceMatcher( races );

t( 'a plain year-prefixed name',      'Coldwater Rumble' === raceFor( '2012 Coldwater Rumble' ) );
t( 'a year-suffixed name',            'Javelina Jundred' === raceFor( 'Javelina Jundred 2016' ) );
t( 'the longest phrase wins',         'Tushars Mountain Runs' === raceFor( 'Tushars Mountain Runs 2021' ) );

// The reason the single-word rule exists at all: "Tushars Mountain Runs"
// matched "Mountain Ridge Trail Race" on the bare word "mountain" until it
// was stopped, and "Copper Mtn Cross Country Meet", somebody else's race
// entirely, matched Copper Corridor on "copper".
t( 'a generic word matches nothing',  null === raceFor( 'Some Mountain Thing' ) );
t( 'and a short one does not either', null === raceFor( 'Copper Mtn Cross Country Meet' ) );
t( 'an unknown race is unmatched',    null === raceFor( 'Boston Marathon' ) );

// Known limitation, recorded rather than fixed: "Mayhem" is six letters, so
// the single-word rule rejects it and "Mayhem 2017" finds no race. Loosening
// the rule to seven characters is what stopped the Copper Mtn mismatch
// above, and one gallery is not worth reopening that.
t( 'a six-letter name is a known miss', null === raceFor( 'Mayhem 2017' ) );

console.log( '\nphrases are built longest first:' );
const p = phrasesFor( 'Coldwater Rumble' );
t( 'the whole name first',            'coldwater rumble' === p[ 0 ] );
t( 'and single words are dropped',    ! p.includes( 'rumble' ) );

console.log( `\n${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
