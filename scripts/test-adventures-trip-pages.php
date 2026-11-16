<?php
/**
 * Render every published trip page from real data, without WordPress, and
 * check what Jay asked for: prices in the glance row and price panel, the
 * New Zealand dates and Milford wording, no hidden trip named anywhere.
 *
 * Run: php scripts/test-adventures-trip-pages.php <data.json> [outdir]
 *
 * data.json  {"trips": [...], "config": {...}} as in test-adventures-camps.php.
 * outdir     also write outdir/<slug>.html for each page.
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

if ( empty( $argv[1] ) || ! is_readable( $argv[1] ) ) {
	fwrite( STDERR, "usage: php scripts/test-adventures-trip-pages.php <data.json> [outdir]\n" );
	exit( 2 );
}
$data = json_decode( (string) file_get_contents( $argv[1] ), true );
$GLOBALS['t_posts'] = array();
foreach ( $data['trips'] as $t ) {
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
	'arv_adventures_landing'    => json_encode( $data['config'] ),
	'arv_adventures_landing_id' => 86050,
);
$outdir = ! empty( $argv[2] ) ? rtrim( $argv[2], '/' ) : '';

// Jay's prices (second note, Nov 16; New Zealand and Patagonia from his
// third note the same day, Patagonia at the WeTravel price).
$prices = array(
	'madeira-2027'     => '$3,895',
	'slovenia-2027'    => '$5,595',
	'patagonia-2027'   => '$4,895',
	'new-zealand-2028' => '$4,695',
);
$hidden = '/lofoten|norway|softrock|hardrock|san juan|colorado|cocodona|training camp/i';
$trips  = arv_adv_trips( false );
check( 'published trips', 4 === count( $trips ), (string) count( $trips ) );

foreach ( $trips as $trip ) {
	$slug = $trip['slug'];
	$html = arv_adv_render_trip( $trip );
	$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES );
	if ( $outdir ) {
		file_put_contents( $outdir . '/' . $slug . '.html', "<!doctype html>\n<html><head><meta charset=\"utf-8\"><meta name=\"robots\" content=\"noindex, nofollow\"><title>" . esc_html( $trip['name'] ) . " (preview)</title></head><body class=\"arv-adv-page\">\n" . $html . "\n</body></html>\n" );
	}
	if ( isset( $prices[ $slug ] ) ) {
		$p      = esc_html( $prices[ $slug ] );
		$glance = preg_match( '#<dt>Price</dt><dd>[^<]*' . preg_quote( $p, '#' ) . '#', $html );
		$panel  = preg_match( '#<dt>Trip Price</dt><dd>[^<]*' . preg_quote( $p, '#' ) . '#', $html );
		check( $slug . ' price in glance row and price panel', $glance && $panel, $prices[ $slug ] );
	}
	$hit = preg_match( $hidden, $html, $m ) ? $m[0] : '';
	check( $slug . ' names no hidden trip', '' === $hit, 'found ' . $hit );
	check( $slug . ' no skyrunning look easy', false === stripos( $text, 'skyrunning look easy' ) );
	check( $slug . ' no em dash', 1 !== preg_match( '/\x{2014}/u', $html ) );
	check( $slug . ' no not-a-hotel line', 1 !== preg_match( '/not a hotel/i', $text ) );
	if ( 'new-zealand-2028' === $slug ) {
		check( $slug . ' dates December 5-12, 2027', false !== strpos( $text, 'December 5-12, 2027' ) && 1 !== preg_match( '/november|dates TBA|coming soon/i', $text ) );
		check( $slug . ' Milford Track not optional, a few hours on it', 1 !== preg_match( '/optional[^.]*Milford Track|Milford Track[^.]*optional/i', $text ) && 1 === preg_match( '/few hours[^.]*Milford Track/i', $text ) );
		$own = json_encode( array( $trip['tagline'], $trip['overview'], $trip['highlights'], $trip['itinerary'], $trip['included'], $trip['not_included'], $trip['difficulty_note'], $trip['note'] ) );
		check( $slug . ' no future Ultra or Endurance version', 1 !== preg_match( '/\bUltra\b|Endurance/', $own ) );
	}
}

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
