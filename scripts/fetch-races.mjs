/**
 * Turn https://www.aravaiparunning.com/races/ into rows for the Aravaipa
 * Upcoming Races and Season Calendar elements.
 *
 * The races page is built in Cornerstone, so its markup is a nest of
 * generated class names with no stable hooks. Regexing it was unreliable;
 * reading the rendered DOM in a real browser is not. That is why this drives
 * headless Chrome over CDP rather than fetching the HTML.
 *
 * The page states dates as "August 29" with no year, and titles itself
 * "2026 Events". A date that has already passed in that year is therefore
 * next year's running, which is how a January race listed on a 2026 page is
 * actually January 2027 — a guess, tracked as `guessed` below, and separate
 * from whether registration is actually confirmed open.
 *
 * Confirmation itself comes from UltraSignup's own group listing
 * (events.svc/groupevents), not from scraping each race's page on the site.
 * The site links every race with a "did", UltraSignup's old per-year event
 * ID, which is only updated by hand when a director rolls a recurring race
 * over to its next running. The group API instead reports "dtid" IDs, which
 * point at whichever running is actually current right now, so a race whose
 * "did" link still shows last year's finished event can still be confirmed
 * correctly by matching it against the group API by name and date. This is
 * the fix for a real bug: 33 races, including Cocodona 250 and Black Canyon,
 * were reading as unconfirmed purely because their site links were stale,
 * when UltraSignup itself has always had a live listing for them.
 *
 * Registration is also not always UltraSignup. Some races link to RunSignUp,
 * RaceRoster, or a page on aravaiparunning.com's own "network" registration
 * system. The extraction below reads whatever the page's own "Register" link
 * points to, by anchor text, rather than assuming a domain — the previous
 * version only recognised ultrasignup.com links and silently dropped every
 * race that registered anywhere else, which included Javelina Jundred, the
 * three RunSignUp-hosted virtual events, and about a dozen more.
 *
 *   node scripts/fetch-races.mjs [--year 2026] [--port 9360] [--gid 7]
 *   node scripts/fetch-races.mjs --post              # scrape and write to the site
 *   node scripts/fetch-races.mjs --post --dry-run     # scrape and report, write nothing
 *
 * --post requires ARAVAIPA_WP_URL, ARAVAIPA_WP_USER and
 * ARAVAIPA_WP_APP_PASSWORD in the environment (an Application Password on a
 * plain Editor-role user, not an Administrator: see includes/race-store.php's
 * /wp-json/aravaipa/v1/races/import for why that split exists).
 */
const args = Object.fromEntries(
  process.argv.slice(2).join(' ').split('--').filter(Boolean)
    .map(s => s.trim().split(/\s+/)).map(([k, v]) => [k, v ?? true])
);
const YEAR = parseInt(args.year || '2026', 10);
const PORT = parseInt(args.port || '9360', 10);
const GID = parseInt(args.gid || '7', 10);
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
" // One race row is an .x-container holding six .x-column cells. Verified",
" // by walking the ancestor chain of a register link on the live page,",
" // rather than assumed: .x-row does not exist on this template at all.",
" var rows=[].slice.call(document.querySelectorAll('.x-container'));",
" return JSON.stringify(rows.map(function(row){",
"   var cells=[].slice.call(row.querySelectorAll('.x-column'))",
"     .map(function(e){return (e.innerText||'').replace(/\\s+/g,' ').trim();})",
"     .filter(function(v){return !!v;});",
"   if(!cells.length) return null;",
"   // Matched on the link's own text, not its domain: registration goes to",
"   // ultrasignup.com, runsignup.com, raceroster.com, or a page on this site",
"   // itself, and only the label is consistent across all of them.",
"   var anchors=[].slice.call(row.querySelectorAll('a'));",
"   var regA=anchors.filter(function(a){return (a.innerText||'').trim()==='Register';})[0];",
"   var img=row.querySelector('img');",
"   // WP Rocket lazy-loads, so src is an inline svg placeholder until the",
"   // image scrolls into view. The real URL sits in data-lazy-src the whole",
"   // time, which is why reading src alone returned one image out of six.",
"   var src=img?(img.getAttribute('data-lazy-src')||img.currentSrc||img.src||''):'';",
"   if(src.indexOf('data:')===0) src='';",
"   var pageUrl=anchors.map(function(x){return x.getAttribute('href');})",
"     .filter(function(h){return h && /aravaiparunning\\.com/.test(h) && !/xmlrpc|wp-content|\\/races\\/?$|\\/network\\//.test(h);})[0]||'';",
"   return {reg:regA?regA.getAttribute('href'):'', cells:cells, img:src, page:pageUrl};",
" }).filter(function(x){return x && x.cells.length>=2;}));",
"})()"].join('\n');

