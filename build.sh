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
cp includes/helpers.php includes/updater.php includes/seo.php includes/race-store.php includes/race-admin.php "$STAGE/includes/"
cp includes/elements/*.php "$STAGE/includes/elements/"
cp assets/aravaipa-elements.css assets/aravaipa-countdown.js assets/aravaipa-calendar.js assets/aravaipa-race-map.js assets/us-outline.svg "$STAGE/assets/"
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

# Every includes/*.php the plugin require_once's must actually be in the
# payload. Unlike the element loop above, these are not guarded by file_exists
# at runtime: a missing one is a fatal that takes the whole site down, not a
# quietly absent element. Shipping v0.7.0 without includes/seo.php, which the
# hand-maintained cp line above had not been updated for, is exactly what this
# is here to stop happening twice.
while read -r inc; do
	[ -f "$STAGE/includes/$inc" ] || { echo "includes/$inc is required by $NAME.php but not packaged" >&2; missing=1; }
done < <(grep -oE "ARV_ELEMENTS_PATH \. 'includes/[a-z-]+\.php'" "$NAME.php" | sed -E "s#.*includes/##; s#'##")
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

( cd "$OUT" && zip -qr "$NAME.zip" "$NAME" )

echo "built $OUT/$NAME.zip (v$VERSION, $(find "$STAGE" -type f | wc -l | tr -d ' ') files, $(du -h "$OUT/$NAME.zip" | cut -f1))"
