<?php
/**
 * Self-updater.
 *
 * WordPress.org's update API only knows about plugins hosted there. A
 * plugin installed from a zip has no update source at all unless something
 * supplies one, which is the entire reason the zip built two weeks before
 * this file was written sat there stale and no one noticed: there was
 * nothing in WP Admin that could have said otherwise.
 *
 * This answers the two questions WordPress asks before it will offer an
 * update, both pointed at this repo's GitHub Releases:
 *
 *   site_transient_update_plugins  is there a newer version, and where is
 *                                   the zip (powers the "Update available"
 *                                   row and its "Update now" link)
 *   plugins_api                    what's in it (powers the "View details"
 *                                   popup and its changelog tab)
 *
 * A release only becomes visible here once build.sh's own zip is attached
 * to it as a release asset, so publishing a GitHub Release with no asset
 * (or a source-code-only auto-generated one) does not trigger anything.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_ELEMENTS_GH_REPO', 'dead-reckoning-labs/aravaipa-wp-elements' );

// Normally handed in by the main plugin file, which is the only place
// plugin_basename() has a real __FILE__ to work from. The fallback is for the
// test harness, which loads this file on its own; it is deliberately the
// conventional folder name rather than anything clever, because in WordPress
// this constant is always already set by the time the file is reached.
if ( ! defined( 'ARV_ELEMENTS_SLUG' ) ) {
	define( 'ARV_ELEMENTS_SLUG', 'aravaipa-elements/aravaipa-elements.php' );
}

/**
 * Fetch the latest GitHub release, cached in a transient.
 *
 * Anonymous GitHub API requests are rate limited to 60 per hour per IP,
 * shared across every plugin's updater on the box, not just this one, so
 * this is checked once every 12 hours rather than on every admin page load
 * that happens to touch the Plugins screen.
 *
 * A failed request is cached too, for 5 minutes rather than the full 12
 * hours: caching nothing would let a GitHub outage retry on every single
 * page load for as long as it lasts, caching it for the full 12 hours would
 * hide a real update for that long the moment GitHub blipped once.
 *
 * @param bool $bypass_cache Skip the cached value and check GitHub directly.
 * @return object|null Decoded release JSON, or null if none is available.
 */
function arv_elements_latest_release( $bypass_cache = false ) {
	if ( ! $bypass_cache ) {
		$cached = get_transient( 'arv_elements_latest_release' );
		if ( false !== $cached ) {
			// A cached failure is stored as the string 'none', since the
			// transient API cannot distinguish a stored false/null from
			// "nothing stored", both of which read back as false.
			return ( 'none' === $cached ) ? null : $cached;
		}
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . ARV_ELEMENTS_GH_REPO . '/releases/latest',
		array(
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'aravaipa-elements-updater',
			),
			'timeout' => 10,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_transient( 'arv_elements_latest_release', 'none', 5 * MINUTE_IN_SECONDS );
		return null;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ) );

	if ( ! is_object( $release ) || empty( $release->tag_name ) ) {
		set_transient( 'arv_elements_latest_release', 'none', 5 * MINUTE_IN_SECONDS );
		return null;
	}

	set_transient( 'arv_elements_latest_release', $release, 12 * HOUR_IN_SECONDS );

	return $release;
}

/**
 * Find the built plugin zip among a release's assets.
 *
 * GitHub also auto-generates a "Source code (zip)" download for every
 * release, which is not this: it archives the repo root as
 * aravaipa-elements-0.3.0/, dev harnesses, README, .git metadata and all,
 * not the includes/assets/plugin-file layout WordPress expects at
 * wp-content/plugins/aravaipa-elements/. Only a real uploaded asset named
 * for the plugin is ever treated as the update package.
 *
 * @param object $release Decoded release JSON.
 * @return string|null
 */
function arv_elements_release_zip_url( $release ) {
	if ( empty( $release->assets ) || ! is_array( $release->assets ) ) {
		return null;
	}

	foreach ( $release->assets as $asset ) {
		if ( isset( $asset->name ) && 'aravaipa-elements.zip' === $asset->name && ! empty( $asset->browser_download_url ) ) {
			return $asset->browser_download_url;
		}
	}

	return null;
}

