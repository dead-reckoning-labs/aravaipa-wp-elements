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

**Region Map**: `Name | X% | Y% | landing page URL | detail (optional) | primary (optional)`. X and Y are the pin's position on `assets/us-outline.svg` as a percentage of its width and height, left and top edges are 0. To place a new pin, open that file at full size in a browser, find the spot, and read its position off as a percentage of the image's own width (X) and height (Y). `primary` (the literal word, in the sixth column) marks the HQ pin: bigger, accent coloured. Detail is optional prose that only appears on hover or keyboard focus, so it costs nothing when left blank.

Region names are always visible on the map, not hover-only (a label that only appears on hover is unusable on a touch screen, which is most of this page's traffic), which means two regions whose real-world locations are close together can run their labels into each other at a phone's width. This is not a bug to chase with smaller and smaller font sizes: it is what every small dot map of the US does, and the fix is the one they all use. Abbreviate whichever pins cluster tightly and move the full name into the (hover/focus-only) detail field, rather than widening the map or shrinking the type further. The default rows below do this twice: California and Nevada's real state centers sit close enough together to collide, and Great Lakes Endurance and White Mountain Endurance are each other's longest labels and land close enough that the map's own edge-avoidance (which slides a pin's label away from whichever side of the map it is closest to) pushes White Mountain's label straight into Great Lakes' at a phone's width.

```
Arizona | 20.9 | 61.9 | https://www.aravaiparunning.com/arizona/ | Southwest roots. Home of Cocodona 250, Javelina Jundred, Black Canyon 100K, and more. | primary
Tucson | 23 | 68.9 | https://www.aravaiparunning.com/tucson-runs/
CA | 9.2 | 45.9 | https://www.aravaiparunning.com/california-races/ | California
NV | 14.4 | 43.3 | https://www.aravaiparunning.com/nevada/ | Nevada
Colorado | 33.8 | 46.7 | https://www.aravaiparunning.com/colorado/
Ultra Adventures | 23.2 | 49.1 | https://www.aravaiparunning.com/ultra-adventures/ | Canyon country. Antelope Canyon, Zion, Tushars, Bryce Canyon.
Great Lakes | 67.1 | 25.5 | https://www.aravaiparunning.com/great-lakes-endurance/ | Great Lakes Endurance. Trail and ultra events across the Great Lakes region.
White Mtn | 91.9 | 22 | https://www.aravaiparunning.com/white-mountain-endurance/ | White Mountain Endurance. Trail and ultra events across the Northeast region.
```

## Install

1. Download `aravaipa-elements.zip`.
2. WP Admin, Plugins, Add New, Upload Plugin.
3. Activate. The elements appear in Cornerstone immediately.

To update, deactivate and delete the old copy first, then upload the new zip. Page content is unaffected: element values live in the page, not the plugin.

## Safety notes

This plugin sits on the site that sells the race entries, so it is written to fail quietly rather than loudly:

- Element registration is guarded by `function_exists( 'cs_register_element' )`. Deactivating Cornerstone stops the elements registering, it does not fatal the site.
- Every element returns an empty string rather than a bare heading when it has no rows to show.
- All editor input is escaped on output.
- The countdown validates its target date and leaves the server rendered zeros in place if it cannot parse one, instead of ticking `NaN`.
- The region map drops any row with no name, no URL, or a non-numeric position, and clamps whatever position is left into 0-100, rather than emitting a pin at an arbitrary or off-map coordinate from a typo.

## Development

There is no build step. PHP, one CSS file, one dependency free JS file.

```bash
php -l aravaipa-elements.php          # lint (repeat per file)
php arv-edge.php                      # 23 edge case assertions
php arv-harness.php                   # writes preview.html
python3 -m http.server 8899           # then open /preview.html
```

`arv-harness.php` and `arv-edge.php` stub the Cornerstone and WordPress functions the elements call, so the render output can be checked in a browser without a WordPress install. Both are excluded from the packaged zip.

## Possible next step

Aravaipa already has an ops engine consolidating UltraSignup registration data. These elements take their values by hand today; a follow up could have Distance Cards and Race Hero read live registration status and entrant counts from that API instead, so a race page stops being a thing anyone has to remember to update when registration closes.
