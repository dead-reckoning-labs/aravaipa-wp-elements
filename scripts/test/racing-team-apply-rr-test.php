<?php
/**
 * RaceReach push for the 2027 Racing Team application
 * (includes/racing-team-apply.php), without WordPress or the network:
 * WordPress, CF7, Flamingo and wp_remote_* are stubbed, and RaceReach's
 * races list comes from fixtures/racereach-races.json (a snapshot of the
 * live GET).
 *
 * Covers: flag off means zero HTTP calls; no secret means skipped; the v1
 * token (format, iss, aud, exp, HMAC); request headers and timeout; every
 * label to code mapping; unknown races dropped; races GET failure sends no
 * race_plans; multipart vs JSON and the 4 MB limits; the retry schedule per
 * status; the 400 optional field resend; nothing ever throws into CF7; no
 * log carries the token or secret; the site's agreement text matches the
 * RaceReach defaults for consent version 2027-1 (when racereach-ro is
 * present); the export rr_status column; the CLI selections.
 *
 * Run: php scripts/test/racing-team-apply-rr-test.php
 *      [--token-vector | --sample-payload | --sample-payload-unknown-race]
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['t_opts']      = array();
$GLOBALS['t_now']       = null;
$GLOBALS['t_meta']      = array();
$GLOBALS['t_flam']      = array();
$GLOBALS['t_trans']     = array();
$GLOBALS['t_gets']      = 0;
$GLOBALS['t_get_fail']  = false;
$GLOBALS['t_posts']     = array();
$GLOBALS['t_responses'] = array();
$GLOBALS['t_sched']     = array();
$GLOBALS['t_actions']   = array();
$GLOBALS['t_up']        = sys_get_temp_dir() . '/rt-apply-rr-' . getmypid();

function add_filter( $h, $fn, $p = 10, $n = 1 ) {
	$GLOBALS['t_actions'][ $h ][] = $fn;
}
function add_action( $h, $fn, $p = 10, $n = 1 ) {
	add_filter( $h, $fn, $p, $n );
}
function remove_filter( $h, $fn, $p = 10 ) {
	return true;
}
function apply_filters( $h, $v, ...$a ) {
	if ( 'arv_rt_apply_now' === $h && null !== $GLOBALS['t_now'] ) {
		return $GLOBALS['t_now'];
	}
	return $v;
}
function add_shortcode( $t, $fn ) {
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['t_opts'] ) ? $GLOBALS['t_opts'][ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['t_opts'][ $k ] = $v;
	return true;
}
function get_transient( $k ) {
	return isset( $GLOBALS['t_trans'][ $k ] ) ? $GLOBALS['t_trans'][ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['t_trans'][ $k ] = $v;
	$GLOBALS['t_trans_ttl']   = $ttl;
	return true;
}
function wp_upload_dir() {
	return array( 'basedir' => $GLOBALS['t_up'] );
}
function wp_mkdir_p( $d ) {
	return is_dir( $d ) || mkdir( $d, 0777, true );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
function sanitize_file_name( $s ) {
	return preg_replace( '/[^a-z0-9._-]+/i', '-', $s );
}
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $s ) );
}
function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}
function wp_unslash( $v ) {
	return $v;
}
function wp_json_encode( $v, $f = 0 ) {
	return json_encode( $v, $f );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function get_post_meta( $id, $k, $single = false ) {
	return isset( $GLOBALS['t_meta'][ $id ][ $k ] ) ? $GLOBALS['t_meta'][ $id ][ $k ] : '';
}
function update_post_meta( $id, $k, $v ) {
	$GLOBALS['t_meta'][ $id ][ $k ] = $v;
}
function get_post_field( $f, $id ) {
	return '2026-11-20 08:00:00';
}
function wp_delete_post( $id, $force = false ) {
	return true;
}
class WP_Error {
	public $m;
	public function __construct( $c = '', $m = '' ) {
		$this->m = $m;
	}
	public function get_error_message() {
		return $this->m;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function wp_remote_get( $url, $args = array() ) {
	++$GLOBALS['t_gets'];
	$GLOBALS['t_get_url'] = $url;
	if ( $GLOBALS['t_get_fail'] ) {
		return new WP_Error( 'http', 'down' );
	}
	return array(
		'code' => 200,
		'body' => file_get_contents( __DIR__ . '/fixtures/racereach-races.json' ),
	);
}
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['t_posts'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	$r = $GLOBALS['t_responses'] ? array_shift( $GLOBALS['t_responses'] ) : array( 200, '{"ok":true}' );
	if ( $r instanceof Throwable ) {
		throw $r;
	}
	if ( 'net' === $r[0] ) {
		return new WP_Error( 'http', 'cURL error 28: timed out' );
	}
	return array(
		'code' => $r[0],
		'body' => $r[1],
	);
}
function wp_remote_request( $url, $args = array() ) {
	return wp_remote_post( $url, $args );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function wp_schedule_single_event( $t, $hook, $args = array() ) {
	$GLOBALS['t_sched'][] = array( $t - time(), $hook, $args );
	return true;
}
function arv_race_store_get() {
	return array(
		array( 'name' => 'Rabid Raccoon', 'iso' => '2027-01-09' ),
		array( 'name' => 'Black Canyon Ultras', 'iso' => '2027-02-13' ),
		array( 'name' => 'Vegas Golden Night and Day', 'iso' => '2027-02-06' ),
		array( 'name' => 'Stillhouse 100K', 'iso' => '2027-04-17' ),
		array( 'name' => 'Old Race', 'iso' => '2026-12-05' ),
	);
}

class WPCF7_ContactForm {
	public static $current = null;
	public $i;
	public function __construct( $id ) {
		$this->i = $id;
	}
	public static function get_current() {
		return self::$current;
	}
	public function id() {
		return $this->i;
	}
	public function name() {
		return 'rt-apply-form';
	}
}
function wpcf7_contact_form( $id ) {
	return new WPCF7_ContactForm( $id );
}
class WPCF7_Submission {
	public static $inst = null;
	public $posted      = array();
	public $files       = array();
	public $meta        = array();
	public static function get_instance() {
		return self::$inst;
	}
	public function get_posted_data( $n = '' ) {
		return '' === $n ? $this->posted : ( isset( $this->posted[ $n ] ) ? $this->posted[ $n ] : null );
	}
	public function uploaded_files() {
		return $this->files;
	}
	public function get_meta( $k ) {
		return isset( $this->meta[ $k ] ) ? $this->meta[ $k ] : null;
	}
}
class Flamingo_Inbound_Message {
	public $i;
	public $fields;
	public function __construct( $i, $f ) {
		$this->i      = $i;
		$this->fields = $f;
	}
	public function id() {
		return $this->i;
	}
	public static function find( $a ) {
		return 'rt-apply-form' === $a['channel'] ? $GLOBALS['t_flam'] : array();
	}
}

require dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php';

const T_SECRET = 'wrheyv-test-secret';

/** A full answer set, as CF7 posts it (arrays for checkboxes). */
function t_fields( $over = array() ) {
	return array_merge(
		array(
			'rt-first-name'           => 'Ann',
			'rt-last-name'            => 'Runner',
			'rt-email'                => 'Ann@Example.com',
			'rt-phone'                => '602-555-0100',
			'rt-birth-date'           => '1990-04-02',
			'rt-city'                 => 'Phoenix',
			'rt-state'                => 'AZ',
			'rt-region'               => 'Utah and Nevada',
			'rt-disciplines'          => array( 'Trail', 'Mountain bike', 'Trail' ),
			'rt-returning'            => 'Yes',
			'rt-returning-years'      => '2025, 2026',
			'rt-resume'               => "1st, Black Canyon 100K, 2026\n3rd, Javelina 100, 2025",
			'rt-ultrasignup'          => 'http://ultrasignup.com/results_participant.aspx?fname=Ann',
			'rt-profiles'             => "https://utmb.world/runner/1\nstrava.com/athletes/2\njavascript:alert(1)\nhttps://a.com/3\nhttps://b.com/4\nhttps://c.com/5\nhttps://d.com/6",
			'rt-goals'                => '',
			'rt-race-plans'           => array( 'Rabid Raccoon, Jan 9', 'Black Canyon Ultras, Feb 13' ),
			'rt-outside-races'        => 'Western States',
			'rt-summit'               => 'Arizona for sure',
			'rt-why'                  => 'Community.',
			'rt-bring'                => '',
			'rt-instagram'            => '@ann',
			'rt-strava'               => '',
			'rt-socials'              => '',
			'rt-content-links'        => 'https://ann.blog/report',
			'rt-sponsors'             => '',
			'rt-support'              => '',
			'rt-size-fit'             => 'Women',
			'rt-size-tee'             => 'S',
			'rt-size-top'             => 'M',
			'rt-size-shorts'          => 'S',
			'rt-shoe-size'            => '8.5',
			'rt-consent-age'          => 'yes',
			'rt-consent-expectations' => 'yes',
			'rt-consent-privacy'      => 'yes',
			'rt-consent-publicity'    => 'no',
		),
		$over
	);
}

