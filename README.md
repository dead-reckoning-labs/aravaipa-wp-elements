# Aravaipa Elements

Custom [Cornerstone](https://theme.co/cornerstone) elements for aravaiparunning.com, built as a standalone WordPress plugin so they survive X theme updates.

Every race page on the site currently rebuilds the same blocks by hand out of raw Cornerstone columns, which is why no two race pages have quite the same spacing or stat order. These five elements replace the ones that repeat the most.

## Elements

Each appears in the Cornerstone builder's element list under its own name.

| Element | Replaces |
|---|---|
| **Aravaipa Race Hero** | The date / race name / location lockup plus registration status and CTA |
| **Aravaipa Distance Cards** | The "Course Information" grid, one card per distance |
| **Aravaipa Event Timeline** | "Event Timeline", "Start Times", "Bib Pickup" schedules, grouped by day |
| **Aravaipa Partner Grid** | The "Thank you to our partners!" logo wall, with sponsor tiers |
| **Aravaipa Countdown** | Countdown to the start gun |

## Repeating rows

Distance Cards, Event Timeline and Partner Grid all take their repeating data as one row per line, pipe separated. Staff can paste a block straight out of the race spreadsheet instead of clicking "add row" eleven times.

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
