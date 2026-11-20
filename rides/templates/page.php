<?php
/**
 * Page template for the Rides layout: the theme header and footer around our markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo '<main class="x-main full rides-v2-main" role="main">';
echo rides_render_layout( rides_current_layout(), rides_wp_ctx() ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderers.
echo '</main>';
get_footer();
