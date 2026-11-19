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
define( 'ARV_RT_APPLY_TO', 'marketing@aravaiparunning.com' );
define( 'ARV_RT_APPLY_BRYCE', 'bryce@aravaiparunning.com' );

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
		array( 'key' => 'region', 'name' => 'rt-region', 'label' => 'Which region would you race for?', 'type' => 'radio', 'required' => true, 'section' => 'about', 'options' => array( 'Arizona', 'Colorado', 'Northeast', 'California', 'Utah and Nevada', 'Great Lakes', 'Southeast', 'Other' ) ),
		array( 'key' => 'disciplines', 'name' => 'rt-disciplines', 'label' => 'Which do you race competitively?', 'type' => 'checkbox', 'required' => true, 'section' => 'about', 'options' => array( 'Trail', 'Ultra', 'Road', 'Mountain bike' ), 'hint' => 'Pick all that apply.' ),
		array( 'key' => 'returning', 'name' => 'rt-returning', 'label' => 'Have you been on the team before?', 'type' => 'radio', 'required' => true, 'section' => 'about', 'options' => array( 'Yes', 'No' ) ),
		array( 'key' => 'returning-years', 'name' => 'rt-returning-years', 'label' => 'Which years?', 'type' => 'text', 'required' => false, 'section' => 'about', 'hint' => 'If yes, for example 2025, 2026.' ),
		array( 'key' => 'resume', 'name' => 'rt-resume', 'label' => 'Racing resume', 'type' => 'textarea', 'required' => true, 'section' => 'racing', 'hint' => 'Your best results in the past 3 years: place, finish time, race name and year. One per line.' ),
		array( 'key' => 'resume-file', 'name' => 'rt-resume-file', 'label' => 'Resume file', 'type' => 'file', 'required' => false, 'section' => 'racing', 'hint' => 'A PDF or image of a results list or athlete CV, if you keep one. 4 MB max.' ),
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
		array( 'key' => 'headshot', 'name' => 'rt-headshot', 'label' => 'Photo', 'type' => 'file', 'required' => false, 'section' => 'kit', 'hint' => 'A clear, recent photo. Running shots welcome. Recommended.' ),
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
			$types = 'headshot' === $f['key'] ? 'jpg|jpeg|png|webp' : 'pdf|jpg|jpeg|png|webp';
			return '[file ' . $name . ' ' . $id . ' limit:4mb filetypes:' . $types . ']';
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
		'mail_sent_ok'     => 'Thank you. Your application for the 2027 Aravaipa Racing Team is in, and the team managers read every one. This page is your receipt: we do not send a confirmation email.',
		'mail_sent_ng'     => 'Your application is saved, but the notice to the team did not send. No need to apply again.',
		'validation_error' => 'Some answers need a look. Check the fields marked below.',
	);
}

/** CF7 additional settings: what Flamingo shows in its list. */
function arv_rt_apply_additional_settings() {
	return "flamingo_email: [rt-email]\nflamingo_name: [rt-first-name] [rt-last-name]\nflamingo_subject: 2027 Racing Team application";
}

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
	if ( 'Yes' === arv_rt_apply_posted( 'rt-returning' ) && '' === arv_rt_apply_posted( 'rt-returning-years' ) ) {
		$t = arv_rt_apply_find_tag( $form, 'rt-returning-years' );
		if ( $t ) {
			$result->invalidate( $t, 'Which years were you on the team?' );
		}
	}
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
}
add_action( 'wpcf7_after_flamingo', 'arv_rt_apply_after_flamingo', 10, 1 );

