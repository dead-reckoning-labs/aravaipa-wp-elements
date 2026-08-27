/**
 * Find which Aravaipa races have sold out, and where their waitlist lives.
 *
 * UltraSignup knows this and publishes it properly, but not anywhere a person
 * reading the page would notice. The visible registration-status line is no
 * help at all: on Javelina Jundred, an event that was sold out with a waitlist
 * open, it read "Registration closes: Mon, Oct 5 @ 11:59PM MT" and nothing
 * else. What it does carry is a JSON-LD Offer with
 * `"availability":"https://schema.org/SoldOut"`, plus a separate anchor with
 * the id `ContentPlaceHolder1_EventInfoThin1_hlWaitlist` pointing at the
 * waitlist itself. Both are server-rendered, so plain HTML is enough and this
 * needs no browser, unlike its sibling fetch-races.mjs.
 *
 * Two things were tried first and do not work, recorded so nobody spends the
 * afternoon rediscovering them:
 *
 *   - The group API (events.svc/groupevents?gid=7) has an `OpenRegistration`
 *     boolean that looks exactly right and is dead. It returns false for all
 *     120 events, including races taking entries that day.
 *   - The visible status label distinguishes open / closed / not-yet-open /
 *     already-run, which is useful, but never mentions selling out.
 *
 * Registration links come from the same race rows the rest of the plugin
 * uses, so this only ever asks about races Aravaipa actually lists. Races
 * registering somewhere other than UltraSignup (RunSignUp, RaceRoster, the
 * in-house system) are skipped rather than guessed at.
 *
 * Output is a JSON object of race name => waitlist URL, which is exactly the
 * shape /wp-json/aravaipa/v1/races/waitlists takes.
 *
 *   node scripts/fetch-waitlists.mjs                  # print the JSON
 *   node scripts/fetch-waitlists.mjs --post           # write it to the site
 *   node scripts/fetch-waitlists.mjs --post --dry-run # report, write nothing
 *
 * --post requires ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and
 * ARAVAIPA_WP_APP_PASSWORD (an Application Password on a plain Editor-role
 * user, same credential and same reasoning as fetch-races.mjs --post).
 */
import { readFile } from 'node:fs/promises';

const args = Object.fromEntries(
  process.argv.slice(2).join(' ').split('--').filter(Boolean)
    .map(s => s.trim().split(/\s+/)).map(([k, v]) => [k, v ?? true])
);

const ROWS = args.rows || new URL('../race-rows-2026.txt', import.meta.url).pathname;
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

const raw = await readFile(ROWS, 'utf8');

// Name is the first cell and the key the store is written under, so it has to
// match the row exactly. The register link is found by content rather than by
// position, because the row format puts a variable number of distance cells
// before the fixed tail.
const races = raw.split('\n').filter(l => l.trim()).map(line => {
  const cells = line.split('|').map(c => c.trim());
  return { name: cells[0], url: cells.find(c => c.includes('ultrasignup.com/register.aspx')) };
}).filter(r => r.name && r.url);

console.error(`${races.length} race(s) registering on UltraSignup, of ${raw.split('\n').filter(l => l.trim()).length} total.`);

const AVAIL = /"availability"\s*:\s*"([^"]+)"/;
const WAIT = /href="([^"]*)"[^>]*id="ContentPlaceHolder1_EventInfoThin1_hlWaitlist"/;

const waitlists = {};
let soldOutWithoutLink = 0;
let failed = 0;

for (const race of races) {
  let body;
  try {
    // redirect: follow matters. A row's link is often the "dtid" form, which
    // 302s to whichever "did" is current; the sold-out markup is only on the
    // page it lands on.
    const res = await fetch(race.url, { headers: { 'User-Agent': UA }, redirect: 'follow' });
    if (!res.ok) { console.error(`  ${race.name}: HTTP ${res.status}`); failed++; continue; }
    body = await res.text();
  } catch (err) {
    console.error(`  ${race.name}: ${err.message}`);
    failed++;
    continue;
  }

  const avail = AVAIL.exec(body);
  if (!avail || !avail[1].endsWith('SoldOut')) continue;

  const wait = WAIT.exec(body);
  if (!wait) {
    // Sold out with no waitlist offered is a real state, not an error: the
    // race is simply full. Nothing to store, since the button only exists to
    // point at a waitlist, but worth counting so a silent scrape failure
    // cannot hide behind it.
    console.error(`  ${race.name}: sold out, no waitlist link`);
    soldOutWithoutLink++;
    continue;
  }

  const href = wait[1].startsWith('/') ? `https://ultrasignup.com${wait[1]}` : wait[1];
  waitlists[race.name] = href;
  console.error(`  ${race.name}: SOLD OUT -> ${href}`);

  // UltraSignup is doing us a favour serving this at all; no reason to hammer.
  await new Promise(r => setTimeout(r, 400));
}

console.error(`\n${Object.keys(waitlists).length} sold out with a waitlist, ${soldOutWithoutLink} sold out without one, ${failed} unreachable.`);

// A scrape where most requests failed produces the same empty-looking result
// as a calendar with nothing sold out. Those mean opposite things, and the
// write below replaces the whole store, so refuse rather than clear it out on
// the strength of a broken run.
if (failed > races.length / 4) {
  console.error(`\n${failed} of ${races.length} races were unreachable. Refusing to report a result from a run that mostly failed.`);
  process.exit(1);
}

const output = JSON.stringify(waitlists, null, 2);

if (!args.post) {
  console.log(output);
  process.exit(0);
}

const url = process.env.ARAVAIPA_WP_URL;
const user = process.env.ARAVAIPA_WP_USER;
const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;

if (!url || !user || !pass) {
  console.error('\n--post given but ARAVAIPA_WP_URL / ARAVAIPA_WP_USER / ARAVAIPA_WP_APP_PASSWORD is not set. Not posting anything.');
  process.exit(1);
}

const body = new URLSearchParams({ waitlists: output });
if (args['dry-run']) body.set('dry_run', 'true');

console.error(`\nPOSTing to ${url}/wp-json/aravaipa/v1/races/waitlists${args['dry-run'] ? ' (dry run)' : ''}...`);

let res;
try {
  res = await fetch(`${url}/wp-json/aravaipa/v1/races/waitlists`, {
    method: 'POST',
    headers: {
      'Authorization': 'Basic ' + Buffer.from(`${user}:${pass}`).toString('base64'),
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  });
} catch (err) {
  console.error(`request failed: ${err.message}`);
  process.exit(1);
}

const json = await res.json().catch(() => null);
console.error(JSON.stringify(json, null, 2));

if (!res.ok || !json || 'refused' === json.status) {
  console.error(`\nwaitlist write did not apply (HTTP ${res.status}).`);
  process.exit(1);
}
