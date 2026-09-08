<?php
/**
 * Current-season race results for athlete profiles, read off UltraSignup.
 *
 * The results already on a profile are the text the old Cornerstone roster
 * page carried, migrated as-is. That text is curated (placings written the
 * way a human would write them) and it is also frozen: the last full season
 * in it is 2025, and only seven of the fifty one athletes have a 2026 line
 * at all. Nothing was ever going to update it except someone typing.
 *
 * This adds a season block above that curated history rather than replacing
 * it. The curation is worth keeping, and a synced block can go stale or wrong
 * without taking hand-written work down with it.
 *
 * Source is UltraSignup's own history feed, the one behind the
 * results_participant.aspx page each athlete already links to:
 *
 *   /service/events.svc/historybyname/{first}/{last}/
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ARV_ATHLETE_RESULTS_OPTION = 'arv_athlete_season_results';
const ARV_ATHLETE_RESULTS_SKIPPED = 'arv_athlete_season_results_skipped';

/**
 * A runner's full UltraSignup history, by name.
 *
 * Cached for six hours. The feed is the whole career in one response, so
 * this is one request per athlete per rebuild rather than one per race.
 *
 * @param string $first
 * @param string $last
 * @return array People, each with their own Results list.
 */
function arv_athlete_results_history( $first, $last ) {
	$key    = 'arv_ushist_' . md5( strtolower( $first . '|' . $last ) );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$url = 'https://ultrasignup.com/service/events.svc/historybyname/'
		. rawurlencode( $first ) . '/' . rawurlencode( $last ) . '/';

	$response = wp_remote_get( $url, array( 'timeout' => 20 ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// An hour, not six: a network blip should not pin an empty history
		// in place for the rest of the day.
		set_transient( $key, array(), HOUR_IN_SECONDS );
		return array();
	}

	$people = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $people ) ) {
		$people = array();
	}

	set_transient( $key, $people, 6 * HOUR_IN_SECONDS );

	return $people;
}

/**
 * Which of the people the feed returned is actually this athlete.
 *
 * A name is not an identifier on UltraSignup: "Alex Johnson" comes back as
 * twenty eight different runners and "Nathan Brown" as twenty one. Guessing
 * would put someone else's races on an athlete's page, so this only answers
 * when it is certain:
 *
 *   - one person came back, or
 *   - the stored participant id matches one of them.
 *
 * Anything else returns null and is reported as skipped rather than filled
 * in with a guess.
 *
 * @param array  $people
 * @param string $participant_id Stored id, may be empty.
 * @return array|null
 */
function arv_athlete_results_pick_person( $people, $participant_id ) {
	if ( 1 === count( $people ) ) {
		return $people[0];
	}

	if ( '' === (string) $participant_id ) {
		return null;
	}

	foreach ( $people as $person ) {
		if ( isset( $person['Id'] ) && (string) $person['Id'] === (string) $participant_id ) {
			return $person;
		}
	}

	return null;
}

/**
 * The finishes from one person's history for a given year.
 *
 * The feed mixes finishes and upcoming registrations: a race someone is
 * signed up for but has not run comes back with status -1, place 0 and a
 * time of "0". Only status 1 rows are real results, and Next Races already
 * covers the other kind.
 *
 * @param array $person
 * @param int   $year
 * @return array
 */
function arv_athlete_results_season( $person, $year ) {
	$out = array();

	foreach ( (array) ( isset( $person['Results'] ) ? $person['Results'] : array() ) as $row ) {
		if ( ! isset( $row['status'] ) || 1 !== (int) $row['status'] ) {
			continue;
		}

		$stamp = isset( $row['eventdate'] ) ? strtotime( (string) $row['eventdate'] ) : false;

		if ( ! $stamp || (int) gmdate( 'Y', $stamp ) !== (int) $year ) {
			continue;
		}

		$place = isset( $row['place'] ) ? (int) $row['place'] : 0;

		if ( $place < 1 ) {
			continue;
		}

		$out[] = array(
			'iso'   => gmdate( 'Y-m-d', $stamp ),
			'race'  => isset( $row['eventname'] ) ? sanitize_text_field( (string) $row['eventname'] ) : '',
			'place' => $place,
			'time'  => arv_athlete_results_clock( isset( $row['formattime'] ) ? (string) $row['formattime'] : '' ),
		);
	}

	// Most recent first, the way a season reads.
	usort(
		$out,
		static function ( $a, $b ) {
			return strcmp( $b['iso'], $a['iso'] );
		}
	);

	return $out;
}

