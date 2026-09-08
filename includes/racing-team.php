<?php
/**
 * The Racing Team roster grid, and what renders on an athlete's own page.
 *
 * The roster page used to be a single 15,000px Cornerstone tree: 51 athletes,
 * each one three accordions deep, none of them a real link. This is the
 * replacement for that page's content, [arv_racing_team], plus the profile
 * markup an individual athlete's own page gets automatically, since nobody
 * should have to hand-build 51 near-identical pages in Cornerstone one at a
 * time the way the old roster page was hand-built once.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The roster grid.
 *
 * [arv_racing_team]
 * [arv_racing_team status="alumni"]
 * [arv_racing_team division="great-lakes-endurance"]
 *
 * Filters are shortcode attributes rather than a client-side dropdown: the
 * roster splits into a handful of pages this way (Racing Team, an Alumni
 * page, maybe a Great Lakes Endurance page later) and each one is a real,
 * separately crawlable URL instead of one page with hidden states behind a
 * dropdown a crawler never operates.
 *
 * @param array $atts
 * @return string
 */
function arv_racing_team_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'status'   => 'current',
			'region'   => '',
			'division' => '',
		),
		$atts,
		'arv_racing_team'
	);

	$athletes = arv_athlete_store_get(
		array_filter(
			array(
				'status'   => $atts['status'],
				'region'   => $atts['region'],
				'division' => $atts['division'],
			)
		)
	);

	if ( empty( $athletes ) ) {
		return '<p class="arv-team-empty">' . esc_html__( 'No athletes to show yet.', 'aravaipa-elements' ) . '</p>';
	}

	$regions   = arv_athlete_store_filter_options( ARV_ATHLETE_REGION_TAX, $atts['status'] );
	$divisions = arv_athlete_store_filter_options( ARV_ATHLETE_DIVISION_TAX, $atts['status'] );

	$out  = '<div class="arv-team" data-arv-team-root>';
	$out .= arv_racing_team_filters_markup( $regions, $divisions, $atts );
	$out .= '<p class="arv-team__count" data-arv-team-count aria-live="polite"></p>';
	$out .= '<div class="arv-team__grid" data-arv-team-grid>';

	foreach ( $athletes as $athlete ) {
		$out .= arv_racing_team_card_markup( $athlete );
	}

	$out .= '</div></div>';

	return $out;
}
add_shortcode( 'arv_racing_team', 'arv_racing_team_shortcode' );

/**
 * The region/division options actually in use for this status, so a filter
 * never offers a choice that would return zero results.
 *
 * @param string $taxonomy
 * @param string $status
 * @return array<int, array{slug: string, name: string}>
 */
function arv_athlete_store_filter_options( $taxonomy, $status ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$options = array();

	foreach ( $terms as $term ) {
		$options[] = array( 'slug' => $term->slug, 'name' => $term->name );
	}

	return $options;
}

/**
 * The filter controls above the grid.
 *
 * @param array $regions
 * @param array $divisions
 * @param array $atts
 * @return string
 */
