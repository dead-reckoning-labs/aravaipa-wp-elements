<?php
/**
 * Plugin Name: Aravaipa Rides
 * Description: Modern layout for aravaiparides.com: homepage hero slider, uniform event tiles, Events, Results, Photos and event pages. Pages opt in with the _rides_v2_layout post meta; nothing changes on a page without it.
 * Version: 0.1.0
 * Author: Aravaipa Running
 * Requires PHP: 8.0
 * Text Domain: aravaipa-rides
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'RIDES_HARNESS' ) ) {
	exit;
}

define( 'RIDES_VERSION', '0.1.0' );
define( 'RIDES_DIR', __DIR__ );

require_once RIDES_DIR . '/includes/data.php';
require_once RIDES_DIR . '/includes/render.php';

if ( function_exists( 'add_filter' ) ) {
	require_once RIDES_DIR . '/includes/wp.php';
}
