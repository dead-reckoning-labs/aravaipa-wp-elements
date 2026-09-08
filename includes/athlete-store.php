<?php
/**
 * Racing Team athletes as real, public, individually linkable posts.
 *
 * Before this, the whole roster lived as one 123-element Cornerstone tree on
 * a single page: every athlete's name was a button inside an accordion, not
 * a heading, so neither Google nor a screen reader could see who was on the
 * team. Nobody could link a sponsor to one runner's page because there was
 * no such page. This gives every athlete their own post, their own URL, and
 * fields a human can actually edit without touching Cornerstone.
 *
 * Public and rewritten, unlike ARV_RACE_POST_TYPE: a race page already
 * exists by hand for every race, so that store only feeds data into pages
 * that were there first. An athlete never had a page at all, so the point
 * here is for WordPress to generate one.
 *
 * Two taxonomies rather than one, because "where they live" and "who they
 * race for" are different questions with different answers. Utah and Nevada
 * are places. Great Lakes Endurance and White Mountain Endurance are teams
 * of ours in those places. A roster page needs to answer both "who's in
 * Arizona" and "who reps Bad Beard" independently, which one mixed list of
 * seven regions never could.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARV_ATHLETE_POST_TYPE', 'arv_athlete' );
define( 'ARV_ATHLETE_REGION_TAX', 'arv_athlete_region' );
define( 'ARV_ATHLETE_DIVISION_TAX', 'arv_athlete_division' );

/**
 * Register the post type and its two taxonomies.
 */
function arv_athlete_store_register() {
	register_post_type(
		ARV_ATHLETE_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Athletes', 'aravaipa-elements' ),
				'singular_name' => __( 'Athlete', 'aravaipa-elements' ),
				'add_new_item'  => __( 'Add Athlete', 'aravaipa-elements' ),
				'edit_item'     => __( 'Edit Athlete', 'aravaipa-elements' ),
				'search_items'  => __( 'Search Athletes', 'aravaipa-elements' ),
			),
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-groups',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'has_archive'  => false,
			'rewrite'      => array(
				'slug'       => 'racing-team',
				'with_front' => false,
			),
			'taxonomies'   => array( ARV_ATHLETE_REGION_TAX, ARV_ATHLETE_DIVISION_TAX ),
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		ARV_ATHLETE_REGION_TAX,
		ARV_ATHLETE_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Regions', 'aravaipa-elements' ),
				'singular_name' => __( 'Region', 'aravaipa-elements' ),
			),
			// Where an athlete lives: Arizona, Colorado, Utah...
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => true,
			'rewrite'      => false,
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		ARV_ATHLETE_DIVISION_TAX,
		ARV_ATHLETE_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Divisions', 'aravaipa-elements' ),
				'singular_name' => __( 'Division', 'aravaipa-elements' ),
			),
			// Who an athlete races for: Aravaipa AZ, Great Lakes Endurance,
			// White Mountain Endurance, Bad Beard... An athlete can carry
			// more than one over time, which is why this is a taxonomy
			// rather than a single meta field.
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => true,
			'rewrite'      => false,
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'arv_athlete_store_register' );

/**
 * The meta keys an athlete carries, mapped to a short label for the admin
 * screen. Bio lives in post_content, where the normal editor already
 * handles it; everything here is a field Cornerstone had no real home for.
 *
 * @return array<string, string>
 */
function arv_athlete_store_fields() {
	return array(
		'_arv_hometown'        => __( 'Hometown (City, ST)', 'aravaipa-elements' ),
		'_arv_status'          => __( 'Status: current or alumni', 'aravaipa-elements' ),
		'_arv_year_joined'     => __( 'Year joined', 'aravaipa-elements' ),
		'_arv_year_departed'   => __( 'Year departed (alumni only)', 'aravaipa-elements' ),
		'_arv_instagram'       => __( 'Instagram handle (with @)', 'aravaipa-elements' ),
		'_arv_strava'          => __( 'Strava URL', 'aravaipa-elements' ),
		// The join key for live results. Storing the ID rather than
		// matching on name: a name matcher missed a real win in the
		// Mogollon Monster preview research this same week because the
		// race name differed slightly between two sources. A stored ID
		// cannot have that bug.
		'_arv_ultrasignup_id'  => __( 'UltraSignup participant ID', 'aravaipa-elements' ),
		// The link the old roster page carried, which is name-based rather
		// than an ID. Kept alongside the ID: the ID is what a future results
		// sync joins on, this is just where the visitor's click goes.
		'_arv_ultrasignup_url' => __( 'UltraSignup profile URL', 'aravaipa-elements' ),
		'_arv_ultrarunning_url' => __( 'UltraRunning Mag profile URL', 'aravaipa-elements' ),
		'_arv_personal_sponsor' => __( 'Personal shoe/gear sponsor, if different from Aravaipa', 'aravaipa-elements' ),
		'_arv_alumni_note'     => __( 'Where they are now (alumni spotlight)', 'aravaipa-elements' ),
		// Plain text, one result per line, with a bare year on its own line
		// as a heading. Parsed at render time by
		// arv_athlete_profile_results_markup(), so a typo is a text edit
		// rather than a data migration.
		'_arv_results_text'    => __( 'Results: one per line, a bare year on its own line starts a new group', 'aravaipa-elements' ),
		'_arv_video_urls'      => __( 'Video URLs, one per line', 'aravaipa-elements' ),
		// Our own blog posts, not press: nothing external has ever covered
		// most of this roster, and pointing at outside coverage would mean
		// a broken link the day someone else's site reorganizes. One URL
		// per line, same shape as the video field, curated by hand rather
		// than matched automatically: a name match against post content
		// pulls in every roster-announcement post that lists fifty names
		// at once, and separately, not every true match is one an athlete
		// wants surfaced on their own page.
		'_arv_article_urls'    => __( 'Article URLs (our own blog posts about this athlete), one per line', 'aravaipa-elements' ),
	);
}

