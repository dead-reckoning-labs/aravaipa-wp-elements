<?php
/**
 * The race store.
 *
 * One race, one record, in WordPress. Everything else reads from here.
 *
 * Before this, the same 69 races existed in five places: a flat file in the
 * repo, baked into two element files as PHP string defaults, and a fourth and
 * fifth copy saved into postmeta for every element instance placed on a page.
 * Changing one date meant regenerating, rebuilding the plugin, cutting a
 * release, and re-adding elements to pick up new defaults. That is absurd for
 * a date change, and it is what this replaces.
 *
 * Deliberately a custom post type rather than an external database. The data
 * is small (tens of rows), it is read almost exclusively by WordPress, and
 * WordPress already has querying, caching, an editing UI, revisions and
 * permissions for exactly this shape of thing. An external database would add
 * a service, a bill, a credential and a sync problem while solving nothing
 * that wp_posts does not already solve. If something outside WordPress ever
 * needs this data, that is the moment to revisit, and it will most likely be
 * RaceGoat, which would become the upstream source rather than another copy.
 *
 * The post type is not public. Races already have real pages on
 * aravaiparunning.com (/blackcanyon/, /cocodona/ and so on) built in
 * Cornerstone, and giving each race a second, competing URL would split the
 * SEO value of the first. Each record links out to the page that already
 * exists instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_RACE_POST_TYPE', 'arv_race' );
define( 'ARV_RACE_TAXONOMY', 'arv_region' );

/**
 * Register the post type and its region taxonomy.
 */
function arv_race_store_register() {
	register_post_type(
		ARV_RACE_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Races', 'aravaipa-elements' ),
				'singular_name' => __( 'Race', 'aravaipa-elements' ),
				'edit_item'     => __( 'Edit Race', 'aravaipa-elements' ),
				'search_items'  => __( 'Search Races', 'aravaipa-elements' ),
			),
			// Not public, and not rewritten: see the file header. These
			// records feed the pages that already exist rather than becoming
			// a second set of pages competing with them.
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title' ),
			'has_archive'  => false,
			'rewrite'      => false,
			'taxonomies'   => array( ARV_RACE_TAXONOMY ),
		)
	);

	register_taxonomy(
		ARV_RACE_TAXONOMY,
		ARV_RACE_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Regions', 'aravaipa-elements' ),
				'singular_name' => __( 'Region', 'aravaipa-elements' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => true,
			'rewrite'      => false,
		)
	);
}
add_action( 'init', 'arv_race_store_register' );

/**
 * The meta keys a race carries, mapped to the array keys the elements
 * already use.
 *
 * Kept identical to the shape arv_upcoming_races_parse_row() returns, on
 * purpose: every render path, phase calculation and schema builder written
 * before this store existed keeps working with no changes at all. The store
 * swaps out where a race comes from, not what a race is.
 *
 * @return array meta key => race array key
 */
function arv_race_store_fields() {
	return array(
		'_arv_iso'       => 'iso',
		'_arv_display'   => 'display',
		'_arv_distances' => 'distances',
		'_arv_venue'     => 'venue',
		'_arv_location'  => 'location',
		// Coordinates travel with the race like every other field. Leaving
		// them out is what put all 84 pins on the map at 0,0: the store
		// simply never returned a 'lat' or 'lng' key, the race map read a
		// missing key as null, cast it to 0.0, and drew the entire season
		// in the Atlantic off the coast of Africa.
		'_arv_lat'       => 'lat',
		'_arv_lng'       => 'lng',
		'_arv_register'  => 'register',
		'_arv_page'      => 'page',
		'_arv_image'     => 'image',
		'_arv_end'       => 'end',
		'_arv_live'      => 'live',
		'_arv_closes'    => 'closes',
		'_arv_confirmed' => 'confirmed',
		'_arv_guessed'   => 'guessed',
	);
}

/**
 * Register the meta so it is readable through the REST API and editable in
 * the admin, rather than being invisible postmeta only this plugin knows
 * about.
 */
