<?php
/**
 * Contact Us form (includes/contact-form.php), without WordPress or the
 * network: WordPress and CF7 are stubbed.
 *
 * Covers: option off leaves the stored form untouched, option on (or the
 * filter) swaps in the v2 template with the stored category pipes; the event
 * is required on the server for the four topics only and only on v2; topic
 * fields of another topic are stripped so they never block; the staff
 * subject tag and the ARV-YYMMDD-XXXX reference; recipient unchanged; the
 * auto-reply is never sent with the option off and goes only to the staff
 * test address in test mode; the race list is current and upcoming only,
 * deduped, with rides and the General choice last; Turnstile attributes
 * only on form 84199; the help slot is filled with the links.
 *
 * Run: php scripts/test/contact-form-test.php
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARV_ELEMENTS_URL', 'https://example.test/wp-content/plugins/aravaipa-elements/' );

$GLOBALS['t_opts']      = array();
$GLOBALS['t_over']      = array();
$GLOBALS['t_trans']     = array();
$GLOBALS['t_current']   = null;
$GLOBALS['t_enqueued']  = array();

function add_filter( $h, $fn, $p = 10, $n = 1 ) {
	$GLOBALS['t_filters'][ $h ][] = $fn;
}
function add_action( $h, $fn, $p = 10, $n = 1 ) {
	add_filter( $h, $fn, $p, $n );
}
function apply_filters( $h, $v, ...$a ) {
	if ( array_key_exists( $h, $GLOBALS['t_over'] ) ) {
		$o = $GLOBALS['t_over'][ $h ];
		return is_callable( $o ) ? $o( $v ) : $o;
	}
	return $v;
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['t_opts'] ) ? $GLOBALS['t_opts'][ $k ] : $d;
}
function get_transient( $k ) {
	return isset( $GLOBALS['t_trans'][ $k ] ) ? $GLOBALS['t_trans'][ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['t_trans'][ $k ] = $v;
	return true;
}
function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_url( $s ) {
	return (string) $s;
}
function wp_unslash( $v ) {
	return $v;
}
function wp_json_encode( $v, $f = 0 ) {
	return json_encode( $v, $f );
}
function wp_enqueue_script( $h, ...$a ) {
	$GLOBALS['t_enqueued'][] = $h;
}
function wpcf7_superglobal_post( $k, $d = '' ) {
	return isset( $_POST[ $k ] ) ? $_POST[ $k ] : $d;
}
function arv_race_store_get() {
	return array(
		array( 'name' => 'Javelina Jundred Presented by: HOKA', 'iso' => '2026-10-31', 'end' => '2026-11-01' ),
		array( 'name' => 'Merry Vertmas', 'iso' => '2026-12-01', 'end' => '2026-12-25' ),
		array( 'name' => 'Fat Ox', 'iso' => '2026-11-20', 'end' => '2026-11-22' ),
		array( 'name' => 'San Tan Scramble', 'iso' => '2027-01-09', 'end' => '' ),
		array( 'name' => 'Javelina Jundred Presented by: HOKA', 'iso' => '2027-10-30', 'end' => '2027-10-31' ),
		array( 'name' => 'Prickly Pedal Runs', 'iso' => '2027-01-31', 'end' => '' ),
		array( 'name' => 'Fat Ox', 'iso' => '2027-11-19', 'end' => '2027-11-21' ),
	);
}
class WPCF7_ContactForm {
	public $i;
	public function __construct( $i ) {
		$this->i = $i;
	}
	public function id() {
		return $this->i;
	}
	public static function get_current() {
		return $GLOBALS['t_current'];
	}
}
class T_Mail {
	public $n;
	public function __construct( $n ) {
		$this->n = $n;
	}
	public function name() {
		return $this->n;
	}
}
class T_Validation {
	public $invalid = array();
	public function invalidate( $tag, $msg ) {
		$this->invalid[ is_object( $tag ) ? $tag->name : $tag['name'] ] = $msg;
	}
}

require dirname( __DIR__, 2 ) . '/includes/contact-form-log.php';
require dirname( __DIR__, 2 ) . '/includes/contact-form.php';

$fails = 0;
$count = 0;
function t( $ok, $what ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo "FAIL: $what\n";
	}
}
function reset_state() {
	$GLOBALS['t_opts']    = array();
	$GLOBALS['t_over']    = array( 'arv_contact_now' => strtotime( '2026-11-27 16:00:00 UTC' ) );
	$GLOBALS['t_trans']   = array();
	$GLOBALS['t_current'] = new WPCF7_ContactForm( 84199 );
	$_POST                = array( '_wpcf7' => '84199' );
	$_FILES               = array();
	arv_contact_ref( true );
}

$stored = "<p class=\"arv-cf-row\">\n  <label>What is this about? (required)<br />\n  [select* category first_as_label\n    \"Choose a topic|info@aravaiparunning.com\"\n    \"Registration, refunds or transfers|info@aravaiparunning.com\"\n    \"Lost and found|info@aravaiparunning.com\"\n    \"Sponsorship and partnerships|jamil@aravaiparunning.com\"\n    \"Something else|info@aravaiparunning.com\"]</label>\n</p>\n\n<p class=\"arv-cf-row\"><label>Event or race name<br />[text event]</label></p>\n[turnstile]";
$ours   = new WPCF7_ContactForm( 84199 );
$other  = new WPCF7_ContactForm( 87176 );

// Option off: stored form untouched.
reset_state();
t( arv_contact_form_prop( $stored, $ours ) === $stored, 'option off leaves the stored form untouched' );
t( false === arv_contact_v2_on(), 'v2 off by default' );

// Option on: v2 template, pipes from the stored form.
$GLOBALS['t_opts']['arv_contact_v2'] = '1';
$v2 = arv_contact_form_prop( $stored, $ours );
t( $v2 !== $stored && false !== strpos( $v2, 'arv-cf2' ), 'option on swaps in the v2 template' );
t( false !== strpos( $v2, 'Sponsorship and partnerships|jamil@aravaiparunning.com' ), 'v2 keeps the stored routing pipes' );
t( strpos( $v2, '[select* category' ) < strpos( $v2, '[text event' ) && strpos( $v2, '[text event' ) < strpos( $v2, '[text* your-name' ), 'order: category, event, name' );
t( false !== strpos( $v2, '[email reg-email]' ) && false !== strpos( $v2, '[text lf-item]' ) && false !== strpos( $v2, '[text lf-where]' ), 'topic fields present' );
t( (bool) preg_match( '/\[file lf-photo limit:8mb filetypes:jpg\|jpeg\|png\|heic\|webp\]/', $v2 ), 'photo: images only, 8 MB' );
t( false === strpos( $v2, '[text* event' ) && false === strpos( $v2, '[email* reg-email' ) && false === strpos( $v2, '[file* ' ), 'event and topic fields are not CF7-required' );
t( false !== strpos( $v2, '[turnstile]' ), 'v2 keeps Turnstile' );
t( arv_contact_form_prop( $stored, $other ) === $stored, 'other forms untouched with option on' );

// Filter forces v2 for one request.
reset_state();
$GLOBALS['t_over']['arv_contact_v2'] = true;
t( arv_contact_form_prop( $stored, $ours ) !== $stored, 'filter forces v2' );

// Server validation.
$tags = array( (object) array( 'name' => 'event' ), (object) array( 'name' => 'your-name' ) );
foreach ( array(
	array( 'Registration, refunds or transfers', '', true ),
	array( 'Race day, results or timing', '', true ),
	array( 'Lost and found', '', true ),
	array( 'Volunteering', '', true ),
	array( 'Volunteering', '   ', true ),
	array( 'Media and press', '', false ),
	array( 'Sponsorship and partnerships', '', false ),
	array( 'Something else', '', false ),
	array( 'Registration, refunds or transfers', 'Javelina Jundred', false ),
	array( 'Lost and found', 'General, not about a race', false ),
) as $c ) {
	reset_state();
	$GLOBALS['t_opts']['arv_contact_v2'] = '1';
	$_POST['category']                   = $c[0];
	$_POST['event']                      = $c[1];
	$r                                   = arv_contact_validate( new T_Validation(), $tags );
	t( isset( $r->invalid['event'] ) === $c[2], 'event required=' . ( $c[2] ? 'yes' : 'no' ) . " for {$c[0]} / '{$c[1]}'" );
}
reset_state();
$_POST['category'] = 'Lost and found';
$r                 = arv_contact_validate( new T_Validation(), $tags );
t( ! $r->invalid, 'no event rule while v2 is off' );
reset_state();
$GLOBALS['t_opts']['arv_contact_v2'] = '1';
$GLOBALS['t_current']                = $other;
$_POST['category']                   = 'Lost and found';
$r                                   = arv_contact_validate( new T_Validation(), $tags );
t( ! $r->invalid, 'no event rule on other forms' );

// Hidden topic fields never block: stripped when another topic is picked.
reset_state();
$_POST['category']  = 'Something else';
$_POST['reg-email'] = 'not an email';
$_POST['lf-item']   = 'x';
$_FILES['lf-photo'] = array( 'name' => 'a.pdf' );
$d                  = arv_contact_strip_offtopic();
t( ! isset( $_POST['reg-email'] ) && ! isset( $_POST['lf-item'] ) && ! isset( $_FILES['lf-photo'] ), 'off-topic fields stripped' );
t( in_array( 'reg-email', $d, true ) && in_array( 'lf-photo', $d, true ), 'strip reports what it dropped' );
reset_state();
$_POST['category']  = 'Lost and found';
$_POST['lf-item']   = 'jacket';
$_POST['reg-email'] = 'x';
arv_contact_strip_offtopic();
t( isset( $_POST['lf-item'] ) && ! isset( $_POST['reg-email'] ), 'on-topic fields kept' );
t( arv_contact_is_our_submit( '/contact-form-7/v1/contact-forms/84199/feedback' ) && ! arv_contact_is_our_submit( '/contact-form-7/v1/contact-forms/87176/feedback' ), 'REST route match is ours only' );

// Reference and subject.
reset_state();
$ref = arv_contact_ref();
t( (bool) preg_match( '/^ARV-261127-[A-HJ-NP-Z2-9]{4}$/', $ref ), "reference format ($ref)" );
t( arv_contact_ref() === $ref, 'one reference per request' );
reset_state();
$GLOBALS['t_over']['arv_contact_now'] = strtotime( '2026-11-28 03:00:00 UTC' );
t( 0 === strpos( arv_contact_ref(), 'ARV-261127-' ), 'reference date is Phoenix time' );

reset_state();
$_POST['category']  = 'Registration, refunds or transfers';
$_POST['event']     = 'Javelina Jundred';
$_POST['your-name'] = "Test\nPerson";
$_POST['reg-email'] = 'other@example.com';
$mail               = array(
	'subject'   => '[_raw_category] - [your-name]',
	'recipient' => 'info@aravaiparunning.com',
	'body'      => "Category: X\nName: Y\nEmail: Z\nEvent: Javelina Jundred\n\nMessage:\nhello\n\n-- \nSent",
);
$c                  = arv_contact_mail_components( $mail, $ours, new T_Mail( 'mail' ) );
$ref                = arv_contact_ref();
t( $c['subject'] === '[' . $ref . '] Registration, refunds or transfers | Javelina Jundred | Test Person', 'subject tag: ' . $c['subject'] );
t( 'info@aravaiparunning.com' === $c['recipient'], 'recipient unchanged' );
t( 0 === strpos( $c['body'], 'Reference: ' . $ref . "\n" ), 'reference at the top of the body' );
t( false !== strpos( $c['body'], "Registered with: other@example.com\n\nMessage:" ), 'topic field lines before the message' );
reset_state();
$_POST['category']  = 'Something else';
$_POST['your-name'] = 'A';
$c                  = arv_contact_mail_components( $mail, $ours, new T_Mail( 'mail' ) );
t( false !== strpos( $c['subject'], '] Something else | General | A' ), 'empty event reads General' );
$_POST['event'] = 'General, not about a race';
arv_contact_ref( true );
$c = arv_contact_mail_components( $mail, $ours, new T_Mail( 'mail' ) );
t( false !== strpos( $c['subject'], '| General |' ), 'General choice reads General' );
$c2 = arv_contact_mail_components( $mail, $other, new T_Mail( 'mail' ) );
t( $c2 === $mail, 'other forms mail untouched' );
$p = arv_contact_mail_prop( array( 'attachments' => '' ), $ours );
t( '' === $p['attachments'], 'no photo attachment while v2 off' );
$GLOBALS['t_opts']['arv_contact_v2'] = '1';
$p                                   = arv_contact_mail_prop( array( 'attachments' => '' ), $ours );
t( '[lf-photo]' === $p['attachments'], 'photo attached on v2' );

// Auto-reply.
reset_state();
$add = array( 'mail_2' => array( 'active' => true ) );
t( ! isset( arv_contact_additional_mail( $add, $ours )['mail_2'] ), 'auto-reply never sent with the option off (even if stored active)' );
t( array( 'stored' ) === arv_contact_mail2_prop( array( 'stored' ), $ours ), 'mail_2 property untouched with option off' );
t( isset( arv_contact_additional_mail( $add, $other )['mail_2'] ), 'other forms keep their mail_2' );
$GLOBALS['t_opts']['arv_contact_autoreply'] = 'test';
t( ! isset( arv_contact_additional_mail( $add, $ours )['mail_2'] ), 'test mode without a test address sends nothing' );
$GLOBALS['t_opts']['arv_contact_autoreply_test_to'] = 'someone@gmail.com';
t( ! isset( arv_contact_additional_mail( $add, $ours )['mail_2'] ), 'test address must be @aravaiparunning.com' );
$GLOBALS['t_opts']['arv_contact_autoreply_test_to'] = 'webtest@aravaiparunning.com';
t( isset( arv_contact_additional_mail( $add, $ours )['mail_2'] ), 'test mode with a staff test address sends' );
$m2 = arv_contact_mail2_prop( array(), $ours );
t( true === $m2['active'] && '[your-email]' === $m2['recipient'], 'mail_2 built in code' );
$_POST['category']  = 'Race day, results or timing';
$_POST['your-name'] = 'Pat Runner';
$_POST['your-email'] = 'pat@example.com';
$c = arv_contact_mail_components( array( 'recipient' => 'pat@example.com', 'subject' => '', 'body' => '' ), $ours, new T_Mail( 'mail_2' ) );
t( 'webtest@aravaiparunning.com' === $c['recipient'], 'test mode diverts to the staff test address' );
t( false !== strpos( $c['subject'], arv_contact_ref() ) && false !== strpos( $c['body'], arv_contact_ref() ), 'auto-reply carries the reference' );
t( false !== strpos( $c['body'], '1 to 2 business days' ), 'auto-reply reply time' );
t( 3 === substr_count( $c['body'], 'https://www.aravaiparunning.com/' ), 'auto-reply has 3 topic links' );
t( false !== strpos( $c['body'], 'Hi Pat,' ), 'auto-reply greets by first name' );
$GLOBALS['t_opts']['arv_contact_autoreply'] = '1';
$c = arv_contact_mail_components( array( 'recipient' => 'pat@example.com', 'subject' => '', 'body' => '' ), $ours, new T_Mail( 'mail_2' ) );
t( 'pat@example.com' === $c['recipient'], 'live mode goes to the sender' );
$_POST['your-name'] = 'Click evil.example.com now';
arv_contact_ref( true );
$c = arv_contact_mail_components( array( 'recipient' => 'x', 'subject' => '', 'body' => '' ), $ours, new T_Mail( 'mail_2' ) );
t( false === strpos( $c['body'], 'evil.example.com' ), 'visitor text defanged' );
foreach ( array_keys( arv_contact_help() ) as $topic ) {
	$r = arv_contact_autoreply( $topic, 'ARV-261127-TEST', 'Pat' );
	t( false === strpos( $r['body'] . $r['subject'], "\u{2014}" ), "no em dash in auto-reply ($topic)" );
}
$ar_topics = array_unique( array_merge( array_keys( arv_contact_help() ), array( 'Registration, refunds or transfers', 'Race day, results or timing', 'Lost and found', 'Volunteering', 'Media and press', 'Sponsorship and partnerships', 'Event services, timing or production', 'Something else', 'Not a real topic' ) ) );
foreach ( $ar_topics as $topic ) {
	$links = arv_contact_autoreply_links( $topic );
	$urls  = array_map( function ( $l ) { return $l[1]; }, $links );
	t( count( $links ) >= 2 && count( $links ) <= 3, "auto-reply has 2 or 3 links ($topic)" );
	t( count( $urls ) === count( array_unique( $urls ) ), "auto-reply links not repeated ($topic)" );
	$r = arv_contact_autoreply( $topic, 'ARV-261127-TEST', 'Pat' );
	t( 0 === count( array_filter( $urls, function ( $u ) use ( $r ) { return false === strpos( $r['body'], $u ); } ) ), "auto-reply body carries its links ($topic)" );
}

// Race list.
reset_state();
$names = arv_contact_event_names( true );
t( in_array( 'Javelina Jundred', $names, true ), 'Javelina 2027 listed, Presented by stripped' );
t( 1 === count( array_keys( $names, 'Javelina Jundred', true ) ) && 1 === count( array_keys( $names, 'Fat Ox', true ) ), 'names deduped' );
t( in_array( 'Merry Vertmas', $names, true ), 'current race (ends after today) listed' );
t( 'General, not about a race' === end( $names ), 'General choice last' );
t( in_array( 'Frenzy Hills Rides', $names, true ), 'upcoming rides listed' );
$GLOBALS['t_over']['arv_contact_now'] = strtotime( '2027-01-05 16:00:00 UTC' );
$names = arv_contact_event_names( true );
t( ! in_array( 'Merry Vertmas', $names, true ) && ! in_array( 'Frenzy Hills Rides', $names, true ), 'past races and rides dropped' );
$GLOBALS['t_over']['arv_contact_now'] = strtotime( '2026-11-27 16:00:00 UTC' );
$GLOBALS['t_trans'] = array( 'arv_contact_events_2026-11-27' => array( 'Cached Race', 'General, not about a race' ) );
t( array( 'Cached Race', 'General, not about a race' ) === arv_contact_event_names(), 'transient cache used' );

// Rendering: Turnstile attrs only on 84199, help slot filled.
reset_state();
$html = '<div class="wpcf7-turnstile cf-turnstile" data-sitekey="k" data-response-field-name="_wpcf7_turnstile_response"></div>';
$out  = arv_contact_form_elements( $html );
t( false !== strpos( $out, 'data-refresh-expired="auto"' ) && false !== strpos( $out, 'data-retry="auto"' ) && false !== strpos( $out, 'data-refresh-timeout="auto"' ), 'Turnstile attrs on 84199' );
t( 1 === substr_count( arv_contact_turnstile_attrs( $out ), 'data-retry' ), 'attrs not doubled' );
t( in_array( 'aravaipa-contact-form', $GLOBALS['t_enqueued'], true ), 'script enqueued on 84199' );
$GLOBALS['t_current'] = $other;
t( arv_contact_form_elements( $html ) === $html, 'no Turnstile change on other forms' );
reset_state();
$out = arv_contact_form_elements( '<p>x</p><div class="arv-cf-help-slot"></div>' . $html );
t( false === strpos( $out, 'arv-cf-help-slot' ) && false !== strpos( $out, 'class="arv-cf-data"' ), 'help slot filled with data' );
t( false !== strpos( $out, 'https://www.aravaiparunning.com/refund-transfer-policy/' ) && false !== strpos( $out, 'data-topic="Lost and found"' ), 'help links rendered' );
t( false === strpos( $out, "\u{2014}" ), 'no em dash in help' );
$out2 = arv_contact_form_elements( "<p>x</p><div class=\"arv-cf-help-slot\">\n\t</div>" . $html );
t( false === strpos( $out2, 'arv-cf-help-slot' ) && false !== strpos( $out2, 'class="arv-cf-data"' ), 'help slot filled after CF7 autop reflow' );
t( false === stripos( arv_contact_css(), 'border-left' ) && false === stripos( arv_contact_css(), 'box-shadow' ) && false === stripos( arv_contact_css(), 'border-radius' ), 'no color bars, shadows or rounded cards' );

// Flamingo.
reset_state();
$_POST['category'] = 'Lost and found';
$a                 = arv_contact_flamingo( arv_contact_log_topic( array( 'subject' => '', 'fields' => array( 'category' => 'info@aravaiparunning.com' ) ) ) );
t( $a['meta']['arv_reference'] === arv_contact_ref() && $a['fields']['reference'] === arv_contact_ref(), 'reference in Flamingo meta and fields' );
t( $a['subject'] === '[' . arv_contact_ref() . '] Lost and found', 'Flamingo subject: ' . $a['subject'] );

echo $fails ? "$fails of $count FAILED\n" : "ALL PASS ($count)\n";
exit( $fails ? 1 : 0 );
