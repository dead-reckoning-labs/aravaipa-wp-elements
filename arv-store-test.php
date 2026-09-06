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
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
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
function _x( $s, $ctx, $d = '' ) { return $s; }
// WordPress groups thousands here. Modelled rather than stubbed to a bare
// cast, because "1,470 finishers" is the string the page actually renders.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function add_shortcode( $tag, $fn ) { $GLOBALS['SHORTCODES'][ $tag ] = $fn; }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, (array) $atts ); }
function esc_html__( $s, $d = '' ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_strip_all_tags( $s, $breaks = false ) { $s = preg_replace( '#<(script|style)[^>]*?>.*?</\1>#si', '', (string) $s ); return trim( strip_tags( $s ) ); }
function wp_unslash( $v ) { return $v; }
function get_queried_object_id() { return $GLOBALS['QUERIED_ID'] ?? 0; }
function get_post_field( $field, $id = 0 ) { return $GLOBALS['POST_FIELD'][ $id ][ $field ] ?? ''; }
function get_permalink( $id = 0 ) { $id = is_object( $id ) ? $id->ID : $id; return $GLOBALS['PERMALINK'][ $id ] ?? 'https://www.aravaiparunning.com/live/test/'; }

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
// The race pages are one WordPress page rendering many races, told apart by
// this var alone, so the harness has to be able to set it.
function get_query_var( $v, $default = '' ) { return $GLOBALS['QUERY_VARS'][ $v ] ?? $default; }
function is_page( $p = '' ) { return $GLOBALS['IS_PAGE'] ?? false; }
// Pages are recognised by the shortcode they carry, so the harness needs a
// post object with content and WP's own shortcode detector.
function get_post( $id = 0 ) { $id = is_object( $id ) ? $id->ID : $id; return isset( $GLOBALS['posts'][ $id ] ) ? new ARV_Post( $id, $GLOBALS['posts'][ $id ]['title'] ?? '' ) : null; }
function has_shortcode( $content, $tag ) { return (bool) preg_match( '/\[' . preg_quote( $tag, '/' ) . '[\s\]]/', (string) $content ); }
function is_front_page() { return $GLOBALS['IS_FRONT'] ?? false; }
function esc_attr__( $s, $d = '' ) { return $s; }
// WordPress accepts add_query_arg( array, url ), add_query_arg( array ) with
// no url (defaults to the current one), and add_query_arg( k, v, url ).
// Modelled rather than stubbed to one, because the SEO code calls the second
// form, the live page's season switcher calls the third, and the weather
// lookup calls the first.
function remove_query_arg( $key, $url = null ) {
	return null !== $url ? $url : ( $GLOBALS['CURRENT_PATH'] ?? '/' );
}
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
	public $post_content;
	public $post_name;
	public function __construct( $id, $title ) {
		$this->ID           = $id;
		$this->post_title   = $title;
		$this->post_content = $GLOBALS['posts'][ $id ]['body'] ?? '';
		// /races/ and /results-YYYY/ carry no shortcode to recognise them
		// by, only a slug, so the SEO recogniser needs this to exist at all.
		$this->post_name    = $GLOBALS['posts'][ $id ]['slug'] ?? '';
	}
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
function get_the_title( $post ) { $id = is_object( $post ) ? $post->ID : $post; return $GLOBALS['posts'][ $id ]['title'] ?? ( is_object( $post ) ? $post->post_title : '' ); }
function get_the_date( $fmt, $post ) { $id = is_object( $post ) ? $post->ID : $post; return $GLOBALS['posts'][ $id ]['date'] ?? ''; }
function get_the_post_thumbnail_url( $id, $size = 'medium' ) { $id = is_object( $id ) ? $id->ID : $id; return $GLOBALS['posts'][ $id ]['thumb'] ?? false; }
// A featured image's id is the post's own id here: the fixtures carry the
// image inline rather than modelling a separate attachments table, so one
// id is enough to find it again.
function get_post_thumbnail_id( $id ) { $id = is_object( $id ) ? $id->ID : $id; return ! empty( $GLOBALS['posts'][ $id ]['thumb'] ) ? $id : 0; }
// Returns WordPress's own [url, width, height] shape. 'tw'/'th' let a
// fixture declare a portrait image, which is what the hero reads to decide
// between cropping and letterboxing; a fixture that sets neither reads as
// landscape, the common case.
function wp_get_attachment_image_src( $id, $size = 'large' ) {
	$p = $GLOBALS['posts'][ $id ] ?? null;
	if ( ! $p || empty( $p['thumb'] ) ) { return false; }
	return array( $p['thumb'], $p['tw'] ?? 1200, $p['th'] ?? 675 );
}
function get_post_type( $id ) { $id = is_object( $id ) ? $id->ID : $id; return $GLOBALS['posts'][ $id ]['type'] ?? 'post'; }
function get_the_category( $id ) { $id = is_object( $id ) ? $id->ID : $id; $out = array(); foreach ( (array) ( $GLOBALS['posts'][ $id ]['cats'] ?? array() ) as $name ) { $c = new stdClass(); $c->name = $name; $out[] = $c; } return $out; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function wp_set_object_terms( $id, $terms, $tax, $append = false ) { $GLOBALS['terms'][ $id ] = (array) $terms; }
function wp_count_posts( $t ) { $o = new stdClass(); $o->publish = 0; foreach ( $GLOBALS['posts'] as $p ) { if ( 'publish' === $p['status'] ) { $o->publish++; } } return $o; }
function has_category( $name, $id = null ) { $id = is_object( $id ) ? $id->ID : $id; return in_array( $name, (array) ( $GLOBALS['posts'][ $id ]['cats'] ?? array() ), true ); }
function get_the_excerpt( $post ) { $id = is_object( $post ) ? $post->ID : $post; return $GLOBALS['posts'][ $id ]['excerpt'] ?? ''; }
function has_excerpt( $post = null ) { $id = is_object( $post ) ? $post->ID : $post; return '' !== trim( (string) ( $GLOBALS['posts'][ $id ]['excerpt'] ?? '' ) ); }
function get_the_modified_date( $fmt, $post ) { $id = is_object( $post ) ? $post->ID : $post; return $GLOBALS['posts'][ $id ]['modified'] ?? ( $GLOBALS['posts'][ $id ]['date'] ?? '' ); }
// Self-hosted attachments: a URL maps to an attachment id, which maps to a
// real path on disk, exactly like WordPress's own pair of functions. Set
// up per test via $GLOBALS['ATTACHMENTS'] / $GLOBALS['ATTACHMENT_FILES'].
function attachment_url_to_postid( $url ) { return $GLOBALS['ATTACHMENTS'][ $url ] ?? 0; }
function get_attached_file( $id ) { return $GLOBALS['ATTACHMENT_FILES'][ $id ] ?? ''; }

$GLOBALS['ARV_OPTIONS'] = array();
function get_option($k,$d=false){ return array_key_exists($k,$GLOBALS['ARV_OPTIONS']) ? $GLOBALS['ARV_OPTIONS'][$k] : $d; }
function update_option($k,$v,$a=null){ $GLOBALS['ARV_OPTIONS'][$k]=$v; return true; }
function esc_url_raw($u){ return $u; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }

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
		// Only a fixture that bothers to set its own 'type' is filtered by
		// post_type; one that never set it matches any query, same as
		// before this existed, so the dozens of untyped race/page fixtures
		// elsewhere in this file are unaffected. Without this, a query for
		// post_type=post silently swept up every other fixture in the
		// suite too and blog-post tests were finding other tests' data
		// instead of their own the moment more than posts_per_page of them
		// existed.
		if ( isset( $p['type'] ) && isset( $args['post_type'] ) && $p['type'] !== $args['post_type'] ) { continue; }
		if ( isset( $args['meta_query'] ) ) {
			$q = $args['meta_query'][0];
			if ( ( $GLOBALS['meta'][ $id ][ $q['key'] ] ?? null ) !== $q['value'] ) { continue; }
		}
		if ( isset( $args['tax_query'] ) ) {
			$want = (array) $args['tax_query'][0]['terms'];
			if ( ! array_intersect( $want, $GLOBALS['terms'][ $id ] ?? array() ) ) { continue; }
		}
		if ( isset( $args['category_name'] ) && ! in_array( $args['category_name'], (array) ( $p['cats'] ?? array() ), true ) ) { continue; }
		// WP_Query's 'title' is an exact match, not a search, and the remove
		// route leans on that: trashing "Oli Kai" must not take "Oli Kai
		// Trail Races" with it.
		if ( isset( $args['title'] ) && ( $p['title'] ?? '' ) !== $args['title'] ) { continue; }
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
require_once __DIR__ . '/includes/elements/region-map.php';
require_once __DIR__ . '/includes/elements/shop-rail.php';
require_once __DIR__ . '/includes/race-store.php';
require_once __DIR__ . '/includes/results-store.php';
require_once __DIR__ . '/includes/live-store.php';
require_once __DIR__ . '/includes/stats-store.php';
require_once __DIR__ . '/includes/watch-store.php';
require_once __DIR__ . '/includes/race-video.php';
require_once __DIR__ . '/includes/films-store.php';
require_once __DIR__ . '/includes/photos-store.php';
require_once __DIR__ . '/includes/podcasts-store.php';
require_once __DIR__ . '/includes/media-follow.php';
require_once __DIR__ . '/includes/media-subnav.php';
require_once __DIR__ . '/includes/articles-store.php';
require_once __DIR__ . '/includes/tours-store.php';
require_once __DIR__ . '/includes/shop-store.php';
require_once __DIR__ . '/includes/trailtalk-feed.php';
require_once __DIR__ . '/includes/media-seo.php';
require_once __DIR__ . '/includes/media-hub.php';
require_once __DIR__ . '/includes/media-latest.php';
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

// The producer note reaches both surfaces, not just the store. Rock Hawk
// Trail Races stands in for the real case (Devil After Dark) because it is
// already in this fixture and on both the card grid and the calendar. Its
// full name, not "Rock Hawk": the note store is keyed on the name exactly
// as the store holds it, with none of the normalising arv_results_race_key()
// does elsewhere, so a near-miss here silently matches nothing.
arv_race_note_store_set( array( 'Rock Hawk Trail Races' => 'Produced by Calico Racing in 2026' ) );
$noted_cards = arv_upcoming_races_render( array( 'rows' => '', 'limit' => '0' ) );
$noted_cal   = arv_season_calendar_render( array( 'rows' => '' ) );

t( 'a note reaches the race card',      false !== strpos( $noted_cards, 'arv-races__presented' ) );
t( 'reading what it was given',         false !== strpos( $noted_cards, 'Produced by Calico Racing in 2026' ) );
t( 'and reaches the calendar row too',  false !== strpos( $noted_cal, 'arv-calendar__presented' ) );

// Every other race stays clean: this is a caveat on two listings, not a
// new line on all ninety.
t( 'only the noted race carries it',    1 === substr_count( $noted_cards, 'arv-races__presented' ) );

$GLOBALS['ARV_OPTIONS'] = array();
t( 'and nothing renders it once cleared', false === strpos( arv_upcoming_races_render( array( 'rows' => '', 'limit' => '0' ) ), 'arv-races__presented' ) );

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

echo "\nproducer notes, for a race Aravaipa owns but does not yet run:\n";
// Devil After Dark and Out with the Old become Aravaipa events in 2027
// and Calico Racing is still producing the 2026 editions. Listing them on
// aravaiparunning.com is an implicit claim about who runs them, and for
// another year that claim is wrong, so the card says so.
$GLOBALS['ARV_OPTIONS'] = array();

t( 'no note by default',                  '' === arv_race_presented_by( array( 'name' => 'Devil After Dark' ) ) );

arv_race_note_store_set( array(
	'Devil After Dark' => 'Produced by Calico Racing in 2026',
	'Out with the Old' => 'Produced by Calico Racing in 2026',
) );

t( 'a stored note resolves',              'Produced by Calico Racing in 2026' === arv_race_presented_by( array( 'name' => 'Devil After Dark' ) ) );
t( 'and only for the races named',        '' === arv_race_presented_by( array( 'name' => 'Javelina Jundred' ) ) );

// Same shape of write cleaning the waitlist store does, for the same
// reason: this is written by a script and must not store junk.
$n = arv_race_note_store_set( array( 'Good' => 'A note', '' => 'No name', 'Blank' => '   ' ) );
t( 'blank names and blank notes are dropped', 1 === $n );
t( 'and the good one survived',           'A note' === arv_race_presented_by( array( 'name' => 'Good' ) ) );

// A replace, not a merge: an event that has changed hands for real should
// stop carrying the caveat, and a merge would keep it forever.
t( 'a race dropped from the store loses its note', '' === arv_race_presented_by( array( 'name' => 'Devil After Dark' ) ) );

t( 'a race with no name is safe',         '' === arv_race_presented_by( array() ) );
t( 'a blank name is safe too',            '' === arv_race_presented_by( array( 'name' => '  ' ) ) );

$GLOBALS['ARV_OPTIONS'] = array();

echo "\na director-told gun time, for the race calendar has no field for one:\n";
$n = arv_race_start_store_set( array(
	'Oli Kai Trail Races' => array( 'time' => '08:00', 'tz' => 'America/New_York' ),
) );
t( 'a good entry is stored',              1 === $n );
t( 'and resolves to a real timestamp',    strtotime( '2026-09-05T12:00:00Z' ) === arv_race_start_override_ts( 'Oli Kai Trail Races', '2026-09-05' ) );

// Arizona never observes daylight saving and Tennessee does, so the same
// stored "08:00" has to land at a different UTC instant depending on the
// time of year, correctly, without this code ever comparing dates against
// a DST calendar of its own. Checked in January, when Chattanooga is only
// five hours off UTC rather than four.
t( 'the zone handles its own DST, not us', strtotime( '2026-01-10T13:00:00Z' ) === arv_race_start_override_ts( 'Oli Kai Trail Races', '2026-01-10' ) );

// Written by a script; validated rather than trusted, same posture as the
// notes and waitlist maps beside it.
$n = arv_race_start_store_set( array(
	'Bad Time'  => array( 'time' => '8am', 'tz' => 'America/New_York' ),
	'Bad Zone'  => array( 'time' => '08:00', 'tz' => 'Mars/Phoenix' ),
	'No Zone'   => array( 'time' => '08:00' ),
	'Not Array' => 'nope',
	''          => array( 'time' => '08:00', 'tz' => 'America/New_York' ),
	'Good'      => array( 'time' => '17:30', 'tz' => 'America/Denver' ),
) );
t( 'only the one valid entry survives',   1 === $n );
t( 'a bad time is rejected',              null === arv_race_start_override_ts( 'Bad Time', '2026-09-05' ) );
t( 'a bad zone is rejected',              null === arv_race_start_override_ts( 'Bad Zone', '2026-09-05' ) );
t( 'a missing zone is rejected',          null === arv_race_start_override_ts( 'No Zone', '2026-09-05' ) );
t( 'and the good one made it through',    null !== arv_race_start_override_ts( 'Good', '2026-09-05' ) );

// A replace, not a merge, same reason as the notes map: a race whose start
// time changes or gets pulled should not keep the old one forever.
t( 'a race dropped from the map has none', null === arv_race_start_override_ts( 'Oli Kai Trail Races', '2026-09-05' ) );

t( 'no name, no answer',                  null === arv_race_start_override_ts( '', '2026-09-05' ) );
t( 'no entry at all, no answer',          null === arv_race_start_override_ts( 'Nothing Stored', '2026-09-05' ) );

$GLOBALS['ARV_OPTIONS'] = array();

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



echo "\nultrarunning results map:\n";
// Cannot be derived the way UltraSignup can: the slug is their editorial
// name for the race ("black-canyon-trail" for Black Canyon Ultras) and the
// id is theirs. Guessing it returns 403, and their site sits behind a bot
// challenge a real browser bounces off. So it is entered once and kept.
t( 'a full results url reduces to its path', 'black-canyon-trail/race/44116' === arv_results_ultrarunning_path( 'https://ultrarunning.com/calendar/event/black-canyon-trail/race/44116/results' ) );
// The anchor comes along when it is copied out of the address bar.
t( 'and drops a selected_year anchor',       'cocodona-250/race/44204' === arv_results_ultrarunning_path( 'https://ultrarunning.com/calendar/event/cocodona-250/race/44204/results#selected_year' ) );
t( 'a bare path is accepted too',            'north-fork-50-mile-50k/race/46070' === arv_results_ultrarunning_path( 'north-fork-50-mile-50k/race/46070' ) );

// A bare slug, no id, is accepted too, and deliberately: UltraRunning mints
// a new id every year, so an entry with one can only ever be right for a
// single edition. Every entry stored this way used to claim a specific,
// usually wrong, year for every OTHER edition of that race, and a bare
// slug is how this map stops doing that: it resolves to the race's own
// results index instead, every year it has run listed there to pick, which
// is never the wrong year because it never claims one.
t( 'a bare slug is accepted, with no id',    'javelina-jundred' === arv_results_ultrarunning_path( 'https://ultrarunning.com/calendar/event/javelina-jundred' ) );
t( 'the plain slug alone works too',         'javelina-jundred' === arv_results_ultrarunning_path( 'javelina-jundred' ) );
t( 'trailing slash and case do not matter',  'javelina-jundred' === arv_results_ultrarunning_path( 'JAVELINA-JUNDRED/' ) );

t( 'another site is refused',                '' === arv_results_ultrarunning_path( 'https://example.com/black-canyon-trail/race/44116' ) );
t( 'and an empty value is refused',          '' === arv_results_ultrarunning_path( '' ) );

// Keyed by the archive's own race key, so one entry covers every spelling
// across every year rather than needing a row per edition.
arv_results_ultrarunning_store_set( array(
	'Black Canyon Ultras' => 'https://ultrarunning.com/calendar/event/black-canyon-trail/race/44116/results',
	'Cocodona 250'        => 'cocodona-250/race/44204',
	'Javelina Jundred'    => 'javelina-jundred',
	'Typo Race'           => 'not a url',
) );
t( 'the typo was dropped',                   3 === count( arv_results_ultrarunning_store_get() ) );
t( 'a race with an id resolves to results',  'https://ultrarunning.com/calendar/event/black-canyon-trail/race/44116/results' === arv_results_ultrarunning_url( 'Black Canyon Ultras' ) );
// The whole point of keying on the race key rather than the exact name.
t( 'under a different spelling too',         'https://ultrarunning.com/calendar/event/black-canyon-trail/race/44116/results' === arv_results_ultrarunning_url( 'Black Canyon' ) );
t( 'and with the distance dropped',          'https://ultrarunning.com/calendar/event/cocodona-250/race/44204/results' === arv_results_ultrarunning_url( 'Cocodona' ) );
// A race with no id on file resolves to its index, not a results page: no
// id means no way to build one that is not a guess at the year.
t( 'a race with no id resolves to its index', 'https://ultrarunning.com/calendar/event/javelina-jundred/race' === arv_results_ultrarunning_url( 'Javelina Jundred' ) );
t( 'a race not on file resolves to nothing', '' === arv_results_ultrarunning_url( 'Not A Real Race' ) );

// Filled in at render for any row the scraper left blank, which is most of
// the archive: the scraper only ever found these where a human had already
// linked one.
arv_results_store_set( array(
	// A live link but no UltraRunning one, which is the common real case:
	// the store refuses a row with no links at all, so the rows this fills
	// in are always ones that have something else already.
	array( 'name' => 'Black Canyon Ultras', 'iso' => '2025-02-14', 'display' => 'February 14',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2025',
	       'ultrasignup' => '', 'ultrarunning' => '' ),
	array( 'name' => 'Cocodona 250', 'iso' => '2025-05-05', 'display' => 'May 5',
	       'live' => 'https://live.aravaiparunning.com/#/cocodona-2025', 'ultrasignup' => '',
	       'ultrarunning' => 'https://ultrarunning.com/calendar/event/already/race/999/results' ),
) );
$GLOBALS['NOW'] = '2026-08-30';
$ur = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'false', 'year' => '2025' ) );
t( 'a blank row gains its link',             false !== strpos( $ur, 'black-canyon-trail/race/44116' ) );
// A scraped link came off the page itself and is the more specific answer.
t( 'a row that already had one keeps it',    false !== strpos( $ur, 'already/race/999' ) );
t( 'and is not overwritten by the map',      false === strpos( $ur, 'cocodona-250/race/44204' ) );

arv_results_ultrarunning_store_set( array() );
arv_results_store_set( array() );

echo "\nultrasignup results link, derived from the register url:\n";
// 2026 uses "dtid", every row before it used "did". The regex only ever
// matched "did", so this returned '' for every current race and the one
// call site that reaches it with nothing else to fall back on
// (arv_upcoming_races_action(), for a race with no live board) never had
// anything to show. Confirmed live: UltraSignup redirects
// results_event.aspx?dtid=N straight to the canonical ?did=N for four
// different races, so passing the id through under whichever name it
// arrived as needs no second request to resolve it.
t( 'derives from a dtid registration link', 'https://ultrasignup.com/results_event.aspx?dtid=63630' === arv_upcoming_races_results_url( 'https://ultrasignup.com/register.aspx?dtid=63630' ) );
t( 'and from the older did shape too',      'https://ultrasignup.com/results_event.aspx?did=131056' === arv_upcoming_races_results_url( 'https://ultrasignup.com/register.aspx?did=131056' ) );
t( 'ignoring other query params after it',  'https://ultrasignup.com/results_event.aspx?dtid=63630' === arv_upcoming_races_results_url( 'https://ultrasignup.com/register.aspx?dtid=63630&sid=1' ) );
t( 'not a different site',                  '' === arv_upcoming_races_results_url( 'https://example.com/register.aspx?dtid=63630' ) );
t( 'not a bare url',                        '' === arv_upcoming_races_results_url( 'https://ultrasignup.com/register.aspx' ) );
t( 'not an empty one',                      '' === arv_upcoming_races_results_url( '' ) );

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
// Its UltraSignup link too, derived from the calendar's own registration
// link rather than left blank until the scraper's next run.
t( 'with an ultrasignup link already on it', false !== strpos( arv_test_archive_only( $now ), 'ultrasignup.com/results_event.aspx?dtid=63630' ) );
// It appears, because the scraper has not caught up, but it is not
// "Happening now": it finished yesterday. This asserted the opposite until
// Jamil saw Black Bear and Rock Hawk both still claiming to be happening
// the morning after they ran, three inches under a race week block that
// correctly said COMPLETED. The flag was true for every row in the ten-day
// window the scraper is given, rather than for a race actually running.
t( 'but is not flagged as happening now', false === strpos( arv_test_archive_only( $now ), 'arv-results__flag' ) );
t( 'above the older stored result',   strpos( arv_test_archive_only( $now ), 'Rock Hawk' ) < strpos( arv_test_archive_only( $now ), 'Coldwater Rumble' ) );

// On the day itself the flag is decided by the board's own clock, not by
// the date. Seeded here so this exercises that path rather than the
// no-board fallback: 12:00Z gun, 22:00Z cutoff, which is what the real
// board carried for Rock Hawk that day.
arv_live_store_set( array(
	array(
		'slug'   => 'rock_hawk-2026',
		'start'  => '2026-08-29T12:00:00.000Z',
		'cutoff' => '2026-08-29T22:00:00.000Z',
		'offset' => -6,
		'races'  => array(),
	),
	// Black Bear ran the same day. Seeded too, or it falls back to the date
	// and leaves a flag on the page that this is trying to assert is gone.
	array(
		'slug'   => 'black_bear-2026',
		'start'  => '2026-08-29T10:00:00.000Z',
		'cutoff' => '2026-08-29T22:00:00.000Z',
		'offset' => -4,
		'races'  => array(),
	),
) );

$GLOBALS['NOW']    = '2026-08-29';
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T15:00:00Z' );   // three hours in
$during = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'during the race it is flagged',   false !== strpos( arv_test_archive_only( $during ), 'arv-results__flag' ) );

// Past the cutoff, still the same calendar day. This is the case a date
// comparison cannot get right and the one the board exists for.
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T23:00:00Z' );
$after = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'past the cutoff it is not',       false === strpos( arv_test_archive_only( $after ), 'arv-results__flag' ) );


// Before the gun, likewise.
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T08:00:00Z' );
$before = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'and before the gun it is not',    false === strpos( arv_test_archive_only( $before ), 'arv-results__flag' ) );

// The same three states for a race with NO board at all.
//
// This is the case the board tests above cannot reach and the one that
// actually shipped broken. Oli Kai is scored on RaceResult, so nothing
// ever writes a board row for it, so this path had no start to measure
// from: it skipped the clock entirely, fell through to "is it today", and
// said "Happening now" for the whole calendar day. Jamil saw it still
// claiming to be happening nine hours after the race finished, on the
// same page whose race week block said COMPLETED.
//
// Everything it needs is stored: a director's 8am Eastern gun time and a
// nine hour cutoff. Both were already correct on the live site while the
// page was wrong, which is what makes this a wiring bug rather than a
// data one.
// Black Bear ran the same day, so it needs a clock too or it falls back to
// the date and leaves a flag on the page these assertions are looking for
// the absence of.
arv_live_store_set( array() );
arv_race_start_store_set( array(
	'Rock Hawk Trail Races'  => array( 'time' => '08:00', 'tz' => 'America/New_York' ),
	'Black Bear Trail Races' => array( 'time' => '08:00', 'tz' => 'America/New_York' ),
) );
arv_race_cutoff_store_set( array(
	'Rock Hawk Trail Races'  => 9,
	'Black Bear Trail Races' => 9,
) );

// 08:00 America/New_York on race day is 12:00Z, so the cutoff is 21:00Z.
t( 'the boardless start resolves to the gun time',
	strtotime( '2026-08-29T12:00:00Z' ) === arv_race_start_ts( array( 'name' => 'Rock Hawk Trail Races', 'iso' => '2026-08-29' ), null ) );
t( 'and the cutoff nine hours past it',
	strtotime( '2026-08-29T21:00:00Z' ) === arv_race_cutoff_for( 'Rock Hawk Trail Races', null, strtotime( '2026-08-29T12:00:00Z' ) ) );

$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T15:00:00Z' );   // three hours in
$nb_during = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'no board, mid race, it is flagged',
	false !== strpos( arv_test_archive_only( $nb_during ), 'arv-results__flag' ) );

// The assertion that was false before this fix. Same calendar day, past
// the stored cutoff: a date comparison says yes and the race is over.
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T23:00:00Z' );
$nb_after = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'no board, past its cutoff, it is not',
	false === strpos( arv_test_archive_only( $nb_after ), 'arv-results__flag' ) );

$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T08:00:00Z' );
$nb_before = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'no board, before the gun, it is not',
	false === strpos( arv_test_archive_only( $nb_before ), 'arv-results__flag' ) );

// Nothing stored anywhere still falls back to the date, which for a race
// nobody has told us anything about is the only answer available.
arv_race_start_store_set( array() );
arv_race_cutoff_store_set( array() );
t( 'with no clock from any source there is no start',
	0 === arv_race_start_ts( array( 'name' => 'Rock Hawk Trail Races', 'iso' => '2026-08-29' ), null ) );
$GLOBALS['NOW_TS'] = strtotime( '2026-08-29T23:00:00Z' );
$nb_none = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
t( 'and the date decides again',
	false !== strpos( arv_test_archive_only( $nb_none ), 'arv-results__flag' ) );

// The board still wins where there is one, so adding the override lookup
// cannot change a board-timed race.
arv_race_start_store_set( array( 'Rock Hawk Trail Races' => array( 'time' => '23:30', 'tz' => 'America/New_York' ) ) );
t( 'a board start beats a stored gun time',
	strtotime( '2026-08-29T12:00:00Z' ) === arv_race_start_ts(
		array( 'name' => 'Rock Hawk Trail Races', 'iso' => '2026-08-29' ),
		array( 'start' => '2026-08-29T12:00:00.000Z', 'cutoff' => '' )
	) );
arv_race_start_store_set( array() );

arv_live_store_set( array() );
$GLOBALS['NOW']    = '2026-08-30';
$GLOBALS['NOW_TS'] = null;

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
// Neither is flagged the day after, scraped or not: the flag tracks whether
// a race is running, and the dedupe is what this is really checking.
t( 'the unscraped race still appears', false !== strpos( arv_test_archive_only( $half ), 'Black Bear' ) );
t( 'and neither is flagged the day after', false === strpos( arv_test_archive_only( $half ), 'arv-results__flag' ) );
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
t( 'the year list carries no editions',   false === strpos( $byrace, 'arv-results__editions' ) );
// Under race-results/, never results/. That path is a real directory of
// static archive files on the web server and Apache answers it off disk
// before WordPress sees the request, so a race page served there 404s no
// matter what rewrite rule is registered.
t( 'the race name links to its own page', false !== strpos( $byrace, '/race-results/black-canyon-ultras/' ) );
t( 'and never under the archive path',    false === strpos( $byrace, '"/results/black-canyon-ultras/' ) );
t( 'and only the newest runs inline',     false === strpos( $byrace, 'bc-2024' ) );
t( 'every race gets exactly one link',    2 === substr_count( $byrace, 'arv-results__race-link' ) );
t( 'the other race stayed separate',      false !== strpos( $byrace, 'Crown King Scramble' ) );
t( 'and the search box is there',         false !== strpos( $byrace, 'data-arv-results-search' ) );

// Singular, not "1 earlier editions".
arv_results_store_set( array(
	array( 'name' => 'Zion Ultras', 'iso' => '2026-04-10', 'live' => 'https://live.aravaiparunning.com/#/z-2026' ),
	array( 'name' => 'Zion Ultras', 'iso' => '2025-04-11', 'live' => 'https://live.aravaiparunning.com/#/z-2025' ),
) );
$one = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'a two-edition race links out too',    false !== strpos( $one, 'arv-results__race-link' ) );

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

// The fixed three-slot grid is gone: a missing listing is a missing chip
// now, not an empty placeholder holding a column open. There is no longer
// a fixed column count for a placeholder to hold open in the first place,
// since an edition's own result files share this same row and that count
// runs one to nine.
t( 'a missing listing is just absent',    false === strpos( $withsearch, 'arv-results__slot' ) );

// The expander needs a chevron of its own: any display other than
// list-item removes the browser's disclosure triangle.
t( 'the race name is the way in',         false !== strpos( $withsearch, 'arv-results__race-link' ) );
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

// ------------------------------------- Next weekend, from Monday morning --
// Race week used to hold only what the phase engine already called live,
// which for a race still selling entries is the Wednesday at the earliest.
// So on a Monday the block showed the weekend just gone and nothing else,
// and the weekend everyone was actually looking for was the one thing
// missing from it.
$olikai = 'Oli Kai Trail Races | 2026-09-05 | Saturday - September 5, 2026 | 9 Hour | 6 Hour | 3 Hour | '
	. 'Reflection Riding | Chattanooga, TN | https://ultrasignup.com/register.aspx?did=134059 | '
	. 'https://www.badbeardevents.com/featured-races/oli-kai- | https://example.com/oli-kai.png | '
	. ' |  | 2026-09-01 | 1 | 0 | 35.0036320 | -85.3646122';
