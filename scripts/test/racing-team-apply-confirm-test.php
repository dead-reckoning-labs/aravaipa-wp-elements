<?php
/**
 * Racing Team apply thank-you screen (job kfk1l8), without WordPress.
 *
 * Covers: the shortcode keeps a hidden "Application received" panel next to
 * the form for CF7's script; after a sent no-JS POST the server renders the
 * panel visible inside the form (where the #wpcf7-f... anchor lands) with the
 * escaped first name, marks the wrapper data-sent="server" and does not print
 * a second panel; the copy (next steps, Nov 30, decisions by email, the
 * confirmation email line); CSS hides the fields, error list and CF7 response
 * line on the sent state; the inline script hooks wpcf7mailsent, fills the
 * name with textContent and scrolls; the panel has no colored edge stripe;
 * other forms and failed submits get no panel.
 *
 * Run: php scripts/test/racing-team-apply-confirm-test.php
 *
 * @package Aravaipa_Elements
 */

require __DIR__ . '/fixtures/rt-apply-stubs.php';
require dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php';

$ours  = 501;
$GLOBALS['t_opts']['arv_rt_apply_form_id'] = $ours;
$GLOBALS['t_now'] = strtotime( '2026-11-25T12:00:00-07:00' );

// 1. Normal page view: hidden panel beside the form, form visible.
$sc = arv_rt_apply_shortcode();
ok( 1 === substr_count( $sc, 'id="rt-thanks"' ), 'one thank-you panel on the page' );
ok( preg_match( '/<div class="rt-thanks" id="rt-thanks"[^>]* hidden>/', $sc ), 'panel hidden before submit' );
ok( false === strpos( $sc, 'data-sent=' ), 'wrapper not marked sent' );
ok( preg_match( '/<div class="rt-state-open">/', $sc ), 'form area visible while open' );
ok( false !== strpos( $sc, 'Application received' ), 'kicker: Application received' );
ok( false !== stripos( $sc, 'confirmation email is on its way' ), 'copy: confirmation email is on its way' );
ok( false !== strpos( $sc, 'November 30' ) && false !== strpos( $sc, 'review every application' ) && false !== strpos( $sc, 'decisions by email' ), 'copy: next steps, deadline, decisions by email' );
ok( false === strpos( $sc, "\u{2014}" ) && false === strpos( $sc, "\u{2013}" ), 'no em or en dashes' );

// JS hook for CF7's script.
$js = arv_rt_apply_thanks_js();
ok( false !== strpos( $sc, $js ), 'inline thank-you script printed with the form' );
ok( false !== strpos( $js, 'addEventListener("wpcf7mailsent"' ), 'listens for wpcf7mailsent' );
ok( false !== strpos( $js, 'rt-first-name' ) && false !== strpos( $js, '.textContent=' ) && false === strpos( $js, 'innerHTML' ), 'fills the first name as text, never HTML' );
ok( false !== strpos( $js, 'scrollIntoView' ) && false !== strpos( $js, 'o.hidden=true' ) && false !== strpos( $js, 't.hidden=false' ), 'hides the form, shows and scrolls to the panel' );
ok( false !== strpos( $js, 'r.contains(e.target)' ), 'ignores other forms on the page' );
ok( false !== strpos( $js, '"server"' ), 'scrolls to the server rendered panel after a no-JS submit' );

// CSS: sent state hides fields and the response line; no stripes on the panel.
$css = arv_rt_apply_css();
foreach ( array( '.rt-apply[data-sent] .rt-form', '.rt-cf7.sent .rt-form', '.rt-cf7.sent .wpcf7-response-output', '.rt-apply[data-sent] .wpcf7-response-output' ) as $sel ) {
	ok( false !== strpos( $css, $sel ), 'css hides on sent: ' . $sel );
}
ok( ! preg_match( '/\.rt-thanks[^{]*\{[^}]*border(-left|-top|-right|-bottom)?\s*:/', $css ), 'panel has no border stripe' );
ok( ! preg_match( '/\.rt-thanks[^{]*\{[^}]*box-shadow\s*:\s*inset/', $css ), 'panel has no inset shadow stripe' );

