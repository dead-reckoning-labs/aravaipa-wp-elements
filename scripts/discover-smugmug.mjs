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
 */

import { readFileSync } from 'node:fs';
import { raceMatcher, normalise, yearFrom, IS_RIDE } from './lib/race-match.mjs';

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

// The accounts to walk, and who each one is. Aravaipa's own first.
const ACCOUNTS = [
  { nick: 'Aravaipa', by: 'Aravaipa Photo Gallery' },
  { nick: 'lwp', by: "Let's Wander Photography" },
  { nick: 'springvelvet', by: 'Spring Velvet Photography' },
];

// How many photographs are actually behind a node, albums nested inside it
// included. A race folder is created on SmugMug when the race is scheduled,
// not when it is shot, so the upcoming half of a season sits there as real,
// correctly-named, completely empty folders. Discovery matched all of them
// happily, and posting that set would have put eight dead galleries on the
// photos page: "Cave Creek Thriller 2026" linking to nothing, three months
// before the race is run. Counted rather than guessed from the date, because
// a race can be shot and posted late, and a folder can be seeded early.
const photoCount = async (nodeId, depth = 0) => {
  if (depth > 2) return 0;

  const kids = await api(`node/${nodeId}!children?count=200`);
  let total = 0;

  for (const child of kids?.Response?.Node ?? []) {
    if (child.Type === 'Album' && child.Uris?.Album?.Uri) {
      const album = await api(child.Uris.Album.Uri.replace('/api/v2/', ''));
      total += album?.Response?.Album?.ImageCount ?? 0;
    } else if (child.Type === 'Folder') {
      total += await photoCount(child.NodeID, depth + 1);
    }
  }

  return total;
};

const api = async path => {
  const url = new URL(`https://api.smugmug.com/api/v2/${path}`);
  url.searchParams.set('APIKey', KEY);

  const res = await fetch(url, { headers: { Accept: 'application/json' }, redirect: 'follow' });

  if (!res.ok) return null;

  return res.json().catch(() => null);
};

// ---------------------------------------------------------------------------
// The matcher lives in scripts/lib/race-match.mjs now, shared with the
// Zenfolio walker, which asks the identical question of a different host.
// Two copies would drift, and the drift would be the expensive kind: both
// would still accept and reject galleries, just not the same ones, so the
// same race could arrive under one name from one host and another from the
// other, or a stranger's race be published as Aravaipa's on one and not it.
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

const yearFromUpload = node => {
  const m = String(node.DateAdded ?? '').match(/^(20[12]\d)/);
  return m ? Number(m[1]) : 0;
};

// ---------------------------------------------------------------------------

const accepted = [];
const rejected = [];

/**
 * Is this folder a container of races, rather than a race itself?
 *
 * Only containers are descended into. Without this the walk goes inside
 * other promoters' event folders and matches whatever is in them: Let's
 * Wander's "Oregon 200 Miler 2025" holds a gallery called "Brian
 * Thrasher", which matched Aravaipa's Thrasher Night Runs on the surname.
 *
 * A container is what is left over when the year is removed: "2025 Race
 * Photography" leaves "race photography", "2026 Events" leaves "events",
 * and both are generic. "Oregon 200 Miler 2025" leaves "oregon 200 miler",
 * which is the name of somebody's race.
 */
const CONTAINER_WORDS = new Set(
  'events event races race photography photos photo running runs run gallery galleries archive'.split(' ')
);

const isContainer = name => {
  const words = normalise(name).replace(/\b20[12]\d\b/g, ' ').split(' ').filter(Boolean);

  // A bare year is the commonest container of all ("2026").
  return words.length === 0 || words.every(w => CONTAINER_WORDS.has(w));
};

/**
 * Walk a node's subtree, accepting the OUTERMOST folder that names a race.
 *
 * Depth matters and is not the same on every account. Aravaipa's own is
 * root > "2026 Events" > "Coldwater Rumble" > "Finish Line 1", so the race
 * is two levels down. Spring Velvet's is root > "Rock River Canyon 50K &
 * 27K Trail Race" > albums, so the race is one level down. Testing at a
 * fixed depth found 0 of Spring Velvet's, because it was reading her album
 * names instead of her folder names.
 *
 * Taking the outermost match is what stops one race being accepted five
 * times over, once per "Finish Line N" album inside it.
 */
async function walk( node, account, ancestors, depth ) {
  // Four levels is past any of these accounts' real nesting and stops a
  // pathological tree from walking forever.
  if ( depth > 3 ) return;

  const trail = [ ...ancestors, node.Name ];
  const race = raceFor( node.Name );

  if ( race ) {
    if ( IS_RIDE.test( node.Name ) ) {
      rejected.push({ account: account.nick, name: node.Name, parent: ancestors.join(' / '), why: 'Aravaipa Rides, belongs on aravaiparides.com' });
      return;
    }

    const year = yearFrom( ...trail.slice().reverse() ) || yearFromUpload( node );

    if ( ! year ) {
      rejected.push({ account: account.nick, name: node.Name, parent: ancestors.join(' / '), why: `matched ${race} but no year` });
      return;
    }

    const photos = await photoCount( node.NodeID );

    if ( photos === 0 ) {
      rejected.push({ account: account.nick, name: node.Name, parent: ancestors.join(' / '), why: 'matched a race but holds no photographs yet' });
      return;
    }

    accepted.push({ race, year, by: account.by, url: node.WebUri, photos });
    // Outermost wins: do not descend into this race's own albums.
    return;
  }

  if ( ! node.HasChildren ) {
    rejected.push({ account: account.nick, name: node.Name, parent: ancestors.join(' / '), why: 'no Aravaipa race in the name' });
    return;
  }

  // Not a race, and not a container of races either: another promoter's
  // event, or a wedding, or a trip. Do not go looking inside it.
  if ( depth > 0 && ! isContainer( node.Name ) ) {
    rejected.push({ account: account.nick, name: node.Name, parent: ancestors.join(' / '), why: 'not an Aravaipa race, and not a container' });
    return;
  }

  const kids = await api(`node/${node.NodeID}!children?count=200`);
  const children = kids?.Response?.Node ?? [];

  if ( ! children.length ) {
    rejected.push({ account: account.nick, name: node.Name, parent: ancestors.join(' / '), why: 'no Aravaipa race in the name' });
    return;
  }

  for ( const child of children ) {
    await walk( child, account, trail, depth + 1 );
  }
}

for (const account of ACCOUNTS) {
  const user = await api(`user/${account.nick}`);
  const rootUri = user?.Response?.User?.Uris?.Node?.Uri;

  if (!rootUri) {
    console.error(`${account.nick}: could not read the account, skipped`);
    continue;
  }

  const before = accepted.length;
  const rootId = rootUri.split('/').pop();
  const top = await api(`node/${rootId}!children?count=200`);

  for (const outer of top?.Response?.Node ?? []) {
    await walk( outer, account, [], 0 );
  }

  console.error(`${account.nick}: ${accepted.length - before} galleries matched an Aravaipa race`);
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
