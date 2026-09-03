/**
 * Turn Momentum's own event history into rows for the Aravaipa Results element.
 *
 * The real fix for the 2023-on gap. scripts/fetch-results.mjs read
 * /results-YYYY/, which is now this element rendering from the store, so it had
 * been reading its own output; the store held two 2026 races against a season
 * that had run dozens. The race calendar (GET /aravaipa/v1/races) turned out to
 * be forward-only and held those same two. Momentum, the timing platform at
 * timing.aravaiparunning.com, is the one source that actually remembers: it has
 * scored every Aravaipa event since 2019 and its authenticated event list pages
 * back through all of them.
 *
 *   node scripts/fetch-results-from-timing.mjs [--year 2026]
 *   node scripts/fetch-results-from-timing.mjs --merge-with other.json --post
 *   node scripts/fetch-results-from-timing.mjs --merge-with other.json --post --dry-run
 *
 * Requires, in the environment:
 *   MOMENTUM_TIMING_URL   default https://timing.aravaiparunning.com
 *   MOMENTUM_TIMING_USER  operator login
 *   MOMENTUM_TIMING_PASS
 *   ARAVAIPA_WP_URL / ARAVAIPA_WP_USER / ARAVAIPA_WP_APP_PASSWORD  (for --post,
 *       and to read the race calendar for UltraSignup links)
 *
 * WHAT THIS DOES NOT DO: ingest every event Momentum timed. Momentum also
 * scores races Aravaipa only times or broadcasts, Western States, Hardrock,
 * Lake Sonoma, Chuckanut, which have never been on the Aravaipa results page
 * and do not belong on it. The filter is membership, not ownership: a timing
 * event is kept only if a race of that name has appeared in the results store
 * before, or is on the race calendar now. Cocodona passes (every prior year is
 * in the store); Western States does not (no year ever was). Anything kept out
 * this way is printed, so a genuinely new Aravaipa race that has never run
 * before is a line to eyeball rather than a silent drop.
 *
 * READ THIS BEFORE --post. The import route replaces the store outright, so
 * --post refuses without --merge-with, the same guard the other two scrapers
 * enforce. Timing rows win for the year they cover: they are the non-circular
 * read that this whole script exists to substitute for the old one.
 */
import { readFileSync } from 'node:fs';

const args = Object.fromEntries(
  process.argv.slice(2).flatMap((a, i, all) =>
    a.startsWith('--') ? [[a.slice(2), all[i + 1]?.startsWith('--') === false ? all[i + 1] : true]] : []
  )
);

const TIMING = (process.env.MOMENTUM_TIMING_URL || 'https://timing.aravaiparunning.com').replace(/\/$/, '');
const TUSER = process.env.MOMENTUM_TIMING_USER;
const TPASS = process.env.MOMENTUM_TIMING_PASS;
if (!TUSER || !TPASS) {
  console.error('MOMENTUM_TIMING_USER and MOMENTUM_TIMING_PASS are required.');
  process.exit(1);
}

const WP = process.env.ARAVAIPA_WP_URL;
const WPUSER = process.env.ARAVAIPA_WP_USER;
const WPPASS = process.env.ARAVAIPA_WP_APP_PASSWORD;

const year = String(args.year || new Date().getUTCFullYear());
const today = new Date().toISOString().slice(0, 10);

const UA =
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

// The store's own race-key normalization, ported from arv_results_race_key in
// includes/results-store.php verbatim so a name matches here exactly the way
// the element groups it: same strip list, same distance and plural handling,
// same "too short, fall back to the light form" guard. If that function
// changes, this has to change with it.
function raceKey(name) {
  const light = String(name).replace(/[^a-z0-9]+/gi, ' ').trim().toLowerCase().replace(/\s+/g, ' ');
  let stripped = light.replace(/\b(trail|trails|run|runs|race|races|ultra|ultras|endurance|the|presented by.*)\b/gi, ' ');
  stripped = stripped.replace(/\b\d+\s*(k|km|m|mi|mile|miler|hour|hr)?\b/gi, ' ').replace(/\s+/g, ' ').trim();
  stripped = stripped
    .split(' ')
    .filter(Boolean)
    .map(w => (w.length > 4 && w.endsWith('s') ? w.slice(0, -1) : w))
    .join(' ');
  return stripped.length < 4 ? light : stripped;
}

