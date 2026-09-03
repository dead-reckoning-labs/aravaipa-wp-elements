#!/usr/bin/env node
/**
 * Read the pre-2023 results pages into rows for the results store.
 *
 * scripts/fetch-results.mjs reads 2023 and later, and says in its own header
 * why it stops there: the older pages are a different shape and bending one
 * parser around both would fit neither. This is that second parser.
 *
 * Two more formats, not one:
 *
 *   2020 to 2022  One line per race, the links inline and labelled
 *                 "Results", pointing at UltraSignup. No per-race live link.
 *
 *   2008 to 2019  Race name, date and venue, then one link per distance
 *                 pointing at a static file under /results/ on Aravaipa's own
 *                 site: "6 Day", "72 Hours", "Splits". Almost nothing points
 *                 at UltraSignup, which did not carry these races at the time.
 *
 * That last format is why this script exists at all rather than being a flag
 * on the other one. A row in the store holds one link of each kind, and a
 * 2016 race has up to five, one per distance, each a different file. So the
 * store gains an `archive` list of label/url pairs and these rows fill it.
 * Rows from 2023 on simply do not have one.
 *
 * Plain fetch and regex rather than the headless Chrome the newer scraper
 * drives. These pages predate the Cornerstone layouts that made the DOM the
 * only reliable reading of a page: they are ordinary markup and they parse.
 *
 *   node scripts/backfill-results.mjs                 # print what it found
 *   node scripts/backfill-results.mjs --year 2016     # one year
 *   node scripts/backfill-results.mjs --merge-with current.json --post
 *   node scripts/backfill-results.mjs --post --dry-run
 *
 * --post requires ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and
 * ARAVAIPA_WP_APP_PASSWORD, an Application Password on an Editor-role user.
 *
 * READ THIS BEFORE --post. The import route replaces the store outright; it
 * does not merge, whatever the request body says. This script covers 2008 to
 * 2022 and the other one covers 2023 on, so posting this alone deletes every
 * year the other one wrote. The route's own guard does not catch it: it
 * refuses a post that would drop more than a fifth of the rows, and 360 rows
 * replacing 195 is a gain by that measure while still destroying all four
 * current years.
 *
 * So --post refuses to run without --merge-with, pointed at a JSON dump of
 * what is already stored:
 *
 *   wp eval 'echo wp_json_encode(get_option("arv_race_results", array()));' > current.json
 *
 * Rows already in that file win on a name-and-date collision, since they came
 * from the richer format.
 */

import { readFileSync } from 'node:fs';

const args = Object.fromEntries(
  process.argv.slice(2).flatMap((a, i, all) =>
    a.startsWith('--') ? [[a.slice(2), all[i + 1]?.startsWith('--') === false ? all[i + 1] : true]] : []
  )
);

const BASE = process.env.ARAVAIPA_WP_URL || 'https://www.aravaiparunning.com';

// The site's security plugin rejects a default script user agent, the same
// way it does for scripts/import-photos.mjs.
const UA =
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

// The page slug changed twice. 2008 to 2019 sit under race-results-YYYY,
// with 2008 to 2010 sharing one page; 2020 onward are results-YYYY.
const PAGES = [
  ['race-results-2008-2010', [2008, 2009, 2010]],
  ...[2011, 2012, 2013, 2014, 2015, 2016, 2017, 2018, 2019].map(y => [`race-results-${y}`, [y]]),
  ...[2020, 2021, 2022].map(y => [`results-${y}`, [y]]),
];

const MONTHS = { january:1, february:2, march:3, april:4, may:5, june:6, july:7,
  august:8, september:9, october:10, november:11, december:12 };

// Numeric entities are decoded generically rather than one at a time.
// WordPress writes & as &#038; rather than &amp;, so a named-entity list
// alone leaves "Whiskey Man &#038; Woman" in a race name, and the next
// surprise would be a different number rather than a different word.
const strip = s =>
  s.replace(/<[^>]+>/g, '')
    .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(parseInt(n, 10)))
    .replace(/&ndash;/g, '-')
    .replace(/&rsquo;|&#x2019;/gi, "'")
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/\s+/g, ' ')
    .trim();

