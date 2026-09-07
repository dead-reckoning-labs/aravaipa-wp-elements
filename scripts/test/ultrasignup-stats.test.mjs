/**
 * Regression coverage for the UltraSignup reader, scripts/fetch-ultrasignup-stats.mjs,
 * and the distance ranking it shares with fetch-archive-stats.mjs.
 *
 * Real imports rather than copies: both files export the pieces under test
 * precisely so this cannot drift from what actually runs.
 *
 *   bun scripts/test/ultrasignup-stats.test.mjs
 */
import {
	parseDistances,
	normalizeDistance,
	winnersOf,
	didOf,
	isNotADistance,
	usableDistances,
} from '../fetch-ultrasignup-stats.mjs';
import { guessMetres, guessSeconds, rankWinners } from '../lib/distances.mjs';

let pass = 0, fail = 0;
const t = ( name, cond ) => {
	if ( cond ) { pass++; console.log( '  ok   ' + name ); }
	else { fail++; console.log( '  FAIL ' + name ); }
};

console.log( '\ndid extraction:' );
t( 'reads the id out of a stored results link',
	didOf( 'https://ultrasignup.com/results_event.aspx?did=84016' ) === '84016' );
t( 'reads it when it is not the first parameter',
	didOf( 'https://ultrasignup.com/results_event.aspx?x=1&did=99' ) === '99' );
t( 'a row with no UltraSignup link yields null', didOf( '' ) === null );
t( 'a link with no id yields null',
	didOf( 'https://ultrasignup.com/results_event.aspx' ) === null );

console.log( '\ndistance labels:' );
t( '"50 K" is spelled the way the rest of the site spells it', normalizeDistance( '50 K' ) === '50K' );
t( '"100 Mile" keeps its word', normalizeDistance( '100 Mile' ) === '100 Mile' );
t( '"100 Miler" is the same distance', normalizeDistance( '100 Miler' ) === '100 Mile' );
t( '"24 Hour" keeps its word', normalizeDistance( '24 hours' ) === '24 Hour' );
t( '"1/2 Marathon" is left alone', normalizeDistance( '1/2 Marathon' ) === '1/2 Marathon' );
t( 'a decimal distance survives', normalizeDistance( '52.4 K' ) === '52.4K' );
t( '"50 Kilometer" is a 50K', normalizeDistance( '50 Kilometer' ) === '50K' );

console.log( '\nsession markers come off the distance:' );
t( '"105K Saturday" is a 105K', normalizeDistance( '105K Saturday' ) === '105K' );
t( '"50K Sat." is a 50K', normalizeDistance( '50K Sat.' ) === '50K' );
t( '"10K-Sat-8pm" is a 10K', normalizeDistance( '10K-Sat-8pm' ) === '10K' );
t( '"31K-Sat-9:30pm" is a 31K', normalizeDistance( '31K-Sat-9:30pm' ) === '31K' );
t( '"15.5 miler-8:30pm" is a 15.5 Mile', normalizeDistance( '15.5 miler-8:30pm' ) === '15.5 Mile' );
t( '"Half Marathon - Sunday" keeps its name', normalizeDistance( 'Half Marathon - Sunday' ) === 'Half Marathon' );
// The point of stripping them: a session marker makes the label unreadable,
// so a 105K and a 10K would measure the same and neither would lead.
t( 'and a stripped label is measurable again', guessMetres( normalizeDistance( '105K Saturday' ) ) === 105000 );
t( 'a distance with no session marker is untouched', normalizeDistance( '10 Mile' ) === '10 Mile' );

console.log( '\nlabels that are not distances:' );
t( 'a virtual edition is not a distance', isNotADistance( 'VIRTUAL- 50K' ) );
t( 'and neither is its dawn breaker', isNotADistance( 'VIRTUAL- Adrenaline Dawn Breaker' ) );
t( 'the Insomniac series entry is not a distance', isNotADistance( '20 K INSOMNIAC' ) );
t( 'a pace wave is not a distance', isNotADistance( 'Chill pace wave' ) );
t( 'nor is a Roundtripper wave', isNotADistance( 'Roundtripper - Elite' ) );
t( 'a real distance is a distance', ! isNotADistance( '50K' ) );
t( 'a kids run is a real result and is kept', ! isNotADistance( "Kid's Fun Run" ) );
t( 'a named course is kept', ! isNotADistance( 'Jackass 31K' ) );

