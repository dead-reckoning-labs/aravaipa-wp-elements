<?php
/**
 * Contact Us form (CF7 form 84199 on /contact-us/).
 *
 * Always on once installed:
 *   - Turnstile freshness. Every row in this form's spam folder (118 by
 *     2026-11-27) was "Turnstile token is empty": the message went out
 *     before the widget had a token, usually because CF7 resets the widget
 *     after any failed submit, or the token had expired on a long message.
 *     The widget gets auto refresh and retry attributes, and the browser
 *     helper holds a submit that has no fresh token until one arrives.
 *   - A reference number (ARV-YYMMDD-XXXX) on every message, in the staff
 *     subject "[ARV-...] <topic> | <race or General> | <name>", at the top
 *     of the body and in Flamingo. Recipients are untouched: the topic pipes
 *     in the stored form still decide where mail goes.
 *
 * Behind option arv_contact_v2 (off by default, or the arv_contact_v2
 * filter for a single request):
 *   - the v2 form: topic first, then the event picker (a combobox fed by
 *     the race calendar and upcoming rides, free text allowed), short help
 *     links for the topic, and extra fields for registration and lost and
 *     found questions;
 *   - the event is required for four topics, checked on the server.
 *
 * Behind option arv_contact_autoreply (off by default):
 *   - '1' sends the "Got it" confirmation (Mail 2) to the sender;
 *   - 'test' sends it only to the staff address in option
 *     arv_contact_autoreply_test_to, never to the sender.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ARV_CONTACT_FORM_ID' ) ) {
	define( 'ARV_CONTACT_FORM_ID', 84199 );
}

/** Topics where the event field is required on the v2 form. */
function arv_contact_event_required_topics() {
	return array(
		'Registration, refunds or transfers',
		'Race day, results or timing',
		'Lost and found',
		'Volunteering',
	);
}

/** The choice that means "not about a race". */
function arv_contact_general_label() {
	return 'General, not about a race';
}

/**
 * Fields that only belong to one topic. Disabled in the browser and dropped
 * on the server when another topic is picked, so they can never block.
 *
 * @return array topic => field names
 */
function arv_contact_topic_fields() {
	return array(
		'Registration, refunds or transfers' => array( 'reg-email' ),
		'Lost and found'                     => array( 'lf-item', 'lf-where', 'lf-photo' ),
	);
}

/**
 * The form id this file serves.
 *
 * @return int
 */
function arv_contact_form_id() {
	return (int) apply_filters( 'arv_contact_form_id', ARV_CONTACT_FORM_ID );
}

/**
 * Whether a CF7 form (object or id) is the Contact Us form.
 *
 * @param mixed $form WPCF7_ContactForm or id.
 * @return bool
 */
function arv_contact_is_ours( $form ) {
	$id = is_object( $form ) && method_exists( $form, 'id' ) ? (int) $form->id() : (int) $form;
	return $id > 0 && arv_contact_form_id() === $id;
}

/**
 * Whether the v2 public form is on.
 *
 * @return bool
 */
function arv_contact_v2_on() {
	$on = in_array( (string) get_option( 'arv_contact_v2', '' ), array( '1', 'on', 'yes', 'true' ), true );
	return (bool) apply_filters( 'arv_contact_v2', $on );
}

/**
 * Auto-reply mode: '' (off), 'test' (staff test address only) or 'live'.
 *
 * @return string
 */
function arv_contact_autoreply_mode() {
	$v = strtolower( trim( (string) get_option( 'arv_contact_autoreply', '' ) ) );
	if ( 'test' === $v ) {
		$mode = 'test';
	} elseif ( in_array( $v, array( '1', 'on', 'yes', 'true', 'live' ), true ) ) {
		$mode = 'live';
	} else {
		$mode = '';
	}
	$mode = (string) apply_filters( 'arv_contact_autoreply_mode', $mode );
	return in_array( $mode, array( '', 'test', 'live' ), true ) ? $mode : '';
}

/**
 * Staff address the auto-reply goes to in test mode. Empty means none set,
 * and then test mode sends nothing at all.
 *
 * @return string
 */
