<?php
/**
 * Adventures with a trip type taken off the site (training camps drafted),
 * without WordPress: filter tabs, camp-free copy, the draft redirect and
 * per-page photo credits.
 *
 * Run: php scripts/test-adventures-camps.php [out.html [data.json]]
 *
 * out.html  also write the landing as a logged-out visitor sees it.
 * data.json render that landing from real data instead of the built-in
 *           fixture: {"trips": [{ID, post_name, post_status, post_title,
 *           menu_order, meta: {_arv_adv_*: value}}], "config": {...}}.
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

$fails = 0;
function check( $label, $ok, $why = '' ) {
	global $fails;
	if ( ! $ok ) {
		++$fails;
		echo 'FAIL ' . $label . ( '' !== $why ? ': ' . $why : '' ) . "\n";
	} else {
		echo 'ok   ' . $label . "\n";
	}
}

/**
 * Load a data set: trips as posts, config as the landing option.
 */
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

function trip_of( $id, $slug, $status, $title, $type, $dest, $year, $photos ) {
	return array(
		'ID'          => $id,
		'post_name'   => $slug,
		'post_status' => $status,
		'post_title'  => $title,
		'meta'        => array(
			'_arv_adv_type'        => $type,
			'_arv_adv_destination' => $dest,
			'_arv_adv_sort_year'   => (string) $year,
			'_arv_adv_sort_month'  => '6',
			'_arv_adv_length'      => '8 days',
			'_arv_adv_dates'       => 'June ' . $year,
			'_arv_adv_photos'      => json_encode( $photos ),
		),
	);
}

