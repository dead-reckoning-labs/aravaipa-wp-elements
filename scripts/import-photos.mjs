#!/usr/bin/env node
/**
 * Seed the photo-gallery store from the site's own photos-YYYY pages.
 *
 * Those pages are the only record of which photographer shot which race:
 * four years of hand-built three-column rows, race name in the first
 * column and one or two gallery links in the others. Nothing else on the
 * site knows it, so this reads it back out of the pages rather than
 * inventing a source.
 *
 * Deliberately a one-time-shaped importer rather than a recurring scraper.
 * Once the store is seeded, the Photos element replaces those pages, and
 * new galleries should be added to the store, not to a hand-built page for
 * this to re-read. It is committed and idempotent all the same, for the
 * same reason every other script here is: re-running it after a WordPress
 * restore should not be an archaeology exercise.
 *
 *   node scripts/import-photos.mjs                 # print what it found
 *   node scripts/import-photos.mjs --post          # write it
 *   node scripts/import-photos.mjs --post --dry-run
 *
 * --post requires ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and
 * ARAVAIPA_WP_APP_PASSWORD, an Application Password on an Editor-role user.
 */

const YEARS = [2023, 2024, 2025, 2026];
const BASE = process.env.ARAVAIPA_WP_URL || 'https://www.aravaiparunning.com';

// The site's security plugin rejects a default script user agent.
const UA =
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const args = Object.fromEntries(
  process.argv.slice(2).map(a => (a.startsWith('--') ? [a.slice(2), true] : [a, true]))
);

// Aravaipa's own site furniture, which sits in the header and footer of
// every one of these pages and is not a gallery: the social row, the Square
// shop, and the Aravaipa Rides site.
const NOT_A_GALLERY =
  /(facebook|twitter|x\.com|instagram|youtube|tiktok|linkedin|strava)\.|square\.site|aravaiparides\.com/i;

/**
 * A photographer's name reduced to what two spellings of it agree on.
 *
 * The same person is written three ways across four years of hand-built
 * pages: "Let's Wander Photography", "Let's Wander Productions" and
 * "Let's Wander Photo" are one photographer with 57 galleries between
 * them, and left alone they would be three entries in the filter. So the
 * trade words come off the end and what is left is the key.
 *
 * Only the display name is ever normalised away; the most common spelling
 * of a key wins and is what a card shows, so nobody's name gets rewritten
 * into something they do not call themselves.
 */
const photographerKey = name =>
  name
    .toLowerCase()
    .replace(/\(.*?\)/g, ' ')
    .replace(/\b(photo gallery|photography|photographie|productions|production|photos|photo|gallery|media|llc|inc)\b/g, ' ')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

const strip = s =>
  s
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&#0?39;/g, "'")
    .replace(/&apos;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const rows = [];

for (const year of YEARS) {
  const res = await fetch(`${BASE}/photos-${year}/`, { headers: { 'User-Agent': UA } });

  if (!res.ok) {
    console.error(`photos-${year}: HTTP ${res.status}, skipped`);
    continue;
  }

  // Scripts first: an inline script containing markup would otherwise be
  // parsed as if it were the page's own rows.
  const html = (await res.text()).replace(/<script[\s\S]*?<\/script>/gi, '');

  // Each row is one x-container holding three x-column divs. Split on the
  // container boundary rather than trying to match nested divs, which is
  // not something a regex can do and not something worth a DOM parser for
  // a page shape that is about to stop existing.
  const containers = html.split(/<div id="" class="x-container max width/).slice(1);
  let found = 0;

  for (const block of containers) {
    const name = block.match(/<div id="" class="x-text"[^>]*>\s*<p>([\s\S]*?)<\/p>/);
    if (!name) continue;

    const race = strip(name[1]);
    if (!race) continue;

    for (const m of block.matchAll(/<a href="([^"]+)"[^>]*>([\s\S]*?)<\/a>/g)) {
      const url = m[1].replace(/&amp;/g, '&');
      const by = strip(m[2]);

      if (!by || !/^https?:\/\//i.test(url)) continue;

      // The page's own nav and share links live in the same markup, and
      // the footer's social row lands inside the last container the split
      // above produces. Excluded by name rather than by position: the
      // footer is not part of the row it happens to trail.
      if (/aravaiparunning\.com/i.test(url)) continue;
      if (NOT_A_GALLERY.test(url)) continue;

      rows.push({ race, year, by, url });
      found++;
    }
  }

  console.error(`photos-${year}: ${found} galleries`);
}

// Pick one spelling per photographer: the one they are written as most
// often across the four years, ties broken by the longer name since that
// is the one carrying the full business name rather than an abbreviation.
const spellings = new Map();
for (const r of rows) {
  const key = photographerKey(r.by);
  if (!key) continue;
  if (!spellings.has(key)) spellings.set(key, new Map());
  const seen = spellings.get(key);
  seen.set(r.by, (seen.get(r.by) || 0) + 1);
}

const canonical = new Map();
for (const [key, seen] of spellings) {
  const best = [...seen].sort((a, b) => b[1] - a[1] || b[0].length - a[0].length)[0][0];
  canonical.set(key, best);
}

for (const r of rows) {
  const key = photographerKey(r.by);
  if (key && canonical.has(key)) r.by = canonical.get(key);
}

const byName = new Map();
for (const r of rows) byName.set(r.by, (byName.get(r.by) || 0) + 1);

console.error(`\n${rows.length} galleries, ${byName.size} photographers`);
for (const [name, n] of [...byName].sort((a, b) => b[1] - a[1])) {
  console.error(`  ${String(n).padStart(3)}  ${name}`);
}

if (!args.post) {
  console.log(JSON.stringify(rows, null, 1));
  process.exit(0);
}

const url = process.env.ARAVAIPA_WP_URL;
const user = process.env.ARAVAIPA_WP_USER;
const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;

if (!url || !user || !pass) {
  console.error('ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD are required for --post');
  process.exit(1);
}

const post = await fetch(`${url}/wp-json/aravaipa/v1/photos/import`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    Authorization: 'Basic ' + Buffer.from(`${user}:${pass}`).toString('base64'),
    'User-Agent': UA,
  },
  body: JSON.stringify({ rows, dry_run: !!args['dry-run'] }),
});

console.error(`HTTP ${post.status}`);
console.error(await post.text());
