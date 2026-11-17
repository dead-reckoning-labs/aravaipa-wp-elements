<?php
/**
 * Volunteer List (includes/volunteer-list.php), without WordPress: date
 * order, past and virtual races left out, RaceGoat rows only for races in
 * the org volunteer feed that are not in pre-launch, iVolunteer rows on
 * their own event or the iVolunteer home, a feed failure falling back to
 * iVolunteer everywhere (and cached), filters built from the data, no
 * outbound request anywhere but racegoat.com, escaped output, no em dash.
 *
 * Run: php scripts/test-volunteer-list.php [volunteer-map.json [outdir]]
 *
 * volunteer-map.json  also render real rows (the kp8w5p map: rows with
 *                     rg_slug, rg_in_feed, rg_prelaunch, volunteer_url).
 * outdir              write outdir/volunteer-list.html from that map.
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', 'https://www.aravaiparunning.com/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_VERSION', 'test' );
define( 'ARV_ELEMENTS_PATH', dirname( __DIR__ ) . '/' );
define( 'ARV_RACE_POST_TYPE', 'arv_race' );

$GLOBALS['t_transients'] = array();
$GLOBALS['t_requests']   = array();
$GLOBALS['t_feed']       = null; // array, 'error', 500, 'badjson'.
$GLOBALS['t_embeds']     = array();
$GLOBALS['t_pages']      = array(); // page url => widget slug.
$GLOBALS['t_scripts']    = array();

class WP_Error {}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function apply_filters( $tag, $value ) {
	return $value;
}
function __( $s ) {
	return $s;
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function current_user_can() {
	return false;
}
function current_time() {
	return '2026-11-17';
}
function wp_enqueue_script( $handle, $src ) {
	$GLOBALS['t_scripts'][ $handle ] = $src;
}
function get_transient( $k ) {
	return isset( $GLOBALS['t_transients'][ $k ] ) ? $GLOBALS['t_transients'][ $k ] : false;
}
function set_transient( $k, $v, $ttl ) {
	$GLOBALS['t_transients'][ $k ] = $v;
	$GLOBALS['t_ttl'][ $k ]        = $ttl;
	return true;
}
function url_to_postid( $url ) {
	return isset( $GLOBALS['t_pages'][ $url ] ) ? crc32( $url ) : 0;
}
function get_post_field( $field, $id ) {
	foreach ( $GLOBALS['t_pages'] as $url => $slug ) {
		if ( crc32( $url ) === $id ) {
			return '<div data-racegoat-race="' . $slug . '"></div>';
		}
	}
	return '';
}
function get_post_meta() {
	return '';
}
function wp_remote_get( $url ) {
	$GLOBALS['t_requests'][] = $url;
	if ( false !== strpos( $url, '/api/embed/volunteer/' ) ) {
		$f = $GLOBALS['t_feed'];
		if ( 'error' === $f ) {
			return new WP_Error();
		}
		if ( 500 === $f ) {
			return array( 'code' => 500, 'body' => '' );
		}
		if ( 'badjson' === $f ) {
			return array( 'code' => 200, 'body' => '{not json' );
		}
		return array( 'code' => 200, 'body' => json_encode( $f ) );
	}
	if ( preg_match( '#/api/embed/([a-z0-9-]+)$#', $url, $m ) && isset( $GLOBALS['t_embeds'][ $m[1] ] ) ) {
		return array( 'code' => 200, 'body' => json_encode( $GLOBALS['t_embeds'][ $m[1] ] ) );
	}
	return array( 'code' => 404, 'body' => '' );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}

require ARV_ELEMENTS_PATH . 'includes/race-schema-racegoat.php';
require ARV_ELEMENTS_PATH . 'includes/volunteer-list.php';

$fails = 0;
$n     = 0;
function ok( $cond, $msg ) {
	global $fails, $n;
	$n++;
	if ( $cond ) {
		echo "ok   $msg\n";
	} else {
		$fails++;
		echo "FAIL $msg\n";
	}
}
function reset_state() {
	$GLOBALS['t_transients'] = array();
	$GLOBALS['t_requests']   = array();
}
function feed( $races ) {
	$months = array();
	foreach ( $races as $r ) {
		$months[ $r[2] ]['key']     = $r[2];
		$months[ $r[2] ]['label']   = $r[2];
		$months[ $r[2] ]['races'][] = array( 'name' => $r[1], 'dates' => '', 'url' => 'https://racegoat.com/events/' . $r[0] . '/volunteer', 'areas' => array() );
	}
	return array( 'org' => array( 'slug' => 'aravaipa', 'name' => 'Aravaipa Running' ), 'months' => array_values( $months ), 'areas' => array() );
}
function embed( $status, $opens = null ) {
	return array( 'name' => 'x', 'date' => 'x', 'status' => $status, 'opensAt' => $opens, 'distances' => array( array( 'name' => '50K' ) ) );
}
/** Rows of the rendered list, in page order: name => [mode, href, region, division, slug]. */
function rows_of( $html ) {
	$out = array();
	$parts = preg_split( '#(?=<div class="arv-vol__row" )#', $html );
	array_shift( $parts );
	foreach ( $parts as $p ) {
		preg_match( '#data-region="([^"]*)" data-division="([^"]*)" data-mode="([^"]*)"#', $p, $a );
		preg_match( '#class="arv-vol__name"[^>]*>(.*?)</#s', $p, $b );
		preg_match( '#<a class="arv-vol__btn" href="([^"]*)"#', $p, $c );
		preg_match( '#data-racegoat-races="([^"]*)"#', $p, $d );
		$out[ html_entity_decode( $b[1], ENT_QUOTES ) ] = array(
			'mode'     => $a[3],
			'href'     => html_entity_decode( $c[1], ENT_QUOTES ),
			'region'   => $a[1],
			'division' => $a[2],
			'slug'     => isset( $d[1] ) ? $d[1] : '',
		);
	}
	return $out;
}

