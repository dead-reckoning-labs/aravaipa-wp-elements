<?php
/**
 * 2027 Aravaipa Racing Team application, on Contact Form 7.
 *
 * The page at /racing-team/apply/ carries the [arv_rt_apply] shortcode. The
 * form is an ordinary CF7 form (created by "wp arv-rt-apply install", its ID
 * kept in the arv_rt_apply_form_id option), so every entry lands in Flamingo,
 * Inbound Messages next to the other site forms, is mailed to the team
 * managers with the photo attached, and is also written to a private JSON
 * lines file under uploads/arv-rt-apply/ before any mail goes out. If mail or
 * Flamingo ever fails, that file still has the application.
 *
 * The questions are the RaceReach 2027 form (ApplyFormV2, validate-v2) field
 * for field, with rt- prefixed names. The race plans list is filled from the
 * 2027 races in the race store, so it follows the calendar.
 *
 * Open and close are by server time: the shortcode shows the state for now,
 * and validation rejects any submission outside the window, so a cached copy
 * of the page can never let one through. The page renders the form even
 * before the window opens (hidden) and a few lines of script swap the state
 * at the exact open and close times, so a page cached before midnight still
 * opens at midnight.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_RT_APPLY_OPENS', '2026-11-20T00:00:00-07:00' );
define( 'ARV_RT_APPLY_CLOSES', '2026-11-30T23:59:59-07:00' );
define( 'ARV_RT_APPLY_SEASON', 2027 );
define( 'ARV_RT_APPLY_MIN_AGE', 18 );
define( 'ARV_RT_APPLY_TO', 'bryce@aravaiparunning.com' );
define( 'ARV_RT_APPLY_BRYCE', 'bryce@aravaiparunning.com' );
// Upload cap per file field and the accepted types. Phone photos are shrunk in
// the browser first (assets/aravaipa-rt-apply.js); HEIC/HEIF is accepted as a
// last resort when the browser cannot convert it.
define( 'ARV_RT_APPLY_FILE_LIMIT', '10mb' ); // Tags read limit:10mb.
define( 'ARV_RT_APPLY_TYPES_PHOTO', 'jpg|jpeg|png|webp|heic|heif' );
define( 'ARV_RT_APPLY_TYPES_RESUME', 'pdf|jpg|jpeg|png|webp|heic|heif' );

/**
 * Every question, in form order (RaceReach racereach main 4e203f3).
 *
 * @return array[]
 */
function arv_rt_apply_fields() {
	return array(
		array( 'key' => 'first-name', 'name' => 'rt-first-name', 'label' => 'First name', 'type' => 'text', 'required' => true, 'section' => 'about' ),
		array( 'key' => 'last-name', 'name' => 'rt-last-name', 'label' => 'Last name', 'type' => 'text', 'required' => true, 'section' => 'about' ),
		array( 'key' => 'email', 'name' => 'rt-email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'section' => 'about' ),
		array( 'key' => 'phone', 'name' => 'rt-phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'section' => 'about' ),
		array( 'key' => 'birth-date', 'name' => 'rt-birth-date', 'label' => 'Date of birth', 'type' => 'date', 'required' => true, 'section' => 'about', 'hint' => 'You must be 18 or older on January 1, 2027.' ),
		array( 'key' => 'city', 'name' => 'rt-city', 'label' => 'City', 'type' => 'text', 'required' => true, 'section' => 'about' ),
		array( 'key' => 'state', 'name' => 'rt-state', 'label' => 'State', 'type' => 'select', 'required' => true, 'section' => 'about', 'options' => array( 'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY' ) ),
		array( 'key' => 'region', 'name' => 'rt-region', 'label' => 'Which region would you race for?', 'type' => 'radio', 'required' => true, 'section' => 'about', 'options' => array( 'West', 'Great Lakes', 'Northeast', 'Southeast', 'Other' ) ),
		array( 'key' => 'disciplines', 'name' => 'rt-disciplines', 'label' => 'Which do you race competitively?', 'type' => 'checkbox', 'required' => true, 'section' => 'about', 'options' => array( 'Trail', 'Ultra', 'Road', 'Mountain bike' ), 'hint' => 'Pick all that apply.' ),
		array( 'key' => 'returning', 'name' => 'rt-returning', 'label' => 'Have you been on the team before?', 'type' => 'radio', 'required' => true, 'section' => 'about', 'options' => array( 'Yes', 'No' ) ),
		array( 'key' => 'returning-years', 'name' => 'rt-returning-years', 'label' => 'Which years?', 'type' => 'text', 'required' => false, 'section' => 'about', 'hint' => 'If yes, for example 2025, 2026.' ),
		array( 'key' => 'resume', 'name' => 'rt-resume', 'label' => 'Racing resume', 'type' => 'textarea', 'required' => true, 'section' => 'racing', 'hint' => 'Your best results in the past 3 years: place, finish time, race name and year. One per line.' ),
		array( 'key' => 'resume-file', 'name' => 'rt-resume-file', 'label' => 'Resume file', 'type' => 'file', 'required' => false, 'section' => 'racing', 'hint' => 'A PDF or image of a results list or athlete CV, if you keep one. 10 MB max.' ),
		array( 'key' => 'ultrasignup', 'name' => 'rt-ultrasignup', 'label' => 'UltraSignup profile', 'type' => 'url', 'required' => false, 'section' => 'racing' ),
		array( 'key' => 'profiles', 'name' => 'rt-profiles', 'label' => 'Other result profiles', 'type' => 'textarea', 'required' => false, 'section' => 'racing', 'hint' => 'UTMB, Strava, Athlinks. One per line.' ),
		array( 'key' => 'goals', 'name' => 'rt-goals', 'label' => 'Your goals for 2027 and beyond', 'type' => 'textarea', 'required' => false, 'section' => 'racing', 'hint' => 'Short term goals for the season and long term goals in the sport.' ),
		array( 'key' => 'race-plans', 'name' => 'rt-race-plans', 'label' => 'Aravaipa races you plan to run', 'type' => 'checkbox', 'required' => false, 'section' => 'season', 'hint' => '2027 Aravaipa races, filled from the race calendar.' ),
		array( 'key' => 'outside-races', 'name' => 'rt-outside-races', 'label' => 'Other races and goal races for 2027', 'type' => 'textarea', 'required' => true, 'section' => 'season', 'hint' => 'Include FKTs or big projects.' ),
		array( 'key' => 'summit', 'name' => 'rt-summit', 'label' => 'Which team summit could you attend?', 'type' => 'text', 'required' => false, 'section' => 'season' ),
		array( 'key' => 'why', 'name' => 'rt-why', 'label' => 'Why do you want to be on the team?', 'type' => 'textarea', 'required' => true, 'section' => 'community', 'hint' => 'A few honest sentences beat a polished essay.' ),
		array( 'key' => 'bring', 'name' => 'rt-bring', 'label' => 'You beyond racing', 'type' => 'textarea', 'required' => false, 'section' => 'community', 'hint' => 'What would you bring to the team and your local trail community?' ),
		array( 'key' => 'instagram', 'name' => 'rt-instagram', 'label' => 'Instagram', 'type' => 'text', 'required' => false, 'section' => 'community', 'hint' => '@yourhandle' ),
		array( 'key' => 'strava', 'name' => 'rt-strava', 'label' => 'Strava', 'type' => 'text', 'required' => false, 'section' => 'community', 'hint' => 'Profile link' ),
		array( 'key' => 'socials', 'name' => 'rt-socials', 'label' => 'Other social handles', 'type' => 'textarea', 'required' => false, 'section' => 'community', 'hint' => 'With rough follower counts.' ),
		array( 'key' => 'content-links', 'name' => 'rt-content-links', 'label' => 'Links to your content', 'type' => 'textarea', 'required' => false, 'section' => 'community', 'hint' => 'Race reports, videos, posts. One per line.' ),
		array( 'key' => 'sponsors', 'name' => 'rt-sponsors', 'label' => 'Current sponsors', 'type' => 'textarea', 'required' => false, 'section' => 'community', 'hint' => 'And any kit or logo rules they have.' ),
		array( 'key' => 'support', 'name' => 'rt-support', 'label' => 'What support would help you reach your potential in 2027?', 'type' => 'textarea', 'required' => false, 'section' => 'community' ),
		array( 'key' => 'size-fit', 'name' => 'rt-size-fit', 'label' => 'Fit', 'type' => 'select', 'required' => true, 'section' => 'kit', 'options' => array( 'Women', 'Men', 'Unisex' ) ),
		array( 'key' => 'size-tee', 'name' => 'rt-size-tee', 'label' => 'Tee', 'type' => 'select', 'required' => true, 'section' => 'kit', 'options' => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' ) ),
		array( 'key' => 'size-top', 'name' => 'rt-size-top', 'label' => 'Long sleeve or jacket', 'type' => 'select', 'required' => true, 'section' => 'kit', 'options' => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' ) ),
		array( 'key' => 'size-shorts', 'name' => 'rt-size-shorts', 'label' => 'Shorts', 'type' => 'select', 'required' => true, 'section' => 'kit', 'options' => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' ) ),
		array( 'key' => 'shoe-size', 'name' => 'rt-shoe-size', 'label' => 'Shoe size (US)', 'type' => 'select', 'required' => true, 'section' => 'kit', 'options' => array( '4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12', '12.5', '13', '13.5', '14', '14.5', '15', '15.5', '16' ), 'hint' => 'Running; riding shoe size for mountain bike.' ),
		array( 'key' => 'headshot', 'name' => 'rt-headshot', 'label' => 'Photo', 'type' => 'file', 'required' => false, 'section' => 'kit', 'hint' => 'A clear, recent photo. Running shots welcome. Recommended. Phone photos are fine, 10 MB max.' ),
	);
}

/**
 * The agreements, as acceptance boxes rt-consent-<key>.
 *
 * @return array[]
 */
function arv_rt_apply_consents() {
	return array(
		array( 'key' => 'age', 'required' => true, 'text' => 'I am 18 or older on January 1, 2027.' ),
		array( 'key' => 'expectations', 'required' => true, 'text' => 'If selected, I agree to the team expectations listed on this page and to race by each event\'s rules and code of conduct.' ),
		array( 'key' => 'privacy', 'required' => true, 'text' => 'You may store this application and contact me about it. My details are only seen by staff.' ),
		array( 'key' => 'publicity', 'required' => false, 'text' => 'If selected, you may use my name, photo and race results to announce and promote the team.' ),
	);
}

/** Section headings, in order. */
function arv_rt_apply_sections() {
	return array(
		'about'     => array( '01', 'About you', 'Your details are only seen by staff.' ),
		'racing'    => array( '02', 'Your racing', 'Results from the past three years tell us the most.' ),
		'season'    => array( '03', 'Your 2027 season', 'Plans change, that is fine. Tell us what you are aiming for in 2027.' ),
		'community' => array( '04', 'You and the community', '' ),
		'kit'       => array( '05', 'Kit and photo', 'Sizes for the team kit if you are selected.' ),
		'consents'  => array( '06', 'Agreements', '' ),
	);
}

/**
 * Current time, filterable so tests can pin it.
 *
 * @return int
 */
function arv_rt_apply_now() {
	return (int) apply_filters( 'arv_rt_apply_now', time() );
}

/**
 * Open and close as timestamps (options override the defaults).
 *
 * @return int[] array( opens, closes ).
 */
function arv_rt_apply_window() {
	$opens  = strtotime( (string) get_option( 'arv_rt_apply_opens', ARV_RT_APPLY_OPENS ) );
	$closes = strtotime( (string) get_option( 'arv_rt_apply_closes', ARV_RT_APPLY_CLOSES ) );
	if ( ! $opens ) {
		$opens = strtotime( ARV_RT_APPLY_OPENS );
	}
	if ( ! $closes ) {
		$closes = strtotime( ARV_RT_APPLY_CLOSES );
	}
	return array( $opens, $closes );
}

/**
 * 'before', 'open' or 'closed' at a time.
 *
 * @param int|null $now Timestamp, default now.
 * @return string
 */
function arv_rt_apply_state( $now = null ) {
	$now = null === $now ? arv_rt_apply_now() : (int) $now;
	list( $opens, $closes ) = arv_rt_apply_window();
	if ( $now < $opens ) {
		return 'before';
	}
	return $now > $closes ? 'closed' : 'open';
}

/**
 * True while a staff test may submit outside the window.
 *
 * @return bool
 */
function arv_rt_apply_test_override() {
	$until = strtotime( (string) get_option( 'arv_rt_apply_test_until', '' ) );
	return $until && $until > arv_rt_apply_now();
}

/** The CF7 form id, or 0. */
function arv_rt_apply_form_id() {
	return (int) get_option( 'arv_rt_apply_form_id', 0 );
}

/**
 * Is this CF7 form ours.
 *
 * @param mixed $form WPCF7_ContactForm or id.
 * @return bool
 */
function arv_rt_apply_is_ours( $form ) {
	$id = is_object( $form ) && method_exists( $form, 'id' ) ? (int) $form->id() : (int) $form;
	return $id > 0 && arv_rt_apply_form_id() === $id;
}

/**
 * Quote a value for a CF7 tag.
 *
 * @param string $v Value.
 * @return string
 */
function arv_rt_apply_q( $v ) {
	return '"' . str_replace( '"', "'", (string) $v ) . '"';
}

/**
 * Accepted file types for a file field key (headshot or resume-file).
 *
 * @param string $key Field key.
 * @return string
 */
function arv_rt_apply_file_types( $key ) {
	return 'headshot' === $key ? ARV_RT_APPLY_TYPES_PHOTO : ARV_RT_APPLY_TYPES_RESUME;
}

/**
 * The CF7 tag for one field.
 *
 * @param array $f Field.
 * @return string
 */
function arv_rt_apply_tag( $f ) {
	$name = 'rt-' . $f['key'];
	$star = $f['required'] ? '*' : '';
	$id   = 'id:' . $name;
	switch ( $f['type'] ) {
		case 'textarea':
			$rows = in_array( $f['key'], array( 'resume' ), true ) ? '7' : ( in_array( $f['key'], array( 'profiles', 'socials' ), true ) ? '2' : '4' );
			return '[textarea' . $star . ' ' . $name . ' ' . $id . ' x' . $rows . ']';
		case 'select':
			$opts = '';
			foreach ( $f['options'] as $o ) {
				$opts .= ' ' . arv_rt_apply_q( $o );
			}
			return '[select' . $star . ' ' . $name . ' ' . $id . ' first_as_label "Choose"' . $opts . ']';
		case 'radio':
			$opts = '';
			foreach ( $f['options'] as $o ) {
				$opts .= ' ' . arv_rt_apply_q( $o );
			}
			return '[radio ' . $name . ' use_label_element' . $opts . ']';
		case 'checkbox':
			$opts = '';
			foreach ( ( isset( $f['options'] ) ? $f['options'] : array( 'None' ) ) as $o ) {
				$opts .= ' ' . arv_rt_apply_q( $o );
			}
			return '[checkbox' . $star . ' ' . $name . ' use_label_element' . $opts . ']';
		case 'file':
			return '[file ' . $name . ' ' . $id . ' limit:' . ARV_RT_APPLY_FILE_LIMIT . ' filetypes:' . arv_rt_apply_file_types( $f['key'] ) . ']';
		case 'email':
		case 'tel':
		case 'date':
		case 'url':
			return '[' . $f['type'] . $star . ' ' . $name . ' ' . $id . ']';
		default:
			$ph = 'instagram' === $f['key'] ? ' placeholder "@yourhandle"' : ( 'strava' === $f['key'] ? ' placeholder "Profile link"' : '' );
			$ac = array(
				'first-name' => 'given-name',
				'last-name'  => 'family-name',
				'city'       => 'address-level2',
			);
			$auto = isset( $ac[ $f['key'] ] ) ? ' autocomplete:' . $ac[ $f['key'] ] : '';
			return '[text' . $star . ' ' . $name . ' ' . $id . $auto . $ph . ']';
	}
}

/**
 * The whole CF7 form template, built from the field list.
 *
 * @return string
 */
function arv_rt_apply_form_template() {
	$by = array();
	foreach ( arv_rt_apply_fields() as $f ) {
		$by[ $f['section'] ][] = $f;
	}
	$out = '<div class="rt-form">' . "\n";
	foreach ( arv_rt_apply_sections() as $key => $s ) {
		$out .= '<section class="rt-sec"><div class="rt-sec-head"><span class="rt-n">' . $s[0] . '</span><h3>' . $s[1] . '</h3>' . ( $s[2] ? '<p>' . $s[2] . '</p>' : '' ) . '</div><div class="rt-sec-body">' . "\n";
		if ( 'consents' === $key ) {
			foreach ( arv_rt_apply_consents() as $c ) {
				$out .= '<div class="rt-f rt-consent">[acceptance rt-consent-' . $c['key'] . ( $c['required'] ? '' : ' optional' ) . ']' . $c['text'] . ( $c['required'] ? '' : ' <span class="rt-opt">Optional</span>' ) . '[/acceptance]</div>' . "\n";
			}
		} else {
			foreach ( isset( $by[ $key ] ) ? $by[ $key ] : array() as $f ) {
				$wide = in_array( $f['type'], array( 'textarea', 'checkbox', 'radio', 'file' ), true ) ? ' rt-wide' : '';
				$req  = $f['required'] ? '<span class="rt-req">*</span>' : ' <span class="rt-opt">Optional</span>';
				$lab  = in_array( $f['type'], array( 'radio', 'checkbox' ), true ) ? '<span class="rt-l">' . $f['label'] . $req . '</span>' : '<label class="rt-l" for="rt-' . $f['key'] . '">' . $f['label'] . $req . '</label>';
				$hint = ! empty( $f['hint'] ) ? '<span class="rt-h">' . $f['hint'] . '</span>' : '';
				$out .= '<div class="rt-f rt-f-' . $f['key'] . $wide . '">' . $lab . $hint . arv_rt_apply_tag( $f ) . '</div>' . "\n";
			}
		}
		$out .= '</div></section>' . "\n";
	}
	$out .= '<div class="rt-submit">[turnstile]' . "\n" . '[submit "Submit application"]</div>' . "\n</div>";
	return $out;
}

/** Mail recipients. */
function arv_rt_apply_recipients() {
	$to = array( ARV_RT_APPLY_TO );
	$b  = (string) get_option( 'arv_rt_apply_bryce', ARV_RT_APPLY_BRYCE );
	if ( $b && is_email( $b ) ) {
		$to[] = $b;
	}
	return implode( ', ', $to );
}

/**
 * The CF7 mail property.
 *
 * @return array
 */
function arv_rt_apply_mail() {
	$body = "New 2027 Aravaipa Racing Team application.\n\n";
	foreach ( arv_rt_apply_fields() as $f ) {
		if ( 'file' === $f['type'] ) {
			$body .= $f['label'] . ": attached if given\n";
			continue;
		}
		$body .= $f['label'] . ': [rt-' . $f['key'] . "]\n";
	}
	$body .= "\nAgreements\n";
	foreach ( arv_rt_apply_consents() as $c ) {
		$body .= $c['text'] . ' [rt-consent-' . $c['key'] . "]\n";
	}
	$body .= "\nSent from [_url] on [_date] at [_time].\nEvery application is also kept in WP Admin, Flamingo, Inbound Messages.";
	return array(
		'active'             => true,
		'subject'            => '2027 Racing Team application: [rt-first-name] [rt-last-name]',
		'sender'             => 'Aravaipa Running <wordpress@aravaiparunning.com>',
		'recipient'          => arv_rt_apply_recipients(),
		'body'               => $body,
		'additional_headers' => 'Reply-To: [rt-email]',
		'attachments'        => "[rt-headshot]\n[rt-resume-file]",
		'use_html'           => false,
		'exclude_blank'      => false,
	);
}

/** CF7 messages that differ from the defaults. */
function arv_rt_apply_messages() {
	return array(
		'mail_sent_ok'     => 'Thank you for applying to the 2027 Aravaipa Racing Team. Your application came through, and a confirmation email is on its way.',
		'mail_sent_ng'     => 'Your application is saved, but the notice to the team did not send. No need to apply again.',
		'validation_error' => 'Some answers need a look. The list next to Submit shows each one.',
		'spam'             => 'We could not confirm you are human, so this did not go through. Press Submit again. If it keeps happening, email your answers to bryce@aravaiparunning.com.',
		'upload_failed'    => 'The file did not upload. Try again, or email it to bryce@aravaiparunning.com.',
		'upload_file_type_invalid' => 'That file type is not accepted. Use a photo (JPG, PNG, WEBP or HEIC) or a PDF.',
		'upload_file_too_large'    => 'That file is over 10 MB. Try a smaller photo, or email it to bryce@aravaiparunning.com.',
	);
}

/**
 * Applicant confirmation email (CF7 Mail (2)). Written into the stored form by
 * the install command and applied at runtime for our form too, so it works
 * before a reinstall. Plain text, Reply-To Bryce (team manager).
 *
 * @return array CF7 mail property.
 */
function arv_rt_apply_confirm_mail() {
	return array(
		'active'             => true,
		'subject'            => arv_rt_apply_confirm_subject(),
		'sender'             => 'Aravaipa Running <wordpress@aravaiparunning.com>',
		'recipient'          => '[rt-email]',
		'body'               => arv_rt_apply_confirm_body( '[rt-first-name]', false ),
		'additional_headers' => 'Reply-To: bryce@aravaiparunning.com',
		'attachments'        => '',
		'use_html'           => false,
		'exclude_blank'      => false,
	);
}

/** Confirmation subject. */
function arv_rt_apply_confirm_subject() {
	return 'We got your 2027 Aravaipa Racing Team application';
}

/**
 * Confirmation body.
 *
 * @param string $first    First name or a CF7 tag.
 * @param bool   $followup True for the one-time note to people who applied before confirmations existed.
 * @return string
 */
function arv_rt_apply_confirm_body( $first, $followup = false ) {
	$hi = '' !== trim( (string) $first ) ? 'Hi ' . trim( (string) $first ) . ',' : 'Hi,';
	if ( $followup ) {
		$open = "Thanks for applying to the 2027 Aravaipa Racing Team. We weren't sending confirmations when you applied, so here it is: your application came through and it's in front of our team managers. If you saw an error while submitting and tried more than once, no worries, we have you.";
		return $hi . "\n\n" . $open . "\n\nWhat's next: applications close November 30. Our team managers read every application, and we'll be in touch by email once the 2027 team is set.\n\nNeed to add or change something? Just reply to this email.\n\nSee you out there,\nAravaipa Running\n";
	}
	return $hi . "\n\n"
		. "Thank you for applying to the 2027 Aravaipa Racing Team. We're grateful you want to race with us, and we're glad to confirm your application came through.\n\n"
		. "What happens next\n"
		. "Applications close November 30. After the deadline, our team managers review every application, and we'll share decisions with everyone by email.\n\n"
		. "If you'd like to add or change anything, just reply to this email and it will reach our team.\n\n"
		. arv_rt_apply_confirm_summary()
		. "\nThanks again, and see you on the trail,\nAravaipa Running\n";
}

/**
 * The short copy of the application in the confirmation email (CF7 tags).
 *
 * @return string
 */
function arv_rt_apply_confirm_summary() {
	return "A copy of your application\n"
		. "Name: [rt-first-name] [rt-last-name]\n"
		. "Region: [rt-region]\n"
		. "Home: [rt-city], [rt-state]\n"
		. "Disciplines: [rt-disciplines]\n"
		. "On the team before: [rt-returning]\n"
		. "Aravaipa races you plan to run: [rt-race-plans]\n"
		. "Other 2027 races and goals: [rt-outside-races]\n"
		. "Kit: [rt-size-fit] fit, tee [rt-size-tee], top [rt-size-top], shorts [rt-size-shorts], shoe [rt-shoe-size]\n"
		. "Photo and resume file: included with your application if you added them\n\n"
		. "Racing resume:\n[rt-resume]\n\n"
		. "Why you want to be on the team:\n[rt-why]\n";
}

/**
 * CF7 Mail (2) for our form: the applicant confirmation.
 *
 * @param mixed $value Stored mail_2.
 * @param mixed $form  WPCF7_ContactForm.
 * @return mixed
 */
function arv_rt_apply_mail2_prop( $value, $form = null ) {
	return arv_rt_apply_is_ours( $form ) ? arv_rt_apply_confirm_mail() : $value;
}
add_filter( 'wpcf7_contact_form_property_mail_2', 'arv_rt_apply_mail2_prop', 10, 2 );

/**
 * Full thank-you screen that replaces the form after a sent application.
 * Rendered visible inside the form on a no-JS submit (so the form's own
 * #wpcf7-f... anchor lands on it), or hidden beside the form for the script.
 * The page already ends with a Meet the current team link, so the panel has none.
 *
 * @param bool   $visible Shown now (no-JS submit) or kept hidden for the script.
 * @param string $first   Applicant first name, if known.
 * @return string
 */
function arv_rt_apply_thanks_html( $visible = false, $first = '' ) {
	$first = trim( preg_replace( '/\s+/', ' ', (string) $first ) );
	if ( function_exists( 'mb_substr' ) ) {
		$first = mb_substr( $first, 0, 60 );
	}
	$name = '' !== $first ? ', ' . htmlspecialchars( $first, ENT_QUOTES, 'UTF-8' ) : '';
	return '<div class="rt-thanks" id="rt-thanks" role="status" aria-live="polite" tabindex="-1"' . ( $visible ? '' : ' hidden' ) . '>'
		. '<p class="rt-thanks-kicker">Application received</p>'
		. '<h2 class="rt-thanks-title">Thank you<span class="rt-thanks-name">' . $name . '</span>.</h2>'
		. '<p>We&rsquo;re grateful you applied for the 2027 Aravaipa Racing Team. Your application came through, and a confirmation email is on its way to you now.</p>'
		. '<p><strong>What happens next:</strong> applications close November 30. After the deadline, our team managers review every application, and we&rsquo;ll share decisions by email.</p>'
		. '<p>Want to add or change something? Reply to the confirmation email or write to <a href="mailto:bryce@aravaiparunning.com">bryce@aravaiparunning.com</a>.</p>'
		. '</div>';
}

/**
 * The browser side of the thank-you screen. With CF7's script on the page
 * the form never reloads: on wpcf7mailsent fill in the first name, hide the
 * form and bring the panel into view. After a no-JS submit the server already
 * rendered the panel (data-sent="server"); just scroll to it.
 *
 * @return string Inline script.
 */
function arv_rt_apply_thanks_js() {
	return '<script>(function(){var r=document.getElementById("rt-apply");if(!r)return;'
		. 'function go(t){try{t.scrollIntoView({behavior:"smooth",block:"start"});}catch(x){t.scrollIntoView();}try{t.focus({preventScroll:true});}catch(y){}}'
		. 'document.addEventListener("wpcf7mailsent",function(e){if(!e.target||!r.contains(e.target))return;var t=document.getElementById("rt-thanks");if(!t)return;var f="";((e.detail&&e.detail.inputs)||[]).forEach(function(i){if(i&&i.name==="rt-first-name"){f=String(i.value||"").replace(/\s+/g," ").trim().slice(0,60);}});var n=t.querySelector(".rt-thanks-name");if(n){n.textContent=f?", "+f:"";}r.setAttribute("data-sent","1");var o=r.querySelector(".rt-state-open");if(o){o.hidden=true;}t.hidden=false;go(t);});'
		. 'if(r.getAttribute("data-sent")==="server"){var s=document.getElementById("rt-thanks");if(s){setTimeout(function(){go(s);},60);}}'
		. '})();</script>';
}

/** True when this request is a no-JS submit of our form that was sent. */
function arv_rt_apply_just_sent() {
	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return false;
	}
	$sub = WPCF7_Submission::get_instance();
	if ( ! $sub || ! method_exists( $sub, 'get_status' ) ) {
		return false;
	}
	$cf = method_exists( $sub, 'get_contact_form' ) ? $sub->get_contact_form() : null;
	return arv_rt_apply_is_ours( $cf ) && 'mail_sent' === $sub->get_status();
}

/**
 * Our friendly copy at display time too, so the stored form needs no reinstall.
 *
 * @param string $message Message.
 * @param string $status  CF7 message key.
 * @return string
 */
function arv_rt_apply_display_message( $message, $status = '' ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! arv_rt_apply_is_ours( WPCF7_ContactForm::get_current() ) ) {
		return $message;
	}
	$ours = arv_rt_apply_messages();
	return isset( $ours[ $status ] ) ? $ours[ $status ] : $message;
}
add_filter( 'wpcf7_display_message', 'arv_rt_apply_display_message', 10, 2 );

/** CF7 additional settings: what Flamingo shows in its list. */
function arv_rt_apply_additional_settings() {
	return "flamingo_email: [rt-email]\nflamingo_name: [rt-first-name] [rt-last-name]\nflamingo_subject: 2027 Racing Team application\nacceptance_as_validation: on";
}

/**
 * Required agreements are checked like any other answer. Without this, CF7's
 * script keeps Submit disabled until every box is ticked, with no word why.
 * Applied at runtime so the stored form needs no reinstall.
 *
 * @param string $value Additional settings.
 * @param mixed  $form  WPCF7_ContactForm.
 * @return string
 */
function arv_rt_apply_additional_prop( $value, $form = null ) {
	if ( ! arv_rt_apply_is_ours( $form ) || false !== strpos( (string) $value, 'acceptance_as_validation' ) ) {
		return $value;
	}
	return rtrim( (string) $value ) . "\nacceptance_as_validation: on";
}
add_filter( 'wpcf7_contact_form_property_additional_settings', 'arv_rt_apply_additional_prop', 10, 2 );

/**
 * 2027 races for the race plans list: "Name, Mon D".
 *
 * @return string[]
 */
function arv_rt_apply_race_options() {
	$out = array();
	if ( ! function_exists( 'arv_race_store_get' ) ) {
		return $out;
	}
	foreach ( (array) arv_race_store_get() as $race ) {
		$iso = isset( $race['iso'] ) ? (string) $race['iso'] : '';
		if ( 0 !== strpos( $iso, ARV_RT_APPLY_SEASON . '-' ) ) {
			continue;
		}
		$ts    = strtotime( substr( $iso, 0, 10 ) . 'T12:00:00Z' );
		$label = trim( (string) $race['name'] ) . ', ' . gmdate( 'M j', $ts );
		$label = str_replace( '"', "'", $label );
		if ( ! in_array( $label, $out, true ) ) {
			$out[] = $label;
		}
	}
	return $out;
}

/**
 * Fill the race plans checkboxes while CF7 renders or scans our form.
 *
 * @param array $tag     Tag.
 * @param bool  $replace Rendering.
 * @return array
 */
function arv_rt_apply_form_tag( $tag, $replace ) {
	if ( is_array( $tag ) && isset( $tag['name'] ) && in_array( $tag['name'], array( 'rt-headshot', 'rt-resume-file' ), true ) ) {
		return arv_rt_apply_file_tag( $tag );
	}
	if ( ! is_array( $tag ) || ! isset( $tag['name'] ) || 'rt-race-plans' !== $tag['name'] ) {
		return $tag;
	}
	$values = arv_rt_apply_race_options();
	if ( ! $values ) {
		$values = array( 'The 2027 calendar is not posted yet' );
	}
	$tag['raw_values'] = $values;
	$tag['values']     = $values;
	$tag['labels']     = $values;
	if ( class_exists( 'WPCF7_Pipes' ) ) {
		$tag['pipes'] = new WPCF7_Pipes( $values );
	}
	return $tag;
}
add_filter( 'wpcf7_form_tag', 'arv_rt_apply_form_tag', 10, 2 );

/**
 * The file limit and types for our two file fields, at render and validation
 * time, so the stored form (installed with the old 4 MB cap) needs no hand edit.
 * Only on our form: CF7 sets the current form while it renders or validates.
 *
 * @param array $tag Tag.
 * @return array
 */
function arv_rt_apply_file_tag( $tag ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! arv_rt_apply_is_ours( WPCF7_ContactForm::get_current() ) ) {
		return $tag;
	}
	$key  = 'rt-headshot' === $tag['name'] ? 'headshot' : 'resume-file';
	$opts = array();
	foreach ( isset( $tag['options'] ) ? (array) $tag['options'] : array() as $o ) {
		if ( 0 === strpos( $o, 'limit:' ) || 0 === strpos( $o, 'filetypes:' ) ) {
			continue;
		}
		$opts[] = $o;
	}
	$opts[]         = 'limit:' . ARV_RT_APPLY_FILE_LIMIT;
	$opts[]         = 'filetypes:' . arv_rt_apply_file_types( $key );
	$tag['options'] = $opts;
	return $tag;
}

/**
 * Turnstile on our form refreshes expired tokens by itself and retries.
 * The attributes go on before api.js renders the widget.
 *
 * @param string $html Form elements HTML.
 * @return string
 */
function arv_rt_apply_form_elements( $html ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! arv_rt_apply_is_ours( WPCF7_ContactForm::get_current() ) ) {
		return $html;
	}
	$html = arv_rt_apply_turnstile_attrs( $html );
	if ( arv_rt_apply_just_sent() ) {
		$html = arv_rt_apply_thanks_html( true, arv_rt_apply_posted( 'rt-first-name' ) ) . $html;
	}
	return $html;
}
add_filter( 'wpcf7_form_elements', 'arv_rt_apply_form_elements', 20, 1 );

/**
 * Add data-refresh-expired="auto" and data-retry="auto" to the Turnstile div.
 *
 * @param string $html HTML.
 * @return string
 */
function arv_rt_apply_turnstile_attrs( $html ) {
	return preg_replace_callback(
		'/<div\b[^>]*\bcf-turnstile\b[^>]*>/',
		function ( $m ) {
			$t = $m[0];
			foreach ( array( 'data-refresh-expired' => 'auto', 'data-refresh-timeout' => 'auto', 'data-retry' => 'auto' ) as $a => $v ) {
				if ( false === strpos( $t, $a . '=' ) ) {
					$t = substr( $t, 0, -1 ) . ' ' . $a . '="' . $v . '">';
				}
			}
			return $t;
		},
		(string) $html
	);
}

/**
 * A posted value as a string.
 *
 * @param string $name Field name.
 * @return string
 */
function arv_rt_apply_posted( $name ) {
	$v = null;
	if ( class_exists( 'WPCF7_Submission' ) ) {
		$s = WPCF7_Submission::get_instance();
		if ( $s ) {
			$v = $s->get_posted_data( $name );
		}
	}
	if ( null === $v && isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$v = wp_unslash( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidationSanitization.InputNotSanitized
	}
	if ( is_array( $v ) ) {
		$v = implode( ', ', array_map( 'strval', $v ) );
	}
	return trim( (string) $v );
}

/**
 * Find a scanned tag by name (needed to attach an error to a field).
 *
 * @param object $form CF7 form.
 * @param string $name Name.
 * @return mixed Tag or null.
 */
function arv_rt_apply_find_tag( $form, $name ) {
	if ( ! is_object( $form ) || ! method_exists( $form, 'scan_form_tags' ) ) {
		return null;
	}
	$tags = $form->scan_form_tags( array( 'name' => $name ) );
	return $tags ? $tags[0] : null;
}

/**
 * Server checks for our form only: the window, the age, returning years.
 *
 * @param object $result WPCF7_Validation.
 * @param array  $tags   Tags.
 * @return object
 */
function arv_rt_apply_validate( $result, $tags = array() ) {
	$form = class_exists( 'WPCF7_ContactForm' ) ? WPCF7_ContactForm::get_current() : null;
	if ( ! $form || ! arv_rt_apply_is_ours( $form ) ) {
		return $result;
	}
	$first = arv_rt_apply_find_tag( $form, 'rt-first-name' );

	$state = arv_rt_apply_state();
	if ( 'open' !== $state && ! arv_rt_apply_test_override() ) {
		$msg = 'before' === $state ? 'Applications open November 20.' : 'Applications for the 2027 team are closed.';
		if ( $first ) {
			$result->invalidate( $first, $msg );
		}
		return $result;
	}

	$dob = arv_rt_apply_posted( 'rt-birth-date' );
	if ( '' !== $dob ) {
		$cut = ( ARV_RT_APPLY_SEASON - ARV_RT_APPLY_MIN_AGE ) . '-01-01';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dob ) || $dob > $cut ) {
			$t = arv_rt_apply_find_tag( $form, 'rt-birth-date' );
			if ( $t ) {
				$result->invalidate( $t, 'You must be ' . ARV_RT_APPLY_MIN_AGE . ' or older on January 1, ' . ARV_RT_APPLY_SEASON . '. If you are younger, email the team managers.' );
			}
		}
	}
	// Which years stays optional, as its label says: returning members were blocked by a hidden rule.
	return $result;
}
add_filter( 'wpcf7_validate', 'arv_rt_apply_validate', 20, 2 );

