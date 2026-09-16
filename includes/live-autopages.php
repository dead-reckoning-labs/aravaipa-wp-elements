<?php
/**
 * Race week live results pages, created without anyone remembering to.
 *
 * A race's LIVE RESULTS button is only as good as two things a person used to
 * have to do by hand. The race store has to carry the race's live board URL,
 * which only happened when scripts/fetch-races.mjs was re-run after the timing
 * team put the race on the board. And the branded page under /live-results/
 * (see live-page.php) had to be created in wp-admin. Miss the first and the
 * button falls back to UltraSignup; miss the second and it sends people to the
 * bare board on live.aravaiparunning.com instead of a page on this site. Bryce
 * Canyon, Jangover and Kilkenny all went into race week in September 2026
 * missing one or the other.
 *
 * This does both, every day, for every race inside its race week window:
 *
 * 1. No live URL in the store: look the race up on the board's own public list
 *    of events and write the URL onto the race. Matched on name and date the
 *    same way fetch-races.mjs does, so the two agree on what counts as the
 *    same race.
 * 2. No page for that board slug: create /live-results/<board name>/ with the
 *    [arv_live] shortcode and the _arv_live_slug meta the rest of live-page.php
 *    discovers pages by.
 * 3. A page already at that address for last year's running: keep last year
 *    as /live-results/<name>-<year>/ and point the main page at this year, so
 *    the stable URL is always the current race and older ones stay reachable.
 * 4. Earlier runnings the results archive knows the board slug for get their
 *    own /live-results/<name>-<year>/ page too, the way Mogollon Monster's
 *    were built by hand, so the archive's per-year Live Results links land on
 *    this site instead of the bare board.
 * 5. The race's own page on this site: a button linking straight to this
 *    year's board, or to this year's UltraSignup results, is repointed at the
 *    new page. Only those two exact URLs, only on that one page.
 *
 * Runs on a daily cron, and on demand with `wp arv live-pages [--dry-run]`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_LIVE_BOARD_EVENTS_API', 'https://live.aravaiparunning.com/api/v1/race_events/live' );
define( 'ARV_LIVE_PARENT_PATH', 'live-results' );

// Days before a race starts that its page is created, and days after it ends
// that a missing one is still worth creating.
define( 'ARV_LIVE_AUTO_LEAD_DAYS', 7 );
define( 'ARV_LIVE_AUTO_TAIL_DAYS', 3 );

/**
 * The board's current events, reduced to what matching needs.
 *
 * @return array<int, array{slug:string,name:string,date:string}>|null Null when the board could not be read.
 */
function arv_live_auto_board_events() {
	$response = wp_remote_get( ARV_LIVE_BOARD_EVENTS_API, array( 'timeout' => 20 ) );

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$events = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $events ) ) {
		return null;
	}

	$out = array();

	foreach ( $events as $event ) {
		if ( empty( $event['slug'] ) || empty( $event['name'] ) ) {
			continue;
		}

		// The event carries no start of its own, only its distances do. The
		// earliest distance start, in the event's own timezone, is its date.
		$first = '';

		foreach ( (array) ( isset( $event['races'] ) ? $event['races'] : array() ) as $race ) {
			if ( ! empty( $race['startTime'] ) && ( '' === $first || $race['startTime'] < $first ) ) {
				$first = (string) $race['startTime'];
			}
		}

		$date = '';

		if ( '' !== $first ) {
			$offset = isset( $event['timezoneOffset'] ) ? (float) $event['timezoneOffset'] : 0;
			$date   = gmdate( 'Y-m-d', (int) ( strtotime( $first ) + $offset * HOUR_IN_SECONDS ) );
		}

		$out[] = array(
			'slug' => (string) $event['slug'],
			'name' => (string) $event['name'],
			'date' => $date,
		);
	}

	return $out;
}

/**
 * Words that identify a race, with the ones every race name shares removed.
 *
 * Same list as normalizeRaceName() in scripts/fetch-races.mjs, including
 * "javelina", which names a whole weekend of distinct races.
 *
 * @param string $name
 * @return string[]
 */
function arv_live_auto_words( $name ) {
	static $stop = array( 'the', 'trail', 'trails', 'run', 'runs', 'race', 'races', 'night', 'ultra', 'ultras', 'marathon', 'presented', 'by', 'hoka', 'javelina' );

	$words = preg_split( '/\s+/', strtolower( preg_replace( '/[^a-z0-9\s]/i', ' ', (string) $name ) ) );

	return array_values( array_unique( array_diff( array_filter( $words ), $stop ) ) );
}

