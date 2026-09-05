#!/usr/bin/env bash
#
# Package the plugin for WP Admin, Plugins, Add New, Upload Plugin.
#
# The first zip was assembled by hand, which is how it ended up shipping
# five elements and a stale stylesheet two weeks after a sixth was added.
# This builds the payload from the working tree every time instead, so the
# zip cannot drift from the source again.
#
# Ships only what WordPress runs. The dev harnesses (arv-harness.php,
# arv-edge.php, arv-standalone.php) stub WordPress and Cornerstone
# functions, so shipping them into a live plugin directory would put a
# second, conflicting definition of esc_html() and friends one stray
# include away from a fatal on the site that sells the race entries.
#
#   ./build.sh          # writes build/aravaipa-elements.zip
#
set -euo pipefail

cd "$(dirname "$0")"

NAME="aravaipa-elements"
OUT="build"
STAGE="$OUT/$NAME"

VERSION=$(grep -m1 "Version:" "$NAME.php" | sed -E 's/.*Version: *//' | tr -d ' \r')
CONST_VERSION=$(grep -m1 "ARV_ELEMENTS_VERSION" "$NAME.php" | sed -E "s/.*'([0-9][^']*)'.*/\1/")

# The plugin header is what WordPress shows on the Plugins screen and what
# the updater compares against a Release tag; the constant is what cache
# busts every enqueued asset. Shipping with those out of step means a
# release whose CSS and JS are served under the previous version's query
# string, so browsers keep the old files and the update looks like it did
# nothing. They were out of step once, silently, which is why this is here.
if [ "$VERSION" != "$CONST_VERSION" ]; then
	echo "version mismatch: header says $VERSION, ARV_ELEMENTS_VERSION says $CONST_VERSION" >&2
	exit 1
fi

if [ -z "$VERSION" ]; then
	echo "could not read Version from $NAME.php header" >&2
	exit 1
fi

# Lint every file that will ship. A parse error reaching the live site
# takes down the whole thing, not just the builder.
for f in "$NAME.php" includes/*.php includes/elements/*.php; do
	php -l "$f" > /dev/null
done

rm -rf "$STAGE" "$OUT/$NAME.zip"
mkdir -p "$STAGE/includes/elements" "$STAGE/assets/logos" "$STAGE/assets/plugin"

cp "$NAME.php" "$STAGE/"
# Read from the require_once calls rather than a list kept beside them. The
# list was maintained by hand and drifted three times: v0.7.0 shipped without
# includes/seo.php, and includes/page-seo.php and includes/photos-admin.php
# were both caught by the guard below on the build that would have shipped
# them. None of those is a missing feature, they are all a fatal on every
# page of a site that sells race entries, because these are require_once and
# not the file_exists-guarded element loads. Adding a require is now the only
# step.
# while-read rather than mapfile: macOS ships bash 3.2, where mapfile does
# not exist, and this script runs there.
INCLUDE_RE="ARV_ELEMENTS_PATH \. 'includes/[A-Za-z0-9_-]+\.php'"
INCLUDES=()
while IFS= read -r inc; do
	INCLUDES+=("$inc")
done < <(grep -oE "$INCLUDE_RE" "$NAME.php" | sed -E "s#.*includes/##; s#'##" | sort -u)

# The derivation is now the only thing standing between a require and the
# payload, so it has to fail loudly rather than quietly come up short. Any
# require_once naming a file under includes/ that the pattern above did not
# match would otherwise be dropped by this and by the guard further down,
# which reads the same pattern and would agree with it.
declared=$(grep -cE "require_once +ARV_ELEMENTS_PATH \. 'includes/" "$NAME.php")

if [ "${#INCLUDES[@]}" -ne "$declared" ]; then
	echo "$NAME.php require_once's $declared files under includes/ but only ${#INCLUDES[@]} parsed: a filename does not match $INCLUDE_RE" >&2
	exit 1
fi

for inc in "${INCLUDES[@]}"; do
	[ -f "includes/$inc" ] || { echo "$NAME.php requires includes/$inc, which does not exist" >&2; exit 1; }
	cp "includes/$inc" "$STAGE/includes/"
done
cp includes/elements/*.php "$STAGE/includes/elements/"
cp assets/aravaipa-elements.css assets/aravaipa-countdown.js assets/aravaipa-calendar.js assets/aravaipa-race-map.js assets/aravaipa-region-map.js assets/aravaipa-results.js assets/aravaipa-footer.js assets/aravaipa-watch.js assets/aravaipa-live.js assets/aravaipa-films.js assets/aravaipa-photos.js assets/aravaipa-media-latest.js assets/aravaipa-articles.js assets/aravaipa-shop.js assets/us-outline.svg "$STAGE/assets/"
cp assets/logos/*.png "$STAGE/assets/logos/"
cp assets/plugin/*.png "$STAGE/assets/plugin/"

# WordPress convention: an empty index.php in every directory so a server
# with directory listing enabled serves nothing instead of the file tree.
for d in "$STAGE" "$STAGE/includes" "$STAGE/includes/elements" "$STAGE/assets" "$STAGE/assets/logos" "$STAGE/assets/plugin"; do
	printf '<?php // Silence is golden.' > "$d/index.php"
done

# Every element file the plugin will try to require must actually be in the
# payload. A missing one is guarded at runtime (file_exists) so it fails
# silently, which is exactly the kind of quiet breakage worth catching here.
missing=0
while read -r el; do
	[ -f "$STAGE/includes/elements/$el.php" ] || { echo "element '$el' registered but not packaged" >&2; missing=1; }
done < <(sed -n "/\$elements = array(/,/);/p" "$NAME.php" | grep -oE "'[a-z-]+'" | tr -d "'")
[ "$missing" -eq 0 ] || exit 1

# And the inverse: an element file nobody loads. The shop rail shipped with
# a shortcode and no element at all, which made it the one rail a builder
# could not place, and the check above could not see it because an element
# that was never registered is never missed. This catches the next one:
# writing the file and forgetting the array is the same mistake one step
# later.
orphan=0
for f in includes/elements/*.php; do
	el=$(basename "$f" .php)
	# index.php is the directory-listing guard, not an element.
	[ "$el" = "index" ] && continue
	sed -n "/\$elements = array(/,/);/p" "$NAME.php" | grep -q "'$el'" \
		|| { echo "element file '$el.php' exists but is never loaded" >&2; orphan=1; }
done
[ "$orphan" -eq 0 ] || exit 1

# str_replace( '<', '<', ... ) is a no-op that reads exactly like the fix it
# is meant to be, and shipped as one in race-map.php until a test caught it.
# The real escape is \u003C. Cheap to check, and the failure mode is an XSS
# hole in a JSON-LD or config block, so it is worth checking every build.
# Comment lines are stripped first, so the explanation of this very trap
# written above the real call is not mistaken for the trap itself.
if grep -rn "str_replace( *'<', *'<'" "$NAME.php" includes/ 2>/dev/null | grep -v ':[0-9]*:[[:space:]]*[*/]'; then
	echo "no-op '<' escape found: use '\\u003C' as the replacement" >&2
	exit 1
