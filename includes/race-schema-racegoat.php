<?php
/**
 * Richer SportsEvent data for races whose entries are sold on our own
 * registration platform.
 *
 * Every race page already carries a SportsEvent (includes/seo.php builds it
 * from the race store). For a race still on UltraSignup that node is as good
 * as the data behind it: a date, a town, one Offer with no price. UltraSignup
 * itself publishes the same race with prices, and outranks the race's own
 * page on the race's own name.
 *
 * For a race whose register link points at our platform we can do better,
 * because the platform publishes the real thing: every distance, its current
 * price, and whether it is open or sold out. So on those pages the existing
 * node is enriched in place, through the arv_seo_race_event filter, rather
 * than a second SportsEvent being printed beside it. Two events describing
 * one race is a contradiction a validator has to resolve; one fuller event
 * is not.
 *
 * What changes on those pages:
 *   - startDate gains a time and a UTC offset (Google warns on a bare date).
 *   - location gains geo when the store has coordinates.
 *   - description uses the page's meta description.
 *   - url is the page's canonical permalink.
 *   - offers becomes one Offer per distance with a price, when the race is
 *     open for entries and the embed answered.
 *
 * Everything degrades to the node includes/seo.php already built: no embed,
 * no price, no time, no zone, each simply leaves that field as it was.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How long a good embed response is trusted. */
const ARV_RG_EMBED_TTL = 6 * 3600;

/** How long a failed fetch is remembered, so a down API is not hit per render. */
const ARV_RG_EMBED_FAIL_TTL = 15 * 60;

/** Assumed gun time when neither the start map nor the page says. */
const ARV_RG_DEFAULT_START = '07:00';

/**
 * The event slug when a register URL is on the platform, else ''.
 *
 * @param string $url
 * @return string
 */
function arv_rg_slug( $url ) {
	$url = trim( (string) $url );

	if ( ! preg_match( '#^https?://(?:www\.)?racegoat\.com/events/([a-z0-9][a-z0-9-]*)/?(?:[?\#].*)?$#i', $url, $m ) ) {
		return '';
	}

	return strtolower( $m[1] );
}

/**
 * The public embed payload for one event, cached.
 *
 * Six hours for a good answer, fifteen minutes for a bad one. A failure is
 * cached too, deliberately: this runs inside wp_head on a cache miss, and an
 * API that is down should cost one slow render, not one per visitor.
 *
 * @param string $slug
 * @return array|null Null when there is no usable answer.
 */
function arv_rg_embed( $slug ) {
	if ( '' === $slug || ! function_exists( 'wp_remote_get' ) ) {
		return null;
	}

	$key    = 'arv_rg_embed_' . md5( $slug );
	$cached = function_exists( 'get_transient' ) ? get_transient( $key ) : false;

	if ( is_array( $cached ) ) {
		return empty( $cached['ok'] ) ? null : $cached['data'];
	}

	$url = apply_filters( 'arv_rg_embed_url', 'https://racegoat.com/api/embed/' . rawurlencode( $slug ), $slug );

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 3,
			'redirection' => 2,
			'headers'     => array( 'Accept' => 'application/json' ),
		)
	);

	$data = null;

	if ( ! ( $response instanceof WP_Error ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( is_array( $decoded ) && ! empty( $decoded['distances'] ) && is_array( $decoded['distances'] ) ) {
			$data = $decoded;
		}
	}

	if ( function_exists( 'set_transient' ) ) {
		set_transient(
			$key,
			null === $data ? array( 'ok' => 0 ) : array( 'ok' => 1, 'data' => $data ),
			null === $data ? ARV_RG_EMBED_FAIL_TTL : ARV_RG_EMBED_TTL
		);
	}

	return $data;
}

/**
 * The zone a race runs in, from its "Town, ST" location.
 *
 * Built on arv_race_state_zone() (helpers.php), which already maps every
 * single-zone state and refuses to guess for split ones. Michigan is in
 * that map as Eastern, which is right for every Aravaipa race there today
 * (Munising, Paradise, Mackinaw City, Emmet County), but four western Upper
 * Peninsula counties on the Wisconsin line run on Central, so their towns
 * are carved out here rather than trusted to the state default.
 *
 * @param string $location
 * @return string IANA id, or '' when it cannot be known.
 */