/**
 * Share of identifying words two names have in common, 0 to 1.
 *
 * Port of nameOverlap() in scripts/fetch-races.mjs. One shared word only counts
 * when a whole name is that one word ("Cocodona" against "Cocodona 250").
 *
 * @param string $a
 * @param string $b
 * @return float
 */
function arv_live_auto_overlap( $a, $b ) {
	$wa = arv_live_auto_words( $a );
	$wb = arv_live_auto_words( $b );

	if ( ! $wa || ! $wb ) {
		return 0.0;
	}

	$hits = count( array_intersect( $wa, $wb ) );

	if ( 1 === $hits && min( count( $wa ), count( $wb ) ) > 1 ) {
		return 0.0;
	}

	return $hits / max( count( $wa ), count( $wb ) );
}

/**
 * The board event that is this race, if the board has it yet.
 *
 * @param array $race   Race store row.
 * @param array $events From arv_live_auto_board_events().
 * @return array|null
 */
function arv_live_auto_match( $race, $events ) {
	$best  = null;
	$score = 0.0;

	foreach ( $events as $event ) {
		if ( '' === $event['date'] ) {
			continue;
		}

		$days = abs( strtotime( $race['iso'] ) - strtotime( $event['date'] ) ) / DAY_IN_SECONDS;

		if ( $days > 1 ) {
			continue;
		}

		$s = arv_live_auto_overlap( $race['name'], $event['name'] );

		if ( $s >= 0.5 && $s > $score ) {
			$best  = $event;
			$score = $s;
		}
	}

	return $best;
}

/**
 * Whether a race is close enough to race day to need its page.
 *
 * @param array  $race
 * @param string $today Y-m-d.
 * @return bool
 */
function arv_live_auto_in_window( $race, $today ) {
	$last  = '' !== $race['end'] ? $race['end'] : $race['iso'];
	$open  = gmdate( 'Y-m-d', strtotime( $race['iso'] . ' -' . ARV_LIVE_AUTO_LEAD_DAYS . ' days' ) );
	$close = gmdate( 'Y-m-d', strtotime( $last . ' +' . ARV_LIVE_AUTO_TAIL_DAYS . ' days' ) );

	return $open <= $today && $today <= $close;
}

/**
 * The page template live results pages use, read off an existing one so a
 * theme change only has to be made on the pages that already exist.
 *
 * @return string
 */
function arv_live_auto_template() {
	foreach ( arv_live_pages() as $page ) {
		$template = get_post_meta( $page['id'], '_wp_page_template', true );

		if ( is_string( $template ) && '' !== $template ) {
			return $template;
		}
	}

	return 'template-blank-4.php';
}

/**
 * Create or update one live results page.
 *
 * @param int    $parent   /live-results/ page id.
 * @param string $slug     Page slug.
 * @param string $title    Page title.
 * @param string $board    Board slug.
 * @param string $year     Edition year for an archive page, '' for the current one.
 * @param int    $existing Page id to update instead of creating.
 * @return int|WP_Error
 */
function arv_live_auto_write_page( $parent, $slug, $title, $board, $year = '', $existing = 0 ) {
	$shortcode = '' === $year
		? sprintf( '[arv_live slug="%s"]', $board )
		: sprintf( '[arv_live slug="%s" year="%s"]', $board, $year );

	$post = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_parent'  => $parent,
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $shortcode,
		'meta_input'   => array(
			arv_live_meta_key()  => $board,
			'_wp_page_template' => arv_live_auto_template(),
		),
	);

	if ( $existing ) {
		$post['ID'] = $existing;

		return wp_update_post( $post, true );
	}

	return wp_insert_post( $post, true );
}

/**
 * Pages for earlier runnings of a race, where the board carried them.
 *
 * @param int    $parent  /live-results/ page id.
 * @param string $base    Current page slug, e.g. "mogollon-monster".
 * @param string $label   Name the year is appended to, e.g. "Mogollon Monster".
 * @param string $board   This year's board slug, skipped.
 * @param bool   $dry_run
 * @return string[] Log lines.
 */
function arv_live_auto_editions( $parent, $base, $label, $board, $dry_run ) {
	$log = array();

	foreach ( arv_live_all_editions( $board ) as $edition ) {
		$slug = arv_live_store_slug( isset( $edition['live'] ) ? $edition['live'] : '' );
		$year = substr( (string) $edition['iso'], 0, 4 );

		if ( '' === $slug || $slug === $board || ! preg_match( '/^\d{4}$/', $year ) ) {
			continue;
		}

		if ( '' !== arv_live_page_for_slug( $slug ) || get_page_by_path( ARV_LIVE_PARENT_PATH . '/' . $base . '-' . $year ) ) {
			continue;
		}

		if ( ! $dry_run ) {
			arv_live_auto_write_page( $parent, $base . '-' . $year, $label . ' ' . $year, $slug, $year );
		}

		$log[] = '  created /' . ARV_LIVE_PARENT_PATH . '/' . $base . '-' . $year . '/ for ' . $slug;
	}

	return $log;
}

