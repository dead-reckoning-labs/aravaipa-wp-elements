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
 * 3. Round 2 (0.105.0): the old baked tiles, stats, photo bands, eras,
 *    past race photo cards, gallery, terrain map image and the tribute that
 *    renders only behind its gate, last on the page.
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
	array_merge( $race, array( 'register' => 'https://ultrasignup.com/register.aspx?dtid=66545' ) ),
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
			'register'  => 'https://ultrasignup.com/register.aspx?dtid=66537',
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
t( 'the store dtid links never show', false === strpos( $html, 'dtid=' ) );

echo "register_page override:\n";
$one = array( 'title' => 'X', 'races' => array( array( 'name' => 'Zion Ultras', 'register_page' => 'https://ultrasignup.com/register.aspx?did=138715' ) ) );
$got = arv_region_hub_races( $one, array( array_merge( $race, array( 'register' => 'https://racegoat.com/events/zion-ultras-2027' ) ) ) );
t( 'once the store moves to RaceGoat the store wins', 'https://racegoat.com/events/zion-ultras-2027' === $got[0]['race']['register'] );
$one['races'][0]['register_page'] = 'https://evil.test/register.aspx?did=1';
$got = arv_region_hub_races( $one, array( array_merge( $race, array( 'register' => 'https://ultrasignup.com/register.aspx?dtid=66545' ) ) ) );
t( 'only an UltraSignup did link can override', 'https://ultrasignup.com/register.aspx?dtid=66545' === $got[0]['race']['register'] );
t( 'four Race Info buttons', 4 === substr_count( $html, '>Race Info</a>' ) );

// The Tushars card alone.
$start  = strpos( $html, 'arv-hub__card--dated' );
$end    = strpos( $html, 'arv-races__card', $start + 30 );
$tush   = substr( $html, $start, $end - $start );
t( 'Tushars shows the Master Events date', false !== strpos( $tush, 'July 31, 2027' ) );
t( 'a dated soon tile is not greyed as undated', false === strpos( $html, 'arv-hub__card--soon' ) );
t( 'Tushars has no time element to guess at', false === strpos( $tush, '<time' ) );
t( 'Tushars has no Register button', false === strpos( $tush, 'Register' ) && false === strpos( $tush, 'ultrasignup.com/register' ) );
t( 'Tushars links its race page', false !== strpos( $tush, 'href="https://www.aravaiparunning.com/tushars/"' ) );
t( 'Tushars carries results and photos', false !== strpos( $tush, '/race-results/tushars-mountain-runs/' ) && false !== strpos( $tush, '>Photos</a>' ) );

t( 'every tile has a photo', 4 === substr_count( $html, 'class="arv-races__photo"' ) );
t( 'tiles are the old baked race tiles', 4 === substr_count( $html, 'arv-hub__card--tile' )
	&& false !== strpos( $html, 'Zion-Ultras-opt.jpg' ) && false !== strpos( $html, 'Antelope-Canyon-Ultras-opt.jpg' )
	&& false !== strpos( $html, 'Bryce-Canyon-Ultras-1-opt.jpg' ) && false !== strpos( $html, 'Tushars-Ultras-opt.jpg' ) );
t( 'no logo plate over a baked tile', 0 === substr_count( $html, 'arv-races__plate' ) );
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

echo "history, past races, films:\n";
t( 'history timeline renders', false !== strpos( $html, 'class="arv-hub__timeline"' ) && false !== strpos( $html, '>The first Zion 100.<' ) );
t( 'history links its archive source', false !== strpos( $html, 'https://web.archive.org/web/20141128071051/http://www.grandcircletrails.com/' ) );
t( 'three past races', 3 === substr_count( $html, 'class="arv-hub__past-race"' ) );
foreach ( array( 'Monument Valley Ultras', 'Grand Canyon Ultras', 'Capitol Reef Ultras' ) as $n ) {
	t( "past race $n", false !== strpos( $html, '<h3 class="arv-hub__past-name">' . $n . '</h3>' ) );
}
t( 'past results are results pages, never register links', false !== strpos( $html, 'results_event.aspx?did=32965' ) && false === strpos( $html, 'register.aspx?did=32965' ) );
t( 'every film is a facade', 0 === substr_count( $html, '<iframe' ) && 10 === substr_count( $html, 'data-arv-hub-video=' ) );
$past_html = substr( $html, strpos( $html, 'arv-hub__past"' ), strpos( $html, 'arv-hub__video' ) - strpos( $html, 'arv-hub__past"' ) );
t( 'past races show course photos, not YouTube thumbnails', 3 === substr_count( $past_html, 'class="arv-hub__past-media"' ) && false === strpos( $past_html, 'ytimg.com' ) );
t( 'past race name plate on each photo', 3 === substr_count( $past_html, 'class="arv-hub__past-plate"' ) );
t( 'Commons photos carry their credit', false !== strpos( $past_html, 'CC BY 4.0' ) && false !== strpos( $past_html, 'CC0' ) );
t( 'old results are small links under a Results label', 3 === substr_count( $past_html, 'arv-hub__past-results-label' ) );
t( 'past films are plain links to YouTube', 3 === substr_count( $past_html, 'arv-hub__past-film' ) );
t( 'nine films in the grid', 9 === substr_count( substr( $html, strpos( $html, 'arv-hub__films"' ) ), 'class="arv-hub__film"' ) );
t( 'each current tile gets a Watch link', 4 === substr_count( substr( $html, 0, strpos( $html, 'arv-hub__map' ) ), '>Watch</a>' ) );
t( 'Facebook and X in Follow', false !== strpos( $html, 'https://www.facebook.com/ultradventures' ) && false !== strpos( $html, 'https://x.com/ultraadventures' ) );
t( 'a bad film id is dropped', false === strpos( arv_region_hub_render( array_merge( $hub, array( 'films' => array( array( 'youtube' => '"><x', 'title' => 'bad' ) ) ) ), $store, '2026-11-25' ), '"><x' ) );
t( 'no hub without history or past still renders', '' !== arv_region_hub_render( array_diff_key( $hub, array( 'history' => 1, 'past' => 1, 'films' => 1 ) ), $store, '2026-11-25' ) );

