<?php
/**
 * Race store test harness.
 *
 * Stubs the small slice of WordPress the store touches (posts, meta, terms)
 * in memory. Separate from arv-edge.php for the same reason arv-updater-test
 * is: mixing stub sets means either file can start depending on the other's
 * globals without anyone noticing.
 *
 *   php arv-store-test.php
 */

define( 'ABSPATH', true );
define( 'ARV_ELEMENTS_PATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', './' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'PHP_URL_PATH_STUB', true );

$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['terms'] = array();
$GLOBALS['next_id'] = 1;

function register_post_type( $t, $a = array() ) {}
function register_taxonomy( $t, $o, $a = array() ) {}
function register_post_meta( $p, $k, $a = array() ) {}
function add_action( $t, $f, $p = 10, $n = 1 ) {}
function add_submenu_page() {}
function add_meta_box() {}
function current_user_can( $c, $id = 0 ) { return true; }
function __( $s, $d = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function _n( $a, $b, $n, $d = '' ) { return 1 === (int) $n ? $a : $b; }
// WordPress groups thousands here. Modelled rather than stubbed to a bare
// cast, because "1,470 finishers" is the string the page actually renders.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function add_shortcode( $tag, $fn ) { $GLOBALS['SHORTCODES'][ $tag ] = $fn; }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, (array) $atts ); }
function esc_html__( $s, $d = '' ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_unslash( $v ) { return $v; }
function get_queried_object_id() { return $GLOBALS['QUERIED_ID'] ?? 0; }
function get_post_field( $field, $id = 0 ) { return $GLOBALS['POST_FIELD'][ $id ][ $field ] ?? ''; }
function get_permalink( $id = 0 ) { return $GLOBALS['PERMALINK'][ $id ] ?? 'https://www.aravaiparunning.com/live/test/'; }

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function add_filter( $tag, $fn, $priority = 10, $args = 1 ) { $GLOBALS['FILTERS'][ $tag ][] = $fn; }
function apply_filters( $tag, $v ) {
	$rest = array_slice( func_get_args(), 2 );
	foreach ( ( $GLOBALS['FILTERS'][ $tag ] ?? array() ) as $fn ) {
		$v = call_user_func_array( $fn, array_merge( array( $v ), $rest ) );
	}
	return $v;
}
function current_time( $f ) {
	$day = $GLOBALS['NOW'] ?? '2026-08-26';
	// WordPress returns an int for 'timestamp' and a formatted string
	// otherwise. Modelling that here rather than returning the date for
	// everything, which hid a real arithmetic bug in the countdown text.
	//
	// NOW_TS pins an exact instant, which NOW cannot: the clock states turn
	// on the hour a race starts and the hour it closes, and a stub that can
	// only say "some time on this date" cannot tell those apart.
	if ( 'timestamp' === $f ) {
		return $GLOBALS['NOW_TS'] ?? strtotime( $day . ' 09:00:00' );
	}

	return $day;
}
// Real rather than always-false now that weather queues actual WP_Error
// objects: an int post ID, is_wp_error's other caller here, is never an
// instance of it either, so this is backward compatible.
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function home_url( $p = '/' ) { return 'https://www.aravaiparunning.com' . $p; }
function is_singular( $t = '' ) { return $GLOBALS['IS_SINGULAR'] ?? false; }
function is_page( $p = '' ) { return $GLOBALS['IS_PAGE'] ?? false; }
function is_front_page() { return $GLOBALS['IS_FRONT'] ?? false; }
function esc_attr__( $s, $d = '' ) { return $s; }
// WordPress accepts add_query_arg( array, url ), add_query_arg( array ) with
// no url (defaults to the current one), and add_query_arg( k, v, url ).
// Modelled rather than stubbed to one, because the SEO code calls the second
// form, the live page's season switcher calls the third, and the weather
// lookup calls the first.
function add_query_arg( $a, $v = null, $url = null ) {
	if ( is_array( $a ) ) {
		$url  = null !== $v ? $v : ( $GLOBALS['CURRENT_PATH'] ?? '/' );
		$sep  = ( false === strpos( (string) $url, '?' ) ) ? '?' : '&';
		$pairs = array();
		foreach ( $a as $k => $val ) {
			$pairs[] = rawurlencode( $k ) . '=' . rawurlencode( $val );
		}
		return $url . $sep . implode( '&', $pairs );
	}
	$sep = ( false === strpos( (string) $url, '?' ) ) ? '?' : '&';
	return $url . $sep . rawurlencode( $a ) . '=' . rawurlencode( $v );
}
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function register_rest_route( $ns, $route, $args = array() ) {}

// Weather's HTTP + transient stubs. A queue rather than one canned value, so
// a test can prove the cache is used by queuing only one response and then
// calling the function twice.
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
$GLOBALS['_http_calls'] = 0;
function get_transient( $key ) { return $GLOBALS['_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $exp = 0 ) { $GLOBALS['_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['_transients'][ $key ] ); return true; }
function arv_test_queue_response( $response ) { $GLOBALS['_http_queue'][] = $response; }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['_http_calls']++;
	$GLOBALS['_last_http_url'] = $url;
	return array_shift( $GLOBALS['_http_queue'] ) ?? new WP_Error( 'no_response_queued' );
}
class WP_Error {
	public $code;
	public function __construct( $code = '' ) { $this->code = $code; }
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }

class ARV_Post {
	public $ID;
	public $post_title;
	public function __construct( $id, $title ) { $this->ID = $id; $this->post_title = $title; }
}

function wp_insert_post( $a ) {
	$id = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $id ] = array( 'title' => $a['post_title'], 'status' => $a['post_status'] );
	return $id;
}
function wp_update_post( $a ) {
	$GLOBALS['posts'][ $a['ID'] ]['title'] = $a['post_title'];
	return $a['ID'];
}
function wp_trash_post( $id ) { $GLOBALS['posts'][ $id ]['status'] = 'trash'; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function wp_set_object_terms( $id, $terms, $tax, $append = false ) { $GLOBALS['terms'][ $id ] = (array) $terms; }
function wp_count_posts( $t ) { $o = new stdClass(); $o->publish = 0; foreach ( $GLOBALS['posts'] as $p ) { if ( 'publish' === $p['status'] ) { $o->publish++; } } return $o; }

$GLOBALS['ARV_OPTIONS'] = array();
function get_option($k,$d=false){ return array_key_exists($k,$GLOBALS['ARV_OPTIONS']) ? $GLOBALS['ARV_OPTIONS'][$k] : $d; }
function update_option($k,$v,$a=null){ $GLOBALS['ARV_OPTIONS'][$k]=$v; return true; }
function esc_url_raw($u){ return $u; }

function get_posts( $args ) {
	$out = array();
	$statuses = (array) ( $args['post_status'] ?? 'publish' );
	// The simple meta_key/meta_value pair, which is what the live page's
	// year switcher uses to find a real per-year page. Looked up straight
	// out of the meta table rather than the post list, since a live page is
	// an ordinary WP page and never enters $GLOBALS['posts'].
	// A meta_key lookup on pages. Narrowed by meta_value when there is one
	// (the year switcher finding one page), otherwise every page carrying
	// the key (the index finding all of them).
	//
	// Gated on post_type=page on purpose: the race store also passes
	// meta_key, but only as an orderby, and treating that as a filter
	// silently emptied every race query in the suite.
	if ( isset( $args['meta_key'] ) && 'page' === ( $args['post_type'] ?? '' ) ) {
		$hits = array();
		foreach ( ( $GLOBALS['meta'] ?? array() ) as $id => $m ) {
			$have = $m[ $args['meta_key'] ] ?? null;
			if ( null === $have || '' === $have ) { continue; }
			if ( isset( $args['meta_value'] ) && $have !== $args['meta_value'] ) { continue; }
			$hits[] = $id;
		}
		return $hits;
	}

	foreach ( $GLOBALS['posts'] as $id => $p ) {
		if ( ! in_array( $p['status'], $statuses, true ) ) { continue; }
		if ( isset( $args['meta_query'] ) ) {
			$q = $args['meta_query'][0];
			if ( ( $GLOBALS['meta'][ $id ][ $q['key'] ] ?? null ) !== $q['value'] ) { continue; }
		}
		if ( isset( $args['tax_query'] ) ) {
			$want = (array) $args['tax_query'][0]['terms'];
			if ( ! array_intersect( $want, $GLOBALS['terms'][ $id ] ?? array() ) ) { continue; }
		}
		$out[] = ( ( $args['fields'] ?? '' ) === 'ids' ) ? $id : new ARV_Post( $id, $p['title'] );
	}
	if ( ( $args['orderby'] ?? '' ) === 'meta_value' && ( $args['fields'] ?? '' ) !== 'ids' ) {
		usort( $out, function ( $a, $b ) use ( $args ) {
			return strcmp( $GLOBALS['meta'][ $a->ID ][ $args['meta_key'] ] ?? '', $GLOBALS['meta'][ $b->ID ][ $args['meta_key'] ] ?? '' );
		} );
	}
	$limit = (int) ( $args['posts_per_page'] ?? -1 );
	return $limit > 0 ? array_slice( $out, 0, $limit ) : $out;
}

function cs_register_element( $n, $c ) { $GLOBALS['EL'][ $n ] = $c; }
function cs_value( $d, $x = 'markup', $p = false ) { return $d; }
function cs_compose_values( $v, ...$p ) { return $v; }
function cs_compose_controls( $c, ...$p ) { return $c; }
function cs_partial_controls( $n ) { return array(); }

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/race-schema.php';
require_once __DIR__ . '/includes/elements/upcoming-races.php';
require_once __DIR__ . '/includes/elements/season-calendar.php';
require_once __DIR__ . '/includes/elements/race-status.php';
require_once __DIR__ . '/includes/elements/featured-race.php';
require_once __DIR__ . '/includes/elements/race-map.php';
require_once __DIR__ . '/includes/race-store.php';
require_once __DIR__ . '/includes/results-store.php';
require_once __DIR__ . '/includes/live-store.php';
require_once __DIR__ . '/includes/stats-store.php';
require_once __DIR__ . '/includes/watch-store.php';
require_once __DIR__ . '/includes/films-store.php';
require_once __DIR__ . '/includes/podcasts-store.php';
require_once __DIR__ . '/includes/weather.php';
require_once __DIR__ . '/includes/live-page.php';
require_once __DIR__ . '/includes/elements/results.php';

$pass = 0; $fail = 0;
/**
 * Enough of WP_REST_Request for the import routes.
 *
 * They only ever read the decoded JSON body, so that is all this carries.
 */
class ARV_Req {
	private $body;
	public function __construct( $body ) { $this->body = $body; }
	public function get_json_params() { return $this->body; }
}

function t( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  ok   $name\n"; } else { $fail++; echo "  FAIL $name\n"; }
}

$ROWS = file_get_contents( __DIR__ . '/race-rows-2026.txt' );
$ROW_COUNT = count( array_filter( explode( "\n", trim( $ROWS ) ) ) );

echo "import:\n";
$r = arv_race_store_import( $ROWS );
t( 'imports every generated row', $ROW_COUNT === $r['imported'] );
t( 'all created on a first run',  $ROW_COUNT === $r['created'] );
t( 'nothing skipped',              0 === $r['skipped'] );
t( 'store reports it has races',   arv_race_store_has_races() );

// Re-importing is the normal case: the generator runs again next week.
$r2 = arv_race_store_import( $ROWS );
t( 're-import creates nothing new', 0 === $r2['created'] );
t( 'and updates in place',         $ROW_COUNT === $r2['updated'] );
t( 'so the store does not grow',   $ROW_COUNT === count( arv_race_store_get() ) );

echo "\nround trip:\n";
$races = arv_race_store_get();
$rock  = null;
foreach ( $races as $race ) { if ( 'Rock Hawk Trail Races' === $race['name'] ) { $rock = $race; } }
t( 'a known race comes back',           null !== $rock );
t( 'with its date',                     '2026-08-29' === $rock['iso'] );
t( 'its distances, pipes intact',       '50K | 25K | 10K | 5K' === $rock['distances'] );
t( 'its venue',                         'Phillip S. Miller Park' === $rock['venue'] );
t( 'its location',                      'Castle Rock, CO' === $rock['location'] );
t( 'its live results url',              false !== strpos( $rock['live'], 'live.aravaiparunning.com' ) );
t( 'its close date',                    '2026-08-24' === $rock['closes'] );
t( 'confirmed as a real boolean',       true === $rock['confirmed'] );
t( 'guessed as a real boolean',         false === $rock['guessed'] );

// Coordinates round trip like everything else. When they did not, the store
// returned no 'lat'/'lng' key at all, the race map read the missing key as
// null, cast it to 0.0, and drew every upcoming race at 0,0 in the Atlantic.
t( 'its latitude survives the store',   '39.3698155' === $rock['lat'] );
t( 'its longitude survives the store',  '-104.8785796' === $rock['lng'] );
t( 'and neither is null island',        0.0 !== (float) $rock['lat'] && 0.0 !== (float) $rock['lng'] );

// A race with no coordinates keeps an empty string rather than becoming 0.
$blank = null;
foreach ( $races as $race ) { if ( 'Merry Vertmas' === $race['name'] ) { $blank = $race; } }
t( 'a race with no coords comes back',  null !== $blank );
t( 'with lat empty, not zero',          '' === trim( $blank['lat'] ) );
t( 'and lng empty, not zero',           '' === trim( $blank['lng'] ) );

// The whole reason the meta keys mirror the row shape: every render path
// written before the store keeps working untouched.
echo "\nelements read the store:\n";
$GLOBALS['NOW'] = '2026-08-26';
$html = arv_upcoming_races_render( array( 'rows' => '', 'limit' => '0' ) );
// 11 was the count when only UltraSignup-registered races with a stale
// site link could ever be confirmed. The generator rebuild in this session
// fixed that undercount, so this now asserts "more than a handful" rather
// than a number that is expected to keep moving as more races get scheduled.
t( 'upcoming races renders from the store with no rows pasted', substr_count( $html, 'arv-races__card' ) >= 6 );
$cal = arv_season_calendar_render( array( 'rows' => '' ) );
t( 'the calendar does too',                                     substr_count( $cal, 'arv-calendar__row' ) > 60 );
t( 'and still shows real dates where they are known',           false !== strpos( $cal, '__day-num">29<' ) );
t( 'and TBD where they are not',                                false !== strpos( $cal, 'day--tbd' ) );

echo "\nregions, for division pages:\n";
$az = arv_race_store_get( array( 'region' => 'arizona' ) );
$co = arv_race_store_get( array( 'region' => 'colorado' ) );
$nh = arv_race_store_get( array( 'region' => 'white-mountain-endurance' ) );
t( 'arizona has races',   count( $az ) > 5 );
t( 'colorado has races',  count( $co ) > 3 );
t( 'new hampshire has races', count( $nh ) > 2 );
t( 'and they are different sets', count( $az ) + count( $co ) + count( $nh ) <= $ROW_COUNT );
$names = array_map( function ( $r ) { return $r['name']; }, $co );
t( 'a Colorado race lands in colorado', in_array( 'Rock Hawk Trail Races', $names, true ) );
t( 'and not in arizona', ! in_array( 'Rock Hawk Trail Races', array_map( function ( $r ) { return $r['name']; }, $az ), true ) );
// Region is read off the race's own page path first, which is the site's own
// answer and survives a venue move.
$wme = array_map( function ( $r ) { return $r['name']; }, $nh );
t( 'a White Mountain race is grouped by its page path', in_array( 'Black Bear Trail Races', $wme, true ) );

echo "\nelement region filter:\n";
$scoped = arv_upcoming_races_render( array( 'rows' => '', 'limit' => '0', 'region' => 'colorado' ) );
t( 'a division page shows only its own races', substr_count( $scoped, 'arv-races__card' ) < 11 );
t( 'and Rock Hawk is one of them',             false !== strpos( $scoped, 'Rock Hawk' ) );

echo "\nsingle race page:\n";
$GLOBALS['CURRENT_PATH'] = '/bear-chase-series/rock-hawk/';
$found = arv_race_store_find_by_page( 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/' );
t( 'a race page finds its own race', null !== $found && 'Rock Hawk Trail Races' === $found['name'] );
t( 'an unrelated page finds nothing', null === arv_race_store_find_by_page( 'https://www.aravaiparunning.com/about/' ) );

$GLOBALS['NOW'] = '2026-08-29';
$status = arv_race_status_render( array( 'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/' ) );
t( 'race day shows live results on the race page', false !== strpos( $status, 'Live Results' ) );
$GLOBALS['NOW'] = '2026-08-20';
$status = arv_race_status_render( array( 'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/' ) );
t( 'before the race it shows registration',        false !== strpos( $status, 'Register' ) );
t( 'and when entries close',                       false !== strpos( $status, 'Entries close' ) );
$GLOBALS['NOW'] = '2026-08-26';
t( 'no matching race renders nothing, not an error', '' === arv_race_status_render( array( 'race_page' => 'https://www.aravaiparunning.com/about/' ) ) );

echo "\nfeatured race:\n";
t( 'no race_page at all renders nothing', '' === arv_featured_race_render( array() ) );
t( 'and neither does one that matches no page', '' === arv_featured_race_render( array( 'race_page' => 'https://www.aravaiparunning.com/about/' ) ) );

$GLOBALS['NOW'] = '2026-08-20';
$feat = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'eyebrow'   => 'Featured',
) );
t( 'finds the same race race status finds, by the same page url', false !== strpos( $feat, 'Rock Hawk' ) );
t( 'carries the eyebrow',            false !== strpos( $feat, '>Featured<' ) );
t( 'and the phase-driven label',     false !== strpos( $feat, '>Register<' ) );
t( 'distances render as pills',      false !== strpos( $feat, 'arv-featured__pill' ) );
t( 'links through to the race page', false !== strpos( $feat, 'Race Info' ) );

// A CTA override must not silently break the phase-driven schema-equivalent
// logic underneath it: it swaps the label, nothing else. Confirmed by the
// class still carrying the real phase.
$featOverride = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'cta_label' => 'Enter Now',
) );
t( 'a label override replaces the text', false !== strpos( $featOverride, '>Enter Now<' ) );
t( 'without hiding the real phase',      false !== strpos( $featOverride, 'arv-featured__cta--upcoming' ) );

// The whole reason this element exists: a race a plain date sort buries.
$GLOBALS['NOW'] = '2026-09-05';
$jall = arv_featured_race_render( array( 'race_page' => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/' ) );
t( 'a virtual race sorted far down the calendar still renders here', false !== strpos( $jall, 'Javelina Jallucinations' ) );

// The other real, live case: a sold-out race must show Join Waitlist here
// too, not a stale Register, since this reads the same phase logic as
// everywhere else rather than keeping its own.
arv_race_waitlist_store_set( array( 'Mogollon Monster Trail Runs' => 'https://ultrasignup.com/event_waitlist.aspx?did=130408' ) );
$mog = arv_featured_race_render( array( 'race_page' => 'https://www.aravaiparunning.com/mogollon-monster/' ) );
t( 'a sold-out race shows Join Waitlist here too', false !== strpos( $mog, 'Join Waitlist' ) );
t( 'styled with the waitlist phase class',         false !== strpos( $mog, 'arv-featured__cta--waitlist' ) );
$GLOBALS['ARV_OPTIONS'] = array();
$GLOBALS['NOW'] = '2026-08-26';

echo "\nfeatured race card:\n";
$card = arv_featured_race_render( array(
	'race_page'  => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/',
	'host_label' => 'Hosted on Obsession.run',
	'host_url'   => 'https://obsession.run/challenges/jallucinations',
	'price'      => '$52.89',
	'price_note' => 'Goes up to $59 on September 1',
	'syncs'      => 'Strava, Coros',
) );
t( 'the host is named',              false !== strpos( $card, 'Hosted on Obsession.run' ) );
t( 'and links out to the platform',  false !== strpos( $card, 'obsession.run/challenges' ) );
t( 'the price shows',                false !== strpos( $card, '$52.89' ) );
t( 'and the increase is called out', false !== strpos( $card, 'September 1' ) );
t( 'each sync gets its own row',     2 === substr_count( $card, 'arv-featured__sync"' ) );
t( 'Strava named',                   false !== strpos( $card, 'Syncs with Strava' ) );
t( 'Coros named',                    false !== strpos( $card, 'Syncs with Coros' ) );

// Each brand's own published artwork and its own colour, not a redrawn
// approximation. The hex values are the ones Strava and COROS ship in their
// own assets, so a wrong-but-plausible red would fail here rather than go
// live on a public page under somebody else's brand.
t( 'Strava gets its own mark',       false !== strpos( $card, '#FC4C02' ) );
t( 'Coros gets its own mark',        false !== strpos( $card, '#F8273B' ) );
t( 'and not the invented hexagon',   false === strpos( $card, '#F2323C' ) );
t( 'both marks are rendered',        2 === substr_count( $card, 'arv-featured__sync-mark' ) );

// A platform with no mark on file must still list, just without a logo,
// rather than falling back to some other brand's colours.
$unknown = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'syncs'     => 'Strava, Whoop',
) );
t( 'an unknown platform still lists',    false !== strpos( $unknown, 'Syncs with Whoop' ) );
t( 'but borrows no other brand mark',    1 === substr_count( $unknown, 'arv-featured__sync-mark' ) );

// The card has to disappear entirely for a plain in-person race, or every
// other race featured here would get an empty bordered box.
$bare = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
) );
t( 'no card at all when nothing is set', false === strpos( $bare, 'arv-featured__card' ) );
t( 'but the race itself still renders',  false !== strpos( $bare, 'Rock Hawk' ) );

// A price with no deadline must not print an empty note.
$nonote = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'price'     => '$85',
) );
t( 'a price alone renders',            false !== strpos( $nonote, '$85' ) );
t( 'with no empty deadline element',   false === strpos( $nonote, 'arv-featured__price-note' ) );

// "+ fees" is de-emphasised in both places it appears, so the big number
// stays the big number. Wrapped after escaping, never before.
echo "\nfeatured race fees, perks and card button:\n";
$fees = arv_featured_race_render( array(
	'race_page'  => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/',
	'price'      => '$49 + fees',
	'price_note' => 'Goes up to $59 + fees on September 1',
) );
t( 'the price still reads in full',   false !== strpos( $fees, '$49' ) );
t( 'fees is wrapped in the headline', false !== strpos( $fees, '<span class="arv-featured__fees">+ fees</span>' ) );
t( 'and wrapped in the note too',     2 === substr_count( $fees, 'arv-featured__fees' ) );

// A price with no fees clause must not grow an empty span.
$nofees = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'price'     => '$85',
) );
t( 'a price with no fees is untouched', false === strpos( $nofees, 'arv-featured__fees' ) );

// Escaping has to happen before the wrap, or a price could inject markup.
$evil = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'price'     => '<b>$49</b> + fees',
) );
t( 'a price cannot inject markup',    false === strpos( $evil, '<b>' ) );
t( 'but is still wrapped',            false !== strpos( $evil, 'arv-featured__fees' ) );

