<?php
/**
 * The WordPress half of the nightly photo ingest (scripts/ingest-photos.mjs).
 *
 * Run with wp-cli, never loaded by the plugin:
 *
 *   ARV_INGEST_MODE=context wp eval-file photos-ingest.php
 *   ARV_INGEST_MODE=apply ARV_INGEST_PAYLOAD=plan.json [ARV_INGEST_COMMIT=1] wp eval-file photos-ingest.php
 *
 * context: prints the live photo store, the race names the calendar and
 * results archive know, and each name's race key, so discovery can match
 * galleries against the cards that are actually on /photos/.
 *
 * apply: APPEND-ONLY. Takes rows to add, drops any whose URL the live store
 * already holds, and appends the rest to the end of the live option. It
 * never modifies or removes an existing row. This is the opposite of
 * import-photos.mjs, which replaces the whole store and so throws away
 * hand-set covers, dates and every gallery discovery cannot see.
 *
 * The write is made against the option as it is at write time, not as it
 * was when the plan was built, because the hourly cover cron rewrites the
 * whole option whenever it finds a cover. A dated backup (autoload no) is
 * taken first, the newest 7 ingest backups are kept, and the store is read
 * back and compared after the write.
 *
 * Output is one line, "ARV_INGEST_JSON <base64 json>", so wp-cli notices
 * and deprecation warnings on the same stream cannot corrupt it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arv_ingest_option = defined( 'ARV_PHOTOS_OPTION' ) ? ARV_PHOTOS_OPTION : 'arv_race_photos';

// Self-test only: point the whole run (write, backup, prune) at a scratch
// copy of the store so the commit path can be exercised on the live site
// without touching /photos/. Nothing but this exact prefix is accepted.
$arv_ingest_selftest = (string) getenv( 'ARV_INGEST_SELFTEST_OPTION' );
if ( '' !== $arv_ingest_selftest ) {
	if ( 0 !== strpos( $arv_ingest_selftest, 'arv_race_photos_selftest' ) ) {
		echo 'ARV_INGEST_JSON ' . base64_encode( '{"ok":false,"error":"self-test option must start with arv_race_photos_selftest"}' ) . "\n";
		return;
	}
	$arv_ingest_option = $arv_ingest_selftest;
}
$arv_ingest_prefix = $arv_ingest_option . '_bak_ingest_';

/**
 * Print the result line and stop.
 *
 * @param array $out
 */
function arv_ingest_out( $out ) {
	echo 'ARV_INGEST_JSON ' . base64_encode( wp_json_encode( $out ) ) . "\n";
}

/**
 * The option as the database holds it right now, past the object cache.
 *
 * @param string $name
 * @return mixed
 */
function arv_ingest_fresh_option( $name ) {
	wp_cache_delete( $name, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );

	return get_option( $name, null );
}

/**
 * One URL per gallery however it was typed: no scheme, no query, no
 * trailing slash, lower case.
 *
 * @param string $url
 * @return string
 */
function arv_ingest_url_key( $url ) {
	$url = strtolower( trim( (string) $url ) );
	$url = preg_replace( '#^https?://#', '', $url );
	$url = preg_replace( '#[?\#].*$#', '', $url );

	return rtrim( $url, '/' );
}

/**
 * Whether the store already shows this gallery: the same URL, or the same
 * gallery linked one level up or down (old rows sometimes link an album
 * inside the race folder, sometimes the folder holding it).
 *
 * @param string $url
 * @param array  $rows
 * @return bool
 */
function arv_ingest_url_present( $url, $rows ) {
	$want = arv_ingest_url_key( $url );

	foreach ( (array) $rows as $row ) {
		if ( ! is_array( $row ) || empty( $row['url'] ) ) {
			continue;
		}

		$have = arv_ingest_url_key( $row['url'] );

		if ( $have === $want || 0 === strpos( $have, $want . '/' ) || 0 === strpos( $want, $have . '/' ) ) {
			return true;
		}
	}

	return false;
}

$mode = (string) getenv( 'ARV_INGEST_MODE' );

if ( ! function_exists( 'arv_photos_race_key' ) || ! function_exists( 'arv_photos_race_date' ) ) {
	arv_ingest_out( array( 'ok' => false, 'error' => 'aravaipa-elements photos store not loaded' ) );
	return;
}

