<?php
/**
 * Pure HTML renderers for the Rides layout. No WordPress calls in here, so the
 * local harness can render every page without a site.
 *
 * Every renderer takes a $ctx array:
 *   base   URL of the plugin folder, trailing slash (assets/heroes/... hang off it)
 *   today  Y-m-d used to mark past rides
 */

if ( ! defined( 'RIDES_DIR' ) ) {
	exit;
}

/**
 * Escape text for HTML.
 *
 * @param mixed $s Text.
 * @return string
 */
function rides_e( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/**
 * Asset URL inside the plugin.
 *
 * @param array  $ctx  Render context.
 * @param string $path Relative path.
 * @return string
 */
function rides_asset( $ctx, $path ) {
	return rtrim( $ctx['base'], '/' ) . '/' . ltrim( $path, '/' );
}

/**
 * URL of a ride logo: local plugin assets resolve against the render base, full URLs pass through.
 *
 * @param array  $ctx  Render context.
 * @param string $logo Logo field (assets/logos/<slug>.png or a URL).
 * @return string
 */
function rides_logo_src( $ctx, $logo ) {
	if ( '' === (string) $logo || preg_match( '#^https?://#i', $logo ) ) {
		return (string) $logo;
	}
	return rides_asset( $ctx, $logo );
}

/**
 * Ride logo as an HTML image (never composited into a photo). Ink logos that vanish on
 * night backgrounds (logo_plate "light" in rides.json) sit on a flat sand plate.
 *
 * @param array  $ctx   Render context.
 * @param array  $e     Event row.
 * @param string $class Class for the img.
 * @param array  $attrs Extra attributes (name => value).
 * @param bool   $plate Allow the sand plate (event heroes pass false: logo as artwork).
 * @return string
 */
function rides_logo_img( $ctx, $e, $class, $attrs = array(), $plate = true ) {
	if ( empty( $e['logo'] ) ) {
		return '';
	}
	$cls  = $class . ( $plate && isset( $e['logo_plate'] ) && 'light' === $e['logo_plate'] ? ' is-plate' : '' );
	$size = '';
	$file = RIDES_DIR . '/' . $e['logo'];
	if ( ! preg_match( '#^https?://#i', $e['logo'] ) && is_readable( $file ) ) {
		$dim = getimagesize( $file );
		if ( $dim ) {
			$size = ' width="' . (int) $dim[0] . '" height="' . (int) $dim[1] . '"';
		}
	}
	$extra = '';
	foreach ( $attrs as $k => $v ) {
		$extra .= ' ' . $k . '="' . rides_e( $v ) . '"';
	}
	return '<img class="' . rides_e( $cls ) . '" src="' . rides_e( rides_logo_src( $ctx, $e['logo'] ) ) . '" alt="' . rides_e( $e['name'] ) . ' logo"' . $size . $extra . '>';
}

/**
 * True when the clean hero photo for a ride ships with the plugin.
 *
 * @param array $e Event row.
 * @return bool
 */
function rides_has_hero( $e ) {
	return ! empty( $e['hero'] ) && is_readable( RIDES_DIR . '/' . $e['hero'] );
}

/**
 * Hero URL for a ride, or '' when none ships.
 *
 * @param array $ctx Render context.
 * @param array $e   Event row.
 * @return string
 */
function rides_hero_url( $ctx, $e ) {
	return rides_has_hero( $e ) ? rides_asset( $ctx, $e['hero'] ) : '';
}

/**
 * "Venue, City" line.
 *
 * @param array $e Event row.
 * @return string
 */
function rides_place( $e ) {
	$loc = isset( $e['location'] ) ? $e['location'] : array();
	if ( is_string( $loc ) ) {
		return $loc;
	}
	return trim( implode( ', ', array_filter( array( $loc['venue'] ?? '', $loc['city'] ?? '' ) ) ) );
}

/**
 * City only (short place for slides and tiles).
 *
 * @param array $e Event row.
 * @return string
 */
function rides_city( $e ) {
	$loc = isset( $e['location'] ) ? $e['location'] : array();
	return is_array( $loc ) ? (string) ( $loc['city'] ?? '' ) : (string) $loc;
}

/**
 * Primary register link of a ride as it is live today.
 *
 * @param array $e Event row.
 * @return string
 */
function rides_register_href( $e ) {
	return ! empty( $e['register'] ) ? (string) $e['register'][0] : '';
}

/**
 * Every register link of a ride exactly as live today, with labels: the event page data
 * labels when it has them (Prickly: MTB and Trail Run), else register_label.
 *
 * @param array $e Event row.
 * @return array [{label, href}]
 */
function rides_register_links( $e ) {
	$labels = array();
	$d      = rides_data( 'events/' . $e['slug'] );
	foreach ( (array) ( $d['register'] ?? array() ) as $l ) {
		if ( ! empty( $l['href'] ) ) {
			$labels[ $l['href'] ] = $l['label'];
		}
	}
	$out = array();
	foreach ( (array) ( $e['register'] ?? array() ) as $href ) {
		$out[] = array(
			'href'  => (string) $href,
			'label' => $labels[ $href ] ?? ( ! empty( $e['register_label'] ) ? $e['register_label'] : 'Register Now' ),
		);
	}
	return $out;
}

/**
 * Root wrapper.
 *
 * @param string $page  Page key.
 * @param string $inner Inner HTML.
 * @return string
 */
function rides_root( $page, $inner ) {
	return '<div class="rides-v2 rides-page-' . rides_e( $page ) . '">' . $inner . '</div>';
}

/* ------------------------------------------------------------- slider */

/**
 * Full width rotating hero slider, one slide per upcoming ride.
 *
 * @param array $ctx Render context.
 * @return string
 */
function rides_render_slider( $ctx ) {
	$events = array_values(
		array_filter(
			rides_events( $ctx['today'] ),
			function ( $e ) {
				return ! $e['is_past'] && rides_has_hero( $e );
			}
		)
	);
	if ( ! $events ) {
		return '';
	}
	$n     = count( $events );
	$out   = '<section class="rides-slider" data-rides-slider aria-roledescription="carousel" aria-label="Upcoming rides" tabindex="0">';
	$out  .= '<div class="rides-slides">';
	foreach ( $events as $i => $e ) {
		$first  = 0 === $i;
		$hero   = rides_hero_url( $ctx, $e );
		$img    = $i < 2
			? '<img class="rides-slide-img" src="' . rides_e( $hero ) . '" alt="" width="1920" height="900"' . ( $first ? ' fetchpriority="high"' : '' ) . '>'
			: '<img class="rides-slide-img" data-src="' . rides_e( $hero ) . '" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" width="1920" height="900">';
		$out   .= '<article class="rides-slide' . ( $first ? ' is-active' : '' ) . '" aria-roledescription="slide" aria-label="' . ( $i + 1 ) . ' of ' . $n . '" aria-hidden="' . ( $first ? 'false' : 'true' ) . '">';
		$out   .= $img . '<div class="rides-slide-scrim" aria-hidden="true"></div>';
		$out   .= '<div class="rides-slide-body">';
		$out   .= '<div class="rides-slide-mark">' . rides_logo_img( $ctx, $e, 'rides-slide-logo', $i < 2 ? array( 'decoding' => 'async' ) : array( 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</div>';
		$out   .= '<div class="rides-slide-text">';
		$out   .= '<p class="rides-slide-kicker rides-eyebrow">' . rides_e( $e['date_label'] ) . '</p>';
		$out   .= '<h2>' . rides_e( $e['name'] ) . '</h2>';
		$out   .= '<p class="rides-slide-meta">' . rides_e( implode( ' | ', (array) $e['distances'] ) ) . '<span>' . rides_e( rides_place( $e ) ) . '</span></p>';
		$out   .= '<div class="rides-actions">';
		$out   .= '<a class="rides-btn rides-btn--ghost" href="' . rides_e( $e['url'] ) . '"' . ( $first ? '' : ' tabindex="-1"' ) . '>More Info</a>';
		foreach ( rides_register_links( $e ) as $l ) {
			$out .= '<a class="rides-btn" href="' . rides_e( $l['href'] ) . '"' . ( $first ? '' : ' tabindex="-1"' ) . '>' . rides_e( $l['label'] ) . '</a>';
		}
		$out .= '</div></div></div></article>';
	}
	$out .= '</div>';
	$out .= '<div class="rides-slider-nav">';
	$out .= '<div class="rides-dots" role="group" aria-label="Choose a ride">';
	foreach ( $events as $i => $e ) {
		$out .= '<button type="button" class="rides-dot' . ( 0 === $i ? ' is-active' : '' ) . '" data-rides-dot="' . $i . '" aria-current="' . ( 0 === $i ? 'true' : 'false' ) . '"><span class="rides-sr">' . rides_e( $e['name'] ) . '</span></button>';
	}
	$out .= '</div><div class="rides-slider-arrows">';
	$out .= '<button type="button" class="rides-arrow" data-rides-prev aria-label="Previous ride">&#8592;</button>';
	$out .= '<button type="button" class="rides-arrow" data-rides-next aria-label="Next ride">&#8594;</button>';
	$out .= '</div></div>';
	$out .= '<p class="rides-sr" aria-live="polite" data-rides-live></p>';
	$out .= '</section>';
	return $out;
}

/* -------------------------------------------------------------- tiles */

/**
 * One uniform event tile.
 *
 * @param array $ctx Render context.
 * @param array $e   Event row.
 * @return string
 */
function rides_render_tile( $ctx, $e ) {
	$hero  = rides_hero_url( $ctx, $e );
	$past  = ! empty( $e['is_past'] );
	$media = '<div class="rides-tile-media">'
		. ( $hero ? '<img class="rides-tile-photo" src="' . rides_e( $hero ) . '" alt="" loading="lazy" width="640" height="400">' : '' )
		. rides_logo_img( $ctx, $e, 'rides-tile-mark', array( 'decoding' => 'async' ) )
		. '</div>';
	$date  = $past ? 'Past event | ' . $e['date_label'] : $e['date_label'];
	return '<li><a class="rides-tile' . ( $past ? ' is-past' : '' ) . '" href="' . rides_e( $e['url'] ) . '">'
		. $media
		. '<div class="rides-tile-body">'
		. '<p class="rides-tile-date">' . rides_e( $date ) . '</p>'
		. '<h3>' . rides_e( $e['name'] ) . '</h3>'
		. '<p class="rides-tile-place">' . rides_e( rides_place( $e ) ) . '</p>'
		. '<p class="rides-tile-dist">' . rides_e( implode( ' | ', (array) $e['distances'] ) ) . '</p>'
		. '<span class="rides-tile-more">More Info</span>'
		. '</div></a></li>';
}

/**
 * Tiles grid.
 *
 * @param array $ctx    Render context.
 * @param array $events Event rows.
 * @return string
 */
function rides_render_tiles( $ctx, $events ) {
	$out = '<ul class="rides-tiles">';
	foreach ( $events as $e ) {
		$out .= rides_render_tile( $ctx, $e );
	}
	return $out . '</ul>';
}

/**
 * Page header band with a clean photo.
 *
 * @param array  $ctx   Render context.
 * @param string $title H1.
 * @param string $lead  Sub line.
 * @param string $slug  Ride whose hero to use.
 * @return string
 */
function rides_render_pagehead( $ctx, $title, $lead, $slug ) {
	$e    = rides_event( $slug );
	$hero = $e ? rides_hero_url( $ctx, $e ) : '';
	return '<header class="rides-pagehead">'
		. ( $hero ? '<img src="' . rides_e( $hero ) . '" alt="" width="1920" height="900">' : '' )
		. '<div class="rides-pagehead-body"><p class="rides-eyebrow">Aravaipa Rides</p><h1>' . rides_e( $title ) . '</h1>'
		. ( $lead ? '<p>' . rides_e( $lead ) . '</p>' : '' )
		. '</div></header>';
}

/* -------------------------------------------------------------- pages */

/**
 * Homepage: slider, then upcoming tiles.
 *
 * @param array $ctx Render context.
 * @return string
 */
function rides_render_home( $ctx ) {
	$events = rides_events( $ctx['today'] );
	$up     = array_values(
		array_filter(
			$events,
			function ( $e ) {
				return ! $e['is_past'];
			}
		)
	);
	$out  = rides_render_slider( $ctx );
	$out .= rides_render_intro();
	$out .= '<section class="rides-section rides-section--night"><div class="rides-wrap">';
	$out .= '<p class="rides-eyebrow">Mountain bike and gravel events in Arizona and Colorado</p>';
	$out .= '<h2 class="rides-h2">Upcoming Rides</h2>';
	$out .= rides_render_tiles( $ctx, $up ? $up : $events );
	$out .= '<div class="rides-actions rides-more-links"><a class="rides-btn rides-btn--line" href="/events/">All Events</a><a class="rides-btn rides-btn--line" href="/results/">Results</a><a class="rides-btn rides-btn--line" href="/photos/">Photos</a></div>';
	$out .= '</div></section>';
	$out .= rides_render_community();
	return rides_root( 'home', $out );
}

/**
 * Short welcome block, trimmed from the live homepage copy.
 *
 * @return string
 */
function rides_render_intro() {
	return '<section class="rides-section rides-intro"><div class="rides-wrap rides-intro-grid">'
		. '<div><p class="rides-eyebrow">Endurance mountain bike events</p><h2 class="rides-h2">Welcome to Aravaipa Rides</h2></div>'
		. '<div class="rides-intro-copy">'
		. '<p>We are an endurance mountain bike event company based in Phoenix, Arizona and Colorado Springs, Colorado. If you know the name Aravaipa, you know we come from a long background of endurance trail and ultra running.</p>'
		. '<p class="rides-intro-big">We are bringing our expertise in trail events to the two wheeled space and we have never been more stoked.</p>'
		. '<p>Our events aim to be fun, inclusive and stunningly beautiful.</p>'
		. '</div></div></section>';
}

/**
 * Community: latest YouTube videos, the podcast and the Strava club. Every box has content:
 * videos fall back to the list in community.json, the podcast is a Spotify embed plus links.
 *
 * @return string
 */
function rides_render_community() {
	$c      = rides_data( 'community' );
	$videos = rides_latest_videos( 3 );
	$pod    = (array) ( $c['podcast'] ?? array() );
	$strava = (array) ( $c['strava'] ?? array() );
	$sub    = ! empty( $c['channel_url'] ) ? $c['channel_url'] : 'https://www.youtube.com/channel/' . RIDES_YT_CHANNEL . '?sub_confirmation=1';
	$out    = '<section class="rides-section rides-section--night rides-community" id="community"><div class="rides-wrap">';
	$out   .= '<p class="rides-eyebrow">Community</p><h2 class="rides-h2">Ride With The Crew</h2>';
	if ( $videos ) {
		$out .= '<div class="rides-comm-head"><h3>Aravaipa Rides on YouTube</h3><a class="rides-btn" href="' . rides_e( $sub ) . '" rel="noopener">Subscribe</a></div>';
		$out .= '<ul class="rides-videos">';
		foreach ( $videos as $v ) {
			$out .= '<li class="rides-video"><div class="rides-video-frame"><iframe src="https://www.youtube-nocookie.com/embed/' . rides_e( $v['id'] ) . '?rel=0" title="' . rides_e( $v['title'] ) . '" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>'
				. '<p>' . rides_e( $v['title'] ) . '</p></li>';
		}
		$out .= '</ul>';
	}
	$out .= '<div class="rides-comm-two">';
	if ( ! empty( $pod['spotify_show'] ) ) {
		$out .= '<div class="rides-comm-block rides-podcast"><h3>' . rides_e( $pod['name'] ?? 'Aravaipa Rides Podcast' ) . '</h3>'
			. '<p class="rides-comm-sub">With ' . rides_e( $pod['host'] ?? 'Aaron Schmidt' ) . '</p>'
			. ( ! empty( $pod['blurb'] ) ? '<p>' . rides_e( $pod['blurb'] ) . '</p>' : '' )
			. '<iframe class="rides-spotify" src="https://open.spotify.com/embed/show/' . rides_e( $pod['spotify_show'] ) . '?theme=0" title="Aravaipa Rides Podcast on Spotify" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>'
			. '<div class="rides-actions">'
			. '<a class="rides-btn" href="' . rides_e( $pod['spotify_url'] ?? '' ) . '" rel="noopener">Listen on Spotify</a>'
			. '<a class="rides-btn rides-btn--ghost" href="' . rides_e( $pod['apple_url'] ?? '' ) . '" rel="noopener">Apple Podcasts</a>'
			. '</div></div>';
	}
	if ( ! empty( $strava['url'] ) ) {
		$out .= '<div class="rides-comm-block rides-strava"><h3>' . rides_e( $strava['name'] ?? 'Aravaipa Rides on Strava' ) . '</h3>'
			. '<p>' . rides_e( $strava['blurb'] ?? '' ) . '</p>'
			. '<div class="rides-actions"><a class="rides-btn" href="' . rides_e( $strava['url'] ) . '" rel="noopener">Join the Strava Club</a></div></div>';
	}
	$out .= '</div></div></section>';
	return $out;
}

/**
 * Events page: every ride as a uniform tile, upcoming first.
 *
 * @param array $ctx Render context.
 * @return string
 */
function rides_render_events( $ctx ) {
	$events = rides_events( $ctx['today'] );
	$out    = rides_render_pagehead( $ctx, 'Events', 'Every Aravaipa Rides event, soonest first.', 'stunner' );
	$out   .= '<section class="rides-section rides-section--night"><div class="rides-wrap">' . rides_render_tiles( $ctx, $events ) . '</div></section>';
	return rides_root( 'events', $out );
}

/**
 * Rows from results.json or photos.json grouped by ride, newest year first.
 *
 * @param array $rows Data rows.
 * @return array slug => rows
 */
function rides_group_rows( $rows ) {
	$by = array();
	foreach ( $rows as $r ) {
		if ( empty( $r['url'] ) ) {
			continue;
		}
		$by[ $r['slug'] ][] = $r;
	}
	foreach ( $by as $slug => $list ) {
		usort(
			$list,
			function ( $a, $b ) {
				return ( (int) $b['year'] <=> (int) $a['year'] ) ?: strcmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			}
		);
		$by[ $slug ] = $list;
	}
	return $by;
}

/**
 * Display name for a slug that may not be a current ride (retired races).
 *
 * @param array  $rows Rows of that slug.
 * @param string $slug Slug.
 * @return array [name, logo]
 */
function rides_row_ride( $rows, $slug ) {
	$e = rides_event( $slug );
	if ( $e ) {
		return array( $e['name'], $e['logo'] );
	}
	$name = ! empty( $rows[0]['ride'] ) ? $rows[0]['ride'] : ucwords( str_replace( '-', ' ', $slug ) );
	$logo = ! empty( $rows[0]['logo'] ) ? $rows[0]['logo'] : '';
	return array( $name, $logo );
}

/**
 * Up to three initials of a ride name, for rides without a logo.
 *
 * @param string $name Ride name.
 * @return string
 */
function rides_initials( $name ) {
	$out = '';
	foreach ( preg_split( '/\s+/', trim( $name ) ) as $w ) {
		if ( preg_match( '/^[A-Za-z]/', $w ) && ! in_array( strtolower( $w ), array( 'rides', 'ride', 'night', 'race', 'mtb', 'the' ), true ) ) {
			$out .= strtoupper( $w[0] );
		}
	}
	return substr( $out ? $out : 'AR', 0, 3 );
}

/**
 * Column of a list of rows (no WordPress dependency, harness-safe).
 *
 * @param array  $rows Rows.
 * @param string $key  Key.
 * @return array
 */
function rides_pluck( $rows, $key ) {
	$out = array();
	foreach ( $rows as $r ) {
		$out[] = isset( $r[ $key ] ) ? $r[ $key ] : '';
	}
	return $out;
}

/**
 * Results or photos list, grouped by ride then year.
 *
 * @param string $kind results|photos.
 * @param array  $rows Data rows.
 * @return string
 */
function rides_render_list( $kind, $rows, $ctx = array( 'base' => '' ) ) {
	$by    = rides_group_rows( $rows );
	$order = array();
	foreach ( rides_events() as $e ) {
		if ( isset( $by[ $e['slug'] ] ) ) {
			$order[] = $e['slug'];
		}
	}
	foreach ( array_keys( $by ) as $slug ) {
		if ( ! in_array( $slug, $order, true ) ) {
			$order[] = $slug;
		}
	}
	$jump = '<ul class="rides-list-jump">';
	$list = '<ul class="rides-' . rides_e( $kind ) . '">';
	foreach ( $order as $slug ) {
		list( $name, $logo ) = rides_row_ride( $by[ $slug ], $slug );
		$id    = 'rides-' . $kind . '-' . $slug;
		$jump .= '<li><a href="#' . rides_e( $id ) . '">' . rides_e( $name ) . '</a></li>';
		$list .= '<li class="rides-list-ride" id="' . rides_e( $id ) . '">';
		$ev    = rides_event( $slug );
		$plate = $ev && isset( $ev['logo_plate'] ) && 'light' === $ev['logo_plate'];
		$list .= '<div class="rides-list-logo' . ( $plate ? ' is-plate' : '' ) . '">' . ( $logo ? '<img src="' . rides_e( rides_logo_src( $ctx, $logo ) ) . '" alt="" loading="lazy">' : '<span class="rides-list-initials" aria-hidden="true">' . rides_e( rides_initials( $name ) ) . '</span>' ) . '</div>';
		$list .= '<div><h3>' . rides_e( $name ) . '</h3><ul class="rides-years">';
		$years = array_count_values( array_map( 'strval', rides_pluck( $by[ $slug ], 'year' ) ) );
		foreach ( $by[ $slug ] as $r ) {
			$label = ! empty( $r['label'] ) && 'Aravaipa Timing' !== $r['label'] ? $r['label'] : '';
			if ( '' === $label && $years[ (string) $r['year'] ] > 1 && ! empty( $r['date_label'] ) && preg_match( '/^([A-Z][a-z]+)/', $r['date_label'], $mm ) ) {
				$label = $mm[1]; // Two editions in one year (Tonto Feb and Nov 2022).
			}
			$list .= '<li><a href="' . rides_e( $r['url'] ) . '" rel="noopener">' . rides_e( $r['year'] )
				. ( $label ? '<small>' . rides_e( $label ) . '</small>' : '' ) . '</a></li>';
		}
		$list .= '</ul></div></li>';
	}
	return $jump . '</ul>' . $list . '</ul>';
}

/**
 * Results page.
 *
 * @param array $ctx Render context.
 * @return string
 */
function rides_render_results( $ctx ) {
	$out  = rides_render_pagehead( $ctx, 'Results', 'Official results for every Aravaipa Rides event, by ride and year.', 'tonto' );
	$out .= '<section class="rides-section"><div class="rides-wrap">' . rides_render_list( 'results', rides_data( 'results' ), $ctx ) . '</div></section>';
	return rides_root( 'results', $out );
}

/**
 * Photos page.
 *
 * @param array $ctx Render context.
 * @return string
 */
function rides_render_photos( $ctx ) {
	$out  = rides_render_pagehead( $ctx, 'Photos', 'Race photo galleries by ride and year.', 'big-woody' );
	$out .= '<section class="rides-section"><div class="rides-wrap">' . rides_render_list( 'photos', rides_data( 'photos' ), $ctx ) . '</div></section>';
	return rides_root( 'photos', $out );
}

/* --------------------------------------------------------- event page */

/**
 * Paragraph list.
 *
 * @param array $paras Strings.
 * @return string
 */
function rides_paras( $paras ) {
	$out = '';
	foreach ( (array) $paras as $p ) {
		$out .= '<p>' . rides_e( $p ) . '</p>';
	}
	return $out;
}

/**
 * Register buttons as they are live today.
 *
 * @param array  $links [{label, href}].
 * @param string $extra Extra class.
 * @return string
 */
function rides_register_buttons( $links, $extra = '' ) {
	$out = '';
	foreach ( (array) $links as $i => $l ) {
		$out .= '<a class="rides-btn rides-register' . ( $extra ? ' ' . $extra : '' ) . '" href="' . rides_e( $l['href'] ) . '">' . rides_e( $l['label'] ) . '</a>';
	}
	return $out;
}

/**
 * Event page, race page order: full-bleed hero with the ride logo as artwork, yellow date band
 * (date, place, register and volunteer), two-column intro with a ride photo, one distance table,
 * then course, schedule, aid, FAQ, results and photos, sponsors. Sections without data are left out.
 *
 * @param array  $ctx  Render context.
 * @param string $slug Ride slug.
 * @return string
 */
function rides_render_event( $ctx, $slug ) {
	$e = rides_event( $slug );
	$d = rides_data( 'events/' . $slug );
	if ( ! $e || ! $d ) {
		return '';
	}
	$night = ( $d['theme'] ?? '' ) === 'night';
	$tone  = isset( $e['logo_plate'] ) && 'light' === $e['logo_plate'] ? 'light' : 'dark';
	$out   = rides_event_hero( $ctx, $e, $d, $tone );
	$out  .= rides_event_band( $d );
	$out  .= rides_event_intro( $ctx, $e, $d );
	$out  .= rides_event_table( $d );
	$out  .= rides_event_course( $d );
	$out  .= rides_event_schedule( $d );
	$out  .= rides_event_aid( $d );
	$out  .= rides_event_faq( $d );
	$out  .= rides_event_history( $slug, $d );
	$out  .= rides_event_sponsors( $d );
	if ( ! empty( $d['register'] ) ) {
		$out .= '<section class="rides-section rides-section--navy" id="register"><div class="rides-wrap rides-register-band">';
		$out .= '<div><p class="rides-eyebrow">Registration</p><h2>' . rides_e( $d['register_headline'] ?? 'Register' ) . '</h2>';
		if ( ! empty( $d['register_note'] ) ) {
			$out .= '<p class="rides-note">' . rides_e( $d['register_note'] ) . '</p>';
		}
		$out .= '</div><div class="rides-actions">' . rides_register_buttons( $d['register'] ) . '</div></div></section>';
	}
	return rides_root( 'event' . ( $night ? ' rides-theme-night' : '' ), $out );
}

/**
 * Full-bleed hero: photo edge to edge, soft gradient for contrast, logo as transparent artwork.
 *
 * @param array  $ctx  Render context.
 * @param array  $e    Event row.
 * @param array  $d    Event data.
 * @param string $tone light (ink logo, light glow) or dark (light logo, dark scrim).
 * @return string
 */
function rides_event_hero( $ctx, $e, $d, $tone ) {
	$hero = rides_hero_url( $ctx, $e );
	$out  = '<header class="rides-event-hero rides-event-hero--' . rides_e( $tone ) . '" id="hero">';
	$out .= $hero ? '<img class="rides-event-photo" src="' . rides_e( $hero ) . '" alt="" width="1920" height="900" fetchpriority="high">' : '';
	$out .= '<div class="rides-event-glow" aria-hidden="true"></div>';
	$out .= '<div class="rides-event-hero-body">' . rides_logo_img( $ctx, $e, 'rides-event-logo', array( 'fetchpriority' => 'high' ), false );
	$out .= '<h1 class="rides-sr">' . rides_e( $e['name'] ) . '</h1></div>';
	return $out . '</header>';
}

/**
 * Yellow date band: date, place and the register and volunteer buttons (series rides for a series).
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_band( $d ) {
	$out  = '<section class="rides-band" id="band"><div class="rides-wrap rides-band-inner">';
	$out .= '<div class="rides-band-when"><p class="rides-band-date">' . rides_e( $d['date_label'] ) . '</p><p class="rides-band-place">' . rides_e( $d['place'] ) . '</p></div>';
	$out .= '<div class="rides-actions rides-band-actions">';
	$out .= rides_register_buttons( $d['register'] ?? array(), 'rides-btn--navy' );
	foreach ( (array) ( $d['series'] ?? array() ) as $s ) {
		$out .= '<a class="rides-btn rides-btn--navy" href="' . rides_e( $s['url'] ) . '">' . rides_e( $s['name'] ) . '</a>';
	}
	if ( ! empty( $d['volunteer']['href'] ) ) {
		$out .= '<a class="rides-btn rides-btn--outline" href="' . rides_e( $d['volunteer']['href'] ) . '">Volunteer</a>';
	}
	$out .= '</div></div>';
	if ( ! empty( $d['register_note'] ) ) {
		$out .= '<div class="rides-wrap"><p class="rides-band-note">' . rides_e( $d['register_note'] ) . '</p></div>';
	}
	return $out . '</section>';
}

/**
 * Two-column intro: copy plus a ride photo from the event's own page.
 *
 * @param array $ctx Render context.
 * @param array $e   Event row.
 * @param array $d   Event data.
 * @return string
 */
function rides_event_intro( $ctx, $e, $d ) {
	$ph   = $d['intro_photo'] ?? array();
	$has  = ! empty( $ph['src'] ) && is_readable( RIDES_DIR . '/' . $ph['src'] );
	$out  = '<section class="rides-section" id="intro"><div class="rides-wrap rides-event-intro' . ( $has ? '' : ' is-single' ) . '">';
	$out .= '<div class="rides-intro-copy">';
	if ( ! empty( $d['kicker'] ) ) {
		$out .= '<p class="rides-eyebrow">' . rides_e( $d['kicker'] ) . '</p>';
	} else {
		$out .= '<p class="rides-eyebrow">The Ride</p>';
	}
	$out .= '<h2 class="rides-h2">' . rides_e( $d['headline'] ?? $e['name'] ) . '</h2>';
	$out .= '<div class="rides-intro-text">' . rides_paras( $d['intro'] ?? array() ) . '</div></div>';
	if ( $has ) {
		$size = '';
		$dim  = getimagesize( RIDES_DIR . '/' . $ph['src'] );
		if ( $dim ) {
			$size = ' width="' . (int) $dim[0] . '" height="' . (int) $dim[1] . '"';
		}
		$out .= '<figure class="rides-intro-photo"><img src="' . rides_e( rides_asset( $ctx, $ph['src'] ) ) . '" alt="' . rides_e( $ph['alt'] ?? '' ) . '"' . $size . ' loading="lazy"></figure>';
	}
	return $out . '</div></section>';
}

/**
 * Column labels for the distance table: the union of fact labels in first-seen order.
 *
 * @param array $dists Distances.
 * @return array
 */
function rides_event_columns( $dists ) {
	$cols = array();
	foreach ( $dists as $dist ) {
		foreach ( (array) ( $dist['facts'] ?? array() ) as $f ) {
			if ( ! in_array( $f[0], $cols, true ) ) {
				$cols[] = $f[0];
			}
		}
	}
	return $cols;
}

/**
 * One distance table (or the series rides for a series page). No prices.
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_table( $d ) {
	$dists  = (array) ( $d['distances'] ?? array() );
	$series = (array) ( $d['series'] ?? array() );
	if ( ! $dists && ! $series ) {
		return '';
	}
	$out = '<section class="rides-section rides-section--mist" id="distances"><div class="rides-wrap">';
	if ( $dists ) {
		$cols  = rides_event_columns( $dists );
		$names = array(
			'Distance'   => 'Length',
			'Time Limit' => 'Time Limit / Cutoff',
		);
		$maps  = count( array_filter( array_column( $dists, 'map' ) ) ) > 0;
		$out  .= '<p class="rides-eyebrow">Distances</p><h2 class="rides-h2">Pick Your Distance</h2>';
		$out  .= '<table class="rides-dist-table"><thead><tr><th scope="col">Distance</th>';
		foreach ( $cols as $c ) {
			$out .= '<th scope="col">' . rides_e( $names[ $c ] ?? $c ) . '</th>';
		}
		$out .= $maps ? '<th scope="col">Course Map</th>' : '';
		$out .= '</tr></thead><tbody>';
		foreach ( $dists as $dist ) {
			$facts = array();
			foreach ( (array) ( $dist['facts'] ?? array() ) as $f ) {
				$facts[ $f[0] ] = $f[1];
			}
			$out .= '<tr><th scope="row" data-label="Distance"><b>' . rides_e( $dist['name'] ) . '</b>' . ( ! empty( $dist['sport'] ) ? '<span>' . rides_e( $dist['sport'] ) . '</span>' : '' ) . '</th>';
			foreach ( $cols as $c ) {
				$v    = $facts[ $c ] ?? '';
				$out .= '<td data-label="' . rides_e( $names[ $c ] ?? $c ) . '"' . ( '' === $v ? ' class="is-empty"' : '' ) . '>' . rides_e( $v ) . '</td>';
			}
			if ( $maps ) {
				$out .= ! empty( $dist['map'] ) ? '<td data-label="Course Map" class="rides-dist-map"><a href="' . rides_e( $dist['map'] ) . '" rel="noopener">Course Map</a></td>' : '<td class="is-empty"></td>';
			}
			$out .= '</tr>';
		}
	} else {
		$out .= '<p class="rides-eyebrow">The Series</p><h2 class="rides-h2">Three Rides, One Pass</h2>';
		$out .= '<table class="rides-dist-table"><thead><tr><th scope="col">Ride</th><th scope="col">Date</th><th scope="col">Place</th><th scope="col">Distances</th><th scope="col">Ride Page</th></tr></thead><tbody>';
		foreach ( $series as $s ) {
			$out .= '<tr><th scope="row" data-label="Ride"><b>' . rides_e( $s['title'] ?? $s['name'] ) . '</b></th>';
			$out .= '<td data-label="Date">' . rides_e( $s['date_label'] ) . '</td>';
			$out .= '<td data-label="Place">' . rides_e( $s['place'] ?? '' ) . '</td>';
			$out .= '<td data-label="Distances">' . rides_e( $s['distances'] ?? '' ) . '</td>';
			$out .= '<td data-label="Ride Page" class="rides-dist-map"><a href="' . rides_e( $s['url'] ) . '">' . rides_e( $s['name'] ) . ' Details</a></td></tr>';
		}
	}
	$out .= '</tbody></table>';
	if ( ! empty( $d['distance_note'] ) ) {
		$out .= '<p class="rides-note rides-dist-note">' . rides_e( $d['distance_note'] ) . '</p>';
	}
	return $out . '</div></section>';
}

/**
 * Course: map link, course descriptions and start lines.
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_course( $d ) {
	$courses = (array) ( $d['courses'] ?? array() );
	$map     = $d['course_map'] ?? array();
	$starts  = (array) ( $d['location']['start_lines'] ?? array() );
	if ( ! $courses && empty( $map['href'] ) ) {
		return '';
	}
	$out  = '<section class="rides-section" id="course"><div class="rides-wrap">';
	$out .= '<div class="rides-head-row"><div><p class="rides-eyebrow">Course</p><h2 class="rides-h2">Course Information</h2></div>';
	if ( ! empty( $map['href'] ) ) {
		$out .= '<a class="rides-btn rides-btn--teal" href="' . rides_e( $map['href'] ) . '" rel="noopener">' . rides_e( $map['label'] ?? 'Course Map' ) . '</a>';
	}
	$out .= '</div><div class="rides-two">';
	foreach ( $courses as $c ) {
		$out .= '<div class="rides-block"><h3>' . rides_e( $c['name'] ) . '</h3>' . rides_paras( $c['paras'] ) . '</div>';
	}
	if ( $starts ) {
		$out .= '<div class="rides-block"><h3>Start Lines</h3>';
		foreach ( $starts as $sl ) {
			$out .= '<p><b>' . rides_e( $sl['name'] ) . ': ' . rides_e( $sl['place'] ) . '</b><br>' . rides_e( $sl['text'] );
			if ( ! empty( $sl['map'] ) ) {
				$out .= ' <a href="' . rides_e( $sl['map'] ) . '" rel="noopener">Map</a>';
			}
			$out .= '</p>';
		}
		if ( ! empty( $d['location']['finish'] ) ) {
			$out .= '<p><b>Finish</b><br>' . rides_e( $d['location']['finish'] ) . '</p>';
		}
		$out .= '</div>';
	}
	return $out . '</div></div></section>';
}

/**
 * Two-column rows table.
 *
 * @param array $t {caption, rows}.
 * @return string
 */
function rides_event_rows_table( $t ) {
	$out = '<table class="rides-schedule"><caption>' . rides_e( $t['caption'] ) . '</caption><tbody>';
	foreach ( $t['rows'] as $r ) {
		$out .= '<tr><th scope="row">' . rides_e( $r[0] ) . '</th><td>' . rides_e( $r[1] ) . '</td></tr>';
	}
	return $out . '</tbody></table>';
}

/**
 * Schedule: race day tables and bib pickup, plus good to know notes.
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_schedule( $d ) {
	if ( empty( $d['schedule'] ) ) {
		return '';
	}
	$info = (array) ( $d['info'] ?? array() );
	$bib  = (array) ( $d['bib_pickup'] ?? array() );
	$out  = '<section class="rides-section rides-section--mist" id="schedule"><div class="rides-wrap">';
	$out .= '<p class="rides-eyebrow">Race Day</p><h2 class="rides-h2">Schedule</h2><div class="rides-two' . ( $info || $bib ? '' : ' is-single' ) . '"><div class="rides-block">';
	foreach ( $d['schedule'] as $sc ) {
		$out .= rides_event_rows_table( $sc );
	}
	$out .= '</div>';
	if ( $info || $bib ) {
		$out .= '<div class="rides-block">';
		foreach ( $bib as $bp ) {
			$out .= rides_event_rows_table( $bp );
		}
		foreach ( $info as $inf ) {
			$out .= '<h3>' . rides_e( $inf['name'] ) . '</h3>' . rides_paras( $inf['paras'] );
		}
		$out .= '</div>';
	}
	return $out . '</div></div></section>';
}

/**
 * Aid stations and what to bring.
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_aid( $d ) {
	$aid = (array) ( $d['aid'] ?? $d['what_to_bring'] ?? array() );
	if ( ! $aid ) {
		return '';
	}
	$out = '<section class="rides-section" id="aid"><div class="rides-wrap"><p class="rides-eyebrow">On Course</p><h2 class="rides-h2">Aid And What To Bring</h2><div class="rides-two">';
	foreach ( $aid as $a ) {
		$out .= '<div class="rides-block"><h3>' . rides_e( $a['name'] ) . '</h3>' . rides_paras( $a['paras'] ) . '</div>';
	}
	return $out . '</div></div></section>';
}

/**
 * FAQ as open/close rows.
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_faq( $d ) {
	if ( empty( $d['faq'] ) ) {
		return '';
	}
	$out = '<section class="rides-section rides-section--mist" id="faq"><div class="rides-wrap"><p class="rides-eyebrow">Questions</p><h2 class="rides-h2">FAQ</h2><div class="rides-faq">';
	foreach ( $d['faq'] as $i => $q ) {
		$out .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . rides_e( $q['q'] ) . '</summary><div class="rides-faq-a">' . rides_paras( $q['a'] ) . '</div></details>';
	}
	$out .= '</div>';
	$links = array_filter( array( $d['faq_link'] ?? null, $d['mail_in'] ?? null ) );
	if ( $links ) {
		$out .= '<div class="rides-actions rides-faq-links">';
		foreach ( $links as $l ) {
			$out .= '<a class="rides-btn rides-btn--line" href="' . rides_e( $l['href'] ) . '" rel="noopener">' . rides_e( $l['label'] ) . '</a>';
		}
		$out .= '</div>';
	}
	return $out . '</div></section>';
}

/**
 * Results (with course records and live tracking) and photo galleries, by year.
 *
 * @param string $slug Ride slug.
 * @param array  $d    Event data.
 * @return string
 */
function rides_event_history( $slug, $d ) {
	$pick = function ( $name ) use ( $slug ) {
		return array_values(
			array_filter(
				rides_data( $name ),
				function ( $r ) use ( $slug ) {
					return $r['slug'] === $slug && ! empty( $r['url'] );
				}
			)
		);
	};
	$res  = $pick( 'results' );
	$pho  = $pick( 'photos' );
	if ( ! $res && ! $pho ) {
		return '';
	}
	$out = '<section class="rides-section" id="history"><div class="rides-wrap"><div class="rides-two' . ( $res && $pho ? '' : ' is-single' ) . '">';
	if ( $res ) {
		$out .= '<div class="rides-block" id="results"><p class="rides-eyebrow">History</p><h2 class="rides-h2">Results</h2><ul class="rides-rows">';
		foreach ( $res as $r ) {
			$out .= '<li><b>' . rides_e( $r['year'] ) . ( ! empty( $r['label'] ) ? ' ' . rides_e( $r['label'] ) : '' ) . '</b><a class="rides-btn rides-btn--line" href="' . rides_e( $r['url'] ) . '" rel="noopener">Results</a></li>';
		}
		$out .= '</ul>';
		if ( ! empty( $d['live']['href'] ) ) {
			$out .= '<p class="rides-live">' . rides_e( $d['live']['text'] ) . ' <a href="' . rides_e( $d['live']['href'] ) . '" rel="noopener">' . rides_e( $d['live']['label'] ) . '</a></p>';
		}
		if ( ! empty( $d['records'] ) ) {
			$out .= rides_event_rows_table(
				array(
					'caption' => 'Course Records',
					'rows'    => $d['records'],
				)
			);
		}
		$out .= '</div>';
	}
	if ( $pho ) {
		$out .= '<div class="rides-block" id="photos"><p class="rides-eyebrow">Galleries</p><h2 class="rides-h2">Photos</h2><ul class="rides-rows">';
		foreach ( $pho as $r ) {
			$out .= '<li><b>' . rides_e( $r['year'] ) . ( ! empty( $r['label'] ) ? ' ' . rides_e( $r['label'] ) : '' ) . '</b><a class="rides-btn rides-btn--line" href="' . rides_e( $r['url'] ) . '" rel="noopener">Photos</a></li>';
		}
		$out .= '</ul></div>';
	}
	return $out . '</div></div></section>';
}

/**
 * Sponsors, each with its logo.
 *
 * @param array $d Event data.
 * @return string
 */
function rides_event_sponsors( $d ) {
	if ( empty( $d['sponsors'] ) ) {
		return '';
	}
	$out = '<section class="rides-section" id="sponsors"><div class="rides-wrap"><p class="rides-eyebrow">Thank You</p><h2 class="rides-h2">Partners</h2><ul class="rides-sponsors">';
	foreach ( $d['sponsors'] as $s ) {
		$img  = '<img src="' . rides_e( $s['logo'] ) . '" alt="' . rides_e( $s['name'] ) . '" loading="lazy">';
		$out .= '<li>' . ( ! empty( $s['href'] ) ? '<a href="' . rides_e( $s['href'] ) . '" rel="noopener">' . $img . '</a>' : '<span>' . $img . '</span>' ) . '</li>';
	}
	$out .= '</ul>';
	if ( ! empty( $d['sponsor_note'] ) ) {
		$out .= '<p class="rides-note rides-sponsor-note">' . rides_e( $d['sponsor_note'] ) . '</p>';
	}
	return $out . '</div></section>';
}

/**
 * Render a layout key: home, events, results, photos or event:<slug>.
 *
 * @param string $layout Layout key.
 * @param array  $ctx    Render context.
 * @return string
 */
function rides_render_layout( $layout, $ctx ) {
	if ( 0 === strpos( $layout, 'event:' ) ) {
		return rides_render_event( $ctx, substr( $layout, 6 ) );
	}
	switch ( $layout ) {
		case 'home':
			return rides_render_home( $ctx );
		case 'events':
			return rides_render_events( $ctx );
		case 'results':
			return rides_render_results( $ctx );
		case 'photos':
			return rides_render_photos( $ctx );
	}
	return '';
}

/**
 * Meta description and share image for a layout.
 *
 * @param string $layout Layout key.
 * @param array  $ctx    Render context.
 * @return array [description, image url]
 */
function rides_layout_meta( $layout, $ctx ) {
	$slug = 'prickly-pedal';
	$desc = 'Aravaipa Rides: mountain bike and gravel events in Arizona and Colorado.';
	if ( 0 === strpos( $layout, 'event:' ) ) {
		$slug = substr( $layout, 6 );
		$d    = rides_data( 'events/' . $slug );
		$e    = rides_event( $slug );
		if ( ! empty( $d['description'] ) ) {
			$desc = $d['description'];
		} elseif ( $e ) {
			$desc = $e['name'] . ', ' . $e['date_label'] . ', ' . rides_place( $e ) . '.';
		}
	} elseif ( 'events' === $layout ) {
		$slug = 'stunner';
		$desc = 'Every Aravaipa Rides event: dates, places, distances and registration.';
	} elseif ( 'results' === $layout ) {
		$slug = 'tonto';
		$desc = 'Official results for Aravaipa Rides events, by ride and year.';
	} elseif ( 'photos' === $layout ) {
		$slug = 'big-woody';
		$desc = 'Race photo galleries for Aravaipa Rides events, by ride and year.';
	} else {
		$up = array_values(
			array_filter(
				rides_events( $ctx['today'] ),
				function ( $e ) {
					return ! $e['is_past'] && rides_has_hero( $e );
				}
			)
		);
		if ( $up ) {
			$slug = $up[0]['slug'];
		}
	}
	$e = rides_event( $slug );
	return array( $desc, $e ? rides_hero_url( $ctx, $e ) : '' );
}
