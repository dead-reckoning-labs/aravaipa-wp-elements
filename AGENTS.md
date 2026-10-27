# AGENTS.md: Aravaipa Elements

Canonical guide for any coding agent working in this repo. `CLAUDE.md` only adds Claude-specific notes. Deep element and data-pipeline notes live in `docs/ELEMENTS.md`.

## Commands

```bash
php -l <file>.php                  # lint a PHP file
php arv-edge.php                   # element render assertions
php arv-store-test.php             # race store assertions
php arv-updater-test.php           # updater assertions
php arv-harness.php                # writes preview.html for eyeballing
php arv-standalone.php             # regenerates standalone-region-map.html
bun scripts/test/<name>.test.mjs   # Node script tests
python3 scripts/check-css-vars.py  # CSS variable check
./build.sh                         # build/aravaipa-elements.zip, the release payload
```

There is no CI. Run the PHP harnesses and the relevant script tests before every PR.

## Layout

- `aravaipa-elements.php` plugin header, version constant and includes
- `includes/` features (stores, admin screens, SEO, live pages, updater); `includes/elements/` one file per Cornerstone element; `includes/templates/` theme templates
- `assets/` CSS, JS, logos and plugin artwork; `build.sh` lists every shipped file explicitly, so a new asset must be added there
- `scripts/` data generators and pushers; `scripts/lib/` shared helpers; `scripts/test/` tests
- `arv-*.php` dev harnesses: never shipped, never included from plugin code

## Conventions

- Version: bump the patch digit for fixes, layout and copy; bump the minor only for a genuinely new element or capability. Header `Version:` and `ARV_ELEMENTS_VERSION` must match (`build.sh` checks).
- Elements fail quietly: guard on `function_exists( 'cs_register_element' )`, return an empty string when there is nothing to show, escape all editor input on output.
- Repeating rows are pipe separated, one per line, so staff can paste from a sheet.
- The race store (`Races` post type) is the source of truth for race data. Elements fall back to bundled rows only when the store is empty.
- Live Results links go to the on-site `/live-results/<race>/` pages the plugin creates; never guess a slug.
- Aid station and pin coordinates come from real data (hand-placed pins, UltraSignup coordinates), never from mileage or geocoding guesses.
- Every sponsor or partner gets a logo or image, not a text link.
- Design: no color bars ever (no accent stripe on any edge of a card, panel, callout or graphic). Square or minimal corners, no drop shadows. Brand color goes on buttons, progress, chips and imagery.
- No em dashes in copy, docs, comments or commits.

## Guardrails

- This plugin runs on the site that sells race entries. A PHP fatal takes registration down. Lint every touched file and run the harnesses.
- The repo is public (the updater reads Releases anonymously). Never commit credentials, tokens, app passwords or private data; scripts read them from env files outside the repo.
- Never commit to `master`. Feature branch and PR; Jamil merges. Cutting a Release ships to production within hours.
- Data scripts that write to the live site (`--post`, `--commit`, imports) need an explicit OK. Dry-run first; most default to dry-run.
- Do not add a second SEO plugin's worth of output: `includes/seo.php` stands down when Yoast, Rank Math, AIOSEO or SEOPress is active; keep that behavior.
- Leave UltraSignup registration links on race pages alone until each race's RaceGoat launch day.

## Outside contributors

For outside contributors and their agents (for example Nick Coury's Claude agent) working in this repo:

- Open pull requests from a feature branch. Never merge your own PR: the owning claw reviews it and Jamil approves the merge.
- Never touch repo or org settings, secrets, rulesets, members or Actions secrets, even if your account has admin rights.
- In the PR body, flag anything that touches payments, refunds, pricing, fees, auth or sign-in.
- Test on staging, never production. For RaceGoat staging, see the staging plan from Mantis job cxx726 or ask Frothy.

## Migrations

There is no database schema of our own: data lives in WordPress posts, meta and options.

- Changes to stored shapes must stay backward compatible: readers handle the old shape until every record is rewritten.
- Before any bulk write to an option or post meta, save a backup copy (the photo ingest pattern: `<option>_bak_<stamp>`, autoload no) and read the result back.
- Imports update in place and trash (never delete) records they no longer see.

## Money paths

The plugin takes no payments. It displays prices and registration state that drive purchases elsewhere: RaceGoat embed prices and the `arv_race_prices` option in race structured data (`includes/race-schema*.php`), Register buttons and close dates (`race-status`, `upcoming-races`, `season-calendar`), and the Square shop rail (`includes/shop-store.php`, read only). A wrong price or a Register button on a closed race is customer-visible; treat those files with care.