/**
 * A finish time, or nothing.
 *
 * A fixed-time race scores distance rather than a clock, and UltraSignup
 * returns that distance in the same field: Johnny Ramos at the Saguaro
 * Showdown comes back as "125.001" and Maia Detmer at the Jackpot 48 Hour
 * as "180.132". Rendered as-is those read as finish times, which they are
 * not. The unit is not in the payload, so rather than guess at "miles" the
 * value is dropped and the line stands on its placing.
 *
 * @param string $raw
 * @return string
 */
function arv_athlete_results_clock( $raw ) {
	$raw = trim( $raw );

	return preg_match( '~^\d{1,4}:[0-5]\d(:[0-5]\d)?$~', $raw ) ? $raw : '';
}

/**
 * The first and last name to query, taken from the athlete's own
 * UltraSignup link rather than from the post title.
 *
 * The links were curated by hand and already carry the spelling UltraSignup
 * knows a runner by, which is not always the spelling on the roster page.
 *
 * @param string $url
 * @return array{0: string, 1: string}|null
 */
function arv_athlete_results_name_from_url( $url ) {
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );

	if ( '' === $query ) {
		return null;
	}

	$args = array();
	wp_parse_str( $query, $args );

	$first = isset( $args['fname'] ) ? trim( (string) $args['fname'] ) : '';
	$last  = isset( $args['lname'] ) ? trim( (string) $args['lname'] ) : '';

	if ( '' === $first || '' === $last ) {
		return null;
	}

	return array( $first, $last );
}

/**
 * Rebuild every athlete's season results. Runs on a daily cron.
 *
 * @param int|null $year Defaults to the current season.
 * @return array{matched: int, skipped: array, races: int}
 */
