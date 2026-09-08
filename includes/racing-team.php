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
			'division' => '',
		),
		$atts,
		'arv_racing_team'
	);

	$athletes = arv_athlete_store_get(
		array_filter(
			array(
				'status'   => $atts['status'],
				'division' => $atts['division'],
			)
		)
	);

	if ( empty( $athletes ) ) {
		return '<p class="arv-team-empty">' . esc_html__( 'No athletes to show yet.', 'aravaipa-elements' ) . '</p>';
	}

	// Grouped by division rather than shown as one flat grid of 51, because
	// each division's card art shares a colour: the Arizona banners are red,
	// Colorado's blue, Utah's orange. Mixed together the page reads as noise;
	// grouped, each block is visually coherent on its own.
	$groups = array();

	foreach ( $athletes as $athlete ) {
		$division = ! empty( $athlete['divisions'] ) ? $athlete['divisions'][0] : __( 'Team', 'aravaipa-elements' );
		$groups[ $division ][] = $athlete;
	}

	$groups = arv_racing_team_sort_groups( $groups );

	$out  = '<div class="arv-team" data-arv-team-root>';
	$out .= arv_racing_team_filters_markup( array_keys( $groups ), $atts );
	$out .= '<p class="arv-team__count" data-arv-team-count aria-live="polite"></p>';

	// A single group is already named by whatever heading the page put above
	// the shortcode, so repeating it here printed "Notable Alumni" twice.
	$show_headings = count( $groups ) > 1;

	foreach ( $groups as $division => $members ) {
		$out .= '<section class="arv-team__group" data-arv-team-group="' . esc_attr( sanitize_title( $division ) ) . '">';

		if ( $show_headings ) {
			$out .= '<h2 class="arv-team__group-title">' . esc_html( $division ) . '</h2>';
		}
		$out .= '<div class="arv-team__grid">';

		foreach ( $members as $athlete ) {
			$out .= arv_racing_team_card_markup( $athlete );
		}

		$out .= '</div></section>';
	}

	$out .= '</div>';

	return $out;
}
add_shortcode( 'arv_racing_team', 'arv_racing_team_shortcode' );

/**
 * Put the division groups in the order the old roster page used.
 *
 * Alphabetical would open the roster on California and bury Arizona in the
 * middle, which is backwards for a company headquartered there. Anything
 * not in this list keeps its place after the ones that are, so adding a
 * new division does not require editing this function to make it appear.
 *
 * @param array $groups division name => athletes
 * @return array
 */
function arv_racing_team_sort_groups( $groups ) {
	$order = array(
		'Arizona Team',
		'Colorado Team',
		'California',
		'Utah',
		'Nevada',
		'North East',
		'Great Lakes',
	);

	$sorted = array();

	foreach ( $order as $division ) {
		if ( isset( $groups[ $division ] ) ) {
			$sorted[ $division ] = $groups[ $division ];
			unset( $groups[ $division ] );
		}
	}

	return $sorted + $groups;
}

/**
 * The filter control above the grid.
 *
 * One dropdown, division only, per Jamil: region and division described the
 * same seven buckets on the old page, so offering both was two ways to ask
 * the same question. Options come from the groups actually rendered, so the
 * dropdown can never offer a choice that returns nothing.
 *
 * @param array $divisions Division names, already in display order.
 * @param array $atts
 * @return string
 */
function arv_racing_team_filters_markup( $divisions, $atts ) {
	if ( count( $divisions ) < 2 ) {
		return '';
	}

	$out  = '<div class="arv-team__filters">';
	$out .= '<select class="arv-team__filter" data-arv-team-division aria-label="'
		. esc_attr__( 'Filter by division', 'aravaipa-elements' ) . '">';
	$out .= '<option value="">' . esc_html__( 'All divisions', 'aravaipa-elements' ) . '</option>';

	foreach ( $divisions as $division ) {
		$slug  = sanitize_title( $division );
		$out  .= '<option value="' . esc_attr( $slug ) . '"'
			. selected( sanitize_title( $atts['division'] ), $slug, false ) . '>'
			. esc_html( $division ) . '</option>';
	}

	$out .= '</select></div>';

	return $out;
}