function arv_rg_zone( $location ) {
	$location = trim( (string) $location );

	if ( preg_match( '/,\s*MI\s*$/i', $location ) ) {
		// Gogebic, Iron, Dickinson and Menominee counties.
		$central = array( 'ironwood', 'bessemer', 'wakefield', 'watersmeet', 'marenisco', 'iron river', 'crystal falls', 'iron mountain', 'kingsford', 'norway', 'quinnesec', 'menominee', 'stephenson', 'gogebic county', 'iron county', 'dickinson county', 'menominee county' );
		$town    = strtolower( trim( preg_replace( '/,\s*MI\s*$/i', '', $location ) ) );

		if ( in_array( $town, $central, true ) ) {
			return 'America/Menominee';
		}
	}

	return function_exists( 'arv_race_state_zone' ) ? arv_race_state_zone( $location ) : '';
}

/**
 * The first gun time printed in a race page's "Start Times" block.
 *
 * Race pages are Cornerstone layouts and every one built from the current
 * template carries a "Start Times" headline whose text lists the guns in
 * order: "29K - 7:30 am 14K - 8:30 am", "7:00PM - 27K Start ...". The first
 * time listed is the first gun. Sunrise and civil twilight sometimes share
 * the block ("Civil Twilight - 5:50 am Sunrise - 6:26 am 50K - 6:00 am")
 * and are skipped, since they come first and are not starts.
 *
 * @param string $content Raw post_content.
 * @return string H:i, or '' when the block is missing or unreadable.
 */
function arv_rg_page_start_time( $content ) {
	$content = (string) $content;
	$at      = stripos( $content, 'Start Times' );

	if ( false === $at ) {
		$at = stripos( $content, 'Start Time' );
	}

	if ( false === $at ) {
		return '';
	}

	$block = substr( $content, $at, 600 );
	$end   = stripos( $block, '[/cs_content_seo]' );

	if ( false !== $end ) {
		$block = substr( $block, 0, $end );
	}

	$block = preg_replace( '/\[[^\]]*\]|<[^>]*>/', ' ', $block );
	$block = html_entity_decode( (string) $block, ENT_QUOTES, 'UTF-8' );

	// Drop the astronomical times so they cannot be mistaken for a gun.
	$block = preg_replace( '/(?:civil\s+)?(?:twilight|sunrise|sunset)\s*(?:-|:|\xE2\x80\x93|\xE2\x80\x94)?\s*\d{1,2}(?::\d{2})?\s*[ap]\.?\s*m\.?/i', ' ', $block );

	// Cornerstone's text runs lines together, so a date can sit glued to
	// the time after it ("Saturday, May 17:00am" is May 1, 7:00am) and a
	// distance can follow with no space ("6:00 amMarathon"). Hence no
	// lookbehind, a lowercase-only lookahead, and the glued digit dropped
	// when the hour cannot be one.
	if ( ! preg_match( '/(\d{1,2})(?::([0-5]\d))?\s*([ap])\.?\s*m(?-i:(?![a-z]))/i', (string) $block, $m ) ) {
		return '';
	}

	$hour = (int) $m[1];
	$min  = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;

	if ( $hour > 12 ) {
		$hour = $hour % 10;
	}

	if ( $hour < 1 || $hour > 12 ) {
		return '';
	}

	if ( 'p' === strtolower( $m[3] ) && 12 !== $hour ) {
		$hour += 12;
	} elseif ( 'a' === strtolower( $m[3] ) && 12 === $hour ) {
		$hour = 0;
	}

	return sprintf( '%02d:%02d', $hour, $min );
}

/**
 * startDate as a full ISO 8601 instant with the venue's offset.
 *
 * Sources, in order: the director start map (explicit time and zone), the
 * page's own Start Times block in the venue's zone, then 07:00 in the
 * venue's zone. DateTimeZone does the offset, so Arizona stays -07:00 all
 * year and New Hampshire is -04:00 in June and -05:00 in February.
 *
 * @param array  $race
 * @param string $content Page content for the Start Times fallback.
 * @return string Empty when no zone can be known; the caller keeps the date.
 */
