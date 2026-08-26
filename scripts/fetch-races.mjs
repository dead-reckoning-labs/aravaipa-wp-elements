/**
 * Turn https://www.aravaiparunning.com/races/ into rows for the Aravaipa
 * Upcoming Races element.
 *
 * The races page is built in Cornerstone, so its markup is a nest of
 * generated class names with no stable hooks. Regexing it was unreliable;
 * reading the rendered DOM in a real browser is not. That is why this drives
 * headless Chrome over CDP rather than fetching the HTML.
 *
 * The page states dates as "August 29" with no year, and titles itself
 * "2026 Events". A date that has already passed in that year is therefore
 * next year's running, which is how a January race listed on a 2026 page is
 * actually January 2027.
 *
 *   node scripts/fetch-races.mjs [--year 2026] [--port 9360]
 */
const args = Object.fromEntries(
  process.argv.slice(2).join(' ').split('--').filter(Boolean)
    .map(s => s.trim().split(/\s+/)).map(([k, v]) => [k, v ?? true])
);
const YEAR = parseInt(args.year || '2026', 10);
const PORT = parseInt(args.port || '9360', 10);
const TODAY = args.today || new Date().toISOString().slice(0, 10);

const targets = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
const page = targets.find(t => t.type === 'page');
if (!page) { console.error(`no page target on :${PORT}. Start headless Chrome with --remote-debugging-port=${PORT}`); process.exit(1); }
const ws = new WebSocket(page.webSocketDebuggerUrl);
let id = 0; const pending = new Map();
await new Promise(r => ws.onopen = r);
ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } };
const send = (method, params = {}) => new Promise(res => { const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async expr => (await send('Runtime.evaluate', { expression: expr, returnByValue: true })).result?.result?.value;

await send('Page.enable'); await send('Runtime.enable');
await send('Page.navigate', { url: 'https://www.aravaiparunning.com/races/' });
await new Promise(r => setTimeout(r, 14000));

const EXTRACT = [
"(function(){",
" var links=[].slice.call(document.querySelectorAll('a[href*=\"ultrasignup.com/register\"]'));",
" return JSON.stringify(links.map(function(a){",
" // One race row is an .x-container holding six .x-column cells. Verified",
" // by walking the ancestor chain of a register link on the live page,",
" // rather than assumed: .x-row does not exist on this template at all.",
"   var row=a.closest('.x-container');",
"   if(!row) return null;",
"   var cells=[].slice.call(row.querySelectorAll('.x-column'))",
"     .map(function(e){return (e.innerText||'').replace(/\\s+/g,' ').trim();})",
"     .filter(function(v){return !!v;});",
"   var img=row.querySelector('img');",
"   // WP Rocket lazy-loads, so src is an inline svg placeholder until the",
"   // image scrolls into view. The real URL sits in data-lazy-src the whole",
"   // time, which is why reading src alone returned one image out of six.",
"   var src=img?(img.getAttribute('data-lazy-src')||img.currentSrc||img.src||''):'';",
"   if(src.indexOf('data:')===0) src='';",
"   var pageUrl=[].slice.call(row.querySelectorAll('a[href*=\"aravaiparunning.com\"]'))",
"     .map(function(x){return x.getAttribute('href');})",
"     .filter(function(h){return h && !/xmlrpc|wp-content|\\/races\\/?$/.test(h);})[0]||'';",
"   return {reg:a.getAttribute('href'), cells:cells, img:src, page:pageUrl};",
" }));",
"})()"].join('\n');

const raw = JSON.parse(await ev(EXTRACT));
ws.close();

const MONTHS = {january:1,february:2,march:3,april:4,may:5,june:6,july:7,august:8,september:9,october:10,november:11,december:12};

function isoFor(dateText) {
  // "August 29", "September 12-13", "January 23", "April - September"
  const m = dateText.match(/([A-Za-z]+)\s+(\d{1,2})/);
  if (!m) return null;                         // a range of months with no day: a series, not a date
  const mo = MONTHS[m[1].toLowerCase()];
  if (!mo) return null;
  const day = parseInt(m[2], 10);
  let iso = `${YEAR}-${String(mo).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
  // Listed on a YEAR page but already past in YEAR: it is next year's running.
  if (iso < TODAY) iso = `${YEAR+1}-${String(mo).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
  return iso;
}

const rows = [], skipped = [];
for (const r of raw) {
  const dateCell = r.cells[0] || '';
  const display  = dateCell.replace(/\s*(Register|Volunteer)\s*/gi, ' ').replace(/\s+/g,' ').trim();
  const iso = isoFor(display);
  const name = r.cells[1] || '';
  if (!iso || !name) { skipped.push({ name: name || '(no name)', date: display, why: !name ? 'no name' : 'no parseable date' }); continue; }
  rows.push([
    name, iso, display,
    r.cells[2] || '',          // distances, may itself contain " | "
    r.cells[3] || '',          // venue
    r.cells[4] || '',          // city, ST
    r.reg || '',
    r.page || '',
    r.img || '',
  ].join(' | '));
}

rows.sort((a, b) => a.split(' | ')[1].localeCompare(b.split(' | ')[1]));
console.log(rows.join('\n'));
console.error(`\n${rows.length} races, ${skipped.length} skipped`);
for (const s of skipped) console.error(`  skipped: ${s.name} (${s.date || 'no date'}) - ${s.why}`);