function arv_racing_team_filters_markup( $regions, $divisions, $atts ) {
	if ( empty( $regions ) && empty( $divisions ) ) {
		return '';
	}

	$out = '<div class="arv-team__filters">';

	if ( ! empty( $regions ) ) {
		$out .= '<select class="arv-team__filter" data-arv-team-region aria-label="' . esc_attr__( 'Filter by region', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'All regions', 'aravaipa-elements' ) . '</option>';
		foreach ( $regions as $r ) {
			$out .= '<option value="' . esc_attr( $r['slug'] ) . '"' . selected( $atts['region'], $r['slug'], false ) . '>' . esc_html( $r['name'] ) . '</option>';
		}
		$out .= '</select>';
	}

	if ( ! empty( $divisions ) ) {
		$out .= '<select class="arv-team__filter" data-arv-team-division aria-label="' . esc_attr__( 'Filter by division', 'aravaipa-elements' ) . '">';
		$out .= '<option value="">' . esc_html__( 'All divisions', 'aravaipa-elements' ) . '</option>';
		foreach ( $divisions as $d ) {
			$out .= '<option value="' . esc_attr( $d['slug'] ) . '"' . selected( $atts['division'], $d['slug'], false ) . '>' . esc_html( $d['name'] ) . '</option>';
		}
		$out .= '</select>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * One athlete's card in the grid.
 *
 * @param array $athlete
 * @return string
 */
function arv_racing_team_card_markup( $athlete ) {
	$regions_attr   = esc_attr( strtolower( implode( '|', $athlete['regions'] ) ) );
	$divisions_attr = esc_attr( strtolower( implode( '|', $athlete['divisions'] ) ) );

	$out  = '<a class="arv-team__card" href="' . esc_url( $athlete['url'] ) . '"';
	$out .= ' data-arv-team-region="' . $regions_attr . '" data-arv-team-division="' . $divisions_attr . '">';

	if ( $athlete['photo'] ) {
		$out .= '<img class="arv-team__photo" src="' . esc_url( $athlete['photo'] ) . '" alt="' . esc_attr( $athlete['name'] ) . '" loading="lazy" width="400" height="400" />';
	}

	$out .= '<span class="arv-team__name">' . esc_html( $athlete['name'] ) . '</span>';

	if ( '' !== $athlete['hometown'] ) {
		$out .= '<span class="arv-team__hometown">' . esc_html( $athlete['hometown'] ) . '</span>';
	}

	$out .= '</a>';

	return $out;
}

/**
 * The profile sections appended to an athlete's own page.
 *
 * Appended to the_content rather than requiring a shortcode in every one of
 * 51 posts: the bio an athlete wrote is the post's own content, and this is
 * the structured data around it that no one should have to hand-place.
 *
 * @param string $content
 * @return string
 */
function arv_athlete_profile_content( $content ) {
	if ( ! is_singular( ARV_ATHLETE_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$athlete = arv_athlete_store_get_one( get_queried_object() );

	if ( ! $athlete ) {
		return $content;
	}

	return arv_athlete_profile_meta_markup( $athlete )
		. $content
		. arv_athlete_profile_results_markup( $athlete )
		. arv_athlete_profile_videos_markup( $athlete )
		. arv_athlete_profile_links_markup( $athlete );
}
add_filter( 'the_content', 'arv_athlete_profile_content' );

/**
 * Hometown, status and division/region badges, shown above the bio.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_meta_markup( $athlete ) {
	$out = '<div class="arv-athlete__meta">';

	if ( '' !== $athlete['hometown'] ) {
		$out .= '<span class="arv-athlete__hometown">' . esc_html( $athlete['hometown'] ) . '</span>';
	}

	foreach ( array_merge( $athlete['divisions'], $athlete['regions'] ) as $tag ) {
		$out .= '<span class="arv-athlete__tag">' . esc_html( $tag ) . '</span>';
	}

	if ( 'alumni' === $athlete['status'] ) {
		$out .= '<span class="arv-athlete__tag arv-athlete__tag--alumni">' . esc_html__( 'Alumni', 'aravaipa-elements' ) . '</span>';
	}

	$out .= '</div>';

	if ( 'alumni' === $athlete['status'] && '' !== $athlete['alumni_note'] ) {
		$out .= '<p class="arv-athlete__alumni-note">' . esc_html( $athlete['alumni_note'] ) . '</p>';
	}

	return $out;
}

/**
 * An athlete's results, grouped by year.
 *
 * The stored text is what the old Cornerstone page carried: a bare four
 * digit year on its own line, then that year's results one per line. The
 * last few lines are the labels of the link buttons that sat underneath
 * ("UltraSignup", "Strava", "UltraRunning Mag"), which are dropped here
 * because arv_athlete_profile_links_markup() renders those as real links
 * from stored URLs instead.
 *
 * Parsed at render time rather than at import, so correcting a typo in a
 * result means editing one plain text field and nothing else.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_results_markup( $athlete ) {
	$raw = isset( $athlete['results_text'] ) ? trim( (string) $athlete['results_text'] ) : '';

	if ( '' === $raw ) {
		return '';
	}

	// Labels of the old link buttons, not results.
	$not_results = array( 'ultrasignup', 'strava', 'ultrarunning mag', 'ultrarunning magazine', 'athlinks' );

	$years = array();
	$year  = '';

	foreach ( preg_split( '/\R/', $raw ) as $line ) {
		$line = trim( $line );

		if ( '' === $line || in_array( strtolower( $line ), $not_results, true ) ) {
			continue;
		}

		if ( preg_match( '/^(19|20)\d{2}$/', $line ) ) {
			$year           = $line;
			$years[ $year ] = isset( $years[ $year ] ) ? $years[ $year ] : array();
			continue;
		}

		// A result before any year heading still belongs somewhere.
		if ( '' === $year ) {
			$year           = __( 'Selected results', 'aravaipa-elements' );
			$years[ $year ] = array();
		}

		$years[ $year ][] = $line;
	}

	$years = array_filter( $years );

	if ( empty( $years ) ) {
		return '';
	}

	$out = '<div class="arv-athlete__results"><h2>' . esc_html__( 'Results', 'aravaipa-elements' ) . '</h2>';

	foreach ( $years as $heading => $results ) {
		$out .= '<h3 class="arv-athlete__results-year">' . esc_html( $heading ) . '</h3><ul class="arv-athlete__results-list">';
		foreach ( $results as $result ) {
			$out .= '<li>' . esc_html( $result ) . '</li>';
		}
		$out .= '</ul>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * The videos an athlete appears in.
 *
 * Rendered as links rather than 65 embedded iframes, which is what the old
 * roster page did on a single page. One athlete's handful of videos could
 * be embedded safely, but a link keeps the page weightless and still gets
 * someone to the video in one click.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_videos_markup( $athlete ) {
	$raw = isset( $athlete['video_urls'] ) ? trim( (string) $athlete['video_urls'] ) : '';

	if ( '' === $raw ) {
		return '';
	}

	$urls = array_filter( array_map( 'trim', preg_split( '/\R/', $raw ) ) );

	if ( empty( $urls ) ) {
		return '';
	}

	$out = '<div class="arv-athlete__videos"><h2>' . esc_html__( 'Watch', 'aravaipa-elements' ) . '</h2><ul class="arv-athlete__videos-list">';

	foreach ( $urls as $i => $url ) {
		$out .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
			. esc_html( sprintf( /* translators: video number */ __( 'Video %d', 'aravaipa-elements' ), $i + 1 ) )
			. '</a></li>';
	}

	$out .= '</ul></div>';

	return $out;
}