// "December 28, 2016 - January 3, 2017" and "December 10-11, 2016" both start
// on the first date named. The year is taken from the first four-digit run
// rather than the last: a race that crosses New Year is filed under the year
// it started, which is the year whose page it is listed on.
function isoFor(text) {
  const m = text.match(/([A-Za-z]+)\s+(\d{1,2})/);
  const y = text.match(/(20\d\d)/);
  if (!m || !y) return '';
  const mo = MONTHS[m[1].toLowerCase()];
  if (!mo) return '';
  return `${y[1]}-${String(mo).padStart(2, '0')}-${String(parseInt(m[2], 10)).padStart(2, '0')}`;
}

// The element groups by month and prints the year in the group heading, so a
// row only needs the part that varies inside that month.
const displayFor = text => text.replace(/,?\s*20\d\d\s*$/, '').replace(/\s*-\s*$/, '').trim();

// The ordinal suffix is the whole reason this is not \d{1,2}\b. Half the
// 2020 to 2022 listings write "September 3rd, 2022", and there is no word
// boundary between the 3 and the rd, so \b rejects exactly those rows while
// accepting "September 3, 2022" beside them. It cost 25 of 2022's 33 races
// before anything was written anywhere.
const DATE_RE = /^[A-Z][a-z]+\s+\d{1,2}(?:st|nd|rd|th)?\b.*20\d\d\s*$/;

// Every host these pages ever sent a reader to for a result, found by
// listing the outbound links on all thirteen of them rather than assuming.
// Fifteen years of racing predates settling on one timing provider: there is
// UltraSignup, RaceResult on three different numbered subdomains, RunSignup
// once, Aravaipa's own ultracast.tv and timing subdomain, and the static
// files under /results/ that predate all of it.
//
// Leaving RaceResult out cost Javelina Jundred 2021 its only result link,
// which is the kind of gap that looks like the race simply had none.
function isResultLink(href) {
  return /\/results\//.test(href)
    || /ultrasignup\.com\/results/.test(href)
    || /raceresult\.com/.test(href)
    || /runsignup\.com/.test(href)
    || /ultracast\.tv/.test(href)
    || /timing\.aravaiparunning/.test(href)
    || /live\.aravaiparunning/.test(href)
    || /ultrarunning\.com/.test(href);
}

// A page reduced to the two kinds of token that matter, in the order they
// appear. There is no element wrapping a single race on any of these pages,
// so document order is the only thing grouping a race with its links, and a
// date is what starts a new one.
function tokenize(html) {
  const main = html.match(/<main[\s\S]*?<\/main>/i)?.[0] || html;
  const body = main.replace(/<(script|style|nav|header|footer)[\s\S]*?<\/\1>/gi, ' ');

  const toks = [];
  const re = /<a[^>]+href="([^"]+)"[^>]*>([\s\S]*?)<\/a>|>([^<>]{2,160})</g;
  let m;
  while ((m = re.exec(body))) {
    if (m[1]) {
      const href = m[1];
      if (!isResultLink(href)) continue;
      const label = strip(m[2]);
      if (label) toks.push({ t: 'link', label, href });
    } else {
      const text = strip(m[3]);
      if (text) toks.push({ t: 'text', text });
    }
  }
  return toks;
}

