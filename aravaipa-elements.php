<?php
/**
 * Plugin Name:       Aravaipa Elements
 * Plugin URI:        https://github.com/dead-reckoning-labs/aravaipa-wp-elements
 * Description:       Custom Cornerstone elements for aravaiparunning.com: race hero, distance cards, event timeline, partner grid, countdown and region map. Replaces the hand-built blocks currently rebuilt on every race page.
 * Version:           0.45.0
 * Author:            Dead Reckoning Labs
 * Author URI:        https://deadreckoninglabs.com
 * License:           GPL-2.0-or-later
 * Text Domain:       aravaipa-elements
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_ELEMENTS_VERSION', '0.45.0' );
define( 'ARV_ELEMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ARV_ELEMENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ARV_ELEMENTS_PATH . 'includes/helpers.php';

// Front end too, unlike the updater: this one writes to <head> on the page a
// visitor (and a crawler) actually requests.
require_once ARV_ELEMENTS_PATH . 'includes/seo.php';

// The single source of truth for races. Loaded on the front end too: the
// elements read from it on every page render, not just in the admin.
require_once ARV_ELEMENTS_PATH . 'includes/race-store.php';

// Phase logic and the SportsEvent builder. Beside the store rather than
// inside an element, because includes/seo.php needs both on race pages that
// contain no element at all. See the file's own header.
require_once ARV_ELEMENTS_PATH . 'includes/race-schema.php';

// Past results, read by the Aravaipa Results element. An option rather than
// a post type, since nothing in it is ever edited by hand; see the file.
require_once ARV_ELEMENTS_PATH . 'includes/results-store.php';

// What the live timing board is carrying: real start times, cutoffs and
// the per-distance ids that let a chip link to its own results.
require_once ARV_ELEMENTS_PATH . 'includes/live-store.php';

// Finisher counts and winners, keyed by the same board slug the live store
// uses, so it has to load after it: arv_stats_store_find() reads the slug
// out of a race's live URL with arv_live_store_slug().
require_once ARV_ELEMENTS_PATH . 'includes/stats-store.php';

// Aravaipa's broadcasts, read live from Mountain Outpost. Loaded
// unconditionally because it registers a shortcode, same as the live page
// below.
require_once ARV_ELEMENTS_PATH . 'includes/watch-store.php';

// Aravaipa's films, read from the same system's YouTube playlists. Loaded
// unconditionally for the same reason.
require_once ARV_ELEMENTS_PATH . 'includes/films-store.php';

// Aravaipa's podcasts. Embedded rather than read: there is no feed here,
// just two Spotify show ids, so this registers its shortcode the same way
// with nothing to fetch.
require_once ARV_ELEMENTS_PATH . 'includes/podcasts-store.php';

require_once ARV_ELEMENTS_PATH . 'includes/weather.php';

// The branded live-results page. Loaded unconditionally rather than with the
// elements, because it registers a shortcode: the eventual shape of this is
// one page per race per year, which is created programmatically, and none of
// that should depend on Cornerstone being active.
require_once ARV_ELEMENTS_PATH . 'includes/live-page.php';
require_once ARV_ELEMENTS_PATH . 'includes/race-admin.php';

// Admin only: every hook in updater.php answers questions WordPress only
// asks inside wp-admin (the Plugins screen, the update cron that runs
// alongside it). Loading it on the front end would just be dead weight on
// every page view a visitor makes.
if ( is_admin() ) {
	// Worked out here, from the real file, rather than spelled out as a
	// literal inside updater.php. WordPress appends a suffix when it installs
	// a plugin whose folder name is already taken, so a second upload of this
	// zip lands in aravaipa-elements-2/ alongside the original. The live site
	// is running exactly that right now. A hardcoded
	// "aravaipa-elements/aravaipa-elements.php" does not match that copy, so
	// the copy actually running the site would never be offered an update,
	// while the stale copy next to it quietly collects them.
	define( 'ARV_ELEMENTS_SLUG', plugin_basename( __FILE__ ) );
	require_once ARV_ELEMENTS_PATH . 'includes/updater.php';
}

/**
 * Register every element with Cornerstone.
 *
 * cs_register_elements only fires when Cornerstone is active, so this is
 * already conditional. The function_exists guard is belt and braces: this
 * plugin sits on a production site whose main job is selling race entries,
 * and a fatal here would take the whole site down, not just the builder.
 */
