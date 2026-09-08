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
 * [arv_racing_team division="great-lakes"]
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
		'Arizona',
		'Colorado',
		'California',
		'Utah',
		'Nevada',
		'Northeast',
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
 * The controls above the grid: a search box and one button per division.
 *
 * Buttons rather than a dropdown because there are only seven divisions and
 * a dropdown hides six of them behind a click. Laid out as a row, every
 * option is readable at a glance and the current one is visible without
 * opening anything.
 *
 * Division only, per Jamil: region and division described the same seven
 * buckets on the old page, so offering both was two ways to ask the same
 * question. The buttons come from the groups actually rendered, so no
 * button can ever return an empty grid.
 *
 * Search covers name and hometown. Both are already on the card, and
 * hometown is what makes "who do we have in Colorado" answerable by typing
 * rather than by reading all fifty one cards.
 *
 * @param array $divisions Division names, already in display order.
 * @param array $atts
 * @return string
 */
function arv_racing_team_filters_markup( $divisions, $atts ) {
	// The alumni block is one group and renders from a second call to this
	// shortcode on the same page. Giving it its own controls put a second
	// search box under the roster that filtered nothing, so the block with
	// something to filter is the only one that gets them. The script reaches
	// every block from that one search box.
	if ( count( $divisions ) < 2 ) {
		return '';
	}

	$active = sanitize_title( $atts['division'] );

	$out  = '<div class="arv-team__controls">';
	$out .= '<input type="search" class="arv-team__search" data-arv-team-search'
		. ' placeholder="' . esc_attr__( 'Search by name or hometown', 'aravaipa-elements' ) . '"'
		. ' aria-label="' . esc_attr__( 'Search athletes', 'aravaipa-elements' ) . '" />';

	$out .= '<div class="arv-team__filters" role="group" aria-label="'
		. esc_attr__( 'Filter by division', 'aravaipa-elements' ) . '">';

	$out .= arv_racing_team_filter_button( '', __( 'All', 'aravaipa-elements' ), '' === $active );

	foreach ( $divisions as $division ) {
		$slug = sanitize_title( $division );
		$out .= arv_racing_team_filter_button( $slug, $division, $slug === $active );
	}

	$out .= '</div></div>';

	return $out;
}

/**
 * One division button.
 *
 * aria-pressed rather than a disabled state or a link: this is a toggle that
 * changes what is already on screen, so a screen reader should hear which
 * one is on, and nothing here navigates.
 *
 * @param string $slug   Division slug, empty for "All".
 * @param string $label
 * @param bool   $active
 * @return string
 */