/**
 * One athlete's card in the grid.
 *
 * The division attribute is the term slug, not its name. It was the name
 * lowercased, which meant the dropdown (whose values are slugs) compared
 * "arizona-team" against "arizona team" and matched nothing: picking any
 * option emptied the grid and reported zero athletes.
 *
 * @param array $athlete
 * @return string
 */
function arv_racing_team_card_markup( $athlete ) {
	$slugs = array_map( 'sanitize_title', $athlete['divisions'] );

	$alumni = 'alumni' === $athlete['status'];

	$out  = '<a class="arv-team__card' . ( $alumni ? ' arv-team__card--alumni' : '' ) . '" href="' . esc_url( $athlete['url'] ) . '"';
	$out .= ' data-arv-team-division="' . esc_attr( implode( '|', $slugs ) ) . '">';

	if ( $athlete['photo'] ) {
		$out .= '<img class="arv-team__photo" src="' . esc_url( $athlete['photo'] ) . '" alt="'
			. esc_attr( $athlete['name'] ) . '" loading="lazy" width="400" height="400" />';
	} else {
		// A placeholder rather than nothing: a card with no image collapsed
		// to its text and left the grid ragged around it, which read as
		// broken rather than as "no photo on file".
		$out .= '<span class="arv-team__photo arv-team__photo--none" aria-hidden="true">'
			. esc_html( arv_racing_team_initials( $athlete['name'] ) ) . '</span>';
	}

	$out .= '<span class="arv-team__name">' . esc_html( $athlete['name'] ) . '</span>';

	if ( $alumni ) {
		$out .= '<span class="arv-team__badge">' . esc_html__( 'Alumni', 'aravaipa-elements' ) . '</span>';
	}

	// For an alumnus the interesting line is where they went, not where they
	// live now, and it is the whole reason the section exists.
	if ( $alumni && '' !== $athlete['alumni_note'] ) {
		$out .= '<span class="arv-team__note">' . esc_html( $athlete['alumni_note'] ) . '</span>';
	} elseif ( '' !== $athlete['hometown'] ) {
		$out .= '<span class="arv-team__hometown">' . esc_html( $athlete['hometown'] ) . '</span>';
	}

	$out .= '</a>';

	return $out;
}

/**
 * Initials, for the placeholder shown when an athlete has no photo on file.
 *
 * @param string $name
 * @return string
 */
function arv_racing_team_initials( $name ) {
	$parts    = preg_split( '/\s+/', trim( $name ) );
	$initials = '';

	foreach ( $parts as $part ) {
		if ( '' !== $part ) {
			$initials .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
		}
	}

	return mb_substr( $initials, 0, 2 );
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

	// Division first, then any region that is not just restating it.
	// The divisions carry the old page's own names ("Arizona Team") and the
	// regions are the states inside them ("Arizona"), so showing both put
	// ARIZONA TEAM next to ARIZONA on most profiles, saying one thing twice.
	$tags = $athlete['divisions'];

	foreach ( $athlete['regions'] as $region ) {
		$duplicate = false;

		foreach ( $tags as $shown ) {
			if ( false !== stripos( $shown, $region ) || false !== stripos( $region, $shown ) ) {
				$duplicate = true;
				break;
			}
		}

		if ( ! $duplicate ) {
			$tags[] = $region;
		}
	}

	foreach ( $tags as $tag ) {
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
 * The videos an athlete appears in, as a horizontal row of thumbnails that
 * swap to a player in place when clicked.
 *
 * Click to play rather than embedding three iframes outright: an embed is
 * roughly a megabyte of YouTube player each, a thumbnail is an image, and
 * most visitors scroll past without watching anything. Whoever does want to
 * watch gets the real player without leaving the page.
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

	$out  = '<div class="arv-athlete__videos"><h2>' . esc_html__( 'Watch', 'aravaipa-elements' ) . '</h2>';
	$out .= '<ul class="arv-athlete__videos-list" data-arv-video-row>';

	foreach ( $urls as $url ) {
		$id = arv_athlete_youtube_id( $url );

		if ( '' === $id ) {
			continue;
		}

		$meta = arv_athlete_video_meta( $url );

		$out .= '<li class="arv-athlete__video">';
		$out .= '<button type="button" class="arv-athlete__video-play" data-arv-video-id="' . esc_attr( $id ) . '">';

		if ( '' !== $meta['thumbnail'] ) {
			$out .= '<img class="arv-athlete__video-thumb" src="' . esc_url( $meta['thumbnail'] ) . '" alt="" loading="lazy" width="320" height="180" />';
		}

		$out .= '<span class="arv-athlete__video-title">' . esc_html( $meta['title'] ) . '</span>';
		$out .= '</button></li>';
	}

	$out .= '</ul></div>';

	return $out;
}

/**
 * The eleven-character video id out of any YouTube URL shape.
 *
 * The roster migration scraped these out of iframe src attributes, so what
 * is stored is the /embed/ form. That matters twice: oEmbed answers 404 for
 * an /embed/ URL and 200 for a /watch? one, which is why every title on
 * every athlete page silently fell back to "Watch on YouTube", and the
 * player needs the bare id anyway.
 *
 * @param string $url
 * @return string
 */
function arv_athlete_youtube_id( $url ) {
	$patterns = array(
		'~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
		'~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~',
		'~youtu\.be/([A-Za-z0-9_-]{11})~',
		'~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $url, $m ) ) {
			return $m[1];
		}
	}

	return '';
}

