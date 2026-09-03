<?php
/**
 * The editing screen for the photo gallery directory.
 *
 * includes/photos-store.php holds "who shot which race, and where are the
 * pictures", and scripts/import-photos.mjs seeded it from the hand-built
 * photos-YYYY pages it replaced. That script's own header says new galleries
 * should be added to the store rather than to a page for it to re-read, and
 * it is right, but nothing was ever built to do that. So the only ways to add
 * a gallery were a developer running a script with an Application Password,
 * or an edit straight into wp_options.
 *
 * That is the gap this closes. A gallery is four short fields and one arrives
 * most race weekends: it should not need either of those people.
 *
 * Sits under Races rather than Media because it is race data. Media is
 * WordPress's own library of uploaded files, and none of these are uploaded
 * here: they live on SmugMug, PassGallery, pic-time and photographers' own
 * sites. Filing a directory of outside links under the local file library
 * would suggest a relationship that does not exist.
 *
 * Scoped to edit_posts, not manage_options, which is the whole point. The
 * people who know which photographer shot Saturday's race are editors, not
 * administrators.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query args that carry a viewer's own access token rather than identifying
 * the gallery.
 *
 * PassGallery hands out links with a `ptat` in them and pic-time an
 * `inviteToken`, both tied to whoever was signed in when the link was copied.
 * They work today, from anywhere, which is exactly what makes them easy to
 * paste in and hard to notice: the gallery opens, so the link looks right.
 * What they are is one person's key on a public page, and when it expires the
 * gallery stops opening for everyone.
 *
 * Both hosts serve the same gallery from the bare URL through their normal
 * sign-in, so dropping these loses nothing.
 */
const ARV_PHOTOS_TOKEN_ARGS = array( 'ptat', 'invitetoken', 'redirect_back', 'token', 'access_token' );

/**
 * A photographer's name reduced to what two spellings of it agree on.
 *
 * The PHP counterpart of photographerKey() in scripts/import-photos.mjs, and
 * deliberately the same reduction: the importer merges "Let's Wander
 * Photography" with "Let's Wander Productions" on the way in, and a name
 * typed into this screen has to land in the same place or the filter on
 * /photos/ grows a second entry for a photographer who already has one.
 *
 * The initial is the addition. "Ethan J Schalekamp Photography" and "Ethan
 * Schalekamp Photography" are one person, and the trade-word list alone does
 * not merge them, so a single letter between two names comes off too.
 *
 * @param string $name
 * @return string
 */
function arv_photos_by_key( $name ) {
	$key = strtolower( (string) $name );
	$key = preg_replace( '/\(.*?\)/', ' ', $key );
	$key = preg_replace( '/\b(photo gallery|photography|photographie|productions|production|photos|photo|gallery|media|llc|inc)\b/', ' ', $key );
	$key = preg_replace( '/[^a-z0-9]+/', ' ', $key );
	$key = trim( (string) $key );

	// A lone letter between two words is a middle initial, not a name.
	$key = preg_replace( '/\b[a-z]\b/', ' ', $key );

	return trim( preg_replace( '/\s+/', ' ', (string) $key ) );
}

/**
 * The spelling of a photographer's name the store already uses, if it knows
 * one.
 *
 * Returns the entered name unchanged when nobody matching is on file, so a
 * genuinely new photographer is written exactly as typed. Nobody's name gets
 * rewritten into a form they do not use; this only avoids inventing a second
 * one for somebody already here.
 *
 * @param string $name
 * @param array  $rows Existing store rows.
 * @return string
 */
function arv_photos_settle_by( $name, $rows ) {
	$name = trim( (string) $name );
	$key  = arv_photos_by_key( $name );

	if ( '' === $key ) {
		return $name;
	}

	// The most common spelling of a key wins, which is the same rule the
	// importer applies, so the two agree about a photographer with three
	// spellings across four years of pages.
	$counts = array();

	foreach ( $rows as $row ) {
		$existing = isset( $row['by'] ) ? trim( (string) $row['by'] ) : '';

		if ( '' === $existing || arv_photos_by_key( $existing ) !== $key ) {
			continue;
		}

		if ( ! isset( $counts[ $existing ] ) ) {
			$counts[ $existing ] = 0;
		}

		$counts[ $existing ]++;
	}

	if ( empty( $counts ) ) {
		return $name;
	}

	arsort( $counts );

	return (string) key( $counts );
}

