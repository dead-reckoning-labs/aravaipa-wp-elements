<?php
/**
 * Bulk import screen for athlete records.
 *
 * Same shape as includes/race-admin.php's importer, for the same reason: the
 * one-time migration off the old Cornerstone roster page produces exactly
 * this format, and a coach adding five new signees at the start of a season
 * should be able to paste five lines rather than click through five separate
 * new-post screens.
 *
 * Bio text is not a column here. A pipe-delimited line is the wrong shape
 * for a paragraph of prose, so the bio goes into post_content the normal way,
 * by editing the post after it is created here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Column order for a bulk-import row.
 *
 * @return array<int, string>
 */
function arv_athlete_import_columns() {
	return array(
		'name',
		'hometown',
		'status',
		'region',
		'division',
		'instagram',
		'strava',
		'ultrasignup_id',
	);
}

function arv_athlete_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=' . ARV_ATHLETE_POST_TYPE,
		__( 'Import Athletes', 'aravaipa-elements' ),
		__( 'Import', 'aravaipa-elements' ),
		'manage_options',
		'arv-athlete-import',
		'arv_athlete_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_athlete_admin_menu' );

/**
 * Create or update one athlete from a parsed row, matched on name.
 *
 * Matched on name rather than an ID, because a bulk row has no post ID to
 * offer: this is the same trade race import makes when a race has no
 * registration URL yet. Good enough for a coach fixing a hometown typo;
 * anything that needs to be exact, like the UltraSignup ID a future results
 * sync will join on, gets typed once here and then corrected by hand on the
 * post if a duplicate name ever collides.
 *
 * @param array $row
 * @return string 'created'|'updated'|'skipped'
 */
function arv_athlete_import_row( $row ) {
	$cols = arv_athlete_import_columns();
	$data = array_combine( $cols, array_pad( array_slice( $row, 0, count( $cols ) ), count( $cols ), '' ) );

	if ( '' === trim( $data['name'] ) ) {
		return 'skipped';
	}

	// get_page_by_title() is deprecated since WP 6.2; this is its replacement.
	$existing = get_posts(
		array(
			'post_type'      => ARV_ATHLETE_POST_TYPE,
			'post_status'    => 'any',
			'title'          => $data['name'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	$postarr = array(
		'post_type'   => ARV_ATHLETE_POST_TYPE,
		'post_status' => 'draft',
		'post_title'  => $data['name'],
	);

	if ( ! empty( $existing ) ) {
		$postarr['ID'] = $existing[0];
		$post_id       = wp_update_post( $postarr );
		$outcome       = 'updated';
	} else {
		$post_id = wp_insert_post( $postarr );
		$outcome = 'created';
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 'skipped';
	}

	if ( '' !== $data['hometown'] ) {
		update_post_meta( $post_id, '_arv_hometown', sanitize_text_field( $data['hometown'] ) );
	}

	update_post_meta( $post_id, '_arv_status', 'alumni' === strtolower( $data['status'] ) ? 'alumni' : 'current' );

	if ( '' !== $data['instagram'] ) {
		update_post_meta( $post_id, '_arv_instagram', sanitize_text_field( $data['instagram'] ) );
	}

	if ( '' !== $data['strava'] ) {
		update_post_meta( $post_id, '_arv_strava', esc_url_raw( $data['strava'] ) );
	}

	if ( '' !== $data['ultrasignup_id'] ) {
		update_post_meta( $post_id, '_arv_ultrasignup_id', sanitize_text_field( $data['ultrasignup_id'] ) );
	}

	if ( '' !== $data['region'] ) {
		wp_set_object_terms( $post_id, arv_parse_list( $data['region'] ), ARV_ATHLETE_REGION_TAX );
	}

	if ( '' !== $data['division'] ) {
		wp_set_object_terms( $post_id, arv_parse_list( $data['division'] ), ARV_ATHLETE_DIVISION_TAX );
	}

	return $outcome;
}

/**
 * The import screen.
 */
function arv_athlete_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = null;

	if ( isset( $_POST['arv_athlete_import'] ) && check_admin_referer( 'arv_athlete_import' ) ) {
		$raw = isset( $_POST['arv_rows'] ) ? wp_unslash( $_POST['arv_rows'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( '' !== trim( $raw ) ) {
			$result = array( 'created' => 0, 'updated' => 0, 'skipped' => 0 );

			foreach ( arv_parse_rows( $raw, 1 ) as $row ) {
				$result[ arv_athlete_import_row( $row ) ]++;
			}
		}
	}

	$count = wp_count_posts( ARV_ATHLETE_POST_TYPE );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Import Athletes', 'aravaipa-elements' ) . '</h1>';

	if ( $result ) {
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: counts */
					__( '%1$d created, %2$d updated, %3$d skipped. New athletes are saved as drafts, review and publish them from the Athletes list.', 'aravaipa-elements' ),
					$result['created'],
					$result['updated'],
					$result['skipped']
				)
			)
		);
	}

	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: athlete count */
				__( 'The roster currently holds %d published athletes.', 'aravaipa-elements' ),
				isset( $count->publish ) ? (int) $count->publish : 0
			)
		)
	);

	echo '<p>' . esc_html__( 'One athlete per line: Name | Hometown | Status (current or alumni) | Region | Division | Instagram handle | Strava URL | UltraSignup ID. Trailing columns can be left blank. Matched on name, so re-importing an existing athlete updates them instead of duplicating them.', 'aravaipa-elements' ) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'arv_athlete_import' );
	echo '<textarea name="arv_rows" rows="16" style="width:100%;font-family:monospace;font-size:12px" placeholder="Abbie Tuomi | Surprise, AZ | current | Arizona | Aravaipa AZ | @abbietuomi | https://www.strava.com/athletes/... | 123456"></textarea>';
	submit_button( __( 'Import', 'aravaipa-elements' ), 'primary', 'arv_athlete_import' );
	echo '</form>';
	echo '</div>';
}
