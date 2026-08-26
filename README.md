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

**Upcoming Races**: `Name | ISO date | display date | distances | venue | city, ST | register URL | race page URL | image URL | ISO end date`. The ISO date (`2026-08-29`) is required: it drives the sort and the structured data, and a row without a real one is dropped rather than shown with a guessed date. Display date is optional and exists for what a single date cannot say, like `September 12-13`. Leave it blank and it is formatted from the ISO date.

The tenth column is an optional ISO end date, for races that run more than one day. It keeps a multi-day race up while it is still running and adds `endDate` to its structured data.

The distances column may contain pipes, because that is how the rest of the site writes them (`50K | 25K | 10K | 5K`). A full-length row is read from both ends, so the first three columns and the last six are fixed and everything between them is the distance list. A short row has no fixed tail to anchor against and falls back to plain positional reading, so it cannot carry pipes.

```
Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | 10K | 5K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://www.aravaiparunning.com/bear-chase-series/rock-hawk/ | https://.../rock-hawk.png
```

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

## Install

1. Download `aravaipa-elements.zip`.
2. WP Admin, Plugins, Add New, Upload Plugin.
3. Activate. The elements appear in Cornerstone immediately.

Uploading over an install that is still present gives you two copies, not one: WordPress will not overwrite an existing plugin directory, so the second upload lands in `aravaipa-elements-2/` next to the original, and whichever one is activated is the one the site actually renders. If `wp-content/plugins/` has more than one of these, deactivate and delete every copy, then upload once. The updater handles the suffixed folder name correctly either way (it reads its own path rather than assuming one), but two installs means two rows on the Plugins screen and no obvious signal about which is live.

That manual deactivate-delete-reupload cycle is only needed once, to get this version installed. From here on, updates show up as a normal "Update available" row on the Plugins screen with a one-click "Update now", the same as any WordPress.org plugin, because `includes/updater.php` points WordPress at this repo's [Releases](https://github.com/dead-reckoning-labs/aravaipa-wp-elements/releases) page instead.

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