/**
 * A gallery link with one person's access token taken back out of it.
 *
 * @param string $url
 * @return string
 */
function arv_photos_clean_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );

	if ( empty( $parts['query'] ) ) {
		return $url;
	}

	parse_str( $parts['query'], $args );

	foreach ( array_keys( $args ) as $arg ) {
		if ( in_array( strtolower( (string) $arg ), ARV_PHOTOS_TOKEN_ARGS, true ) ) {
			unset( $args[ $arg ] );
		}
	}

	$clean = $parts['scheme'] . '://' . $parts['host'];

	if ( ! empty( $parts['port'] ) ) {
		$clean .= ':' . $parts['port'];
	}

	$clean .= isset( $parts['path'] ) ? $parts['path'] : '';

	if ( ! empty( $args ) ) {
		$clean .= '?' . http_build_query( $args );
	}

	return $clean;
}

/**
 * Add the screen under the Races menu.
 */
function arv_photos_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=' . ARV_RACE_POST_TYPE,
		__( 'Photo Galleries', 'aravaipa-elements' ),
		__( 'Photo Galleries', 'aravaipa-elements' ),
		'edit_posts',
		'arv-photo-galleries',
		'arv_photos_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_photos_admin_menu' );

/**
 * Which year the screen is showing.
 *
 * Defaults to the newest year the store knows rather than the calendar year,
 * so the screen opens on the races people are actually adding galleries for
 * even in the first week of January.
 *
 * @param array $rows
 * @return int
 */
