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
/**
 * The per-request memo behind arv_race_store_get().
 *
 * A holder function rather than a static inside the getter, purely so
 * arv_race_store_flush_cache() can empty it: a static local belongs to the
 * function that declares it and nothing else can reach it.
 *
 * Not a transient and not persistent, this only has to survive one page
 * build. The homepage alone asks for the same list from four places
 * (upcoming races, the race map, the featured race, the region map) and each
 * was a fresh get_posts plus sixteen get_post_meta calls per race.
 *
 * @return array Reference to the cache, keyed by the getter's arguments.
 */
function &arv_race_store_cache() {
	static $cache = array();
	return $cache;
}

function arv_race_store_get( $args = array() ) {
	$args = array_merge(
		array(
			'region' => '',
			'limit'  => 0,
		),
		$args
	);

	$cache =& arv_race_store_cache();
	$key   = md5( (string) wp_json_encode( $args ) );

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

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

	$cache[ $key ] = $races;

	return $races;
}

/**
 * Drop the per-request memo above.
 *
 * The importer writes every race and then, in the same request, reads them
 * back to report what changed. Without this it would report against the list
 * as it stood before the write.
 *
 * Hooked to the post lifecycle rather than called from the importer, so an
 * edit made from the Races admin screen, or a race trashed by hand, clears
 * it too.
 */
function arv_race_store_flush_cache() {
	$cache =& arv_race_store_cache();
	$cache = array();
}
add_action( 'save_post_' . ARV_RACE_POST_TYPE, 'arv_race_store_flush_cache' );
add_action( 'deleted_post', 'arv_race_store_flush_cache' );
add_action( 'trashed_post', 'arv_race_store_flush_cache' );

/**
 * Turn one stored post into the race array the elements use.
 *
 * @param WP_Post $post
 * @return array|null Null when the record has no usable date, matching how
 *                    arv_upcoming_races_parse_row() drops an unusable row
 *                    rather than rendering something broken.
 */