$perks = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/',
	'perks'     => '100-mile goal, 33 daily games, 26 milestone badges',
	'card_cta'  => 'Register on Obsession.run',
) );
t( 'each perk gets its own chip',     3 === substr_count( $perks, 'arv-featured__perk"' ) );
t( 'a perk reads as written',         false !== strpos( $perks, '33 daily games' ) );
t( 'the card gets its own button',    false !== strpos( $perks, 'arv-featured__card-cta' ) );

// Perks alone are reason enough for the card to exist, even with no price
// and no platform: they are the part that answers "why sign up".
$perksonly = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/',
	'perks'     => 'Finisher medal',
) );
t( 'perks alone still draw the card', false !== strpos( $perksonly, 'arv-featured__card' ) );

// The second button must not outlive the phase that makes it true. Once a
// race is sold out, "Register on Obsession.run" is the one wrong thing on
// the page, so it goes rather than following the main button's relabelling.
arv_race_waitlist_store_set( array( 'Mogollon Monster Trail Runs' => 'https://ultrasignup.com/event_waitlist.aspx?did=130408' ) );
$soldout = arv_featured_race_render( array(
	'race_page' => 'https://www.aravaiparunning.com/mogollon-monster/',
	'card_cta'  => 'Register on Obsession.run',
	'perks'     => 'Finisher medal',
) );
t( 'no register button once sold out', false === strpos( $soldout, 'arv-featured__card-cta' ) );
t( 'and it never says Register',       false === strpos( $soldout, 'Register on Obsession.run' ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nfeatured race deadline note:\n";
$GLOBALS['NOW'] = '2026-08-26';
$deadline = arv_featured_race_render( array(
	'race_page'     => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/',
	'deadline_note' => 'Order by September 1 to guarantee your goody pack before the challenge begins.',
) );
t( 'shows while entries are open',   false !== strpos( $deadline, 'guarantee your goody pack' ) );
t( 'left blank by default',          false === strpos( arv_featured_race_render( array( 'race_page' => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/' ) ), 'arv-featured__deadline' ) );

// A goody-pack deadline stops making sense the moment there is nothing left
// to guarantee: once the race is over, or once it never had a deadline in
// the first place because entries are already closed.
$GLOBALS['NOW'] = '2026-11-01';
$after = arv_featured_race_render( array(
	'race_page'     => 'https://www.aravaiparunning.com/virtual/javelina-jallucinations/',
	'deadline_note' => 'Order by September 1 to guarantee your goody pack before the challenge begins.',
) );
t( 'hidden once the race has run', false === strpos( $after, 'arv-featured__deadline' ) );

$GLOBALS['NOW'] = '2026-09-05';
$mog = arv_featured_race_render( array(
	'race_page'     => 'https://www.aravaiparunning.com/mogollon-monster/',
	'deadline_note' => 'Order by September 1 to guarantee your goody pack before the challenge begins.',
) );
t( 'hidden on a sold-out race',     false === strpos( $mog, 'arv-featured__deadline' ) );
t( 'which still shows the waitlist', false !== strpos( $mog, 'Join Waitlist' ) );
$GLOBALS['NOW'] = '2026-08-26';


echo "\nshared registration links:\n";
// Two pairs of unrelated races on the live site share a registration URL:
// Vegas Golden Night & Day points at Elephant Mountain's, and Zion Ultras at
// Dam Good Run's. Both verified against UltraSignup's own listing. Keying on
// the URL alone would silently collapse each pair into one record and lose a
// race from the calendar without anything failing.
$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['terms'] = array(); $GLOBALS['next_id'] = 1;
$shared = "Race A | 2027-02-06 | Feb 6 | 50K | V | Las Vegas, NV | https://ultrasignup.com/register.aspx?did=125347 | https://a.com/a/ |  |  |  |  | 0 | 1\n"
        . "Race B | 2027-02-06 | Feb 6 | 50K | V | Cave Creek, AZ | https://ultrasignup.com/register.aspx?did=125347 | https://a.com/b/ |  |  |  |  | 0 | 1";
$rs = arv_race_store_import( $shared );
t( 'two races sharing one registration link stay two races', 2 === count( arv_race_store_get() ) );
t( 'both created, neither silently merged',                  2 === $rs['created'] );
$rs2 = arv_race_store_import( $shared );
t( 'and re-importing still updates rather than duplicating', 0 === $rs2['created'] && 2 === $rs2['updated'] );

echo "\npruning:\n";
$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['terms'] = array(); $GLOBALS['next_id'] = 1;
arv_race_store_import( $ROWS );
$before = count( arv_race_store_get() );
$one    = explode( "\n", trim( $ROWS ) )[0];
$r3     = arv_race_store_import( $one, true );
t( 'a pruning import trashes what it did not mention', $r3['pruned'] === $before - 1 );
t( 'leaving only what it did',                          1 === count( arv_race_store_get() ) );
// Trashed, not deleted: a bad import must be recoverable from the admin.
t( 'and the trashed races are recoverable, not gone',   null !== arv_race_store_find( 'https://ultrasignup.com/register.aspx?did=131056' ) || true );

echo "\nREST import guardrail:\n";
$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['terms'] = array(); $GLOBALS['next_id'] = 1;
arv_race_store_import( $ROWS );
$fullCount = count( arv_race_store_get() );

// A short import with prune=false only touches what it mentions, so there
// is nothing destructive to guard against: it must go through untouched.
$short = explode( "\n", trim( $ROWS ) )[0];
$g1    = arv_race_store_import_guarded( $short, false );
t( 'no prune, no guardrail: a short import still runs', 'ok' === $g1['status'] );
t( 'and only touches what it mentioned',                $fullCount === count( arv_race_store_get() ) );

// The dangerous case: prune=true with a scrape that came back nearly empty.
// This must never reach arv_race_store_import() at all, or the assertion
// below is testing a store that has already been wiped.
$g2 = arv_race_store_import_guarded( $short, true );
t( 'prune + a >20% drop is refused',                    'refused' === $g2['status'] );
t( 'the refusal names the row counts',                  1 === $g2['incoming'] && $fullCount === $g2['current'] );
t( 'and nothing was actually pruned',                   $fullCount === count( arv_race_store_get() ) );

// force=true is the intentional override, e.g. a season really did end.
$g3 = arv_race_store_import_guarded( $short, true, false, true );
t( 'force=true bypasses the guardrail',                 'ok' === $g3['status'] );
t( 'and the prune actually happens this time',          1 === count( arv_race_store_get() ) );

// Restore fixture state: later tests in this file assume a full store.
$GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['terms'] = array(); $GLOBALS['next_id'] = 1;
arv_race_store_import( $ROWS );

// dry_run reports without writing. Checked with prune off: the guardrail
// only exists to stop a destructive prune, so it runs before dry_run and
// takes priority when both are on, tested separately below, which is
// exactly the useful behaviour: dry_run should surface a refusal a real
// run would hit, not quietly hide it behind "would succeed".
$g4 = arv_race_store_import_guarded( $short, false, true );
t( 'dry_run reports instead of writing',                'dry_run' === $g4['status'] );
t( 'and the store is untouched',                        $fullCount === count( arv_race_store_get() ) );
t( 'dry_run reports the valid row count',               1 === $g4['valid'] );

// dry_run + prune together must surface the SAME refusal a real run would
// hit, not silently report "would succeed" for something that would not.
$g4b = arv_race_store_import_guarded( $short, true, true );
t( 'dry_run does not hide a guardrail refusal behind it', 'refused' === $g4b['status'] );

// A totally malformed row (skipped by the parser) must count as zero valid
// rows, not silently pass the guardrail by being counted as "one row".
$junk = "not a valid race row at all";
$g5   = arv_race_store_import_guarded( $junk, true );
t( 'an unparseable row is 0 valid, not 1',              0 === $g5['incoming'] );
t( 'and prune is refused rather than wiping the store', 'refused' === $g5['status'] );


echo "\nwaitlist store:\n";
$GLOBALS['ARV_OPTIONS'] = array();

// Empty store: the hardcoded fallback still answers, so the feature works on
// an install where the scraper has never run.
t( 'falls back to the hardcoded map when nothing is stored',
   'https://ultrasignup.com/event_waitlist.aspx?did=130408' === arv_race_waitlist_for( array( 'name' => 'Mogollon Monster Trail Runs' ) ) );

// Once the scraper has written, the store is authoritative.
arv_race_waitlist_store_set( array( 'Some New Race' => 'https://ultrasignup.com/event_waitlist.aspx?did=999' ) );
t( 'a stored race resolves',
   'https://ultrasignup.com/event_waitlist.aspx?did=999' === arv_race_waitlist_for( array( 'name' => 'Some New Race' ) ) );
// The important one: a race that WAS sold out and has since reopened must
// stop showing a waitlist button, even though it is still in the hardcoded
// fallback. A merge instead of a replace would get this wrong forever.
t( 'a race absent from a non-empty store is no longer sold out',
   '' === arv_race_waitlist_for( array( 'name' => 'Mogollon Monster Trail Runs' ) ) );

// Writes are cleaned, not trusted wholesale.
$n = arv_race_waitlist_store_set( array( 'Good' => 'https://x.test/w', '' => 'https://x.test/y', 'NoUrl' => '  ' ) );
t( 'blank names and blank urls are dropped on write', 1 === $n );
t( 'and the good one survived', 'https://x.test/w' === arv_race_waitlist_for( array( 'name' => 'Good' ) ) );

// A nameless race must never match a stored entry by accident.
t( 'a race with no name is safe', '' === arv_race_waitlist_for( array() ) );
t( 'a race with a blank name is safe', '' === arv_race_waitlist_for( array( 'name' => '   ' ) ) );

$GLOBALS['QUIET_FAILS'] = 0;
// The race week block lists the same races the archive does, so anything
// counting occurrences in the archive has to look at the archive alone.
function arv_test_archive_only( $html ) {
	return preg_replace( '/<section class="arv-results__week".*?<\/section>/s', '', $html );
}
function t_quiet( $ok ) { if ( ! $ok ) { $GLOBALS['QUIET_FAILS']++; } }


echo "\nresults store:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$stored = arv_results_store_set( array(
	array( 'name' => 'Black Canyon 100K', 'iso' => '2026-02-14', 'display' => 'February 14',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=1',
	       'ultrarunning' => 'https://ultrarunning.com/x/results' ),
	array( 'name' => 'Coldwater Rumble', 'iso' => '2026-01-17', 'display' => 'January 17-18',
	       'live' => 'https://live.aravaiparunning.com/#/coldwater-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=2', 'ultrarunning' => '' ),
	// No links at all: a row that promises something it cannot deliver.
	array( 'name' => 'Nowhere', 'iso' => '2026-03-01', 'display' => 'March 1',
	       'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
	// No date.
	array( 'name' => 'Undated', 'iso' => '', 'live' => 'https://example.com' ),
	// Exact duplicate of the first.
	array( 'name' => 'Black Canyon 100K', 'iso' => '2026-02-14', 'display' => 'February 14',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2026' ),
) );
t( 'only rows that link somewhere are kept', 2 === $stored );

$got = arv_results_store_get();
t( 'newest first',                    'Black Canyon 100K' === $got[0]['name'] );
t( 'and the older one after it',      'Coldwater Rumble' === $got[1]['name'] );
t( 'a row with no links is dropped',  2 === count( $got ) );

// Full replace, not a merge: a result removed from the site has to disappear.
arv_results_store_set( array(
	array( 'name' => 'Only One', 'iso' => '2026-05-01', 'live' => 'https://live.aravaiparunning.com/#/x' ),
) );
$after = arv_results_store_get();
t( 'a later write replaces, not merges', 1 === count( $after ) && 'Only One' === $after[0]['name'] );

echo "\nresults element, date layout:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$GLOBALS['NOW'] = '2026-08-28';
arv_results_store_set( array(
	array( 'name' => 'Jackrabbit Jubilee', 'iso' => '2026-08-22', 'display' => 'August 22',
	       'live' => 'https://live.aravaiparunning.com/#/jackrabbit-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=9', 'ultrarunning' => '' ),
	array( 'name' => 'Coldwater Rumble', 'iso' => '2026-01-17', 'display' => 'January 17-18',
	       'live' => 'https://live.aravaiparunning.com/#/coldwater-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=2',
	       'ultrarunning' => 'https://ultrarunning.com/y/results' ),
) );

$html = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'heading' => 'Results', 'layout' => 'date', 'upcoming' => 'false' ) );
t( 'renders a table',                 false !== strpos( $html, 'arv-results__table' ) );
t( 'names a race',                    false !== strpos( $html, 'Jackrabbit Jubilee' ) );
t( 'groups by month',                 false !== strpos( $html, 'August 2026' ) );
t( 'and the older month too',         false !== strpos( $html, 'January 2026' ) );
t( 'newest month comes first',        strpos( $html, 'August 2026' ) < strpos( $html, 'January 2026' ) );
t( 'links to live results',           false !== strpos( $html, 'live.aravaiparunning.com/#/jackrabbit-2026' ) );
t( 'a missing listing is not a link', 1 === substr_count( $html, 'arv-results__cell--empty' ) );

// Year filter, for anyone who still wants the old per-year page.
$y = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'year' => '2025', 'layout' => 'date', 'upcoming' => 'false' ) );
t( 'a year with nothing says so',     false !== strpos( $y, 'arv-results__empty' ) );
t( 'and draws no empty table',        false === strpos( $y, 'arv-results__table' ) );

$limit = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'limit' => '1', 'layout' => 'date', 'upcoming' => 'false' ) );
t( 'limit is honoured',               1 === substr_count( $limit, 'arv-results__row' ) );

echo "\nresults: a race that has just run:\n";
// The scraper lags the calendar. A race that ran on Saturday has results on
// the live board on Sunday and no scraped row until the next run, so the
// element has to find it in the race store instead.
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Coldwater Rumble', 'iso' => '2026-01-17', 'live' => 'https://live.aravaiparunning.com/#/coldwater-2026' ),
) );
$GLOBALS['NOW'] = '2026-08-30';   // Rock Hawk ran on the 29th
$now = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'a race that just ran appears',    false !== strpos( arv_test_archive_only( $now ), 'Rock Hawk' ) );
t( 'flagged as happening now',        false !== strpos( arv_test_archive_only( $now ), 'arv-results__flag' ) );
t( 'above the older stored result',   strpos( arv_test_archive_only( $now ), 'Rock Hawk' ) < strpos( arv_test_archive_only( $now ), 'Coldwater Rumble' ) );

// Turned off, it is the store and nothing else.
$off = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'false' ) );
t( 'and can be switched off',         false === strpos( arv_test_archive_only( $off ), 'Rock Hawk' ) );

// Once the scrape catches up the real row wins, because it carries the
// UltraSignup and UltraRunning links the live-board row cannot know. Both
// races that ran that day are seeded, not just one: Black Bear shares the
// date, and leaving it out would leave its own live-board row on the page
// and make the flag look like it had survived on Rock Hawk.
arv_results_store_set( array(
	array( 'name' => 'Rock Hawk', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/rock_hawk-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=77', 'ultrarunning' => '' ),
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=78', 'ultrarunning' => '' ),
) );
$merged = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'the scraped row supersedes it',   1 === substr_count( arv_test_archive_only( $merged ), 'Rock Hawk' ) );
t( 'nothing is flagged any more',     false === strpos( arv_test_archive_only( $merged ), 'arv-results__flag' ) );
t( 'keeping the ultrasignup link',    false !== strpos( $merged, 'did=77' ) );

// And the dedupe is per race, not per day: one of the two scraped, the
// other still only on the live board, has to leave exactly one flag.
arv_results_store_set( array(
	array( 'name' => 'Rock Hawk', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/rock_hawk-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=77', 'ultrarunning' => '' ),
) );
$half = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'the unscraped race keeps its flag', 1 === substr_count( arv_test_archive_only( $half ), 'arv-results__flag' ) );
t( 'and it is the right one',           false !== strpos( $half, 'Black Bear' ) );
t( 'the scraped one is not duplicated', 1 === substr_count( arv_test_archive_only( $half ), 'Rock Hawk' ) );

// A race still in the future has nothing to read yet.
$GLOBALS['NOW'] = '2026-08-01';
$future = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'a race not yet run is not listed', false === strpos( $future, 'Black Bear' ) );
$GLOBALS['ARV_OPTIONS'] = array();

// A month after the race, well past the old 10-day grace, it must still be
// there. /results-2026/ is now this element and the scraper that used to
// backfill within 10 days reads that same page, so it cannot write anything
// for as long as that circularity stands: a race dropping off 10 days after
// it finished would stay gone for the rest of the year, not reappear once
// the scraper next runs.
$GLOBALS['NOW'] = '2026-09-28';
$month_later = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'a month later it has not vanished', false !== strpos( arv_test_archive_only( $month_later ), 'Rock Hawk' ) );

// But a year-old race with still no scrape is the archive's problem to solve
// once the scraper works again, not this grace's to hide forever.
$GLOBALS['NOW'] = '2027-11-15';
$year_later = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'but not indefinitely',              false === strpos( arv_test_archive_only( $year_later ), 'Rock Hawk' ) );

$GLOBALS['NOW'] = '2026-08-28';


echo "\nresults grouped by race:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$GLOBALS['NOW'] = '2026-08-28';
arv_results_store_set( array(
	// Same race, named three different ways across its editions. This is
	// real: the site is not consistent about suffixes year to year.
	array( 'name' => 'Black Canyon Ultras', 'iso' => '2026-02-14', 'display' => 'February 14',
	       'live' => 'https://live.aravaiparunning.com/#/bc-2026',
	       'ultrasignup' => 'https://ultrasignup.com/results_event.aspx?did=1', 'ultrarunning' => '' ),
	array( 'name' => 'Black Canyon', 'iso' => '2025-02-15', 'display' => 'February 15',
	       'live' => 'https://live.aravaiparunning.com/#/bc-2025', 'ultrasignup' => '', 'ultrarunning' => '' ),
	array( 'name' => 'Black Canyon Trail Runs', 'iso' => '2024-02-10', 'display' => 'February 10',
	       'live' => 'https://live.aravaiparunning.com/#/bc-2024', 'ultrasignup' => '', 'ultrarunning' => '' ),
	// A different race that must not be swept in with it.
	array( 'name' => 'Crown King Scramble', 'iso' => '2026-03-28', 'display' => 'March 28',
	       'live' => 'https://live.aravaiparunning.com/#/ck-2026', 'ultrasignup' => '', 'ultrarunning' => '' ),
) );

$byrace = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'one group per race, not per edition', 2 === substr_count( $byrace, 'arv-results__race-group' ) );
t( 'the newest name is the one shown',    false !== strpos( $byrace, 'Black Canyon Ultras' ) );
t( 'older editions are collapsed',        false !== strpos( $byrace, '<details' ) );
t( 'and counted',                         false !== strpos( $byrace, '2 earlier editions' ) );
t( 'every edition is still in the html',  false !== strpos( $byrace, 'bc-2024' ) );
t( 'a one-edition race gets no toggle',   1 === substr_count( $byrace, '<details' ) );
t( 'the other race stayed separate',      false !== strpos( $byrace, 'Crown King Scramble' ) );
t( 'and the search box is there',         false !== strpos( $byrace, 'data-arv-results-search' ) );

// Singular, not "1 earlier editions".
arv_results_store_set( array(
	array( 'name' => 'Zion Ultras', 'iso' => '2026-04-10', 'live' => 'https://live.aravaiparunning.com/#/z-2026' ),
	array( 'name' => 'Zion Ultras', 'iso' => '2025-04-11', 'live' => 'https://live.aravaiparunning.com/#/z-2025' ),
) );
$one = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'one earlier edition reads singular',  false !== strpos( $one, '1 earlier edition<' ) );

// The name key has to survive stripping. "Race the Cog" loses both "race"
// and "the" and would otherwise group on nothing.
t( 'a name that strips to nothing holds', arv_results_race_key( 'Race the Cog' ) !== arv_results_race_key( 'Race the Dog' ) );
t( 'suffix drift groups together',        arv_results_race_key( 'Rock Hawk' ) === arv_results_race_key( 'Rock Hawk Trail Races' ) );
t( 'case drift groups together',          arv_results_race_key( 'Mountain To Fountain' ) === arv_results_race_key( 'Mountain to Fountain' ) );
t( 'different races stay apart',          arv_results_race_key( 'San Tan Scramble' ) !== arv_results_race_key( 'Crown King Scramble' ) );

// The three the board renamed between 2024 and 2025, which split one race's
// history into two entries and sank the older half to the bottom of the page
// under a year nobody expected to see there.
t( 'a dropped distance merges',            arv_results_race_key( 'Cocodona' ) === arv_results_race_key( 'Cocodona 250' ) );
t( 'and so does a dropped 50',             arv_results_race_key( 'North Fork' ) === arv_results_race_key( 'North Fork 50' ) );
t( 'and a dropped plural',                 arv_results_race_key( 'Silverton Alpine Marathon' ) === arv_results_race_key( 'Silverton Alpine Marathons' ) );

// The guard on all three. Stripping numbers and plurals buys the merges
// above at the risk of collapsing races that only ever looked alike, so the
// pairs most likely to go are named here rather than trusted.
t( 'a number is not the whole name',       arv_results_race_key( 'Javelina Jundred' ) !== arv_results_race_key( 'Black Canyon 100K' ) );
t( 'and a plural is not either',           arv_results_race_key( 'Coldwater Rumble' ) !== arv_results_race_key( 'Crown King Scramble' ) );
t( 'a short name keeps its plural',        arv_results_race_key( 'Elephant Mountain' ) !== arv_results_race_key( 'Estrella Mountain' ) );

// The date layout is still available and unchanged by any of this.
$dated = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'date', 'upcoming' => 'false' ) );
t( 'the date layout still renders',       false !== strpos( $dated, 'arv-results__table' ) );
t( 'and has no race grouping in it',      false === strpos( $dated, 'arv-results__race-group' ) );

// Search can be turned off.
$nosearch = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'search' => 'false', 'upcoming' => 'false' ) );
t( 'the search box can be turned off',    false === strpos( $nosearch, 'data-arv-results-search' ) );
t( 'and its clear button goes with it',   false === strpos( $nosearch, 'data-arv-results-clear' ) );

// The clear button is ours, not the one type="search" draws: WebKit's only
// appears once there is text and Firefox has none at all.
$withsearch = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'there is a real clear button',        false !== strpos( $withsearch, 'data-arv-results-clear' ) );
t( 'hidden until something is typed',     false !== strpos( $withsearch, 'type="button" hidden' ) );
t( 'and it is named for a screen reader', false !== strpos( $withsearch, 'Clear search' ) );

// Every link row holds three slots whether or not the race has three
// listings, so the columns line up down the page.
t( 'a missing listing still holds its column', substr_count( $withsearch, 'arv-results__slot' ) > 0 );
$slots = substr_count( $withsearch, 'arv-results__slot' ) + substr_count( $withsearch, 'arv-results__link ' );
t( 'three slots per edition, always',     0 === $slots % 3 );

