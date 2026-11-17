<?php
/**
 * Adventures interest form to ActiveCampaign
 * (includes/adventures-activecampaign.php), without WordPress or the
 * network: WordPress, CF7 and the ActiveCampaign API are stubbed.
 *
 * Covers: other forms ignored; wpcf7_mail_sent and wpcf7_mail_failed both
 * push; request order and bodies; trip label to tag (Madeira); "Any trip"
 * gets only the general tag; list and tags created once and cached; the
 * newsletter list only with the box ticked; timeout and Api-Token on every
 * request; a 500 stops the chain, logs one row without the key and
 * schedules exactly one retry (wp_schedule_single_event); a failing retry
 * schedules nothing; the log caps at 50; missing settings skip quietly;
 * a one-word name gives an empty last name.
 *
 * Run: php scripts/test/adventures-ac-test.php
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );

const T_KEY  = 'testkey-not-real-0000';
const T_BASE = 'https://example.api-us1.com';

$GLOBALS['t_opts']     = array();
$GLOBALS['t_reqs']     = array();
$GLOBALS['t_sched']    = array();
$GLOBALS['t_actions']  = array();
$GLOBALS['t_fail']     = array(); // path prefix => code.
$GLOBALS['t_ac_lists'] = array(); // name => id in the fake account.
$GLOBALS['t_ac_tags']  = array(); // name => id.
$GLOBALS['t_posted']   = array();

class WP_Error {
	public $m;
	public function __construct( $m = '' ) {
		$this->m = $m;
	}
	public function get_error_message() {
		return $this->m;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function add_action( $hook, $fn ) {
	$GLOBALS['t_actions'][ $hook ][] = $fn;
}
function do_action( $hook, ...$args ) {
	foreach ( isset( $GLOBALS['t_actions'][ $hook ] ) ? $GLOBALS['t_actions'][ $hook ] : array() as $fn ) {
		$fn( ...$args );
	}
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['t_opts'] ) ? $GLOBALS['t_opts'][ $k ] : $d;
}
function update_option( $k, $v ) {
	$GLOBALS['t_opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['t_opts'][ $k ] );
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}
function wp_schedule_single_event( $t, $hook, $args ) {
	$GLOBALS['t_sched'][] = array( $t, $hook, $args );
	return true;
}
function arv_adv_trips() {
	return array(
		array( 'name' => 'Island in the Sky', 'dates' => 'June 6-13, 2027' ),
		array( 'name' => 'Madeira Adventure', 'dates' => 'October 3-9, 2027' ),
		array( 'name' => 'Sail &#038; Trail', 'dates' => 'August 2028' ),
		array( 'name' => 'Island in the Sky', 'dates' => 'June 2028' ),
	);
}
class WPCF7_Submission {
	public static function get_instance() {
		return new self();
	}
	public function get_posted_data( $k ) {
		return isset( $GLOBALS['t_posted'][ $k ] ) ? $GLOBALS['t_posted'][ $k ] : '';
	}
}
class T_Form {
	public $i;
	public function __construct( $i ) {
		$this->i = $i;
	}
	public function id() {
		return $this->i;
	}
}

/** Fake ActiveCampaign. */
function t_ac( $method, $url, $args ) {
	$path = substr( $url, strlen( T_BASE . '/api/3/' ) );
	$body = isset( $args['body'] ) ? json_decode( $args['body'], true ) : null;
	$GLOBALS['t_reqs'][] = array(
		'method'  => $method,
		'path'    => $path,
		'body'    => $body,
		'timeout' => isset( $args['timeout'] ) ? $args['timeout'] : null,
		'token'   => isset( $args['headers']['Api-Token'] ) ? $args['headers']['Api-Token'] : null,
	);
	foreach ( $GLOBALS['t_fail'] as $prefix => $code ) {
		if ( 0 === strpos( $method . ' ' . $path, $prefix ) ) {
			return t_res( $code, array( 'errors' => array( array( 'title' => 'Server exploded near ' . T_KEY ) ) ) );
		}
	}
	if ( 'POST contact/sync' === $method . ' ' . $path ) {
		return t_res( 201, array( 'contact' => array( 'id' => '777' ) ) );
	}
	if ( 'GET' === $method && 0 === strpos( $path, 'lists?' ) ) {
		parse_str( substr( $path, 6 ), $q );
		$name = $q['filters']['name'];
		$out  = isset( $GLOBALS['t_ac_lists'][ $name ] ) ? array( array( 'id' => (string) $GLOBALS['t_ac_lists'][ $name ], 'name' => $name ) ) : array();
		return t_res( 200, array( 'lists' => $out ) );
	}
	if ( 'POST lists' === $method . ' ' . $path ) {
		$GLOBALS['t_ac_lists'][ $body['list']['name'] ] = 55;
		return t_res( 201, array( 'list' => array( 'id' => '55' ) ) );
	}
	if ( 'GET' === $method && 0 === strpos( $path, 'tags?' ) ) {
		parse_str( substr( $path, 5 ), $q );
		$out = array( array( 'id' => '1', 'tag' => 'entrant-division-ultra-adventures' ) );
		foreach ( $GLOBALS['t_ac_tags'] as $n => $id ) {
			if ( false !== stripos( $n, $q['search'] ) ) {
				$out[] = array( 'id' => (string) $id, 'tag' => $n );
			}
		}
		return t_res( 200, array( 'tags' => $out ) );
	}
	if ( 'POST tags' === $method . ' ' . $path ) {
		$id = 100 + count( $GLOBALS['t_ac_tags'] );
		$GLOBALS['t_ac_tags'][ $body['tag']['tag'] ] = $id;
		return t_res( 201, array( 'tag' => array( 'id' => (string) $id ) ) );
	}
	if ( 'POST contactLists' === $method . ' ' . $path ) {
		return t_res( 201, array( 'contactList' => array( 'id' => '9' ) ) );
	}
	if ( 'POST contactTags' === $method . ' ' . $path ) {
		return t_res( 201, array( 'contactTag' => array( 'id' => '9' ) ) );
	}
	return t_res( 404, array( 'message' => 'no route' ) );
}
function t_res( $code, $body ) {
	return array(
		'code' => $code,
		'body' => json_encode( $body ),
	);
}
function wp_remote_get( $url, $args ) {
	return t_ac( 'GET', $url, $args );
}
function wp_remote_post( $url, $args ) {
	return t_ac( 'POST', $url, $args );
}
function wp_remote_request( $url, $args ) {
	return t_ac( $args['method'], $url, $args );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}

