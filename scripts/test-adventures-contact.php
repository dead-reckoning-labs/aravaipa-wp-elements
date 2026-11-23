<?php
/**
 * Adventures contact (job k74mfa): the adventures@ line on the landing and
 * on every trip page, the FAQ "Reach out" mailto link and the fallback Email
 * us button. Synthetic fixtures, no WordPress.
 *
 * Run: php scripts/test-adventures-contact.php
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
/**
 * Stand-in for wp_kses_post: keeps <a href>, <strong>, <em>, and drops any
 * href whose protocol is not in WordPress's default list subset used here
 * (http, https, mailto). Real wp_kses_post allows mailto by default.
 */
function wp_kses_post( $s ) {
	$s = strip_tags( (string) $s, '<a><strong><em><br>' );
	return preg_replace_callback(
		'/<a\s+href="([^"]*)"[^>]*>/i',
		static function ( $m ) {
			return preg_match( '#^(https?:|mailto:)#i', $m[1] ) ? '<a href="' . $m[1] . '">' : '<a>';
		},
		$s
	);
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

function trip_of( $id, $slug, $title, $type, $dest ) {
	return (object) array(
		'ID'          => $id,
		'post_name'   => $slug,
		'post_status' => 'publish',
		'post_title'  => $title,
		'menu_order'  => 0,
		'meta'        => array(
			'_arv_adv_type'        => $type,
			'_arv_adv_destination' => $dest,
			'_arv_adv_sort_year'   => '2027',
			'_arv_adv_sort_month'  => '6',
			'_arv_adv_length'      => '8 days',
			'_arv_adv_dates'       => 'June 2027',
			'_arv_adv_price'       => '$1,000',
			'_arv_adv_photos'      => json_encode( array( 'hero' => array( 'id' => 11 ), 'intro' => array( 'id' => 12 ), 'form' => array( 'id' => 13 ) ) ),
		),
	);
}

$email  = 'adventures@aravaiparunning.com';
$link   = '<a href="mailto:' . $email . '">Reach out</a>';
$answer = 'On some trips, yes. Madeira and Patagonia in particular have plenty to do off the trail. ' . $link . ' and we will tell you honestly whether it makes sense.';

$GLOBALS['t_posts']   = array(
	trip_of( 1, 'madeira-2027', 'Island in the Sky', 'Race', 'Madeira, Portugal' ),
	trip_of( 2, 'patagonia-2027', 'Fitz Roy', 'Adventure', 'Patagonia, Argentina' ),
);
$GLOBALS['t_options'] = array(
	'arv_adventures_landing'    => json_encode(
		array(
			'hero' => array( 'id' => 21 ),
			'form' => array( 'id' => 22 ),
			'faqs' => array(
				array( 'How big are the groups?', 'Small.' ),
				array( 'Can a non-running partner come?', $answer ),
				array( 'Bad link?', 'Try <a href="javascript:alert(1)">this</a>.' ),
			),
		)
	),
	'arv_adventures_landing_id' => 86050,
);

$line_re = '#<p class="aa-small-note aa-contact-line">Questions\? Email <a href="mailto:' . preg_quote( $email, '#' ) . '">' . preg_quote( $email, '#' ) . '</a></p>#';
$faq_re  = '#<summary>Can a non-running partner come\?</summary><div class="aa-faq-body"><p>[^<]*' . preg_quote( $link, '#' ) . ' and we will#';

$landing = arv_adv_render_landing();
$trips   = arv_adv_trips();
check( 'fixture trips', 2 === count( $trips ), (string) count( $trips ) );

$pages = array( 'landing' => $landing );
foreach ( $trips as $trip ) {
	$pages[ $trip['slug'] ] = arv_adv_render_trip( $trip );
}

// FAQ answer: the mailto link survives, a javascript: href does not.
$faq_ok = true;
foreach ( $pages as $name => $html ) {
	if ( 1 !== preg_match( $faq_re, $html ) || false !== stripos( $html, 'javascript:' ) ) {
		$faq_ok = false;
		echo "  faq on $name\n";
	}
}
check( 'faq mailto on landing and every trip page', $faq_ok );

// Contact line: exactly once on the hub, inside the FAQ section heading column.
check( 'hub contact line', 1 === preg_match_all( $line_re, $landing ) && 1 === preg_match( '#id="faq"><div class="aa-container aa-faq-grid"><div>.*?</h2>' . substr( $line_re, 1, -1 ) . '</div><div class="aa-faq-list">#s', $landing ) );

// Contact line: exactly once on every trip page, not in the hero or price panel.
$trip_ok = true;
foreach ( $trips as $trip ) {
	$html  = $pages[ $trip['slug'] ];
	$hero  = preg_match( '#<section class="aa-hero.*?</section>#s', $html, $m ) ? $m[0] : '';
	$panel = preg_match( '#<aside class="aa-price-panel">.*?</aside>#s', $html, $m ) ? $m[0] : '';
	if ( 1 !== preg_match_all( $line_re, $html ) || false !== strpos( $hero . $panel, 'aa-contact-line' ) || 1 !== preg_match( '#id="faq">.*?' . substr( $line_re, 1, -1 ) . '#s', $html ) ) {
		$trip_ok = false;
		echo '  contact line on ' . $trip['slug'] . "\n";
	}
}
check( 'trip contact line on every trip page', $trip_ok );

// Fallback button when the Contact Form 7 form is not there.
$form = arv_adv_render_form( array( 'id' => 5 ), 'Interest List' );
check( 'fallback button uses adventures@', false !== strpos( $form, 'class="aa-btn aa-btn-yellow" href="mailto:' . $email . '?subject=Aravaipa%20Adventures%20interest">Email us</a>' ) );

$all = implode( "\n", $pages ) . $form;
check( 'no info@ anywhere rendered', false === strpos( $all, 'info@aravaiparunning.com' ) && false === strpos( (string) file_get_contents( __DIR__ . '/../includes/adventures-render.php' ), 'info@aravaiparunning.com' ) );
check( 'no em dash in rendered pages or render source', 1 !== preg_match( '/\x{2014}/u', $all . file_get_contents( __DIR__ . '/../includes/adventures-render.php' ) ) );

// Booking state, dates and prices pass through untouched.
check( 'trip dates and price unchanged', false !== strpos( $pages['patagonia-2027'], '<dt>Trip Price</dt><dd>$1,000</dd>' ) && false !== strpos( $pages['patagonia-2027'], '<dt>Trip Dates</dt><dd>June 2027</dd>' ) );

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
