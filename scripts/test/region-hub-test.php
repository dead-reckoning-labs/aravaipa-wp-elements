<?php
/**
 * Region hub ([arv_region_hub]) and the shared race card it draws with.
 *
 *   php scripts/test/region-hub-test.php
 *
 * 1. Upcoming Races output is byte for byte what it was before the card
 *    render moved into arv_races_card() (snapshot taken on the base commit).
 * 2. The hub renders the Ultra Adventures data file against a store shaped
 *    like the live one: order, exact Register links, Race Info, photos with
 *    logo plates, the date-coming-soon tile with no Register, the map pins.
 */
require __DIR__ . '/fixtures/region-hub/bootstrap.php';

function remove_query_arg( $key, $url = null ) {
	$parts = explode( '?', (string) $url, 2 );
	return $parts[0];
}
function add_query_arg( $k, $v = null, $url = null ) {
	return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( (string) $v );
}
// Stand-in for the Racing Team form's loader, which the hub borrows for the
// state outlines; same file, same decode.
function arv_rt_apply_data( $file ) {
	$d = json_decode( (string) file_get_contents( ARV_ELEMENTS_PATH . 'includes/data/' . basename( $file ) ), true );
	return is_array( $d ) ? $d : array();
}
require_once ARV_ELEMENTS_PATH . 'includes/region-hub.php';

$pass = 0;
$fail = 0;
function t( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "  ok   $name\n";
	} else {
		++$fail;
		echo "  FAIL $name\n";
	}
}

echo "upcoming races output unchanged:\n";
$cases = require __DIR__ . '/fixtures/region-hub/cases.php';
$snap  = json_decode( file_get_contents( __DIR__ . '/fixtures/region-hub/upcoming-races.snapshot.json' ), true );
foreach ( $cases as $k => $data ) {
	t( "case $k is byte identical", isset( $snap[ $k ] ) && arv_upcoming_races_render( $data ) === $snap[ $k ] );
}

echo "shared card defaults:\n";
$race = array(
	'name'      => 'Zion Ultras',
	'iso'       => '2027-04-10',
	'display'   => 'April 10-11',
	'distances' => '100 Mile | 100K',
	'venue'     => 'Ruby Rider Ranch',
	'location'  => 'Apple Valley, UT',
	'register'  => 'https://ultrasignup.com/register.aspx?did=138715',
	'page'      => 'https://www.aravaiparunning.com/zion/',
	'image'     => 'https://x.test/zion.png',
	'end'       => '2027-04-11',
	'live'      => '',
	'closes'    => '2027-04-05',
	'confirmed' => true,
	'guessed'   => false,
	'lat'       => '37.1142363',
	'lng'       => '-113.1072639',
);
$c = arv_races_card( $race, '2026-11-25' );
t( 'still says Race Details by default', false !== strpos( $c['html'], '>Race Details</a>' ) );
t( 'no photo markup by default', false === strpos( $c['html'], 'arv-races__photo' ) );
t( 'schema event returned', is_array( $c['event'] ) );
$c = arv_races_card( $race, '2026-11-25', array( 'details_label' => 'Race Info', 'photo' => 'https://x.test/p.jpg', 'photo_alt' => 'Cliffs' ) );
t( 'Race Info label', false !== strpos( $c['html'], '>Race Info</a>' ) && false === strpos( $c['html'], 'Race Details' ) );
t( 'photo in the media slot with its alt', false !== strpos( $c['html'], 'class="arv-races__photo" src="https://x.test/p.jpg" alt="Cliffs"' ) );
t( 'logo rides on it as a plate', false !== strpos( $c['html'], 'arv-races__plate' ) && false !== strpos( $c['html'], 'https://x.test/zion.png' ) );