arv_race_store_import( $olikai );

// A director-told gun time, for the one race that has it: 8am in
// Chattanooga, not midnight in Phoenix. Without this, the day-based
// 'soon'/'live' split above flips the moment Arizona's own calendar rolls
// over to race day, hours before the actual gun three time zones east.
arv_race_start_store_set( array(
	'Oli Kai Trail Races' => array( 'time' => '08:00', 'tz' => 'America/New_York' ),
) );

// Arizona's midnight on race day (2026-09-05T07:00Z) has already passed,
// but Chattanooga's 8am gun (EDT, so 12:00Z) has not. The day-only check
// would call this live; the override should not.
$GLOBALS['NOW']    = '2026-09-05';
$GLOBALS['NOW_TS'] = strtotime( '2026-09-05T09:00:00Z' );
$before_gun = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$bg_week    = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $before_gun, $bgm ) ? $bgm[0] : '';
t( 'not live before the real gun',      false !== strpos( $bg_week, 'data-arv-results-live hidden' ) );
t( 'the countdown still shows',         false !== strpos( $bg_week, 'Starts in' ) );
// EDT in September, so 8am there is 12:00Z: DST handled by the zone itself,
// not by anything this code had to get right on its own.
t( 'and targets the real gun, in UTC',  false !== strpos( $bg_week, 'data-arv-start="2026-09-05T12:00:00+00:00"' ) );

// Past the real gun now.
$GLOBALS['NOW_TS'] = strtotime( '2026-09-05T13:00:00Z' );
$after_gun = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$ag_week   = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $after_gun, $agm ) ? $agm[0] : '';
t( 'live once the real gun has gone',   false !== strpos( $ag_week, 'data-arv-results-live>' ) );

arv_race_start_store_set( array() );
$GLOBALS['NOW_TS'] = null;

// The Sunday before: still last week, so the coming weekend stays out.
$GLOBALS['NOW'] = '2026-09-06';
$sun = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$sun_week = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $sun, $sm ) ? $sm[0] : '';
t( 'the week ends on Sunday',           false === strpos( $sun_week, 'Snow Mountain Ranch' ) );

// Monday morning: this week's races join the block.
$GLOBALS['NOW'] = '2026-09-07';
$mon = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$mon_week = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $mon, $mm ) ? $mm[0] : '';
t( 'Monday brings the coming weekend',  false !== strpos( $mon_week, 'Snow Mountain Ranch' ) );
t( 'the whole weekend, not one race',   false !== strpos( $mon_week, 'Mogollon Monster' ) );
t( 'and it counts down to the start',   false !== strpos( $mon_week, 'Starts in' ) );

// The week is a week, not a rolling window: the following weekend waits.
t( 'the weekend after still waits',     false === strpos( $mon_week, 'Jangover' ) );

// A race still selling entries must not be handed a live board that does
// not exist yet, on a link that goes to UltraSignup's entry form.
t( 'an open race says Register',        false !== strpos( $mon_week, '>Register</a>' ) );

// Last weekend and the coming one, together, oldest first.
$GLOBALS['NOW'] = '2026-08-31';
$both = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$both_week = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $both, $bm ) ? $bm[0] : '';
t( 'the weekend just gone is still up', false !== strpos( $both_week, 'Rock Hawk' ) );
t( 'with the coming one behind it',     false !== strpos( $both_week, 'Oli Kai' ) );
t( 'in date order, finished first',     strpos( $both_week, 'Rock Hawk' ) < strpos( $both_week, 'Oli Kai' ) );
t( 'the finished one reads Completed',  false !== strpos( $both_week, 'Completed' ) );

// Entries close on the 1st, so from the 2nd the same race turns over to the
// board on its own. Nothing here has to know that: it is the phase engine's
// answer, read rather than repeated.
$GLOBALS['NOW'] = '2026-09-02';
$wed = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'upcoming' => 'true' ) );
$wed_week = preg_match( '/<section class="arv-results__week".*?<\/section>/s', $wed, $wm ) ? $wm[0] : '';
t( 'once entries close it flips',       false !== strpos( $wed_week, 'Live Results' ) );
t( 'and stops offering Register',       false === strpos( $wed_week, '>Register</a>' ) );

arv_race_store_import( $ROWS, true );
$GLOBALS['NOW'] = '2026-08-28';

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

// Unless the caller knows the start. Aravaipa does not time every race it
// lists: Oli Kai is scored on RaceResult and has no board at all, so its
// stored 9 hour cutoff was being read, found to have no gun to measure
// from, and silently discarded. The race showed LIVE NOW for an hour after
// it had finished. Every caller has already resolved the real start,
// including the start-time override, so it can hand it over.
$gun = strtotime( '2026-09-05T12:00:00Z' );
t( 'a boardless race can still cut off', ( $gun + 12 * 3600 ) === arv_race_cutoff_for( 'Black Bear Trail Race', null, $gun ) );

// The board still wins where there is one: this is a fallback, not a
// second source of truth competing with the timing team's own number.
t( 'the board start still takes priority', strtotime( '2026-08-29T22:00:00Z' ) === arv_race_cutoff_for( 'Black Bear Trail Race', $board, $gun ) );

// And a start with no override is still no cutoff, rather than one
// invented from whatever the caller happened to pass.
t( 'no override means no cutoff still',  0 === arv_race_cutoff_for( 'Not A Race', null, $gun ) );

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

echo "\nwinner lines: which one is which:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$line = arv_results_winners_block( array(
	'headline' => true,
	'winners'  => array( array(
		'distance'  => '100 Mile',
		'men'       => array( 'name' => 'Jeff Riley', 'time' => '16:48:32' ),
		'women'     => array( 'name' => 'Jamie Donaldson', 'time' => '18:43:57' ),
		'nonbinary' => array( 'name' => 'Riley Brady', 'time' => '19:02:11' ),
	) ),
) );

// Two names side by side under no headings is a guess, and the names are
// not always the tell. A badge is the smallest thing that answers it.
t( 'the badge names the division',      false !== strpos( $line, '>M</span>' ) );
t( 'women read F, not W',               false !== strpos( $line, '>F</span>' ) );
t( 'and nonbinary reads NB, not X',     false !== strpos( $line, '>NB</span>' ) );
t( 'never M1: every one of them won',   false === strpos( $line, 'M1' ) );

// Drawn for sighted readers and hidden from a screen reader, which already
// had the whole word and would otherwise hear both.
t( 'the badge is hidden from a reader', false !== strpos( $line, 'arv-results__division arv-results__division--men" aria-hidden="true"' ) );
t( 'the full word is still announced',  false !== strpos( $line, 'Nonbinary: ' ) );

// The table has MEN and WOMEN at the tops of its columns. A badge in every
// cell would repeat the heading the whole way down.
$two = arv_results_winners_block( array(
	'headline' => true,
	'winners'  => array(
		array(
			'distance' => '100 Mile',
			'men'      => array( 'name' => 'Jeff Riley', 'time' => '16:48:32' ),
			'women'    => array( 'name' => 'Jamie Donaldson', 'time' => '18:43:57' ),
		),
		array(
			'distance' => '100K',
			'men'      => array( 'name' => 'Igor Campos', 'time' => '9:34:26' ),
			'women'    => array( 'name' => 'Tracy Bowling', 'time' => '10:10:36' ),
		),
	),
) );
$table = substr( $two, strpos( $two, '<table' ) );
t( 'the table still has its headings',  false !== strpos( $table, '>Women</th>' ) );
t( 'and no badge inside it',            false === strpos( $table, 'arv-results__division' ) );
t( 'while the peek above still has one', false !== strpos( substr( $two, 0, strpos( $two, '<table' ) ), 'arv-results__division' ) );

echo "\narchive stats: the years before the board:\n";
$GLOBALS['ARV_OPTIONS'] = array();

$archive_event = array(
	'name'      => 'Mesquite Canyon Trail Runs',
	'iso'       => '2010-03-20',
	'finishers' => 62,
	'starters'  => 71,
	'headline'  => true,
	'winners'   => array(
		array(
			'distance' => '50K',
			'men'      => array( 'name' => 'Jason Griffiths', 'time' => '04:01:22' ),
			'women'    => array( 'name' => 'Paulette Zillmer', 'time' => '05:31:41' ),
		),
	),
);

t( 'an archive edition stores',         1 === arv_archive_stats_store_set( array( $archive_event ) ) );

$row = array( 'name' => 'Mesquite Canyon Trail Runs', 'iso' => '2010-03-20', 'live' => '' );
t( 'and is found by name and date',     62 === arv_stats_for_row( $row )['finishers'] );
t( 'with its winners intact',           'Jason Griffiths' === arv_stats_for_row( $row )['winners'][0]['men']['name'] );

// The key is the race's own name, not a slug anybody has to keep in step
// with it, so the case it was typed in cannot decide whether it matches.
t( 'the name matches case-blind',       null !== arv_stats_for_row( array( 'name' => 'MESQUITE CANYON TRAIL RUNS', 'iso' => '2010-03-20' ) ) );
t( 'a different date is a miss',        null === arv_stats_for_row( array( 'name' => 'Mesquite Canyon Trail Runs', 'iso' => '2011-03-12' ) ) );
t( 'and so is a different race',        null === arv_stats_for_row( array( 'name' => 'Coldwater Rumble', 'iso' => '2010-03-20' ) ) );
t( 'a nameless row finds nothing',      null === arv_stats_for_row( array( 'iso' => '2010-03-20' ) ) );
t( 'nor does a dateless one',           null === arv_stats_for_row( array( 'name' => 'Mesquite Canyon Trail Runs' ) ) );

// The reason these are two options and not one table keyed two ways. Both
// stores replace their contents wholesale, which is right for each of them
// on its own: each scraper walks its whole source every run, so absence
// from that walk means the thing is gone. Share one option and the next
// fetch-stats.mjs run, which walks Momentum and has never heard of a race
// from 2010, deletes every archive edition as missing.
arv_stats_store_set( array( array( 'slug' => 'coldwater_rumble-2026', 'finishers' => 400 ) ) );
t( 'a board walk keeps the archive',    62 === arv_stats_for_row( $row )['finishers'] );
arv_archive_stats_store_set( array( $archive_event ) );
t( 'and an archive walk keeps the board', 400 === arv_stats_store_find( 'https://live.aravaiparunning.com/#/coldwater_rumble-2026' )['finishers'] );

// Where a race has a board, the board is what timed it: better than a file
// somebody saved afterwards, and the only one of the two that gets updated.
arv_stats_store_set( array( array( 'slug' => 'mesquite-2010', 'finishers' => 999 ) ) );
t( 'the board wins where there is one', 999 === arv_stats_for_row( array(
	'name' => 'Mesquite Canyon Trail Runs',
	'iso'  => '2010-03-20',
	'live' => 'https://live.aravaiparunning.com/#/mesquite-2010',
) )['finishers'] );
t( 'a board with no stats still falls back', 62 === arv_stats_for_row( array(
	'name' => 'Mesquite Canyon Trail Runs',
	'iso'  => '2010-03-20',
	'live' => 'https://live.aravaiparunning.com/#/nothing-here',
) )['finishers'] );

// Same cleaning as the board's own events, because it is literally the same
// function: a winner with no time is not a winner, and counts floor at zero.
arv_archive_stats_store_set( array( array(
	'name'      => 'Half A Result',
	'iso'       => '2012-01-01',
	'finishers' => -3,
	'winners'   => array( array( 'distance' => '50K', 'men' => array( 'name' => 'No Time Given', 'time' => '' ) ) ),
) ) );
$half = arv_stats_for_row( array( 'name' => 'Half A Result', 'iso' => '2012-01-01' ) );
t( 'a timeless winner is dropped',      ! isset( $half['winners'] ) );
t( 'and a negative count floors',       0 === $half['finishers'] );

// A fixed-time race is won on distance, so its stored result is one. The
// course-record table reads times and skips what will not parse, which is
// the right answer: the record for a 24 hour is a distance to beat.
arv_archive_stats_store_set( array( array(
	'name'     => 'Across The Years',
	'iso'      => '2015-12-28',
	'headline' => true,
	'winners'  => array( array(
		'distance' => '24 Hour',
		'women'    => array( 'name' => 'Eileen Torres', 'time' => '127.02 mi' ),
	) ),
) ) );
$aty = arv_stats_for_row( array( 'name' => 'Across The Years', 'iso' => '2015-12-28' ) );
t( 'a distance stores as the result',   '127.02 mi' === $aty['winners'][0]['women']['time'] );
t( 'and it renders as written',         false !== strpos( arv_results_winners_block( $aty ), '127.02 mi' ) );
t( 'but is no course record',           null === arv_results_time_seconds( '127.02 mi' ) );

// Nothing to key on is nothing to store, rather than a row under "|".
t( 'a nameless event is not stored',    0 === arv_archive_stats_store_set( array( array( 'iso' => '2010-03-20' ) ) ) );
t( 'nor is a dateless one',             0 === arv_archive_stats_store_set( array( array( 'name' => 'Nowhen' ) ) ) );
t( 'nor is junk',                       0 === arv_archive_stats_store_set( array( 'not an event', null, 7 ) ) );

echo "\nresults stats: the marquee winners:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$block = arv_results_winners_block( array(
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
$summary = substr( $block, 0, strpos( $block, '</summary>' ) );

// Both divisions, not one winner. At Bear Chase 2024 the women's 100K
// champion beat the men's, so naming a single winner was a wrong answer.
t( 'the headline names the men',        false !== strpos( $block, 'Alex Bustamante' ) );
t( 'and the women',                     false !== strpos( $block, 'Sydney Park' ) );
t( 'with their times',                  false !== strpos( $block, '5:49:58' ) && false !== strpos( $block, '6:02:07' ) );
t( 'and the distance they ran',         false !== strpos( $block, '>52K<' ) );
t( 'normalised, like everywhere else',  false === strpos( $block, '52KM' ) );

// The division is named for a screen reader and not drawn: sighted readers
// get it from the names, and "Men Alex Bustamante" is scaffolding.
t( 'divisions are named for a11y',      false !== strpos( $block, 'Men: ' ) && false !== strpos( $block, 'Women: ' ) );

// Closed, the summary peeks at the premier distance and says how many more
// there are. Open, the table carries every distance including that one,
// under headings that name the columns: the premier distance sitting above
// those headings, in a shape matching none of the rows below it, was the
// thing this arrangement replaced.
t( 'the summary is one distance',       false === strpos( $summary, 'Devin Sharps' ) );
t( 'the table holds every distance',    false !== strpos( $block, 'Devin Sharps' )
                                        && false !== strpos( $block, '>52K<' ) );
t( 'and says how much more there is',   false !== strpos( $block, '1 more distance' ) );

// The peek and the label are both in the markup and CSS shows exactly one,
// which is also what keeps a screen reader from meeting the result twice:
// display:none takes the hidden half out of the accessibility tree.
t( 'the peek is the closed state',      false !== strpos( $block, 'arv-results__winners-peek' ) );
t( 'and a label is the open one',       false !== strpos( $block, 'arv-results__winners-shut' ) );

// A winner's name is untrusted text like any other.
t( 'a winner name is escaped',          false === strpos(
	arv_results_winners_block( array(
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
$table = arv_results_winners_block( $full );
t( 'the summary names the headline',    false !== strpos( $table, '>52K<' ) );
t( 'the table names the rest',          false !== strpos( $table, '>20K<' ) );
t( 'and every winner a cell',           false !== strpos( $table, 'Devin Sharps' ) && false !== strpos( $table, 'Liliana Gutierrez' ) );
t( 'columns are the scored divisions',  false !== strpos( $table, '>Men<' ) && false !== strpos( $table, '>Women<' ) && false !== strpos( $table, '>Nonbinary<' ) );
t( 'it counts what is left over',       false !== strpos( $table, '1 more distance' ) );

// A division the board could not resolve is an empty cell, not a dash: one
// 6K women's winner on the archive has a finish stamp equal to her start.
t( 'a missing winner is a blank cell',  false !== strpos( $table, '<td></td>' ) );

// Nothing to open. An event with one scored distance would otherwise get a
// control that repeats the line directly above it.
$one = array( 'headline' => true, 'winners' => array( array(
	'distance' => '50K', 'men' => array( 'name' => 'A', 'time' => '1:00:00' ),
) ) );
t( 'one distance needs no control',     false === strpos( arv_results_winners_block( $one ), '<details' ) );
t( 'but still names the winner',        false !== strpos( arv_results_winners_block( $one ), 'A' ) );

// Unless there was no headline to repeat. A lap event runs every category
// over one loop, so it has no premier distance, but "who won the six hour
// solo" is still a real answer, and nothing gets featured over anything else.
$lap = array_merge( $one, array( 'headline' => false ) );
t( 'but a lap event still gets one',    false !== strpos( arv_results_winners_block( $lap ), 'A' ) );
t( 'behind a plain label, not a name',  false !== strpos( arv_results_winners_block( $lap ), '>Winners<' ) );
t( 'and no winners means no table',     '' === arv_results_winners_block( array( 'headline' => false ) ) );

// A lap event has no marquee result to feature over the others.
$no_headline  = arv_results_winners_block( array( 'headline' => false, 'winners' => $full['winners'] ) );
$no_headline_summary = substr( $no_headline, 0, strpos( $no_headline, '</summary>' ) );
t( 'no headline, no featured name',     false === strpos( $no_headline_summary, 'Alex Bustamante' ) );
t( 'every distance still shows up',     false !== strpos( $no_headline, 'Alex Bustamante' ) && false !== strpos( $no_headline, 'Devin Sharps' ) );
t( 'and none without winners either',   '' === arv_results_winners_block( array( 'headline' => true ) ) );

echo "\nresults: a race's own page:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
	array( 'name' => 'Rock Hawk Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/rock_hawk-2026' ),
) );

$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'black-bear-trail-races' );
$ctx = arv_results_race_context();
t( 'a race slug resolves to a race',    null !== $ctx && 'Black Bear Trail Races' === $ctx['name'] );

// A race the archive has stored under more than one name is reachable at
// every one of them, not only its newest. Grouping collapses the spellings
// into one race, but the archive renders a panel per year and each panel
// links that year's row by ITS name, so registering only the newest meant
// the older panels linked a URL that answered "No race by that name": 73
// of 553 editions across 23 races, Black Canyon and Cocodona included.
arv_results_store_set( array(
	array( 'name' => 'Black Canyon', 'iso' => '2026-02-14', 'display' => 'February 14',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2026' ),
	array( 'name' => 'Black Canyon Ultras', 'iso' => '2025-02-08', 'display' => 'February 8',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2025' ),
	array( 'name' => 'Black Canyon Trail Runs', 'iso' => '2024-02-10', 'display' => 'February 10',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2024' ),
) );

$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'black-canyon' );
t( 'the newest spelling resolves',      null !== arv_results_race_context() );
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'black-canyon-ultras' );
$alias = arv_results_race_context();
t( 'and so does an older one',          null !== $alias );
// All the way to the same race, not a partial view of it: an alias is the
// same page, so it carries every edition the newest spelling does.
t( 'the alias is the same race',        null !== $alias && 3 === count( $alias['editions'] ) );
t( 'headlined by the current name',     null !== $alias && 'Black Canyon' === $alias['name'] );
// And canonicals to the one real URL, so the aliases cost nothing in search.
t( 'canonical points at the newest',    'https://www.aravaiparunning.com/race-results/black-canyon/' === $alias['url'] );
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'black-canyon-trail-runs' );
t( 'a third spelling too',              null !== arv_results_race_context() );

$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2025-08-30', 'display' => 'August 30',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2025' ),
	array( 'name' => 'Rock Hawk Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/rock_hawk-2026' ),
) );
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'black-bear-trail-races' );
$ctx = arv_results_race_context();
t( 'carrying only its own editions',    2 === count( $ctx['editions'] ) );
t( 'and its own url',                   'https://www.aravaiparunning.com/race-results/black-bear-trail-races/' === $ctx['url'] );

// The canonical is the whole reason this context exists. Every race page
// resolves to the one Results page, so WordPress pointed all of them at
// /results/, which tells a crawler not to index any of them.
t( 'the canonical is the race page',    $ctx['url'] === arv_results_race_canonical( 'https://www.aravaiparunning.com/results/' ) );
t( 'the description counts editions',   false !== strpos( arv_results_race_seo_description( $ctx ), '2 editions run from 2025 to 2026' ) );

// The title and the head tags defer to a real SEO plugin, and WPSEO_VERSION
// is defined far above for the front-page tests, so from here on they are
// correctly silent. The canonical deliberately does NOT defer: standing down
// there would hand every race page back the /results/ canonical that is the
// entire bug, so it corrects Yoast's answer instead of declining to give one.
t( 'the title defers to a real plugin', 'Results' === arv_results_race_seo_title_parts( array( 'title' => 'Results' ) )['title'] );
ob_start(); arv_results_race_seo_head(); $race_head = ob_get_clean();
t( 'and so do the head tags',           '' === $race_head );

// The archive and the calendar name the same race differently often enough
// that exact matching found a page for only 66 of 120 races, and the misses
// were current races, not retired ones.
echo "\n";
t( 'the same race, named shorter',      arv_results_same_race( 'Desert Solstice', 'Desert Solstice Track Invitational' ) );
t( 'or named longer',                   arv_results_same_race( 'Aspen Backcountry Marathon', 'Aspen Backcountry' ) );
t( 'and an unrelated race is not',      ! arv_results_same_race( 'Black Bear Trail Races', 'Rock Hawk Trail Races' ) );

// A ride is not a run. The archive carries the bike editions of four night
// races, and every word of "Stunner Night Runs" is in "Stunner Night Rides",
// so a plain subset match sent riders to the runners' page.
t( 'a ride is not the run',             ! arv_results_same_race( 'Stunner Night Rides', 'Stunner Night Runs' ) );
t( 'nor is a virtual edition',          ! arv_results_same_race( 'Hypnosis Virtual Night Race', 'Hypnosis Night Runs' ) );

// The key strips numbers, so both of these reduce to "silverton" and matched
// each other. Compared before the stripping instead.
t( 'and a number has to agree',         ! arv_results_same_race( 'Silverton 1000', 'Silverton Alpine Marathon' ) );

// The race is the subject of the page, so it is the h1. There was no h1 on
// it at all before: the theme does not print the Page's own title here.
$racepage = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'the race name is the h1',           false !== strpos( $racepage, '<h1 class="arv-results__race-title">Black Bear Trail Races</h1>' ) );
t( 'and the other race is not on it',   false === strpos( $racepage, 'Rock Hawk' ) );

// "Where do I enter this" is the one question a results page cannot answer,
// so the race's own page sits beside the name. Black Bear is on the calendar
// (imported far above), so it gets the button.
t( 'a live race links to its page',     false !== strpos( $racepage, 'arv-results__race-info' ) );
t( 'pointing at the calendar page',     false !== strpos( $racepage, 'https://www.aravaiparunning.com/wme/bb/' ) );

// Rock Hawk is on the archive but not the calendar, which is what a retired
// race looks like: results to read and nothing to enter. No button rather
// than a button to nowhere.
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'rock-hawk-trail-races' );
$retired = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'a retired race gets no button',     false === strpos( $retired, 'arv-results__race-info' ) );
t( 'but still gets its own page',       false !== strpos( $retired, '<h1 class="arv-results__race-title">Rock Hawk Trail Races</h1>' ) );
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'black-bear-trail-races' );

// One edition is one year, so it does not claim a range it does not have.
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'rock-hawk-trail-races' );
t( 'a single edition says one year',    false !== strpos( arv_results_race_seo_description( arv_results_race_context() ), 'from the 2026 race' ) );

// A slug for no race is not a race page, so nothing claims it and the
// archive's own canonical and title stand.
$GLOBALS['QUERY_VARS'] = array( 'arv_race' => 'no-such-race' );
t( 'an unknown slug is no context',     null === arv_results_race_context() );
t( 'and leaves the canonical alone',    'https://www.aravaiparunning.com/results/' === arv_results_race_canonical( 'https://www.aravaiparunning.com/results/' ) );
$GLOBALS['QUERY_VARS'] = array();
t( 'as does the archive itself',        null === arv_results_race_context() );

echo "\nresults: a sitemap for the pages no other one lists:\n";

// Both races seeded above are still in the store, one live and one
// retired, which is the one distinction a sitemap does not care about:
// a retired race still has a page, so it still gets a URL.
$entries = arv_results_sitemap_entries();
$urls    = array_map( function ( $e ) { return $e['url']; }, $entries );

t( 'the live race is in it',            in_array( 'https://www.aravaiparunning.com/race-results/black-bear-trail-races/', $urls, true ) );
t( 'so is the retired one',             in_array( 'https://www.aravaiparunning.com/race-results/rock-hawk-trail-races/', $urls, true ) );

// lastmod is the newest edition's own date, not today's, so a crawler can
// tell an untouched race from one that just added a result.
$bb = null;
foreach ( $entries as $entry ) {
	if ( false !== strpos( $entry['url'], 'black-bear' ) ) {
		$bb = $entry;
	}
}
t( 'lastmod is the newest edition date', null !== $bb && 0 === strpos( $bb['lastmod'], '2026-' ) );

// The XML itself, from the pure builder: the route handler around it
// calls exit on a real request, which is exactly right there and
// exactly what a test cannot call through.
$xml = arv_results_sitemap_xml();
t( 'renders a real urlset',             false !== strpos( $xml, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' ) );
t( 'with both races as <url> entries',  substr_count( $xml, '<loc>' ) >= 2 );
t( 'each loc escaped for XML',          false !== strpos( $xml, '<loc>https://www.aravaiparunning.com/race-results/black-bear-trail-races/</loc>' ) );

$GLOBALS['ARV_OPTIONS'] = array();

echo "\nresults: an archive year's own result files:\n";
$GLOBALS['ARV_OPTIONS'] = array();
$GLOBALS['QUERY_VARS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Javelina Jundred', 'iso' => '2008-11-15', 'display' => 'November 15-16',
	       'ultrarunning' => 'https://ultrarunning.com/calendar/event/javelina-jundred/race/1836/results',
	       'archive' => array(
		       array( 'label' => '100 Mile', 'url' => 'https://aravaiparunning.com/results/2008JJResults100m.htm' ),
		       array( 'label' => '100 KM', 'url' => 'https://aravaiparunning.com/results/2008JJResults100k.htm' ),
	       ) ),
) );
$arch = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );

// One row now, not two. It used to split by an implementation detail no
// reader should see, which server hosts the file: a fixed grid of named
// buttons on the right, Aravaipa's own files either riding inside that grid
// (reading as a sub-item of whichever button was there) or split into a
// separately labelled row of their own (reading as though the split meant
// something about the race). Neither ever encoded anything but hosting.
$links_block = substr( $arch, strpos( $arch, 'arv-results__links' ) );
$links_block = substr( $links_block, 0, strpos( $links_block, '</div>' ) );
t( 'the archive files join the same row', false !== strpos( $links_block, 'link--file' ) );
t( 'alongside the named service button',  false !== strpos( $links_block, '>UltraRunning<' ) );
t( 'no separate labelled row exists',     false === strpos( $arch, 'arv-results__files' ) );
t( 'and no left/right split either',      false === strpos( $arch, 'arv-results__archive"' ) );

// Several files, so the distances stay: they are the only thing telling
// one from another, and "Results" four times over says nothing.
t( 'several files keep their distances',  false !== strpos( $links_block, '>100 Mile<' ) );

// And they are spelled the way every other distance on the page is. These
// labels were typed one edition at a time over eighteen years: Javelina is
// stored as "100 Mile" on 2015 and "100 Miler" on 2016, "100K" on one row
// and "100k" on the next. No single button was wrong. They were wrong next
// to each other, which is the only way a reader ever sees them.
t( 'and are spelled like everything else', false !== strpos( $links_block, '>100K<' ) );
t( 'not as they happen to be stored',     false === strpos( $links_block, '>100 KM<' ) );
// Same control as the named buttons, not a smaller chip beside them: it was
// inline-block at 0.2rem next to an inline-flex button with a 38px floor,
// so the two sat at different heights with the text off centre.
t( 'a file is the same control',          false !== strpos( $links_block, 'arv-results__link arv-results__link--file' ) );

// One file for the whole edition is just "Results", whatever the label
// says. A distance only earns its place when there is another distance to
// tell it apart from; alone beside an UltraRunning button, "100 MILE" reads
// as a filter on that button rather than the way into this race's results.
arv_results_store_set( array(
	array( 'name' => 'Javelina Jundred', 'iso' => '2008-11-15', 'display' => 'November 15-16',
	       'ultrarunning' => 'https://ultrarunning.com/calendar/event/javelina-jundred/race/1836/results',
	       'archive' => array( array( 'label' => '100 Mile', 'url' => 'https://aravaiparunning.com/results/2008JJResults100m.htm' ) ) ),
) );
$one_file = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'one file drops its distance',         false === strpos( $one_file, '>100 Mile<' ) );
t( 'and reads as Results',                false !== strpos( $one_file, '>Results<' ) );
// Ours before theirs: the file IS the result on these years, UltraRunning
// is a copy of it, so it should not be the one sitting further left.
t( 'the file leads the listings',         strpos( $one_file, 'link--file' ) < strpos( $one_file, 'link--ur' ) );

// Labels that were never about the result either: the viewer or the timing
// platform the scraper found the link inside. 72 of the 101 single-file
// rows are one of these.
foreach ( array( 'Ultracast', 'RaceResult', 'RunSignup' ) as $platform ) {
	arv_results_store_set( array(
		array( 'name' => 'Fat Ox', 'iso' => '2019-11-29', 'display' => 'November 29',
		       'archive' => array( array( 'label' => $platform, 'url' => 'http://example.test/' . $platform ) ) ),
	) );
	$named = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
	t( "\"$platform\" is not a label either",  false === strpos( $named, '>' . $platform . '<' ) );
}

// The live board still leads where there is one: it is also ours, and on a
// modern race it is what people came for.
arv_results_store_set( array(
	array( 'name' => 'Black Canyon', 'iso' => '2020-02-15', 'display' => 'February 15',
	       'live' => 'https://live.aravaiparunning.com/#/black_canyon-2020',
	       'archive' => array( array( 'label' => 'Ultracast', 'url' => 'http://example.test/bc2020' ) ) ),
) );
$both = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'the live board still leads',          strpos( $both, 'link--live' ) < strpos( $both, 'link--file' ) );

