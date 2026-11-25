<?php
/**
 * Dev harness for includes/series-pass.php. Not shipped (build.sh copies
 * only required includes). Run: php arv-series-pass-test.php
 *
 * Stubs the handful of WordPress functions the file touches, then walks the
 * real series-passes.json across every price step and close date.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_PATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', 'https://example.test/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_VERSION', 'test' );

$GLOBALS['SHORTCODES'] = array();
$GLOBALS['ENQUEUED']   = array();
function add_action( $t, $f, $p = 10, $n = 1 ) {}
function add_shortcode( $t, $f ) { $GLOBALS['SHORTCODES'][ $t ] = $f; }
function shortcode_atts( $pairs, $atts, $sc = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $k => $v ) {
		$out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v;
	}
	return $out;
}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_enqueue_style( $h, $src = '', $deps = array(), $v = false ) { $GLOBALS['ENQUEUED'][] = $h; }

require_once __DIR__ . '/includes/series-pass.php';

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
function at( $local, $tz = 'America/Phoenix' ) {
	return ( new DateTimeImmutable( $local, new DateTimeZone( $tz ) ) )->getTimestamp();
}

$all = arv_series_pass_all();
t( 'three series load from series-passes.json', isset( $all['sonoran-desert-cup'], $all['insomniac'], $all['white-mountain'] ) );
t( '_note key is not a series', ! isset( $all['_note'] ) );

// Sonoran Desert Cup.
$s = $all['sonoran-desert-cup'];
$v = arv_series_pass_view( $s, at( '2026-11-25 09:00' ) );
t( 'sonoran: deal line with $360', 'Pick any 3 Cup races plus the Dam Good Run 26K final, $360.' === $v['deal'] );
t( 'sonoran: pass button to RaceGoat', 'https://racegoat.com/events/sonoran-desert-cup-2026-27' === $v['pass_url'] );
t( 'sonoran: cup button to /sonoran-desert-cup/', 'https://www.aravaiparunning.com/sonoran-desert-cup/' === $v['page_url'] );
t( 'sonoran: no Coldwater anywhere', false === stripos( json_encode( $s ), 'coldwater' ) );
$v = arv_series_pass_view( $s, at( '2027-01-04 23:58' ) );
t( 'sonoran: still open one minute before close', ! $v['closed'] && null !== $v['pass_url'] );
$v = arv_series_pass_view( $s, at( '2027-01-05 00:01' ) );
t( 'sonoran: closed after Jan 4 11:59 PM', $v['closed'] );
t( 'sonoran: closed hides pass button', null === $v['pass_url'] );
t( 'sonoran: closed hides deal and price', null === $v['deal'] && null === $v['price'] );
t( 'sonoran: closed keeps About the cup', 'About the cup' === $v['page_label'] && '' !== $v['page_url'] );
$h = arv_series_pass_html( 'sonoran-desert-cup', $v );
t( 'sonoran: closed markup has one button', 1 === substr_count( $h, 'aravaipa-series-pass__btn ' ) && false === strpos( $h, 'racegoat.com' ) );

// Insomniac: five tiers, lowest current price, steps by date.
$s = $all['insomniac'];
$v = arv_series_pass_view( $s, at( '2026-11-25 09:00' ) );
t( 'insomniac: from $216 before Dec 15', 'All six 2027 night runs on one registration, five tiers from $216.' === $v['deal'] );
$v = arv_series_pass_view( $s, at( '2026-12-15 08:00' ) );
t( 'insomniac: from $252 from Dec 15 (Jitters step)', false !== strpos( (string) $v['deal'], 'from $252' ) );
$v = arv_series_pass_view( $s, at( '2027-03-02 08:00' ) );
t( 'insomniac: from $252 still lowest in March', false !== strpos( (string) $v['deal'], 'from $252' ) );
t( 'insomniac: About the series label', 'About the series' === $v['page_label'] );
$v = arv_series_pass_view( $s, at( '2027-04-20 00:30' ) );
t( 'insomniac: closed after Apr 19', $v['closed'] && null === $v['pass_url'] && null === $v['deal'] );

// White Mountain: dated single price, Eastern time.
$s  = $all['white-mountain'];
$et = 'America/New_York';
$v  = arv_series_pass_view( $s, at( '2026-11-25 09:00', $et ) );
t( 'wme: $432 through Dec 14', 'All four White Mountain races on one registration, $432 through Dec 14.' === $v['deal'] );
$v = arv_series_pass_view( $s, at( '2026-12-14 23:30', $et ) );
t( 'wme: Dec 14 late evening ET still $432', false !== strpos( (string) $v['deal'], '$432' ) );
$v = arv_series_pass_view( $s, at( '2026-12-15 00:30', $et ) );
t( 'wme: Dec 15 ET is $464 through Mar 31', false !== strpos( (string) $v['deal'], '$464 through Mar 31' ) );
$v = arv_series_pass_view( $s, at( '2027-04-01 09:00', $et ) );
t( 'wme: $495 from Apr 1 with no "through"', ', $495.' === substr( (string) $v['deal'], -7 ) );
t( 'wme: no close date means never closed', ! $v['closed'] && null !== $v['pass_url'] );
t( 'wme: cup page link', 'https://www.aravaiparunning.com/white-mountain-endurance/wme-cup/' === $v['page_url'] );

// No price valid today: deal line without a price.
$np = array( 'name' => 'Test', 'deal' => 'Four races', 'prices' => array( array( 'cents' => 100, 'from' => '2030-01-01', 'until' => null ) ), 'pass_url' => 'https://racegoat.com/events/x', 'page_url' => 'https://www.aravaiparunning.com/x/' );
$v  = arv_series_pass_view( $np, at( '2026-11-25 09:00' ) );
t( 'no current price: deal shows without price', 'Four races.' === $v['deal'] && null === $v['price'] );

// Money format.
t( 'money: whole dollars', '$1,500' === arv_series_pass_money( 150000 ) );
t( 'money: cents kept', '$382.24' === arv_series_pass_money( 38224 ) );

// Markup and shortcode.
$GLOBALS['ENQUEUED'] = array();
$out = call_user_func( $GLOBALS['SHORTCODES']['aravaipa_series_pass'], array( 'series' => 'sonoran-desert-cup' ) );
t( 'shortcode registered and renders', false !== strpos( $out, 'aravaipa-series-pass--sonoran-desert-cup' ) );
t( 'shortcode enqueues the stylesheet', in_array( 'aravaipa-series-pass', $GLOBALS['ENQUEUED'], true ) );
t( 'unknown series renders only a comment', 0 === strpos( call_user_func( $GLOBALS['SHORTCODES']['aravaipa_series_pass'], array( 'series' => 'nope' ) ), '<!--' ) );
t( 'empty atts render only a comment', 0 === strpos( call_user_func( $GLOBALS['SHORTCODES']['aravaipa_series_pass'], '' ), '<!--' ) );
foreach ( $all as $k => $series ) {
	$h = arv_series_pass_html( $k, arv_series_pass_view( $series, at( '2026-11-25 09:00' ) ) );
	t( "$k: markup has no em dash", false === strpos( $h, "\u{2014}" ) );
	t( "$k: markup has no arv class or url", ! preg_match( '/class="[^"]*\barv-|[?&]arv/', $h ) );
	t( "$k: two buttons while open", 2 === substr_count( $h, 'aravaipa-series-pass__btn ' ) );
	t( "$k: escapes markup", false === strpos( arv_series_pass_html( $k, array_merge( arv_series_pass_view( $series, 0 ), array( 'name' => '<b>x' ) ) ), '<b>x' ) );
}
t( 'data file has no em dash', false === strpos( file_get_contents( __DIR__ . '/includes/data/series-passes.json' ), "\u{2014}" ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