$IV  = 'https://aravaiparunning.ivolunteer.com/';
$now = strtotime( '2026-11-17T21:00:00Z' );

$races = array(
	array( 'post_id' => 9, 'name' => 'Whiskey Basin Trail Runs', 'iso' => '2027-04-17', 'location' => 'Prescott, AZ', 'register' => 'https://example.com/register?id=1', 'page' => 'https://www.aravaiparunning.com/whiskey-basin/', 'volunteer_url' => $IV . 'wb27' ),
	array( 'post_id' => 1, 'name' => 'Pass Mountain', 'iso' => '2026-11-14', 'location' => 'Mesa, AZ', 'register' => '', 'page' => 'https://www.aravaiparunning.com/pass-mountain/', 'volunteer_url' => $IV . 'pm26' ),
	array( 'post_id' => 2, 'name' => 'Merry Vertmas', 'iso' => '2026-12-01', 'location' => 'Worldwide', 'register' => '', 'page' => '', 'volunteer_url' => '' ),
	array( 'post_id' => 3, 'name' => 'Coldwater Hundred', 'iso' => '2027-01-16', 'location' => 'Goodyear, AZ', 'register' => 'https://example.com/register?id=2', 'page' => 'https://www.aravaiparunning.com/coldwater/', 'volunteer_url' => $IV . 'cwr27' ),
	array( 'post_id' => 4, 'name' => 'Fat Ox', 'iso' => '2026-11-20', 'location' => 'Goodyear, AZ', 'register' => '', 'page' => 'https://www.aravaiparunning.com/fat-ox/', 'volunteer_url' => $IV . 'fo26' ),
	array( 'post_id' => 5, 'name' => 'Devil After Dark', 'iso' => '2027-09-04', 'location' => 'Las Vegas, NV', 'register' => 'https://racegoat.com/events/devil-after-dark-2027', 'page' => 'https://www.aravaiparunning.com/devil-after-dark/', 'volunteer_url' => '' ),
	array( 'post_id' => 6, 'name' => 'Ram Party', 'iso' => '2027-05-15', 'location' => 'Colorado Springs, CO', 'register' => 'https://racegoat.com/events/ram-party-2027', 'page' => 'https://www.aravaiparunning.com/ram-party/', 'volunteer_url' => '' ),
	array( 'post_id' => 7, 'name' => 'Sinister Night Rides', 'iso' => '2027-04-23', 'location' => 'Queen Creek, AZ', 'register' => '', 'page' => '', 'volunteer_url' => '' ),
	array( 'post_id' => 8, 'name' => 'Run <b>Around</b> Tucson', 'iso' => '2027-01-23', 'location' => 'Tucson, AZ', 'register' => '', 'page' => 'https://www.aravaiparunning.com/rat/', 'volunteer_url' => 'https://evil.example.com/x' ),
	array( 'post_id' => 10, 'name' => 'Mountain Ridge Trail Race', 'iso' => '2027-04-10', 'location' => 'Highlands Ranch, CO', 'register' => 'https://racegoat.com/events/mountain-ridge-2027', 'page' => '', 'volunteer_url' => '' ),
);

