<?php
/**
 * Plugin Name:       Aravaipa Elements
 * Plugin URI:        https://github.com/dead-reckoning-labs/aravaipa-wp-elements
 * Description:       Custom Cornerstone elements for aravaiparunning.com: race hero, distance cards, event timeline, partner grid, countdown and region map. Replaces the hand-built blocks currently rebuilt on every race page.
 * Version:           0.3.0
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

define( 'ARV_ELEMENTS_VERSION', '0.3.0' );
define( 'ARV_ELEMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ARV_ELEMENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ARV_ELEMENTS_PATH . 'includes/helpers.php';

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
