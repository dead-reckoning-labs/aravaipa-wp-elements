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

**Region Map**: `Name | X% | Y% | landing page URL | detail (optional) | flags (optional) | full name (optional) | logo (optional)`. X and Y are the pin's position on `assets/us-outline.svg` as a percentage of its width and height, left and top edges are 0. To place a new pin, open that file at full size in a browser, find the spot, and read its position off as a percentage of the image's own width (X) and height (Y). Flags (sixth column) is a space separated list: `primary` marks the HQ pin (bigger, accent coloured), `above` lifts that pin's label over its dot instead of under it. Two pins at nearly the same latitude collide horizontally however their labels are anchored, so putting one above and one below is what separates them; that is how California and Nevada both carry their real names. The eighth column is a logo, either `ARV_LOGO:file.png` for one of the marks bundled in `assets/logos/` or a full URL. Detail is optional prose, shown on hover or keyboard focus over the map and always in the list beneath it. The seventh column is the unabbreviated name for that list, for regions whose map label had to be shortened to stop it colliding with a neighbour.

Two other controls: **Theme** (dark panel or light, dark by default, since on a white page the map read as a pale shape floating in whitespace) and **Region list below map** (on by default). The list repeats every region as a text link under the map. It is what makes the section usable on a phone, where the pin labels are hidden because several regions sit within about 25% of the map's width and their labels overlap at any readable size, and it is the only part of this element a search engine can read: the map itself is one decorative SVG with no place names in it.

Above phone width the map labels are always visible rather than hover-only, so two regions close together can still run their labels into each other. Reach for the `above` flag first, which lifts one of the pair over its dot so they sit on different lines: that is what lets California and Nevada, whose state centers are about 25 miles apart, both carry their full names. Only if that is not enough, shorten the map label and put the real name in the seventh column, which is what every small dot map of the US does.

```
Arizona | 20.9 | 61.9 | .../arizona/ | Southwest roots. Home of Cocodona 250, Javelina Jundred and Black Canyon 100K. | primary | | ARV_LOGO:aravaipa.png
Tucson | 23 | 68.9 | .../tucson-runs/ | Saguaro country, in the shadow of the Santa Catalinas. | | | ARV_LOGO:aravaipa.png
California | 9.2 | 45.9 | .../california-races/ | Coastal ranges and Sierra foothills. | | | ARV_LOGO:aravaipa.png
Nevada | 14.4 | 43.3 | .../nevada/ | High desert and the Spring Mountains. | above | | ARV_LOGO:aravaipa.png
Colorado | 33.8 | 46.7 | .../colorado/ | Front Range and high country. | | | ARV_LOGO:aravaipa.png
Ultra Adventures | 23.2 | 49.1 | .../ultra-adventures/ | Canyon country. Antelope Canyon, Zion, Tushars, Bryce Canyon. | | | ARV_LOGO:ultra-adventures.png
Great Lakes Endurance | 64.3 | 18.2 | .../great-lakes-endurance/ | Trail and ultra events across the Great Lakes region. | | | ARV_LOGO:great-lakes-endurance.png
White Mountain Endurance | 91.9 | 22 | .../white-mountain-endurance/ | Trail and ultra events across the Northeast.
Bad Beard Events | 71.2 | 61.1 | .../bad-beard/ | Chattanooga, Tennessee.
```

Aravaipa's own regions carry the Aravaipa mountain icon; partner brands carry their own, which is also what distinguishes the two at a glance. White Mountain Endurance and Bad Beard Events have no mark yet: neither has a brand logo anywhere on aravaiparunning.com, only sponsor logos on their pages. See `assets/logos/README.md`.

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

That manual deactivate-delete-reupload cycle is only needed once, to get this version installed. From here on, updates show up as a normal "Update available" row on the Plugins screen with a one-click "Update now", the same as any WordPress.org plugin, because `includes/updater.php` points WordPress at this repo's [Releases](https://github.com/dead-reckoning-labs/aravaipa-wp-elements/releases) page instead.

Publishing a new version means cutting a GitHub Release with `build/aravaipa-elements.zip` attached as the release asset (`gh release create vX.Y.Z build/aravaipa-elements.zip`, after bumping the `Version:` header and re-running `./build.sh`) and nothing else. No server access, no manual file transfer. WordPress checks for a new release at most once every 12 hours per site, and clicking "Check Again" on the Plugins screen bypasses that and checks immediately.

This is also why the repo is public: WordPress's background update check runs on cron, with no admin session and nowhere to put a credential, so it has to be able to read the releases API anonymously. There's nothing in this repo that needs to be private (checked before flipping it: no keys, no tokens, nothing beyond the plugin code and aravaiparunning.com's own already-public URLs).

## Safety notes

This plugin sits on the site that sells the race entries, so it is written to fail quietly rather than loudly:

- Element registration is guarded by `function_exists( 'cs_register_element' )`. Deactivating Cornerstone stops the elements registering, it does not fatal the site.
- Every element returns an empty string rather than a bare heading when it has no rows to show.
- All editor input is escaped on output.
- The countdown validates its target date and leaves the server rendered zeros in place if it cannot parse one, instead of ticking `NaN`.
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
