<?php
/**
 * A meta description for every race page, composed from the race store.
 *
 * includes/page-seo.php added a written-description field and deliberately
 * emits nothing without one, on the reasoning that a templated string across
 * every page reads as filler. That reasoning is right for a page whose only
 * distinguishing fact is its title. It is wrong for a race, which already
 * carries the four things a searcher actually wants before they click: what
 * it is called, when it runs, where, and how far.
 *
 * The audit that prompted this found 40 of 40 sampled race pages with no
 * description at all, and Jetpack's "Visit the post for more." standing in as
 * the og:description on every one of them. That is the text that appears when
 * somebody shares a race in a group chat.
 *
 * So this generates, but only from stored facts, and only for pages the race
 * store recognises. Nothing here invents a claim: every clause is dropped
 * when the field behind it is empty, and a hand-written description always
 * wins.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google truncates a snippet somewhere near this, and a description that is
 * cut mid-word reads worse than a shorter one that is not.
 */
const ARV_RACE_SEO_MAX = 158;

/**
 * The race the current request is a page for, or null.
 *
 * Wraps the store's own lookup so the guards live in one place rather than
 * being repeated by the description, the Open Graph filter and any later
 * caller.
 *
 * @return array|null
 */
function arv_race_seo_race() {
	if ( ! is_page() || is_front_page() ) {
		return null;
	}

	if ( ! function_exists( 'arv_race_store_find_by_page' ) ) {
		return null;
	}

	return arv_race_store_find_by_page();
}

/**
 * The written description for this page, if an editor set one.
 *
 * A generated description is a floor, not a ceiling: the moment somebody
 * writes something better, that wins and this file goes quiet for that page.
 *
 * @return string
 */
function arv_race_seo_written() {
	if ( ! defined( 'ARV_PAGE_SEO_META' ) ) {
		return '';
	}

	$id = get_queried_object_id();

	return $id ? trim( (string) get_post_meta( $id, ARV_PAGE_SEO_META, true ) ) : '';
}

/**
 * "50K, 25K, 10K and 5K" from the store's "50K | 25K | 10K | 5K".
 *
 * Reuses arv_split_distances() rather than splitting here, because the store
 * holds two delimiters and has since the beginning: see that function.
 *
 * Long lists are cut rather than run to nine items, since the point of the
 * clause is to signal range, not to enumerate. A race with more distances
 * than fit says so instead of listing them.
 *
 * @param string $raw   Raw distances field.
 * @param int    $limit Most distances to name.
 * @return string Empty when there are none.
 */
function arv_race_seo_distances( $raw, $limit = 4 ) {
	if ( ! function_exists( 'arv_split_distances' ) ) {
		return '';
	}

	$all = arv_split_distances( (string) $raw );

	if ( empty( $all ) ) {
		return '';
	}

	$shown = array_slice( $all, 0, $limit );

	if ( count( $all ) > count( $shown ) ) {
		// Deliberately "and more" rather than "and 3 more": the caller
		// appends a noun ("trail races"), and a count landing in front of it
		// reads as broken agreement, "10 KM and 1 more trail races".
		/* translators: %s is a list of distances. */
		return sprintf( __( '%s and more', 'aravaipa-elements' ), implode( ', ', $shown ) );
	}

	if ( 1 === count( $shown ) ) {
		return $shown[0];
	}

	$last = array_pop( $shown );

	/* translators: 1: all distances but the last, 2: the last distance. */
	return sprintf( __( '%1$s and %2$s', 'aravaipa-elements' ), implode( ', ', $shown ), $last );
}

/**
 * "September 12-13, 2026" from the store's iso and optional end date.
 *
 * Built off iso rather than the store's own display string, which is written
 * for a race card and carries a weekday ("Saturday - September 5") that reads
 * oddly mid-sentence and no year at all. A description outlives the season it
 * was written in, so the year is the part that cannot be dropped.
 *
 * @param array $race
 * @return string Empty when the date is unusable.
 */