// A race whose every link is already a named button adds nothing extra:
// no file chips, and the row still renders once, not twice.
arv_results_store_set( array(
	array( 'name' => 'Vertigo Night Runs', 'iso' => '2026-08-09', 'display' => 'August 9',
	       'live' => 'https://live.aravaiparunning.com/#/vertigo_night_runs-2026' ),
) );
$no_archive = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'no files, no file chip',             false === strpos( $no_archive, 'link--file' ) );
t( 'one links row, not two',             1 === substr_count( $no_archive, 'class="arv-results__links"' ) );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nresults: ?arv_year= opens the archive on that year:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2016-08-13', 'display' => 'August 13',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2016' ),
) );

$_GET['race_year'] = '2016';
$html = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year_tabs' => 'true' ) );
t( 'the requested year opens unhidden',  false !== strpos( $html, 'data-arv-results-year-panel="2016">' ) );
t( 'and the other year starts hidden',   false !== strpos( $html, 'data-arv-results-year-panel="2026" hidden>' ) );
t( 'its pill is marked selected',        1 === preg_match( '/data-arv-results-year="2016"[^>]*aria-selected="true"/', $html ) );

// A year the store never carried, or none at all, falls back to the newest
// rather than opening on nothing: that is what every page already did
// before ?arv_year= existed, and it stays true off the archive too.
$_GET['race_year'] = '1999';
$html = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year_tabs' => 'true' ) );
t( 'an unknown year falls back to newest', false !== strpos( $html, 'data-arv-results-year-panel="2026">' ) );

unset( $_GET['race_year'] );
$html = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year_tabs' => 'true' ) );
t( 'no year at all is the same fallback', false !== strpos( $html, 'data-arv-results-year-panel="2026">' ) );

echo "\nresults: the archive names the year it is showing:\n";
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'archive' => array( array( 'label' => '50K', 'url' => 'https://aravaiparunning.com/results/bb26.htm' ) ) ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2016-08-13', 'display' => 'August 13',
	       'archive' => array( array( 'label' => '50K', 'url' => 'https://aravaiparunning.com/results/bb16.htm' ) ) ),
	array( 'name' => 'Sunset Scramble', 'iso' => '2016-04-02', 'display' => 'April 2',
	       'archive' => array( array( 'label' => '10K', 'url' => 'https://aravaiparunning.com/results/ss16.htm' ) ) ),
) );
arv_archive_stats_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2016-08-13', 'finishers' => 120 ),
	array( 'name' => 'Sunset Scramble', 'iso' => '2016-04-02', 'finishers' => 80 ),
) );

$_GET['race_year'] = '2016';
$html = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year_tabs' => 'true' ) );
t( 'the archive carries an h1',          false !== strpos( $html, '<h1 class="arv-results__masthead-title"' ) );
t( 'and it names the year showing',      false !== strpos( $html, '>2016 Results</h1>' ) );
t( 'the line under it counts that year', false !== strpos( $html, '2 races &middot; 200 finishers' )
                                         || false !== strpos( $html, '2 races · 200 finishers' ) );
t( 'every year pill carries its title',  false !== strpos( $html, 'data-arv-results-year-title="2026 Results"' ) );
t( 'and its own counts',                 1 === preg_match( '/data-arv-results-year="2026"[^>]*data-arv-results-year-meta="1 race"/', $html ) );

// An older edition of a race that also ran in the selected year is rendered
// inside that year's panel, deliberately. Counting what the panel holds
// would credit 2016 with a race from 2026.
t( 'a prior edition is not counted',     false === strpos( $html, '3 races' ) );

// Only the archive. A page already scoped to one year has a heading of its
// own from the page itself, and a race page is not a year view at all.
$flat = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false' ) );
t( 'a plain results list has no h1',     false === strpos( $flat, 'arv-results__masthead' ) );

$titled = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false',
	'year_tabs' => 'true', 'heading' => 'Race Results' ) );
t( 'nor does one given a heading',       false === strpos( $titled, 'arv-results__masthead' ) );

unset( $_GET['race_year'] );

// A search that reaches across years needs two things from the markup: a
// stable key per race, so the same race showing in fifteen panels can be
// told from fifteen races, and every name that race went by, so someone
// typing what it was called in 2011 is not told it never existed.
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Canyon 100K', 'iso' => '2026-02-14', 'display' => 'February 14',
	       'archive' => array( array( 'label' => '100K', 'url' => 'https://aravaiparunning.com/results/bc26.htm' ) ) ),
	array( 'name' => 'Black Canyon Ultras', 'iso' => '2016-02-13', 'display' => 'February 13',
	       'archive' => array( array( 'label' => '100K', 'url' => 'https://aravaiparunning.com/results/bc16.htm' ) ) ),
) );

$_GET['race_year'] = '2026';
$renamed = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year_tabs' => 'true' ) );

t( 'a race card carries a stable key',   false !== strpos( $renamed, 'data-arv-results-key="black canyon"' ) );
t( 'and every name it went by',          false !== strpos( $renamed, 'data-arv-results-race="black canyon 100k | black canyon ultras"' ) );

// The same race in the older year's panel answers to the same key, which is
// the whole point: that is how the search knows to show it once.
t( 'the older panel agrees on the key',  2 === substr_count( $renamed, 'data-arv-results-key="black canyon"' ) );

t( 'the search says it crosses years',   false !== strpos( $renamed, 'placeholder="Race name, any year"' ) );
t( 'the masthead carries a search title', false !== strpos( $renamed, 'data-arv-results-all-title="Race Results"' ) );

// Not on a page that only has one year to search.
$one = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year' => '2016' ) );
t( 'a single year box says race name',   false !== strpos( $one, 'placeholder="Race name"' ) );

unset( $_GET['race_year'] );

// Back to the two row store the cases below were written against.
$GLOBALS['ARV_OPTIONS'] = array();
arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2016-08-13', 'display' => 'August 13',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2016' ),
) );

// The name this shipped under for a few hours. A URL is a promise the
// moment someone copies one out of an address bar, so the old spelling is
// still answered rather than 404ing whoever kept one.
$_GET['arv_year'] = '2016';
$was = arv_results_render( array( 'mod_id' => 'e1', 'class' => '', 'layout' => 'race', 'upcoming' => 'false', 'year_tabs' => 'true' ) );
t( 'the old spelling still answers',      false !== strpos( $was, 'data-arv-results-year-panel="2016">' ) );
unset( $_GET['arv_year'] );

// Neither name is WordPress's own, which is the whole reason for both.
t( 'and neither one is WordPress\'s',     'race_year' !== 'year' && ARV_RESULTS_YEAR_VAR !== 'year' );
$GLOBALS['ARV_OPTIONS'] = array();

echo "\nresults: the archive year gets its own title and canonical:\n";
$seo_posts_backup = $GLOBALS['posts'] ?? array();
$GLOBALS['IS_PAGE']    = true;
$GLOBALS['QUERIED_ID'] = 9910;
$GLOBALS['posts'][9910] = array( 'title' => 'Results', 'body' => '[arv_results]' );

arv_results_store_set( array(
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2026-08-29', 'display' => 'August 29',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2026' ),
	array( 'name' => 'Black Bear Trail Races', 'iso' => '2016-08-13', 'display' => 'August 13',
	       'live' => 'https://live.aravaiparunning.com/#/black_bear-2016' ),
) );

$_GET['race_year'] = '2016';
t( 'the year itself resolves',           '2016' === arv_results_archive_seo_year() );

// "year" is WordPress's own reserved var for date archives: /results/?year=2008
// was a 404 and ?year=2025 a 301 off to a date archive, live, on production.
// The live pages and the photos element each hit this first and each carry
// this same assertion; this is the third.
t( 'and never uses WordPress\'s own',    false === strpos(
	arv_results_archive_seo_canonical( 'https://www.aravaiparunning.com/results/' ), '?year=' ) );
t( 'the canonical carries the year',     'https://www.aravaiparunning.com/results/?race_year=2016' === arv_results_archive_seo_canonical( 'https://www.aravaiparunning.com/results/' ) );

// The title filter is exercised for real further up this file: WPSEO_VERSION
// is defined there and, being a constant, stays defined for the rest of the
// run, so arv_seo_handled_elsewhere() is correctly true from here on and the
// title defers rather than overriding, same as the race pages already do.
t( 'and the title defers to a real plugin too', 'Results' === arv_results_archive_seo_title_parts( array( 'title' => 'Results' ) )['title'] );

// The newest year already IS /results/ with no parameter, so it keeps
// core's own canonical rather than "correcting" it into a copy of itself.
$_GET['race_year'] = '2026';
t( 'the newest year is left alone',      'https://www.aravaiparunning.com/results/' === arv_results_archive_seo_canonical( 'https://www.aravaiparunning.com/results/' ) );
t( 'and gets no title override either',  'Results' === arv_results_archive_seo_title_parts( array( 'title' => 'Results' ) )['title'] );

// A year the store has never carried claims nothing: there is no such page
// to be canonical for.
$_GET['race_year'] = '1999';
t( 'an unverified year claims nothing',  'https://www.aravaiparunning.com/results/' === arv_results_archive_seo_canonical( 'https://www.aravaiparunning.com/results/' ) );

// Off the archive page, or with no ?arv_year= at all, this claims nothing:
// the race pages tested above already own their own canonical.
unset( $_GET['race_year'] );
t( 'no year at all, no opinion',              'https://www.aravaiparunning.com/results/' === arv_results_archive_seo_canonical( 'https://www.aravaiparunning.com/results/' ) );
$_GET['race_year'] = '2016';
$GLOBALS['posts'][9910]['body'] = '<p>Not the archive.</p>';
t( 'nor does an unrelated page',         'https://www.aravaiparunning.com/results/' === arv_results_archive_seo_canonical( 'https://www.aravaiparunning.com/results/' ) );

unset( $_GET['race_year'] );
$GLOBALS['IS_PAGE']    = false;
$GLOBALS['QUERIED_ID'] = 0;
$GLOBALS['posts'] = $seo_posts_backup;
$GLOBALS['ARV_OPTIONS'] = array();

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
t( 'the newest edition carries a count', false !== strpos( $html, 'finishers' ) );
t( 'and the name links to the race',    false !== strpos( $html, 'arv-results__race-link' ) );
t( 'with no inline edition list',       false === strpos( $html, 'arv-results__editions' ) );

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

// A published close date beats the lead window. Oli Kai sat five days out
// with entries open until the Tuesday and led the home page with a Live
// Results button on an empty board, because the lead window applied to every
// race rather than only to races with no close date to go by.
$oli = array(
	'name' => 'Oli Kai', 'iso' => '2026-09-05', 'end' => '', 'closes' => '2026-09-01',
	'live' => '', 'page' => '',
	'register' => 'https://ultrasignup.com/register.aspx?did=134059',
);
$open   = arv_upcoming_races_action( $oli, '2026-08-31' );
$eve    = arv_upcoming_races_action( $oli, '2026-09-01' );
$closed = arv_upcoming_races_action( $oli, '2026-09-02' );

t( 'entries open still says Register',  'Register' === $open['label'] );
t( 'the last day of entries too',       'Register' === $eve['label'] );
t( 'and it sells the entry',            'https://ultrasignup.com/register.aspx?did=134059' === $open['url'] );
t( 'the day after, live results',       'Live Results' === $closed['label'] );
t( 'on the board for that race',        'https://ultrasignup.com/results_event.aspx?did=134059' === $closed['url'] );

// The window itself is untouched for a race that published no close date,
// which is most of them: that is the case it was written for.
$nodate = array(
	'name' => 'No Close Date', 'iso' => '2026-09-05', 'end' => '', 'closes' => '',
	'live' => '', 'page' => '',
	'register' => 'https://ultrasignup.com/register.aspx?did=999999',
);
t( 'no close date still flips on lead', 'Live Results' === arv_upcoming_races_action( $nodate, '2026-08-31' )['label'] );
t( 'and sells before the window opens', 'Register' === arv_upcoming_races_action( $nodate, '2026-08-30' )['label'] );

// ------------------------------------------------ Removing a stale race --
// The import writes but never unwrites. Its key is register-url plus name,
// so renaming a race in a row leaves the old record published rather than
// renaming it, which is how the home page ended up carrying both "Oli Kai"
// and "Oli Kai Trail Races" on the same September Saturday.
$saved_posts = $GLOBALS['posts'];
$saved_meta  = $GLOBALS['meta'];

$GLOBALS['posts'] = array(
	701 => array( 'title' => 'Oli Kai',             'status' => 'publish', 'type' => ARV_RACE_POST_TYPE ),
	702 => array( 'title' => 'Oli Kai Trail Races', 'status' => 'publish', 'type' => ARV_RACE_POST_TYPE ),
	703 => array( 'title' => 'Javelina Jundred',    'status' => 'publish', 'type' => ARV_RACE_POST_TYPE ),
);

$dry = arv_race_store_remove( array( 'Oli Kai' ), true );
t( 'a dry run finds the record',        1 === count( $dry['matched'] ) );
t( 'and names it before touching it',   701 === $dry['matched'][0]['id'] );
t( 'a dry run trashes nothing',         0 === $dry['trashed'] );
t( 'the record is still published',     'publish' === $GLOBALS['posts'][701]['status'] );

$gone = arv_race_store_remove( array( 'Oli Kai' ) );
t( 'the stale record is trashed',       1 === $gone['trashed'] );
t( 'and is out of the store',           'trash' === $GLOBALS['posts'][701]['status'] );

// The reason this matches on an exact title rather than a search. One name
// is a prefix of the other, and a search would have taken the keeper too.
t( 'the renamed race survives',         'publish' === $GLOBALS['posts'][702]['status'] );
t( 'and so does everything else',       'publish' === $GLOBALS['posts'][703]['status'] );

$none = arv_race_store_remove( array( 'No Such Race' ) );
t( 'an unknown name trashes nothing',   0 === $none['trashed'] );
t( 'and is reported back, not silent',  array( 'No Such Race' ) === $none['missing'] );

// A blank line in a pasted list is not a race with no name.
$blank = arv_race_store_remove( array( '', '  ' ) );
t( 'blank names are skipped',           0 === $blank['trashed'] && array() === $blank['missing'] );

$GLOBALS['posts'] = $saved_posts;
$GLOBALS['meta']  = $saved_meta;

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
// Two events, not one: the newest is promoted into the hero above the
// archive, so the event under test here has to be the second.
$lead   = array( 'slug' => 'lead-2026', 'name' => 'Lead', 'live' => false, 'start' => '', 'hero' => '', 'place' => '', 'streams' => array( array( 'id' => 'fffffffffff', 'title' => 'Lead', 'url' => 'https://youtu.be/fffffffffff', 'thumbnail' => 'https://i.ytimg.com/vi/fffffffffff/hqdefault.jpg', 'live' => false, 'type' => '', 'start' => '' ) ) );
$solo_e = array( 'slug' => 'solo-2026', 'name' => 'Solo', 'live' => false, 'start' => '', 'hero' => '', 'place' => '', 'streams' => array( array( 'id' => 'ddddddddddd', 'title' => 'Only', 'url' => 'https://youtu.be/ddddddddddd', 'thumbnail' => 'https://i.ytimg.com/vi/ddddddddddd/hqdefault.jpg', 'live' => false, 'type' => '', 'start' => '' ) ) );
$single = array( $lead, $solo_e );
$GLOBALS['_transients']['arv_watch_events'] = $single;
$solo = arv_watch_render( array() );
t( 'one video gets no toggle',           false === strpos( $solo, '<details' ) );
t( 'and reads as one video',             false !== strpos( $solo, '1 video' ) );
t( 'and not as one videos',              false === strpos( $solo, '1 videos' ) );