/**
 * A video's title and thumbnail, from YouTube's oEmbed endpoint.
 *
 * Cached for a week. A failed lookup caches for an hour instead, so a
 * transient network problem does not pin a blank title in place for the
 * full week.
 *
 * @param string $url
 * @return array{title: string, thumbnail: string}
 */
function arv_athlete_video_meta( $url ) {
	$id = arv_athlete_youtube_id( $url );

	if ( '' === $id ) {
		return array( 'title' => __( 'Watch on YouTube', 'aravaipa-elements' ), 'thumbnail' => '' );
	}

	// Keyed on the id, not the URL: the same video stored as an /embed/ URL
	// on one athlete and a /watch? URL on another is one video.
	$key    = 'arv_vid_' . $id;
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	// oEmbed only answers for a watch URL, whatever shape came in.
	$watch = 'https://www.youtube.com/watch?v=' . $id;

	$fallback = array(
		'title'     => __( 'Watch on YouTube', 'aravaipa-elements' ),
		// The thumbnail is predictable from the id, so a failed title lookup
		// still leaves a real image rather than an empty card.
		'thumbnail' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
	);

	$response = wp_remote_get(
		'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( $watch ),
		array( 'timeout' => 5 )
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		set_transient( $key, $fallback, HOUR_IN_SECONDS );
		return $fallback;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || empty( $body['title'] ) ) {
		set_transient( $key, $fallback, HOUR_IN_SECONDS );
		return $fallback;
	}

	$meta = array(
		'title'     => (string) $body['title'],
		'thumbnail' => ! empty( $body['thumbnail_url'] ) ? (string) $body['thumbnail_url'] : $fallback['thumbnail'],
	);

	set_transient( $key, $meta, WEEK_IN_SECONDS );

	return $meta;
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
		$links[] = array(
			'url'   => 'https://www.instagram.com/' . ltrim( $athlete['instagram'], '@' ),
			'label' => $athlete['instagram'],
			'icon'  => 'instagram',
		);
	}

	if ( '' !== $athlete['strava'] ) {
		$links[] = array( 'url' => $athlete['strava'], 'label' => 'Strava', 'icon' => 'strava' );
	}

	if ( '' !== $athlete['ultrasignup_url'] ) {
		$links[] = array( 'url' => $athlete['ultrasignup_url'], 'label' => 'UltraSignup', 'icon' => '' );
	}

	if ( '' !== $athlete['ultrarunning_url'] ) {
		$links[] = array( 'url' => $athlete['ultrarunning_url'], 'label' => 'UltraRunning Magazine', 'icon' => '' );
	}

	if ( empty( $links ) ) {
		return '';
	}

	$out = '<ul class="arv-athlete__links">';

	foreach ( $links as $link ) {
		$out .= '<li><a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener">'
			. arv_athlete_social_icon( $link['icon'] )
			. '<span>' . esc_html( $link['label'] ) . '</span></a></li>';
	}

	$out .= '</ul>';

	return $out;
}