/**
 * Social and results links, shown after the bio.
 *
 * Results, videos and tagged articles are deliberately not rendered here
 * yet: this ships the page and the data model first, wiring in the
 * existing results, films and articles stores is the next step, once every
 * athlete carries a real UltraSignup ID to join against rather than a name
 * that might not match.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_profile_links_markup( $athlete ) {
	$links = array();

	if ( '' !== $athlete['instagram'] ) {
		$links[] = array( 'https://www.instagram.com/' . ltrim( $athlete['instagram'], '@' ), 'Instagram' );
	}

	if ( '' !== $athlete['strava'] ) {
		$links[] = array( $athlete['strava'], 'Strava' );
	}

	if ( '' !== $athlete['ultrasignup_id'] ) {
		$links[] = array( 'https://ultrasignup.com/athlete_history.aspx?athlete_id=' . rawurlencode( $athlete['ultrasignup_id'] ), 'UltraSignup' );
	}

	if ( '' !== $athlete['ultrarunning_url'] ) {
		$links[] = array( $athlete['ultrarunning_url'], 'UltraRunning Magazine' );
	}

	if ( empty( $links ) ) {
		return '';
	}

	$out = '<p class="arv-athlete__links">';

	foreach ( $links as $i => $link ) {
		if ( $i > 0 ) {
			$out .= ' ';
		}
		$out .= '<a href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener">' . esc_html( $link[1] ) . '</a>';
	}

	$out .= '</p>';

	return $out;
}

/**
 * Put Racing Team into an athlete's breadcrumb trail.
 *
 * The theme builds breadcrumbs from a post's own hierarchy, and a custom
 * post type has none, so every athlete read "Home > Devon Yanko" as though
 * they sat at the top level of the site. The roster page they belong to was
 * missing from the trail entirely, which is wrong for a visitor trying to
 * get back to it and wrong for the BreadcrumbList schema the theme emits
 * around it.
 *
 * Filters the rendered markup rather than the data, because that is the only
 * hook the theme offers: x_breadcrumbs passes the finished HTML string. The
 * inserted crumb is built to match the surrounding structure exactly,
 * including the itemprop attributes, and the trailing position values are
 * renumbered so the schema stays sequential.
 *
 * @param string $output
 * @return string
 */
function arv_athlete_breadcrumbs( $output ) {
	if ( ! is_singular( ARV_ATHLETE_POST_TYPE ) || '' === $output ) {
		return $output;
	}

	$roster = get_page_by_path( 'racing-team' );

	if ( ! $roster ) {
		return $output;
	}

	// The athlete's own crumb is the last item in the list; the new one goes
	// immediately before it.
	$needle = strrpos( $output, '<span itemprop="itemListElement"' );

	if ( false === $needle ) {
		return $output;
	}

	$delimiter = '';
	if ( preg_match( '~<span class="delimiter">.*?</span>~s', $output, $m ) ) {
		$delimiter = ' ' . $m[0] . ' ';
	}

	$crumb = '<span itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">'
		. '<a itemtype="http://schema.org/Thing" itemprop="item" href="' . esc_url( get_permalink( $roster ) ) . '">'
		. '<span itemprop="name">' . esc_html( get_the_title( $roster ) ) . '</span></a>'
		. $delimiter
		. '<meta itemprop="position" content="2" />'
		. '</span>';

	$output = substr( $output, 0, $needle ) . $crumb . substr( $output, $needle );

	// The athlete was position 2 and is now position 3.
	$output = preg_replace_callback(
		'~<meta itemprop="position" content="(\d+)"~',
		function () {
			static $i = 0;
			$i++;
			return '<meta itemprop="position" content="' . $i . '"';
		},
		$output
	);

	return $output;
}
add_filter( 'x_breadcrumbs', 'arv_athlete_breadcrumbs' );