require dirname( __DIR__, 2 ) . '/includes/adventures-activecampaign.php';

$fails = 0;
$count = 0;
function ok( $cond, $msg ) {
	global $fails, $count;
	++$count;
	if ( ! $cond ) {
		++$fails;
		echo "FAIL: $msg\n";
	}
}
function reset_world( $keep_cache = false ) {
	$cache = array();
	foreach ( array( 'arv_adv_ac_list_id', 'arv_adv_ac_tag_ids' ) as $k ) {
		if ( $keep_cache && isset( $GLOBALS['t_opts'][ $k ] ) ) {
			$cache[ $k ] = $GLOBALS['t_opts'][ $k ];
		}
	}
	$GLOBALS['t_opts'] = array_merge(
		array(
			'arv_adventures_form_id'  => 86051,
			'settings_activecampaign' => array( 'api_url' => T_BASE, 'api_key' => T_KEY, 'css' => 1 ),
		),
		$cache
	);
	$GLOBALS['t_reqs']  = array();
	$GLOBALS['t_sched'] = array();
	$GLOBALS['t_fail']  = array();
	if ( ! $keep_cache ) {
		$GLOBALS['t_ac_lists'] = array();
		$GLOBALS['t_ac_tags']  = array();
	}
}
function submit( $hook, $form, $name, $email, $trip, $news = '' ) {
	$GLOBALS['t_posted'] = array( 'adv-name' => $name, 'adv-email' => $email, 'adv-trip' => $trip, 'adv-news' => $news );
	do_action( $hook, new T_Form( $form ) );
}
function seq() {
	return array_map(
		static function ( $r ) {
			return $r['method'] . ' ' . preg_replace( '/\?.*/', '?', $r['path'] );
		},
		$GLOBALS['t_reqs']
	);
}
function bodies( $path ) {
	$out = array();
	foreach ( $GLOBALS['t_reqs'] as $r ) {
		if ( 'POST' === $r['method'] && $r['path'] === $path ) {
			$out[] = $r['body'];
		}
	}
	return $out;
}