function displayFor(iso) {
  const d = new Date(`${iso}T00:00:00Z`);
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  return `${months[d.getUTCMonth()]} ${d.getUTCDate()}`;
}

async function timingToken() {
  const res = await fetch(`${TIMING}/api/v1/authenticate`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-type': 'application/json', 'User-Agent': UA },
    body: JSON.stringify({ u: TUSER, p: TPASS }),
  });
  if (!res.ok) throw new Error(`timing authenticate ${res.status}`);
  const body = await res.json();
  if (!body.token) throw new Error('timing authenticate returned no token');
  return body.token;
}

// Walk the offset-paginated event list to the end. The list is the same 20-row
// window whether or not you authenticate; ?o= is the only thing that moves it,
// and it stops returning rows past the real total, which is the loop's exit.
async function allEvents(token) {
  const seen = new Map();
  for (let offset = 0; offset < 5000; ) {
    const res = await fetch(`${TIMING}/api/v1/race_events?o=${offset}`, {
      headers: { Accept: 'application/json', Authentication: `Bearer ${token}`, 'User-Agent': UA },
    });
    if (!res.ok) throw new Error(`race_events?o=${offset} -> ${res.status}`);
    const body = await res.json();
    const page = Array.isArray(body) ? body : body.race_events || body.data || [];
    if (page.length === 0) break;
    for (const e of page) if (e.id) seen.set(e.id, e);
    offset += page.length;
  }
  return [...seen.values()];
}

// The set of race keys that belong on the Aravaipa results page: everything the
// store has ever carried, plus everything on the calendar now. Read from the
// merge file when posting (it is a full store dump) and from the live store
// otherwise, so a plain run and a --post run judge membership the same way.
async function membershipKeys(existing) {
  const keys = new Set(existing.map(r => raceKey(r.name)));

  if (WP && WPUSER && WPPASS) {
    try {
      const auth = 'Basic ' + Buffer.from(`${WPUSER}:${WPPASS}`).toString('base64');
      const res = await fetch(`${WP}/wp-json/aravaipa/v1/races`, {
        headers: { Accept: 'application/json', Authorization: auth },
      });
      if (res.ok) {
        const body = await res.json();
        for (const r of body.races || []) keys.add(raceKey(r.name));
      }
    } catch {
      // Calendar is a bonus signal, not required: the store history alone is
      // enough to keep Western States out and let Cocodona in.
    }
  }
  return keys;
}

// Whether a timing event's race key belongs, allowing for the descriptor that
// drifts between the timing system and the store: Momentum says "Aspen
// Backcountry Marathon" where the store says "Aspen Backcountry", and
// "Chocorua" where the store says "Chocorua Mountain Race". A word-set subset
// in either direction bridges both. Exact-only missed those two real Aravaipa
// races; a looser fuzzy match would risk letting Western States in. Subset is
// the safe middle: every word of one name is present in the other, so the two
// are describing the same race with one carrying an extra word, and an
// unrelated outsider (no shared word run) never matches. Verified against the
// full 2026 field that Western States, Hardrock, Lake Sonoma and Chuckanut all
// stay out under this rule.
function belongs(timingKey, memberKeys) {
  if (memberKeys.has(timingKey)) return true;
  const tw = new Set(timingKey.split(' ').filter(Boolean));
  if (tw.size === 0) return false;
  for (const mk of memberKeys) {
    const mw = new Set(mk.split(' ').filter(Boolean));
    if (mw.size === 0) continue;
    const subset = (a, b) => [...a].every(w => b.has(w));
    if (subset(mw, tw) || subset(tw, mw)) return true;
  }
  return false;
}