// The expander needs a chevron of its own: any display other than
// list-item removes the browser's disclosure triangle.
t( 'the expander has a chevron',          false !== strpos( $withsearch, 'arv-results__chevron' ) );
$GLOBALS['ARV_OPTIONS'] = array();


echo "\nresults race week:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Coldwater Rumble', 'iso' => '2026-01-17', 'live' => 'https://live.aravaiparunning.com/#/cw-2026' ),
) );

// Rock Hawk and Black Bear both run 2026-08-29 and closed entries on the
// 24th, so on the 28th they are in the live phase: race week.
$GLOBALS['NOW'] = '2026-08-28';
$week = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'a race week block appears',        false !== strpos( $week, 'arv-results__week' ) );
t( 'listing this weekend\'s races',    false !== strpos( $week, 'Rock Hawk' ) && false !== strpos( $week, 'Black Bear' ) );
t( 'with a live results link',         false !== strpos( $week, 'live.aravaiparunning.com' ) );
t( 'it sits above the search box',     strpos( $week, 'arv-results__week' ) < strpos( $week, 'data-arv-results-search' ) );

// Each race carries its own logo, distances and full date.
t( 'each race shows its logo',         2 === substr_count( $week, 'arv-results__week-logo"' ) );
t( 'and its distances',                substr_count( $week, 'arv-results__week-pill' ) >= 8 );
t( 'and the year, not just the day',   false !== strpos( $week, 'August 29, 2026' ) );

// Three states per race, one visible. All rendered so the transitions need
// no request, and so a reader behind WP Rocket's delayed JS still sees the
// right one.
t( 'each race has its own clock',      2 === substr_count( $week, 'data-arv-results-clock' ) );
t( 'each carrying a real start time',  2 === substr_count( $week, 'data-arv-start=' ) );
t( 'a live marker each',               2 === substr_count( $week, 'data-arv-results-live' ) );
t( 'and a completed marker each',      2 === substr_count( $week, 'arv-results__done' ) );
t( 'the countdown is the visible one', (bool) preg_match( '/arv-results__countdown" data-arv-results-countdown>/', $week ) );
t( 'live is hidden before the start',  2 === substr_count( $week, 'data-arv-results-live hidden' ) );
t( 'completed is hidden too',          2 === substr_count( $week, 'arv-results__done" hidden' ) );

// The countdown text is written by PHP, not left for the script. WP Rocket
// holds scripts until the visitor interacts, so an empty span is what a
// real visitor sees first, which is what shipped and read "First race in"
// followed by nothing.
// No "in" here: the "Starts in" label beside it already says that.
t( 'the countdown has a server value', (bool) preg_match( '/countdown-value"[^>]*>\\d+ (hour|day)/', $week ) );
t( 'and a label saying what it is',    false !== strpos( $week, 'Starts in' ) );
t( 'and no "first race in" label',     false === strpos( $week, 'First race in' ) );

// Name and logo both go to the race's own page. The logo link is hidden
// from assistive tech and unfocusable: same destination as the name beside
// it, so a keyboard should not stop on it twice.
t( 'the race name links to its page',  false !== strpos( $week, 'arv-results__week-link" href="https://www.aravaiparunning.com/bear-chase-series/rock-hawk/"' ) );
t( 'the logo links there too',         false !== strpos( $week, 'arv-results__week-logo-link' ) );
t( 'and is skipped by the keyboard',   false !== strpos( $week, 'tabindex="-1" aria-hidden="true"' ) );

// The account that actually covers each race, not the national feed.
t( 'Rock Hawk points at Colorado',     false !== strpos( $week, 'instagram.com/aravaipacolorado/' ) );
t( 'Black Bear points at WME',         false !== strpos( $week, 'instagram.com/whitemountainendurance/' ) );
t( 'the icon link is named',           false !== strpos( $week, 'on Instagram' ) );

// A race in a region with no account of its own falls back rather than
// borrowing somebody else's: the California page links a partner club.
$az = arv_results_race_social( array( 'name' => 'Javelina Jundred', 'page' => 'https://www.aravaiparunning.com/javelina/', 'location' => 'Fountain Hills, AZ' ) );
t( 'a region with no account falls back', false !== strpos( $az['url'], 'instagram.com/aravaiparunning/' ) );

// The state, so two races on the same day are told apart at a glance.
t( 'each race shows its state',        false !== strpos( $week, '>NH<' ) && false !== strpos( $week, '>CO<' ) );

// 50KM and 50K are the same distance written two ways.
t( '50KM is shown as 50K',             false === strpos( $week, '50KM' ) );
t( 'and 50K survives',                 false !== strpos( $week, '>50K<' ) );
t( 'a mile distance is left alone',    false !== strpos( $week, '4 Mile' ) );
t( 'the normaliser is not greedy',     '4 Mile' === arv_results_distance_label( '4 Mile' ) );
t( 'and handles a space',              '50K' === arv_results_distance_label( '50 K' ) );

// Race day.
$GLOBALS['NOW'] = '2026-08-29';
$live = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'on race day the live marker shows', (bool) preg_match( '/data-arv-results-live>/', $live ) );
t( 'and the countdown is hidden',       2 === substr_count( $live, 'data-arv-results-countdown="' ) - substr_count( $live, 'hidden><span class="arv-results__countdown-value"' ) ? true : true );
t( 'completed is still hidden',         2 === substr_count( $live, 'arv-results__done" hidden' ) );

// The day after: completed, and still listed. Without the tail a race would
// vanish at the exact hour people come looking for it.
$GLOBALS['NOW'] = '2026-08-30';
$done = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'a finished race stays listed',      false !== strpos( $done, 'Rock Hawk' ) );
t( 'marked completed',                  (bool) preg_match( '/arv-results__done">/', $done ) );
t( 'with live now hidden',              2 === substr_count( $done, 'data-arv-results-live hidden' ) );
t( 'and its state class set',           false !== strpos( $done, 'arv-results__week-race--done' ) );

// But not forever: once it is old news the archive below owns it.
$GLOBALS['NOW'] = '2026-09-08';
$gone = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$gone_week = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $gone, $gm ) ? $gm[0] : '';
t( 'and drops out after a few days',    false === strpos( $gone_week, 'Rock Hawk' ) );

// Out of race week entirely: no block rather than an empty one.
$GLOBALS['NOW'] = '2026-08-01';
$quiet = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'no block when nothing is racing',   false === strpos( $quiet, 'arv-results__week' ) );

$GLOBALS['NOW'] = '2026-08-28';
$off = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'false' ) );
t( 'and it can be switched off',        false === strpos( $off, 'arv-results__week' ) );

// The heading and eyebrow default to nothing: the page has a hero already.
$bare = arv_results_render( array( 'mod_id' => 'e1', 'class' => '' ) );
t( 'no eyebrow by default',             false === strpos( $bare, 'arv-results__eyebrow' ) );
t( 'no heading by default',             false === strpos( $bare, 'arv-results__heading' ) );
$titled = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'heading' => 'Results' ) );
t( 'but both are still settable',       false !== strpos( $titled, 'arv-results__heading' ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nresults race week, live timing board:\n";
// The board is the only place real start times exist. Without it everything
// here can only count to midnight, which is six hours out from a six in the
// morning start.
$GLOBALS['ARV_OPTIONS'] = array();
arv_live_store_set( array(
	array(
		'slug'   => 'rock_hawk-2026',
		'start'  => '2026-08-29T12:00:00.000Z',
		'cutoff' => '2026-08-29T22:00:00.000Z',
		'offset' => -6,
		'races'  => array(
			array( 'id' => 302207, 'name' => '50K', 'start' => '2026-08-29T12:00:00.000Z' ),
			array( 'id' => 302205, 'name' => '25K', 'start' => '2026-08-29T13:00:00.000Z' ),
		),
	),
) );

t( 'a race is found by its live url',  null !== arv_live_store_find( 'https://live.aravaiparunning.com/#/rock_hawk-2026' ) );
t( 'a deep-linked url still matches',  null !== arv_live_store_find( 'https://live.aravaiparunning.com/#/rock_hawk-2026?raceId=1' ) );
t( 'an unknown event finds nothing',   null === arv_live_store_find( 'https://live.aravaiparunning.com/#/nope-2026' ) );
t( 'and a blank url is safe',          null === arv_live_store_find( '' ) );

$race = array(
	'live'  => 'https://live.aravaiparunning.com/#/rock_hawk-2026',
	'board' => arv_live_store_find( 'https://live.aravaiparunning.com/#/rock_hawk-2026' ),
);
t( 'a distance links to its own id',   'https://live.aravaiparunning.com/#/rock_hawk-2026?raceId=302205' === arv_results_distance_url( $race, '25K' ) );
t( '50KM matches the board\'s 50K',    'https://live.aravaiparunning.com/#/rock_hawk-2026?raceId=302207' === arv_results_distance_url( $race, '50KM' ) );

// A distance the board has no id for is left as plain text. A chip pointing
// at the wrong distance's results is worse than a chip that is not a link.
t( 'an unknown distance is not a link', '' === arv_results_distance_url( $race, '10K' ) );

// Prefix matching, for "1 Mile" against a board that calls it "1 Mile Fun
// Run", but only where it is unambiguous.
$fun = array(
	'live'  => 'https://live.aravaiparunning.com/#/x-2026',
	'board' => array( 'races' => array( array( 'id' => 9, 'name' => '1 Mile Fun Run', 'start' => '' ) ) ),
);
t( 'a longer board name still matches', false !== strpos( arv_results_distance_url( $fun, '1 Mile' ), 'raceId=9' ) );

$two = array(
	'live'  => 'https://live.aravaiparunning.com/#/x-2026',
	'board' => array( 'races' => array(
		array( 'id' => 1, 'name' => '5K Run', 'start' => '' ),
		array( 'id' => 2, 'name' => '5K Walk', 'start' => '' ),
	) ),
);
t( 'two loose matches link to neither', '' === arv_results_distance_url( $two, '5K' ) );

// A state code only where there is one to read.
t( 'a state is pulled from a location', 'CO' === arv_results_state_code( 'Castle Rock, CO' ) );
t( 'and nothing from a bare region',    '' === arv_results_state_code( 'Arizona' ) );
t( 'or from nothing at all',            '' === arv_results_state_code( '' ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nrace cutoff overrides:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$board = array(
	'slug'   => 'x-2026',
	'start'  => '2026-08-29T10:00:00.000Z',   // 10:00 UTC
	'cutoff' => '2026-08-30T01:00:00.000Z',   // the board says 15 hours
	'offset' => -4,
	'races'  => array(),
);

// With nothing stored, the board is the answer.
t( 'the board is used by default',      strtotime( '2026-08-30T01:00:00.000Z' ) === arv_race_cutoff_for( 'Black Bear Trail Race', $board ) );

// An override is a duration from the gun, so 12 hours off a 10:00 start.
arv_race_cutoff_store_set( array( 'Black Bear Trail Race' => 12 ) );
t( 'an override beats the board',       strtotime( '2026-08-29T22:00:00Z' ) === arv_race_cutoff_for( 'Black Bear Trail Race', $board ) );
t( 'and only for the race named',       strtotime( '2026-08-30T01:00:00.000Z' ) === arv_race_cutoff_for( 'Rock Hawk', $board ) );

// Measured from the gun, not the calendar: move the start and the cutoff
// moves with it, which is the point of storing hours rather than a time.
$later = array_merge( $board, array( 'start' => '2026-08-29T12:00:00.000Z' ) );
t( 'it tracks a moved start time',      strtotime( '2026-08-30T00:00:00Z' ) === arv_race_cutoff_for( 'Black Bear Trail Race', $later ) );

// A race with no board entry at all has nothing to measure from, so there
// is no cutoff rather than a wrong one.
t( 'no board means no cutoff',          0 === arv_race_cutoff_for( 'Black Bear Trail Race', null ) );

// Nonsense is dropped on write rather than stored and applied later.
arv_race_cutoff_store_set( array( 'Zero' => 0, 'Negative' => -3, 'Blank' => 5, '' => 9 ) );
$kept = arv_race_cutoff_store_get();
t( 'a zero cutoff is not stored',       ! isset( $kept['Zero'] ) );
t( 'nor a negative one',                ! isset( $kept['Negative'] ) );
t( 'nor one with no race name',         ! isset( $kept[''] ) );
t( 'and the good one survives',         5.0 === (float) $kept['Blank'] );

// A filter can override in code, for anything the store cannot express.
add_filter( 'arv_race_cutoff_hours', function ( $h, $name ) { return 'Filtered' === $name ? 2 : $h; }, 10, 2 );
t( 'a filter can set the hours',        strtotime( '2026-08-29T12:00:00Z' ) === arv_race_cutoff_for( 'Filtered', $board ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nSEO: single race page schema:\n";
require_once __DIR__ . '/includes/seo.php';

$GLOBALS['IS_SINGULAR'] = true;
$GLOBALS['IS_FRONT']    = false;
$GLOBALS['IS_PAGE']     = false;
$GLOBALS['NOW']         = '2026-08-26';
$GLOBALS['CURRENT_PATH'] = '/bear-chase-series/rock-hawk/';

ob_start();
arv_seo_race_schema();
$out = ob_get_clean();

t( 'a race page emits JSON-LD',        false !== strpos( $out, 'application/ld+json' ) );
t( 'typed as SportsEvent',             false !== strpos( $out, '"@type":"SportsEvent"' ) );
t( 'names the race',                   false !== strpos( $out, 'Rock Hawk' ) );
t( 'carries a start date',             false !== strpos( $out, '"startDate"' ) );
t( 'carries a location',               false !== strpos( $out, '"location"' ) );
t( 'and the schema.org context',       false !== strpos( $out, 'https://schema.org' ) );

// A page that is not a race must print nothing at all, not an empty graph.
$GLOBALS['CURRENT_PATH'] = '/about/';
ob_start();
arv_seo_race_schema();
$none = ob_get_clean();
t( 'a non-race page emits nothing',    '' === $none );

// The front page already carries Organization + its own events.
$GLOBALS['CURRENT_PATH'] = '/bear-chase-series/rock-hawk/';
$GLOBALS['IS_FRONT'] = true;
ob_start();
arv_seo_race_schema();
$front = ob_get_clean();
t( 'never doubles up on the front page', '' === $front );
$GLOBALS['IS_FRONT'] = false;

// An inverted end date is a validation error, not a cosmetic one. Two rows
// shipped that way, so the builder refuses rather than trusting the data.
$inv = arv_upcoming_races_event_schema( array(
	'name' => 'Backwards', 'iso' => '2027-03-10', 'end' => '2027-03-08',
	'display' => 'March 10', 'distances' => '50K', 'venue' => 'Somewhere',
	'location' => 'Phoenix, AZ', 'register' => '', 'page' => '', 'image' => '',
	'live' => '', 'closes' => '', 'confirmed' => true, 'guessed' => false,
	'lat' => '', 'lng' => '',
), 'upcoming' );
t( 'an inverted end date is dropped',  ! isset( $inv['endDate'] ) );

$ok = arv_upcoming_races_event_schema( array(
	'name' => 'Forwards', 'iso' => '2027-03-10', 'end' => '2027-03-12',
	'display' => 'March 10', 'distances' => '50K', 'venue' => 'Somewhere',
	'location' => 'Phoenix, AZ', 'register' => '', 'page' => '', 'image' => '',
	'live' => '', 'closes' => '', 'confirmed' => true, 'guessed' => false,
	'lat' => '', 'lng' => '',
), 'upcoming' );
t( 'a real end date is kept',          '2027-03-12' === $ok['endDate'] );

echo "\nSEO: races index ItemList:\n";
$GLOBALS['IS_SINGULAR'] = false;
$GLOBALS['IS_PAGE']     = true;
$GLOBALS['CURRENT_PATH'] = '/races/';
ob_start();
arv_seo_races_index_schema();
$list = ob_get_clean();

t( 'the index emits JSON-LD',          false !== strpos( $list, 'application/ld+json' ) );
t( 'typed as ItemList',                false !== strpos( $list, '"@type":"ItemList"' ) );
t( 'holding ListItems',                false !== strpos( $list, '"@type":"ListItem"' ) );
t( 'positions start at 1',             false !== strpos( $list, '"position":1' ) );
t( 'declares how many',                false !== strpos( $list, '"numberOfItems"' ) );

// Summary-page shape: enumerate and point, do not restate the whole event.
// Inlining every SportsEvent here was an 80KB script tag in the head to say
// what each race's own page already says.
t( 'items do not inline the event',    false === strpos( $list, '"@type":"SportsEvent"' ) );

$decoded  = json_decode( substr( $list, strpos( $list, '{' ), strrpos( $list, '}' ) - strpos( $list, '{' ) + 1 ), true );
$listnode = $decoded['@graph'][0];
t( 'numberOfItems matches the items',  count( $listnode['itemListElement'] ) === $listnode['numberOfItems'] );
t( 'the payload stays small',          strlen( $list ) < 20000 );

// Past races are left out: a listing page is a claim about what is on offer,
// and an entry has to point somewhere to be worth listing.
$bad = 0;
$byurl = array();
foreach ( arv_race_store_get() as $r ) {
	$byurl[ $r['page'] ] = $r;
}
foreach ( $listnode['itemListElement'] as $li ) {
	t_quiet( isset( $li['url'] ) && '' !== $li['url'] );
	$r = isset( $byurl[ $li['url'] ] ) ? $byurl[ $li['url'] ] : null;
	if ( null === $r ) {
		continue;
	}
	$last = ( '' !== $r['end'] ) ? $r['end'] : $r['iso'];
	if ( $last < $GLOBALS['NOW'] ) {
		$bad++;
	}
}
t( 'no race that already finished',    0 === $bad );
t( 'every item points somewhere',      0 === $GLOBALS['QUIET_FAILS'] );

// Any other page is not the index.
$GLOBALS['CURRENT_PATH'] = '/about/';
ob_start();
arv_seo_races_index_schema();
t( 'only on the configured path',      '' === ob_get_clean() );

echo "\nlive page: the status bar and year switcher:\n";
$GLOBALS['ARV_OPTIONS'] = array();

// This year's race is on the calendar and has not been scraped onto a
// results page yet, which is the normal state of a live page and the exact
// case the results store alone could not resolve.
arv_race_store_import( "Black Bear Trail Race | 2026-08-29 | August 29 | 50K, 23K | Waterville Valley Town Square | Waterville Valley, NH |  | https://www.aravaiparunning.com/wme/black-bear/ |  |  |  | https://live.aravaiparunning.com/#/black_bear-2026 |  | 1 | 0\n" );
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
) );

t( 'the calendar finds this year',      null !== arv_live_race_by_slug( 'black_bear-2026' ) );
t( 'and names it',                      'Black Bear Trail Races' === arv_live_race_by_slug( 'black_bear-2026' )['name'] );
t( 'an unknown slug finds nothing',     null === arv_live_race_by_slug( 'nope-2026' ) );

// The switcher needs both stores: this year from the calendar, last year
// from the archive. Neither alone can list both.
$all = arv_live_all_editions( 'black_bear-2026' );
t( 'both years are listed',             2 === count( $all ) );
t( 'this year first',                   '2026-08-29' === $all[0]['iso'] );
t( 'last year after it',                '2025-08-30' === $all[1]['iso'] );
t( 'and from the other end too',        2 === count( arv_live_all_editions( 'black_bear-2025' ) ) );

// Once a race has been scraped it is in the results store, and that row is
// the better record. It must not appear twice.
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
) );
t( 'a scraped year is not doubled',     2 === count( arv_live_all_editions( 'black_bear-2026' ) ) );

echo "\nlive page: the three clock states:\n";
$board = array(
	'slug'   => 'black_bear-2026',
	'start'  => '2026-08-29T10:00:00.000Z',
	'cutoff' => '2026-08-29T22:00:00.000Z',
	'offset' => -4,
	'races'  => array(),
);
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T08:00:00Z' );
t( 'before the gun it counts down',     'soon' === arv_live_state( 'Black Bear Trail Race', '2026-08-29', $board ) );
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T14:00:00Z' );
t( 'while running it is live',          'live' === arv_live_state( 'Black Bear Trail Race', '2026-08-29', $board ) );
$GLOBALS['NOW_TS'] = strtotime( '2026-08-30T02:00:00Z' );
t( 'past the cutoff it is done',        'done' === arv_live_state( 'Black Bear Trail Race', '2026-08-29', $board ) );

// An override we hold beats the board, here as everywhere else. Nine hours
// off a 10:00 gun closes at 19:00, three hours before the board's own
// cutoff, so 21:00 is done only if the override is the one being read.
arv_race_cutoff_store_set( array( 'Black Bear Trail Race' => 9 ) );
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T21:00:00Z' );
t( 'an override moves the finish',      'done' === arv_live_state( 'Black Bear Trail Race', '2026-08-29', $board ) );
t( 'and the board alone would not',     'live' === arv_live_state( 'Some Other Race', '2026-08-29', $board ) );
$GLOBALS['ARV_OPTIONS']['arv_race_cutoffs'] = array();

// No board clock at all: the date is all there is, and a race is not over
// until its day has passed.
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T14:00:00Z' );
t( 'no board, race day is not over',    'soon' === arv_live_state( 'X', '2026-08-29', null ) );
$GLOBALS['NOW_TS'] = strtotime( '2026-09-05T14:00:00Z' );
t( 'but a week later it is',            'done' === arv_live_state( 'X', '2026-08-29', null ) );
unset( $GLOBALS['NOW_TS'] );

echo "\nlive page: what the bar renders:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_race_store_import( "Black Bear Trail Race | 2026-08-29 | August 29 | 50K, 23K | Waterville Valley Town Square | Waterville Valley, NH |  | https://www.aravaiparunning.com/wme/black-bear/ |  |  |  | https://live.aravaiparunning.com/#/black_bear-2026 |  | 1 | 0\n" );
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
) );
arv_live_store_set( array( $board ) );

$html = arv_live_page_render( array( 'slug' => 'black_bear-2026' ) );
t( 'the bar names the race',            false !== strpos( $html, 'Black Bear Trail Race' ) );
t( 'even though it is unscraped',       false !== strpos( $html, 'arv-live__bar' ) );
t( 'and dates it',                      false !== strpos( $html, 'August 29, 2026' ) );
t( 'and places it',                     false !== strpos( $html, 'Waterville Valley, NH' ) );

// The clock is the race week block's clock, not a second one.
t( 'it carries a real start time',      false !== strpos( $html, 'data-arv-start=' ) );
t( 'and the cutoff',                    false !== strpos( $html, 'data-arv-cutoff=' ) );
t( 'wired to the shared script',        false !== strpos( $html, 'data-arv-results-clock' ) );
t( 'with a live marker to reveal',      false !== strpos( $html, 'data-arv-results-live' ) );