function arv_rg_start_date( $race, $content = '' ) {
	$iso  = isset( $race['iso'] ) ? (string) $race['iso'] : '';
	$name = isset( $race['name'] ) ? (string) $race['name'] : '';

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $iso ) ) {
		return '';
	}

	$starts = function_exists( 'arv_race_start_store_get' ) ? arv_race_start_store_get() : array();
	$time   = '';
	$zone   = '';

	if ( '' !== $name && ! empty( $starts[ $name ]['time'] ) && ! empty( $starts[ $name ]['tz'] ) ) {
		$time = (string) $starts[ $name ]['time'];
		$zone = (string) $starts[ $name ]['tz'];
	}

	if ( '' === $zone ) {
		$zone = arv_rg_zone( isset( $race['location'] ) ? $race['location'] : '' );
	}

	if ( '' === $zone ) {
		return '';
	}

	if ( '' === $time ) {
		$time = arv_rg_page_start_time( $content );
	}

	if ( '' === $time ) {
		$time = ARV_RG_DEFAULT_START;
	}

	try {
		$dt = new DateTime( $iso . ' ' . $time, new DateTimeZone( $zone ) );
	} catch ( Exception $e ) {
		return '';
	}

	return $dt->format( 'Y-m-d\TH:i:sP' );
}

/**
 * "Goes up to $125.00 on Jan 1, 2027" to the last day the current price
 * holds, 2026-12-31. '' when the note does not say.
 *
 * @param string $note
 * @return string
 */
function arv_rg_price_valid_until( $note ) {
	if ( ! preg_match( '/\bon\s+([A-Z][a-z]{2,8}\.?\s+\d{1,2},\s*\d{4})/', (string) $note, $m ) ) {
		return '';
	}

	$ts = strtotime( str_replace( '.', '', $m[1] ) . ' 00:00:00 UTC' );

	return $ts ? gmdate( 'Y-m-d', $ts - 86400 ) : '';
}

/**
 * One Offer per distance from the embed payload.
 *
 * @param array  $embed
 * @param string $url   The event's registration URL.
 * @return array Empty when no distance carries a usable price.
 */
function arv_rg_offers( $embed, $url ) {
	$offers = array();
	$status = isset( $embed['status'] ) ? strtolower( (string) $embed['status'] ) : '';
	// opensAt is the ISO instant registration opens (every race); opensOn is only a display string.
	$opens = isset( $embed['opensAt'] ) && is_string( $embed['opensAt'] ) ? $embed['opensAt'] : '';
	if ( '' === $opens || false === strtotime( $opens ) ) {
		$opens = isset( $embed['opensOn'] ) && is_string( $embed['opensOn'] ) ? $embed['opensOn'] : '';
	}

	foreach ( (array) ( isset( $embed['distances'] ) ? $embed['distances'] : array() ) as $d ) {
		if ( ! is_array( $d ) || ! isset( $d['priceCents'] ) || ! is_numeric( $d['priceCents'] ) ) {
			continue;
		}

		$state = isset( $d['state'] ) ? strtolower( (string) $d['state'] ) : $status;

		if ( 'open' === $status && 'open' === $state ) {
			$left         = isset( $d['left'] ) && is_numeric( $d['left'] ) ? (int) $d['left'] : null;
			$availability = ( null !== $left && $left > 0 && $left <= 10 ) ? 'LimitedAvailability' : 'InStock';
			if ( null !== $left && $left <= 0 ) {
				$availability = 'SoldOut';
			}
		} elseif ( '' !== $opens && in_array( $status, array( 'soon', 'upcoming', 'scheduled', 'presale' ), true ) ) {
			$availability = 'PreOrder';
		} else {
			$availability = 'SoldOut';
		}

		$offer = array(
			'@type'         => 'Offer',
			'name'          => isset( $d['name'] ) ? (string) $d['name'] : '',
			'url'           => $url,
			'price'         => round( ( (float) $d['priceCents'] ) / 100, 2 ),
			'priceCurrency' => 'USD',
			'availability'  => 'https://schema.org/' . $availability,
			'category'      => 'primary',
		);

		if ( '' === $offer['name'] ) {
			unset( $offer['name'] );
		}

		if ( '' !== $opens && false !== strtotime( $opens ) ) {
			$offer['validFrom'] = gmdate( 'c', strtotime( $opens ) );
		}

		$until = arv_rg_price_valid_until( isset( $d['priceNoteTitle'] ) ? $d['priceNoteTitle'] : '' );
		if ( '' !== $until ) {
			$offer['priceValidUntil'] = $until;
		}

		$offers[] = $offer;
	}

	return $offers;
}

