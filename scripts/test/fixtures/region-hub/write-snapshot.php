<?php
/**
 * Writes upcoming-races.snapshot.json from the current code. Run once on the
 * base commit, before touching the card render; the test then proves the
 * output did not move. Test only.
 */
require __DIR__ . '/bootstrap.php';
$cases = require __DIR__ . '/cases.php';
$out   = array();
foreach ( $cases as $k => $data ) {
	$out[ $k ] = arv_upcoming_races_render( $data );
}
file_put_contents( __DIR__ . '/upcoming-races.snapshot.json', json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo 'wrote ', count( $out ), " cases\n";