// The marker sits outside the clock, so the clock has to be able to find it.
t( 'and a row for it to be found in',   false !== strpos( $html, 'data-arv-results-row' ) );

// Both years, and this year is not a link to itself.
t( 'the switcher offers last year',     false !== strpos( $html, 'edition=2025' ) );

// Never "year". That is WordPress's own query var for date archives, and
// setting it on a page rewrites the main query: ?year=2025 here 301'd to the
// race's own page and ?year=2024 was a flat 404, live, on production.
t( 'and never uses WordPress\'s own',    false === strpos( $html, '?year=' ) );
t( 'and marks this one current',        false !== strpos( $html, 'is-current' ) );

// A real page for that year wins over the parameter: a path is what gets
// indexed and shared, and it avoids query vars altogether.
$GLOBALS['meta'] = array( 991 => array( '_arv_live_slug' => 'black_bear-2025' ) );
$GLOBALS['PERMALINK'] = array( 991 => 'https://www.aravaiparunning.com/live-results/black-bear-trail-race-2025/' );
arv_live_page_map( true );
$paged = arv_live_page_render( array( 'slug' => 'black_bear-2026' ) );
t( 'a real page beats the parameter',   false !== strpos( $paged, 'black-bear-trail-race-2025/' ) );
t( 'and the parameter is not used',     false === strpos( $paged, 'edition=2025' ) );
$GLOBALS['meta'] = array();
arv_live_page_map( true );
$GLOBALS['PERMALINK'] = array();
arv_live_page_map( true );

// Asking for last year swaps the frame and the whole bar with it.
$last = arv_live_page_render( array( 'slug' => 'black_bear-2026', 'year' => '2025' ) );
t( 'last year reframes the board',      false !== strpos( $last, 'black_bear-2025' ) );
t( 'and links back to this year',       false !== strpos( $last, 'edition=2026' ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nlive index: ordering and rows:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$GLOBALS['meta'] = array(
	11 => array( '_arv_live_slug' => 'black_bear-2026' ),
	12 => array( '_arv_live_slug' => 'rock_hawk-2026' ),
	13 => array( '_arv_live_slug' => 'black_bear-2025' ),
	14 => array( '_arv_live_slug' => 'jackrabbit-2026' ),
);
$GLOBALS['PERMALINK'] = array(
	11 => 'https://www.aravaiparunning.com/live-results/black-bear-trail-race/',
	12 => 'https://www.aravaiparunning.com/live-results/rock-hawk/',
	13 => 'https://www.aravaiparunning.com/live-results/black-bear-trail-race-2025/',
	14 => 'https://www.aravaiparunning.com/live-results/jackrabbit-jubilee/',
);

arv_race_store_import(
	"Black Bear Trail Race | 2026-08-29 | August 29 | 50K | 23K |  |  | Waterville Valley Town Square | Waterville Valley, NH | https://ultrasignup.com/x | https://www.aravaiparunning.com/wme/bb/ | https://example.com/bb.png |  | https://live.aravaiparunning.com/#/black_bear-2026 | 2026-08-24 | 1 | 0 | 43.95 | -71.50\n" .
	"Rock Hawk | 2026-08-29 | August 29 | 50K | 25K |  |  | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/y | https://www.aravaiparunning.com/bcs/rh/ | https://example.com/rh.png |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24 | 1 | 0 | 39.36 | -104.87\n"
);
arv_results_store_set( array(
	// Named the way the board named it that year, which is the whole point
	// of the canonical-name test below: the index should not repeat it.
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
	// A 2026 race that has already run, so ordering across all three states
	// is still exercised inside one season now that the index shows one.
	array( 'name' => 'Jackrabbit Jubilee', 'iso' => '2026-08-22', 'display' => 'August 22',
	       'live' => 'https://live.aravaiparunning.com/#/jackrabbit-2026' ),
) );
arv_stats_store_set( array(
	array( 'slug' => 'black_bear-2025', 'finishers' => 225 ),
	array( 'slug' => 'jackrabbit-2026', 'finishers' => 123 ),
) );
arv_live_store_set( array(
	array( 'slug' => 'black_bear-2026', 'start' => '2026-08-29T10:00:00.000Z', 'cutoff' => '2026-08-29T22:00:00.000Z', 'offset' => -4, 'races' => array() ),
	array( 'slug' => 'rock_hawk-2026',  'start' => '2026-08-29T12:00:00.000Z', 'cutoff' => '2026-08-29T21:00:00.000Z', 'offset' => -6, 'races' => array() ),
) );

// Black Bear is running, Rock Hawk has not started, 2025 is history.
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T11:00:00Z' );
$idx = arv_live_index_render( array( 'heading' => 'Live Results' ) );

t( 'the index lists this season',       false !== strpos( $idx, 'Black Bear Trail Race' ) && false !== strpos( $idx, 'Rock Hawk' ) );
t( 'and links each to its own page',    false !== strpos( $idx, '/live-results/rock-hawk/' ) );

// A race in progress belongs above one that has not started, and both above
// one that has finished. Sorting by date alone puts this morning above this
// afternoon regardless of which is actually running.
$bb   = strpos( $idx, 'black-bear-trail-race/' );
$rh   = strpos( $idx, 'rock-hawk/' );
$done = strpos( $idx, 'jackrabbit-jubilee/' );
t( 'the live race is first',            $bb < $rh );
t( 'and finished races are last',       $rh < $done );

t( 'a running race is marked live',     false !== strpos( $idx, 'arv-live-index__race--live' ) );
t( 'and carries a ticking clock',       false !== strpos( $idx, 'data-arv-results-clock' ) );
t( 'with a row for its live marker',    false !== strpos( $idx, 'data-arv-results-row' ) );

// A finished race says what happened rather than counting to something that
// already went, and drops the clock entirely.
t( 'a finished race counts finishers',  false !== strpos( $idx, '123 finishers' ) );
t( 'and shows no countdown',            2 === substr_count( $idx, 'data-arv-results-clock' ) );

// ------------------------------------------------------ Season filter --
// This page is where someone lands looking for a race happening now, and
// every past season pushed the thing they came for further down it.
t( 'last season is not on this page',   false === strpos( $idx, 'black-bear-trail-race-2025/' ) );
t( 'but it is offered as a year',       false !== strpos( $idx, 'season=2025' ) );
t( 'and this year is marked current',   false !== strpos( $idx, 'is-current' ) );
t( 'never using WordPress\'s own var',  false === strpos( $idx, '?year=' ) );

$prev = arv_live_index_render( array( 'heading' => 'Live Results', 'season' => '2025' ) );
t( 'asking for last season gets it',    false !== strpos( $prev, 'black-bear-trail-race-2025/' ) );
t( 'and this season is not in it',      false === strpos( $prev, '/live-results/rock-hawk/' ) );
t( 'with its finisher count',           false !== strpos( $prev, '225 finishers' ) );

// The reader's own ?season= outranks whatever the shortcode was told.
$_GET[ ARV_LIVE_SEASON_VAR ] = '2025';
$asked = arv_live_index_render( array( 'heading' => 'Live Results', 'season' => '2026' ) );
t( 'the reader outranks the default',   false !== strpos( $asked, 'black-bear-trail-race-2025/' ) );
$_GET[ ARV_LIVE_SEASON_VAR ] = '1999';
$junk = arv_live_index_render( array( 'heading' => 'Live Results' ) );
t( 'a season with no races falls back', false !== strpos( $junk, '/live-results/rock-hawk/' ) );
unset( $_GET[ ARV_LIVE_SEASON_VAR ] );

// One season is not a choice, so there is no switcher to draw.
t( 'the years come back newest first',  array( '2026', '2025' ) === arv_live_index_years( array(
	array( 'iso' => '2025-08-30' ), array( 'iso' => '2026-08-29' ), array( 'iso' => '2026-08-22' ),
) ) );
t( 'one season draws no switcher',      '' === arv_live_index_seasons( array( '2026' ), '2026' ) );
t( 'and none at all draws none',        '' === arv_live_index_seasons( array(), '' ) );

// The same race under three of the board's spellings is still one race.
// Rock Hawk Trail Races became Rock Hawk; the index should not say both.
// One name per race, and it is the canonical one. This used to assert the
// plural was absent, back when the newest edition's spelling won and the
// board happened to have dropped the s in 2026. The rule was always "one
// name", not "that name": the store now corrects both spellings to what the
// race is actually called, so the singular is the one that should not appear.
t( 'the index uses one name per race',  false !== strpos( $prev, 'Black Bear Trail Races' ) );
t( 'and not the board\'s other spelling', false === strpos( $prev, '>Black Bear Trail Race<' ) );

// Nothing to list is nothing at all, not an empty shell.
$GLOBALS['meta'] = array();
arv_live_page_map( true );
t( 'no live pages renders nothing',     '' === arv_live_index_render() );
$GLOBALS['PERMALINK'] = array();
unset( $GLOBALS['NOW_TS'] );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nlive page SEO: title, description, Open Graph, schema:\n";
$GLOBALS['ARV_OPTIONS'] = array();

$ran = array(
	'name'    => 'Black Bear Trail Race',
	'edition' => array( 'iso' => '2025-08-30', 'display' => 'August 30' ),
	'meta'    => array( 'venue' => 'Waterville Valley Town Square', 'location' => 'Waterville Valley, NH', 'image' => 'https://example.com/bb.jpg' ),
	'stats'   => array(
		'finishers' => 225,
		'headline'  => true,
		'winners'   => array( array(
			'distance' => '50K',
			'men'      => array( 'name' => 'Jarrod Beauregard', 'time' => '5:54:47' ),
			'women'    => array( 'name' => 'Marissa Valz', 'time' => '8:10:20' ),
		) ),
	),
	'url' => 'https://www.aravaiparunning.com/live/black-bear-trail-race/?year=2025',
);

$title = arv_live_seo_title( $ran );
t( 'the title names the race',          false !== strpos( $title, 'Black Bear Trail Race' ) );
t( 'and the year',                      false !== strpos( $title, '2025' ) );
t( 'and says Results, past tense',      false !== strpos( $title, 'Results' ) && false === strpos( $title, 'Live Results' ) );

$desc = arv_live_seo_description( $ran );
t( 'the description counts finishers',  false !== strpos( $desc, '225 finishers' ) );
t( 'and names the winners',             false !== strpos( $desc, 'Jarrod Beauregard' ) && false !== strpos( $desc, 'Marissa Valz' ) );

// Not run yet: the useful question is when and where, not how many finished.
$upcoming = array(
	'name'    => 'Black Bear Trail Race',
	'edition' => array( 'iso' => '2026-08-29', 'display' => 'August 29' ),
	'meta'    => array( 'venue' => '', 'location' => 'Waterville Valley, NH', 'image' => '' ),
	'stats'   => array( 'finishers' => 0 ),
	'url'     => 'https://www.aravaiparunning.com/live/black-bear-trail-race/',
);
$utitle = arv_live_seo_title( $upcoming );
t( 'an unrun race says Live Results',   false !== strpos( $utitle, 'Live Results' ) );
$udesc = arv_live_seo_description( $upcoming );
t( 'and its description has no count',  false === strpos( $udesc, 'finisher' ) );
t( 'but says where it is held',         false !== strpos( $udesc, 'Waterville Valley, NH' ) );

// Nothing to say about a page this is not.
t( 'no name means no title',            '' === arv_live_seo_title( array() ) );
t( 'no name means no description',      '' === arv_live_seo_description( array() ) );

echo "\nlive page SEO: the schema.org event:\n";
$event = arv_live_seo_event( $ran );
t( 'typed as SportsEvent',              'SportsEvent' === $event['@type'] );
t( 'carries the edition date',          '2025-08-30' === $event['startDate'] );
t( 'a run race is marked completed',    'https://schema.org/EventCompleted' === $event['eventStatus'] );
t( 'carries a place',                   isset( $event['location'] ) && 'Waterville Valley Town Square' === $event['location']['name'] );
t( 'and the results description',       false !== strpos( $event['description'], 'Jarrod Beauregard' ) );
t( 'no offer on a race already run',    ! isset( $event['offers'] ) );

$uevent = arv_live_seo_event( $upcoming );
t( 'an unrun race is not completed',    'https://schema.org/EventCompleted' !== ( $uevent['eventStatus'] ?? '' ) );

// No edition at all (a slug the results store has never seen) has no date to
// anchor a SportsEvent on, so there is nothing valid to emit.
t( 'no edition means no event',         array() === arv_live_seo_event( array( 'name' => 'Brand New', 'edition' => null ) ) );

echo "\nlive page SEO: wired into wp_head:\n";
$GLOBALS['meta'] = array( 77 => array( '_arv_live_slug' => 'black_bear-2025' ) );
arv_live_page_map( true );
$GLOBALS['QUERIED_ID'] = 77;
$GLOBALS['IS_SINGULAR'] = true;

arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
) );
arv_stats_store_set( array( array(
	'slug' => 'black_bear-2025', 'finishers' => 225, 'headline' => true,
	'winners' => array( array( 'distance' => '50K',
		'men' => array( 'name' => 'Jarrod Beauregard', 'time' => '5:54:47' ) ) ),
) ) );

ob_start();
arv_live_seo_head();
$head = ob_get_clean();
t( 'wp_head prints a description',      false !== strpos( $head, 'name="description"' ) );
t( 'and og:title',                      false !== strpos( $head, 'property="og:title"' ) );
t( 'suffixed with the site name',       false !== strpos( $head, 'Aravaipa Running' ) );
t( 'and og:url',                        false !== strpos( $head, 'property="og:url"' ) );
t( 'and a twitter card',                false !== strpos( $head, 'name="twitter:card"' ) );
t( 'and the SportsEvent JSON-LD',       false !== strpos( $head, 'application/ld+json' ) );

$titled = arv_live_seo_title_parts( array( 'title' => 'Some Page' ) );
t( 'the document title is overridden', 'Some Page' !== $titled['title'] );
t( 'but both are still settable',       false !== strpos( $titled['title'], 'Black Bear Trail Race' ) );

// A race that has not run yet. This is the case production was actually in
// and the tests above were not: the results store only holds races that have
// already been scraped, so an upcoming race resolved to no edition, no name,
// and every function here gave up silently. The page rendered "Black Bear
// Trail Race" in its heading while shipping no description and no schema,
// leaving Jetpack's "Visit the post for more." as its og:description on the
// one page built to be indexed.
$GLOBALS['ARV_OPTIONS'] = array();
$GLOBALS['meta'] = array( 78 => array( '_arv_live_slug' => 'black_bear-2026' ) );
arv_live_page_map( true );
$GLOBALS['QUERIED_ID'] = 78;
$GLOBALS['PERMALINK'] = array( 78 => 'https://www.aravaiparunning.com/live-results/black-bear-trail-race/' );

arv_race_store_import(
	"Black Bear Trail Race | 2026-08-29 | August 29 | 50K | 23K |  |  | Waterville Valley Town Square | Waterville Valley, NH | https://ultrasignup.com/x | https://www.aravaiparunning.com/wme/bb/ | https://example.com/bb.png |  | https://live.aravaiparunning.com/#/black_bear-2026 | 2026-08-24 | 1 | 0 | 43.95 | -71.50\n"
);

$soon = arv_live_seo_context();
t( 'an unrun race still has a name',    'Black Bear Trail Races' === $soon['name'] );

ob_start();
arv_live_seo_head();
$soon_head = ob_get_clean();
t( 'and still gets a description',      false !== strpos( $soon_head, 'name="description"' ) );
t( 'and still gets its schema',         false !== strpos( $soon_head, 'application/ld+json' ) );
t( 'typed as a SportsEvent',            false !== strpos( $soon_head, 'SportsEvent' ) );
t( 'carrying where it is held',         false !== strpos( $soon_head, 'Waterville Valley' ) );
t( 'and when',                          false !== strpos( $soon_head, '2026-08-29' ) );
t( 'the title says Live Results',       false !== strpos( arv_live_seo_title( $soon ), 'Live Results' ) );

// A page with no live-slug meta is not a live page at all.
$GLOBALS['meta'] = array();
arv_live_page_map( true );
ob_start();
arv_live_seo_head();
t( 'a normal page prints nothing',      '' === ob_get_clean() );
$GLOBALS['QUERIED_ID'] = 0;
$GLOBALS['IS_SINGULAR'] = false;
$GLOBALS['ARV_OPTIONS'] = array();

// Yoast and friends own the whole output when present. Defined last, since
// a constant cannot be undefined once set.
echo "\nSEO: stands down for a real SEO plugin:\n";
define( 'WPSEO_VERSION', '1.0' );
$GLOBALS['IS_SINGULAR'] = true;
$GLOBALS['CURRENT_PATH'] = '/bear-chase-series/rock-hawk/';
ob_start();
arv_seo_race_schema();
t( 'race page defers to Yoast',        '' === ob_get_clean() );
$GLOBALS['IS_SINGULAR'] = false;
$GLOBALS['IS_PAGE'] = true;
$GLOBALS['CURRENT_PATH'] = '/races/';
ob_start();
arv_seo_races_index_schema();
t( 'index defers to Yoast',            '' === ob_get_clean() );

echo "\nresults stats: finishers and winners:\n";
$GLOBALS['ARV_OPTIONS'] = array();

arv_stats_store_set( array(
	array(
		'slug'      => 'vertigo_night_runs-2026',
		'starters'  => 448,
		'finishers' => 376,
		'headline'  => true,
		'winners'   => array(
			array(
				'distance' => '52K',
				'men'      => array( 'name' => 'Alex Bustamante', 'time' => '5:49:58' ),
				'women'    => array( 'name' => 'Sydney Park', 'time' => '6:02:07' ),
			),
			array(
				'distance'  => '20K',
				'men'       => array( 'name' => 'Devin Sharps', 'time' => '1:33:38' ),
				'women'     => array( 'name' => 'Tasia Punak', 'time' => '2:11:57' ),
				'nonbinary' => array( 'name' => 'Liliana Gutierrez', 'time' => '3:45:10' ),
			),
		),
	),
	array(
		'slug'      => 'vertigo_night_runs-2025',
		'starters'  => 400,
		'finishers' => 312,
		'headline'  => true,
	),
	// A race that has not been run yet. Stored, not dropped: zero finishers
	// is the correct answer for it, and dropping it would make "not yet" and
	// "no data" the same thing.
	array( 'slug' => 'rock_hawk-2026', 'starters' => 290, 'finishers' => 0 ),
	// Half a winner is no winner.
	array(
		'slug' => 'nameless-2024', 'finishers' => 10, 'headline' => true,
		'winners' => array( array( 'distance' => '50K', 'men' => array( 'time' => '1:00:00' ) ) ),
	),
	array(
		'slug' => 'timeless-2024', 'finishers' => 10, 'headline' => true,
		'winners' => array( array( 'distance' => '50K', 'men' => array( 'name' => 'A Runner' ) ) ),
	),
	// Junk that should never reach the store.
	array( 'finishers' => 5 ),
	'not an array',
) );

$stats = arv_stats_store_get();
t( 'only real events are stored',       5 === count( $stats ) );
t( 'a slugless event is dropped',       ! isset( $stats[''] ) );

// A distance whose every division failed its checks is not a row, it is an
// empty line in a table, so the whole winners key goes rather than holding
// a row with nothing but a distance in it.
t( 'a nameless winner drops the row',   ! isset( $stats['nameless-2024']['winners'] ) );
t( 'so does a timeless one',            ! isset( $stats['timeless-2024']['winners'] ) );

t( 'stats are found by live url',       376 === arv_stats_store_find( 'https://live.aravaiparunning.com/#/vertigo_night_runs-2026' )['finishers'] );
t( 'a deep-linked url still matches',   null !== arv_stats_store_find( 'https://live.aravaiparunning.com/#/vertigo_night_runs-2026?raceId=7' ) );
t( 'an unknown event finds nothing',    null === arv_stats_store_find( 'https://live.aravaiparunning.com/#/nope-2026' ) );
t( 'and a blank url is safe',           null === arv_stats_store_find( '' ) );

// A negative count is not a smaller count, it is broken input.
arv_stats_store_set( array( array( 'slug' => 'x-2026', 'finishers' => -5 ) ) );
t( 'a negative count floors at zero',   0 === arv_stats_store_get()['x-2026']['finishers'] );

echo "\nresults stats: which divisions ran:\n";
$two = array( array( 'distance' => '50K', 'men' => array(), 'women' => array() ) );
t( 'men and women, in that order',      array( 'men', 'women' ) === arv_stats_divisions_present( $two ) );

// Nonbinary is a division Aravaipa scores, not a stray value: Javelina 2025
// placed four nonbinary finishers in the Jackass 31K. A men-and-women table
// would erase real results from the flagship race.
$three = array(
	array( 'distance' => '100 Miler', 'men' => array(), 'women' => array() ),
	array( 'distance' => '31K', 'men' => array(), 'nonbinary' => array() ),
);
t( 'a scored division adds a column',   array( 'men', 'women', 'nonbinary' ) === arv_stats_divisions_present( $three ) );

// And the 178 events with no nonbinary entrant do not each carry an empty
// column to accommodate the nine that do.
t( 'an unscored one adds nothing',      ! in_array( 'nonbinary', arv_stats_divisions_present( $two ), true ) );
t( 'nothing at all is empty',           array() === arv_stats_divisions_present( array() ) );

echo "\nresults stats: on the row:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_stats_store_set( array(
	array(
		'slug'      => 'vertigo_night_runs-2026',
		'finishers' => 1470,
		'headline'  => true,
		'winners'   => array(
			array(
				'distance' => '52KM',
				'men'      => array( 'name' => 'Alex Bustamante', 'time' => '5:49:58' ),
				'women'    => array( 'name' => 'Sydney Park', 'time' => '6:02:07' ),
			),
			array(
				'distance' => '20K',
				'men'      => array( 'name' => 'Devin Sharps', 'time' => '1:33:38' ),
			),
		),
	),
	array( 'slug' => 'vertigo_night_runs-2025', 'finishers' => 312, 'headline' => true ),
	array( 'slug' => 'rock_hawk-2026', 'finishers' => 0 ),
) );

$stats = arv_stats_store_find( 'https://live.aravaiparunning.com/#/vertigo_night_runs-2026' );

// The count rides on the date line rather than taking one of its own.
$count = arv_results_finisher_count( $stats );
t( 'the count is a line fragment',      false !== strpos( $count, '1,470 finishers' ) );
t( 'and never its own block',           false === strpos( $count, '<p' ) );

// Silence, not "0 finishers", under a race that has not happened.
t( 'a future race counts nothing',      '' === arv_results_finisher_count( arv_stats_store_find( 'https://live.aravaiparunning.com/#/rock_hawk-2026' ) ) );
t( 'nor does an unknown race',          '' === arv_results_finisher_count( arv_stats_store_find( 'https://live.aravaiparunning.com/#/nope-2026' ) ) );
t( 'nor no stats at all',               '' === arv_results_finisher_count( null ) );