if ( 'context' === $mode ) {
	$stored = arv_ingest_fresh_option( $arv_ingest_option );

	if ( ! is_array( $stored ) ) {
		arv_ingest_out( array( 'ok' => false, 'error' => 'store option is not an array' ) );
		return;
	}

	$rows = array();

	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) || empty( $row['race'] ) || empty( $row['url'] ) ) {
			continue;
		}

		$display = function_exists( 'arv_race_display_name' ) ? arv_race_display_name( (string) $row['race'] ) : (string) $row['race'];

		$rows[] = array(
			'race'    => (string) $row['race'],
			'display' => $display,
			'key'     => arv_photos_race_key( $display ),
			'year'    => isset( $row['year'] ) ? (int) $row['year'] : 0,
			'by'      => isset( $row['by'] ) ? (string) $row['by'] : '',
			'url'     => (string) $row['url'],
		);
	}

	// Names the site knows from elsewhere: the calendar (next editions)
	// and the results archive (everything that has run). A gallery that
	// matches one of these and no existing card is a race new to /photos/,
	// which is reported for review rather than guessed at.
	$names = array();
	$seen  = array();
	$since = (int) gmdate( 'Y' ) - 2;

	$add_name = function ( $name, $source, $iso ) use ( &$names, &$seen ) {
		$name = trim( (string) $name );

		if ( '' === $name || isset( $seen[ $source . '|' . $name ] ) ) {
			return;
		}

		$seen[ $source . '|' . $name ] = true;
		$names[] = array(
			'name'   => $name,
			'key'    => arv_photos_race_key( $name ),
			'source' => $source,
			'iso'    => (string) $iso,
		);
	};

	if ( function_exists( 'arv_race_store_get' ) ) {
		foreach ( (array) arv_race_store_get() as $race ) {
			if ( is_array( $race ) && ! empty( $race['name'] ) ) {
				$add_name( $race['name'], 'calendar', isset( $race['iso'] ) ? $race['iso'] : '' );
			}
		}
	}

	if ( function_exists( 'arv_results_store_get' ) ) {
		foreach ( (array) arv_results_store_get() as $race ) {
			if ( is_array( $race ) && ! empty( $race['name'] ) && ! empty( $race['iso'] ) && (int) substr( (string) $race['iso'], 0, 4 ) >= $since ) {
				$add_name( $race['name'], 'results', $race['iso'] );
			}
		}
	}

	arv_ingest_out(
		array(
			'ok'    => true,
			'rows'  => $rows,
			'names' => $names,
		)
	);
	return;
}

if ( 'apply' !== $mode ) {
	arv_ingest_out( array( 'ok' => false, 'error' => 'ARV_INGEST_MODE must be context or apply' ) );
	return;
}

$commit  = '1' === (string) getenv( 'ARV_INGEST_COMMIT' );
$payload = json_decode( (string) @file_get_contents( (string) getenv( 'ARV_INGEST_PAYLOAD' ) ), true );

if ( ! is_array( $payload ) || ! isset( $payload['rows'] ) || ! is_array( $payload['rows'] ) ) {
	arv_ingest_out( array( 'ok' => false, 'error' => 'payload missing or has no rows' ) );
	return;
}

$max    = isset( $payload['max'] ) ? (int) $payload['max'] : 15;
$keep   = isset( $payload['keep'] ) ? max( 1, (int) $payload['keep'] ) : 7;
$stamp  = isset( $payload['stamp'] ) ? preg_replace( '/[^0-9]/', '', (string) $payload['stamp'] ) : gmdate( 'YmdHis' );
$minrow = isset( $payload['min_store'] ) ? (int) $payload['min_store'] : 100;

if ( count( $payload['rows'] ) > $max ) {
	arv_ingest_out( array( 'ok' => false, 'error' => 'more than ' . $max . ' rows in one run, refused' ) );
	return;
}