/**
 * Turn a release's version tag into a bare version string.
 *
 * Tags are written "v0.3.0"; the plugin header's Version field is "0.3.0".
 * version_compare() would otherwise read "v0.3.0" as lower than "0.3.0"
 * (a bare "v" is not numeric and sorts as a pre-release marker), which
 * would make every real release look older than what's already installed
 * and silently never offer an update at all.
 *
 * @param string $tag_name Git tag, e.g. "v0.3.0".
 * @return string
 */
function arv_elements_version_from_tag( $tag_name ) {
	return ltrim( (string) $tag_name, 'vV' );
}

/**
 * Artwork WordPress shows for this plugin.
 *
 * Without these it falls back to a generic grey plug icon on the Plugins and
 * Updates screens, which is what it did until now.
 *
 * The keys are WordPress's own and are not arbitrary: '1x' and '2x' for
 * icons, 'low' and 'high' for banners. Anything else is ignored silently.
 *
 * URLs point at the installed plugin's own directory rather than at GitHub,
 * so they always resolve to whatever version is actually on the site and do
 * not depend on a release asset staying reachable.
 *
 * @return array {icons, banners}
 */
function arv_elements_artwork() {
	// Guarded rather than assumed. This constant is defined by the main
	// plugin file before this one loads, so in WordPress it is always there,
	// but every other hook in this file already degrades to "return what you
	// were given" rather than fataling, and a fatal raised from an update
	// check takes wp-admin down with it. Artwork is not worth that.
	if ( ! defined( 'ARV_ELEMENTS_URL' ) ) {
		return array(
			'icons'   => array(),
			'banners' => array(),
		);
	}

	$base = ARV_ELEMENTS_URL . 'assets/plugin/';

	return array(
		'icons'   => array(
			'1x'      => $base . 'icon-128x128.png',
			'2x'      => $base . 'icon-256x256.png',
			'default' => $base . 'icon-256x256.png',
		),
		'banners' => array(
			'low'  => $base . 'banner-772x250.png',
			'high' => $base . 'banner-1544x500.png',
		),
	);
}

/**
 * site_transient_update_plugins filter.
 *
 * @param object $transient
 * @return object
 */