/** Styles: the site's type, plain fields, no cards, no shadows. */
function arv_rt_apply_css() {
	// The page around the form mirrors the team page's own styles (scoped there to .page-id-79463).
	return '.arv-rt-page{text-align:left}.arv-rt-page .arv-team-h1{text-align:center;margin:0 0 1.5rem}'
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
		. '@media (max-width:767px){.rt-sec{grid-template-columns:1fr;gap:16px;padding:28px 0}.rt-sec-body{grid-template-columns:1fr}.rt-submit .wpcf7-submit{width:100%}}';
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
	$h .= '<div class="rt-apply" id="rt-apply" data-state="' . esc_attr( $state ) . '" data-opens="' . (int) $opens . '" data-closes="' . (int) $closes . '">';
	$h .= '<p class="rt-state rt-state-before"' . ( 'before' === $state ? '' : ' hidden' ) . '>Applications open November 20.</p>';
	$h .= '<p class="rt-state rt-state-closed"' . ( 'closed' === $state ? '' : ' hidden' ) . '>Applications for the 2027 team are closed.</p>';
	if ( $form ) {
		$h .= '<div class="rt-state-open"' . ( 'open' === $state ? '' : ' hidden' ) . '>' . $form . '</div>';
	} elseif ( 'open' === $state ) {
		$h .= '<p class="rt-state">The application form is not available right now. Email marketing@aravaiparunning.com and we will help.</p>';
	}
	$h .= '</div>';
	if ( $form ) {
		$h .= arv_rt_apply_region_hint_js();
	}
	$h .= '<script>(function(){var r=document.getElementById("rt-apply");if(!r)return;function t(){var n=Date.now()/1000,o=+r.dataset.opens,c=+r.dataset.closes,s=n<o?"before":(n>c?"closed":"open");var was=r.dataset.state;r.dataset.state=s;["before","closed","open"].forEach(function(k){var e=r.querySelector(".rt-state-"+k);if(e)e.hidden=(k!==s);});if(was!==s&&s==="open"&&window.turnstile){r.querySelectorAll(".wpcf7-turnstile,.cf-turnstile").forEach(function(w){try{window.turnstile.reset(w);}catch(x){}});}}t();setInterval(t,15000);})();</script>';
	return $h;
}
add_shortcode( 'arv_rt_apply', 'arv_rt_apply_shortcode' );

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
 * "Region lines are being finalized." while the region lists are provisional
 * (anything but source "mantis").
 *
 * @param array $d rt-regions.json data.
 * @return string
 */
function arv_rt_apply_regions_note( $d ) {
	if ( is_array( $d ) && isset( $d['source'] ) && 'mantis' === $d['source'] ) {
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
				if ( is_array( $e ) ) {
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
			if ( is_array( $e ) && strtolower( (string) $e['email'] ) === $email ) {
				foreach ( (array) $e['files'] as $p ) {
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
	$mail2           = $cf->prop( 'mail_2' );
	$mail2['active'] = false;
	$cf->set_properties(
		array(
			'form'                => arv_rt_apply_form_template(),
			'mail'                => arv_rt_apply_mail(),
			'mail_2'              => $mail2,
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
		'rt-region'          => 'Arizona',
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
		 * Run one TEST application through the real pipeline.
		 *
		 * --email=<e>
		 *
		 * @param array $args  Args.
		 * @param array $assoc Flags.
		 */
		public function test( $args, $assoc ) {
			$r = arv_rt_apply_run_test( (string) $assoc['email'] );
			WP_CLI::log( 'status ' . $r['status'] . ( isset( $r['message'] ) ? ' ' . $r['message'] : '' ) );
			if ( ! in_array( $r['status'], array( 'mail_sent', 'mail_failed' ), true ) ) {
				WP_CLI::error( 'test submission failed: ' . wp_json_encode( isset( $r['invalid_fields'] ) ? $r['invalid_fields'] : $r ) );
			}
			WP_CLI::success( 'test submitted' );
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
			if ( false === strpos( $e, '+rtapply@' ) && empty( $assoc['force'] ) ) {
				WP_CLI::error( 'delete-test only removes test addresses (+rtapply@); pass --force for another' );
			}
			WP_CLI::log( wp_json_encode( arv_rt_apply_delete_email( $e ) ) );
		}
	}
	WP_CLI::add_command( 'arv-rt-apply', 'ARV_RT_Apply_CLI' );
}