function arv_contact_autoreply_test_to() {
	$to = trim( (string) apply_filters( 'arv_contact_autoreply_test_to', (string) get_option( 'arv_contact_autoreply_test_to', '' ) ) );
	if ( '' === $to || ! is_email( $to ) ) {
		return '';
	}
	// Only our own domain: test mode must never reach a member of the public.
	return preg_match( '/@aravaiparunning\.com$/i', $to ) ? $to : '';
}

/**
 * Now as a Unix time (filterable for tests).
 *
 * @return int
 */
function arv_contact_now() {
	return (int) apply_filters( 'arv_contact_now', time() );
}

/**
 * Today in Phoenix (UTC-7 all year, no daylight saving).
 *
 * @return string Y-m-d
 */
function arv_contact_today() {
	return gmdate( 'Y-m-d', arv_contact_now() - 7 * 3600 );
}

/* -------------------------------------------------------------------------
 * Reference number
 * ---------------------------------------------------------------------- */

/**
 * The reference for this submission: ARV-YYMMDD-XXXX, Phoenix date, four
 * characters from an alphabet with no 0/O or 1/I/L to misread. One per
 * request, so the staff mail, the auto-reply and Flamingo agree.
 *
 * @param bool $reset Start a new one (tests).
 * @return string
 */
function arv_contact_ref( $reset = false ) {
	static $ref = '';
	if ( $reset ) {
		$ref = '';
		return '';
	}
	if ( '' === $ref ) {
		$abc  = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$tail = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$tail .= $abc[ random_int( 0, strlen( $abc ) - 1 ) ];
		}
		$ref = 'ARV-' . gmdate( 'ymd', arv_contact_now() - 7 * 3600 ) . '-' . $tail;
	}
	return $ref;
}

/* -------------------------------------------------------------------------
 * Posted values
 * ---------------------------------------------------------------------- */

/**
 * A posted value as one clean line.
 *
 * @param string $name Field name.
 * @param int    $max  Max length.
 * @return string
 */
function arv_contact_posted( $name, $max = 200 ) {
	if ( function_exists( 'wpcf7_superglobal_post' ) ) {
		$v = wpcf7_superglobal_post( $name );
	} else {
		$v = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	if ( is_array( $v ) ) {
		$v = reset( $v );
	}
	$v = trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $v ) );
	$v = preg_replace( '/\s{2,}/', ' ', $v );
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $v, 0, $max );
	}
	return substr( $v, 0, $max );
}

/**
 * The topic label the visitor picked (before CF7 pipes it to an address).
 *
 * @return string
 */
function arv_contact_topic() {
	$t = function_exists( 'arv_contact_raw_topic' ) ? arv_contact_raw_topic() : arv_contact_posted( 'category' );
	return 'Choose a topic' === $t ? '' : $t;
}

/**
 * Race for the subject: the event as typed, or General.
 *
 * @return string
 */
function arv_contact_race_label() {
	$e = arv_contact_posted( 'event', 60 );
	if ( '' === $e || 0 === strcasecmp( $e, arv_contact_general_label() ) || 0 === strcasecmp( $e, 'General' ) ) {
		return 'General';
	}
	return $e;
}

/**
 * The staff subject: [ARV-...] <topic> | <race or General> | <name>.
 *
 * @return string
 */
function arv_contact_subject() {
	$topic = arv_contact_topic();
	$name  = arv_contact_posted( 'your-name', 60 );
	return '[' . arv_contact_ref() . '] ' . ( '' !== $topic ? $topic : 'Contact' ) . ' | ' . arv_contact_race_label() . ' | ' . ( '' !== $name ? $name : 'No name' );
}

/* -------------------------------------------------------------------------
 * Race list for the combobox
 * ---------------------------------------------------------------------- */

/**
 * Normalize a race name for the picker.
 *
 * @param string $name Name.
 * @return string
 */
function arv_contact_norm_name( $name ) {
	$name = html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
	$name = preg_replace( '/\s+presented by:?\s.*$/i', '', $name );
	return trim( preg_replace( '/\s+/', ' ', $name ) );
}

/**
 * Upcoming Aravaipa Rides events, baked from rides/data/rides.json at build
 * time (the rides plugin runs on aravaiparides.com, not on this site).
 *
 * @return array names
 */
