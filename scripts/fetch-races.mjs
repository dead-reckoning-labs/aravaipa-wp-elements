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

const MONTHS = {january:1,february:2,march:3,april:4,may:5,june:6,july:7,august:8,september:9,october:10,november:11,december:12,
                jan:1,feb:2,mar:3,apr:4,jun:6,jul:7,aug:8,sep:9,sept:9,oct:10,nov:11,dec:12};

/**
 * The date entries close, from the race's own UltraSignup page.
 *
 * UltraSignup prints "Registration closes: Mon, Sep 7 @ 11:59PM MT" above the
 * fold, with no year, and only on races whose director has set a hard close:
 * 9 of the 69 in the current calendar. The rest simply take entries until
 * race day, which is what the element assumes when this comes back empty.
 *
 * The year is taken from the race, then pulled back one if that would put the
 * close after the race it belongs to, which is how a January close for a
 * February race lands correctly.
 */
async function fetchRegClose(registerUrl, raceIso) {
  try {
    const res = await fetch(registerUrl, { signal: AbortSignal.timeout(20000) });
    if (!res.ok) return '';
    const html = await res.text();
    const m = html.match(/Registration closes:\s*(?:[A-Za-z]{3},\s*)?([A-Za-z]+)\s+(\d{1,2})/i);
    if (!m) return '';
    const mo = MONTHS[m[1].toLowerCase()];
    if (!mo) return '';
    let y = parseInt(raceIso.slice(0, 4), 10);
    const iso = () => `${y}-${String(mo).padStart(2,'0')}-${String(parseInt(m[2],10)).padStart(2,'0')}`;
    if (iso() > raceIso) y -= 1;
    return iso();
  } catch {
    return '';
  }
}

// "September 12-13" and "October 30 - November 1" both describe a race that
// is still running on its later day. Without an end date the module drops a
// multi-day race the morning of day two, while runners are still out there.
function isoEndFor(dateText, startIso) {
  const sameMonth = dateText.match(/([A-Za-z]+)\s+\d{1,2}\s*[-\u2013]\s*(\d{1,2})/);
  if (sameMonth) {
    const mo = MONTHS[sameMonth[1].toLowerCase()];
    if (mo) return `${startIso.slice(0,4)}-${String(mo).padStart(2,'0')}-${String(parseInt(sameMonth[2],10)).padStart(2,'0')}`;
  }
  const crossMonth = dateText.match(/[A-Za-z]+\s+\d{1,2}\s*[-\u2013]\s*([A-Za-z]+)\s+(\d{1,2})/);
  if (crossMonth) {
    const mo = MONTHS[crossMonth[1].toLowerCase()];
    if (!mo) return '';
    // A range that runs backwards through the calendar has crossed a year.
    const y = mo < parseInt(startIso.slice(5,7),10) ? parseInt(startIso.slice(0,4),10)+1 : parseInt(startIso.slice(0,4),10);
    return `${y}-${String(mo).padStart(2,'0')}-${String(parseInt(crossMonth[2],10)).padStart(2,'0')}`;
  }
  return '';
}

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
let withClose = 0;
for (const r of raw) {
  const dateCell = r.cells[0] || '';
  const display  = dateCell.replace(/\s*(Register|Volunteer)\s*/gi, ' ').replace(/\s+/g,' ').trim();
  const iso = isoFor(display);
  const name = r.cells[1] || '';
  if (!iso || !name) { skipped.push({ name: name || '(no name)', date: display, why: !name ? 'no name' : 'no parseable date' }); continue; }
  // One request per race, paced. Sequential on purpose: 69 parallel hits on
  // someone else's site to save a few seconds is not a trade worth making.
  const regClose = r.reg ? await fetchRegClose(r.reg, iso) : '';
  if (regClose) withClose++;
  await new Promise(r2 => setTimeout(r2, 200));
  rows.push([
    name, iso, display,
    r.cells[2] || '',          // distances, may itself contain " | "
    r.cells[3] || '',          // venue
    r.cells[4] || '',          // city, ST
    r.reg || '',
    r.page || '',
    r.img || '',
    isoEndFor(display, iso),
    // Live/results override, for a race with its own tracker or broadcast
    // page. Left blank: the element derives an UltraSignup results link from
    // the register URL's did, which serves both the live field and the final
    // results, so most races never need this.
    '',
    regClose,
  ].join(' | '));
}

rows.sort((a, b) => a.split(' | ')[1].localeCompare(b.split(' | ')[1]));
console.log(rows.join('\n'));
console.error(`\n${rows.length} races, ${skipped.length} skipped, ${withClose} with a published registration close date`);
for (const s of skipped) console.error(`  skipped: ${s.name} (${s.date || 'no date'}) - ${s.why}`);