// Hooks are registered on both CF7 actions.
ok( ! empty( $GLOBALS['t_actions']['wpcf7_mail_sent'] ), 'wpcf7_mail_sent hooked' );
ok( ! empty( $GLOBALS['t_actions']['wpcf7_mail_failed'] ), 'wpcf7_mail_failed hooked' );

// Other forms are ignored.
reset_world();
submit( 'wpcf7_mail_sent', 12345, 'Jane Doe', 'jane@example.com', 'Madeira Adventure, October 3-9, 2027' );
ok( 0 === count( $GLOBALS['t_reqs'] ), 'other form id makes no request' );

// First Madeira signup on a fresh account: list and tags are created.
reset_world();
submit( 'wpcf7_mail_sent', 86051, 'Jane Q Doe', 'jane@example.com', 'Madeira Adventure, October 3-9, 2027' );
ok(
	seq() === array( 'POST contact/sync', 'GET lists?', 'POST lists', 'POST contactLists', 'GET tags?', 'POST tags', 'POST contactTags', 'GET tags?', 'POST tags', 'POST contactTags' ),
	'first run request sequence: ' . implode( ', ', seq() )
);
$sync = bodies( 'contact/sync' );
ok( array( 'email' => 'jane@example.com', 'firstName' => 'Jane', 'lastName' => 'Q Doe' ) === $sync[0]['contact'], 'contact/sync body' );
$cl = bodies( 'contactLists' );
ok( 1 === count( $cl ) && 55 === $cl[0]['contactList']['list'] && 777 === $cl[0]['contactList']['contact'] && 1 === $cl[0]['contactList']['status'], 'contactLists list 55, contact 777, status 1' );
$lists = bodies( 'lists' );
ok( 'Aravaipa Adventures Interest' === $lists[0]['list']['name'] && ! empty( $lists[0]['list']['sender_url'] ) && ! empty( $lists[0]['list']['sender_reminder'] ), 'list created with name and sender fields' );
$tags = array_map(
	static function ( $b ) {
		return $b['tag']['tag'] . '/' . $b['tag']['tagType'];
	},
	bodies( 'tags' )
);
ok( array( 'Adventures interest/contact', 'Adventures: Madeira Adventure/contact' ) === $tags, 'tags created: ' . implode( ', ', $tags ) );
ok( 2 === count( bodies( 'contactTags' ) ), 'two contactTags' );
ok( 55 === get_option( 'arv_adv_ac_list_id' ), 'list id cached' );
ok( array( 'Adventures interest' => 100, 'Adventures: Madeira Adventure' => 101 ) === get_option( 'arv_adv_ac_tag_ids' ), 'tag ids cached' );
ok( 0 === count( $GLOBALS['t_sched'] ) && ! get_option( 'arv_adv_ac_log' ), 'success: no retry, no log row' );
ok( 'j***@example.com' === get_option( 'arv_adv_ac_last_ok' )['email'], 'last ok masked' );
foreach ( $GLOBALS['t_reqs'] as $r ) {
	ok( $r['timeout'] && $r['timeout'] <= 5, 'timeout <= 5 on ' . $r['path'] );
	ok( T_KEY === $r['token'], 'Api-Token header on ' . $r['path'] );
}
ok( ! in_array( 'POST contactLists', array_slice( seq(), 4 ), true ), 'no newsletter list without the box' );

