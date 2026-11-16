<?php
/**
 * Aravaipa Adventures after Jay's Nov 13 note, without WordPress: no
 * training camps, Norway, Colorado and Cocodona named nowhere, 2028 shown
 * as one coming-soon photo block, stats from what is published.
 *
 * Run: php scripts/test-adventures-jay.php [out.html [data.json]]
 *
 * out.html  also write the landing as a logged-out visitor sees it.
 * data.json render from real data instead of the built-in fixture (same
 *           format as scripts/test-adventures-camps.php).
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', 'https://www.aravaiparunning.com/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_VERSION', 'test' );
define( 'ARV_ELEMENTS_PATH', dirname( __DIR__ ) . '/' );

$GLOBALS['t_posts']   = array();
$GLOBALS['t_options'] = array();
$GLOBALS['t_editor']  = false;
$GLOBALS['t_is_page'] = false;

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function wp_kses_post( $s ) {
	return (string) $s;
}
function home_url( $path = '/' ) {
	return 'https://www.aravaiparunning.com' . $path;
}
function get_option( $key ) {
	return isset( $GLOBALS['t_options'][ $key ] ) ? $GLOBALS['t_options'][ $key ] : false;
}
function current_user_can() {
	return $GLOBALS['t_editor'];
}
function is_page() {
	return $GLOBALS['t_is_page'];
}
function is_singular() {
	return false;
}
function get_post( $p ) {
	if ( is_object( $p ) ) {
		return $p;
	}
	if ( 86050 === (int) $p ) {
		return (object) array( 'ID' => 86050, 'post_name' => 'adventures', 'post_status' => 'publish', 'post_type' => 'page' );
	}
	foreach ( $GLOBALS['t_posts'] as $post ) {
		if ( (int) $post->ID === (int) $p ) {
			return $post;
		}
	}
	return null;
}
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['t_posts'] as $post ) {
		if ( in_array( $post->post_status, (array) $args['post_status'], true ) ) {
			$out[] = $post;
		}
	}
	return $out;
}
function get_post_meta( $id, $key ) {
	$post = get_post( $id );
	return $post && isset( $post->meta[ $key ] ) ? $post->meta[ $key ] : '';
}
function get_the_title( $post ) {
	return get_post( $post )->post_title;
}
function get_permalink( $post ) {
	$post = get_post( $post );
	return 86050 === (int) $post->ID ? home_url( '/adventures/' ) : home_url( '/adventures/' . $post->post_name . '/' );
}
function wp_get_attachment_image_url( $id, $size ) {
	$suffix = array(
		'thumbnail' => '-150x150',
		'large'     => '-1024x683',
	);
	return 'https://www.aravaiparunning.com/wp-content/uploads/2026/11/p' . (int) $id . ( isset( $suffix[ $size ] ) ? $suffix[ $size ] : '' ) . '.jpg';
}
function wp_attachment_is_image( $id ) {
	return $id > 0;
}
function wp_get_attachment_image( $id, $size, $icon, $atts ) {
	$o = '<img src="' . esc_url( wp_get_attachment_image_url( $id, $size ) ) . '"';
	foreach ( $atts as $k => $v ) {
		$o .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return $o . ' />';
}

require __DIR__ . '/../includes/adventures.php';
require __DIR__ . '/../includes/adventures-render.php';
require __DIR__ . '/../includes/adventures-form.php';

$fails = 0;
function check( $label, $ok, $why = '' ) {
	global $fails;
	if ( ! $ok ) {
		++$fails;
		echo 'FAIL ' . $label . ( '' !== $why ? ': ' . $why : '' ) . "\n";
	} else {
		echo 'ok ' . $label . "\n";
	}
}

function load_data( $trips, $config ) {
	$GLOBALS['t_posts'] = array();
	foreach ( $trips as $t ) {
		$GLOBALS['t_posts'][] = (object) array(
			'ID'          => (int) $t['ID'],
			'post_name'   => $t['post_name'],
			'post_status' => $t['post_status'],
			'post_title'  => $t['post_title'],
			'menu_order'  => isset( $t['menu_order'] ) ? (int) $t['menu_order'] : 0,
			'meta'        => $t['meta'],
		);
	}
	$GLOBALS['t_options'] = array(
		'arv_adventures_landing'    => json_encode( $config ),
		'arv_adventures_landing_id' => 86050,
	);
}

/** Photo ids listed in the Photo Credits section. */
function credit_ids( $html ) {
	preg_match_all( '/<li data-photo="(\d+)"/', $html, $m );
	return array_map( 'intval', $m[1] );
}

