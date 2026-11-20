<?php
/**
 * Tests for the Rides plugin with a stubbed WordPress.
 *
 *   php rides/tests/run.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['rides_hooks']   = array();
$GLOBALS['rides_options'] = array();
$GLOBALS['rides_meta']    = array();
$GLOBALS['rides_q']       = array( 'id' => 0, 'singular' => true, 'front' => false );

function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['rides_hooks'][ $hook ][] = $cb;
	return true;
}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	return add_filter( $hook, $cb, $prio, $args );
}
function apply( $hook, $value = null ) {
	foreach ( $GLOBALS['rides_hooks'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value );
	}
	return $value;
}
function run_action( $hook ) {
	ob_start();
	foreach ( $GLOBALS['rides_hooks'][ $hook ] ?? array() as $cb ) {
		$cb();
	}
	return ob_get_clean();
}
function get_option( $k, $d = false ) {
	return $GLOBALS['rides_options'][ $k ] ?? $d;
}
function get_post_meta( $id, $k, $single = false ) {
	return $GLOBALS['rides_meta'][ $id ][ $k ] ?? '';
}
function is_singular( $t = '' ) {
	return $GLOBALS['rides_q']['singular'];
}
function is_front_page() {
	return $GLOBALS['rides_q']['front'];
}
function get_queried_object_id() {
	return $GLOBALS['rides_q']['id'];
}
function wp_unslash( $v ) {
	return $v;
}
function plugins_url( $path, $file ) {
	return 'https://aravaiparides.com/wp-content/plugins/aravaipa-rides/' . ltrim( $path, '/' );
}
function wp_date( $f ) {
	return '2026-11-19';
}
function nocache_headers() {
	$GLOBALS['rides_test_headers'][] = 'nocache_headers()';
}
function wp_enqueue_style( $h, $src ) {
	$GLOBALS['rides_enqueued'][] = $src;
}
function wp_enqueue_script( $h, $src ) {
	$GLOBALS['rides_enqueued'][] = $src;
}
function wp_get_document_title() {
	return 'Prickly Pedal | Aravaipa Rides';
}
function get_permalink( $id ) {
	return 'https://aravaiparides.com/?p=' . $id;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

require dirname( __DIR__ ) . '/aravaipa-rides.php';

$fails = 0;
$count = 0;
function ok( $cond, $msg ) {
	global $fails, $count;
	$count++;
	if ( ! $cond ) {
		$fails++;
		echo "FAIL: $msg\n";
	}
}

/** Fresh request. */
function req( $id, $get = array(), $meta = null, $front = false ) {
	$GLOBALS['rides_state']        = array();
	$GLOBALS['rides_test_headers'] = array();
	$GLOBALS['rides_enqueued']     = array();
	$GLOBALS['rides_q']            = array( 'id' => $id, 'singular' => true, 'front' => $front );
	$_GET                          = $get;
	if ( null !== $meta ) {
		$GLOBALS['rides_meta'][ $id ]['_rides_v2_layout'] = $meta;
	} else {
		unset( $GLOBALS['rides_meta'][ $id ] );
	}
}

$TOKEN                                          = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
$GLOBALS['rides_options']['rides_preview_token'] = $TOKEN;
$orig                                           = '/theme/page.php';

// 1. A page without meta and without preview is untouched.
foreach ( array( 26, 102, 265, 22674, 24080, 23895 ) as $id ) {
	req( $id, array(), null, 26 === $id );
	ok( apply( 'template_include', $orig ) === $orig, "page $id without meta keeps its template" );
	ok( '' === rides_current_layout(), "page $id has no layout" );
	ok( '' === run_action( 'wp_head' ), "page $id prints nothing in wp_head" );
	run_action( 'template_redirect' );
	ok( array() === $GLOBALS['rides_test_headers'], "page $id sends no headers" );
	ok( apply( 'wp_robots', array( 'max-image-preview' => 'large' ) ) === array( 'max-image-preview' => 'large' ), "page $id robots untouched" );
	ok( apply( 'jetpack_enable_open_graph', true ) === true, "page $id keeps Jetpack og" );
	run_action( 'wp_enqueue_scripts' );
	ok( array() === $GLOBALS['rides_enqueued'], "page $id enqueues nothing" );
}

// 2. Wrong, short, empty or array tokens do nothing.
foreach ( array( 'wrong', '', substr( $TOKEN, 0, 31 ), $TOKEN . 'x', strtoupper( $TOKEN ) ) as $bad ) {
	req( 24080, array( 'rides_preview' => $bad ) );
	ok( apply( 'template_include', $orig ) === $orig, "bad token '$bad' keeps template" );
}
req( 24080, array( 'rides_preview' => array( $TOKEN ) ) );
ok( apply( 'template_include', $orig ) === $orig, 'array token keeps template' );

// 3. An unset or too short option never matches, even an empty token.
$GLOBALS['rides_options']['rides_preview_token'] = '';
req( 24080, array( 'rides_preview' => '' ) );
ok( '' === rides_current_layout(), 'empty option + empty token is not a preview' );
$GLOBALS['rides_options']['rides_preview_token'] = 'short';
req( 24080, array( 'rides_preview' => 'short' ) );
ok( '' === rides_current_layout(), 'short option is not accepted' );
$GLOBALS['rides_options']['rides_preview_token'] = $TOKEN;

