<?php
/**
 * Registration options form for an event that changed under its runners.
 *
 * Louisville Trail Race 2026 moved to Rogers Grove Park in Longmont a month
 * out, and every registrant can take a full refund, a 100% race credit, or
 * stay registered. [arv_event_options event="louisville-2026"] renders the
 * form; each submission is kept as a private arv_event_request post so the
 * team can work the list in wp-admin (Event Requests), mark each one done,
 * and export CSV. The runner gets a confirmation email and info@ gets one
 * daily digest of new requests.
 *
 * Every name is checked against the event's Momentum roster, so a typo or a
 * non-registrant is flagged on the list instead of looking like a real
 * request. The form closes itself at the deadline.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Events that take requests. Keyed by the shortcode's event attribute.
 */
function arv_event_options_events() {
	return array(
		'louisville-2026' => array(
			'name'      => 'Louisville Trail Races at Rogers Grove',
			'race_date' => 'Saturday, November 14, 2026',
			'deadline'  => '2026-10-27 23:59:59',
			'tz'        => 'America/Denver',
			'distances' => array( 'Half Marathon', '10K', '5K' ),
			'momentum'  => 'louisville_trail_race-2026',
			'notify'    => 'info@aravaiparunning.com',
			'page'      => 'https://www.aravaiparunning.com/louisville/',
		),
	);
}

/** The three choices, value => label. */
function arv_event_options_choices() {
	return array(
		'refund' => 'Full refund',
		'credit' => '100% race credit toward another Aravaipa race',
		'stay'   => 'Stay registered, see you on November 14',
	);
}

function arv_event_options_closed( $ev ) {
	$deadline = new DateTime( $ev['deadline'], new DateTimeZone( $ev['tz'] ) );
	return time() > $deadline->getTimestamp();
}

/**
 * Registrant names from Momentum's public event payload, lowercased and
 * whitespace-collapsed, cached for an hour. Empty array if Momentum is down,
 * which the caller treats as "could not check", never as "not registered".
 */
function arv_event_options_roster( $slug ) {
	$key    = 'arv_evopt_roster_' . md5( $slug );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$res = wp_remote_get(
		'https://live.aravaiparunning.com/api/v1/race_events/' . rawurlencode( $slug ),
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json', 'x-live-ver' => '200' ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return array();
	}
	$data  = json_decode( wp_remote_retrieve_body( $res ), true );
	$names = array();
	foreach ( (array) ( $data['participants'] ?? array() ) as $p ) {
		$names[] = arv_event_options_norm( ( $p['firstName'] ?? '' ) . ' ' . ( $p['lastName'] ?? '' ) );
	}
	set_transient( $key, $names, HOUR_IN_SECONDS );
	return $names;
}

function arv_event_options_norm( $s ) {
	return trim( preg_replace( '/\s+/', ' ', strtolower( remove_accents( (string) $s ) ) ) );
}

/** 'yes', 'no' or 'unknown' (roster unavailable). */
function arv_event_options_match( $ev, $name ) {
	$roster = arv_event_options_roster( $ev['momentum'] );
	if ( ! $roster ) {
		return 'unknown';
	}
	return in_array( arv_event_options_norm( $name ), $roster, true ) ? 'yes' : 'no';
}