/** Photo ids rendered anywhere outside the Photo Credits section. */
function rendered_ids( $html ) {
	$body = preg_replace( '#<section class="aa-credits".*?</section>#s', '', $html );
	preg_match_all( '#/p(\d+)(?:-\d+x\d+)?\.jpg#', $body, $m );
	return array_values( array_unique( array_map( 'intval', $m[1] ) ) );
}

function ph( $id, $alt = '' ) {
	return array( 'id' => $id, 'alt' => $alt );
}

// Fixture: the 10 live trips after the change (ids, slugs, types, places,
// seasons and hero photos as on the site), six of them drafted.
$rows  = array(
	array( 85992, 'cocodona-training-2028', 'draft', 'Cocodona 250 Training Camp', 'Training', 'Arizona, USA', 2028, 3, 85993, 'aravaipa' ),
	array( 86000, 'madeira-2027', 'publish', 'Island in the Sky', 'Race', 'Madeira, Portugal', 2027, 6, 86001, '' ),
	array( 86008, 'slovenia-2027', 'publish', 'Julian Alps Traverse', 'Adventure', 'Julian Alps, Slovenia', 2027, 8, 86009, '' ),
	array( 86016, 'softrock-2028', 'draft', 'Softrock', 'Training', 'Colorado, USA', 2028, 8, 86017, 'tbd' ),
	array( 86024, 'patagonia-2027', 'publish', 'Fitz Roy Frontier', 'Race', 'El Chaltén, Argentina', 2027, 10, 86025, '' ),
	array( 86028, 'new-zealand-2028', 'publish', 'South Island Odyssey', 'Adventure', 'South Island, New Zealand', 2027, 12, 86029, '' ),
	array( 86035, 'madeira-2028', 'draft', 'Island in the Sky', 'Race', 'Madeira, Portugal', 2028, 6, 86003, '' ),
	array( 86036, 'norway-2028', 'draft', 'Sail & Trail', 'Adventure', 'Lofoten, Norway', 2028, 8, 86037, '' ),
	array( 86044, 'slovenia-2028', 'draft', 'Julian Alps Traverse', 'Adventure', 'Julian Alps, Slovenia', 2028, 9, 86045, '' ),
	array( 86046, 'patagonia-2028', 'draft', 'Fitz Roy Frontier', 'Race', 'El Chaltén, Argentina', 2028, 10, 86027, '' ),
);
$trips = array();
foreach ( $rows as $r ) {
	$trips[] = array(
		'ID'          => $r[0],
		'post_name'   => $r[1],
		'post_status' => $r[2],
		'post_title'  => $r[3],
		'meta'        => array(
			'_arv_adv_type'        => $r[4],
			'_arv_adv_destination' => $r[5],
			'_arv_adv_sort_year'   => (string) $r[6],
			'_arv_adv_sort_month'  => (string) $r[7],
			'_arv_adv_length'      => '8 days, 7 nights',
			'_arv_adv_dates'       => 2027 === $r[6] ? 'Fall ' . $r[6] : 'Summer ' . $r[6],
			'_arv_adv_host'        => $r[9],
			'_arv_adv_photos'      => json_encode( array( 'hero' => ph( $r[8] ), 'gallery' => array( ph( $r[8] + 1 ) ) ) ),
		),
	);
}
$credit = static function ( $id, $label ) {
	return array( 'photo' => array( 'id' => $id ), 'label' => $label, 'html' => 'Photo: someone.' );
};
$config = array(
	'hero'          => ph( 86025, 'Fitz Roy at sunrise' ),
	'pano'          => ph( 86001, 'Ridgeline trail above the clouds on Madeira' ),
	'who'           => ph( 86026 ),
	'form'          => ph( 86047 ),
	'partner_photo' => ph( 86048 ),
	'partner_logo'  => ph( 86049 ),
	'coming_soon'   => array( 'year' => 2028, 'photo' => ph( 86030, 'Trail above a South Island lake' ) ),
	'faqs'          => array( array( 'How big are the groups?', 'Small. Trips cap at 12 runners or fewer.' ) ),
	'credits'       => array(
		$credit( 86025, 'Adventures hero' ), $credit( 86001, 'Panorama' ), $credit( 86030, 'Coming soon' ),
		$credit( 86037, 'Sail & Trail 2028, Lofoten' ), $credit( 86017, 'Softrock hero, Colorado' ), $credit( 85993, 'Cocodona 250 Training Camp hero' ),
	),
	'credits_intro' => 'Trip photography is from Trail Run Adventures, our trip partner.',
	'credits_outro' => 'Logo and founders photo courtesy of the partner.',
);

