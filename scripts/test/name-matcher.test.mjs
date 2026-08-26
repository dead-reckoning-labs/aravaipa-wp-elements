/**
 * Regression coverage for the race-name matcher in scripts/fetch-races.mjs.
 *
 * Not run through the PHP harnesses since this logic lives in the Node
 * generator, not the plugin. The implementation here is a deliberate copy
 * rather than an import, because fetch-races.mjs is a script, not a module
 * with exports; keeping the two in sync is the cost of that, paid by running
 * this test whenever nameOverlap() changes.
 *
 *   bun scripts/test/name-matcher.test.mjs
 */
const MONTHS = {january:1,february:2,march:3,april:4,may:5,june:6,july:7,august:8,september:9,october:10,november:11,december:12};

function normalizeRaceName(s) {
  return s.toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .split(/\s+/)
    .filter(w => w && !['the','trail','trails','run','runs','race','races','night','ultra','ultras','marathon','presented','by','hoka','javelina'].includes(w));
}
function nameOverlap(a, b) {
  const wa = new Set(normalizeRaceName(a)), wb = new Set(normalizeRaceName(b));
  if (!wa.size || !wb.size) return 0;
  let hits = 0;
  for (const w of wa) if (wb.has(w)) hits++;
  if (hits === 1 && Math.min(wa.size, wb.size) > 1) return 0;
  return hits / Math.max(wa.size, wb.size);
}

let pass = 0, fail = 0;
function t(name, cond) { if (cond) { pass++; console.log('  ok   ' + name); } else { fail++; console.log('  FAIL ' + name); } }

// The two real collisions found tonight, both live races on the same site
// with the same generic shared word.
t('Javelina Jallucinations does not match Javelina Jangover',
  nameOverlap('Javelina Jallucinations', 'Javelina Jangover Night Runs') === 0);
t('Across The Globe does not match Across the Years',
  nameOverlap('Across The Globe', 'Across the Years') === 0);

// The legitimate single-shared-word matches that must keep working: one
// side collapses to exactly the shared word, a real subset relationship.
t('Cocodona 250 still matches Cocodona', nameOverlap('Cocodona 250', 'Cocodona') > 0);
t('Desert Solstice Track Invitational still matches Desert Solstice',
  nameOverlap('Desert Solstice Track Invitational', 'Desert Solstice') > 0);

// Multi-word overlaps unaffected by the single-word guard.
t('Mogollon Monster Trail Runs still matches Mogollon Monster 100',
  nameOverlap('Mogollon Monster Trail Runs', 'Mogollon Monster 100') > 0);
t('unrelated names score zero',
  nameOverlap('Rock Hawk', 'Snow Mountain Ranch') === 0);

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail > 0 ? 1 : 0);