// Second run reuses cached ids: no lookups, no creates. Via mail_failed.
reset_world( true );
submit( 'wpcf7_mail_failed', 86051, 'Sam', 'sam@example.com', array( 'Madeira Adventure, October 3-9, 2027' ) );
ok( seq() === array( 'POST contact/sync', 'POST contactLists', 'POST contactTags', 'POST contactTags' ), 'mail_failed pushes, cached ids reused: ' . implode( ', ', seq() ) );
$sync = bodies( 'contact/sync' );
ok( 'Sam' === $sync[0]['contact']['firstName'] && ! array_key_exists( 'lastName', $sync[0]['contact'] ), 'one-word name: no lastName sent (an existing last name is kept)' );
ok( '' === arv_adv_ac_payload( array( 'adv-name' => 'Sam', 'adv-email' => 'sam@example.com' ) )['last'], 'one-word name: empty last name in the payload' );
reset_world( true );
submit( 'wpcf7_mail_sent', 86051, '', 'blank@example.com', 'Any trip, keep me posted' );
$sync = bodies( 'contact/sync' );
ok( array( 'email' => 'blank@example.com' ) === $sync[0]['contact'], 'blank name: only the email is synced' );

// Any trip: only the general tag.
reset_world( true );
submit( 'wpcf7_mail_sent', 86051, 'Ann Lee', 'ann@example.com', 'Any trip, keep me posted' );
$ct = bodies( 'contactTags' );
ok( 1 === count( $ct ) && 100 === $ct[0]['contactTag']['tag'], 'Any trip gets only the general tag' );

// Newsletter box ticked: main list too. HTML entity in trip name decoded.
reset_world( true );
submit( 'wpcf7_mail_sent', 86051, 'Ann Lee', 'ann@example.com', 'Sail & Trail, August 2028', '1' );
$cl = bodies( 'contactLists' );
ok( 2 === count( $cl ) && 55 === $cl[0]['contactList']['list'] && 2 === $cl[1]['contactList']['list'], 'news box adds the Newsletter list (2)' );
ok( isset( $GLOBALS['t_ac_tags']['Adventures: Sail & Trail'] ), 'entity decoded in trip tag' );
reset_world( true );
submit( 'wpcf7_mail_sent', 86051, 'Ann Lee', 'ann@example.com', 'Any trip, keep me posted', array( '0' ) );
ok( 1 === count( bodies( 'contactLists' ) ), 'unticked box (0) adds no newsletter list' );

// Existing list and tag in the account are found, not recreated.
reset_world();
$GLOBALS['t_ac_lists']['Aravaipa Adventures Interest'] = 42;
$GLOBALS['t_ac_tags']['Adventures interest']             = 900;
submit( 'wpcf7_mail_sent', 86051, 'Jo', 'jo@example.com', 'Any trip, keep me posted' );
ok( seq() === array( 'POST contact/sync', 'GET lists?', 'POST contactLists', 'GET tags?', 'POST contactTags' ), 'existing list and tag found: ' . implode( ', ', seq() ) );
ok( 42 === get_option( 'arv_adv_ac_list_id' ), 'found list id cached' );

// A 500 stops the chain, logs one row without the key, one retry.
reset_world( true );
$GLOBALS['t_fail'] = array( 'POST contactLists' => 500 );
submit( 'wpcf7_mail_sent', 86051, 'Jane Doe', 'jane@example.com', 'Madeira Adventure, October 3-9, 2027' );
ok( seq() === array( 'POST contact/sync', 'POST contactLists' ), '500 stops the chain' );
$log = get_option( 'arv_adv_ac_log' );
ok( 1 === count( $log ) && 'list_add' === $log[0]['step'] && 500 === $log[0]['code'], 'one log row for the failure' );
ok( false === strpos( json_encode( $log ), T_KEY ) && false === strpos( json_encode( $log ), 'example.api-us1' ), 'log row has no key or account url' );
ok( false === strpos( json_encode( $log ), 'jane@' ), 'log row masks the email' );
ok( 1 === count( $GLOBALS['t_sched'] ) && 'arv_adv_ac_retry' === $GLOBALS['t_sched'][0][1] && 2 === $GLOBALS['t_sched'][0][2][0]['attempt'], 'exactly one retry scheduled with attempt 2' );
ok( $GLOBALS['t_sched'][0][0] >= time() + 290, 'retry about five minutes out' );