// Built against the live option, retried if the cover cron writes it
// between this read and the write below.
for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
	$live = arv_ingest_fresh_option( $arv_ingest_option );

	if ( ! is_array( $live ) || count( $live ) < $minrow ) {
		arv_ingest_out( array( 'ok' => false, 'error' => 'live store missing or smaller than ' . $minrow . ' rows, refused' ) );
		return;
	}

	$added   = array();
	$present = array();
	$invalid = array();
	$next    = $live;

	foreach ( $payload['rows'] as $row ) {
		$race = isset( $row['race'] ) ? sanitize_text_field( (string) $row['race'] ) : '';
		$by   = isset( $row['by'] ) ? sanitize_text_field( (string) $row['by'] ) : '';
		$year = isset( $row['year'] ) ? (int) $row['year'] : 0;
		$url  = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

		if ( '' === $race || '' === $by || $year < 2000 || $year > 2100 || ! preg_match( '#^https://#i', $url ) ) {
			$invalid[] = $row;
			continue;
		}

		// Checked against the rows already added this run as well, so a plan
		// holding the same gallery twice adds it once.
		if ( arv_ingest_url_present( $url, $next ) ) {
			$present[] = array( 'race' => $race, 'year' => $year, 'url' => $url );
			continue;
		}

		$new = array(
			'race' => $race,
			'year' => $year,
			'by'   => $by,
			'url'  => $url,
		);

		// The date the calendar or results know today. The calendar forgets a
		// race's date when it rolls to the next edition, so it is written on
		// the row while it is still knowable. Only a real day in the row's
		// own year is kept (arv_photos_stored_iso()).
		$display = function_exists( 'arv_race_display_name' ) ? arv_race_display_name( $race ) : $race;
		$iso     = arv_photos_stored_iso( array( 'iso' => arv_photos_race_date( $display, $year ) ), $year );

		if ( '' !== $iso ) {
			$new['iso'] = $iso;
		}

		$next[]  = $new;
		$added[] = $new;
	}

	$result = array(
		'ok'      => true,
		'commit'  => $commit,
		'before'  => count( $live ),
		'after'   => count( $next ),
		'added'   => $added,
		'present' => $present,
		'invalid' => $invalid,
	);

	if ( ! $added || ! $commit ) {
		arv_ingest_out( $result );
		return;
	}

	$backup = $arv_ingest_prefix . $stamp;

	if ( false !== get_option( $backup, false ) ) {
		arv_ingest_out( array( 'ok' => false, 'error' => 'backup ' . $backup . ' already exists, refused' ) );
		return;
	}

	add_option( $backup, $live, '', 'no' );

	if ( arv_ingest_fresh_option( $backup ) !== $live ) {
		delete_option( $backup );
		arv_ingest_out( array( 'ok' => false, 'error' => 'backup did not read back identical, nothing written' ) );
		return;
	}

	// Last look before the write. If the cover cron saved in the meantime,
	// throw this attempt away and rebuild on what it wrote.
	if ( arv_ingest_fresh_option( $arv_ingest_option ) !== $live ) {
		delete_option( $backup );
		continue;
	}

	update_option( $arv_ingest_option, $next, false );

	$readback = arv_ingest_fresh_option( $arv_ingest_option );
	$verified = ( $readback === $next );

	// Belt and braces on the append-only promise: every row that was there
	// before is still there, in the same place, unchanged.
	$prefix_ok = is_array( $readback ) && array_slice( $readback, 0, count( $live ) ) === $live;

	// Keep the newest $keep ingest backups. Only this script's own backups
	// are pruned; hand-made ones (arv_race_photos_bak_2026...) are left.
	global $wpdb;
	$like    = $wpdb->esc_like( $arv_ingest_prefix ) . '%';
	$backups = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name DESC", $like ) );
	$pruned  = array();

	foreach ( array_slice( (array) $backups, $keep ) as $old ) {
		delete_option( $old );
		$pruned[] = $old;
	}

	$result['backup']    = $backup;
	$result['verified']  = $verified && $prefix_ok;
	$result['pruned']    = $pruned;
	$result['attempt']   = $attempt;
	$result['ok']        = $verified && $prefix_ok;

	if ( ! $result['ok'] ) {
		$result['error'] = 'store did not read back as written; restore from ' . $backup;
	}

	arv_ingest_out( $result );
	return;
}

arv_ingest_out( array( 'ok' => false, 'error' => 'store kept changing under the write (cover cron), retry next run' ) );
