<?php
/**
 * Racing Team apply confirmation email, CF7 Mail (2) (job kfk1l8), without WordPress.
 *
 * Covers: mail_2 is active, to [rt-email], from Aravaipa Running
 * <wordpress@aravaiparunning.com>, Reply-To bryce@aravaiparunning.com, the
 * subject, plain text, no attachments; the body greets by first name, echoes
 * region and a summary of the answers, says what happens next; every tag in
 * it is a real field; house copy rules (no em or en dashes, no prices, no
 * banned flourishes); the runtime mail_2 filter applies to our form only; the
 * install command writes mail_2 and messages and is idempotent; mail_sent_ok
 * no longer says there is no confirmation email; the admin mail is unchanged.
 *
 * Run: php scripts/test/racing-team-apply-mail2-test.php
 *
 * @package Aravaipa_Elements
 */

require __DIR__ . '/fixtures/rt-apply-stubs.php';
require dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php';

$ours = 501;
$GLOBALS['t_opts']['arv_rt_apply_form_id'] = $ours;

$m = arv_rt_apply_confirm_mail();
ok( true === $m['active'], 'mail_2 active' );
ok( '[rt-email]' === $m['recipient'], 'to the applicant [rt-email]' );
ok( 'Aravaipa Running <wordpress@aravaiparunning.com>' === $m['sender'], 'from Aravaipa Running <wordpress@aravaiparunning.com>' );
ok( 'Reply-To: bryce@aravaiparunning.com' === $m['additional_headers'], 'Reply-To bryce@ (Jamil 2026-11-24)' );
ok( 'We got your 2027 Aravaipa Racing Team application' === $m['subject'], 'subject' );
ok( false === $m['use_html'] && '' === $m['attachments'], 'plain text, no attachments' );

$b = $m['body'];
ok( 0 === strpos( $b, 'Hi [rt-first-name],' ), 'greets by first name' );
ok( false !== strpos( $b, 'Region: [rt-region]' ), 'echoes region' );
ok( false !== strpos( $b, 'Name: [rt-first-name] [rt-last-name]' ), 'echoes full name' );
foreach ( array( 'rt-city', 'rt-state', 'rt-disciplines', 'rt-returning', 'rt-race-plans', 'rt-outside-races', 'rt-resume', 'rt-why', 'rt-size-tee', 'rt-shoe-size' ) as $t ) {
	ok( false !== strpos( $b, '[' . $t . ']' ), 'summary has ' . $t );
}
ok( false !== strpos( $b, 'November 30' ) && false !== strpos( $b, 'review every application' ) && false !== strpos( $b, 'decisions' ) && false !== strpos( $b, 'by email' ), 'next steps' );
ok( false !== strpos( $b, 'reply to this email' ), 'reply line' );
ok( false !== strpos( $b, "Aravaipa Running\n" ), 'signed Aravaipa Running' );

// Every tag in the body is a real field, so CF7 never prints a raw tag.
$names = array();
foreach ( arv_rt_apply_fields() as $f ) {
	$names[] = $f['name'];
}
preg_match_all( '/\[([a-z0-9_-]+)\]/', $b, $mm );
ok( count( $mm[1] ) > 10, 'body uses field tags' );
foreach ( array_unique( $mm[1] ) as $t ) {
	ok( in_array( $t, $names, true ), 'tag is a field: ' . $t );
}
ok( false === strpos( $b, '[rt-headshot]' ) && false === strpos( $b, '[rt-resume-file]' ) && false === strpos( $b, '[rt-birth-date]' ) && false === strpos( $b, '[rt-phone]' ), 'no files, birth date or phone echoed' );

// House copy rules.
$all = $m['subject'] . "\n" . $b . "\n" . arv_rt_apply_messages()['mail_sent_ok'];
ok( false === strpos( $all, "\u{2014}" ) && false === strpos( $all, "\u{2013}" ), 'no em or en dashes' );
ok( false === strpos( $all, '$' ), 'no price amounts' );
ok( ! preg_match( '/\b(wild|unforgettable|magical|epic|breathtaking|awaits|adventure|give yourself|seldom)\b/i', $all ), 'no banned flourishes' );
ok( ! preg_match( "/you've|you know|you remember|as a past|you ran|you rode/i", $b ), 'no recap lines' );

// Rendered sample: no raw tags left.
$sample = array( 'rt-first-name' => 'Stella', 'rt-last-name' => 'Ruiz', 'rt-region' => 'West' );
$r      = preg_replace_callback( '/\[([a-z0-9_-]+)\]/', function ( $x ) use ( $sample ) {
	return isset( $sample[ $x[1] ] ) ? $sample[ $x[1] ] : 'x';
}, $b );
ok( 0 === strpos( $r, 'Hi Stella,' ) && false !== strpos( $r, 'Region: West' ) && false === strpos( $r, '[rt-' ), 'sample render' );

// Runtime filter: our form only.
ok( arv_rt_apply_mail2_prop( array( 'active' => false ), new WPCF7_ContactForm( $ours ) ) === $m, 'mail_2 filter: ours gets the confirmation' );
ok( array( 'active' => false ) === arv_rt_apply_mail2_prop( array( 'active' => false ), new WPCF7_ContactForm( 777 ) ), 'mail_2 filter: other form untouched' );
ok( in_array( 'arv_rt_apply_mail2_prop', $GLOBALS['t_filters']['wpcf7_contact_form_property_mail_2'], true ), 'mail_2 filter registered' );

// Install writes mail_2 and messages, idempotently.
$GLOBALS['t_store'][ $ours ] = array(
	'mail_2'   => array( 'active' => false, 'subject' => 'old' ),
	'messages' => array( 'mail_sent_ok' => 'This page is your receipt: we do not send a confirmation email.', 'invalid_email' => 'Bad email.' ),
);
ok( $ours === arv_rt_apply_install(), 'install returns the form id' );
$first = $GLOBALS['t_store'][ $ours ];
ok( $first['mail_2'] === $m, 'install: stored mail_2 is the confirmation' );
ok( true === $first['mail_2']['active'], 'install: mail_2 active' );
ok( arv_rt_apply_messages()['mail_sent_ok'] === $first['messages']['mail_sent_ok'], 'install: new mail_sent_ok' );
ok( 'Bad email.' === $first['messages']['invalid_email'], 'install: other stored messages kept' );
ok( $first['mail'] === arv_rt_apply_mail(), 'install: admin mail written' );
arv_rt_apply_install();
ok( $GLOBALS['t_store'][ $ours ] === $first, 'install twice: same stored config' );
ok( 2 === $GLOBALS['t_saves'], 'install saved each run' );
ok( is_string( arv_rt_apply_install( true ) ) && 2 === $GLOBALS['t_saves'], 'dry run saves nothing' );

// Admin mail unchanged: still to the team, Reply-To the applicant, with files.
$a = arv_rt_apply_mail();
ok( 'Reply-To: [rt-email]' === $a['additional_headers'] && false !== strpos( $a['attachments'], '[rt-headshot]' ), 'admin mail unchanged' );

$src = file_get_contents( dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php' );
ok( false === stripos( $src, 'we do not send a confirmation email' ), 'old no-confirmation copy gone' );
ok( ! preg_match( "/mail2\\['active'\\]\\s*=\\s*false/", $src ), 'install no longer forces mail_2 off' );

if ( is_dir( $GLOBALS['t_up'] ) ) {
	exec( 'rm -rf ' . escapeshellarg( $GLOBALS['t_up'] ) );
}
echo $fails ? "$fails of $n checks FAILED\n" : "ALL PASS ($n checks)\n";
exit( $fails ? 1 : 0 );
