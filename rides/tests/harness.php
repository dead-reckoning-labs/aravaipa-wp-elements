<?php
/**
 * Local harness: renders the phase 1 pages to static HTML with file:// assets.
 *
 *   php rides/tests/harness.php <outdir> [today Y-m-d] [--look=take2|blend]
 *
 * A look outside the allowlist falls back to take2, like rides_current_look().
 */

define( 'RIDES_HARNESS', true );
require dirname( __DIR__ ) . '/aravaipa-rides.php';

$look = 'take2';
$args = array();
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( 0 === strpos( $a, '--look=' ) ) {
		$want = substr( $a, 7 );
		$look = in_array( $want, array( 'take2', 'blend' ), true ) ? $want : 'take2';
	} else {
		$args[] = $a;
	}
}
$argv = array_merge( array( $argv[0] ), $args );
$out  = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
if ( ! $out ) {
	fwrite( STDERR, "usage: php rides/tests/harness.php <outdir> [today]\n" );
	exit( 2 );
}
if ( ! is_dir( $out ) && ! mkdir( $out, 0755, true ) ) {
	fwrite( STDERR, "cannot create $out\n" );
	exit( 1 );
}

$ctx   = array(
	'base'  => 'file://' . RIDES_DIR . '/',
	'today' => isset( $argv[2] ) ? $argv[2] : gmdate( 'Y-m-d' ),
	'look'  => $look,
);
$pages = array(
	'home'          => 'home',
	'events'        => 'events',
	'results'       => 'results',
	'photos'        => 'photos',
	'prickly-pedal' => 'event:prickly-pedal',
	'insomniac'     => 'event:insomniac',
	'big-woody'     => 'event:big-woody',
);
foreach ( array( 'adrenaline', 'sinister', 'stunner', 'frenzy-hills', 'black-canyon-crusher', 'royal-gorge-groove', 'tonto', 'rough-canyon', 'group-rides' ) as $slug ) {
	$pages[ $slug ] = 'event:' . $slug;
}
$css   = rides_asset( $ctx, 'assets/rides.css' );
$js    = rides_asset( $ctx, 'assets/rides.js' );
$logo  = 'https://aravaiparides.com/wp-content/uploads/2019/08/2019_AravaipaLogo_Redesign_Aravaipa_RidesLogo-1.png';

foreach ( $pages as $file => $layout ) {
	list( $desc, $img ) = rides_layout_meta( $layout, $ctx );
	$body               = rides_render_layout( $layout, $ctx );
	if ( '' === $body ) {
		fwrite( STDERR, "empty render for $layout\n" );
		exit( 1 );
	}
	$html = "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">"
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>' . rides_e( ucfirst( $file ) ) . ' | Aravaipa Rides</title>'
		. '<meta name="description" content="' . rides_e( $desc ) . '">'
		. '<meta property="og:image" content="' . rides_e( $img ) . '">'
		. '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&amp;display=swap">'
		. '<link rel="stylesheet" href="' . rides_e( $css ) . '">'
		. '<style>body{margin:0}.h-bar{height:80px;display:flex;align-items:center;padding:0 24px;background:#fff;border-bottom:1px solid #eee}.h-bar img{height:46px;width:auto}.h-foot{padding:40px 24px;background:#000;color:#fff;font:14px Lato,Arial,sans-serif}</style>'
		. '</head><body>'
		. '<header class="h-bar"><img src="' . rides_e( $logo ) . '" alt="Aravaipa Rides"></header>'
		. $body
		. '<footer class="h-foot x-colophon">Harness footer</footer>'
		. '<script src="' . rides_e( $js ) . '"></script></body></html>';
	file_put_contents( "$out/$file.html", $html );
}
echo "wrote " . count( $pages ) . " pages to $out\n";