/** The fixture races. */
function t_races() {
	return json_decode( file_get_contents( __DIR__ . '/fixtures/racereach-races.json' ), true );
}

// ---- Output modes for the job checks --------------------------------------
$mode = isset( $argv[1] ) ? $argv[1] : '';
if ( '--token-vector' === $mode ) {
	echo arv_rt_apply_rr_token( T_SECRET, 1795000000 ) . "\n";
	exit( 0 );
}
if ( '--sample-payload' === $mode ) {
	echo json_encode( arv_rt_apply_rr_payload( t_fields(), t_races() ) ) . "\n";
	exit( 0 );
}
if ( '--sample-payload-unknown-race' === $mode ) {
	$f                  = t_fields();
	$f['rt-race-plans'] = array( 'nope-not-a-race', 'Nope Not A Race, Mar 3', 'Rabid Raccoon, Jan 9' );
	echo json_encode( arv_rt_apply_rr_payload( $f, t_races() ) ) . "\n";
	exit( 0 );
}

// ---- Harness -----------------------------------------------------------------
$fails = 0;
$n     = 0;
function ok( $c, $m ) {
	global $fails, $n;
	++$n;
	if ( ! $c ) {
		++$fails;
		echo "FAIL: $m\n";
	}
}
function rmrf( $d ) {
	if ( ! is_dir( $d ) ) {
		return;
	}
	foreach ( scandir( $d ) as $f ) {
		if ( '.' === $f || '..' === $f ) {
			continue;
		}
		$p = $d . '/' . $f;
		is_dir( $p ) ? rmrf( $p ) : unlink( $p );
	}
	rmdir( $d );
}
function t_reset() {
	$GLOBALS['t_opts']      = array( 'arv_rt_apply_form_id' => 77 );
	$GLOBALS['t_meta']      = array();
	$GLOBALS['t_flam']      = array();
	$GLOBALS['t_trans']     = array();
	$GLOBALS['t_gets']      = 0;
	$GLOBALS['t_get_fail']  = false;
	$GLOBALS['t_posts']     = array();
	$GLOBALS['t_responses'] = array();
	$GLOBALS['t_sched']     = array();
	rmrf( $GLOBALS['t_up'] );
}
function t_on() {
	$GLOBALS['t_opts']['arv_rt_apply_rr_push']   = '1';
	$GLOBALS['t_opts']['arv_rt_apply_rr_secret'] = T_SECRET;
}
function t_entry( $id = 501, $over = array(), $files = array() ) {
	return array(
		'id'     => $id,
		'email'  => 'ann@example.com',
		'fields' => t_fields( $over ),
		'files'  => $files,
		'ip'     => '203.0.113.9',
	);
}
function t_b64d( $s ) {
	return base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) );
}
/** Decode the JSON payload of a posted request (JSON or multipart). */
function t_body_payload( $post ) {
	$b = $post['args']['body'];
	if ( 0 === strpos( $post['args']['headers']['Content-Type'], 'application/json' ) ) {
		return json_decode( $b, true );
	}
	if ( preg_match( '/name="payload"\r\nContent-Type: application\/json\r\n\r\n(.*?)\r\n--/s', $b, $m ) ) {
		return json_decode( $m[1], true );
	}
	return null;
}