function arv_athlete_results_rebuild( $year = null ) {
	if ( null === $year ) {
		$year = (int) current_time( 'Y' );
	}

	$athletes = get_posts(
		array(
			'post_type'      => ARV_ATHLETE_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$mapping = array();
	$skipped = array();
	$races   = 0;

	foreach ( $athletes as $athlete ) {
		$url = (string) get_post_meta( $athlete->ID, '_arv_ultrasignup_url', true );

		if ( '' === $url ) {
			continue;
		}

		$name = arv_athlete_results_name_from_url( $url );

		if ( null === $name ) {
			continue;
		}

		$people = arv_athlete_results_history( $name[0], $name[1] );

		if ( empty( $people ) ) {
			continue;
		}

		$person = arv_athlete_results_pick_person(
			$people,
			get_post_meta( $athlete->ID, '_arv_ultrasignup_id', true )
		);

		if ( null === $person ) {
			$skipped[ $athlete->post_title ] = count( $people );
			continue;
		}

		// Worth storing while we have it: the next rebuild can then
		// disambiguate this athlete by id even if the name cannot.
		if ( isset( $person['Id'] ) && '' === (string) get_post_meta( $athlete->ID, '_arv_ultrasignup_id', true ) ) {
			update_post_meta( $athlete->ID, '_arv_ultrasignup_id', sanitize_text_field( (string) $person['Id'] ) );
		}

		$season = arv_athlete_results_season( $person, $year );

		if ( empty( $season ) ) {
			continue;
		}

		$mapping[ $athlete->ID ] = $season;
		$races                  += count( $season );
	}

	update_option( ARV_ATHLETE_RESULTS_OPTION, $mapping, false );
	update_option( ARV_ATHLETE_RESULTS_SKIPPED, $skipped, false );

	return array(
		'matched' => count( $mapping ),
		'skipped' => $skipped,
		'races'   => $races,
	);
}

/**
 * The season results block, rendered above the curated career results.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_season_results_markup( $athlete ) {
	if ( empty( $athlete['id'] ) ) {
		return '';
	}

	$mapping = get_option( ARV_ATHLETE_RESULTS_OPTION, array() );

	if ( empty( $mapping[ $athlete['id'] ] ) ) {
		return '';
	}

	$year = (int) current_time( 'Y' );

	$out = '<div class="arv-athlete__season">'
		. '<h2>' . esc_html( sprintf( __( '%d Results', 'aravaipa-elements' ), $year ) ) . '</h2>'
		. '<ul class="arv-athlete__season-list">';

	foreach ( $mapping[ $athlete['id'] ] as $race ) {
		$out .= '<li>';
		$out .= '<span class="arv-athlete__season-place">' . esc_html( arv_athlete_results_ordinal( $race['place'] ) ) . '</span> ';
		$out .= '<span class="arv-athlete__season-race">' . esc_html( $race['race'] ) . '</span>';

		if ( '' !== $race['time'] && '0' !== $race['time'] ) {
			$out .= ' <span class="arv-athlete__season-time">' . esc_html( $race['time'] ) . '</span>';
		}

		$out .= '</li>';
	}

	$out .= '</ul></div>';

	return $out;
}

/**
 * 1 to 1st, 2 to 2nd, and the teens that break the pattern.
 *
 * @param int $n
 * @return string
 */
function arv_athlete_results_ordinal( $n ) {
	$n = (int) $n;

	if ( in_array( $n % 100, array( 11, 12, 13 ), true ) ) {
		return $n . 'th';
	}

	$suffixes = array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' );

	return $n . $suffixes[ $n % 10 ];
}

/**
 * Daily cron, offset from the upcoming-races rebuild so the two are not
 * hammering UltraSignup in the same minute.
 */
function arv_athlete_results_cron_schedule() {
	if ( ! wp_next_scheduled( 'arv_athlete_results_cron' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 4:30am' ), 'daily', 'arv_athlete_results_cron' );
	}
}
add_action( 'init', 'arv_athlete_results_cron_schedule' );
add_action( 'arv_athlete_results_cron', 'arv_athlete_results_rebuild' );

function arv_athlete_results_deactivate() {
	wp_clear_scheduled_hook( 'arv_athlete_results_cron' );
}
register_deactivation_hook( ARV_ELEMENTS_PATH . 'aravaipa-elements.php', 'arv_athlete_results_deactivate' );

/**
 * A manual rebuild button under Athletes, which also lists who was skipped
 * for being ambiguous. That list is the point of the screen: it is the only
 * place the gaps are visible, and each one is fixed by putting the right
 * participant id on the athlete.
 */
function arv_athlete_results_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=' . ARV_ATHLETE_POST_TYPE,
		__( 'Season Results', 'aravaipa-elements' ),
		__( 'Season Results', 'aravaipa-elements' ),
		'manage_options',
		'arv-athlete-results',
		'arv_athlete_results_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_athlete_results_admin_menu' );

function arv_athlete_results_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['arv_rebuild'] ) && check_admin_referer( 'arv_athlete_results_rebuild' ) ) {
		$start  = microtime( true );
		$report = arv_athlete_results_rebuild();
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					'Rebuilt in %s seconds: %d athletes, %d results, %d skipped as ambiguous.',
					round( microtime( true ) - $start, 1 ),
					$report['matched'],
					$report['races'],
					count( $report['skipped'] )
				)
			)
		);
	}

	$mapping = get_option( ARV_ATHLETE_RESULTS_OPTION, array() );
	$skipped = get_option( ARV_ATHLETE_RESULTS_SKIPPED, array() );
	$year    = (int) current_time( 'Y' );

	echo '<div class="wrap"><h1>' . esc_html__( 'Season Results', 'aravaipa-elements' ) . '</h1>';
	echo '<p>' . esc_html(
		sprintf(
			/* translators: %d: the current year. */
			__( 'Reads each athlete\'s UltraSignup history and keeps their %d finishes on their profile. Runs automatically once a day; use this to run it now.', 'aravaipa-elements' ),
			$year
		)
	) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'arv_athlete_results_rebuild' );
	submit_button( __( 'Rebuild now', 'aravaipa-elements' ), 'primary', 'arv_rebuild' );
	echo '</form>';

	echo '<table class="widefat"><thead><tr><th>Athlete</th><th>' . esc_html( $year ) . ' results</th></tr></thead><tbody>';
	foreach ( $mapping as $post_id => $races ) {
		$list = implode(
			', ',
			array_map(
				static function ( $r ) {
					return arv_athlete_results_ordinal( $r['place'] ) . ' ' . $r['race'];
				},
				$races
			)
		);
		printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( get_the_title( $post_id ) ), esc_html( $list ) );
	}
	echo '</tbody></table>';

	if ( ! empty( $skipped ) ) {
		echo '<h2>' . esc_html__( 'Skipped as ambiguous', 'aravaipa-elements' ) . '</h2>';
		echo '<p>' . esc_html__( 'UltraSignup returned more than one runner with this name and the athlete has no participant ID on file, so there is no way to tell which one they are. Put the right ID on the athlete and these fill in on the next rebuild.', 'aravaipa-elements' ) . '</p>';
		echo '<table class="widefat"><thead><tr><th>Athlete</th><th>Runners with that name</th></tr></thead><tbody>';
		foreach ( $skipped as $name => $count ) {
			printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $name ), esc_html( $count ) );
		}
		echo '</tbody></table>';
	}

	echo '</div>';
}
