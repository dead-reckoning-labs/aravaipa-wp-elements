<?php
/**
 * HTML Racing Team emails (0.101.32), without WordPress: WordPress, CF7 and
 * PHPMailer are stubbed.
 *
 * Covers: the staff email header block, sections and bold labels; every
 * answer escaped once (script tags, quotes, ampersands); javascript:, data:
 * and vbscript: never become links; Instagram, Strava, UltraSignup and URLs
 * inside long answers become links; blank answers and empty sections are
 * dropped; line breaks kept; no em dash and no edge stripes; the inline
 * headshot: a small one embedded (cid) and not attached, a large or HEIC one
 * attached and not embedded, never both; the
 * plain-text AltBody and the photo armed through phpmailer_init for the staff
 * send only; the applicant confirmation stays plain text by default and the
 * HTML variant only comes in behind the flag.
 *
 * Run: php scripts/test/racing-team-apply-mail-html-test.php
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['t_opts']    = array( 'arv_rt_apply_form_id' => 501 );
$GLOBALS['t_filters'] = array();
$GLOBALS['t_posted']  = array();
$GLOBALS['t_files']   = array();
$GLOBALS['t_tmp']     = sys_get_temp_dir() . '/rt-mail-html-' . getmypid();
@mkdir( $GLOBALS['t_tmp'], 0777, true );

function add_filter( $h, $fn, $p = 10, $n = 1 ) {
	$GLOBALS['t_filters'][ $h ][ $p ][] = $fn;
}
function add_action( $h, $fn, $p = 10, $n = 1 ) {
	add_filter( $h, $fn, $p, $n );
}
function apply_filters( $h, $v, ...$a ) {
	return $v;
}
function add_shortcode( $t, $fn ) {}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['t_opts'] ) ? $GLOBALS['t_opts'][ $k ] : $d;
}
function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}
function wp_unslash( $v ) {
	return $v;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
class WPCF7_ContactForm {
	public $i;
	public function __construct( $i ) {
		$this->i = $i;
	}
	public function id() {
		return $this->i;
	}
}
class WPCF7_Submission {
	public static $inst = null;
	public static function get_instance() {
		return self::$inst;
	}
	public function get_posted_data( $n ) {
		return isset( $GLOBALS['t_posted'][ $n ] ) ? $GLOBALS['t_posted'][ $n ] : null;
	}
	public function uploaded_files() {
		return $GLOBALS['t_files'];
	}
	public function get_meta( $k ) {
		return 'url' === $k ? 'https://aravaiparunning.com/racing-team-apply/' : '';
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
class T_PHPMailer {
	public $Subject = '';
	public $Body    = '';
	public $AltBody = '';
	public $embedded = array();
	public function addEmbeddedImage( $path, $cid, $name = '' ) {
		$this->embedded[] = array( $path, $cid, $name );
		return true;
	}
}

require dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php';

$n     = 0;
$fails = 0;
function ok( $c, $m ) {
	global $n, $fails;
	++$n;
	if ( ! $c ) {
		++$fails;
		echo "FAIL: $m\n";
	}
}
function has( $hay, $needle ) {
	return false !== strpos( $hay, $needle );
}
/** Run a filter hook the way WordPress would (by priority). */
function run_filter( $h, $v, ...$a ) {
	$by = isset( $GLOBALS['t_filters'][ $h ] ) ? $GLOBALS['t_filters'][ $h ] : array();
	ksort( $by );
	foreach ( $by as $fns ) {
		foreach ( $fns as $fn ) {
			$v = $fn( $v, ...$a );
		}
	}
	return $v;
}
function no_stripes( $html ) {
	return ! preg_match( '/border-(left|right|top|bottom)\s*:/i', $html ) && ! preg_match( '/border\s*:\s*[^;"]*(#|rgb)/i', $html );
}

