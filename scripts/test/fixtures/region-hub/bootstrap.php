<?php
/**
 * WordPress stubs for scripts/test/region-hub-test.php, lifted from the
 * arv-edge.php preamble so the hub and the shared race tile load exactly as
 * that harness loads them. Test only, never shipped.
 */
define( 'ABSPATH', true );
define( 'ARV_ELEMENTS_URL', './' );
define( 'ARV_ELEMENTS_VERSION', '0.0.0-test' );
define( 'ARV_ELEMENTS_PATH', dirname( __DIR__, 4 ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
function cs_register_element( $n, $c ) { $GLOBALS['ARV_EL'][ $n ] = $c; }
function cs_value( $d, $x = 'markup', $p = false ) { return $d; }
function cs_compose_values( $v, ...$p ) { return $v; }
function cs_compose_controls( $c, ...$p ) { return $c; }
function cs_partial_controls( $n ) { return array(); }
function __( $s, $d = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $u ) { return $u; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
$GLOBALS['ARV_SHORTCODES'] = array();
function add_action( $t, $f, $p = 10, $n = 1 ) {}
function add_shortcode( $t, $f ) { $GLOBALS['ARV_SHORTCODES'][ $t ] = $f; }
function add_filter( ...$a ) {}
$GLOBALS['ENQUEUED'] = array();
function wp_enqueue_script( $h, $src = '', $deps = array(), $v = false, $footer = false ) { $GLOBALS['ENQUEUED'][] = 'js:' . $h; }
function wp_enqueue_style( $h, $src = '', $deps = array(), $v = false ) { $GLOBALS['ENQUEUED'][] = 'css:' . $h; }
function _n( $single, $plural, $count, $domain = '' ) { return 1 === $count ? $single : $plural; }
function _x( $s, $ctx, $d = '' ) { return $s; }
$GLOBALS['NOW'] = '2026-11-25';
function current_time( $f ) { return $GLOBALS['NOW']; }
function apply_filters( $tag, $v ) { return $v; }
function get_posts( $args = array() ) { return array(); }
$GLOBALS['ARV_OPTIONS'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['ARV_OPTIONS'] ) ? $GLOBALS['ARV_OPTIONS'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['ARV_OPTIONS'][ $k ] = $v; return true; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $p = '' ) { return 'https://www.aravaiparunning.com' . $p; }
function shortcode_atts( $pairs, $atts, $sc = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $k => $v ) {
		$out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v;
	}
	return $out;
}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
require_once ARV_ELEMENTS_PATH . 'includes/helpers.php';
require_once ARV_ELEMENTS_PATH . 'includes/race-store.php';
require_once ARV_ELEMENTS_PATH . 'includes/race-schema.php';
require_once ARV_ELEMENTS_PATH . 'includes/elements/upcoming-races.php';