// UltraSignup links, keyed by race, from the calendar. Only current races carry
// one, so this fills what it can and leaves the rest to the element, which
// derives UltraRunning on its own and shows a live-only row cleanly.
async function ultrasignupByKey() {
  const map = new Map();
  if (!WP || !WPUSER || !WPPASS) return map;
  try {
    const auth = 'Basic ' + Buffer.from(`${WPUSER}:${WPPASS}`).toString('base64');
    const res = await fetch(`${WP}/wp-json/aravaipa/v1/races`, {
      headers: { Accept: 'application/json', Authorization: auth },
    });
    if (!res.ok) return map;
    const body = await res.json();
    for (const r of body.races || []) {
      if (r.results_url && r.results_url.trim()) map.set(raceKey(r.name), r.results_url.trim());
    }
  } catch {
    /* optional */
  }
  return map;
}

async function main() {
  const existing = args['merge-with'] ? JSON.parse(readFileSync(args['merge-with'], 'utf8')) : [];

  const token = await timingToken();
  const events = await allEvents(token);

  const ran = events.filter(e => {
    const iso = (e.startTime || '').slice(0, 10);
    return iso.startsWith(year) && iso <= today;
  });

  const member = await membershipKeys(existing);
  const usByKey = await ultrasignupByKey();

  const kept = [];
  const held = [];

  for (const e of ran) {
    const key = raceKey(e.name);
    const row = {
      name: e.name,
      iso: (e.startTime || '').slice(0, 10),
      // Momentum's slug carries the distance/misspelling quirks ("stunner_night_2026",
      // "damn_good_run-2026"); the name field is the clean one, so the live URL is
      // the only thing built from the slug.
      live: `https://live.aravaiparunning.com/#/${e.slug}`,
      ultrasignup: usByKey.get(key) || '',
      ultrarunning: '',
      archive: [],
    };
    row.display = displayFor(row.iso);
    (belongs(key, member) ? kept : held).push(row);
  }

  kept.sort((a, b) => (a.iso === b.iso ? a.name.localeCompare(b.name) : a.iso < b.iso ? 1 : -1));

  if (held.length) {
    console.error(`\nheld back ${held.length} timed event(s) with no prior results-store or calendar match (Aravaipa times these but has never listed them, or they are genuinely new):`);
    for (const r of held.sort((a, b) => a.iso.localeCompare(b.iso))) {
      console.error(`  ${r.iso}  ${r.name}`);
    }
    console.error('');
  }

  if (!args.post) {
    console.log(JSON.stringify(kept, null, 1));
    console.error(`${year}: ${kept.length} races kept, ${held.length} held, of ${ran.length} timed and run`);
    process.exit(0);
  }

  if (!args['merge-with']) {
    console.error('--post needs --merge-with <file>, a full dump of the current store.');
    console.error('The import route replaces the store, so posting one year alone deletes the rest:');
    console.error('  node scripts/backfill-results.mjs > older.json   # 2008-2022');
    console.error('  # plus the current store; simplest is to dump what is live and merge onto that.');
    process.exit(1);
  }

  // Timing owns the whole target year, so replace every existing row dated in
  // it rather than dedup row by row. Dedup by name would have kept both the
  // store's "Rock Hawk Trail Races" and timing's "Rock Hawk" for the same day,
  // a same-date duplicate under one race group, and left the store's wrong live
  // link (it pointed Rock Hawk 2026 at the jackrabbit_jubilee board) in place
  // beside the correct one. Everything outside the year carries through
  // untouched. Safe because every Aravaipa race is Momentum-timed, so timing's
  // set for the year is the complete set; the only existing rows this drops are
  // the two the circular scraper had left, which is the point.
  const carried = existing.filter(r => !(r.iso || '').startsWith(year));
  const union = [...kept, ...carried].sort((a, b) =>
    a.iso === b.iso ? a.name.localeCompare(b.name) : a.iso < b.iso ? 1 : -1
  );

  console.error(`posting ${union.length} rows: ${kept.length} from the ${year} timing history, ${carried.length} carried through, ${existing.length - carried.length} ${year} row(s) replaced`);

  const auth = 'Basic ' + Buffer.from(`${WPUSER}:${WPPASS}`).toString('base64');
  const res = await fetch(`${WP}/wp-json/aravaipa/v1/results/import`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: auth },
    body: JSON.stringify({ rows: union, dry_run: !!args['dry-run'] }),
  });
  console.error(`HTTP ${res.status}`);
  console.error(await res.text());
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
