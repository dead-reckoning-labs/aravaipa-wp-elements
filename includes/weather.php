<?php
/**
 * A one-glance forecast beside the live bar's clock.
 *
 * Open-Meteo, not a keyed provider: it answers with no API key and no
 * account, which means no credential for this plugin to manage or leak.
 * Same wp_remote_get + transient pattern the updater already uses to talk
 * to GitHub, for the same reason: the free tier is generous but not
 * infinite, and every visitor to a race page should not be a fresh request.
 *
 * Before a race starts, this is the forecast for the gun. Once it is
 * running, it is the current conditions instead: "68 and sunny at the
 * start" stops being interesting the moment the weather has actually
 * changed on the runners out there. Never shown for a race that is over,
 * since Open-Meteo's forecast endpoint has no memory and the honest answer
 * for the past is silence, not a guess.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forecast for one race, at the gun or right now depending on its state.
 *
 * Cached per race per hour rather than per request: the forecast for
 * "2026-08-29T10:00" does not change from one visitor to the next, and
 * rounding the target time down to the hour is what lets every visitor in
 * that hour share one cached answer instead of one each.
 *
 * @param float  $lat
 * @param float  $lng
 * @param string $state 'soon' or 'live'. Anything else returns nothing.
 * @param string $start_iso ISO 8601 start time, required for 'soon'.
 * @return array|null array( 'temp' => int, 'code' => int ), or null.
 */
function arv_live_weather( $lat, $lng, $state, $start_iso = '' ) {
	if ( ! in_array( $state, array( 'soon', 'live' ), true ) ) {
		return null;
	}

	$lat = (float) $lat;
	$lng = (float) $lng;

	// 0,0 is "no coordinates recorded", not the Gulf of Guinea: the same
	// guard the race map uses for the same reason.
	if ( 0.0 === $lat && 0.0 === $lng ) {
		return null;
	}

	if ( 'soon' === $state ) {
		$target_ts = strtotime( (string) $start_iso );

		if ( ! $target_ts ) {
			return null;
		}
	} else {
		$target_ts = arv_results_now();
	}

	// Rounded to the hour, which is the forecast's own resolution and what
	// makes the cache key shared rather than personal.
	$hour = gmdate( 'Y-m-d\TH:00', $target_ts );
	$key  = 'arv_wx_' . md5( round( $lat, 2 ) . ',' . round( $lng, 2 ) . ',' . $hour . ',' . $state );

	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return ( 'none' === $cached ) ? null : $cached;
	}

	$result = 'live' === $state
		? arv_live_weather_current( $lat, $lng )
		: arv_live_weather_at( $lat, $lng, $target_ts );

	// A miss is cached too, briefly: an outage should not mean a fresh
	// outbound request on every single page view while it lasts.
	set_transient( $key, null === $result ? 'none' : $result, null === $result ? 10 * MINUTE_IN_SECONDS : 30 * MINUTE_IN_SECONDS );

	return $result;
}

/**
 * Right now, at a coordinate.
 *
 * @param float $lat
 * @param float $lng
 * @return array|null
 */
function arv_live_weather_current( $lat, $lng ) {
	$data = arv_live_weather_fetch(
		add_query_arg(
			array(
				'latitude'         => $lat,
				'longitude'        => $lng,
				'current_weather'  => 'true',
				'temperature_unit' => 'fahrenheit',
				'timezone'         => 'UTC',
			),
			'https://api.open-meteo.com/v1/forecast'
		)
	);

	if ( ! $data || empty( $data['current_weather'] ) ) {
		return null;
	}

	$cw = $data['current_weather'];

	if ( ! isset( $cw['temperature'], $cw['weathercode'] ) ) {
		return null;
	}

	return array(
		'temp' => (int) round( (float) $cw['temperature'] ),
		'code' => (int) $cw['weathercode'],
	);
}

/**
 * The forecast for one specific hour, up to sixteen days out.
 *
 * Anything further out than that is not asked for: Open-Meteo's own forecast
 * horizon ends there, and a race further away than that gets no weather
 * rather than a request that always fails.
 *
 * forecast_days=N covers today plus N-1 more days, so day 0 (today) needs 1,
 * day 15 needs 16, and day 16 is out of range. floor() rather than ceil()
 * is what makes that line up: a target 15.9 days out is still on day 15 and
 * fits in a 16-day request, but ceil() would have called it day 16 and
 * rejected it a full day early. Caught by testing a race 15 days out and
 * getting nothing back for it.
 *
 * @param float $lat
 * @param float $lng
 * @param int   $target_ts
 * @return array|null
 */
function arv_live_weather_at( $lat, $lng, $target_ts ) {
	$day_index = (int) floor( ( $target_ts - arv_results_now() ) / DAY_IN_SECONDS );
	$days      = $day_index + 1;

	if ( $day_index < 0 || $days > 16 ) {
		return null;
	}

	$data = arv_live_weather_fetch(
		add_query_arg(
			array(
				'latitude'         => $lat,
				'longitude'        => $lng,
				'hourly'           => 'temperature_2m,weathercode',
				'temperature_unit' => 'fahrenheit',
				'timezone'         => 'UTC',
				'forecast_days'    => $days,
			),
			'https://api.open-meteo.com/v1/forecast'
		)
	);

	if ( ! $data || empty( $data['hourly']['time'] ) ) {
		return null;
	}

	$hour = gmdate( 'Y-m-d\TH:00', $target_ts );
	$at   = array_search( $hour, $data['hourly']['time'], true );

	if ( false === $at ) {
		return null;
	}

	if ( ! isset( $data['hourly']['temperature_2m'][ $at ], $data['hourly']['weathercode'][ $at ] ) ) {
		return null;
	}

	return array(
		'temp' => (int) round( (float) $data['hourly']['temperature_2m'][ $at ] ),
		'code' => (int) $data['hourly']['weathercode'][ $at ],
	);
}