add_action(
	'init',
	function () {
		register_post_type(
			'arv_event_request',
			array(
				'labels'          => array(
					'name'          => 'Event Requests',
					'singular_name' => 'Event Request',
					'menu_name'     => 'Event Requests',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-feedback',
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	}
);

add_shortcode(
	'arv_event_options',
	function ( $atts ) {
		$atts   = shortcode_atts( array( 'event' => '' ), $atts );
		$events = arv_event_options_events();
		if ( ! isset( $events[ $atts['event'] ] ) ) {
			return '';
		}
		$ev   = $events[ $atts['event'] ];
		$slug = $atts['event'];
		ob_start();
		echo '<div class="arv-evopt">';
		echo '<style>.arv-evopt{max-width:640px;margin:0 auto;text-align:left;font-family:Lato,sans-serif;color:#000;font-size:15px;line-height:1.5}.arv-evopt label{display:block;font-weight:700;margin:14px 0 4px}.arv-evopt input[type=text],.arv-evopt input[type=email],.arv-evopt select,.arv-evopt textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #bbb;border-radius:0;font:inherit}.arv-evopt .opt{display:block;font-weight:400;margin:6px 0;text-align:left}.arv-evopt button{margin-top:18px;background:#fdcf00;color:#ff2a13;border:0;border-radius:36px;padding:12px 28px;font:700 15px Lato,sans-serif;cursor:pointer;text-shadow:none}.arv-evopt .ok{padding:14px 0;font-weight:700}.arv-evopt .err{color:#b00020;font-weight:700}.arv-evopt .hp{position:absolute;left:-9999px}.arv-evopt .small{color:#555;font-size:13px}</style>';
		$state = isset( $_GET['arv_evopt'] ) ? sanitize_key( wp_unslash( $_GET['arv_evopt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sent' === $state ) {
			echo '<p class="ok">Thanks, we have your request. A confirmation is on its way to your email, and our team will follow up within a few business days.</p></div>';
			return ob_get_clean();
		}
		if ( arv_event_options_closed( $ev ) ) {
			echo '<p class="ok">The request window for ' . esc_html( $ev['name'] ) . ' has closed. Questions? Email <a href="mailto:info@aravaiparunning.com">info@aravaiparunning.com</a>.</p></div>';
			return ob_get_clean();
		}
		if ( 'err' === $state ) {
			echo '<p class="err">Please fill in your name, the email you registered with, your distance and a choice.</p>';
		}
		$deadline = new DateTime( $ev['deadline'], new DateTimeZone( $ev['tz'] ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="arv_event_options">';
		echo '<input type="hidden" name="event" value="' . esc_attr( $slug ) . '">';
		echo '<input type="hidden" name="back" value="' . esc_url( get_permalink() ) . '">';
		wp_nonce_field( 'arv_evopt_' . $slug, '_arv_evopt' );
		echo '<label class="hp" aria-hidden="true">Leave blank <input type="text" name="website" tabindex="-1" autocomplete="off"></label>';
		echo '<label for="evopt-name">Runner name (as registered)</label><input id="evopt-name" type="text" name="runner_name" required maxlength="120">';
		echo '<label for="evopt-email">Email you registered with</label><input id="evopt-email" type="email" name="runner_email" required maxlength="160">';
		echo '<label for="evopt-dist">Distance</label><select id="evopt-dist" name="distance" required><option value="">Choose one</option>';
		foreach ( $ev['distances'] as $d ) {
			echo '<option>' . esc_html( $d ) . '</option>';
		}
		echo '</select><label>Your choice</label>';
		foreach ( arv_event_options_choices() as $k => $label ) {
			echo '<label class="opt"><input type="radio" name="choice" value="' . esc_attr( $k ) . '" required> ' . esc_html( $label ) . '</label>';
		}
		echo '<label for="evopt-note">Anything we should know? (optional)</label><textarea id="evopt-note" name="note" rows="3" maxlength="1000"></textarea>';
		echo '<button type="submit">Submit request</button>';
		echo '<p class="small">Requests close ' . esc_html( $deadline->format( 'l, F j' ) ) . ' at 11:59 PM MT. One request per runner; if you registered more than one person, submit once for each.</p>';
		echo '</form></div>';
		return ob_get_clean();
	}
);

function arv_event_options_handle() {
	$slug   = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
	$events = arv_event_options_events();
	$back   = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : home_url( '/' );
	if ( ! isset( $events[ $slug ] ) || ! isset( $_POST['_arv_evopt'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_arv_evopt'] ) ), 'arv_evopt_' . $slug ) ) {
		wp_safe_redirect( $back );
		exit;
	}
	$ev = $events[ $slug ];
	if ( ! empty( $_POST['website'] ) || arv_event_options_closed( $ev ) ) {
		wp_safe_redirect( add_query_arg( 'arv_evopt', 'sent', $back ) );
		exit;
	}
	$name     = sanitize_text_field( wp_unslash( $_POST['runner_name'] ?? '' ) );
	$email    = sanitize_email( wp_unslash( $_POST['runner_email'] ?? '' ) );
	$distance = sanitize_text_field( wp_unslash( $_POST['distance'] ?? '' ) );
	$choice   = sanitize_key( wp_unslash( $_POST['choice'] ?? '' ) );
	$note     = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
	$choices  = arv_event_options_choices();
	if ( '' === $name || ! is_email( $email ) || ! in_array( $distance, $ev['distances'], true ) || ! isset( $choices[ $choice ] ) ) {
		wp_safe_redirect( add_query_arg( 'arv_evopt', 'err', $back ) );
		exit;
	}
	$match = arv_event_options_match( $ev, $name );
	wp_insert_post(
		array(
			'post_type'   => 'arv_event_request',
			'post_status' => 'private',
			'post_title'  => $name . ' - ' . $choices[ $choice ],
			'meta_input'  => array(
				'_evopt_event'    => $slug,
				'_evopt_name'     => $name,
				'_evopt_email'    => $email,
				'_evopt_distance' => $distance,
				'_evopt_choice'   => $choice,
				'_evopt_note'     => $note,
				'_evopt_roster'   => $match,
				'_evopt_status'   => 'new',
			),
		)
	);
	$label   = $choices[ $choice ];
	$summary = "Event: {$ev['name']} ({$ev['race_date']})\nName: {$name}\nEmail: {$email}\nDistance: {$distance}\nChoice: {$label}\n" . ( $note ? "Note: {$note}\n" : '' );
	wp_mail(
		$email,
		'We got your request: ' . $ev['name'],
		"Hi {$name},\n\nThanks for letting us know. Here is what we received:\n\n{$summary}\nOur team will process your request and follow up within a few business days. Questions? Reply to this email or write to info@aravaiparunning.com.\n\nThe Aravaipa Team\n",
		array( 'From: Aravaipa Running <info@aravaiparunning.com>', 'Reply-To: info@aravaiparunning.com' )
	);
	wp_safe_redirect( add_query_arg( 'arv_evopt', 'sent', $back ) );
	exit;
}
add_action( 'admin_post_nopriv_arv_event_options', 'arv_event_options_handle' );
add_action( 'admin_post_arv_event_options', 'arv_event_options_handle' );

// One email a day to each event's notify address instead of one per request:
// a copy per submission buried info@ the morning the Louisville email went
// out. The digest lists what came in since the last one, flags names not on
// the roster, and links to the Event Requests list. Nothing new, no email.
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'arv_evopt_digest' ) ) {
			// Start from now, so the first digest doesn't resend what info@ already got.
			add_option( 'arv_evopt_digest_last', current_time( 'mysql' ), '', false );
			$next = new DateTime( 'tomorrow 07:00', new DateTimeZone( 'America/Denver' ) );
			$now  = new DateTime( 'today 07:00', new DateTimeZone( 'America/Denver' ) );
			wp_schedule_event( time() < $now->getTimestamp() ? $now->getTimestamp() : $next->getTimestamp(), 'daily', 'arv_evopt_digest' );
		}
	}
);
add_action( 'arv_evopt_digest', 'arv_event_options_digest' );

function arv_event_options_digest() {
	$since   = (string) get_option( 'arv_evopt_digest_last', '' );
	$now     = current_time( 'mysql' );
	$events  = arv_event_options_events();
	$choices = arv_event_options_choices();
	$args    = array(
		'post_type'   => 'arv_event_request',
		'post_status' => 'any',
		'numberposts' => -1,
		'orderby'     => 'date',
		'order'       => 'ASC',
	);
	if ( '' !== $since ) {
		$args['date_query'] = array( array( 'after' => $since, 'inclusive' => false ) );
	}
	$by_event = array();
	foreach ( get_posts( $args ) as $p ) {
		$by_event[ (string) get_post_meta( $p->ID, '_evopt_event', true ) ][] = $p;
	}
	foreach ( $by_event as $slug => $posts ) {
		if ( ! isset( $events[ $slug ] ) ) {
			continue;
		}
		$ev     = $events[ $slug ];
		$counts = array_fill_keys( array_keys( $choices ), 0 );
		$lines  = array();
		foreach ( $posts as $p ) {
			$m      = function ( $k ) use ( $p ) {
				return (string) get_post_meta( $p->ID, $k, true );
			};
			$choice = $m( '_evopt_choice' );
			if ( isset( $counts[ $choice ] ) ) {
				++$counts[ $choice ];
			}
			$flag    = 'yes' === $m( '_evopt_roster' ) ? '' : ( 'no' === $m( '_evopt_roster' ) ? '  [name not on roster, match by email]' : '  [roster check unavailable]' );
			$note    = $m( '_evopt_note' );
			$lines[] = '- ' . $m( '_evopt_name' ) . ' <' . $m( '_evopt_email' ) . '>, ' . $m( '_evopt_distance' ) . ': ' . ( $choices[ $choice ] ?? $choice ) . $flag . ( '' !== $note ? "\n  Note: " . $note : '' );
		}
		$tally = array();
		foreach ( $counts as $k => $n ) {
			$tally[] = $choices[ $k ] . ': ' . $n;
		}
		wp_mail(
			$ev['notify'],
			'[' . $ev['name'] . '] ' . count( $posts ) . ' new request' . ( 1 === count( $posts ) ? '' : 's' ),
			"New since the last digest:\n" . implode( "\n", $tally ) . "\n\n" . implode( "\n", $lines ) . "\n\nWork the list and export CSV here:\n" . admin_url( 'edit.php?post_type=arv_event_request' ) . "\n",
			array( 'From: Aravaipa Running <info@aravaiparunning.com>' )
		);
	}
	update_option( 'arv_evopt_digest_last', $now, false );
}

// wp-admin list: the columns the team works from, a status they can flip,
// and a CSV export.
add_filter(
	'manage_arv_event_request_posts_columns',
	function () {
		return array(
			'cb'       => '<input type="checkbox" />',
			'title'    => 'Runner',
			'evemail'  => 'Email',
			'evdist'   => 'Distance',
			'evchoice' => 'Choice',
			'evroster' => 'On roster',
			'evstatus' => 'Status',
			'date'     => 'Submitted',
		);
	}
);
add_action(
	'manage_arv_event_request_posts_custom_column',
	function ( $col, $id ) {
		$map = array(
			'evemail'  => '_evopt_email',
			'evdist'   => '_evopt_distance',
			'evchoice' => '_evopt_choice',
			'evroster' => '_evopt_roster',
			'evstatus' => '_evopt_status',
		);
		if ( ! isset( $map[ $col ] ) ) {
			return;
		}
		$v = (string) get_post_meta( $id, $map[ $col ], true );
		if ( 'evstatus' === $col ) {
			$next = 'new' === $v ? 'done' : 'new';
			$url  = wp_nonce_url( admin_url( 'admin-post.php?action=arv_evopt_status&id=' . $id . '&to=' . $next ), 'arv_evopt_status_' . $id );
			echo esc_html( $v ) . ' <a href="' . esc_url( $url ) . '">mark ' . esc_html( $next ) . '</a>';
			return;
		}
		echo esc_html( $v );
	},
	10,
	2
);
add_action(
	'admin_post_arv_evopt_status',
	function () {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$to = isset( $_GET['to'] ) && 'done' === $_GET['to'] ? 'done' : 'new';
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'arv_evopt_status_' . $id ) ) {
			wp_die( 'Not allowed' );
		}
		update_post_meta( $id, '_evopt_status', $to );
		wp_safe_redirect( admin_url( 'edit.php?post_type=arv_event_request' ) );
		exit;
	}
);
add_action(
	'restrict_manage_posts',
	function ( $type ) {
		if ( 'arv_event_request' === $type ) {
			echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=arv_evopt_csv' ), 'arv_evopt_csv' ) ) . '">Export CSV</a>';
		}
	}
);
add_action(
	'admin_post_arv_evopt_csv',
	function () {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'arv_evopt_csv' ) ) {
			wp_die( 'Not allowed' );
		}
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=event-requests-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'submitted', 'event', 'name', 'email', 'distance', 'choice', 'on_roster', 'status', 'note' ) );
		$posts = get_posts( array( 'post_type' => 'arv_event_request', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'ASC' ) );
		foreach ( $posts as $p ) {
			$m = function ( $k ) use ( $p ) {
				return (string) get_post_meta( $p->ID, $k, true );
			};
			fputcsv( $out, array( $p->post_date, $m( '_evopt_event' ), $m( '_evopt_name' ), $m( '_evopt_email' ), $m( '_evopt_distance' ), $m( '_evopt_choice' ), $m( '_evopt_roster' ), $m( '_evopt_status' ), $m( '_evopt_note' ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);
