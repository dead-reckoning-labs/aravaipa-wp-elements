<?php
/**
 * Racing Team apply hardening (job ldqh0d, 0.101.27), without WordPress:
 * WordPress, CF7 and the REST API are stubbed the same way as
 * racing-team-apply-test.php.
 *
 * Covers: the install template writes limit:10mb and HEIC/HEIF on both file
 * tags; the wpcf7_form_tag filter rewrites limit and filetypes on our form
 * and leaves other forms alone; hint copy says 10 MB; the friendly display
 * messages apply to our form only; Turnstile gets refresh-expired and retry;
 * the shortcode enqueues assets/aravaipa-rt-apply.js with the plugin version
 * and passes the beacon URL; the failed submit log (fail_log): mail_sent is
 * not logged, validation_failed is logged with field names and no values,
 * other forms are ignored, the ring keeps 300; the beacon refuses unknown
 * statuses and fields and rate limits; GET needs manage_options.
 *
 * Run: php scripts/test/racing-team-apply-harden-test.php
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

// Extra stubs for this test.
$GLOBALS['t_enq']       = array();
$GLOBALS['t_trans']     = array();
$GLOBALS['t_can']       = false;
$GLOBALS['t_routes']    = array();
define( 'ARV_ELEMENTS_URL', 'https://example.test/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_VERSION', '9.9.9' );
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['t_enq'][ $h ] = array( $src, $ver, $footer );
}
function wpcf7_enqueue_scripts() {
	$GLOBALS['t_enq']['contact-form-7'] = array( 'cf7', false, true );
}
function wpcf7_enqueue_styles() {
	$GLOBALS['t_enq']['contact-form-7-css'] = array( 'cf7', false, false );
}
function rest_url( $p = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $p, '/' );
}
function get_transient( $k ) {
	return isset( $GLOBALS['t_trans'][ $k ] ) ? $GLOBALS['t_trans'][ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['t_trans'][ $k ] = $v;
	return true;
}
function current_user_can( $cap ) {
	return 'manage_options' === $cap && $GLOBALS['t_can'];
}
function register_rest_route( $ns, $route, $args ) {
	$GLOBALS['t_routes'][ $ns . $route ] = $args;
}
class WP_Error {
	public $code;
	public $data;
	public function __construct( $code, $msg = '', $data = array() ) {
		$this->code = $code;
		$this->data = $data;
	}
}
class T_Request {
	public $b;
	public function __construct( $b ) {
		$this->b = $b;
	}
	public function get_body() {
		return $this->b;
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


function t_last() {
	$l = get_option( 'arv_rt_apply_fail_log' );
	return end( $l );
}

$ours  = 501;
$other = 777;
$GLOBALS['t_opts']['arv_rt_apply_form_id'] = $ours;

// Template: 10 MB and HEIC on both file tags.
$tmpl = arv_rt_apply_form_template();
ok( false !== strpos( $tmpl, '[file rt-headshot id:rt-headshot limit:10mb filetypes:jpg|jpeg|png|webp|heic|heif]' ), 'template headshot: 10mb + heic' );
ok( false !== strpos( $tmpl, '[file rt-resume-file id:rt-resume-file limit:10mb filetypes:pdf|jpg|jpeg|png|webp|heic|heif]' ), 'template resume: 10mb + heic' );
ok( false === strpos( $tmpl, 'limit:4mb' ), 'no 4mb left in the template' );
ok( false !== strpos( $tmpl, '10 MB max' ) && false === strpos( $tmpl, '4 MB' ), 'hint copy says 10 MB' );

// Runtime filter: the stored form still says limit:4mb.
$stored = array(
	'type'    => 'file',
	'name'    => 'rt-headshot',
	'options' => array( 'id:rt-headshot', 'limit:4mb', 'filetypes:jpg|jpeg|png|webp' ),
);
WPCF7_ContactForm::$current = new WPCF7_ContactForm( $ours );
$t = arv_rt_apply_form_tag( $stored, true );
ok( in_array( 'limit:10mb', $t['options'], true ) && ! in_array( 'limit:4mb', $t['options'], true ), 'our form: limit becomes 10mb' );
ok( in_array( 'filetypes:jpg|jpeg|png|webp|heic|heif', $t['options'], true ), 'our form: headshot takes heic' );
ok( in_array( 'id:rt-headshot', $t['options'], true ) && 3 === count( $t['options'] ), 'our form: other options kept, no duplicates' );
$r = arv_rt_apply_form_tag( array( 'type' => 'file', 'name' => 'rt-resume-file', 'options' => array( 'limit:4mb', 'filetypes:pdf|jpg' ) ), false );
ok( in_array( 'limit:10mb', $r['options'], true ) && in_array( 'filetypes:pdf|jpg|jpeg|png|webp|heic|heif', $r['options'], true ), 'our form: resume 10mb + heic at validation time' );
WPCF7_ContactForm::$current = new WPCF7_ContactForm( $other );
ok( $stored === arv_rt_apply_form_tag( $stored, true ), 'other form: file tag untouched' );
WPCF7_ContactForm::$current = null;
ok( $stored === arv_rt_apply_form_tag( $stored, true ), 'no current form: file tag untouched' );
ok( in_array( 'arv_rt_apply_form_tag', $GLOBALS['t_filters']['wpcf7_form_tag'], true ), 'form tag filter registered' );

// Friendly messages at display time, ours only.
WPCF7_ContactForm::$current = new WPCF7_ContactForm( $ours );
ok( false !== strpos( arv_rt_apply_display_message( 'x', 'spam' ), 'marketing@aravaiparunning.com' ), 'spam message has the email fallback' );
ok( false !== strpos( arv_rt_apply_display_message( 'x', 'upload_file_too_large' ), '10 MB' ), 'too large message says 10 MB' );
ok( 'x' === arv_rt_apply_display_message( 'x', 'not_ours_key' ), 'unknown key passes through' );
WPCF7_ContactForm::$current = new WPCF7_ContactForm( $other );
ok( 'x' === arv_rt_apply_display_message( 'x', 'spam' ), 'other form keeps its own messages' );
foreach ( arv_rt_apply_messages() as $k => $m ) {
	ok( false === strpos( $m, "\u{2014}" ), "no em dash in message $k" );
}

// Agreements validate like answers, so Submit is never silently disabled.
ok( false !== strpos( arv_rt_apply_additional_prop( "flamingo_email: [rt-email]", new WPCF7_ContactForm( $ours ) ), "\nacceptance_as_validation: on" ), 'our form: acceptance_as_validation added at runtime' );
ok( 1 === substr_count( arv_rt_apply_additional_prop( arv_rt_apply_additional_settings(), new WPCF7_ContactForm( $ours ) ), 'acceptance_as_validation' ), 'template has it once' );
ok( 'x' === arv_rt_apply_additional_prop( 'x', new WPCF7_ContactForm( $other ) ), 'other form: additional settings untouched' );

// Turnstile attributes on our form only.
$ts = '<div class="rt-submit"><div class="wpcf7-turnstile cf-turnstile" data-sitekey="abc" data-response-field-name="_wpcf7_turnstile_response"></div></div>';
WPCF7_ContactForm::$current = new WPCF7_ContactForm( $ours );
$h = arv_rt_apply_form_elements( $ts );
ok( false !== strpos( $h, 'data-refresh-expired="auto"' ) && false !== strpos( $h, 'data-retry="auto"' ), 'turnstile: refresh-expired and retry' );
ok( 1 === substr_count( arv_rt_apply_form_elements( $h ), 'data-refresh-expired' ), 'turnstile: attributes not doubled' );
WPCF7_ContactForm::$current = new WPCF7_ContactForm( $other );
ok( $ts === arv_rt_apply_form_elements( $ts ), 'turnstile: other form untouched' );

// Shortcode enqueues the helper script when the form renders.
$GLOBALS['t_now'] = ts( '2026-11-25T12:00:00-07:00' );
$sc               = arv_rt_apply_shortcode();
ok( isset( $GLOBALS['t_enq']['aravaipa-rt-apply'] ), 'aravaipa-rt-apply enqueued' );
ok( isset( $GLOBALS['t_enq']['aravaipa-rt-apply'] ) && ARV_ELEMENTS_URL . 'assets/aravaipa-rt-apply.js' === $GLOBALS['t_enq']['aravaipa-rt-apply'][0] && '9.9.9' === $GLOBALS['t_enq']['aravaipa-rt-apply'][1] && true === $GLOBALS['t_enq']['aravaipa-rt-apply'][2], 'enqueue: asset path, plugin version, footer' );
ok( false !== strpos( $sc, 'data-fail-url="https://example.test/wp-json/aravaipa/v1/rt-apply-fail"' ), 'beacon URL on the wrapper, no arv in the path' );
ok( false !== strpos( $sc, '.rt-summary' ) && ! preg_match( '/rt-(summary|alert|notice)[^}]*border-(left|top|right|bottom)/', $sc ), 'summary styles, no edge stripes' );
ok( is_file( dirname( __DIR__, 2 ) . '/assets/aravaipa-rt-apply.js' ), 'asset file exists' );
ok( isset( $GLOBALS['t_enq']['contact-form-7'] ) && isset( $GLOBALS['t_enq']['contact-form-7-css'] ), 'CF7 script and styles loaded with the form (site runs CF7 without them)' );
$GLOBALS['t_enq'] = array();
$GLOBALS['t_now'] = ts( '2026-12-02T12:00:00-07:00' );
arv_rt_apply_shortcode();
ok( ! isset( $GLOBALS['t_enq']['aravaipa-rt-apply'] ), 'closed: no script enqueued' );
$GLOBALS['t_now'] = null;

// fail_log: server side.
$GLOBALS['t_opts']['arv_rt_apply_fail_log'] = array();
$_SERVER['HTTP_USER_AGENT']                 = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
$_SERVER['REMOTE_ADDR']                     = '203.0.113.9';
WPCF7_Submission::$inst                     = new WPCF7_Submission();
WPCF7_Submission::$inst->posted             = array( 'rt-first-name' => 'Courtney', 'rt-email' => 'c@example.com' );
$_FILES = array( 'rt-headshot' => array( 'name' => 'IMG_1234.HEIC', 'size' => 6 * 1048576 + 300000 ) );
arv_rt_apply_fail_on_submit( new WPCF7_ContactForm( $ours ), array( 'status' => 'mail_sent' ) );
ok( array() === get_option( 'arv_rt_apply_fail_log' ), 'fail_log: mail_sent not logged' );
arv_rt_apply_fail_on_submit(
	new WPCF7_ContactForm( $ours ),
	array(
		'status'         => 'validation_failed',
		'invalid_fields' => array(
			'rt-headshot' => array( 'reason' => 'too large', 'idref' => 'rt-headshot' ),
			'rt-why'      => array( 'reason' => 'required', 'idref' => 'rt-why' ),
		),
	)
);
$log = get_option( 'arv_rt_apply_fail_log' );
ok( 1 === count( $log ) && 'validation_failed' === $log[0]['status'] && 'server' === $log[0]['src'], 'fail_log: validation_failed logged' );
ok( array( 'rt-headshot', 'rt-why' ) === $log[0]['fields'], 'fail_log: invalid field names' );
ok( array( 'headshot' => 6.3 ) === $log[0]['files'], 'fail_log: file size to 0.1 MB' );
ok( 'iOS Safari' === $log[0]['ua'], 'fail_log: browser family' );
$flat = json_encode( $log );
ok( false === strpos( $flat, 'Courtney' ) && false === strpos( $flat, 'example.com' ) && false === strpos( $flat, '203.0.113' ) && false === strpos( $flat, 'IMG_1234' ) && false === strpos( $flat, 'too large' ), 'fail_log: no names, emails, answers, IPs or file names' );
arv_rt_apply_fail_on_submit( new WPCF7_ContactForm( $ours ), array( 'status' => 'validation_failed', 'invalid_fields' => array( array( 'field' => 'rt-email', 'message' => 'x' ), array( 'field' => 'evil', 'message' => 'y' ) ) ) );
$log = get_option( 'arv_rt_apply_fail_log' );
ok( array( 'rt-email' ) === end( $log )['fields'], 'fail_log: list shape, unknown names dropped' );
arv_rt_apply_fail_on_submit( new WPCF7_ContactForm( $other ), array( 'status' => 'spam' ) );
ok( 2 === count( get_option( 'arv_rt_apply_fail_log' ) ), 'fail_log: other forms ignored' );
arv_rt_apply_fail_on_submit( new WPCF7_ContactForm( $ours ), array( 'status' => 'spam' ) );
ok( 'spam' === t_last()['status'], 'fail_log: spam logged' );
ok( in_array( 'arv_rt_apply_fail_on_submit', $GLOBALS['t_filters']['wpcf7_submit'], true ), 'fail_log: wpcf7_submit hooked' );
$_FILES = array();
WPCF7_Submission::$inst = null;
$GLOBALS['t_opts']['arv_rt_apply_fail_log'] = array_fill( 0, 299, array( 'status' => 'old' ) );
arv_rt_apply_fail_on_submit( new WPCF7_ContactForm( $ours ), array( 'status' => 'aborted' ) );
arv_rt_apply_fail_on_submit( new WPCF7_ContactForm( $ours ), array( 'status' => 'aborted' ) );
$log = get_option( 'arv_rt_apply_fail_log' );
ok( 300 === count( $log ) && 'aborted' === end( $log )['status'], 'fail_log: ring keeps the last 300' );

// fail_log: beacon.
$GLOBALS['t_opts']['arv_rt_apply_fail_log'] = array();
$ok = arv_rt_apply_fail_beacon( new T_Request( json_encode( array( 'status' => 'network', 'fields' => array() ) ) ) );
ok( is_array( $ok ) && ! empty( $ok['ok'] ) && 'beacon' === get_option( 'arv_rt_apply_fail_log' )[0]['src'], 'beacon: network logged' );
$bad = arv_rt_apply_fail_beacon( new T_Request( json_encode( array( 'status' => 'network', 'fields' => array( 'rt-email', 'password' ) ) ) ) );
ok( $bad instanceof WP_Error && 400 === $bad->data['status'], 'beacon: unknown field refused' );
$bad = arv_rt_apply_fail_beacon( new T_Request( json_encode( array( 'status' => 'Courtney Clifford' ) ) ) );
ok( $bad instanceof WP_Error, 'beacon: unknown status refused' );
ok( arv_rt_apply_fail_beacon( new T_Request( 'not json' ) ) instanceof WP_Error, 'beacon: bad body refused' );
ok( 1 === count( get_option( 'arv_rt_apply_fail_log' ) ), 'beacon: refused reports not logged' );
$ok = arv_rt_apply_fail_beacon( new T_Request( json_encode( array( 'status' => 'no_token', 'fields' => array( 'rt-why', 'rt-consent-' . arv_rt_apply_consents()[0]['key'] ) ) ) ) );
ok( is_array( $ok ) && 'no_token' === t_last()['status'], 'beacon: allowed fields logged' );
$last = null;
for ( $i = 0; $i < 20; $i++ ) {
	$last = arv_rt_apply_fail_beacon( new T_Request( json_encode( array( 'status' => 'network' ) ) ) );
}
ok( $last instanceof WP_Error && 429 === $last->data['status'], 'beacon: rate limited per IP' );
ok( ARV_RT_APPLY_BEACON_PER_HOUR === count( get_option( 'arv_rt_apply_fail_log' ) ), 'beacon: log stops at the hourly cap' );

// Routes.
arv_rt_apply_fail_routes();
$rt = isset( $GLOBALS['t_routes']['aravaipa/v1/rt-apply-fail'] ) ? $GLOBALS['t_routes']['aravaipa/v1/rt-apply-fail'] : array();
ok( 2 === count( $rt ), 'routes registered under aravaipa/v1/rt-apply-fail' );
$get = null;
foreach ( $rt as $r ) {
	if ( 'GET' === $r['methods'] ) {
		$get = $r;
	}
}
ok( $get && 'arv_rt_apply_fail_can_read' === $get['permission_callback'], 'GET guarded' );
$GLOBALS['t_can'] = false;
ok( false === arv_rt_apply_fail_can_read(), 'GET requires manage_options' );
$GLOBALS['t_can'] = true;
ok( true === arv_rt_apply_fail_can_read(), 'admin may read' );
$read = arv_rt_apply_fail_read();
ok( ARV_RT_APPLY_BEACON_PER_HOUR === $read['count'], 'GET returns the log' );

ok( 0 === $GLOBALS['t_remote'], 'no remote calls' );
rmrf( $GLOBALS['t_up'] );

if ( $fails ) {
	echo "$fails of $n FAILED\n";
	exit( 1 );
}
echo "ALL PASS ($n checks)\n";
