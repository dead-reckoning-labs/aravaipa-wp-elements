<?php
/**
 * 2027 Racing Team application (includes/racing-team-apply.php), without
 * WordPress or the network: WordPress, CF7 and Flamingo are stubbed.
 *
 * Covers: the window states at fixed Phoenix times; validation rejects
 * outside the window and the staff test override lets one through; age and
 * returning-years checks; other form ids are ignored; the form template has
 * every field of the RaceReach form (out/fields.json when present, else the
 * include's own list) plus Turnstile; mail goes to bryce@ with the
 * photo attached; the shortcode state and the hidden form; before-mail
 * copies the photo into the private folder (deny-all) and logs the entry;
 * the Flamingo link; export header and rows; delete-test removes only the
 * given email. The v2 page ([arv_rt_apply_page]): hero button state, the
 * us-atlas map and region colors, legend hub links and the region hint.
 * No RaceReach push is configured (sink mode cf7), so no
 * wp_remote_* call may happen at all.
 *
 * Run: php scripts/test/racing-team-apply-test.php
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['t_opts']    = array();
$GLOBALS['t_filters'] = array();
$GLOBALS['t_now']     = null;
$GLOBALS['t_meta']    = array();
$GLOBALS['t_deleted'] = array();
$GLOBALS['t_remote']  = 0;
$GLOBALS['t_flam']    = array();
$GLOBALS['t_up']      = __DIR__ . '/.rt-apply-tmp-' . getmypid();

function add_filter( $h, $fn, $p = 10, $n = 1 ) {
	$GLOBALS['t_filters'][ $h ][] = $fn;
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
	$GLOBALS['t_shortcode'][ $t ] = $fn;
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['t_opts'] ) ? $GLOBALS['t_opts'][ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['t_opts'][ $k ] = $v;
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
function wp_json_encode( $v ) {
	return json_encode( $v );
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
	$GLOBALS['t_deleted'][] = $id;
	$GLOBALS['t_flam']      = array_values( array_filter( $GLOBALS['t_flam'], function ( $m ) use ( $id ) {
		return $m->id() !== $id;
	} ) );
	return true;
}
function wp_remote_post( ...$a ) {
	++$GLOBALS['t_remote'];
	return array();
}
function wp_remote_request( ...$a ) {
	++$GLOBALS['t_remote'];
	return array();
}
function wp_schedule_single_event( ...$a ) {
	return true;
}
function arv_race_store_get() {
	return array(
		array( 'name' => 'Rabid Raccoon', 'iso' => '2027-01-09' ),
		array( 'name' => 'Old Race', 'iso' => '2026-12-05' ),
		array( 'name' => 'Black Canyon Ultras', 'iso' => '2027-02-13' ),
	);
}

class WPCF7_Pipes {
	public $v;
	public function __construct( $v ) {
		$this->v = $v;
	}
}
class WPCF7_ContactForm {
	public static $current = null;
	public $i;
	public $props = array();
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
	public function scan_form_tags( $c ) {
		return array( (object) array( 'name' => $c['name'] ) );
	}
	public function form_html( $a = array() ) {
		return '<form class="wpcf7-form">FORM-' . $this->i . '</form>';
	}
}
function wpcf7_contact_form( $id ) {
	return new WPCF7_ContactForm( $id );
}
class WPCF7_Validation {
	public $bad = array();
	public function invalidate( $tag, $msg ) {
		$this->bad[ $tag->name ] = $msg;
	}
}
class WPCF7_Submission {
	public static $inst = null;
	public $posted      = array();
	public $files       = array();
	public static function get_instance() {
		return self::$inst;
	}
	public function get_posted_data( $n = '' ) {
		return '' === $n ? $this->posted : ( isset( $this->posted[ $n ] ) ? $this->posted[ $n ] : null );
	}
	public function uploaded_files() {
		return $this->files;
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
function ts( $s ) {
	return strtotime( $s );
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

// Window states.
ok( 'before' === arv_rt_apply_state( ts( '2026-11-19T23:59:00-07:00' ) ), 'before at 11:59 PM Nov 19' );
ok( 'open' === arv_rt_apply_state( ts( '2026-11-20T00:00:00-07:00' ) ), 'open at midnight Nov 20' );
ok( 'open' === arv_rt_apply_state( ts( '2026-11-30T23:59:59-07:00' ) ), 'open at 11:59:59 PM Nov 30' );
ok( 'closed' === arv_rt_apply_state( ts( '2026-12-01T00:00:00-07:00' ) ), 'closed Dec 1' );
$GLOBALS['t_opts']['arv_rt_apply_closes'] = '2026-11-25T00:00:00-07:00';
ok( 'closed' === arv_rt_apply_state( ts( '2026-11-26T00:00:00-07:00' ) ), 'option overrides close' );
unset( $GLOBALS['t_opts']['arv_rt_apply_closes'] );

// Template: every field from fields.json, Turnstile, starred required.
$tmpl = arv_rt_apply_form_template();
$fj   = dirname( __DIR__, 5 ) . '/.claude/claudeclaw/long-jobs/4cges9/out/fields.json';
$keys = array();
if ( is_file( $fj ) ) {
	$j = json_decode( file_get_contents( $fj ), true );
	foreach ( $j['fields'] as $f ) {
		$keys[] = $f['key'];
	}
	foreach ( $j['consents'] as $c ) {
		ok( false !== strpos( $tmpl, 'rt-consent-' . $c['key'] ), 'consent ' . $c['key'] );
	}
} else {
	foreach ( arv_rt_apply_fields() as $f ) {
		$keys[] = $f['key'];
	}
}
ok( count( $keys ) >= 25, 'at least 25 fields' );
foreach ( $keys as $k ) {
	ok( preg_match( '/\[[a-z]+\*? rt-' . preg_quote( $k, '/' ) . '[ \]]/', $tmpl ) === 1, "template has rt-$k" );
}
ok( false !== strpos( $tmpl, '[turnstile]' ), 'turnstile tag' );
ok( false !== strpos( $tmpl, '[email* rt-email' ), 'email required' );
ok( false !== strpos( $tmpl, '[textarea* rt-resume' ), 'resume required' );
ok( false !== strpos( $tmpl, '[file rt-headshot id:rt-headshot limit:64mb filetypes:jpg|jpeg|png|webp|heic|heif]' ), 'headshot file tag' );
ok( false !== strpos( $tmpl, '[acceptance rt-consent-publicity optional]' ), 'publicity optional' );
ok( false === strpos( $tmpl, "\u{2014}" ), 'no em dash in template' );

// Race plans fill: only 2027 races, for our tag only.
$t = arv_rt_apply_form_tag( array( 'name' => 'rt-race-plans', 'options' => array() ), true );
ok( array( 'Rabid Raccoon, Jan 9', 'Black Canyon Ultras, Feb 13' ) === $t['values'], 'race plans 2027 only' );
$o = arv_rt_apply_form_tag( array( 'name' => 'adv-trip', 'values' => array( 'x' ) ), true );
ok( array( 'x' ) === $o['values'], 'other tags untouched' );

// Mail.
$m = arv_rt_apply_mail();
ok( false !== strpos( $m['recipient'], 'bryce@aravaiparunning.com' ), 'mail to bryce' );
ok( false !== strpos( $m['attachments'], '[rt-headshot]' ), 'photo attached' );
ok( '2027 Racing Team application: [rt-first-name] [rt-last-name]' === $m['subject'], 'subject' );
foreach ( arv_rt_apply_fields() as $f ) {
	if ( 'file' !== $f['type'] ) {
		ok( false !== strpos( $m['body'], '[rt-' . $f['key'] . ']' ), 'mail body has ' . $f['key'] );
	}
}

// Validation.
$GLOBALS['t_opts']['arv_rt_apply_form_id'] = 501;
$sub                                       = new WPCF7_Submission();
$sub->posted                               = array( 'rt-birth-date' => '1990-05-01', 'rt-returning' => 'No' );
WPCF7_Submission::$inst                    = $sub;
WPCF7_ContactForm::$current                = new WPCF7_ContactForm( 501 );

$GLOBALS['t_now'] = ts( '2026-11-19T22:00:00-07:00' );
$r                = arv_rt_apply_validate( new WPCF7_Validation() );
ok( isset( $r->bad['rt-first-name'] ) && false !== strpos( $r->bad['rt-first-name'], 'open November 20' ), 'rejects before open' );
$GLOBALS['t_now'] = ts( '2026-12-01T00:00:01-07:00' );
$r                = arv_rt_apply_validate( new WPCF7_Validation() );
ok( isset( $r->bad['rt-first-name'] ) && false !== strpos( $r->bad['rt-first-name'], 'closed' ), 'rejects after close' );
$GLOBALS['t_now']                             = ts( '2026-11-19T22:00:00-07:00' );
$GLOBALS['t_opts']['arv_rt_apply_test_until'] = gmdate( 'c', ts( '2026-11-19T22:15:00-07:00' ) );
$r                                            = arv_rt_apply_validate( new WPCF7_Validation() );
ok( array() === $r->bad, 'test override lets it through' );
$GLOBALS['t_opts']['arv_rt_apply_test_until'] = gmdate( 'c', ts( '2026-11-19T21:00:00-07:00' ) );
$r                                            = arv_rt_apply_validate( new WPCF7_Validation() );
ok( isset( $r->bad['rt-first-name'] ), 'expired override does not' );
unset( $GLOBALS['t_opts']['arv_rt_apply_test_until'] );

$GLOBALS['t_now'] = ts( '2026-11-21T10:00:00-07:00' );
$r                = arv_rt_apply_validate( new WPCF7_Validation() );
ok( array() === $r->bad, 'valid inside the window' );
$sub->posted = array( 'rt-birth-date' => '2009-06-01', 'rt-returning' => 'Yes', 'rt-returning-years' => '' );
$r           = arv_rt_apply_validate( new WPCF7_Validation() );
ok( isset( $r->bad['rt-birth-date'] ), 'under 18 on Jan 1 2027 rejected' );
ok( ! isset( $r->bad['rt-returning-years'] ), 'returning yes without years is allowed (field is optional)' );
$sub->posted = array( 'rt-birth-date' => '2009-01-01', 'rt-returning' => 'Yes', 'rt-returning-years' => '2026' );
$r           = arv_rt_apply_validate( new WPCF7_Validation() );
ok( array() === $r->bad, '18 exactly on Jan 1 2027 ok' );

WPCF7_ContactForm::$current = new WPCF7_ContactForm( 86051 );
$GLOBALS['t_now']           = ts( '2026-12-05T10:00:00-07:00' );
$r                          = arv_rt_apply_validate( new WPCF7_Validation() );
ok( array() === $r->bad, 'other form ids ignored by validation' );

// Shortcode.
$GLOBALS['t_now'] = ts( '2026-11-19T22:00:00-07:00' );
$h                = arv_rt_apply_shortcode();
ok( false !== strpos( $h, 'data-state="before"' ) && preg_match( '/rt-state-before">Applications open November 20\./', $h ), 'before shows opens line' );
ok( false !== strpos( $h, '<div class="rt-state-open" hidden>' ) && false !== strpos( $h, 'FORM-501' ), 'before has form hidden for the midnight swap' );
$GLOBALS['t_now'] = ts( '2026-11-25T12:00:00-07:00' );
$h                = arv_rt_apply_shortcode();
ok( false !== strpos( $h, '<div class="rt-state-open">' ), 'open shows form' );
$GLOBALS['t_now'] = ts( '2026-12-02T12:00:00-07:00' );
$h                = arv_rt_apply_shortcode();
ok( false === strpos( $h, 'FORM-501' ) && preg_match( '/rt-state-closed">Applications for the 2027 team are closed\./', $h ), 'closed: message, no form' );
ok( false === strpos( arv_rt_apply_css(), 'box-shadow' ) && ! preg_match( '/border-(left|top|right|bottom)\s*:\s*\d+px\s+solid/', arv_rt_apply_css() ), 'no shadows, no accent stripes' );

// Before mail: photo kept, entry logged; other forms ignored.
$GLOBALS['t_now'] = ts( '2026-11-21T10:00:00-07:00' );
mkdir( $GLOBALS['t_up'], 0777, true );
$photo = $GLOBALS['t_up'] . '/upload-tmp.jpg';
file_put_contents( $photo, 'JPEGDATA' );
$sub->posted = array(
	'rt-first-name'      => 'Ann',
	'rt-last-name'       => 'Runner',
	'rt-email'           => 'Ann@Example.com',
	'rt-disciplines'     => array( 'Trail', 'Ultra' ),
	'rt-consent-age'     => '1',
);
$sub->files  = array( 'rt-headshot' => array( $photo ) );
arv_rt_apply_before_mail( new WPCF7_ContactForm( 86051 ) );
ok( ! is_file( $GLOBALS['t_up'] . '/arv-rt-apply/entries.jsonl' ), 'other form not logged' );
arv_rt_apply_before_mail( new WPCF7_ContactForm( 501 ) );
$dir = $GLOBALS['t_up'] . '/arv-rt-apply';
ok( is_file( $dir . '/.htaccess' ) && false !== strpos( file_get_contents( $dir . '/.htaccess' ), 'denied' ), 'deny-all htaccess' );
ok( is_file( $dir . '/index.php' ), 'index.php' );
$saved = $GLOBALS['arv_rt_apply_last']['files']['rt-headshot'];
ok( is_file( $saved ) && 'JPEGDATA' === file_get_contents( $saved ) && 0 === strpos( $saved, $dir . '/' ), 'photo copied into the private folder' );
$lines = file( $dir . '/entries.jsonl' );
$e     = json_decode( $lines[0], true );
ok( 1 === count( $lines ) && 'ann@example.com' === $e['email'] && 'Trail, Ultra' === $e['fields']['rt-disciplines'] && 'yes' === $e['fields']['rt-consent-age'] && 'no' === $e['fields']['rt-consent-publicity'], 'entry logged' );
arv_rt_apply_after_flamingo( array( 'flamingo_inbound_id' => 9001 ) );
ok( $saved === $GLOBALS['t_meta'][9001]['_arv_rt_files']['rt-headshot'], 'photo path on the Flamingo entry' );

// Export: header with every field; Flamingo rows.
$GLOBALS['t_flam'] = array(
	new Flamingo_Inbound_Message( 9001, array( 'rt-first-name' => 'Ann', 'rt-email' => 'ann@example.com', 'rt-disciplines' => array( 'Trail', 'Ultra' ), 'rt-consent-age' => '1' ) ),
	new Flamingo_Inbound_Message( 9002, array( 'rt-first-name' => 'TEST', 'rt-email' => 'jamil+rtapply@aravaiparunning.com' ) ),
);
$GLOBALS['t_meta'][9001]['_arv_rt_rr'] = array( 'status' => 'sent', 'skipped_files' => array( 'resume_file over 4 MB' ) );
$fh = fopen( 'php://memory', 'w+' );
$c  = arv_rt_apply_export_csv( $fh );
rewind( $fh );
$head = fgetcsv( $fh, 0, ',', '"', '\\' );
$row  = fgetcsv( $fh, 0, ',', '"', '\\' );
ok( 2 === $c, 'export rows' );
foreach ( $keys as $k ) {
	ok( in_array( $k, $head, true ), "export header has $k" );
}
$ix = array_flip( $head );
ok( 'sent' === $row[ $ix['rr_status'] ] && 'resume_file over 4 MB' === $row[ $ix['rr_skipped_files'] ], 'export shows skipped files with the reason' );
ok( 'Ann' === $row[ $ix['first-name'] ] && 'Trail; Ultra' === $row[ $ix['disciplines'] ] && basename( $saved ) === $row[ $ix['headshot'] ] && 'yes' === $row[ $ix['consent-age'] ] && 'no' === $row[ $ix['consent-publicity'] ], 'export row values' );

// Delete test: only the given email.
$sub->posted = array( 'rt-first-name' => 'TEST', 'rt-last-name' => 'TEST', 'rt-email' => 'jamil+rtapply@aravaiparunning.com' );
$sub->files  = array();
arv_rt_apply_before_mail( new WPCF7_ContactForm( 501 ) );
$d = arv_rt_apply_delete_email( 'jamil+rtapply@aravaiparunning.com' );
ok( array( 9002 ) === $GLOBALS['t_deleted'], 'deleted only the test Flamingo entry' );
ok( 1 === $d['log'] && 1 === $d['flamingo'], 'one log line, one entry' );
$left = file( $dir . '/entries.jsonl' );
ok( 1 === count( $left ) && false !== strpos( $left[0], 'ann@example.com' ), 'real entry kept in the log' );
ok( is_file( $saved ), 'real photo kept' );
ok( array( 'flamingo' => 0, 'files' => 0, 'log' => 0 ) === arv_rt_apply_delete_email( '' ), 'empty email deletes nothing' );

// v2 page [arv_rt_apply_page] (job 70sv0c): hero, button state, us-atlas map, regions, legend, region hint.
if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $pairs, $atts, $sc = '' ) {
		$out = array();
		foreach ( $pairs as $k => $v ) {
			$out[ $k ] = array_key_exists( $k, (array) $atts ) ? $atts[ $k ] : $v;
		}
		return $out;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
$inc_dir = dirname( __DIR__, 2 ) . '/includes/data/';
$atlas   = json_decode( file_get_contents( $inc_dir . 'us-states-albers.json' ), true );
$regs    = json_decode( file_get_contents( $inc_dir . 'rt-regions.json' ), true );
ok( 51 === count( $atlas['states'] ), 'atlas has 50 states + DC' );
ok( false !== stripos( $atlas['projection'], 'albers' ) && false !== strpos( $atlas['source'], 'us-atlas' ), 'atlas is us-atlas, Albers USA' );
ok( strlen( $atlas['states']['TX'] ) > 400 && strlen( $atlas['states']['CA'] ) > 400, 'real state outlines, not blocks' );
$names = array_column( $regs['regions'], 'name' );
ok( array( 'West', 'Great Lakes', 'Northeast', 'Southeast' ) === $names, '4 regions in order: West, Great Lakes, Northeast, Southeast' );
ok( 'jamil 2026-11-20' === $regs['source'], 'regions source jamil 2026-11-20' );
$sr = arv_rt_apply_state_regions();
foreach ( array( 'AZ', 'CA', 'CO', 'NV', 'UT' ) as $st ) {
	ok( 'West' === $sr[ $st ]['name'] && 'West' === $sr[ $st ]['option'] && 'https://www.aravaiparunning.com/races/' === $sr[ $st ]['hub'], "$st maps to West (one Aravaipa color)" );
}
foreach ( array( 'TN', 'AL', 'GA', 'NC', 'SC' ) as $st ) {
	ok( 'Southeast' === $sr[ $st ]['name'] && 'Bad Beard Events' === $sr[ $st ]['hubName'] && 'Southeast' === $sr[ $st ]['option'], "$st maps to Southeast, Bad Beard hub" );
}
ok( 'Northeast' === $sr['NH']['name'] && 'Great Lakes' === $sr['MI']['name'] && 'Great Lakes' === $sr['WI']['name'], 'NH Northeast, MI WI Great Lakes' );
ok( 'Great Lakes' === $sr['PA']['name'] && ! isset( $sr['NJ'] ) && ! isset( $sr['DE'] ) && ! isset( $sr['MD'] ), '2026 split: PA in Great Lakes, NJ DE MD outside' );
ok( ! isset( $sr['TX'] ) && ! isset( $sr['AK'] ) && ! isset( $sr['KY'] ) && ! isset( $sr['MS'] ), 'TX, AK, KY and MS outside the regions' );
ok( 'Great Lakes' === $sr['OH']['option'] && 'Northeast' === $sr['NH']['option'], 'state maps to the region answer on the form' );
$ropts = array();
foreach ( arv_rt_apply_fields() as $f ) {
	if ( 'region' === $f['key'] ) {
		$ropts = $f['options'];
	}
}
ok( array( 'West', 'Great Lakes', 'Northeast', 'Southeast', 'Other' ) === $ropts, 'form region options: the 4 names + Other (no Utah and Nevada)' );
$all = array();
foreach ( $regs['regions'] as $rg ) {
	$all = array_merge( $all, $rg['states'] );
	ok( 0 === strpos( $rg['hub_url'], 'https://www.aravaiparunning.com/' ), 'hub on aravaiparunning.com: ' . $rg['name'] );
}
ok( count( $all ) === count( array_unique( $all ) ), 'no state in two regions' );

$GLOBALS['t_now'] = ts( '2026-11-19T22:00:00-07:00' );
$p                = arv_rt_apply_page_shortcode( array( 'hero_url' => 'https://www.aravaiparunning.com/wp-content/uploads/rt-hero.jpg', 'focal' => '60% 35%', 'focal_mobile' => '70% 30%', 'credit' => 'Photo: Howie Stern' ) );
ok( false !== strpos( $p, '<h1 id="rt-hero-title">Apply for the 2027 Aravaipa Racing Team</h1>' ), 'page title' );
ok( false !== strpos( $p, 'rt-hero__img' ) && false !== strpos( $p, '--rt-focal:60% 35%;' ) && false !== strpos( $p, '--rt-focal-m:70% 30%;' ) && false !== strpos( $p, '<p class="rt-hero__credit">Photo: Howie Stern</p>' ), 'hero image with focal points and credit' );
ok( preg_match( '/id="rt-cta"[^>]*>Opens Nov 20</', $p ), 'before open: button reads Opens Nov 20' );
list( $o_, $c_ ) = arv_rt_apply_window();
ok( false !== strpos( $p, 'data-opens="' . $o_ . '" data-closes="' . $c_ . '">Opens Nov 20' ), 'button follows the same window' );
ok( false !== strpos( $p, 'href="#rt-apply-form"' ) && false !== strpos( $p, 'id="rt-apply-form"' ), 'button jumps to the form' );
ok( false !== strpos( $p, 'What the team asks' ) && false !== strpos( $p, 'What members get' ), 'asks and perks' );
ok( false !== strpos( $p, 'alt="HOKA"' ) && false !== strpos( $p, 'alt="Athletico Physical Therapy"' ), 'HOKA and Athletico lockup' );
ok( 51 === preg_match_all( '/<path data-state="[A-Z]{2}"/', $p ), 'map draws 51 state paths' );
ok( count( $all ) === preg_match_all( '/<path data-state="[A-Z]{2}"[^>]*rt-st--on/', $p ), 'every region state colored' );
ok( preg_match( '/<path data-state="NH"[^>]*class="rt-st rt-st--on rt-r-ne"/', $p ) && preg_match( '/<path data-state="TX"[^>]*class="rt-st"/', $p ), 'NH colored, TX gray' );
ok( count( $regs['regions'] ) === preg_match_all( '/class="rt-legend__item/', $p ), 'legend item per region' );
ok( false !== strpos( $p, 'href="https://www.aravaiparunning.com/white-mountain-endurance/"' ), 'legend links the hub' );
ok( 'provisional' !== $regs['source'] || false !== strpos( $p, '<p class="rt-map__note">Region lines are being finalized.</p>' ), 'provisional note shows while regions are provisional' );
ok( false !== strpos( arv_rt_apply_regions_note( array( 'source' => 'provisional' ) ), 'Region lines are being finalized.' ), 'note for a provisional source' );
ok( '' === arv_rt_apply_regions_note( array( 'source' => 'mantis' ) ), 'no note once Mantis confirmed the regions' );
ok( false !== strpos( $p, '<span class="rt-legend__states">CT, DE, MA, MD, ME, NH, NJ, NY, PA, RI, VT</span>' ) || count( $regs['regions'] ) === preg_match_all( '/<span class="rt-legend__states">[A-Z]{2}(, [A-Z]{2})*<\/span>/', $p ), 'legend lists the states of each region' );
ok( count( $regs['regions'] ) === preg_match_all( '/<span class="rt-legend__states">[A-Z]{2}(, [A-Z]{2})*<\/span>/', $p ), 'states line on every legend item' );
ok( count( $all ) === preg_match_all( '/<path data-state="[A-Z]{2}"[^>]*rt-st--on[^>]*tabindex="0" role="button"[^>]*><title>[^<]+ region<\/title><\/path>/', $p ), 'eligible states: role=button with a title' );
ok( preg_match( '/<path data-state="AZ"[^>]*><title>Arizona: West region<\/title>/', $p ), 'AZ title reads Arizona: West region' );
ok( preg_match( '/<path data-state="KY"[^>]*class="rt-st"/', $p ), 'KY gray' );
ok( false === strpos( $p, 'Region lines are being finalized' ), 'no provisional note rendered' );
ok( false === strpos( $p, 'Utah and Nevada' ), 'old region name not rendered' );
ok( false !== strpos( $p, 'Your region' ) && false !== strpos( $p, 'Outside our 2027 regions' ), 'region hint on the state picker' );
ok( false !== strpos( $p, 'data-state="before"' ) && false !== strpos( $p, '<div class="rt-state-open" hidden>' ) && false !== strpos( $p, 'FORM-501' ), 'form block and window swap intact inside the page' );
ok( false === strpos( $p, "\u{2014}" ), 'no em dash on the page' );
$pcss = arv_rt_apply_page_css() . arv_rt_apply_region_css();
ok( ! preg_match( '/box-shadow\s*:\s*(?!none)/', $pcss ) && ! preg_match( '/border-(left|top|right)\s*:\s*[2-9]\d*px/', $pcss ), 'page css: no shadows, no accent bars' );
$GLOBALS['t_now'] = ts( '2026-11-20T00:00:01-07:00' );
$p                = arv_rt_apply_page_shortcode( array() );
ok( preg_match( '/id="rt-cta" class="rt-cta"[^>]*>Apply now</', $p ) && false !== strpos( $p, '<div class="rt-state-open">' ), 'open: Apply now and the form shows' );
$GLOBALS['t_now'] = ts( '2026-12-01T00:00:01-07:00' );
$p                = arv_rt_apply_page_shortcode( array() );
ok( preg_match( '/>Applications closed</', $p ) && false === strpos( $p, 'FORM-501' ), 'closed: button and no form' );
ok( false === strpos( arv_rt_apply_page_shortcode( array( 'hero_url' => 'https://i.ytimg.com/vi/x/maxresdefault.jpg' ) ), 'ytimg' ), 'ytimg hero refused' );
ok( isset( $GLOBALS['t_shortcode']['arv_rt_apply_page'] ) && isset( $GLOBALS['t_shortcode']['arv_rt_apply'] ), 'both shortcodes registered' );

// No RaceReach push configured: nothing leaves the server.
ok( 0 === $GLOBALS['t_remote'], 'no remote calls' );

rmrf( $GLOBALS['t_up'] );

if ( $fails ) {
	echo "$fails of $n FAILED\n";
	exit( 1 );
}
echo "ALL PASS ($n checks)\n";