/**
 * Every check on one logged-out landing render.
 *
 * @param string $tag     Label prefix ('' for the fixture, 'data: ' for real data).
 * @param int    $deps    Expected Departures.
 * @param int    $nations Expected Countries.
 * @return string Landing HTML.
 */
function check_landing( $tag, $deps, $nations ) {
	$GLOBALS['t_editor'] = false;
	$trips   = arv_adv_trips();
	$landing = arv_adv_render_landing();
	$text    = html_entity_decode( strip_tags( $landing ), ENT_QUOTES );
	$GLOBALS['t_is_page'] = true;
	$share                = arv_adv_share_context();
	$GLOBALS['t_is_page'] = false;
	$form = arv_adv_form_tag( array( 'name' => 'adv-trip', 'options' => array() ), true );
	$opts = implode( ' | ', $form['values'] );

	check( $tag . 'no training camp', false === strpos( $landing, 'data-filter="training"' ) && 1 !== preg_match( '/training camp|aa-badge-training/i', $landing ) && 1 !== preg_match( '/training/i', $share['desc'] ) );
	check( $tag . 'filter is race trips and adventures', false !== strpos( $landing, 'data-filter="race"' ) && false !== strpos( $landing, 'data-filter="adventure"' ) && 3 === substr_count( $landing, 'data-filter=' ) );
	$hidden = '/lofoten|norway|softrock|hardrock|san juan|colorado|cocodona/i';
	$hit    = preg_match( $hidden, $landing . ' ' . $share['desc'] . ' ' . $opts, $m ) ? $m[0] : '';
	check( $tag . 'no hidden trip names', '' === $hit, 'found ' . $hit );
	check( $tag . 'form lists only shown trips', count( $trips ) + 1 === count( $form['values'] ), $opts );

	$soon = preg_match( '#<div class="aa-season aa-season-soon">.*?</div></div></div>#s', $landing, $sm ) ? $sm[0] : '';
	check(
		$tag . 'coming soon block',
		'' !== $soon && 1 === preg_match( '#<img [^>]*src="[^"]+"|background-image#', $soon )
			&& false !== stripos( html_entity_decode( strip_tags( $soon ), ENT_QUOTES ), '2028 destinations and dates coming soon' )
			&& 1 === preg_match( '/develop[a-z]* new trips[^.]*explore new areas/i', $soon )
			&& false !== strpos( $soon, 'href="#interest"' )
			&& false !== strpos( $soon, '2028 <small>Season</small>' ),
		$soon
	);
	$tiles = preg_match_all( '#<a class="aa-tile" href="([^"]+)"#', $landing, $tm ) ? $tm[1] : array();
	$t2028 = array();
	foreach ( $trips as $t ) {
		if ( 2028 === $t['sort_year'] ) {
			$t2028[] = $t['slug'];
		}
	}
	check( $tag . 'no 2028 trip tiles', ! $t2028 && count( $tiles ) === count( $trips ) && 1 === substr_count( $landing, '<small>Season</small>' ) - 1, implode( ',', $tiles ) );

	preg_match_all( '#<div class="aa-fact-num">([^<]*)</div><div class="aa-fact-label">([^<]*)</div>#', $landing, $fm );
	$facts = array_combine( $fm[2], $fm[1] );
	check(
		$tag . 'stats',
		isset( $facts['Departures'], $facts['Countries'], $facts['Runners Max Per Trip'], $facts['Season'] )
			&& (string) $deps === $facts['Departures'] && (string) $nations === $facts['Countries']
			&& '12' === $facts['Runners Max Per Trip'] && '2027' === $facts['Season'],
		json_encode( $facts )
	);
	check( $tag . 'milford not optional, no full milford promise', 1 !== preg_match( '/milford[^.]*optional|optional[^.]*milford|Milford, Routeburn, and Kepler/i', $text ) );
	check( $tag . 'no em dash', 1 !== preg_match( '/\x{2014}/u', $landing . $share['desc'] ) );
	$cids = credit_ids( $landing );
	check( $tag . 'credits only for shown photos', $cids && ! array_diff( $cids, rendered_ids( $landing ) ), 'extra ' . implode( ',', array_diff( $cids, rendered_ids( $landing ) ) ) );
	return $landing;
}

