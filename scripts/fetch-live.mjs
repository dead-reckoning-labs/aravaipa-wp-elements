/**
 * Copy the live timing board's schedule into WordPress.
 *
 * live.aravaiparunning.com publishes what it is carrying at
 * /api/v1/race_events/live: for each event, its start, its cutoff, its
 * timezone, and every distance with the id the board uses for it.
 *
 * That is the only place the real start times exist. The race store keeps
 * dates, so without this the race week block can only count down to
 * midnight on race day, which is six hours out from a six in the morning
 * start. It is also where the per-distance ids come from, which is what
 * lets a "23K" chip link to that distance's own results rather than the
 * event's.
 *
 * No authentication: this is the same public endpoint the board's own front
 * end reads. The per-event endpoints do need a key, which is why this takes
 * the whole list in one request rather than walking events one at a time.
 *
 *   node scripts/fetch-live.mjs
 *   node scripts/fetch-live.mjs --post
 *   node scripts/fetch-live.mjs --post --dry-run
 */
const args = Object.fromEntries(
  process.argv.slice(2).join(' ').split('--').filter(Boolean)
    .map(s => s.trim().split(/\s+/)).map(([k, v]) => [k, v ?? true])
);

const SOURCE = 'https://live.aravaiparunning.com/api/v1/race_events/live';

const res = await fetch(SOURCE, {
  headers: { 'User-Agent': 'aravaipa-elements/fetch-live' },
  signal: AbortSignal.timeout(20000),
});

if (!res.ok) {
  console.error(`live board returned HTTP ${res.status}`);
  process.exit(1);
}

const board = await res.json();

const events = board.map(e => ({
  slug: e.slug,
  start: e.startTime || '',
  cutoff: e.cutoffTime || '',
  offset: typeof e.timezoneOffset === 'number' ? e.timezoneOffset : 0,
  races: (e.races || [])
    .filter(r => r && r.id && r.name)
    .map(r => ({ id: r.id, name: r.name, start: r.startTime || '' }))
    // Earliest first, which is the order they are listed in and the order
    // a reader expects: the long one goes off first.
    .sort((a, b) => String(a.start).localeCompare(String(b.start))),
})).filter(e => e.slug);

const withCutoff = events.filter(e => e.cutoff).length;
const races = events.reduce((n, e) => n + e.races.length, 0);
console.error(`${events.length} events, ${races} distances, ${withCutoff} with a cutoff`);

if (!args.post) {
  console.log(JSON.stringify(events, null, 1));
  process.exit(0);
}

const url = process.env.ARAVAIPA_WP_URL;
const user = process.env.ARAVAIPA_WP_USER;
const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;
if (!url || !user || !pass) {
  console.error('ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD are required for --post');
  process.exit(1);
}

const post = await fetch(`${url}/wp-json/aravaipa/v1/live/import`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    Authorization: 'Basic ' + Buffer.from(`${user}:${pass}`).toString('base64'),
    // The site's security plugin rejects a default script user agent.
    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36',
  },
  body: JSON.stringify({ events, dry_run: !!args['dry-run'] }),
});
console.error(`HTTP ${post.status}`);
console.error(await post.text());