register_shutdown_function(
	function () {
		rmrf( $GLOBALS['t_up'] );
	}
);

// ---- 1. Flag off: zero HTTP calls ---------------------------------------------
t_reset();
ok( false === arv_rt_apply_rr_enabled(), 'flag defaults off' );
$GLOBALS['t_opts']['arv_rt_apply_rr_secret'] = T_SECRET;
$r = arv_rt_apply_rr_push( t_entry() );
ok( 'skipped' === $r['status'] && 0 === count( $GLOBALS['t_posts'] ) && 0 === $GLOBALS['t_gets'], 'flag off: push sends nothing' );
$GLOBALS['arv_rt_apply_last'] = array(
	'email'  => 'ann@example.com',
	'fields' => t_fields(),
	'files'  => array(),
);
arv_rt_apply_rr_on_submit( new WPCF7_ContactForm( 77 ), array( 'status' => 'mail_sent' ) );
ok( null === $GLOBALS['arv_rt_apply_rr_queue'] && 0 === count( $GLOBALS['t_posts'] ), 'flag off: submit hook queues nothing' );
ok( empty( $GLOBALS['t_meta'] ), 'flag off: no meta written' );
foreach ( array( '0', '', 'no', 'off', false ) as $v ) {
	$GLOBALS['t_opts']['arv_rt_apply_rr_push'] = $v;
	ok( false === arv_rt_apply_rr_enabled(), 'flag value ' . var_export( $v, true ) . ' is off' );
}
foreach ( array( '1', 'yes', 'on', true ) as $v ) {
	$GLOBALS['t_opts']['arv_rt_apply_rr_push'] = $v;
	ok( true === arv_rt_apply_rr_enabled(), 'flag value ' . var_export( $v, true ) . ' is on' );
}

// ---- 2. No secret: skipped, nothing sent -------------------------------------------
t_reset();
$GLOBALS['t_opts']['arv_rt_apply_rr_push'] = '1';
$r = arv_rt_apply_rr_push( t_entry() );
ok( 'skipped' === $r['status'] && 'no secret' === $r['error'] && 0 === count( $GLOBALS['t_posts'] ), 'no secret: skipped, no POST' );
ok( 'skipped' === $GLOBALS['t_meta'][501]['_arv_rt_rr']['status'], 'no secret: skipped recorded on the entry' );

// ---- 3. Token ------------------------------------------------------------------------
$tok   = arv_rt_apply_rr_token( T_SECRET, 1795000000 );
$parts = explode( '.', $tok );
ok( 3 === count( $parts ) && 'v1' === $parts[0], 'token is v1.<body>.<sig>' );
$body = json_decode( t_b64d( $parts[1] ), true );
ok( 'aravaipa-site' === $body['iss'] && 'racereach' === $body['aud'], 'token iss and aud' );
ok( 1795000000 === $body['iat'] && 120 === $body['exp'] - $body['iat'], 'token exp is iat + 120 (<= 300)' );
ok( hash_equals( rtrim( strtr( base64_encode( hash_hmac( 'sha256', 'v1.' . $parts[1], T_SECRET, true ) ), '+/', '-_' ), '=' ), $parts[2] ), 'token HMAC-SHA256 over "v1.<body>"' );
ok( false === strpos( $tok, '=' ) && false === strpos( $tok, '+' ) && false === strpos( $tok, '/' ), 'token is base64url without padding' );
$fresh = json_decode( t_b64d( explode( '.', arv_rt_apply_rr_token( T_SECRET ) )[1] ), true );
ok( abs( $fresh['iat'] - time() ) <= 2, 'token minted now by default' );

// ---- 4. Request: url, headers, timeout, JSON when no files ------------------------------
t_reset();
t_on();
$r = arv_rt_apply_rr_push( t_entry() );
ok( 'sent' === $r['status'] && 200 === $r['code'] && 1 === $r['attempts'], 'push 200 is sent' );
ok( 1 === count( $GLOBALS['t_posts'] ), 'one POST' );
$p = $GLOBALS['t_posts'][0];
ok( 'https://racereach.io/api/apply-public/racing-team' === $p['url'], 'POST url' );
ok( 'https://racereach.io/api/apply-public/racing-team/races' === $GLOBALS['t_get_url'], 'races GET url' );
ok( 0 === strpos( $p['args']['headers']['Authorization'], 'Bearer v1.' ), 'Authorization Bearer v1 token' );
ok( '203.0.113.9' === $p['args']['headers']['X-Applicant-IP'], 'X-Applicant-IP header' );
ok( 'application/json' === $p['args']['headers']['Content-Type'], 'JSON body when there are no files' );
ok( $p['args']['timeout'] <= 8 && $p['args']['timeout'] > 0, 'timeout <= 8 s' );
ok( 'sent' === $GLOBALS['t_meta'][501]['_arv_rt_rr']['status'], '_arv_rt_rr meta sent' );
$e = arv_rt_apply_rr_push( array_merge( t_entry( 502 ), array( 'ip' => 'not-an-ip' ) ) );
ok( ! isset( $GLOBALS['t_posts'][1]['args']['headers']['X-Applicant-IP'] ), 'bad IP: header left out' );
$GLOBALS['t_opts']['arv_rt_apply_rr_url'] = 'http://evil.example/x';
ok( 'https://racereach.io/api/apply-public/racing-team' === arv_rt_apply_rr_url(), 'non https url override ignored' );
$GLOBALS['t_opts']['arv_rt_apply_rr_url'] = 'https://staging.racereach.io/api/apply-public/racing-team/';
ok( 'https://staging.racereach.io/api/apply-public/racing-team' === arv_rt_apply_rr_url(), 'https url override used' );

