/**
 * Regression coverage for the nightly photo ingest's planner
 * (scripts/lib/photo-ingest.mjs) and the tie-aware matcher it uses.
 *
 * The planner decides what gets written to /photos/ with nobody watching,
 * so every case here is about NOT adding: a gallery already in the store,
 * a tie between two races, a race with no card, a second gallery for the
 * same card and photographer, a run that finds too much.
 *
 *   bun scripts/test/photo-ingest.test.mjs
 */
import { planIngest, urlKey, urlPresent, cardName, folderSignature } from '../lib/photo-ingest.mjs';
import { raceMatcherWithTies, raceMatcher } from '../lib/race-match.mjs';
import { isContainer, isPublic } from '../lib/smugmug.mjs';

let pass = 0, fail = 0;
const t = ( name, cond ) => {
	if ( cond ) { pass++; console.log( '  ok   ' + name ); }
	else { fail++; console.log( '  FAIL ' + name ); }
};

const row = ( display, year, by, url, key = display.toLowerCase().replace( / (trail runs|night runs|night trail)$/, '' ) ) =>
	( { race: display, display, key, year, by, url } );

// A slice of the real store, in the shape photos-ingest.php's context mode
// prints it, including the cards that came back wrong in the first live run.
const context = {
	rows: [
		row( 'Coldwater Hundred', 2025, "Let's Wander Photography", 'https://lwp.smugmug.com/2025-Race-Photography/Coldwater-Rumble-Trail-Runs-2025', 'coldwater hundred' ),
		row( 'Coldwater Hundred', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/Coldwater-Rumble', 'coldwater hundred' ),
		row( 'The Bear Chase', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/The-Bear-Chase-Race-2025', 'bear chase' ),
		row( 'Mayhem Night Trail', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/McDowell-Mountain-Frenzy-Mayhem', 'mayhem night' ),
		row( 'McDowell Mountain Frenzy', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/McDowell-Mountain-Frenzy', 'mcdowell mountain frenzy' ),
		row( 'Flagstaff Extreme Big Pine Trail Runs', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/Flagstaff-Extreme', 'flagstaff extreme big pine' ),
		row( 'Flagstaff Sky Peaks', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/Sky-Peaks', 'flagstaff sky peak' ),
		row( 'Aspen Backcountry', 2025, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2025-Events/Aspen-Backcountry', 'aspen backcountry' ),
		row( 'Sinister Night Runs', 2026, 'Aravaipa Photo Gallery', 'https://aravaipa.smugmug.com/2026-Events/Sinister-Night-Runs/Finish', 'sinister' ),
	],
	names: [
		{ name: 'Coldwater Rumble', key: 'coldwater rumble', source: 'results' },
		{ name: 'Big Pine', key: 'big pine', source: 'results' },
		{ name: 'Aspen Backcountry Marathon', key: 'aspen backcountry marathon', source: 'results' },
		{ name: 'Devil After Dark', key: 'devil after dark', source: 'calendar' },
		{ name: 'Flagstaff 50 Endurance Runs', key: 'flagstaff 50 endurance', source: 'calendar' },
	],
};

const g = ( race, year, url, name, extra = {} ) =>
	( { race, tied: [], year, by: 'Aravaipa Photo Gallery', account: 'Aravaipa', url, name, photos: 100, ...extra } );

console.log( '\nurls:' );
t( 'scheme, case, query and trailing slash ignored', urlKey( 'HTTPS://Lwp.SmugMug.com/2026/X/?k=1' ) === 'lwp.smugmug.com/2026/x' );
t( 'an album inside a stored folder counts as present', urlPresent( 'https://a.smugmug.com/E/Race/Finish', [ 'a.smugmug.com/e/race' ] ) );
t( 'the folder holding a stored album counts as present', urlPresent( 'https://a.smugmug.com/E/Race', [ 'a.smugmug.com/e/race/finish' ] ) );
t( 'a sibling with a shared prefix is not present', ! urlPresent( 'https://a.smugmug.com/E/Race-2', [ 'a.smugmug.com/e/race' ] ) );
t( 'folder signature drops the year', folderSignature( 'Coldwater Rumble Trail Runs 2026' ) === folderSignature( 'Coldwater Rumble Trail Runs 2025' ) );

console.log( '\ncard names:' );
t( 'newest year name wins', cardName( [ { race: 'Old Name', year: 2024 }, { race: 'New Name', year: 2025 } ] ) === 'New Name' );

console.log( '\nmatcher ties:' );
const m = raceMatcherWithTies( [ 'Aspen Backcountry', 'Aspen Backcountry Marathon', 'Coldwater Rumble' ] );
t( 'longest phrase wins, no tie', m( 'Aspen Backcountry Marathon 2026' ).race === 'Aspen Backcountry Marathon' && m( 'Aspen Backcountry Marathon 2026' ).tied.length === 0 );
t( 'equal phrases are reported as a tie', m( 'Aspen Backcountry 2026' ).tied.includes( 'Aspen Backcountry Marathon' ) );
t( 'no match is null', null === m( 'Paavo Nurmi Marathon' ) );
t( 'agrees with raceMatcher on the winner', m( 'Coldwater Rumble 2026' ).race === raceMatcher( [ 'Aspen Backcountry', 'Coldwater Rumble' ] )( 'Coldwater Rumble 2026' ) );

console.log( '\nsmugmug:' );
t( '"2026 Events" is a container', isContainer( '2026 Events' ) );
t( '"Oregon 200 Miler 2025" is not', ! isContainer( 'Oregon 200 Miler 2025' ) );
t( 'password gallery is not public', ! isPublic( { EffectiveSecurityType: 'Password' } ) );
t( 'open gallery is public', isPublic( { EffectiveSecurityType: 'None' } ) );

console.log( '\nplanning:' );
const discovered2025 = [
	g( 'Coldwater Rumble', 2025, 'https://aravaipa.smugmug.com/2025-Events/Coldwater-Rumble', 'Coldwater Rumble' ),
	g( 'Coldwater Rumble', 2025, 'https://lwp.smugmug.com/2025-Race-Photography/Coldwater-Rumble-Trail-Runs-2025', 'Coldwater Rumble Trail Runs 2025', { by: "Let's Wander Photography", account: 'lwp' } ),
	g( 'McDowell Mountain Frenzy', 2025, 'https://aravaipa.smugmug.com/2025-Events/McDowell-Mountain-Frenzy-Mayhem', 'McDowell Mountain Frenzy Mayhem' ),
	g( 'McDowell Mountain Frenzy', 2025, 'https://aravaipa.smugmug.com/2025-Events/McDowell-Mountain-Frenzy', 'McDowell Mountain Frenzy' ),
];

let p = planIngest( { discovered: discovered2025, context } );
t( 'galleries already stored are never added', 0 === p.add.length && 0 === p.review.length && 4 === p.present.length );
t( 'stored galleries with a store-specific card are recognised (agreement)', p.agreement.agree >= 2 );

p = planIngest( {
	discovered: [ ...discovered2025, g( 'Coldwater Rumble', 2026, 'https://lwp.smugmug.com/2026/Coldwater-Rumble-Trail-Runs-2026', 'Coldwater Rumble Trail Runs 2026', { by: "Let's Wander Photography", account: 'lwp' } ) ],
	context,
} );
t( 'next year of a renamed race lands on the card name (history)', 1 === p.add.length && 'Coldwater Hundred' === p.add[ 0 ].race && 2026 === p.add[ 0 ].year );

p = planIngest( { discovered: [ ...discovered2025, g( 'McDowell Mountain Frenzy', 2026, 'https://aravaipa.smugmug.com/2026-Events/McDowell-Mountain-Frenzy-Mayhem', 'McDowell Mountain Frenzy Mayhem' ) ], context } );
t( 'a recurring folder follows last year\'s folder, not its matched name', 'Mayhem Night Trail' === p.add[ 0 ]?.race );

p = planIngest( { discovered: [ ...discovered2025, g( 'McDowell Mountain Frenzy', 2026, 'https://aravaipa.smugmug.com/2026-Events/McDowell-Mountain-Frenzy', 'McDowell Mountain Frenzy 2026' ) ], context } );
t( 'split history still allows the name\'s own card', 'McDowell Mountain Frenzy' === p.add[ 0 ]?.race );

p = planIngest( { discovered: [ g( 'The Bear Chase', 2026, 'https://aravaipa.smugmug.com/2026-Events/The-Bear-Chase', 'The Bear Chase' ) ], context: { ...context, names: [ ...context.names, { name: 'The Bear Chase', key: 'bear chase' } ] } } );
t( 'the Bear Chase 2026 miss is added under its card', 1 === p.add.length && 'The Bear Chase' === p.add[ 0 ].race && 'Aravaipa Photo Gallery' === p.add[ 0 ].by );

p = planIngest( { discovered: [ g( 'Big Pine', 2026, 'https://aravaipa.smugmug.com/2026-Events/Big-Pine', 'Big Pine' ) ], context } );
t( 'a short name with one containing card resolves to it', 'Flagstaff Extreme Big Pine Trail Runs' === p.add[ 0 ]?.race );

p = planIngest( { discovered: [ g( 'Aspen Backcountry', 2026, 'https://aravaipa.smugmug.com/2026-Events/Aspen-Backcountry', 'Aspen Backcountry', { tied: [ 'Aspen Backcountry Marathon' ] } ) ], context } );
t( 'a tie between two names for one card is not a tie', 'Aspen Backcountry' === p.add[ 0 ]?.race );

p = planIngest( { discovered: [ g( 'Flagstaff Sky Peaks', 2026, 'https://lwp.smugmug.com/2026/Flagstaff-Exreme-Big-Pines-2026', 'Flagstaff Exreme Big Pines 2026', { tied: [ 'Flagstaff Extreme Big Pine Trail Runs', 'Flagstaff 50 Endurance Runs' ] } ) ], context } );
t( 'a tie between two cards goes to review', 0 === p.add.length && /ambiguous/.test( p.review[ 0 ]?.why ) );

p = planIngest( { discovered: [ g( 'Devil After Dark', 2026, 'https://aravaipa.smugmug.com/2026-Events/Devil-After-Dark', 'Devil After Dark' ) ], context } );
t( 'a race with no card goes to review', 0 === p.add.length && /no card/.test( p.review[ 0 ]?.why ) );

p = planIngest( { discovered: [ g( 'Sinister Night Runs', 2026, 'https://aravaipa.smugmug.com/2026-Events/Sinister-Night-Runs-2', 'Sinister Night Runs 2' ) ], context } );
t( 'second gallery, same card, year and photographer goes to review', 0 === p.add.length && /already has/.test( p.review[ 0 ]?.why ) );

p = planIngest( {
	discovered: [
		g( 'The Bear Chase', 2026, 'https://aravaipa.smugmug.com/2026-Events/A', 'The Bear Chase A' ),
		g( 'The Bear Chase', 2026, 'https://aravaipa.smugmug.com/2026-Events/B', 'The Bear Chase B' ),
	],
	context: { ...context, names: [ { name: 'The Bear Chase', key: 'bear chase' } ] },
} );
t( 'two new galleries for one card in one run go to review', 0 === p.add.length && 2 === p.review.length );

const many = Array.from( { length: 16 }, ( _, i ) => g( 'The Bear Chase', 2030 + i, `https://aravaipa.smugmug.com/x/${ i }`, `The Bear Chase ${ 2030 + i }` ) );
p = planIngest( { discovered: many, context: { ...context, names: [ { name: 'The Bear Chase', key: 'bear chase' } ] }, max: 15 } );
t( 'over the cap adds nothing and reviews everything', 0 === p.add.length && 16 === p.review.length && /over the cap/.test( p.review[ 0 ].why ) );
p = planIngest( { discovered: many.slice( 0, 15 ), context: { ...context, names: [ { name: 'The Bear Chase', key: 'bear chase' } ] }, max: 15 } );
t( 'at the cap adds them all', 15 === p.add.length );

p = planIngest( { discovered: [ discovered2025[ 0 ], discovered2025[ 0 ] ], context } );
t( 'a gallery listed twice is counted once', 1 === p.present.length );

p = planIngest( {
	discovered: [
		g( 'McDowell Mountain Frenzy', 2025, 'https://aravaipa.smugmug.com/2025-Events/McDowell-Mountain-Frenzy-Mayhem', 'McDowell Mountain Frenzy Mayhem' ),
		g( 'McDowell Mountain Frenzy', 2026, 'https://lwp.smugmug.com/2026/McDowell-Mt-Frenzy-2026', 'McDowell Mt Frenzy 2026', { by: "Let's Wander Photography", account: 'lwp' } ),
	],
	context,
} );
t( 'one odd folder in the history does not pull a race off its own card', 'McDowell Mountain Frenzy' === p.add[ 0 ]?.race );

console.log( `\n${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
