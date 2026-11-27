<?php
/**
 * Careers page ([arv_careers_page], includes/careers-page.php).
 *
 *   php scripts/test/careers-page-test.php
 *
 * 1. Empty BambooHR list: the warm no-openings block, the contact path, the
 *    job board link, Volunteer and Racing Team, and no JobPosting schema.
 * 2. One live opening: title, department, place and type, an Apply button
 *    to its BambooHR posting, and exactly one JobPosting block.
 * 3. BambooHR down: the last good list is used, not an empty page.
 * 4. The list is cached for 1 hour.
 * 5. No stray "Join the Aravaipa Running Team Today!" line, no em dashes,
 *    every data file claim free of pay or benefit words.
 */
require __DIR__ . '/fixtures/region-hub/bootstrap.php';

define( 'HOUR_IN_SECONDS', 3600 );
function remove_query_arg( $key, $url = null ) {
	$parts = explode( '?', (string) $url, 2 );
	return $parts[0];
}
function add_query_arg( $k, $v = null, $url = null ) {
	return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( (string) $v );
}
function arv_rt_apply_data( $file ) {
	return array();
}
function esc_html__( $s, $d = '' ) { return esc_html( $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_trim_words( $t, $n = 55, $more = '…' ) {
	$w = preg_split( '/\s+/', trim( (string) $t ) );
	return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . $more : implode( ' ', $w );
}
function wp_kses( $s, $a ) { return strip_tags( (string) $s ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {}

$GLOBALS['T'] = array();
$GLOBALS['TTL'] = array();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['T'] ) ? $GLOBALS['T'][ $k ] : false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['T'][ $k ] = $v; $GLOBALS['TTL'][ $k ] = $ttl; return true; }
function delete_transient( $k ) { unset( $GLOBALS['T'][ $k ] ); }

// The fake BambooHR. 'down' answers every call with a WP_Error.
$GLOBALS['BAMBOO'] = array( 'mode' => 'empty' );
$GLOBALS['CALLS']  = 0;
function wp_remote_get( $url, $args = array() ) {
	++$GLOBALS['CALLS'];
	$mode = $GLOBALS['BAMBOO']['mode'];
	if ( 'down' === $mode ) {
		return new WP_Error();
	}
	if ( false !== strpos( $url, '/careers/list' ) ) {
		$result = 'one' === $mode ? array( arv_test_job() ) : array();
		return array( 'code' => 200, 'body' => json_encode( array( 'meta' => array( 'totalCount' => count( $result ) ), 'result' => $result ) ) );
	}
	if ( preg_match( '~/careers/(\d+)/detail~', $url ) ) {
		return array(
			'code' => 200,
			'body' => json_encode(
				array(
					'result' => array(
						'jobOpening' => array(
							'description'  => '<p>Race Crew | Phoenix, AZ | Seasonal</p><p><strong>About the Role</strong></p><p>Build and run aid stations, courses and finish lines at our trail and ultra races across Arizona all season long.</p>',
							'compensation' => '$20/hr',
							'datePosted'   => '2026-11-20',
						),
					),
				)
			),
		);
	}
	return array( 'code' => 404, 'body' => '' );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function arv_test_job() {
	return array(
		'id'                    => '71',
		'jobOpeningName'        => 'Seasonal Race Crew',
		'departmentLabel'       => 'Events',
		'employmentStatusLabel' => 'Seasonal',
		'location'              => array( 'city' => 'Phoenix', 'state' => 'Arizona' ),
		'isRemote'              => null,
	);
}

require_once ARV_ELEMENTS_PATH . 'includes/region-hub.php';
require_once ARV_ELEMENTS_PATH . 'includes/careers-store.php';
require_once ARV_ELEMENTS_PATH . 'includes/careers-page.php';

$pass = 0;
$fail = 0;
function t( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "  ok   $name\n";
	} else {
		++$fail;
		echo "  FAIL $name\n";
	}
}
function reset_state( $mode ) {
	$GLOBALS['T']               = array();
	$GLOBALS['BAMBOO']['mode']  = $mode;
	$GLOBALS['CALLS']           = 0;
}

$data = arv_careers_page_data();
t( 'data file decodes', is_array( $data ) );
t( 'shortcode registered', isset( $GLOBALS['ARV_SHORTCODES']['arv_careers_page'] ) );

echo "empty list:\n";
reset_state( 'empty' );
$GLOBALS['ARV_OPTIONS'] = array();
$html = call_user_func( $GLOBALS['ARV_SHORTCODES']['arv_careers_page'], array() );
t( 'renders', '' !== $html && 0 === strpos( $html, '<div class="arv-hub arv-careers-page">' ) );
t( 'hero h1 Careers', false !== strpos( $html, '<h1 class="arv-hub__h1">Careers</h1>' ) );
t( 'hero photo with alt', false !== strpos( $html, 'careers-hero-javelina-course-crew.jpg' ) && false !== strpos( $html, 'alt="Aravaipa course crew member' ) );
t( 'empty state block', false !== strpos( $html, 'arv-careers-page__empty' ) && false !== strpos( $html, 'Nothing open right now' ) );
t( 'openings anchor', false !== strpos( $html, 'id="openings"' ) );
t( 'contact path', false !== strpos( $html, 'href="https://www.aravaiparunning.com/contact-us/"' ) );
t( 'job board link inline in the empty copy', false !== strpos( $html, 'shows up here and on <a href="https://aravaipa.bamboohr.com/careers" target="_blank" rel="noopener">our job board</a> the same day.' ) );
t( 'no raw placeholder', false === strpos( $html, '{board}' ) );
t( 'volunteer link', false !== strpos( $html, 'href="https://www.aravaiparunning.com/volunteer/"' ) );
t( 'racing team link', false !== strpos( $html, 'href="https://www.aravaiparunning.com/racing-team/"' ) );
t( 'no JobPosting schema when empty', false === strpos( $html, 'JobPosting' ) && false === strpos( $html, 'application/ld+json' ) );
t( 'no Apply button when empty', false === strpos( $html, 'arv-careers-page__job-apply' ) );
t( 'no stray Join line', false === stripos( $html, 'Join the Aravaipa Running Team Today' ) );
t( 'no em dash in output', false === strpos( $html, "\u{2014}" ) && false === strpos( $html, '&mdash;' ) );
t( 'life sections render', 3 === substr_count( $html, 'arv-careers-page__life' . '"' ) + substr_count( $html, 'arv-careers-page__life arv-careers-page__life--flip' ) );
t( 'stats strip', false !== strpos( $html, 'arv-hub__stats' ) && false !== strpos( $html, '>2009<' ) );
t( 'gallery five frames, one big', 5 === substr_count( $html, 'class="arv-hub__shot' ) && 1 === substr_count( $html, 'arv-hub__shot--big' ) );
t( 'every img has alt text', ! preg_match( '~<img(?![^>]*alt="[^"]+")[^>]*>~', $html ) );
t( 'styles enqueued', in_array( 'css:aravaipa-careers', $GLOBALS['ENQUEUED'], true ) && in_array( 'css:aravaipa-region-hub', $GLOBALS['ENQUEUED'], true ) );
t( 'list cached 1 hour', HOUR_IN_SECONDS === $GLOBALS['TTL']['arv_careers_list'] );
t( 'empty answer stored as last good', array() === get_option( ARV_CAREERS_LAST_GOOD_OPTION, 'unset' ) );

echo "one live opening:\n";
reset_state( 'one' );
$html = arv_careers_page_shortcode();
t( 'job row', 1 === substr_count( $html, '<li class="arv-careers-page__job">' ) );
t( 'title', false !== strpos( $html, '<h3 class="arv-careers-page__job-title">Seasonal Race Crew</h3>' ) );
t( 'department, place, type', false !== strpos( $html, '<li>Events</li><li>Phoenix, Arizona</li><li>Seasonal</li>' ) );
t( 'apply to the posting', false !== strpos( $html, 'href="https://aravaipa.bamboohr.com/careers/71"' ) && false !== strpos( $html, '>Apply<span class="screen-reader-text">: Seasonal Race Crew</span>' ) );
preg_match( '~<p class="arv-careers-page__job-summary">(.*?)</p>~', $html, $sm );
t( 'summary skips the pipe header and label', isset( $sm[1] ) && 0 === strpos( $sm[1], 'Build and run aid stations' ) && false === strpos( $sm[1], '|' ) );
t( 'pay from BambooHR only', false !== strpos( $html, '$20/hr' ) );
t( 'exactly one JobPosting', 1 === substr_count( $html, '"@type":"JobPosting"' ) );
preg_match( '~<script type="application/ld\+json">(.*?)</script>~', $html, $m );
$ld = isset( $m[1] ) ? json_decode( $m[1], true ) : null;
t( 'schema decodes with title and location', is_array( $ld ) && 'Seasonal Race Crew' === $ld['title'] && 'Phoenix' === $ld['jobLocation']['address']['addressLocality'] );
t( 'schema description keeps word breaks', is_array( $ld ) && false !== strpos( $ld['description'], 'About the Role Build' ) );
t( 'schema employment type', is_array( $ld ) && 'TEMPORARY' === $ld['employmentType'] );
t( 'schema hourly pay', is_array( $ld ) && 'HOUR' === $ld['baseSalary']['value']['unitText'] && 20.0 === (float) $ld['baseSalary']['value']['value'] );
t( 'no empty state when populated', false === strpos( $html, 'arv-careers-page__empty' ) );
t( 'board link under the list', false !== strpos( $html, '<p class="arv-careers-page__board">Every opening is also on <a href="https://aravaipa.bamboohr.com/careers"' ) && false === strpos( $html, '{board}' ) );
t( 'last good now holds the opening', 1 === count( get_option( ARV_CAREERS_LAST_GOOD_OPTION, array() ) ) );

echo "cache:\n";
$GLOBALS['CALLS'] = 0;
arv_careers_page_shortcode();
t( 'second render within the hour does not call BambooHR', 0 === $GLOBALS['CALLS'] );

echo "BambooHR down:\n";
reset_state( 'down' );
$html = arv_careers_page_shortcode();
t( 'falls back to the last good list', false !== strpos( $html, 'Seasonal Race Crew' ) );
t( 'not cached on failure', false === get_transient( 'arv_careers_list' ) );
$GLOBALS['ARV_OPTIONS'] = array();
reset_state( 'down' );
$html = arv_careers_page_shortcode();
t( 'no last good: empty state, page still renders', false !== strpos( $html, 'Nothing open right now' ) && false === strpos( $html, 'JobPosting' ) );

echo "data file:\n";
$raw = file_get_contents( ARV_ELEMENTS_PATH . 'includes/data/careers.json' );
t( 'no em dash', false === strpos( $raw, "\u{2014}" ) );
$copy = strtolower( preg_replace( '~"_about":\s*"[^"]*",~', '', $raw ) );
t( 'no benefit or pay claims', ! preg_match( '~\b(benefit|benefits|401k|health insurance|pto|salary|perks?|unlimited)\b~', $copy ) );
foreach ( array( 'includes/careers-page.php', 'assets/aravaipa-careers.css', 'scripts/test/careers-page-test.php' ) as $f ) {
	t( "no em dash in $f", false === strpos( file_get_contents( ARV_ELEMENTS_PATH . $f ), "\u{2014}" ) );
}
$css = file_get_contents( ARV_ELEMENTS_PATH . 'assets/aravaipa-careers.css' );
t( 'css: no edge stripes, shadows or rounding', ! preg_match( '~border-(left|top|right|bottom)\s*:|box-shadow\s*:\s*+(?!none)|border-radius\s*:\s*+(?!0)~', $css ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
