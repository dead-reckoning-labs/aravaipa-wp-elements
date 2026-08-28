/**
 * Turn the per-year results pages on aravaiparunning.com into rows for the
 * Aravaipa Results element.
 *
 * There is one hand-built page per year, /results-2026/ and back, each a
 * Cornerstone layout with no stable class names. Same problem the race
 * scraper has and the same answer: drive headless Chrome and read the
 * rendered DOM rather than regex the markup.
 *
 * Every race on those pages is the same shape, a name, a date, then up to
 * three links: Aravaipa's own live timing, UltraSignup's results for that
 * running, and UltraRunning's where the race is listed there at all. That
 * last one is missing for roughly half of them, because UltraRunning only
 * lists ultras and a good part of the calendar is 5Ks and road races.
 *
 * Only 2023 and later are read. 2020 to 2022 are a different layout, one
 * line per race with the links inline and labelled "Ultrasignup Results",
 * and no per-race live link at all; they need their own parser and are worth
 * doing separately rather than bending this one until it fits both badly.
 * Everything before 2020 predates the format entirely and carries almost no
 * links of any of the three kinds. Those pages all still exist and still
 * work; this simply does not read them yet.
 *
 *   node scripts/fetch-results.mjs [--from 2023] [--to 2026] [--port 9333]
 *   node scripts/fetch-results.mjs --post
 *   node scripts/fetch-results.mjs --post --dry-run
 *
 * --post requires ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and
 * ARAVAIPA_WP_APP_PASSWORD, an Application Password on an Editor-role user.
 */
const args = Object.fromEntries(
  process.argv.slice(2).join(' ').split('--').filter(Boolean)
    .map(s => s.trim().split(/\s+/)).map(([k, v]) => [k, v ?? true])
);
const FROM = parseInt(args.from || '2023', 10);
const TO   = parseInt(args.to || String(new Date().getFullYear()), 10);
const PORT = parseInt(args.port || '9333', 10);

const MONTHS = { january:1, february:2, march:3, april:4, may:5, june:6, july:7,
  august:8, september:9, october:10, november:11, december:12 };

// Reads a rendered results page and returns one record per race.
//
// Walks the document once in order rather than trying to find a row
// container, because there is no row container: the name, the date and the
// links are siblings in a flat Cornerstone layout with nothing wrapping a
// single race. So the order they appear in is the only thing that groups
// them, and a date is what starts a new race.
const EXTRACT = String.raw`(()=>{
  const DATE=/^[A-Z][a-z]+\s+\d{1,2}(\s*[-–]\s*(?:[A-Z][a-z]+\s+)?\d{1,2})?,\s*(20\d\d)$/;
  const isRes = h => /live\.aravaiparunning|ultrasignup\.com\/results|ultrarunning\.com/.test(h||'');
  const w=document.createTreeWalker(document.body,NodeFilter.SHOW_ELEMENT);
  const toks=[]; let n;
  while((n=w.nextNode())){
    if(n.tagName==='A' && isRes(n.href)){
      toks.push({t:'link',label:(n.innerText||'').replace(/\s+/g,' ').trim(),href:n.href});
      continue;
    }
    if(n.children.length===0){
      const s=(n.innerText||'').replace(/\s+/g,' ').trim();
      if(!s||s.length>90) continue;
      if(DATE.test(s)) toks.push({t:'date',v:s});
      else if(/^[A-Za-z0-9][\w'’&.\- ()\/]{2,80}$/.test(s)
        && !/^(Live Results|Ultrasignup|Ultra Running|Results|Home|Navigation)$/i.test(s))
        toks.push({t:'name',v:s});
    }
  }
  const races=[]; let cur=null, lastName=null;
  for(const tk of toks){
    if(tk.t==='name') lastName=tk.v;
    else if(tk.t==='date'){ cur={name:lastName,date:tk.v,links:{}}; races.push(cur); }
    else if(tk.t==='link' && cur){
      const l=tk.label.toLowerCase();
      const k = l.includes('live') ? 'live' : l.includes('ultrasignup') ? 'ultrasignup' : 'ultrarunning';
      if(!cur.links[k]) cur.links[k]=tk.href;
    }
  }
  return JSON.stringify(races.filter(r=>r.name&&Object.keys(r.links).length));
})()`;