const raw = JSON.parse(await ev(EXTRACT));
ws.close();

const MONTHS = {january:1,february:2,march:3,april:4,may:5,june:6,july:7,august:8,september:9,october:10,november:11,december:12,
                jan:1,feb:2,mar:3,apr:4,jun:6,jul:7,aug:8,sep:9,sept:9,oct:10,nov:11,dec:12};

/**
 * UltraSignup's own list of every event under Aravaipa's group, current and
 * past. This is the authoritative source for whether a race is really
 * scheduled: it carries a "dtid" per year's running rather than the site's
 * "did", which UltraSignup only updates by hand. A recurring race gets one
 * entry per year it has ever run; only the ones dated today or later matter
 * here.
 *
 * @param {number} gid
 * @return {Promise<Array<{name:string, iso:string, end:string, dtid:number}>>}
 */
async function fetchGroupEvents(gid) {
  try {
    const res = await fetch(`https://ultrasignup.com/service/events.svc/groupevents?gid=${gid}`, { signal: AbortSignal.timeout(20000) });
    if (!res.ok) return [];
    const events = await res.json();
    const toIso = s => {
      // "8/29/2026 12:00:00 AM"
      const m = String(s || '').match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
      return m ? `${m[3]}-${m[1].padStart(2,'0')}-${m[2].padStart(2,'0')}` : '';
    };
    return events
      .filter(e => !e.Cancelled)
      .map(e => ({
        name: e.EventName,
        iso: toIso(e.EventDate),
        end: toIso(e.EventDateEnd),
        dtid: e.EventDateId,
        // Every one of the 121 events in this listing carries real
        // coordinates, which is what makes a pin map possible without
        // geocoding anything ourselves.
        lat: e.Latitude || '',
        lng: e.Longitude || '',
      }))
      .filter(e => e.iso);
  } catch {
    return [];
  }
}

function normalizeRaceName(s) {
  return s.toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .split(/\s+/)
    // 'javelina' is Aravaipa's own umbrella brand for an entire race
    // weekend covering three distinct races (Jundred, Jangover, Jallucinations),
    // so on its own it does not distinguish anything. Dropping it here is what
    // stopped Javelina Jallucinations, a RunSignUp virtual event with its own
    // real October date, from matching Javelina Jangover's UltraSignup dtid
    // purely because both names contain the word "Javelina": both scored a
    // 0.5 overlap on that one shared token, which cleared the 0.4 threshold.
    .filter(w => w && !['the','trail','trails','run','runs','race','races','night','ultra','ultras','marathon','presented','by','hoka','javelina'].includes(w));
}

function nameOverlap(a, b) {
  const wa = new Set(normalizeRaceName(a)), wb = new Set(normalizeRaceName(b));
  if (!wa.size || !wb.size) return 0;
  let hits = 0;
  for (const w of wa) if (wb.has(w)) hits++;

  // A single shared word is only trustworthy when one whole name collapses
  // to just that word: "Cocodona" against "Cocodona 250" is a real match on
  // one shared token, because the shorter side IS that token, nothing else.
  // "Across The Globe" against "Across the Years" shares one token too, and
  // is not the same race: neither name is a subset of the other, each has a
  // different word the other lacks, and rejecting only the second case needs
  // this distinction rather than a bigger stopword list. A stopword fixes one
  // specific collision (this one fixed "javelina" separately); this fixes
  // the shape of the problem.
  if (hits === 1 && Math.min(wa.size, wb.size) > 1) return 0;

  return hits / Math.max(wa.size, wb.size);
}