$lindsay = array(
	'first-name'      => 'Lindsay',
	'last-name'       => 'Kostelnick',
	'email'           => 'lindsay@example.com',
	'phone'           => '928-555-0142',
	'birth-date'      => '1990-05-14',
	'city'            => 'Flagstaff',
	'state'           => 'AZ',
	'region'          => 'West',
	'disciplines'     => 'Trail, Ultra, Road',
	'returning'       => 'Yes',
	'returning-years' => '2025, 2026',
	'resume'          => "1st F, Black Canyon 100K 2026\n2nd F, Flagstaff Sky Peaks 26K 2025",
	'ultrasignup'     => 'Lindsay Kostelnick',
	'profiles'        => "UTMB: https://utmb.world/runner/12345\nStrava: strava.com/athletes/987",
	'goals'           => '',
	'race-plans'      => '',
	'outside-races'   => 'Western States lottery, Broken Arrow 46K',
	'summit'          => '',
	'why'             => "Racing for Aravaipa made trail running feel like a team sport.\n\nI want to keep that going.",
	'bring'           => '',
	'instagram'       => '@lindsayruns',
	'strava'          => 'https://www.strava.com/athletes/987',
	'socials'         => '',
	'content-links'   => 'Race report: www.example.com/bcu-report.',
	'sponsors'        => '',
	'support'         => '',
	'size-fit'        => 'Women',
	'size-tee'        => 'S',
	'size-top'        => 'S',
	'size-shorts'     => 'S',
	'shoe-size'       => '8',
	'consent-age'          => 'yes',
	'consent-expectations' => 'yes',
	'consent-privacy'      => 'yes',
	'consent-publicity'    => 'no',
);
// The stripe check itself catches a colored edge (border-left, border-top and so on).
$c1 = '#2a5e6b';
$c2 = 'rgb(1,2,3)';
ok( ! no_stripes( '<td style="border-left:4px solid ' . $c1 . '">' ) && ! no_stripes( '<div style="border-top: 3px solid ' . $c2 . '">' ) && no_stripes( '<img style="border:0">' ), 'stripe detector works' );
$meta = array( 'https://aravaiparunning.com/racing-team-apply/', 'November 24, 2026', '9:15 am' );

// Header, sections, labels.
$h = arv_rt_apply_mail_html( $lindsay, array(), $meta, false );
ok( has( $h, '>Lindsay Kostelnick</td>' ), 'header: name' );
ok( has( $h, '>West</span>' ), 'header: region badge' );
ok( has( $h, '<strong>Home:</strong> Flagstaff, AZ' ), 'header: home city and state' );
ok( has( $h, '<strong>Returning:</strong> Yes, 2025, 2026' ), 'header: returning with years' );
ok( has( arv_rt_apply_mail_html( array_merge( $lindsay, array( 'returning' => 'No', 'returning-years' => '' ) ), array(), $meta ), '<strong>Returning:</strong> No, new applicant' ), 'header: not returning' );
ok( has( $h, '>About you</td>' ) && has( $h, '>Your racing</td>' ) && has( $h, '>Kit and photo</td>' ) && has( $h, '>Agreements</td>' ), 'section headings' );
ok( has( $h, '<strong>Races:</strong> Trail, Ultra, Road' ), 'short label, bold' );
ok( 1 === substr_count( $h, 'Flagstaff, AZ' ) && ! has( $h, '<strong>City:' ) && ! has( $h, 'Which years' ), 'header fields not repeated in sections' );
ok( has( $h, '<strong style="display:block;margin:0 0 2px">Racing resume</strong>1st F, Black Canyon 100K 2026<br>' . "\n" . '2nd F, Flagstaff Sky Peaks 26K 2025' ), 'long answer keeps line breaks' );
ok( has( $h, 'team sport.<br>' . "\n" . '<br>' . "\n" . 'I want' ), 'blank line inside an answer kept' );
ok( has( $h, 'max-width:600px' ) && has( $h, 'name="viewport"' ) && has( $h, '-apple-system' ), 'one column, 600px, viewport, system fonts' );
ok( has( $h, '<html' ) && has( $h, '</html>' ) && preg_match( '%<html[>\s].*</html>%is', $h ), 'has its own <html> (CF7 skips htmlize and autop)' );
ok( ! has( $h, "\u{2014}" ) && ! has( $h, '&mdash;' ), 'no em dash' );
ok( no_stripes( $h ), 'no edge stripes' );
ok( 1 >= substr_count( $h, ARV_RT_APPLY_MAIL_TEAL . ';color:#ffffff' ), 'teal background only on the region badge' );
ok( has( $h, '<strong>Yes:</strong> I am 18' ) && has( $h, '<strong>No:</strong> If selected, you may use my name' ), 'agreements yes and no' );
ok( has( $h, 'Sent from https://aravaiparunning.com/racing-team-apply/ on November 24, 2026 at 9:15 am.' ), 'footer line' );