// 4. Preview with the right token on the 5 phase 1 pages.
$map = array( 26 => 'home', 102 => 'events', 265 => 'results', 22674 => 'photos', 24080 => 'event:prickly-pedal' );
foreach ( $map as $id => $layout ) {
	req( $id, array( 'rides_preview' => $TOKEN ), null, 26 === $id );
	ok( rides_current_layout() === $layout, "preview $id resolves $layout" );
	ok( apply( 'template_include', $orig ) === RIDES_DIR . '/templates/page.php', "preview $id switches template" );
	run_action( 'template_redirect' );
	$h = implode( "\n", $GLOBALS['rides_test_headers'] );
	ok( false !== strpos( $h, 'X-Robots-Tag: noindex' ), "preview $id sends X-Robots-Tag noindex" );
	ok( false !== strpos( $h, 'no-store' ), "preview $id sends no-store" );
	$robots = apply( 'wp_robots', array( 'max-image-preview' => 'large' ) );
	ok( ! empty( $robots['noindex'] ) && ! isset( $robots['max-image-preview'] ), "preview $id wp_robots noindex" );
	ok( false === apply( 'jetpack_enable_open_graph', true ), "preview $id turns off Jetpack og" );
	$head = run_action( 'wp_head' );
	ok( (bool) preg_match( '/<meta name="description" content="[^"]{20,}">/', $head ), "preview $id meta description" );
	ok( (bool) preg_match( '#<meta property="og:image" content="https://aravaiparides.com/wp-content/plugins/aravaipa-rides/assets/heroes/[a-z-]+\.jpg">#', $head ), "preview $id og:image is a clean hero" );
	run_action( 'wp_enqueue_scripts' );
	ok( 2 === count( $GLOBALS['rides_enqueued'] ), "preview $id enqueues css and js" );
	$html = rides_render_layout( $layout, rides_wp_ctx() );
	ok( false !== strpos( $html, 'class="rides-v2' ), "preview $id renders .rides-v2" );
	ok( false === strpos( $html, "\u{2014}" ) && false === stripos( $html, '&' . 'mdash;' ) && false === strpos( $html, '&#' . '8212;' ), "preview $id has no em dash" );
	ok( false === strpos( $html, 'ytimg' ), "preview $id has no YouTube thumbnails" );
}

// 5. Preview token on a page outside phase 1 changes nothing.
req( 23895, array( 'rides_preview' => $TOKEN ) );
ok( '' === rides_current_layout(), 'preview token on Frenzy (not phase 1) does nothing' );
ok( apply( 'template_include', $orig ) === $orig, 'Frenzy keeps template under preview' );

// 6. Rollout: meta alone switches the layout, with no preview headers.
req( 24080, array(), 'event:prickly-pedal' );
ok( 'event:prickly-pedal' === rides_current_layout(), 'meta switches Prickly to the new layout' );
run_action( 'template_redirect' );
ok( array() === $GLOBALS['rides_test_headers'], 'rolled out page sends no noindex headers' );
$robots = apply( 'wp_robots', array( 'max-image-preview' => 'large' ) );
ok( empty( $robots['noindex'] ), 'rolled out page stays indexable' );
ok( false !== strpos( run_action( 'wp_head' ), 'og:image' ), 'rolled out page prints og:image' );

// 7. Junk meta is ignored.
foreach ( array( 'bogus', 'event:../../etc', 'event:not-a-ride', 'event:' ) as $junk ) {
	req( 24080, array(), $junk );
	ok( '' === rides_current_layout(), "junk meta '$junk' ignored" );
}

// 8. Renderers.
$ctx  = array( 'base' => 'https://x/', 'today' => '2026-11-19' );
$home = rides_render_home( $ctx );
$up   = array_filter( rides_events( '2026-11-19' ), function ( $e ) { return ! $e['is_past']; } );
ok( preg_match_all( '/class="rides-slide( is-active)?"/', $home ) === count( $up ), 'one slide per upcoming ride' );
ok( 1 === substr_count( $home, 'rides-slide is-active' ), 'exactly one active slide' );
ok( false !== strpos( $home, 'data-rides-prev' ) && false !== strpos( $home, 'data-rides-next' ), 'slider has prev and next' );
ok( substr_count( $home, 'data-rides-dot=' ) === count( $up ), 'one dot per slide' );
foreach ( rides_events() as $e ) {
	if ( ! empty( $e['register'] ) ) {
		ok( false !== strpos( $home, 'href="' . htmlspecialchars( $e['register'][0], ENT_QUOTES ) . '"' ), "slide register href for {$e['slug']} is the live link" );
	}
}
$past = rides_render_events( array( 'base' => 'https://x/', 'today' => '2026-12-07' ) );
ok( false !== strpos( $past, 'rides-tile is-past' ), 'a ride after its date is marked past' );
ok( false !== strpos( $past, 'Past event | December 6, 2026' ), 'past ride shows plain text, not a badge' );
$ev = rides_render_events( $ctx );
ok( 10 === preg_match_all( '/class="rides-tile( is-past)?"/', $ev ), 'events page has 10 tiles' );
$pp = rides_render_event( $ctx, 'prickly-pedal' );
$pp_ev = rides_event( 'prickly-pedal' );
ok( 2 === count( $pp_ev['register'] ), 'Prickly has two live register links' );
foreach ( $pp_ev['register'] as $u ) {
	$u = htmlspecialchars( $u, ENT_QUOTES );
	ok( false !== strpos( $pp, 'rides-register" href="' . $u . '"' ), "Prickly register button $u" );
}
ok( '' === rides_render_event( $ctx, 'nope' ), 'unknown event renders nothing' );
ok( 'J' === rides_initials( 'Jangover Night Rides' ) && 'LQC' === rides_initials( 'Last Quarter Crusher' ), 'initials helper' );

echo "$count checks, $fails failed\n";
exit( $fails ? 1 : 0 );