/**
 * Match one site row against UltraSignup's group listing.
 *
 * Both name and date have to agree, not just one. Name alone is too loose:
 * "Cocodona" and "Cocodona Training Run" share enough words to pass a lenient
 * threshold, and the group listing carries both as separate events. Date
 * alone collides even more readily across a 120-event list. The window is 45
 * days, wide enough to cover Mogollon Monster (site says the 12th, the API's
 * single-day record says the 13th) and Cocodona (site's guessed date and the
 * API's real one differ by two days after a year-boundary roll), but tight
 * enough that "Cocodona Training Run" — a real event, just not this one —
 * cannot win by drifting in on name alone from months away.
 *
 * @param {string} name
 * @param {string} iso Our own best-guess date for this race.
 * @param {Array}  groupEvents
 * @return {{iso:string, end:string, dtid:number}|null}
 */
function matchGroupEvent(name, iso, groupEvents) {
  let best = null;
  for (const g of groupEvents) {
    if (g.iso < TODAY) continue;
    const dayDiff = Math.abs((new Date(iso) - new Date(g.iso)) / 86400000);
    if (dayDiff > 45) continue;
    const score = nameOverlap(name, g.name);
    if (score < 0.4) continue;
    if (!best || score > best.score || (score === best.score && dayDiff < best.dayDiff)) {
      best = { score, dayDiff, iso: g.iso, end: g.end, dtid: g.dtid, groupName: g.name, lat: g.lat, lng: g.lng };
    }
  }
  return best;
}


/**
 * Coordinates for a race, from any running of it the group listing knows
 * about, including ones already past.
 *
 * Separate from matchGroupEvent because the two questions have different
 * evidence requirements. "Is this race scheduled" must only ever be answered
 * by a current listing: a finished event proves nothing about next year.
 * "Where is this race held" is answered fine by last year's entry, because a
 * venue does not move when a date rolls over, and using the wider pool takes
 * map coverage from 44 races to most of the calendar.
 *
 * Name-only, with the same subset rule the dated matcher uses, so the two
 * name collisions found earlier (Javelina, Across The) cannot reappear here
 * through the back door.
 *
 * @param {string} name
 * @param {Array} groupEvents
 * @return {{lat:string, lng:string}}
 */
function findCoords(name, groupEvents) {
  let best = null;
  for (const g of groupEvents) {
    if (!g.lat || !g.lng) continue;
    const score = nameOverlap(name, g.name);
    if (score < 0.5) continue;
    // Ties go to the most recent running, whose venue is likeliest current.
    if (!best || score > best.score || (score === best.score && g.iso > best.iso)) {
      best = { score, iso: g.iso, lat: g.lat, lng: g.lng };
    }
  }
  return best ? { lat: best.lat, lng: best.lng } : { lat: '', lng: '' };
}

/**
 * The date entries close, read off the race's actual current registration
 * page. Only meaningful for UltraSignup: RunSignUp, RaceRoster and
 * aravaiparunning.com's own registration pages do not carry this text, and
 * scraping them for it would just as easily match unrelated copy.
 *
 * UltraSignup prints "Registration closes: Mon, Sep 7 @ 11:59PM MT" for a
 * race still open, present tense with no year, or "Registration Closed Mon.
 * Aug 24, 2026 @ 11:59 PM" for one that has shut, past tense with its own
 * year. Both have to be matched: reading only the first turns every already
 * -closed race into one that looks like it never published a close date at
 * all, which happened here once already.
 *
 * fetch() follows the redirect a dtid URL issues to its current did on its
 * own, so this works the same whether it is given a dtid or a resolved did.
 *
 * @param {string} registerUrl
 * @param {string} raceIso
 * @return {Promise<string>} Y-m-d, or '' when nothing is published.
 */