load_data( $trips, $config );
$landing = check_landing( '', 4, 4 );

// Defaults: no coming_soon config still gives the block (panorama photo).
$bare = $config;
unset( $bare['coming_soon'] );
load_data( $trips, $bare );
$b = arv_adv_coming_soon( $bare, arv_adv_trips() );
check( 'coming soon defaults', $b && 2028 === $b['year'] && 86001 === $b['photo']['id'] && 1 === preg_match( '/explore new areas/', $b['text'] ) );
$off            = $config;
$off['coming_soon']['off'] = true;
check( 'coming soon can be switched off', null === arv_adv_coming_soon( $off, arv_adv_trips() ) );

// A 2028 trip published again: its season comes back and the block goes.
$back                    = $trips;
$back[6]['post_status']  = 'publish';
load_data( $back, $config );
$l2 = arv_adv_render_landing();
check( 'coming soon hides once 2028 has a trip', false === strpos( $l2, 'aa-season-soon' ) && false !== strpos( $l2, '2028 <small>Season</small>' ) );

// A training camp published again still stays off the site.
$camp                   = $trips;
$camp[0]['post_status'] = 'publish';
load_data( $camp, $config );
check( 'published camp stays hidden', 4 === count( arv_adv_trips() ) && 1 !== preg_match( '/cocodona|training camp/i', arv_adv_render_landing() ) );
$GLOBALS['t_editor'] = true;
check( 'editors never see the camp either', ! in_array( 'cocodona-training-2028', array_column( arv_adv_trips(), 'slug' ), true ) );
$GLOBALS['t_editor'] = false;

// Trip pages of kept trips: no link to a hidden trip under More adventures.
load_data( $trips, $config );
$nz = arv_adv_render_trip( arv_adv_trip( 86028 ) );
check( 'trip page more adventures skip hidden trips', 1 !== preg_match( '/norway-2028|softrock-2028|cocodona-training-2028|madeira-2028|slovenia-2028|patagonia-2028/', $nz ) );

if ( ! empty( $argv[2] ) ) {
	$data = json_decode( (string) file_get_contents( $argv[2] ), true );
	load_data( $data['trips'], $data['config'] );
	$pub     = arv_adv_trips( false );
	$nations = array();
	foreach ( $pub as $t ) {
		$parts                         = explode( ',', $t['destination'] );
		$nations[ trim( end( $parts ) ) ] = true;
	}
	$landing = check_landing( 'data: ', count( $pub ), count( $nations ) );
	check( 'data: 4 countries', 4 === count( $nations ) );
}
if ( ! empty( $argv[1] ) ) {
	file_put_contents( $argv[1], "<!doctype html>\n<html><head><meta charset=\"utf-8\"><meta name=\"robots\" content=\"noindex, nofollow\"><title>Aravaipa Adventures (preview)</title></head><body class=\"arv-adv-page\">\n" . $landing . "\n</body></html>\n" );
	echo 'wrote ' . $argv[1] . "\n";
}

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