function arv_race_store_to_race( $post ) {
	// Corrected on the way out, so every consumer of the store gets the name
	// the race is actually called rather than whatever the calendar page it
	// was scraped from happened to say. See arv_race_display_name().
	$race = array( 'name' => arv_race_display_name( $post->post_title ) );

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

	// Not one of arv_race_store_fields(): terrain is a hand-curated,
	// rarely-changing call, not something the scraper knows or should be
	// able to overwrite on its next import. See arv_race_terrain().
	$race['terrain'] = arv_race_terrain( $race['name'] );

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
	// Explicit, not left to the save_post hook. wp_insert_post fires that
	// hook in WordPress, but this function both writes and then reads back
	// to report what changed, so correctness here should not depend on a
	// hook having run: flushed on the way in, and again on the way out
	// below, so nothing downstream in this request sees the pre-import list.
	arv_race_store_flush_cache();

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

	// Everything above wrote; anything reading the store from here on, the
	// prune query included, has to see the new state.
	arv_race_store_flush_cache();

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
			arv_race_store_flush_cache();
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

/**
 * REST import: the same importer the admin screen uses, reachable without
 * an admin login.
 *
 * The admin screen at Races -> Import is gated on manage_options
 * deliberately (it can prune every race in the store), and that gate should
 * stay. But that also means the only way to automate an import was to hand
 * out full Administrator credentials to whatever runs the cron. This gives
 * the generator (scripts/fetch-races.mjs) a door of its own, scoped to
 * edit_posts, so the automation account never needs to be an admin.
 *
 * Split into a pure core function and a thin REST wrapper on purpose: the
 * core takes and returns plain values, so arv-store-test.php can exercise
 * the guardrail logic directly without constructing a WP_REST_Request.
 *
 * arv_upcoming_races_parse_row(), which arv_race_store_import() calls,
 * lives in includes/elements/upcoming-races.php. That file is normally
 * only loaded by arv_elements_register(), hooked to Cornerstone's
 * cs_register_elements, a hook this plugin's own main file already
 * documents as "not guaranteed to run" before other standard WordPress
 * hooks (see the countdown.php script enqueue in aravaipa-elements.php
 * hitting the same problem with wp_enqueue_scripts). A bare REST request
 * never fires a Cornerstone hook at all, so without this the import route
 * would call an undefined function on its very first request.
 *
 * Required here, on rest_api_init, rather than unconditionally at the top
 * of this file: upcoming-races.php calls cs_register_element() at its own
 * top level, and requiring it before Cornerstone itself has loaded would
 * fatal the entire site on every request, not just REST ones. rest_api_init
 * fires well after plugins_loaded, so every active plugin (Cornerstone
 * included, if it is even active) is already loaded by the time this runs,
 * which is the same function_exists( 'cs_register_element' ) guarantee
 * arv_elements_register() already relies on elsewhere in this plugin.
 */
function arv_race_store_register_rest_route() {
	if ( function_exists( 'cs_register_element' ) && ! function_exists( 'arv_upcoming_races_parse_row' ) ) {
		require_once ARV_ELEMENTS_PATH . 'includes/elements/upcoming-races.php';
	}

	register_rest_route(
		'aravaipa/v1',
		'/races/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_race_store_rest_import',
			// edit_posts, not manage_options: this is the whole point. An
			// Editor-scoped Application Password can reach this route and
			// nothing else an admin could do.
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'rows'    => array(
					'required' => true,
					'type'     => 'string',
				),
				'prune'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'dry_run' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'force'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'arv_race_store_register_rest_route' );

/**
 * Trash stored races by name.
 *
 * The import cannot do this. Its key is the register URL and the name
 * together, so renaming a race in a row does not rename the record: it
 * writes a second one and leaves the first behind, live on the site. That is
 * exactly how the home page came to carry both "Oli Kai" and "Oli Kai Trail
 * Races" on the same September Saturday, and the only way out was for
 * someone to open wp-admin and trash one by hand.
 *
 * Trash rather than delete, and the same edit_posts scope as the import,
 * because this is the destructive half of the same job and a mistake here
 * should be one click to undo rather than gone. Prune already works this way
 * for the same reason.
 *
 * @param string[] $names Race names, matched against the record title.
 * @param bool     $dry_run Report what would go, change nothing.
 * @return array {trashed, missing, names}
 */
function arv_race_store_remove( $names, $dry_run = false ) {
	$result = array(
		'trashed' => 0,
		'missing' => array(),
		'matched' => array(),
		'dry_run' => (bool) $dry_run,
	);

	foreach ( $names as $name ) {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			continue;
		}

		$found = get_posts(
			array(
				'post_type'      => ARV_RACE_POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// An exact title match, not a search: "Oli Kai" must not
				// take "Oli Kai Trail Races" with it, which is the whole
				// reason this exists.
				'title'          => $name,
			)
		);

		if ( empty( $found ) ) {
			$result['missing'][] = $name;
			continue;
		}

		foreach ( $found as $post_id ) {
			$result['matched'][] = array(
				'id'    => (int) $post_id,
				'name'  => $name,
			);

			if ( ! $dry_run ) {
				wp_trash_post( (int) $post_id );
				$result['trashed']++;
			}
		}
	}

	if ( ! $dry_run && $result['trashed'] > 0 ) {
		arv_race_store_flush_cache();
	}

	return $result;
}

/**
 * REST handler for the remove route.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function arv_race_store_rest_remove( $request ) {
	$names = $request->get_param( 'names' );

	if ( ! is_array( $names ) ) {
		$names = preg_split( '/\r\n|\r|\n/', (string) $names );
	}

	return new WP_REST_Response( arv_race_store_remove( $names, (bool) $request->get_param( 'dry_run' ) ), 200 );
}

/**
 * Register the remove route.
 */
function arv_race_store_register_remove_route() {
	register_rest_route(
		'aravaipa/v1',
		'/races/remove',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_race_store_rest_remove',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'names'   => array(
					'required' => true,
				),
				'dry_run' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'arv_race_store_register_remove_route' );

/**
 * The importer's guardrails and dry-run reporting, independent of REST.
 *
 * A scraper that fails partway through (a timeout, a changed page structure,
 * UltraSignup's group API returning an error page) tends to produce a
 * short, still-technically-valid file rather than an obvious error, a
 * handful of rows instead of eighty. Run that through a pruning import
 * unattended and it looks identical to a season legitimately ending: most
 * of the calendar gets trashed. This is the check a human pasting into the
 * admin screen effectively did by eye every time ("that count looks low, let
 * me check before I hit import"), made explicit so the unattended path has
 * it too.
 *
 * Comparing against valid rows, not raw lines: a scrape that returns 80
 * lines where 60 fail to parse should trip this exactly like a scrape that
 * returns 20 lines outright, since either way the store would end up with
 * a fifth of what it has now.
 *
 * @param string $raw     Pipe-separated rows, the same format the admin
 *                         screen and every element take.
 * @param bool   $prune   Remove stored races the import did not mention.
 * @param bool   $dry_run Report what would happen without writing anything.
 * @param bool   $force   Skip the row-count guardrail for an intentional
 *                         shrink (a season legitimately ending, an explicit
 *                         cleanup).
 * @return array {status, ...} status is 'ok', 'dry_run', or 'refused'.
 */
function arv_race_store_import_guarded( $raw, $prune = false, $dry_run = false, $force = false ) {
	$rows  = arv_parse_rows( $raw, 2 );
	$valid = 0;

	foreach ( $rows as $row ) {
		if ( null !== arv_upcoming_races_parse_row( $row ) ) {
			$valid++;
		}
	}

	$current = count( arv_race_store_get() );

	// The guardrail only matters when prune is on: without it, a short
	// import just updates/creates the races it mentions and leaves
	// everything else alone, so there is nothing destructive to refuse.
	if ( $prune && ! $force && $current > 0 && $valid < $current * 0.8 ) {
		return array(
			'status'   => 'refused',
			'reason'   => 'row_count_drop',
			'message'  => sprintf(
				'Refusing to prune: import has %1$d valid race(s), the store currently has %2$d. That is more than a 20%% drop, which usually means the scrape failed rather than the season shrinking. Pass force=true to override.',
				$valid,
				$current
			),
			'incoming' => $valid,
			'current'  => $current,
		);
	}

	if ( $dry_run ) {
		return array(
			'status'  => 'dry_run',
			'valid'   => $valid,
			'skipped' => count( $rows ) - $valid,
			'current' => $current,
		);
	}

	$result           = arv_race_store_import( $raw, $prune );
	$result['status'] = 'ok';

	return $result;
}

/**
 * REST wrapper. Reads the request, defers everything to
 * arv_race_store_import_guarded(), maps its status to an HTTP code.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function arv_race_store_rest_import( $request ) {
	// The route registration function tries to load the parser this depends
	// on, but only when Cornerstone is active (see the long comment there
	// for why). If Cornerstone is deactivated between that check and this
	// call, or was never active, this fails as a clean REST error instead
	// of an uncaught "call to undefined function" fatal reaching the client
	// as a raw PHP error page.
	if ( ! function_exists( 'arv_upcoming_races_parse_row' ) ) {
		return new WP_REST_Response(
			array(
				'status'  => 'refused',
				'reason'  => 'parser_unavailable',
				'message' => 'The race row parser is not loaded, which normally means Cornerstone is not active. This endpoint depends on it the same way the rest of the plugin does.',
			),
			503
		);
	}

	$result = arv_race_store_import_guarded(
		(string) $request->get_param( 'rows' ),
		(bool) $request->get_param( 'prune' ),
		(bool) $request->get_param( 'dry_run' ),
		(bool) $request->get_param( 'force' )
	);

	$code = ( 'refused' === $result['status'] ) ? 409 : 200;

	return new WP_REST_Response( $result, $code );
}

/**
 * The option holding scraped sold-out/waitlist state, keyed by race name.
 *
 * Deliberately not a column on the race row, which was the obvious first
 * instinct. Two reasons, one practical and one structural.
 *
 * Practical: the row format counts backwards from ARV_RACES_COLUMNS to find
 * its fixed tail, so adding a seventeenth field silently re-slices every
 * existing row that carries multi-cell distances. The plugin and the stored
 * rows would then have to update in lockstep, and whichever landed first
 * would misparse the calendar until the other caught up. Not a risk worth
 * taking for one field.
 *
 * Structural, and the real reason: this changes on a completely different
 * cadence to everything else in a row. A race's date, venue, distances and
 * page URL are set once a season and then sit still; whether it is sold out
 * flips without warning and can flip back when someone withdraws. Data that
 * changes hourly does not belong in the same record as data that changes
 * yearly, because the write patterns are different: this wants a small,
 * frequent, unattended overwrite, and the rows want a rare, careful,
 * guardrailed one.
 */
define( 'ARV_WAITLIST_OPTION', 'arv_race_waitlists' );

/**
 * Per-race producer notes, keyed by race name.
 *
 * Separate from a row for the same two reasons the waitlist option is:
 * the row parser counts backwards from a fixed column count, so a new
 * column re-slices every existing row, and this changes once when an
 * event changes hands where a row changes every season.
 */
define( 'ARV_RACE_NOTE_OPTION', 'arv_race_notes' );

/**
 * The stored waitlist map: race name => waitlist URL.
 *
 * @return array<string, string>
 */
function arv_race_waitlist_store_get() {
	$stored = get_option( ARV_WAITLIST_OPTION, array() );

	return is_array( $stored ) ? $stored : array();
}

/**
 * Replace the stored waitlist map wholesale.
 *
 * A full replace rather than a merge, on purpose: a race selling out is only
 * half the story, and the half that a merge would silently get wrong is a
 * race coming *back* from sold out when entries are released or someone
 * withdraws. Merging would leave the waitlist button up forever on a race
 * that reopened. The scraper always reports the complete current picture, so
 * absence from that picture is meaningful and has to be honoured.
 *
 * @param array<string, string> $map Race name => waitlist URL.
 * @return int Number of races stored.
 */
function arv_race_waitlist_store_set( $map ) {
	$clean = array();

	foreach ( (array) $map as $name => $url ) {
		$name = trim( (string) $name );
		$url  = esc_url_raw( trim( (string) $url ) );

		if ( '' === $name || '' === $url ) {
			continue;
		}

		$clean[ $name ] = $url;
	}

	update_option( ARV_WAITLIST_OPTION, $clean, false );

	return count( $clean );
}

/**
 * Register the waitlist write route.
 *
 * Same edit_posts scoping as the row importer, for the same reason: this is
 * reachable by the Editor-scoped Application Password the scraper runs as,
 * and by nothing with more reach than that.
 */
function arv_race_waitlist_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/races/waitlists',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_race_waitlist_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'waitlists' => array(
					'required' => true,
					'type'     => 'string',
				),
				'dry_run'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'arv_race_waitlist_register_rest_route' );

