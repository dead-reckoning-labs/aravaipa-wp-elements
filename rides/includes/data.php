<?php
/**
 * Data access for the Rides layout. All content lives in rides/data/*.json.
 */

if ( ! defined( 'RIDES_DIR' ) ) {
	exit;
}

/**
 * Read one JSON file from rides/data (cached per request).
 *
 * @param string $name File name without .json, may include a sub folder.
 * @return array
 */
function rides_data( $name ) {
	static $cache = array();
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}
	$file = RIDES_DIR . '/data/' . $name . '.json';
	$data = array();
	if ( preg_match( '#^[a-z0-9/_-]+$#', $name ) && is_readable( $file ) ) {
		$decoded = json_decode( (string) file_get_contents( $file ), true );
		$data    = is_array( $decoded ) ? $decoded : array();
	}
	$cache[ $name ] = $data;
	return $data;
}

/**
 * All listed events, upcoming first (by date, undated last within their year), then past ones.
 * Rows with "unlisted": true (pages that have a blend layout but are not on the slider, tiles or
 * results order, like Rough Canyon and Group Rides) are left out unless $all is true.
 *
 * @param string|null $today Y-m-d, defaults to today.
 * @param bool        $all   Include unlisted rows.
 * @return array
 */
function rides_events( $today = null, $all = false ) {
	$raw    = rides_data( 'rides' );
	$events = isset( $raw['events'] ) ? $raw['events'] : $raw;
	$today  = $today ? $today : gmdate( 'Y-m-d' );
	if ( ! $all ) {
		$events = array_values(
			array_filter(
				$events,
				function ( $e ) {
					return empty( $e['unlisted'] );
				}
			)
		);
	}
	foreach ( $events as $i => $e ) {
		$events[ $i ]['_order']  = $i;
		$events[ $i ]['is_past'] = rides_event_is_past( $e, $today );
	}
	usort(
		$events,
		function ( $a, $b ) {
			if ( $a['is_past'] !== $b['is_past'] ) {
				return $a['is_past'] ? 1 : -1;
			}
			return $a['_order'] <=> $b['_order'];
		}
	);
	return $events;
}

/**
 * A ride is past when its data says so, or its date is before today.
 *
 * @param array  $e     Event row.
 * @param string $today Y-m-d.
 * @return bool
 */
function rides_event_is_past( $e, $today ) {
	if ( isset( $e['status'] ) && 'past' === $e['status'] ) {
		return true;
	}
	return ! empty( $e['date'] ) && $e['date'] < $today;
}

/**
 * One event by slug, unlisted rows included (their own page still renders).
 *
 * @param string $slug Event slug.
 * @return array|null
 */
function rides_event( $slug ) {
	foreach ( rides_events( null, true ) as $e ) {
		if ( $e['slug'] === $slug ) {
			return $e;
		}
	}
	return null;
}
