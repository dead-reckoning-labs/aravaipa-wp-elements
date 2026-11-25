<?php
/**
 * Dev harness for [aravaipa_adventures_home] (includes/adventures-home.php).
 * Not shipped: build.sh copies only what aravaipa-elements.php requires.
 *
 *   php arv-adventures-home-test.php
 *
 * Stubs just enough WordPress to load includes/adventures.php for real and
 * feeds it trips from an in-memory post store, so the shortcode runs the
 * same arv_adv_trips( false ) path it runs on the site.
 */

define( 'ABSPATH', true );
define( 'ARV_ELEMENTS_URL', 'https://example.test/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_PATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_VERSION', '0.0.0-test' );

function add_action( ...$a ) {}
function add_filter( ...$a ) {}
$GLOBALS['SHORTCODES'] = array();
function add_shortcode( $tag, $fn ) { $GLOBALS['SHORTCODES'][ $tag ] = $fn; }
function shortcode_atts( $pairs, $atts, $tag = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $k => $v ) {
		$out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v;
	}
	return $out;
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s, $p = null ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function home_url( $p = '/' ) { return 'https://www.aravaiparunning.com' . $p; }
function current_user_can( $c ) { return $GLOBALS['CAN_EDIT']; }
$GLOBALS['CAN_EDIT'] = true;
$GLOBALS['ENQUEUED'] = array();
function wp_enqueue_style( $h, $src = '', $d = array(), $v = false ) { $GLOBALS['ENQUEUED'][] = $h; }
$GLOBALS['OPTIONS'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['OPTIONS'] ) ? $GLOBALS['OPTIONS'][ $k ] : $d; }
function wp_attachment_is_image( $id ) { return $id > 0; }
function wp_get_attachment_image( $id, $size, $icon, $atts ) {
	$o = '<img src="https://www.aravaiparunning.com/wp-content/uploads/' . (int) $id . '-1024x683.jpg" srcset="https://www.aravaiparunning.com/wp-content/uploads/' . (int) $id . '-768x512.jpg 768w, https://www.aravaiparunning.com/wp-content/uploads/' . (int) $id . '-1024x683.jpg 1024w"';
	foreach ( $atts as $k => $v ) {
		$o .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	return $o . ' />';
}
function wp_get_attachment_image_url( $id, $size ) { return 'https://www.aravaiparunning.com/wp-content/uploads/' . (int) $id . '.jpg'; }

// In-memory posts.
class WP_Post {
	public $ID;
	public $post_title;
	public $post_name;
	public $post_status;
	public $menu_order;
	public $meta = array();
}
$GLOBALS['POSTS'] = array();
function get_post( $p ) { return $p instanceof WP_Post ? $p : $GLOBALS['POSTS'][ $p ]; }
function get_the_title( $p ) { return get_post( $p )->post_title; }
function get_permalink( $p ) { return 'https://www.aravaiparunning.com/adventures/' . get_post( $p )->post_name . '/'; }
function get_post_meta( $id, $k, $single = false ) { $p = $GLOBALS['POSTS'][ $id ]; return isset( $p->meta[ $k ] ) ? $p->meta[ $k ] : ''; }
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['POSTS'] as $p ) {
		if ( in_array( $p->post_status, (array) $args['post_status'], true ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

require_once __DIR__ . '/includes/adventures.php';
require_once __DIR__ . '/includes/adventures-render.php';
require_once __DIR__ . '/includes/adventures-home.php';

function add_trip( $id, $name, $slug, $type, $dest, $dates, $y, $m, $hero, $status = 'publish' ) {
	$p              = new WP_Post();
	$p->ID          = $id;
	$p->post_title  = $name;
	$p->post_name   = $slug;
	$p->post_status = $status;
	$p->menu_order  = 0;
	$p->meta        = array(
		'_arv_adv_type'        => $type,
		'_arv_adv_destination' => $dest,
		'_arv_adv_dates'       => $dates,
		'_arv_adv_sort_year'   => (string) $y,
		'_arv_adv_sort_month'  => (string) $m,
		'_arv_adv_length'      => '8 days, 7 nights',
		'_arv_adv_photos'      => json_encode( array( 'hero' => array( 'id' => $hero, 'alt' => 'x', 'focal' => '50% 55%' ) ) ),
		'_arv_adv_race'        => 'Race' === $type ? json_encode( array( 'name' => 'Ultra Test' ) ) : '{}',
	);
	$GLOBALS['POSTS'][ $id ] = $p;
}

$pass = 0;
$fail = 0;
function t( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ok   $name\n";
	} else {
		$fail++;
		echo "  FAIL $name\n";
	}
}

$em = "\u{2014}";

echo "empty:\n";
$GLOBALS['ENQUEUED'] = array();
t( 'no trips renders nothing', '' === arv_adv_home_shortcode( array() ) );
t( 'no trips enqueues nothing', array() === $GLOBALS['ENQUEUED'] );
t( 'render with empty list is empty', '' === arv_adv_home_render( array(), null ) );
add_trip( 90, 'Draft Only', 'draft-only', 'Adventure', 'Nowhere', 'May 1-2, 2027', 2027, 5, 9, 'draft' );
t( 'drafts only, even for an editor, renders nothing', '' === arv_adv_home_shortcode( array() ) );

echo "five trips:\n";
add_trip( 1, 'Island in the Sky', 'madeira-2027', 'Race', 'Madeira, Portugal', 'June 6-13, 2027', 2027, 6, 101 );
add_trip( 2, 'Julian Alps Traverse', 'slovenia-2027', 'Adventure', 'Julian Alps, Slovenia', 'September 12-19, 2027', 2027, 9, 102 );
add_trip( 3, 'Madeira Epic Adventure', 'madeira-october-2027', 'Adventure', 'Madeira, Portugal', 'October 3-9, 2027', 2027, 10, 103 );
add_trip( 4, 'Fitz Roy <script>alert(1)</script> "Frontier"', 'patagonia-2027', 'Race', 'El Chaltén, Argentina', 'October 10-17, 2027', 2027, 10, 104 );
add_trip( 5, 'South Island Odyssey', 'new-zealand-2028', 'Adventure', 'South Island, New Zealand', 'December 5-12, 2027', 2027, 12, 105 );

$GLOBALS['ENQUEUED'] = array();
$out = arv_adv_home_shortcode( array() );
t( 'tile count 5 (no coming soon configured)', 5 === substr_count( $out, 'class="aah-tile' ) );
t( 'draft trip left out', false === strpos( $out, 'Draft Only' ) );
t( 'css enqueued on render', array( 'aravaipa-adventures-home' ) === $GLOBALS['ENQUEUED'] );
t( 'hub button', false !== strpos( $out, 'href="https://www.aravaiparunning.com/adventures/"' ) );
t( 'interest button', false !== strpos( $out, 'href="https://www.aravaiparunning.com/adventures/#interest"' ) );
t( 'trip links', false !== strpos( $out, 'href="https://www.aravaiparunning.com/adventures/slovenia-2027/"' ) );
t( 'dates shown', false !== strpos( $out, 'December 5-12, 2027' ) && false !== strpos( $out, 'June 6-13, 2027' ) );
t( 'calendar order', strpos( $out, 'June 6-13' ) < strpos( $out, 'September 12-19' ) && strpos( $out, 'October 10-17' ) < strpos( $out, 'December 5-12' ) );
t( 'race label and race name', false !== strpos( $out, '>Race Trip<' ) && false !== strpos( $out, 'Island in the Sky, Ultra Test' ) );
t( 'adventure label', false !== strpos( $out, '>Adventure<' ) );
t( 'hostile name escaped', false === strpos( $out, '<script>' ) && false !== strpos( $out, '&lt;script&gt;alert(1)&lt;/script&gt; &quot;Frontier&quot;' ) );
t( 'srcset and lazy', false !== strpos( $out, 'srcset="' ) && false !== strpos( $out, 'loading="lazy"' ) );
t( 'focal point kept', false !== strpos( $out, 'object-position:50% 55%' ) );
t( 'no base64', false === strpos( $out, 'base64' ) );
t( 'no em dash', false === strpos( $out, $em ) );
t( 'five tiles: first two wide (no orphan row)', false !== strpos( $out, 'aah-grid-lead-2' ) );
t( 'heading', false !== strpos( $out, '>Aravaipa Adventures</h2>' ) );

echo "coming soon:\n";
$GLOBALS['OPTIONS']['arv_adventures_landing'] = json_encode(
	array(
		'coming_soon' => array(
			'year'  => 2028,
			'photo' => array( 'id' => 106, 'alt' => 'x' ),
			'title' => '2028 destinations and dates coming soon!',
			'cta'   => 'Join the Interest List',
		),
	)
);
$out = arv_adv_home_shortcode( array() );
t( 'tile count 6 with coming soon', 6 === substr_count( $out, 'class="aah-tile' ) );
t( 'coming soon tile', false !== strpos( $out, '2028 destinations and dates coming soon!' ) && false !== strpos( $out, '2028 Season' ) );
t( 'six tiles: thirds', false !== strpos( $out, 'aah-grid-thirds' ) );
t( 'no em dash', false === strpos( $out, $em ) );

echo "attributes escaped:\n";
$out = arv_adv_home_shortcode( array( 'heading' => '<b>Hi</b>', 'intro' => 'a & b' ) );
t( 'heading escaped', false !== strpos( $out, '&lt;b&gt;Hi&lt;/b&gt;' ) );
t( 'intro escaped', false !== strpos( $out, 'a &amp; b' ) );

echo "grid modes:\n";
$modes = array();
foreach ( range( 1, 9 ) as $n ) {
	$modes[] = arv_adv_home_grid_mode( $n );
}
t( 'modes 1..9', array( 'solo', 'pairs', 'thirds', 'pairs', 'lead-2', 'thirds', 'lead-4', 'lead-2', 'thirds' ) === $modes );

echo "files:\n";
foreach ( array( 'includes/adventures-home.php', 'assets/aravaipa-adventures-home.css', 'arv-adventures-home-test.php' ) as $f ) {
	t( "no em dash in $f", false === strpos( file_get_contents( __DIR__ . '/' . $f ), $em ) );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