/**
 * Replace the per-race producer notes.
 *
 * @param array $map Race name => note. An empty note removes that race's.
 * @return int Number of notes stored.
 */
function arv_race_note_store_set( $map ) {
	$clean = array();

	foreach ( (array) $map as $name => $note ) {
		$name = trim( (string) $name );
		$note = sanitize_text_field( trim( (string) $note ) );

		if ( '' === $name || '' === $note ) {
			continue;
		}

		$clean[ $name ] = $note;
	}

	update_option( ARV_RACE_NOTE_OPTION, $clean, false );

	return count( $clean );
}

/**
 * Register the note write route.
 *
 * Same edit_posts scoping as the row importer and the waitlist route, so
 * the Editor-scoped Application Password reaches this and nothing wider.
 */
function arv_race_note_register_rest_route() {
	register_rest_route(
		'aravaipa/v1',
		'/races/notes',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_race_note_rest_set',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'notes'   => array(
					'required' => true,
					'type'     => 'string',
				),
				'dry_run' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'arv_race_note_register_rest_route' );

/**
 * POST /wp-json/aravaipa/v1/races/notes
 *
 * JSON as a string, the same shape the waitlist route takes, so both
 * writers look identical from a script's side.
 *
 * @param WP_REST_Request $request
 * @return array
 */