// ---- 5. Payload mapping -------------------------------------------------------------------
$pl = arv_rt_apply_rr_payload( t_fields(), t_races() );
ok( 'utnv' === $pl['region'], 'region Utah and Nevada => utnv' );
foreach ( array( 'Arizona' => 'az', 'Colorado' => 'co', 'Northeast' => 'ne', 'California' => 'ca', 'Great Lakes' => 'gl', 'Southeast' => 'se', 'Other' => 'other' ) as $lab => $code ) {
	$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-region' => $lab ) ), array() );
	ok( $code === $x['region'], "region $lab => $code" );
}
ok( array( 'trail', 'mtb' ) === $pl['disciplines'], 'disciplines mapped, deduped' );
$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-disciplines' => 'Trail, Ultra, Road, Mountain bike' ) ), array() );
ok( array( 'trail', 'ultra', 'road', 'mtb' ) === $x['disciplines'], 'disciplines from a joined string' );
ok( 'yes' === $pl['returning'] && '2025, 2026' === $pl['returning_years'], 'returning yes with years' );
$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-returning' => 'No' ) ), array() );
ok( 'no' === $x['returning'] && ! isset( $x['returning_years'] ), 'returning no: years left out' );
ok( '1990-04-02' === $pl['birth_date'], 'birth_date kept as YYYY-MM-DD' );
$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-birth-date' => '04/02/1990' ) ), array() );
ok( '1990-04-02' === $x['birth_date'], 'birth_date m/d/Y normalised' );
ok( '8.5' === $pl['shoe_size'] && is_string( $pl['shoe_size'] ), 'shoe_size string' );
ok( 'ann@example.com' === $pl['email'], 'email lowercased' );
ok( 'https://ultrasignup.com/results_participant.aspx?fname=Ann' === $pl['ultrasignup'], 'ultrasignup http upgraded to https' );
$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-ultrasignup' => 'my profile' ) ), array() );
ok( ! isset( $x['ultrasignup'] ), 'ultrasignup non link left out' );
$prof = explode( "\n", $pl['profiles'] );
ok( 5 === count( $prof ) && 'https://strava.com/athletes/2' === $prof[1], 'profiles: https only, bare host upgraded, max 5' );
ok( false === strpos( $pl['profiles'], 'javascript' ), 'profiles: javascript link dropped' );
ok( ! isset( $pl['goals'] ) && ! isset( $pl['bring'] ) && ! isset( $pl['strava'] ) && ! isset( $pl['sponsors'] ), 'empty optional values omitted' );
ok( '@ann' === $pl['instagram'] && 'https://ann.blog/report' === $pl['content_links'], 'optional text kept' );
ok( 'az' === $pl['summit'], 'summit Arizona => az' );
foreach ( array( 'Colorado please' => 'co', 'great lakes' => 'gl', 'New England' => 'ne', 'Not sure yet' => 'not_sure', 'AZ or CO' => '', 'whichever' => '', '' => '' ) as $in => $want ) {
	$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-summit' => $in ) ), array() );
	ok( $want === ( isset( $x['summit'] ) ? $x['summit'] : '' ), "summit '$in' => '$want'" );
}
ok( array( 'age' => true, 'expectations' => true, 'privacy' => true, 'publicity' => false ) === $pl['consents'], 'consents booleans' );
ok( '2027-1' === $pl['consent_version'] && '' === $pl['website'], 'consent_version 2027-1 and website ""' );
foreach ( array_keys( $pl ) as $k ) {
	ok( false === strpos( $k, '-' ), "underscore key $k" );
}
ok( ! isset( $pl['headshot'] ) && ! isset( $pl['resume_file'] ) && ! isset( $pl['started_at'] ), 'no file or started_at keys in the JSON' );
$x = arv_rt_apply_rr_payload( t_fields( array( 'rt-resume' => str_repeat( 'a', 7000 ) ) ), array() );
ok( 6000 === strlen( $x['resume'] ), 'resume capped at 6000' );

