<?php
/**
 * 2027 Racing Team application (includes/racing-team-apply.php), without
 * WordPress or the network: WordPress, CF7 and Flamingo are stubbed.
 *
 * Covers: the window states at fixed Phoenix times; validation rejects
 * outside the window and the staff test override lets one through; age and
 * returning-years checks; other form ids are ignored; the form template has
 * every field of the RaceReach form (out/fields.json when present, else the
 * include's own list) plus Turnstile; mail goes to marketing@ with the
 * photo attached; the shortcode state and the hidden form; before-mail
 * copies the photo into the private folder (deny-all) and logs the entry;
 * the Flamingo link; export header and rows; delete-test removes only the
 * given email. No RaceReach push is configured (sink mode cf7), so no
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
ok( false !== strpos( $tmpl, '[file rt-headshot id:rt-headshot limit:4mb filetypes:jpg|jpeg|png|webp]' ), 'headshot file tag' );
ok( false !== strpos( $tmpl, '[acceptance rt-consent-publicity optional]' ), 'publicity optional' );
ok( false === strpos( $tmpl, "\u{2014}" ), 'no em dash in template' );

// Race plans fill: only 2027 races, for our tag only.
$t = arv_rt_apply_form_tag( array( 'name' => 'rt-race-plans', 'options' => array() ), true );
ok( array( 'Rabid Raccoon, Jan 9', 'Black Canyon Ultras, Feb 13' ) === $t['values'], 'race plans 2027 only' );
$o = arv_rt_apply_form_tag( array( 'name' => 'adv-trip', 'values' => array( 'x' ) ), true );
ok( array( 'x' ) === $o['values'], 'other tags untouched' );

// Mail.
$m = arv_rt_apply_mail();
ok( false !== strpos( $m['recipient'], 'marketing@aravaiparunning.com' ), 'mail to marketing' );
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
ok( isset( $r->bad['rt-returning-years'] ), 'returning yes needs years' );
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
ok( 0 === count( arv_rt_apply_delete_email( '' ) ) - 3, 'empty email deletes nothing' );

// No RaceReach push configured: nothing leaves the server.
ok( 0 === $GLOBALS['t_remote'], 'no remote calls' );

rmrf( $GLOBALS['t_up'] );

if ( $fails ) {
	echo "$fails of $n FAILED\n";
	exit( 1 );
}
echo "ALL PASS ($n checks)\n";