// "August 14-16, 2026" -> 2026-08-14. The day a race started is the one that
// sorts it; the range stays in the display string exactly as the page wrote
// it, because that is the edition's own label.
function isoFor(text) {
  const m = text.match(/^([A-Za-z]+)\s+(\d{1,2}).*?(20\d\d)$/);
  if (!m) return '';
  const mo = MONTHS[m[1].toLowerCase()];
  if (!mo) return '';
  return `${m[3]}-${String(mo).padStart(2,'0')}-${String(parseInt(m[2],10)).padStart(2,'0')}`;
}

// The page writes "August 14-16, 2026"; the element already groups by month
// and prints the year in the group heading, so the row itself only needs the
// part that varies within that month.
function displayFor(text) {
  return text.replace(/,\s*20\d\d$/, '').trim();
}

const targets = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
const page = targets.find(t => t.type === 'page') || targets[0];
if (!page) { console.error(`no page target on port ${PORT}; is Chrome running with --remote-debugging-port=${PORT}?`); process.exit(1); }

const ws = new WebSocket(page.webSocketDebuggerUrl);
let id = 0; const pend = new Map();
const send = (method, params={}) => new Promise((res, rej) => {
  const n = ++id;
  const to = setTimeout(() => { pend.delete(n); rej(new Error('timeout ' + method)); }, 40000);
  pend.set(n, v => { clearTimeout(to); res(v); });
  ws.send(JSON.stringify({ id: n, method, params }));
});
await new Promise(r => ws.onopen = r);
ws.onmessage = e => { const d = JSON.parse(e.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d.result); pend.delete(d.id); } };
const ev = async expr => JSON.parse((await send('Runtime.evaluate', { expression: expr, returnByValue: true })).result.value);

await send('Page.enable');

const rows = [];
const seen = new Set();

for (let year = TO; year >= FROM; year--) {
  const url = `https://www.aravaiparunning.com/results-${year}/`;
  await send('Page.navigate', { url });
  await new Promise(r => setTimeout(r, 9000));

  let found = [];
  try { found = await ev(EXTRACT); } catch (e) { console.error(`  ${year}: extract failed, ${e.message}`); continue; }

  let kept = 0;
  for (const r of found) {
    const iso = isoFor(r.date);
    if (!iso) continue;
    // The page's own year wins over the URL's: a January race listed on the
    // previous year's page would otherwise be filed twelve months out.
    const key = `${r.name.toLowerCase()}|${iso}`;
    if (seen.has(key)) continue;
    seen.add(key);
    rows.push({
      name: r.name,
      iso,
      display: displayFor(r.date),
      live: r.links.live || '',
      ultrasignup: r.links.ultrasignup || '',
      ultrarunning: r.links.ultrarunning || '',
    });
    kept++;
  }
  console.error(`  results-${year}: ${kept} races`);
}

ws.close();

rows.sort((a, b) => a.iso === b.iso ? a.name.localeCompare(b.name) : (a.iso < b.iso ? 1 : -1));

const withUR = rows.filter(r => r.ultrarunning).length;
const withUS = rows.filter(r => r.ultrasignup).length;
const withLive = rows.filter(r => r.live).length;
console.error(`\n${rows.length} races: ${withLive} live, ${withUS} ultrasignup, ${withUR} ultrarunning`);

if (!args.post) {
  console.log(JSON.stringify(rows, null, 1));
  process.exit(0);
}

const url = process.env.ARAVAIPA_WP_URL;
const user = process.env.ARAVAIPA_WP_USER;
const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;
if (!url || !user || !pass) { console.error('ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD are required for --post'); process.exit(1); }

const res = await fetch(`${url}/wp-json/aravaipa/v1/results/import`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    Authorization: 'Basic ' + Buffer.from(`${user}:${pass}`).toString('base64'),
  },
  body: JSON.stringify({ rows, dry_run: !!args['dry-run'] }),
});
console.error(`HTTP ${res.status}`);
console.error(await res.text());