async function fetchRegClose(registerUrl, raceIso) {
  try {
    const res = await fetch(registerUrl, { signal: AbortSignal.timeout(20000) });
    if (!res.ok) return '';
    const html = await res.text();

    const closed = html.match(/Registration Closed\s+(?:[A-Za-z]{3,9}\.?,?\s*)?([A-Za-z]{3,9})\.?\s+(\d{1,2}),?\s+(\d{4})/);
    if (closed) {
      const mo = MONTHS[closed[1].toLowerCase()];
      if (mo) return `${closed[3]}-${String(mo).padStart(2,'0')}-${String(parseInt(closed[2],10)).padStart(2,'0')}`;
    }

    const open = html.match(/Registration closes:\s*(?:[A-Za-z]{3,9},?\s*)?([A-Za-z]{3,9})\.?\s+(\d{1,2})/i);
    if (open) {
      const mo = MONTHS[open[1].toLowerCase()];
      if (mo) {
        let y = parseInt(raceIso.slice(0, 4), 10);
        const iso = () => `${y}-${String(mo).padStart(2,'0')}-${String(parseInt(open[2],10)).padStart(2,'0')}`;
        if (iso() > raceIso) y -= 1;
        return iso();
      }
    }

    return '';
  } catch {
    return '';
  }
}

// "September 12-13" and "October 30 - November 1" both describe a race that
// is still running on its later day. Without an end date the module drops a
// multi-day race the morning of day two, while runners are still out there.
function isoEndFor(dateText, startIso) {
  const sameMonth = dateText.match(/([A-Za-z]+)\s+\d{1,2}\s*[-–]\s*(\d{1,2})/);
  if (sameMonth) {
    const mo = MONTHS[sameMonth[1].toLowerCase()];
    if (mo) return `${startIso.slice(0,4)}-${String(mo).padStart(2,'0')}-${String(parseInt(sameMonth[2],10)).padStart(2,'0')}`;
  }
  const crossMonth = dateText.match(/[A-Za-z]+\s+\d{1,2}\s*[-–]\s*([A-Za-z]+)\s+(\d{1,2})/);
  if (crossMonth) {
    const mo = MONTHS[crossMonth[1].toLowerCase()];
    if (!mo) return '';
    // A range that runs backwards through the calendar has crossed a year.
    const y = mo < parseInt(startIso.slice(5,7),10) ? parseInt(startIso.slice(0,4),10)+1 : parseInt(startIso.slice(0,4),10);
    return `${y}-${String(mo).padStart(2,'0')}-${String(parseInt(crossMonth[2],10)).padStart(2,'0')}`;
  }
  return '';
}