function arv_elements_register() {
	if ( ! function_exists( 'cs_register_element' ) ) {
		return;
	}

	$elements = array(
		'race-hero',
		'distance-cards',
		'event-timeline',
		'partner-grid',
		'countdown',
		'region-map',
		'upcoming-races',
		'season-calendar',
		'race-status',
		'race-map',
		'featured-race',
		'results',
		'live-embed',
		'watch',
		'watch-race',
		'films',
		'podcasts',
	);

	foreach ( $elements as $element ) {
		$file = ARV_ELEMENTS_PATH . 'includes/elements/' . $element . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
add_action( 'cs_register_elements', 'arv_elements_register' );

/**
 * Front-end styles.
 *
 * Loaded on every page rather than per element: Cornerstone renders elements
 * deep inside its own template pipeline, so there is no reliable render-time
 * hook to enqueue from once the page has already started streaming. The file
 * is a few KB and WP Rocket (active on this site) will combine it.
 */
function arv_elements_assets() {
	wp_enqueue_style(
		'aravaipa-elements',
		ARV_ELEMENTS_URL . 'assets/aravaipa-elements.css',
		array(),
		ARV_ELEMENTS_VERSION
	);

	// Enqueued here rather than from countdown.php because that file is only
	// required during cs_register_elements, which is not guaranteed to run
	// before wp_enqueue_scripts. The script no-ops on pages with no countdown.
	// Filtering for the season calendar. Deferred and dependency-free: the
	// list it filters is already complete in the HTML, so nothing waits on
	// this to render, and it no-ops on pages with no calendar.
	wp_enqueue_script(
		'aravaipa-calendar',
		ARV_ELEMENTS_URL . 'assets/aravaipa-calendar.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	// Opens a region's card on the first tap instead of navigating straight
	// off the homepage. Only does anything where hover does not exist; on a
	// desktop it returns immediately and the CSS keeps handling the card.
	// Search for the Results element. Hides and shows rows that are already
	// in the page; no-ops where the element is not present.
	wp_enqueue_script(
		'aravaipa-results',
		ARV_ELEMENTS_URL . 'assets/aravaipa-results.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	wp_enqueue_script(
		'aravaipa-region-map',
		ARV_ELEMENTS_URL . 'assets/aravaipa-region-map.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	wp_enqueue_script(
		'aravaipa-countdown',
		ARV_ELEMENTS_URL . 'assets/aravaipa-countdown.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	// Collapses the theme's own footer widget to an accordion on a phone.
	// No-ops anywhere the footer is not the shape it expects, so it is safe
	// to load on every page rather than only where an element renders.
	wp_enqueue_script(
		'aravaipa-footer',
		ARV_ELEMENTS_URL . 'assets/aravaipa-footer.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	// A Watch race page's segment playlist. No-ops anywhere that markup is
	// not present, same as every other script enqueued here.
	wp_enqueue_script(
		'aravaipa-watch',
		ARV_ELEMENTS_URL . 'assets/aravaipa-watch.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	// The Films page's player. No-ops anywhere that markup is not present,
	// same as every other script enqueued here.
	wp_enqueue_script(
		'aravaipa-films',
		ARV_ELEMENTS_URL . 'assets/aravaipa-films.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);

	// Lets a phone scroll past the live board, which is taller than the
	// screen and does not hand its touches back. Touch devices only; see the
	// file. No-ops everywhere the board is not present.
	wp_enqueue_script(
		'aravaipa-live',
		ARV_ELEMENTS_URL . 'assets/aravaipa-live.js',
		array(),
		ARV_ELEMENTS_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'arv_elements_assets' );

/**
 * Clear the cached GitHub release on activation.
 *
 * Installing a zip by hand is an "install", not an "update", so the
 * upgrader hook in includes/updater.php does not fire for it. Without this,
 * a fresh install that checks GitHub while it happens to be the newest
 * release caches "you are current" for 12 hours, and a release published an
 * hour later stays invisible for the rest of that window. Exactly that
 * happened on the first real install.
 *
 * Deliberately not calling into updater.php: that file only loads in
 * wp-admin, and this needs to run whenever activation happens.
 */
function arv_elements_on_activate() {
	delete_transient( 'arv_elements_latest_release' );
	// Also drop WordPress's own cached update check, so the Plugins screen
	// reflects reality on the next load rather than up to 12 hours later.
	delete_site_transient( 'update_plugins' );
}
register_activation_hook( __FILE__, 'arv_elements_on_activate' );

/**
 * Admin notice when Cornerstone is missing, so an accidental deactivation
 * surfaces as an explanation rather than elements silently vanishing from
 * the builder.
 */
function arv_elements_dependency_notice() {
	if ( function_exists( 'cs_register_element' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Aravaipa Elements:</strong> Cornerstone is not active, so the custom elements are not registered. Existing pages still render their saved content.</p></div>';
}
add_action( 'admin_notices', 'arv_elements_dependency_notice' );