// Blank answers and empty sections dropped.
ok( ! has( $h, 'Goals' ) && ! has( $h, 'Summit' ) && ! has( $h, 'Sponsors' ) && ! has( $h, 'Support wanted' ), 'blank answers dropped' );
$bare = $lindsay;
foreach ( array( 'race-plans', 'outside-races', 'summit' ) as $k ) {
	$bare[ $k ] = '  ';
}
ok( ! has( arv_rt_apply_mail_html( $bare, array(), $meta ), 'Your 2027 season' ), 'empty section dropped' );
ok( has( $h, 'Your 2027 season' ), 'section with an answer kept' );
ok( ! has( $h, '>Photo' ) && ! has( $h, 'Resume file' ), 'no files: file rows dropped' );

// Links.
ok( has( $h, 'href="https://www.instagram.com/lindsayruns/"' ) && has( $h, '>@lindsayruns</a>' ), 'instagram @handle link' );
ok( has( arv_rt_apply_mail_value_html( array( 'key' => 'instagram', 'type' => 'text' ), 'lindsay.runs' ), 'href="https://www.instagram.com/lindsay.runs/"' ), 'instagram handle without @' );
ok( has( arv_rt_apply_mail_value_html( array( 'key' => 'instagram', 'type' => 'text' ), 'https://instagram.com/lindsay_r/' ), 'href="https://www.instagram.com/lindsay_r/"' ), 'instagram link' );
ok( ! has( arv_rt_apply_mail_value_html( array( 'key' => 'instagram', 'type' => 'text' ), 'my handle is x' ), '<a ' ), 'instagram free text: no link' );
ok( has( $h, 'href="https://www.strava.com/athletes/987"' ), 'strava link' );
ok( has( $h, 'href="https://ultrasignup.com/results_participant.aspx?fname=Lindsay&amp;lname=Kostelnick"' ) && has( $h, '(name search)' ), 'ultrasignup name search link' );
ok( has( arv_rt_apply_mail_value_html( array( 'key' => 'ultrasignup', 'type' => 'text' ), 'ultrasignup.com/results_participant.aspx?fname=A&lname=B' ), 'href="https://ultrasignup.com/results_participant.aspx?fname=A&amp;lname=B"' ), 'ultrasignup link' );
ok( has( $h, 'UTMB: <a href="https://utmb.world/runner/12345"' ) && has( $h, 'href="https://strava.com/athletes/987"' ), 'urls in a multi-line answer' );
ok( has( $h, 'href="https://www.example.com/bcu-report"' ) && has( $h, '>www.example.com/bcu-report</a>.' ), 'www link, trailing period left out' );
ok( has( $h, 'href="mailto:lindsay@example.com"' ) && has( $h, 'href="tel:9285550142"' ), 'email and phone links' );

// Escaping and dangerous links.
$evil = array_merge( $lindsay, array(
	'first-name'    => '<script>alert(1)</script>',
	'why'           => 'Tom & Jerry say "hi" & \'bye\' &amp; <b>bold</b>',
	'strava'        => 'javascript:alert(1)',
	'content-links' => "javascript:alert(2)\ndata:text/html;base64,PHNjcmlwdD4=\nvbscript:msgbox(1)\nhttps://ok.example.com/a?b=1&c=\"2\"",
	'instagram'     => '"><img src=x onerror=alert(1)>',
	'ultrasignup'   => 'javascript://example.com/%0Aalert(1)',
	'profiles'      => '<a href="javascript:alert(3)">x</a>',
) );
$e = arv_rt_apply_mail_html( $evil, array(), $meta );
ok( ! has( $e, '<script>' ) && has( $e, '&lt;script&gt;alert(1)&lt;/script&gt;' ), 'script escaped' );
ok( has( $e, 'Tom &amp; Jerry say &quot;hi&quot; &amp; &#039;bye&#039; &amp;amp; &lt;b&gt;bold&lt;/b&gt;' ), 'quotes and ampersands escaped once' );
ok( ! preg_match( '/href="\s*(javascript|data|vbscript):/i', $e ) && ! has( $e, '<img src=x' ), 'no javascript:, data: or vbscript: link' );
ok( ! preg_match( '/<a [^>]*>[^<]*javascript/i', $e ), 'javascript text never inside a link' );
ok( has( $e, 'href="https://ok.example.com/a?b=1&amp;c="' ), 'quote ends a link and is escaped' );
ok( has( $e, '&lt;a href=&quot;javascript:alert(3)&quot;&gt;' ), 'typed html stays text' );
ok( '' === arv_rt_apply_mail_href( 'javascript:alert(1)' ) && '' === arv_rt_apply_mail_href( 'data:text/html,x' ) && '' === arv_rt_apply_mail_href( 'vbscript:x' ) && '' === arv_rt_apply_mail_href( 'JaVaScRiPt://x.com/%0a1' ), 'href rejects bad schemes' );
ok( 'https://a.example.com/x' === arv_rt_apply_mail_href( 'http://a.example.com/x' ), 'http upgraded' );
ok( arv_rt_apply_h( '&amp;' ) === '&amp;amp;', 'escape is exactly once (no double-encode skip)' );