function arv_race_store_register_meta() {
	foreach ( arv_race_store_fields() as $meta_key => $race_key ) {
		register_post_meta(
			ARV_RACE_POST_TYPE,
			$meta_key,
			array(
				'type'         => in_array( $race_key, array( 'confirmed', 'guessed' ), true ) ? 'boolean' : 'string',
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'arv_race_store_register_meta' );

/**
 * True when the store has anything in it.
 *
 * Every element checks this before falling back to its own pasted or bundled
 * rows, so installing this version changes nothing until races are actually
 * imported. A half-migrated site keeps rendering what it rendered yesterday.
 *
 * @return bool
 */
function arv_race_store_has_races() {
	$found = get_posts(
		array(
			'post_type'      => ARV_RACE_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	return ! empty( $found );
}

/**
 * Read races out of the store, in the same array shape the elements expect.
 *
 * @param array $args {
 *     @type string $region Region slug, for a division page. Empty for all.
 *     @type int    $limit  Maximum to return. 0 for everything.
 * }
 * @return array<int, array> Race arrays, unsorted: callers already sort by
 *                           the rule they care about (soonest first on the
 *                           homepage, days-until-next on the calendar).
 */
function arv_race_store_get( $args = array() ) {
	$args = array_merge(
		array(
			'region' => '',
			'limit'  => 0,
		),
		$args
	);

	$query = array(
		'post_type'      => ARV_RACE_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => $args['limit'] > 0 ? (int) $args['limit'] : -1,
		'no_found_rows'  => true,
		// Ordered by the race date rather than post date, so a caller that
		// does not sort still gets something sensible.
		'meta_key'       => '_arv_iso', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	);

	if ( '' !== $args['region'] ) {
		$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => ARV_RACE_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $args['region'],
			),
		);
	}

	$posts = get_posts( $query );
	$races = array();

	foreach ( $posts as $post ) {
		$race = arv_race_store_to_race( $post );
		if ( null !== $race ) {
			$races[] = $race;
		}
	}

	return $races;
}

/**
 * Turn one stored post into the race array the elements use.
 *
 * @param WP_Post $post
 * @return array|null Null when the record has no usable date, matching how
 *                    arv_upcoming_races_parse_row() drops an unusable row
 *                    rather than rendering something broken.
 */
function arv_race_store_to_race( $post ) {
	$race = array( 'name' => $post->post_title );

	foreach ( arv_race_store_fields() as $meta_key => $race_key ) {
		$value = get_post_meta( $post->ID, $meta_key, true );

		if ( 'confirmed' === $race_key || 'guessed' === $race_key ) {
			$race[ $race_key ] = ( '1' === (string) $value || true === $value );
			continue;
		}

		$race[ $race_key ] = is_string( $value ) ? $value : '';
	}

	if ( '' === trim( $race['name'] ) || '' === arv_upcoming_races_date( $race['iso'] ) ) {
		return null;
	}

	// Normalised the same way a pasted row is, so a hand-edited record with a
	// malformed end date behaves like a hand-typed row with one.
	$race['end']    = arv_upcoming_races_date( $race['end'] );
	$race['closes'] = arv_upcoming_races_date( $race['closes'] );

	return $race;
}

/**
 * Import races from the pipe-separated format into the store.
 *
 * Upserts on the registration URL where there is one, falling back to the
 * race name. The registration URL carries UltraSignup's "did", which is the
 * closest thing to a stable identifier these races have; names get
 * reworded ("Black Bear" and "Black Bear Trail Race" are the same race in
 * two of our own sources) and a name-only match would create duplicates
 * every time marketing renames something.
 *
 * @param string $raw   Pipe-separated rows, the same format the elements take.
 * @param bool   $prune Remove stored races the import did not mention.
 * @return array {imported, updated, created, skipped, pruned}
 */
function arv_race_store_import( $raw, $prune = false ) {
	$rows   = arv_parse_rows( $raw, 2 );
	$result = array(
		'imported' => 0,
		'created'  => 0,
		'updated'  => 0,
		'skipped'  => 0,
		'pruned'   => 0,
	);

	$seen = array();

	foreach ( $rows as $row ) {
		$race = arv_upcoming_races_parse_row( $row );

		if ( null === $race ) {
			$result['skipped']++;
			continue;
		}

		$key      = arv_race_store_key( $race );
		$existing = arv_race_store_find( $key );

		$postarr = array(
			'post_type'   => ARV_RACE_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $race['name'],
		);

		if ( $existing ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr );
			$result['updated']++;
		} else {
			$post_id = wp_insert_post( $postarr );
			$result['created']++;
		}

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			$result['skipped']++;
			continue;
		}

		$seen[] = $post_id;

		foreach ( arv_race_store_fields() as $meta_key => $race_key ) {
			$value = $race[ $race_key ];
			if ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		// The import key, so the next run can find this record again even
		// after someone renames the race in the admin.
		update_post_meta( $post_id, '_arv_key', $key );

		$region = arv_race_store_region_for( $race );
		if ( '' !== $region ) {
			wp_set_object_terms( $post_id, $region, ARV_RACE_TAXONOMY, false );
		}

		$result['imported']++;
	}

	if ( $prune ) {
		$all = get_posts(
			array(
				'post_type'      => ARV_RACE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( array_diff( $all, $seen ) as $orphan ) {
			// Trashed rather than deleted. A bad import that drops half the
			// calendar should be recoverable from the admin, not gone.
			wp_trash_post( $orphan );
			$result['pruned']++;
		}
	}

	return $result;
}

/**
 * The stable identifier for a race.
 *
 * The registration URL first, because it carries UltraSignup's "did" and
 * survives the renaming that names do not: "Black Bear" and "Black Bear Trail
 * Race" are the same race in two of our own sources.
 *
 * Qualified with the race name rather than used alone, though. Two pairs of
 * unrelated races on the live site currently share a registration link
 * (Vegas Golden Night & Day points at Elephant Mountain's, Zion Ultras at Dam
 * Good Run's, both verified against UltraSignup's own listing). Keying on the
 * URL alone silently collapses each pair into one record and loses a race.
 * The name disambiguates without giving up the "did"'s stability, since a
 * rename only ever affects the race that was renamed rather than merging it
 * into another.
 *
 * @param array $race
 * @return string
 */
function arv_race_store_key( $race ) {
	if ( '' === $race['register'] ) {
		return 'name:' . $race['name'];
	}

	return $race['register'] . '#' . $race['name'];
}

/**
 * Find a stored race by its import key.
 *
 * @param string $key
 * @return int|null Post ID.
 */
function arv_race_store_find( $key ) {
	$found = get_posts(
		array(
			'post_type'      => ARV_RACE_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_arv_key',
					'value' => $key,
				),
			),
		)
	);

	return ! empty( $found ) ? (int) $found[0] : null;
}

/**
 * Work out which region a race belongs to, for division pages.
 *
 * Read off the race's own page URL first, because that is the site's own
 * answer: /white-mountain-endurance/black-bear-trail-races/ is unambiguous in
 * a way that "Waterville Valley, NH" is not, and it survives a race moving
 * venue. Falls back to the state in the location string.
 *
 * @param array $race
 * @return string Region slug, or '' when nothing can be determined.
 */
function arv_race_store_region_for( $race ) {
	$by_path = array(
		'white-mountain-endurance' => 'white-mountain-endurance',
		'great-lakes-endurance'    => 'great-lakes-endurance',
		'ultra-adventures'         => 'ultra-adventures',
		'bad-beard'                => 'bad-beard',
		'bear-chase-series'        => 'colorado',
		'insomniac'                => 'arizona',
		'drt-series'               => 'arizona',
	);

	if ( '' !== $race['page'] ) {
		$path = wp_parse_url( $race['page'], PHP_URL_PATH );
		foreach ( $by_path as $needle => $slug ) {
			if ( false !== strpos( (string) $path, '/' . $needle ) ) {
				return $slug;
			}
		}
	}

	$by_state = array(
		'AZ' => 'arizona',
		'CA' => 'california',
		'CO' => 'colorado',
		'NV' => 'nevada',
		'UT' => 'ultra-adventures',
		'NH' => 'white-mountain-endurance',
		'MI' => 'great-lakes-endurance',
		'TN' => 'bad-beard',
	);

	if ( preg_match( '/,\s*([A-Z]{2})\s*$/', $race['location'], $m ) && isset( $by_state[ $m[1] ] ) ) {
		return $by_state[ $m[1] ];
	}

	return '';
}

/**
 * One race, looked up for a single race page.
 *
 * Matched on the page URL rather than a name, so dropping a status element
 * onto /blackcanyon/ finds the right record without anyone typing anything
 * and without breaking when the race is renamed.
 *
 * @param string $page_url Defaults to the current page.
 * @return array|null
 */
function arv_race_store_find_by_page( $page_url = '' ) {
	if ( '' === $page_url ) {
		$page_url = home_url( add_query_arg( array() ) );
	}

	$path = trim( (string) wp_parse_url( $page_url, PHP_URL_PATH ), '/' );

	if ( '' === $path ) {
		return null;
	}

	foreach ( arv_race_store_get() as $race ) {
		$race_path = trim( (string) wp_parse_url( $race['page'], PHP_URL_PATH ), '/' );
		if ( '' !== $race_path && $race_path === $path ) {
			return $race;
		}
	}

	return null;
}