$GLOBALS['t_pages']  = array( 'https://www.aravaiparunning.com/coldwater/' => 'coldwater-hundred' );
$GLOBALS['t_embeds'] = array(
	'coldwater-hundred'     => embed( 'open' ),
	'ram-party-2027'        => embed( 'open' ),
	'sinister-night-rides-2027' => embed( 'open' ),
	'devil-after-dark-2027' => embed( 'upcoming', '2026-11-20T17:00:00.000Z' ),
	'mountain-ridge-2027'   => embed( 'upcoming', '2026-11-01T17:00:00.000Z' ),
);
$GLOBALS['t_feed']   = feed(
	array(
		array( 'coldwater-hundred', 'Coldwater Hundred', '2027-01' ),
		array( 'ram-party-2027', 'Ram Party Trail Runs', '2027-05' ),
		array( 'sinister-night-rides-2027', 'Sinister Night Rides', '2027-04' ),
		array( 'devil-after-dark-2027', 'Devil After Dark', '2027-09' ),
		array( 'mountain-ridge-2027', 'Mountain Ridge Trail Race', '2027-04' ),
	)
);

reset_state();
$html = arv_vol_render( array( 'races' => $races, 'today' => '2026-11-17', 'now' => $now ) );
$rows = rows_of( $html );
$names = array_keys( $rows );

ok( array( 'Fat Ox', 'Coldwater Hundred', 'Run <b>Around</b> Tucson', 'Mountain Ridge Trail Race', 'Whiskey Basin Trail Runs', 'Sinister Night Rides', 'Ram Party', 'Devil After Dark' ) === $names, 'rows in date order: ' . implode( ', ', $names ) );
ok( ! isset( $rows['Pass Mountain'] ), 'past race left out' );
ok( ! isset( $rows['Merry Vertmas'] ) && false === strpos( $html, 'Worldwide' ), 'virtual (Worldwide) race left out' );

ok( 'racegoat' === $rows['Coldwater Hundred']['mode'] && 'coldwater-hundred' === $rows['Coldwater Hundred']['slug'], 'Coldwater: in feed, slug from its race page widget, RaceGoat row' );
ok( false !== strpos( $html, 'data-racegoat-volunteer="aravaipa" data-racegoat-races="coldwater-hundred" data-racegoat-theme="host" data-racegoat-branding="none" data-racegoat-title="none"' ), 'Coldwater panel carries the RaceGoat volunteer widget markup' );
ok( 'https://racegoat.com/events/coldwater-hundred/volunteer' === $rows['Coldwater Hundred']['href'], 'Coldwater button falls back to its RaceGoat shift board without scripts' );
ok( 'racegoat' === $rows['Ram Party']['mode'] && 'ram-party-2027' === $rows['Ram Party']['slug'], 'slug from a RaceGoat register URL' );
ok( 'racegoat' === $rows['Sinister Night Rides']['mode'] && 'sinister-night-rides-2027' === $rows['Sinister Night Rides']['slug'], 'slug from an exact name match in the same month of the feed' );
ok( 'racegoat' === $rows['Mountain Ridge Trail Race']['mode'], 'upcoming status with an open time already past counts as launched' );

ok( 'ivolunteer' === $rows['Devil After Dark']['mode'] && '' === $rows['Devil After Dark']['slug'], 'feed race in pre-launch (upcoming, opens in the future) stays on iVolunteer' );
ok( $IV === $rows['Devil After Dark']['href'], 'pre-launch race with no code links the iVolunteer root' );
ok( $IV . 'fo26' === $rows['Fat Ox']['href'] && 'ivolunteer' === $rows['Fat Ox']['mode'], 'race not in the feed links its own _arv_volunteer_url (fo26)' );
ok( $IV . 'wb27' === $rows['Whiskey Basin Trail Runs']['href'], 'Whiskey Basin links wb27' );
ok( $IV === $rows['Run <b>Around</b> Tucson']['href'], 'a volunteer_url off iVolunteer is never used: root instead' );

