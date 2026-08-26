<?php
/**
 * Import screen for the race store.
 *
 * Sits under the Races menu. Takes the same pipe-separated rows
 * scripts/fetch-races.mjs produces, so the generator's output goes straight
 * in with no intermediate step, and a single race can be fixed by hand in the
 * normal post editor afterwards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the import screen under the Races menu.
 */
function arv_race_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=' . ARV_RACE_POST_TYPE,
		__( 'Import Races', 'aravaipa-elements' ),
		__( 'Import', 'aravaipa-elements' ),
		'manage_options',
		'arv-race-import',
		'arv_race_admin_screen'
	);
}
add_action( 'admin_menu', 'arv_race_admin_menu' );

/**
 * The import screen.
 */
function arv_race_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = null;

	if ( isset( $_POST['arv_race_import'] ) && check_admin_referer( 'arv_race_import' ) ) {
		$raw   = isset( $_POST['arv_rows'] ) ? wp_unslash( $_POST['arv_rows'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$prune = ! empty( $_POST['arv_prune'] );

		if ( '' !== trim( $raw ) ) {
			$result = arv_race_store_import( $raw, $prune );
		}
	}

	$count = wp_count_posts( ARV_RACE_POST_TYPE );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Import Races', 'aravaipa-elements' ) . '</h1>';

	if ( $result ) {
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: counts */
					__( 'Imported %1$d races: %2$d created, %3$d updated, %4$d skipped, %5$d removed.', 'aravaipa-elements' ),
					$result['imported'],
					$result['created'],
					$result['updated'],
					$result['skipped'],
					$result['pruned']
				)
			)
		);
	}

	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: race count */
				__( 'The store currently holds %d published races. Everything on the site that lists races reads from here.', 'aravaipa-elements' ),
				isset( $count->publish ) ? (int) $count->publish : 0
			)
		)
	);

	echo '<p>' . esc_html__( 'Paste rows in the same format scripts/fetch-races.mjs produces. Existing races are matched on their registration URL and updated in place, so re-importing is safe and will not create duplicates.', 'aravaipa-elements' ) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'arv_race_import' );
	echo '<textarea name="arv_rows" rows="16" style="width:100%;font-family:monospace;font-size:12px" placeholder="Race Name | 2026-08-29 | August 29 | 50K | 25K | Venue | City, ST | https://ultrasignup.com/... | https://www.aravaiparunning.com/... | image | end | live | closes | 1 | 0"></textarea>';
	echo '<p><label><input type="checkbox" name="arv_prune" value="1" /> ';
	echo esc_html__( 'Remove races this import does not mention (they are trashed, not deleted).', 'aravaipa-elements' );
	echo '</label></p>';
	submit_button( __( 'Import', 'aravaipa-elements' ), 'primary', 'arv_race_import' );
	echo '</form>';
	echo '</div>';
}

/**
 * Show the stored fields on the race edit screen.
 *
 * Plain inputs rather than a custom UI: the point of the store is that a date
 * can be corrected in thirty seconds without a plugin release, and a text
 * field does that.
 */
function arv_race_admin_meta_box() {
	add_meta_box(
		'arv-race-fields',
		__( 'Race details', 'aravaipa-elements' ),
		'arv_race_admin_meta_box_render',
		ARV_RACE_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'arv_race_admin_meta_box' );

/**
 * Render the meta box.
 *
 * @param WP_Post $post
 */
function arv_race_admin_meta_box_render( $post ) {
	wp_nonce_field( 'arv_race_fields', 'arv_race_fields_nonce' );

	$labels = array(
		'_arv_iso'       => __( 'Date (YYYY-MM-DD)', 'aravaipa-elements' ),
		'_arv_end'       => __( 'End date, for multi-day races', 'aravaipa-elements' ),
		'_arv_display'   => __( 'Date as shown', 'aravaipa-elements' ),
		'_arv_distances' => __( 'Distances', 'aravaipa-elements' ),
		'_arv_venue'     => __( 'Venue', 'aravaipa-elements' ),
		'_arv_location'  => __( 'City, ST', 'aravaipa-elements' ),
		'_arv_register'  => __( 'Registration URL', 'aravaipa-elements' ),
		'_arv_page'      => __( 'Race page URL', 'aravaipa-elements' ),
		'_arv_image'     => __( 'Logo URL', 'aravaipa-elements' ),
		'_arv_live'      => __( 'Live results URL', 'aravaipa-elements' ),
		'_arv_closes'    => __( 'Entries close (YYYY-MM-DD)', 'aravaipa-elements' ),
		'_arv_confirmed' => __( 'Registration confirmed for this year (1 or 0)', 'aravaipa-elements' ),
		'_arv_guessed'   => __( 'Date is a guess (1 or 0)', 'aravaipa-elements' ),
	);

	echo '<table class="form-table">';
	foreach ( $labels as $key => $label ) {
		printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%1$s" value="%3$s" class="regular-text" /></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( (string) get_post_meta( $post->ID, $key, true ) )
		);
	}
	echo '</table>';
}

/**
 * Save the meta box.
 *
 * @param int $post_id
 */
function arv_race_admin_save( $post_id ) {
	if ( ! isset( $_POST['arv_race_fields_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['arv_race_fields_nonce'] ), 'arv_race_fields' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array_keys( arv_race_store_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			// URLs keep their query strings (UltraSignup's ?did=), so
			// sanitize_text_field rather than esc_url_raw, which would be
			// fine here too but is stricter than these fields need.
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
}
add_action( 'save_post_' . ARV_RACE_POST_TYPE, 'arv_race_admin_save' );