// 2. No-JS POST that CF7 sent: server renders the panel inside the form.
$sub         = new WPCF7_Submission();
$sub->status = 'mail_sent';
$sub->form   = new WPCF7_ContactForm( $ours );
$sub->posted = array( 'rt-first-name' => ' Courtney <b>x</b> ' );
WPCF7_Submission::$inst = $sub;
ok( arv_rt_apply_just_sent(), 'just_sent true for our sent form' );
$sc = arv_rt_apply_shortcode();
ok( false !== strpos( $sc, 'data-sent="server"' ), 'wrapper marked data-sent="server"' );
ok( 1 === substr_count( $sc, 'id="rt-thanks"' ), 'still exactly one panel' );
ok( preg_match( '/<form class="wpcf7-form sent"[^>]*><div class="rt-thanks" id="rt-thanks"[^>]*tabindex="-1">/', $sc ), 'panel visible, first thing inside the sent form' );
ok( false !== strpos( $sc, 'Thank you<span class="rt-thanks-name">, Courtney &lt;b&gt;x&lt;/b&gt;</span>.' ), 'first name shown, trimmed and escaped' );
ok( preg_match( '/<div class="rt-state-open">/', $sc ), 'form area left visible so the anchor lands on the panel' );
ok( strpos( $sc, 'id="rt-thanks"' ) < strpos( $sc, 'FORM-' . $ours ), 'panel sits above the fields' );

// Long names are capped.
$sub->posted = array( 'rt-first-name' => str_repeat( 'a', 200 ) );
$sc          = arv_rt_apply_shortcode();
ok( false !== strpos( $sc, ', ' . str_repeat( 'a', 60 ) . '</span>' ) && false === strpos( $sc, str_repeat( 'a', 61 ) ), 'first name capped at 60 characters' );

// No name: plain thank you.
ok( false !== strpos( arv_rt_apply_thanks_html( true, '' ), 'Thank you<span class="rt-thanks-name"></span>.' ), 'no name, plain thank you' );

// 3. Failed or other submits: no visible panel.
$sub->status = 'validation_failed';
$sc          = arv_rt_apply_shortcode();
ok( ! arv_rt_apply_just_sent() && false === strpos( $sc, 'data-sent=' ) && preg_match( '/id="rt-thanks"[^>]* hidden>/', $sc ), 'validation failure: form, no panel' );
$sub->status = 'mail_sent';
$sub->form   = new WPCF7_ContactForm( 777 );
ok( ! arv_rt_apply_just_sent(), 'other form sent: not ours' );
WPCF7_ContactForm::$current = new WPCF7_ContactForm( 777 );
ok( false === strpos( arv_rt_apply_form_elements( '<div class="rt-form">X</div>' ), 'rt-thanks' ), 'other form elements untouched' );
WPCF7_ContactForm::$current = null;
WPCF7_Submission::$inst     = null;
ok( ! arv_rt_apply_just_sent(), 'no submission: not sent' );

// 4. CF7 message for the sent state matches the panel.
$m = arv_rt_apply_messages();
ok( false !== stripos( $m['mail_sent_ok'], 'confirmation email is on its way' ) && false === stripos( $m['mail_sent_ok'], 'do not send' ), 'mail_sent_ok mentions the confirmation email' );

// 5. The asset script no longer duplicates the swap.
$asset = file_get_contents( dirname( __DIR__, 2 ) . '/assets/aravaipa-rt-apply.js' );
ok( false === strpos( $asset, "getElementById('rt-thanks')" ), 'asset JS leaves the swap to the inline script' );

if ( is_dir( $GLOBALS['t_up'] ) ) {
	exec( 'rm -rf ' . escapeshellarg( $GLOBALS['t_up'] ) );
}
echo $fails ? "$fails of $n checks FAILED\n" : "ALL PASS ($n checks)\n";
exit( $fails ? 1 : 0 );