$codes = array();
foreach ( $rows as $name => $r ) {
	if ( 'ivolunteer' === $r['mode'] && $r['href'] !== $IV ) {
		$codes[ $r['href'] ][] = $name;
	}
}
ok( count( $codes ) === count( array_filter( $codes, function ( $v ) { return 1 === count( $v ); } ) ), 'no iVolunteer code shared by two races in the fixture' );
$racegoat_markup = substr_count( $html, 'data-racegoat-volunteer=' );
ok( 4 === $racegoat_markup, "widget markup only on the 4 launched feed races (got $racegoat_markup)" );

ok( false !== strpos( $html, 'data-region="arizona"' ) && false !== strpos( $html, '>Arizona</button>' ) && false !== strpos( $html, '>Colorado</button>' ) && false !== strpos( $html, '>Nevada</button>' ), 'region chips Arizona, Colorado, Nevada from the data' );
ok( false === strpos( $html, '>Utah</button>' ) && false === strpos( $html, '>California</button>' ), 'no chip for a region with no rows' );
ok( false !== strpos( $html, '>Aravaipa Running</button>' ) && false !== strpos( $html, '>Aravaipa Rides</button>' ) && false === strpos( $html, '>Steep Life</button>' ), 'division tabs Running and Rides (from the name), no empty Steep Life tab' );
ok( 'rides' === $rows['Sinister Night Rides']['division'] && 'running' === $rows['Ram Party']['division'], 'division: Rides from the name, else Running' );
ok( 'steeplife' === arv_vol_division( 'Anything', 'steeplife' ) && 'running' === arv_vol_division( 'Anything', 'bogus' ), '_arv_division meta wins when valid' );

ok( false === strpos( $html, '<b>Around</b>' ) && false !== strpos( $html, 'Run &lt;b&gt;Around&lt;/b&gt; Tucson' ), 'titles escaped' );
ok( false === strpos( $html, "\u{2014}" ), 'no em dash in the output' );
ok( false !== strpos( $html, 'hidden data-arv-vol-js' ) && 0 === preg_match( '#class="arv-vol__row"[^>]*hidden#', $html ), 'filter bars start hidden (shown by script), every row visible without scripts' );
ok( isset( $GLOBALS['t_scripts']['aravaipa-volunteer'] ), 'script enqueued' );

$bad = array_filter( $GLOBALS['t_requests'], function ( $u ) { return 0 !== strpos( $u, 'https://racegoat.com/' ); } );
ok( empty( $bad ) && ! empty( $GLOBALS['t_requests'] ), 'every outbound request goes to racegoat.com (' . count( $GLOBALS['t_requests'] ) . ' requests)' );
ok( 1 === count( array_filter( $GLOBALS['t_requests'], function ( $u ) { return false !== strpos( $u, '/api/embed/volunteer/' ); } ) ), 'feed read once per render' );

$before = count( $GLOBALS['t_requests'] );
arv_vol_render( array( 'races' => $races, 'today' => '2026-11-17', 'now' => $now ) );
ok( count( $GLOBALS['t_requests'] ) === $before, 'second render served from transients, no new requests' );
ok( ARV_VOL_FEED_TTL === $GLOBALS['t_ttl'][ ARV_VOL_FEED_KEY ], 'good feed cached 6 h' );

foreach ( array( 'error' => 'WP_Error', 500 => 'HTTP 500', 'badjson' => 'bad JSON' ) as $mode => $label ) {
	reset_state();
	$GLOBALS['t_feed'] = $mode;
	$h = arv_vol_render( array( 'races' => $races, 'today' => '2026-11-17', 'now' => $now ) );
	$r = rows_of( $h );
	$all_iv = count( $r ) === 8 && count( array_filter( $r, function ( $x ) { return 'ivolunteer' === $x['mode']; } ) ) === 8;
	ok( $all_iv && false === strpos( $h, 'data-racegoat-volunteer=' ), "feed $label: every row falls back to iVolunteer" );
	ok( $IV . 'cwr27' === $r['Coldwater Hundred']['href'], "feed $label: Coldwater falls back to its own iVolunteer event" );
	ok( isset( $GLOBALS['t_transients'][ ARV_VOL_FEED_KEY ] ) && empty( $GLOBALS['t_transients'][ ARV_VOL_FEED_KEY ]['ok'] ) && ARV_VOL_FEED_FAIL_TTL === $GLOBALS['t_ttl'][ ARV_VOL_FEED_KEY ], "feed $label: failure cached 15 min" );
	$c = count( $GLOBALS['t_requests'] );
	arv_vol_render( array( 'races' => $races, 'today' => '2026-11-17', 'now' => $now ) );
	ok( count( $GLOBALS['t_requests'] ) === $c, "feed $label: no refetch while the failure is cached" );
}