// Returns {iso, guessed}, or null when the text carries no day at all (a
// month range like "April - September" is a series, not a single date).
// guessed is true when the listed date has already passed this year and the
// year was rolled forward: the day and month are real, published by
// Aravaipa, but the year is this script's assumption. Kept separate from
// whether registration is confirmed, because those are different facts — The
// Bear Chase has a real, future, site-published date and, independently, may
// or may not have a live registration set up yet.
function isoFor(dateText) {
  const m = dateText.match(/([A-Za-z]+)\s+(\d{1,2})/);
  if (!m) return null;
  const mo = MONTHS[m[1].toLowerCase()];
  if (!mo) return null;
  const day = parseInt(m[2], 10);
  let iso = `${YEAR}-${String(mo).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
  if (iso < TODAY) {
    return { iso: `${YEAR+1}-${String(mo).padStart(2,'0')}-${String(day).padStart(2,'0')}`, guessed: true };
  }
  return { iso, guessed: false };
}

/**
 * Aravaipa's own timing system, at live.aravaiparunning.com. Real results,
 * not a scrape: a public JSON list of the races currently on its board, each
 * with a stable slug, and `#/{slug}` is a working results page for it.
 *
 * Only a rolling window of races lives here, not the full season, so most
 * rows will not find a match here and fall back to the UltraSignup-derived
 * results link the element already computes on its own.
 */
async function fetchLiveResultsIndex() {
  try {
    const res = await fetch('https://live.aravaiparunning.com/api/v1/race_events/live', { signal: AbortSignal.timeout(15000) });
    if (!res.ok) return [];
    const events = await res.json();
    return events.map(e => {
      const utc = new Date(e.startTime);
      const local = new Date(utc.getTime() + e.timezoneOffset * 3600000);
      return { name: e.name, slug: e.slug, localDate: local.toISOString().slice(0, 10) };
    });
  } catch {
    return [];
  }
}

/**
 * Find this race on the live results board, if it is there. See the module
 * doc comment on matchGroupEvent for why both name and date have to agree.
 */
function findLiveResultsUrl(raceName, raceIso, liveIndex) {
  let best = null;
  for (const item of liveIndex) {
    const dayDiff = Math.abs((new Date(raceIso) - new Date(item.localDate)) / 86400000);
    if (dayDiff > 1) continue;
    const score = nameOverlap(raceName, item.name);
    if (score < 0.5) continue;
    if (!best || score > best.score) best = { score, slug: item.slug };
  }
  return best ? `https://live.aravaiparunning.com/#/${best.slug}` : '';
}

const rows = [], skipped = [];
let withClose = 0, withLive = 0, withConfirmed = 0, withGuessed = 0, withGroupMatch = 0, withGeo = 0;

const [liveIndex, groupEvents] = await Promise.all([fetchLiveResultsIndex(), fetchGroupEvents(GID)]);
console.error(`live results board: ${liveIndex.length} races currently on it`);
console.error(`UltraSignup group listing: ${groupEvents.length} events total, ${groupEvents.filter(g => g.iso >= TODAY).length} dated today or later`);

for (const r of raw) {
  const dateCell = r.cells[0] || '';
  const display  = dateCell.replace(/\s*(Register|Volunteer)\s*/gi, ' ').replace(/\s+/g,' ').trim();

  // A race the site itself has marked cancelled is not a date to publish
  // anywhere, however cleanly it happens to parse.
  if (/\bcancell?ed\b/i.test(display)) {
    skipped.push({ name: r.cells[1] || '(no name)', date: display, why: 'cancelled' });
    continue;
  }

  const dateInfo = isoFor(display);
  const name = r.cells[1] || '';
  if (!dateInfo || !name) {
    skipped.push({ name: name || '(no name)', date: display, why: !name ? 'no name' : 'no parseable date' });
    continue;
  }

  let iso = dateInfo.iso;
  let end = isoEndFor(display, iso);
  let guessed = dateInfo.guessed;
  let confirmed = false;
  let register = r.reg || '';
  let closes = '';
  let lat = '';
  let lng = '';

  const match = matchGroupEvent(name, iso, groupEvents);

  if (match) {
    // UltraSignup's own listing settles the date: real, current, and not a
    // guess, which is why this overrides whatever isoFor() worked out from
    // the site's undated text.
    if (args.audit) console.error(`  MATCH  ${name.padEnd(40)} -> ${match.groupName.padEnd(40)} score=${match.score.toFixed(2)} dayDiff=${match.dayDiff}`);
    iso = match.iso;
    if (match.end) end = match.end;
    guessed = false;
    confirmed = true;
    register = `https://ultrasignup.com/register.aspx?dtid=${match.dtid}`;
    withGroupMatch++;
  } else if (register) {
    const isUltraSignup = /ultrasignup\.com/i.test(register);
    // A register link that is not UltraSignup does not suffer from the
    // stale-did problem this whole pipeline exists to catch: the site
    // controls that URL directly. Trusted as confirmed as long as the date
    // itself is not a guess. An UltraSignup link with no group-listing match
    // is the old problem exactly — a "did" nobody has rolled over — so it
    // stays unconfirmed even though a link still exists.
    confirmed = !isUltraSignup && !guessed;
  }

  const coords = findCoords(name, groupEvents);
  lat = coords.lat;
  lng = coords.lng;

  const liveUrl = findLiveResultsUrl(name, iso, liveIndex);
  if (liveUrl) withLive++;

  if (register && /ultrasignup\.com/i.test(register)) {
    // One request per race, paced. Sequential on purpose: dozens of parallel
    // hits on someone else's site to save a few seconds is not a trade
    // worth making.
    closes = await fetchRegClose(register, iso);
    await new Promise(r2 => setTimeout(r2, 200));
  }

  if (closes) withClose++;
  if (confirmed) withConfirmed++;
  if (guessed) withGuessed++;
  if (lat && lng) withGeo++;

  rows.push([
    name, iso, display,
    r.cells[2] || '',          // distances, may itself contain " | "
    r.cells[3] || '',          // venue
    r.cells[4] || '',          // city, ST
    register,
    r.page || '',
    r.img || '',
    end,
    liveUrl,
    closes,
    confirmed ? '1' : '0',
    guessed ? '1' : '0',
    lat,
    lng,
  ].join(' | '));
}

rows.sort((a, b) => a.split(' | ')[1].localeCompare(b.split(' | ')[1]));
const outputText = rows.join('\n');
console.log(outputText);
console.error(`\n${rows.length} races, ${skipped.length} skipped, ${withGroupMatch} matched to UltraSignup's group listing, ${withConfirmed} confirmed overall, ${withGuessed} with a rolled-forward (guessed) year, ${withClose} with a published registration close date, ${withLive} matched to the live results board, ${withGeo} with map coordinates`);
for (const s of skipped) console.error(`  skipped: ${s.name} (${s.date || 'no date'}) - ${s.why}`);

/**
 * --post: send the rows straight to the site instead of leaving them for a
 * human to copy into Races -> Import.
 *
 * Off by default. Every invocation above this line is unchanged: stdout
 * still gets the rows, stderr still gets the summary, so a manual run
 * (`node scripts/fetch-races.mjs > rows.txt`) behaves exactly as it always
 * has. This only runs the extra step of also POSTing them.
 *
 * prune=true is not optional here, unlike the admin screen's checkbox: this
 * script's whole output IS the current, complete state of the races page,
 * so anything it does not mention is not there anymore and belongs pruned.
 * A manual admin-screen import might reasonably paste a partial list; a
 * cron re-running this same scrape every time never has a reason to.
 *
 * The receiving endpoint (POST /wp-json/aravaipa/v1/races/import, added in
 * v0.21.12) is the one that actually enforces safety: it refuses to prune
 * when the row count drops more than 20% from what's currently stored,
 * which is exactly the "the scrape half-failed" case this cron is most at
 * risk of. --dry-run here still exercises that same check server-side (a
 * dry run WITH prune=true reports what prune would have refused), it just
 * never writes.
 */
if (args.post) {
  const url = process.env.ARAVAIPA_WP_URL;
  const user = process.env.ARAVAIPA_WP_USER;
  const pass = process.env.ARAVAIPA_WP_APP_PASSWORD;

  if (!url || !user || !pass) {
    console.error('\n--post given but ARAVAIPA_WP_URL / ARAVAIPA_WP_USER / ARAVAIPA_WP_APP_PASSWORD is not set. Not posting anything.');
    process.exit(1);
  }

  const body = new URLSearchParams({ rows: outputText, prune: 'true' });
  if (args['dry-run']) body.set('dry_run', 'true');

  console.error(`\nPOSTing to ${url}/wp-json/aravaipa/v1/races/import${args['dry-run'] ? ' (dry run)' : ''}...`);

  let res;
  try {
    res = await fetch(`${url}/wp-json/aravaipa/v1/races/import`, {
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

  // A non-2xx status, or a body the endpoint itself flagged as refused
  // (the row-count guardrail, or the parser-unavailable fallback), both
  // mean the calendar was not updated. Either has to fail the cron run
  // loudly rather than exit 0 on a no-op.
  if (!res.ok || !json || 'refused' === json.status) {
    console.error(`\nimport did not apply (HTTP ${res.status}).`);
    process.exit(1);
  }
}