fi

# Belt and braces on the copy above, which now derives this same list. Cheap,
# and it catches the case where the copy loop ran but something later in this
# script removed or overwrote a file in the payload.
while read -r inc; do
	[ -f "$STAGE/includes/$inc" ] || { echo "includes/$inc is required by $NAME.php but not packaged" >&2; missing=1; }
done < <(printf '%s\n' "${INCLUDES[@]}")
[ "$missing" -eq 0 ] || exit 1

# The artwork WordPress renders on its own screens. A missing icon is not a
# fatal, it just silently reverts to the generic grey plug, which is the exact
# state this replaced and would be easy not to notice again.
for art in icon-128x128.png icon-256x256.png banner-772x250.png banner-1544x500.png; do
	[ -f "$STAGE/assets/plugin/$art" ] || { echo "plugin artwork '$art' missing from the payload" >&2; missing=1; }
done
[ "$missing" -eq 0 ] || exit 1

# Every bundled logo an element references must actually be in the payload,
# for the same reason the element check above exists: a missing one renders
# as a broken image on a live page rather than failing anywhere visible.
while read -r logo; do
	[ -f "$STAGE/assets/logos/$logo" ] || { echo "logo '$logo' referenced but not packaged" >&2; missing=1; }
# Comment lines are stripped first, so the "ARV_LOGO:name.png" written as
# an example in the resolver's own doc block is not mistaken for a real
# reference to a file called name.png.
done < <(grep -hv '^[[:space:]]*[*/]' includes/elements/*.php | grep -oE 'ARV_LOGO:[A-Za-z0-9._-]+' | sed 's/ARV_LOGO://' | sort -u)
[ "$missing" -eq 0 ] || exit 1

# Every script the plugin enqueues has to be in the payload. This list is
# written out by hand above, so adding an enqueue without adding it to that
# cp is a one-line mistake that produces a 404 for the file and a feature
# that silently does nothing on the live site, with the plugin otherwise
# looking fine. Derived from the enqueues themselves so the two cannot drift.
while read -r js; do
	[ -f "$STAGE/assets/$js" ] || { echo "assets/$js is enqueued by $NAME.php but not packaged" >&2; missing=1; }
done < <(grep -oE "ARV_ELEMENTS_URL \. 'assets/[a-z-]+\.js'" "$NAME.php" | sed -E "s#.*assets/##; s#'##")
[ "$missing" -eq 0 ] || exit 1

# .arv-calendar__row sets its own unconditional `display: flex`, which has
# equal specificity to the browser's `[hidden] { display: none }` default
# and, being an author rule, always wins the tie regardless of source order.
# Shipped this way in v0.21.1: the calendar's search, state and month filters
# all set `row.hidden = true` correctly and nothing visibly happened. Cheap
# to check for the specific override that fixes it.
grep -q '\.arv-calendar__row\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-calendar__row[hidden] override is missing, calendar filters will silently do nothing" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

# Same trap, same fix, for the Watch archive's own unconditional
# ".arv-watch__race { display: flex }": without the [hidden] override below
# it, aravaipa-watch.js's search and year filter would set the attribute on
# every card it means to hide and nothing would visibly happen.
grep -q '\.arv-watch__race\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-watch__race[hidden] override is missing, the Watch search and year filter will silently do nothing" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

# Third time for the same trap, on the Films shelf's own cards: without
# this the search, sort and race filter would set the hidden attribute on
# every card they mean to hide and nothing would visibly happen.
# The Watch hero is hidden by the same filter, and unlike the cards it has
# no display rule of its own today, so nothing visibly breaks until someone
# gives .arv-media-hero one. That is exactly the change this catches.
grep -q '\.arv-watch__hero\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-watch__hero[hidden] override is missing, the featured broadcast will stay put while the archive filters under it" >&2; missing=1; }

grep -q '\.arv-films__card\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-films__card[hidden] override is missing, the Films search and filters will silently do nothing" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

# Fourth time, on the Photos grid. Same specificity tie, same silent
# failure: the search and photographer filter would set the attribute on
# every card and the grid would not change.
grep -q '\.arv-photos__card\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-photos__card[hidden] override is missing, the Photos search and filter will silently do nothing" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

# Fifth time, on the Latest feed. Same specificity tie, same silent
# failure: the type filter would set the attribute on every card it
# means to hide and the feed would not visibly change.
grep -q '\.arv-articles__card\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-articles__card[hidden] override is missing, the Articles search, category and year filters will silently do nothing" >&2; missing=1; }

grep -q '\.arv-media-latest__card\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-media-latest__card[hidden] override is missing, the Latest feed's type filter will silently do nothing" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

# The live board's touch shield is rendered hidden and revealed by
# aravaipa-live.js, so a page whose JavaScript never runs has no
# undismissable layer sitting over its results. That only holds while the
# CSS actually honours the attribute: .arv-live__shield sets its own
# unconditional `display: flex`, the same tie against the browser's
# `[hidden] { display: none }` that the two checks above exist for, and
# losing this one would cover the board on every device instead of fixing
# scrolling on phones.
grep -q '\.arv-live__shield\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-live__shield[hidden] override is missing, the touch shield would cover the live board permanently" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

# A theme's own "a:hover { color: ... }" is one element plus one
# pseudo-class, which outranks a bare class on specificity, so a button
# whose colour is only ever set via ".arv-featured__cta { color: #fff }"
# loses its own text colour to the theme's link colour on hover. Shipped
# this way in v0.21.36: the featured race's red button turned red-on-red
# and its teal card button turned red-on-teal for as long as the cursor
# sat on either, which is the one moment someone is deciding whether to
# click. Cheap to check that the :link/:visited reinforcement fixing it
# is still there for both.
# Every --arv-* custom property a rule reads without a fallback has to be
# set somewhere: in the stylesheet, inline from PHP, or from JS. An
# undefined one with no fallback is invalid at computed-value time, and
# the property then resolves to `unset`, which for an inherited property
# like font-size means `inherit` rather than the browser's own rule. That
# is silent: no console warning, nothing in a diff, the heading just
# quietly renders at body size. --arv-fs-h2 shipped that way and three
# headings, Photos, the Latest feed and the Media hero's title, were all
# 15px because of it.
grep -q '\.arv-shop__detail\[hidden\]' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-shop__detail[hidden] override is missing, the item detail drawer would stay on screen permanently" >&2; missing=1; }

python3 scripts/check-css-vars.py || missing=1

grep -q '\.arv-featured__cta:link' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-featured__cta:link is missing, the button will lose its colour to the theme's a:hover on hover" >&2; missing=1; }
grep -q '\.arv-featured__card-cta:link' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-featured__card-cta:link is missing, the button will lose its colour to the theme's a:hover on hover" >&2; missing=1; }

# Same trap on the results archive files. These are the only route to the
# result files for the years Aravaipa scored its own races, twenty of them,
# and they are hollow: dark text on white. Losing the text colour to the
# theme's a:hover turns a hovered one into red-on-white against a row of
# black-on-white siblings, on the one link the visitor is about to click.
#
# Was .arv-results__archive-link until these stopped being a smaller chip
# beside the buttons and became the same control as them, so the guard
# follows the class rather than being dropped: the hazard is the hollow
# treatment, not the name it went under.
grep -q '\.arv-results__link--file:link' assets/aravaipa-elements.css \
	|| { echo "assets/aravaipa-elements.css: .arv-results__link--file:link is missing, the result-file links will lose their colour to the theme's a:hover on hover" >&2; missing=1; }
[ "$missing" -eq 0 ] || exit 1

( cd "$OUT" && zip -qr "$NAME.zip" "$NAME" )

echo "built $OUT/$NAME.zip (v$VERSION, $(find "$STAGE" -type f | wc -l | tr -d ' ') files, $(du -h "$OUT/$NAME.zip" | cut -f1))"