function arv_contact_ride_names() {
	$file = __DIR__ . '/data/contact-rides.json';
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$data  = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$today = arv_contact_today();
	$out   = array();
	foreach ( (array) ( isset( $data['rides'] ) ? $data['rides'] : array() ) as $r ) {
		if ( ! empty( $r['name'] ) && ! empty( $r['date'] ) && (string) $r['date'] >= $today ) {
			$out[] = arv_contact_norm_name( $r['name'] );
		}
	}
	return $out;
}

/**
 * Picker options: current and upcoming races from the race calendar
 * (arv_race store), then upcoming rides, deduped, then the General choice.
 * Cached for six hours.
 *
 * @param bool $fresh Skip the cache.
 * @return array names
 */
function arv_contact_event_names( $fresh = false ) {
	$key = 'arv_contact_events_' . arv_contact_today();
	if ( ! $fresh ) {
		$hit = get_transient( $key );
		if ( is_array( $hit ) && $hit ) {
			return $hit;
		}
	}
	$today = arv_contact_today();
	$names = array();
	$seen  = array();
	$races = function_exists( 'arv_race_store_get' ) ? (array) arv_race_store_get() : array();
	foreach ( $races as $r ) {
		$end = ! empty( $r['end'] ) ? (string) $r['end'] : ( isset( $r['iso'] ) ? (string) $r['iso'] : '' );
		if ( '' === $end || $end < $today ) {
			continue;
		}
		$n = arv_contact_norm_name( isset( $r['name'] ) ? $r['name'] : '' );
		$k = strtolower( $n );
		if ( '' === $n || isset( $seen[ $k ] ) ) {
			continue;
		}
		$seen[ $k ] = true;
		$names[]    = $n;
	}
	foreach ( arv_contact_ride_names() as $n ) {
		$k = strtolower( $n );
		if ( '' !== $n && ! isset( $seen[ $k ] ) ) {
			$seen[ $k ] = true;
			$names[]    = $n;
		}
	}
	$names[] = arv_contact_general_label();
	$names   = (array) apply_filters( 'arv_contact_event_names', $names );
	if ( count( $names ) > 1 ) {
		set_transient( $key, $names, 6 * 3600 );
	}
	return $names;
}

/* -------------------------------------------------------------------------
 * Help links per topic (every URL checked 200 on 2026-11-27)
 * ---------------------------------------------------------------------- */

/**
 * Help shown under the topic once picked.
 *
 * @return array topic => array( 'text' => string, 'links' => array( array( label, url ) ) )
 */
function arv_contact_help() {
	$b = 'https://www.aravaiparunning.com';
	return (array) apply_filters(
		'arv_contact_help',
		array(
			'Registration, refunds or transfers'   => array(
				'text'  => 'Entries cannot be refunded or transferred to another runner, but you can often change distance or use a race credit. The answer may already be here:',
				'links' => array(
					array( 'Refund and transfer policy', $b . '/refund-transfer-policy/' ),
					array( 'Changing distance', $b . '/faq/#change-distance' ),
					array( 'Race credits', $b . '/faq/#race-credits' ),
				),
			),
			'Race day, results or timing'          => array(
				'text'  => 'Results go up on our results page after each race. During a race, follow along on live results.',
				'links' => array(
					array( 'Results', $b . '/results/' ),
					array( 'Live results', $b . '/live-results/' ),
					array( 'Live tracking and finish times', $b . '/faq/#live-tracking' ),
				),
			),
			'Lost and found'                       => array(
				'text'  => 'Tell us the race, what you lost and where you last had it. A photo helps us match it to what was turned in.',
				'links' => array(),
			),
			'Volunteering'                         => array(
				'text'  => 'Pick an event to volunteer at and see what to expect on the day:',
				'links' => array(
					array( 'Volunteer with us', $b . '/volunteer/' ),
					array( 'Volunteer information', $b . '/volunteer-information/' ),
					array( 'Volunteer perks', $b . '/faq/#volunteer-perks' ),
				),
			),
			'Sponsorship and partnerships'         => array(
				'text'  => 'Our sponsorship and vendor options are here:',
				'links' => array(
					array( 'Sponsorship and vendor opportunities', $b . '/sponsorship-2/' ),
				),
			),
			'Event services, timing or production' => array(
				'text'  => 'More about the timing we offer other events:',
				'links' => array(
					array( 'Timing services', $b . '/timing/' ),
				),
			),
			'Something else'                       => array(
				'text'  => 'Many questions are answered in our FAQ:',
				'links' => array(
					array( 'FAQ', $b . '/faq/' ),
				),
			),
		)
	);
}