// ---- 6. Race ids -------------------------------------------------------------------------
ok( array( 'rabid-raccoon', 'black-canyon-ultras' ) === $pl['race_plans'], 'race labels matched to RaceReach ids' );
$f                  = t_fields();
$f['rt-race-plans'] = array( 'Nope Not A Race, Mar 3', 'Rabid Raccoon, Jan 9', 'nope-not-a-race' );
$x                  = arv_rt_apply_rr_payload( $f, t_races() );
ok( array( 'rabid-raccoon' ) === $x['race_plans'], 'unknown race labels dropped' );
$f['rt-race-plans'] = array( 'Nope Not A Race, Mar 3' );
$x                  = arv_rt_apply_rr_payload( $f, t_races() );
ok( ! isset( $x['race_plans'] ), 'all unknown: race_plans left out' );
$f['rt-race-plans'] = 'Rabid Raccoon, Jan 9, Black Canyon Ultras, Feb 13';
$x                  = arv_rt_apply_rr_payload( $f, t_races() );
ok( array( 'rabid-raccoon', 'black-canyon-ultras' ) === $x['race_plans'], 'race labels from a joined string' );
$map = arv_rt_apply_rr_match_races( arv_rt_apply_race_options(), t_races() );
ok( 'vegas-golden-night-and-day' === $map['Vegas Golden Night and Day, Feb 6'], '& and "and" match' );
ok( 'the-stillhouse' === $map['Stillhouse 100K, Apr 17'], 'name match on the same date' );
ok( ! isset( $map['Old Race, Dec 5'] ), '2026 race not offered' );
$fuzzy = arv_rt_apply_rr_match_races(
	array( 'Rabid Raccoon 25k, Jan 9', 'Run Around Tucson (RAT), Jan 23', 'Prickly Pedal Runs, Jan 31', 'Stump Jump 50k & 10 Miler, Mar 6', 'Run With The Roosters, Jun 20', 'ET Full Moon, Aug 14', 'Stunner Night Runs, Jul 10', 'Devil After Dark, Sep 4', 'Jangover Night Runs, Sep 18' ),
	t_races()
);
ok(
	array(
		'Rabid Raccoon 25k, Jan 9'         => 'rabid-raccoon',
		'Run Around Tucson (RAT), Jan 23'  => 'run-around-tucson',
		'Prickly Pedal Runs, Jan 31'       => 'prickly-pedal-trail-race',
		'Stump Jump 50k & 10 Miler, Mar 6' => 'stump-jump',
		'Run With The Roosters, Jun 20'    => 'run-with-the-rooster',
		'ET Full Moon, Aug 14'             => 'et-full-moon-midnight',
		'Stunner Night Runs, Jul 10'       => 'insomniac-stunner',
		'Jangover Night Runs, Sep 18'      => 'javelina-jangover-night-runs',
	) === $fuzzy,
	'fuzzy race match: distances, plurals, a day apart, rides kept apart, no match dropped'
);
$x = arv_rt_apply_rr_payload( t_fields(), array() );
ok( ! isset( $x['race_plans'] ), 'no races list: race_plans left out' );
t_reset();
t_on();
$GLOBALS['t_get_fail'] = true;
$r                     = arv_rt_apply_rr_push( t_entry() );
$sent                  = t_body_payload( $GLOBALS['t_posts'][0] );
ok( 'sent' === $r['status'] && ! isset( $sent['race_plans'] ) && ! empty( $r['note'] ), 'races GET down: sent without race_plans, noted' );
t_reset();
t_on();
arv_rt_apply_rr_push( t_entry( 501 ) );
arv_rt_apply_rr_push( t_entry( 502 ) );
ok( 1 === $GLOBALS['t_gets'] && 300 === $GLOBALS['t_trans_ttl'], 'races cached 5 minutes' );

// ---- 7. Files: multipart vs JSON ------------------------------------------------------------
t_reset();
t_on();
wp_mkdir_p( $GLOBALS['t_up'] );
$small = $GLOBALS['t_up'] . '/head.jpg';
file_put_contents( $small, str_repeat( 'J', 2000 ) );
$pdf = $GLOBALS['t_up'] . '/cv.pdf';
file_put_contents( $pdf, '%PDF-1.4 test' );
$r  = arv_rt_apply_rr_push( t_entry( 501, array(), array( 'rt-headshot' => $small, 'rt-resume-file' => $pdf ) ) );
$p  = $GLOBALS['t_posts'][0];
$ct = $p['args']['headers']['Content-Type'];
ok( 0 === strpos( $ct, 'multipart/form-data; boundary=' ), 'multipart when files exist' );
ok( false !== strpos( $p['args']['body'], 'name="headshot"; filename="head.jpg"' ) && false !== strpos( $p['args']['body'], 'Content-Type: image/jpeg' ), 'headshot part' );
ok( false !== strpos( $p['args']['body'], 'name="resume_file"; filename="cv.pdf"' ) && false !== strpos( $p['args']['body'], 'Content-Type: application/pdf' ), 'resume_file part' );
ok( 'Ann' === t_body_payload( $p )['first_name'], 'payload part is the JSON payload' );
$b = substr( $ct, strlen( 'multipart/form-data; boundary=' ) );
ok( '--' . $b . "--\r\n" === substr( $p['args']['body'], -strlen( $b ) - 6 ), 'multipart closes with the boundary' );
$big = $GLOBALS['t_up'] . '/big.jpg';
$fh  = fopen( $big, 'w' );
ftruncate( $fh, 4 * 1024 * 1024 + 10 );
fclose( $fh );
$r = arv_rt_apply_rr_push( t_entry( 502, array(), array( 'rt-headshot' => $big ) ) );
$p = $GLOBALS['t_posts'][1];
ok( 'application/json' === $p['args']['headers']['Content-Type'] && 'sent' === $r['status'], 'file over 4 MB: JSON body' );
ok( array( 'headshot over 4 MB' ) === $r['skipped_files'], 'skipped file noted' );
$log = get_option( 'arv_rt_apply_log' );
ok( false !== strpos( end( $log ), 'headshot over 4 MB' ), 'skipped file in the ring log' );
$h3 = $GLOBALS['t_up'] . '/h3.jpg';
$r3 = $GLOBALS['t_up'] . '/r3.pdf';
foreach ( array( $h3, $r3 ) as $pth ) {
	$fh = fopen( $pth, 'w' );
	ftruncate( $fh, 2500000 );
	fclose( $fh );
}
$pick = arv_rt_apply_rr_pick_files( array( 'rt-headshot' => $h3, 'rt-resume-file' => $r3 ) );
ok( array( 'headshot' ) === array_keys( $pick['parts'] ) && array( 'resume_file over the 4 MB total' ) === $pick['skipped'], 'together over 4 MB: second file skipped' );