function arv_race_seo_date( $race ) {
	$iso = isset( $race['iso'] ) ? (string) $race['iso'] : '';

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $iso ) ) {
		return '';
	}

	$start = strtotime( $iso . ' 00:00:00 UTC' );

	if ( false === $start ) {
		return '';
	}

	$end_iso = isset( $race['end'] ) ? (string) $race['end'] : '';

	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_iso ) && $end_iso > $iso ) {
		$end = strtotime( $end_iso . ' 00:00:00 UTC' );

		if ( false !== $end ) {
			// Same month reads as "September 12-13, 2026"; a race that runs
			// across a month boundary has to name both.
			if ( gmdate( 'Y-m', $start ) === gmdate( 'Y-m', $end ) ) {
				return gmdate( 'F j', $start ) . '-' . gmdate( 'j, Y', $end );
			}

			return gmdate( 'F j', $start ) . ' to ' . gmdate( 'F j, Y', $end );
		}
	}

	return gmdate( 'F j, Y', $start );
}

/**
 * "Pine, Arizona" from the store's "Pine, AZ".
 *
 * The state is spelled out because this is prose a person reads in a search
 * result, not a filter value: "Castle Rock, CO" is how the card says it and
 * "Castle Rock, Colorado" is how a sentence does.
 *
 * @param array $race
 * @return string
 */
function arv_race_seo_place( $race ) {
	$location = isset( $race['location'] ) ? trim( (string) $race['location'] ) : '';

	if ( '' === $location ) {
		return '';
	}

	if ( preg_match( '/^(.*),\s*([A-Za-z]{2})$/', $location, $m ) && function_exists( 'arv_state_name' ) ) {
		$state = arv_state_name( $m[2] );

		if ( '' !== $state && strtoupper( $m[2] ) !== $state ) {
			return trim( $m[1] ) . ', ' . $state;
		}
	}

	return $location;
}

/**
 * The closing clause, which depends on whether the race has run.
 *
 * A page for a race three weeks out and a page for one three years past are
 * the same page, and the description is the only part that can say which. The
 * phase comes from arv_upcoming_races_action(), the same call the page's own
 * button reads, so the description cannot promise registration on a page
 * whose button says Results.
 *
 * @param array $race
 * @return string
 */
function arv_race_seo_closing( $race ) {
	if ( ! function_exists( 'arv_upcoming_races_action' ) || ! function_exists( 'arv_upcoming_races_today' ) ) {
		return __( 'Course details, schedule and results.', 'aravaipa-elements' );
	}

	// arv_upcoming_races_action() reads keys the store always sets but a
	// caller passing a partial record might not, and this runs on wp_head:
	// a missing key there is a PHP notice printed into the document head.
	$race = array_merge(
		array(
			'iso'      => '',
			'end'      => '',
			'closes'   => '',
			'live'     => '',
			'register' => '',
		),
		$race
	);

	if ( '' === $race['iso'] ) {
		return __( 'Course details, schedule and results.', 'aravaipa-elements' );
	}

	$action = arv_upcoming_races_action( $race, arv_upcoming_races_today() );
	$phase  = isset( $action['phase'] ) ? $action['phase'] : '';

	if ( 'upcoming' === $phase ) {
		return __( 'Registration, course details and aid stations.', 'aravaipa-elements' );
	}

	if ( 'waitlist' === $phase || 'closed' === $phase ) {
		return __( 'Course details, schedule and live results.', 'aravaipa-elements' );
	}

	if ( 'live' === $phase ) {
		return __( 'Live results, course details and schedule.', 'aravaipa-elements' );
	}

	return __( 'Results, course details and photos.', 'aravaipa-elements' );
}

/**
 * The description for one race.
 *
 * Assembled clause by clause so a race missing a field loses that clause
 * rather than emitting an empty fragment, which is the usual way generated
 * copy embarrasses itself ("... in . 50K and 25K.").
 *
 * @param array $race
 * @return string Empty when there is not enough to say.
 */
