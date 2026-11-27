#!/usr/bin/env bash
# Rebuild includes/data/contact-rides.json (ride names and dates for the
# Contact Us event picker) from the rides plugin's rides/data/rides.json.
# The rides plugin runs on aravaiparides.com, so this site cannot read it
# live; run this when the rides list changes. Past rides are skipped at
# render time, so a stale file only ever shows fewer rides, never old ones.
set -euo pipefail
cd "$(dirname "$0")/.."
php -r '
$d = json_decode( file_get_contents( "rides/data/rides.json" ), true );
$out = array();
foreach ( $d["events"] as $e ) {
	if ( ! empty( $e["date"] ) ) {
		$out[] = array( "name" => $e["name"], "date" => $e["date"] );
	}
}
file_put_contents( "includes/data/contact-rides.json", json_encode( array( "source" => "rides/data/rides.json (scripts/contact-rides.sh)", "rides" => $out ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo count( $out ) . " rides\n";
'