// ---- 8. Retry schedule ------------------------------------------------------------------------
t_reset();
t_on();
$GLOBALS['t_flam']      = array( new Flamingo_Inbound_Message( 501, t_fields() ) );
$GLOBALS['t_responses'] = array( array( 500, '{"ok":false,"error":"Something went wrong."}' ) );
$r                      = arv_rt_apply_rr_push( t_entry() );
ok( 'retry' === $r['status'] && 500 === $r['code'], '500: retry' );
ok( 300 === $GLOBALS['t_sched'][0][0] && 'arv_rt_apply_rr_retry' === $GLOBALS['t_sched'][0][1] && array( 501 ) === $GLOBALS['t_sched'][0][2], 'first retry in 5 min' );
$GLOBALS['t_responses'] = array( array( 502, '' ) );
arv_rt_apply_rr_retry( 501 );
ok( 1800 === $GLOBALS['t_sched'][1][0], 'second retry in 30 min' );
$GLOBALS['t_responses'] = array( 'net' );
arv_rt_apply_rr_retry( 501 );
ok( 10800 === $GLOBALS['t_sched'][2][0], 'third retry in 3 h' );
$GLOBALS['t_responses'] = array( array( 503, '' ) );
arv_rt_apply_rr_retry( 501 );
$m = $GLOBALS['t_meta'][501]['_arv_rt_rr'];
ok( 'failed' === $m['status'] && 3 === count( $GLOBALS['t_sched'] ) && 4 === $m['attempts'], 'after three retries: failed, no fourth schedule' );
$before = count( $GLOBALS['t_posts'] );
arv_rt_apply_rr_retry( 501 );
ok( count( $GLOBALS['t_posts'] ) === $before, 'retry hook ignores entries no longer in retry' );
foreach ( array( 429, 401, 'net' ) as $code ) {
	t_reset();
	t_on();
	$GLOBALS['t_responses'] = array( 'net' === $code ? 'net' : array( $code, '{"ok":false,"error":"x"}' ) );
	$r                      = arv_rt_apply_rr_push( t_entry() );
	ok( 'retry' === $r['status'] && 1 === count( $GLOBALS['t_sched'] ), "$code: retry" );
}
foreach ( array( 403, 404, 413 ) as $code ) {
	t_reset();
	t_on();
	$GLOBALS['t_responses'] = array( array( $code, '{"ok":false,"error":"Applications for the team are closed."}' ) );
	$r                      = arv_rt_apply_rr_push( t_entry() );
	ok( 'failed' === $r['status'] && $code === $r['code'] && 0 === count( $GLOBALS['t_sched'] ) && 1 === count( $GLOBALS['t_posts'] ), "$code: failed, not retried" );
}
t_reset();
t_on();
$GLOBALS['t_responses'] = array( array( 500, '' ) );
$r                      = arv_rt_apply_rr_push( t_entry( 0 ) );
ok( 'failed' === $r['status'] && 0 === count( $GLOBALS['t_sched'] ), 'no Flamingo id: cannot retry, failed (kept in the log and export)' );

// ---- 9. 400 handling --------------------------------------------------------------------------
t_reset();
t_on();
$GLOBALS['t_responses'] = array( array( 400, '{"ok":false,"error":"Check the fields.","fields":["profiles","race_plans"]}' ), array( 200, '{"ok":true}' ) );
$r                      = arv_rt_apply_rr_push( t_entry() );
$second                 = t_body_payload( $GLOBALS['t_posts'][1] );
ok( 'sent' === $r['status'] && 2 === count( $GLOBALS['t_posts'] ) && 2 === $r['attempts'], '400 on optional fields: resent once, sent' );
ok( ! isset( $second['profiles'] ) && ! isset( $second['race_plans'] ) && 'Ann' === $second['first_name'], 'resend leaves out only the named fields' );
ok( array( 'profiles', 'race_plans' ) === $r['dropped'], 'dropped fields recorded' );
t_reset();
t_on();
$GLOBALS['t_responses'] = array( array( 400, '{"ok":false,"error":"x","fields":["summit"]}' ), array( 400, '{"ok":false,"error":"x","fields":["goals"]}' ) );
$r                      = arv_rt_apply_rr_push( t_entry() );
ok( 'failed' === $r['status'] && 2 === count( $GLOBALS['t_posts'] ), '400 again after the resend: failed, no third try' );
t_reset();
t_on();
$GLOBALS['t_responses'] = array( array( 400, '{"ok":false,"error":"Check the fields.","fields":["birth_date","profiles"]}' ) );
$r                      = arv_rt_apply_rr_push( t_entry() );
ok( 'failed' === $r['status'] && 1 === count( $GLOBALS['t_posts'] ) && array( 'birth_date', 'profiles' ) === $r['rr_fields'], '400 on a required field: failed, not resent' );
ok( 'failed' === $GLOBALS['t_meta'][501]['_arv_rt_rr']['status'], '400 failure recorded on the entry' );

// ---- 10. Never throws ----------------------------------------------------------------------------
t_reset();
t_on();
$GLOBALS['t_responses'] = array( new RuntimeException( 'boom ' . T_SECRET ) );
$threw                  = false;
try {
	$r = arv_rt_apply_rr_push( t_entry() );
} catch ( Throwable $e ) {
	$threw = true;
}
ok( ! $threw && 'failed' === $r['status'], 'exception inside the push is caught' );
ok( false === strpos( $r['error'], T_SECRET ), 'exception text has the secret masked' );
$GLOBALS['t_responses']       = array( new RuntimeException( 'boom' ) );
$GLOBALS['arv_rt_apply_last'] = array(
	'email'       => 'ann@example.com',
	'fields'      => t_fields(),
	'files'       => array(),
	'flamingo_id' => 501,
);
$threw = false;
try {
	arv_rt_apply_rr_on_submit( new WPCF7_ContactForm( 77 ), array( 'status' => 'mail_sent' ) );
	arv_rt_apply_rr_shutdown();
	arv_rt_apply_rr_on_submit( 'garbage', null );
	arv_rt_apply_rr_retry( 999 );
} catch ( Throwable $e ) {
	$threw = true;
}
ok( ! $threw, 'submit, shutdown and retry hooks never throw into CF7' );