echo "round 2 sections:\n";
t( 'stats strip with four numbers', 4 === substr_count( $html, 'arv-hub__stat-value' ) );
t( 'four full-bleed photo bands', 4 === substr_count( $html, 'class="arv-hub__band"' ) );
$pos = function ( $needle ) use ( $html ) {
	$at = strpos( $html, $needle );
	return false === $at ? -1 : $at;
};
t( 'section order', $pos( 'id="races"' ) < $pos( 'arv-hub__stats' ) && $pos( 'arv-hub__stats' ) < $pos( 'arv-hub__map' )
	&& $pos( 'arv-hub__map' ) < $pos( 'arv-hub__story' ) && $pos( 'arv-hub__story' ) < $pos( 'arv-hub__history' )
	&& $pos( 'arv-hub__history' ) < $pos( 'arv-hub__past"' ) && $pos( 'arv-hub__past"' ) < $pos( 'arv-hub__video' )
	&& $pos( 'arv-hub__video' ) < $pos( 'arv-hub__gallery' ) && $pos( 'arv-hub__gallery' ) < $pos( 'arv-hub__connect' ) );
t( 'four eras, each with a photo', 4 === substr_count( $html, 'class="arv-hub__era"' ) && 4 === substr_count( $html, 'class="arv-hub__era-photo"' ) );
foreach ( array( 'Ultra Adventures', 'Joined Vacation Races', 'The Tushars Come to Aravaipa', 'Joined Aravaipa Running' ) as $era ) {
	t( "era $era", false !== strpos( $html, '<h3 class="arv-hub__era-label">' . $era . '</h3>' ) );
}
t( 'Vacation Races in 2016, not 2019', false !== strpos( $html, '<p class="arv-hub__era-years">2016</p>' ) && ! preg_match( '/2019[^<]*Vacation Races/', $html ) );
t( 'no Mad Moose or Behind the Rocks', false === stripos( $html, 'mad moose' ) && false === stripos( $html, 'behind the rocks' ) );
t( 'gallery has 15 photos, 3 big', 15 === substr_count( $html, 'class="arv-hub__shot' ) && 3 === substr_count( $html, 'arv-hub__shot--big' ) );
t( 'fade-in hooks', substr_count( $html, 'data-arv-fade' ) >= 10 );
t( 'tribute hidden by default', false === strpos( $html, 'arv-hub__tribute' ) && false === strpos( $html, 'ua-hub-matt-gunn' ) );
$on = arv_region_hub_render( $hub, $store, '2026-11-25', array( 'tribute' => true ) );
$tr = strpos( $on, '<section class="arv-hub__section arv-hub__tribute"' );
t( 'tribute renders when the gate is on', false !== $tr && false !== strpos( $on, 'ua-hub-matt-gunn.png' ) && false !== strpos( $on, '>In Remembrance<' ) );
t( 'tribute is the last section', false !== $tr && false === strpos( $on, '<section', $tr + 10 ) && $tr > strpos( $on, 'arv-hub__connect' ) );
t( 'tribute has a gate option', 'arv_region_hub_tribute_ultra_adventures' === $hub['tribute']['gate_option'] );

echo "map:\n";
t( 'terrain map image, full width', false !== strpos( $html, 'class="arv-hub__map-img"' ) && false !== strpos( $html, 'ua-hub-map-desktop.jpg' ) );
t( 'phone gets its own crop', (bool) preg_match( '#<source media="\(max-width: 619px\)" srcset="[^"]*ua-hub-map-mobile\.jpg#', $html ) );
t( 'map attribution line', false !== strpos( $html, 'AWS Terrain Tiles' ) && false !== strpos( $html, 'National Park Service' ) );
$svg = arv_region_hub_render( array_merge( $hub, array( 'map' => array( 'heading' => 'X', 'states' => array( 'UT', 'AZ' ) ) ) ), $store, '2026-11-25' );
t( 'without an image the outline map still draws four pins', 4 === substr_count( $svg, 'class="arv-hub__pin"' ) );
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