/**
 * Repoint this year's raw results links on the race's own page.
 *
 * Buttons on race pages are placed by hand in Cornerstone, so they carry
 * whatever URL existed when someone added them: the bare board, or UltraSignup's
 * results for the running. Both exact URLs are replaced with the live page, in
 * the Cornerstone data and in the rendered content. Nothing else is touched, and
 * a board link that already names a distance (?raceId=) is left alone.
 *
 * @param array  $race
 * @param string $board
 * @param string $url     Live page permalink.
 * @param bool   $dry_run
 * @return string[] Log lines.
 */
function arv_live_auto_relink_race_page( $race, $board, $url, $dry_run ) {
	$post_id = '' !== $race['page'] ? url_to_postid( $race['page'] ) : 0;

	if ( ! $post_id ) {
		return array();
	}

	$targets = array( ARV_LIVE_BASE . $board );

	if ( preg_match( '/[?&]dtid=(\d+)/', (string) $race['register'], $m ) ) {
		$targets[] = 'https://ultrasignup.com/results_event.aspx?dtid=' . $m[1];
	}

	$patterns = array();

	foreach ( $targets as $target ) {
		foreach ( array( $target, str_replace( '/', '\\/', $target ) ) as $form ) {
			// Not followed by more URL: "...#/jangover_runs-2026?raceId=1" is a
			// distance link and stays, "...dtid=63189" must not match "...dtid=631890".
			$patterns[] = '~' . preg_quote( $form, '~' ) . '(?![\w?&=-])~';
		}
	}

	$count   = 0;
	$content = get_post_field( 'post_content', $post_id );
	$data    = get_post_meta( $post_id, '_cornerstone_data', true );

	$new_content = preg_replace( $patterns, $url, (string) $content, -1, $n1 );
	$new_data    = is_string( $data ) ? preg_replace( $patterns, str_replace( '/', '\/', $url ), $data, -1, $n2 ) : $data;
	$count       = (int) $n1 + ( isset( $n2 ) ? (int) $n2 : 0 );

	if ( ! $count ) {
		return array();
	}

	if ( ! $dry_run ) {
		// Data first: Cornerstone rebuilds content from its data on save, so
		// updating content first could be overwritten from the old data.
		if ( ! empty( $n2 ) ) {
			update_post_meta( $post_id, '_cornerstone_data', wp_slash( $new_data ) );
		}

		if ( $n1 ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );
		}
	}

	return array( '  repointed ' . $count . ' results link(s) on ' . $race['page'] );
}

/**
 * Bring every race in its race week up to date.
 *
 * @param bool        $dry_run Report what would change, change nothing.
 * @param string|null $today   Y-m-d, for testing. Defaults to the site's today.
 * @return string[] One line per race looked at.
 */