echo "hub data file:\n";
$hub = arv_region_hub_data( 'ultra-adventures' );
t( 'loads', is_array( $hub ) );
t( 'unknown region is null', null === arv_region_hub_data( 'nowhere' ) );
t( 'no path out of the hubs folder', null === arv_region_hub_data( '../rt-regions' ) && null === arv_region_hub_data( '../../aravaipa-elements' ) );
$raw = file_get_contents( ARV_ELEMENTS_PATH . 'includes/data/hubs/ultra-adventures.json' );
t( 'no em dash in the copy', false === strpos( $raw, "\u{2014}" ) );
t( 'no inline base64 images', false === strpos( $raw, 'data:image' ) );
foreach ( array( 'title', 'intro', 'hero', 'races', 'socials', 'newsletter' ) as $k ) {
	t( "has $k", ! empty( $hub[ $k ] ) );
}

echo "hub render:\n";
// The live store as read on 2026-11-25 (job tmvzwk out/races.json), plus a
// race that is over and one from another region, to prove neither leaks in.
$store = array(
	$race,
	array_merge(
		$race,
		array(
			'name'      => 'Antelope Canyon Ultras',
			'iso'       => '2027-03-06',
			'display'   => 'March 6',
			'end'       => '',
			'distances' => '50 Mile | 55K | 30K | Half Marathon',
			'venue'     => 'Page Sports Complex',
			'location'  => 'Page, AZ',
			'register'  => 'https://ultrasignup.com/register.aspx?did=138695',
			'page'      => 'https://www.aravaiparunning.com/antelope/',
			'image'     => 'https://x.test/antelope.png',
			'lat'       => '36.9005756',
			'lng'       => '-111.4588201',
		)
	),
	array_merge(
		$race,
		array(
			'name'      => 'Bryce Canyon Ultras',
			'iso'       => '2027-09-18',
			'display'   => 'September 18-19',
			'end'       => '2027-09-19',
			'distances' => '100M | 50M | 60K | 50K | 30K | Half Marathon',
			'venue'     => 'Lucky 7 Ranch',
			'location'  => 'Hatch, UT',
			'register'  => 'https://racegoat.com/events/bryce-canyon-ultras-2027',
			'page'      => 'https://www.aravaiparunning.com/bryce/',
			'image'     => 'https://x.test/bryce.png',
			'lat'       => '37.6720948',
			'lng'       => '-112.4068428',
		)
	),
	array_merge( $race, array( 'name' => 'Javelina Jundred', 'iso' => '2027-10-30', 'register' => 'https://x.test/jj' ) ),
);
$html = arv_region_hub_render( $hub, $store, '2026-11-25' );
t( 'renders', '' !== $html );
t( 'one h1, the hub title', 1 === substr_count( $html, '<h1' ) && false !== strpos( $html, '<h1 class="arv-hub__h1">Ultra Adventures</h1>' ) );
t( 'no empty headings', ! preg_match( '#<h[1-6][^>]*>\s*</h[1-6]>#', $html ) );
t( 'no em dash in the output', false === strpos( $html, "\u{2014}" ) );

$races_at = strpos( $html, 'id="races"' );
foreach ( array( 'arv-hub__map', 'arv-hub__story', 'arv-hub__video', 'arv-hub__gallery', 'arv-hub__connect' ) as $sec ) {
	$at = strpos( $html, $sec );
	t( "races come before $sec", false !== $at && $races_at < $at );
}

preg_match_all( '#<h3 class="arv-races__name"><a [^>]*>([^<]+)</a></h3>#', $html, $m );
t( 'order is the hub file order (date order)', array( 'Antelope Canyon Ultras', 'Zion Ultras', 'Tushars Ultras', 'Bryce Canyon Ultras' ) === $m[1] );
t( 'other regions stay off', false === strpos( $html, 'Javelina' ) );

foreach ( array(
	'https://ultrasignup.com/register.aspx?did=138695',
	'https://ultrasignup.com/register.aspx?did=138715',
	'https://racegoat.com/events/bryce-canyon-ultras-2027',
) as $url ) {
	t( "Register goes to $url exactly", 1 === substr_count( $html, 'href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="Register for ' ) );
}
t( 'three Register buttons, not four', 3 === substr_count( $html, '>Register</a>' ) );
t( 'four Race Info buttons', 4 === substr_count( $html, '>Race Info</a>' ) );

