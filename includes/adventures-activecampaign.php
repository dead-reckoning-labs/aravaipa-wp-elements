<?php
/**
 * Aravaipa Adventures interest form to ActiveCampaign.
 *
 * Every valid signup on the Adventures interest form (the CF7 form whose ID
 * sits in the arv_adventures_form_id option) is also pushed to
 * ActiveCampaign, so the trips team can email each trip's crowd on its own:
 *
 *   - contact synced by email, first and last name split from adv-name;
 *   - added to the list "Aravaipa Adventures Interest" (created once if
 *     missing, its ID cached in arv_adv_ac_list_id);
 *   - tagged "Adventures interest", plus "Adventures: <trip>" for each trip
 *     picked (trip name without the dates, created once if missing, IDs
 *     cached in arv_adv_ac_tag_ids). "Any trip, keep me posted" only gets
 *     the general tag;
 *   - added to the main Aravaipa newsletter list as well, but only when the
 *     visitor ticked the optional "Also send me Aravaipa Running news" box
 *     (adv-news). Nobody lands on the newsletter without that box.
 *
 * It runs on wpcf7_mail_sent and on wpcf7_mail_failed, so a mail hiccup
 * never loses a lead. CF7 fires neither for spam or invalid submissions.
 *
 * The account connection is the one the official ActiveCampaign plugin
 * already holds (option settings_activecampaign: api_url, api_key). It is
 * read here at request time and never logged, echoed or stored anywhere
 * else.
 *
 * The visitor waits on this, so every request has a short timeout and the
 * chain stops at the first failure. A failure is written to a ring buffer
 * (arv_adv_ac_log, last 50, no key, no headers, masked email) and retried
 * once five minutes later through wp_schedule_single_event. The retry never
 * schedules another.
 *
 * WP-CLI: wp arv-adv-ac status | log | ensure | backfill [--apply] |
 * test --email=<e> [--trip=<label>] [--name=<n>] [--news] |
 * verify --email=<e> [--trip=<label>] | delete-test --email=<e>.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_ADV_AC_LIST_NAME', 'Aravaipa Adventures Interest' );
define( 'ARV_ADV_AC_TAG_ALL', 'Adventures interest' );
define( 'ARV_ADV_AC_TAG_PREFIX', 'Adventures: ' );
define( 'ARV_ADV_AC_ANY_TRIP', 'Any trip, keep me posted' );
define( 'ARV_ADV_AC_LOG_MAX', 50 );
/** ActiveCampaign list "Newsletter", the main Aravaipa list (recon 2026-11-17). */
define( 'ARV_ADV_AC_NEWS_LIST_DEFAULT', 2 );

/**
 * The ActiveCampaign connection from the official plugin's settings.
 *
 * @return array{base: string, key: string}|null Null when not connected.
 */
function arv_adv_ac_conn() {
	$s = get_option( 'settings_activecampaign' );
	if ( ! is_array( $s ) || empty( $s['api_url'] ) || empty( $s['api_key'] ) ) {
		return null;
	}
	return array(
		'base' => rtrim( (string) $s['api_url'], '/' ) . '/api/3/',
		'key'  => (string) $s['api_key'],
	);
}

/**
 * One API call.
 *
 * @param string     $method GET, POST or DELETE.
 * @param string     $path   Path under /api/3/, query included.
 * @param array|null $body   JSON body.
 * @return array{code: int, body: array, error: string}
 */