/** Private folder for photos and the entry log; created with deny-all files. */
function arv_rt_apply_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'arv-rt-apply';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	return $dir;
}

/** Last saved entry in this request, for the Flamingo link. */
$GLOBALS['arv_rt_apply_last'] = null;

/** Sample files from the staff test only (name => path). */
$GLOBALS['arv_rt_apply_test_files'] = array();

/**
 * Before mail: keep the photo and resume file, and write the entry to the
 * private log, so the application survives even if mail and Flamingo fail.
 *
 * @param object $form CF7 form.
 */
function arv_rt_apply_before_mail( $form ) {
	if ( ! arv_rt_apply_is_ours( $form ) || ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}
	$s = WPCF7_Submission::get_instance();
	if ( ! $s ) {
		return;
	}
	$dir   = arv_rt_apply_dir();
	$email = strtolower( arv_rt_apply_posted( 'rt-email' ) );
	$stamp = gmdate( 'Ymd-His', arv_rt_apply_now() );
	$slug  = sanitize_file_name( strtolower( arv_rt_apply_posted( 'rt-first-name' ) . '-' . arv_rt_apply_posted( 'rt-last-name' ) ) );
	$files = array();
	foreach ( (array) $s->uploaded_files() as $name => $paths ) {
		foreach ( (array) $paths as $i => $path ) {
			if ( ! $path || ! is_file( $path ) ) {
				continue;
			}
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$dest = $dir . '/' . $stamp . '-' . $slug . '-' . sanitize_key( $name ) . ( $i ? '-' . $i : '' ) . '.' . $ext;
			if ( @copy( $path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$files[ $name ] = $dest;
			}
		}
	}
	// The staff test (wp arv-rt-apply test) cannot upload through PHP's
	// upload checks, so it hands its sample photo over here instead.
	foreach ( (array) $GLOBALS['arv_rt_apply_test_files'] as $name => $path ) {
		if ( is_file( $path ) ) {
			$dest = $dir . '/' . $stamp . '-' . $slug . '-' . sanitize_key( $name ) . '.' . strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( @copy( $path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$files[ $name ] = $dest;
			}
		}
	}
	$entry = array(
		'at'      => gmdate( 'c', arv_rt_apply_now() ),
		'form_id' => arv_rt_apply_form_id(),
		'email'   => $email,
		'fields'  => array(),
		'files'   => $files,
	);
	foreach ( arv_rt_apply_fields() as $f ) {
		if ( 'file' !== $f['type'] ) {
			$entry['fields'][ 'rt-' . $f['key'] ] = arv_rt_apply_posted( 'rt-' . $f['key'] );
		}
	}
	foreach ( arv_rt_apply_consents() as $c ) {
		$entry['fields'][ 'rt-consent-' . $c['key'] ] = arv_rt_apply_posted( 'rt-consent-' . $c['key'] ) ? 'yes' : 'no';
	}
	file_put_contents( $dir . '/entries.jsonl', wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	// For the RaceReach push only (kept in memory, not in the log line above):
	// the applicant IP and the checkbox answers as real lists.
	$entry['ip']    = function_exists( 'arv_rt_apply_rr_request_ip' ) ? arv_rt_apply_rr_request_ip( $s ) : '';
	$entry['lists'] = array();
	foreach ( array( 'rt-disciplines', 'rt-race-plans' ) as $k ) {
		$raw = $s->get_posted_data( $k );
		if ( is_array( $raw ) ) {
			$entry['lists'][ $k ] = $raw;
		}
	}
	$GLOBALS['arv_rt_apply_last'] = $entry;
}
add_action( 'wpcf7_before_send_mail', 'arv_rt_apply_before_mail', 5, 1 );

/**
 * After Flamingo stores the entry, record the saved file paths on it.
 *
 * @param array $result CF7 result with flamingo_inbound_id.
 */
function arv_rt_apply_after_flamingo( $result ) {
	$e = $GLOBALS['arv_rt_apply_last'];
	if ( ! $e || empty( $result['flamingo_inbound_id'] ) ) {
		return;
	}
	update_post_meta( (int) $result['flamingo_inbound_id'], '_arv_rt_files', $e['files'] );
	update_post_meta( (int) $result['flamingo_inbound_id'], '_arv_rt_email', $e['email'] );
	if ( ! empty( $e['ip'] ) ) {
		update_post_meta( (int) $result['flamingo_inbound_id'], '_arv_rt_ip', $e['ip'] );
	}
	$GLOBALS['arv_rt_apply_last']['flamingo_id'] = (int) $result['flamingo_inbound_id'];
}
add_action( 'wpcf7_after_flamingo', 'arv_rt_apply_after_flamingo', 10, 1 );

/** Styles: the site's type, plain fields, no cards, no shadows. */
function arv_rt_apply_css() {
	// The page around the form mirrors the team page's own styles (scoped there to .page-id-79463).
	return '.rt-apply[data-sent] .rt-form,.rt-apply[data-sent] .wpcf7-response-output,.rt-cf7.sent .rt-form,.rt-cf7.sent .wpcf7-response-output,.rt-apply[data-sent] .rt-summary,.rt-apply[data-sent] .rt-alert{display:none!important}'
		. '.rt-thanks{padding:40px 32px;background:#f4f1ea;margin:8px 0 24px;scroll-margin-top:120px}.rt-thanks:focus{outline:none}.rt-thanks-kicker{font-size:13px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:#b3261e;margin:0 0 8px}.rt-thanks-title{font-size:34px;line-height:1.1;margin:0 0 16px}.rt-thanks p{font-size:17px;line-height:1.55;max-width:640px}.rt-thanks-link{display:inline-block;margin-top:8px;padding:12px 22px;background:#b3261e;color:#fff!important;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.06em;font-size:14px}@media(max-width:600px){.rt-thanks{padding:28px 20px}.rt-thanks-title{font-size:27px}}.arv-rt-page{text-align:left}.arv-rt-page .arv-team-h1{text-align:center;margin:0 0 1.5rem}'
		. '.arv-rt-page .arv-team-intro{max-width:760px;margin-left:auto;margin-right:auto;text-align:center}'
		. '.arv-rt-page .arv-team-sponsors{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:2.5rem;margin:1.75rem auto 2.25rem}'
		. '.arv-rt-page .arv-team-sponsors img{display:block;width:auto;max-width:100%;border:0;opacity:.9}'
		. '.arv-rt-page .arv-team-sponsor--hoka img{height:42px}.arv-rt-page .arv-team-sponsor--athletico img{height:44px}'
		. '.arv-rt-cols{display:grid;grid-template-columns:1fr 1fr;gap:48px;max-width:1080px;margin:0 auto 24px}'
		. '.arv-rt-col h2{font-size:24px;margin:0 0 12px}.arv-rt-col ul{margin:0;padding-left:1.25rem}.arv-rt-col li{margin-bottom:.5rem}'
		. '.arv-rt-back{text-align:center;margin:40px 0 0;font-weight:600}'
		. '@media (max-width:767px){.arv-rt-cols{grid-template-columns:1fr;gap:24px}}'
		. '@media (max-width:480px){.arv-rt-page .arv-team-sponsors{gap:1.5rem;flex-wrap:nowrap}.arv-rt-page .arv-team-sponsor--hoka img{height:34px}.arv-rt-page .arv-team-sponsor--athletico img{height:36px}}'
		. '.rt-apply{max-width:1080px;margin:0 auto;text-align:left}'
		. '.rt-apply [hidden]{display:none!important}'
		. '.rt-state{font-size:20px;line-height:1.5;margin:24px 0}'
		. '.rt-sec{display:grid;grid-template-columns:minmax(0,4fr) minmax(0,8fr);gap:48px;padding:40px 0}'
		. '.rt-sec-head h3{margin:4px 0 8px;font-size:26px;line-height:1.15;text-transform:none}'
		. '.rt-sec-head p{margin:0;color:#666;font-size:15px;line-height:1.5}'
		. '.rt-n{font-size:14px;color:#999;font-variant-numeric:tabular-nums}'
		. '.rt-sec-body{display:grid;grid-template-columns:1fr 1fr;gap:20px 24px;min-width:0}'
		. '.rt-f{min-width:0}.rt-wide,.rt-consent{grid-column:1/-1}'
		. '.rt-l{display:block;font-weight:600;font-size:15px;margin:0 0 2px;color:#111}'
		. '.rt-h{display:block;font-size:13px;color:#666;margin:0 0 6px;line-height:1.4}'
		. '.rt-req{color:#d71f20;margin-left:3px}.rt-opt{font-weight:400;font-size:12px;color:#999;margin-left:6px}'
		. '.rt-form input[type=text],.rt-form input[type=email],.rt-form input[type=tel],.rt-form input[type=date],.rt-form input[type=url],.rt-form select,.rt-form textarea{width:100%;box-sizing:border-box;padding:10px 12px;font-size:16px;border:1px solid #c8c8c8;border-radius:0;background:#fff;margin:0}'
		. '.rt-form .wpcf7-checkbox,.rt-form .wpcf7-radio{display:flex;flex-wrap:wrap;gap:8px 18px}'
		. '.rt-form .wpcf7-list-item{margin:0}.rt-form .wpcf7-list-item label{display:inline-flex;gap:8px;align-items:center;min-height:32px;font-size:15px}'
		. '.rt-f-race-plans .wpcf7-checkbox{max-height:420px;overflow-y:auto;border:1px solid #ddd;padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:4px 16px}'
		. '.rt-consent .wpcf7-list-item label{align-items:flex-start;font-size:15px;line-height:1.5}'
		. '.rt-submit{padding:32px 0 8px}'
		. '.rt-submit .wpcf7-submit{background:#d71f20;color:#fff;border:0;border-radius:0;padding:14px 36px;font-size:16px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;cursor:pointer;margin-top:12px}'
		. '.rt-region-hint{display:block;margin:10px 0 0;font-size:15px;line-height:1.5;color:#272727}.rt-region-hint a{font-weight:600;margin-left:6px;text-decoration:underline;text-underline-offset:3px}.rt-region-hint__sw{display:inline-block;width:13px;height:13px;margin-right:8px;vertical-align:-1px}.rt-region-hint--out{color:#5f5f5f}'
		. '@media (max-width:767px){.rt-sec{grid-template-columns:1fr;gap:16px;padding:28px 0}.rt-sec-body{grid-template-columns:1fr}.rt-submit .wpcf7-submit{width:100%}}'
		. arv_rt_apply_harden_css();
}

/**
 * Styles for the error list, highlighted fields and notes added by
 * assets/aravaipa-rt-apply.js. Full borders or tints only, never an edge stripe.
 *
 * @return string
 */
function arv_rt_apply_harden_css() {
	return '.rt-form .rt-f{scroll-margin-top:140px}'
		. '.rt-form .rt-f.rt-bad{background:#fff3f3;outline:2px solid #d71f20;outline-offset:6px}'
		. '.rt-summary,.rt-alert{border:1px solid #e3b4b4;background:#fff6f6;color:#272727;padding:14px 16px;margin:0 0 16px;font-size:15px;line-height:1.5}'
		. '.rt-summary strong,.rt-alert strong{display:block;margin:0 0 6px}'
		. '.rt-summary ul{margin:0;padding-left:1.25rem}.rt-summary li{margin:2px 0}'
		. '.rt-summary a,.rt-alert a{font-weight:600;text-decoration:underline;text-underline-offset:3px}'
		. '.rt-notice{border:1px solid #d5d5d5;background:#f6f6f6;color:#272727;padding:10px 14px;margin:0 0 16px;font-size:15px;line-height:1.5}'
		. '.rt-photo-note{display:block;margin:8px 0 0;font-size:14px;color:#3d6b35}.rt-photo-note--busy{color:#666}.rt-photo-note--warn{color:#8a5a00}';
}

/**
 * [arv_rt_apply]: the state for now, plus the form when it can open.
 *
 * @return string
 */
function arv_rt_apply_shortcode() {
	list( $opens, $closes ) = arv_rt_apply_window();
	$state = arv_rt_apply_state();
	$id    = arv_rt_apply_form_id();
	$form  = '';
	if ( 'closed' !== $state && $id && function_exists( 'wpcf7_contact_form' ) ) {
		$cf = wpcf7_contact_form( $id );
		if ( $cf ) {
			$form = $cf->form_html( array( 'html_class' => 'rt-cf7' ) );
		}
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	$h  = '<style>' . arv_rt_apply_css() . arv_rt_apply_region_css() . '</style>';
	$fail = function_exists( 'rest_url' ) ? rest_url( 'aravaipa/v1/rt-apply-fail' ) : '';
	$sent = $form && arv_rt_apply_just_sent();
	$h   .= '<div class="rt-apply" id="rt-apply"' . ( $sent ? ' data-sent="server"' : '' ) . ' data-state="' . esc_attr( $state ) . '" data-opens="' . (int) $opens . '" data-closes="' . (int) $closes . '"' . ( $fail ? ' data-fail-url="' . esc_attr( $fail ) . '"' : '' ) . '>';
	$h .= '<p class="rt-state rt-state-before"' . ( 'before' === $state ? '' : ' hidden' ) . '>Applications open November 20.</p>';
	$h .= '<p class="rt-state rt-state-closed"' . ( 'closed' === $state ? '' : ' hidden' ) . '>Applications for the 2027 team are closed.</p>';
	if ( $form ) {
		// After a no-JS submit the panel is already inside the form (form_elements filter).
		$h .= $sent ? '' : arv_rt_apply_thanks_html( false );
		$h .= '<div class="rt-state-open"' . ( 'open' === $state ? '' : ' hidden' ) . '>' . $form . '</div>';
	} elseif ( 'open' === $state ) {
		$h .= '<p class="rt-state">The application form is not available right now. Email bryce@aravaiparunning.com and we will help.</p>';
	}
	$h .= '</div>';
	if ( $form ) {
		$h .= arv_rt_apply_region_hint_js();
		$h .= arv_rt_apply_thanks_js();
		arv_rt_apply_enqueue();
	}
	$h .= '<script>(function(){var r=document.getElementById("rt-apply");if(!r)return;function t(){var n=Date.now()/1000,o=+r.dataset.opens,c=+r.dataset.closes,s=n<o?"before":(n>c?"closed":"open");var was=r.dataset.state;r.dataset.state=s;["before","closed","open"].forEach(function(k){var e=r.querySelector(".rt-state-"+k);if(e)e.hidden=(k!==s)||(k==="open"&&r.dataset.sent==="1");});if(was!==s&&s==="open"&&window.turnstile){r.querySelectorAll(".wpcf7-turnstile,.cf-turnstile").forEach(function(w){try{window.turnstile.reset(w);}catch(x){}});}}t();setInterval(t,15000);})();</script>';
	return $h;
}
add_shortcode( 'arv_rt_apply', 'arv_rt_apply_shortcode' );

/**
 * The HEIC converter (heic2any 0.0.4, MIT) ships inside the plugin at
 * assets/vendor/heic2any.min.js; the client loads it on demand from this
 * URL (handed over as a JS value next to the script, never read from page
 * markup and never a third-party CDN). Versioned so a release busts the cache.
 *
 * @return string '' when the plugin URL is unknown.
 */
function arv_rt_apply_heic_url() {
	if ( ! defined( 'ARV_ELEMENTS_URL' ) ) {
		return '';
	}
	$url = ARV_ELEMENTS_URL . 'assets/vendor/heic2any.min.js';
	return defined( 'ARV_ELEMENTS_VERSION' ) ? $url . '?ver=' . rawurlencode( ARV_ELEMENTS_VERSION ) : $url;
}

/**
 * The form's browser helper (photo shrink, HEIC, error list, Turnstile
 * check, autosave, failure beacon). Only when the form renders; footer.
 */
function arv_rt_apply_enqueue() {
	// The live site does not load CF7's own script and styles (CF7 runs in
	// its no-JS mode, so every submit reloads the page and a failed one
	// drops the answers). Load them for this form, the way CF7 documents for
	// sites that turn its assets off globally.
	if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
		wpcf7_enqueue_scripts();
	}
	if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
		wpcf7_enqueue_styles();
	}
	if ( ! function_exists( 'wp_enqueue_script' ) || ! defined( 'ARV_ELEMENTS_URL' ) ) {
		return;
	}
	// After CF7's script, so the helper sees CF7's settings (cached page reset).
	$deps = function_exists( 'wp_script_is' ) && wp_script_is( 'contact-form-7', 'registered' ) ? array( 'contact-form-7' ) : array();
	wp_enqueue_script(
		'aravaipa-rt-apply',
		ARV_ELEMENTS_URL . 'assets/aravaipa-rt-apply.js',
		$deps,
		defined( 'ARV_ELEMENTS_VERSION' ) ? ARV_ELEMENTS_VERSION : false,
		true
	);
	$heic = arv_rt_apply_heic_url();
	if ( $heic && function_exists( 'wp_add_inline_script' ) ) {
		wp_add_inline_script( 'aravaipa-rt-apply', 'window.arvRtApplyHeicUrl=' . wp_json_encode( $heic ) . ';', 'before' );
	}
}

/* ------------------------------------------------------------------------
 * v2 page: [arv_rt_apply_page] (job 70sv0c, 0.101.21)
 *
 * The whole apply page in one shortcode: a full-width photo hero in the
 * team page look, the asks and perks, a real US map of the regions we take
 * applicants from, then the [arv_rt_apply] form. The map is drawn from
 * includes/data/us-states-albers.json (us-atlas states, Albers USA, built by
 * .scratch/70sv0c/map/gen-map.mjs) and colored from
 * includes/data/rt-regions.json. Nothing here touches the window logic or
 * the form itself: the form block is still arv_rt_apply_shortcode().
 * --------------------------------------------------------------------- */

/**
 * A JSON file from includes/data, decoded once per request.
 *
 * @param string $file us-states-albers.json or rt-regions.json.
 * @return array
 */
function arv_rt_apply_data( $file ) {
	static $cache = array();
	if ( ! isset( $cache[ $file ] ) ) {
		$p = __DIR__ . '/data/' . basename( $file );
		$d = is_readable( $p ) ? json_decode( (string) file_get_contents( $p ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$cache[ $file ] = is_array( $d ) ? $d : array();
	}
	return $cache[ $file ];
}

/** Regions we take applicants from (rt-regions.json). */
function arv_rt_apply_regions() {
	$d = arv_rt_apply_data( 'rt-regions.json' );
	return isset( $d['regions'] ) && is_array( $d['regions'] ) ? $d['regions'] : array();
}

/**
 * State code => region info, with the matching answer on the region question.
 *
 * @return array[]
 */
function arv_rt_apply_state_regions() {
	$opts = array();
	foreach ( arv_rt_apply_fields() as $f ) {
		if ( 'region' === $f['key'] ) {
			$opts = $f['options'];
		}
	}
	$out = array();
	foreach ( arv_rt_apply_regions() as $r ) {
		$opt = '';
		foreach ( $opts as $o ) {
			if ( $o === $r['name'] || false !== strpos( $o, $r['name'] ) ) {
				$opt = $o;
				break;
			}
		}
		foreach ( $r['states'] as $s ) {
			$out[ $s ] = array(
				'key'    => $r['key'],
				'name'   => $r['name'],
				'hub'    => $r['hub_url'],
				'hubName' => $r['hub_name'],
				'option' => $opt,
			);
		}
	}
	return $out;
}

/**
 * Script for the form: "Your region: X" under the state picker, and the
 * region answer filled in when it is still empty. Never blocks submit.
 *
 * @return string
 */
function arv_rt_apply_region_hint_js() {
	$map = wp_json_encode( arv_rt_apply_state_regions(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	return '<script>(function(){var R=' . $map . ';var root=document.getElementById("rt-apply");if(!root)return;'
		. 'var sel=root.querySelector(\'select[name="rt-state"]\');if(!sel)return;'
		. 'var box=document.createElement("p");box.className="rt-region-hint";box.setAttribute("aria-live","polite");box.hidden=true;'
		. 'var wrap=sel.closest(".wpcf7-form-control-wrap")||sel;wrap.parentNode.insertBefore(box,wrap.nextSibling);'
		. 'function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"}[c];});}'
		. 'function upd(){var v=sel.value,r=R[v];box.className="rt-region-hint";if(!v||v==="Choose"){box.hidden=true;box.innerHTML="";return;}box.hidden=false;'
		. 'if(r){box.classList.add("rt-region-hint--in","rt-r-"+r.key);box.innerHTML=\'<span class="rt-region-hint__sw"></span>Your region: <strong>\'+esc(r.name)+\'</strong> <a href="\'+esc(r.hub)+\'" target="_blank" rel="noopener">\'+esc(r.hubName)+\'</a>\';'
		. 'var picked=root.querySelector(\'input[name="rt-region"]:checked\');if(!picked&&r.option){var o=root.querySelector(\'input[name="rt-region"][value="\'+r.option.replace(/"/g,"")+\'"]\');if(o){o.checked=true;}}}'
		. 'else{box.classList.add("rt-region-hint--out");box.innerHTML="Outside our 2027 regions. You can still apply: pick the region you would race for below.";}}'
		. 'sel.addEventListener("change",upd);upd();})();</script>';
}

/**
 * The inline SVG map: every state from us-atlas, eligible ones colored by region.
 *
 * @return string
 */
function arv_rt_apply_map_svg() {
	$m = arv_rt_apply_data( 'us-states-albers.json' );
	if ( empty( $m['states'] ) ) {
		return '';
	}
	$by    = arv_rt_apply_state_regions();
	$names = isset( $m['names'] ) ? $m['names'] : array();
	$vb    = isset( $m['viewBox'] ) ? $m['viewBox'] : '0 0 975 610';
	$out   = '<svg class="rt-map__svg" viewBox="' . esc_attr( $vb ) . '" role="group" aria-labelledby="rt-map-title" focusable="false"><title id="rt-map-title">Map of the United States. States where we take 2027 applicants are colored by region.</title>';
	$on    = '';
	foreach ( $m['states'] as $code => $d ) {
		$nm = isset( $names[ $code ] ) ? $names[ $code ] : $code;
		if ( isset( $by[ $code ] ) ) {
			$r   = $by[ $code ];
			$on .= '<path data-state="' . esc_attr( $code ) . '" data-name="' . esc_attr( $nm ) . '" data-region="' . esc_attr( $r['key'] ) . '" class="rt-st rt-st--on rt-r-' . esc_attr( $r['key'] ) . '" tabindex="0" role="button" aria-label="' . esc_attr( $nm . ': ' . $r['name'] . ' region' ) . '" d="' . esc_attr( $d ) . '"><title>' . esc_html( $nm . ': ' . $r['name'] . ' region' ) . '</title></path>';
		} else {
			$out .= '<path data-state="' . esc_attr( $code ) . '" data-name="' . esc_attr( $nm ) . '" class="rt-st" d="' . esc_attr( $d ) . '"/>';
		}
	}
	// Eligible states last so their outlines sit on top of the gray ones.
	return $out . $on . '</svg>';
}

/**
 * "Region lines are being finalized." only while the region lists are marked
 * provisional (source "provisional"). Final lists render no note.
 *
 * @param array $d rt-regions.json data.
 * @return string
 */
function arv_rt_apply_regions_note( $d ) {
	if ( ! is_array( $d ) || ! isset( $d['source'] ) || 'provisional' !== $d['source'] ) {
		return '';
	}
	return '<p class="rt-map__note">Region lines are being finalized.</p>';
}

/**
 * Map, tooltip and legend.
 *
 * @return string
 */
function arv_rt_apply_map_html() {
	$svg = arv_rt_apply_map_svg();
	if ( ! $svg ) {
		return '';
	}
	$hubs = array();
	foreach ( arv_rt_apply_regions() as $r ) {
		$hubs[ $r['key'] ] = array(
			'name'    => $r['name'],
			'hub'     => $r['hub_url'],
			'hubName' => $r['hub_name'],
		);
	}
	$h  = '<div class="rt-map" id="rt-map" data-hubs="' . esc_attr( wp_json_encode( $hubs ) ) . '">' . $svg;
	$h .= '<div class="rt-map__tip" role="status" aria-live="polite" hidden></div></div>';
	$h .= '<ul class="rt-legend">';
	foreach ( arv_rt_apply_regions() as $r ) {
		$h .= '<li class="rt-legend__item rt-r-' . esc_attr( $r['key'] ) . '" data-region="' . esc_attr( $r['key'] ) . '"><span class="rt-legend__sw" aria-hidden="true"></span>'
			. '<span class="rt-legend__txt"><span class="rt-legend__name">' . esc_html( $r['name'] ) . '</span>'
			. '<span class="rt-legend__states">' . esc_html( implode( ', ', $r['states'] ) ) . '</span>'
			. '<a class="rt-legend__hub" href="' . esc_url( $r['hub_url'] ) . '">' . esc_html( $r['hub_name'] ) . '</a></span></li>';
	}
	$h .= '</ul>';
	$h .= arv_rt_apply_regions_note( arv_rt_apply_data( 'rt-regions.json' ) );
	return $h;
}

/** Region colors as CSS, from rt-regions.json. */
function arv_rt_apply_region_css() {
	$d   = arv_rt_apply_data( 'rt-regions.json' );
	$off = isset( $d['ineligible_fill'] ) ? $d['ineligible_fill'] : '#e4e4e4';
	$css = '.rt-map .rt-st{fill:' . $off . ';stroke:#fff;stroke-width:.8;stroke-linejoin:round}';
	foreach ( arv_rt_apply_regions() as $r ) {
		$k    = sanitize_key( $r['key'] );
		$css .= '.rt-map .rt-r-' . $k . '{fill:' . $r['fill'] . '}'
			. '.rt-map .rt-r-' . $k . ':hover,.rt-map .rt-r-' . $k . ':focus,.rt-map.is-hl-' . $k . ' .rt-r-' . $k . '{fill:' . $r['fill_hover'] . '}'
			. '.rt-legend .rt-r-' . $k . ' .rt-legend__sw,.rt-region-hint.rt-r-' . $k . ' .rt-region-hint__sw{background:' . $r['fill'] . '}'
			. '.rt-legend .rt-r-' . $k . ' .rt-legend__hub,.rt-region-hint.rt-r-' . $k . ' a{color:' . $r['ink'] . '}';
	}
	return $css;
}

/** Page styles: full-width hero, plain type, no cards, no shadows. */
function arv_rt_apply_page_css() {
	return '.entry-wrap:has(.rt-v2){padding:0;background:transparent;border:0;box-shadow:none}'
		. '.x-container.offset:has(.rt-v2){margin-top:0;margin-bottom:0}'
		. 'body:has(.rt-v2){overflow-x:clip}'
		. '.rt-v2{text-align:left;color:#272727;--rt-red:#ff2a13;--rt-btn:#d41a06;--rt-ink:#272727;--rt-muted:#5f5f5f}'
		. '.rt-v2 .rt-hero{position:relative;width:100vw;margin-left:calc(50% - 50vw);min-height:min(86vh,800px);display:flex;align-items:flex-end;background:#1d2322;color:#fff;overflow:hidden}'
		. '.rt-v2 .rt-hero__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;margin:0;max-width:none;border:0}'
		. '.rt-v2 .rt-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.62) 0%,rgba(0,0,0,.38) 34%,rgba(0,0,0,0) 58%),linear-gradient(180deg,rgba(0,0,0,0) 55%,rgba(0,0,0,.45) 100%)}'
		. '.rt-v2 .rt-hero__img{object-position:var(--rt-focal,50% 40%)}'
		. '.rt-v2 .rt-hero__credit{position:absolute;z-index:1;right:18px;bottom:12px;margin:0;font-size:11px;letter-spacing:.04em;color:rgba(255,255,255,.75)}'
		. '.rt-v2 .rt-hero__in{position:relative;z-index:1;width:100%;max-width:1440px;margin:0 auto;padding:0 64px 64px;box-sizing:border-box}'
		. '.rt-v2 .rt-hero__txt{max-width:560px}'
		. '.rt-v2 .rt-hero__kicker{margin:0 0 14px;font-size:14px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#fff}'
		. '.rt-v2 .rt-hero h1{margin:0 0 18px;font-size:clamp(40px,4.7vw,70px);line-height:.98;font-weight:800;letter-spacing:-.02em;color:#fff;text-transform:none}'
		. '.rt-v2 .rt-hero__dates{margin:0 0 30px;font-size:clamp(17px,1.6vw,21px);line-height:1.45;max-width:640px;color:#fff}'
		. '.rt-v2 .rt-hero__row{display:flex;flex-wrap:wrap;align-items:center;gap:28px 40px}'
		. '.rt-v2 .rt-cta{display:inline-block;background:var(--rt-btn);color:#fff;text-decoration:none;font-size:19px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:18px 38px;border-radius:2px;line-height:1}'
		. '.rt-v2 .rt-cta:hover,.rt-v2 .rt-cta:focus{background:#a91403;color:#fff}'
		. '.rt-v2 .rt-cta--wait{background:#fff;color:#272727}.rt-v2 .rt-cta--wait:hover,.rt-v2 .rt-cta--wait:focus{background:#f0f0f0;color:#272727}'
		. '.rt-v2 .rt-cta--closed{background:rgba(255,255,255,.18);color:#fff;pointer-events:none}'
		. '.rt-v2 .rt-lockup{display:flex;align-items:center;gap:28px}'
		. '.rt-v2 .rt-lockup img{display:block;width:auto;border:0;max-width:none;filter:brightness(0) invert(1)}'
		. '.rt-v2 .rt-lockup .rt-lockup__hoka{height:34px}.rt-v2 .rt-lockup .rt-lockup__ath{height:36px}'
		. '.rt-v2 .rt-lockup__x{width:1px;height:34px;background:rgba(255,255,255,.55)}'
		. '.rt-v2 .rt-body{width:100vw;margin-left:calc(50% - 50vw);background:#fff}'
		. '.rt-v2 .rt-wrap{max-width:1200px;margin:0 auto;padding:0 40px;box-sizing:border-box}'
		. '.rt-v2 .rt-block{padding:88px 0 0}'
		. '.rt-v2 .rt-eyebrow{display:block;margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--rt-muted)}'
		. '.rt-v2 h2.rt-h2{margin:0 0 14px;font-size:clamp(30px,3.4vw,46px);line-height:1.05;font-weight:800;letter-spacing:-.01em;color:#272727;text-transform:none}'
		. '.rt-v2 .rt-lead{margin:0 0 36px;font-size:19px;line-height:1.5;color:var(--rt-muted);max-width:720px}'
		. '.rt-v2 .rt-cols{display:grid;grid-template-columns:1fr 1fr;gap:72px}'
		. '.rt-v2 .rt-col h2.rt-h2{padding-bottom:18px;border-bottom:1px solid rgba(0,0,0,.14);margin-bottom:6px}'
		. '.rt-v2 .rt-list{list-style:none;margin:0;padding:0}'
		. '.rt-v2 .rt-list li{display:flex;gap:18px;align-items:baseline;margin:0;padding:18px 0;border-bottom:1px solid rgba(0,0,0,.08);font-size:19px;line-height:1.45}'
		. '.rt-v2 .rt-list .rt-list__n{flex:0 0 auto;font-size:15px;font-weight:700;color:var(--rt-btn);font-variant-numeric:tabular-nums;min-width:24px}'
		. '.rt-v2 .rt-map{position:relative;max-width:1060px;margin:0 auto}'
		. '.rt-v2 .rt-map__svg{display:block;width:100%;height:auto;overflow:visible}'
		. '.rt-v2 .rt-st--on{cursor:pointer;transition:fill .15s}.rt-v2 .rt-st--on:focus{outline:none;stroke:#272727;stroke-width:1.6}'
		. '.rt-v2 .rt-map.is-hl .rt-st--on{opacity:.35}.rt-v2 .rt-map.is-hl .rt-st--on.is-hl{opacity:1}'
		. '.rt-v2 .rt-map__tip{position:absolute;z-index:3;min-width:180px;max-width:260px;background:#272727;color:#fff;padding:12px 14px;font-size:15px;line-height:1.35;transform:translate(-50%,calc(-100% - 14px));pointer-events:auto}'
		. '.rt-v2 .rt-map__tip strong{display:block;font-size:17px}.rt-v2 .rt-map__tip span{display:block;color:rgba(255,255,255,.75);font-size:14px}'
		. '.rt-v2 .rt-map__tip a{display:inline-block;margin-top:6px;color:#fff;font-weight:700;text-decoration:underline;text-underline-offset:3px}'
		. '.rt-v2 .rt-legend{list-style:none;margin:36px auto 0;padding:0;max-width:1060px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px 28px}'
		. '.rt-v2 .rt-legend__item{display:flex;gap:12px;align-items:flex-start;margin:0;cursor:default}'
		. '.rt-v2 .rt-legend__sw{flex:0 0 auto;width:22px;height:22px;margin-top:1px}'
		. '.rt-v2 .rt-legend__txt{display:flex;flex-direction:column;min-width:0}'
		. '.rt-v2 .rt-legend__name{font-size:18px;font-weight:700;line-height:1.2;color:#272727}'
		. '.rt-v2 .rt-legend__states{font-size:14px;line-height:1.4;color:var(--rt-muted);letter-spacing:.02em}'
		. '.rt-v2 .rt-map__note{margin:20px auto 0;max-width:1060px;font-size:15px;color:var(--rt-muted)}'
		. '.rt-v2 .rt-legend__hub{font-size:15px;font-weight:600;line-height:1.4;text-decoration:none}.rt-v2 .rt-legend__hub:hover{text-decoration:underline}'
		. '.rt-v2 .rt-legend__off{display:flex;gap:12px;align-items:center;font-size:15px;color:var(--rt-muted)}'
		. '.rt-v2 .rt-legend__off .rt-legend__sw{background:#e4e4e4}'
		. '.rt-v2 .rt-formblock{padding-bottom:96px}'
		. '.rt-v2 .rt-back{margin:48px 0 0;font-size:17px;font-weight:700}.rt-v2 .rt-back a{color:var(--rt-btn)}'
		// The form, bigger and plainer inside the v2 page.
		. '.rt-v2 .rt-apply{max-width:none}'
		. '.rt-v2 .rt-state{font-size:22px;margin:8px 0 0}'
		. '.rt-v2 .rt-sec{border-top:1px solid rgba(0,0,0,.14);padding:48px 0;gap:56px}'
		. '.rt-v2 .rt-n{display:block;font-size:44px;line-height:1;font-weight:800;color:var(--rt-btn);letter-spacing:-.02em;margin-bottom:10px}'
		. '.rt-v2 .rt-sec-head h3{font-size:28px;font-weight:800}'
		. '.rt-v2 .rt-sec-head p{font-size:16px;color:var(--rt-muted)}'
		. '.rt-v2 .rt-sec-body{gap:26px 28px}'
		. '.rt-v2 .rt-l{font-size:16px;margin-bottom:6px;color:#272727}'
		. '.rt-v2 .rt-h{font-size:14px;color:var(--rt-muted);margin-bottom:8px}'
		. '.rt-v2 .rt-form input[type=text],.rt-v2 .rt-form input[type=email],.rt-v2 .rt-form input[type=tel],.rt-v2 .rt-form input[type=date],.rt-v2 .rt-form input[type=url],.rt-v2 .rt-form select,.rt-v2 .rt-form textarea{min-height:54px;padding:14px 16px;font-size:17px;border:1px solid #b9b9b9;border-radius:2px;color:#272727;box-shadow:none}'
		. '.rt-v2 .rt-form textarea{min-height:0}'
		. '.rt-v2 .rt-form input:focus,.rt-v2 .rt-form select:focus,.rt-v2 .rt-form textarea:focus{outline:2px solid #2a5e6b;outline-offset:1px;border-color:#2a5e6b}'
		. '.rt-v2 .rt-form .wpcf7-list-item label{font-size:16px;min-height:36px}'
		. '.rt-v2 .rt-form input[type=checkbox],.rt-v2 .rt-form input[type=radio]{width:20px;height:20px;accent-color:#2a5e6b}'
		. '.rt-v2 .rt-submit{padding:40px 0 0;border-top:1px solid rgba(0,0,0,.14)}'
		. '.rt-v2 .rt-submit .wpcf7-submit{background:var(--rt-btn);padding:20px 48px;font-size:18px;border-radius:2px}'
		. '.rt-v2 .rt-submit .wpcf7-submit:hover{background:#a91403}'
		. '@media (max-width:1023px){.rt-v2 .rt-legend{grid-template-columns:repeat(2,minmax(0,1fr))}}'
		. '@media (max-width:767px){.rt-v2 .rt-hero{display:block;min-height:0;background:#1d2322}.rt-v2 .rt-hero__img{position:relative;display:block;height:min(92vw,400px);object-position:var(--rt-focal-m,var(--rt-focal,50% 40%))}.rt-v2 .rt-hero::after{display:none}.rt-v2 .rt-hero__in{padding:28px 22px 36px}.rt-v2 .rt-hero__credit{top:10px;bottom:auto;right:12px}.rt-v2 .rt-hero__row{gap:22px}.rt-v2 .rt-cta{display:block;text-align:center;width:100%;box-sizing:border-box}'
		. '.rt-v2 .rt-lockup .rt-lockup__hoka{height:26px}.rt-v2 .rt-lockup .rt-lockup__ath{height:28px}.rt-v2 .rt-lockup__x{height:26px}'
		. '.rt-v2 .rt-wrap{padding:0 22px}.rt-v2 .rt-block{padding-top:56px}.rt-v2 .rt-cols{grid-template-columns:1fr;gap:48px}.rt-v2 .rt-list li{font-size:17px;padding:14px 0}.rt-v2 .rt-lead{font-size:17px;margin-bottom:24px}'
		. '.rt-v2 .rt-legend{grid-template-columns:1fr;gap:16px;margin-top:24px}.rt-v2 .rt-sec{padding:36px 0;gap:18px}.rt-v2 .rt-n{font-size:36px}.rt-v2 .rt-formblock{padding-bottom:64px}}';
}

/**
 * Map tooltip and legend highlight, plus the hero button that follows the window.
 *
 * @return string
 */
function arv_rt_apply_page_js() {
	return '<script>(function(){'
		// Hero button: same window as the form, swapped in the browser so a cached page still flips at midnight.
		. 'var b=document.getElementById("rt-cta");if(b){var tk=function(){var n=Date.now()/1000,o=+b.dataset.opens,c=+b.dataset.closes,s=n<o?"before":(n>c?"closed":"open");if(b.dataset.state===s)return;b.dataset.state=s;'
		. 'b.textContent=s==="open"?"Apply now":(s==="before"?"Opens Nov 20":"Applications closed");b.className="rt-cta"+(s==="open"?"":(s==="before"?" rt-cta--wait":" rt-cta--closed"));};b.dataset.state="";tk();setInterval(tk,15000);}'
		// Map: hover or tap a state for its region and hub link.
		. 'var m=document.getElementById("rt-map");if(!m)return;var tip=m.querySelector(".rt-map__tip"),H={};try{H=JSON.parse(m.dataset.hubs||"{}");}catch(e){}'
		. 'function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"}[c];});}'
		. 'var pinned=null;function show(p,x,y){var k=p.getAttribute("data-region"),r=k&&H[k];var sn=p.getAttribute("data-name");tip.innerHTML=r?"<strong>"+esc(r.name)+"</strong>"+(sn!==r.name?"<span>"+esc(sn)+"</span>":"")+"<a href=\\""+esc(r.hub)+"\\">"+esc(r.hubName)+"</a>":"<strong>"+esc(p.getAttribute("data-name"))+"</strong><span>Outside our 2027 regions</span>";'
		. 'var b=m.getBoundingClientRect();if(x==null){var q=p.getBoundingClientRect();x=q.left+q.width/2;y=q.top+q.height/2;}var lx=Math.max(100,Math.min(b.width-100,x-b.left)),ly=Math.max(70,y-b.top);tip.style.left=lx+"px";tip.style.top=ly+"px";tip.hidden=false;}'
		. 'function hide(){if(!pinned)tip.hidden=true;}'
		. 'm.addEventListener("pointermove",function(e){if(pinned||e.pointerType==="touch")return;var p=e.target.closest&&e.target.closest("path[data-state]");if(p)show(p,e.clientX,e.clientY);else hide();});'
		. 'm.addEventListener("pointerleave",hide);'
		. 'm.addEventListener("click",function(e){if(e.target.closest("a"))return;var p=e.target.closest&&e.target.closest("path[data-state]");if(!p){pinned=null;tip.hidden=true;return;}pinned=p;show(p,e.clientX,e.clientY);});'
		. 'm.addEventListener("focusin",function(e){var p=e.target.closest&&e.target.closest("path[data-state]");if(p){pinned=p;show(p);}});'
		. 'm.addEventListener("keydown",function(e){if(e.key==="Escape"){pinned=null;tip.hidden=true;}if(e.key==="Enter"&&pinned){var a=tip.querySelector("a");if(a)a.click();}});'
		. 'document.addEventListener("click",function(e){if(!m.contains(e.target)){pinned=null;tip.hidden=true;}});'
		// Legend: hovering a region lights its states.
		. 'document.querySelectorAll(".rt-legend__item").forEach(function(li){var k=li.getAttribute("data-region");li.addEventListener("mouseenter",function(){m.classList.add("is-hl");m.querySelectorAll(".rt-r-"+k).forEach(function(p){p.classList.add("is-hl");});});li.addEventListener("mouseleave",function(){m.classList.remove("is-hl");m.querySelectorAll(".is-hl").forEach(function(p){p.classList.remove("is-hl");});});});'
		. '})();</script>';
}

/**
 * Hero image tag: a media library attachment (hero="ID") or a plain URL (hero_url).
 *
 * @param array $a Shortcode attributes.
 * @return string
 */
function arv_rt_apply_hero_img( $a ) {
	$alt   = (string) $a['alt'];
	$focal = preg_replace( '/[^0-9.% a-z]/', '', strtolower( (string) $a['focal'] ) );
	$fm    = preg_replace( '/[^0-9.% a-z]/', '', strtolower( (string) $a['focal_mobile'] ) );
	$style = ( $focal ? '--rt-focal:' . $focal . ';' : '' ) . ( $fm ? '--rt-focal-m:' . $fm . ';' : '' );
	$id    = (int) $a['hero'];
	if ( $id && function_exists( 'wp_get_attachment_image' ) ) {
		$img = wp_get_attachment_image(
			$id,
			'full',
			false,
			array(
				'class'         => 'rt-hero__img',
				'alt'           => $alt,
				'sizes'         => '100vw',
				'style'         => $style,
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
		if ( $img ) {
			return $img;
		}
	}
	$url = (string) $a['hero_url'];
	if ( $url && false === strpos( $url, 'ytimg.com' ) ) {
		return '<img class="rt-hero__img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" style="' . esc_attr( $style ) . '" fetchpriority="high" decoding="async">';
	}
	return '';
}

/**
 * [arv_rt_apply_page hero="ID" focal="50% 40%" alt="..."]: the whole v2 page.
 *
 * @param array|string $atts Attributes.
 * @return string
 */
function arv_rt_apply_page_shortcode( $atts = array() ) {
	$a = shortcode_atts(
		array(
			'hero'     => (string) get_option( 'arv_rt_apply_hero', '' ),
			'hero_url' => '',
			'focal'        => '50% 40%',
			'focal_mobile' => '',
			'credit'       => '',
			'alt'          => 'An Aravaipa Racing Team runner racing an Aravaipa trail race',
		),
		is_array( $atts ) ? $atts : array(),
		'arv_rt_apply_page'
	);
	list( $opens, $closes ) = arv_rt_apply_window();
	$state = arv_rt_apply_state();
	$cta   = 'open' === $state ? 'Apply now' : ( 'before' === $state ? 'Opens Nov 20' : 'Applications closed' );
	$ccls  = 'rt-cta' . ( 'open' === $state ? '' : ( 'before' === $state ? ' rt-cta--wait' : ' rt-cta--closed' ) );
	$up    = 'https://www.aravaiparunning.com/wp-content/uploads/';

	$asks  = array(
		'Race at least one Aravaipa event in 2027.',
		'Wear the team kit at every event you race.',
		'Volunteer one shift at an Aravaipa event: a race, a group run or course marking.',
	);
	$gets  = array(
		'Unlimited entries to Aravaipa races (Cocodona excluded).',
		'The 2027 team kit and a shoe allotment from HOKA.',
		'Performance bonuses for big results.',
		'Team summits to train, eat and take photos together.',
	);
	$list = function ( $items ) {
		$o = '<ol class="rt-list">';
		foreach ( $items as $i => $t ) {
			$o .= '<li><span class="rt-list__n">' . sprintf( '%02d', $i + 1 ) . '</span><span>' . esc_html( $t ) . '</span></li>';
		}
		return $o . '</ol>';
	};

	$h  = '<style>' . arv_rt_apply_page_css() . '</style>';
	$h .= '<div class="rt-v2">';
	$h .= '<section class="rt-hero" aria-labelledby="rt-hero-title">' . arv_rt_apply_hero_img( $a ) . '<div class="rt-hero__in"><div class="rt-hero__txt">';
	$h .= '<p class="rt-hero__kicker">Aravaipa Racing Team</p>';
	$h .= '<h1 id="rt-hero-title">Apply for the 2027 Aravaipa Racing Team</h1>';
	$h .= '<p class="rt-hero__dates">Applications open November 20 and close November 30 at 11:59 PM Arizona time.</p>';
	$h .= '<div class="rt-hero__row"><a id="rt-cta" class="' . esc_attr( $ccls ) . '" href="#rt-apply-form" data-state="' . esc_attr( $state ) . '" data-opens="' . (int) $opens . '" data-closes="' . (int) $closes . '">' . esc_html( $cta ) . '</a>';
	$h .= '<div class="rt-lockup"><a href="https://www.hoka.com/en/us/" target="_blank" rel="noopener"><img class="rt-lockup__hoka" src="' . $up . '2022/10/HOKA_Logo_Process-Blue-300x82.png" srcset="' . $up . '2022/10/HOKA_Logo_Process-Blue-768x210.png 768w, ' . $up . '2022/10/HOKA_Logo_Process-Blue-300x82.png 300w" sizes="160px" width="300" height="82" alt="HOKA"></a>'
		. '<span class="rt-lockup__x" aria-hidden="true"></span>'
		. '<a href="https://www.athletico.com/" target="_blank" rel="noopener"><img class="rt-lockup__ath" src="' . $up . 'athletico-physical-therapy-logo-300x72.png" srcset="' . $up . 'athletico-physical-therapy-logo-768x184.png 768w, ' . $up . 'athletico-physical-therapy-logo-300x72.png 300w" sizes="160px" width="300" height="72" alt="Athletico Physical Therapy"></a></div>';
	$h .= '</div></div></div>' . ( '' !== trim( (string) $a['credit'] ) ? '<p class="rt-hero__credit">' . esc_html( $a['credit'] ) . '</p>' : '' ) . '</section>';

	$h .= '<div class="rt-body"><div class="rt-wrap">';
	$h .= '<section class="rt-block rt-cols"><div class="rt-col"><h2 class="rt-h2">What the team asks</h2>' . $list( $asks ) . '</div>';
	$h .= '<div class="rt-col"><h2 class="rt-h2">What members get</h2>' . $list( $gets ) . '</div></section>';

	$map = arv_rt_apply_map_html();
	if ( $map ) {
		$h .= '<section class="rt-block rt-regions" aria-labelledby="rt-regions-title"><span class="rt-eyebrow">Regions</span><h2 class="rt-h2" id="rt-regions-title">Where we take applicants</h2>';
		$h .= '<p class="rt-lead">Each member races for one region. Tap a state to see its region and races.</p>' . $map . '</section>';
	}

	$h .= '<section class="rt-block rt-formblock" id="rt-apply-form" aria-labelledby="rt-form-title"><span class="rt-eyebrow">Application</span><h2 class="rt-h2" id="rt-form-title">Apply</h2>';
	$h .= '<p class="rt-lead">Six short sections. About 15 minutes.</p>';
	$h .= arv_rt_apply_shortcode();
	$h .= '<p class="rt-back"><a href="/racing-team/">Meet the current team</a></p></section>';
	$h .= '</div></div></div>';
	$h .= arv_rt_apply_page_js();
	return $h;
}
add_shortcode( 'arv_rt_apply_page', 'arv_rt_apply_page_shortcode' );

/**
 * Entries for export: Flamingo first, the private log as the fallback.
 *
 * @return array[] Each: fields map, at, files.
 */
function arv_rt_apply_entries() {
	$rows = array();
	$id   = arv_rt_apply_form_id();
	if ( $id && class_exists( 'Flamingo_Inbound_Message' ) && function_exists( 'wpcf7_contact_form' ) ) {
		$cf = wpcf7_contact_form( $id );
		if ( $cf ) {
			$msgs = Flamingo_Inbound_Message::find(
				array(
					'channel'        => $cf->name(),
					'posts_per_page' => -1,
				)
			);
			foreach ( (array) $msgs as $m ) {
				$files  = get_post_meta( $m->id(), '_arv_rt_files', true );
				$rows[] = array(
					'id'     => $m->id(),
					'at'     => get_post_field( 'post_date_gmt', $m->id() ),
					'fields' => (array) $m->fields,
					'files'  => is_array( $files ) ? $files : array(),
				);
			}
		}
	}
	if ( ! $rows ) {
		$log = arv_rt_apply_dir() . '/entries.jsonl';
		if ( is_file( $log ) ) {
			foreach ( file( $log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
				$e = json_decode( $line, true );
				if ( is_array( $e ) && isset( $e['fields'] ) && empty( $e['kind'] ) ) {
					$rows[] = array(
						'id'     => 0,
						'at'     => $e['at'],
						'fields' => $e['fields'],
						'files'  => (array) $e['files'],
					);
				}
			}
		}
	}
	return $rows;
}

/**
 * Export header row: one column per field and consent.
 *
 * @return string[]
 */
function arv_rt_apply_export_header() {
	$h = array( 'submitted_at' );
	foreach ( arv_rt_apply_fields() as $f ) {
		$h[] = $f['key'];
	}
	foreach ( arv_rt_apply_consents() as $c ) {
		$h[] = 'consent-' . $c['key'];
	}
	$h[] = 'flamingo_id';
	$h[] = 'rr_status';
	$h[] = 'rr_skipped_files';
	return $h;
}

/**
 * Write the CSV.
 *
 * @param resource $fh Handle.
 * @return int Rows written.
 */
function arv_rt_apply_export_csv( $fh ) {
	fputcsv( $fh, arv_rt_apply_export_header(), ',', '"', '\\' );
	$n = 0;
	foreach ( arv_rt_apply_entries() as $r ) {
		$row = array( $r['at'] );
		foreach ( arv_rt_apply_fields() as $f ) {
			$k = 'rt-' . $f['key'];
			if ( 'file' === $f['type'] ) {
				$row[] = isset( $r['files'][ $k ] ) ? basename( $r['files'][ $k ] ) : '';
				continue;
			}
			$v     = isset( $r['fields'][ $k ] ) ? $r['fields'][ $k ] : '';
			$row[] = is_array( $v ) ? implode( '; ', $v ) : (string) $v;
		}
		foreach ( arv_rt_apply_consents() as $c ) {
			$v     = isset( $r['fields'][ 'rt-consent-' . $c['key'] ] ) ? $r['fields'][ 'rt-consent-' . $c['key'] ] : '';
			$v     = is_array( $v ) ? implode( '', $v ) : (string) $v;
			$row[] = ( '' !== $v && 'no' !== $v && '0' !== $v ) ? 'yes' : 'no';
		}
		$row[] = $r['id'];
		$row[] = arv_rt_apply_rr_status( $r['id'] );
		$row[] = arv_rt_apply_rr_skipped( $r['id'] );
		fputcsv( $fh, $row, ',', '"', '\\' );
		++$n;
	}
	return $n;
}

/**
 * Remove every stored trace of one email: Flamingo entries, saved files and
 * log lines. Used to clean up the staff test only.
 *
 * @param string $email Email.
 * @return array Counts.
 */
function arv_rt_apply_delete_email( $email ) {
	$email = strtolower( trim( $email ) );
	$out   = array(
		'flamingo' => 0,
		'files'    => 0,
		'log'      => 0,
	);
	if ( ! $email ) {
		return $out;
	}
	foreach ( arv_rt_apply_entries() as $r ) {
		$e = isset( $r['fields']['rt-email'] ) ? strtolower( (string) $r['fields']['rt-email'] ) : '';
		if ( $e !== $email ) {
			continue;
		}
		if ( $r['id'] ) {
			wp_delete_post( (int) $r['id'], true );
			++$out['flamingo'];
		}
		foreach ( (array) $r['files'] as $p ) {
			if ( is_file( $p ) && 0 === strpos( realpath( $p ), realpath( arv_rt_apply_dir() ) ) ) {
				unlink( $p ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$out['files'];
			}
		}
	}
	// Flamingo also keeps an address book row per email; drop the test one.
	if ( class_exists( 'Flamingo_Contact' ) && method_exists( 'Flamingo_Contact', 'search_by_email' ) ) {
		$contact = Flamingo_Contact::search_by_email( $email );
		if ( $contact && method_exists( $contact, 'id' ) && $contact->id() ) {
			wp_delete_post( (int) $contact->id(), true );
			$out['contact'] = 1;
		}
	}
	$log = arv_rt_apply_dir() . '/entries.jsonl';
	if ( is_file( $log ) ) {
		$keep = array();
		foreach ( file( $log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			$e = json_decode( $line, true );
			if ( is_array( $e ) && isset( $e['email'] ) && strtolower( (string) $e['email'] ) === $email ) {
				foreach ( isset( $e['files'] ) ? (array) $e['files'] : array() as $p ) {
					if ( is_file( $p ) ) {
						unlink( $p ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
						++$out['files'];
					}
				}
				++$out['log'];
				continue;
			}
			$keep[] = $line;
		}
		file_put_contents( $log, $keep ? implode( "\n", $keep ) . "\n" : '', LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	return $out;
}

/**
 * Create or update the CF7 form; returns the id.
 *
 * @param bool $dry_run Only report.
 * @return int|string Id, or a dry run note.
 */
function arv_rt_apply_install( $dry_run = false ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		return 'Contact Form 7 is not active';
	}
	$id = arv_rt_apply_form_id();
	$cf = $id ? wpcf7_contact_form( $id ) : null;
	if ( $dry_run ) {
		return $cf ? 'would update form ' . $id : 'would create form "2027 Racing Team Application"';
	}
	if ( ! $cf ) {
		$cf = WPCF7_ContactForm::get_template( array( 'title' => '2027 Racing Team Application' ) );
	}
	$cf->set_properties(
		array(
			'form'                => arv_rt_apply_form_template(),
			'mail'                => arv_rt_apply_mail(),
			'mail_2'              => arv_rt_apply_confirm_mail(),
			'messages'            => array_merge( (array) $cf->prop( 'messages' ), arv_rt_apply_messages() ),
			'additional_settings' => arv_rt_apply_additional_settings(),
		)
	);
	$new = (int) $cf->save();
	if ( $new ) {
		update_option( 'arv_rt_apply_form_id', $new, false );
	}
	arv_rt_apply_dir();
	return $new;
}

/**
 * A full test application, run through the real CF7 pipeline.
 *
 * @param string $email Test email.
 * @return array CF7 result.
 */
function arv_rt_apply_run_test( $email ) {
	$id = arv_rt_apply_form_id();
	$cf = $id && function_exists( 'wpcf7_contact_form' ) ? wpcf7_contact_form( $id ) : null;
	if ( ! $cf ) {
		return array( 'status' => 'no_form' );
	}
	$races = arv_rt_apply_race_options();
	$post  = array(
		'_wpcf7'             => $id,
		'rt-first-name'      => 'TEST',
		'rt-last-name'       => 'TEST',
		'rt-email'           => $email,
		'rt-phone'           => '602-555-0100',
		'rt-birth-date'      => '1990-01-01',
		'rt-city'            => 'Phoenix',
		'rt-state'           => 'AZ',
		'rt-region'          => 'West',
		'rt-disciplines'     => array( 'Trail', 'Ultra' ),
		'rt-returning'       => 'No',
		'rt-resume'          => 'TEST entry from Vaip. Delete me.',
		'rt-race-plans'      => $races ? array( $races[0] ) : array(),
		'rt-outside-races'   => 'TEST',
		'rt-why'             => 'TEST',
		'rt-size-fit'        => 'Unisex',
		'rt-size-tee'        => 'M',
		'rt-size-top'        => 'M',
		'rt-size-shorts'     => 'M',
		'rt-shoe-size'       => '10',
		'rt-consent-age'          => '1',
		'rt-consent-expectations' => '1',
		'rt-consent-privacy'      => '1',
	);
	$_POST = $post; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$_SERVER['HTTP_USER_AGENT'] = 'arv-rt-apply test';
	$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
	// Turnstile needs a browser token; the staff test skips only that check.
	remove_filter( 'wpcf7_spam', 'wpcf7_turnstile_verify_response', 9 );
	add_filter( 'wpcf7_skip_spam_check', '__return_true' );
	// A 1x1 PNG stands in for the photo, so the photo copy runs too.
	$png = wp_tempnam( 'rt-test.png' );
	file_put_contents( $png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$GLOBALS['arv_rt_apply_test_files'] = array( 'rt-headshot' => $png );
	$res = $cf->submit();
	$GLOBALS['arv_rt_apply_test_files'] = array();
	remove_filter( 'wpcf7_skip_spam_check', '__return_true' );
	@unlink( $png ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
	return is_array( $res ) ? $res : array( 'status' => 'unknown' );
}

/* ------------------------------------------------------------------------
 * RaceReach push (job wrheyv, 0.101.22)
 *
 * After CF7 has saved the entry (Flamingo and the private log) and mailed
 * the team managers, each application is also posted to RaceReach, signed
 * with the shared apply-service secret (racereach docs/apply-service.md).
 * It runs after the response has gone back to the applicant, so they never
 * wait on RaceReach or see its errors. Off unless the option
 * arv_rt_apply_rr_push is on and a secret exists (wp-config constant
 * RR_APPLY_SERVICE_SECRET, else the option arv_rt_apply_rr_secret).
 *
 * Every result is kept on the Flamingo entry (_arv_rt_rr), in the private
 * entries.jsonl and in a short ring log (arv_rt_apply_log, masked email,
 * never the token or secret). Network errors, 5xx, 429 and 401 are retried
 * at +5 min, +30 min and +3 h, then marked failed; anything not sent shows
 * in the CSV export's rr_status column, so nothing is lost.
 * --------------------------------------------------------------------- */

define( 'ARV_RT_APPLY_RR_URL', 'https://racereach.io/api/apply-public/racing-team' );
define( 'ARV_RT_APPLY_RR_CONSENT_VERSION', '2027-1' );
define( 'ARV_RT_APPLY_RR_FILE_MAX', 4 * 1024 * 1024 );
define( 'ARV_RT_APPLY_RR_BODY_MAX', 4000000 );
define( 'ARV_RT_APPLY_RR_TIMEOUT', 8 );

/** The apply endpoint (option arv_rt_apply_rr_url may override). */
function arv_rt_apply_rr_url() {
	$u = (string) get_option( 'arv_rt_apply_rr_url', '' );
	return ( '' !== $u && 0 === strpos( $u, 'https://' ) ) ? rtrim( $u, '/' ) : ARV_RT_APPLY_RR_URL;
}

/** Is the push switched on (default off). */
function arv_rt_apply_rr_enabled() {
	$v = get_option( 'arv_rt_apply_rr_push', '0' );
	return true === $v || in_array( strtolower( (string) $v ), array( '1', 'yes', 'on', 'true' ), true );
}

/** The shared secret, or ''. Never logged or printed. */
function arv_rt_apply_rr_secret() {
	if ( defined( 'RR_APPLY_SERVICE_SECRET' ) && '' !== (string) constant( 'RR_APPLY_SERVICE_SECRET' ) ) {
		return (string) constant( 'RR_APPLY_SERVICE_SECRET' );
	}
	return trim( (string) get_option( 'arv_rt_apply_rr_secret', '' ) );
}

/**
 * Base64url without padding.
 *
 * @param string $s Bytes.
 * @return string
 */
function arv_rt_apply_rr_b64u( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * A fresh v1 service token: v1.<b64u(json)>.<b64u(HMAC-SHA256(secret, "v1.<b64u(json)>"))>.
 *
 * @param string   $secret Shared secret.
 * @param int|null $iat    Issued at (unix s), default now.
 * @return string
 */
function arv_rt_apply_rr_token( $secret, $iat = null ) {
	$iat  = null === $iat ? time() : (int) $iat;
	$body = arv_rt_apply_rr_b64u(
		json_encode(
			array(
				'iss' => 'aravaipa-site',
				'aud' => 'racereach',
				'iat' => $iat,
				'exp' => $iat + 120,
			)
		)
	);
	$sig  = arv_rt_apply_rr_b64u( hash_hmac( 'sha256', 'v1.' . $body, (string) $secret, true ) );
	return 'v1.' . $body . '.' . $sig;
}

/** Optional payload keys: a 400 naming only these is resent once without them. */
function arv_rt_apply_rr_optional_keys() {
	return array( 'ultrasignup', 'profiles', 'goals', 'race_plans', 'summit', 'bring', 'instagram', 'strava', 'socials', 'content_links', 'sponsors', 'support', 'headshot', 'resume_file' );
}

/**
 * Site region answer => RaceReach code.
 *
 * West goes out as az (interim) until RaceReach's 2027 program takes the new
 * codes; the switch is a config change: update the option
 * arv_rt_apply_rr_region_codes (array answer => code), no release needed.
 * The old answers stay mapped so entries made before the change still push.
 *
 * @return array
 */
function arv_rt_apply_rr_region_map() {
	$map = array(
		'West'            => 'az',
		'Great Lakes'     => 'gl',
		'Northeast'       => 'ne',
		'Southeast'       => 'se',
		'Other'           => 'other',
		// Legacy answers (form before 2026-11-20).
		'Arizona'         => 'az',
		'Colorado'        => 'co',
		'California'      => 'ca',
		'Utah and Nevada' => 'utnv',
	);
	$o = get_option( 'arv_rt_apply_rr_region_codes', array() );
	if ( is_array( $o ) ) {
		foreach ( $o as $answer => $code ) {
			$code = strtolower( trim( (string) $code ) );
			if ( is_string( $answer ) && '' !== $answer && preg_match( '/^[a-z]{2,8}$/', $code ) ) {
				$map[ $answer ] = $code;
			}
		}
	}
	return $map;
}

/**
 * Log line when West goes out under the interim code, else ''.
 *
 * @param string $answer Region answer on the entry.
 * @param string $code   Code sent.
 * @return string
 */
function arv_rt_apply_rr_region_note( $answer, $code ) {
	if ( 'West' === $answer && 'west' !== $code ) {
		return 'region West sent as ' . $code . ' (interim)';
	}
	return '';
}

/** Site discipline answer => RaceReach code. */
function arv_rt_apply_rr_discipline_map() {
	return array(
		'Trail'         => 'trail',
		'Ultra'         => 'ultra',
		'Road'          => 'road',
		'Mountain bike' => 'mtb',
	);
}

/**
 * A field value as a list (CF7 and Flamingo keep arrays; the log keeps "a, b").
 *
 * @param mixed $v Value.
 * @return string[]
 */
function arv_rt_apply_rr_list( $v ) {
	if ( is_array( $v ) ) {
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $v ) ), 'strlen' ) );
	}
	$v = trim( (string) $v );
	return '' === $v ? array() : array_values( array_filter( array_map( 'trim', explode( ',', $v ) ), 'strlen' ) );
}

/**
 * Race plan labels ("Name, Mon D"). A joined string is split on the date part,
 * since race names can hold commas of their own.
 *
 * @param mixed $v Value.
 * @return string[]
 */
function arv_rt_apply_rr_race_labels( $v ) {
	if ( is_array( $v ) ) {
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $v ) ), 'strlen' ) );
	}
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return array();
	}
	preg_match_all( '/\s*(.+?, [A-Z][a-z]{2} \d{1,2})(?=, |$)/', $v, $m );
	return $m[1] ? array_map( 'trim', $m[1] ) : array( $v );
}

/**
 * Normalised race name for matching.
 *
 * @param string $s Name.
 * @return string
 */
function arv_rt_apply_rr_norm( $s ) {
	$s = strtolower( str_replace( '&', ' and ', (string) $s ) );
	$s = preg_replace( '/\b(the|trail runs?|ultras?|races?)\b/', ' ', $s );
	return preg_replace( '/[^a-z0-9]+/', '', $s );
}

/**
 * Significant words of a race name: lowercased, plural s dropped, without
 * distances, parentheses and words every race shares (trail, run, night...).
 *
 * @param string $s Name.
 * @return string[]
 */
function arv_rt_apply_rr_words( $s ) {
	$s    = strtolower( preg_replace( '/\([^)]*\)/', ' ', str_replace( array( '&', '.' ), array( ' and ', '' ), (string) $s ) ) );
	$stop = array( 'the', 'and', 'of', 'a', 'at', 'trail', 'run', 'running', 'race', 'night', 'ultra', 'festival', 'marathon', 'half', 'mile', 'miler', 'series', 'event' );
	$out  = array();
	foreach ( preg_split( '/[^a-z0-9]+/', $s ) as $w ) {
		if ( strlen( $w ) > 3 && 's' === substr( $w, -1 ) && 'ss' !== substr( $w, -2 ) ) {
			$w = substr( $w, 0, -1 );
		}
		if ( '' === $w || in_array( $w, $stop, true ) || preg_match( '/^\d+(k|m|mi)?$/', $w ) ) {
			continue;
		}
		$out[] = $w;
	}
	return array_values( array_unique( $out ) );
}

/**
 * RaceReach race id for each site label; labels with no match are left out.
 *
 * @param string[] $labels Site labels ("Name, Mon D").
 * @param array[]  $races  RaceReach races (id, name, date).
 * @return array label => id
 */
function arv_rt_apply_rr_match_races( $labels, $races ) {
	$out = array();
	foreach ( (array) $labels as $label ) {
		$label = trim( (string) $label );
		if ( ! preg_match( '/^(.*), ([A-Z][a-z]{2}) (\d{1,2})$/', $label, $m ) ) {
			continue;
		}
		$name = arv_rt_apply_rr_norm( $m[1] );
		$dt   = DateTime::createFromFormat( '!Y M j', ARV_RT_APPLY_SEASON . ' ' . $m[2] . ' ' . $m[3], new DateTimeZone( 'UTC' ) );
		if ( ! $dt ) {
			continue;
		}
		$md   = $dt->format( 'm-d' );
		$hits = array();
		foreach ( (array) $races as $r ) {
			if ( empty( $r['id'] ) || empty( $r['name'] ) ) {
				continue;
			}
			if ( '' !== $name && arv_rt_apply_rr_norm( $r['name'] ) === $name ) {
				$hits[] = $r;
			}
		}
		if ( count( $hits ) > 1 ) {
			$same = array_values(
				array_filter(
					$hits,
					function ( $r ) use ( $md ) {
						return isset( $r['date'] ) && substr( (string) $r['date'], 5, 5 ) === $md;
					}
				)
			);
			$hits = 1 === count( $same ) ? $same : array();
		}
		if ( ! $hits ) {
			// Fuzzy: races within a day of the label's date, scored by shared
			// name words ("Stump Jump 50k & 10 Miler" and "Stump Jump"). Only a
			// clear single best is taken; a tie drops the label.
			$want = arv_rt_apply_rr_words( $m[1] );
			$day  = strtotime( ARV_RT_APPLY_SEASON . '-' . $md . ' 12:00 UTC' );
			$best = 0.0;
			$next = 0.0;
			$pick = null;
			foreach ( (array) $races as $r ) {
				if ( empty( $r['id'] ) || empty( $r['date'] ) || ! $want ) {
					continue;
				}
				$rd = strtotime( substr( (string) $r['date'], 0, 10 ) . ' 12:00 UTC' );
				if ( ! $rd || abs( $rd - $day ) > 86400 ) {
					continue;
				}
				$have = arv_rt_apply_rr_words( isset( $r['name'] ) ? $r['name'] : '' );
				// A ride is never the run of the same name (Stunner Night Rides vs runs).
				if ( in_array( 'ride', $want, true ) !== in_array( 'ride', $have, true ) ) {
					continue;
				}
				$both = count( array_intersect( $want, $have ) );
				if ( ! $both ) {
					continue;
				}
				$score = $both / count( array_unique( array_merge( $want, $have ) ) ) + ( $rd === $day ? 0.001 : 0 );
				if ( $score > $best ) {
					$next = $best;
					$best = $score;
					$pick = $r;
				} elseif ( $score > $next ) {
					$next = $score;
				}
			}
			if ( $pick && $best - $next > 0.01 ) {
				$hits = array( $pick );
			}
		}
		if ( $hits ) {
			$out[ $label ] = (string) $hits[0]['id'];
		}
	}
	return $out;
}

/**
 * RaceReach races for the season, cached 5 minutes; null when the GET fails.
 *
 * @param bool $fresh Skip the cache.
 * @return array[]|null
 */
function arv_rt_apply_rr_races( $fresh = false ) {
	$cached = $fresh ? false : get_transient( 'arv_rt_apply_rr_races' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$res = wp_remote_get(
		arv_rt_apply_rr_url() . '/races',
		array(
			'timeout' => 5,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$d = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $d ) ) {
		return null;
	}
	$rows = array();
	foreach ( $d as $r ) {
		if ( is_array( $r ) && ! empty( $r['id'] ) ) {
			$rows[] = array(
				'id'   => (string) $r['id'],
				'name' => isset( $r['name'] ) ? (string) $r['name'] : '',
				'date' => isset( $r['date'] ) ? (string) $r['date'] : '',
			);
		}
	}
	set_transient( 'arv_rt_apply_rr_races', $rows, 300 );
	return $rows;
}

/**
 * An https link, or ''. Bare hosts get https://, http:// is upgraded.
 *
 * @param string $v Link.
 * @return string
 */
function arv_rt_apply_rr_https( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v || preg_match( '/\s/', $v ) ) {
		return '';
	}
	if ( 0 === stripos( $v, 'http://' ) ) {
		$v = 'https://' . substr( $v, 7 );
	} elseif ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $v ) ) {
		if ( ! preg_match( '/^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(\/.*)?$/i', $v ) ) {
			return '';
		}
		$v = 'https://' . $v;
	}
	if ( 0 !== stripos( $v, 'https://' ) || ! filter_var( $v, FILTER_VALIDATE_URL ) ) {
		return '';
	}
	return $v;
}

/**
 * Free text summit answer => az|co|gl|ne|not_sure, or '' when unclear.
 *
 * @param string $v Answer.
 * @return string
 */
function arv_rt_apply_rr_summit( $v ) {
	$t = strtolower( trim( (string) $v ) );
	if ( '' === $t ) {
		return '';
	}
	if ( preg_match( "/\b(not sure|unsure|don'?t know|dont know|tbd|undecided|maybe)\b/", $t ) ) {
		return 'not_sure';
	}
	$hits = array();
	$pats = array(
		'az' => '/\b(arizona|az|phoenix|tucson|flagstaff|sedona)\b/',
		'co' => '/\b(colorado|co|denver|boulder)\b/',
		'gl' => '/\b(great lakes|michigan|wisconsin|minnesota|ohio|illinois)\b/',
		'ne' => '/\b(northeast|north east|new england|new york|vermont|new hampshire|maine)\b/',
	);
	foreach ( $pats as $code => $re ) {
		if ( preg_match( $re, $t ) ) {
			$hits[] = $code;
		}
	}
	return 1 === count( $hits ) ? $hits[0] : '';
}

/**
 * Birth date as YYYY-MM-DD, or the input trimmed when it cannot be read.
 *
 * @param string $v Date.
 * @return string
 */
function arv_rt_apply_rr_date( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
		return $v;
	}
	if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $m[1], $m[2] );
	}
	$ts = strtotime( $v . ' 12:00 UTC' );
	return $ts ? gmdate( 'Y-m-d', $ts ) : $v;
}

/**
 * Is a consent box ticked.
 *
 * @param mixed $v Value.
 * @return bool
 */
function arv_rt_apply_rr_ticked( $v ) {
	$v = is_array( $v ) ? implode( '', array_map( 'strval', $v ) ) : trim( (string) $v );
	return '' !== $v && 'no' !== strtolower( $v ) && '0' !== $v;
}

/**
 * The ApplyFormV2 payload for one entry.
 *
 * @param array $fields   Entry fields keyed rt-*.
 * @param array $race_ids RaceReach races (id, name, date) from the races GET; empty sends no race_plans.
 * @return array
 */
function arv_rt_apply_rr_payload( array $fields, array $race_ids ) {
	$g = function ( $k ) use ( $fields ) {
		$v = isset( $fields[ 'rt-' . $k ] ) ? $fields[ 'rt-' . $k ] : '';
		return is_array( $v ) ? trim( implode( ', ', array_map( 'strval', $v ) ) ) : trim( (string) $v );
	};
	$region = arv_rt_apply_rr_region_map();
	$disc   = arv_rt_apply_rr_discipline_map();
	$p      = array(
		'first_name' => $g( 'first-name' ),
		'last_name'  => $g( 'last-name' ),
		'email'      => strtolower( $g( 'email' ) ),
		'phone'      => $g( 'phone' ),
		'birth_date' => arv_rt_apply_rr_date( $g( 'birth-date' ) ),
		'city'       => $g( 'city' ),
		'state'      => strtoupper( $g( 'state' ) ),
		'region'     => isset( $region[ $g( 'region' ) ] ) ? $region[ $g( 'region' ) ] : 'other',
	);
	$d = array();
	foreach ( arv_rt_apply_rr_list( isset( $fields['rt-disciplines'] ) ? $fields['rt-disciplines'] : '' ) as $x ) {
		if ( isset( $disc[ $x ] ) && ! in_array( $disc[ $x ], $d, true ) ) {
			$d[] = $disc[ $x ];
		}
	}
	$p['disciplines'] = $d;
	$p['returning']   = 'yes' === strtolower( $g( 'returning' ) ) ? 'yes' : 'no';
	if ( 'yes' === $p['returning'] && '' !== $g( 'returning-years' ) ) {
		$p['returning_years'] = $g( 'returning-years' );
	}
	$p['resume'] = function_exists( 'mb_substr' ) ? mb_substr( $g( 'resume' ), 0, 6000 ) : substr( $g( 'resume' ), 0, 6000 );
	$us          = arv_rt_apply_rr_https( $g( 'ultrasignup' ) );
	if ( '' !== $us ) {
		$p['ultrasignup'] = $us;
	}
	$prof = array();
	$raw  = isset( $fields['rt-profiles'] ) ? $fields['rt-profiles'] : '';
	foreach ( preg_split( '/[\s,]+/', is_array( $raw ) ? implode( "\n", $raw ) : (string) $raw ) as $x ) {
		$x = arv_rt_apply_rr_https( $x );
		if ( '' !== $x && ! in_array( $x, $prof, true ) && count( $prof ) < 5 ) {
			$prof[] = $x;
		}
	}
	if ( $prof ) {
		$p['profiles'] = implode( "\n", $prof );
	}
	foreach ( array( 'goals' ) as $k ) {
		if ( '' !== $g( $k ) ) {
			$p[ $k ] = $g( $k );
		}
	}
	if ( $race_ids ) {
		$ids = array_values( array_unique( array_values( arv_rt_apply_rr_match_races( arv_rt_apply_rr_race_labels( isset( $fields['rt-race-plans'] ) ? $fields['rt-race-plans'] : '' ), $race_ids ) ) ) );
		if ( $ids ) {
			$p['race_plans'] = $ids;
		}
	}
	$p['outside_races'] = $g( 'outside-races' );
	$summit             = arv_rt_apply_rr_summit( $g( 'summit' ) );
	if ( '' !== $summit ) {
		$p['summit'] = $summit;
	}
	$p['why'] = $g( 'why' );
	foreach ( array( 'bring', 'instagram', 'strava', 'socials', 'content-links', 'sponsors', 'support' ) as $k ) {
		if ( '' !== $g( $k ) ) {
			$p[ str_replace( '-', '_', $k ) ] = $g( $k );
		}
	}
	$p['size_fit']    = $g( 'size-fit' );
	$p['size_tee']    = $g( 'size-tee' );
	$p['size_top']    = $g( 'size-top' );
	$p['size_shorts'] = $g( 'size-shorts' );
	$p['shoe_size']   = (string) $g( 'shoe-size' );
	$c                = array();
	foreach ( arv_rt_apply_consents() as $x ) {
		$c[ $x['key'] ] = arv_rt_apply_rr_ticked( isset( $fields[ 'rt-consent-' . $x['key'] ] ) ? $fields[ 'rt-consent-' . $x['key'] ] : '' );
	}
	$p['consents']        = $c;
	$p['consent_version'] = ARV_RT_APPLY_RR_CONSENT_VERSION;
	$p['website']         = '';
	return $p;
}

/**
 * Applicant IP from the request: Cloudflare's header (the origin only takes
 * Cloudflare traffic), else CF7's remote_ip. Loopback and private addresses
 * (the CLI test) give '', so RaceReach counts the server's own address.
 *
 * @param object|null $submission CF7 submission.
 * @return string
 */
function arv_rt_apply_rr_request_ip( $submission = null ) {
	$cf = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$pub = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
	if ( '' !== $cf && filter_var( $cf, FILTER_VALIDATE_IP, $pub ) ) {
		return $cf;
	}
	if ( $submission && method_exists( $submission, 'get_meta' ) ) {
		$ip = trim( (string) $submission->get_meta( 'remote_ip' ) );
		if ( filter_var( $ip, FILTER_VALIDATE_IP, $pub ) ) {
			return $ip;
		}
	}
	return '';
}

/**
 * Mime type for a saved file.
 *
 * @param string $path Path.
 * @return string
 */
function arv_rt_apply_rr_mime( $path ) {
	$map = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'heic' => 'image/heic',
		'pdf'  => 'application/pdf',
	);
	$ext = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
	return isset( $map[ $ext ] ) ? $map[ $ext ] : 'application/octet-stream';
}

/**
 * Mime type from the file's bytes: finfo when the server has it, else a
 * magic-byte sniff for the types we care about. Empty when unknown.
 *
 * @param string $path File path.
 * @return string
 */
function arv_rt_apply_rr_sniff_mime( $path ) {
	if ( function_exists( 'finfo_open' ) ) {
		$fi = finfo_open( FILEINFO_MIME_TYPE );
		if ( $fi ) {
			$m = finfo_file( $fi, $path );
			finfo_close( $fi );
			if ( is_string( $m ) && '' !== $m ) {
				return strtolower( $m );
			}
		}
	}
	$h = (string) file_get_contents( $path, false, null, 0, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( 0 === strpos( $h, "\xFF\xD8\xFF" ) ) {
		return 'image/jpeg';
	}
	if ( 0 === strpos( $h, "\x89PNG\r\n\x1A\n" ) ) {
		return 'image/png';
	}
	if ( 0 === strpos( $h, 'RIFF' ) && 'WEBP' === substr( $h, 8, 4 ) ) {
		return 'image/webp';
	}
	if ( 'ftyp' === substr( $h, 4, 4 ) && preg_match( '/^(heic|heix|hevc|heim|heis|mif1|msf1)/', substr( $h, 8, 4 ) ) ) {
		return 'image/heic';
	}
	return '';
}

/**
 * Headshot check: extension AND detected mime must both be jpg/jpeg/png/webp
 * and agree. RaceReach only takes those, so anything else (a HEIC the
 * browser could not convert, a mislabeled file) stays behind.
 *
 * @param string $path File path.
 * @return string Empty when fine, else the skip reason.
 */
function arv_rt_apply_rr_headshot_problem( $path ) {
	$ok  = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
	);
	$ext = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
	$ext = preg_replace( '/[^a-z0-9]/', '', $ext );
	if ( ! isset( $ok[ $ext ] ) ) {
		return 'headshot not jpg/png/webp (' . ( '' !== $ext ? substr( $ext, 0, 10 ) : 'no extension' ) . ')';
	}
	$mime = arv_rt_apply_rr_sniff_mime( $path );
	if ( $mime !== $ok[ $ext ] ) {
		return 'headshot not jpg/png/webp (' . $ext . ' file, ' . ( '' !== $mime ? substr( preg_replace( '/[^a-z0-9.\/+-]/', '', $mime ), 0, 40 ) : 'unknown' ) . ' inside)';
	}
	return '';
}

/**
 * Which saved files go along: each under 4 MB, together under about 4 MB,
 * and the headshot only when it really is a jpg, png or webp.
 *
 * @param array $files Saved files keyed rt-headshot, rt-resume-file.
 * @return array array( parts => array( field => path ), skipped => string[] )
 */
function arv_rt_apply_rr_pick_files( $files ) {
	$parts   = array();
	$skipped = array();
	$total   = 0;
	foreach ( array( 'rt-headshot' => 'headshot', 'rt-resume-file' => 'resume_file' ) as $site => $rr ) {
		if ( empty( $files[ $site ] ) ) {
			continue;
		}
		$p = (string) $files[ $site ];
		if ( ! is_file( $p ) || ! is_readable( $p ) ) {
			$skipped[] = $rr . ' missing';
			continue;
		}
		$sz = (int) filesize( $p );
		if ( $sz <= 0 || $sz >= ARV_RT_APPLY_RR_FILE_MAX ) {
			$skipped[] = $rr . ' over 4 MB';
			continue;
		}
		if ( 'headshot' === $rr ) {
			// Extension AND sniffed mime (finfo, else magic bytes) must be jpg/jpeg/png/webp; HEIC and the rest stay behind.
			$bad = arv_rt_apply_rr_headshot_problem( $p );
			if ( '' !== $bad ) {
				$skipped[] = $bad;
				continue;
			}
		}
		if ( $total + $sz > ARV_RT_APPLY_RR_BODY_MAX ) {
			$skipped[] = $rr . ' over the 4 MB total';
			continue;
		}
		$total        += $sz;
		$parts[ $rr ] = $p;
	}
	return array(
		'parts'   => $parts,
		'skipped' => $skipped,
	);
}

/**
 * Request body and content type: multipart when files go along, else JSON.
 *
 * @param array $payload Payload.
 * @param array $parts   field => path.
 * @return array array( body, content_type )
 */
function arv_rt_apply_rr_body( $payload, $parts ) {
	$json = wp_json_encode( $payload );
	if ( ! $parts ) {
		return array( $json, 'application/json' );
	}
	$b    = 'arvrt' . md5( uniqid( '', true ) );
	$body = '--' . $b . "\r\nContent-Disposition: form-data; name=\"payload\"\r\nContent-Type: application/json\r\n\r\n" . $json . "\r\n";
	foreach ( $parts as $field => $path ) {
		$body .= '--' . $b . "\r\nContent-Disposition: form-data; name=\"" . $field . '"; filename="' . preg_replace( '/[^A-Za-z0-9._-]/', '_', basename( $path ) ) . "\"\r\nContent-Type: " . arv_rt_apply_rr_mime( $path ) . "\r\n\r\n" . file_get_contents( $path ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	$body .= '--' . $b . "--\r\n";
	return array( $body, 'multipart/form-data; boundary=' . $b );
}

/**
 * Masked email for logs: a***@example.com.
 *
 * @param string $e Email.
 * @return string
 */
function arv_rt_apply_rr_mask( $e ) {
	$e = (string) $e;
	$at = strpos( $e, '@' );
	return false === $at ? '***' : substr( $e, 0, 1 ) . '***' . substr( $e, $at );
}

/**
 * Short error text, with anything token-like taken out.
 *
 * @param string $s Text.
 * @return string
 */
function arv_rt_apply_rr_clean( $s ) {
	$s = preg_replace( '/v1\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', '[token]', (string) $s );
	$sec = arv_rt_apply_rr_secret();
	if ( '' !== $sec ) {
		$s = str_replace( $sec, '[secret]', $s );
	}
	return substr( trim( preg_replace( '/\s+/', ' ', $s ) ), 0, 200 );
}

/**
 * One line in the ring log option (last 100).
 *
 * @param string $line Line.
 */
function arv_rt_apply_rr_ring( $line ) {
	$log = get_option( 'arv_rt_apply_log', array() );
	$log = is_array( $log ) ? $log : array();
	$log[] = gmdate( 'Y-m-d\TH:i:s\Z' ) . ' ' . arv_rt_apply_rr_clean( $line );
	if ( count( $log ) > 100 ) {
		$log = array_slice( $log, -100 );
	}
	update_option( 'arv_rt_apply_log', $log, false );
}

/** Retry delays after attempt 1, 2 and 3 (seconds). */
function arv_rt_apply_rr_delays() {
	return array( 300, 1800, 10800 );
}

/**
 * Does a status code get retried.
 *
 * @param int $code HTTP code, 0 for a network error.
 * @return bool
 */
function arv_rt_apply_rr_retriable( $code ) {
	$code = (int) $code;
	return 0 === $code || 401 === $code || 429 === $code || $code >= 500;
}

/**
 * Send one request. Returns code (0 on network error), error and fields.
 *
 * @param array  $payload Payload.
 * @param array  $parts   Files.
 * @param string $ip      Applicant IP.
 * @return array
 */
function arv_rt_apply_rr_send( $payload, $parts, $ip ) {
	list( $body, $type ) = arv_rt_apply_rr_body( $payload, $parts );
	$headers             = array(
		'Authorization' => 'Bearer ' . arv_rt_apply_rr_token( arv_rt_apply_rr_secret() ),
		'Content-Type'  => $type,
		'Accept'        => 'application/json',
	);
	if ( '' !== (string) $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		$headers['X-Applicant-IP'] = (string) $ip;
	}
	$res = wp_remote_post(
		arv_rt_apply_rr_url(),
		array(
			'timeout'     => ARV_RT_APPLY_RR_TIMEOUT,
			'redirection' => 0,
			'headers'     => $headers,
			'body'        => $body,
			'data_format' => 'body',
		)
	);
	if ( is_wp_error( $res ) ) {
		return array(
			'code'   => 0,
			'error'  => 'network: ' . $res->get_error_message(),
			'fields' => array(),
			'multipart' => (bool) $parts,
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$d    = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return array(
		'code'      => $code,
		'error'     => 200 === $code ? '' : ( is_array( $d ) && isset( $d['error'] ) ? (string) $d['error'] : 'HTTP ' . $code ),
		'fields'    => is_array( $d ) && isset( $d['fields'] ) && is_array( $d['fields'] ) ? array_values( array_map( 'strval', $d['fields'] ) ) : array(),
		'multipart' => (bool) $parts,
	);
}

/**
 * Record a result: entry meta, entries.jsonl, ring log.
 *
 * @param array $entry Entry (id, email).
 * @param array $rec   Record.
 */
function arv_rt_apply_rr_record( $entry, $rec ) {
	$id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
	if ( $id ) {
		update_post_meta( $id, '_arv_rt_rr', $rec );
	}
	$email = isset( $entry['email'] ) ? strtolower( (string) $entry['email'] ) : '';
	$line  = array(
		'kind'        => 'rr',
		'at'          => $rec['at'],
		'flamingo_id' => $id,
		'email'       => $email,
		'rr'          => $rec,
	);
	file_put_contents( arv_rt_apply_dir() . '/entries.jsonl', wp_json_encode( $line ) . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	arv_rt_apply_rr_ring( '#' . $id . ' ' . arv_rt_apply_rr_mask( $email ) . ' ' . $rec['status'] . ' ' . $rec['code'] . ' try ' . $rec['attempts'] . ( $rec['error'] ? ' ' . $rec['error'] : '' ) . ( ! empty( $rec['skipped_files'] ) ? ' files: ' . implode( ', ', $rec['skipped_files'] ) : '' ) );
}

/**
 * Push one entry to RaceReach. Never throws.
 *
 * @param array $entry id (Flamingo id or 0), email, fields (rt-*), files, ip.
 * @param array $opts  dry_run (bool), force (ignore the flag, for the CLI).
 * @return array Record (status sent|retry|failed|skipped, code, error, attempts, at, rr_fields) plus payload on dry runs.
 */
function arv_rt_apply_rr_push( $entry, $opts = array() ) {
	$dry = ! empty( $opts['dry_run'] );
	try {
		$id   = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
		$prev = $id ? get_post_meta( $id, '_arv_rt_rr', true ) : array();
		$prev = is_array( $prev ) ? $prev : array();
		$rec  = array(
			'status'   => 'skipped',
			'code'     => 0,
			'error'    => '',
			'attempts' => isset( $prev['attempts'] ) ? (int) $prev['attempts'] : 0,
			'at'       => gmdate( 'c', arv_rt_apply_now() ),
			'rr_fields' => array(),
		);
		if ( ! arv_rt_apply_rr_enabled() && empty( $opts['force'] ) ) {
			$rec['error'] = 'push is off';
			return $rec;
		}
		if ( '' === arv_rt_apply_rr_secret() ) {
			$rec['error'] = 'no secret';
			if ( ! $dry ) {
				arv_rt_apply_rr_record( $entry, $rec );
			}
			return $rec;
		}
		$races = arv_rt_apply_rr_races();
		$fields = isset( $entry['fields'] ) ? (array) $entry['fields'] : array();
		$payload = arv_rt_apply_rr_payload( $fields, is_array( $races ) ? $races : array() );
		$pick    = arv_rt_apply_rr_pick_files( isset( $entry['files'] ) ? (array) $entry['files'] : array() );
		$rec['skipped_files'] = $pick['skipped'];
		if ( null === $races ) {
			$rec['note'] = 'races GET failed, race_plans not sent';
		}
		$ra    = isset( $fields['rt-region'] ) ? trim( is_array( $fields['rt-region'] ) ? implode( ', ', $fields['rt-region'] ) : (string) $fields['rt-region'] ) : '';
		$rnote = arv_rt_apply_rr_region_note( $ra, isset( $payload['region'] ) ? (string) $payload['region'] : '' );
		if ( '' !== $rnote ) {
			$rec['region_note'] = $rnote;
			$rec['note']        = isset( $rec['note'] ) ? $rec['note'] . '; ' . $rnote : $rnote;
		}
		$dropped = ! empty( $prev['dropped'] ) ? (array) $prev['dropped'] : array();
		foreach ( $dropped as $k ) {
			unset( $payload[ $k ] );
			if ( isset( $pick['parts'][ $k ] ) ) {
				unset( $pick['parts'][ $k ] );
			}
		}
		if ( $dry ) {
			$rec['status']  = 'dry_run';
			$rec['payload'] = $payload;
			$rec['files']   = array_keys( $pick['parts'] );
			return $rec;
		}
		$ip = isset( $entry['ip'] ) ? (string) $entry['ip'] : '';
		++$rec['attempts'];
		$r = arv_rt_apply_rr_send( $payload, $pick['parts'], $ip );
		// A 400 naming only optional fields: resend once without them.
		$opt = arv_rt_apply_rr_optional_keys();
		if ( 400 === $r['code'] && $r['fields'] && ! array_diff( $r['fields'], $opt ) && ! $dropped ) {
			$dropped = $r['fields'];
			foreach ( $dropped as $k ) {
				unset( $payload[ $k ] );
				unset( $pick['parts'][ $k ] );
			}
			$rec['dropped'] = $dropped;
			++$rec['attempts'];
			$r = arv_rt_apply_rr_send( $payload, $pick['parts'], $ip );
		} elseif ( $dropped ) {
			$rec['dropped'] = $dropped;
		}
		$rec['code']      = (int) $r['code'];
		$rec['rr_fields'] = $r['fields'];
		$rec['error']     = arv_rt_apply_rr_clean( $r['error'] );
		$rec['multipart'] = $r['multipart'];
		if ( 200 === $r['code'] ) {
			$rec['status'] = 'sent';
			$rec['error']  = '';
		} elseif ( arv_rt_apply_rr_retriable( $r['code'] ) ) {
			$tries  = isset( $prev['retries'] ) ? (int) $prev['retries'] : 0;
			$delays = arv_rt_apply_rr_delays();
			if ( $id && $tries < count( $delays ) ) {
				$rec['status']   = 'retry';
				$rec['retries']  = $tries + 1;
				$rec['next_at']  = gmdate( 'c', time() + $delays[ $tries ] );
				wp_schedule_single_event( time() + $delays[ $tries ], 'arv_rt_apply_rr_retry', array( $id ) );
			} else {
				$rec['status']  = 'failed';
				$rec['retries'] = $tries;
			}
		} else {
			$rec['status'] = 'failed';
		}
		arv_rt_apply_rr_record( $entry, $rec );
		return $rec;
	} catch ( \Throwable $e ) {
		$rec = array(
			'status'   => 'failed',
			'code'     => 0,
			'error'    => arv_rt_apply_rr_clean( 'exception: ' . $e->getMessage() ),
			'attempts' => isset( $rec['attempts'] ) ? (int) $rec['attempts'] : 0,
			'at'       => gmdate( 'c' ),
			'rr_fields' => array(),
		);
		if ( ! $dry ) {
			try {
				arv_rt_apply_rr_record( $entry, $rec );
			} catch ( \Throwable $e2 ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Nothing more to do: the entry is still in Flamingo and the log.
			}
		}
		return $rec;
	}
}

/**
 * One stored entry by Flamingo id, ready to push; null when not found.
 *
 * @param int $id Flamingo id.
 * @return array|null
 */
function arv_rt_apply_rr_entry( $id ) {
	foreach ( arv_rt_apply_entries() as $r ) {
		if ( (int) $r['id'] === (int) $id ) {
			return arv_rt_apply_rr_from_row( $r );
		}
	}
	return null;
}

/**
 * Turn an export row into a push entry.
 *
 * @param array $r Row from arv_rt_apply_entries().
 * @return array
 */
function arv_rt_apply_rr_from_row( $r ) {
	$id = (int) $r['id'];
	$ip = $id ? (string) get_post_meta( $id, '_arv_rt_ip', true ) : '';
	return array(
		'id'     => $id,
		'email'  => isset( $r['fields']['rt-email'] ) ? strtolower( (string) $r['fields']['rt-email'] ) : '',
		'fields' => (array) $r['fields'],
		'files'  => (array) $r['files'],
		'ip'     => $ip,
		'at'     => isset( $r['at'] ) ? $r['at'] : '',
	);
}

/**
 * Push status for an entry ('' when never pushed).
 *
 * @param int $id Flamingo id.
 * @return string
 */
function arv_rt_apply_rr_status( $id ) {
	if ( ! $id ) {
		return '';
	}
	$m = get_post_meta( (int) $id, '_arv_rt_rr', true );
	return is_array( $m ) && isset( $m['status'] ) ? (string) $m['status'] : '';
}

/**
 * Files that did not go to RaceReach for an entry, with the reason each
 * (for example 'resume_file over 4 MB'); '' when none.
 *
 * @param int $id Flamingo id.
 * @return string
 */
function arv_rt_apply_rr_skipped( $id ) {
	if ( ! $id ) {
		return '';
	}
	$m = get_post_meta( (int) $id, '_arv_rt_rr', true );
	return is_array( $m ) && ! empty( $m['skipped_files'] ) ? implode( '; ', (array) $m['skipped_files'] ) : '';
}

/**
 * Scheduled retry for one entry.
 *
 * @param int $id Flamingo id.
 */
function arv_rt_apply_rr_retry( $id ) {
	try {
		if ( 'retry' !== arv_rt_apply_rr_status( $id ) ) {
			return;
		}
		$e = arv_rt_apply_rr_entry( $id );
		if ( $e ) {
			arv_rt_apply_rr_push( $e );
		}
	} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Never surface: the entry keeps its retry state for push --pending.
	}
}
add_action( 'arv_rt_apply_rr_retry', 'arv_rt_apply_rr_retry', 10, 1 );

/** The entry waiting to be pushed once this request has answered. */
$GLOBALS['arv_rt_apply_rr_queue'] = null;

/**
 * After CF7 has mailed and Flamingo has stored the entry (wpcf7_submit runs
 * Flamingo at priority 10), queue the push. It runs on shutdown, after the
 * response is out; the CLI test pushes inline so it can report the result.
 *
 * @param object $form   CF7 form.
 * @param array  $result CF7 result.
 */
function arv_rt_apply_rr_on_submit( $form, $result = array() ) {
	try {
		if ( ! arv_rt_apply_is_ours( $form ) || ! arv_rt_apply_rr_enabled() ) {
			return;
		}
		$st = is_array( $result ) && isset( $result['status'] ) ? $result['status'] : '';
		if ( ! in_array( $st, array( 'mail_sent', 'mail_failed' ), true ) ) {
			return;
		}
		$last = $GLOBALS['arv_rt_apply_last'];
		if ( ! $last ) {
			return;
		}
		$id    = ! empty( $last['flamingo_id'] ) ? (int) $last['flamingo_id'] : ( ! empty( $result['flamingo_inbound_id'] ) ? (int) $result['flamingo_inbound_id'] : 0 );
		$fields = $last['fields'];
		foreach ( isset( $last['lists'] ) ? (array) $last['lists'] : array() as $k => $v ) {
			$fields[ $k ] = $v;
		}
		$GLOBALS['arv_rt_apply_rr_queue'] = array(
			'id'     => $id,
			'email'  => $last['email'],
			'fields' => $fields,
			'files'  => $last['files'],
			'ip'     => isset( $last['ip'] ) ? $last['ip'] : '',
		);
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			arv_rt_apply_rr_flush();
		} else {
			add_action( 'shutdown', 'arv_rt_apply_rr_shutdown', 1 );
		}
	} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// The applicant's submission has already succeeded; never break it.
	}
}
add_action( 'wpcf7_submit', 'arv_rt_apply_rr_on_submit', 20, 2 );

/** Push the queued entry now. */
function arv_rt_apply_rr_flush() {
	$q = $GLOBALS['arv_rt_apply_rr_queue'];
	$GLOBALS['arv_rt_apply_rr_queue'] = null;
	if ( $q ) {
		$GLOBALS['arv_rt_apply_rr_last'] = arv_rt_apply_rr_push( $q );
	}
}

/** On shutdown: hand the response to the applicant first, then push. */
function arv_rt_apply_rr_shutdown() {
	try {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		arv_rt_apply_rr_flush();
	} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Response already sent; the entry is in Flamingo and the log.
	}
}

/**
 * Counts by push status over every stored entry ('none' = never pushed).
 *
 * @return int[]
 */
function arv_rt_apply_rr_counts() {
	$n = array();
	foreach ( arv_rt_apply_entries() as $r ) {
		$s       = arv_rt_apply_rr_status( $r['id'] );
		$s       = '' === $s ? 'none' : $s;
		$n[ $s ] = isset( $n[ $s ] ) ? $n[ $s ] + 1 : 1;
	}
	return $n;
}

/**
 * Entries a CLI push run should take.
 *
 * @param string $mode  pending, entry or backfill.
 * @param array  $assoc Flags (entry, since).
 * @return array[] Push entries.
 */
function arv_rt_apply_rr_select( $mode, $assoc ) {
	$out   = array();
	$since = isset( $assoc['since'] ) ? strtotime( (string) $assoc['since'] ) : 0;
	foreach ( arv_rt_apply_entries() as $r ) {
		if ( ! $r['id'] ) {
			continue;
		}
		$m = get_post_meta( (int) $r['id'], '_arv_rt_rr', true );
		$m = is_array( $m ) ? $m : array();
		$s = isset( $m['status'] ) ? $m['status'] : '';
		if ( 'entry' === $mode ) {
			$take = (int) $assoc['entry'] === (int) $r['id'];
		} elseif ( 'pending' === $mode ) {
			$take = 'retry' === $s || 'skipped' === $s || ( 'failed' === $s && arv_rt_apply_rr_retriable( isset( $m['code'] ) ? $m['code'] : 0 ) );
		} else {
			$at   = strtotime( (string) $r['at'] . ( preg_match( '/Z|[+-]\d\d:?\d\d$/', (string) $r['at'] ) ? '' : ' UTC' ) );
			$take = '' === $s && ( ! $since || ( $at && $at >= $since ) );
		}
		if ( $take ) {
			$out[] = arv_rt_apply_rr_from_row( $r );
		}
	}
	return $out;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * 2027 Racing Team application form.
	 */
	class ARV_RT_Apply_CLI {
		/**
		 * Create or update the CF7 form.
		 *
		 * [--dry-run]
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function install( $args, $assoc ) {
			$r = arv_rt_apply_install( ! empty( $assoc['dry-run'] ) );
			WP_CLI::log( is_int( $r ) ? 'form id ' . $r : $r );
		}

		/** Window, form id, entries. */
		public function status() {
			list( $o, $c ) = arv_rt_apply_window();
			WP_CLI::log( 'state ' . arv_rt_apply_state() . ' opens ' . gmdate( 'c', $o ) . ' closes ' . gmdate( 'c', $c ) );
			WP_CLI::log( 'form id ' . arv_rt_apply_form_id() . ' recipients ' . arv_rt_apply_recipients() );
			WP_CLI::log( 'test override ' . ( arv_rt_apply_test_override() ? 'on' : 'off' ) . ' entries ' . count( arv_rt_apply_entries() ) . ' races ' . count( arv_rt_apply_race_options() ) );
		}

		/**
		 * Stored entries for one email: Flamingo id and saved files (JSON).
		 *
		 * --email=<e>
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function find( $args, $assoc ) {
			$e   = strtolower( (string) $assoc['email'] );
			$out = array();
			foreach ( arv_rt_apply_entries() as $r ) {
				if ( isset( $r['fields']['rt-email'] ) && strtolower( (string) $r['fields']['rt-email'] ) === $e ) {
					$files = array();
					foreach ( (array) $r['files'] as $k => $p ) {
						$files[ $k ] = is_file( $p ) ? basename( $p ) : 'missing';
					}
					$out[] = array( 'flamingo_id' => $r['id'], 'files' => $files );
				}
			}
			WP_CLI::log( wp_json_encode( $out ) );
		}

		/** Print the window state. */
		public function window() {
			WP_CLI::log( arv_rt_apply_state() );
		}

		/**
		 * CSV of every application.
		 *
		 * [--file=<csv>]
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function export( $args, $assoc ) {
			$fh = ! empty( $assoc['file'] ) ? fopen( $assoc['file'], 'w' ) : fopen( 'php://stdout', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$n  = arv_rt_apply_export_csv( $fh );
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			if ( ! empty( $assoc['file'] ) ) {
				WP_CLI::log( $n . ' rows to ' . $assoc['file'] );
			}
		}

		/**
		 * Run one TEST application through the real pipeline. With the
		 * RaceReach push on, it is pushed too and the result printed.
		 *
		 * ## OPTIONS
		 *
		 * --email=<e>
		 * : Applicant email for the TEST entry.
		 *
		 * [--[no-]mail]
		 * : Send the staff notice email (default yes). --no-mail skips it.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function test( $args, $assoc ) {
			if ( ( isset( $assoc['mail'] ) && false === $assoc['mail'] ) || ! empty( $assoc['no-mail'] ) ) {
				add_filter( 'wpcf7_skip_mail', '__return_true' );
			}
			$GLOBALS['arv_rt_apply_rr_last'] = null;
			$r = arv_rt_apply_run_test( (string) $assoc['email'] );
			WP_CLI::log( 'status ' . $r['status'] . ( isset( $r['message'] ) ? ' ' . $r['message'] : '' ) );
			if ( ! in_array( $r['status'], array( 'mail_sent', 'mail_failed' ), true ) ) {
				WP_CLI::error( 'test submission failed: ' . wp_json_encode( isset( $r['invalid_fields'] ) ? $r['invalid_fields'] : $r ) );
			}
			$last = isset( $GLOBALS['arv_rt_apply_last'] ) ? $GLOBALS['arv_rt_apply_last'] : null;
			if ( $last && ! empty( $last['flamingo_id'] ) ) {
				WP_CLI::log( 'flamingo id ' . (int) $last['flamingo_id'] );
			}
			$rr = $GLOBALS['arv_rt_apply_rr_last'];
			if ( $rr ) {
				WP_CLI::log( 'rr ' . $rr['status'] . ' ' . $rr['code'] . ( $rr['error'] ? ' ' . $rr['error'] : '' ) . ( ! empty( $rr['rr_fields'] ) ? ' fields ' . implode( ',', $rr['rr_fields'] ) : '' ) );
			} else {
				WP_CLI::log( 'rr not pushed (push ' . ( arv_rt_apply_rr_enabled() ? 'on' : 'off' ) . ')' );
			}
			WP_CLI::success( 'test submitted' );
		}

		/**
		 * RaceReach push: flag, secret present, url, counts, last 10 log lines.
		 *
		 * @subcommand rr-status
		 */
		public function rr_status() {
			WP_CLI::log( 'push ' . ( arv_rt_apply_rr_enabled() ? 'on' : 'off' ) );
			WP_CLI::log( 'secret ' . ( '' !== arv_rt_apply_rr_secret() ? 'yes' : 'no' ) . ( defined( 'RR_APPLY_SERVICE_SECRET' ) ? ' (wp-config)' : ' (option)' ) );
			WP_CLI::log( 'url ' . arv_rt_apply_rr_url() );
			WP_CLI::log( 'counts ' . wp_json_encode( arv_rt_apply_rr_counts() ) );
			$log = get_option( 'arv_rt_apply_log', array() );
			foreach ( array_slice( is_array( $log ) ? $log : array(), -10 ) as $l ) {
				WP_CLI::log( '  ' . $l );
			}
		}

		/**
		 * RaceReach race ids against the site's race picker labels.
		 *
		 * @subcommand rr-races
		 */
		public function rr_races() {
			$races = arv_rt_apply_rr_races( true );
			if ( null === $races ) {
				WP_CLI::error( 'races GET failed' );
			}
			$labels = arv_rt_apply_race_options();
			$map    = arv_rt_apply_rr_match_races( $labels, $races );
			foreach ( $labels as $l ) {
				WP_CLI::log( ( isset( $map[ $l ] ) ? $map[ $l ] : '(dropped)' ) . "\t" . $l );
			}
			WP_CLI::log( 'matched ' . count( $map ) . ' of ' . count( $labels ) . ' site labels; RaceReach has ' . count( $races ) . ' races' );
		}

		/**
		 * Push stored entries to RaceReach (needs the secret; ignores the flag).
		 *
		 * [--pending]
		 * : Entries waiting on a retry, skipped, or failed with a retriable code.
		 *
		 * [--entry=<id>]
		 * : One Flamingo entry.
		 *
		 * [--backfill]
		 * : Entries never pushed.
		 *
		 * [--since=<iso>]
		 * : With --backfill, only entries at or after this time.
		 *
		 * [--dry-run]
		 * : Print what would be sent, send nothing.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function push( $args, $assoc ) {
			$mode = ! empty( $assoc['entry'] ) ? 'entry' : ( ! empty( $assoc['pending'] ) ? 'pending' : ( ! empty( $assoc['backfill'] ) ? 'backfill' : '' ) );
			if ( ! $mode ) {
				WP_CLI::error( 'pass --pending, --entry=<id> or --backfill [--since=<iso>]' );
			}
			if ( 'backfill' === $mode && ! empty( $assoc['since'] ) && ! strtotime( (string) $assoc['since'] ) ) {
				WP_CLI::error( 'bad --since' );
			}
			$dry  = ! empty( $assoc['dry-run'] );
			$list = arv_rt_apply_rr_select( $mode, $assoc );
			$n    = array();
			foreach ( $list as $e ) {
				$r                 = arv_rt_apply_rr_push( $e, array( 'dry_run' => $dry, 'force' => true ) );
				$n[ $r['status'] ] = isset( $n[ $r['status'] ] ) ? $n[ $r['status'] ] + 1 : 1;
				$extra             = '';
				if ( $dry && isset( $r['payload'] ) ) {
					$extra = ' keys ' . implode( ',', array_keys( $r['payload'] ) ) . ( isset( $r['payload']['race_plans'] ) ? ' races ' . implode( ',', $r['payload']['race_plans'] ) : '' ) . ' files ' . implode( ',', $r['files'] );
				}
				WP_CLI::log( '#' . $e['id'] . ' ' . arv_rt_apply_rr_mask( $e['email'] ) . ' ' . $r['status'] . ' ' . $r['code'] . ( $r['error'] ? ' ' . $r['error'] : '' ) . $extra );
			}
			WP_CLI::log( count( $list ) . ' entries ' . wp_json_encode( $n ) . ( $dry ? ' (dry run)' : '' ) );
		}

		/**
		 * One-time thank-you to everyone who applied before confirmations existed.
		 *
		 * ## OPTIONS
		 *
		 * [--send]
		 * : Send for real. Without it, a dry run that lists counts only.
		 *
		 * [--before=<datetime>]
		 * : Only applications saved before this local time (Y-m-d H:i:s); later ones got the automatic confirmation.
		 *
		 * [--only=<email>]
		 * : Send to this one applicant address only (a check before the batch).
		 *
		 * [--preview]
		 * : Print the subject and one body.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function thank( $args, $assoc ) {
			global $wpdb;
			$before = isset( $assoc['before'] ) ? (string) $assoc['before'] : current_time( 'mysql' );
			$rows   = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_date, e.meta_value AS email, f.meta_value AS first FROM {$wpdb->posts} p
				 JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_field_rt-email'
				 LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_field_rt-first-name'
				 WHERE p.post_type = 'flamingo_inbound' AND p.post_status NOT IN ('flamingo-spam','trash') AND p.post_title LIKE %s AND p.post_date < %s
				 ORDER BY p.post_date ASC",
				'2027 Racing Team%',
				$before
			), ARRAY_A );
			$seen = array();
			foreach ( $rows as $r ) {
				$em = strtolower( trim( (string) $r['email'] ) );
				if ( '' === $em || ! is_email( $em ) || preg_match( '/\+rtapply(-[a-z0-9]+)?@/i', $em ) ) {
					continue;
				}
				$seen[ $em ] = trim( (string) $r['first'] );
			}
			if ( isset( $assoc['only'] ) ) {
				$o    = strtolower( trim( (string) $assoc['only'] ) );
				$seen = isset( $seen[ $o ] ) ? array( $o => $seen[ $o ] ) : array( $o => '' );
			}
			$done = get_option( 'arv_rt_apply_thanked', array() );
			$done = is_array( $done ) ? $done : array();
			$todo = array_diff_key( $seen, $done );
			if ( isset( $assoc['preview'] ) ) {
				WP_CLI::log( 'Subject: ' . arv_rt_apply_confirm_subject() . "\n\n" . arv_rt_apply_confirm_body( 'Stella', true ) );
			}
			WP_CLI::log( 'applicants ' . count( $seen ) . ', already thanked ' . ( count( $seen ) - count( $todo ) ) . ', to send ' . count( $todo ) . ' (before ' . $before . ')' );
			if ( empty( $assoc['send'] ) ) {
				WP_CLI::log( 'dry run, nothing sent' );
				return;
			}
			$headers = array( 'From: Aravaipa Running <wordpress@aravaiparunning.com>', 'Reply-To: bryce@aravaiparunning.com' );
			$sent    = 0;
			$fail    = 0;
			foreach ( $todo as $em => $first ) {
				$ok = wp_mail( $em, arv_rt_apply_confirm_subject(), arv_rt_apply_confirm_body( $first, true ), $headers );
				if ( $ok ) {
					$done[ $em ] = gmdate( 'c' );
					update_option( 'arv_rt_apply_thanked', $done, false );
					++$sent;
				} else {
					++$fail;
					WP_CLI::warning( 'failed ' . arv_rt_apply_rr_mask( $em ) );
				}
				usleep( 400000 );
			}
			WP_CLI::log( 'sent ' . $sent . ', failed ' . $fail );
		}

		/**
		 * Remove the stored test entry for one email.
		 *
		 * --email=<e>
		 *
		 * @subcommand delete-test
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function delete_test( $args, $assoc ) {
			$e = (string) $assoc['email'];
			if ( ! preg_match( '/\+rtapply(-[a-z0-9]+)?@/i', $e ) && empty( $assoc['force'] ) ) {
				WP_CLI::error( 'delete-test only removes test addresses (+rtapply@ or +rtapply-x@); pass --force for another' );
			}
			WP_CLI::log( wp_json_encode( arv_rt_apply_delete_email( $e ) ) );
		}
	}
	WP_CLI::add_command( 'arv-rt-apply', 'ARV_RT_Apply_CLI' );
}

/* ------------------------------------------------------------------------
 * Failed submit log (job ldqh0d, 0.101.28)
 *
 * Every result other than mail_sent for our form adds one line to a ring
 * option: time, CF7 status, the names of invalid fields, which file fields
 * had a file and how big (0.1 MB), and the browser family. Never names,
 * emails, answers or IPs. The browser script also sends a beacon when the
 * request never reached CF7 (network down, server error) or Turnstile gave
 * no token. Admins read it at GET /wp-json/aravaipa/v1/rt-apply-fail.
 * --------------------------------------------------------------------- */

define( 'ARV_RT_APPLY_FAIL_MAX', 300 );
define( 'ARV_RT_APPLY_BEACON_PER_HOUR', 12 );
// All IPs together, per hour: many addresses cannot push real server entries out of the ring.
define( 'ARV_RT_APPLY_BEACON_GLOBAL_PER_HOUR', 60 );
// Raw beacon body, bytes. A real one is well under 1 KB.
define( 'ARV_RT_APPLY_BEACON_BODY_MAX', 2048 );
// One stored ring entry, JSON bytes (server and beacon lines alike).
define( 'ARV_RT_APPLY_FAIL_LINE_MAX', 1024 );

/** Statuses the browser beacon may report. */
function arv_rt_apply_fail_statuses() {
	return array( 'network', 'server', 'timeout', 'no_token', 'spam', 'mail_failed', 'aborted' );
}

/** Every field name on the form (rt-...), for the beacon allowlist. */
function arv_rt_apply_field_names() {
	$out = array();
	foreach ( arv_rt_apply_fields() as $f ) {
		$out[] = 'rt-' . $f['key'];
	}
	foreach ( arv_rt_apply_consents() as $c ) {
		$out[] = 'rt-consent-' . $c['key'];
	}
	return $out;
}

/**
 * Browser family from a user agent, coarse on purpose.
 *
 * @param string $ua User agent.
 * @return string
 */
function arv_rt_apply_ua_family( $ua ) {
	$ua = (string) $ua;
	if ( '' === $ua ) {
		return 'unknown';
	}
	$os = preg_match( '/iPhone|iPad|iPod/', $ua ) ? 'iOS' : ( false !== stripos( $ua, 'Android' ) ? 'Android' : ( false !== stripos( $ua, 'Mac OS X' ) ? 'Mac' : ( false !== stripos( $ua, 'Windows' ) ? 'Windows' : 'other' ) ) );
	if ( preg_match( '/Edg\//', $ua ) ) {
		$b = 'Edge';
	} elseif ( preg_match( '/FBAN|FBAV|Instagram/', $ua ) ) {
		$b = 'In-app';
	} elseif ( preg_match( '/CriOS|Chrome\//', $ua ) ) {
		$b = 'Chrome';
	} elseif ( preg_match( '/FxiOS|Firefox\//', $ua ) ) {
		$b = 'Firefox';
	} elseif ( false !== strpos( $ua, 'Safari/' ) ) {
		$b = 'Safari';
	} else {
		$b = 'other';
	}
	return $os . ' ' . $b;
}

/**
 * Bound one ring entry: every string field short, lists capped, and the
 * whole entry at most ARV_RT_APPLY_FAIL_LINE_MAX bytes of JSON (field names
 * are dropped from the end until it fits, and 'cut' marks that).
 *
 * @param array $line Entry.
 * @return array
 */
function arv_rt_apply_fail_bound( $line ) {
	$line = is_array( $line ) ? $line : array();
	$out  = array();
	foreach ( array( 'ts' => 24, 'src' => 10, 'status' => 32, 'ua' => 40 ) as $k => $len ) {
		if ( isset( $line[ $k ] ) ) {
			$out[ $k ] = substr( is_scalar( $line[ $k ] ) ? (string) $line[ $k ] : '', 0, $len );
		}
	}
	$fields = array();
	foreach ( array_slice( isset( $line['fields'] ) ? (array) $line['fields'] : array(), 0, 60 ) as $f ) {
		if ( is_scalar( $f ) ) {
			$fields[] = substr( (string) $f, 0, 48 );
		}
	}
	$out['fields'] = $fields;
	$files         = array();
	foreach ( array_slice( isset( $line['files'] ) ? (array) $line['files'] : array(), 0, 4, true ) as $k => $v ) {
		$files[ substr( (string) $k, 0, 24 ) ] = is_numeric( $v ) ? round( (float) $v, 1 ) : 0;
	}
	$out['files'] = $files;
	while ( strlen( (string) wp_json_encode( $out ) ) > ARV_RT_APPLY_FAIL_LINE_MAX && $out['fields'] ) {
		array_pop( $out['fields'] );
		$out['cut'] = true;
	}
	return $out;
}

/**
 * Add one line to the ring log.
 *
 * @param array $line Entry.
 * @return void
 */
function arv_rt_apply_fail_add( $line ) {
	$line  = arv_rt_apply_fail_bound( $line );
	$log   = get_option( 'arv_rt_apply_fail_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = $line;
	if ( count( $log ) > ARV_RT_APPLY_FAIL_MAX ) {
		$log = array_slice( $log, -ARV_RT_APPLY_FAIL_MAX );
	}
	update_option( 'arv_rt_apply_fail_log', $log, false );
}

/**
 * Invalid field names from a CF7 result (keyed by name, or a list of
 * { field } rows), kept only when they are our fields.
 *
 * @param mixed $inv invalid_fields.
 * @return string[]
 */
function arv_rt_apply_fail_fields( $inv ) {
	$names = array();
	foreach ( (array) $inv as $k => $v ) {
		$n = is_string( $k ) ? $k : ( is_array( $v ) && isset( $v['field'] ) ? (string) $v['field'] : '' );
		$n = preg_replace( '/\[\]$/', '', $n );
		if ( in_array( $n, arv_rt_apply_field_names(), true ) && ! in_array( $n, $names, true ) ) {
			$names[] = $n;
		}
	}
	return $names;
}

/**
 * File fields with a file, size in MB to one decimal. Sizes only.
 *
 * @param mixed $submission WPCF7_Submission or null.
 * @return array
 */
function arv_rt_apply_fail_files( $submission ) {
	$out = array();
	foreach ( array( 'rt-headshot', 'rt-resume-file' ) as $n ) {
		$size = 0;
		if ( isset( $_FILES[ $n ]['size'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$size = (int) ( is_array( $_FILES[ $n ]['size'] ) ? array_sum( $_FILES[ $n ]['size'] ) : $_FILES[ $n ]['size'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		} elseif ( $submission && method_exists( $submission, 'uploaded_files' ) ) {
			$up = (array) $submission->uploaded_files();
			foreach ( isset( $up[ $n ] ) ? (array) $up[ $n ] : array() as $path ) {
				$size += is_string( $path ) && is_file( $path ) ? (int) filesize( $path ) : 0;
			}
		}
		if ( $size > 0 ) {
			$out[ substr( $n, 3 ) ] = round( $size / 1048576, 1 );
		}
	}
	return $out;
}

/**
 * Log every non-sent result for our form.
 *
 * @param mixed $form   WPCF7_ContactForm.
 * @param array $result CF7 result.
 * @return void
 */
function arv_rt_apply_fail_on_submit( $form, $result = array() ) {
	if ( ! arv_rt_apply_is_ours( $form ) ) {
		return;
	}
	$status = isset( $result['status'] ) ? (string) $result['status'] : 'unknown';
	if ( 'mail_sent' === $status ) {
		return;
	}
	$sub = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
	arv_rt_apply_fail_add(
		array(
			'ts'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'src'    => 'server',
			'status' => preg_replace( '/[^a-z0-9_]/', '', strtolower( $status ) ),
			'fields' => arv_rt_apply_fail_fields( isset( $result['invalid_fields'] ) ? $result['invalid_fields'] : array() ),
			'files'  => arv_rt_apply_fail_files( $sub ),
			'ua'     => arv_rt_apply_ua_family( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		)
	);
}
add_action( 'wpcf7_submit', 'arv_rt_apply_fail_on_submit', 30, 2 );

/**
 * Beacon from the browser: body at most ARV_RT_APPLY_BEACON_BODY_MAX bytes,
 * status from the allowlist, field names from our form only, rate limited
 * per hashed IP and across all IPs per hour. Anything else is refused.
 *
 * @param mixed $request WP_REST_Request.
 * @return array|WP_Error
 */
function arv_rt_apply_fail_beacon( $request ) {
	$raw = (string) $request->get_body();
	if ( strlen( $raw ) > ARV_RT_APPLY_BEACON_BODY_MAX ) {
		return new WP_Error( 'rt_big', 'Report too big.', array( 'status' => 413 ) );
	}
	$body = json_decode( $raw, true );
	if ( ! is_array( $body ) ) {
		return new WP_Error( 'rt_bad', 'Bad request.', array( 'status' => 400 ) );
	}
	$status = isset( $body['status'] ) ? (string) $body['status'] : '';
	$fields = isset( $body['fields'] ) && is_array( $body['fields'] ) ? array_values( $body['fields'] ) : array();
	if ( ! in_array( $status, arv_rt_apply_fail_statuses(), true ) || count( $fields ) > 60 ) {
		return new WP_Error( 'rt_bad', 'Bad request.', array( 'status' => 400 ) );
	}
	$allowed = arv_rt_apply_field_names();
	foreach ( $fields as $f ) {
		if ( ! is_string( $f ) || ! in_array( $f, $allowed, true ) ) {
			return new WP_Error( 'rt_bad', 'Bad request.', array( 'status' => 400 ) );
		}
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$key = 'arv_rt_fb_' . substr( hash( 'sha256', $ip . '|' . ( function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'rt' ) ), 0, 20 );
	$n   = (int) get_transient( $key );
	if ( $n >= ARV_RT_APPLY_BEACON_PER_HOUR ) {
		return new WP_Error( 'rt_slow', 'Too many reports.', array( 'status' => 429 ) );
	}
	$all = (int) get_transient( 'arv_rt_fb_all' );
	if ( $all >= ARV_RT_APPLY_BEACON_GLOBAL_PER_HOUR ) {
		return new WP_Error( 'rt_slow', 'Too many reports.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, 3600 );
	set_transient( 'arv_rt_fb_all', $all + 1, 3600 );
	arv_rt_apply_fail_add(
		array(
			'ts'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'src'    => 'beacon',
			'status' => $status,
			'fields' => array_values( array_unique( $fields ) ),
			'files'  => array(),
			'ua'     => arv_rt_apply_ua_family( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		)
	);
	return array( 'ok' => true );
}

/**
 * Admin read of the log, newest last.
 *
 * @return array
 */
function arv_rt_apply_fail_read() {
	$log = get_option( 'arv_rt_apply_fail_log', array() );
	return array(
		'count' => is_array( $log ) ? count( $log ) : 0,
		'log'   => is_array( $log ) ? array_values( $log ) : array(),
	);
}

/** Only admins read the log. */
function arv_rt_apply_fail_can_read() {
	return current_user_can( 'manage_options' );
}

/** Routes: POST beacon (public), GET log (manage_options). */
function arv_rt_apply_fail_routes() {
	register_rest_route(
		'aravaipa/v1',
		'/rt-apply-fail',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'arv_rt_apply_fail_beacon',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'GET',
				'callback'            => 'arv_rt_apply_fail_read',
				'permission_callback' => 'arv_rt_apply_fail_can_read',
			),
		)
	);
}
add_action( 'rest_api_init', 'arv_rt_apply_fail_routes' );
