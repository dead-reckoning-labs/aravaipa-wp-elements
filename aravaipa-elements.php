<?php
/**
 * Plugin Name:       Aravaipa Elements
 * Plugin URI:        https://github.com/dead-reckoning-labs/aravaipa-wp-elements
 * Description:       Custom Cornerstone elements for aravaiparunning.com: race hero, distance cards, event timeline, partner grid, countdown and region map. Replaces the hand-built blocks currently rebuilt on every race page.
 * Version:           0.21.14
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

define( 'ARV_ELEMENTS_VERSION', '0.21.14' );
define( 'ARV_ELEMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ARV_ELEMENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ARV_ELEMENTS_PATH . 'includes/helpers.php';

// Front end too, unlike the updater: this one writes to <head> on the page a
// visitor (and a crawler) actually requests.
require_once ARV_ELEMENTS_PATH . 'includes/seo.php';

// The single source of truth for races. Loaded on the front end too: the
// elements read from it on every page render, not just in the admin.
require_once ARV_ELEMENTS_PATH . 'includes/race-store.php';
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

	wp_enqueue_script(
		'aravaipa-countdown',
		ARV_ELEMENTS_URL . 'assets/aravaipa-countdown.js',
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
