/**
 * Regression coverage for the inclusion filter in scripts/discover-smugmug.mjs.
 *
 * The whole point of that script is that it walks SmugMug accounts which
 * are not Aravaipa-only, so every rule here exists to stop somebody else's
 * race being published as Aravaipa's. Each case below is one that actually
 * came back wrong during a real run against the live accounts, which is
 * why they are worth holding on to.
 *
 * A deliberate copy rather than an import, same as name-matcher.test.mjs
 * beside it: discover-smugmug.mjs is a script, not a module with exports.
 *
 *   bun scripts/test/smugmug-filter.test.mjs
 */

const GENERIC = new Set(
  ('mountain mountains canyon valley ridge creek desert lake park springs river peak peaks ' +
   'trail trails endurance festival classic series marathon ultras ultra night runs run race races ' +
   'events event photos photo gallery half').split(' ')
);

const CONTAINER_WORDS = new Set(
  'events event races race photography photos photo running runs run gallery galleries archive'.split(' ')
);

const IS_RIDE = /\b(rides?|bike|mtb)\b/i;

const normalise = s =>
  String(s)
    .toLowerCase()
    .replace(/\b2\b/g, ' to ')
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const phrasesFor = race => {
  const words = normalise(race).split(' ');
  const out = [];
  for (let n = words.length; n > 0; n--) {
    const phrase = words.slice(0, n).join(' ');
    if (phrase.length < 5) continue;
    if (n === 1 && (phrase.length < 7 || GENERIC.has(phrase))) continue;
    out.push(phrase);
  }
  return out;
};

const isContainer = name => {
  const words = normalise(name).replace(/\b20[12]\d\b/g, ' ').split(' ').filter(Boolean);
  return words.length === 0 || words.every(w => CONTAINER_WORDS.has(w));
};

// A representative slice of the real race list, including the ones that
// caused trouble.
const RACES = [
  'Coldwater Rumble', 'Copper Corridor', 'Bobcat Trail Runs', 'Thrasher Night Runs',
  'Royal Gorge Groove', 'Mountain to Fountain', 'Tushars Mountain Runs',
  'Mountain Ridge Trail Race', 'Rock River Canyon', 'Two Hearted', 'Whiskey Basin Trail Runs',
  'Hypnosis Night Runs', 'Blackout Night Runs', 'Adrenaline Night Runs',
];
const RACE_PHRASES = RACES.map(race => ({ race, phrases: phrasesFor(race) }));

const raceFor = name => {
  const hay = ` ${normalise(name)} `;
  let best = null;
  let bestLen = 0;
  for (const { race, phrases } of RACE_PHRASES) {
    for (const phrase of phrases) {
      if (phrase.length <= bestLen) continue;
      if (hay.includes(` ${phrase} `)) { best = race; bestLen = phrase.length; }
    }
  }
  return best;
};

let pass = 0, fail = 0;
const t = (name, cond) => { if (cond) { pass++; console.log('  ok   ' + name); } else { fail++; console.log('  FAIL ' + name); } };

console.log('\nfolders that are Aravaipa races:');
t('an exact race folder',            'Coldwater Rumble' === raceFor('Coldwater Rumble'));
t('with a year appended',            'Coldwater Rumble' === raceFor('Coldwater-Rumble-Trail-Runs-2026'));
t('with the distance appended',      'Whiskey Basin Trail Runs' === raceFor('Whiskey Basin Trail Runs 2023'));
// "Mountain 2 Fountain" is how Let's Wander writes it.
t('a numeral standing in for a word', 'Mountain to Fountain' === raceFor('Mountain-2-Fountain-2026'));
// "Rock River Canyon 50K & 27K Trail Race" is Spring Velvet's whole folder name.
t('an ampersand and distances',      'Rock River Canyon' === raceFor('Rock River Canyon 50K & 27K Trail Race'));
t('shouty caps',                     'Two Hearted' === raceFor('TWO-HEARTED-TRAIL-RUN'));

console.log('\nfolders that are somebody else\'s race:');
// Every one of these is real, off the live accounts.
t('another promoter, no shared word', null === raceFor('Way Too Cool Trail Runs 2026'));
t('another promoter, generic words',  null === raceFor('Lake Sonoma 100k, 50k & Trail Sisters Half Marathon'));
t('a half marathon somewhere else',   null === raceFor('18th Annual Parkway Half Marathon 2026'));
// This one matched Copper Corridor on the bare word "copper" when a single
// word only had to be six characters. It is why the rule is seven.
t('a six-letter word is not a race',  null === raceFor('Copper Mtn Cross Country Meet 10-10-2024'));
// "mountain" alone happily matched Mountain Ridge Trail Race.
t('a generic word is not a race',     'Tushars Mountain Runs' === raceFor('Tushars Mountain Runs 2021'));
t('and not on its own either',        null === raceFor('Some Mountain Thing'));

console.log('\ncontainers, which are descended into:');
t('a bare year',                      isContainer('2026'));
t('a year and a noun',                isContainer('2026 Events'));
t('a year and two nouns',             isContainer('2025 Race Photography'));
t('the noun first',                   isContainer('Race Photography 2019'));

console.log('\nnon-containers, which are not:');
// Let's Wander's "Oregon 200 Miler 2025" holds a gallery called "Brian
// Thrasher", which matched Thrasher Night Runs on the surname. Refusing to
// descend into another promoter's event folder is what stops that.
t('another promoter\'s event folder',  !isContainer('Oregon 200 Miler 2025'));
t('a wedding',                        !isContainer('Supercynski Farm'));
t('a trip',                           !isContainer('North Shore Trip July 2025'));
t('a named race with a year',         !isContainer('SISU SKI FEST 2026'));

console.log('\nAravaipa Rides, which belongs on aravaiparides.com:');
t('a rides folder',                   IS_RIDE.test('Sinister Night Rides 2026'));
t('singular',                         IS_RIDE.test('Adrenaline Night Ride'));
t('spelled bike',                     IS_RIDE.test('Royal Gorge Bike'));
t('spelled mtb',                      IS_RIDE.test('Prickly Pedal MTB 2026'));
// The run at the same venue must still come through.
t('the run at the same venue is not', !IS_RIDE.test('Sinister Night Runs 2026'));
t('nor is a normal race',             !IS_RIDE.test('Coldwater Rumble 2026'));

console.log(`\n${pass} passed, ${fail} failed\n`);
process.exit(fail > 0 ? 1 : 0);