function arv_live_auto_run( $dry_run = false, $today = null ) {
	$today  = $today ? $today : arv_upcoming_races_today();
	$parent = get_page_by_path( ARV_LIVE_PARENT_PATH );
	$log    = array();

	if ( ! $parent ) {
		return array( 'no /' . ARV_LIVE_PARENT_PATH . '/ page, nothing to do' );
	}

	$events  = null;
	$changed = false;

	$posts = get_posts(
		array(
			'post_type'      => ARV_RACE_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post ) {
		$race = arv_race_store_to_race( $post );

		if ( ! $race || ! arv_live_auto_in_window( $race, $today ) ) {
			continue;
		}

		$live  = trim( (string) $race['live'] );
		$board = arv_live_store_slug( $live );
		$name  = '';

		// Read lazily: most days no race is in the window and there is no
		// reason to call the board at all.
		if ( null === $events ) {
			$events = arv_live_auto_board_events();
			$events = null === $events ? array() : $events;
		}

		foreach ( $events as $event ) {
			if ( $event['slug'] === $board ) {
				$name = $event['name'];
			}
		}

		if ( '' === $board ) {
			$match = arv_live_auto_match( $race, $events );

			if ( ! $match ) {
				$log[] = $race['name'] . ': not on the live board yet';
				continue;
			}

			$board = $match['slug'];
			$name  = $match['name'];
			$live  = ARV_LIVE_BASE . $board;

			if ( ! $dry_run ) {
				update_post_meta( $post->ID, '_arv_live', $live );
				$changed = true;
			}

			$log[] = $race['name'] . ': live URL set to ' . $live;
		}

		$existing_url = arv_live_page_for_slug( $board );

		if ( '' !== $existing_url ) {
			$log[] = $race['name'] . ': page already exists for ' . $board;
			$base  = basename( untrailingslashit( wp_parse_url( $existing_url, PHP_URL_PATH ) ) );
			$log   = array_merge(
				$log,
				arv_live_auto_editions( $parent->ID, $base, '' !== $name ? $name : $race['name'], $board, $dry_run ),
				arv_live_auto_relink_race_page( $race, $board, $existing_url, $dry_run )
			);
			arv_live_page_map( true );
			continue;
		}

		// The board's own name makes the address ("Bryce Canyon", not
		// "Bryce Canyon Ultras"), which is how every page made by hand before
		// this was named. The race's name is the title people read.
		$slug  = sanitize_title( '' !== $name ? $name : $race['name'] );
		$page  = get_page_by_path( ARV_LIVE_PARENT_PATH . '/' . $slug );
		$title = $race['name'];

		if ( $page ) {
			$old      = trim( (string) get_post_meta( $page->ID, arv_live_meta_key(), true ) );
			$old_year = arv_live_year_from_slug( $old );

			if ( '' === $old || '' === $old_year ) {
				$log[] = $race['name'] . ': /' . ARV_LIVE_PARENT_PATH . '/' . $slug . '/ exists but is not a dated live page, left alone';
				continue;
			}

			$archive_slug = $slug . '-' . $old_year;

			if ( ! $dry_run && ! get_page_by_path( ARV_LIVE_PARENT_PATH . '/' . $archive_slug ) ) {
				arv_live_auto_write_page( $parent->ID, $archive_slug, preg_replace( '/\s+\d{4}$/', '', $race['name'] ) . ' ' . $old_year, $old, $old_year );
			}

			if ( ! $dry_run ) {
				arv_live_auto_write_page( $parent->ID, $slug, $title, $board, '', $page->ID );
				$changed = true;
			}

			$log[] = $race['name'] . ': rolled /' . $slug . '/ to ' . $board . ', ' . $old . ' kept at /' . $archive_slug . '/';
			arv_live_page_map( true );
			continue;
		}

		if ( ! $dry_run ) {
			$result = arv_live_auto_write_page( $parent->ID, $slug, $title, $board );

			if ( is_wp_error( $result ) ) {
				$log[] = $race['name'] . ': page create failed, ' . $result->get_error_message();
				continue;
			}

			$changed = true;
		}

		$log[] = $race['name'] . ': created /' . ARV_LIVE_PARENT_PATH . '/' . $slug . '/ for ' . $board;

		if ( ! $dry_run ) {
			arv_live_page_map( true );
			$url = arv_live_page_for_slug( $board );
			$log = array_merge(
				$log,
				arv_live_auto_editions( $parent->ID, $slug, '' !== $name ? $name : $race['name'], $board, $dry_run ),
				'' !== $url ? arv_live_auto_relink_race_page( $race, $board, $url, $dry_run ) : array()
			);
		}
	}

	if ( $changed && function_exists( 'arv_race_store_flush_cache' ) ) {
		arv_race_store_flush_cache();
	}

	if ( ! $log ) {
		$log[] = 'no races in their race week window';
	}

	return $log;
}

function arv_live_auto_cron_schedule() {
	if ( ! wp_next_scheduled( 'arv_live_auto_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'arv_live_auto_cron' );
	}
}
add_action( 'init', 'arv_live_auto_cron_schedule' );
add_action(
	'arv_live_auto_cron',
	function () {
		arv_live_auto_run();
	}
);

function arv_live_auto_deactivate() {
	wp_clear_scheduled_hook( 'arv_live_auto_cron' );
}
register_deactivation_hook( ARV_ELEMENTS_PATH . 'aravaipa-elements.php', 'arv_live_auto_deactivate' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Create race week live results pages.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without changing it.
	 *
	 * [--today=<date>]
	 * : Pretend today is this Y-m-d date.
	 */
	WP_CLI::add_command(
		'arv live-pages',
		function ( $args, $assoc ) {
			$lines = arv_live_auto_run( isset( $assoc['dry-run'] ), isset( $assoc['today'] ) ? $assoc['today'] : null );

			foreach ( $lines as $line ) {
				WP_CLI::log( $line );
			}
		}
	);
}