function arv_photos_admin_year( $rows ) {
	if ( isset( $_GET['arv_year'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (int) $_GET['arv_year']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$years = array();

	foreach ( $rows as $row ) {
		$years[] = isset( $row['year'] ) ? (int) $row['year'] : 0;
	}

	return $years ? max( $years ) : (int) gmdate( 'Y' );
}

/**
 * Save whatever the screen posted.
 *
 * The store is one option holding every row, so a save rewrites all of them.
 * The form only carries the year being edited, which means the rows for every
 * other year have to be carried across from the stored copy rather than read
 * from the request. Getting that wrong deletes twelve years of galleries, so
 * it is done by rebuilding from the store and replacing only the year in
 * hand.
 *
 * @return array{saved:int,added:int,deleted:int,merged:string}|null
 */
function arv_photos_admin_save() {
	if ( ! isset( $_POST['arv_photos_save'] ) || ! check_admin_referer( 'arv_photos_save' ) ) {
		return null;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		return null;
	}

	$stored = get_option( ARV_PHOTOS_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	$year   = isset( $_POST['arv_year'] ) ? (int) $_POST['arv_year'] : 0;

	// Every row that is not the year being edited, untouched.
	$keep = array();

	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( (int) ( isset( $row['year'] ) ? $row['year'] : 0 ) !== $year ) {
			$keep[] = $row;
		}
	}

	$posted  = isset( $_POST['arv_row'] ) && is_array( $_POST['arv_row'] ) ? wp_unslash( $_POST['arv_row'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$deleted = 0;
	$added   = 0;
	$merged  = '';
	$edited  = array();

	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$race = isset( $row['race'] ) ? trim( sanitize_text_field( $row['race'] ) ) : '';
		$url  = isset( $row['url'] ) ? arv_photos_clean_url( $row['url'] ) : '';
		$by   = isset( $row['by'] ) ? trim( sanitize_text_field( $row['by'] ) ) : '';
		$new  = ! empty( $row['is_new'] );

		// An empty new row is the blank line at the bottom of the form, not
		// an attempt to save nothing.
		if ( $new && '' === $race && '' === $url ) {
			continue;
		}

		if ( ! empty( $row['delete'] ) ) {
			$deleted++;
			continue;
		}

		if ( '' === $race || '' === $url ) {
			continue;
		}

		$settled = arv_photos_settle_by( $by, array_merge( $stored, $edited ) );

		if ( '' !== $by && $settled !== $by && '' === $merged ) {
			$merged = $settled;
		}

		$edited[] = array(
			'race' => $race,
			'year' => $year,
			'by'   => $settled,
			'url'  => $url,
		);

		if ( $new ) {
			$added++;
		}
	}

	// arv_photos_store_set() does the sanitising and the http(s) check, and
	// is what the importer writes through, so both routes into the store
	// enforce the same rules.
	$saved = arv_photos_store_set( array_merge( $keep, $edited ) );

	return array(
		'saved'   => $saved,
		'added'   => $added,
		'deleted' => $deleted,
		'merged'  => $merged,
	);
}

/**
 * The screen.
 */
function arv_photos_admin_screen() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$result = arv_photos_admin_save();

	$stored = get_option( ARV_PHOTOS_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	$year   = arv_photos_admin_year( $stored );

	$years = array();
	$rows  = array();

	foreach ( $stored as $i => $row ) {
		if ( ! is_array( $row ) || empty( $row['race'] ) ) {
			continue;
		}

		$row_year = isset( $row['year'] ) ? (int) $row['year'] : 0;
		$years[]  = $row_year;

		if ( $row_year === $year ) {
			$rows[ $i ] = $row;
		}
	}

	$years = array_values( array_unique( $years ) );
	rsort( $years );

	// Sorted the way the front end sorts, so the screen and the page it
	// edits are in the same order.
	uasort(
		$rows,
		function ( $a, $b ) {
			return strcasecmp( (string) $a['race'], (string) $b['race'] );
		}
	);

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Photo Galleries', 'aravaipa-elements' ) . '</h1>';

	echo '<p class="description">' . esc_html__(
		'Who shot which race, and where the pictures are. These link out to the photographer: nothing is uploaded here.',
		'aravaipa-elements'
	) . '</p>';

	if ( $result ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: galleries added, 2: galleries removed, 3: total galleries stored. */
					__( 'Saved. %1$d added, %2$d removed, %3$d galleries in total.', 'aravaipa-elements' ),
					$result['added'],
					$result['deleted'],
					$result['saved']
				)
			)
		);

		if ( '' !== $result['merged'] ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s is the photographer name already on file. */
						__( 'Filed under the spelling already on file: %s. That keeps one photographer to one entry in the filter on the photos page.', 'aravaipa-elements' ),
						$result['merged']
					)
				)
			);
		}
	}

	// Year tabs.
	echo '<ul class="subsubsub">';

	foreach ( $years as $i => $y ) {
		printf(
			'%s<li><a href="%s"%s>%s</a></li>',
			$i ? ' | ' : '',
			esc_url(
				add_query_arg(
					array(
						'post_type' => ARV_RACE_POST_TYPE,
						'page'      => 'arv-photo-galleries',
						'arv_year'  => $y,
					),
					admin_url( 'edit.php' )
				)
			),
			$y === $year ? ' class="current"' : '',
			esc_html( (string) $y )
		);
	}

	echo '</ul>';
	echo '<div style="clear:both"></div>';

	echo '<form method="post">';
	wp_nonce_field( 'arv_photos_save' );
	printf( '<input type="hidden" name="arv_year" value="%d" />', (int) $year );

	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr>';
	echo '<th style="width:22%">' . esc_html__( 'Race', 'aravaipa-elements' ) . '</th>';
	echo '<th style="width:22%">' . esc_html__( 'Photographer', 'aravaipa-elements' ) . '</th>';
	echo '<th>' . esc_html__( 'Gallery URL', 'aravaipa-elements' ) . '</th>';
	echo '<th style="width:70px">' . esc_html__( 'Remove', 'aravaipa-elements' ) . '</th>';
	echo '</tr></thead><tbody>';

	$n = 0;

	foreach ( $rows as $row ) {
		arv_photos_admin_row( $n++, $row, false );
	}

	// One blank row, which is the common case: a race ran and a gallery
	// arrived. Adding a second gallery for the same race is the same
	// gesture again, so the form does not need to offer several at once.
	arv_photos_admin_row( $n, array(), true );

	echo '</tbody></table>';

	echo '<p class="description">' . esc_html__(
		'A link copied while signed in to PassGallery or pic-time carries your own access token. It is removed on save, since it would stop working for everyone else once it expired.',
		'aravaipa-elements'
	) . '</p>';

	submit_button( __( 'Save Galleries', 'aravaipa-elements' ), 'primary', 'arv_photos_save' );
	echo '</form>';

	arv_photos_admin_datalists( $stored );

	echo '</div>';
}

