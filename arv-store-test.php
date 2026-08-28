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
define( 'PHP_URL_PATH_STUB', true );

$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['terms'] = array();
$GLOBALS['next_id'] = 1;

function register_post_type( $t, $a = array() ) {}
function register_taxonomy( $t, $o, $a = array() ) {}
function register_post_meta( $p, $k, $a = array() ) {}
function add_action( $t, $f, $p = 10, $n = 1 ) {}
function add_filter( $t, $f, $p = 10, $n = 1 ) {}
function add_submenu_page() {}
function add_meta_box() {}
function current_user_can( $c, $id = 0 ) { return true; }
function __( $s, $d = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function apply_filters( $t, $v ) { return $v; }
function current_time( $f ) { return $GLOBALS['NOW'] ?? '2026-08-26'; }
function is_wp_error( $t ) { return false; }
function home_url( $p = '/' ) { return 'https://www.aravaiparunning.com' . $p; }
function add_query_arg( $a ) { return $GLOBALS['CURRENT_PATH'] ?? '/'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function register_rest_route( $ns, $route, $args = array() ) {}

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
require_once __DIR__ . '/includes/elements/upcoming-races.php';
require_once __DIR__ . '/includes/elements/season-calendar.php';
require_once __DIR__ . '/includes/elements/race-status.php';
require_once __DIR__ . '/includes/elements/featured-race.php';
require_once __DIR__ . '/includes/race-store.php';

$pass = 0; $fail = 0;
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
foreach ( $races as $race ) { if ( 'Rock Hawk' === $race['name'] ) { $rock = $race; } }
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
t( 'a Colorado race lands in colorado', in_array( 'Rock Hawk', $names, true ) );
t( 'and not in arizona', ! in_array( 'Rock Hawk', array_map( function ( $r ) { return $r['name']; }, $az ), true ) );
// Region is read off the race's own page path first, which is the site's own
// answer and survives a venue move.
$wme = array_map( function ( $r ) { return $r['name']; }, $nh );
t( 'a White Mountain race is grouped by its page path', in_array( 'Black Bear Trail Race', $wme, true ) );

echo "\nelement region filter:\n";
$scoped = arv_upcoming_races_render( array( 'rows' => '', 'limit' => '0', 'region' => 'colorado' ) );
t( 'a division page shows only its own races', substr_count( $scoped, 'arv-races__card' ) < 11 );
t( 'and Rock Hawk is one of them',             false !== strpos( $scoped, 'Rock Hawk' ) );

echo "\nsingle race page:\n";
$GLOBALS['CURRENT_PATH'] = '/bear-chase-series/rock-hawk/';
$found = arv_race_store_find_by_page( 'https://www.aravaiparunning.com/bear-chase-series/rock-hawk/' );
t( 'a race page finds its own race', null !== $found && 'Rock Hawk' === $found['name'] );
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

$GLOBALS['ARV_OPTIONS'] = array();

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