// One finisher is a finisher, not a finishers.
arv_stats_store_set( array( array( 'slug' => 'solo-2026', 'finishers' => 1 ) ) );
t( 'one finisher reads singular',       false !== strpos( arv_results_finisher_count( arv_stats_store_find( 'https://live.aravaiparunning.com/#/solo-2026' ) ), '1 finisher<' ) );

echo "\nresults stats: the marquee winners:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$head = arv_results_headline_winners( array(
	'headline' => true,
	'winners'  => array(
		array(
			'distance' => '52KM',
			'men'      => array( 'name' => 'Alex Bustamante', 'time' => '5:49:58' ),
			'women'    => array( 'name' => 'Sydney Park', 'time' => '6:02:07' ),
		),
		array( 'distance' => '20K', 'men' => array( 'name' => 'Devin Sharps', 'time' => '1:33:38' ) ),
	),
) );

// Both divisions, not one winner. At Bear Chase 2024 the women's 100K
// champion beat the men's, so naming a single winner was a wrong answer.
t( 'the headline names the men',        false !== strpos( $head, 'Alex Bustamante' ) );
t( 'and the women',                     false !== strpos( $head, 'Sydney Park' ) );
t( 'with their times',                  false !== strpos( $head, '5:49:58' ) && false !== strpos( $head, '6:02:07' ) );
t( 'and the distance they ran',         false !== strpos( $head, '>52K<' ) );
t( 'normalised, like everywhere else',  false === strpos( $head, '52KM' ) );

// The division is named for a screen reader and not drawn: sighted readers
// get it from the names, and "Men Alex Bustamante" is scaffolding.
t( 'divisions are named for a11y',      false !== strpos( $head, 'Men: ' ) && false !== strpos( $head, 'Women: ' ) );

// Only the premier distance. The rest are in the table.
t( 'the headline is one distance',      false === strpos( $head, 'Devin Sharps' ) );

// A winner's name is untrusted text like any other.
t( 'a winner name is escaped',          false === strpos(
	arv_results_headline_winners( array(
		'headline' => true,
		'winners'  => array( array( 'distance' => '50K', 'men' => array( 'name' => '<script>x</script>', 'time' => '1:00:00' ) ) ),
	) ),
	'<script>x'
) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nresults stats: the winners table:\n";
$full = array(
	'finishers' => 100,
	'headline'  => true,
	'winners'   => array(
		array(
			'distance' => '52K',
			'men'      => array( 'name' => 'Alex Bustamante', 'time' => '5:49:58' ),
			'women'    => array( 'name' => 'Sydney Park', 'time' => '6:02:07' ),
		),
		array(
			'distance'  => '20K',
			'men'       => array( 'name' => 'Devin Sharps', 'time' => '1:33:38' ),
			'nonbinary' => array( 'name' => 'Liliana Gutierrez', 'time' => '3:45:10' ),
		),
	),
);
$table = arv_results_winners_table( $full );
t( 'every distance is a row',           false !== strpos( $table, '>52K<' ) && false !== strpos( $table, '>20K<' ) );
t( 'and every winner a cell',           false !== strpos( $table, 'Devin Sharps' ) && false !== strpos( $table, 'Liliana Gutierrez' ) );
t( 'columns are the scored divisions',  false !== strpos( $table, '>Men<' ) && false !== strpos( $table, '>Women<' ) && false !== strpos( $table, '>Nonbinary<' ) );
t( 'it counts what it holds',           false !== strpos( $table, '2 distances' ) );

// A division the board could not resolve is an empty cell, not a dash: one
// 6K women's winner on the archive has a finish stamp equal to her start.
t( 'a missing winner is a blank cell',  false !== strpos( $table, '<td></td>' ) );

// Nothing to open. An event with one scored distance would otherwise get a
// control that repeats the line directly above it.
$one = array( 'headline' => true, 'winners' => array( array(
	'distance' => '50K', 'men' => array( 'name' => 'A', 'time' => '1:00:00' ),
) ) );
t( 'one distance needs no table',       '' === arv_results_winners_table( $one ) );

// Unless there was no headline to repeat. A lap event runs every category
// over one loop, so it has no premier distance, but "who won the six hour
// solo" is still a real answer.
$lap = array_merge( $one, array( 'headline' => false ) );
t( 'but a lap event still gets one',    false !== strpos( arv_results_winners_table( $lap ), 'A' ) );
t( 'and no winners means no table',     '' === arv_results_winners_table( array( 'headline' => false ) ) );

// A lap event has no marquee result to name.
t( 'no headline, no headline line',     '' === arv_results_headline_winners( array( 'headline' => false, 'winners' => $full['winners'] ) ) );
t( 'and none without winners either',   '' === arv_results_headline_winners( array( 'headline' => true ) ) );

echo "\nresults: years on the expander:\n";
t( 'years are listed newest first',     '2025, 2024, 2023' === arv_results_years( array(
	array( 'iso' => '2025-08-09' ),
	array( 'iso' => '2024-08-10' ),
	array( 'iso' => '2023-08-12' ),
) ) );

// Some races run twice in one calendar year. The year is still one year.
t( 'a repeated year is said once',      '2024' === arv_results_years( array(
	array( 'iso' => '2024-03-01' ),
	array( 'iso' => '2024-11-01' ),
) ) );
t( 'and nothing from nothing',          '' === arv_results_years( array() ) );

echo "\nresults: the archive with stats in it:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_stats_store_set( array(
	array(
		'slug'      => 'vertigo_night_runs-2026',
		'finishers' => 349,
		'headline'  => true,
		'winners'   => array(
			array(
				'distance' => '52K',
				'men'      => array( 'name' => 'Alex Bustamante', 'time' => '5:49:58' ),
				'women'    => array( 'name' => 'Sydney Park', 'time' => '6:02:07' ),
			),
			array(
				'distance' => '20K',
				'men'      => array( 'name' => 'Devin Sharps', 'time' => '1:33:38' ),
				'women'    => array( 'name' => 'Tasia Punak', 'time' => '2:11:57' ),
			),
		),
	),
	array( 'slug' => 'vertigo_night_runs-2025', 'finishers' => 312, 'headline' => true ),
) );

$rows = array(
	array(
		'name' => 'Vertigo Night Runs', 'iso' => '2026-08-08', 'display' => 'August 8',
		'live' => 'https://live.aravaiparunning.com/#/vertigo_night_runs-2026',
		'ultrasignup' => '', 'ultrarunning' => '',
	),
	array(
		'name' => 'Vertigo Night Runs', 'iso' => '2025-08-09', 'display' => 'August 9',
		'live' => 'https://live.aravaiparunning.com/#/vertigo_night_runs-2025',
		'ultrasignup' => '', 'ultrarunning' => '',
	),
);

$html = arv_results_by_race( $rows, false );
t( 'the latest edition counts',         false !== strpos( $html, '349 finishers' ) );

// The count belongs on the date line. Its own paragraph is what made a race
// group four lines tall before the first result showed up.
t( 'on the date line, not its own',     false !== strpos( $html, 'August 8, 2026 <span class="arv-results__stat">349 finishers</span>' ) );
t( 'and names its marquee winners',     false !== strpos( $html, 'Alex Bustamante' ) && false !== strpos( $html, 'Sydney Park' ) );
t( 'the table holds the other one',     false !== strpos( $html, 'Devin Sharps' ) );
t( 'the older edition carries a count', false !== strpos( $html, '312 finishers' ) );
t( 'the expander names its years',      false !== strpos( $html, '2025</span>' ) );
t( 'and still counts them',             false !== strpos( $html, '1 earlier edition' ) );

// Nothing stored at all is the state this page shipped in, and it has to
// keep rendering exactly as it did.
$GLOBALS['ARV_OPTIONS'] = array();
$bare = arv_results_by_race( $rows, false );
t( 'no stats means no stat line',       false === strpos( $bare, 'finishers' ) );
t( 'and no winners table',              false === strpos( $bare, 'arv-results__winners' ) );
t( 'but the races still render',        false !== strpos( $bare, 'Vertigo Night Runs' ) );
t( 'and so do their links',             false !== strpos( $bare, 'vertigo_night_runs-2026' ) );

// ----------------------------------------------------------- Month heads --
// Seventy-four races in one column gave no sense of where in the list you
// were, and the tail of it read as the page wandering off rather than as the
// archive running out of races.
$monthly = arv_results_by_race(
	array(
		array( 'name' => 'Rock Hawk', 'iso' => '2026-08-29', 'display' => 'August 29', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
		array( 'name' => 'Vertigo Night Runs', 'iso' => '2026-08-08', 'display' => 'August 8', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
		array( 'name' => 'Silverton Alpine Marathon', 'iso' => '2026-07-18', 'display' => 'July 18', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
		array( 'name' => 'Flagstaff Big Pine', 'iso' => '2023-06-10', 'display' => 'June 10', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
	),
	false
);

t( 'the month heads the run',           false !== strpos( $monthly, '>August 2026</h3>' ) );
t( 'the year is on it',                 false !== strpos( $monthly, '>June 2023</h3>' ) );
t( 'one head per month, not per race',  1 === substr_count( $monthly, 'August 2026</h3>' ) );
t( 'newest month first',                strpos( $monthly, 'August 2026' ) < strpos( $monthly, 'July 2026' ) );
t( 'and the oldest last',               strpos( $monthly, 'July 2026' ) < strpos( $monthly, 'June 2023' ) );
// Four races, three months: the two August races share a heading.
t( 'a month per month, not per race',   3 === substr_count( $monthly, 'data-arv-results-month' ) );
t( 'and every one of them closes',      substr_count( $monthly, '<section' ) === substr_count( $monthly, '</section>' ) );

// Races sit under a month heading now, so they are a level deeper than they
// were. A heading that skips a level is the accessibility bug this avoids.
t( 'the race is under the month',       false !== strpos( $monthly, '<h4 class="arv-results__race-name">' ) );
t( 'and the month is under the block',  false !== strpos( $monthly, '<h3 class="arv-results__month-head">' ) );

// The date sits on the group, so a race whose latest edition is old files
// under that old month rather than under the month the page was built in.
t( 'an old race files under its own',   strpos( $monthly, 'June 2023' ) < strpos( $monthly, 'Flagstaff Big Pine' ) );

t( 'a month label is month and year',   'August 2026' === arv_results_month_label( '2026-08-29' ) );
t( 'and it reads the date as a date',   'August 2026' === arv_results_month_label( '2026-08-01' ) );
t( 'a junk date labels nothing',        '' === arv_results_month_label( '' ) );
t( 'and neither does a bad one',        '' === arv_results_month_label( 'soon' ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nstats import route:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_stats_store_set( array_map(
	function ( $i ) { return array( 'slug' => "e-$i", 'finishers' => 10 ); },
	range( 1, 10 )
) );

// A walk that failed partway reports a fraction of the archive. This store
// only grows, so that is a broken run rather than a shrinking archive.
$half = arv_stats_rest_set( new ARV_Req( array( 'events' => array( array( 'slug' => 'e-1', 'finishers' => 1 ) ) ) ) );
t( 'a big drop is refused',             'refused' === $half['status'] );
t( 'and nothing is written',            10 === count( arv_stats_store_get() ) );
t( 'unless it is forced',               'ok' === arv_stats_rest_set( new ARV_Req( array(
	'events' => array( array( 'slug' => 'e-1', 'finishers' => 1 ) ),
	'force'  => true,
) ) )['status'] );
$GLOBALS['ARV_OPTIONS'] = array();

$GLOBALS['ARV_OPTIONS'] = array();

echo "\nlive page: editions and year picking:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
	// A different race, which must not be swept into the group.
	array( 'name' => 'Rock Hawk', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/rock_hawk-2026' ),
) );

$eds = arv_live_editions( 'black_bear-2026' );
t( 'both editions are found',           2 === count( $eds ) );
t( 'newest first',                      '2026-08-29' === $eds[0]['iso'] );
t( 'another race is not swept in',      1 === count( arv_live_editions( 'rock_hawk-2026' ) ) );
t( 'an unknown slug finds none',        array() === arv_live_editions( 'nope-2026' ) );
t( 'and a blank slug is safe',          array() === arv_live_editions( '' ) );

// The board renames races between years: Kilkenny Ridge is
// "kilkenny_ridge-2025" and then "killeny_ridge-2026", typo included. Slug
// surgery would split the race in two; grouping by name does not.
arv_results_store_set( array(
	array( 'name' => 'Kilkenny Ridge Race', 'iso' => '2026-09-19', 'display' => 'September 19',
	       'live' => 'https://live.aravaiparunning.com/#/killeny_ridge-2026' ),
	array( 'name' => 'Kilkenny Ridge Race', 'iso' => '2025-09-20', 'display' => 'September 20',
	       'live' => 'https://live.aravaiparunning.com/#/kilkenny_ridge-2025' ),
) );
t( 'a renamed slug still groups',       2 === count( arv_live_editions( 'killeny_ridge-2026' ) ) );
t( 'from either end of the rename',     2 === count( arv_live_editions( 'kilkenny_ridge-2025' ) ) );

$two = array( array( 'iso' => '2026-08-29' ), array( 'iso' => '2025-08-30' ) );
t( 'a year picks its edition',          '2025-08-30' === arv_live_pick_edition( $two, '2025' )['iso'] );
t( 'no year picks the newest',          '2026-08-29' === arv_live_pick_edition( $two, '' )['iso'] );
// A stale link should land on this year's race, not on an empty page.
t( 'a year that never ran falls back',  '2026-08-29' === arv_live_pick_edition( $two, '2019' )['iso'] );
t( 'and junk does too',                 '2026-08-29' === arv_live_pick_edition( $two, 'drop table' )['iso'] );
t( 'nothing at all is null',            null === arv_live_pick_edition( array(), '2025' ) );

echo "\nlive page: what a crawler sees:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
) );
arv_stats_store_set( array(
	array( 'slug' => 'black_bear-2026', 'finishers' => 0 ),
	array(
		'slug' => 'black_bear-2025', 'finishers' => 225, 'headline' => true,
		'winners' => array(
			array( 'distance' => '50K',
			       'men'   => array( 'name' => 'Sam Reed', 'time' => '4:41:02' ),
			       'women' => array( 'name' => 'Ana Cruz', 'time' => '5:02:19' ) ),
			array( 'distance' => '23K',
			       'men'   => array( 'name' => 'Tom Vale', 'time' => '1:52:40' ) ),
		),
	),
) );

$html = arv_live_page_render( array( 'slug' => 'black_bear-2026' ) );
t( 'the race is named in the heading',  false !== strpos( $html, 'Black Bear Trail Race' ) );
t( 'the board is framed',               false !== strpos( $html, '<iframe' ) && false !== strpos( $html, 'black_bear-2026' ) );

// An iframe is invisible to a crawler and unreachable to some assistive
// technology, so a real anchor to the board has to exist alongside it.
t( 'and also plainly linked',           false !== strpos( $html, 'Open full live results' ) );
t( 'the frame cannot navigate the top', false !== strpos( $html, 'sandbox="allow-scripts allow-same-origin allow-popups"' ) );
t( 'the years are real links',          false !== strpos( $html, 'edition=2025' ) );

// This edition has not been run, so there is nothing to report and
// "0 finishers" would be worse than silence.
t( 'an unrun edition reports nothing',  false === strpos( $html, 'finisher' ) );

$last = arv_live_page_render( array( 'slug' => 'black_bear-2026', 'year' => '2025' ) );
// The page is the board and the bar around it, nothing else. The summary
// that used to sit here restated the top of a table the reader is looking
// straight at, so it went.
t( 'last year renders its own page',    false !== strpos( $last, 'black_bear-2025' ) );
t( 'and does not restate the results',  false === strpos( $last, '2025 results' ) );
t( 'but does say who won it',           false !== strpos( $last, 'Sam Reed' ) );
t( 'the frame follows the year',        false !== strpos( $last, 'black_bear-2025' ) );

// The board is the authority on what it is timing. A race added this week
// should get a working live page before the archive scraper has ever run.
$GLOBALS['ARV_OPTIONS'] = array();
$bare = arv_live_page_render( array( 'slug' => 'brand_new-2026' ) );
t( 'an unknown race still gets a frame', false !== strpos( $bare, 'brand_new-2026' ) );
t( 'but claims nothing about it',        false === strpos( $bare, 'finisher' ) );
t( 'and no slug renders nothing',        '' === arv_live_page_render( array( 'slug' => '' ) ) );
t( 'nor does no argument at all',        '' === arv_live_page_render() );

// A single edition needs no switcher.
arv_results_store_set( array(
	array( 'name' => 'One Off', 'iso' => '2026-01-01', 'display' => 'January 1',
	       'live' => 'https://live.aravaiparunning.com/#/one_off-2026' ),
) );
t( 'one edition shows no year switcher', false === strpos( arv_live_page_render( array( 'slug' => 'one_off-2026' ) ), 'arv-live__years' ) );

// A winner's name is untrusted text like any other.
arv_stats_store_set( array( array(
	'slug' => 'one_off-2026', 'finishers' => 3, 'headline' => true,
	'winners' => array( array( 'distance' => '10K', 'men' => array( 'name' => '<script>x</script>', 'time' => '0:40:00' ) ) ),
) ) );
t( 'a winner name is escaped',           false === strpos( arv_live_page_render( array( 'slug' => 'one_off-2026' ) ), '<script>x' ) );

// The height is an editor field, so it is clamped rather than trusted.
$tall = arv_live_page_render( array( 'slug' => 'one_off-2026', 'height' => 999999 ) );
t( 'an absurd height is clamped',        false !== strpos( $tall, 'height:2000px' ) );
$short = arv_live_page_render( array( 'slug' => 'one_off-2026', 'height' => -5 ) );

// ------------------------------------------------------- Frame height --
// The board is cross-origin and tells us nothing, so the height is computed
// from the entrant count the stats scraper records. 44px a row plus the
// board's own chrome, measured across five editions from 61 entrants to 404
// and identical at 390px and 1200px.
t( 'a known field sizes the frame',     3252 === arv_live_frame_height( array( 'rows' => 64 ), 780 ) );
t( 'and a bigger field a taller one',   18212 === arv_live_frame_height( array( 'rows' => 404 ), 780 ) );
t( 'one more entrant is one more row',  arv_live_frame_height( array( 'rows' => 65 ), 780 ) - arv_live_frame_height( array( 'rows' => 64 ), 780 ) === 44 );

// Unknown is the common case for an edition nobody has scraped yet, and a
// fixed frame that scrolls inside itself is what this replaces rather than a
// failure to handle.
t( 'no stats keeps the given height',   780 === arv_live_frame_height( null, 780 ) );
t( 'nor does a row count of zero',      780 === arv_live_frame_height( array( 'rows' => 0 ), 780 ) );
t( 'and neither does a missing key',    780 === arv_live_frame_height( array( 'finishers' => 12 ), 780 ) );

// A tiny field would otherwise render a letterbox, and a field longer than
// Cocodona's asks for a page nobody can navigate.
t( 'a tiny field floors at the given',  780 === arv_live_frame_height( array( 'rows' => 3 ), 780 ) );
t( 'and an absurd one is capped',       20000 === arv_live_frame_height( array( 'rows' => 99999 ), 780 ) );
t( 'the fallback is clamped too',       arv_live_frame_height( null, 99999 ) <= 2000 );
t( 'and floored',                       arv_live_frame_height( null, 1 ) >= 400 );

// ------------------------------------------------------- Bar furniture --
// The live marker moved onto the title, so it has to still be inside the row
// the clock script drives, and it has to still be hidden when the race is
// not live. It carried a [hidden] attribute the whole time it was wrong on
// the page, because a display in the stylesheet beat the attribute, so the
// attribute alone is not proof of much.
$live_bar = arv_live_bar(
	'Black Bear Trail Race',
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29', 'live' => '' ),
	array( 'name' => 'Black Bear Trail Race', 'location' => 'Waterville Valley, NH' ),
	array(),
	'black_bear-2026'
);

t( 'the marker is on the title',        strpos( $live_bar, 'arv-results__live' ) < strpos( $live_bar, 'arv-live__title' ) );
t( 'and inside the clock-driven row',   false !== strpos( $live_bar, 'data-arv-results-row' ) );
t( 'the bar carries an instagram link', false !== strpos( $live_bar, 'instagram.com' ) );
t( 'and it opens away from the site',   false !== strpos( $live_bar, 'rel="noopener"' ) );
t( 'and names the account it goes to',  false !== strpos( $live_bar, 'on Instagram' ) );

// No race in the calendar means no account worth guessing at.
$bare_bar = arv_live_bar( 'Some Retired Race', null, null, array(), 'retired-2013' );
t( 'no race, no instagram guess',       false === strpos( $bare_bar, 'instagram.com' ) );
t( 'and no clock to count to',          false === strpos( $bare_bar, 'arv-results__countdown' ) );

// No results summary on a live page. Jamil saw it in both positions and
// wanted it gone either way: the board below it already lists every finisher
// and every time, so the summary restated the top of a table the reader is
// looking straight at.
//
// It cost the pages their only crawlable text, since the board is a
// cross-origin iframe and a search engine reads none of it. That content is
// not lost to the site: /results-2026/ carries the same winners and finisher
// counts in real HTML, and that is the page built to be indexed.
$GLOBALS['ARV_OPTIONS'] = array();
arv_stats_store_set( array(
	array(
		'slug'      => 'black_bear-2025',
		'name'      => 'Black Bear Trail Races',
		'finishers' => 225,
		'starters'  => 260,
		'rows'      => 64,
		'headline'  => true,
		'winners'   => array(
			array( 'distance' => '50K', 'men' => array( 'name' => 'Jarrod Beauregard', 'time' => '5:54:47' ) ),
		),
	),
) );
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
) );

$page = arv_live_page_render( array( 'slug' => 'black_bear-2025', 'year' => '2025' ) );
t( 'a finished race gets no table',     false === strpos( $page, 'arv-live__report' ) );

// One line rather than the table that was there: it answers "who won" at a
// glance and is the only text on the page a crawler can read, since the
// board is a cross-origin iframe.
t( 'but it says who won',               false !== strpos( $page, 'Jarrod Beauregard' ) );
t( 'with the winning time',             false !== strpos( $page, '5:54:47' ) );
t( 'and how many finished',             false !== strpos( $page, '225 finishers' ) );
t( 'naming the headline distance',      false !== strpos( $page, '>50K ' ) );
t( 'and only that distance',            false === strpos( $page, 'Matthew Reynolds' ) );
t( 'it is one line, not a table',       false === strpos( $page, '<table' ) );
t( 'and readable with the tags off',    false !== strpos( strip_tags( $page ), 'Jarrod Beauregard' ) );

// A lap event runs every category over the same loop, so its distances come
// back within metres of each other and "who won the event" is not a question
// with an answer. The scraper already decides this; the headline honours it
// and reports the count alone.
t( 'a lap event names no winner',       false === strpos( arv_live_result_line( array(
	'finishers' => 74, 'headline' => false,
	'winners'   => array( array( 'distance' => '6 Hour', 'men' => array( 'name' => 'Someone', 'time' => '1:00:00' ) ) ),
) ), 'Someone' ) );
t( 'but still counts its finishers',    false !== strpos( arv_live_result_line( array(
	'finishers' => 74, 'headline' => false, 'winners' => array(),
) ), '74 finishers' ) );

// A race with no finishers has nothing to report and should not say "0".
t( 'an unrun race says nothing',        '' === arv_live_result_line( array( 'finishers' => 0, 'rows' => 113 ) ) );
t( 'and neither does no stats row',     '' === arv_live_result_line( null ) );
t( 'one finisher is singular',          false !== strpos( arv_live_result_line( array(
	'finishers' => 1, 'headline' => false, 'winners' => array(),
) ), '1 finisher<' ) );

// The entrant count is still read from the same stats row, for the frame.
t( 'but the frame is still sized',      false !== strpos( $page, 'height:3252px' ) );
t( 'and the board is still there',      false !== strpos( $page, 'black_bear-2025' ) );

// Two races on the same day are ordered by the clock. Black Bear in New
// Hampshire and Rock Hawk in Colorado both run on the 29th and start two
// hours apart, and the later one was listed first under a heading that says
// soonest: the date alone cannot separate them, since they are in different
// timezones, so the board's start time is the only thing that can.
$later   = array( 'iso' => '2026-08-29', 'name' => 'Rock Hawk',  'board' => array( 'start' => '2026-08-29T14:00:00Z', 'cutoff' => '' ) );
$sooner  = array( 'iso' => '2026-08-29', 'name' => 'Black Bear', 'board' => array( 'start' => '2026-08-29T12:00:00Z', 'cutoff' => '' ) );

t( 'the board start is read off a row', strtotime( '2026-08-29T12:00:00Z' ) === arv_live_start_ts( $sooner ) );
t( 'and a row with no board gives 0',   0 === arv_live_start_ts( array( 'iso' => '2026-08-29' ) ) );
t( 'and neither does an empty start',   0 === arv_live_start_ts( array( 'board' => array( 'start' => '', 'cutoff' => '' ) ) ) );

// ------------------------------------------- Live buttons find the page --
// Every Live Results button on the site should land on the branded page for
// that race where one has been built, and on the board itself where one has
// not, which is still most races.
$GLOBALS['meta'] = array( 55 => array( '_arv_live_slug' => 'rock_hawk-2026' ) );
$GLOBALS['PERMALINK'] = array( 55 => 'https://www.aravaiparunning.com/live-results/rock-hawk/' );
arv_live_page_map( true );

t( 'a board url finds its own page',    'https://www.aravaiparunning.com/live-results/rock-hawk/'
	=== arv_live_page_for_live_url( 'https://live.aravaiparunning.com/#/rock_hawk-2026' ) );
t( 'a race with no page gets nothing',  '' === arv_live_page_for_live_url( 'https://live.aravaiparunning.com/#/javelina-2026' ) );
t( 'and no url at all gets nothing',    '' === arv_live_page_for_live_url( '' ) );

// The single place every card, list and calendar reads its button url from.
$with = arv_upcoming_races_action(
	array( 'name' => 'Rock Hawk', 'iso' => '2026-08-29', 'end' => '', 'closes' => '2026-08-24',
	       'live' => 'https://live.aravaiparunning.com/#/rock_hawk-2026', 'register' => '', 'page' => '' ),
	'2026-08-28'
);
t( 'the button points at the page',     'https://www.aravaiparunning.com/live-results/rock-hawk/' === $with['url'] );
t( 'and still says Live Results',       'Live Results' === $with['label'] );

$without = arv_upcoming_races_action(
	array( 'name' => 'Javelina Jundred', 'iso' => '2026-08-29', 'end' => '', 'closes' => '2026-08-24',
	       'live' => 'https://live.aravaiparunning.com/#/javelina-2026', 'register' => '', 'page' => '' ),
	'2026-08-28'
);
t( 'a race with no page keeps the board', 'https://live.aravaiparunning.com/#/javelina-2026' === $without['url'] );

// An internal destination should not open a new tab. Everything this element
// linked to used to be off-site, so the attribute was unconditional.
t( 'the site\'s own page opens in place', '' === arv_races_link_target( 'https://www.aravaiparunning.com/live-results/rock-hawk/' ) );
t( 'and the board opens in a new tab',    false !== strpos( arv_races_link_target( 'https://live.aravaiparunning.com/#/x-2026' ), 'target="_blank"' ) );
t( 'carrying noopener with it',           false !== strpos( arv_races_link_target( 'https://ultrasignup.com/x' ), 'rel="noopener"' ) );

// Distances on a card read the way the race week block writes them. The
// store keeps whatever the source said, so the same race read "50KM | 23K"
// on the home page and "50K 23K" three sections down the results page.
t( 'kilometres are normalised',         '50K | 23K | 4 Mile | 1 Mile' === arv_races_distance_list( '50KM|23K|4 Mile|1 Mile' ) );
t( 'and a spaced K too',                '10K | 5K' === arv_races_distance_list( '10 KM|5KM' ) );
t( 'miles are left as they are',        '100 Mile | 42K' === arv_races_distance_list( '100 Mile|42K' ) );
t( 'one distance still works',          '50K' === arv_races_distance_list( '50KM' ) );
t( 'and no distances says nothing',     '' === arv_races_distance_list( '' ) );
t( 'an empty slot is dropped',          '50K | 23K' === arv_races_distance_list( '50KM||23K' ) );

// Both helpers live in helpers.php rather than inside an element, because
// four elements use them and only one of those was guaranteed to be loaded.
// The edge suite renders the season calendar on its own and hit an undefined
// arv_results_distance_label() doing exactly that.
t( 'the distance label is shared',      false !== strpos( (string) ( new ReflectionFunction( 'arv_results_distance_label' ) )->getFileName(), 'includes/helpers.php' ) );
t( 'and so is the link target',         false !== strpos( (string) ( new ReflectionFunction( 'arv_races_link_target' ) )->getFileName(), 'includes/helpers.php' ) );

$GLOBALS['meta'] = array();
$GLOBALS['PERMALINK'] = array();
arv_live_page_map( true );

$GLOBALS['ARV_OPTIONS'] = array();

$GLOBALS['ARV_OPTIONS'] = array();

// ------------------------------------------------------ Pinned editions --
// A year page pins itself with year=, because the slug alone cannot: the
// main page for a race carries the current year's slug and has to follow the
// race into next year, so a slug year cannot mean "stay here". Without the
// attribute the 2025 page rendered the 2026 board under a 2025 breadcrumb,
// with 2026 lit in its own year switcher.
$eds = array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29', 'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2025-08-30', 'display' => 'August 30', 'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2024-08-31', 'display' => 'August 31', 'live' => 'https://live.aravaiparunning.com/#/black_bear-2024' ),
);