// Headshot.
$jpg = $GLOBALS['t_tmp'] . '/x-rt-headshot.jpg';
file_put_contents( $jpg, "\xFF\xD8\xFF\xE0jpeg" );
$heic = $GLOBALS['t_tmp'] . '/x-rt-headshot.heic';
file_put_contents( $heic, 'heic' );
$pdf = $GLOBALS['t_tmp'] . '/x-rt-resume-file.pdf';
file_put_contents( $pdf, '%PDF' );
$hp = arv_rt_apply_mail_html( $lindsay, array( 'headshot' => $jpg, 'resume-file' => $pdf ), $meta, true );
ok( has( $hp, 'src="cid:rt-headshot"' ) && ! has( $hp, 'photo attached' ), 'headshot inline by cid, not called attached' );
ok( has( $hp, '<strong>Resume file:</strong> attached to this email' ), 'resume file noted as attached' );
ok( has( $hp, 'max-width:280px' ) && no_stripes( $hp ), 'photo sized for phones, no stripes' );
ok( arv_rt_apply_mail_inline_ok( $jpg ) && ! arv_rt_apply_mail_inline_ok( $heic ) && ! arv_rt_apply_mail_inline_ok( '' ), 'heic not inline' );
$hh = arv_rt_apply_mail_html( $lindsay, array( 'headshot' => $heic ), $meta, false );
ok( ! has( $hh, 'cid:' ) && has( $hh, '<strong>Photo:</strong> attached to this email' ), 'heic photo: attached, not inline' );
ok( "[rt-headshot]\n[rt-resume-file]" === arv_rt_apply_mail()['attachments'], 'attachments list unchanged (photo still attached)' );

// Plain text version.
$t = arv_rt_apply_mail_text( $lindsay, array( 'headshot' => $jpg ), $meta );
ok( has( $t, "Lindsay Kostelnick applied for the 2027 Aravaipa Racing Team.\nRegion: West   Home: Flagstaff, AZ" ), 'text: summary' );
ok( has( $t, "Racing resume:\n1st F, Black Canyon 100K 2026\n2nd F" ) && has( $t, 'Photo: attached' ), 'text: answers and photo' );
ok( ! has( $t, 'Goals' ) && ! has( $t, '[rt-' ) && ! has( $t, '[_' ), 'text: blanks dropped, no tags left' );

