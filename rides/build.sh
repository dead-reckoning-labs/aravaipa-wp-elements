#!/usr/bin/env bash
#
# Package the Aravaipa Rides plugin: writes build/aravaipa-rides.zip at the repo root
# with an aravaipa-rides/ folder. Ships only what WordPress runs: no tests,
# no build script, no env or token files.
#
set -euo pipefail
cd "$(dirname "$0")/.."

NAME="aravaipa-rides"
OUT="build"
STAGE="$OUT/$NAME"

VERSION=$(grep -m1 "Version:" rides/$NAME.php | sed -E 's/.*Version: *//' | tr -d ' \r')
CONST=$(grep -m1 "RIDES_VERSION'" rides/$NAME.php | sed -E "s/.*'([0-9][^']*)'.*/\1/")
if [ "$VERSION" != "$CONST" ]; then
	echo "version mismatch: header $VERSION, RIDES_VERSION $CONST" >&2
	exit 1
fi

for f in rides/*.php rides/includes/*.php rides/templates/*.php; do
	php -l "$f" >/dev/null
done

rm -rf "$STAGE" "$OUT/$NAME.zip"
mkdir -p "$STAGE"
cp rides/$NAME.php "$STAGE/"
cp -R rides/includes rides/templates rides/assets rides/data "$STAGE/"
printf '<?php\n// Silence is golden.\n' > "$STAGE/index.php"
find "$STAGE" \( -name '.DS_Store' -o -name '.env*' -o -name '*token*' -o -name '*.bak' \) -exec rm -f {} +

(cd "$OUT" && zip -qr "$NAME.zip" "$NAME")
rm -rf "$STAGE"
echo "built $OUT/$NAME.zip ($VERSION, $(du -h "$OUT/$NAME.zip" | cut -f1))"