/**
 * Help blocks HTML, one per topic, all hidden until the script shows one.
 *
 * @return string
 */
function arv_contact_help_html() {
	$h = '<div class="arv-cf-helps" aria-live="polite">';
	foreach ( arv_contact_help() as $topic => $help ) {
		$h .= '<div class="arv-cf-help" data-topic="' . esc_attr( $topic ) . '"><p>' . esc_html( $help['text'] ) . '</p>';
		if ( ! empty( $help['links'] ) ) {
			$h .= '<ul>';
			foreach ( $help['links'] as $l ) {
				$h .= '<li><a href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener">' . esc_html( $l[0] ) . '</a></li>';
			}
			$h .= '</ul>';
		}
		$h .= '</div>';
	}
	return $h . '</div>';
}

/* -------------------------------------------------------------------------
 * v2 template
 * ---------------------------------------------------------------------- */

/**
 * The category select from the stored form, pipes and all, so routing
 * stays exactly what staff set in CF7.
 *
 * @param string $stored Stored form template.
 * @return string
 */
function arv_contact_category_tag( $stored ) {
	if ( preg_match( '/\[select\*?\s+category\b[^\]]*\]/s', (string) $stored, $m ) ) {
		return $m[0];
	}
	return '[select* category first_as_label "Choose a topic" "Registration, refunds or transfers" "Race day, results or timing" "Lost and found" "Volunteering" "Media and press" "Sponsorship and partnerships" "Event services, timing or production" "Something else"]';
}

/**
 * The v2 form template. Each block sits on one line so CF7's autop adds no
 * stray line breaks.
 *
 * @param string $stored Stored form template.
 * @return string
 */
function arv_contact_v2_template( $stored ) {
	$t  = '<div class="arv-cf2">' . "\n\n";
	$t .= '<p class="arv-cf-row"><label>What is this about? (required)<br />' . arv_contact_category_tag( $stored ) . '</label></p>' . "\n\n";
	$t .= '<div class="arv-cf-help-slot"></div>' . "\n\n";
	$t .= '<p class="arv-cf-row arv-cf-event"><label for="arv-cf-event">Event or race name <span class="arv-cf-req">(required)</span></label><br />[text event id:arv-cf-event autocomplete:off placeholder "Start typing a race name"]<span class="arv-cf-hint">Pick from the list or type your own. Choose General if it is not about a race.</span></p>' . "\n\n";
	$t .= '<p class="arv-cf-row"><label>Your name (required)<br />[text* your-name autocomplete:name]</label></p>' . "\n\n";
	$t .= '<p class="arv-cf-row"><label>Your email (required)<br />[email* your-email autocomplete:email]</label></p>' . "\n\n";
	$t .= '<div class="arv-cf-topic" data-topic="Registration, refunds or transfers"><p class="arv-cf-row"><label>Email you registered with (if different)<br />[email reg-email]</label></p></div>' . "\n\n";
	$t .= '<div class="arv-cf-topic" data-topic="Lost and found"><p class="arv-cf-row"><label>What did you lose?<br />[text lf-item]</label></p><p class="arv-cf-row"><label>Where did you last have it? (aid station, finish area, parking)<br />[text lf-where]</label></p><p class="arv-cf-row"><label>Photo of the item or a similar one (optional, image up to 8 MB)<br />[file lf-photo limit:8mb filetypes:jpg|jpeg|png|heic|webp]</label></p></div>' . "\n\n";
	$t .= '<p class="arv-cf-row"><label>Your message (required)<br />[textarea* your-message]</label></p>' . "\n\n";
	$t .= '<p class="arv-cf-row">[turnstile]</p>' . "\n\n";
	$t .= '<p>[submit "Send message"]</p>' . "\n\n";
	$t .= '</div>';
	return $t;
}