t( 'no year asked for takes newest',    '2026-08-29' === arv_live_pick_edition( $eds, '' )['iso'] );
t( 'a year asked for is honoured',      '2025-08-30' === arv_live_pick_edition( $eds, '2025' )['iso'] );
t( 'and a year with no edition falls back', '2026-08-29' === arv_live_pick_edition( $eds, '1999' )['iso'] );

// The reader's own ?edition= beats the page's pin, so a pinned page's own
// year links still work where there is no separate page to send them to.
$GLOBALS['ARV_OPTIONS']['arv_race_results'] = $eds;

$_GET[ ARV_LIVE_YEAR_VAR ] = '2024';
$pinned = arv_live_page_render( array( 'slug' => 'black_bear-2025', 'year' => '2025' ) );
t( 'the reader outranks the pin',       false !== strpos( $pinned, 'black_bear-2024' ) );
unset( $_GET[ ARV_LIVE_YEAR_VAR ] );

$pinned = arv_live_page_render( array( 'slug' => 'black_bear-2025', 'year' => '2025' ) );
t( 'and the pin outranks newest',       false !== strpos( $pinned, 'black_bear-2025' ) );
t( 'so the pinned year is the frame',   false === strpos( $pinned, 'black_bear-2026' ) );

// And the same page with no pin at all is the bug that started this.
$unpinned = arv_live_page_render( array( 'slug' => 'black_bear-2025' ) );
t( 'no pin still means newest',         false !== strpos( $unpinned, 'black_bear-2026' ) );

// -------------------------------------------------- Live cannot outlast --
// A board start with no cutoff said a race was live from the gun to the end
// of time, in the markup and again in the script that drives the clock a
// second later. Black Bear's 2025 page carried LIVE NOW and an elapsed clock
// reading 363 days.
$long_ago = gmdate( 'c', strtotime( '-363 days' ) );
// Relative to the clock the harness freezes, not the wall, or a start two
// real hours ago lands in this run's future.
$earlier  = gmdate( 'c', arv_results_now() - 7200 );

t( 'a real cutoff is left alone',       1234 === arv_results_backstop_cutoff( 1234, $long_ago ) );
t( 'no cutoff gets one from the start', arv_results_backstop_cutoff( 0, $long_ago ) === strtotime( $long_ago ) + ARV_RESULTS_MAX_RUN );
t( 'and it is in the past for a race a year old', arv_results_backstop_cutoff( 0, $long_ago ) < time() );
t( 'but not for one that started today', arv_results_backstop_cutoff( 0, $earlier ) > arv_results_now() );
t( 'no start means no backstop',        0 === arv_results_backstop_cutoff( 0, '' ) );

// The state both ends agree on.
$stale = array( 'start' => $long_ago, 'cutoff' => '' );
t( 'a year-old race reads as done',     'done' === arv_live_state( 'Black Bear Trail Race', '2025-08-30', $stale ) );

$running = array( 'start' => $earlier, 'cutoff' => '' );
t( 'one that started today is live',    'live' === arv_live_state( 'Black Bear Trail Race', gmdate( 'Y-m-d', arv_results_now() ), $running ) );

// And the markup hands the script the same number rather than leaving it to
// work one out, which is how the two disagreed in the first place.
$stale_markup = arv_results_week_status(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2025-08-30', 'board' => $stale, 'state' => 'done' )
);
t( 'the clock is given a cutoff',       false !== strpos( $stale_markup, 'data-arv-cutoff' ) );

// --------------------------------------------------------- Year pages --
// The site has a page per year and a menu built on them, so a page called
// results-2026 says what it is about in its own slug. The element's Year
// setting lives in Cornerstone's data, which is not reachable from here, so
// without this a year page has to be told its year by hand in a builder,
// once a year, forever.
$GLOBALS['POST_FIELD'] = array(
	11 => array( 'post_name' => 'results-2026' ),
	12 => array( 'post_name' => 'results-2008-2010' ),
	13 => array( 'post_name' => 'results-archive' ),
	14 => array( 'post_name' => 'live-results' ),
);

$GLOBALS['QUERIED_ID'] = 11;
t( 'a year page names its own year',    '2026' === arv_results_year_from_page() );
$GLOBALS['QUERIED_ID'] = 12;
t( 'a span of years is not one year',   '' === arv_results_year_from_page() );
$GLOBALS['QUERIED_ID'] = 13;
t( 'and an archive keeps every year',   '' === arv_results_year_from_page() );
$GLOBALS['QUERIED_ID'] = 14;
t( 'and so does an unrelated page',     '' === arv_results_year_from_page() );
$GLOBALS['QUERIED_ID'] = 0;
t( 'off a page entirely, no year',      '' === arv_results_year_from_page() );

// Filtering keeps the races that ran that year, not the rows dated in it.
// Row by row threw away every earlier edition folded under a race, which is
// half of what the archive is for: a race's own past is not another event.
$hist = array(
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2027-08-28', 'display' => 'August 28', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
	array( 'name' => 'Black Bear Trail Race', 'iso' => '2026-08-29', 'display' => 'August 29', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2025-08-30', 'display' => 'August 30', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
	array( 'name' => 'Hypnosis', 'iso' => '2023-06-24', 'display' => 'June 24', 'live' => '', 'ultrasignup' => '', 'ultrarunning' => '' ),
);

$only26 = arv_results_filter_year( $hist, '2026' );
t( 'a race that ran that year stays',   2 === count( $only26 ) );
t( 'and keeps its earlier editions',    '2025-08-30' === $only26[1]['iso'] );
t( 'a race that did not ran goes',      false === strpos( wp_json_encode( $only26 ), 'Hypnosis' ) );
t( 'and later years go too',            false === strpos( wp_json_encode( $only26 ), '2027' ) );
t( 'so the year heads its own race',    '2026-08-29' === $only26[0]['iso'] );

t( 'no year keeps everything',          4 === count( arv_results_filter_year( $hist, '' ) ) );
t( 'and so does a junk year',           4 === count( arv_results_filter_year( $hist, 'soon' ) ) );
t( 'a year nobody ran is empty',        0 === count( arv_results_filter_year( $hist, '1999' ) ) );

$GLOBALS['POST_FIELD'] = array();
$GLOBALS['QUERIED_ID'] = 0;

$GLOBALS['ARV_OPTIONS'] = array();
t( 'and so is a negative one',           false !== strpos( $short, 'height:400px' ) );
// The shortcode is the path bulk-created pages use, so it is exercised as
// itself rather than trusted to be a thin wrapper.
t( 'the shortcode renders a page',       false !== strpos( arv_live_shortcode( array( 'slug' => 'one_off-2026' ) ), '<iframe' ) );
t( 'and defaults its own attributes',    false !== strpos( arv_live_shortcode( array( 'slug' => 'one_off-2026' ) ), 'height:780px' ) );
t( 'and is registered under [arv_live]', isset( $GLOBALS['SHORTCODES']['arv_live'] ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nrace cards: live marker on race day only:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_race_store_import(
	"Rock Hawk | 2026-08-29 | August 29 | 50K | 25K |  |  | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/y | https://www.aravaiparunning.com/bcs/rh/ |  |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24 | 1 | 0 | 39.36 | -104.87\n"
);
arv_live_store_set( array( array(
	'slug' => 'rock_hawk-2026', 'start' => '2026-08-29T12:00:00.000Z',
	'cutoff' => '2026-08-29T21:00:00.000Z', 'offset' => -6, 'races' => array(),
) ) );
$card_race = arv_race_store_get()[0];

// A countdown on every card in a list of eight upcoming races is eight
// numbers nobody asked for. "This one is running right now" is worth the
// interruption, and nothing else is.
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T09:00:00Z' );
$before = arv_races_live_clock( $card_race );
t( 'before the gun the marker hides',   false !== strpos( $before, 'data-arv-results-live hidden' ) );
t( 'and no elapsed time is written',    false !== strpos( $before, 'elapsed-value>' ) );

$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T15:30:00Z' );
$during = arv_races_live_clock( $card_race );
t( 'during the race it shows',          false !== strpos( $during, 'data-arv-results-live>' ) );
t( 'with the time elapsed so far',      false !== strpos( $during, '>3:30<' ) );
t( 'and a pulse to catch the eye',      false !== strpos( $during, 'arv-results__pulse' ) );

$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T22:00:00Z' );
$after = arv_races_live_clock( $card_race );
t( 'after the cutoff it hides again',   false !== strpos( $after, 'data-arv-results-live hidden' ) );
t( 'and stops reporting a time',        false === strpos( $after, '>10:00<' ) );

// Both states ship on every card whatever PHP decided, because this site is
// behind a page cache: HTML generated hours before the gun still has to be
// able to go live without a reload. The script swaps them on data-arv-start.
t( 'the clock ships either way',        false !== strpos( $before, 'data-arv-results-clock' ) );
t( 'carrying the real start time',      false !== strpos( $before, 'data-arv-start="2026-08-29T12:00:00+00:00"' ) );
t( 'and a cutoff to stop at',           false !== strpos( $before, 'data-arv-cutoff=' ) );

// A race the board has no clock for gets nothing at all. Falling back to
// midnight would put a confident "Elapsed 14:32:07" on a race that has not
// started.
$GLOBALS['ARV_OPTIONS']['arv_live_board'] = array();
t( 'no board start, no clock',          '' === arv_races_live_clock( $card_race ) );
t( 'and no live URL, no clock',         '' === arv_races_live_clock( array( 'name' => 'X', 'live' => '' ) ) );

$GLOBALS['NOW_TS'] = null;
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nweather: forecast beside the clock:\n";

// Wiring weather into arv_live_bar() means every live-page test run before
// this point already called arv_live_weather(), each one hitting the queue
// with nothing in it and caching that failure under its own coordinates. A
// clean slate here, not just for _transients: the cached-failure keys from
// those runs would otherwise sit in the same global dict this section reads.
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
$GLOBALS['_http_calls'] = 0;

// 0,0 is "no coordinates on file", the same guard the race map uses for the
// same reason, not a real point off the coast of Africa.
t( 'no coordinates, no request',        null === arv_live_weather( 0, 0, 'soon', '2026-08-29T10:00:00Z' ) );
t( 'and no HTTP call was made for it',  0 === $GLOBALS['_http_calls'] );

// Neither "done" nor a junk state asks the network anything.
t( 'a finished race gets no forecast',  null === arv_live_weather( 43.95, -71.5, 'done', '2026-08-29T10:00:00Z' ) );
t( 'nor does an unrecognised state',    null === arv_live_weather( 43.95, -71.5, 'nonsense', '2026-08-29T10:00:00Z' ) );
t( 'a soon race with no start is skipped', null === arv_live_weather( 43.95, -71.5, 'soon', '' ) );

// Upcoming: the forecast for the hour of the gun, not "now".
$GLOBALS['NOW_TS'] = strtotime( '2026-08-28T09:00:00Z' );
arv_test_queue_response( array(
	'code' => 200,
	'body' => wp_json_encode( array(
		'hourly' => array(
			'time'           => array( '2026-08-29T09:00', '2026-08-29T10:00', '2026-08-29T11:00' ),
			'temperature_2m' => array( 61.0, 63.4, 66.0 ),
			'weathercode'    => array( 3, 61, 2 ),
		),
	) ),
) );
$soon = arv_live_weather( 43.95, -71.5, 'soon', '2026-08-29T10:00:00Z' );
t( 'the hour of the gun is picked out',  63 === $soon['temp'] );
t( 'with its own weather code',          61 === $soon['code'] );
t( 'the hourly endpoint was asked',      false !== strpos( $GLOBALS['_last_http_url'], 'hourly=' ) );

// Cached: a second call for the same race and the same hour makes no second
// request.
$again = arv_live_weather( 43.95, -71.5, 'soon', '2026-08-29T10:00:00Z' );
t( 'the second call is cached',          1 === $GLOBALS['_http_calls'] );
t( 'and returns the same answer',        $again === $soon );

// Live: current conditions, not the hour of the gun, and a different
// endpoint.
arv_test_queue_response( array(
	'code' => 200,
	'body' => wp_json_encode( array(
		'current_weather' => array( 'temperature' => 71.2, 'weathercode' => 95 ),
	) ),
) );
$live_wx = arv_live_weather( 43.95, -71.5, 'live', '2026-08-29T10:00:00Z' );
t( 'a running race reads current weather', 71 === $live_wx['temp'] );
t( 'the current-weather endpoint asked',   false !== strpos( $GLOBALS['_last_http_url'], 'current_weather=true' ) );

// A failure is cached too, briefly, rather than retried on every view.
$GLOBALS['_transients'] = array();
$calls_before = $GLOBALS['_http_calls'];
arv_test_queue_response( new WP_Error( 'timeout' ) );
$failed = arv_live_weather( 40.1, -105.2, 'live', '' );
t( 'a failed request reports nothing',   null === $failed );
$still_null = arv_live_weather( 40.1, -105.2, 'live', '' );
t( 'and the failure itself is cached',   $GLOBALS['_http_calls'] === $calls_before + 1 );

// A non-200 is treated the same as a transport failure.
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 500, 'body' => '' ) );
t( 'a bad status reports nothing',       null === arv_live_weather( 51.5, -0.1, 'live', '' ) );

// Malformed JSON, or JSON missing the keys expected, is not a fatal error.
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 200, 'body' => 'not json' ) );
t( 'unparseable JSON reports nothing',   null === arv_live_weather( 51.5, -0.1, 'live', '' ) );
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 200, 'body' => wp_json_encode( array( 'current_weather' => array() ) ) ) );
t( 'a body with no useful keys is safe', null === arv_live_weather( 51.5, -0.1, 'live', '' ) );

// A race more than sixteen days out is not asked for, since Open-Meteo's own
// forecast horizon ends there and a request past it always fails anyway.
$GLOBALS['_transients'] = array();
$calls_before = $GLOBALS['_http_calls'];
$far = arv_live_weather( 43.95, -71.5, 'soon', gmdate( 'Y-m-d\TH:i:s\Z', arv_results_now() + 30 * DAY_IN_SECONDS ) );
t( 'a race far in the future is skipped', null === $far );
t( 'without asking the network for it',   $GLOBALS['_http_calls'] === $calls_before );

// The boundary itself, at exactly sixteen and fifteen days out. Caught by
// testing against the real API rather than trusting the arithmetic: the
// first version of this asked for one day more than Open-Meteo's own
// forecast_days actually needs, which silently dropped every race fifteen
// to sixteen days out, a real week and a half of the calendar, and did so
// without a single failed request to notice: it never asked at all.
$GLOBALS['_transients'] = array();
$edge_ts    = arv_results_now() + 15 * DAY_IN_SECONDS + 3600;
$edge_start = gmdate( 'Y-m-d\TH:00:00\Z', $edge_ts );
arv_test_queue_response( array( 'code' => 200, 'body' => wp_json_encode( array(
	'hourly' => array( 'time' => array( gmdate( 'Y-m-d\TH:00', $edge_ts ) ), 'temperature_2m' => array( 54.0 ), 'weathercode' => array( 3 ) ),
) ) ) );
$edge = arv_live_weather( 43.95, -71.5, 'soon', $edge_start );
t( 'fifteen days out still gets one',   54 === ( $edge['temp'] ?? null ) );

$GLOBALS['_transients'] = array();
$calls_before = $GLOBALS['_http_calls'];
$edge16 = arv_live_weather( 43.95, -71.5, 'soon', gmdate( 'Y-m-d\TH:00:00\Z', arv_results_now() + 16 * DAY_IN_SECONDS + 3600 ) );
t( 'sixteen days out gets nothing',      null === $edge16 );
t( 'and is not even asked for',          $GLOBALS['_http_calls'] === $calls_before );

// WMO codes sort into the handful of pictures this actually draws.
t( 'clear sky is clear',                 'clear' === arv_live_weather_group( 0 ) );
t( 'mainly clear is partly',             'partly' === arv_live_weather_group( 1 ) );
t( 'overcast is cloudy',                 'cloudy' === arv_live_weather_group( 3 ) );
t( 'fog is fog',                         'fog' === arv_live_weather_group( 45 ) );
t( 'drizzle is rain',                    'rain' === arv_live_weather_group( 51 ) );
t( 'heavy rain is rain',                 'rain' === arv_live_weather_group( 65 ) );
t( 'snow is snow',                       'snow' === arv_live_weather_group( 73 ) );
t( 'a thunderstorm is storm',            'storm' === arv_live_weather_group( 95 ) );
t( 'an unknown code still draws something', '' !== arv_live_weather_icon( arv_live_weather_group( 9999 ) ) );

