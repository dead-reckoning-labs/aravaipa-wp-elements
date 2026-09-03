/**
 * Push the UltraRunning results map into WordPress.
 *
 * UltraRunning's results URL cannot be derived the way UltraSignup's can:
 * the slug is their editorial name for the race ("black-canyon-trail" for
 * Black Canyon Ultras) and the id is theirs. Guessing the slug and asking
 * returns 403, and their site sits behind a bot challenge a real browser
 * bounces off, which is there precisely to stop this being looked up
 * automatically.
 *
 * So the map is maintained by hand. This is the way to send it, and
 * ultrarunning-seed.json beside it is the races recovered from the links a
 * human had put on the old per-year results pages, so nobody has to start
 * from an empty list.
 *
 * WRONG UNTIL 2026-09-03: this used to claim the id identifies the race, not
 * the edition, so one entry would keep working forever. Confirmed false:
 * Westminster's stored id (46612) was that race's 2025 results page, and its
 * 2026 results live at a different id (52620) under the same slug.
 * UltraRunning mints a new numeric id per year per race, same slug. Every
 * entry here is only as current as whenever it was last checked against
 * that race's actual current-year results page, and needs re-verifying each
 * season, not just once.
 *
 *   node scripts/push-ultrarunning.mjs            # dry run
 *   node scripts/push-ultrarunning.mjs --post
 */
import fs from 'fs';
import path from 'path';

const args = new Set(process.argv.slice(2));
const seed = JSON.parse(
  fs.readFileSync(path.join(import.meta.dirname, 'ultrarunning-seed.json'), 'utf8')
);

const env = Object.fromEntries(
  fs.readFileSync('/Users/jamilcoury/claudeclaw/.env', 'utf8')
    .split('\n').filter(l => l.includes('=') && !l.startsWith('#'))
    .map(l => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1).replace(/^"|"$/g, '')]; })
);

const names = Object.keys(seed).sort();
console.log(`${names.length} races:\n`);
for (const n of names) console.log(`  ${n.padEnd(30)} ${seed[n]}`);

if (!args.has('--post')) {
  console.log('\ndry run, nothing sent. pass --post to write.');
  process.exit(0);
}

const auth = Buffer.from(
  `${env.ARAVAIPA_WP_ADMIN_USER}:${env.ARAVAIPA_WP_ADMIN_APP_PASSWORD}`
).toString('base64');

const res = await fetch(`${env.ARAVAIPA_WP_URL}/wp-json/aravaipa/v1/races/ultrarunning`, {
  method: 'POST',
  headers: {
    'Authorization': `Basic ${auth}`,
    'Content-Type': 'application/json',
    // The site's security plugin answers a default script user agent with a
    // 406, so every request from here carries a browser one.
    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
  },
  body: JSON.stringify({ races: seed }),
});

console.log('\n' + res.status, await res.text());
