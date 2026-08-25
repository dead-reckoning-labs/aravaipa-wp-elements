<?php
/**
 * Updater test harness. Stubs the WordPress transient and HTTP APIs
 * in-memory rather than the Cornerstone/rendering stubs arv-edge.php uses,
 * which is why this is a separate file: mixing the two stub sets would mean
 * either file could silently start depending on the other's globals.
 *
 *   php arv-updater-test.php
 */

define( 'ABSPATH', true );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['_transients']    = array();
$GLOBALS['_http_queue']    = array();
$GLOBALS['_http_calls']    = 0;
$GLOBALS['_wp_option_ver'] = '6.6';

function get_transient( $key ) {
	return $GLOBALS['_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['_transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['_transients'][ $key ] );
	return true;
}

/** Queue a canned HTTP response (or a WP_Error) for the next wp_remote_get call. */
function arv_test_queue_response( $response ) {
	$GLOBALS['_http_queue'][] = $response;
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['_http_calls']++;
	return array_shift( $GLOBALS['_http_queue'] ) ?? new WP_Error( 'no_response_queued' );
}
class WP_Error {
	public $code;
	public function __construct( $code = '' ) {
		$this->code = $code;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['code'] ?? 0;
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'] ?? '';
}
function get_bloginfo( $show = '' ) {
	return $GLOBALS['_wp_option_ver'];
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

$GLOBALS['_filters'] = array();
$GLOBALS['_actions'] = array();
function add_filter( $tag, $fn ) {
	$GLOBALS['_filters'][ $tag ][] = $fn;
}
function add_action( $tag, $fn ) {
	$GLOBALS['_actions'][ $tag ][] = $fn;
}
function apply_filter_stub( $tag, ...$args ) {
	$fn = $GLOBALS['_filters'][ $tag ][0];
	return call_user_func_array( $fn, $args );
}
function do_action_stub( $tag, ...$args ) {
	$fn = $GLOBALS['_actions'][ $tag ][0];
	return call_user_func_array( $fn, $args );
}

require_once __DIR__ . '/includes/updater.php';

$pass = 0;
$fail = 0;
function t( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ok   $name\n";
	} else {
		$fail++;
		echo "  FAIL $name\n";
	}
}

function reset_state() {
	$GLOBALS['_transients'] = array();
	$GLOBALS['_http_queue'] = array();
	$GLOBALS['_http_calls'] = 0;
}

function fake_release( $tag, $assets = array(), $body = 'Notes.' ) {
	return array(
		'code' => 200,
		'body' => json_encode(
			array(
				'tag_name' => $tag,
				'html_url' => 'https://github.com/x/x/releases/tag/' . $tag,
				'body'     => $body,
				'assets'   => $assets,
			)
		),
	);
}

echo "version_from_tag:\n";
t( 'strips leading v', arv_elements_version_from_tag( 'v0.3.0' ) === '0.3.0' );
t( 'leaves bare version alone', arv_elements_version_from_tag( '0.3.0' ) === '0.3.0' );

echo "release_zip_url:\n";
$rel = (object) array(
	'assets' => array(
		(object) array( 'name' => 'aravaipa-elements-0.3.0.zip', 'browser_download_url' => 'https://x/wrong.zip' ),
		(object) array( 'name' => 'aravaipa-elements.zip', 'browser_download_url' => 'https://x/right.zip' ),
	),
);
t( 'finds the exact packaged filename among other assets', arv_elements_release_zip_url( $rel ) === 'https://x/right.zip' );
t( 'no matching asset returns null', arv_elements_release_zip_url( (object) array( 'assets' => array() ) ) === null );
t( 'missing assets key returns null, not a fatal', arv_elements_release_zip_url( (object) array() ) === null );

echo "latest_release caching:\n";
reset_state();
arv_test_queue_response( fake_release( 'v0.3.0' ) );
$first  = arv_elements_latest_release();
$second = arv_elements_latest_release();
t( 'first call fetches', null !== $first );
t( 'second call is served from cache, not a second request', 1 === $GLOBALS['_http_calls'] );

reset_state();
arv_test_queue_response( array( 'code' => 500, 'body' => '' ) );
$failed = arv_elements_latest_release();
t( 'non-200 response returns null', null === $failed );
t( 'failure is cached rather than retried every call', get_transient( 'arv_elements_latest_release' ) === 'none' );
$still_null = arv_elements_latest_release();
t( 'cached failure does not trigger a second request', 1 === $GLOBALS['_http_calls'] );

reset_state();
arv_test_queue_response( new WP_Error( 'timeout' ) );
t( 'WP_Error response returns null, not a fatal', null === arv_elements_latest_release() );

reset_state();
set_transient( 'arv_elements_latest_release', json_decode( fake_release( 'v0.1.0' )['body'] ), HOUR_IN_SECONDS );
arv_test_queue_response( fake_release( 'v0.9.0' ) );
$bypassed = arv_elements_latest_release( true );
t( 'bypass_cache ignores a fresh cache and fetches anyway', '0.9.0' === arv_elements_version_from_tag( $bypassed->tag_name ) );

echo "check_for_update:\n";
$zip_asset = array( (object) array( 'name' => 'aravaipa-elements.zip', 'browser_download_url' => 'https://x/z.zip' ) );

reset_state();
arv_test_queue_response( fake_release( 'v0.3.0', $zip_asset ) );
$transient = (object) array(
	'checked'  => array( ARV_ELEMENTS_SLUG => '0.2.0' ),
	'response' => array(),
);
$_GET = array();
$out = arv_elements_check_for_update( $transient );
t( 'newer release populates response for our slug', isset( $out->response[ ARV_ELEMENTS_SLUG ] ) );
t( 'new_version matches the release tag', '0.3.0' === $out->response[ ARV_ELEMENTS_SLUG ]->new_version );
t( 'package points at the real zip asset, not the auto-generated source zip', 'https://x/z.zip' === $out->response[ ARV_ELEMENTS_SLUG ]->package );

reset_state();
arv_test_queue_response( fake_release( 'v0.3.0', $zip_asset ) );
$transient = (object) array(
	'checked'  => array( ARV_ELEMENTS_SLUG => '0.3.0' ),
	'response' => array( ARV_ELEMENTS_SLUG => (object) array( 'stale' => true ) ),
);
$out = arv_elements_check_for_update( $transient );
t( 'already current: stale response entry is cleared, not left in place', ! isset( $out->response[ ARV_ELEMENTS_SLUG ] ) );

reset_state();
arv_test_queue_response( fake_release( 'v0.3.0', $zip_asset ) );
$transient = (object) array(
	'checked'  => array( ARV_ELEMENTS_SLUG => '0.9.0' ),
	'response' => array(),
);
$out = arv_elements_check_for_update( $transient );
t( 'locally ahead of the release: no update offered', ! isset( $out->response[ ARV_ELEMENTS_SLUG ] ) );

reset_state();
$transient = (object) array( 'checked' => array() );
$out       = arv_elements_check_for_update( $transient );
t( 'empty checked list returns the transient untouched, no fatal', $out === $transient );
t( 'and makes no HTTP request at all', 0 === $GLOBALS['_http_calls'] );

reset_state();
arv_test_queue_response( fake_release( 'v0.3.0', array() ) );
$transient = (object) array( 'checked' => array( ARV_ELEMENTS_SLUG => '0.1.0' ), 'response' => array() );
$out       = arv_elements_check_for_update( $transient );
t( 'newer release with no zip asset offers nothing to install', ! isset( $out->response[ ARV_ELEMENTS_SLUG ] ) );

echo "plugins_api:\n";
reset_state();
$result = arv_elements_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'some-other-plugin' ) );
t( 'wrong slug passes the original result through untouched', false === $result );