function ph( $id ) {
	return array( 'id' => $id, 'alt' => '' );
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

function same_set( $a, $b ) {
	sort( $a );
	sort( $b );
	return array_values( array_unique( $a ) ) === array_values( array_unique( $b ) );
}

// Built-in fixture: a race trip, an adventure and a drafted training camp.
$trips  = array(
	trip_of( 1, 'madeira-2027', 'publish', 'Island in the Sky', 'Race', 'Madeira, Portugal', 2027, array( 'hero' => ph( 101 ), 'intro' => ph( 102 ), 'form' => ph( 103 ), 'gallery' => array( ph( 104 ), ph( 105 ) ) ) ),
	trip_of( 2, 'norway-2028', 'publish', 'Sail & Trail', 'Adventure', 'Lofoten, Norway', 2028, array( 'hero' => ph( 201 ), 'intro' => ph( 202 ), 'gallery' => array( ph( 203 ) ) ) ),
	trip_of( 3, 'cocodona-training-2028', 'draft', 'Cocodona 250 Training Camp', 'Training', 'Arizona, USA', 2028, array( 'hero' => ph( 301 ), 'intro' => ph( 302 ), 'gallery' => array( ph( 303 ) ) ) ),
);
$credit = static function ( $id, $label ) {
	return array( 'photo' => array( 'id' => $id ), 'label' => $label, 'html' => 'Photo: someone.' );
};
$config = array(
	'hero'          => ph( 201 ),
	'pano'          => ph( 103 ),
	'who'           => ph( 900 ),
	'form'          => ph( 901 ),
	'partner_photo' => ph( 950 ),
	'partner_logo'  => ph( 951 ),
	'coaching'      => array( 'url' => 'https://example.com/coaching', 'photo' => ph( 960 ) ),
	'faqs'          => array( array( 'Q', 'A' ) ),
	'credits'       => array(
		$credit( 101, 'Madeira hero' ), $credit( 102, 'Madeira story' ), $credit( 103, 'Madeira form and panorama' ),
		$credit( 104, 'Madeira gallery one' ), $credit( 105, 'Madeira gallery two' ),
		$credit( 201, 'Norway hero and landing hero' ), $credit( 202, 'Norway story' ), $credit( 203, 'Norway gallery' ),
		$credit( 301, 'Cocodona 250 Training Camp hero' ), $credit( 302, 'Cocodona camp story' ), $credit( 303, 'Cocodona camp gallery' ),
		$credit( 900, 'Who It Is For' ), $credit( 901, 'Interest form' ),
	),
	'credits_intro' => 'Trip photography is from our partner.',
	'credits_outro' => 'Logo and founders photo courtesy of the partner.',
);
load_data( $trips, $config );

// Logged out: the camp is a draft, so the visitor sees two trips.
$GLOBALS['t_editor'] = false;
$out_trips = arv_adv_trips();
$landing   = arv_adv_render_landing();
$text      = html_entity_decode( strip_tags( $landing ), ENT_QUOTES );

check( 'logged-out trips exclude the draft', 2 === count( $out_trips ) );
$vis = arv_adv_visible_types( $out_trips );
check( 'filter hides empty type', ! isset( $vis['Training'] ) && false === strpos( $landing, 'data-filter="training"' ) && false === strpos( $landing, 'Training Camps' ) );
check( 'filter keeps race and adventure', isset( $vis['Race'], $vis['Adventure'] ) && false !== strpos( $landing, 'data-filter="race"' ) && false !== strpos( $landing, 'data-filter="adventure"' ) && false !== strpos( $landing, 'data-filter="all"' ) );

$copy = arv_adv_landing_copy( false );
check( 'hero copy without camps', 1 !== preg_match( '/training camp|arizona|cocodona/i', $copy['hero'] ) && false !== strpos( $landing, '<p class="aa-hero-sub">' . esc_html( $copy['hero'] ) . '</p>' ) );
check( 'hero copy with camps', 'Race trips, training camps, and guided trail running adventures, from Arizona to Patagonia.' === arv_adv_landing_copy( true )['hero'] );
check( 'intro copy without camps', 1 !== preg_match( '/training camp|cocodona|hardrock loop|arizona/i', $copy['lede'] . ' ' . $copy['more'] ) && false !== strpos( $landing, esc_html( $copy['lede'] ) ) && false !== strpos( $landing, esc_html( $copy['more'] ) ) );
check( 'intro copy with camps', false !== strpos( arv_adv_landing_copy( true )['lede'], 'Cocodona 250 course' ) && false !== strpos( arv_adv_landing_copy( true )['more'], 'Training camps put you on a race course' ) );
check( 'no camp text on the landing', 1 !== preg_match( '/training camp|cocodona/i', $text ), 'found camp text' );
check( 'no em or en dashes in copy', 1 !== preg_match( '/\x{2013}|\x{2014}/u', implode( ' ', arv_adv_landing_copy( false ) ) . implode( ' ', arv_adv_landing_copy( true ) ) ) );

$GLOBALS['t_is_page'] = true;
$share                = arv_adv_share_context();
$GLOBALS['t_is_page'] = false;
check( 'share desc without camps', is_array( $share ) && $share['desc'] === $copy['hero'] );

// Credits on the landing.
$cids = credit_ids( $landing );
$rids = rendered_ids( $landing );
check( 'landing credits only rendered photos', $cids && ! array_diff( $cids, $rids ) && same_set( $cids, array( 101, 103, 201, 900, 901 ) ), 'credits ' . implode( ',', $cids ) . ' rendered ' . implode( ',', $rids ) );
check( 'camp credits hidden when drafted', ! array_intersect( $cids, array( 301, 302, 303 ) ) );
check( 'trip-page-only credits gone from landing', ! array_intersect( $cids, array( 102, 104, 105, 202, 203 ) ) );
check( 'partner outro kept', false !== strpos( $landing, 'Logo and founders photo courtesy of the partner.' ) && false !== strpos( $landing, 'Trip photography is from our partner.' ) );
$no_partner = $config;
unset( $no_partner['partner_photo'], $no_partner['partner_logo'] );
load_data( $trips, $no_partner );
check( 'partner outro dropped without partner photos', false === strpos( arv_adv_render_landing(), 'Logo and founders photo courtesy' ) );
load_data( $trips, $config );

// Trip pages list their own photos only.
$madeira = arv_adv_render_trip( arv_adv_trip( 1 ) );
$norway  = arv_adv_render_trip( arv_adv_trip( 2 ) );
check( 'trip credits scoped to trip', same_set( credit_ids( $madeira ), array( 101, 102, 103, 104, 105 ) ) && same_set( credit_ids( $norway ), array( 201, 202, 203 ) ), 'madeira ' . implode( ',', credit_ids( $madeira ) ) . ' norway ' . implode( ',', credit_ids( $norway ) ) );
check( 'trip credits are on the page', ! array_diff( credit_ids( $madeira ), rendered_ids( $madeira ) ) && ! array_diff( credit_ids( $norway ), rendered_ids( $norway ) ) );
check( 'trip page has Photo Credits', false !== strpos( $madeira, '<summary>Photo Credits</summary>' ) );

// Editors still see the camp, and every camp mention comes back.
$GLOBALS['t_editor'] = true;
$ed_trips            = arv_adv_trips();
$ed_landing          = arv_adv_render_landing();
check( 'filter shows training when a camp is visible', 3 === count( $ed_trips ) && false !== strpos( $ed_landing, 'data-filter="training"' ) );
check( 'camp copy returns when a camp is visible', false !== strpos( $ed_landing, esc_html( arv_adv_landing_copy( true )['hero'] ) ) );
check( 'camp credits return when a camp is visible', (bool) array_intersect( credit_ids( $ed_landing ), array( 301 ) ) && ! array_intersect( credit_ids( $ed_landing ), array( 302, 303 ) ) );
$GLOBALS['t_editor'] = false;

// Draft redirect.
$landing_url = 'https://www.aravaiparunning.com/adventures/';
check( 'redirect draft to landing', array( $landing_url, 302 ) === arv_adv_unpublished_redirect( 'draft', false, $landing_url ) && array( $landing_url, 302 ) === arv_adv_unpublished_redirect( 'pending', false, $landing_url ) && array( $landing_url, 302 ) === arv_adv_unpublished_redirect( 'private', false, $landing_url ) );
check( 'no redirect for published', null === arv_adv_unpublished_redirect( 'publish', false, $landing_url ) && null === arv_adv_unpublished_redirect( 'trash', false, $landing_url ) );
check( 'no redirect for editors', null === arv_adv_unpublished_redirect( 'draft', true, $landing_url ) );
$go = arv_adv_unpublished_redirect( 'draft', false, $landing_url );
check( 'redirect uses 302', is_array( $go ) && 302 === $go[1] );

// Optional: render the landing from real data and write it out.
if ( ! empty( $argv[2] ) ) {
	$data = json_decode( (string) file_get_contents( $argv[2] ), true );
	load_data( $data['trips'], $data['config'] );
	$landing = arv_adv_render_landing();
	$cids    = credit_ids( $landing );
	check( 'data: every credit is on the landing', $cids && ! array_diff( $cids, rendered_ids( $landing ) ), 'credits ' . implode( ',', array_diff( $cids, rendered_ids( $landing ) ) ) );
	check( 'data: no camp text on the landing', 1 !== preg_match( '/training camp|cocodona/i', html_entity_decode( strip_tags( $landing ), ENT_QUOTES ) ) );
}
if ( ! empty( $argv[1] ) ) {
	file_put_contents( $argv[1], "<!doctype html>\n<html><head><meta charset=\"utf-8\"><meta name=\"robots\" content=\"noindex, nofollow\"><title>Aravaipa Adventures (fixture)</title></head><body class=\"arv-adv-page\">\n" . $landing . "\n</body></html>\n" );
	echo 'wrote ' . $argv[1] . "\n";
}

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