// The Tushars card alone.
$start  = strpos( $html, 'arv-hub__card--soon' );
$end    = strpos( $html, 'arv-races__card', $start + 30 );
$tush   = substr( $html, $start, $end - $start );
t( 'Tushars says its date is coming', false !== strpos( $tush, '2027 date coming soon' ) );
t( 'Tushars has no time element to guess at', false === strpos( $tush, '<time' ) );
t( 'Tushars has no Register button', false === strpos( $tush, 'Register' ) && false === strpos( $tush, 'ultrasignup.com/register' ) );
t( 'Tushars links its race page', false !== strpos( $tush, 'href="https://www.aravaiparunning.com/tushars/"' ) );
t( 'Tushars carries results and photos', false !== strpos( $tush, '/race-results/tushars-mountain-runs/' ) && false !== strpos( $tush, '>Photos</a>' ) );

t( 'every tile has a photo', 4 === substr_count( $html, 'class="arv-races__photo"' ) );
t( 'and a logo plate', 4 === substr_count( $html, 'arv-races__plate' ) );
t( 'hero is an img with alt and srcset', (bool) preg_match( '#<img class="arv-hub__hero-img" src="[^"]+" srcset="[^"]+" sizes="100vw" alt="Runners[^"]+"#', $html ) );

$json = array();
if ( preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $sm ) ) {
	$json = json_decode( $sm[1], true );
}
t( 'event schema for the three dated races only', isset( $json['@graph'] ) && 3 === count( $json['@graph'] ) );

t( 'video is a facade until clicked', false !== strpos( $html, 'data-arv-hub-video="kaKkCW4x_mI"' ) && false === strpos( $html, '<iframe' ) );
t( 'facade still works without script', false !== strpos( $html, 'href="https://www.youtube.com/watch?v=kaKkCW4x_mI"' ) );
t( 'newsletter keeps the form the page uses', false !== strpos( $html, 'https://aravaiparunning.activehosted.com/f/62' ) );
t( 'Instagram is the Ultra Adventures account', false !== strpos( $html, 'https://www.instagram.com/ultraadventures/' ) );

echo "map:\n";
t( 'four numbered pins', 4 === substr_count( $html, 'class="arv-hub__pin"' ) );
// Projection sanity: the same projection drew the outlines, so a Zion pin
// must land inside Utah's box and Page inside Arizona's.
$atlas = arv_rt_apply_data( 'us-states-albers.json' );
$bbox  = function ( $d ) {
	preg_match_all( '/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $d, $mm );
	return array( min( $mm[1] ), min( $mm[2] ), max( $mm[1] ), max( $mm[2] ) );
};
$in = function ( $xy, $b ) {
	return $xy[0] >= $b[0] && $xy[0] <= $b[2] && $xy[1] >= $b[1] && $xy[1] <= $b[3];
};
t( 'Zion lands in Utah', $in( arv_region_hub_project( -113.1072639, 37.1142363 ), $bbox( $atlas['states']['UT'] ) ) );
t( 'Page lands in Arizona', $in( arv_region_hub_project( -111.4588201, 36.9005756 ), $bbox( $atlas['states']['AZ'] ) ) );
t( 'Phoenix is not in Utah', ! $in( arv_region_hub_project( -112.074, 33.448 ), $bbox( $atlas['states']['UT'] ) ) );

echo "fails quietly:\n";
t( 'no races, no hub', '' === arv_region_hub_render( array( 'title' => 'X', 'races' => array( array( 'name' => 'Nope' ) ) ), $store, '2026-11-25' ) );
t( 'a finished race drops off', false === strpos( arv_region_hub_render( $hub, $store, '2027-04-12' ), '>Antelope Canyon Ultras</a></h3>' ) );
t( 'shortcode with a bad region renders nothing', '' === arv_region_hub_shortcode( array( 'region' => '../x' ) ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
