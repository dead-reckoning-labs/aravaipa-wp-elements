<?php
/**
 * WordPress, CF7 and Flamingo stubs shared by the racing-team-apply confirm
 * and mail2 tests (same shape as racing-team-apply-harden-test.php, plus
 * CF7 props, save, submission status and the form_elements filter on render).
 *
 * @package Aravaipa_Elements
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['t_opts']    = array();
$GLOBALS['t_filters'] = array();
$GLOBALS['t_now']     = null;
$GLOBALS['t_meta']    = array();
$GLOBALS['t_deleted'] = array();
$GLOBALS['t_remote']  = 0;
$GLOBALS['t_flam']    = array();
$GLOBALS['t_store']   = array();
$GLOBALS['t_saves']   = 0;
$GLOBALS['t_up']      = sys_get_temp_dir() . '/rt-apply-tmp-' . getmypid();

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
		$prev          = self::$current;
		self::$current = $this;
		$inner         = '<div class="rt-form">FORM-' . $this->i . '</div>';
		foreach ( isset( $GLOBALS['t_filters']['wpcf7_form_elements'] ) ? $GLOBALS['t_filters']['wpcf7_form_elements'] : array() as $fn ) {
			$inner = call_user_func( $fn, $inner );
		}
		self::$current = $prev;
		$sent          = WPCF7_Submission::$inst && 'mail_sent' === WPCF7_Submission::$inst->status;
		return '<div class="wpcf7" id="wpcf7-f' . $this->i . '-p1-o1"><form class="wpcf7-form ' . ( $sent ? 'sent' : 'init' ) . '" data-status="' . ( $sent ? 'sent' : 'init' ) . '">' . $inner . '<div class="wpcf7-response-output"></div></form></div>';
	}
	public function prop( $k ) {
		return isset( $GLOBALS['t_store'][ $this->i ][ $k ] ) ? $GLOBALS['t_store'][ $this->i ][ $k ] : null;
	}
	public function set_properties( $p ) {
		$this->props = array_merge( $this->props, $p );
	}
	public function save() {
		$id = $this->i ? $this->i : 9001;
		$GLOBALS['t_store'][ $id ] = array_merge( isset( $GLOBALS['t_store'][ $id ] ) ? $GLOBALS['t_store'][ $id ] : array(), $this->props );
		++$GLOBALS['t_saves'];
		return $id;
	}
	public static function get_template( $a = array() ) {
		return new WPCF7_ContactForm( 0 );
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
	public $status      = 'init';
	public $form        = null;
	public function uploaded_files() {
		return $this->files;
	}
	public function get_status() {
		return $this->status;
	}
	public function get_contact_form() {
		return $this->form;
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
function wp_add_inline_script( $h, $js, $pos = 'after' ) {
	$GLOBALS['t_inline'][ $h ][] = array( $js, $pos );
	return true;
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


function ok( $c, $m ) {
	global $fails, $n;
	++$n;
	if ( ! $c ) {
		++$fails;
		echo "FAIL: $m\n";
	}
}
$fails = 0;
$n     = 0;