// ---- 11. Full flow: before mail, Flamingo, submit, shutdown ------------------------------------------
t_reset();
t_on();
WPCF7_Submission::$inst          = new WPCF7_Submission();
WPCF7_Submission::$inst->posted  = t_fields( array( 'rt-email' => 'flow@example.com' ) );
WPCF7_Submission::$inst->meta    = array( 'remote_ip' => '198.51.100.7' );
$_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8::5';
$form                            = new WPCF7_ContactForm( 77 );
arv_rt_apply_before_mail( $form );
arv_rt_apply_after_flamingo( array( 'flamingo_inbound_id' => 640 ) );
ok( 0 === count( $GLOBALS['t_posts'] ), 'nothing sent before CF7 finishes' );
arv_rt_apply_rr_on_submit( new WPCF7_ContactForm( 12 ), array( 'status' => 'mail_sent' ) );
ok( null === $GLOBALS['arv_rt_apply_rr_queue'], 'other forms are ignored' );
arv_rt_apply_rr_on_submit( $form, array( 'status' => 'validation_failed' ) );
ok( null === $GLOBALS['arv_rt_apply_rr_queue'], 'failed validation is not pushed' );
arv_rt_apply_rr_on_submit( $form, array( 'status' => 'mail_sent', 'flamingo_inbound_id' => 640 ) );
ok( 0 === count( $GLOBALS['t_posts'] ) && in_array( 'arv_rt_apply_rr_shutdown', $GLOBALS['t_actions']['shutdown'], true ), 'push waits for shutdown (after the response)' );
arv_rt_apply_rr_shutdown();
ok( 1 === count( $GLOBALS['t_posts'] ), 'pushed once on shutdown' );
$p = $GLOBALS['t_posts'][0];
ok( '2001:db8::5' === $p['args']['headers']['X-Applicant-IP'], 'X-Applicant-IP from CF-Connecting-IP' );
ok( '2001:db8::5' === $GLOBALS['t_meta'][640]['_arv_rt_ip'], 'applicant IP kept on the entry for retries' );
ok( array( 'rabid-raccoon', 'black-canyon-ultras' ) === t_body_payload( $p )['race_plans'], 'race plans from the posted list' );
ok( 'sent' === $GLOBALS['t_meta'][640]['_arv_rt_rr']['status'], 'flow: entry marked sent' );
arv_rt_apply_rr_shutdown();
ok( 1 === count( $GLOBALS['t_posts'] ), 'second shutdown does not push again' );
unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
ok( '198.51.100.7' === arv_rt_apply_rr_request_ip( WPCF7_Submission::$inst ), 'IP falls back to CF7 remote_ip' );
WPCF7_Submission::$inst->meta = array( 'remote_ip' => '127.0.0.1' );
ok( '' === arv_rt_apply_rr_request_ip( WPCF7_Submission::$inst ), 'loopback IP (CLI test) is left out' );
$lines = file( $GLOBALS['t_up'] . '/arv-rt-apply/entries.jsonl', FILE_IGNORE_NEW_LINES );
$e0    = json_decode( $lines[0], true );
$e1    = json_decode( $lines[1], true );
ok( isset( $e0['fields'] ) && ! isset( $e0['ip'] ) && ! isset( $e0['lists'] ), 'entry line unchanged (no IP written to the log line)' );
ok( 'rr' === $e1['kind'] && 640 === $e1['flamingo_id'] && 'sent' === $e1['rr']['status'], 'push result line in entries.jsonl' );