console.log( '\nbikes, kept or dropped by what else the event ran:' );
const mixed = usableDistances( [
	{ did: '1', label: '50K' },
	{ did: '2', label: '20 Miler Bike' },
	{ did: '3', label: '10K' },
] );
t( 'a bike division inside a running race is dropped',
	mixed.map( ( d ) => d.label ).join( ',' ) === '50K,10K' );

const allBikes = usableDistances( [
	{ did: '1', label: '50 Miler Bike' },
	{ did: '2', label: '30 Miler Bike' },
] );
t( 'an event that was only ever a bike race keeps its divisions', allBikes.length === 2 );

const nightRuns = usableDistances( [
	{ did: '1', label: '52K' },
	{ did: '2', label: 'VIRTUAL- 52K' },
	{ did: '3', label: '20 K INSOMNIAC' },
	{ did: '4', label: '6K' },
] );
t( 'a night run keeps only the distances it actually ran',
	nightRuns.map( ( d ) => d.label ).join( ',' ) === '52K,6K' );
t( 'an event whose every label is a pace wave yields nothing',
	usableDistances( [ { did: '1', label: 'Elite wave' }, { did: '2', label: 'Chill pace wave' } ] ).length === 0 );

console.log( '\nsibling distances off an event page:' );
const page = `
	<a href='/results_event.aspx?did=81915' class='event_selected_link' >91K</a>
	<a href='/results_event.aspx?did=81916' >60 K</a>
	<a href='/results_event.aspx?did=81918' >1/2 Marathon</a>
	<a href='/results_event.aspx?did=126299' >2026</a>
	<a href='/results_event.aspx?did=81915' >2021</a>
`;
const found = parseDistances( page, 81915 );
t( 'finds every distance including the selected one', found.length === 3 );
t( 'keeps the distances in page order',
	found.map( ( d ) => d.label ).join( ',' ) === '91K,60K,1/2 Marathon' );
t( 'a year link is not a distance', ! found.some( ( d ) => /^\d{4}$/.test( d.label ) ) );
t( 'the same id linked twice is only taken once',
	new Set( found.map( ( d ) => d.did ) ).size === found.length );

const solo = parseDistances( '<html>no sibling links at all</html>', 84016 );
t( 'a single-distance race still yields the id it came from',
	solo.length === 1 && solo[ 0 ].did === '84016' );

console.log( '\nwinners from result rows:' );
const rows = [
	{ status: 1, gender: 'M', gender_place: 1, firstname: 'Nick', lastname: 'Coury', formattime: '8:04:52' },
	{ status: 1, gender: 'M', gender_place: 2, firstname: 'Matt', lastname: 'Belus', formattime: '8:19:27' },
	{ status: 1, gender: 'F', gender_place: 1, firstname: 'Meghan', lastname: 'Slavin', formattime: '10:37:20' },
	{ status: 2, gender: 'M', gender_place: 0, firstname: 'Norb', lastname: 'Lyle', formattime: '0' },
	{ status: 2, gender: 'F', gender_place: 0, firstname: 'Margaret', lastname: 'Montfort', formattime: '0' },
];
const got = winnersOf( rows );
t( 'the fastest man wins', got.men.name === 'Nick Coury' && got.men.time === '8:04:52' );
t( 'the fastest woman wins', got.women.name === 'Meghan Slavin' );
t( 'a DNF is not a finisher', got.finishers === 3 );
t( 'a DNF is still a starter', got.starters === 5 );

// A DNF carries place 0, so anything that sorted on place alone would put
// every one of them ahead of the winner.
const dnfFirst = winnersOf( [
	{ status: 2, gender: 'M', gender_place: 0, firstname: 'Did', lastname: 'Notfinish', formattime: '0' },
	{ status: 1, gender: 'M', gender_place: 1, firstname: 'Real', lastname: 'Winner', formattime: '5:00:00' },
] );
t( 'a DNF listed first does not win', dnfFirst.men.name === 'Real Winner' );

// Some older editions carry every gender_place as 0, so the clock has to be
// what decides rather than a field that is not always filled in.
const noPlaces = winnersOf( [
	{ status: 1, gender: 'F', gender_place: 0, firstname: 'Slower', lastname: 'Runner', formattime: '6:00:00' },
	{ status: 1, gender: 'F', gender_place: 0, firstname: 'Faster', lastname: 'Runner', formattime: '4:30:00' },
] );
t( 'with no places recorded the clock decides', noPlaces.women.name === 'Faster Runner' );