/**
 * Swap in the v2 template for our form when the option is on.
 *
 * @param mixed $value Stored form template.
 * @param mixed $form  WPCF7_ContactForm.
 * @return mixed
 */
function arv_contact_form_prop( $value, $form = null ) {
	if ( ! arv_contact_is_ours( $form ) || ! arv_contact_v2_on() ) {
		return $value;
	}
	return arv_contact_v2_template( (string) $value );
}
add_filter( 'wpcf7_contact_form_property_form', 'arv_contact_form_prop', 10, 2 );

/* -------------------------------------------------------------------------
 * Rendering: Turnstile attributes, help, data, script
 * ---------------------------------------------------------------------- */

/**
 * Auto refresh, refresh on timeout and retry on the Turnstile widget.
 *
 * @param string $html HTML.
 * @return string
 */
function arv_contact_turnstile_attrs( $html ) {
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
 * Small stylesheet for the v2 form: plain, the site's look. No color bars,
 * no rounded cards, no shadows.
 *
 * @return string
 */
function arv_contact_css() {
	return '.arv-cf2 .arv-cf-event{position:relative}'
		. '.arv-cf2 .arv-cf-req{display:none}'
		. '.arv-cf2.arv-cf-need-event .arv-cf-req{display:inline}'
		. '.arv-cf2 .arv-cf-hint{display:block;font-size:.85em;opacity:.75;margin-top:4px}'
		. '.arv-cf2 .arv-cf-helps{margin:0 0 1em}'
		. '.arv-cf2 .arv-cf-help{display:none;margin:0;padding:12px 0;border-top:1px solid #d9d9d9;border-bottom:1px solid #d9d9d9}'
		. '.arv-cf2 .arv-cf-help.is-on{display:block}'
		. '.arv-cf2 .arv-cf-help p{margin:0 0 6px}'
		. '.arv-cf2 .arv-cf-help ul{margin:0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px 20px}'
		. '.arv-cf2 .arv-cf-help li{margin:0;padding:0}'
		. '.arv-cf2 .arv-cf-help a{text-decoration:underline;font-weight:600}'
		. '.arv-cf2-js .arv-cf-topic{display:none}'
		. '.arv-cf2-js .arv-cf-topic.is-on{display:block}'
		. '.arv-cf2 .arv-cf-list{position:absolute;left:0;right:0;z-index:50;margin:2px 0 0;padding:0;list-style:none;background:#fff;border:1px solid #bdbdbd;max-height:280px;overflow-y:auto;color:#222}'
		. '.arv-cf2 .arv-cf-list[hidden]{display:none}'
		. '.arv-cf2 .arv-cf-list li{margin:0;padding:10px 14px;min-height:44px;box-sizing:border-box;display:flex;align-items:center;cursor:pointer;line-height:1.3}'
		. '.arv-cf2 .arv-cf-list li[aria-selected=true]{background:#ececec}'
		. '.arv-cf2 .arv-cf-list li.arv-cf-general{border-top:1px solid #e3e3e3;font-style:italic}'
		. '.arv-cf2 .arv-cf-list mark{background:none;color:inherit;font-weight:700}'
		. '.arv-cf2 .arv-cf-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);border:0;white-space:nowrap}'
		. '.arv-cf-wait{display:block;margin:6px 0 0;font-size:.9em}';
}

/**
 * Add Turnstile attrs, the help blocks, the picker data and our script.
 *
 * @param string $html Form elements HTML.
 * @return string
 */
function arv_contact_form_elements( $html ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! arv_contact_is_ours( WPCF7_ContactForm::get_current() ) ) {
		return $html;
	}
	$html = arv_contact_turnstile_attrs( $html );
	if ( false !== strpos( $html, 'arv-cf-help-slot' ) ) {
		$data = array(
			'events'   => array_values( arv_contact_event_names() ),
			'general'  => arv_contact_general_label(),
			'required' => arv_contact_event_required_topics(),
			'fields'   => arv_contact_topic_fields(),
		);
		$slot = arv_contact_help_html()
			. '<script type="application/json" class="arv-cf-data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>'
			. '<style>' . arv_contact_css() . '</style>';
		$html = preg_replace( '#<div class="arv-cf-help-slot"></div>#', $slot, $html, 1 );
	}
	arv_contact_enqueue();
	return $html;
}
add_filter( 'wpcf7_form_elements', 'arv_contact_form_elements', 20, 1 );