// Runtime: CF7 mail property and the send path.
$form = new WPCF7_ContactForm( 501 );
$mp   = arv_rt_apply_mail_prop( array( 'body' => 'x', 'use_html' => false ), $form );
ok( true === $mp['use_html'], 'staff mail use_html on at runtime' );
ok( false === arv_rt_apply_mail_prop( array( 'body' => 'x', 'use_html' => false ), new WPCF7_ContactForm( 9 ) )['use_html'], 'other forms untouched' );
WPCF7_Submission::$inst = new WPCF7_Submission();
foreach ( $lindsay as $k => $v ) {
	$GLOBALS['t_posted'][ 0 === strpos( $k, 'consent-' ) ? 'rt-' . $k : 'rt-' . $k ] = 0 === strpos( $k, 'consent-' ) ? ( 'yes' === $v ? '1' : '' ) : $v;
}
$GLOBALS['arv_rt_apply_last'] = array( 'files' => array( 'rt-headshot' => $jpg ) );
$c = run_filter( 'wpcf7_mail_components', array( 'subject' => '2027 Racing Team application: Lindsay Kostelnick', 'body' => 'CF7 body', 'attachments' => array() ), $form, new T_Mail( 'mail' ) );
ok( has( $c['body'], '>Lindsay Kostelnick</td>' ) && has( $c['body'], 'cid:rt-headshot' ), 'send path: staff body is our HTML with the inline photo' );
$pm          = new T_PHPMailer();
$pm->Subject = '2027 Racing Team application: Lindsay Kostelnick';
$pm->AltBody = 'html2text output';
run_filter( 'phpmailer_init', $pm );
ok( has( $pm->AltBody, 'Lindsay Kostelnick applied' ) && ! has( $pm->AltBody, '<' ), 'AltBody is our plain text' );
ok( 1 === count( $pm->embedded ) && $jpg === $pm->embedded[0][0] && 'rt-headshot' === $pm->embedded[0][1], 'headshot embedded as cid rt-headshot' );
ok( null === $GLOBALS['arv_rt_apply_mailer'], 'disarmed after the staff send' );
$pm2 = new T_PHPMailer();
$pm2->Subject = 'Some other site mail';
run_filter( 'phpmailer_init', $pm2 );
ok( '' === $pm2->AltBody && ! $pm2->embedded, 'next mail on the site untouched' );
// Armed but the staff send never reached phpmailer_init: the confirmation must not get it.
run_filter( 'wpcf7_mail_components', array( 'subject' => 'S', 'body' => 'b' ), $form, new T_Mail( 'mail' ) );
$c2  = run_filter( 'wpcf7_mail_components', array( 'subject' => arv_rt_apply_confirm_subject(), 'body' => 'plain' ), $form, new T_Mail( 'mail_2' ) );
$pm3 = new T_PHPMailer();
$pm3->Subject = arv_rt_apply_confirm_subject();
run_filter( 'phpmailer_init', $pm3 );
ok( 'plain' === $c2['body'] && '' === $pm3->AltBody && ! $pm3->embedded, 'staff extras never leak into the confirmation' );
// Small headshot vs large headshot: embedded or attached, never both.
$big = $GLOBALS['t_tmp'] . '/x-rt-headshot-big.jpg';
$fh  = fopen( $big, 'w' );
fwrite( $fh, "\xFF\xD8\xFF\xE0" );
ftruncate( $fh, 30 * 1024 * 1024 ); // A 30 MB phone original (sparse).
fclose( $fh );
$edge = $GLOBALS['t_tmp'] . '/x-rt-headshot-edge.png';
$fh   = fopen( $edge, 'w' );
ftruncate( $fh, ARV_RT_APPLY_INLINE_MAX + 1 );
fclose( $fh );
ok( 3 * 1024 * 1024 === ARV_RT_APPLY_INLINE_MAX && arv_rt_apply_mail_inline_ok( $jpg ) && ! arv_rt_apply_mail_inline_ok( $big ) && ! arv_rt_apply_mail_inline_ok( $edge ), 'inline limit is about 3 MB (old 15 MB limit gone)' );
$send = function ( $photo ) use ( $form, $pdf ) {
	$GLOBALS['arv_rt_apply_last'] = array( 'files' => array( 'rt-headshot' => $photo, 'rt-resume-file' => $pdf ) );
	$c  = run_filter( 'wpcf7_mail_components', array( 'subject' => 'Size check', 'body' => 'CF7 body', 'attachments' => array( $photo, $pdf ) ), $form, new T_Mail( 'mail' ) );
	$pm = new T_PHPMailer();
	$pm->Subject = 'Size check';
	run_filter( 'phpmailer_init', $pm );
	return array( $c, $pm );
};
list( $cs, $pms ) = $send( $jpg );
ok( has( $cs['body'], 'src="cid:rt-headshot"' ) && ! has( $cs['body'], '<strong>Photo:</strong> attached' ), 'small headshot: shown inline in the HTML' );
ok( ! in_array( $jpg, $cs['attachments'], true ), 'small headshot: embedded, not in attachments' );
ok( in_array( $pdf, $cs['attachments'], true ), 'small headshot: resume file still in attachments' );
ok( 1 === count( $pms->embedded ) && $jpg === $pms->embedded[0][0], 'small headshot: phpmailer_init embeds it' );
list( $cl, $pml ) = $send( $big );
ok( ! has( $cl['body'], 'cid:' ) && has( $cl['body'], '<strong>Photo:</strong> attached to this email' ), 'large headshot: HTML says attached' );
ok( in_array( $big, $cl['attachments'], true ), 'large headshot: in attachments' );
ok( in_array( $pdf, $cl['attachments'], true ), 'large headshot: resume file still in attachments' );
ok( ! $pml->embedded, 'large headshot: attached, not embedded' );
list( $ch, $pmh ) = $send( $heic );
ok( in_array( $heic, $ch['attachments'], true ) && in_array( $pdf, $ch['attachments'], true ) && ! $pmh->embedded && ! has( $ch['body'], 'cid:' ), 'heic headshot: attached, not embedded' );
// CF7 attaches its own upload path; a small photo saved elsewhere still leaves once.
$cf7tmp = $GLOBALS['t_tmp'] . '/cf7-upload-headshot.jpg';
copy( $jpg, $cf7tmp );
$GLOBALS['t_files'] = array( 'rt-headshot' => array( $cf7tmp ) );
list( $cu, $pmu ) = $send( $jpg );
ok( ! in_array( $cf7tmp, $cu['attachments'], true ) && in_array( $pdf, $cu['attachments'], true ) && 1 === count( $pmu->embedded ), 'small headshot: CF7 upload path dropped from attachments too' );
$GLOBALS['t_files'] = array();
// #396 resize: a big phone original shrunk before mail. The resized copy is
// what rides, so it is embedded once and neither file is also attached.
$resized = $GLOBALS['t_tmp'] . '/cf7-upload-headshot-resized.jpg';
copy( $jpg, $resized );
$GLOBALS['t_files']             = array( 'rt-headshot' => array( $big ) );
$GLOBALS['arv_rt_apply_resized'] = array( $big => $resized );
list( $cr, $pmr ) = $send( $jpg );
ok( ! in_array( $big, $cr['attachments'], true ) && ! in_array( $resized, $cr['attachments'], true ) && in_array( $pdf, $cr['attachments'], true ) && 1 === count( $pmr->embedded ) && has( $cr['body'], 'src="cid:rt-headshot"' ), 'resized big headshot: embedded once, not attached' );
$GLOBALS['arv_rt_apply_last'] = null;
$c5  = run_filter( 'wpcf7_mail_components', array( 'subject' => 'Size check', 'body' => 'CF7 body', 'attachments' => array( $big, $pdf ) ), $form, new T_Mail( 'mail' ) );
$pm5 = new T_PHPMailer();
$pm5->Subject = 'Size check';
run_filter( 'phpmailer_init', $pm5 );
ok( 1 === count( $pm5->embedded ) && $resized === $pm5->embedded[0][0] && array( $pdf ) === array_values( $c5['attachments'] ), 'no private copy: the resized upload is what the inline test sees and embeds' );
$GLOBALS['arv_rt_apply_resized'] = array();
// HTML build fails: the fallback clears embed (or the mailer), so the photo stays attached.
$GLOBALS['arv_rt_apply_mailer'] = array( 'subject' => 'S', 'alt' => 't', 'embed' => '' );
$kf = arv_rt_apply_mail_drop_embedded_attachment( array( 'attachments' => array( $jpg, $pdf ) ) );
$GLOBALS['arv_rt_apply_mailer'] = null;
$kn = arv_rt_apply_mail_drop_embedded_attachment( array( 'attachments' => array( $jpg, $pdf ) ) );
ok( array( $jpg, $pdf ) === $kf['attachments'] && array( $jpg, $pdf ) === $kn['attachments'], 'html build fails: small headshot still attached when embed is cleared' );
$GLOBALS['arv_rt_apply_last'] = array( 'files' => array( 'rt-headshot' => $jpg ) );