reset_state();
$GLOBALS['t_feed'] = feed( array() );
$one = array_values( array_filter( $races, function ( $r ) { return 'Sinister Night Rides' !== $r['name']; } ) );
$h   = arv_vol_render( array( 'races' => $one, 'today' => '2026-11-17', 'now' => $now ) );
ok( false === strpos( $h, 'arv-vol__tabs' ), 'no division tabs when the data has only one division' );
ok( '' === arv_vol_render( array( 'races' => array(), 'today' => '2026-11-17', 'now' => $now ) ), 'nothing to show renders nothing' );

$src = file_get_contents( ARV_ELEMENTS_PATH . 'includes/volunteer-list.php' ) . file_get_contents( ARV_ELEMENTS_PATH . 'assets/aravaipa-volunteer.js' );
ok( ! preg_match( '/ultrasignup|runsignup/i', $src ), 'no UltraSignup or RunSignUp anywhere in the list code' );
ok( ! preg_match( '/wp_remote_\w+\(\s*[\'"]https?:\/\/(?!racegoat\.com)/', $src ), 'no outbound request to any host but racegoat.com in the source' );
ok( false === strpos( $src, "\u{2014}" ), 'no em dash in the source' );
$css = file_get_contents( ARV_ELEMENTS_PATH . 'assets/aravaipa-elements.css' );
$vcss = substr( $css, strpos( $css, '/* Volunteer List' ) );
ok( ! preg_match( '/box-shadow\s*:\s*(?!none)/', $vcss ) && ! preg_match( '/border-(left|right)\s*:/', $vcss ) && ! preg_match( '/border-radius\s*:\s*([3-9]|\d\d+)px|border-radius\s*:\s*var/', $vcss ), 'list CSS: no shadow, no side stripe, radius 2px at most' );

// Real data preview.
if ( isset( $argv[1] ) ) {
	$map = json_decode( file_get_contents( $argv[1] ), true );
	$map = isset( $map['rows'] ) ? $map['rows'] : $map;
	$feedrows = array();
	$GLOBALS['t_embeds'] = array();
	$real = array();
	foreach ( $map as $m ) {
		if ( $m['rg_in_feed'] ) {
			$feedrows[] = array( $m['rg_slug'], $m['title'], substr( $m['iso'], 0, 7 ) );
			$GLOBALS['t_embeds'][ $m['rg_slug'] ] = $m['rg_prelaunch'] ? embed( 'upcoming', '2099-01-01T00:00:00Z' ) : embed( 'open' );
		}
		$real[] = array( 'post_id' => $m['post_id'], 'name' => $m['title'], 'iso' => $m['iso'], 'location' => $m['location'], 'register' => $m['rg_slug'] ? 'https://racegoat.com/events/' . $m['rg_slug'] : '', 'page' => $m['page'], 'volunteer_url' => $m['volunteer_url'], 'division_meta' => '' );
	}
	reset_state();
	$GLOBALS['t_feed'] = feed( $feedrows );
	$h = arv_vol_render( array( 'races' => $real, 'today' => '2026-11-17', 'now' => $now ) );
	$r = rows_of( $h );
	$want = array();
	foreach ( $map as $m ) {
		$want[ $m['title'] ] = $m['mode'];
	}
	$got = array_map( function ( $x ) { return $x['mode']; }, $r );
	ok( count( $r ) === count( $map ), 'real map: ' . count( $r ) . ' rows rendered' );
	ok( $got == $want, 'real map: every row mode matches the map (' . count( array_filter( $got, function ( $x ) { return 'racegoat' === $x; } ) ) . ' RaceGoat, ' . count( array_filter( $got, function ( $x ) { return 'ivolunteer' === $x; } ) ) . ' iVolunteer)' );
	if ( isset( $argv[2] ) ) {
		@mkdir( $argv[2], 0777, true );
		file_put_contents( rtrim( $argv[2], '/' ) . '/volunteer-list.html', $h );
		echo "wrote {$argv[2]}/volunteer-list.html\n";
	}
}

echo "\n" . ( $n - $fails ) . " passed, $fails failed\n";
exit( $fails ? 1 : 0 );