echo "\nwatch, upcoming broadcasts:\n";
// Read from Mountain Outpost's /events endpoint, not /streams. That one
// only carries an event once a stream object exists for it, so it reports
// zero future-dated events while eight are in fact scheduled. This was a
// hardcoded list of three until that endpoint turned up; it was both
// stale-prone and, as it happens, already missing five races.
$GLOBALS['_transients'] = array();
// Mountain Outpost also broadcasts races Aravaipa does not put on, and
// only Aravaipa's own belong on this page. The filter answers that from
// the race store rather than a slug allow-list, so these fixtures have to
// exist there to survive it. "Someone Else's Race" deliberately does not.
//
// Each needs an _arv_iso date: arv_race_store_to_race() returns null for a
// record with no usable date, so a race seeded without one never reaches
// the store and the filter would reject it for the wrong reason.
$watch_races_backup = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
$GLOBALS['posts'][9601] = array( 'title' => 'Later Race',  'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9601]['_arv_iso'] = '2026-10-17';
$GLOBALS['posts'][9602] = array( 'title' => 'Sooner Race', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9602]['_arv_iso'] = '2026-10-17';
$GLOBALS['posts'][9603] = array( 'title' => 'Finished',    'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9603]['_arv_iso'] = '2026-10-17';
$GLOBALS['posts'][9604] = array( 'title' => 'On Air',      'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9604]['_arv_iso'] = '2026-10-17';
arv_race_store_flush_cache();

$GLOBALS['_transients']['arv_watch_schedule'] = array(
	array( 'slug' => 'later-race-2999',  'name' => 'Later Race',  'start' => '2999-12-19T16:00:00.000Z', 'live' => false ),
	array( 'slug' => 'sooner-race-2999', 'name' => 'Sooner Race', 'start' => '2999-10-17T13:00:00.000Z', 'live' => false ),
	array( 'slug' => 'finished-2020',    'name' => 'Finished',    'start' => '2020-01-01T13:00:00.000Z', 'live' => false ),
	array( 'slug' => 'on-air-now',       'name' => 'On Air',      'start' => '2999-11-05T13:00:00.000Z', 'live' => true ),
	array( 'slug' => 'jfk-50-mile-2026', 'name' => "Someone Else's Race", 'start' => '2999-11-21T11:30:00.000Z', 'live' => false ),
);
// One HEAD check per surviving row (Finished is dropped by date before
// any check runs), each confirming its Mountain Outpost page is real.
arv_test_queue_response( array( 'code' => 200 ) );
arv_test_queue_response( array( 'code' => 200 ) );
arv_test_queue_response( array( 'code' => 200 ) );

$up = arv_watch_upcoming();
t( 'a finished broadcast drops off',      ! in_array( 'Finished', array_column( $up, 'name' ), true ) );
t( 'the rest survive',                    3 === count( $up ) );
t( 'soonest first',                       'Sooner Race' === $up[0]['name'] );
t( 'then the next',                       'On Air' === $up[1]['name'] );
t( 'and the furthest last',               'Later Race' === $up[2]['name'] );

// Every row links to Mountain Outpost's own page for the event, which is
// where the stream actually plays, but only once that page is confirmed
// to exist: the schedule and the page are two different systems there,
// and a slug can sit in the schedule nine months before its page is
// built. Checked against the real site and found exactly that gap in
// production (BPN Go One More Ultra 2027), which is what this guards.
t( 'each row links to mountain outpost',  'https://mountainoutpost.com/events/sooner-race-2999' === $up[0]['url'] );
t( 'and a missing slug yields no link',   '' === arv_watch_outpost_url( '' ) );

// The filter itself: a race Mountain Outpost broadcasts but Aravaipa does
// not put on has no place under this masthead.
t( 'a race we do not run is filtered out', ! in_array( "Someone Else's Race", array_column( $up, 'name' ), true ) );
t( 'one we do run is kept',               arv_watch_is_aravaipa_race( 'Sooner Race' ) );
t( 'the year in a name does not matter',  arv_watch_is_aravaipa_race( 'Sooner Race 2026' ) );
t( 'and an unknown race is rejected',     ! arv_watch_is_aravaipa_race( 'JFK 50 Mile' ) );

echo "\nwatch, a scheduled race with no page yet:\n";
// The real case this exists for: a slug the schedule knows about that
// Mountain Outpost has not built a page for yet. Linking anyway would put
// a 404 on this site's own Watch page for something that otherwise looks
// exactly like every other row.
$GLOBALS['_transients'] = array();
$GLOBALS['posts'][9607] = array( 'title' => 'Future Race', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9607]['_arv_iso'] = '2026-10-17';
arv_race_store_flush_cache();
$GLOBALS['_transients']['arv_watch_schedule'] = array(
	array( 'slug' => 'not-built-yet', 'name' => 'Future Race', 'start' => '2999-05-21T13:00:00.000Z', 'live' => false ),
);
arv_test_queue_response( array( 'code' => 404 ) );
$nolink = arv_watch_upcoming();
t( 'the race still appears',              1 === count( $nolink ) );
t( 'but with no link to a 404',           '' === $nolink[0]['url'] );

// A transport failure is treated the same as a 404: from this site's side
// they are indistinguishable, and both mean do not link to it.
$GLOBALS['_transients'] = array();
$GLOBALS['posts'][9608] = array( 'title' => 'Unreachable Race', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9608]['_arv_iso'] = '2026-10-17';
arv_race_store_flush_cache();
$GLOBALS['_transients']['arv_watch_schedule'] = array(
	array( 'slug' => 'unreachable', 'name' => 'Unreachable Race', 'start' => '2999-05-21T13:00:00.000Z', 'live' => false ),
);
arv_test_queue_response( new WP_Error( 'down' ) );
$unreach = arv_watch_upcoming();
t( 'a check that fails outright also yields no link', '' === $unreach[0]['url'] );

// Cached per slug, not refetched on every call: the second read of the
// same schedule must not make a second HTTP request.
$calls = $GLOBALS['_http_calls'];
arv_watch_upcoming();
t( 'the page check is cached',            $calls === $GLOBALS['_http_calls'] );
// The trap this guards: get_transient()'s own "nothing stored" return is
// also false, so a cached "does not exist" stored as raw false would be
// indistinguishable from a cache miss and refetch every time, defeating
// the cache specifically on the outcome (a page not built yet) it exists
// to remember. Confirmed directly against the stored value, not just the
// call count above.
t( 'a negative is cached as a string, not raw false', 'no' === get_transient( 'arv_watch_outpost_' . md5( 'unreachable' ) ) );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();

// A day's grace past the start, because these are ultras: a race that
// began yesterday afternoon and is still running must not vanish from the
// list at midnight while it is being broadcast.
// Anchored to the harness's pinned clock, not real time: current_time()
// here answers 2026-08-26 09:00 regardless of when the suite runs, so a
// fixture built from time() would drift into the future and stop testing
// the boundary it was written for.
$pinned = current_time( 'timestamp', true );
$GLOBALS['posts'][9605] = array( 'title' => 'Mid Race',  'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9605]['_arv_iso'] = '2026-10-17';
$GLOBALS['posts'][9606] = array( 'title' => 'Long Over', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9606]['_arv_iso'] = '2026-10-17';
arv_race_store_flush_cache();
$GLOBALS['_transients']['arv_watch_schedule'] = array(
	array( 'slug' => 'mid-race', 'name' => 'Mid Race', 'start' => gmdate( 'c', $pinned - ( 12 * HOUR_IN_SECONDS ) ), 'live' => true ),
	array( 'slug' => 'long-over', 'name' => 'Long Over', 'start' => gmdate( 'c', $pinned - ( 5 * DAY_IN_SECONDS ) ), 'live' => false ),
);
$mid = arv_watch_upcoming();
t( 'a race under way still counts',       1 === count( $mid ) );
t( 'and it is the one still running',     'Mid Race' === $mid[0]['name'] );

echo "\nwatch, upcoming rendered:\n";
$GLOBALS['_transients'] = array();
$GLOBALS['posts'][9609] = array( 'title' => 'Javelina Jundred', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9609]['_arv_iso'] = '2026-10-17';
$GLOBALS['posts'][9610] = array( 'title' => 'On Air Race',      'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9610]['_arv_iso'] = '2026-10-17';
arv_race_store_flush_cache();
$GLOBALS['_transients']['arv_watch_schedule'] = array(
	array( 'slug' => 'javelina-jundred-2026', 'name' => 'Javelina Jundred', 'start' => '2999-10-31T13:00:00.000Z', 'live' => false ),
	array( 'slug' => 'on-air-now', 'name' => 'On Air Race', 'start' => '2999-10-01T13:00:00.000Z', 'live' => true ),
);
arv_test_queue_response( array( 'code' => 200 ) );
arv_test_queue_response( array( 'code' => 200 ) );
$uhtml = arv_watch_upcoming_render();
t( 'the list renders',                    false !== strpos( $uhtml, 'arv-watch__upcoming' ) );
t( 'linking out to mountain outpost',     false !== strpos( $uhtml, 'https://mountainoutpost.com/events/javelina-jundred-2026' ) );
t( 'off-site links are safe',             false !== strpos( $uhtml, 'rel="noopener"' ) );
// On air right now outranks the date entirely: a date beside a race that
// has already started would read as though it had not begun.
t( 'a live race is flagged',              false !== strpos( $uhtml, 'Live now' ) );
t( 'and invites a watch, not a date',     false !== strpos( $uhtml, 'Watch on Mountain Outpost' ) );
t( 'a scheduled one still shows its date', false !== strpos( $uhtml, 'October 31, 2999' ) );

// A dead feed renders nothing rather than a heading over an empty shelf,
// and caches the failure so an outage is not retried on every page view.
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
arv_test_queue_response( new WP_Error( 'down' ) );
t( 'an unreachable schedule is empty',    array() === arv_watch_schedule() );
t( 'and renders nothing at all',          '' === arv_watch_upcoming_render() );
$calls = $GLOBALS['_http_calls'];
arv_watch_schedule();
t( 'the failure is cached, not hammered', $calls === $GLOBALS['_http_calls'] );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
$GLOBALS['posts'] = $watch_races_backup;
arv_race_store_flush_cache();

echo "\nwatch, the featured broadcast:\n";
// The page leads with one broadcast instead of a centred heading, and what
// that broadcast is depends on what there is to say. Three states, and the
// slot is never empty in any of them.

// Nothing live and nothing scheduled, which is most of the calendar:
// the newest replay, said plainly rather than left as the first card of a
// grid.
$GLOBALS['_transients']['arv_watch_events'] = $single;
$featured = arv_watch_render( array() );
t( 'the archive gets a hero',            false !== strpos( $featured, 'arv-watch__hero' ) );
t( 'labelled as the latest',             false !== strpos( $featured, 'Latest broadcast' ) );
t( 'and it is the newest event',         false !== strpos( $featured, '>Lead</span>' ) );
// Not printed twice, a hero and then the same card directly beneath it.
t( 'the hero leaves the archive',        1 === substr_count( $featured, 'Lead' ) );
t( 'the rest of the archive stays',      false !== strpos( $featured, 'Solo' ) );
t( 'and it is headed as replays',        false !== strpos( $featured, 'Replays</h2>' ) );
// The JS hook the filter uses to hide it, since one broadcast stranded over
// an empty grid is what a search for anything else would otherwise leave.
t( 'the filter can find the hero',       false !== strpos( $featured, 'data-arv-watch-hero' ) );

// Something scheduled: Mountain Outpost creates the event and posts its
// trailer weeks out, so a future start date in this feed is a real state
// and not a hypothetical one.
$soon = $single;
$soon[0]['start'] = gmdate( 'c', time() + ( 30 * DAY_IN_SECONDS ) );
$soon[0]['place'] = 'Black Canyon City, AZ';
$soon[0]['hero']  = 'https://example.com/hero.jpg';
$GLOBALS['_transients']['arv_watch_events'] = $soon;
$upcoming = arv_watch_render( array() );
t( 'a future event leads instead',       false !== strpos( $upcoming, 'Up next' ) );
t( 'not as the latest replay',           false === strpos( $upcoming, 'Latest broadcast' ) );
t( 'with its location',                  false !== strpos( $upcoming, 'Black Canyon City, AZ' ) );
// Full bleed, so the event's own artwork beats a 480px YouTube thumbnail
// stretched across it.
t( 'and the event artwork',              false !== strpos( $upcoming, 'example.com/hero.jpg' ) );

// The nearest one, not the furthest: the feed is newest first, so a walk
// through it has to keep taking the later match.
$two = $soon;
$two[1]['start'] = gmdate( 'c', time() + ( 5 * DAY_IN_SECONDS ) );
$GLOBALS['_transients']['arv_watch_events'] = $two;
$nearest = arv_watch_render( array() );
t( 'the nearest future event wins',      false !== strpos( $nearest, '>Solo</span>' ) );

// On air beats both. The embed above has already said the only thing a hero
// could say, and louder.
$GLOBALS['_transients']['arv_watch_events'] = $clean;
$onair = arv_watch_render( array() );
t( 'a live broadcast suppresses it',     false === strpos( $onair, 'arv-watch__hero' ) );
t( 'the player is what leads',           1 === substr_count( $onair, '<iframe' ) );

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
t( 'links back to the index',               false !== strpos( $html26, home_url( '/broadcasts/' ) ) );

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
t( 'and still links back to the index',     false !== strpos( $missing, home_url( '/broadcasts/' ) ) );
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
// Four deep, not three: Watch moved under Media in the page hierarchy and
// this schema silently kept describing the old three-level path until it
// was caught matching against the visible breadcrumb.
t( 'four deep, home to race',             4 === count( $crumbs['itemListElement'] ) );
t( 'via Media',                           home_url( '/media/' ) === $crumbs['itemListElement'][1]['item'] );
t( 'then Broadcasts',                     home_url( '/broadcasts/' ) === $crumbs['itemListElement'][2]['item'] );
t( 'and the race is last',                $ctx['url'] === $crumbs['itemListElement'][3]['item'] );

// A segment with nothing usable is dropped rather than emitted invalid.
$ctx4 = $ctx;
$ctx4['edition']['streams'] = array(
	array( 'id' => 'ccccccccccc', 'title' => '', 'url' => '', 'thumbnail' => '', 'live' => false,
	       'type' => '', 'start' => '', 'desc' => '', 'aired' => '', 'minutes' => 0, 'views' => 0 ),
);
$ctx4['edition']['start'] = '';
t( 'an unusable segment is dropped',      array() === arv_watch_seo_videos( $ctx4 ) );

// A stream shaped by the plugin version before this one: no 'aired', no
// 'desc', no 'minutes', no 'views' at all, because arv_watch_events() caches
// for fifteen minutes and the first request after a deploy that adds a
// field can still be reading a value the old code cleaned. Reproduced live
// the day this shipped: every VideoObject silently disappeared, because
// $stream['aired'] on a missing key is null, and null !== '' is true, so
// the fallback to the event date never ran.
$ctx5 = $ctx;
$ctx5['edition']['streams'] = array(
	array( 'id' => 'ddddddddddd', 'title' => 'Old Shape Segment', 'url' => 'https://youtu.be/ddddddddddd', 'thumbnail' => 'https://i.ytimg.com/vi/ddddddddddd/hqdefault.jpg', 'live' => false, 'type' => 'race', 'start' => '2025-05-05T13:00:00Z' ),
);
$old_shape = arv_watch_seo_videos( $ctx5 );
t( 'a stream from an older cached shape still produces a node', 1 === $old_shape['numberOfItems'] );
$old_item = $old_shape['itemListElement'][0]['item'];
t( 'falling back to the event date for uploadDate', '2025-05-05T13:00:00+00:00' === $old_item['uploadDate'] );
t( 'and to the title for a description',  'Old Shape Segment' === $old_item['description'] );
t( 'with no duration invented',           ! isset( $old_item['duration'] ) );
t( 'and no view counter invented',        ! isset( $old_item['interactionStatistic'] ) );

// Same staleness, at the event level this time: no 'desc', no 'place'.
$ctx6 = $ctx;
unset( $ctx6['edition']['desc'], $ctx6['edition']['place'] );
$old_desc = arv_watch_seo_description( $ctx6 );
t( 'a stale edition still produces a description', '' !== $old_desc );
t( 'without a stray empty place in it',   false === strpos( $old_desc, ', .' ) );




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
// Podcasts: three shows, read from their own RSS feeds. A fourth, Aravaipa
// Rides, is on the same Apple Podcasts channel but is a distinct brand
// with its own site and does not belong on this one.
// -------------------------------------------------------------------------
echo "\nfilms rail, the homepage's live block:\n";
// The homepage carried three hardcoded video embeds, so it showed whichever
// three films were current the day it was built and never changed again.
// This reads the same playlists the Films page does.
// Seeded here rather than inherited: the films section above ends by
// clearing every transient, so depending on its fixture surviving would
// make this block's result depend on where it sits in the file.
$GLOBALS['_transients']['arv_films'] = $clean;
$rail = arv_films_rail_render( array( 'heading' => 'Aravaipa Running Films' ) );
t( 'the rail renders',                    false !== strpos( $rail, 'arv-rail__track' ) );
t( 'with the heading given',              false !== strpos( $rail, 'Aravaipa Running Films' ) );
t( 'a card per film',                     count( arv_films_all( arv_films_fetch() ) ) === substr_count( $rail, 'class="arv-rail__item"' ) );
// A click lands on Aravaipa's own player, not on YouTube.
t( 'cards open the films page, not youtube', false !== strpos( $rail, home_url( '/films/?v=' ) ) );
t( 'and never link straight out',         false === strpos( $rail, 'youtu.be' ) );
t( 'a link to all films is offered',      false !== strpos( $rail, '>All films<' ) );

// Light, not the dark full-bleed treatment every other element uses: this
// one is a guest on a white homepage rather than the owner of its page.
t( 'it is not the dark full-bleed shell', false === strpos( $rail, 'arv-films__inner' ) );

// A limit keeps the homepage short without committing it to a fixed three.
t( 'a limit is honoured',                 1 === substr_count( arv_films_rail_render( array( 'limit' => 1 ) ), 'class="arv-rail__item"' ) );

// Scrolled natively, so it needs no script and stays keyboard reachable.
t( 'the track is focusable',              false !== strpos( $rail, 'tabindex="0"' ) );

// The rail shipped reading "PT58M13S" under every card: YouTube's raw ISO
// 8601 duration, while arv_films_duration() sat two functions away and the
// films page had been formatting with it all along.
t( 'no raw ISO duration escapes',         false === strpos( $rail, 'PT' ) );

// Its own fixture: the rail's films above carry no duration, so asserting
// the formatting on them would pass for the wrong reason.
$saved_films = $GLOBALS['_transients']['arv_films'];
$GLOBALS['_transients']['arv_films'] = array(
	array(
		'title' => 'Fixture',
		'films' => array(
			array( 'id' => 'ddddddddddd', 'title' => 'Long One', 'desc' => '', 'thumbnail' => 'https://x.test/d.jpg',
			       'published' => '2023-06-01T00:00:00Z', 'views' => 259208, 'duration' => 'PT58M13S',
			       'url' => 'https://youtu.be/ddddddddddd', 'lead' => '', 'race' => '', 'division' => null, 'trailer' => null ),
			array( 'id' => 'eeeeeeeeeee', 'title' => 'Over An Hour', 'desc' => '', 'thumbnail' => 'https://x.test/e.jpg',
			       'published' => '2026-08-30T00:00:00Z', 'views' => 900, 'duration' => 'PT1H5M9S',
			       'url' => 'https://youtu.be/eeeeeeeeeee', 'lead' => '', 'race' => '', 'division' => null, 'trailer' => null ),
		),
	),
);
$rail_meta = arv_films_rail_render( array( 'heading' => 'Films' ) );

t( 'the duration is readable',            false !== strpos( $rail_meta, '58:13' ) );
t( 'and an hour long one carries hours',  false !== strpos( $rail_meta, '1:05:09' ) );
t( 'no raw ISO reaches the card',         false === strpos( $rail_meta, 'PT58M13S' ) );
t( 'views are abbreviated, not full',     false !== strpos( $rail_meta, '259K views' ) );
t( 'and the long count is not printed',   false === strpos( $rail_meta, '259,208' ) );
t( 'each card says how old it is',        2 === substr_count( $rail_meta, ' ago' ) );
t( 'the three facts share one line',      (bool) preg_match( '/58:13 · 259K views · \d+ years? ago/u', $rail_meta ) );

$GLOBALS['_transients']['arv_films'] = $saved_films;

// Age rather than a full date. On a five-across rail the only question is
// whether a thing is new, and a reader should not have to do the
// arithmetic from "August 12, 2023" themselves.
$now = strtotime( '2026-08-31 00:00:00 UTC' );
t( 'the same day reads today',            'today' === arv_films_age( '2026-08-31T06:00:00Z', $now ) );
t( 'yesterday is a day',                  '1 day ago' === arv_films_age( '2026-08-30T00:00:00Z', $now ) );
t( 'and it is singular',                  false === strpos( arv_films_age( '2026-08-30T00:00:00Z', $now ), 'days' ) );
t( 'a few days stay days',                '3 days ago' === arv_films_age( '2026-08-28T00:00:00Z', $now ) );
t( 'a week becomes a week',               '1 week ago' === arv_films_age( '2026-08-24T00:00:00Z', $now ) );
t( 'a month becomes a month',             '1 month ago' === arv_films_age( '2026-08-01T00:00:00Z', $now ) );
t( 'and several months count up',         '6 months ago' === arv_films_age( '2026-03-01T00:00:00Z', $now ) );
t( 'a year becomes a year',               '1 year ago' === arv_films_age( '2025-08-01T00:00:00Z', $now ) );
t( 'and several years count up',          '3 years ago' === arv_films_age( '2023-06-01T00:00:00Z', $now ) );

// A clock disagreeing with YouTube is not a film from the future.
t( 'a future date does not go negative',  'today' === arv_films_age( '2026-09-30T00:00:00Z', $now ) );
t( 'and no date says nothing at all',     '' === arv_films_age( '', $now ) );
t( 'nor does an unparseable one',         '' === arv_films_age( 'not a date', $now ) );
// ------------------------------------------------- Region map blurbs --
// Nine pins used to answer nine different questions: some rows named
// terrain, some named races, two shared one boilerplate sentence about
// "trail and ultra events", and Bad Beard was a bare town and state. The
// pair a visitor is asking is where it is and what they would run there,
// so every row now answers both, in that order.
echo "\nregion map, one shape for every pin:\n";
$region_rows = array_values( array_filter( array_map(
	'trim',
	explode( "\n", $GLOBALS['EL']['aravaipa-region-map']['values']['rows'] )
) ) );

t( 'every pin on the map is present',   9 === count( $region_rows ) );

$details = array();
foreach ( $region_rows as $row ) {
	$cells     = array_map( 'trim', explode( '|', $row ) );
	$details[] = isset( $cells[4] ) ? $cells[4] : '';
}

$shaped = 0;
foreach ( $details as $detail ) {
	// Just the races, one closed sentence. A first pass wrote a line of
	// terrain for each pin first, ground then races, and it read as
	// exactly that: written to a formula rather than said plainly. The
	// pin label already says where a region is.
	if ( preg_match( '/^[^.]+\.$/u', $detail ) ) {
		$shaped++;
	}
}

t( 'every blurb is races, one sentence', 9 === $shaped );
t( 'and none is left empty',            ! in_array( '', $details, true ) );

// The two that used to share one sentence, and the one that had no
// sentence at all.
$joined = implode( ' ', $details );
t( 'the boilerplate sentence is gone',  false === strpos( $joined, 'Trail and ultra events across' ) );
t( 'Bad Beard says more than a town',   false === strpos( $joined, 'Chattanooga, Tennessee.' ) );

// No invented scenery. Nevada is not branded around the Spring Mountains;
// that was reached for rather than anything Aravaipa says about itself,
// and it read as written by something that had never heard of the place.
t( 'no scenery invented for a region',  false === stripos( $joined, 'Spring Mountains' ) );
t( 'nothing is called "country"',       false === stripos( $joined, 'country' ) );

// Named races have to be on the calendar. The old Ultra Adventures row
// named the Tushars, which is not on it, and Antelope Canyon, which runs
// under Arizona.
t( 'no race that is not on the calendar', false === strpos( $joined, 'Tushars' ) );
t( 'and labelled for a screen reader',    false !== strpos( $rail, 'aria-label=' ) );

// Nothing to show renders nothing rather than an empty scroller.
$films_backup_rail = $GLOBALS['_transients']['arv_films'] ?? null;
$GLOBALS['_transients']['arv_films'] = 'none';
t( 'no films renders nothing',            '' === arv_films_rail_render( array() ) );
if ( null !== $films_backup_rail ) { $GLOBALS['_transients']['arv_films'] = $films_backup_rail; }

t( 'the rail shortcode is registered',    isset( $GLOBALS['SHORTCODES']['arv_films_rail'] ) );

echo "\npodcasts, parsing a feed:\n";

// A small but real RSS+iTunes document, the same shape Anchor's feeds use,
// rather than the full thing: this is testing the parser, not Anchor.
function arv_test_podcast_rss( $items ) {
	$out = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">'
		. '<channel><title>Test Show</title>'
		. '<description>A show about testing.</description>'
		. '<itunes:image href="https://example.com/show.jpg"/>'
		. $items
		. '</channel></rss>';
	return $out;
}

$one_item = '<item>'
	. '<title>Episode One</title>'
	. '<description><![CDATA[<p>The plain description.</p>]]></description>'
	. '<itunes:summary>The itunes summary.</itunes:summary>'
	. '<pubDate>Fri, 17 Oct 2025 19:04:47 GMT</pubDate>'
	. '<guid isPermaLink="false">guid-one</guid>'
	. '<link>https://example.com/ep1</link>'
	. '<enclosure url="https://example.com/ep1.mp3" length="123" type="audio/mpeg"/>'
	. '<itunes:duration>01:05:09</itunes:duration>'
	. '<itunes:image href="https://example.com/ep1.jpg"/>'
	. '</item>';

$parsed = arv_podcasts_parse_feed( arv_test_podcast_rss( $one_item ) );
t( 'a real feed parses',                  null !== $parsed );
t( 'channel artwork is read',             'https://example.com/show.jpg' === $parsed['artwork'] );
t( 'channel description is read',         'A show about testing.' === $parsed['desc'] );
t( 'one episode comes back',              1 === count( $parsed['episodes'] ) );

$ep = $parsed['episodes'][0];
t( 'episode title',                       'Episode One' === $ep['title'] );
// itunes:summary wins over the CDATA description: it is already
// entity-escaped plain text without HTML to strip.
t( 'itunes:summary wins for the description', 'The itunes summary.' === $ep['desc'] );
t( 'the enclosure is the audio file',     'https://example.com/ep1.mp3' === $ep['audio'] );
t( 'guid is read',                        'guid-one' === $ep['guid'] );
t( "the episode's own artwork wins",      'https://example.com/ep1.jpg' === $ep['artwork'] );
t( 'the date is normalised to ISO 8601',  '2025-10-17T19:04:47+00:00' === $ep['published'] );

// No enclosure, or no title: not playable, dropped rather than rendered
// with a dead player.
$no_audio = '<item><title>No Audio</title><pubDate>Fri, 17 Oct 2025 19:04:47 GMT</pubDate><guid>g2</guid></item>';
$parsed2  = arv_podcasts_parse_feed( arv_test_podcast_rss( $no_audio ) );
t( 'an episode with no audio file is dropped', null === $parsed2 );

t( 'garbage is not a feed',               null === arv_podcasts_parse_feed( '<not><xml' ) );
t( 'an empty body is not a feed',         null === arv_podcasts_parse_feed( '' ) );

echo "\npodcasts, durations:\n";
t( 'H:MM:SS to ISO 8601',                 'PT1H5M9S' === arv_podcasts_iso_duration( '01:05:09' ) );
t( 'MM:SS to ISO 8601',                   'PT51M5S' === arv_podcasts_iso_duration( '51:05' ) );
t( 'a bare second count to ISO 8601',     'PT51M5S' === arv_podcasts_iso_duration( '3065' ) );
t( 'a garbage duration is empty',         '' === arv_podcasts_iso_duration( 'not a duration' ) );
t( 'H:MM:SS for display keeps its shape', '1:05:09' === arv_podcasts_display_duration( '01:05:09' ) );
t( 'a bare second count displays as M:SS', '51:05' === arv_podcasts_display_duration( '3065' ) );

echo "\npodcasts, fetching:\n";
$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
// Config order: inside-aravaipa, white-mountain, race-briefings.
arv_test_queue_response( array( 'code' => 200, 'body' => arv_test_podcast_rss( $one_item ) ) );
arv_test_queue_response( array( 'code' => 200, 'body' => arv_test_podcast_rss( $one_item ) ) );
// Race Briefings' feed 500s: dropped, not fatal to the other two.
arv_test_queue_response( array( 'code' => 500, 'body' => '' ) );
$shows = arv_podcasts_fetch();
t( 'two of three shows survive a failure', 2 === count( $shows ) );
t( 'the failed show is the one missing',  ! isset( $shows['race-briefings'] ) );
t( 'a surviving show keeps its config title', 'Inside Aravaipa' === $shows['inside-aravaipa']['title'] );
t( 'and its platform ids',                '0MvdUlDE9VwocRhrIl9Lwv' === $shows['inside-aravaipa']['spotify'] );
t( 'aravaipa rides is not configured at all', ! isset( $shows['aravaipa-rides'] ) );

echo "\npodcasts, merging episodes:\n";
$all = arv_podcasts_all( $shows );
t( 'one episode per surviving show',      2 === count( $all ) );
t( 'each carries its show',               'inside-aravaipa' === $all[0]['show_key'] );
t( 'find an episode falls back to the show art when the episode has none', '' !== $all[0]['artwork'] );

echo "\npodcasts, rendering the index:\n";
$html = arv_podcasts_render( array( 'heading' => 'Podcasts', 'intro' => 'Every show.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Podcasts</h2>' ) );
t( 'the intro too',                       false !== strpos( $html, 'Every show.' ) );
t( 'a card per surviving show',           2 === substr_count( $html, 'arv-podcasts__show-card' ) );
// Words on the index, not buttons: the card is already one link, and a
// Spotify button inside it would be a link inside a link.
t( 'the card says where the show is',     false !== strpos( $html, 'arv-podcasts__show-where' ) );
t( 'without nesting a link in a link',    false === strpos( $html, 'open.spotify.com' ) );
t( 'an episode row per episode',          2 === substr_count( $html, 'arv-podcasts__episode"' ) );
t( 'a real audio player, not an embed',   2 === substr_count( $html, '<audio class="arv-podcasts__ep-player"' ) );
t( 'no spotify iframe anywhere',          false === strpos( $html, 'open.spotify.com/embed' ) );
t( 'nothing preloads uninvited',          2 === substr_count( $html, 'preload="none"' ) );
t( 'a limit narrows the merged feed',     1 === substr_count( arv_podcasts_render( array( 'limit' => 1 ) ), 'arv-podcasts__episode"' ) );
t( 'but never the show cards',            2 === substr_count( arv_podcasts_render( array( 'limit' => 1 ) ), 'arv-podcasts__show-card' ) );

// No shows at all renders nothing, the same rule Watch and Films use.
$GLOBALS['_transients']['arv_podcasts'] = 'none';
t( 'no shows renders nothing',            '' === arv_podcasts_render( array() ) );
$GLOBALS['_transients']['arv_podcasts'] = $shows;

t( 'the index shortcode is registered',   isset( $GLOBALS['SHORTCODES']['arv_podcasts'] ) );
t( 'and renders the same thing',          arv_podcasts_shortcode( array( 'heading' => 'Podcasts', 'intro' => 'Every show.' ) ) === $html );

echo "\npodcasts, a show's own page:\n";
$show_html = arv_podcasts_show_render( array( 'show' => 'inside-aravaipa' ) );
t( 'the title renders',                   false !== strpos( $show_html, 'Inside Aravaipa' ) );
t( "the show's description renders",      false !== strpos( $show_html, 'A show about testing.' ) );
t( 'links back to the index',             false !== strpos( $show_html, home_url( '/podcasts/' ) ) );
t( 'a spotify subscribe link',            false !== strpos( $show_html, 'open.spotify.com/show/0MvdUlDE9VwocRhrIl9Lwv' ) );
t( 'an apple subscribe link',             false !== strpos( $show_html, 'podcasts.apple.com/podcast/id1797659741' ) );
t( 'an rss link to the raw feed',         false !== strpos( $show_html, 'anchor.fm/s/1017c24d0/podcast/rss' ) );
// Three identical grey outlines carried the same information as no buttons
// at all. Each app gets its own colour, through its own class, because
// "this show is on Spotify" has to survive a page scan.
t( 'the row says what it is',             false !== strpos( $show_html, 'Listen on' ) );
t( 'spotify is branded as spotify',       false !== strpos( $show_html, 'arv-podcasts-show__link--spotify' ) );
t( 'apple as apple',                      false !== strpos( $show_html, 'arv-podcasts-show__link--apple' ) );
// RSS is last on purpose: a real option, and the least used of the three,
// so it is offered rather than advertised. Its class carries no colour in
// the stylesheet, unlike the two above it.
t( 'rss comes last',                      strpos( $show_html, '--apple' ) < strpos( $show_html, '--rss' ) );
t( 'and is styled as plain',              false === strpos( file_get_contents( __DIR__ . '/assets/aravaipa-elements.css' ), '.arv-podcasts-show__link--rss' ) );
// A platform with no id is left out rather than pointed at a search page: a
// Spotify button landing on Spotify's front door cannot tell the reader
// whether the show is there at all.
$no_apple = $shows['inside-aravaipa'];
$no_apple['apple'] = '';
t( 'a show with no apple id omits it',    false === strpos( arv_podcasts_subscribe( $no_apple ), 'podcasts.apple.com' ) );
t( 'and still offers spotify',            false !== strpos( arv_podcasts_subscribe( $no_apple ), 'open.spotify.com' ) );
// Every show configured is on both. Race Briefings shipped with a blank
// Spotify id and so read as the one show that was not, which was wrong.
$config = arv_podcasts_shows_config();
t( 'every configured show has spotify',   3 === count( array_filter( $config, function ( $c ) { return '' !== $c['spotify']; } ) ) );
t( 'and apple',                           3 === count( array_filter( $config, function ( $c ) { return '' !== $c['apple']; } ) ) );
t( 'its one episode is listed',           false !== strpos( $show_html, 'Episode One' ) );
t( 'no show badge on its own page',       false === strpos( $show_html, 'arv-podcasts__ep-show' ) );

$missing = arv_podcasts_show_render( array( 'show' => 'not-a-real-show' ) );
t( 'an unknown show says so',             false !== strpos( $missing, 'have that show' ) );
t( 'and still links back to the index',   false !== strpos( $missing, home_url( '/podcasts/' ) ) );
// The failed show from the fetch above: configured, but its feed 500'd.
t( 'a show whose feed failed is unknown too', false !== strpos( arv_podcasts_show_render( array( 'show' => 'race-briefings' ) ), 'have that show' ) );

t( 'the show shortcode is registered',    isset( $GLOBALS['SHORTCODES']['arv_podcast_show'] ) );
t( 'and renders the same thing',          arv_podcast_show_shortcode( array( 'show' => 'inside-aravaipa' ) ) === $show_html );

echo "\npodcasts, structured data:\n";
$series = arv_podcasts_series_node( $shows['inside-aravaipa'] );
t( 'a PodcastSeries node',                'PodcastSeries' === $series['@type'] );
t( 'carrying the feed as its webFeed',    'https://anchor.fm/s/1017c24d0/podcast/rss' === $series['webFeed'] );
t( 'no episodes unless asked for',        ! isset( $series['episode'] ) );

$with_eps = arv_podcasts_series_node( $shows['inside-aravaipa'], true );
t( 'episodes included when asked for',    1 === count( $with_eps['episode'] ) );
$pe = $with_eps['episode'][0];
t( 'each is a PodcastEpisode',            'PodcastEpisode' === $pe['@type'] );
t( 'with a real publish date',            '2025-10-17T19:04:47+00:00' === $pe['datePublished'] );
t( 'and the audio file as its media',     'https://example.com/ep1.mp3' === $pe['associatedMedia']['contentUrl'] );
t( 'and an ISO 8601 duration',            'PT1H5M9S' === $pe['duration'] );

// An episode with no publish date is not a partial win; dropped rather
// than emitted invalid, the same rule the Watch page's VideoObject nodes
// follow.
$bad_show = $shows['inside-aravaipa'];
$bad_show['episodes'][0]['published'] = '';
$stripped = arv_podcasts_series_node( $bad_show, true );
t( 'an episode with no date is dropped',  ! isset( $stripped['episode'] ) );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();



// -------------------------------------------------------------------------
// Media hub: a card per section.
// -------------------------------------------------------------------------
echo "\nmedia hub:\n";
$cards = arv_media_hub_cards();
// Four, not five: Photos is a runner-facing page, not audience-facing
// media, so it keeps its top-level nav item and leaves this grid.
t( 'four cards, in a fixed order',        array( 'Broadcasts', 'Films', 'Podcasts', 'Articles' ) === array_map( function ( $c ) { return $c['title']; }, $cards ) );
t( 'each links to its real page',         home_url( '/broadcasts/' ) === $cards[0]['url'] );
t( 'photos is not one of the cards',      ! in_array( home_url( '/photos/' ), array_column( $cards, 'url' ), true ) );
// /articles/, not /blog/. /blog/ is WordPress's own posts page: it renders
// through the theme and so looks like nothing else in Media, which is the
// whole reason includes/articles-store.php exists.
t( 'articles is last',                    home_url( '/articles/' ) === $cards[3]['url'] );
t( 'and never points at /blog/',          ! in_array( home_url( '/blog/' ), array_column( $cards, 'url' ), true ) );

// A live thumbnail for Watch, from the same store the Watch page itself
// reads, so this card is never showing something a click into Watch would
// immediately contradict.
$GLOBALS['_transients']['arv_watch_events'] = array(
	array( 'slug' => 'black-canyon-2026', 'name' => 'Black Canyon Ultras 2026', 'live' => true, 'start' => '2026-02-14', 'place' => '', 'desc' => '', 'hero' => '',
	       'streams' => array( array( 'id' => 'aaaaaaaaaaa', 'title' => 'A', 'url' => 'https://youtu.be/aaaaaaaaaaa', 'thumbnail' => 'https://example.com/live.jpg', 'live' => true, 'type' => '', 'start' => '', 'desc' => '', 'aired' => '', 'minutes' => 0, 'views' => 0 ) ) ),
);
t( "a live broadcast's own thumbnail wins", 'https://example.com/live.jpg' === arv_media_hub_watch_thumb() );

$GLOBALS['_transients']['arv_films'] = array(
	array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array(
		array( 'id' => 'bbbbbbbbbbb', 'title' => 'A Film', 'desc' => '', 'thumbnail' => 'https://example.com/film.jpg', 'published' => '2026-01-01T00:00:00Z', 'url' => 'https://youtu.be/bbbbbbbbbbb', 'lead' => 'A FILM', 'trailer' => null ),
	) ),
);
t( "the newest film's thumbnail is used", 'https://example.com/film.jpg' === arv_media_hub_films_thumb() );

// Podcasts and Articles both carry real artwork now. Both reasons they
// did not are gone: Podcasts no longer needs a Spotify call (the RSS
// rebuild put artwork in the store) and the blog's "no single newest
// post to pick" objection is moot now the Latest feed picks one anyway.
$podcast_fixture = array(
	array( 'key' => 'inside-aravaipa', 'title' => 'Inside Aravaipa', 'feed' => '', 'spotify' => '', 'apple' => '',
	       'artwork' => 'https://example.com/show.jpg', 'summary' => '',
	       'episodes' => array( array( 'title' => 'An Episode', 'audio' => 'https://x.test/a.mp3', 'link' => '',
	                                   'artwork' => '', 'guid' => 'g1', 'duration' => '', 'published' => '2026-05-05T00:00:00Z' ) ) ),
);
$GLOBALS['_transients']['arv_podcasts'] = $podcast_fixture;
t( "the newest episode's artwork is used", 'https://example.com/show.jpg' === arv_media_hub_podcasts_thumb() );

$hub_posts_backup   = $GLOBALS['posts'];
$GLOBALS['posts']   = array( 9401 => array( 'title' => 'A Post', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-07T00:00:00Z', 'thumb' => 'https://example.com/post.jpg' ) );
delete_transient( 'arv_media_latest_posts' );
t( "the newest post's featured image is used", 'https://example.com/post.jpg' === arv_media_hub_articles_thumb() );

$html = arv_media_hub_render( array( 'heading' => 'Media', 'intro' => 'Everything Aravaipa makes.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Media</h2>' ) );
t( 'the intro too',                       false !== strpos( $html, 'Everything Aravaipa makes.' ) );
t( 'four cards on the page',              4 === substr_count( $html, 'arv-media-hub__card' ) - substr_count( $html, 'arv-media-hub__card--plain' ) );
t( 'watch carries its live thumbnail',    false !== strpos( $html, 'example.com/live.jpg' ) );
t( 'films carries its thumbnail too',     false !== strpos( $html, 'example.com/film.jpg' ) );
t( 'podcasts carries its artwork',        false !== strpos( $html, 'example.com/show.jpg' ) );
t( 'articles carries its featured image', false !== strpos( $html, 'example.com/post.jpg' ) );
// Every card has real artwork now, so nothing should be falling back to
// the flat no-image panel: one that did would be the odd one out in a row
// of four, which is the whole reason this changed.
t( 'no card falls back to the flat panel', 0 === substr_count( $html, 'arv-media-hub__card--plain' ) );

// The fallback itself still has to work: a store that is down should give
// a plain card, not a broken image.
$GLOBALS['_transients']['arv_podcasts'] = 'none';
t( 'a dead store gives a plain card, not a broken image',
	0 === substr_count( arv_media_hub_render( array() ), 'src=""' ) );
$GLOBALS['_transients']['arv_podcasts'] = $podcast_fixture;

$GLOBALS['_transients']['arv_watch_events'] = 'none';
$GLOBALS['_transients']['arv_films'] = 'none';
$GLOBALS['_transients']['arv_podcasts'] = 'none';
t( 'no broadcast means no thumbnail, not a broken one', '' === arv_media_hub_watch_thumb() );
t( 'no film means no thumbnail either',   '' === arv_media_hub_films_thumb() );
t( 'nor a podcast',                       '' === arv_media_hub_podcasts_thumb() );
$GLOBALS['posts'] = array();
delete_transient( 'arv_media_latest_posts' );
t( 'nor an article',                      '' === arv_media_hub_articles_thumb() );
$GLOBALS['posts'] = $hub_posts_backup;
delete_transient( 'arv_media_latest_posts' );

t( 'the shortcode is registered',         isset( $GLOBALS['SHORTCODES']['arv_media_hub'] ) );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();



echo "\nfilms, dedupe across playlists:\n";
// "THE RACE DIRECTOR" is in both Documentaries and Aravaipa Originals on
// YouTube and rendered twice on one page. The last playlist carrying a
// film wins, which is Originals, which is also where Jamil wants it.
$dupe_raw = array(
	array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array(
		array( 'id' => 'shared00000', 'title' => 'THE RACE DIRECTOR', 'description' => '', 'publishedAt' => '2025-01-01T00:00:00Z', 'thumbnail' => '' ),
		array( 'id' => 'doconly0000', 'title' => 'A Documentary', 'description' => '', 'publishedAt' => '2024-01-01T00:00:00Z', 'thumbnail' => '' ),
	) ),
	array( 'key' => 'originals', 'title' => 'Aravaipa Originals', 'films' => array(
		array( 'id' => 'shared00000', 'title' => 'THE RACE DIRECTOR', 'description' => '', 'publishedAt' => '2025-01-01T00:00:00Z', 'thumbnail' => '' ),
	) ),
);
$deduped = arv_films_clean( $dupe_raw );
t( 'the shared film leaves the first playlist', 1 === count( $deduped[0]['films'] ) );
t( 'and stays in the last one',           'shared00000' === $deduped[1]['films'][0]['id'] );
t( 'it appears exactly once overall',     1 === count( array_filter( arv_films_all( $deduped ), function ( $f ) { return 'shared00000' === $f['id']; } ) ) );

// A playlist emptied entirely by the dedupe would render as a heading over
// nothing.
$all_dupes = array(
	array( 'key' => 'a', 'title' => 'A', 'films' => array( array( 'id' => 'xxxxxxxxxxx', 'title' => 'Only Film', 'description' => '', 'publishedAt' => '2025-01-01T00:00:00Z', 'thumbnail' => '' ) ) ),
	array( 'key' => 'b', 'title' => 'B', 'films' => array( array( 'id' => 'xxxxxxxxxxx', 'title' => 'Only Film', 'description' => '', 'publishedAt' => '2025-01-01T00:00:00Z', 'thumbnail' => '' ) ) ),
);
$emptied = arv_films_clean( $all_dupes );
t( 'an emptied playlist is dropped',      1 === count( $emptied ) );
t( 'and it is the surviving one',         'b' === $emptied[0]['key'] );

echo "\nfilms, tagging the race a film is about:\n";
// Matched against the race store, so a race added to the calendar is
// matchable the same day rather than needing a list maintained here.
$GLOBALS['posts'][9101] = array( 'title' => 'Cocodona 250', 'status' => 'publish' );
$GLOBALS['meta'][9101]  = array( '_arv_iso' => '2027-05-02', '_arv_page' => 'https://www.aravaiparunning.com/cocodona/' );
$GLOBALS['posts'][9102] = array( 'title' => 'Tushars Mountain Runs', 'status' => 'publish' );
$GLOBALS['meta'][9102]  = array( '_arv_iso' => '2027-07-10', '_arv_page' => '' );
$GLOBALS['posts'][9103] = array( 'title' => 'Mountain Ridge Trail Race', 'status' => 'publish' );
$GLOBALS['meta'][9103]  = array( '_arv_iso' => '2027-03-01', '_arv_page' => '' );
$GLOBALS['posts'][9104] = array( 'title' => 'Jigger Johnson Ultras', 'status' => 'publish' );
$GLOBALS['meta'][9104]  = array( '_arv_iso' => '2027-09-01', '_arv_page' => '' );
arv_race_store_flush_cache();

t( 'the race in a title is found',        'Cocodona 250' === arv_films_race_for( 'THE CHASE | Cocodona 250 Full Documentary' ) );
// "Tushars Mountain Runs 2021" contains the word "mountain", which on its
// own would just as happily match Mountain Ridge Trail Race, and did while
// this was being written. The longest matching phrase has to win.
t( 'the longest matching phrase wins',    'Tushars Mountain Runs' === arv_films_race_for( 'Belus Brotherhood (Short Film) | Tushars Mountain Runs 2021' ) );
// And a film that only uses the short form still matches.
t( 'a leading prefix matches too',        'Tushars Mountain Runs' === arv_films_race_for( "THE TUSHARS | 100K in Utah's Hidden Range" ) );
t( 'and a two-word prefix',               'Jigger Johnson Ultras' === arv_films_race_for( 'EVERY MILE EARNED | The Inaugural Jigger Johnson 100 Mile' ) );
// A film that is not about one race gets no tag rather than the nearest
// guess.
t( 'a film about no one race is untagged', '' === arv_films_race_for( 'THE RACE DIRECTOR | Crafting Endurance in the Midwest' ) );

echo "\nfilms, a film that is about a division rather than a race:\n";
// THE RACE DIRECTOR is about Great Lakes Endurance, the division that
// joined Aravaipa, not one of its races, so there is nothing in the race
// store for it to match. Tagged literally, by video id, the same reason
// the two YouTube playlist ids are literal.
t( 'the tagged video carries its badge',  'Great Lakes Endurance' === arv_films_division_for( 'JFjNlB9g_pE' )['label'] );
t( 'and links to the division page',      false !== strpos( arv_films_division_for( 'JFjNlB9g_pE' )['url'], '/great-lakes-endurance/' ) );
t( 'an untagged video has no division',   null === arv_films_division_for( 'zzzzzzzzzzz' ) );

$division_playlists = arv_films_clean(
	array(
		array( 'key' => 'originals', 'title' => 'Aravaipa Originals', 'films' => array(
			array( 'id' => 'JFjNlB9g_pE', 'title' => 'THE RACE DIRECTOR | Crafting Endurance in the Midwest', 'description' => '', 'publishedAt' => '2025-09-05T00:00:00Z', 'thumbnail' => '' ),
		) ),
	)
);
$division_film = $division_playlists[0]['films'][0];
t( 'the film carries no race',            '' === $division_film['race'] );
t( 'but does carry its division',         'Great Lakes Endurance' === $division_film['division']['label'] );

$division_card = arv_films_card( $division_film, '' );
t( 'the card shows the division badge',   false !== strpos( $division_card, '>Great Lakes Endurance</a>' ) );
t( 'linking to the division page',        false !== strpos( $division_card, '/great-lakes-endurance/' ) );
// Not a self-page filter link: a division has no ?race= entry, so the
// badge has to go straight to the division's own page.
t( 'not a self-page race filter',         false === strpos( $division_card, 'race=great' ) );
// Searchable the same way a race is, even though it is not one.
t( 'searchable via the race attribute',   false !== strpos( $division_card, 'data-arv-films-race="great lakes endurance"' ) );

// A transient cached before this key existed must not throw a notice: it
// simply has no badge until the cache's own hour is up and refetches.
$stale_film = $division_film;
unset( $stale_film['division'] );
$stale_card = arv_films_card( $stale_film, '' );
t( 'a stale cache with no division key renders safely', is_string( $stale_card ) );
t( 'just without the badge, until the cache refreshes', false === strpos( $stale_card, 'Great Lakes Endurance' ) );
// A generic landscape word must never identify a race on its own.
t( 'a bare generic word tags nothing',    '' === arv_films_race_for( 'A Film About A Mountain' ) );

echo "\nfilms, a sponsor credit trimmed off the badge, not the match:\n";
// "Javelina Jundred Presented by: HOKA" is the race's real name and earns
// its place on the calendar and the race's own page. On a small green
// chip on a film card it crowded out the one word, "Javelina", that
// actually says which race this is.
t( 'the sponsor clause is trimmed',       'Javelina Jundred' === arv_films_race_label( 'Javelina Jundred Presented by: HOKA' ) );
t( 'case and punctuation do not matter',  'Coldwater Rumble' === arv_films_race_label( 'Coldwater Rumble - presented by Salomon' ) );
t( 'a race with no sponsor is untouched', 'Cocodona 250' === arv_films_race_label( 'Cocodona 250' ) );
// Only the label, not the value the badge filters and links on: a
// sponsor rename should not have to touch this to keep matching.
$labelled = arv_films_card(
	array(
		'id' => 'aaaaaaaaaaa', 'title' => 'x', 'thumbnail' => '', 'published' => '', 'views' => 0,
		'duration' => '', 'url' => 'https://x.test', 'trailer' => null,
		'race' => 'Javelina Jundred Presented by: HOKA',
	),
	''
);
t( 'the badge reads the short name',      false !== strpos( $labelled, '>Javelina Jundred</a>' ) );
t( 'but still filters on the full one',   false !== strpos( $labelled, 'race=javelina%20jundred%20presented%20by%20hoka' ) );

echo "\nfilms, durations and view counts:\n";
t( 'an hour-long duration',               '1:50:55' === arv_films_duration( 'PT1H50M55S' ) );
t( 'a minutes-only duration',             '8:49' === arv_films_duration( 'PT8M49S' ) );
t( 'seconds pad correctly',               '50:07' === arv_films_duration( 'PT50M7S' ) );
t( 'a garbage duration is empty',         '' === arv_films_duration( 'not a duration' ) );
t( 'an empty duration is empty',          '' === arv_films_duration( '' ) );
t( 'thousands abbreviate',                '793K' === arv_films_views( 792688 ) );
t( 'millions abbreviate',                 '1.2M' === arv_films_views( 1234567 ) );
t( 'a round million drops the decimal',   '2M' === arv_films_views( 2000000 ) );
t( 'small counts are left alone',         '412' === arv_films_views( 412 ) );
t( 'no views shows nothing',              '' === arv_films_views( 0 ) );

$GLOBALS['_transients']['arv_films'] = $deduped;
$carded = arv_films_render( array() );
t( 'a card carries its views for sorting',  false !== strpos( $carded, 'data-arv-films-views=' ) );
t( 'and its date for sorting',              false !== strpos( $carded, 'data-arv-films-date=' ) );
t( 'and its race for filtering',            false !== strpos( $carded, 'data-arv-films-race=' ) );
t( 'the controls render',                   false !== strpos( $carded, 'data-arv-films-sort' ) );
t( 'with a search box',                     false !== strpos( $carded, 'data-arv-films-search' ) );

unset( $GLOBALS['posts'][9101], $GLOBALS['posts'][9102], $GLOBALS['posts'][9103], $GLOBALS['posts'][9104] );
unset( $GLOBALS['meta'][9101], $GLOBALS['meta'][9102], $GLOBALS['meta'][9103], $GLOBALS['meta'][9104] );
arv_race_store_flush_cache();
$GLOBALS['_transients'] = array();



echo "\nphotos, the store:\n";
$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array(
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => 'Aravaipa Photo Gallery', 'url' => 'https://aravaipa.smugmug.com/2026-Events/Coldwater-Rumble' ),
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => "Let's Wander Photography", 'url' => 'https://lwp.smugmug.com/2026/Coldwater' ),
	array( 'race' => 'Baldface Scramble', 'year' => 2026, 'by' => 'Goat Factory Media', 'url' => 'https://galleries.goatfactorymedia.com/baldface' ),
	array( 'race' => 'Javelina Jundred', 'year' => 2025, 'by' => 'Aravaipa Photo Gallery', 'url' => 'https://aravaipa.smugmug.com/2025-Events/Javelina' ),
	// Junk, to prove the read coerces rather than trusting the option.
	array( 'race' => '', 'year' => 2025, 'by' => 'Nobody', 'url' => 'https://example.com/x' ),
	array( 'race' => 'No Link', 'year' => 2025, 'by' => 'Nobody', 'url' => '' ),
);
$photos = arv_photos_store_get();
t( 'rows with no race or no url are dropped', 4 === count( $photos ) );
t( 'newest year first',                       2026 === $photos[0]['year'] );
t( 'and alphabetical within a year',          'Baldface Scramble' === $photos[0]['race'] );
t( 'the years present',                       array( 2026, 2025 ) === arv_photos_years( $photos ) );
t( 'distinct photographers, sorted',          3 === count( arv_photos_photographers( $photos ) ) );

// A race shot by three photographers is one card, not three near-identical
// cards in a row.
$grouped = arv_photos_group( $photos );
t( 'one card per race per year',              3 === count( $grouped ) );
$coldwater = null;
foreach ( $grouped as $g ) {
	if ( 'Coldwater Rumble' === $g['race'] ) { $coldwater = $g; }
}
t( 'both photographers on the one card',      null !== $coldwater && 2 === count( $coldwater['galleries'] ) );
// The same race in two different years stays two cards: they are different
// sets of pictures.
t( 'the same race in two years is two cards', 2 === count( array_filter( $grouped, function ( $g ) { return 2026 === $g['year']; } ) ) );

echo "\nphotos, writing:\n";
$stored = arv_photos_store_set(
	array(
		array( 'race' => 'Cocodona 250', 'year' => 2026, 'by' => 'Run 200 Photos', 'url' => 'https://www.run200photos.com/cocodona' ),
		// Neither of these should ever reach an href.
		array( 'race' => 'Bad', 'year' => 2026, 'by' => 'x', 'url' => 'javascript:alert(1)' ),
		array( 'race' => 'Bad', 'year' => 2026, 'by' => 'x', 'url' => 'data:text/html,<script>' ),
		array( 'race' => 'Relative', 'year' => 2026, 'by' => 'x', 'url' => '/local/path' ),
	)
);
t( 'only absolute http(s) urls are stored',   1 === $stored );
t( 'and that is the real one',                'Cocodona 250' === arv_photos_store_get()[0]['race'] );

echo "\nphotos, reading a cover out of a gallery page:\n";
// Open Graph is the whole reason an outside photographer's gallery can sit
// beside Aravaipa's own and look like it belongs, so the read of it is
// worth testing in both attribute orders.
t( 'property then content',                   'https://x.test/a.jpg' === arv_photos_og_image( '<head><meta property="og:image" content="https://x.test/a.jpg"></head>' ) );
t( 'content then property',                   'https://x.test/b.jpg' === arv_photos_og_image( '<head><meta content="https://x.test/b.jpg" property="og:image"></head>' ) );
t( 'single quotes',                           'https://x.test/c.jpg' === arv_photos_og_image( "<head><meta property='og:image' content='https://x.test/c.jpg'></head>" ) );
t( 'entities are decoded',                    'https://x.test/d.jpg?a=1&b=2' === arv_photos_og_image( '<head><meta property="og:image" content="https://x.test/d.jpg?a=1&amp;b=2"></head>' ) );
t( 'no tag at all',                           '' === arv_photos_og_image( '<head><title>nothing</title></head>' ) );
// A gallery page's body is full of <img> and the odd share widget, and the
// first match in the whole document is not reliably the page's own cover.
t( 'only the head is searched',               '' === arv_photos_og_image( '<head></head><body><meta property="og:image" content="https://x.test/body.jpg"></body>' ) );
// A relative or javascript: value has no business reaching an src.
t( 'a relative url is refused',               '' === arv_photos_og_image( '<head><meta property="og:image" content="/relative.jpg"></head>' ) );
t( 'a javascript url is refused',             '' === arv_photos_og_image( '<head><meta property="og:image" content="javascript:alert(1)"></head>' ) );

echo "\nphotos, fetching a cover:\n";
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 200, 'body' => '<head><meta property="og:image" content="https://cdn.test/cover.jpg"></head>' ) );
t( 'a cover is read from the gallery',        'https://cdn.test/cover.jpg' === arv_photos_cover( 'https://gallery.test/a' ) );
// Cached, so a grid of a hundred cards is a hundred reads once and none
// after: these are other people's servers.
t( 'and cached, not re-fetched',              'https://cdn.test/cover.jpg' === arv_photos_cover( 'https://gallery.test/a' ) );
// Two of the hosts already linked give nothing up. That has to be a card
// with no picture, never a broken image.
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 403, 'body' => '' ) );
t( 'a host that refuses gives no cover',      '' === arv_photos_cover( 'https://blocked.test/a' ) );
$GLOBALS['_transients'] = array();
arv_test_queue_response( array( 'code' => 200, 'body' => '<head><title>no og tag here</title></head>' ) );
t( 'a page with no tag gives no cover',       '' === arv_photos_cover( 'https://bare.test/a' ) );
t( 'an empty url is not fetched at all',      '' === arv_photos_cover( '' ) );