/**
 * GET, decoded, or null. One place both callers fail the same way.
 *
 * @param string $url
 * @return array|null
 */
function arv_live_weather_fetch( $url ) {
	$response = wp_remote_get(
		$url,
		array(
			'headers' => array( 'User-Agent' => 'aravaipa-elements-weather' ),
			'timeout' => 5,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * A WMO weather code, sorted into the handful of pictures this actually
 * draws.
 *
 * The full table has close to thirty codes distinguishing, for instance,
 * light and heavy freezing drizzle. Nobody scanning a race page for "what do
 * I wear" needs that distinction; they need one of six pictures.
 *
 * https://open-meteo.com/en/docs is the source table.
 *
 * @param int $code
 * @return string One of the keys arv_live_weather_icon() draws.
 */
function arv_live_weather_group( $code ) {
	$code = (int) $code;

	if ( 0 === $code ) {
		return 'clear';
	}

	if ( in_array( $code, array( 1, 2 ), true ) ) {
		return 'partly';
	}

	if ( 3 === $code ) {
		return 'cloudy';
	}

	if ( in_array( $code, array( 45, 48 ), true ) ) {
		return 'fog';
	}

	if ( in_array( $code, array( 71, 73, 75, 77, 85, 86 ), true ) ) {
		return 'snow';
	}

	if ( in_array( $code, array( 95, 96, 99 ), true ) ) {
		return 'storm';
	}

	// Everything left is one of the drizzle, rain or shower families.
	return 'rain';
}

/**
 * The forecast, as a span of text ready to sit beside the clock.
 *
 * Nothing at all where there is nothing to say, rather than a placeholder:
 * a race with no coordinates on file, or a forecast request that failed,
 * should leave the row exactly as it was before this existed.
 *
 * @param array|null $forecast
 * @return string
 */
function arv_live_weather_render( $forecast ) {
	if ( ! is_array( $forecast ) || ! isset( $forecast['temp'], $forecast['code'] ) ) {
		return '';
	}

	$group = arv_live_weather_group( $forecast['code'] );

	return '<span class="arv-live__weather">'
		. arv_live_weather_icon( $group )
		. '<span class="arv-live__weather-temp">' . esc_html( $forecast['temp'] ) . '&deg;</span>'
		. '</span>';
}

/**
 * One small stroke icon, matching the Instagram glyph already in this file:
 * no icon font, no sprite sheet, just inline SVG at the same 18px this bar
 * already draws at.
 *
 * @param string $group
 * @return string
 */
function arv_live_weather_icon( $group ) {
	$icons = array(
		'clear'   => '<circle cx="12" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="1.8"/>'
			. '<path d="M12 2.5v2.5M12 19v2.5M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2.5 12H5M19 12h2.5M4.2 19.8L6 18M18 6l1.8-1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
		'partly'  => '<circle cx="9" cy="10" r="3.6" fill="none" stroke="currentColor" stroke-width="1.7"/>'
			. '<path d="M9 3.5v2M9 14.5v2M2.5 10h2M13.5 10h2M4.4 4.9l1.4 1.4M4.4 15.1l1.4-1.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>'
			. '<path d="M9.5 18.5a4 4 0 0 1 .3-8 5 5 0 0 1 9.6 1.7 3.3 3.3 0 0 1-.9 6.3z" fill="none" stroke="currentColor" stroke-width="1.7"/>',
		'cloudy'  => '<path d="M6.5 18a4 4 0 0 1 .4-8 5.5 5.5 0 0 1 10.6 1.9 3.6 3.6 0 0 1-1 6.1z" fill="none" stroke="currentColor" stroke-width="1.8"/>',
		'fog'     => '<path d="M4 9h16M3 12.5h18M4 16h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
		'rain'    => '<path d="M6.5 13a4 4 0 0 1 .4-8 5.5 5.5 0 0 1 10.6 1.9 3.6 3.6 0 0 1-1 6.1z" fill="none" stroke="currentColor" stroke-width="1.8"/>'
			. '<path d="M8 16.5l-1.3 3M12 16.5l-1.3 3M16 16.5l-1.3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
		'snow'    => '<path d="M6.5 12a4 4 0 0 1 .4-8 5.5 5.5 0 0 1 10.6 1.9 3.6 3.6 0 0 1-1 6.1z" fill="none" stroke="currentColor" stroke-width="1.8"/>'
			. '<path d="M8 16.5v3M6.6 18l2.8 1M9.4 18l-2.8 1M12 16.5v3M10.6 18l2.8 1M13.4 18l-2.8 1M16 16.5v3M14.6 18l2.8 1M17.4 18l-2.8 1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>',
		'storm'   => '<path d="M6.5 12a4 4 0 0 1 .4-8 5.5 5.5 0 0 1 10.6 1.9 3.6 3.6 0 0 1-1 6.1z" fill="none" stroke="currentColor" stroke-width="1.8"/>'
			. '<path d="M12.5 15l-2.5 4h2.2l-1.2 3.5 3.7-5h-2.2l1-2.5z" fill="currentColor" stroke="currentColor" stroke-width="0.6" stroke-linejoin="round"/>',
	);

	$path = isset( $icons[ $group ] ) ? $icons[ $group ] : $icons['clear'];

	return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' . $path . '</svg>';
}
