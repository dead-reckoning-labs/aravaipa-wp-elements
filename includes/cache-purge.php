<?php
/**
 * A token-guarded endpoint that clears WP Rocket from inside PHP.
 *
 * Why this exists: WP Rocket writes its page cache folders as the web
 * server's user, readable but not writable by the SSH user. So
 * rocket_clean_domain() run over SSH through wp-cli prints "cleared" and
 * silently skips every folder it cannot delete, and the plain URL keeps
 * serving the old page. scripts/aravaipa-cache-reset.sh calls this instead,
 * which runs as the web server's user and can delete them.
 *
 * POST /wp-json/arv/v1/purge-cache with header X-Arv-Purge-Token. The token
 * lives in the arv_purge_token option (set once over wp-cli) and in
 * ARAVAIPA_PURGE_TOKEN in the claw's .env.secrets. No option set means the
 * route refuses everything.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arv_cache_purge_register_route() {
	register_rest_route(
		'arv/v1',
		'/purge-cache',
		array(
			'methods'             => 'POST',
			'callback'            => 'arv_cache_purge_handle',
			'permission_callback' => 'arv_cache_purge_permission',
		)
	);
}
add_action( 'rest_api_init', 'arv_cache_purge_register_route' );

/**
 * @param WP_REST_Request $request
 * @return bool
 */
function arv_cache_purge_permission( $request ) {
	$expected = (string) get_option( 'arv_purge_token', '' );
	$given    = (string) $request->get_header( 'x_arv_purge_token' );

	return strlen( $expected ) >= 32 && hash_equals( $expected, $given );
}

/**
 * @return WP_REST_Response
 */
function arv_cache_purge_handle() {
	$done = array();

	if ( function_exists( 'rocket_clean_minify' ) ) {
		rocket_clean_minify();
		$done[] = 'minify';
	}

	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
		$done[] = 'pages';
	}

	wp_cache_flush();
	$done[] = 'object';

	// What is left proves it worked: a count of cached pages still on disk
	// for this host, which should be zero or close to it (a request landing
	// mid-purge can write one straight back).
	$left = 0;

	if ( defined( 'WP_ROCKET_CACHE_PATH' ) ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$dir  = trailingslashit( WP_ROCKET_CACHE_PATH ) . $host;

		if ( is_dir( $dir ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( '.html' === substr( $file->getFilename(), -5 ) ) {
					$left++;
				}
			}
		}
	}

	return new WP_REST_Response(
		array(
			'cleared'     => $done,
			'pages_left'  => $left,
		),
		200
	);
}
