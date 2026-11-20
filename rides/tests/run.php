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
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['rides_transients'] = array();
$GLOBALS['rides_http']       = null; // null = network error, else array( code, body ).
$GLOBALS['rides_http_calls'] = 0;
function get_transient( $k ) {
	return $GLOBALS['rides_transients'][ $k ]['v'] ?? false;
}
function set_transient( $k, $v, $ttl ) {
	$GLOBALS['rides_transients'][ $k ] = array( 'v' => $v, 'ttl' => $ttl );
	return true;
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['rides_http_calls']++;
	$GLOBALS['rides_http_url'] = $url;
	return null === $GLOBALS['rides_http'] ? array( 'error' => true ) : $GLOBALS['rides_http'];
}
function is_wp_error( $r ) {
	return is_array( $r ) && ! empty( $r['error'] );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r[0] ?? 0;
}
function wp_remote_retrieve_body( $r ) {
	return $r[1] ?? '';
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
	ok( 3 === count( $GLOBALS['rides_enqueued'] ), "preview $id enqueues fonts, css and js" );
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


// 9. Community: YouTube feed parse, 12 h cache and fallback.
$feed = '<?xml version="1.0"?><feed xmlns:yt="http://www.youtube.com/xml/schemas/2015">'
	. '<entry><yt:videoId>AAAAAAAAAA1</yt:videoId><title>Older &amp; slower</title><published>2025-01-01T00:00:00+00:00</published></entry>'
	. '<entry><yt:videoId>BBBBBBBBBB2</yt:videoId><title>Newest ride</title><published>2026-02-01T00:00:00+00:00</published></entry>'
	. '<entry><yt:videoId>CCCCCCCCCC3</yt:videoId><title>Middle</title><published>2025-06-01T00:00:00+00:00</published></entry>'
	. '<entry><title>No id, skipped</title></entry></feed>';
$parsed = rides_parse_yt_feed( $feed );
ok( 3 === count( $parsed ), 'feed parse finds 3 videos and skips entries without an id' );
ok( 'BBBBBBBBBB2' === $parsed[0]['id'] && 'AAAAAAAAAA1' === $parsed[2]['id'], 'feed parse sorts newest first' );
ok( 'Older & slower' === $parsed[2]['title'], 'feed parse decodes titles' );
ok( array() === rides_parse_yt_feed( '' ) && array() === rides_parse_yt_feed( '<html>error</html>' ), 'feed parse of junk is empty' );
$fb = rides_fallback_videos();
ok( count( $fb ) >= 3, 'community.json fallback has at least 3 videos' );
$GLOBALS['rides_transients'] = array();
$GLOBALS['rides_http']       = null;
$v = rides_latest_videos( 3 );
ok( array_column( $v, 'id' ) === array_slice( array_column( $fb, 'id' ), 0, 3 ), 'network error falls back to community.json' );
ok( HOUR_IN_SECONDS === $GLOBALS['rides_transients'][ RIDES_YT_TRANSIENT ]['ttl'], 'fallback is cached only 1 h' );
ok( false !== strpos( $GLOBALS['rides_http_url'], 'feeds/videos.xml?channel_id=UCjGtoAWVpwUYPEHV5IrN8Yg' ), 'reads the channel RSS feed' );
$GLOBALS['rides_transients'] = array();
$GLOBALS['rides_http']       = array( 200, $feed );
$v = rides_latest_videos( 3 );
ok( array( 'BBBBBBBBBB2', 'CCCCCCCCCC3', 'AAAAAAAAAA1' ) === array_column( $v, 'id' ), 'live feed videos used when the feed works' );
ok( 12 * HOUR_IN_SECONDS === $GLOBALS['rides_transients'][ RIDES_YT_TRANSIENT ]['ttl'], 'feed cached 12 h' );
$calls = $GLOBALS['rides_http_calls'];
rides_latest_videos( 3 );
ok( $calls === $GLOBALS['rides_http_calls'], 'cached feed does not refetch' );
$GLOBALS['rides_transients'] = array();
$GLOBALS['rides_http']       = array( 500, 'oops' );
ok( array_column( rides_latest_videos( 3 ), 'id' ) === array_slice( array_column( $fb, 'id' ), 0, 3 ), 'HTTP 500 falls back to community.json' );
$GLOBALS['rides_transients'] = array();
$GLOBALS['rides_http']       = null;
$cm = rides_render_community();
ok( 3 === substr_count( $cm, 'youtube-nocookie.com/embed/' ) && 3 === substr_count( $cm, 'loading="lazy" allow="accelerometer' ), 'community renders 3 lazy YouTube embeds' );
ok( false === strpos( $cm, 'ytimg' ), 'community has no ytimg thumbnails' );
ok( false !== strpos( $cm, 'open.spotify.com/embed/show/78yAY8h5cNhSP91lTvyVrL' ) && false !== strpos( $cm, 'id1691934660' ) && false !== strpos( $cm, 'strava.com/clubs/aravaiparides' ), 'community has Spotify, Apple and Strava' );
$GLOBALS['rides_transients'] = array();

// 10. Logos and lockup.
foreach ( rides_events() as $e ) {
	ok( (bool) preg_match( '#^assets/logos/' . preg_quote( $e['slug'], '#' ) . '\.png$#', $e['logo'] ) && is_readable( RIDES_DIR . '/' . $e['logo'] ), "{$e['slug']} logo is a local asset" );
}
ok( false !== strpos( $home, 'class="rides-slide-logo' ), 'slides carry the ride logo as an HTML image' );
ok( false === strpos( $home, 'rides-tile-logo' ) && false !== strpos( $home, 'rides-tile-mark' ), 'tiles carry the big logo, no corner badge' );
ok( false !== strpos( $pp, 'rides-event-lockup' ) && false !== strpos( $pp, 'assets/logos/prickly-pedal.png' ), 'Prickly hero has the logo lockup' );
ok( false !== strpos( $home, 'Welcome to Aravaipa Rides' ) && false !== strpos( $home, 'never been more stoked' ), 'home has the welcome intro' );

// 11. v3 meet in the middle: eyebrows, normal-case headings, balanced grid, soft CSS.
$evh = rides_render_events( array( 'base' => '', 'today' => '2026-11-20' ) );
ok( substr_count( $home, 'class="rides-eyebrow"' ) >= 2 && false === strpos( $home, 'class="rides-kicker"' ), 'home section labels are .rides-eyebrow' );
ok( false !== strpos( $home, 'rides-slide-kicker rides-eyebrow' ), 'slide date line is an eyebrow' );
ok( false !== strpos( $pp, 'rides-slide-kicker rides-eyebrow' ) && false === strpos( $pp, 'class="rides-kicker"' ), 'Prickly section labels are eyebrows' );
ok( 1 === preg_match( '#<ul class="rides-tiles"[^>]*>(\s*<li>.*?</li>)+\s*</ul>#s', $evh ), 'events tiles are list items inside ul.rides-tiles (balanced grid hooks)' );
ok( 10 === substr_count( $evh, '<li><a class="rides-tile' ) || 10 <= substr_count( $evh, 'class="rides-tile ' ) + substr_count( $evh, 'class="rides-tile"' ), 'events render all 10 tiles' );
$css = file_get_contents( RIDES_DIR . '/assets/rides.css' );
ok( false !== strpos( $css, 'grid-template-columns: repeat(8, minmax(0, 1fr))' ) && false !== strpos( $css, ':nth-child(4n+1):nth-last-child(2)' ), 'tile grid centers a short last row' );
ok( false !== strpos( $css, 'height: min(60vh, 560px)' ) && false !== strpos( $css, 'height: 420px' ), 'hero slider is 60vh capped at 560px, 420px on phone' );
ok( 0 === preg_match( '#Oswald|Bebas#', $css ), 'no condensed display face left' );
ok( 0 === preg_match( '#box-shadow\s*:\s*+(?!none)#', $css ), 'no box shadows' );
preg_match_all( '#border-radius\s*:\s*([^;]+);#', $css, $rm );
$bad = array_filter( $rm[1], function ( $v ) { return ! in_array( trim( $v ), array( '0', 'var(--rides-radius)', '6px', '7px', '8px', '50%' ), true ); } );
ok( ! $bad, 'border-radius only 0, 6 to 8px or 50% (dots): ' . implode( ', ', $bad ) );

echo "$count checks, $fails failed\n";
exit( $fails ? 1 : 0 );