function arv_adv_ac_request( $method, $path, $body = null ) {
	$conn = arv_adv_ac_conn();
	if ( ! $conn ) {
		return array(
			'code'  => 0,
			'body'  => array(),
			'error' => 'not connected',
		);
	}
	$args = array(
		'method'  => $method,
		'timeout' => 5,
		'headers' => array(
			'Api-Token'    => $conn['key'],
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$url = $conn['base'] . ltrim( $path, '/' );
	if ( 'GET' === $method ) {
		$res = wp_remote_get( $url, $args );
	} elseif ( 'POST' === $method ) {
		$res = wp_remote_post( $url, $args );
	} else {
		$res = wp_remote_request( $url, $args );
	}
	if ( is_wp_error( $res ) ) {
		return array(
			'code'  => 0,
			'body'  => array(),
			'error' => arv_adv_ac_clean_msg( $res->get_error_message() ),
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$json = is_array( $json ) ? $json : array();
	$err  = '';
	if ( $code >= 300 ) {
		$err = 'HTTP ' . $code;
		if ( ! empty( $json['errors'][0]['title'] ) ) {
			$err .= ': ' . $json['errors'][0]['title'];
		} elseif ( ! empty( $json['message'] ) ) {
			$err .= ': ' . $json['message'];
		}
		$err = arv_adv_ac_clean_msg( $err );
	}
	return array(
		'code'  => $code,
		'body'  => $json,
		'error' => $err,
	);
}

/**
 * A message safe to store: short, and never carrying the key or the URL.
 *
 * @param string $msg Message.
 * @return string
 */
function arv_adv_ac_clean_msg( $msg ) {
	$msg  = (string) $msg;
	$conn = arv_adv_ac_conn();
	if ( $conn ) {
		$msg = str_replace( array( $conn['key'], $conn['base'] ), '[redacted]', $msg );
	}
	$msg = preg_replace( '#https?://\S+#', '[url]', $msg );
	return substr( $msg, 0, 160 );
}

/**
 * Email masked for the log: first letter and domain.
 *
 * @param string $email Email.
 * @return string
 */
function arv_adv_ac_mask( $email ) {
	$parts = explode( '@', (string) $email, 2 );
	if ( 2 !== count( $parts ) ) {
		return '?';
	}
	return substr( $parts[0], 0, 1 ) . '***@' . $parts[1];
}

/**
 * Append one row to the failure log, keeping the last 50.
 *
 * @param array $row Row.
 */
function arv_adv_ac_log( $row ) {
	$log = get_option( 'arv_adv_ac_log', array() );
	$log = is_array( $log ) ? $log : array();
	$log[] = array_merge( array( 'time' => gmdate( 'c' ) ), $row );
	if ( count( $log ) > ARV_ADV_AC_LOG_MAX ) {
		$log = array_slice( $log, -ARV_ADV_AC_LOG_MAX );
	}
	update_option( 'arv_adv_ac_log', array_values( $log ), false );
}

/**
 * Split "First Middle Last" into first word and the rest.
 *
 * @param string $name Full name.
 * @return array{0: string, 1: string}
 */
function arv_adv_ac_split_name( $name ) {
	$name = trim( preg_replace( '/\s+/', ' ', (string) $name ) );
	if ( '' === $name ) {
		return array( '', '' );
	}
	$parts = explode( ' ', $name, 2 );
	return array( $parts[0], isset( $parts[1] ) ? $parts[1] : '' );
}

/**
 * Trip names for the labels a visitor picked ("Name, dates").
 *
 * @param string|array $picked Select value(s).
 * @return string[] Unique trip names, without dates, "Any trip" dropped.
 */
function arv_adv_ac_trip_names( $picked ) {
	$map = array();
	if ( function_exists( 'arv_adv_trips' ) ) {
		foreach ( (array) arv_adv_trips( true ) as $trip ) {
			$name  = html_entity_decode( (string) $trip['name'], ENT_QUOTES, 'UTF-8' );
			$label = html_entity_decode( $trip['name'] . ', ' . $trip['dates'], ENT_QUOTES, 'UTF-8' );
			$map[ $label ] = $name;
		}
	}
	$out = array();
	foreach ( (array) $picked as $label ) {
		$label = trim( html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $label || ARV_ADV_AC_ANY_TRIP === $label ) {
			continue;
		}
		if ( isset( $map[ $label ] ) ) {
			$name = $map[ $label ];
		} else {
			$cut  = strrpos( $label, ',' );
			$name = false === $cut ? $label : trim( substr( $label, 0, $cut ) );
		}
		if ( '' !== $name && ! in_array( $name, $out, true ) ) {
			$out[] = $name;
		}
	}
	return $out;
}

/**
 * Whether a posted checkbox or acceptance value means yes.
 *
 * @param mixed $v Posted value.
 * @return bool
 */
function arv_adv_ac_truthy( $v ) {
	if ( is_array( $v ) ) {
		$v = reset( $v );
	}
	if ( is_bool( $v ) ) {
		return $v;
	}
	$v = strtolower( trim( (string) $v ) );
	return '' !== $v && ! in_array( $v, array( '0', 'false', 'no', 'off' ), true );
}

/**
 * The push payload from form fields (the same shape the hook builds).
 *
 * @param array $fields adv-name, adv-email, adv-trip, adv-news.
 * @return array|null Null without a valid email.
 */
function arv_adv_ac_payload( $fields ) {
	$email = isset( $fields['adv-email'] ) ? trim( (string) ( is_array( $fields['adv-email'] ) ? reset( $fields['adv-email'] ) : $fields['adv-email'] ) ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		return null;
	}
	$name = isset( $fields['adv-name'] ) ? ( is_array( $fields['adv-name'] ) ? reset( $fields['adv-name'] ) : $fields['adv-name'] ) : '';
	list( $first, $last ) = arv_adv_ac_split_name( $name );
	return array(
		'email'   => $email,
		'first'   => $first,
		'last'    => $last,
		'trips'   => arv_adv_ac_trip_names( isset( $fields['adv-trip'] ) ? $fields['adv-trip'] : array() ),
		'news'    => isset( $fields['adv-news'] ) && arv_adv_ac_truthy( $fields['adv-news'] ),
		'attempt' => 1,
	);
}

/**
 * The Adventures list ID, created once if missing.
 *
 * @return array{id: int, error: string, code: int}
 */
function arv_adv_ac_list_id() {
	$id = (int) get_option( 'arv_adv_ac_list_id' );
	if ( $id ) {
		return array( 'id' => $id, 'error' => '', 'code' => 200 );
	}
	$r = arv_adv_ac_request( 'GET', 'lists?' . http_build_query( array( 'filters' => array( 'name' => ARV_ADV_AC_LIST_NAME ) ) ) );
	if ( $r['error'] ) {
		return array( 'id' => 0, 'error' => $r['error'], 'code' => $r['code'] );
	}
	foreach ( (array) ( isset( $r['body']['lists'] ) ? $r['body']['lists'] : array() ) as $l ) {
		if ( isset( $l['name'] ) && ARV_ADV_AC_LIST_NAME === $l['name'] ) {
			$id = (int) $l['id'];
		}
	}
	if ( ! $id ) {
		$r = arv_adv_ac_request(
			'POST',
			'lists',
			array(
				'list' => array(
					'name'            => ARV_ADV_AC_LIST_NAME,
					'stringid'        => 'aravaipa-adventures-interest',
					'sender_url'      => 'https://www.aravaiparunning.com/adventures/',
					'sender_reminder' => 'You asked for Aravaipa Adventures trip updates on aravaiparunning.com.',
				),
			)
		);
		if ( $r['error'] || empty( $r['body']['list']['id'] ) ) {
			return array( 'id' => 0, 'error' => $r['error'] ? $r['error'] : 'list create: no id', 'code' => $r['code'] );
		}
		$id = (int) $r['body']['list']['id'];
	}
	update_option( 'arv_adv_ac_list_id', $id, false );
	return array( 'id' => $id, 'error' => '', 'code' => 200 );
}

/**
 * A tag ID by exact name, created once if missing.
 *
 * @param string $tag Tag name.
 * @return array{id: int, error: string, code: int}
 */
function arv_adv_ac_tag_id( $tag ) {
	$ids = get_option( 'arv_adv_ac_tag_ids', array() );
	$ids = is_array( $ids ) ? $ids : array();
	if ( ! empty( $ids[ $tag ] ) ) {
		return array( 'id' => (int) $ids[ $tag ], 'error' => '', 'code' => 200 );
	}
	$id = 0;
	$r  = arv_adv_ac_request( 'GET', 'tags?' . http_build_query( array( 'search' => $tag, 'limit' => 100 ) ) );
	if ( $r['error'] ) {
		return array( 'id' => 0, 'error' => $r['error'], 'code' => $r['code'] );
	}
	foreach ( (array) ( isset( $r['body']['tags'] ) ? $r['body']['tags'] : array() ) as $t ) {
		if ( isset( $t['tag'] ) && 0 === strcasecmp( trim( $t['tag'] ), $tag ) ) {
			$id = (int) $t['id'];
			break;
		}
	}
	if ( ! $id ) {
		$r = arv_adv_ac_request(
			'POST',
			'tags',
			array(
				'tag' => array(
					'tag'         => $tag,
					'tagType'     => 'contact',
					'description' => 'Aravaipa Adventures interest form',
				),
			)
		);
		if ( $r['error'] || empty( $r['body']['tag']['id'] ) ) {
			return array( 'id' => 0, 'error' => $r['error'] ? $r['error'] : 'tag create: no id', 'code' => $r['code'] );
		}
		$id = (int) $r['body']['tag']['id'];
	}
	$ids         = get_option( 'arv_adv_ac_tag_ids', array() );
	$ids         = is_array( $ids ) ? $ids : array();
	$ids[ $tag ] = $id;
	update_option( 'arv_adv_ac_tag_ids', $ids, false );
	return array( 'id' => $id, 'error' => '', 'code' => 200 );
}

/**
 * Tags a payload gets: the general one plus one per trip.
 *
 * @param array $p Payload.
 * @return string[]
 */
function arv_adv_ac_tags_for( $p ) {
	$tags = array( ARV_ADV_AC_TAG_ALL );
	foreach ( (array) $p['trips'] as $trip ) {
		$tags[] = ARV_ADV_AC_TAG_PREFIX . $trip;
	}
	return $tags;
}

/**
 * Push one signup. Stops at the first failure.
 *
 * @param array $p Payload from arv_adv_ac_payload().
 * @return array{ok: bool, step: string, code: int, error: string, contact: int}
 */
function arv_adv_ac_push( $p ) {
	$fail = static function ( $step, $r ) {
		return array( 'ok' => false, 'step' => $step, 'code' => (int) $r['code'], 'error' => (string) $r['error'], 'contact' => 0 );
	};
	if ( ! arv_adv_ac_conn() ) {
		return $fail( 'settings', array( 'code' => 0, 'error' => 'ActiveCampaign not connected' ) );
	}

	// Empty name parts are left out: most of these people are already in
	// the account from race entries, and a blank here would wipe the name
	// ActiveCampaign already has for them.
	$contact = array( 'email' => $p['email'] );
	if ( '' !== $p['first'] ) {
		$contact['firstName'] = $p['first'];
	}
	if ( '' !== $p['last'] ) {
		$contact['lastName'] = $p['last'];
	}
	$r = arv_adv_ac_request( 'POST', 'contact/sync', array( 'contact' => $contact ) );
	if ( $r['error'] || empty( $r['body']['contact']['id'] ) ) {
		return $fail( 'contact', $r['error'] ? $r : array( 'code' => $r['code'], 'error' => 'no contact id' ) );
	}
	$contact = (int) $r['body']['contact']['id'];

	$lists = array();
	$l     = arv_adv_ac_list_id();
	if ( $l['error'] ) {
		return $fail( 'list', $l );
	}
	$lists[] = $l['id'];
	if ( ! empty( $p['news'] ) ) {
		$lists[] = (int) get_option( 'arv_adv_ac_news_list_id', ARV_ADV_AC_NEWS_LIST_DEFAULT );
	}
	foreach ( $lists as $i => $list ) {
		$r = arv_adv_ac_request(
			'POST',
			'contactLists',
			array(
				'contactList' => array(
					'list'    => $list,
					'contact' => $contact,
					'status'  => 1,
				),
			)
		);
		if ( $r['error'] ) {
			// A cached Adventures list that was deleted in ActiveCampaign:
			// forget it so the retry finds or recreates it.
			if ( 0 === $i && in_array( $r['code'], array( 404, 422 ), true ) ) {
				delete_option( 'arv_adv_ac_list_id' );
			}
			return $fail( 0 === $i ? 'list_add' : 'news_add', $r );
		}
	}

	foreach ( arv_adv_ac_tags_for( $p ) as $tag ) {
		$t = arv_adv_ac_tag_id( $tag );
		if ( $t['error'] ) {
			return $fail( 'tag', $t );
		}
		$r = arv_adv_ac_request(
			'POST',
			'contactTags',
			array(
				'contactTag' => array(
					'contact' => $contact,
					'tag'     => $t['id'],
				),
			)
		);
		$dup = 409 === $r['code'] || ( 422 === $r['code'] && false !== stripos( $r['error'], 'duplicate' ) );
		if ( $r['error'] && ! $dup ) {
			if ( in_array( $r['code'], array( 404, 422 ), true ) ) {
				$ids = get_option( 'arv_adv_ac_tag_ids', array() );
				if ( is_array( $ids ) ) {
					unset( $ids[ $tag ] );
					update_option( 'arv_adv_ac_tag_ids', $ids, false );
				}
			}
			return $fail( 'tag_add', $r );
		}
	}

	return array( 'ok' => true, 'step' => 'done', 'code' => 200, 'error' => '', 'contact' => $contact );
}

/**
 * Push, and on failure log it and schedule the one retry.
 *
 * @param array $p           Payload.
 * @param bool  $allow_retry False for the CLI test signup, so a failed test
 *                           never re-creates the test contact after cleanup.
 * @return array Push result.
 */
function arv_adv_ac_handle( $p, $allow_retry = true ) {
	$res = arv_adv_ac_push( $p );
	if ( $res['ok'] ) {
		update_option(
			'arv_adv_ac_last_ok',
			array(
				'time'    => gmdate( 'c' ),
				'email'   => arv_adv_ac_mask( $p['email'] ),
				'attempt' => (int) $p['attempt'],
			),
			false
		);
		return $res;
	}
	$attempt = isset( $p['attempt'] ) ? (int) $p['attempt'] : 1;
	$retry   = $allow_retry && 1 === $attempt && 'settings' !== $res['step'];
	arv_adv_ac_log(
		array(
			'step'    => $res['step'],
			'code'    => $res['code'],
			'msg'     => $res['error'],
			'email'   => arv_adv_ac_mask( $p['email'] ),
			'attempt' => $attempt,
			'retry'   => $retry,
		)
	);
	if ( $retry ) {
		$p['attempt'] = 2;
		wp_schedule_single_event( time() + 300, 'arv_adv_ac_retry', array( $p ) );
	}
	return $res;
}

/**
 * The scheduled retry. Never schedules another.
 *
 * @param array $p Payload with attempt 2.
 */
function arv_adv_ac_retry( $p ) {
	if ( ! is_array( $p ) || empty( $p['email'] ) ) {
		return;
	}
	$p['attempt'] = 2;
	arv_adv_ac_handle( $p );
}
add_action( 'arv_adv_ac_retry', 'arv_adv_ac_retry', 10, 1 );

/**
 * Form fields from the current CF7 submission.
 *
 * @return array
 */
function arv_adv_ac_submission_fields() {
	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return array();
	}
	$sub = WPCF7_Submission::get_instance();
	if ( ! $sub ) {
		return array();
	}
	$out = array();
	foreach ( array( 'adv-name', 'adv-email', 'adv-trip', 'adv-news' ) as $k ) {
		$out[ $k ] = $sub->get_posted_data( $k );
	}
	return $out;
}

/**
 * CF7 hook: wpcf7_mail_sent and wpcf7_mail_failed.
 *
 * Never throws: the visitor's submission must go through whatever happens
 * here.
 *
 * @param object $contact_form WPCF7_ContactForm.
 */
function arv_adv_ac_on_submit( $contact_form ) {
	try {
		$form_id = (int) get_option( 'arv_adventures_form_id' );
		if ( ! $form_id || ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) || (int) $contact_form->id() !== $form_id ) {
			return;
		}
		$p = arv_adv_ac_payload( arv_adv_ac_submission_fields() );
		if ( $p ) {
			arv_adv_ac_handle( $p );
		}
	} catch ( \Throwable $e ) {
		arv_adv_ac_log(
			array(
				'step'    => 'exception',
				'code'    => 0,
				'msg'     => arv_adv_ac_clean_msg( $e->getMessage() ),
				'email'   => '?',
				'attempt' => 1,
				'retry'   => false,
			)
		);
	}
}
add_action( 'wpcf7_mail_sent', 'arv_adv_ac_on_submit', 20, 1 );
add_action( 'wpcf7_mail_failed', 'arv_adv_ac_on_submit', 20, 1 );

/**
 * Flamingo entries for the Adventures form.
 *
 * @return array<int, array> Field arrays, oldest first.
 */
function arv_adv_ac_flamingo_entries() {
	$form_id = (int) get_option( 'arv_adventures_form_id' );
	$post    = $form_id ? get_post( $form_id ) : null;
	if ( ! $post || ! taxonomy_exists( 'flamingo_inbound_channel' ) ) {
		return array();
	}
	$term = get_term_by( 'slug', $post->post_name, 'flamingo_inbound_channel' );
	if ( ! $term ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'      => 'flamingo_inbound',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'flamingo_inbound_channel',
					'field'    => 'term_id',
					'terms'    => (int) $term->term_id,
				),
			),
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		$f = array( 'id' => (int) $id );
		foreach ( array( 'adv-name', 'adv-email', 'adv-trip', 'adv-news' ) as $k ) {
			$f[ $k ] = get_post_meta( $id, '_field_' . $k, true );
		}
		if ( '' === (string) ( is_array( $f['adv-email'] ) ? reset( $f['adv-email'] ) : $f['adv-email'] ) ) {
			$f['adv-email'] = get_post_meta( $id, '_from_email', true );
		}
		$out[] = $f;
	}
	return $out;
}

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	/**
	 * Adventures interest form to ActiveCampaign.
	 */
	class Arv_Adv_AC_Command {

		/**
		 * Connection, cached list and tag IDs, last success and the last log rows.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function status( $args, $assoc ) {
			$me = arv_adv_ac_conn() ? arv_adv_ac_request( 'GET', 'users/me' ) : array( 'code' => 0 );
			WP_CLI::log( 'connected: ' . ( arv_adv_ac_conn() ? 'yes' : 'no' ) . ', users/me ' . (int) $me['code'] );
			WP_CLI::log( 'form id: ' . (int) get_option( 'arv_adventures_form_id' ) );
			WP_CLI::log( 'list id: ' . (int) get_option( 'arv_adv_ac_list_id' ) . ' (' . ARV_ADV_AC_LIST_NAME . ')' );
			WP_CLI::log( 'news list id: ' . (int) get_option( 'arv_adv_ac_news_list_id', ARV_ADV_AC_NEWS_LIST_DEFAULT ) );
			WP_CLI::log( 'tag ids: ' . wp_json_encode( get_option( 'arv_adv_ac_tag_ids', array() ) ) );
			WP_CLI::log( 'last ok: ' . wp_json_encode( get_option( 'arv_adv_ac_last_ok', null ) ) );
			WP_CLI::log( 'flamingo entries: ' . count( arv_adv_ac_flamingo_entries() ) );
			$log = (array) get_option( 'arv_adv_ac_log', array() );
			WP_CLI::log( 'log rows: ' . count( $log ) );
			foreach ( array_slice( $log, -5 ) as $row ) {
				WP_CLI::log( '  ' . wp_json_encode( $row ) );
			}
		}

		/**
		 * The failure log (last 50).
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function log( $args, $assoc ) {
			foreach ( (array) get_option( 'arv_adv_ac_log', array() ) as $row ) {
				WP_CLI::log( wp_json_encode( $row ) );
			}
		}

		/**
		 * Make sure the list and the tags for every current trip exist.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function ensure( $args, $assoc ) {
			$l = arv_adv_ac_list_id();
			if ( $l['error'] ) {
				WP_CLI::error( 'list: ' . $l['error'] );
			}
			WP_CLI::log( 'list ' . $l['id'] );
			$names = array();
			foreach ( (array) arv_adv_trips( true ) as $trip ) {
				$names[] = html_entity_decode( (string) $trip['name'], ENT_QUOTES, 'UTF-8' );
			}
			$tags = array( ARV_ADV_AC_TAG_ALL );
			foreach ( array_unique( $names ) as $n ) {
				$tags[] = ARV_ADV_AC_TAG_PREFIX . $n;
			}
			foreach ( $tags as $tag ) {
				$t = arv_adv_ac_tag_id( $tag );
				if ( $t['error'] ) {
					WP_CLI::error( 'tag ' . $tag . ': ' . $t['error'] );
				}
				WP_CLI::log( 'tag ' . $t['id'] . ' ' . $tag );
			}
			WP_CLI::success( 'ensured list and ' . count( $tags ) . ' tags' );
		}

		/**
		 * Push Flamingo entries for this form. Dry run unless --apply.
		 *
		 * [--apply]
		 * : Push for real.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function backfill( $args, $assoc ) {
			$entries = arv_adv_ac_flamingo_entries();
			$valid   = 0;
			$ok      = 0;
			$bad     = 0;
			foreach ( $entries as $f ) {
				$p = arv_adv_ac_payload( $f );
				if ( ! $p ) {
					continue;
				}
				++$valid;
				if ( empty( $assoc['apply'] ) ) {
					continue;
				}
				$res = arv_adv_ac_handle( $p );
				$res['ok'] ? ++$ok : ++$bad;
			}
			if ( empty( $assoc['apply'] ) ) {
				WP_CLI::log( 'backfill dry run: ' . count( $entries ) . ' entries, ' . $valid . ' with a valid email' );
				return;
			}
			WP_CLI::log( 'backfill: ' . count( $entries ) . ' entries, ' . $valid . ' valid, ' . $ok . ' pushed, ' . $bad . ' failed (retry scheduled)' );
		}

		/**
		 * Push one signup through the same handler the form hook uses.
		 *
		 * --email=<email>
		 * : Email.
		 *
		 * [--name=<name>]
		 * : Full name.
		 *
		 * [--trip=<label>]
		 * : Trip select label, for example "Madeira Adventure, October 3-9, 2027".
		 *
		 * [--news]
		 * : Also tick the newsletter box.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function test( $args, $assoc ) {
			$p = arv_adv_ac_payload(
				array(
					'adv-name'  => isset( $assoc['name'] ) ? $assoc['name'] : 'Adventures Test',
					'adv-email' => isset( $assoc['email'] ) ? $assoc['email'] : '',
					'adv-trip'  => isset( $assoc['trip'] ) ? $assoc['trip'] : ARV_ADV_AC_ANY_TRIP,
					'adv-news'  => ! empty( $assoc['news'] ) ? '1' : '',
				)
			);
			if ( ! $p ) {
				WP_CLI::error( 'valid --email required' );
			}
			$res = arv_adv_ac_handle( $p, false );
			WP_CLI::log( 'tags: ' . implode( ' | ', arv_adv_ac_tags_for( $p ) ) );
			if ( ! $res['ok'] ) {
				WP_CLI::error( 'push failed at ' . $res['step'] . ' (' . $res['code'] . ') ' . $res['error'] );
			}
			WP_CLI::success( 'pushed contact ' . $res['contact'] );
		}

		/**
		 * Check a contact is on the list with the general tag (and a trip tag).
		 *
		 * --email=<email>
		 * : Email.
		 *
		 * [--trip=<label>]
		 * : Trip label whose tag must be present.
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function verify( $args, $assoc ) {
			$email = isset( $assoc['email'] ) ? (string) $assoc['email'] : '';
			$c     = $this->contact_id( $email );
			$list  = (int) get_option( 'arv_adv_ac_list_id' );
			$r     = arv_adv_ac_request( 'GET', 'contacts/' . $c . '/contactLists' );
			$on    = false;
			foreach ( (array) ( isset( $r['body']['contactLists'] ) ? $r['body']['contactLists'] : array() ) as $cl ) {
				if ( (int) $cl['list'] === $list && 1 === (int) $cl['status'] ) {
					$on = true;
				}
			}
			WP_CLI::log( 'contact ' . $c . ', on list ' . $list . ': ' . ( $on ? 'yes' : 'no' ) );
			$want = arv_adv_ac_tags_for( array( 'trips' => arv_adv_ac_trip_names( isset( $assoc['trip'] ) ? $assoc['trip'] : array() ) ) );
			$ids  = (array) get_option( 'arv_adv_ac_tag_ids', array() );
			$r    = arv_adv_ac_request( 'GET', 'contacts/' . $c . '/contactTags' );
			$have = array();
			foreach ( (array) ( isset( $r['body']['contactTags'] ) ? $r['body']['contactTags'] : array() ) as $ct ) {
				$have[] = (int) $ct['tag'];
			}
			$missing = array();
			foreach ( $want as $tag ) {
				$ok = ! empty( $ids[ $tag ] ) && in_array( (int) $ids[ $tag ], $have, true );
				WP_CLI::log( 'tag ' . $tag . ': ' . ( $ok ? 'yes' : 'no' ) );
				if ( ! $ok ) {
					$missing[] = $tag;
				}
			}
			if ( ! $on || $missing ) {
				WP_CLI::error( 'verify failed' );
			}
			WP_CLI::success( 'contact on list with ' . count( $want ) . ' tags' );
		}

		/**
		 * Delete a test contact from ActiveCampaign. Only +advtest addresses.
		 *
		 * --email=<email>
		 * : A +advtest@ address.
		 *
		 * @subcommand delete-test
		 *
		 * @param array $args  Args.
		 * @param array $assoc Assoc args.
		 */
		public function delete_test( $args, $assoc ) {
			$email = isset( $assoc['email'] ) ? (string) $assoc['email'] : '';
			if ( false === strpos( $email, '+advtest@' ) ) {
				WP_CLI::error( 'only +advtest@ test addresses can be deleted here' );
			}
			$this->clear_test_retries( $email );
			$c = $this->contact_id( $email );
			$r = arv_adv_ac_request( 'DELETE', 'contacts/' . $c );
			if ( $r['error'] ) {
				WP_CLI::error( 'delete failed: ' . $r['error'] );
			}
			WP_CLI::success( 'deleted contact ' . $c );
		}

		/**
		 * Unschedule any pending retry for a test address, so wp-cron can
		 * never re-create the test contact after it is deleted.
		 *
		 * @param string $email Test email.
		 */
		private function clear_test_retries( $email ) {
			$n    = 0;
			$cron = function_exists( '_get_cron_array' ) ? (array) _get_cron_array() : array();
			foreach ( $cron as $ts => $hooks ) {
				if ( empty( $hooks['arv_adv_ac_retry'] ) ) {
					continue;
				}
				foreach ( $hooks['arv_adv_ac_retry'] as $ev ) {
					$a = isset( $ev['args'] ) ? $ev['args'] : array();
					if ( isset( $a[0]['email'] ) && strtolower( $a[0]['email'] ) === strtolower( $email ) ) {
						wp_unschedule_event( $ts, 'arv_adv_ac_retry', $a );
						$n++;
					}
				}
			}
			WP_CLI::log( 'test retries cleared: ' . $n );
		}

		/**
		 * Contact ID by email, or exit.
		 *
		 * @param string $email Email.
		 * @return int
		 */
		private function contact_id( $email ) {
			if ( ! is_email( $email ) ) {
				WP_CLI::error( 'valid --email required' );
			}
			$r = arv_adv_ac_request( 'GET', 'contacts?' . http_build_query( array( 'email' => $email ) ) );
			$c = isset( $r['body']['contacts'][0]['id'] ) ? (int) $r['body']['contacts'][0]['id'] : 0;
			if ( ! $c ) {
				WP_CLI::error( 'contact not found (' . (int) $r['code'] . ')' );
			}
			return $c;
		}
	}

	WP_CLI::add_command( 'arv-adv-ac', 'Arv_Adv_AC_Command' );
}