// ---- 12. Logs carry no token or secret -----------------------------------------------------------------
t_reset();
t_on();
$GLOBALS['t_responses'] = array( array( 401, '{"ok":false,"error":"Unauthorized. got Bearer v1.abc.def"}' ) );
arv_rt_apply_rr_push( t_entry() );
$sent_tok = substr( $GLOBALS['t_posts'][0]['args']['headers']['Authorization'], 7 );
$all      = json_encode( $GLOBALS['t_opts']['arv_rt_apply_log'] ) . json_encode( $GLOBALS['t_meta'] ) . file_get_contents( $GLOBALS['t_up'] . '/arv-rt-apply/entries.jsonl' );
ok( false === strpos( $all, $sent_tok ) && false === strpos( $all, T_SECRET ) && false === strpos( $all, 'v1.abc.def' ), 'no token or secret in meta, entries.jsonl or the ring log' );
ok( false !== strpos( end( $GLOBALS['t_opts']['arv_rt_apply_log'] ), 'a***@example.com' ) && false === strpos( json_encode( $GLOBALS['t_opts']['arv_rt_apply_log'] ), 'ann@example.com' ), 'ring log masks the email' );
for ( $i = 0; $i < 120; $i++ ) {
	arv_rt_apply_rr_ring( 'line ' . $i );
}
ok( 100 === count( $GLOBALS['t_opts']['arv_rt_apply_log'] ), 'ring log keeps 100' );
$src = file_get_contents( dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php' );
ok( ! preg_match( '/update_option\(\s*\'arv_rt_apply_log\'[^;]*,\s*true\s*\)/', $src ), 'ring log not autoloaded' );

// ---- 13. Export rr_status and CLI selections -------------------------------------------------------------
t_reset();
t_on();
$GLOBALS['t_flam'] = array(
	new Flamingo_Inbound_Message( 701, t_fields( array( 'rt-email' => 'a@x.com' ) ) ),
	new Flamingo_Inbound_Message( 702, t_fields( array( 'rt-email' => 'b@x.com' ) ) ),
	new Flamingo_Inbound_Message( 703, t_fields( array( 'rt-email' => 'c@x.com' ) ) ),
	new Flamingo_Inbound_Message( 704, t_fields( array( 'rt-email' => 'd@x.com' ) ) ),
);
$GLOBALS['t_meta'][701]['_arv_rt_rr'] = array( 'status' => 'sent', 'code' => 200 );
$GLOBALS['t_meta'][702]['_arv_rt_rr'] = array( 'status' => 'retry', 'code' => 500 );
$GLOBALS['t_meta'][703]['_arv_rt_rr'] = array( 'status' => 'failed', 'code' => 403 );
$fh                                   = fopen( 'php://memory', 'w+' );
arv_rt_apply_export_csv( $fh );
rewind( $fh );
$head = fgetcsv( $fh, 0, ',', '"', '\\' );
$rows = array();
while ( ( $row = fgetcsv( $fh, 0, ',', '"', '\\' ) ) ) {
	$rows[] = $row;
}
$ix = array_flip( $head );
ok( isset( $ix['rr_status'] ), 'export has an rr_status column' );
ok( 'sent' === $rows[0][ $ix['rr_status'] ] && 'retry' === $rows[1][ $ix['rr_status'] ] && 'failed' === $rows[2][ $ix['rr_status'] ] && '' === $rows[3][ $ix['rr_status'] ], 'export rr_status values' );
$ids = function ( $l ) {
	return array_map(
		function ( $e ) {
			return $e['id'];
		},
		$l
	);
};
ok( array( 702 ) === $ids( arv_rt_apply_rr_select( 'pending', array() ) ), 'push --pending takes retry, not 403 failed' );
ok( array( 704 ) === $ids( arv_rt_apply_rr_select( 'backfill', array() ) ), 'push --backfill takes never pushed' );
ok( array() === $ids( arv_rt_apply_rr_select( 'backfill', array( 'since' => '2026-11-21T00:00:00Z' ) ) ), 'push --backfill --since filters by time' );
ok( array( 703 ) === $ids( arv_rt_apply_rr_select( 'entry', array( 'entry' => 703 ) ) ), 'push --entry' );
ok( array( 'sent' => 1, 'retry' => 1, 'failed' => 1, 'none' => 1 ) === arv_rt_apply_rr_counts(), 'rr-status counts' );
$before = count( $GLOBALS['t_posts'] );
$r      = arv_rt_apply_rr_push( arv_rt_apply_rr_entry( 704 ), array( 'dry_run' => true, 'force' => true ) );
ok( 'dry_run' === $r['status'] && count( $GLOBALS['t_posts'] ) === $before && '' === arv_rt_apply_rr_status( 704 ), 'dry run sends and records nothing' );
$GLOBALS['t_opts']['arv_rt_apply_rr_push'] = '0';
$r = arv_rt_apply_rr_push( arv_rt_apply_rr_entry( 704 ), array( 'force' => true ) );
ok( 'sent' === $r['status'], 'CLI push (force) works with the flag off' );

// ---- 14. delete-test removes push lines too ------------------------------------------------------------
t_reset();
t_on();
arv_rt_apply_rr_push( t_entry( 0 ) );
$out = arv_rt_apply_delete_email( 'ann@example.com' );
ok( 1 === $out['log'], 'delete-test removes the push line for that email' );
ok( 1 === preg_match( '/\+rtapply(-[a-z0-9]+)?@/i', 'jamil+rtapply-rr@aravaiparunning.com' ), 'delete-test accepts +rtapply-rr@' );

// ---- 15. Agreement text matches RaceReach 2027-1 -----------------------------------------------------------
$rr_src = '';
foreach ( array( getenv( 'RACEREACH_RO' ), dirname( __DIR__, 5 ) . '/racereach-ro', dirname( __DIR__, 3 ) . '/racereach-ro' ) as $dir ) {
	if ( $dir && is_dir( $dir . '/.git' ) ) {
		$rr_src = (string) shell_exec( 'git -C ' . escapeshellarg( $dir ) . ' show origin/main:app/lib/applications/program-config.ts 2>/dev/null' );
		if ( '' === $rr_src && is_file( $dir . '/app/lib/applications/program-config.ts' ) ) {
			$rr_src = file_get_contents( $dir . '/app/lib/applications/program-config.ts' );
		}
		break;
	}
}
if ( preg_match( '/function defaultConsentTexts[^{]*\{(.*?)\n\}/s', $rr_src, $m ) ) {
	preg_match_all( '/key: "([a-z_]+)", required: (true|false), text: ["`](.*?)["`] \}/', $m[1], $cs, PREG_SET_ORDER );
	$rr = array();
	foreach ( $cs as $c ) {
		$rr[ $c[1] ] = array( 'true' === $c[2], str_replace( array( '${minAge}', '${season}' ), array( '18', '2027' ), $c[3] ) );
	}
	ok( 4 === count( $rr ), 'RaceReach default agreements parsed' );
	foreach ( arv_rt_apply_consents() as $c ) {
		ok( isset( $rr[ $c['key'] ] ) && $rr[ $c['key'] ][1] === $c['text'] && $rr[ $c['key'] ][0] === $c['required'], 'agreement ' . $c['key'] . ' matches RaceReach 2027-1' );
	}
} else {
	echo "note: racereach-ro not found, agreement text parity skipped\n";
}

echo ( $fails ? "FAILED $fails of $n checks\n" : "ALL PASS ($n checks)\n" );
exit( $fails ? 1 : 0 );