function arv_racing_team_filter_button( $slug, $label, $active ) {
	return '<button type="button" class="arv-team__filter' . ( $active ? ' is-active' : '' ) . '"'
		. ' data-arv-team-division="' . esc_attr( $slug ) . '"'
		. ' aria-pressed="' . ( $active ? 'true' : 'false' ) . '">'
		. esc_html( $label ) . '</button>';
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

	// Name and hometown, lowercased once here rather than on every keystroke
	// in the browser. This is what the search box matches against.
	$haystack = strtolower( trim( $athlete['name'] . ' ' . $athlete['hometown'] ) );

	$out  = '<a class="arv-team__card' . ( $alumni ? ' arv-team__card--alumni' : '' ) . '" href="' . esc_url( $athlete['url'] ) . '"';
	$out .= ' data-arv-team-division="' . esc_attr( implode( '|', $slugs ) ) . '"';
	$out .= ' data-arv-team-text="' . esc_attr( $haystack ) . '">';

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
		. ( function_exists( 'arv_athlete_profile_upcoming_markup' ) ? arv_athlete_profile_upcoming_markup( $athlete ) : '' )
		. ( function_exists( 'arv_athlete_profile_season_results_markup' ) ? arv_athlete_profile_season_results_markup( $athlete ) : '' )
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

	// Division first, then any region that is not just restating it. Most
	// divisions are named for the one state in them, so showing both put
	// ARIZONA next to ARIZONA on most profiles, saying one thing twice.
	// Northeast and Great Lakes are the ones where the region still adds
	// something (New Hampshire, Michigan), and those survive the check.
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
 * The videos an athlete appears in: one large player, with the rest of the
 * videos as a row of thumbnails scrolling sideways underneath it.
 *
 * The stage ships showing the first video's poster, not an empty box and
 * not a loaded player. A still image is what makes the section read as a
 * feature rather than a row of equal thumbnails, and it costs one JPEG
 * instead of the roughly one megabyte a YouTube embed weighs. The player
 * itself only loads once someone actually asks for it.
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

	$videos = array();

	foreach ( $urls as $url ) {
		$id = arv_athlete_youtube_id( $url );

		if ( '' === $id ) {
			continue;
		}

		$meta         = arv_athlete_video_meta( $url );
		$meta['id']   = $id;
		$videos[]     = $meta;
	}

	if ( empty( $videos ) ) {
		return '';
	}

	$cards = '';

	foreach ( $videos as $i => $video ) {
		// The first one is what the stage is already showing, so it starts
		// marked: dimmed and without its play badge, the same state a
		// thumbnail takes once it has been picked.
		$active = ( 0 === $i );

		$cards .= '<li class="arv-athlete__video">';
		$cards .= '<button type="button" class="arv-athlete__video-play' . ( $active ? ' is-playing' : '' ) . '"'
			. ' aria-pressed="' . ( $active ? 'true' : 'false' ) . '"'
			. ' data-arv-video-id="' . esc_attr( $video['id'] ) . '"'
			. ' data-arv-video-title="' . esc_attr( $video['title'] ) . '">';

		if ( '' !== $video['thumbnail'] ) {
			$cards .= '<img class="arv-athlete__video-thumb" src="' . esc_url( $video['thumbnail'] ) . '" alt="" loading="lazy" width="320" height="180" />';
		}

		$cards .= '<span class="arv-athlete__video-title">' . esc_html( $video['title'] ) . '</span>';
		$cards .= '</button></li>';
	}

	$out  = '<div class="arv-athlete__videos" data-arv-videos><h2>' . esc_html__( 'Watch', 'aravaipa-elements' ) . '</h2>';
	$out .= '<div class="arv-athlete__video-stage" data-arv-video-stage>'
		. arv_athlete_video_poster_markup( $videos[0] ) . '</div>';
	$out .= '<ul class="arv-athlete__videos-list" data-arv-video-row>' . $cards . '</ul>';
	$out .= '</div>';

	return $out;
}

/**
 * The still that fills the stage before anyone presses play.
 *
 * Served at hqdefault, which YouTube has for every video. Roughly nine in
 * ten also have a maxresdefault, which is the real 16:9 frame rather than
 * a 4:3 one that has to be cropped, but the other one in ten 404s. The
 * upgrade is left to the script, which only swaps the source once the
 * bigger file has actually loaded, so a missing one is never seen.
 *
 * @param array $video id, title, thumbnail
 * @return string
 */
function arv_athlete_video_poster_markup( $video ) {
	if ( '' === $video['thumbnail'] ) {
		return '';
	}

	$out  = '<button type="button" class="arv-athlete__video-poster"'
		. ' data-arv-video-id="' . esc_attr( $video['id'] ) . '"'
		. ' data-arv-video-title="' . esc_attr( $video['title'] ) . '"'
		. ' aria-label="' . esc_attr( sprintf( __( 'Play %s', 'aravaipa-elements' ), $video['title'] ) ) . '">';
	$out .= '<img class="arv-athlete__video-poster-img"'
		. ' src="' . esc_url( $video['thumbnail'] ) . '"'
		. ' data-arv-video-hires="' . esc_url( 'https://i.ytimg.com/vi/' . $video['id'] . '/maxresdefault.jpg' ) . '"'
		. ' alt="" loading="lazy" width="1280" height="720" />';
	$out .= '</button>';

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
	// The official marks, taken from Simple Icons rather than approximated.
	// These were hand-drawn from memory first and looked it: the Instagram
	// glyph was a rough rounded square and the Strava one a plain triangle
	// that is not the chevron Strava actually uses.
	$icons = array(
		'instagram' => '<path d="M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077"/>',
		'strava'    => '<path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.828h4.169"/>',
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