// Other forms: no change.
$c3 = run_filter( 'wpcf7_mail_components', array( 'subject' => 'S', 'body' => 'other' ), new WPCF7_ContactForm( 9 ), new T_Mail( 'mail' ) );
ok( 'other' === $c3['body'] && null === $GLOBALS['arv_rt_apply_mailer'], 'other forms: body and mailer untouched' );
// A failure while building never stops the mail.
$GLOBALS['t_posted']['rt-first-name'] = array( 'Lindsay', 'x' );
$c4 = run_filter( 'wpcf7_mail_components', array( 'subject' => 'S', 'body' => 'CF7 body' ), $form, new T_Mail( 'mail' ) );
ok( is_string( $c4['body'] ) && '' !== $c4['body'], 'odd posted data still gives a body' );
$GLOBALS['t_posted']['rt-first-name'] = 'Lindsay';

// Applicant confirmation: plain by default, HTML behind the flag.
ok( false === ARV_RT_APPLY_CONFIRM_HTML && false === arv_rt_apply_confirm_html_on(), 'confirmation flag defaults off' );
$cm = arv_rt_apply_confirm_mail();
ok( false === $cm['use_html'] && arv_rt_apply_confirm_body( '[rt-first-name]', false ) === $cm['body'], 'default confirmation is the current plain text' );
$GLOBALS['t_opts']['arv_rt_apply_confirm_html'] = '1';
$cm = arv_rt_apply_confirm_mail();
ok( true === arv_rt_apply_confirm_html_on() && true === $cm['use_html'] && has( $cm['body'], '<html' ), 'option on: HTML confirmation' );
ok( has( $cm['body'], 'Hi [rt-first-name],' ) && has( $cm['body'], '<strong>What happens next</strong><br>' ) && has( $cm['body'], 'Applications close November 30.' ), 'HTML confirmation: same copy, tag for CF7 to fill and escape' );
ok( has( $cm['body'], 'just reply to this email' ) && has( $cm['body'], 'Thanks again, and see you on the trail,<br>' ) && has( $cm['body'], '<strong>A copy of your application</strong><br>' ) && has( $cm['body'], 'Name: [rt-first-name] [rt-last-name]' ), 'HTML confirmation: reply line, summary and sign-off' );
$GLOBALS['t_posted']['rt-first-name'] = 'evil.example.com';
$cx = run_filter( 'wpcf7_mail_components', array( 'subject' => 'S', 'body' => $cm['body'] ), $form, new T_Mail( 'mail_2' ) );
ok( ! has( $cx['body'], 'evil.example.com' ) && is_array( $GLOBALS['arv_rt_apply_mailer'] ) && has( $GLOBALS['arv_rt_apply_mailer']['alt'], 'evil[.]example[.]com' ) && ! has( $GLOBALS['arv_rt_apply_mailer']['alt'], 'evil.example.com' ), 'HTML confirmation on: AltBody defanged like the plain mail_2' );
$GLOBALS['t_posted']['rt-first-name'] = 'Lindsay';
ok( ! has( $cm['body'], "\u{2014}" ) && no_stripes( $cm['body'] ) && ! has( $cm['body'], '<img' ), 'HTML confirmation: no em dash, no stripes, no images' );
ok( 'Reply-To: bryce@aravaiparunning.com' === $cm['additional_headers'] && $cm['subject'] === arv_rt_apply_confirm_subject(), 'HTML confirmation: same subject and Reply-To' );
ok( ! has( arv_rt_apply_confirm_html_body( '<b>Al</b>' ), '<b>Al' ), 'HTML confirmation escapes a literal name' );
run_filter( 'wpcf7_mail_components', array( 'subject' => arv_rt_apply_confirm_subject(), 'body' => 'x' ), $form, new T_Mail( 'mail_2' ) );
$pm4 = new T_PHPMailer();
$pm4->Subject = arv_rt_apply_confirm_subject();
run_filter( 'phpmailer_init', $pm4 );
ok( has( $pm4->AltBody, 'Hi Lindsay,' ) && ! $pm4->embedded, 'HTML confirmation: plain AltBody armed' );
$GLOBALS['t_opts']['arv_rt_apply_confirm_html'] = '0';
ok( false === arv_rt_apply_confirm_html_on() && false === arv_rt_apply_confirm_mail()['use_html'], 'option 0: plain again' );
unset( $GLOBALS['t_opts']['arv_rt_apply_confirm_html'] );

array_map( 'unlink', glob( $GLOBALS['t_tmp'] . '/*' ) );
@rmdir( $GLOBALS['t_tmp'] );

if ( $fails ) {
	echo "$fails of $n FAILED\n";
	exit( 1 );
}
echo "ALL PASS ($n checks)\n";