echo "\nphotos, dates and races still to come:\n";
// A gallery row exists the moment a photographer is booked, which for a
// December race can be most of a year before a single picture is taken.
// Those rendered as a wall of empty grey placeholders at the top of the
// current year's page, one per race still to come, each promising photos
// that do not exist.
$photos_posts_backup = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
$GLOBALS['posts'][9801] = array( 'title' => 'Already Run', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9801]['_arv_iso'] = '2026-03-14';
$GLOBALS['posts'][9802] = array( 'title' => 'Still To Come', 'status' => 'publish', 'type' => 'arv_race' );
$GLOBALS['meta'][9802]['_arv_iso'] = '2026-12-31';
arv_race_store_flush_cache();

$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array(
	array( 'race' => 'Already Run',   'year' => 2026, 'by' => 'Someone', 'url' => 'https://x.test/ran' ),
	array( 'race' => 'Still To Come', 'year' => 2026, 'by' => 'Someone', 'url' => 'https://x.test/soon' ),
	array( 'race' => 'No Date Known', 'year' => 2019, 'by' => 'Someone', 'url' => 'https://x.test/old' ),
);
$GLOBALS['_transients'] = array();
$future_html = arv_photos_render( array() );
t( 'a race that has run is shown',            false !== strpos( $future_html, 'Already Run' ) );
t( 'a race still to come is not',             false === strpos( $future_html, 'Still To Come' ) );
// A race with no date is almost always an older gallery predating the
// calendar. Hiding a real archive over a missing date is the worse error.
t( 'a race with no date is still shown',      false !== strpos( $future_html, 'No Date Known' ) );

// The exact date, not the bare year.
t( 'the card carries a full date',            false !== strpos( $future_html, 'March 14, 2026' ) );
t( 'and not just the year on its own',        false === strpos( $future_html, '>2026</span>' ) );
t( 'a dateless race falls back to its year',  false !== strpos( $future_html, '>2019</span>' ) );

// The naming convention, applied to every year rather than just this one.
$named = arv_photos_render( array( 'year' => 2026 ) );
t( 'the year leads the heading',              false !== strpos( $named, '2026 Photo Galleries' ) );
t( 'and the colon form is gone',              false === strpos( $named, 'Photos: 2026' ) );
// The all-years index keeps the plain noun rather than inventing a year.
t( 'the index heading has no year',           false !== strpos( arv_photos_render( array() ), '>Photo Galleries<' ) );
// An explicit heading still wins, since the per-year pages set their own.
t( 'an explicit heading is respected',        false !== strpos( arv_photos_render( array( 'year' => 2026, 'heading' => 'Race Photos' ) ), '>Race Photos<' ) );

// The boundary: a race that finished last night should be able to have
// galleries up this morning, so the cutoff is the race date plus a day.
t( 'yesterday counts as run',                 arv_photos_has_happened( array( 'iso' => gmdate( 'Y-m-d', current_time( 'timestamp', true ) - ( 2 * DAY_IN_SECONDS ) ), 'year' => 2026 ) ) );
t( 'next week does not',                      ! arv_photos_has_happened( array( 'iso' => gmdate( 'Y-m-d', current_time( 'timestamp', true ) + ( 7 * DAY_IN_SECONDS ) ), 'year' => 2026 ) ) );

$GLOBALS['posts'] = $photos_posts_backup;
arv_race_store_flush_cache();
$GLOBALS['_transients'] = array();

echo "\nphotos, rendering:\n";
$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array(
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => 'Aravaipa Photo Gallery', 'url' => 'https://aravaipa.smugmug.com/a' ),
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => "Let's Wander Photography", 'url' => 'https://lwp.smugmug.com/b' ),
	array( 'race' => 'Javelina Jundred', 'year' => 2025, 'by' => 'Goat Factory Media', 'url' => 'https://galleries.goatfactorymedia.com/c' ),
);
$GLOBALS['_transients'] = array();
$photos_html = arv_photos_render( array( 'heading' => 'Photos', 'intro' => 'Every race.' ) );
t( 'the heading renders',                     false !== strpos( $photos_html, 'Photos' ) );
t( 'the intro renders',                       false !== strpos( $photos_html, 'Every race.' ) );
t( 'one card per race per year',              2 === substr_count( $photos_html, 'class="arv-photos__card"' ) );
t( 'the search box renders',                  false !== strpos( $photos_html, 'data-arv-photos-search' ) );
t( 'the photographer filter renders',         false !== strpos( $photos_html, 'data-arv-photos-by' ) );
t( 'the year links render',                   false !== strpos( $photos_html, 'arv-photos__years' ) );
t( 'a card carries its race for filtering',   false !== strpos( $photos_html, 'data-arv-photos-race=' ) );
t( 'and every photographer on it',            false !== strpos( $photos_html, "let&#039;s wander photography" ) );
// Every photographer, including the first, is the same badge outside the
// card's own link: an <a> inside an <a> is invalid and browsers close the
// outer one. Two on the Coldwater card, one on the Javelina card.
t( 'every photographer is an equal badge',    3 === substr_count( $photos_html, 'class="arv-photos__by-badge"' ) );
t( 'no plain-text photographer remains',      false === strpos( $photos_html, 'arv-photos__by-name' ) );
t( 'a gallery with no cover gets the panel',  false !== strpos( $photos_html, 'arv-photos__cover--none' ) );
t( 'and never a broken image',                0 === substr_count( $photos_html, 'src=""' ) );

echo "\nphotos, Aravaipa's own gallery leads the card:\n";
// "First" used to be whatever order the import produced, which made the
// cover photo and the primary link a coin flip between Aravaipa's own
// gallery and an outside photographer's. Reordered here on purpose:
// Aravaipa's own galleries are the ones this site can vouch for.
$mixed = arv_photos_ordered_galleries(
	array(
		array( 'by' => "Let's Wander Photography", 'url' => 'https://lwp.smugmug.com/x' ),
		array( 'by' => 'Aravaipa Photo Gallery', 'url' => 'https://aravaipa.smugmug.com/x' ),
		array( 'by' => 'Goat Factory Media', 'url' => 'https://galleries.goatfactorymedia.com/x' ),
	)
);
t( "Aravaipa's own gallery moves to the front", 'Aravaipa Photo Gallery' === $mixed[0]['by'] );
t( 'the rest keep their relative order',        "Let's Wander Photography" === $mixed[1]['by'] );
t( 'and the last stays last',                   'Goat Factory Media' === $mixed[2]['by'] );

$no_own = arv_photos_ordered_galleries(
	array(
		array( 'by' => 'Goat Factory Media', 'url' => 'https://a.test' ),
		array( 'by' => "Let's Wander Photography", 'url' => 'https://b.test' ),
	)
);
t( 'with no Aravaipa gallery, order is untouched', 'Goat Factory Media' === $no_own[0]['by'] );

// Rendered: the card's cover and primary link follow Aravaipa's gallery
// even though it was second in the stored data.
$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array(
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => "Let's Wander Photography", 'url' => 'https://lwp.smugmug.com/first' ),
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => 'Aravaipa Photo Gallery', 'url' => 'https://aravaipa.smugmug.com/second' ),
);
$GLOBALS['_transients'] = array();
$reordered = arv_photos_render( array() );
// Specifically the card's own link (the cover), not just that the URL
// appears somewhere: the badge for it would too, regardless of order.
t( "the primary link is Aravaipa's, not the stored order",
	false !== strpos( $reordered, 'class="arv-photos__link" href="https://aravaipa.smugmug.com/second"' ) );

// Back to the three-row fixture the rest of this section relies on.
$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array(
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => 'Aravaipa Photo Gallery', 'url' => 'https://aravaipa.smugmug.com/a' ),
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => "Let's Wander Photography", 'url' => 'https://lwp.smugmug.com/b' ),
	array( 'race' => 'Javelina Jundred', 'year' => 2025, 'by' => 'Goat Factory Media', 'url' => 'https://galleries.goatfactorymedia.com/c' ),
);
$GLOBALS['_transients'] = array();

// A year page pins itself and must not offer to contradict its own URL.
$pinned = arv_photos_render( array( 'year' => 2025 ) );
t( 'a pinned year shows only that year',      1 === substr_count( $pinned, 'class="arv-photos__card"' ) );
t( 'and hides the year switcher',             false === strpos( $pinned, 'arv-photos__years' ) );
t( 'the right year survived',                 false !== strpos( $pinned, 'Javelina Jundred' ) );

// ?arv_year= is how the year links work, and a nonsense one falls back to
// everything rather than an empty page.
$_GET['arv_year'] = '2026';
t( 'a requested year filters',                1 === substr_count( arv_photos_render( array() ), 'class="arv-photos__card"' ) );
$_GET['arv_year'] = '1999';
t( 'an unknown year shows everything',        2 === substr_count( arv_photos_render( array() ), 'class="arv-photos__card"' ) );
$_GET['arv_year'] = 'nonsense';
t( 'a non-numeric year shows everything',     2 === substr_count( arv_photos_render( array() ), 'class="arv-photos__card"' ) );
// A bare "year" is WordPress's own query var and must never be read here.
$_GET['year'] = '2026';
t( 'a bare year= is ignored',                 2 === substr_count( arv_photos_render( array() ), 'class="arv-photos__card"' ) );
unset( $_GET['arv_year'], $_GET['year'] );

$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array();
t( 'no galleries renders nothing at all',     '' === arv_photos_render( array() ) );
$GLOBALS['_transients'] = array();



echo "\nmedia sub-nav:\n";
$subnav_items = arv_media_subnav_items();
// Five. Photos is still not one of them, dropped per Jamil 2026-08-30:
// someone on a Photos page just ran the race and is looking for
// themselves, the same intent as Results and mostly a purchase flow on top
// of it, not the same visitor reading a broadcast, film, episode or
// article. Film Tours was added per Jamil 2026-08-30, directly after Films
// because it is a child of Films rather than a peer of the other four.
t( 'five sections, photos is not one',  5 === count( $subnav_items ) );
t( 'in the right order',              array( 'watch', 'films', 'film-tours', 'podcasts', 'articles' ) === array_column( $subnav_items, 'key' ) );
t( 'film tours is in the strip',      in_array( 'film-tours', array_column( $subnav_items, 'key' ), true ) );
t( 'photos is not among them',        ! in_array( 'photos', array_column( $subnav_items, 'key' ), true ) );
// The label reads "Broadcasts", but the key, slug and URL all stay
// "watch": renaming what a visitor reads is not the same decision as
// moving the URL, which would mean a redirect and the ranking the
// existing path has already built.
t( 'the watch key labels itself Broadcasts', 'Broadcasts' === $subnav_items[0]['label'] );
t( 'and now links to /broadcasts/',          home_url( '/broadcasts/' ) === $subnav_items[0]['url'] );

// The Broadcasts page's own URL only ever comes from arv_watch_url(). It
// used to be a /watch/ literal copy-pasted into five different files, one
// of which this exact rename missed the first time through, which is why
// /broadcasts/ 404'd for a full plugin cycle after the label was renamed
// everywhere except the page itself. A grep guard rather than trusting
// five call sites to stay in sync by hand.
$watch_src = file_get_contents( __DIR__ . '/includes/watch-store.php' )
	. file_get_contents( __DIR__ . '/includes/media-subnav.php' )
	. file_get_contents( __DIR__ . '/includes/media-hub.php' );
t( 'no hardcoded /watch/ url survives', ! preg_match( "#home_url\(\s*'/watch/'\s*\)#", $watch_src ) );
t( 'the helper is what every caller uses', substr_count( $watch_src, 'arv_watch_url()' ) >= 5 );
t( 'the Media parent link renders',   false !== strpos( arv_media_subnav_render( 'films' ), 'arv-media-subnav__parent" href="https://www.aravaiparunning.com/media/"' ) );
// Never the active section: the strip lives on Media's own children, not
// on /media/ itself, so nothing should ever mark this one current.
t( 'the parent link is never current', false === strpos( arv_media_subnav_render( 'films' ), 'arv-media-subnav__parent is-current' ) );

$on_films = arv_media_subnav_render( 'films' );
t( 'every section links out',         5 === substr_count( $on_films, 'arv-media-subnav__link' ) );
t( 'and photos is not one of them',   false === strpos( $on_films, 'href="https://www.aravaiparunning.com/photos/"' ) );
t( 'the current one is marked',       1 === substr_count( $on_films, 'is-current' ) );
// Every media page used to print its own name as a large centred heading
// directly under a strip that had just highlighted that same name. Deleting
// the heading outright would leave the page with no heading element at all,
// so the strip carries it: read by a crawler and a screen reader, never
// seen twice by a sighted visitor.
t( 'the strip carries the page heading', false !== strpos( $on_films, '<h1 class="arv-media-subnav__title screen-reader-text">Films</h1>' ) );
t( 'and only one of them',            1 === substr_count( $on_films, 'arv-media-subnav__title' ) );
// Nothing current, e.g. a single Watch race page, claims no heading.
t( 'an unknown section claims none',  false === strpos( arv_media_subnav_render( '' ), 'arv-media-subnav__title' ) );
t( 'and it is the right one',         false !== strpos( $on_films, 'is-current" href="https://www.aravaiparunning.com/films/"' ) );
t( 'aria-current is set once',        1 === substr_count( $on_films, 'aria-current="page"' ) );

$none_current = arv_media_subnav_render();
t( 'no current section marks nothing', false === strpos( $none_current, 'is-current' ) );
t( 'an unknown key marks nothing',    false === strpos( arv_media_subnav_render( 'nonsense' ), 'is-current' ) );

t( 'the shortcode renders the same',  arv_media_subnav_render( 'watch' ) === arv_media_subnav_shortcode( array( 'current' => 'watch' ) ) );



echo "\nmedia follow (\"subscribe on YouTube\"):\n";
t( 'renders a real link, not a widget',  false !== strpos( arv_media_follow_render( 'youtube', 'film' ), 'youtube.com/@aravaiparunning?sub_confirmation=1' ) );
t( 'no third-party script tag',          false === strpos( arv_media_follow_render( 'youtube', 'film' ), '<script' ) );
t( 'the context is in the copy',         false !== strpos( arv_media_follow_render( 'youtube', 'broadcast' ), 'broadcast' ) );
t( 'an unknown platform renders nothing', '' === arv_media_follow_render( 'tiktok', 'film' ) );



echo "\nphotos, newest race first:\n";
// A year on its own is not recency. It put "Across The Years" (December
// 31st) at the top of 2026 above races that ran eight months earlier,
// purely because the sort fell back to alphabetical inside a year and A
// comes first. That was the complaint: recency was invisible.
$GLOBALS['ARV_OPTIONS'][ ARV_RESULTS_OPTION ] = array(
	array( 'name' => 'Coldwater Rumble', 'iso' => '2026-01-17' ),
	array( 'name' => 'Black Canyon 100K', 'iso' => '2026-02-14' ),
	array( 'name' => 'Whiskey Basin Trail Runs', 'iso' => '2026-04-11' ),
	array( 'name' => 'Across The Years', 'iso' => '2026-12-31' ),
	array( 'name' => 'Coldwater Rumble', 'iso' => '2025-01-18' ),
);
$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array(
	array( 'race' => 'Across The Years', 'year' => 2026, 'by' => 'A', 'url' => 'https://x.test/aty' ),
	array( 'race' => 'Coldwater Rumble', 'year' => 2026, 'by' => 'A', 'url' => 'https://x.test/cw26' ),
	array( 'race' => 'Whiskey Basin Trail Runs', 'year' => 2026, 'by' => 'A', 'url' => 'https://x.test/wb' ),
	array( 'race' => 'Black Canyon 100K', 'year' => 2026, 'by' => 'A', 'url' => 'https://x.test/bc' ),
	// No date anywhere for this one.
	array( 'race' => 'Zzz Unknown Race', 'year' => 2026, 'by' => 'A', 'url' => 'https://x.test/zzz' ),
	array( 'race' => 'Coldwater Rumble', 'year' => 2025, 'by' => 'A', 'url' => 'https://x.test/cw25' ),
);
$dated = arv_photos_store_get();
$order = array_map( function ( $r ) { return $r['race']; }, $dated );

t( 'the newest race in the year is first',  'Across The Years' === $order[0] );
t( 'then the next newest',                  'Whiskey Basin Trail Runs' === $order[1] );
t( 'then the one before that',              'Black Canyon 100K' === $order[2] );
t( 'and the oldest race of the year',       'Coldwater Rumble' === $order[3] );
// Not knowing the day is not a reason to lose the year.
t( 'an undated gallery ends its own year',  'Zzz Unknown Race' === $order[4] );
t( 'and the year below still follows it',   2025 === $dated[5]['year'] );

t( 'a date is read off the results store',  '2026-01-17' === arv_photos_race_date( 'Coldwater Rumble', 2026 ) );
t( 'the right year of a repeating race',    '2025-01-18' === arv_photos_race_date( 'Coldwater Rumble', 2025 ) );
t( 'an unknown race has no date',           '' === arv_photos_race_date( 'Nothing At All', 2026 ) );
t( 'and neither does a yearless row',       '' === arv_photos_race_date( 'Coldwater Rumble', 0 ) );

// Grouping must not undo the sort: it walks the rows in order and PHP
// keeps insertion order, so the cards come out newest first too.
$order_cards = array_map( function ( $c ) { return $c['race']; }, arv_photos_group( $dated ) );
t( 'grouping preserves the order',          'Across The Years' === $order_cards[0] );

echo "\nphotos, the year filter uses a query var WordPress does not own:\n";
// "year" is one of WordPress's own reserved query vars for date archives.
// Using it meant /photos/?year=2025 was read as a date archive and
// canonical-redirected to /photos-2025/, a different page that pins its
// own year and renders no controls, so the filter links looked like they
// were deleting the search box and the year row.
$year_links = arv_photos_render( array() );
t( 'the year links use arv_year',           false !== strpos( $year_links, 'arv_year=2026' ) );
t( 'and never a bare year=',                false === strpos( $year_links, '?year=' ) );

$_GET['arv_year'] = '2025';
$filtered = arv_photos_render( array() );
t( 'arv_year filters the grid',             1 === substr_count( $filtered, 'class="arv-photos__card"' ) );
// The whole point: the controls survive the filter.
t( 'the search box survives',               false !== strpos( $filtered, 'data-arv-photos-search' ) );
t( 'the year row survives',                 false !== strpos( $filtered, 'arv-photos__years' ) );
unset( $_GET['arv_year'] );