// Thirteen years of hand-edited markup does not yield clean hrefs.
//
//   &amp; survives into the URL, because an href is HTML before it is a URL
//   and a RaceResult link carries two of them in its query string.
//
//   Spaces appear literally: "Silverton 1000 2016 Results 12 Hour.htm" is a
//   real file with a real link to it, and curl will not fetch it unencoded.
//
//   One link on the 2018 page never closes its quote, so a naive read of
//   href="..." swallows the rest of the tag and returns markup as a URL.
//   Anything still carrying < > or " after cleaning is that, and is dropped
//   rather than stored as a link that cannot work.
function cleanHref(href) {
  const decoded = href
    .replace(/&amp;/g, '&')
    .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(parseInt(n, 10)))
    .trim();

  if (/[<>"]/.test(decoded)) return '';

  // "2011ATYResults24h .htm" on the 2011 page is a typo that has been there
  // since 2011: the file is named without the space and the link has one, so
  // it has 404'd for as long as it has existed. Encoding it faithfully
  // preserves a broken link; dropping the space before the extension repairs
  // it. Only that position, so a genuine "Silverton 1000 2016 Results 12
  // Hour.htm" keeps every space it is supposed to have.
  const repaired = decoded.replace(/\s+(\.[A-Za-z0-9]{2,5})(\?|#|$)/, '$1$2');

  // Only the path, and only the parts that are not already encoded: a query
  // string that already reads %20 must not become %2520.
  return repaired.replace(/ /g, '%20');
}

function absolute(href) {
  const clean = cleanHref(href);
  if (!clean) return '';
  if (/^https?:\/\//i.test(clean)) return clean;
  return `${BASE}${clean.startsWith('/') ? '' : '/'}${clean}`;
}

// Walk the tokens once. Text that looks like a date closes the race being
// built and opens the next; the text immediately before a date is that
// race's name, and links after it belong to it until the next date.
function parsePage(html, years) {
  const toks = tokenize(html);
  const out = [];
  let current = null;
  let prevText = '';

  const close = () => {
    if (current && current.name && current.iso) out.push(current);
    current = null;
  };

  for (const tok of toks) {
    if (tok.t === 'text') {
      if (DATE_RE.test(tok.text)) {
        const iso = isoFor(tok.text);
        // Only dates in a year this page is meant to cover. Pages carry
        // stray dates in their furniture, and 2008-2010 covers three.
        if (iso && years.includes(parseInt(iso.slice(0, 4), 10))) {
          close();
          current = {
            name: prevText,
            iso,
            display: displayFor(tok.text),
            live: '',
            ultrasignup: '',
            ultrarunning: '',
            archive: [],
          };
        }
      }
      prevText = tok.text;
      continue;
    }

    if (!current) continue;

    const href = absolute(tok.href);
    if (!href) continue;

    // The Ultracast viewer and its .clax data are still on the old host: the
    // whole /ultracast/ directory was left behind the same way /results/ was,
    // so every one of these 404s today. Storing them would put a dead button
    // on thirty races and call it a result. They come back when that
    // directory does.
    if (/\/ultracast\//.test(href)) continue;

    if (/live\.aravaiparunning/.test(href)) {
      current.live ||= href;
    } else if (/ultrarunning\.com/.test(href)) {
      current.ultrarunning ||= href;
    } else if (/raceresult\.com|runsignup\.com|ultracast\.tv|timing\.aravaiparunning/.test(href)) {
      // No column of their own in the store, and they do not need one: what
      // a reader wants is a labelled way through to the result, which is
      // exactly what the archive list is. The label the page gave is kept,
      // and "Results" is replaced with the provider, since a row can carry
      // several of these and three buttons all reading "Results" says
      // nothing about which is which.
      const provider = /raceresult\.com/.test(href) ? 'RaceResult'
        : /runsignup\.com/.test(href) ? 'RunSignup'
        : /ultracast\.tv/.test(href) ? 'Ultracast'
        : 'Timing';
      const label = tok.label && !/^results?$/i.test(tok.label) ? tok.label : provider;
      if (!current.archive.some(a => a.url === href)) {
        current.archive.push({ label, url: href });
      }
    } else if (/ultrasignup\.com/.test(href)) {
      // 2020 to 2022 label these "Results", which says nothing a reader does
      // not already know from the button it becomes. A distance label does,
      // so it is kept when the page gave one.
      current.ultrasignup ||= href;
      if (tok.label && !/^results?$/i.test(tok.label)) {
        current.archive.push({ label: tok.label, url: href });
      }
    } else {
      current.archive.push({ label: tok.label, url: href });
    }
  }
  close();
  return out;
}

// The 2020 virtual races, which need rescuing twice over.
//
// Lockdown moved the whole spring calendar to virtual events, and the results
// pages linked each one to a WordPress page: /aravaipa-strong-results/ and so
// on. Those pages are gone, all of them 404, so on the results page these
// races read as though they were never scored.
//
// They were. The scores are real HTML tables of names, places and times,
// sitting under /results/virtual/ where they were recovered from the old
// host, one directory per race and one file per distance. Nothing links to
// them, so this does.
//
// Spelled out rather than discovered because the directory has indexing
// turned off and, more to the point, this is a closed set: eleven races in
// one year that ended six years ago and will not gain a twelfth.
const VIRTUAL_2020 = {
  'aravaipa strong':  ['2020-04-17-aravaipa-strong', ['Half Marathon:1-2-marathon','100 Mile:100-mile','10K:10k','50 Mile:50-mile','50K:50k','5K:5k','Marathon:marathon']],
  'adrenaline':       ['2020-05-16-adrenaline', ['106K:106k','10K:10k','15K:15k','25K:25k','3hr Ride:3h-ride','50K:50k','6hr Ride:6h-ride','6K:6k','Dawnbreaker 100 Mile Ride:dawnbreaker-100-mile-ride','Dawnbreaker 106K:dawnbreaker-106k']],
  'limitless':        ['2020-05-25-limitless', ['7 Day:limitless-7-day']],
  'hypnosis':         ['2020-06-15-hypnosis', ['14K:14k','20K:20k','34K:34k','3hr Ride:3h-ride','54K:54k','6hr Ride:6h-ride','6K:6k','Dawnbreaker 100 Mile Ride:dawnbreaker-100-mile-ride','Dawnbreaker 128K:dawnbreaker-128k']],
  'stunner':          ['2020-07-13-stunner', ['12K:12k','25K:25k','3hr Ride:3h-ride','50K:50k','6hr Ride:6h-ride','6K:6k','Dawnbreaker 100 Mile Ride:dawnbreaker-100-mile-ride','Dawnbreaker 93K:dawnbreaker-93k']],
  'vertigo':          ['2020-08-10-vertigo', ['10K:10k','20K:20k','31K:31k','3hr Ride:3h-ride','52K:52k','6hr Ride:6h-ride','6K:6k','Dawnbreaker 100 Mile Ride:dawnbreaker-100-mile-ride','Dawnbreaker 119K:dawnbreaker-119k']],
  'sinister':         ['2020-08-24-sinister', ['18K:18k','27K:27k','3hr Ride:3h-ride','54K:54k','5K:5k','6hr Ride:6h-ride','9K:9k','Dawnbreaker 100 Mile Ride:dawnbreaker-100-mile-ride','Dawnbreaker 113K:dawnbreaker-113k']],
  'jangover':         ['2020-09-21-jangover', ['15K:15k','25K:25k','3hr Ride:3h-ride','50K:50k','6hr Ride:6h-ride','75K:75k','7K:7k','Dawnbreaker 100 Mile Ride:dawnbreaker-100-mile-ride','Dawnbreaker 172K:dawnbreaker-172k']],
  'be moved':         ['2020-10-01-be-moved', ['1 Mile:1-mile','10K:10k','5K:5k','Challenge:be-moved-challenge','Half Marathon:half-marathon','Marathon:marathon']],
  'every damn':       ['2020-every-damn', ['Every Damn Day:every-damn']],
};

// Matched on a keyword in the race name rather than its date, because the
// page's date and the directory's are not always the same day: the page
// dates a virtual race by when its window opened, the directory by when it
// was scored, and "Adrenaline" is May 18th on one and May 16th on the other.
function virtualFor(name, iso) {
  if (!iso.startsWith('2020')) return null;
  const n = name.toLowerCase();
  for (const [key, [dir, files]] of Object.entries(VIRTUAL_2020)) {
    if (n.includes(key)) {
      return files.map(f => {
        const [label, file] = f.split(':');
        return { label, url: `${BASE}/results/virtual/${dir}/${file}.html` };
      });
    }
  }
  return null;
}

async function readPage(slug) {
  const res = await fetch(`${BASE}/${slug}/`, { headers: { 'User-Agent': UA } });
  if (!res.ok) {
    console.error(`  ${slug}: HTTP ${res.status}`);
    return '';
  }
  return res.text();
}

const only = args.year ? parseInt(args.year, 10) : null;
const rows = [];

for (const [slug, years] of PAGES) {
  if (only && !years.includes(only)) continue;
  const html = await readPage(slug);
  if (!html) continue;
  const found = parsePage(html, years);
  console.error(`  ${slug}: ${found.length} races, ${found.reduce((n, r) => n + r.archive.length, 0)} archive links`);
  rows.push(...found);
}

// Same key the store merges on, so a race listed twice on a page collapses.
const seen = new Map();
for (const r of rows) {
  const key = `${r.name.toLowerCase()}|${r.iso}`;
  const prev = seen.get(key);
  if (!prev) { seen.set(key, r); continue; }
  prev.live ||= r.live;
  prev.ultrasignup ||= r.ultrasignup;
  prev.ultrarunning ||= r.ultrarunning;
  for (const a of r.archive) {
    if (!prev.archive.some(x => x.url === a.url)) prev.archive.push(a);
  }
}

// Only where the page left a race with nothing, so a race that does still
// have a working link keeps the one its own page chose.
let restored = 0;
for (const r of seen.values()) {
  if (r.archive.length || r.ultrasignup || r.live) continue;
  const virt = virtualFor(r.name, r.iso);
  if (virt) { r.archive = virt; restored++; }
}
if (restored) console.error(`  restored ${restored} 2020 virtual races from the rescued archive`);

const merged = [...seen.values()]
  .sort((a, b) => (a.iso === b.iso ? a.name.localeCompare(b.name) : (a.iso < b.iso ? 1 : -1)));

const withArchive = merged.filter(r => r.archive.length).length;
const withUS = merged.filter(r => r.ultrasignup).length;
console.error(`\n${merged.length} races: ${withArchive} with archive links, ${withUS} with UltraSignup`);

if (!args.post) {
  console.log(JSON.stringify(merged, null, 1));
  process.exit(0);
}

// The store is replaced, not merged, so what is posted has to already be
// everything. See the header.
if (!args['merge-with']) {
  console.error('\n--post needs --merge-with <file>, a JSON dump of the stored rows.');
  console.error('The import route replaces the store, so posting only these years would');
  console.error('delete 2023 onward. Dump the current rows first:');
  console.error(`  wp eval 'echo wp_json_encode(get_option("arv_race_results", array()));' > current.json`);
  process.exit(1);
}

const existing = JSON.parse(readFileSync(args['merge-with'], 'utf8'));
const keyOf = r => `${String(r.name).trim().toLowerCase()}|${r.iso}`;
const have = new Set(existing.map(keyOf));

// Stored rows win: they were read from the newer pages by the other scraper,
// which gets a per-race live link and an UltraRunning link where this one
// only ever sees an archive file.
const union = [...existing, ...merged.filter(r => !have.has(keyOf(r)))]
  .sort((a, b) => (a.iso === b.iso ? a.name.localeCompare(b.name) : (a.iso < b.iso ? 1 : -1)));

console.error(`posting ${union.length} rows: ${existing.length} already stored, ${union.length - existing.length} new`);

const url = process.env.ARAVAIPA_WP_URL;
const user = process.env.ARAVAIPA_WP_USER;
const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;
if (!url || !user || !pass) {
  console.error('ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD are required for --post');
  process.exit(1);
}

const res = await fetch(`${url}/wp-json/aravaipa/v1/results/import`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    Authorization: 'Basic ' + Buffer.from(`${user}:${pass}`).toString('base64'),
  },
  body: JSON.stringify({ rows: union, dry_run: !!args['dry-run'] }),
});
console.error(`HTTP ${res.status}`);
console.error(await res.text());