/**
 * One row of the form.
 *
 * @param int   $n
 * @param array $row
 * @param bool  $is_new
 */
function arv_photos_admin_row( $n, $row, $is_new ) {
	$race = isset( $row['race'] ) ? (string) $row['race'] : '';
	$by   = isset( $row['by'] ) ? (string) $row['by'] : '';
	$url  = isset( $row['url'] ) ? (string) $row['url'] : '';

	echo '<tr>';

	printf(
		'<td><input type="text" list="arv-photos-races" name="arv_row[%1$d][race]" value="%2$s" class="widefat" placeholder="%3$s" /></td>',
		(int) $n,
		esc_attr( $race ),
		esc_attr__( 'Race name', 'aravaipa-elements' )
	);

	printf(
		'<td><input type="text" list="arv-photos-by" name="arv_row[%1$d][by]" value="%2$s" class="widefat" placeholder="%3$s" /></td>',
		(int) $n,
		esc_attr( $by ),
		esc_attr__( 'Photographer', 'aravaipa-elements' )
	);

	printf(
		'<td><input type="url" name="arv_row[%1$d][url]" value="%2$s" class="widefat" placeholder="https://" /></td>',
		(int) $n,
		esc_attr( $url )
	);

	if ( $is_new ) {
		printf( '<td><input type="hidden" name="arv_row[%d][is_new]" value="1" /></td>', (int) $n );
	} else {
		printf(
			'<td style="text-align:center"><input type="checkbox" name="arv_row[%d][delete]" value="1" /></td>',
			(int) $n
		);
	}

	echo '</tr>';
}

/**
 * Autocomplete for the two fields where a typo makes a duplicate.
 *
 * A race misspelt is a gallery that never joins the rest of its race, and a
 * photographer misspelt is a second entry in the filter. Both are quiet
 * failures: the gallery appears, it just appears on its own. Offering what is
 * already on file is cheaper than checking for it afterwards.
 *
 * Races come from the race store as well as the galleries, so a race that has
 * never had a gallery before is still offered.
 *
 * @param array $rows
 */
function arv_photos_admin_datalists( $rows ) {
	$races = array();
	$by    = array();

	foreach ( $rows as $row ) {
		if ( ! empty( $row['race'] ) ) {
			$races[ (string) $row['race'] ] = true;
		}

		if ( ! empty( $row['by'] ) ) {
			$by[ (string) $row['by'] ] = true;
		}
	}

	$posts = get_posts(
		array(
			'post_type'      => ARV_RACE_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $id ) {
		$races[ get_the_title( $id ) ] = true;
	}

	$races = array_keys( $races );
	$by    = array_keys( $by );
	sort( $races );
	sort( $by );

	echo '<datalist id="arv-photos-races">';
	foreach ( $races as $race ) {
		printf( '<option value="%s"></option>', esc_attr( $race ) );
	}
	echo '</datalist>';

	echo '<datalist id="arv-photos-by">';
	foreach ( $by as $name ) {
		printf( '<option value="%s"></option>', esc_attr( $name ) );
	}
	echo '</datalist>';
}