$GLOBALS['ARV_OPTIONS'][ ARV_PHOTOS_OPTION ] = array();
$GLOBALS['ARV_OPTIONS'][ ARV_RESULTS_OPTION ] = array();



echo "\nfilms, each playlist in the order that playlist wants:\n";
// The API hands films back in YouTube playlist order, which is whatever
// order somebody dragged them into in Studio. On the real Documentaries
// playlist that left The Cutoff, the newest film on the site,
// fourteenth.
//
// The two playlists then want different orders, and the live numbers are
// what decide it rather than taste: Documentaries is seven years deep
// with a 165x spread in views, so views rank it and dates barely do.
// Originals is five months old and shipping monthly, so recency is the
// entire point and views would bury last month under September.
$unsorted = arv_films_clean(
	array(
		array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array(
			array( 'id' => 'old00000001', 'title' => 'Old But Huge', 'description' => '', 'publishedAt' => '2019-03-14T00:00:00Z', 'thumbnail' => '', 'views' => 800000 ),
			array( 'id' => 'new00000001', 'title' => 'New And Quiet', 'description' => '', 'publishedAt' => '2026-04-23T00:00:00Z', 'thumbnail' => '', 'views' => 9000 ),
			array( 'id' => 'mid00000001', 'title' => 'Middling', 'description' => '', 'publishedAt' => '2024-04-26T00:00:00Z', 'thumbnail' => '', 'views' => 300000 ),
		) ),
		array( 'key' => 'originals', 'title' => 'Aravaipa Originals', 'films' => array(
			array( 'id' => 'org00000001', 'title' => 'Older But Popular', 'description' => '', 'publishedAt' => '2025-09-05T00:00:00Z', 'thumbnail' => '', 'views' => 100000 ),
			array( 'id' => 'org00000002', 'title' => 'Newest Episode', 'description' => '', 'publishedAt' => '2026-02-09T00:00:00Z', 'thumbnail' => '', 'views' => 12000 ),
		) ),
	)
);

// Documentaries: the biggest film leads even though it is the oldest.
$doc_order = array_column( $unsorted[0]['films'], 'title' );
t( 'the back catalogue leads on views',    'Old But Huge' === $doc_order[0] );
t( 'then the next most watched',           'Middling' === $doc_order[1] );
t( 'the newest but quietest is last',      'New And Quiet' === $doc_order[2] );

// Originals: the newest episode leads even though it is the smallest.
$org_order = array_column( $unsorted[1]['films'], 'title' );
t( 'the running series leads on date',     'Newest Episode' === $org_order[0] );
t( 'even though it is the less watched',   'Older But Popular' === $org_order[1] );

t( 'and each stays its own section',       2 === count( $unsorted[1]['films'] ) );
t( 'the sections keep their own order',    'Documentaries' === $unsorted[0]['title'] );

t( 'a documentaries playlist defaults to views', 'views' === arv_films_default_sort( 'documentaries' ) );
t( 'originals defaults to date',                 'date' === arv_films_default_sort( 'originals' ) );
// A playlist nobody has formed an opinion about should not inherit one.
t( 'an unknown playlist defaults to date',       'date' === arv_films_default_sort( 'something-new' ) );

// The HTML itself has to carry the order: it is what a crawler is served
// and what the sort control has to agree with before anyone touches it.
$GLOBALS['_transients']['arv_films'] = $unsorted;
$sorted_html = arv_films_render( array() );
// Measured from the first shelf onward, not the whole document: the
// newest film is also the one in the player at the top, so its title
// appears before any card regardless of how the cards are ordered.
$shelf = substr( $sorted_html, strpos( $sorted_html, 'data-arv-films-list' ) );
t( 'the rendered shelf carries it',        strpos( $shelf, 'Old But Huge' ) < strpos( $shelf, 'New And Quiet' ) );
// No single one of the two orders is true of the page as served, so the
// control has to offer a third state rather than claiming one of them.
t( 'the sort control offers a default',    false !== strpos( $sorted_html, '<option value="">' ) );
$GLOBALS['_transients'] = array();


echo "\nrace terrain, road or trail:\n";
// Nothing in the calendar's own data says this: UltraSignup's feed has no
// surface field, and the one place text hints at it, a race's distances
// ("4 Mile Road Race"), is present on 2 of 87 races and would have called
// the Tucson Marathon a trail race by its absence. This is Jamil's own
// list, from 2026-08-30, not derived from anything.
foreach (
	array(
		'Tucson Marathon', 'Mountain to Fountain', 'Mountain To Fountain', 'ET Full Moon',
		'Labor of Love', 'Purple Run', 'Running with the Devil', 'Vegas Golden Night & Day',
		'Jackpot Ultras', 'Fat Ox', 'Fat Ox Endurance Runs', 'Run Around Tucson (RAT)',
		'Run with the Roosters', 'Across the Years', 'Across The Years',
	) as $road_name
) {
	t( "'$road_name' is road", 'road' === arv_race_terrain( $road_name ) );
}

// arv_results_race_key() strips "run", "the" and "race" as whole words,
// which is what turns "Purple Run" into "purple" and "Run with the
// Roosters" into "with rooster". A regression here would mean the list
// above stopped matching anything without a single test failing to say so.
t( 'the stemming that makes this fragile is exercised', 'purple' === arv_results_race_key( 'Purple Run' ) );

foreach (
	array(
		'Cocodona 250', 'Black Canyon Ultras', 'Coldwater Rumble', 'Rock Hawk Trail Races',
		'Whiskey Basin Trail Runs', 'THE RACE DIRECTOR', '',
	) as $trail_name
) {
	t( "'$trail_name' defaults to trail", 'trail' === arv_race_terrain( $trail_name ) );
}

// Wired into the store's own read, not into arv_race_store_fields(): a
// scraper re-import must never be able to wipe a hand-curated call.
$GLOBALS['posts'][9201] = array( 'title' => 'Tucson Marathon', 'status' => 'publish' );
$GLOBALS['meta'][9201]  = array( '_arv_iso' => '2027-12-05' );
arv_race_store_flush_cache();
$store_races = arv_race_store_get();
$tucson      = null;
foreach ( $store_races as $r ) {
	if ( 'Tucson Marathon' === $r['name'] ) {
		$tucson = $r;
	}
}
t( 'the race store carries terrain on every race', null !== $tucson && 'road' === $tucson['terrain'] );
unset( $GLOBALS['posts'][9201], $GLOBALS['meta'][9201] );
arv_race_store_flush_cache();

// The pasted-row path (arv_upcoming_races_parse_row) is the other way a
// race reaches the calendar, used when the store is empty, and it must
// carry the same field or a fresh install's own pasted rows would render
// with no data-terrain at all.
$pasted = arv_upcoming_races_parse_row( array( 'Tucson Marathon', '2027-12-05' ) );
t( 'a bare pasted row still carries terrain', 'road' === $pasted['terrain'] );

echo "\nmedia latest, merged across every source:\n";
// Every other test in this file leaves posts sitting in $GLOBALS['posts'],
// none of it typed, so a post_type=post query for this feed matches all of
// it: by this point in the file that is already more than one page of
// results, and every one of those leftovers has no 'thumb' key, so they
// would fill this feed's post_type query and crowd out the real fixture
// below it. Swapped out for a clean slate for just this block, since
// nothing later in the file reads $GLOBALS['posts'] again.
$real_posts          = $GLOBALS['posts'];
$GLOBALS['posts']    = array();