function arv_elements_check_for_update( $transient ) {
	// $transient->checked is populated from the currently installed plugin
	// versions by WordPress itself before this filter runs; empty means
	// nothing has been checked yet this request, most commonly because
	// get_plugins() has not been called, and there is no current version
	// here to compare a release against.
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	// WP Admin's own "Check Again" link deletes this transient and forces
	// a fresh run of every registered checker, which is meaningless if this
	// checker then hands back the last 12 hours' answer regardless. The
	// query var it sets is the one signal available here that this run is
	// that deliberate, not-just-routine check.
	$release = arv_elements_latest_release( isset( $_GET['force-check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! $release ) {
		return $transient;
	}

	$remote_version  = arv_elements_version_from_tag( $release->tag_name );
	$current_version = isset( $transient->checked[ ARV_ELEMENTS_SLUG ] )
		? $transient->checked[ ARV_ELEMENTS_SLUG ]
		: null;

	$zip_url = arv_elements_release_zip_url( $release );

	$art = arv_elements_artwork();

	$info = (object) array(
		'slug'        => 'aravaipa-elements',
		'plugin'      => ARV_ELEMENTS_SLUG,
		'new_version' => $remote_version,
		'url'         => $release->html_url,
		'package'     => $zip_url ? $zip_url : '',
		'icons'       => $art['icons'],
		'banners'     => $art['banners'],
		'banners_rtl' => array(),
		'tested'      => get_bloginfo( 'version' ),
	);

	if ( ! $current_version || ! $zip_url || ! version_compare( $remote_version, $current_version, '>' ) ) {
		// Already current, ahead of the latest release (a manual copy of an
		// unreleased build), or the release has no installable zip.
		//
		// Landing here still means describing the plugin in ->no_update
		// rather than just returning. WordPress only draws the "Enable
		// auto-updates" link for plugins it finds in ->response or
		// ->no_update, so a plugin that is simply up to date and absent
		// from both looks unmanaged, and its Automatic Updates column comes
		// out empty while every other plugin on the screen offers the
		// toggle. That is exactly what it did before this.
		//
		// Any stale ->response entry from an earlier, newer-at-the-time
		// check is cleared at the same time rather than left to linger.
		unset( $transient->response[ ARV_ELEMENTS_SLUG ] );
		$transient->no_update[ ARV_ELEMENTS_SLUG ] = $info;
		return $transient;
	}

	unset( $transient->no_update[ ARV_ELEMENTS_SLUG ] );
	$transient->response[ ARV_ELEMENTS_SLUG ] = $info;

	return $transient;
}
add_filter( 'site_transient_update_plugins', 'arv_elements_check_for_update' );

/**
 * plugins_api filter: the "View details" popup WordPress shows when someone
 * clicks the version number on an update notice.
 *
 * @param false|object|array $result
 * @param string             $action
 * @param object             $args
 * @return false|object|array
 */
function arv_elements_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'aravaipa-elements' !== $args->slug ) {
		return $result;
	}

	$release = arv_elements_latest_release();
	if ( ! $release ) {
		return $result;
	}

	$zip_url = arv_elements_release_zip_url( $release );
	$body    = isset( $release->body ) ? trim( (string) $release->body ) : '';
	$art     = arv_elements_artwork();

	return (object) array(
		'name'          => 'Aravaipa Elements',
		'slug'          => 'aravaipa-elements',
		'version'       => arv_elements_version_from_tag( $release->tag_name ),
		'author'        => '<a href="https://deadreckoninglabs.com">Dead Reckoning Labs</a>',
		'homepage'      => 'https://github.com/' . ARV_ELEMENTS_GH_REPO,
		'sections'      => array(
			'description' => '<p>Custom Cornerstone elements for aravaiparunning.com: race hero, distance cards, event timeline, partner grid, countdown, region map.</p>',
			// esc_html + nl2br rather than a Markdown parser: release notes
			// are written by one person (whoever cuts the release) in plain
			// paragraphs, not arbitrary user input needing full Markdown
			// rendering, and pulling in a parser is more than this needs.
			'changelog'   => '' !== $body ? '<p>' . nl2br( esc_html( $body ) ) . '</p>' : '<p>No release notes provided.</p>',
		),
		'download_link' => $zip_url ? $zip_url : '',
		'icons'         => $art['icons'],
		'banners'       => $art['banners'],
	);
}
add_filter( 'plugins_api', 'arv_elements_plugins_api', 10, 3 );

/**
 * Adds a GitHub link to the plugin's row on the Plugins screen, next to
 * "View details" / "Deactivate". Not load-bearing for updates, just the
 * more direct path from "something looks off" to the actual source.
 *
 * @param array  $links
 * @param string $file
 * @return array
 */
function arv_elements_row_meta( $links, $file ) {
	if ( ARV_ELEMENTS_SLUG !== $file ) {
		return $links;
	}

	$links[] = '<a href="https://github.com/' . ARV_ELEMENTS_GH_REPO . '" target="_blank" rel="noopener noreferrer">GitHub</a>';

	return $links;
}
add_filter( 'plugin_row_meta', 'arv_elements_row_meta', 10, 2 );

/**
 * Clears the cached release after any plugin update runs.
 *
 * Without this, updating right after a release ships could still read the
 * previous check's cached answer for up to 12 hours and describe the
 * version just installed as out of date, or, the other direction, cache a
 * pre-update "you're current" answer moments before the update this same
 * pageload just kicked off actually lands.
 *
 * @param WP_Upgrader $upgrader
 * @param array       $data
 */
function arv_elements_clear_cache_after_update( $upgrader, $data ) {
	if ( 'update' === ( $data['action'] ?? null ) && 'plugin' === ( $data['type'] ?? null ) ) {
		delete_transient( 'arv_elements_latest_release' );
	}
}
add_action( 'upgrader_process_complete', 'arv_elements_clear_cache_after_update', 10, 2 );
