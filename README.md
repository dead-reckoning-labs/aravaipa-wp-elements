# Aravaipa Elements

Custom [Cornerstone](https://theme.co/cornerstone) elements for aravaiparunning.com, built as a standalone WordPress plugin so they survive X theme updates.

Every race page on the site currently rebuilds the same blocks by hand out of raw Cornerstone columns, which is why no two race pages have quite the same spacing or stat order. These six elements replace the ones that repeat the most.

## Elements

Each appears in the Cornerstone builder's element list under its own name.

| Element | Replaces |
|---|---|
| **Aravaipa Race Hero** | The date / race name / location lockup plus registration status and CTA |
| **Aravaipa Distance Cards** | The "Course Information" grid, one card per distance |
| **Aravaipa Event Timeline** | "Event Timeline", "Start Times", "Bib Pickup" schedules, grouped by day |
| **Aravaipa Partner Grid** | The "Thank you to our partners!" logo wall, with sponsor tiers |
| **Aravaipa Countdown** | Countdown to the start gun |
| **Aravaipa Region Map** | The "Where to find us" regional choropleth |
| **Aravaipa Upcoming Races** | Nothing. New: the next races with dates and a real Register button, plus their Event structured data |
| **Aravaipa Race Map** | Nothing. New: every race as a pin, with a popup carrying the basics and links to Race Info and registration |
| **Aravaipa Race Status** | The hand-written registration button on each race's own page, which is why some still point at a registration that closed months ago |
| **Aravaipa Season Calendar** | The full-year race table on /races/, which mixed already-run races with a live Register button on 46 of its 72 rows |

## Repeating rows

Distance Cards, Event Timeline, Partner Grid and Region Map all take their repeating data as one row per line, pipe separated. Staff can paste a block straight out of the race spreadsheet instead of clicking "add row" eleven times.

**Distance Cards**: `Name | stat | stat | stat | register URL`. The URL is optional and is only treated as a button when it actually starts with `http` or `/`, so a race with no registration link yet will not turn its start time into a button. Stat columns are labelled by the comma separated "Stat labels" control.

```
100K | 5,900 ft | 20 hrs | 7:00 AM | https://ultrasignup.com/register.aspx?did=1
50K  | 2,700 ft | 11 hrs | 8:00 AM | https://ultrasignup.com/register.aspx?did=2
60K Sky | 4,100 ft | 14 hrs | 6:30 AM
```

**Event Timeline**: `Day | Time | What happens`. Consecutive rows sharing a day are grouped under one heading, and rows stay in the order written rather than being sorted, since the copy never states a year to sort by.

```
Friday, February 12 | 4:00 PM | Packet pickup opens
Friday, February 12 | 7:00 PM | Mandatory pre-race briefing
Saturday, February 13 | 7:00 AM | 100K start
```

**Partner Grid**: `Name | logo URL | link URL | tier`. Tier is `title`, `presenting` or `supporting` and defaults to supporting. Title sponsors span two grid columns. The link is optional. The partner name becomes the image alt text, so a sponsor whose contract says their name appears on the page still gets it in a screen reader.

```
HOKA | https://.../hoka.png | https://hoka.com | title
Kahtoola | https://.../kahtoola.png | https://kahtoola.com | presenting
Squirrels Nut Butter | https://.../snb.png | | supporting
```

**Region Map**: `Name | X% | Y% | landing page URL | detail (optional) | flags (optional) | full name (optional) | logo (optional)`. X and Y are the pin's position on `assets/us-outline.svg` as a percentage of its width and height, left and top edges are 0. To place a new pin, open that file at full size in a browser, find the spot, and read its position off as a percentage of the image's own width (X) and height (Y). The projection is an oblique conic, so latitude does not run straight down Y and longitude does not run straight across X: pick the spot off the drawing rather than converting from real coordinates. Flags (sixth column) is a space separated list: `primary` marks the HQ pin (bigger, accent coloured), `above` lifts that pin's label over its dot instead of under it. Two pins at nearly the same latitude collide horizontally however their labels are anchored, so putting one above and one below is what separates them. Nothing needs it at the moment, because the pins sit on the cities the races are actually in rather than on state centres, which spreads them out on its own. The eighth column is a logo, either `ARV_LOGO:file.png` for one of the marks bundled in `assets/logos/` or a full URL. Detail is optional prose, shown on hover or keyboard focus over the map and always in the list beneath it. Every pin's card also carries a "View races" button through to that region's landing page. The seventh column is the unabbreviated name for that list, for regions whose map label had to be shortened to stop it colliding with a neighbour.

Two other controls: **Theme** (dark panel or light, dark by default, since on a white page the map read as a pale shape floating in whitespace) and **Region list below map** (on by default). The list repeats every region as a text link under the map. It is what makes the section usable on a phone, where the pin labels are hidden because several regions sit within about 25% of the map's width and their labels overlap at any readable size, and it is the only part of this element a search engine can read: the map itself is one decorative SVG with no place names in it.

Above phone width the map labels are always visible rather than hover-only, so two regions close together can still run their labels into each other. Reach for the `above` flag first, which lifts one of the pair over its dot so they sit on different lines. Before that, though, check the coordinates themselves: California and Nevada used to need the flag only because both sat on their state centres, about 25 miles apart, and moving them onto Orange County and Las Vegas fixed the collision and the geography at once. Only if neither helps, shorten the map label and put the real name in the seventh column, which is what every small dot map of the US does.

```
Arizona | 20.9 | 61.9 | .../arizona/ | Southwest roots. Home of Cocodona 250, Javelina Jundred and Black Canyon 100K. | primary | | ARV_LOGO:aravaipa.png
Tucson | 23 | 68.9 | .../tucson-runs/ | Saguaro country, in the shadow of the Santa Catalinas. | | | ARV_LOGO:aravaipa.png
California | 9.4 | 59.7 | .../california-races/ | Coastal ranges and Sierra foothills. | | | ARV_LOGO:aravaipa.png
Nevada | 15.6 | 52.7 | .../nevada/ | High desert and the Spring Mountains. | | | ARV_LOGO:aravaipa.png
Colorado | 33.8 | 46.7 | .../colorado/ | Front Range and high country. | | | ARV_LOGO:colorado.png
Ultra Adventures | 23.2 | 49.1 | .../ultra-adventures/ | Canyon country. Antelope Canyon, Zion, Tushars, Bryce Canyon. | | | ARV_LOGO:ultra-adventures.png
Great Lakes Endurance | 64.3 | 18.2 | .../great-lakes-endurance/ | Trail and ultra events across the Great Lakes region. | | | ARV_LOGO:great-lakes-endurance.png
White Mountain Endurance | 91.9 | 22 | .../white-mountain-endurance/ | Trail and ultra events across the Northeast. | | | ARV_LOGO:white-mountain-endurance.png
Bad Beard Events | 71.2 | 61.1 | .../bad-beard/ | Chattanooga, Tennessee. | | | ARV_LOGO:bad-beard.png
```

Every region now has a mark. Arizona, Tucson, California and Nevada carry the Aravaipa mountain icon; Colorado, Ultra Adventures, Great Lakes Endurance, White Mountain Endurance and Bad Beard Events each carry their own, which is also what distinguishes an Aravaipa region from a partner brand at a glance. Two are crops rather than whole logos, for reasons worth reading before replacing them: see `assets/logos/README.md`.

**Upcoming Races**: `Name | ISO date | display date | distances | venue | city, ST | register URL | race page URL | image URL | ISO end date | live URL | ISO registration close date | confirmed (1 or 0)`. Season Calendar takes the identical row format, so one paste works for both. The ISO date (`2026-08-29`) is required: it drives the sort and the structured data, and a row without a real one is dropped rather than shown with a guessed date. Display date is optional and exists for what a single date cannot say, like `September 12-13`. Leave it blank and it is formatted from the ISO date.

The tenth column is an optional ISO end date, for races that run more than one day. It keeps a multi-day race up while it is still running and adds `endDate` to its structured data. The eleventh is an optional live/results URL. `scripts/fetch-races.mjs` fills it automatically when a race is on `live.aravaiparunning.com`, Aravaipa's own timing system, which is a real results page rather than the entrants list UltraSignup falls back to. Leave it blank otherwise and the element derives an UltraSignup results link from the register URL's `did` instead.

Matched on date and name together, never one alone: two races often share a start date, and name matching alone is loose enough to fuzzy-match the wrong race entirely (checked, and it happens). A match within a day of the row's own date and with real word overlap in the name is trusted; anything looser is left for the UltraSignup fallback, which is always safe even when it is the weaker link.

### The race lifecycle

The primary button changes with where a race is in its life. "Race Details" is always beside it as the hollow secondary, so the pair keeps its shape and the eye is not re-learning the layout on every card.

| When | Primary button | Goes to |
|---|---|---|
| Before race day, entries open | **Register** (red) | UltraSignup registration |
| Entries closed, or within the lead window | **Live Results** (teal) | the timing board, which carries the start list before anyone runs |
| Entries closed with no results board at all | **Entries Closed** (grey, not a link) | nowhere; Race Details beside it is the only thing to press |
| Race day, through the last day of a multi-day race | **Live Results** (teal) | live URL, or the derived UltraSignup results page |
| After the race, until the Monday after | **Results** (teal) | same |
| Monday morning | gone | |

Red means "there is something to buy". Once a race is running or done there is nothing to sell, so those phases use teal.

### Why live results appear before the race

`live.aravaiparunning.com` populates a race's board well before the gun: four days out from Rock Hawk it already carried the full start list with bib numbers. That is worth reaching, so the switch does not wait for race morning.

It happens at whichever comes first: **entries closing**, which is the natural moment because there is nothing left to sell, or **the lead window**, which defaults to 5 days and covers races that never published a close date. Set **Days before race day to show live results** to 0 to wait for race day.

The lead is clamped to 30 days. A lead longer than the gap between races would put the whole list into its live phase at once and read as though the entire season were running today.

The **Entries Closed** chip is now the rare case: it only appears for a race whose entries have shut and which has no results board and no derivable results link, so there is genuinely nowhere to send anyone.

### How the close date is known

UltraSignup prints the close date above the fold, visible without logging in, and `scripts/fetch-races.mjs` reads it per race into the twelfth column.

There are **two different messages** and both have to be matched (see below for how the URL checked is now always the current one, not whatever `/races/` happens to link). A race still taking entries says `Registration closes: Mon, Sep 7 @ 11:59PM MT`, present tense, no year. A race that has already stopped says `Registration Closed Mon. Aug 24, 2026 @ 11:59 PM`, past tense, with the year. Matching only the first, which is what this did originally, reads every already-closed race as though no close date were published at all and leaves it offering Register after entries have shut.

**Only some races publish one, tracked per race.** The other 60 come back empty and behave as they always did, taking entries until race day, which is what they actually do. So the close date sharpens the answer where it exists and changes nothing where it does not.

Do not try to infer it from registration *status* instead. UltraSignup does not report that in any way worth trusting: every race page carries "Register Now" in its title whether or not entries are open, Rock Hawk (open) contains the word "Closed", Black Canyon returns "Register Now", "Wait List" and "Lottery" together, and the `events.svc` JSON endpoints all 404. That was all checked before settling on the published close date, which is a fact rather than an inference.

The close date carries no year, so it takes the race's, pulled back one if that would put the close after the race it belongs to.

The distances column may contain pipes, because that is how the rest of the site writes them (`50K | 25K | 10K | 5K`). A full-length row is read from both ends, so the first three columns and the last six are fixed and everything between them is the distance list. A short row has no fixed tail to anchor against and falls back to plain positional reading, so it cannot carry pipes.

```
Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | 10K | 5K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://www.aravaiparunning.com/bear-chase-series/rock-hawk/ | https://.../rock-hawk.png
```

### Confirmed vs. guessed dates

Most of this calendar is recurring races. When a race's date has already passed for the year shown, the row's year gets bumped forward by a generator heuristic, a guess, not a fact. `scripts/fetch-races.mjs` now checks that guess against UltraSignup's own listing for the race and records whether the year actually agrees: **11 of 69 races in the current calendar are confirmed; the other 58 are guesses**, because their organiser has not rolled the UltraSignup listing over to the next running yet. That is normal (races open a few months out, not a year ahead for the whole season) but it means a Register button on an unconfirmed row would send someone to whatever UltraSignup is still showing, which is very often last year's, already-finished race.

**Only show confirmed races** (on by default) makes Upcoming Races skip anything unconfirmed rather than guess. A row from before this column existed, or written without it, is treated as confirmed, so nothing that already worked silently disappears.

### How current it stays

Two of the three things you would want are automatic, and it is worth being clear about which is which.

**Automatic, on every page load.** Races that have already happened are dropped, the rest are sorted by date, and the first few are shown. A race stays up through its own race day, and through the final day of a multi-day race when an end date is given, then disappears the next morning. So the whole season lives in the box and the page shows the correct next six on its own, indefinitely, with nobody touching it. "Today" is the site's timezone, not the server's, so an Arizona race drops the morning after it ran in Arizona.

**Not automatic.** A race that did not exist when the rows were generated will not appear, and a date or link that changed on `/races/` will not update. Rerun `scripts/fetch-races.mjs` when the calendar moves. The element ships with the season baked in as its default value, so a freshly placed element is correct without any editing at all.

If every race in the list is in the past, the element renders nothing rather than a heading over an empty grid, which is the signal that the rows need regenerating.

### Where the rows come from

`scripts/fetch-races.mjs` generates them from https://www.aravaiparunning.com/races/, which is the page that already has every race and every UltraSignup link on it.

```bash
# headless Chrome with a debugging port, then:
node scripts/fetch-races.mjs --year 2026 > rows.txt
```

It drives a real browser rather than fetching HTML because the races page is built in Cornerstone and its markup is generated class names with no stable hooks. Two things it handles that are easy to get wrong: the page states dates without a year (a date already past in the stated year is next year's running), and WP Rocket lazy-loads the artwork, so the real image URL is in `data-lazy-src` and `img.src` is a placeholder. Series with a month range rather than a date ("April - September") have no start date to give schema, so they are skipped and reported on stderr rather than guessed at.

## Structured data

Two blocks, both of which the site had none of before.

**Event**, from the Upcoming Races element, one `SportsEvent` per race it shows, wrapped in a single `@context`/`@graph` script. This is what makes a race eligible for Google's event results and legible to an AI answer engine. Only fields we actually have are emitted: no invented entry price (they vary by distance and by how early you enter, and a wrong price is worse than no price), no guessed end date. `eventAttendanceMode` is set explicitly because Google otherwise assumes online.

**Organization and WebSite**, from `includes/seo.php`, on the front page only, along with a meta description. That file also stands down entirely if Yoast, Rank Math, All in One SEO or SEOPress is ever activated, so it can never produce a second competing description. The front page description is filterable via `arv_seo_front_page_description`, and the Organization node via `arv_seo_organization`.

## Where the race data actually comes from

`scripts/fetch-races.mjs` was rebuilt this session after two real problems surfaced, both worth recording because they were silent: nothing errored, the generator just quietly produced less and worse data than the site actually has.

**It was dropping every race that did not register through UltraSignup.** Registration was matched by scanning for `ultrasignup.com/register` links only, so a race using RunSignUp, RaceRoster, or a page on aravaiparunning.com's own registration system vanished from the output entirely, with no error. That included Javelina Jundred, three RunSignUp-hosted virtual races (Javelina Jallucinations, Merry Vertmas, Across The Globe), Sonoma Fall Classic, Tucson Marathon, and about a dozen more. Fixed by reading whichever link the page's own "Register" button points to, by its text, not its domain: **69 rows became 84.**

**It was checking the wrong identifier for whether a race is actually scheduled.** `/races/` links every race with UltraSignup's "did", a per-year ID a director only updates by hand when they roll a recurring race over to its next running. Most of Aravaipa's races had not been rolled over yet, so their `did` link still pointed at last year's finished event, and the generator read that as "not scheduled" for races that UltraSignup itself has always had a live listing for. **Cocodona 250, Black Canyon, Zion Ultras and 30 others were reading as unconfirmed for this reason alone.**

The fix is UltraSignup's own group listing, `events.svc/groupevents?gid=7`, which reports a "dtid" per year's actual running rather than a hand-maintained `did`. The generator now cross-references every race against it by name and date, and when a match is found, trusts the API's own date and mints a fresh `register.aspx?dtid=...` link that always points at the current running rather than whatever the site happens to link. **Confirmed races went from 11 to 51.**

Matching two live catalogs by name is exactly the kind of thing that produces plausible-looking wrong answers, and it did, twice, both caught before shipping rather than after:

- **Javelina Jallucinations** (a real RunSignUp virtual race in October) matched **Javelina Jangover Night Runs** (a real, unrelated September race) purely because both names contain "Javelina" — Aravaipa's own umbrella brand for an entire race weekend covering three distinct races. Fixed by adding it to the matcher's stopword list.
- **Across The Globe** (RunSignUp, December) matched **Across the Years** (Aravaipa's own multi-day track race, also December) on the shared word "across". A stopword does not fix this one, because "across" is not a generic word in general, just a coincidence between these two specific names. Fixed structurally instead: a single shared word is only trusted when one whole name collapses to just that word (`Cocodona` inside `Cocodona 250` is a real subset relationship; `Across The Globe` and `Across the Years` share one word each while each also carries a different one, which is not).

Regression-tested in `scripts/test/name-matcher.test.mjs`, run with `bun scripts/test/name-matcher.test.mjs`, separately from the PHP harnesses since this logic lives in the Node generator.

A race the site itself marks cancelled (Tushars Ultras, wildfire) is now dropped rather than parsed as a normally-scheduled date with no register button.

## Search and filter

The Season Calendar carries a search box and state / month / open-only filters above the list once it holds more than five races.

Filtering happens in the browser on rows that are **already rendered**, which is the whole design: every race is in the HTML before any JavaScript runs, so the page is a complete list with JavaScript off, and search engines index all 84 races regardless of what any filter is set to. A page whose entire job is being the complete list should not be an empty shell waiting on a fetch.

The bar itself is hidden until its script has run. A search box that does nothing because its JavaScript failed to load reads as broken; absent is better than broken, and the list underneath is complete either way.

Search matches name, venue, town and distances together, word by word in any order, so "tucson 50k" finds a 50K near Tucson. The state and month dropdowns are built from the races actually present, so neither can offer an option that returns nothing. The hiatus list hides while any filter is active: it sits outside the filtered list because those races have no date to filter on, but leaving it visible during a search reads as the filter having missed something.

The JS hides a non-matching row by setting its `hidden` attribute, which relies on the browser's own `[hidden] { display: none }` default. `.arv-calendar__row` also carries an unconditional `display: flex` for its own layout, and an author rule always wins a specificity tie against a user-agent default regardless of source order — so every filter set `hidden` correctly and nothing visibly changed (shipped this way, fixed in v0.21.2 with an explicit `.arv-calendar__row[hidden] { display: none; }` override, and guarded in `build.sh` so it can't silently regress).

## The race map

Every race as a pin, popup carrying date, distances, location, and the same phase-aware buttons as everywhere else (Register / Live Results / Results, plus Race Info).

Coordinates come from UltraSignup's group listing, which carries a real latitude and longitude for all 121 of its Aravaipa events. Nothing is geocoded or guessed from a place name. Coordinates are looked up across **every** running of a race, including past ones, unlike scheduling which only ever trusts a current listing: a venue does not move when a date rolls over, and using the wider pool took coverage from 44 races to 74.

Coordinates are validated rather than trusted: out of range, non-numeric, and `0` (the Atlantic, which is what an empty field becomes when something casts before checking) all resolve to "no pin". A race with no usable location is **named underneath the map** rather than dropped, because a race missing from a complete list with no explanation is exactly the bug this page has spent a while fixing.

**Leaflet with OpenStreetMap tiles by default**, not Mapbox: no account, no token, no billing relationship, nothing to rotate when someone leaves. The tile URL and attribution are settings, so pointing it at Mapbox for their styling is a field change rather than a rewrite. Leaflet is this plugin's only third-party dependency and loads only when the map actually renders pins, from inside the render function itself: an earlier version gated the enqueue on the page's `post_content`, which Cornerstone never populates since it stores element data in postmeta, so Leaflet silently never loaded at all (shipped broken in v0.20.0/v0.21.0, fixed in v0.21.1).

The markup includes a `<noscript>` list of every pinned race. The map genuinely cannot work without JavaScript and a CDN; the list can, so the races stay readable and indexable if either fails.

## The race store

One race, one record, in WordPress. Everything else reads from it.

Before this the same 69 races existed in **five places**: a flat file in the repo, baked into two element files as PHP defaults, and a copy saved into postmeta for every element instance placed on a page. Changing one date meant regenerating, rebuilding, releasing, and re-adding elements to pick up new defaults, which is absurd for a date change.

Now: **Races** in the WordPress admin. Edit a date in thirty seconds. Every element on every page reflects it immediately.

**Why a custom post type and not an external database.** The data is tens of rows, read almost exclusively by WordPress, which already has querying, caching, an editing UI, revisions and permissions for exactly this. An external database would add a service, a bill, a credential and a sync problem while solving nothing `wp_posts` does not. If something outside WordPress ever needs it, that is the moment to revisit, and it will most likely be RaceGoat becoming the upstream source rather than another copy.

The post type is **not public**. Races already have real pages (`/blackcanyon/`, `/cocodona/`), and a second competing URL per race would split the SEO value of the first. Each record links out to the page that already exists.

### What it feeds

| Where | Element | Notes |
|---|---|---|
| Homepage | Upcoming Races | the next few open races |
| /races/ | Upcoming Races + Season Calendar | open now, then the full forward-looking year |
| Division pages | either, with a **region slug** | `arizona`, `colorado`, `nevada`, `california`, `ultra-adventures`, `great-lakes-endurance`, `white-mountain-endurance`, `bad-beard` |
| A race's own page | **Race Status** | finds its own race by matching the page URL, no configuration |

Region is read off the race's own page path first (`/white-mountain-endurance/black-bear-trail-races/` is unambiguous in a way that "Waterville Valley, NH" is not, and survives a venue move), falling back to the state in the location.

### Importing

**Races → Import** in the admin. Takes the same rows `scripts/fetch-races.mjs` produces, so the generator's output goes straight in. Races are matched and updated in place, so re-importing is safe.

Matched on the registration URL **qualified by race name**, not the URL alone. Two pairs of unrelated races on the live site currently share a registration link: Vegas Golden Night & Day points at Elephant Mountain's, and Zion Ultras at Dam Good Run's, both verified against UltraSignup's own listing. Keying on the URL alone silently collapses each pair into one record and loses a race with nothing failing.

Optional pruning removes races an import does not mention. They are **trashed, not deleted**, so a bad import that drops half the calendar is recoverable from the admin.

### Migration safety

Every element checks the store first and falls back to its own bundled rows when it is empty, so installing this changes nothing until races are actually imported. A half-migrated site keeps rendering exactly what it rendered yesterday.

## Season Calendar

`/races/` was one table doing two jobs at once: "what can I enter right now" and "what does the year look like." Those need different treatment, and trying to do both in one place is what let 46 of 72 rows sit there with a live Register button months after the race had already run.

**Aravaipa Upcoming Races**, with **Only show confirmed races** left on and **Maximum races to show** set to 0, is the first job: every race that is genuinely open, full stop.

**Aravaipa Season Calendar** is the second, and it only ever looks forward. Races are ordered by how many days until they next come round, so a race three weeks out sits near the top and one that ran last month sits near the bottom under next year's heading. The list rolls over on its own as the year turns; nothing needs re-editing in January. A race that has just run keeps its place for a short grace period (default 2 days) before flipping forward.

The date shown depends on whether it is actually known, which is a different question from whether registration is open:

- **Date published by Aravaipa** (real, future): the day is shown, even if registration has not opened yet. The Bear Chase reads "3-4" under October 2026.
- **Year rolled forward by the generator** (the listed date has already passed): the month is real but the day belongs to a running nobody has scheduled, so it reads **TBD** rather than stating a date no one committed to.

Currently 23 of 69 rows show a real date and 46 read TBD.

Conflating those two facts is a mistake worth not repeating: keying the TBD on "registration confirmed" instead of "date guessed" hid The Bear Chase's real published October date behind a TBD purely because its UltraSignup listing had not rolled over.

Every row links to the race's own page. A race whose registration is confirmed also gets a small **Live Results** / **Results** link while it is running and just after, driven by the same `arv_upcoming_races_action()` the homepage runs on, so both pages change state at the same moment by construction rather than by two implementations agreeing.

The other 58 rows stay quiet. Every URL available for them is derived from an UltraSignup listing that still describes a previous running, so "results" would be last year's results. Register is never offered here at all: selling entries is Upcoming Races' job, and two Register buttons on one page pointing at the same place is noise.

A published date is only real for the running it was published for. When a race rolls past its grace window into next year, its day stops being printed and becomes TBD, recomputed at render time so it self-corrects rather than asserting a date for a running nobody has scheduled.

**On hiatus** is a hand-maintained list at the bottom, one per line: `Name | race page URL (optional) | note (optional)`. Deliberately not derived from anything, because "next year is not scheduled yet" and "we are not running this again" look identical from outside and only one of them should be told to a runner as a hiatus.

## Region Map without the plugin

The Region Map also ships as `standalone-region-map.html`: one self-contained block to paste into a Cornerstone **Raw Content** element, no plugin required. Inline `<style>`, inline SVG, no JavaScript, no external requests, no API keys.

This exists for the case where installing a plugin is a bigger change to the live site than swapping one Raw Content block for another. The "Where to find us" block it replaces was itself a Raw Content block.

It is generated, not hand written. To change anything (move a pin, add a region, restyle):

```bash
# edit includes/elements/region-map.php and/or assets/aravaipa-elements.css
php arv-standalone.php     # rewrites standalone-region-map.html
```

`arv-standalone.php` calls the element's real render function and extracts every `arv-region-map` rule out of the shared stylesheet, preserving `@media` wrappers. The map itself needs no handling: the element inlines the SVG rather than pointing an `<img>` at it, so the page's own stylesheet can theme the state fills, and the render output is already self contained. Nothing is duplicated by hand, so the plugin element and the paste-in block cannot drift apart. It refuses to write the file at all rather than emit an unstyled block, an empty one, or one still referencing a plugin asset URL that would 404.

Editing `standalone-region-map.html` directly works until the next regeneration overwrites it, which is why the generated file says so in its own header comment.

## Plugin artwork

`assets/plugin/` holds the icon and banner WordPress renders on its own screens. Without them it falls back to a generic grey plug, which is what it showed until v0.21.0.

Built from Aravaipa's real marks rather than redrawn, on `#1d2624`, the site's own dark sampled from its CSS. The banners use the **white** variant of the mark: the standard lockup sets "ARAVAIPA" in a dark navy that disappears against that background. The banner says only "ELEMENTS" beside the mark, because the mark already reads "ARAVAIPA RUNNING" and an earlier version that spelled it out again said the word twice.

Quantized to a small palette, which took them from 195KB to 19KB with no visible change: they are flat colour and a two-tone mark, and they ship in every download.

They are referenced from `includes/updater.php` by absolute URL into the installed plugin's own directory, so they resolve for whatever version is actually on the site rather than depending on a release asset staying reachable. Both places WordPress asks for artwork are populated: the update row on the Plugins screen and the "View details" modal. `build.sh` fails if any of the four files is missing from the payload, since a missing icon is not an error, it just quietly reverts to the grey plug.

## Install

1. Download `aravaipa-elements.zip`.
2. WP Admin, Plugins, Add New, Upload Plugin.
3. Activate. The elements appear in Cornerstone immediately.

Uploading over an install that is still present gives you two copies, not one: WordPress will not overwrite an existing plugin directory, so the second upload lands in `aravaipa-elements-2/` next to the original, and whichever one is activated is the one the site actually renders. If `wp-content/plugins/` has more than one of these, deactivate and delete every copy, then upload once. The updater handles the suffixed folder name correctly either way (it reads its own path rather than assuming one), but two installs means two rows on the Plugins screen and no obvious signal about which is live.

That manual deactivate-delete-reupload cycle is only needed once, to get this version installed. From here on, updates show up as a normal "Update available" row on the Plugins screen with a one-click "Update now", the same as any WordPress.org plugin, because `includes/updater.php` points WordPress at this repo's [Releases](https://github.com/dead-reckoning-labs/aravaipa-wp-elements/releases) page instead.

**Bump the patch number.** `0.21` ran to `0.21.51` across 37 releases, which is the right shape: the minor moves when a new element or a genuinely new capability lands, and everything else, fixes, layout corrections, copy, styling, is a patch. One evening of work in August 2026 went from `0.22` to `0.40` by treating every user-visible change as a minor bump, which made the version say "eighteen new capabilities" about an evening that added about four. Nothing downstream cares, since the updater only compares for "is this newer", but the number is the only summary of the project's shape anyone reads at a glance, and it should not lie about it.

Publishing a new version means cutting a GitHub Release with `build/aravaipa-elements.zip` attached as the release asset (`gh release create vX.Y.Z build/aravaipa-elements.zip`, after bumping the `Version:` header and re-running `./build.sh`) and nothing else. No server access, no manual file transfer. WordPress checks for a new release at most once every 12 hours per site, and clicking "Check Again" on the Plugins screen bypasses that and checks immediately.

This is also why the repo is public: WordPress's background update check runs on cron, with no admin session and nowhere to put a credential, so it has to be able to read the releases API anonymously. There's nothing in this repo that needs to be private (checked before flipping it: no keys, no tokens, nothing beyond the plugin code and aravaiparunning.com's own already-public URLs).

## Safety notes

This plugin sits on the site that sells the race entries, so it is written to fail quietly rather than loudly:

- Element registration is guarded by `function_exists( 'cs_register_element' )`. Deactivating Cornerstone stops the elements registering, it does not fatal the site.
- Every element returns an empty string rather than a bare heading when it has no rows to show.
- All editor input is escaped on output.
- The countdown validates its target date and leaves the server rendered zeros in place if it cannot parse one, instead of ticking `NaN`.
- The region map's "View races" button is a `span` inside the pin's own anchor, not a nested link or button. It goes where the pin already goes, so there is one link and one destination per region rather than two interactive elements announcing the same page. On touch, where there is no hover, the card never opens and tapping the pin goes straight through.
- The region map drops any row with no name, no URL, or a non-numeric position, and clamps whatever position is left into 0-100, rather than emitting a pin at an arbitrary or off-map coordinate from a typo.
- The updater only loads in wp-admin (`is_admin()`), never on a page a visitor requests. A failed or slow GitHub check is cached as a failure for 5 minutes rather than retried on every admin page load, and every one of its own filters returns whatever it was handed untouched if anything about the request doesn't match this plugin, rather than assuming it does.

## Development

There is no build step. PHP, one CSS file, one dependency free JS file.

```bash
php -l aravaipa-elements.php          # lint (repeat per file)
php arv-edge.php                      # 36 element render assertions
php arv-updater-test.php              # 31 self-updater assertions
php arv-harness.php                   # writes preview.html
php arv-standalone.php                # writes standalone-region-map.html
python3 -m http.server 8899           # then open /preview.html
./build.sh                            # writes build/aravaipa-elements.zip
```

`arv-harness.php` and `arv-edge.php` stub the Cornerstone and WordPress functions the elements call, so the render output can be checked in a browser without a WordPress install. `arv-standalone.php` stubs the same set to generate the paste-in Region Map. `arv-updater-test.php` stubs a different set (the transient cache and HTTP APIs, not Cornerstone) to exercise the update checker against canned GitHub responses, including failures, without making a real network request. All four are excluded from the packaged zip.

## Possible next step

Aravaipa already has an ops engine consolidating UltraSignup registration data. These elements take their values by hand today; a follow up could have Distance Cards and Race Hero read live registration status and entrant counts from that API instead, so a race page stops being a thing anyone has to remember to update when registration closes.