/**
 * True when the current page's own content already carries a SportsEvent,
 * hand-pasted into a Cornerstone raw-content element or similar.
 *
 * @param int $post_id
 * @return bool
 */
function arv_rg_page_has_own_event( $post_id ) {
	if ( ! $post_id || ! function_exists( 'get_post_field' ) ) {
		return false;
	}

	$haystack = (string) get_post_field( 'post_content', $post_id );

	return false !== stripos( $haystack, 'application/ld+json' ) && false !== stripos( $haystack, 'SportsEvent' );
}

/**
 * Enrich the race page SportsEvent for a race sold on the platform.
 *
 * Hooked on arv_seo_race_event, so it only ever runs where includes/seo.php
 * has already matched the page to a race and decided to print an event.
 *
 * @param array $event
 * @param array $race
 * @return array
 */
function arv_rg_enrich_event( $event, $race ) {
	if ( ! is_array( $event ) || ! is_array( $race ) ) {
		return $event;
	}

	$register = isset( $race['register'] ) ? (string) $race['register'] : '';
	$slug     = arv_rg_slug( $register );

	if ( '' === $slug ) {
		return $event;
	}

	$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
	$content = ( $post_id && function_exists( 'get_post_field' ) ) ? (string) get_post_field( 'post_content', $post_id ) : '';

	$start = arv_rg_start_date( $race, $content );
	if ( '' !== $start ) {
		$event['startDate'] = $start;
	}

	if ( $post_id && function_exists( 'get_permalink' ) ) {
		$permalink = get_permalink( $post_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			$event['url'] = $permalink;
		}
	}

	$description = '';
	if ( function_exists( 'arv_race_seo_written' ) ) {
		$description = arv_race_seo_written();
	}
	if ( '' === $description && function_exists( 'arv_race_seo_compose' ) ) {
		$description = arv_race_seo_compose( $race );
	}
	if ( '' !== $description ) {
		$event['description'] = $description;
	}

	if ( isset( $event['location'] ) && is_array( $event['location'] ) ) {
		$lat = isset( $race['lat'] ) ? trim( (string) $race['lat'] ) : '';
		$lng = isset( $race['lng'] ) ? trim( (string) $race['lng'] ) : '';

		if ( is_numeric( $lat ) && is_numeric( $lng ) && abs( (float) $lat ) <= 90 && abs( (float) $lng ) <= 180 && ( 0.0 !== (float) $lat || 0.0 !== (float) $lng ) ) {
			$event['location']['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => round( (float) $lat, 6 ),
				'longitude' => round( (float) $lng, 6 ),
			);
		}
	}

	// Prices only while entries are actually on sale, which is exactly when
	// includes/seo.php emitted an InStock offer pointing at this register
	// URL. Waitlist and closed races keep the single offer that builder
	// chose for them.
	$selling = isset( $event['offers']['url'], $event['offers']['availability'] )
		&& $event['offers']['url'] === $register
		&& 'https://schema.org/InStock' === $event['offers']['availability'];

	if ( $selling ) {
		$embed  = arv_rg_embed( $slug );
		$offers = is_array( $embed ) ? arv_rg_offers( $embed, $register ) : array();

		if ( ! empty( $offers ) ) {
			$event['offers'] = $offers;
		}
	}

	return $event;
}
add_filter( 'arv_seo_race_event', 'arv_rg_enrich_event', 10, 2 );