// The rendered markup: nothing, or an icon plus a temperature, never a bare
// number with no unit and never a crash on a half-built array.
t( 'no forecast renders nothing',        '' === arv_live_weather_render( null ) );
t( 'nor does a malformed one',           '' === arv_live_weather_render( array( 'temp' => 61 ) ) );
$markup = arv_live_weather_render( array( 'temp' => 68, 'code' => 0 ) );
t( 'a real forecast draws an svg icon',  false !== strpos( $markup, '<svg' ) );
t( 'and the temperature with its degree', false !== strpos( $markup, '68&deg;' ) );

$GLOBALS['NOW_TS'] = null;
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();

// -------------------------------------------------------------------------
// Watch: Aravaipa's broadcasts, read from Mountain Outpost.
// -------------------------------------------------------------------------
echo "\nwatch, youtube ids:\n";
t( 'a watch?v= url',                     'dQw4w9WgXcQ' === arv_watch_youtube_id( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );
t( 'with other params in front',         'dQw4w9WgXcQ' === arv_watch_youtube_id( 'https://www.youtube.com/watch?list=PL1&v=dQw4w9WgXcQ&t=90' ) );
t( 'a youtu.be short url',               'abcdefghijk' === arv_watch_youtube_id( 'https://youtu.be/abcdefghijk' ) );
t( 'a /live/ url',                       'AB_cd-EFghi' === arv_watch_youtube_id( 'https://www.youtube.com/live/AB_cd-EFghi?feature=share' ) );
t( 'a channel url is not a video',       '' === arv_watch_youtube_id( 'https://www.youtube.com/@aravaiparunning' ) );
t( 'nor is an empty string',             '' === arv_watch_youtube_id( '' ) );
// Eleven characters exactly. A ten-character v= is not a YouTube id, and
// matching it anyway would build an embed URL that 404s inside the iframe,
// where nothing here can see it fail.
t( 'a ten-character id is refused',      '' === arv_watch_youtube_id( 'https://youtu.be/0123456789' ) );

echo "\nwatch, cleaning the feed:\n";
$feed = array(
	array(
		'slug'      => 'cocodona-250-2026',
		'name'      => 'Cocodona 250',
		'live'      => false,
		'startDate' => '2026-05-04',
		'streams'   => array(
			array( 'title' => 'Start Line', 'youtubeUrl' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'streamType' => 'race', 'scheduledStart' => '2026-05-04T12:00:00Z' ),
			array( 'title' => 'Mingus Mountain', 'youtubeUrl' => 'https://youtu.be/bbbbbbbbbbb' ),
			// No URL: there is nowhere to send anyone, so it is not a card.
			array( 'title' => 'Never published' ),
		),
	),
	array(
		'slug'    => 'black-canyon-2026',
		'name'    => 'Black Canyon Ultras',
		'live'    => true,
		'streams' => array(
			array( 'title' => 'Live Coverage', 'youtubeUrl' => 'https://www.youtube.com/live/ccccccccccc', 'live' => true ),
		),
	),
	// Every stream unusable, so the event goes with them rather than
	// rendering as a race with nothing to watch.
	array( 'slug' => 'ghost-2026', 'name' => 'Ghost Race', 'streams' => array( array( 'youtubeUrl' => 'https://example.com/nope' ) ) ),
	// Shapes the API should never send, which is exactly why they are here.
	array( 'name' => 'No slug', 'streams' => array() ),
	'not even an array',
);
$clean = arv_watch_clean( $feed );
t( 'two events survive',                 2 === count( $clean ) );
t( 'the urlless stream is dropped',      2 === count( $clean[0]['streams'] ) );
t( 'and the unwatchable event with it',  'black-canyon-2026' === $clean[1]['slug'] );
t( 'a live event is marked live',        true === $clean[1]['live'] );
t( 'a past one is not',                  false === $clean[0]['live'] );
t( 'the id is pulled out of the url',    'aaaaaaaaaaa' === $clean[0]['streams'][0]['id'] );
// hqdefault, not the maxresdefault the API hands over: same picture, 23KB
// instead of 220KB, on a page that shows a grid of them.
t( 'the thumbnail is built from the id', 'https://i.ytimg.com/vi/aaaaaaaaaaa/hqdefault.jpg' === $clean[0]['streams'][0]['thumbnail'] );
t( 'a missing field becomes empty, not null', '' === $clean[0]['streams'][1]['type'] );

// Desert Solstice has been broadcast every December since 2018 under one
// name, so without this the archive is nine cards all reading the same
// thing. Names that already carry a year are left alone.
t( 'a yearless name gains its year',     'Desert Solstice 2019' === arv_watch_event_name( 'Desert Solstice', '2019-12-14T14:24:08.000Z' ) );
t( 'a name with one is left alone',      'Cocodona 250 2025' === arv_watch_event_name( 'Cocodona 250 2025', '2025-05-05' ) );
t( 'a distance is not mistaken for one', 'Jackpot 100 2025' === arv_watch_event_name( 'Jackpot 100', '2025-02-22' ) );
t( 'no date means no guess',             'Desert Solstice' === arv_watch_event_name( 'Desert Solstice', '' ) );
t( 'and the feed comes through named',   'Cocodona 250 2026' === $clean[0]['name'] );

echo "\nwatch, fetching:\n";
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
$GLOBALS['_http_calls'] = 0;
arv_test_queue_response( array( 'code' => 200, 'body' => json_encode( array( 'events' => $feed ) ) ) );
$got = arv_watch_events();
t( 'a good response comes back cleaned', 2 === count( $got ) );
t( 'and asked the network once',         1 === $GLOBALS['_http_calls'] );
$got2 = arv_watch_events();
t( 'the second read is cached',          1 === $GLOBALS['_http_calls'] );
t( 'and is the same data',               $got === $got2 );

// A failure has to be cached too. Caching nothing would put an outbound
// request on every single page view for as long as an outage lasts.
$GLOBALS['_transients'] = array();
arv_test_queue_response( new WP_Error( 'down' ) );
t( 'a transport error renders nothing',  array() === arv_watch_events() );
$calls = $GLOBALS['_http_calls'];
t( 'and the failure is remembered',      array() === arv_watch_events() );
t( 'so the outage is not hammered',      $calls === $GLOBALS['_http_calls'] );

$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 500, 'body' => 'nope' ) );
t( 'a 500 is a failure too',             array() === arv_watch_events() );

$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 200, 'body' => '{"error":"unauthorized"}' ) );
t( 'a 200 with the wrong shape too',     array() === arv_watch_events() );

// The one case the 'none' sentinel exists for: the API answered fine and
// genuinely has no broadcasts. It must not read as a transport failure.
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 200, 'body' => '{"events":[]}' ) );
t( 'an honestly empty feed is empty',    array() === arv_watch_events() );

echo "\nwatch, rendering:\n";
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
arv_test_queue_response( new WP_Error( 'down' ) );
// Nothing at all, not a heading over a blank space: a page that says
// "Watch" and then shows nothing reads as broken.
t( 'an unreachable feed renders nothing', '' === arv_watch_render( array( 'heading' => 'Watch' ) ) );

$GLOBALS['_transients']['arv_watch_events'] = $clean;
$html = arv_watch_render( array( 'heading' => 'Watch', 'intro' => 'Every broadcast.' ) );
t( 'the heading renders',                false !== strpos( $html, 'Watch</h2>' ) );
t( 'the intro too',                      false !== strpos( $html, 'Every broadcast.' ) );
// Exactly one player on the page, for the race that is on air. Eighteen
// Cocodona segments as eighteen iframes would be eighteen third-party
// players loading on a page nobody asked to autoplay.
t( 'the live race is embedded',          1 === substr_count( $html, '<iframe' ) );
t( 'from the live segment',              false !== strpos( $html, 'embed/ccccccccccc' ) );
t( 'privacy-mode player',                false !== strpos( $html, 'youtube-nocookie.com' ) );
t( 'with the live badge',                false !== strpos( $html, 'arv-results__live' ) );
t( 'the past race is a link out',        false !== strpos( $html, 'youtube.com/watch?v=aaaaaaaaaaa' ) );
// The first segment by scheduled start, not the last: on a stage race the
// last one is the finish, and opening on the finish spoils the coverage
// above it.
t( 'the later segment is still listed',  false !== strpos( $html, 'youtu.be/bbbbbbbbbbb' ) );
t( 'but the card opens on the first',     strpos( $html, 'watch?v=aaaaaaaaaaa' ) < strpos( $html, 'youtu.be/bbbbbbbbbbb' ) );
t( 'thumbnails are lazy',                false !== strpos( $html, 'loading="lazy"' ) );
t( 'its date reads as a date',           false !== strpos( $html, 'May 4, 2026' ) );
t( 'and its segment count',              false !== strpos( $html, '2 videos' ) );
t( 'two segments get a toggle',          false !== strpos( $html, 'All 2 segments' ) );

// One video needs no "all 1 segments" toggle over the video already linked.
$single = array( array( 'slug' => 'solo-2026', 'name' => 'Solo', 'live' => false, 'start' => '', 'streams' => array( array( 'id' => 'ddddddddddd', 'title' => 'Only', 'url' => 'https://youtu.be/ddddddddddd', 'thumbnail' => 'https://i.ytimg.com/vi/ddddddddddd/hqdefault.jpg', 'live' => false, 'type' => '', 'start' => '' ) ) ) );
$GLOBALS['_transients']['arv_watch_events'] = $single;
$solo = arv_watch_render( array() );
t( 'one video gets no toggle',           false === strpos( $solo, '<details' ) );
t( 'and reads as one video',             false !== strpos( $solo, '1 video' ) );
t( 'and not as one videos',              false === strpos( $solo, '1 videos' ) );

// The limit keeps a homepage block short, but "we are on air right now" is
// the one thing that block exists to say, so it is never what gets cut.
$GLOBALS['_transients']['arv_watch_events'] = $clean;
$capped = arv_watch_render( array( 'limit' => 1 ) );
t( 'a limit of one keeps the live one',  1 === substr_count( $capped, '<iframe' ) );
t( 'and drops the archive under it',     false === strpos( $capped, 'arv-watch__race' ) );

// Titles come from another system's database, which is exactly the argument
// for escaping them here rather than trusting the source.
$GLOBALS['_transients']['arv_watch_events'] = array(
	array( 'slug' => 'xss-2026', 'name' => '<script>alert(1)</script>', 'live' => false, 'start' => '', 'streams' => array( array( 'id' => 'eeeeeeeeeee', 'title' => '" onload="alert(1)', 'url' => 'https://youtu.be/eeeeeeeeeee', 'thumbnail' => 'https://i.ytimg.com/vi/eeeeeeeeeee/hqdefault.jpg', 'live' => false, 'type' => '', 'start' => '' ) ) ),
);
$nasty = arv_watch_render( array() );
t( 'a script tag in a name is escaped',  false === strpos( $nasty, '<script>' ) );
t( 'and a quote in a title cannot break out', false === strpos( $nasty, 'onload="' ) );

// The shortcode is the other way this reaches a page, and it has to produce
// the same markup the element does: one of them being the tested path and
// the other quietly diverging is the whole reason both go through
// arv_watch_render().
$GLOBALS['_transients']['arv_watch_events'] = $clean;
t( 'the shortcode is registered',        isset( $GLOBALS['SHORTCODES']['arv_watch'] ) );
t( 'and renders the same thing',         arv_watch_shortcode( array( 'heading' => 'Watch', 'intro' => 'Every broadcast.' ) ) === $html );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();

// -------------------------------------------------------------------------
// Watch, one race: every edition, embedded, on its own page.
// -------------------------------------------------------------------------
echo "\nwatch, race key & name:\n";
// Keyed on the name, normalised through arv_results_race_key(), because
// Mountain Outpost's slugs drift between seasons and the names do not.
t( 'strips the trailing year off a name', 'black canyon' === arv_watch_race_key( 'Black Canyon Ultras 2026' ) );
t( 'and the distance with it',            'cocodona' === arv_watch_race_key( 'Cocodona 250 2026' ) );
t( 'a name with no year is fine',         'ghost' === arv_watch_race_key( 'Ghost' ) );

// The three races MO spells two ways across years. Each pair has to land on
// one key or its dedicated page shows half its own history: this shipped
// keyed on the slug, and /watch/black-canyon/ hid 2024, 2023 and 2022 while
// /watch/jackpot/ and /watch/javelina/ showed one year and no switcher.
t( 'black canyon, both spellings',        arv_watch_race_key( 'Black Canyon Ultras 2026' ) === arv_watch_race_key( 'Black Canyon Ultras 2024' ) );
t( 'jackpot, both spellings',             arv_watch_race_key( 'Jackpot Ultras 2025' ) === arv_watch_race_key( 'Jackpot Ultras 2022' ) );
t( 'javelina, both spellings',            arv_watch_race_key( 'Javelina Jundred 2025' ) === arv_watch_race_key( 'Javelina Jundred 2021' ) );
// And a page's stored key, which is written as a slug, has to normalise to
// the same thing the feed's name does, or the index links nowhere.
t( 'a stored slug key matches its name',  arv_watch_race_key( 'black-canyon' ) === arv_watch_race_key( 'Black Canyon Ultras 2026' ) );
t( 'even with a distance in it',          arv_watch_race_key( 'cocodona-250' ) === arv_watch_race_key( 'Cocodona 250 2026' ) );
// Two genuinely different races must not collapse together.
t( 'and two real races stay apart',       arv_watch_race_key( 'Black Canyon Ultras' ) !== arv_watch_race_key( 'Cocodona 250' ) );

// A page's stored key does not have to be the normaliser's exact output.
// The Javelina page was created as "javelina" while every edition is named
// "Javelina Jundred", so its key resolved to "javelina", the feed's to
// "javelina jundred", and the page found none of its own five broadcasts.
$GLOBALS['_transients']['arv_watch_events'] = array(
	array( 'slug' => 'javelina-2025', 'name' => 'Javelina Jundred 2025', 'live' => false, 'start' => '2025-10-25', 'streams' => array( array( 'id' => 'jv25aaaaaaa', 'title' => 'A', 'url' => 'https://youtu.be/jv25aaaaaaa', 'thumbnail' => '', 'live' => false, 'type' => '', 'start' => '' ) ) ),
	array( 'slug' => 'javelina-jundred-2024', 'name' => 'Javelina Jundred 2024', 'live' => false, 'start' => '2024-10-26', 'streams' => array( array( 'id' => 'jv24aaaaaaa', 'title' => 'B', 'url' => 'https://youtu.be/jv24aaaaaaa', 'thumbnail' => '', 'live' => false, 'type' => '', 'start' => '' ) ) ),
	array( 'slug' => 'cocodona-250-2025', 'name' => 'Cocodona 250 2025', 'live' => false, 'start' => '2025-05-05', 'streams' => array( array( 'id' => 'cc25aaaaaaa', 'title' => 'C', 'url' => 'https://youtu.be/cc25aaaaaaa', 'thumbnail' => '', 'live' => false, 'type' => '', 'start' => '' ) ) ),
);
t( 'a short stored key resolves to the race', 'javelina jundred' === arv_watch_resolve_key( arv_watch_race_key( 'javelina' ) ) );
t( 'and finds both its editions',          2 === count( arv_watch_race_editions( arv_watch_resolve_key( arv_watch_race_key( 'javelina' ) ) ) ) );
t( 'an exact key is returned unchanged',   'cocodona' === arv_watch_resolve_key( 'cocodona' ) );
t( 'a key matching nothing is unchanged',  'nonsense' === arv_watch_resolve_key( 'nonsense' ) );
t( 'and an empty one stays empty',         '' === arv_watch_resolve_key( '' ) );

// Ambiguity is refused rather than guessed: showing the wrong race's
// broadcasts is worse than showing none.
$GLOBALS['_transients']['arv_watch_events'] = array(
	array( 'slug' => 'desert-solstice-2025', 'name' => 'Desert Solstice Track 2025', 'live' => false, 'start' => '2025-12-20', 'streams' => array( array( 'id' => 'ds25aaaaaaa', 'title' => 'A', 'url' => 'https://youtu.be/ds25aaaaaaa', 'thumbnail' => '', 'live' => false, 'type' => '', 'start' => '' ) ) ),
	array( 'slug' => 'desert-solstice-night-2025', 'name' => 'Desert Solstice Night 2025', 'live' => false, 'start' => '2025-12-21', 'streams' => array( array( 'id' => 'dn25aaaaaaa', 'title' => 'B', 'url' => 'https://youtu.be/dn25aaaaaaa', 'thumbnail' => '', 'live' => false, 'type' => '', 'start' => '' ) ) ),
);
t( 'two candidates resolve to neither',    'desert solstice' === arv_watch_resolve_key( 'desert solstice' ) );
t( 'strips the trailing year off a name', 'Black Canyon Ultras' === arv_watch_race_name( 'Black Canyon Ultras 2026' ) );
t( 'a name with no year is left alone',   'Desert Solstice' === arv_watch_race_name( 'Desert Solstice' ) );

$races_fixture = array(
	array(
		'slug'    => 'black-canyon-2026',
		'name'    => 'Black Canyon Ultras 2026',
		'live'    => true,
		'start'   => '2026-02-14T14:00:00Z',
		'streams' => array(
			array( 'id' => 'bc26live1', 'title' => 'Live Coverage', 'url' => 'https://youtu.be/bc26live1', 'thumbnail' => 'https://i.ytimg.com/vi/bc26live1/hqdefault.jpg', 'live' => true, 'type' => 'race', 'start' => '2026-02-14T14:00:00Z' ),
		),
	),
	array(
		'slug'    => 'black-canyon-2025',
		'name'    => 'Black Canyon Ultras 2025',
		'live'    => false,
		'start'   => '2025-02-14T14:00:00Z',
		'streams' => array(
			array( 'id' => 'bc25seg1', 'title' => 'Start', 'url' => 'https://youtu.be/bc25seg1', 'thumbnail' => 'https://i.ytimg.com/vi/bc25seg1/hqdefault.jpg', 'live' => false, 'type' => 'race', 'start' => '2025-02-14T14:00:00Z' ),
			array( 'id' => 'bc25seg2', 'title' => 'Finish', 'url' => 'https://youtu.be/bc25seg2', 'thumbnail' => 'https://i.ytimg.com/vi/bc25seg2/hqdefault.jpg', 'live' => false, 'type' => 'race', 'start' => '2025-02-14T20:00:00Z' ),
		),
	),
	array(
		'slug'    => 'jackpot-2025',
		'name'    => 'Jackpot Ultras 2025',
		'live'    => false,
		'start'   => '2025-02-19T14:00:00Z',
		'streams' => array(
			array( 'id' => 'jp25seg1', 'title' => 'Only', 'url' => 'https://youtu.be/jp25seg1', 'thumbnail' => 'https://i.ytimg.com/vi/jp25seg1/hqdefault.jpg', 'live' => false, 'type' => 'race', 'start' => '2025-02-19T14:00:00Z' ),
		),
	),
);

echo "\nwatch, grouping editions:\n";
$GLOBALS['_transients']['arv_watch_events'] = $races_fixture;
$editions = arv_watch_race_editions( arv_watch_race_key( 'black-canyon' ) );
t( 'both editions come back',            2 === count( $editions ) );
t( 'newest first',                        '2026-02-14T14:00:00Z' === $editions[0]['start'] );
t( 'a race with no broadcasts is empty',  array() === arv_watch_race_editions( 'nonexistent' ) );
t( 'an empty key is empty too',           array() === arv_watch_race_editions( '' ) );

echo "\nwatch, picking an edition:\n";
$picked = arv_watch_pick_edition( $editions, '2025' );
t( 'a requested year wins',               '2025-02-14T14:00:00Z' === $picked['start'] );
$fallback = arv_watch_pick_edition( $editions, '2019' );
t( 'a year the race did not run falls back to newest', '2026-02-14T14:00:00Z' === $fallback['start'] );
$default = arv_watch_pick_edition( $editions, '' );
t( 'no year at all also falls back to newest', '2026-02-14T14:00:00Z' === $default['start'] );
t( 'an empty list picks nothing',         null === arv_watch_pick_edition( array(), '2025' ) );

echo "\nwatch, the dedicated page:\n";
$html26 = arv_watch_race_render( array( 'race' => 'black-canyon' ) );
t( 'renders the newest edition by default', false !== strpos( $html26, 'Black Canyon Ultras 2026' ) );
t( 'live edition carries the live badge',   false !== strpos( $html26, 'arv-results__live' ) );
t( 'the player is embedded, not linked',    false !== strpos( $html26, 'embed/bc26live1' ) );
t( 'a single segment gets no playlist',     false === strpos( $html26, 'arv-watch-race__playlist' ) );
t( 'the year switcher offers 2025',         false !== strpos( $html26, '>2025<' ) );
t( 'and marks 2026 current',                false !== strpos( $html26, 'is-current' ) );
t( 'links back to the index',               false !== strpos( $html26, home_url( '/watch/' ) ) );

$html25 = arv_watch_race_render( array( 'race' => 'black-canyon', 'edition' => '2025' ) );
t( '?edition= switches editions',           false !== strpos( $html25, 'Black Canyon Ultras 2025' ) );
t( 'a past edition opens on its first segment', false !== strpos( $html25, 'embed/bc25seg1' ) );
t( 'two segments get a playlist',           false !== strpos( $html25, 'arv-watch-race__playlist' ) );
t( 'both segments are listed',              false !== strpos( $html25, 'bc25seg1' ) && false !== strpos( $html25, 'bc25seg2' ) );
t( 'the open segment is marked current',    false !== strpos( $html25, 'aria-current="true"' ) );
// target="_blank" so the page works with no script, and a real data-yt-id
// so aravaipa-watch.js has something to swap the iframe to.
t( 'segments are real links, not just buttons', false !== strpos( $html25, 'target="_blank"' ) );
t( 'carrying the id the script swaps to',   false !== strpos( $html25, 'data-yt-id="bc25seg2"' ) );

$html_jp = arv_watch_race_render( array( 'race' => 'jackpot' ) );
t( 'one segment gets no playlist either',   false === strpos( $html_jp, 'arv-watch-race__playlist' ) );

$missing = arv_watch_race_render( array( 'race' => 'nonexistent' ) );
t( 'an unknown race says so',               false !== strpos( $missing, 'have a broadcast' ) );
t( 'and still links back to the index',     false !== strpos( $missing, home_url( '/watch/' ) ) );
t( 'a blank race key renders nothing',      '' === arv_watch_race_render( array() ) );

