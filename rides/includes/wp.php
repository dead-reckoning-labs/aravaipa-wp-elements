<?php
/**
 * WordPress wiring for the Rides layout.
 *
 * A page shows the new layout only when it carries the post meta
 * _rides_v2_layout (home, events, results, photos or event:<slug>). Pages
 * without it are left exactly as they are.
 *
 * Preview: ?rides_preview=<token> renders the new layout on the phase 1 pages
 * before rollout. The token is compared with hash_equals against the option
 * rides_preview_token, and a preview is sent noindex and no-store.
 */

if ( ! defined( 'RIDES_DIR' ) ) {
	exit;
}

/** Phase 1 pages that may be previewed before they get the meta. */
function rides_preview_pages() {
	return array(
		26    => 'home',
		102   => 'events',
		265   => 'results',
		22674 => 'photos',
		24080 => 'event:prickly-pedal',
	);
}

/**
 * Is a layout key one we know how to render?
 *
 * @param string $layout Layout key.
 * @return bool
 */
function rides_valid_layout( $layout ) {
	if ( in_array( $layout, array( 'home', 'events', 'results', 'photos' ), true ) ) {
		return true;
	}
	if ( 0 === strpos( (string) $layout, 'event:' ) ) {
		$slug = substr( $layout, 6 );
		return (bool) preg_match( '/^[a-z0-9-]+$/', $slug ) && is_readable( RIDES_DIR . '/data/events/' . $slug . '.json' );
	}
	return false;
}

/**
 * True when the request carries the right preview token.
 *
 * @return bool
 */
function rides_is_preview() {
	if ( isset( $GLOBALS['rides_state']['preview'] ) ) {
		return $GLOBALS['rides_state']['preview'];
	}
	$ok = false;
	if ( isset( $_GET['rides_preview'] ) && is_string( $_GET['rides_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$given = (string) wp_unslash( $_GET['rides_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$token = (string) get_option( 'rides_preview_token', '' );
		$ok    = strlen( $token ) >= 16 && hash_equals( $token, $given );
	}
	$GLOBALS['rides_state']['preview'] = $ok;
	return $ok;
}

/**
 * Layout key for the current request, or '' to leave the page alone.
 *
 * @return string
 */
function rides_current_layout() {
	if ( isset( $GLOBALS['rides_state']['layout'] ) ) {
		return $GLOBALS['rides_state']['layout'];
	}
	$GLOBALS['rides_state']['layout'] = rides_resolve_layout();
	return $GLOBALS['rides_state']['layout'];
}

/**
 * Work out the layout key (uncached).
 *
 * @return string
 */
function rides_resolve_layout() {
	$layout = '';
	if ( ! is_singular( 'page' ) && ! is_front_page() ) {
		return $layout;
	}
	$id = (int) get_queried_object_id();
	if ( ! $id ) {
		return $layout;
	}
	$meta = (string) get_post_meta( $id, '_rides_v2_layout', true );
	if ( $meta && rides_valid_layout( $meta ) ) {
		$layout = $meta;
	} elseif ( rides_is_preview() ) {
		$map = rides_preview_pages();
		if ( isset( $map[ $id ] ) && rides_valid_layout( $map[ $id ] ) ) {
			$layout = $map[ $id ];
		}
	}
	return $layout;
}

/**
 * Send a header (tests capture them instead).
 *
 * @param string $line Header line.
 */
function rides_header( $line ) {
	if ( isset( $GLOBALS['rides_test_headers'] ) && is_array( $GLOBALS['rides_test_headers'] ) ) {
		$GLOBALS['rides_test_headers'][] = $line;
		return;
	}
	if ( ! headers_sent() ) {
		header( $line, true );
	}
}

/**
 * Render context for the live site.
 *
 * @return array
 */
function rides_wp_ctx() {
	return array(
		'base'  => plugins_url( '/', RIDES_DIR . '/aravaipa-rides.php' ),
		'today' => wp_date( 'Y-m-d' ),
	);
}

/**
 * True when this request is a preview (token valid, page has no meta yet).
 *
 * @return bool
 */
function rides_preview_active() {
	return rides_current_layout() && rides_is_preview();
}

add_action(
	'template_redirect',
	function () {
		if ( ! rides_preview_active() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		rides_header( 'X-Robots-Tag: noindex, nofollow' );
		rides_header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	},
	1
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( rides_preview_active() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
		}
		return $robots;
	},
	99
);

add_filter(
	'template_include',
	function ( $template ) {
		if ( rides_current_layout() ) {
			return RIDES_DIR . '/templates/page.php';
		}
		return $template;
	},
	99
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( rides_current_layout() ) {
			$classes[] = 'rides-v2-page';
		}
		return $classes;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! rides_current_layout() ) {
			return;
		}
		$base = plugins_url( 'assets/', RIDES_DIR . '/aravaipa-rides.php' );
		wp_enqueue_style( 'aravaipa-rides-fonts', 'https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap', array(), null );
		wp_enqueue_style( 'aravaipa-rides', $base . 'rides.css', array( 'aravaipa-rides-fonts' ), RIDES_VERSION );
		wp_enqueue_script( 'aravaipa-rides', $base . 'rides.js', array(), RIDES_VERSION, true );
	},
	20
);

/* Our own description and share image on new layout pages; Jetpack's tags step aside. */
add_filter(
	'jetpack_enable_open_graph',
	function ( $on ) {
		return rides_current_layout() ? false : $on;
	},
	99
);

add_action(
	'wp_head',
	function () {
		$layout = rides_current_layout();
		if ( ! $layout ) {
			return;
		}
		$ctx                 = rides_wp_ctx();
		list( $desc, $img ) = rides_layout_meta( $layout, $ctx );
		$title               = wp_get_document_title();
		$url                 = get_permalink( get_queried_object_id() );
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		}
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
		}
	},
	2
);