function arv_race_note_rest_set( $request ) {
	$decoded = json_decode( (string) $request->get_param( 'notes' ), true );

	if ( ! is_array( $decoded ) ) {
		return array( 'status' => 'refused', 'reason' => 'notes is not a JSON object' );
	}

	if ( $request->get_param( 'dry_run' ) ) {
		return array( 'status' => 'dry_run', 'valid' => count( $decoded ) );
	}

	return array( 'status' => 'ok', 'stored' => arv_race_note_store_set( $decoded ) );
}

/**
 * Write the scraped waitlist map.
 *
 * Takes JSON as a string rather than a structured parameter so the payload
 * survives a plain form-encoded POST, which is what the scraper sends and
 * what curl sends by hand when checking this by eye.
 *
 * No row-count guardrail here, unlike the row importer, and that asymmetry
 * is deliberate. Zero sold-out races is an ordinary, correct answer that
 * happens every time the last sold-out race on the calendar runs, whereas
 * zero *races* only ever means the scrape broke. Refusing an empty write
 * here would mean a stale waitlist button outliving the race it points at.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function arv_race_waitlist_rest_set( $request ) {
	$raw    = (string) $request->get_param( 'waitlists' );
	$parsed = json_decode( $raw, true );

	if ( ! is_array( $parsed ) ) {
		return new WP_REST_Response(
			array(
				'status'  => 'refused',
				'reason'  => 'bad_json',
				'message' => 'The waitlists parameter must be a JSON object of race name => waitlist URL.',
			),
			400
		);
	}

	$before = arv_race_waitlist_store_get();

	if ( $request->get_param( 'dry_run' ) ) {
		return new WP_REST_Response(
			array(
				'status'   => 'dry_run',
				'incoming' => count( $parsed ),
				'current'  => count( $before ),
				'races'    => array_keys( $parsed ),
			),
			200
		);
	}

	$count = arv_race_waitlist_store_set( $parsed );

	return new WP_REST_Response(
		array(
			'status'  => 'ok',
			'stored'  => $count,
			'was'     => count( $before ),
			'races'   => array_keys( arv_race_waitlist_store_get() ),
		),
		200
	);
}
