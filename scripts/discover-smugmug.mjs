#!/usr/bin/env node
/**
 * Find Aravaipa race galleries on SmugMug, and only Aravaipa race galleries.
 *
 * The three SmugMug accounts this walks are not Aravaipa-only. Aravaipa's
 * own is clean ("2026 Events > Coldwater Rumble"), but the photographers
 * shoot for other people too: Spring Velvet's account carries Paavo Nurmi,
 * Escanaba Half Marathon, Queen City Half Marathon, a UK trip and a farm.
 * None of that belongs on aravaiparunning.com.
 *
 * So the rule here is match-or-reject, never include-by-default: a folder
 * is only ever accepted if its name matches a race Aravaipa actually puts
 * on. Everything rejected is printed with its reason, so the filter can be
 * audited rather than trusted, and a race that should have matched shows
 * up as a visible miss rather than a silent absence.
 *
 * Matching is the same shape as arv_films_race_for() in
 * includes/films-store.php, which reads the race off a film's own title:
 * longest matching phrase across every known race wins, and a single word
 * only counts if it is not generic landscape or race vocabulary. That rule
 * exists because "Tushars Mountain Runs 2021" happily matched "Mountain
 * Ridge Trail Race" on the bare word "mountain" until it was stopped.
 *
 *   node scripts/discover-smugmug.mjs --races races.txt        # report
 *   node scripts/discover-smugmug.mjs --races races.txt --json # emit rows
 *
 * Needs SMUGMUG_API_KEY. Read-only: it never writes to SmugMug, and it
 * does not write to WordPress either. Feed its --json into
 * scripts/import-photos.mjs territory deliberately by hand, so a bad match
 * run can never overwrite the store on its own.
 *
 * For new galleries, do not do that: scripts/ingest-photos.mjs runs the same
 * walk every night and appends only what is new, never replacing the store.
 */

import { readFileSync } from 'node:fs';
import { raceMatcher } from './lib/race-match.mjs';
import { ACCOUNTS, smugmugApi, walkAccount } from './lib/smugmug.mjs';

const KEY = process.env.SMUGMUG_API_KEY;

if (!KEY) {
  console.error('SMUGMUG_API_KEY is required');
  process.exit(1);
}

const args = Object.fromEntries(
  process.argv.slice(2).flatMap((a, i, all) =>
    a.startsWith('--') ? [[a.slice(2), all[i + 1]?.startsWith('--') === false ? all[i + 1] : true]] : []
  )
);

// ---------------------------------------------------------------------------
// The matcher lives in scripts/lib/race-match.mjs, shared with the Zenfolio
// walker, and the account walk (accounts, photo counts, the container rule,
// outermost-match-wins) lives in scripts/lib/smugmug.mjs, shared with the
// nightly ingest. Two copies would drift, and the drift would be the
// expensive kind: both would still accept and reject galleries, just not
// the same ones, so a stranger's race could be published as Aravaipa's on
// one path and not the other.
// ---------------------------------------------------------------------------

const raceNames = String(
  args.races ? readFileSync(args.races, 'utf8') : ''
)
  .split('\n')
  .map(s => s.trim())
  .filter(Boolean);

if (!raceNames.length) {
  console.error('--races <file> is required: one Aravaipa race name per line');
  process.exit(1);
}

const raceFor = raceMatcher(raceNames);
const match = name => {
  const race = raceFor(name);
  return race ? { race } : null;
};

const api = smugmugApi(KEY);
const accepted = [];
const rejected = [];

for (const account of ACCOUNTS) {
  try {
    const res = await walkAccount({ api, account, match });
    // Same row shape this script has always emitted.
    accepted.push(...res.accepted.map(({ race, year, by, url, photos }) => ({ race, year, by, url, photos })));
    rejected.push(...res.rejected);
    console.error(`${account.nick}: ${res.accepted.length} galleries matched an Aravaipa race`);
  } catch (e) {
    console.error(`${account.nick}: could not read the account, skipped`);
  }
}

console.error(`\n${accepted.length} accepted, ${rejected.length} rejected\n`);

// Every rejection, printed. The point of this script is that inclusion is
// never blind, which is only true if the exclusions are visible.
const byReason = new Map();
for (const r of rejected) {
  // Grouped on the whole reason, not on a "matched" prefix. That prefix
  // collapse predates there being a second reason starting with the same
  // word, and it silently filed 23 galleries rejected for holding no
  // photographs under "no year could be read", which is a different and
  // wrong claim about why they were skipped.
  const k = r.why;
  byReason.set(k, (byReason.get(k) || 0) + 1);
}
console.error('rejected, by reason:');
for (const [why, n] of byReason) console.error(`  ${String(n).padStart(4)}  ${why}`);

console.error('\na sample of what was rejected, to eyeball for false negatives:');
for (const r of rejected.slice(0, 25)) {
  console.error(`  [${r.account}] ${r.name}${r.parent ? `  (in ${r.parent})` : ''}`);
}

/**
 * Merge with what the store already holds, rather than replacing it.
 *
 * Discovery only knows about the three SmugMug accounts. The store also
 * holds 24 galleries on hosts it cannot see at all (PassGallery, Pixieset,
 * pic-time, Flickr, Google Photos, photographers' own sites), plus the
 * handful discovery deliberately declines to guess at: a folder called
 * "Bobcat", too short a word to be safe, and one called "Adrenaine Night
 * Runs", which is a typo nothing should be clever enough to match.
 *
 * Replacing would throw all of those away to gain the ones discovery
 * found. Merging keeps both, and is also the safer failure mode: a
 * discovery run that matches nothing leaves the site exactly as it was.
 */
if (args.merge) {
  const existing = JSON.parse(readFileSync(args.merge, 'utf8'));
  const slug = u => String(u).split('?')[0].replace(/^https?:\/\//, '').replace(/\/+$/, '').toLowerCase();
  const found = accepted.map(r => slug(r.url));

  // An old row is superseded when discovery found that same gallery, or
  // found the folder it lives inside: discovery targets the race folder,
  // while the old pages sometimes linked one album down.
  const kept = existing.filter(r => !found.some(f => slug(r.url) === f || slug(r.url).startsWith(f + '/')));

  console.error(`\nmerge: ${accepted.length} discovered + ${kept.length} kept from the store`);
  console.error(`       (${existing.length - kept.length} of ${existing.length} existing rows superseded by discovery)`);

  const merged = [...accepted, ...kept].sort((a, b) => b.year - a.year || a.race.localeCompare(b.race));

  console.error(`       ${merged.length} rows total`);

  if (args.json) console.log(JSON.stringify(merged, null, 1));
} else if (args.json) {
  console.log(JSON.stringify(accepted, null, 1));
}