// The retry failing schedules nothing more; succeeding clears nothing.
$retry_args         = $GLOBALS['t_sched'][0][2];
$GLOBALS['t_sched'] = array();
do_action( 'arv_adv_ac_retry', ...$retry_args );
ok( 0 === count( $GLOBALS['t_sched'] ), 'failing retry schedules nothing' );
ok( 2 === count( get_option( 'arv_adv_ac_log' ) ) && false === get_option( 'arv_adv_ac_log' )[1]['retry'], 'failing retry logs a second row, retry false' );
$GLOBALS['t_fail'] = array();
do_action( 'arv_adv_ac_retry', ...$retry_args );
ok( 0 === count( $GLOBALS['t_sched'] ) && 2 === get_option( 'arv_adv_ac_last_ok' )['attempt'], 'retry succeeds once the API is back' );

// Contact sync failing (503) stops at the first request.
reset_world( true );
$GLOBALS['t_fail'] = array( 'POST contact/sync' => 503 );
submit( 'wpcf7_mail_sent', 86051, 'Jane Doe', 'jane@example.com', 'Any trip, keep me posted' );
ok( 1 === count( $GLOBALS['t_reqs'] ) && 'contact' === get_option( 'arv_adv_ac_log' )[0]['step'], 'contact failure stops at the first request' );

// Ring buffer caps at 50.
reset_world( true );
$GLOBALS['t_fail'] = array( 'POST contact/sync' => 500 );
for ( $i = 0; $i < 60; $i++ ) {
	submit( 'wpcf7_mail_sent', 86051, 'N', "n$i@example.com", 'Any trip, keep me posted' );
}
$log = get_option( 'arv_adv_ac_log' );
ok( 50 === count( $log ) && 'n***@example.com' === $log[49]['email'], 'ring buffer keeps the last 50' );

// Missing settings: skip quietly, no request, no retry.
reset_world();
unset( $GLOBALS['t_opts']['settings_activecampaign'] );
submit( 'wpcf7_mail_sent', 86051, 'Jane Doe', 'jane@example.com', 'Any trip, keep me posted' );
ok( 0 === count( $GLOBALS['t_reqs'] ) && 0 === count( $GLOBALS['t_sched'] ), 'missing settings: no request, no retry' );
ok( 'settings' === get_option( 'arv_adv_ac_log' )[0]['step'], 'missing settings logged' );

// Invalid email: nothing.
reset_world();
submit( 'wpcf7_mail_sent', 86051, 'Jane Doe', 'not-an-email', 'Any trip, keep me posted' );
ok( 0 === count( $GLOBALS['t_reqs'] ), 'invalid email makes no request' );

// Trip label fallback (unknown trip): text before the last comma.
ok( array( 'Mystery Trip' ) === arv_adv_ac_trip_names( 'Mystery Trip, May 2029' ), 'unknown label falls back to text before the last comma' );
ok( array( 'Island in the Sky' ) === arv_adv_ac_trip_names( array( 'Island in the Sky, June 6-13, 2027', 'Island in the Sky, June 2028' ) ), 'repeated trip name gives one tag' );

// Source never logs the key.
$src = file_get_contents( dirname( __DIR__, 2 ) . '/includes/adventures-activecampaign.php' );
ok( false === strpos( $src, 'error_log' ), 'no error_log in the module' );

echo $fails ? "$fails of $count FAILED\n" : "ALL PASS ($count)\n";
exit( $fails ? 1 : 0 );
