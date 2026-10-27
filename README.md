# Aravaipa Elements

WordPress plugin that runs most of aravaiparunning.com's custom features on the X theme and Cornerstone. It started as a set of Cornerstone elements (race hero, distance cards, event timeline, partner grid, countdown, region and race maps, upcoming races, season calendar, race status, course map) and is now the site's feature layer: the race store (a `Races` post type every element reads from), live results and auto-created live pages, results, photos, films, podcasts, watch and media hub pages, the shop rail, athlete pages, Aravaipa Adventures trips, race and site SEO and structured data, accessibility fixes, and a GitHub Releases self-updater. It is a standalone plugin so it survives theme updates.

**Live:** https://www.aravaiparunning.com (plugin version in `aravaipa-elements.php`)

**Owner:** Vaip (Vaip Daddi claw).

## Stack

- PHP 8 WordPress plugin for the X theme and Cornerstone; plain CSS and dependency-free JS in `assets/` (Leaflet from a CDN for the race map only)
- Data in WordPress itself: custom post types and options (`includes/*-store.php`)
- Node scripts in `scripts/` that build and refresh data (UltraSignup, live timing, SmugMug and Zenfolio photos, YouTube, Square shop, results) and push it through the WordPress REST API or `wp eval-file`
- Updates ship as GitHub Releases that the plugin's updater (`includes/updater.php`) installs; this repo is public for that reason

## Local setup

No WordPress install needed for most work. The harnesses stub WordPress and Cornerstone:

```bash
php arv-harness.php           # writes preview.html with every element rendered
python3 -m http.server 8899   # then open http://localhost:8899/preview.html
```

Data scripts read credentials from a local env file (see `.env.example`); several load the claudeclaw root `.env` directly.

## Env vars

Names only, used by `scripts/` (the plugin itself reads none). See `.env.example`.

- WordPress REST: `ARAVAIPA_WP_URL`, `ARAVAIPA_WP_USER`, `ARAVAIPA_WP_APP_PASSWORD`, `ARAVAIPA_WP_ADMIN_USER`, `ARAVAIPA_WP_ADMIN_APP_PASSWORD`
- Timing: `MOMENTUM_TIMING_URL`, `MOMENTUM_TIMING_USER`, `MOMENTUM_TIMING_PASS`
- Media and shop: `SMUGMUG_API_KEY`, `YOUTUBE_API_KEY`, `SQUARE_ACCESS_TOKEN`, `SQUARE_LOCATION_ID`
- Photo ingest (set by `ingest-photos.mjs` for `photos-ingest.php`): `ARV_INGEST_MODE`, `ARV_INGEST_COMMIT`, `ARV_INGEST_PAYLOAD`

## Tests

```bash
php arv-edge.php              # element render assertions
php arv-store-test.php        # race store assertions
php arv-updater-test.php      # self-updater assertions
bun scripts/test/<name>.test.mjs   # Node data-script tests (name matcher, photo ingest, SmugMug, Zenfolio, ...)
python3 scripts/check-css-vars.py  # CSS custom property check
./build.sh                    # packages the zip and fails on version or payload mistakes
```

There is no CI workflow in this repo; run the harnesses before every release.

## Deploy

1. Bump `Version:` and `ARV_ELEMENTS_VERSION` in `aravaipa-elements.php` (patch digit for fixes).
2. `./build.sh` writes `build/aravaipa-elements.zip`.
3. Cut a GitHub Release with that zip attached. WordPress offers the update on its Plugins screen within 12 hours ("Check Again" forces it).
4. After the update, clear the site caches and check the change on the plain URL.

## Related repos

- `racegoat` (Frothy): registration embeds and live prices read by race pages and structured data
- `mountain-outpost`: broadcast platform linked from watch pages; source of the YouTube key some scripts use
- `aravaipa-ops-engine`: Aravaipa staff ops dashboard
- Element and data-pipeline details: `docs/ELEMENTS.md`