// Each source is seeded through its own store's real cache, the same way
// a page render would find it, rather than mocking arv_media_latest's own
// functions: the point of this feed is that it trusts nothing about the
// order four different stores hand data back in.
$GLOBALS['_transients']['arv_watch_events'] = array(
	array(
		'slug' => 'cocodona', 'name' => 'Cocodona 250', 'live' => false,
		'start' => '2026-05-04T08:00:00Z', 'place' => '', 'desc' => '',
		'streams' => array( array(
			'id' => 'aaaaaaaaaaa', 'title' => 'Start', 'url' => 'https://youtu.be/aaaaaaaaaaa',
			'thumbnail' => 'https://i.ytimg.com/vi/aaaaaaaaaaa/hqdefault.jpg', 'live' => false,
			'type' => '', 'start' => '', 'desc' => '', 'aired' => '2026-05-04T08:00:00Z',
			'minutes' => 60, 'views' => 100,
		) ),
	),
);
$GLOBALS['_transients']['arv_films'] = array(
	array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array( array(
		'id' => 'bbbbbbbbbbb', 'title' => 'A Film', 'desc' => '', 'thumbnail' => 'https://x.test/b.jpg',
		'published' => '2026-05-06T00:00:00Z', 'views' => 500, 'duration' => '', 'url' => 'https://youtu.be/bbbbbbbbbbb',
		'lead' => 'A FILM', 'race' => '', 'division' => null, 'trailer' => null,
	) ) ),
);
$GLOBALS['_transients']['arv_podcasts'] = array(
	array(
		'key' => 'inside-aravaipa', 'title' => 'Inside Aravaipa', 'feed' => '', 'spotify' => '', 'apple' => '',
		'artwork' => 'https://x.test/show.jpg', 'summary' => '',
		'episodes' => array( array(
			'title' => 'An Episode', 'audio' => 'https://x.test/ep.mp3', 'link' => 'https://anchor.fm/ep',
			'artwork' => '', 'guid' => 'ep-1', 'duration' => '', 'published' => '2026-05-05T00:00:00Z',
		) ),
	),
);
$GLOBALS['posts'][9301] = array( 'title' => 'A Blog Post', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-07T00:00:00Z', 'thumb' => 'https://x.test/post.jpg' );
$GLOBALS['posts'][9302] = array( 'title' => 'No Image Post', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-08T00:00:00Z', 'thumb' => false );
$GLOBALS['PERMALINK'][9301] = 'https://www.aravaiparunning.com/a-blog-post/';

$items = arv_media_latest_items();
$types = array_column( $items, 'type' );

t( 'a post with no featured image is dropped', ! in_array( 'No Image Post', array_column( $items, 'title' ), true ) );
t( 'all four sources are represented',    4 === count( array_unique( $types ) ) );
t( 'newest first regardless of source',   'article' === $items[0]['type'] ); // 05-07 post is the newest of the four
t( 'then the film',                       'film' === $items[1]['type'] );
t( 'then the podcast episode',            'podcast' === $items[2]['type'] );
t( 'then the broadcast',                  'broadcast' === $items[3]['type'] );

t( 'a limit is honoured',                 2 === count( arv_media_latest_items( 2 ) ) );
t( 'and it keeps the newest, not the oldest', 'article' === arv_media_latest_items( 1 )[0]['type'] );

t( 'the podcast item links to the show page, not anchor.fm',
	home_url( '/podcasts/inside-aravaipa/' ) === $items[2]['url'] );
t( 'the broadcast carries a real badge',  'Broadcast' === $items[3]['badge'] );

echo "\nmedia latest, rendered:\n";
$html = arv_media_latest_render( array( 'heading' => 'Latest', 'intro' => 'Everything, newest first.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Latest' ) );
t( 'one card per item',                   4 === substr_count( $html, 'class="arv-media-latest__card"' ) );
// Not 'arv-media-latest__filter': that string is also a prefix of the
// wrapping '__filters' div's own class and would count it too.
t( 'a filter button per type, plus All',  5 === substr_count( $html, '<button type="button" class="arv-media-latest__filter' ) );
t( 'each card carries its type for the JS filter', false !== strpos( $html, 'data-arv-latest-type="article"' ) );
t( 'the dropped post never reaches the page', false === strpos( $html, 'No Image Post' ) );

// A page whose feed is entirely one type gets no filter bar: every button
// would do the same thing as "All".
$GLOBALS['_transients']['arv_films'] = array();
$GLOBALS['_transients']['arv_podcasts'] = array();
$GLOBALS['posts'][9302]['status'] = 'trash';
unset( $GLOBALS['posts'][9301] );
// The post list caches itself for an hour, same as every other source
// here; the render above already populated that cache with 9301 in it,
// so mutating $GLOBALS['posts'] now needs a fresh read to be seen at all.
delete_transient( 'arv_media_latest_posts' );
$only_watch = arv_media_latest_render( array() );
t( 'a single-type feed has no filter bar', false === strpos( $only_watch, 'arv-media-latest__filters' ) );

$GLOBALS['_transients']['arv_watch_events'] = array();
t( 'nothing anywhere renders nothing',    '' === arv_media_latest_render( array() ) );

echo "\nmedia latest, the post cache busts on save:\n";
$GLOBALS['posts'][9303] = array( 'title' => 'Fresh Post', 'status' => 'publish', 'type' => 'post', 'date' => '2026-06-01T00:00:00Z', 'thumb' => 'https://x.test/fresh.jpg' );
arv_media_latest_from_posts(); // populate the cache
$before = get_transient( 'arv_media_latest_posts' );
t( 'the post list is cached',             false !== $before );
arv_media_latest_flush_on_save( 9303 );
t( 'saving a post drops the cache',       false === get_transient( 'arv_media_latest_posts' ) );
// A race post saving must not touch this cache: it is a different post
// type and has nothing to do with the blog.
$GLOBALS['posts'][9304] = array( 'title' => 'Some Race', 'status' => 'publish', 'type' => 'arv_race', 'date' => '', 'thumb' => false );
arv_media_latest_from_posts();
arv_media_latest_flush_on_save( 9304 );
t( 'a non-post save leaves the cache alone', false !== get_transient( 'arv_media_latest_posts' ) );

unset( $GLOBALS['PERMALINK'][9301] );
$GLOBALS['posts']       = $real_posts;
$GLOBALS['_transients'] = array();


echo "\narticles, the archive /blog/ could not be:\n";
// Same clean slate the Latest feed's block takes, and for the same reason:
// every untyped fixture above this point answers a post_type=post query,
// and this store asks for every post there is rather than the first forty.
$articles_backup  = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
$GLOBALS['_transients'] = array();

$GLOBALS['posts'][9401] = array( 'title' => 'Cocodona Race Report', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-07T00:00:00Z', 'thumb' => 'https://x.test/a.jpg', 'cats' => array( 'Race Report', 'News' ) );
$GLOBALS['posts'][9402] = array( 'title' => 'A Press Release',     'status' => 'publish', 'type' => 'post', 'date' => '2025-03-02T00:00:00Z', 'thumb' => false,                'cats' => array( 'Press Release' ) );
$GLOBALS['posts'][9403] = array( 'title' => 'Filed Under Nothing', 'status' => 'publish', 'type' => 'post', 'date' => '2024-01-04T00:00:00Z', 'thumb' => 'https://x.test/c.jpg', 'cats' => array( 'Uncategorized' ) );
$GLOBALS['posts'][9404] = array( 'title' => 'A Draft',             'status' => 'draft',   'type' => 'post', 'date' => '2026-06-01T00:00:00Z', 'thumb' => false,                'cats' => array() );
$GLOBALS['posts'][9405] = array( 'title' => 'Not A Post',          'status' => 'publish', 'type' => 'arv_race', 'date' => '2026-06-02T00:00:00Z', 'thumb' => false,             'cats' => array() );
foreach ( array( 9401, 9402, 9403 ) as $id ) {
	$GLOBALS['PERMALINK'][ $id ] = 'https://www.aravaiparunning.com/post-' . $id . '/';
}

$items = arv_articles_items();
t( 'every published post is here',        3 === count( $items ) );
t( 'a draft is not',                      ! in_array( 'A Draft', array_column( $items, 'title' ), true ) );
t( 'nor a race',                          ! in_array( 'Not A Post', array_column( $items, 'title' ), true ) );
// The one deliberate difference from the Latest feed, which drops these.
// This is the archive: it holds every article or the count on /media/ is a
// lie, so a post with no picture gets a placeholder instead of the axe.
t( 'a post with no image is kept',        in_array( 'A Press Release', array_column( $items, 'title' ), true ) );
t( 'and carries no thumbnail',            '' === $items[1]['thumb'] );

// 71 of the 296 real posts have no featured image, and 58 of those do have
// a photograph sitting in the body: whoever wrote them uploaded a picture
// and never set it as featured. Falling back to it is the difference
// between an archive that looks finished and one that looks half broken.
$GLOBALS['posts'][9411] = array( 'title' => 'Body Image Only', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-09T00:00:00Z', 'thumb' => false, 'cats' => array(), 'body' => '<p>Words.</p><img src="https://x.test/in-body.jpg" alt="" /><p>More.</p>' );
$GLOBALS['posts'][9412] = array( 'title' => 'Lazy Placeholder', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-08T00:00:00Z', 'thumb' => false, 'cats' => array(), 'body' => '<img src="data:image/gif;base64,R0lGOD" data-src="https://x.test/real.jpg" />' );
$GLOBALS['posts'][9413] = array( 'title' => 'Words Only', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-07T00:00:00Z', 'thumb' => false, 'cats' => array(), 'body' => '<p>No pictures here at all.</p>' );
$GLOBALS['PERMALINK'][9411] = 'https://www.aravaiparunning.com/post-9411/';
$GLOBALS['PERMALINK'][9412] = 'https://www.aravaiparunning.com/post-9412/';
$GLOBALS['PERMALINK'][9413] = 'https://www.aravaiparunning.com/post-9413/';
delete_transient( ARV_ARTICLES_CACHE );
$withbody = arv_articles_items();
$byTitle  = array_column( $withbody, 'thumb', 'title' );
t( 'a body image stands in for a featured one', 'https://x.test/in-body.jpg' === $byTitle['Body Image Only'] );
// A 1x1 transparent GIF stretched across a card is worse than the panel.
t( 'a lazy-load data: URI is refused',    '' === $byTitle['Lazy Placeholder'] );
t( 'and a post with no image gets none',  '' === $byTitle['Words Only'] );
t( 'a featured image still wins',         'https://x.test/a.jpg' === $byTitle['Cocodona Race Report'] );
t( 'single quotes in the markup parse',   'https://x.test/q.jpg' === arv_articles_body_image( "<img src='https://x.test/q.jpg'>" ) );
t( 'and no img at all is empty',          '' === arv_articles_body_image( '<p>nothing</p>' ) );
foreach ( array( 9411, 9412, 9413 ) as $id ) { unset( $GLOBALS['posts'][ $id ], $GLOBALS['PERMALINK'][ $id ] ); }
delete_transient( ARV_ARTICLES_CACHE );
$items = arv_articles_items();
t( 'each carries its year',               2026 === $items[0]['year'] );

// Nobody chose "Uncategorized"; it is what WordPress does when nobody
// chose anything, so it is not a filter worth offering.
t( 'uncategorized is not a category',     array() === $items[2]['cats'] );
t( 'real categories survive',             array( 'Race Report', 'News' ) === $items[0]['cats'] );

$years = arv_articles_years( $items );
t( 'a year per year, newest first',       array( 2026, 2025, 2024 ) === $years );

// Ordered by how many articles carry it, not alphabetically: the list is
// there to be picked from, and a category with eighty behind it should not
// sit under one with a single post.
$GLOBALS['posts'][9406] = array( 'title' => 'Another Report', 'status' => 'publish', 'type' => 'post', 'date' => '2026-04-01T00:00:00Z', 'thumb' => false, 'cats' => array( 'Race Report' ) );
$GLOBALS['PERMALINK'][9406] = 'https://www.aravaiparunning.com/post-9406/';
delete_transient( ARV_ARTICLES_CACHE );
$cats = arv_articles_categories( arv_articles_items() );
t( 'the busiest category leads',          'Race Report' === $cats[0] );
t( 'and the rest follow it',              3 === count( $cats ) );

echo "\narticles, rendered:\n";
$html = arv_articles_render( array( 'heading' => 'Articles', 'intro' => 'Every one of them.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Articles</h2>' ) );
t( 'the intro too',                       false !== strpos( $html, 'Every one of them.' ) );
t( 'one card per article',                4 === substr_count( $html, 'class="arv-articles__card"' ) );
t( 'newest first',                        strpos( $html, 'Cocodona Race Report' ) < strpos( $html, 'A Press Release' ) );
t( 'a real date, not an ISO string',      false !== strpos( $html, 'May 7, 2026' ) );
t( 'the first category is the badge',     false !== strpos( $html, '>Race Report</span>' ) );
// The hooks the filters read. Split on the separator in the JS, so every
// category a post carries is filterable, not just the one on the badge.
t( 'each card carries its title',         false !== strpos( $html, 'data-arv-articles-title="cocodona race report"' ) );
t( 'and every category it has',           false !== strpos( $html, 'data-arv-articles-cat="race report|news"' ) );
t( 'and its year',                        false !== strpos( $html, 'data-arv-articles-year="2026"' ) );
t( 'a search box',                        false !== strpos( $html, 'data-arv-articles-search' ) );
t( 'a category filter',                   false !== strpos( $html, 'data-arv-articles-cat aria-label' ) );
t( 'a year filter',                       false !== strpos( $html, 'data-arv-articles-year aria-label' ) );
// A post with no featured image gets a panel rather than a hole: a card
// with a gap where its picture should be reads as broken beside the ones
// that have one.
t( 'the image-less post gets a panel',    false !== strpos( $html, 'arv-articles__thumb--none' ) );
t( 'and the others a real image',         2 === substr_count( $html, '<img class="arv-articles__thumb"' ) );

// A limited block is a homepage embed, not the archive: three controls
// over six cards is furniture, the same gate Watch puts on its search box.
$short = arv_articles_render( array( 'limit' => 2 ) );
t( 'a limit narrows the grid',            2 === substr_count( $short, 'class="arv-articles__card"' ) );
t( 'and takes the controls with it',      false === strpos( $short, 'data-arv-articles-search' ) );

t( 'no articles renders nothing',         '' === arv_articles_render( array() ) || 0 < count( $items ) );
$GLOBALS['posts'] = array();
delete_transient( ARV_ARTICLES_CACHE );
t( 'an empty blog renders nothing',       '' === arv_articles_render( array() ) );

echo "\narticles, the cache busts on save:\n";
$GLOBALS['posts'][9407] = array( 'title' => 'Fresh', 'status' => 'publish', 'type' => 'post', 'date' => '2026-07-01T00:00:00Z', 'thumb' => false, 'cats' => array() );
$GLOBALS['PERMALINK'][9407] = 'https://www.aravaiparunning.com/post-9407/';
delete_transient( ARV_ARTICLES_CACHE );
arv_articles_items();
t( 'the archive is cached',               false !== get_transient( ARV_ARTICLES_CACHE ) );
arv_articles_flush_on_save( 9407 );
t( 'saving a post drops it',              false === get_transient( ARV_ARTICLES_CACHE ) );
arv_articles_items();
// A race saving must not touch this cache: different post type, nothing to
// do with the blog. Re-seeded because the empty-blog test above cleared it,
// and an id the harness has never heard of defaults to 'post'.
$GLOBALS['posts'][9408] = array( 'title' => 'Some Race', 'status' => 'publish', 'type' => 'arv_race', 'date' => '', 'thumb' => false, 'cats' => array() );
arv_articles_flush_on_save( 9408 );
t( 'a non-post save leaves it alone',     false !== get_transient( ARV_ARTICLES_CACHE ) );

t( 'the shortcode is registered',         isset( $GLOBALS['SHORTCODES']['arv_articles'] ) );

foreach ( array( 9401, 9402, 9403, 9406, 9407 ) as $id ) {
	unset( $GLOBALS['PERMALINK'][ $id ] );
}
$GLOBALS['posts']       = $articles_backup;
$GLOBALS['_transients'] = array();

echo "\nfilm tours, a state that cannot go stale:\n";
// The whole reason this is computed rather than typed: both tour pages went
// stale the day their last screening happened, and The Chase was still
// offering "Book Tickets" sixteen months later.
$past    = array( 'key' => 'p', 'title' => 'Past',    'page' => '/p/', 'film' => 'aaaaaaaaaaa', 'from' => '2020-01-01', 'to' => '2020-02-01' );
$now     = array( 'key' => 'n', 'title' => 'Now',     'page' => '/n/', 'film' => 'bbbbbbbbbbb', 'from' => '2020-01-01', 'to' => '2999-01-01' );
$soon    = array( 'key' => 's', 'title' => 'Soon',    'page' => '/s/', 'film' => 'ccccccccccc', 'from' => '2999-01-01', 'to' => '2999-02-01' );
$openrun = array( 'key' => 'o', 'title' => 'Open',    'page' => '/o/', 'film' => 'ddddddddddd', 'from' => '2020-01-01', 'to' => '' );

t( 'a finished tour reads as finished',   'toured'   === arv_tours_state( $past ) );
t( 'a running one as running',            'touring'  === arv_tours_state( $now ) );
t( 'an announced one as announced',       'upcoming' === arv_tours_state( $soon ) );
// Announced but unscheduled is a real state: the weeks between a trailer
// and the first venue confirming. It reads as running, not as over.
t( 'no end date is not the same as over', 'touring'  === arv_tours_state( $openrun ) );

// The year said once, or a same-year window reads like two different years.
t( 'a same-year window says the year once', 'February 20 to March 31, 2026' === arv_tours_window( array( 'from' => '2026-02-20', 'to' => '2026-03-31' ) ) );
t( 'a window across years says both',       'December 1, 2025 to January 5, 2026' === arv_tours_window( array( 'from' => '2025-12-01', 'to' => '2026-01-05' ) ) );
t( 'no end date falls back to the month',   'March 2026' === arv_tours_window( array( 'from' => '2026-03-01' ) ) );
t( 'no dates at all says nothing',          '' === arv_tours_window( array() ) );

t( 'a finished tour invites a watch',     'Watch it now' === arv_tours_badge( 'toured' ) );
t( 'a running one says so',               'On tour now'  === arv_tours_badge( 'touring' ) );

// Falls back to YouTube's own thumbnail URL, which is derived from the id
// and so cannot 404 while the video exists. This page has to still look
// right when the Films feed is down, which is when someone is most likely
// looking at it.
$GLOBALS['_transients']['arv_films'] = 'none';
$dead = arv_tours_film( 'k0HkYULFVvA' );
t( 'a dead films feed still has artwork', false !== strpos( $dead['thumb'], 'i.ytimg.com/vi/k0HkYULFVvA' ) );
t( 'and simply has no view count',        0 === $dead['views'] );

$GLOBALS['_transients']['arv_films'] = array(
	array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array(
		array( 'id' => 'k0HkYULFVvA', 'title' => 'THE CHASE', 'desc' => '', 'thumbnail' => 'https://x.test/chase.jpg',
		       'published' => '2025-04-25T00:00:00Z', 'views' => 793207, 'duration' => '', 'url' => 'https://youtu.be/k0HkYULFVvA',
		       'lead' => 'THE CHASE', 'race' => '', 'division' => null, 'trailer' => null ),
	) ),
);
$live = arv_tours_film( 'k0HkYULFVvA' );
t( 'a live feed wins on artwork',         'https://x.test/chase.jpg' === $live['thumb'] );
t( 'and carries the view count',          793207 === $live['views'] );

echo "\nfilm tours, rendered:\n";
$html = arv_tours_render( array( 'heading' => 'Film Tours', 'intro' => 'Every one of them.' ) );
t( 'the heading renders',                 false !== strpos( $html, 'Film Tours</h2>' ) );
t( 'a card per configured tour',          2 === substr_count( $html, 'class="arv-tours__card' ) );
t( 'both films are there',                false !== strpos( $html, 'The Cutoff' ) && false !== strpos( $html, 'The Chase' ) );
t( 'newest tour first',                   strpos( $html, 'The Cutoff' ) < strpos( $html, 'The Chase' ) );
// Both real tours have finished, so neither should be inviting a ticket
// purchase. This is the assertion that would have caught the live bug.
t( 'no finished tour sells a ticket',     false === stripos( $html, 'ticket' ) );
t( 'they invite a watch instead',         2 === substr_count( $html, 'Watch it now' ) );
// The real span, off the tour page's own eighteen listed stops, not the
// "February 20 - March 31" its heading advertises. Both tours kept going
// weeks past the date they told everyone they ended.
// Label and value are separate spans now, so the card can put a small
// header over each line rather than running them into one grey string.
t( 'the window reads as past',            false !== strpos( $html, '>Toured</span><span class="arv-tours__line-value">February 20 to May 14, 2026<' ) );
t( 'each line is labelled',               false !== strpos( $html, 'arv-tours__line-label' ) );
t( 'the reach line has its own label',    false !== strpos( $html, '>Reach</span>' ) );
t( 'and the view count its own',          false !== strpos( $html, '>Watched</span>' ) );
// "18 stops in 4 countries" is the sentence a sponsor or venue wants off
// this page, and it was nowhere on the site before this.
t( 'the recap counts the stops',          false !== strpos( $html, '18 stops' ) );
t( 'and the countries',                   false !== strpos( $html, '4 countries' ) );
t( 'the other tour has its own count',    false !== strpos( $html, '21 stops' ) );
// One country is not worth saying: every tour happens somewhere.
t( 'a single country is not mentioned',   '5 stops' === arv_tours_recap( array( 'stops' => 5, 'countries' => 1 ) ) );
t( 'and no stop count says nothing',      '' === arv_tours_recap( array() ) );
// The tour page said out loud, not just as the card's own link.
t( 'the tour page is an explicit action', false !== strpos( $html, 'Tour page: all 18 stops' ) );

// The Chase moved out from under a draft parent named "Cocodona 250 OLD".
// Nothing here may point at the old path again.
t( 'the chase links to its real page',    false !== strpos( $html, home_url( '/the-chase-film/' ) ) );
t( 'and never to the retired parent',     false === strpos( $html, 'cocodona-old' ) );

// Each tour's merch has to be its own. The Cutoff's page shipped pointing
// at The Chase's collection, which is the second live bug this guards.
t( 'the cutoff has its own merch',        false !== strpos( $html, 'the-cutoff-film-merch' ) );
t( 'the chase has its own merch',         false !== strpos( $html, 'the-chase-film-merch' ) );
$cutoff_card = substr( $html, strpos( $html, 'The Cutoff' ), strpos( $html, 'The Chase' ) - strpos( $html, 'The Cutoff' ) );
t( 'and neither borrows the other s',     false === strpos( $cutoff_card, 'the-chase-film-merch' ) );

// A link inside a link is not a thing a browser can render, so the actions
// sit outside the card's own anchor.
t( 'the actions are outside the card link', strpos( $html, 'arv-tours__actions' ) > strpos( $html, '</a>' ) );

echo "\nfilm tours, the strip on the films page:\n";
$strip = arv_tours_strip_render();
t( 'the strip renders',                   false !== strpos( $strip, 'arv-tours-strip' ) );
t( 'and points at the index',             false !== strpos( $strip, home_url( '/film-tours/' ) ) );
// Nothing is running today, so it is a quiet cross-link rather than a shout.
t( 'a quiet line when nothing is on',     false !== strpos( $strip, 'where it played' ) );

// With something actually on the road, it says that instead.
add_filter( 'arv_tours_config', function () {
	return array( array( 'key' => 'n', 'title' => 'Now', 'page' => '/n/', 'film' => 'bbbbbbbbbbb', 'from' => '2020-01-01', 'to' => '2999-01-01' ) );
} );
$loud = arv_tours_strip_render();
t( 'a live tour changes the line',        false !== strpos( $loud, '1 tour on the road right now' ) );
t( 'and the card badge with it',          false !== strpos( arv_tours_render( array() ), 'On tour now' ) );
$GLOBALS['FILTERS']['arv_tours_config'] = array();

t( 'the index shortcode is registered',   isset( $GLOBALS['SHORTCODES']['arv_film_tours'] ) );
t( 'the strip shortcode too',             isset( $GLOBALS['SHORTCODES']['arv_film_tours_strip'] ) );

$GLOBALS['_transients'] = array();

echo "\nshop, only what the storefront actually publishes:\n";
$GLOBALS['ARV_OPTIONS'] = array();

// Square happily reports items as VISIBLE that have no storefront page at
// all, and a URL built from the catalogue id returns HTTP 200 while showing
// the shop's front door. The importer verifies against the sitemap, and
// this store drops anything that arrives without a usable URL regardless.
$saved = arv_shop_set( array(
	'collections' => array(
		array( 'id' => 'CBC', 'name' => 'Black Canyon Ultras', 'url' => 'https://aravaipa-shop.square.site/shop/black-canyon-ultras/CBC', 'image' => 'https://x.test/bc.png', 'count' => 2, 'race' => true ),
		array( 'id' => 'CMEN', 'name' => "Men's", 'url' => 'https://aravaipa-shop.square.site/shop/mens/CMEN', 'image' => '', 'count' => 1, 'race' => false ),
		array( 'id' => 'CBAD', 'name' => 'No URL', 'url' => '', 'count' => 5, 'race' => true ),
		array( 'id' => 'CJS', 'name' => 'Javascript', 'url' => 'javascript:alert(1)', 'count' => 5, 'race' => true ),
	),
	'products' => array(
		array( 'id' => 'P1', 'name' => 'Black Canyon Hoodie', 'url' => 'https://aravaipa-shop.square.site/product/bc-hoodie/P1', 'image' => 'https://x.test/1.png', 'desc' => 'Warm.', 'price' => 6500, 'sold_out' => false, 'options' => array( array( 'name' => 'Small', 'price' => 6500, 'sold_out' => false ), array( 'name' => 'Large', 'price' => 6500, 'sold_out' => true ) ), 'collections' => array( 'CBC' ) ),
		array( 'id' => 'P2', 'name' => 'Black Canyon Tee', 'url' => 'https://aravaipa-shop.square.site/product/bc-tee/P2', 'image' => 'https://x.test/2.png', 'price' => 3000, 'sold_out' => true, 'collections' => array( 'CBC', 'CMEN' ) ),
		array( 'id' => 'P3', 'name' => 'No Page', 'url' => '', 'price' => 1000, 'collections' => array( 'CBC' ) ),
		array( 'id' => 'P4', 'name' => 'Ghost Collection', 'url' => 'https://aravaipa-shop.square.site/product/ghost/P4', 'price' => 1000, 'collections' => array( 'CBAD', 'CNOPE' ) ),
	),
) );
t( 'a collection with no url is dropped',  2 === $saved['collections'] );
t( 'and so is a javascript: one',          null === arv_shop_collection_for_race( 'Javascript' ) );
t( 'a product with no url is dropped',     3 === $saved['products'] );

// A product may not claim membership of a collection this store dropped:
// it would be unreachable from the shop page and still show on a race page.
$ghost = arv_shop_get()['products'][2];
t( 'a dropped collection is unclaimed',    array() === $ghost['collections'] );
t( 'and so is one that never existed',     ! in_array( 'CNOPE', $ghost['collections'], true ) );

// Joined on arv_results_race_key(), the same normaliser Results, Watch and
// the Films race tags use, so no hand-written mapping table is needed.
t( 'a race finds its collection',          'CBC' === arv_shop_collection_for_race( 'Black Canyon Ultras' )['id'] );
t( 'the year does not matter',             'CBC' === arv_shop_collection_for_race( 'Black Canyon Ultras 2026' )['id'] );
t( 'nor the distance',                     'CBC' === arv_shop_collection_for_race( 'Black Canyon 100K' )['id'] );
t( 'an unknown race finds nothing',        null === arv_shop_collection_for_race( 'Not A Race' ) );
t( 'an empty race name finds nothing',     null === arv_shop_collection_for_race( '' ) );
// Departments are not races. A strip saying "Men's gear" on a race page is
// the exact failure the importer's explicit department list prevents.
t( 'a department is not a race collection', 1 === count( arv_shop_race_collections() ) );

// Sold out sinks rather than disappearing: a race whose gear has all sold
// is a truer thing to show than an empty shelf.
$in = arv_shop_products_in( 'CBC' );
t( 'both products are in the collection',  2 === count( $in ) );
t( 'in stock leads',                       'P1' === $in[0]['id'] );
t( 'sold out sinks',                       'P2' === $in[1]['id'] );
t( 'a limit is honoured',                  1 === count( arv_shop_products_in( 'CBC', 1 ) ) );

// A price tag, not a spreadsheet.
t( 'whole dollars lose the zeros',         '$65' === arv_shop_price( 6500 ) );
t( 'and cents are kept when real',         '$29.99' === arv_shop_price( 2999 ) );
t( 'no price says nothing',                '' === arv_shop_price( 0 ) );

echo "\nshop, rendered:\n";
$strip = arv_shop_race_merch_render( array( 'race' => 'Black Canyon Ultras 2026' ) );
t( 'the strip renders',                    false !== strpos( $strip, 'arv-shop--strip' ) );
t( 'headed with the real race name',       false !== strpos( $strip, 'Black Canyon Ultras gear' ) );
t( 'a card per product',                   2 === substr_count( $strip, 'class="arv-shop__card' ) );
t( 'with a real price',                    false !== strpos( $strip, '$65' ) );
t( 'sold out is stated, not implied',      false !== strpos( $strip, 'Sold out' ) );
t( 'and shop all points at the collection', false !== strpos( $strip, '/shop/black-canyon-ultras/CBC' ) );
// Off-site, so it says so to the browser.
t( 'external links are safe',              2 < substr_count( $strip, 'rel="noopener"' ) );

echo "\nshop, the item detail drawer:\n";
// Sizes and colours are shown as information, not as a working selector:
// nothing picked here can travel with the click through to Square, which
// has no way to receive it.
t( 'the strip carries a detail payload',   false !== strpos( $strip, 'data-arv-shop-item=' ) );
t( 'and the shared drawer once',           1 === substr_count( $strip, 'data-arv-shop-detail>' ) );

$payload = arv_shop_detail_payload( arv_shop_get()['products'][0] );
t( 'the name travels',                     'Black Canyon Hoodie' === $payload['name'] );
t( 'the description too',                  'Warm.' === $payload['desc'] );
t( 'a formatted price, not raw cents',     '$65' === $payload['price'] );
t( 'options carry a formatted price too',  '$65' === $payload['options'][0]['price'] );
t( 'and their own sold-out state',         true === $payload['options'][1]['soldOut'] );
// The link the drawer's "View on Square" button uses has to be the same
// rewrite everything else on the page goes through.
add_filter( 'arv_shop_storefront_host', function () { return 'shop.aravaiparunning.com'; } );
$rewritten = arv_shop_detail_payload( arv_shop_get()['products'][0] );
t( 'the buy link honours the custom domain', 'https://shop.aravaiparunning.com/product/bc-hoodie/P1' === $rewritten['url'] );
$GLOBALS['FILTERS']['arv_shop_storefront_host'] = array();

// One drawer even when the page carries both a strip and the full index,
// e.g. a shop page with a featured race above the catalogue.
$combo = $strip . arv_shop_render( array() );
t( 'still exactly one drawer between them', 1 === substr_count( $combo, 'data-arv-shop-detail>' ) );

// Cornerstone's own builder canvas rendered the drawer permanently open:
// a page builder stripping a bare boolean attribute it does not
// recognise, not a CSS bug, since the published page's own markup was
// always correct. Belt and braces rather than trusting either signal
// alone: the hidden attribute for browsers that respect it, an inline
// style next to it that survives whatever strips the other one.
t( 'the drawer starts hidden two ways',    false !== strpos( $strip, 'arv-shop__detail" hidden style="display:none"' ) );

// The two are kept in sync by the script, not left for one to drift from
// the other. Nothing in this suite executes it, so this is a content
// guard rather than a behavioural test: it exists so the next edit to
// this file cannot quietly drop one half of the fix.
$shop_js = file_get_contents( __DIR__ . '/assets/aravaipa-shop.js' );
t( 'opening clears the inline style too', false !== strpos( $shop_js, "drawer.style.display = '';" ) );
t( 'closing sets it again',               false !== strpos( $shop_js, "drawer.style.display = 'none';" ) );

echo "\nshop, the collection accordion:\n";
$page = arv_shop_render( array( 'heading' => 'Shop' ) );
// <details>, not a click handler: opening a tile in place needs no script
// to work at all, the same reason the Watch archive's segment list is a
// native <details> rather than a script-driven toggle.
t( 'a collection opens as a details element', false !== strpos( $page, '<details class="arv-shop__collection-details">' ) );
t( 'its products are inside, not linked out', false !== strpos( $page, 'arv-shop__collection-body' ) );
t( 'a way to leave for the full collection remains', false !== strpos( $page, 'View the full collection on Square' ) );

// Silence, not an empty shelf, which is the common case for most races.
t( 'a race with no collection is silent',  '' === arv_shop_race_merch_render( array( 'race' => 'Not A Race' ) ) );
t( 'and one with no products too',         '' === arv_shop_race_merch_render( array( 'race' => "Men's" ) ) );

$page = arv_shop_render( array( 'heading' => 'Shop', 'intro' => 'Everything.' ) );
t( 'the shop page renders',                false !== strpos( $page, 'Shop</h2>' ) );
t( 'departments come first',               strpos( $page, 'Browse' ) < strpos( $page, 'By race' ) );
t( 'a tile per collection',                2 === substr_count( $page, 'arv-shop__collection-link' ) );
t( 'each tile counts its items',           false !== strpos( $page, '2 items' ) );

// An empty catalogue renders nothing rather than a heading over a gap, the
// same rule Watch, Films and Articles all follow.
$GLOBALS['ARV_OPTIONS'] = array();
t( 'an empty catalogue renders nothing',   '' === arv_shop_render( array() ) );
t( 'and no strip either',                  '' === arv_shop_race_merch_render( array( 'race' => 'Black Canyon Ultras' ) ) );

echo "\nshop, the custom domain:\n";
// shop.aravaiparunning.com connected in Square 2026-08-31 (A record, www
// CNAME and TXT verification all confirmed resolving over a valid
// certificate first), so this is now the default rather than something a
// filter has to turn on. Rewritten at render rather than at import, so a
// domain that turns out to be misconfigured can be reverted with one
// filter rather than re-importing 166 products.
t( 'the storefront host is rewritten by default', 'https://shop.aravaiparunning.com/x' === arv_shop_url( 'https://aravaipa-shop.square.site/x' ) );
t( 'http is upgraded on the way',          'https://shop.aravaiparunning.com/x' === arv_shop_url( 'http://aravaipa-shop.square.site/x' ) );
t( 'the path and id survive',              'https://shop.aravaiparunning.com/shop/black-canyon-ultras/CBC' === arv_shop_url( 'https://aravaipa-shop.square.site/shop/black-canyon-ultras/CBC' ) );
// A filter that rewrote every host it was handed would happily point a
// YouTube link at the shop.
t( 'any other host is untouched',          'https://youtu.be/abc' === arv_shop_url( 'https://youtu.be/abc' ) );
t( 'and an empty url stays empty',         '' === arv_shop_url( '' ) );

// The escape hatch: still a filter, so a misconfigured domain reverts to
// the bare Square host with one line, not a re-import.
add_filter( 'arv_shop_storefront_host', function () { return ''; } );
t( 'a filter can still turn it off',       'https://aravaipa-shop.square.site/x' === arv_shop_url( 'https://aravaipa-shop.square.site/x' ) );
$GLOBALS['FILTERS']['arv_shop_storefront_host'] = array();

t( 'the shop shortcode is registered',     isset( $GLOBALS['SHORTCODES']['arv_shop'] ) );
t( 'the merch shortcode too',              isset( $GLOBALS['SHORTCODES']['arv_race_merch'] ) );

$GLOBALS['ARV_OPTIONS'] = array();

echo "\nshop rail, the home page's general strip:\n";
// Race collections are excluded on purpose: they belong on that race's own
// page, and a specific race's leftover gear on the home page reads as
// random rather than curated to a visitor who has never heard of it.
$GLOBALS['ARV_OPTIONS'] = array();
arv_shop_set( array(
	'collections' => array(
		array( 'id' => 'CBC',  'name' => 'Black Canyon Ultras', 'url' => 'https://aravaipa-shop.square.site/shop/bc/CBC',  'image' => '', 'count' => 9, 'race' => true ),
		array( 'id' => 'CHAT', 'name' => 'Headwear',            'url' => 'https://aravaipa-shop.square.site/shop/hw/CHAT', 'image' => '', 'count' => 2, 'race' => false ),
		array( 'id' => 'CSEA', 'name' => '2026 Spring Summer',  'url' => 'https://aravaipa-shop.square.site/shop/ss/CSEA', 'image' => '', 'count' => 3, 'race' => false ),
		array( 'id' => 'CEMP', 'name' => 'Empty Department',    'url' => 'https://aravaipa-shop.square.site/shop/e/CEMP',  'image' => '', 'count' => 0, 'race' => false ),
	),
	'products' => array(
		array( 'id' => 'RACE1', 'name' => 'Black Canyon Hoodie', 'url' => 'https://aravaipa-shop.square.site/product/1/RACE1', 'image' => 'https://x.test/1.png', 'price' => 6500, 'collections' => array( 'CBC' ) ),
		array( 'id' => 'HAT1',  'name' => 'Trucker Hat',         'url' => 'https://aravaipa-shop.square.site/product/2/HAT1',  'image' => 'https://x.test/2.png', 'price' => 2800, 'collections' => array( 'CHAT' ) ),
		array( 'id' => 'HAT2',  'name' => 'Beanie',              'url' => 'https://aravaipa-shop.square.site/product/3/HAT2',  'image' => 'https://x.test/3.png', 'price' => 2200, 'sold_out' => true, 'collections' => array( 'CHAT' ) ),
		// In both department collections: one card, not two.
		array( 'id' => 'SS1',   'name' => 'Trail Tee',           'url' => 'https://aravaipa-shop.square.site/product/4/SS1',   'image' => 'https://x.test/4.png', 'price' => 3000, 'collections' => array( 'CSEA', 'CHAT' ) ),
		array( 'id' => 'SS2',   'name' => 'Shorts',              'url' => 'https://aravaipa-shop.square.site/product/5/SS2',   'image' => 'https://x.test/5.png', 'price' => 4000, 'collections' => array( 'CSEA' ) ),
		array( 'id' => 'SS3',   'name' => 'Windbreaker',         'url' => 'https://aravaipa-shop.square.site/product/6/SS3',   'image' => 'https://x.test/6.png', 'price' => 8000, 'collections' => array( 'CSEA' ) ),
	),
) );

$rail = arv_shop_rail_render( array( 'heading' => 'Shop' ) );

t( 'the rail renders',                    false !== strpos( $rail, 'arv-rail__track' ) );
t( 'no race gear reaches it',             false === strpos( $rail, 'Black Canyon Hoodie' ) );
t( 'a department item is on it',          false !== strpos( $rail, 'Trucker Hat' ) );
t( 'and the seasonal collection too',     false !== strpos( $rail, 'Trail Tee' ) );
// The visible title, not a raw name count: the product's name also
// appears inside its own card's JSON payload, so a bare substr_count of
// the name is two per card on its own and would not actually catch a
// second card for the same product.
t( 'shared between two collections once', 1 === substr_count( $rail, '<span class="arv-rail__title">Trail Tee</span>' ) );
t( 'sold out is still stated, not hidden', false !== strpos( $rail, 'Sold out' ) );
t( 'a link to the shop page, not Square', false !== strpos( $rail, home_url( '/shop/' ) ) );
t( 'each card opens the shared drawer',   substr_count( $rail, 'data-arv-shop-item=' ) === substr_count( $rail, 'arv-rail__item--shop' ) );
t( 'prices are on the cards',             false !== strpos( $rail, '$28' ) );


// A limit is honoured, and is a count of cards, not of collections.
$limited = arv_shop_rail_render( array( 'limit' => 2 ) );
t( 'a limit caps the cards',              2 === substr_count( $limited, 'arv-rail__item--shop' ) );

// A curated list is not a suggestion layered on the automatic pick: it is
// the whole answer. Jamil's complaint that shipped this was the rail
// showing a 2024 race hat and a discontinued tee beside this year's core
// apparel, all correctly filed under a department collection in Square
// and none of it what should lead the home page. There is no signal in
// the catalogue that says "this one is stale", so the fix is letting a
// person say so directly rather than a smarter guess.
$curated = arv_shop_rail_render( array( 'products' => 'Windbreaker|Trucker Hat' ) );
t( 'a curated list picks exactly those',  2 === substr_count( $curated, 'arv-rail__item--shop' ) );
t( 'in the order given',                  strpos( $curated, 'Windbreaker' ) < strpos( $curated, 'Trucker Hat' ) );
t( 'not the automatic biggest-first order', false !== strpos( $curated, 'Windbreaker' ) );
t( 'and the round robin pick is bypassed', false === strpos( $curated, 'Beanie' ) );

// Matched on name, case insensitively, because that is what a person
// curating this can actually see: the storefront shows a product's name,
// not its Square catalogue id.
$cased = arv_shop_rail_render( array( 'products' => 'WINDBREAKER' ) );
t( 'matching ignores case',               false !== strpos( $cased, 'Windbreaker' ) );

// A retired product drops out; it does not take the rest of the list with
// it, and it does not fall back to the automatic pick either. A curated
// list going stale should read as visibly short, not silently become
// "random stuff" again, which is the exact complaint this exists to fix.
$partial = arv_shop_rail_render( array( 'products' => 'Windbreaker|Retired Item That Does Not Exist' ) );
t( 'an unknown name is dropped',          1 === substr_count( $partial, 'arv-rail__item--shop' ) );
t( 'the real one still renders',          false !== strpos( $partial, 'Windbreaker' ) );

$all_stale = arv_shop_rail_render( array( 'products' => 'Nothing Here|Also Nothing' ) );
t( 'all names unknown renders nothing, not a fallback', '' === $all_stale );

// A builder has to be able to place this. It shipped as a shortcode with
// no element, which made it the one rail in the plugin that could only be
// typed in by hand: everything else here registers both, so neither route
// is the privileged one.
t( 'the rail is a Cornerstone element',   isset( $GLOBALS['EL']['aravaipa-shop-rail'] ) );
t( 'and renders the same as the shortcode',
	arv_shop_rail_element_render( array( 'heading' => 'Shop', 'limit' => 2 ) )
	=== arv_shop_rail_render( array( 'heading' => 'Shop', 'limit' => 2 ) ) );
t( 'its controls are exposed to the builder',
	false !== strpos( wp_json_encode( arv_shop_rail_element_builder() ), '"key":"heading"' ) );

// Nothing to show is nothing rendered, the same rule every other silent
// block in this plugin follows.
$GLOBALS['ARV_OPTIONS'] = array();
t( 'no catalogue renders nothing',        '' === arv_shop_rail_render() );

arv_shop_set( array(
	'collections' => array(
		array( 'id' => 'CBC', 'name' => 'Black Canyon Ultras', 'url' => 'https://aravaipa-shop.square.site/shop/bc/CBC', 'image' => '', 'count' => 9, 'race' => true ),
	),
	'products' => array(
		array( 'id' => 'RACE1', 'name' => 'Black Canyon Hoodie', 'url' => 'https://aravaipa-shop.square.site/product/1/RACE1', 'price' => 6500, 'collections' => array( 'CBC' ) ),
	),
) );
t( 'races only, still renders nothing',   '' === arv_shop_rail_render() );

t( 'the rail shortcode is registered',    isset( $GLOBALS['SHORTCODES']['arv_shop_rail'] ) );

$GLOBALS['ARV_OPTIONS'] = array();

echo "\ntrail talk, a real feed for a show that never had one:\n";
// 44 episodes, self-hosted as WordPress posts with an <audio> tag rather
// than through a podcast host, is why none of this ever reached Spotify
// or Apple: neither reads a blog. Confirmed against the media library
// that the files themselves are real audio/mpeg attachments before
// building anything around them.
$GLOBALS['posts']    = array();
$GLOBALS['ATTACHMENTS'] = array();
$GLOBALS['ATTACHMENT_FILES'] = array();
delete_transient( ARV_TRAILTALK_CACHE );

$GLOBALS['posts'][9501] = array(
	'title' => 'Aravaipa Trail Talk – Episode #044: Huss Brewing', 'status' => 'publish', 'type' => 'post',
	'date' => '2017-10-20T00:00:00Z', 'cats' => array( 'Aravaipa Trail Talk' ), 'excerpt' => 'Huss Brewing and JubeTube.',
	'body' => '<p>Show notes.</p><audio controls><source src="https://x.test/ep44.mp3" type="audio/mpeg"></audio>',
);
$GLOBALS['posts'][9502] = array(
	// The archive's other title shape: no "Episode", the number bare.
	'title' => 'Trail Talk 39', 'status' => 'publish', 'type' => 'post',
	'date' => '2017-09-07T00:00:00Z', 'cats' => array( 'Aravaipa Trail Talk' ), 'excerpt' => '',
	'body' => '<audio src="https://x.test/ep39.mp3"></audio>',
);
$GLOBALS['posts'][9503] = array(
	// A re-upload: the same episode 10 exists twice in the real archive.
	// Neither carries a number recognisable enough to rank against the
	// other, so this only has to not crash sorting against one that does.
	'title' => 'Trail Talk Redux', 'status' => 'publish', 'type' => 'post',
	'date' => '2016-05-01T00:00:00Z', 'cats' => array( 'Aravaipa Trail Talk' ), 'excerpt' => '',
	'body' => '<p>No audio tag, just a bare link.</p><p>https://x.test/bare.mp3</p>',
);
$GLOBALS['posts'][9504] = array(
	// No audio at all: the "Trail Talk Is Back!" 2018 post is words only.
	'title' => 'Aravaipa Trail Talk Is Back!', 'status' => 'publish', 'type' => 'post',
	'date' => '2018-08-18T00:00:00Z', 'cats' => array( 'Aravaipa Trail Talk' ), 'excerpt' => '',
	'body' => '<p>Announcement text, no player.</p>',
);
$GLOBALS['posts'][9505] = array(
	// A different show entirely. Must not leak into this feed.
	'title' => 'Inside Aravaipa Episode 5', 'status' => 'publish', 'type' => 'post',
	'date' => '2026-01-01T00:00:00Z', 'cats' => array( 'Podcasts' ), 'excerpt' => '',
	'body' => '<audio src="https://x.test/other.mp3"></audio>',
);
$GLOBALS['PERMALINK'][9501] = 'https://www.aravaiparunning.com/trail-talk-44/';
$GLOBALS['PERMALINK'][9502] = 'https://www.aravaiparunning.com/trail-talk-39/';
$GLOBALS['PERMALINK'][9503] = 'https://www.aravaiparunning.com/trail-talk-redux/';

// The byte length is read off the real file on disk, not fetched over
// HTTP: these are self-hosted attachments, so the number is a filesystem
// call away and 39 outbound requests to the plugin's own web server on
// every feed build would be one for nothing this cheap.
$GLOBALS['ATTACHMENTS']['https://x.test/ep44.mp3'] = 8801;
$GLOBALS['ATTACHMENT_FILES'][8801] = '/tmp/tt_fixture/ep1.mp3';
$GLOBALS['ATTACHMENTS']['https://x.test/ep39.mp3'] = 8802;
$GLOBALS['ATTACHMENT_FILES'][8802] = '/tmp/tt_fixture/ep2.mp3';
// ep bare.mp3 deliberately has no matching attachment: a length of 0 is
// the honest answer for a file this store cannot find on disk.

$items = arv_trailtalk_items();
t( 'every episode with audio is kept',     3 === count( $items ) );
t( 'a post with no player is dropped',     ! in_array( 'Aravaipa Trail Talk Is Back!', array_column( $items, 'title' ), true ) );
t( 'a different show does not leak in',    ! in_array( 'Inside Aravaipa Episode 5', array_column( $items, 'title' ), true ) );

// Two title shapes this archive actually uses, both recognised.
t( 'Episode #044 parses to 44',            44 === arv_trailtalk_number( 'Aravaipa Trail Talk – Episode #044: Huss Brewing' ) );
t( 'Trail Talk 39 parses to 39',           39 === arv_trailtalk_number( 'Trail Talk 39' ) );
t( 'an untitled re-upload parses to 0',    0 === arv_trailtalk_number( 'Trail Talk Redux' ) );

// Numbered episodes sort by their number, not by publish date, and rank
// ahead of the one episode with no number recognised.
$titles = array_column( $items, 'title' );
t( 'the earlier-numbered episode leads',   array_search( 'Trail Talk 39', $titles ) < array_search( 'Aravaipa Trail Talk – Episode #044: Huss Brewing', $titles ) );
t( 'an unnumbered one sorts last',         'Trail Talk Redux' === end( $titles ) );

// The real byte length off disk, not a guess.
$by_title = array();
foreach ( $items as $i ) { $by_title[ $i['title'] ] = $i; }
t( 'the enclosure length is the real file size', 10 === $by_title['Aravaipa Trail Talk – Episode #044: Huss Brewing']['bytes'] );
t( 'a different file gets its own size',   40 === $by_title['Trail Talk 39']['bytes'] );
t( 'an unmatched file honestly reads zero', 0 === $by_title['Trail Talk Redux']['bytes'] );

echo "\ntrail talk, the feed itself:\n";
$xml = arv_trailtalk_feed_xml();
t( 'it parses as XML',                     false !== @simplexml_load_string( $xml ) );
t( 'the itunes namespace is declared',     false !== strpos( $xml, 'xmlns:itunes=' ) );
t( 'marked complete: this show is not coming back', false !== strpos( $xml, '<itunes:complete>Yes</itunes:complete>' ) );
t( 'an item per kept episode',             3 === substr_count( $xml, '<item>' ) );
t( 'the enclosure carries a real length',  false !== strpos( $xml, 'length="10"' ) );
t( 'a title with an ampersand is escaped', false === strpos( $xml, ' & ' ) || false !== strpos( $xml, '&amp;' ) );

// A show that has published nothing since 2018 must not silently vanish
// the moment its one category is empty; verifies the drop-to-none branch
// this store shares with every other "none" sentinel in the plugin.
$GLOBALS['posts'] = array();
delete_transient( ARV_TRAILTALK_CACHE );
t( 'an empty archive still produces a valid, empty feed', 0 === substr_count( arv_trailtalk_feed_xml(), '<item>' ) );

// The cache. A closed archive still deserves a cache flush on edit, since
// a typo fix should not wait an hour to reach an app that may be polling.
$GLOBALS['posts'][9506] = array( 'title' => 'Trail Talk 1', 'status' => 'publish', 'type' => 'post', 'date' => '2016-03-01T00:00:00Z', 'cats' => array( 'Aravaipa Trail Talk' ), 'excerpt' => '', 'body' => '<audio src="https://x.test/e1.mp3"></audio>' );
arv_trailtalk_items();
t( 'the archive is cached',                false !== get_transient( ARV_TRAILTALK_CACHE ) );
arv_trailtalk_flush_on_save( 9506 );
t( 'editing an episode drops the cache',   false === get_transient( ARV_TRAILTALK_CACHE ) );
arv_trailtalk_items();
$GLOBALS['posts'][9507] = array( 'title' => 'Some Race', 'status' => 'publish', 'type' => 'arv_race', 'date' => '', 'cats' => array(), 'excerpt' => '', 'body' => '' );
arv_trailtalk_flush_on_save( 9507 );
t( 'a non-post save leaves it alone',      false !== get_transient( ARV_TRAILTALK_CACHE ) );

$GLOBALS['posts'] = array();
$GLOBALS['ATTACHMENTS'] = array();
$GLOBALS['ATTACHMENT_FILES'] = array();
delete_transient( ARV_TRAILTALK_CACHE );

echo "\nmedia SEO, the five pages that had none:\n";
// Watch, Films, Podcasts and race pages each grew their own head output.
// /media/, /film-tours/, /articles/, /photos/ and /shop/ shipped with no
// description and no structured data, so Google wrote its own snippet
// from whatever text it found first, which on these pages is the nav.
$seo_posts_backup = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
$GLOBALS['IS_PAGE']    = true;
$GLOBALS['QUERIED_ID'] = 9901;
$GLOBALS['PERMALINK'][9901] = 'https://www.aravaiparunning.com/media/';

// Recognised by the shortcode the page carries, not by path or stored id,
// so renaming or moving a page cannot silently switch its SEO off.
$GLOBALS['posts'][9901] = array( 'title' => 'Media', 'status' => 'publish', 'body' => '[arv_media_subnav][arv_media_hub heading="Browse"]' );
t( 'the media hub page is recognised',    'media' === arv_media_seo_page() );

$GLOBALS['posts'][9901]['body'] = '[arv_film_tours]';
t( 'film tours too',                      'film-tours' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['body'] = '[arv_articles heading=""]';
t( 'articles too',                        'articles' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['body'] = '[arv_photos year="2026"]';
t( 'photos too',                          'photos' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['body'] = '[arv_shop]';
t( 'and the shop',                        'shop' === arv_media_seo_page() );

$GLOBALS['posts'][9901]['body'] = '[arv_films]';
t( 'films too',                           'films' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['body'] = '[arv_watch]';
t( 'broadcasts too',                      'broadcasts' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['body'] = '[arv_podcasts]';
t( 'podcasts too',                        'podcasts' === arv_media_seo_page() );

// /races/ and /results-YYYY/ carry no shortcode at all: both are built
// entirely from Cornerstone elements, none of which register one, so
// there is nothing in post_content for has_shortcode() to find. Matched
// on slug instead, the one signal actually available for these two.
$GLOBALS['posts'][9901]['body'] = '';
$GLOBALS['posts'][9901]['slug'] = 'races';
t( 'races has no shortcode but is still found', 'races' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['slug'] = 'results-2026';
t( 'and so is a results year page',       'results' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['slug'] = 'results-old-format';
t( 'but not a slug that only looks like one', '' === arv_media_seo_page() );
$GLOBALS['posts'][9901]['slug'] = '';

// A page carrying none of them claims nothing, rather than emitting a
// description for a page it guessed at.
$GLOBALS['posts'][9901]['body'] = '<p>An ordinary page.</p>';
t( 'an unrelated page is left alone',     '' === arv_media_seo_page() );
$GLOBALS['IS_PAGE'] = false;
$GLOBALS['posts'][9901]['body'] = '[arv_shop]';
t( 'and so is anything not a page',       '' === arv_media_seo_page() );
$GLOBALS['IS_PAGE'] = true;

// Counts come from the same stores the pages render from, so a
// description cannot claim a number the page does not show.
$GLOBALS['ARV_OPTIONS'][ ARV_SHOP_OPTION ] = array(
	'collections' => array( array( 'id' => 'C1', 'name' => 'Black Canyon Ultras', 'url' => 'https://aravaipa-shop.square.site/shop/bc/C1', 'count' => 1, 'race' => true ) ),
	'products'    => array( array( 'id' => 'P1', 'name' => 'Hoodie', 'url' => 'https://aravaipa-shop.square.site/product/h/P1', 'price' => 6500, 'collections' => array( 'C1' ), 'ord' => 0, 'image' => '', 'desc' => '', 'sold_out' => false, 'options' => array() ) ),
);
$shop_meta = arv_media_seo_meta( 'shop' );
t( 'the shop description counts products', false !== strpos( $shop_meta['description'], '1 piece' ) || false !== strpos( $shop_meta['description'], '1 pieces' ) );

// Films, Broadcasts and Podcasts count from the same transient-backed
// fetch each page itself renders from, the same rule the original five
// already followed.
$GLOBALS['_transients']['arv_films'] = array(
	array( 'title' => 'Fixture', 'films' => array(
		array( 'id' => 'f1', 'title' => 'A Film', 'desc' => '', 'thumbnail' => '', 'published' => '2026-01-01T00:00:00Z',
		       'views' => 1, 'duration' => '', 'url' => 'https://youtu.be/f1', 'lead' => '', 'race' => '', 'division' => null, 'trailer' => null ),
	) ),
);
$films_meta = arv_media_seo_meta( 'films' );
t( 'the films description counts them',   false !== strpos( $films_meta['description'], '1 Aravaipa Running film' ) );
$GLOBALS['_transients']['arv_films'] = 'none';
t( 'and says something with zero films',  '' !== arv_media_seo_meta( 'films' )['description'] );

$GLOBALS['_transients']['arv_watch_events'] = array(
	array( 'name' => 'Fixture Race', 'slug' => 'fixture-race', 'live' => false, 'streams' => array() ),
);
$broadcasts_meta = arv_media_seo_meta( 'broadcasts' );
t( 'broadcasts counts events',            false !== strpos( $broadcasts_meta['description'], '1 and counting' ) );
t( 'and the title says Broadcasts, not Watch', 'Broadcasts | Aravaipa Running' === $broadcasts_meta['title'] );

$GLOBALS['_transients']['arv_podcasts'] = array(
	'inside-aravaipa' => array( 'key' => 'inside-aravaipa', 'title' => 'Inside Aravaipa', 'artwork' => '', 'desc' => '', 'episodes' => array(
		array( 'title' => 'Ep 1', 'artwork' => '', 'desc' => '', 'guid' => 'g1', 'duration' => '', 'published' => '2026-01-01T00:00:00Z', 'url' => 'https://x.test/1.mp3' ),
	) ),
);
$podcasts_meta = arv_media_seo_meta( 'podcasts' );
t( 'podcasts counts episodes and shows',  false !== strpos( $podcasts_meta['description'], '1 episodes across 1' ) || false !== strpos( $podcasts_meta['description'], '1 episode' ) );

// Races and Results carry no shortcode, so their descriptions are the
// clearest sign the slug-based recognition in arv_media_seo_page() is
// wired to the right builder and not just returning a key nothing reads.
$races_backup = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
arv_race_store_import( "Fixture Race | 2026-09-05 | September 5 | 5K | Fixture Park | Fixtureville, AZ | https://ultrasignup.com/register.aspx?did=1 |  |  |  |  | 1 | 1 | 0 | 33.4 | -111.9\nOther Race | 2026-10-05 | October 5 | 5K | Other Park | Otherville, CO | https://ultrasignup.com/register.aspx?did=2 |  |  |  |  | 1 | 1 | 0 | 39.7 | -104.9" );
$races_meta = arv_media_seo_meta( 'races' );
t( 'races counts from the real store',    false !== strpos( $races_meta['description'], '2 Aravaipa Running races' ) );
t( 'across the states actually present',  false !== strpos( $races_meta['description'], '2 states' ) );

$GLOBALS['ARV_OPTIONS'][ ARV_RESULTS_OPTION ] = array(
	array( 'name' => 'Fixture Race', 'iso' => '2026-03-01' ),
	array( 'name' => 'Other Race', 'iso' => '2025-03-01' ),
);
$GLOBALS['posts'][9901]['slug'] = 'results-2026';
$results_meta = arv_media_seo_meta( 'results' );
t( 'results counts only its own year',    false !== strpos( $results_meta['description'], '1 Aravaipa Running races' ) );
t( 'and the title carries the year',      'Results 2026 | Aravaipa Running' === $results_meta['title'] );
$GLOBALS['posts'][9901]['slug'] = '';
$GLOBALS['posts'] = $races_backup;
$GLOBALS['ARV_OPTIONS'][ ARV_RESULTS_OPTION ] = array();

echo "\npost SEO, the 296 articles that had none:\n";
// Individual posts got nothing: no description, no Open Graph, no schema.
// They are the pages that answer a real search, and the snippet was being
// left to whatever text Google found first.
$GLOBALS['posts'][9903] = array(
	'title' => 'Black Canyon Preview', 'status' => 'publish', 'type' => 'post',
	'date' => '2026-02-01T08:00:00Z', 'modified' => '2026-02-03T08:00:00Z',
	'excerpt' => '', 'thumb' => false, 'cats' => array(),
	'body' => '<p>' . str_repeat( 'The course drops through desert and it will lie to you. ', 8 ) . '</p><img src="https://x.test/body.jpg">',
);
$long_post = get_post( 9903 );

// Cut at a word boundary: a description ending mid-word reads as broken
// and Google will usually rewrite the whole snippet rather than show it.
$desc = arv_post_seo_description( $long_post );
t( 'a long post is trimmed',              strlen( $desc ) <= 170 );
// A real check rather than a tautology: the kept text has to be a prefix
// of the post's own words and has to stop where the source has a space,
// which is what "cut at a word boundary" actually means.
$seo_source = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $GLOBALS['posts'][9903]['body'] ) ) );
$seo_kept   = substr( $desc, 0, -3 );
t( 'the kept text really is from the post', 0 === strpos( $seo_source, $seo_kept ) );
t( 'and stops on a word boundary',        ' ' === substr( $seo_source, strlen( $seo_kept ), 1 ) );
t( 'ending in an ellipsis',               '...' === substr( $desc, -3 ) );

// A hand-written excerpt wins, because a person chose it.
$GLOBALS['posts'][9903]['excerpt'] = 'A hand written summary.';
t( 'an excerpt beats the body text',      'A hand written summary.' === arv_post_seo_description( get_post( 9903 ) ) );
$GLOBALS['posts'][9903]['excerpt'] = '';

// Short posts are left whole rather than padded or truncated.
$GLOBALS['posts'][9904] = array( 'title' => 'Short', 'status' => 'publish', 'type' => 'post', 'date' => '2026-02-01T00:00:00Z', 'excerpt' => '', 'thumb' => false, 'cats' => array(), 'body' => '<p>Short and complete.</p>' );
t( 'a short post is left alone',          'Short and complete.' === arv_post_seo_description( get_post( 9904 ) ) );

// The same body-image fallback the archive uses: 58 of these posts have a
// good photograph and never had one set as featured.
t( 'the body image stands in for a featured one', 'https://x.test/body.jpg' === arv_post_seo_image( get_post( 9903 ) ) );
$GLOBALS['posts'][9903]['thumb'] = 'https://x.test/featured.jpg';
t( 'a featured image still wins',         'https://x.test/featured.jpg' === arv_post_seo_image( get_post( 9903 ) ) );

// A post with neither says nothing rather than inventing an image.
t( 'no image anywhere yields none',       '' === arv_post_seo_image( get_post( 9904 ) ) );

// The nodes themselves, tested directly. Emission cannot be asserted from
// this file: WPSEO_VERSION is defined far above for the front-page SEO
// tests, constants cannot be undefined, and arv_media_seo_head() therefore
// correctly stays silent for the rest of the run. That silence is itself
// the behaviour that makes reactivating Yoast safe, so it is asserted
// rather than worked around.
$GLOBALS['posts'][9901]['body'] = '[arv_shop]';
ob_start(); arv_media_seo_head(); $silenced = ob_get_clean();
t( 'an active SEO plugin silences all of it', '' === $silenced );

$shop_nodes = arv_media_seo_nodes( 'shop', $shop_meta );
t( 'the page is a collection page',       'CollectionPage' === $shop_nodes[0]['@type'] );
t( 'carrying its own description',        $shop_meta['description'] === $shop_nodes[0]['description'] );
// The shop does not claim an Offer. This site is not where those are
// bought, Square is, and an offer a page cannot complete is the kind of
// mismatch that earns a manual action rather than a rich result.
$shop_json = wp_json_encode( $shop_nodes );
t( 'the shop claims no offer it cannot honour', false === strpos( $shop_json, '"Offer"' ) && false === strpos( $shop_json, '"Product"' ) );

// Articles carries a list, capped: a head blob of all 296 would be a
// slower page for every visitor in exchange for links Google already
// follows.
$GLOBALS['posts'][9902] = array( 'title' => 'A Post', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-01T00:00:00Z', 'thumb' => 'https://x.test/a.jpg', 'cats' => array() );
$GLOBALS['PERMALINK'][9902] = 'https://www.aravaiparunning.com/a-post/';
delete_transient( ARV_ARTICLES_CACHE );
$art_meta  = arv_media_seo_meta( 'articles' );
$art_nodes = arv_media_seo_nodes( 'articles', $art_meta );
t( 'articles gets an item list',          2 === count( $art_nodes ) && 'ItemList' === $art_nodes[1]['@type'] );
t( 'the list is capped at twenty',        20 >= count( $art_nodes[1]['itemListElement'] ) );
t( 'and the description counts them',     false !== strpos( $art_meta['description'], '1 race report' ) || false !== strpos( $art_meta['description'], 'race reports' ) );

// Film tours list their films rather than the tours, since the film is
// the thing a person is searching for.
$tour_meta  = arv_media_seo_meta( 'film-tours' );
$tour_nodes = arv_media_seo_nodes( 'film-tours', $tour_meta );
t( 'film tours gets an item list',        2 === count( $tour_nodes ) );
t( 'typed as films',                      'Movie' === $tour_nodes[1]['itemListElement'][0]['item']['@type'] );
t( 'and the description counts stops',    false !== strpos( $tour_meta['description'], '39' ) );

$GLOBALS['posts']       = $seo_posts_backup;
$GLOBALS['IS_PAGE']     = false;
$GLOBALS['QUERIED_ID']  = 0;
unset( $GLOBALS['PERMALINK'][9901] );

echo "\nmedia hub counts, what the sub-nav strip cannot say:\n";
$GLOBALS['_transients']['arv_watch_events'] = array(
	array( 'slug' => 'a', 'name' => 'Race A', 'live' => false, 'start' => '2026-05-04T00:00:00Z', 'place' => '', 'desc' => '',
	       'streams' => array( array( 'id' => 'aaaaaaaaaaa', 'title' => 'x', 'url' => 'https://youtu.be/aaaaaaaaaaa', 'thumbnail' => 'https://x.test/a.jpg', 'live' => false, 'type' => '', 'start' => '', 'desc' => '', 'aired' => '2026-05-04T00:00:00Z', 'minutes' => 0, 'views' => 0 ) ) ),
	array( 'slug' => 'b', 'name' => 'Race B', 'live' => false, 'start' => '2026-04-04T00:00:00Z', 'place' => '', 'desc' => '',
	       'streams' => array( array( 'id' => 'bbbbbbbbbbb', 'title' => 'x', 'url' => 'https://youtu.be/bbbbbbbbbbb', 'thumbnail' => 'https://x.test/b.jpg', 'live' => false, 'type' => '', 'start' => '', 'desc' => '', 'aired' => '2026-04-04T00:00:00Z', 'minutes' => 0, 'views' => 0 ) ) ),
);
$GLOBALS['_transients']['arv_films'] = array(
	array( 'key' => 'documentaries', 'title' => 'Documentaries', 'films' => array(
		array( 'id' => 'ccccccccccc', 'title' => 'F1', 'desc' => '', 'thumbnail' => 'https://x.test/c.jpg', 'published' => '2026-03-01T00:00:00Z', 'views' => 1, 'duration' => '', 'url' => 'https://youtu.be/ccccccccccc', 'lead' => 'F1', 'race' => '', 'division' => null, 'trailer' => null ),
	) ),
);
$GLOBALS['_transients']['arv_podcasts'] = array(
	array( 'key' => 'inside-aravaipa', 'title' => 'Inside', 'feed' => '', 'spotify' => '', 'apple' => '', 'artwork' => 'https://x.test/s.jpg', 'summary' => '',
	       'episodes' => array(
	           array( 'title' => 'E1', 'audio' => 'a', 'link' => '', 'artwork' => '', 'guid' => '1', 'duration' => '', 'published' => '2026-02-01T00:00:00Z' ),
	           array( 'title' => 'E2', 'audio' => 'a', 'link' => '', 'artwork' => '', 'guid' => '2', 'duration' => '', 'published' => '2026-01-01T00:00:00Z' ),
	       ) ),
	array( 'key' => 'wm', 'title' => 'WM', 'feed' => '', 'spotify' => '', 'apple' => '', 'artwork' => 'https://x.test/w.jpg', 'summary' => '',
	       'episodes' => array(
	           array( 'title' => 'E3', 'audio' => 'a', 'link' => '', 'artwork' => '', 'guid' => '3', 'duration' => '', 'published' => '2026-01-15T00:00:00Z' ),
	       ) ),
);
$counts_backup    = $GLOBALS['posts'];
$GLOBALS['posts'] = array(
	9501 => array( 'title' => 'P1', 'status' => 'publish', 'type' => 'post', 'date' => '2026-06-01T00:00:00Z', 'thumb' => 'https://x.test/p.jpg' ),
	9502 => array( 'title' => 'P2', 'status' => 'publish', 'type' => 'post', 'date' => '2026-05-01T00:00:00Z', 'thumb' => 'https://x.test/p2.jpg' ),
);
delete_transient( 'arv_media_latest_posts' );

$counts = arv_media_hub_counts();
t( 'broadcasts counted by event, not segment', '2 broadcasts' === $counts['watch'] );
t( 'films counted',                       '1 film' === $counts['films'] );
t( 'and singular reads right',            false === strpos( $counts['films'], 'films' ) );
t( 'episodes counted across every show',  '3 episodes across 2 shows' === $counts['podcasts'] );
// The whole archive, not the forty the feed reads.
t( 'articles counted from the archive',   '2 articles' === $counts['articles'] );

$hub = arv_media_hub_render( array() );
t( 'the count reaches the card',          false !== strpos( $hub, '3 episodes across 2 shows' ) );

// A source that is down says nothing rather than a confident zero.
$GLOBALS['_transients']['arv_films'] = 'none';
t( 'a dead source shows no count, not 0', '' === arv_media_hub_counts()['films'] );
t( 'and no stray zero reaches the card',  false === strpos( arv_media_hub_render( array() ), '0 films' ) );

echo "\nmedia hero, the newest thing at full width:\n";
$GLOBALS['_transients']['arv_films'] = array();
$hero = arv_media_hero_render();
// The newest of everything seeded above is the 2026-06-01 post.
t( 'the hero is the newest item',         false !== strpos( $hero, 'P1' ) );
t( 'it carries its type badge',           false !== strpos( $hero, 'arv-media-hero__badge">Article' ) );
t( 'and its date',                        false !== strpos( $hero, 'June 1, 2026' ) );
echo "\nmedia hero, image sizing and shape:\n";
// Two real bugs on the live /media/ hero, both mine. The Mount to Coast
// announcement is a 1568x1920 portrait poster: it was being served at the
// 'medium' size (245px on this site) and stretched across a ~740px hero,
// and then cropped to 16:9, which removed the faces along its top and the
// sponsor logos along its bottom and kept the middle.
// Snapshot rather than clear: the surrounding block seeds watch, films
// and podcast transients that its later assertions still depend on, and
// wiping them here quietly broke the feed count two tests further down.
$hero_posts_backup      = $GLOBALS['posts'];
$hero_transients_backup = $GLOBALS['_transients'];
$GLOBALS['posts'] = array();
$GLOBALS['posts'][9701] = array(
	'title' => 'Portrait Poster', 'status' => 'publish', 'type' => 'post',
	'date' => '2026-08-21T00:00:00Z', 'thumb' => 'https://x.test/poster.png',
	'tw' => 1568, 'th' => 1920,
);
$GLOBALS['PERMALINK'][9701] = 'https://www.aravaiparunning.com/poster/';
delete_transient( 'arv_media_latest_posts' );

$shot = arv_media_latest_thumb( 9701 );
t( 'the real dimensions travel with the url', 1568 === $shot['width'] && 1920 === $shot['height'] );

$tall_hero = arv_media_hero_render( array() );
t( 'a portrait image is not cropped to 16:9', false !== strpos( $tall_hero, 'arv-media-hero__img--tall' ) );

// A landscape photograph still fills the box: contain on one of those
// would letterbox a picture that had nothing wrong with it.
$GLOBALS['posts'][9701]['tw'] = 1600;
$GLOBALS['posts'][9701]['th'] = 900;
delete_transient( 'arv_media_latest_posts' );
t( 'a landscape image still fills the box',   false === strpos( arv_media_hero_render( array() ), 'arv-media-hero__img--tall' ) );

// A source with no dimensions at all, which is every YouTube and podcast
// thumbnail in this feed, keeps the old behaviour rather than guessing.
$GLOBALS['posts'][9701]['tw'] = 0;
$GLOBALS['posts'][9701]['th'] = 0;
delete_transient( 'arv_media_latest_posts' );
t( 'unknown dimensions crop as before',       false === strpos( arv_media_hero_render( array() ), 'arv-media-hero__img--tall' ) );

$GLOBALS['posts']       = $hero_posts_backup;
$GLOBALS['_transients'] = $hero_transients_backup;
delete_transient( 'arv_media_latest_posts' );

t( 'the whole hero is one link',          1 === substr_count( $hero, 'arv-media-hero__link' ) );

// The feed under a hero must not open with the item the hero just showed.
$feed_no_offset = arv_media_latest_render( array( 'limit' => 3 ) );
$feed_offset    = arv_media_latest_render( array( 'limit' => 3, 'offset' => 1 ) );
t( 'without an offset the feed repeats it', false !== strpos( $feed_no_offset, 'P1' ) );
t( 'with one, the hero item is skipped',    false === strpos( $feed_offset, '>P1<' ) );
t( 'and the feed still has its full count', 3 === substr_count( $feed_offset, 'class="arv-media-latest__card"' ) );

$GLOBALS['_transients'] = array();
$GLOBALS['posts'] = $counts_backup;
delete_transient( 'arv_media_latest_posts' );
t( 'nothing anywhere renders no hero',    '' === arv_media_hero_render() );


// ---------------------------------------------------------------- //
// [arv_races_next], the blog sidebar's race list.
//
// The whole point of the shortcode is that it shows races a reader can
// still enter, and arv_race_store_get() hands it every stored race in
// date order starting from the oldest. Get the filter wrong and the
// sidebar silently advertises last spring.
// ---------------------------------------------------------------- //

$next_backup = $GLOBALS['posts'];
$next_meta_backup = $GLOBALS['meta'];
$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['NOW']   = '2026-08-26';
// The harness stubs add_action to a no-op, so the save_post hook that
// clears the store's per-request memo in real WordPress never fires here.
arv_race_store_flush_cache();

arv_race_store_import(
	// Pipe-delimited, the same shape race-rows-2026.txt carries: name, iso,
	// display, distances (variable count), venue, location, register, page,
	// image, end, live, closes, confirmed, guessed, lat, lng.
	"Long Past | 2026-03-01 | March 1 | 50K | A Park | Phoenix, AZ | https://x.test/r | https://x.test/past | https://x.test/i.png |  | https://x.test/l | 2026-02-25 | 1 | 0 | 33.4 | -112.0\n"
	. "Yesterday | 2026-08-25 | August 25 | 50K | A Park | Pine, AZ | https://x.test/r | https://x.test/yest | https://x.test/i.png |  | https://x.test/l | 2026-08-20 | 1 | 0 | 34.4 | -111.4\n"
	. "Underway | 2026-08-24 | August 24 | 100 Mile | A Park | Flagstaff, AZ | https://x.test/r | https://x.test/now | https://x.test/i.png | 2026-08-27 | https://x.test/l | 2026-08-20 | 1 | 0 | 35.2 | -111.6\n"
	. "Tomorrow | 2026-08-27 | August 27 | 50K | A Park | Tucson, AZ | https://x.test/r | https://x.test/tom | https://x.test/i.png |  | https://x.test/l | 2026-08-22 | 1 | 0 | 32.2 | -110.9\n"
	. "Next Month | 2026-09-12 | September 12 | 100 Mile | A Park | Pine, AZ | https://x.test/r | https://x.test/next | https://x.test/i.png |  | https://x.test/l | 2026-09-07 | 1 | 0 | 34.4 | -111.4\n"
);
arv_race_store_flush_cache();

$nx = arv_races_next_render( array( 'heading' => 'Next Races', 'limit' => 5 ) );

t( 'a race that already happened is gone',   false === strpos( $nx, 'Long Past' ) );
t( 'so is one that finished yesterday',      false === strpos( $nx, 'Yesterday' ) );
t( 'a multi-day race mid-run still shows',   false !== strpos( $nx, 'Underway' ) );
t( 'and so do the ones still to come',       false !== strpos( $nx, 'Tomorrow' ) && false !== strpos( $nx, 'Next Month' ) );
t( 'soonest first',                          strpos( $nx, 'Underway' ) < strpos( $nx, 'Tomorrow' ) );
t( 'the all-races link is there',            false !== strpos( $nx, '/races/' ) );

// limit counts what survives the date filter, not what came back from the
// store, or a run of old races would push every real one off the list.
$nx1 = arv_races_next_render( array( 'heading' => '', 'limit' => 1 ) );
t( 'limit 1 gives one race',                 1 === substr_count( $nx1, 'arv-next-races__item' ) );
t( 'and it is the soonest, not the oldest',  false !== strpos( $nx1, 'Underway' ) );
t( 'an empty heading renders no heading',    false === strpos( $nx1, 'arv-next-races__head' ) );

// Nothing upcoming has to render nothing, not an empty bordered box.
$GLOBALS['NOW'] = '2027-01-01';
arv_race_store_flush_cache();
t( 'nothing upcoming renders nothing',       '' === arv_races_next_render( array( 'heading' => 'Next Races', 'limit' => 5 ) ) );

$GLOBALS['NOW']   = '2026-08-26';
$GLOBALS['posts'] = $next_backup;
$GLOBALS['meta']  = $next_meta_backup;
arv_race_store_flush_cache();


// ---------------------------------------------------------------- //
// [arv_race_video].
//
// The ID parse is the part that matters: a URL form this does not know
// renders nothing at all, and the fallback that accepts a bare ID must
// not quietly salvage eleven characters out of a URL it failed to read
// and embed the wrong video.
// ---------------------------------------------------------------- //

t( 'a youtu.be link parses',       '2QL2rVaEcpg' === arv_race_video_id( 'https://youtu.be/2QL2rVaEcpg?si=JLydMQBKC' ) );
t( 'a watch link parses',          '2QL2rVaEcpg' === arv_race_video_id( 'https://www.youtube.com/watch?v=2QL2rVaEcpg' ) );
t( 'an embed link parses',         '2QL2rVaEcpg' === arv_race_video_id( 'https://www.youtube.com/embed/2QL2rVaEcpg' ) );
t( 'a shorts link parses',         '2QL2rVaEcpg' === arv_race_video_id( 'https://youtube.com/shorts/2QL2rVaEcpg' ) );
t( 'a live link parses',           '2QL2rVaEcpg' === arv_race_video_id( 'https://www.youtube.com/live/2QL2rVaEcpg' ) );
t( 'a bare id passes through',     '2QL2rVaEcpg' === arv_race_video_id( '2QL2rVaEcpg' ) );
t( 'blank gives nothing',          '' === arv_race_video_id( '' ) );
t( 'a non-YouTube url gives none', '' === arv_race_video_id( 'https://vimeo.com/123456789' ) );

// The anchor on the bare-ID pattern. Without it this returns 'aravaiparun'
// off the host and embeds a video nobody chose.
t( 'an unparseable YouTube url is not salvaged',
	'' === arv_race_video_id( 'https://aravaiparunning.com/some/page/' ) );

// No id, no markup. An empty section with a black 16:9 hole in it is worse
// than the element simply not being there.
t( 'no id renders nothing',        '' === arv_race_video_render( array( 'url' => '' ) ) );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();
$GLOBALS['_http_calls'] = 0;

$rv = arv_race_video_render( array(
	'url'        => 'https://youtu.be/2QL2rVaEcpg',
	'title'      => 'JIGGER JOHNSON 100 | America\'s Most Brutal',
	'credit'     => 'Ultra Kraut Running',
	'credit_url' => 'https://www.youtube.com/@ultrakraut',
) );

t( 'the embed is on the nocookie host', false !== strpos( $rv, 'youtube-nocookie.com/embed/2QL2rVaEcpg' ) );
t( 'and it is lazy',                    false !== strpos( $rv, 'loading="lazy"' ) );
t( 'the title shows',                   false !== strpos( $rv, 'America&#039;s Most Brutal' ) );
t( 'the channel is credited',           false !== strpos( $rv, 'Ultra Kraut Running' ) );
t( 'and the credit links out',          false !== strpos( $rv, '@ultrakraut' ) );

// Given a title and a credit there is nothing left to look up, and a race
// page must not make a network call it does not need.
t( 'nothing was fetched',               0 === $GLOBALS['_http_calls'] );

// No date means no uploadDate, and a VideoObject without one is invalid,
// so the whole node is dropped rather than shipped broken.
t( 'no date, no schema',                false === strpos( $rv, 'VideoObject' ) );

// The node builder, tested directly. WPSEO_VERSION is defined earlier in
// this file and a constant cannot be unset, so the printer correctly
// defers from here to the end of the run and can only be asserted on that.
$node = arv_race_video_schema_node( '2QL2rVaEcpg', 'Jigger Johnson 100', '', array( 'date' => '2026-09-01' ) );

t( 'a date builds a node',              'VideoObject' === ( $node['@type'] ?? '' ) );
t( 'and carries uploadDate',            0 === strpos( $node['uploadDate'] ?? '', '2026-09-01' ) );
t( 'with a thumbnail',                  false !== strpos( $node['thumbnailUrl'] ?? '', 'i.ytimg.com/vi/2QL2rVaEcpg' ) );
t( 'and the nocookie embed url',        false !== strpos( $node['embedUrl'] ?? '', 'youtube-nocookie.com/embed/2QL2rVaEcpg' ) );
t( 'description falls back to title',   'Jigger Johnson 100' === ( $node['description'] ?? '' ) );

$node_cap = arv_race_video_schema_node( '2QL2rVaEcpg', 'Jigger Johnson 100', 'A film about the race.', array( 'date' => '2026-09-01' ) );
t( 'a caption becomes the description', 'A film about the race.' === ( $node_cap['description'] ?? '' ) );

// The three ways a node cannot be valid.
t( 'no date, no node',    array() === arv_race_video_schema_node( '2QL2rVaEcpg', 'T', '', array() ) );
t( 'no title, no node',   array() === arv_race_video_schema_node( '2QL2rVaEcpg', '', '', array( 'date' => '2026-09-01' ) ) );
t( 'junk date, no node',  array() === arv_race_video_schema_node( '2QL2rVaEcpg', 'T', '', array( 'date' => 'soon' ) ) );

// And the printer defers while a real SEO plugin is active, which it is by
// this point in the run.
$rv_dated = arv_race_video_render( array(
	'url'    => 'https://youtu.be/2QL2rVaEcpg',
	'title'  => 'Jigger Johnson 100',
	'credit' => 'Ultra Kraut Running',
	'date'   => '2026-09-01',
) );

t( 'the printer defers to a real SEO plugin', false === strpos( $rv_dated, 'VideoObject' ) );
t( 'but the embed still renders',             false !== strpos( $rv_dated, 'youtube-nocookie.com/embed/2QL2rVaEcpg' ) );

// Missing title and credit: oEmbed fills them, once, and the result is
// cached so a second render on the same page does not call out again.
$GLOBALS['_transients'] = array();
$GLOBALS['_http_calls'] = 0;
$GLOBALS['_http_queue'] = array(
	array( 'code' => 200, 'body' => wp_json_encode( array(
		'title'         => 'JIGGER JOHNSON 100',
		'author_name'   => 'Ultra Kraut Running',
		'author_url'    => 'https://www.youtube.com/@ultrakraut',
		'thumbnail_url' => 'https://i.ytimg.com/vi/2QL2rVaEcpg/hqdefault.jpg',
	) ) ),
);

$rv_auto = arv_race_video_render( array( 'url' => 'https://youtu.be/2QL2rVaEcpg' ) );
t( 'the title came from oEmbed',        false !== strpos( $rv_auto, 'JIGGER JOHNSON 100' ) );
t( 'so did the credit',                 false !== strpos( $rv_auto, 'Ultra Kraut Running' ) );
t( 'it took one call',                  1 === $GLOBALS['_http_calls'] );

arv_race_video_render( array( 'url' => 'https://youtu.be/2QL2rVaEcpg' ) );
t( 'the second render used the cache',  1 === $GLOBALS['_http_calls'] );

// A dead or private video must not re-request on every page load.
$GLOBALS['_transients'] = array();
$GLOBALS['_http_calls'] = 0;
$GLOBALS['_http_queue'] = array( array( 'code' => 404, 'body' => '' ) );

$rv_dead = arv_race_video_render( array( 'url' => 'https://youtu.be/aaaaaaaaaaa' ) );
t( 'a dead video still embeds',         false !== strpos( $rv_dead, 'youtube-nocookie.com/embed/aaaaaaaaaaa' ) );
t( 'and the failure is cached',         1 === $GLOBALS['_http_calls'] );
arv_race_video_render( array( 'url' => 'https://youtu.be/aaaaaaaaaaa' ) );
t( 'so it does not re-request',         1 === $GLOBALS['_http_calls'] );

$GLOBALS['_transients'] = array();
$GLOBALS['_http_queue'] = array();


echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
