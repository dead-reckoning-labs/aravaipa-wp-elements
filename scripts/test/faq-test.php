<?php
/**
 * FAQ module (job aclb03), without WordPress: item and section extraction,
 * slugs, the jump nav, the Contact top block, the topic map and answers.
 *
 * Run: php scripts/test/faq-test.php
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', 'https://example.test/wp-content/plugins/aravaipa-elements/' );
define( 'ARV_ELEMENTS_VERSION', '0.0.0' );

$GLOBALS['t_shortcodes'] = array();
$GLOBALS['t_post']       = null;

function add_shortcode( $tag, $cb ) { $GLOBALS['t_shortcodes'][ $tag ] = $cb; }
function add_action() {}
function apply_filters( $hook, $value ) { return $value; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function get_post() { return $GLOBALS['t_post']; }
function home_url( $p = '' ) { return 'https://www.aravaiparunning.com' . $p; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_kses( $html, $allowed ) {
	$tags = '<' . implode( '><', array_keys( $allowed ) ) . '>';
	$out  = strip_tags( $html, $tags );
	// Drop every attribute except href, like wp_kses with the list above.
	return preg_replace_callback( '~<([a-z]+)\b([^>]*)>~i', function ( $m ) {
		$href = preg_match( '~\bhref="([^"]*)"~', $m[2], $h ) ? ' href="' . $h[1] . '"' : '';
		return '<' . $m[1] . $href . '>';
	}, $out );
}

require dirname( __DIR__, 2 ) . '/includes/faq.php';

$fails = 0;
function ok( $cond, $msg ) {
	global $fails;
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $msg . "\n";
	if ( ! $cond ) {
		$fails++;
	}
}

$content = <<<'HTML'
[arv_faq_search]

[arv_faq_nav]

<div class="arv-faq-section" id="registration">
<h2>Registration</h2>

<details class="arv-faq-q" id="change-distance" data-topics="registration" data-keywords="switch">
<summary>How do I change my race distance?</summary>
<div class="arv-faq-a">
<p>Change it <strong>yourself</strong>. <a href="/refund-transfer-policy/" onclick="x()">Policy</a></p>
</div>
</details>

<details class="arv-faq-q" id="race-credits" data-topics="registration">
<summary>How do I use my race credits?</summary>
<div class="arv-faq-a">
<p>At checkout.</p><script>alert(1)</script>
</div>
</details>
</div>

<div class="arv-faq-section" id="race-day">
<h2>Race Week and Race Day</h2>

<details class="arv-faq-q" id="parking" data-topics="raceday">
<summary>Is there a parking fee?</summary>
<div class="arv-faq-a"><p>Sometimes.</p></div>
</details>

<details class="arv-faq-q" id="results" data-topics="raceday">
<summary>Where can I find race results?</summary>
<div class="arv-faq-a"><p>On the Results page.</p></div>
</details>

<details class="arv-faq-q" id="race-weekend-contact" data-topics="raceday other">
<summary>How do I reach you during race weekend?</summary>
<div class="arv-faq-a"><p>Text us.</p></div>
</details>

<details class="arv-faq-q" id="cupless" data-topics="raceday">
<summary>Are your races cupless?</summary>
<div class="arv-faq-a"><p>Mostly.</p></div>
</details>
</div>
HTML;

// Extraction.
$items = arv_faq_extract_items( $content );
ok( 6 === count( $items ), 'six questions extracted' );
ok( 'change-distance' === $items[0]['id'], 'slug id read from the details' );
ok( array( 'registration' ) === $items[0]['topics'], 'topics read' );
ok( array( 'raceday', 'other' ) === $items[4]['topics'], 'several topics split on spaces' );
ok( 'Registration' === $items[0]['section'] && 'Race Week and Race Day' === $items[2]['section'], 'section names follow the h2' );
ok( 6 === count( arv_faq_extract_pairs( $content ) ), 'schema pairs unchanged by the new attributes' );

$ids = array_column( $items, 'id' );
ok( count( $ids ) === count( array_unique( $ids ) ), 'ids unique' );
foreach ( $ids as $id ) {
	ok( 1 === preg_match( '/^[a-z0-9-]+$/', $id ), "slug $id is lowercase" );
}

// Sections and nav.
$sections = arv_faq_extract_sections( $content );
ok( 2 === count( $sections ) && 'race-day' === $sections[1]['id'], 'sections with ids' );
$GLOBALS['t_post'] = (object) array( 'post_content' => $content );
$nav = $GLOBALS['t_shortcodes']['arv_faq_nav']();
ok( false !== strpos( $nav, '<a href="#registration">Registration</a>' ), 'nav links to sections' );
ok( false !== strpos( $nav, 'aria-label="FAQ sections"' ), 'nav labelled' );
$GLOBALS['t_post'] = (object) array( 'post_content' => '<p>no faq</p>' );
ok( '' === $GLOBALS['t_shortcodes']['arv_faq_nav'](), 'no nav without sections' );

// Shortcodes registered.
foreach ( array( 'arv_faq_search', 'arv_faq_nav', 'arv_faq_top' ) as $sc ) {
	ok( isset( $GLOBALS['t_shortcodes'][ $sc ] ), "shortcode $sc registered" );
}

// Safe HTML.
$safe = arv_faq_safe_html( $items[1]['a'] . $items[0]['a'] );
ok( false === strpos( $safe, '<script' ), 'script stripped from answers used off the FAQ page' );
ok( false === strpos( $safe, 'onclick' ), 'event attributes stripped' );
ok( false !== strpos( $safe, '<a href="/refund-transfer-policy/">' ), 'links kept' );

// Top block.
$top = arv_faq_top_html( $items, array( 'race-credits', 'missing', 'change-distance', 'results', 'parking', 'cupless', 'race-weekend-contact' ), 'https://www.aravaiparunning.com/faq/' );
ok( 6 === substr_count( $top, '<details class="arv-faq-q"' ), 'top block holds at most six questions, missing ids skipped' );
ok( strpos( $top, 'How do I use my race credits?' ) < strpos( $top, 'How do I change my race distance?' ), 'top block keeps the given order' );
ok( false !== strpos( $top, 'action="https://www.aravaiparunning.com/faq/"' ) && false !== strpos( $top, 'name="q"' ), 'search goes to the FAQ with ?q=' );
ok( false !== strpos( $top, 'id="top-race-credits"' ), 'top ids do not clash with FAQ ids' );
ok( false !== strpos( $top, 'class="arv-faq-topic-data"' ), 'topic data printed with the block' );
ok( false === strpos( $top, '<script>alert' ), 'no raw script from answers in the block' );
ok( 0 === preg_match( '/\x{2014}|\x26mdash;/u', $top . file_get_contents( dirname( __DIR__, 2 ) . '/includes/faq.php' ) ), 'no em dashes' );

// Topic map and answers.
$slugs = arv_faq_topic_slugs();
ok( 8 === count( $slugs ), 'every contact form topic is mapped' );
ok( 'registration' === $slugs['Registration, refunds or transfers'], 'registration label mapped' );
$answers = arv_faq_topic_answers( $items );
ok( array( 'change-distance', 'race-credits' ) === array_column( $answers['registration'], 'id' ), 'registration answers in preferred order, missing ids skipped' );
ok( array( 'results', 'race-weekend-contact', 'parking' ) === array_column( $answers['raceday'], 'id' ), 'raceday topped up from tagged questions to three' );
ok( ! isset( $answers['sponsorship'] ), 'topic with no questions on the page is left out' );
foreach ( $answers as $slug => $list ) {
	ok( count( $list ) <= 3, "$slug has at most three answers" );
}

preg_match( '~<script type="application/json" class="arv-faq-topic-data">(.*?)</script>~s', $top, $m );
$data = json_decode( $m[1], true );
ok( is_array( $data ) && isset( $data['topics'], $data['answers'] ), 'topic data is valid JSON' );
ok( 'https://www.aravaiparunning.com/faq/#results' === $data['answers']['raceday'][0]['url'], 'answers link to the FAQ deep link' );

echo $fails ? "\n$fails failed\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