reset_state();
$result = arv_elements_plugins_api( false, 'query_plugins', (object) array( 'slug' => 'aravaipa-elements' ) );
t( 'wrong action passes through untouched', false === $result );

reset_state();
arv_test_queue_response( fake_release( 'v0.3.0', $zip_asset, "<script>alert(1)</script>\nSecond line." ) );
$result = arv_elements_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'aravaipa-elements' ) );
t( 'right slug and action returns the release info', '0.3.0' === $result->version );
t( 'download_link is the real zip asset', 'https://x/z.zip' === $result->download_link );
t( 'changelog body is escaped', strpos( $result->sections['changelog'], '<script>alert' ) === false );
t( 'changelog keeps the line break', strpos( $result->sections['changelog'], '<br' ) !== false );

echo "row meta:\n";
t( 'other plugin file is untouched', array( 'a' ) === arv_elements_row_meta( array( 'a' ), 'other/other.php' ) );
$meta = arv_elements_row_meta( array(), ARV_ELEMENTS_SLUG );
t( 'our plugin gets a GitHub link appended', false !== strpos( $meta[0], 'github.com/' . ARV_ELEMENTS_GH_REPO ) );

echo "cache clearing after update:\n";
set_transient( 'arv_elements_latest_release', 'anything', HOUR_IN_SECONDS );
arv_elements_clear_cache_after_update( null, array( 'action' => 'update', 'type' => 'plugin' ) );
t( 'plugin update clears the cache', false === get_transient( 'arv_elements_latest_release' ) );

set_transient( 'arv_elements_latest_release', 'anything', HOUR_IN_SECONDS );
arv_elements_clear_cache_after_update( null, array( 'action' => 'install', 'type' => 'plugin' ) );
t( 'a plugin install (not update) leaves the cache alone', 'anything' === get_transient( 'arv_elements_latest_release' ) );

set_transient( 'arv_elements_latest_release', 'anything', HOUR_IN_SECONDS );
arv_elements_clear_cache_after_update( null, array( 'action' => 'update', 'type' => 'theme' ) );
t( 'a theme update leaves the cache alone', 'anything' === get_transient( 'arv_elements_latest_release' ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