/**
 * Enqueue the browser helper (Turnstile guard always, picker on v2).
 */
function arv_contact_enqueue() {
	if ( function_exists( 'wp_enqueue_script' ) && defined( 'ARV_ELEMENTS_URL' ) ) {
		wp_enqueue_script( 'aravaipa-contact-form', ARV_ELEMENTS_URL . 'assets/contact-form.js', array(), defined( 'ARV_ELEMENTS_VERSION' ) ? ARV_ELEMENTS_VERSION : null, true );
	}
}

/* -------------------------------------------------------------------------
 * Submission: off-topic fields, validation
 * ---------------------------------------------------------------------- */

/**
 * Drop fields that belong to a topic other than the one picked, before CF7
 * reads the submission, so a leftover value (a half typed email, a photo
 * picked before switching topic) can never block the message.
 *
 * @return array names dropped
 */
function arv_contact_strip_offtopic() {
	$topic   = arv_contact_topic();
	$dropped = array();
	foreach ( arv_contact_topic_fields() as $t => $fields ) {
		if ( $t === $topic ) {
			continue;
		}
		foreach ( $fields as $f ) {
			if ( isset( $_POST[ $f ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				unset( $_POST[ $f ] );
				$dropped[] = $f;
			}
			if ( isset( $_FILES[ $f ] ) ) {
				unset( $_FILES[ $f ] );
				$dropped[] = $f;
			}
		}
	}
	return array_values( array_unique( $dropped ) );
}

/**
 * Whether the current request is a submission of our form.
 *
 * @param string $route REST route, when called from the REST hook.
 * @return bool
 */
function arv_contact_is_our_submit( $route = '' ) {
	$id = arv_contact_form_id();
	if ( '' !== $route ) {
		return (bool) preg_match( '#^/contact-form-7/v1/contact-forms/' . $id . '/feedback$#', $route );
	}
	return isset( $_POST['_wpcf7'] ) && (int) $_POST['_wpcf7'] === $id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

/**
 * REST submit (the normal path): strip off-topic fields first.
 *
 * @param mixed $result  Pre-dispatch result.
 * @param mixed $server  WP_REST_Server.
 * @param mixed $request WP_REST_Request.
 * @return mixed
 */
function arv_contact_rest_pre_dispatch( $result, $server = null, $request = null ) {
	if ( is_object( $request ) && method_exists( $request, 'get_route' ) && arv_contact_is_our_submit( (string) $request->get_route() ) ) {
		arv_contact_strip_offtopic();
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'arv_contact_rest_pre_dispatch', 1, 3 );

/** No-JS submit (CF7 handles it on 'wp'): same. */
function arv_contact_nojs_submit() {
	if ( arv_contact_is_our_submit() ) {
		arv_contact_strip_offtopic();
	}
}
add_action( 'wp', 'arv_contact_nojs_submit', 1 );

/**
 * Event required for four topics, on the server. Only when the form has an
 * event field to fill, so the stored form is never blocked by this.
 *
 * @param mixed $result WPCF7_Validation.
 * @param array $tags   Form tags.
 * @return mixed
 */
function arv_contact_validate( $result, $tags = array() ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! arv_contact_is_ours( WPCF7_ContactForm::get_current() ) || ! arv_contact_v2_on() ) {
		return $result;
	}
	if ( ! in_array( arv_contact_topic(), arv_contact_event_required_topics(), true ) ) {
		return $result;
	}
	if ( '' !== arv_contact_posted( 'event' ) ) {
		return $result;
	}
	foreach ( (array) $tags as $tag ) {
		$name = is_object( $tag ) ? ( isset( $tag->name ) ? $tag->name : '' ) : ( isset( $tag['name'] ) ? $tag['name'] : '' );
		if ( 'event' === $name ) {
			$result->invalidate( $tag, 'Please tell us which race or event this is about, or choose General.' );
			break;
		}
	}
	return $result;
}
add_filter( 'wpcf7_validate', 'arv_contact_validate', 20, 2 );

/* -------------------------------------------------------------------------
 * Mail
 * ---------------------------------------------------------------------- */

/**
 * On v2, the lost and found photo rides along with the staff mail.
 *
 * @param mixed $value Stored mail.
 * @param mixed $form  WPCF7_ContactForm.
 * @return mixed
 */
function arv_contact_mail_prop( $value, $form = null ) {
	if ( ! arv_contact_is_ours( $form ) || ! is_array( $value ) || ! arv_contact_v2_on() ) {
		return $value;
	}
	$att = isset( $value['attachments'] ) ? (string) $value['attachments'] : '';
	if ( false === strpos( $att, '[lf-photo]' ) ) {
		$value['attachments'] = trim( $att . "\n[lf-photo]" );
	}
	return $value;
}
add_filter( 'wpcf7_contact_form_property_mail', 'arv_contact_mail_prop', 10, 2 );

/**
 * Extra lines for the staff body: topic fields that were filled.
 *
 * @return string
 */
function arv_contact_extra_lines() {
	$map   = array(
		'reg-email' => 'Registered with',
		'lf-item'   => 'Item',
		'lf-where'  => 'Where',
	);
	$lines = '';
	foreach ( $map as $f => $label ) {
		$v = arv_contact_posted( $f );
		if ( '' !== $v ) {
			$lines .= $label . ': ' . $v . "\n";
		}
	}
	return $lines;
}

/**
 * The mail name ('mail' or 'mail_2') of a WPCF7_Mail.
 *
 * @param mixed $mail WPCF7_Mail.
 * @return string
 */
function arv_contact_mail_name( $mail ) {
	return is_object( $mail ) && method_exists( $mail, 'name' ) ? (string) $mail->name() : '';
}

/**
 * Staff mail: reference subject, reference and topic fields in the body.
 * Auto-reply: reference, links, defanged, and the test diversion.
 * Recipient of the staff mail is never touched.
 *
 * @param array $components CF7 mail components.
 * @param mixed $form       WPCF7_ContactForm.
 * @param mixed $mail       WPCF7_Mail.
 * @return array
 */
function arv_contact_mail_components( $components, $form = null, $mail = null ) {
	if ( ! is_array( $components ) || ! arv_contact_is_ours( $form ) ) {
		return $components;
	}
	$name = arv_contact_mail_name( $mail );
	if ( 'mail' === $name ) {
		$components['subject'] = arv_contact_subject();
		$body                  = isset( $components['body'] ) ? (string) $components['body'] : '';
		$extra                 = arv_contact_extra_lines();
		if ( '' !== $extra ) {
			$pos = strpos( $body, "\n\nMessage:" );
			$body = false === $pos ? $body . "\n" . $extra : substr( $body, 0, $pos ) . "\n" . rtrim( $extra, "\n" ) . substr( $body, $pos );
		}
		$components['body'] = 'Reference: ' . arv_contact_ref() . "\n" . $body;
		return $components;
	}
	if ( 'mail_2' === $name ) {
		$reply                 = arv_contact_autoreply( arv_contact_topic() );
		$components['subject'] = $reply['subject'];
		$components['body']    = $reply['body'];
		if ( 'test' === arv_contact_autoreply_mode() ) {
			$components['recipient'] = arv_contact_autoreply_test_to();
		}
	}
	return $components;
}
add_filter( 'wpcf7_mail_components', 'arv_contact_mail_components', 30, 3 );

/* -------------------------------------------------------------------------
 * Auto-reply (Mail 2)
 * ---------------------------------------------------------------------- */

/**
 * Defang text that came from the visitor, so the auto-reply cannot be used
 * to send someone a clickable link from our address.
 *
 * @param string $text Text.
 * @return string
 */
function arv_contact_defang( $text ) {
	$text = preg_replace( '~\b[a-z][a-z0-9+.\-]{1,15}://~i', '', (string) $text );
	$text = preg_replace( '~\bwww\.~i', 'www[.]', $text );
	return preg_replace_callback(
		'~\b(?:[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,63}\b~i',
		function ( $m ) {
			return str_replace( '.', '[.]', $m[0] );
		},
		$text
	);
}

/**
 * Auto-reply subject and body for a topic.
 *
 * @param string $topic Topic label.
 * @param string $ref   Reference (default: this submission's).
 * @param string $name  First name (default: posted name).
 * @return array{subject:string,body:string}
 */
function arv_contact_autoreply( $topic, $ref = '', $name = null ) {
	$ref  = '' !== $ref ? $ref : arv_contact_ref();
	$name = null === $name ? arv_contact_posted( 'your-name', 60 ) : (string) $name;
	$first = trim( (string) strtok( $name, ' ' ) );
	$first = arv_contact_defang( $first );
	$help  = arv_contact_help();
	$links = isset( $help[ $topic ]['links'] ) ? array_slice( $help[ $topic ]['links'], 0, 3 ) : array();
	if ( ! $links ) {
		$links = array( array( 'FAQ', 'https://www.aravaiparunning.com/faq/' ) );
	}
	$body  = ( '' !== $first ? 'Hi ' . $first . ",\n\n" : "Hi,\n\n" );
	$body .= "Got it. Your message reached the Aravaipa Running team and we will reply within 1 to 2 business days.\n\n";
	$body .= 'Your reference number is ' . $ref . ". If you write again about this, keep it in the subject so we can find your message.\n\n";
	$body .= "While you wait, these may help:\n";
	foreach ( $links as $l ) {
		$body .= $l[0] . ': ' . $l[1] . "\n";
	}
	$body .= "\nAravaipa Running\nhttps://www.aravaiparunning.com\n";
	return array(
		'subject' => 'Got it: we received your message (' . $ref . ')',
		'body'    => $body,
	);
}

/**
 * Mail 2 for our form, only while the auto-reply is on.
 *
 * @param mixed $value Stored mail_2.
 * @param mixed $form  WPCF7_ContactForm.
 * @return mixed
 */
function arv_contact_mail2_prop( $value, $form = null ) {
	if ( ! arv_contact_is_ours( $form ) || '' === arv_contact_autoreply_mode() ) {
		return $value;
	}
	return array(
		'active'             => true,
		'subject'            => 'Got it',
		'sender'             => 'Aravaipa Running <wordpress@aravaiparunning.com>',
		'recipient'          => '[your-email]',
		'body'               => 'Got it',
		'additional_headers' => 'Reply-To: info@aravaiparunning.com',
		'attachments'        => '',
		'use_html'           => false,
		'exclude_blank'      => false,
	);
}
add_filter( 'wpcf7_contact_form_property_mail_2', 'arv_contact_mail2_prop', 10, 2 );

/**
 * Hard stop: with the auto-reply off, or in test mode with no staff test
 * address, Mail 2 of our form is never sent, whatever is stored.
 *
 * @param array $additional Additional mails (name => template).
 * @param mixed $form       WPCF7_ContactForm.
 * @return array
 */
function arv_contact_additional_mail( $additional, $form = null ) {
	if ( ! arv_contact_is_ours( $form ) || ! is_array( $additional ) ) {
		return $additional;
	}
	$mode = arv_contact_autoreply_mode();
	if ( '' === $mode || ( 'test' === $mode && '' === arv_contact_autoreply_test_to() ) ) {
		unset( $additional['mail_2'] );
	}
	return $additional;
}
add_filter( 'wpcf7_additional_mail', 'arv_contact_additional_mail', 99, 2 );

/* -------------------------------------------------------------------------
 * Flamingo
 * ---------------------------------------------------------------------- */

/**
 * Reference in Flamingo: subject prefix, a field and meta.
 *
 * @param array $args Flamingo_Inbound_Message::add() parameters.
 * @return array
 */
function arv_contact_flamingo( $args ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! arv_contact_is_ours( WPCF7_ContactForm::get_current() ) || ! is_array( $args ) ) {
		return $args;
	}
	$ref = arv_contact_ref();
	if ( ! isset( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
		$args['fields'] = array();
	}
	$args['fields']['reference'] = $ref;
	if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
		$args['meta'] = array();
	}
	$args['meta']['arv_reference'] = $ref;
	$subject                       = isset( $args['subject'] ) ? (string) $args['subject'] : '';
	$args['subject']               = '[' . $ref . '] ' . ( '' !== $subject ? $subject : 'Contact' );
	return $args;
}
add_filter( 'wpcf7_flamingo_inbound_message_parameters', 'arv_contact_flamingo', 20 );