function arv_race_seo_compose( $race ) {
	$name = isset( $race['name'] ) ? trim( (string) $race['name'] ) : '';

	if ( '' === $name ) {
		return '';
	}

	$date  = arv_race_seo_date( $race );
	$place = arv_race_seo_place( $race );

	// First sentence: what, when, where. Each piece optional.
	$opening = $name;

	if ( '' !== $date ) {
		$opening .= ', ' . $date;
	}

	if ( '' !== $place ) {
		/* translators: %s is a place like "Pine, Arizona". */
		$opening .= ' ' . sprintf( __( 'in %s', 'aravaipa-elements' ), $place );
	}

	$sentences = array( rtrim( $opening, '.' ) . '.' );

	// Second sentence: how far, and what kind of running it is.
	$distances = arv_race_seo_distances( isset( $race['distances'] ) ? $race['distances'] : '' );

	if ( '' !== $distances ) {
		$terrain = ( isset( $race['terrain'] ) && 'road' === $race['terrain'] )
			? __( 'road races', 'aravaipa-elements' )
			: __( 'trail races', 'aravaipa-elements' );

		/* translators: 1: a list of distances, 2: "trail races" or "road races". */
		$sentences[] = sprintf( __( '%1$s %2$s.', 'aravaipa-elements' ), $distances, $terrain );
	}

	$sentences[] = arv_race_seo_closing( $race );

	$description = trim( implode( ' ', array_filter( $sentences ) ) );

	return arv_race_seo_trim( $description );
}

/**
 * Cut to length on a sentence, then on a word, never mid-word.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function arv_race_seo_trim( $text, $max = ARV_RACE_SEO_MAX ) {
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

	if ( '' === $text || strlen( $text ) <= $max ) {
		return $text;
	}

	// Prefer dropping whole trailing sentences: the closing clause is the
	// least load-bearing part, so a long race loses that before it loses
	// its distances.
	$parts = preg_split( '/(?<=\.)\s+/', $text );

	if ( is_array( $parts ) && count( $parts ) > 1 ) {
		$kept = '';

		foreach ( $parts as $part ) {
			$candidate = '' === $kept ? $part : $kept . ' ' . $part;

			if ( strlen( $candidate ) > $max ) {
				break;
			}

			$kept = $candidate;
		}

		if ( '' !== $kept ) {
			return $kept;
		}
	}

	$cut   = substr( $text, 0, $max );
	$space = strrpos( $cut, ' ' );

	if ( false !== $space ) {
		$cut = substr( $cut, 0, $space );
	}

	return rtrim( $cut, " ,.;:" ) . '…';
}

/**
 * The description this request should carry, written or generated.
 *
 * @return string
 */
function arv_race_seo_description() {
	if ( function_exists( 'arv_seo_handled_elsewhere' ) && arv_seo_handled_elsewhere() ) {
		return '';
	}

	// A written one is already printed by includes/page-seo.php. Returning
	// it here too would put two descriptions on the page, which is the exact
	// problem that file's own claimed-elsewhere check exists to avoid.
	if ( '' !== arv_race_seo_written() ) {
		return '';
	}

	$race = arv_race_seo_race();

	return null === $race ? '' : arv_race_seo_compose( $race );
}

/**
 * Print it.
 *
 * Priority 1 alongside the other description writers, all of which bail when
 * another has claimed the page.
 */
function arv_race_seo_head() {
	$description = arv_race_seo_description();

	if ( '' === $description ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'arv_race_seo_head', 1 );

/**
 * Point Jetpack's og:description and twitter:description at the same text.
 *
 * Filtered rather than printed, the same way includes/page-seo.php does it:
 * Jetpack already writes the Open Graph tags for these pages and a second
 * og:description would leave a scraper to pick. This is the tag that
 * currently reads "Visit the post for more." on every race the site has.
 *
 * @param array $tags
 * @return array
 */
function arv_race_seo_open_graph( $tags ) {
	$description = arv_race_seo_description();

	if ( '' === $description ) {
		return $tags;
	}

	$tags['og:description']      = $description;
	$tags['twitter:description'] = $description;

	return $tags;
}
add_filter( 'jetpack_open_graph_tags', 'arv_race_seo_open_graph' );