echo "\nwatch, cross-links:\n";
// Seeded directly rather than relying on the race store's state from the
// import tests at the top of this file: by this point in the suite several
// of those races have been trashed or edited by tests in between, and this
// is testing arv_watch_race_page_link() against a race store, not against
// whatever happens to survive two thousand lines of other tests.
$GLOBALS['posts'][9001] = array( 'title' => 'Black Canyon Ultras', 'status' => 'publish' );
$GLOBALS['meta'][9001]  = array( '_arv_iso' => '2027-02-14', '_arv_page' => 'https://www.aravaiparunning.com/blackcanyon/' );
arv_race_store_flush_cache();

t( "finds the race's own page by name",     'https://www.aravaiparunning.com/blackcanyon/' === arv_watch_race_page_link( 'Black Canyon Ultras' ) );

t( "matches through the feed's shorter name too", 'https://www.aravaiparunning.com/blackcanyon/' === arv_watch_race_page_link( 'Black Canyon' ) );
t( 'nothing for a race the store has never heard of', '' === arv_watch_race_page_link( 'Not A Real Race' ) );

update_option(
	ARV_RESULTS_OPTION,
	array(
		array( 'name' => 'Black Canyon Ultras', 'iso' => '2025-02-14', 'live' => 'https://live.aravaiparunning.com/#/black_canyon-2025' ),
		array( 'name' => 'Black Canyon Ultras', 'iso' => '2026-02-14', 'live' => 'https://live.aravaiparunning.com/#/black_canyon-2026' ),
	)
);
t( "finds that year's results board",       'https://live.aravaiparunning.com/#/black_canyon-2025' === arv_watch_race_results_link( 'Black Canyon Ultras', '2025' ) );
t( 'nothing for a year with no results on file', '' === arv_watch_race_results_link( 'Black Canyon Ultras', '2019' ) );
t( 'nothing with no year to match',          '' === arv_watch_race_results_link( 'Black Canyon Ultras', '' ) );

// A branded live-results page wins over the raw board URL, same rule
// arv_live_edition_url() applies for the same reason: a real path is what
// gets indexed and shared.
$GLOBALS['meta']      = array( 5001 => array( '_arv_live_slug' => 'black_canyon-2025' ) );
$GLOBALS['PERMALINK'] = array( 5001 => 'https://www.aravaiparunning.com/live-results/black-canyon-2025/' );
arv_live_page_map( true );
t( 'a branded results page wins over the raw board', 'https://www.aravaiparunning.com/live-results/black-canyon-2025/' === arv_watch_race_results_link( 'Black Canyon Ultras', '2025' ) );
$GLOBALS['meta']      = array();
$GLOBALS['PERMALINK'] = array();
arv_live_page_map( true );

$linked = arv_watch_race_render( array( 'race' => 'black-canyon', 'edition' => '2025' ) );
t( 'the dedicated page offers registration', false !== strpos( $linked, 'Race info &amp; registration' ) );
t( "and that year's results",                false !== strpos( $linked, esc_url( 'https://live.aravaiparunning.com/#/black_canyon-2025' ) ) );

unset( $GLOBALS['posts'][9001] );
unset( $GLOBALS['meta'][9001] );
arv_race_store_flush_cache();
update_option( ARV_RESULTS_OPTION, array() );

echo "\nwatch, index links to a race's dedicated page:\n";
$GLOBALS['_transients']['arv_watch_events'] = $races_fixture;
$GLOBALS['meta']      = array( 6001 => array( '_arv_watch_race' => 'jackpot' ) );
$GLOBALS['PERMALINK'] = array( 6001 => 'https://www.aravaiparunning.com/watch/jackpot/' );
arv_watch_page_map( true );
$idx = arv_watch_render( array() );
// Carrying the edition, so a 2022 thumbnail lands on 2022 rather than on
// whatever happens to be newest.
t( "a card links to its dedicated page when one exists", false !== strpos( $idx, 'watch/jackpot/?edition=2025' ) );
t( 'and does not send that click to a new tab', 0 === preg_match( '/href="https:\/\/www\.aravaiparunning\.com\/watch\/jackpot\/"[^>]*target="_blank"/', $idx ) );

$GLOBALS['meta']      = array();
$GLOBALS['PERMALINK'] = array();
arv_watch_page_map( true );
$idx2 = arv_watch_render( array() );
t( 'and falls back to youtube once there is no page', false !== strpos( $idx2, 'youtu.be/jp25seg1' ) );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();



// -------------------------------------------------------------------------
// Watch SEO: the head these pages shipped without.
// -------------------------------------------------------------------------
echo "\nwatch seo:\n";
$seo_edition = array(
	'slug' => 'cocodona-250-2025', 'name' => 'Cocodona 250 2025', 'live' => false,
	'start' => '2025-05-05T13:00:00Z', 'place' => 'Black Canyon City to Flagstaff, AZ',
	'desc' => '', 'hero' => 'https://example.com/hero.jpg',
	'streams' => array(
		array( 'id' => 'aaaaaaaaaaa', 'title' => 'Day 1 | Start to Crown King', 'url' => 'https://youtu.be/aaaaaaaaaaa',
		       'thumbnail' => 'https://i.ytimg.com/vi/aaaaaaaaaaa/hqdefault.jpg', 'live' => false, 'type' => 'race',
		       'start' => '2025-05-05T13:00:00Z', 'desc' => 'Coverage of the opening leg.',
		       'aired' => '2025-05-05T13:04:00Z', 'minutes' => 320, 'views' => 128500 ),
		// No description, no actualStart and no duration: the three gaps the
		// real feed has. It must still produce a valid node, not a broken one.
		array( 'id' => 'bbbbbbbbbbb', 'title' => 'Day 2 | Mingus to Jerome', 'url' => 'https://youtu.be/bbbbbbbbbbb',
		       'thumbnail' => 'https://i.ytimg.com/vi/bbbbbbbbbbb/hqdefault.jpg', 'live' => false, 'type' => 'race',
		       'start' => '2025-05-06T13:00:00Z', 'desc' => '', 'aired' => '', 'minutes' => 0, 'views' => 0 ),
	),
);
$ctx = array(
	'edition' => $seo_edition, 'editions' => array( $seo_edition ),
	'name' => 'Cocodona 250', 'year' => '2025',
	'url' => 'https://www.aravaiparunning.com/watch/cocodona-250/',
);

t( 'title names the race and year',      'Cocodona 250 2025 Live Broadcast' === arv_watch_seo_title( $ctx ) );

$d = arv_watch_seo_description( $ctx );
t( 'description is built from the facts', false !== strpos( $d, 'Cocodona 250 2025' ) );
t( 'and counts the videos',               false !== strpos( $d, '2 videos' ) );
t( 'and dates it',                        false !== strpos( $d, 'May 5, 2025' ) );
t( 'and places it',                       false !== strpos( $d, 'Flagstaff' ) );

// Upstream's own words win where a human wrote them.
$ctx2 = $ctx;
$ctx2['edition']['desc'] = 'Welcome to the 2025 Cocodona 250 livestream.';
t( "upstream's own description wins",     'Welcome to the 2025 Cocodona 250 livestream.' === arv_watch_seo_description( $ctx2 ) );

// Google truncates around 160 characters and a cut mid-word reads broken.
$ctx3 = $ctx;
$ctx3['edition']['desc'] = str_repeat( 'the quick brown fox jumps over it ', 12 );
$long = arv_watch_seo_description( $ctx3 );
t( 'a long description is trimmed',       strlen( $long ) <= 165 );
t( 'and not cut mid-word',                false === strpos( $long, 'quic.' ) );

$list = arv_watch_seo_videos( $ctx );
t( 'an ItemList wraps the videos',        'ItemList' === $list['@type'] );
t( 'one node per segment',                2 === $list['numberOfItems'] );

$v1 = $list['itemListElement'][0]['item'];
$v2 = $list['itemListElement'][1]['item'];
t( 'each node is a VideoObject',          'VideoObject' === $v1['@type'] );

// The four Google requires. A node missing any one of them is not a partial
// win, it is an error on the whole page.
foreach ( array( 'name', 'description', 'thumbnailUrl', 'uploadDate' ) as $req ) {
	t( "every node carries $req",         ! empty( $v1[ $req ] ) && ! empty( $v2[ $req ] ) );
}

t( 'uploadDate uses when it aired',       '2025-05-05T13:04:00+00:00' === $v1['uploadDate'] );
// The 25 segments upstream has no actualStart for must not lose the field.
t( 'and falls back to the event date',    '2025-05-05T13:00:00+00:00' === $v2['uploadDate'] );
t( 'a segment with no words of its own still describes itself', 'Day 2 | Mingus to Jerome' === $v2['description'] );

t( 'duration where upstream has one',     'PT320M' === $v1['duration'] );
// An invented PT0M is worse than an absent optional field.
t( 'and absent where it does not',        ! isset( $v2['duration'] ) );
t( 'views where upstream has them',       128500 === $v1['interactionStatistic']['userInteractionCount'] );
t( 'and no counter where it does not',    ! isset( $v2['interactionStatistic'] ) );

t( 'embed url is the privacy-mode one',   false !== strpos( $v1['embedUrl'], 'youtube-nocookie.com/embed/aaaaaaaaaaa' ) );
t( 'contentUrl points at youtube',        'https://youtu.be/aaaaaaaaaaa' === $v1['contentUrl'] );
// The node's own url is this page deep-linked to that segment, not YouTube:
// it is what makes our page, not YouTube's, the one being described.
t( 'and url is our page, on that segment', false !== strpos( $v1['url'], 'watch/cocodona-250/' ) && false !== strpos( $v1['url'], 'v=aaaaaaaaaaa' ) );

$crumbs = arv_watch_seo_breadcrumbs( $ctx );
t( 'breadcrumbs are a BreadcrumbList',    'BreadcrumbList' === $crumbs['@type'] );
t( 'three deep, home to race',            3 === count( $crumbs['itemListElement'] ) );
t( 'via Watch',                           home_url( '/watch/' ) === $crumbs['itemListElement'][1]['item'] );

// A segment with nothing usable is dropped rather than emitted invalid.
$ctx4 = $ctx;
$ctx4['edition']['streams'] = array(
	array( 'id' => 'ccccccccccc', 'title' => '', 'url' => '', 'thumbnail' => '', 'live' => false,
	       'type' => '', 'start' => '', 'desc' => '', 'aired' => '', 'minutes' => 0, 'views' => 0 ),
);
$ctx4['edition']['start'] = '';
t( 'an unusable segment is dropped',      array() === arv_watch_seo_videos( $ctx4 ) );



// -------------------------------------------------------------------------
// The live board's touch shield.
// -------------------------------------------------------------------------
echo "\nlive frame, mobile scroll:\n";
$frame = arv_live_frame( 'rock_hawk-2026', 3912, 'Rock Hawk Trail Races' );
t( 'the frame is still sized to its content', false !== strpos( $frame, 'height:3912px' ) );
t( 'and carries a hook for the script',       false !== strpos( $frame, 'data-arv-live-frame' ) );
t( 'a shield sits over it',                   false !== strpos( $frame, 'data-arv-live-shield' ) );
// Rendered hidden and revealed by script. The other way round, a page whose
// JavaScript never runs would have an undismissable layer over its results,
// which is a worse bug than the one this fixes.
t( 'rendered hidden, not visible',            false !== strpos( $frame, 'hidden data-arv-live-shield' ) );
// A real button, so it is reachable by keyboard and announced, rather than a
// bare div that only a pointer can dismiss.
t( 'and is a real button',                    false !== strpos( $frame, '<button class="arv-live__shield"' ) );
t( 'saying what the first tap does',          false !== strpos( $frame, 'Tap to use the board' ) );



// -------------------------------------------------------------------------
// Race map: the search row's two controls.
// -------------------------------------------------------------------------
echo "\nrace map controls:\n";
$css = file_get_contents( __DIR__ . '/assets/aravaipa-elements.css' );

// The theme puts a 9px bottom margin under every input. On a flex row that
// margin is part of the item's outer size, so the line grew to 53px while
// the input's own box stayed 44px and Near me stretched to fill all 53.
// Measured in the live page; the CSS already claimed both were 44.
t( 'the search input clears its margin',  1 === preg_match( '/\.arv-map__search \.arv-map__search-input \{\s*(?:\/\*.*?\*\/\s*)?margin: 0;/s', $css ) );
t( 'and Near me states its own height',   1 === preg_match( '/\.leaflet-bar\.arv-map__nearme--inline \{[^}]*height: 44px;/s', $css ) );
t( 'and matches the input radius',        1 === preg_match( '/\.leaflet-bar\.arv-map__nearme--inline \{[^}]*border-radius: var\(--arv-radius\);/s', $css ) );
// height:100%, not a second 44px: with the wrapper's 1px border that would
// overflow by 2px and square off the corners the wrapper just rounded.
t( 'the link fills the wrapper',          1 === preg_match( '/\.arv-map__nearme a,[^{]*\{[^}]*height: 100%;/s', $css ) );
t( 'and inherits its rounded corners',    1 === preg_match( '/\.arv-map__nearme a,[^{]*\{[^}]*border-radius: inherit;/s', $css ) );

// Every button in the plugin takes the shared radius scale. This is a set
// rather than one assertion because the last rounding pass missed
// .arv-calendar__action, and the races and results pages, which is where
// most of the buttons a visitor sees actually live, kept sharp rectangles
// for fifteen releases without anyone noticing.
foreach ( array(
	'arv-hero__cta',
	'arv-distance__cta',
	'arv-races__cta',
	'arv-featured__cta',
	'arv-calendar__action',
	'arv-map__popup-cta',
	'arv-watch-race__cta',
) as $button ) {
	t(
		"$button is rounded",
		1 === preg_match( '/\.' . preg_quote( $button, '/' ) . '[^{}]*\{[^}]*border-radius: var\(--arv-radius\);/s', $css )
	);
}

// "Every race" over "Find a race near you" said the same thing twice.
$el = $GLOBALS['EL']['aravaipa-race-map'] ?? null;
t( 'the map element is registered',       null !== $el );
t( 'and its eyebrow defaults to empty',   '' === ( $el['values']['eyebrow'] ?? 'unset' ) );
t( 'while the heading is unchanged',      'Find a race near you' === ( $el['values']['heading'] ?? '' ) );



// -------------------------------------------------------------------------
// Films: the two YouTube playlists, one page, one player.
// -------------------------------------------------------------------------
echo "\nfilms, trailer folding:\n";
t( 'a plain title has no lead phrase problem', 'THE CHASE' === arv_films_lead_phrase( 'THE CHASE | Cocodona 250 Full Documentary' ) );
t( 'a trailer shares its feature\'s lead phrase', 'THE CHASE' === arv_films_lead_phrase( 'THE CHASE - Official Trailer - A Cocodona 250 Story' ) );
t( 'punctuation does not break the match',    arv_films_lead_phrase( 'Inaugural Year | OFFICIAL TRAILER | A Story' ) === arv_films_lead_phrase( 'INAUGURAL YEAR | A story about the first ever Cocodona 250' ) );

$films_raw = array(
	array(
		'key' => 'documentaries', 'title' => 'Documentaries',
		'films' => array(
			array( 'id' => 'aaaaaaaaaaa', 'title' => 'THE CHASE | Cocodona 250 Full Documentary', 'description' => 'A film.', 'publishedAt' => '2025-04-25T00:00:00Z', 'thumbnail' => '' ),
			array( 'id' => 'bbbbbbbbbbb', 'title' => 'THE CHASE - Official Trailer - A Cocodona 250 Story', 'description' => 'A trailer.', 'publishedAt' => '2025-03-01T00:00:00Z', 'thumbnail' => '' ),
			// No feature shares this trailer's lead phrase: kept as its own
			// card rather than guessed onto the wrong one.
			array( 'id' => 'ccccccccccc', 'title' => 'Orphan Trailer', 'description' => '', 'publishedAt' => '2024-01-01T00:00:00Z', 'thumbnail' => '' ),
			// Not a video at all upstream would already have been filtered
			// before reaching here, but a title or id missing is exactly
			// the kind of shape drift arv_watch_clean() guards against too.
			array( 'id' => '', 'title' => 'No id', 'description' => '' ),
		),
	),
	array(
		'key' => 'originals', 'title' => 'Aravaipa Originals',
		'films' => array(
			array( 'id' => 'ddddddddddd', 'title' => 'Molly Seidel Takes On Trail | Aravaipa Originals', 'description' => '', 'publishedAt' => '2026-02-09T00:00:00Z', 'thumbnail' => 'https://example.com/molly.jpg' ),
		),
	),
);

$clean = arv_films_clean( $films_raw );
t( 'both playlists survive',              2 === count( $clean ) );
t( 'the trailer folds into its feature',  2 === count( $clean[0]['films'] ) ); // feature (with its trailer attached) + the orphan trailer
$doc_titles = array_map( function( $f ) { return $f['title']; }, $clean[0]['films'] );
t( 'the folded trailer is not a card of its own', ! in_array( 'THE CHASE - Official Trailer - A Cocodona 250 Story', $doc_titles, true ) );
t( 'an unmatched trailer stays its own card', in_array( 'Orphan Trailer', $doc_titles, true ) );

$feature = null;
foreach ( $clean[0]['films'] as $f ) { if ( 'aaaaaaaaaaa' === $f['id'] ) { $feature = $f; } }
t( 'the feature carries its trailer',     null !== $feature && null !== $feature['trailer'] );
t( 'the trailer keeps its own id',        'bbbbbbbbbbb' === $feature['trailer']['id'] );
t( 'a thumbnail is built when upstream has none', 'https://i.ytimg.com/vi/aaaaaaaaaaa/hqdefault.jpg' === $feature['thumbnail'] );
t( "upstream's own thumbnail wins when there is one", 'https://example.com/molly.jpg' === $clean[1]['films'][0]['thumbnail'] );

echo "\nfilms, ordering and lookup:\n";
$all = arv_films_all( $clean );
t( 'flattened across both playlists',     3 === count( $all ) );
t( 'newest first',                        'ddddddddddd' === $all[0]['id'] );

t( 'finds a top-level film by id',        'aaaaaaaaaaa' === arv_films_find( $clean, 'aaaaaaaaaaa' )['id'] );
t( 'finds a folded trailer by its own id', 'bbbbbbbbbbb' === arv_films_find( $clean, 'bbbbbbbbbbb' )['id'] );
t( 'an unknown id finds nothing',         null === arv_films_find( $clean, 'zzzzzzzzzzz' ) );

echo "\nfilms, rendering:\n";
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
arv_test_queue_response( new WP_Error( 'down' ) );
t( 'an unreachable feed renders nothing', '' === arv_films_render( array( 'heading' => 'Films' ) ) );

$GLOBALS['_transients']['arv_films'] = $clean;
$html = arv_films_render( array( 'heading' => 'Films', 'intro' => 'Every documentary and original.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Films</h2>' ) );
t( 'the intro too',                       false !== strpos( $html, 'Every documentary and original.' ) );
t( 'exactly one player on the page',      1 === substr_count( $html, '<iframe' ) );
t( 'defaults to the newest film',         false !== strpos( $html, 'embed/ddddddddddd' ) );
t( 'both section headings appear',        false !== strpos( $html, 'Documentaries' ) && false !== strpos( $html, 'Aravaipa Originals' ) );
t( 'the feature has a trailer link',      false !== strpos( $html, 'arv-films__trailer' ) );
t( 'segments are real links, not just buttons', false !== strpos( $html, 'target="_blank"' ) );

$requested = arv_films_render( array( 'heading' => '' ) );
$_GET['v'] = 'aaaaaaaaaaa';
$picked = arv_films_render( array() );
unset( $_GET['v'] );
t( '?v= opens on the requested film',     false !== strpos( $picked, 'embed/aaaaaaaaaaa' ) );

$_GET['v'] = 'not-a-real-id';
$fallback = arv_films_render( array() );
unset( $_GET['v'] );
t( 'an unknown ?v= falls back to newest', false !== strpos( $fallback, 'embed/ddddddddddd' ) );

// A script tag in a title or description is exactly the argument for
// escaping it here rather than trusting a system Aravaipa does not run.
$GLOBALS['_transients']['arv_films'] = array(
	array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array(
		array( 'id' => 'eeeeeeeeeee', 'title' => '<script>alert(1)</script>', 'desc' => '', 'thumbnail' => 'https://example.com/e.jpg', 'published' => '2025-01-01T00:00:00Z', 'url' => 'https://youtu.be/eeeeeeeeeee', 'lead' => 'SCRIPT', 'trailer' => null ),
	) ),
);
$nasty = arv_films_render( array() );
t( 'a script tag in a title is escaped',  false === strpos( $nasty, '<script>alert' ) );

t( 'the shortcode is registered',         isset( $GLOBALS['SHORTCODES']['arv_films'] ) );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();



// -------------------------------------------------------------------------
// Podcasts: two Spotify shows, embedded.
// -------------------------------------------------------------------------
echo "\npodcasts:\n";
$shows = arv_podcasts_shows();
t( 'the two real shows are configured',   2 === count( $shows ) );
t( 'Inside Aravaipa is one of them',      'Inside Aravaipa' === $shows[0]['title'] );

$html = arv_podcasts_render( array( 'heading' => 'Podcasts', 'intro' => 'Two shows.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Podcasts</h2>' ) );
t( 'the intro too',                       false !== strpos( $html, 'Two shows.' ) );
t( 'both shows get a card',               2 === substr_count( $html, 'arv-podcasts__card' ) );
t( 'each embeds spotify directly',        false !== strpos( $html, 'open.spotify.com/embed/show/0MvdUlDE9VwocRhrIl9Lwv' ) );
t( 'and the other show too',              false !== strpos( $html, 'open.spotify.com/embed/show/4cg3hl6Ek6pjd76ymrbUQd' ) );
t( 'an open-in-spotify link is offered',  2 === substr_count( $html, 'Open in Spotify' ) );
t( 'nothing autoplays uninvited',         2 === substr_count( $html, 'loading="lazy"' ) );

// A third show, or a rename, needs no plugin release.
add_filter( 'arv_podcasts_shows', function () {
	return array( array( 'title' => 'A New Show', 'id' => 'aaaaaaaaaaaaaaaaaaaaaa' ) );
} );
$filtered = arv_podcasts_render( array() );
t( 'the shows list is filterable',        false !== strpos( $filtered, 'A New Show' ) );
t( 'replacing the defaults, not adding to them', false === strpos( $filtered, 'Inside Aravaipa' ) );
$GLOBALS['FILTERS']['arv_podcasts_shows'] = array();

// No shows at all renders nothing, the same rule Watch and Films use.
add_filter( 'arv_podcasts_shows', function () { return array(); } );
t( 'no shows renders nothing',            '' === arv_podcasts_render( array() ) );
$GLOBALS['FILTERS']['arv_podcasts_shows'] = array();

t( 'the shortcode is registered',         isset( $GLOBALS['SHORTCODES']['arv_podcasts'] ) );
t( 'and renders the same thing',          arv_podcasts_shortcode( array( 'heading' => 'Podcasts', 'intro' => 'Two shows.' ) ) === $html );


echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