/**
 * An inline SVG mark for a social link.
 *
 * Inline rather than an icon font or an image request: it is two links per
 * page, the paths are tiny, and this way the mark inherits currentColor and
 * cannot flash unstyled while a font loads.
 *
 * @param string $name
 * @return string
 */
function arv_athlete_social_icon( $name ) {
	$icons = array(
		'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.2.05 1.8.25 2.2.42.6.22 1 .48 1.4.9.43.44.7.83.9 1.4.18.4.38 1 .43 2.2.06 1.3.07 1.7.07 4.9s0 3.6-.07 4.9c-.05 1.2-.25 1.8-.42 2.2-.22.6-.48 1-.9 1.4-.44.43-.83.7-1.4.9-.4.18-1 .38-2.2.43-1.3.06-1.7.07-4.9.07s-3.6 0-4.9-.07c-1.2-.05-1.8-.25-2.2-.42-.6-.22-1-.48-1.4-.9-.43-.44-.7-.83-.9-1.4-.18-.4-.38-1-.43-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.9c.05-1.2.25-1.8.42-2.2.22-.6.48-1 .9-1.4.44-.43.83-.7 1.4-.9.4-.18 1-.38 2.2-.43C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.8.07-1.1.05-1.7.24-2.1.4-.5.2-.9.44-1.3.84-.4.4-.64.8-.84 1.3-.16.4-.35 1-.4 2.1C2.5 8.5 2.5 8.9 2.5 12s0 3.5.07 4.8c.05 1.1.24 1.7.4 2.1.2.5.44.9.84 1.3.4.4.8.64 1.3.84.4.16 1 .35 2.1.4 1.3.06 1.7.07 4.8.07s3.5 0 4.8-.07c1.1-.05 1.7-.24 2.1-.4.5-.2.9-.44 1.3-.84.4-.4.64-.8.84-1.3.16-.4.35-1 .4-2.1.06-1.3.07-1.7.07-4.8s0-3.5-.07-4.8c-.05-1.1-.24-1.7-.4-2.1-.2-.5-.44-.9-.84-1.3-.4-.4-.8-.64-1.3-.84-.4-.16-1-.35-2.1-.4C15.5 4 15.1 4 12 4z"/><path d="M12 15.3a3.3 3.3 0 1 1 0-6.6 3.3 3.3 0 0 1 0 6.6zm0-8.4a5.1 5.1 0 1 0 0 10.2 5.1 5.1 0 0 0 0-10.2z"/><circle cx="17.3" cy="6.7" r="1.2"/>',
		'strava'    => '<path d="M13.8 2 7 15.2h4l2.8-5.5 2.8 5.5h4L13.8 2zm2.8 13.2-1.9 3.7-1.9-3.7H9.9L14.7 24l4.8-8.8h-2.9z"/>',
	);

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	return '<svg class="arv-athlete__icon" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">'
		. $icons[ $name ] . '</svg>';
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
		. '<span itemprop="name">' . esc_html( arv_athlete_breadcrumb_label( $roster ) ) . '</span></a>'
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
/**
 * The label for the roster crumb.
 *
 * Not the page's own title: that is "Aravaipa Racing Team Powered By HOKA",
 * which is right at the top of the page it names and far too long sitting
 * between a house icon and an athlete's name. Filterable so the sponsor
 * changing does not mean editing this file.
 *
 * @param WP_Post $roster
 * @return string
 */
function arv_athlete_breadcrumb_label( $roster ) {
	return (string) apply_filters( 'arv_athlete_breadcrumb_label', __( 'Racing Team', 'aravaipa-elements' ), $roster );
}

// Priority 50, not the default 10. The theme calls
// apply_filters( 'x_breadcrumbs', '', $args ) with an empty string and
// Cornerstone's own Breadcrumbs::outputHtml is what actually builds the
// markup, also at 10. Registered first, this filter was handed '' and
// returned early every time, so the fix silently did nothing on a live
// page while looking correct in isolation.
add_filter( 'x_breadcrumbs', 'arv_athlete_breadcrumbs', 50 );