/**
 * Fields long enough to need a textarea rather than a single-line input.
 *
 * @return array<int, string>
 */
function arv_athlete_store_textarea_fields() {
	return array( '_arv_alumni_note', '_arv_results_text', '_arv_video_urls', '_arv_article_urls' );
}

/**
 * Register the meta so it is readable through the REST API and editable in
 * the admin, same reasoning as arv_race_store_register_meta().
 */
function arv_athlete_store_register_meta() {
	foreach ( array_keys( arv_athlete_store_fields() ) as $meta_key ) {
		register_post_meta(
			ARV_ATHLETE_POST_TYPE,
			$meta_key,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'arv_athlete_store_register_meta' );

/**
 * Show the stored fields on the athlete edit screen.
 *
 * Plain inputs, same reasoning as the race edit screen: a hometown typo
 * should take thirty seconds to fix, not a plugin release.
 *
 * @param WP_Post $post
 */
function arv_athlete_admin_meta_box_render( $post ) {
	wp_nonce_field( 'arv_athlete_fields', 'arv_athlete_fields_nonce' );

	$textareas = arv_athlete_store_textarea_fields();

	echo '<table class="form-table">';
	foreach ( arv_athlete_store_fields() as $key => $label ) {
		$value = (string) get_post_meta( $post->ID, $key, true );

		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';

		if ( in_array( $key, $textareas, true ) ) {
			printf(
				'<textarea id="%1$s" name="%1$s" rows="%3$d" class="large-text">%2$s</textarea>',
				esc_attr( $key ),
				esc_textarea( $value ),
				// A results block runs to a couple of dozen lines; a "where
				// are they now" note is a sentence.
				'_arv_results_text' === $key ? 14 : 4
			);
		} else {
			printf(
				'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
				esc_attr( $key ),
				esc_attr( $value )
			);
		}

		echo '</td></tr>';
	}
	echo '</table>';
}

function arv_athlete_admin_meta_box() {
	add_meta_box(
		'arv-athlete-fields',
		__( 'Athlete details', 'aravaipa-elements' ),
		'arv_athlete_admin_meta_box_render',
		ARV_ATHLETE_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'arv_athlete_admin_meta_box' );

/**
 * Save the meta box.
 *
 * @param int $post_id
 */
function arv_athlete_admin_save( $post_id ) {
	if ( ! isset( $_POST['arv_athlete_fields_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['arv_athlete_fields_nonce'] ), 'arv_athlete_fields' )
	) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$textareas = arv_athlete_store_textarea_fields();

	foreach ( array_keys( arv_athlete_store_fields() ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		update_post_meta(
			$post_id,
			$key,
			in_array( $key, $textareas, true ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw )
		);
	}
}
add_action( 'save_post_' . ARV_ATHLETE_POST_TYPE, 'arv_athlete_admin_save' );

/**
 * Alt text on the athlete's own card photo, if the attachment does not
 * already have one.
 *
 * The migration off the old Cornerstone page set 51 featured images and
 * none of their alt text, because that lives on the attachment, not the
 * post: WordPress has never had a "this athlete's featured image" concept
 * to hang it on automatically. Checked for existing text first and only
 * filled in when blank, so a real caption someone wrote by hand for the
 * photo itself is never overwritten just because an athlete post saved.
 *
 * @param int $post_id
 */
function arv_athlete_admin_set_photo_alt( $post_id ) {
	$thumb_id = get_post_thumbnail_id( $post_id );

	if ( ! $thumb_id ) {
		return;
	}

	$existing = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );

	if ( '' !== trim( (string) $existing ) ) {
		return;
	}

	$name = get_the_title( $post_id );

	if ( '' === $name ) {
		return;
	}

	update_post_meta( $thumb_id, '_wp_attachment_image_alt', sanitize_text_field( $name ) );
}
add_action( 'save_post_' . ARV_ATHLETE_POST_TYPE, 'arv_athlete_admin_set_photo_alt' );

/**
 * One athlete, in the shape every render path below shares.
 *
 * @param int|WP_Post $post
 * @return array|null
 */
function arv_athlete_store_get_one( $post ) {
	$post = get_post( $post );

	if ( ! $post || ARV_ATHLETE_POST_TYPE !== $post->post_type ) {
		return null;
	}

	$athlete = array( 'id' => $post->ID, 'name' => $post->post_title, 'bio' => $post->post_content, 'url' => get_permalink( $post ) );

	foreach ( arv_athlete_store_fields() as $meta_key => $label ) {
		// Strip the leading underscore for a plain array key: '_arv_hometown'
		// becomes 'hometown', matching the convention arv_race_store_fields()
		// already established for its own array keys.
		$field               = preg_replace( '/^_arv_/', '', $meta_key );
		$athlete[ $field ]   = (string) get_post_meta( $post->ID, $meta_key, true );
	}

	$athlete['regions']   = wp_get_post_terms( $post->ID, ARV_ATHLETE_REGION_TAX, array( 'fields' => 'names' ) );
	$athlete['divisions'] = wp_get_post_terms( $post->ID, ARV_ATHLETE_DIVISION_TAX, array( 'fields' => 'names' ) );
	$athlete['photo']     = get_the_post_thumbnail_url( $post->ID, 'medium_large' );

	return $athlete;
}

/**
 * All published athletes, optionally filtered.
 *
 * @param array $args Optional 'status', 'region', 'division'.
 * @return array<int, array>
 */
function arv_athlete_store_get( $args = array() ) {
	$query_args = array(
		'post_type'      => ARV_ATHLETE_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(),
		'tax_query'      => array(),
	);

	if ( ! empty( $args['status'] ) ) {
		$query_args['meta_query'][] = array(
			'key'   => '_arv_status',
			'value' => sanitize_key( $args['status'] ),
		);
	}

	if ( ! empty( $args['region'] ) ) {
		$query_args['tax_query'][] = array(
			'taxonomy' => ARV_ATHLETE_REGION_TAX,
			'field'    => 'slug',
			'terms'    => sanitize_title( $args['region'] ),
		);
	}

	if ( ! empty( $args['division'] ) ) {
		$query_args['tax_query'][] = array(
			'taxonomy' => ARV_ATHLETE_DIVISION_TAX,
			'field'    => 'slug',
			'terms'    => sanitize_title( $args['division'] ),
		);
	}

	$posts    = get_posts( $query_args );
	$athletes = array();

	foreach ( $posts as $post ) {
		$athletes[] = arv_athlete_store_get_one( $post );
	}

	return $athletes;
}

/**
 * Find an athlete by their UltraSignup participant ID.
 *
 * The join key a future results-sync job will use, so an athlete's live
 * results can be attributed exactly instead of by matching a name that
 * might be spelled two different ways across two data sources.
 *
 * @param string $ultrasignup_id
 * @return int|null Post ID.
 */
function arv_athlete_store_find_by_ultrasignup_id( $ultrasignup_id ) {
	$ultrasignup_id = trim( (string) $ultrasignup_id );

	if ( '' === $ultrasignup_id ) {
		return null;
	}

	$posts = get_posts(
		array(
			'post_type'      => ARV_ATHLETE_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_arv_ultrasignup_id',
					'value' => $ultrasignup_id,
				),
			),
		)
	);

	return ! empty( $posts ) ? (int) $posts[0] : null;
}

/**
 * An athlete's bio, cleaned for a field a machine reads.
 *
 * The bio is written for a human reading the page top to bottom, where a
 * trailing "@handle" line under a photo makes sense. Handed to Google as a
 * Person's description, that same line reads as "...delicious food.
 * @drbri_runningmd", so it is stripped here rather than at the source: the
 * bio itself is fine, this is only about the one other place it gets reused.
 *
 * @param string $bio
 * @return string
 */
function arv_athlete_clean_bio( $bio ) {
	$text = wp_strip_all_tags( $bio );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$lines = array_filter(
		preg_split( '/\R/', $text ),
		function ( $line ) {
			// A line that is only an @handle, the Instagram credit line
			// baked into most bios, not part of the bio itself.
			return ! preg_match( '/^@[A-Za-z0-9_.]+$/', trim( $line ) );
		}
	);

	$text = preg_replace( '/\s+/', ' ', implode( ' ', $lines ) );

	return trim( $text );
}

/**
 * A search-result snippet for an athlete's page.
 *
 * Built from the structured fields rather than the bio: a bio is written in
 * the athlete's own voice and at whatever length they wrote it, which is
 * exactly right for the page and exactly wrong for a search snippet that
 * needs to say who this is in about 155 characters. This says the same
 * thing every time: name, hometown, division, and their most recent result
 * if one is on file.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_seo_description( $athlete ) {
	$parts = array( $athlete['name'] );

	$division = ! empty( $athlete['divisions'] ) ? $athlete['divisions'][0] : '';
	$parts[]  = $division ? "races for the Aravaipa Racing Team's {$division}" : 'races for the Aravaipa Racing Team';

	if ( '' !== $athlete['hometown'] ) {
		$parts[] = "out of {$athlete['hometown']}";
	}

	$description = implode( ' ', $parts ) . '.';

	$top_result = arv_athlete_top_result( $athlete );

	if ( '' !== $top_result ) {
		$with_result = $description . ' Recent result: ' . $top_result . '.';
		// Only append if it still fits a snippet; a name-only sentence beats
		// a result truncated mid-word.
		if ( mb_strlen( $with_result ) <= 155 ) {
			$description = $with_result;
		}
	}

	return $description;
}

/**
 * The first result line under the first year heading in an athlete's
 * results text, the same plain-text format
 * arv_athlete_profile_results_markup() parses.
 *
 * @param array $athlete
 * @return string
 */
function arv_athlete_top_result( $athlete ) {
	$raw = isset( $athlete['results_text'] ) ? trim( (string) $athlete['results_text'] ) : '';

	if ( '' === $raw ) {
		return '';
	}

	$year   = '';
	$result = '';

	foreach ( preg_split( '/\R/', $raw ) as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		if ( preg_match( '/^(19|20)\d{2}$/', $line ) ) {
			$year = $line;
			continue;
		}

		if ( '' !== $year && in_array( strtolower( $line ), array( 'ultrasignup', 'strava', 'ultrarunning mag', 'ultrarunning magazine' ), true ) ) {
			continue;
		}

		if ( '' !== $year ) {
			$result = "{$line} ({$year})";
			break;
		}
	}

	return $result;
}

/**
 * The meta description, on the same wp_head priority as
 * arv_athlete_schema_head() so both are decided by the same guard.
 */
function arv_athlete_seo_head() {
	if ( ! function_exists( 'arv_seo_handled_elsewhere' ) || arv_seo_handled_elsewhere() ) {
		return;
	}

	if ( ! is_singular( ARV_ATHLETE_POST_TYPE ) ) {
		return;
	}

	$athlete = arv_athlete_store_get_one( get_queried_object() );

	if ( ! $athlete ) {
		return;
	}

	$description = arv_athlete_seo_description( $athlete );

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'arv_athlete_seo_head', 5 );

/**
 * Person structured data on an athlete's own page.
 *
 * Athletes are exactly what this page type never had before: a real,
 * crawlable name attached to real results. This is the whole SEO point of
 * the rebuild, not a nice-to-have bolted on after.
 */
function arv_athlete_schema_head() {
	if ( ! function_exists( 'arv_seo_handled_elsewhere' ) || arv_seo_handled_elsewhere() ) {
		return;
	}

	if ( ! is_singular( ARV_ATHLETE_POST_TYPE ) ) {
		return;
	}

	$athlete = arv_athlete_store_get_one( get_queried_object() );

	if ( ! $athlete ) {
		return;
	}

	$node = array(
		'@type' => 'Person',
		'name'  => $athlete['name'],
		'url'   => $athlete['url'],
	);

	if ( '' !== $athlete['bio'] ) {
		$node['description'] = arv_athlete_clean_bio( $athlete['bio'] );
	}

	if ( $athlete['photo'] ) {
		$node['image'] = $athlete['photo'];
	}

	if ( '' !== $athlete['hometown'] ) {
		$node['homeLocation'] = array(
			'@type' => 'Place',
			'name'  => $athlete['hometown'],
		);
	}

	foreach ( array( 'instagram', 'strava', 'ultrasignup_url', 'ultrarunning_url' ) as $field ) {
		if ( '' === $athlete[ $field ] ) {
			continue;
		}

		$url = 'instagram' === $field
			? 'https://www.instagram.com/' . ltrim( $athlete['instagram'], '@' )
			: $athlete[ $field ];

		$node['sameAs'][] = $url;
	}

	$node['memberOf'] = array(
		'@type' => 'SportsOrganization',
		'name'  => 'Aravaipa Racing Team',
		'url'   => home_url( '/racing-team/' ),
	);

	echo arv_seo_schema_script( array( $node ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'arv_athlete_schema_head', 5 );