t( 'a gender with only DNFs has no winner',
	winnersOf( [ { status: 2, gender: 'F', firstname: 'A', lastname: 'B', formattime: '0' } ] ).women === null );
t( 'an empty result set is not a crash',
	winnersOf( [] ).finishers === 0 && winnersOf( null ).men === null );
t( 'a finisher with no usable time is not a winner',
	winnersOf( [ { status: 1, gender: 'M', firstname: 'No', lastname: 'Time', formattime: '' } ] ).men === null );
t( 'a nameless row is not a winner',
	winnersOf( [ { status: 1, gender: 'M', firstname: '', lastname: '', formattime: '5:00:00' } ] ).men === null );

console.log( '\ndistance ranking, shared with the archive reader:' );
t( 'kilometres read', guessMetres( '50K' ) === 50000 );
t( 'miles read', guessMetres( '100 Mile' ) === 160934 );
t( 'a half marathon reads', guessMetres( '1/2 Marathon' ) === 21098 );
t( 'a label with no distance in it reads as none', guessMetres( 'Kendall Mountain Run' ) === 0 );
t( 'hours read', guessSeconds( '24 Hour' ) === 86400 );
t( 'days read', guessSeconds( '6 Day' ) === 518400 );
t( 'a ground distance is not a clock', guessSeconds( '50K' ) === 0 );

const w = [ { distance: '10K' }, { distance: '91K' }, { distance: '1/2 Marathon' } ];
t( 'the longest distance leads', rankWinners( w ) === true && w[ 0 ].distance === '91K' );
t( 'and the rest follow it longest first',
	w.map( ( x ) => x.distance ).join( ',' ) === '91K,1/2 Marathon,10K' );

const timed = [ { distance: '100 Mile' }, { distance: '24 Hour' } ];
t( 'a fixed-time race outranks a ground distance at the same event',
	rankWinners( timed ) === true && timed[ 0 ].distance === '24 Hour' );

t( 'a single distance is its own headline', rankWinners( [ { distance: '50K' } ] ) === true );
t( 'two of the same length tie and neither leads',
	rankWinners( [ { distance: '50K' }, { distance: '50K' } ] ) === false );
t( 'labels nothing can measure do not lead',
	rankWinners( [ { distance: 'Relay' }, { distance: 'Team' } ] ) === false );
t( 'no winners at all is not a headline', rankWinners( [] ) === false );

console.log( '\na bike event still has a longest division:' );
t( 'a bike distance reads the same as a run', guessMetres( '50 Miler Bike' ) === guessMetres( '50 Mile' ) );
t( 'a ride reads the same way',              guessMetres( '20 Mile Ride' ) === guessMetres( '20 Mile' ) );
const bikeOnly = [
	{ distance: '50 Miler Bike' }, { distance: '30 Miler Bike' },
	{ distance: '20 Miler Bike' }, { distance: '10 Miler Bike' },
];
t( 'Tonto Mountain 2020 gets a leader',        rankWinners( bikeOnly ) === true );
t( 'and it is the 50, not whichever was first', bikeOnly[ 0 ].distance === '50 Miler Bike' );

console.log( '\nLast Person Standing, scored in distance rather than time:' );
const lps = [
	{ status: 1, gender: 'M', firstname: 'Brian', lastname: 'Bondy', formattime: '129.3' },
	{ status: 1, gender: 'M', firstname: 'Graham', lastname: 'Felsenthal', formattime: '125.1' },
	{ status: 1, gender: 'F', firstname: 'Kelly', lastname: 'Young', formattime: '112.6' },
	{ status: 2, gender: 'M', firstname: 'Did', lastname: 'Notfinish', formattime: '0' },
];
const lpsWon = winnersOf( lps );
t( 'the furthest man wins',       lpsWon.men.name === 'Brian Bondy' && lpsWon.men.time === '129.3 mi' );
t( 'the furthest woman wins',     lpsWon.women.name === 'Kelly Young' && lpsWon.women.time === '112.6 mi' );
t( 'DNFs are not finishers here either', lpsWon.finishers === 3 );
t( 'a real race is not read as mileage', ( () => {
	const timed = winnersOf( [
		{ status: 1, gender: 'M', firstname: 'A', lastname: 'B', formattime: '5:00:00' },
	] );
	return timed.men.time === '5:00:00';
} )() );

console.log( `\n${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
