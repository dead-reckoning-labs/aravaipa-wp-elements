<?php
/**
 * Aravaipa Adventures: the trip editing screen.
 *
 * Two boxes on the classic edit screen. "Trip details" holds the plain
 * fields (destination, dates, difficulty...). "Trip content" holds the
 * lists as JSON, one textarea per section, each checked on save: a section
 * that does not parse is not saved, the previous value stays, and the screen
 * says which section and why. That is the v1 trade: a little JSON instead of
 * a repeater UI, and no way to save a broken page.
 *
 * Admin only; nothing here runs on a page a visitor requests.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the boxes.
 */
function arv_adv_admin_boxes() {
	add_meta_box( 'arv_adv_details', 'Trip details', 'arv_adv_admin_details', 'adventure', 'normal', 'high' );
	add_meta_box( 'arv_adv_content', 'Trip content (JSON)', 'arv_adv_admin_content', 'adventure', 'normal', 'default' );
}
add_action( 'add_meta_boxes_adventure', 'arv_adv_admin_boxes' );

/**
 * Choices for the fields that only take a few values.
 *
 * @return array<string, array<string, string>>
 */
function arv_adv_admin_choices() {
	return array(
		'type'       => array(
			'Race'      => 'Race Trip',
			'Training'  => 'Training Camp',
			'Adventure' => 'Adventure',
		),
		'host'       => array(
			''         => 'Trail Run Adventures (partner)',
			'aravaipa' => 'Aravaipa race staff',
			'tbd'      => 'Hosts to be announced',
		),
		'difficulty' => array(
			'1' => '1 (Easy)',
			'2' => '2 (Moderate)',
			'3' => '3 (Moderate)',
			'4' => '4 (Challenging)',
			'5' => '5 (Expert)',
		),
	);
}

/**
 * Plain fields.
 *
 * @param WP_Post $post Post.
 */
function arv_adv_admin_details( $post ) {
	wp_nonce_field( 'arv_adv_save', 'arv_adv_nonce' );
	$fields  = arv_adv_fields();
	$choices = arv_adv_admin_choices();
	$long    = array( 'tagline', 'difficulty_note', 'note', 'fineprint' );

	echo '<p>The trip name is the title above, and the address is its slug (Permalink). The page itself is laid out by the Aravaipa Elements plugin.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields['scalar'] as $key => $label ) {
		$name  = 'arv_adv[' . $key . ']';
		$value = (string) get_post_meta( $post->ID, '_arv_adv_' . $key, true );
		echo '<tr><th scope="row"><label for="arv-adv-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( isset( $choices[ $key ] ) ) {
			echo '<select id="arv-adv-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $choices[ $key ] as $v => $l ) {
				echo '<option value="' . esc_attr( $v ) . '"' . selected( $value, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
		} elseif ( in_array( $key, $long, true ) ) {
			echo '<textarea class="large-text" rows="3" id="arv-adv-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="text" id="arv-adv-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * JSON sections, plus thumbnails of the photos the photos section points at.
 *
 * @param WP_Post $post Post.
 */
function arv_adv_admin_content( $post ) {
	$fields = arv_adv_fields();
	echo '<p>Each box is JSON. Keep the quotes and brackets; a box that does not parse is not saved and the old value stays. Photos use Media Library IDs (the number in the attachment\'s edit link).</p>';

	foreach ( $fields['json'] as $key => $label ) {
		$raw    = (string) get_post_meta( $post->ID, '_arv_adv_' . $key, true );
		$val    = json_decode( $raw, true );
		$pretty = null === $val ? $raw : wp_json_encode( $val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$rows   = max( 4, min( 24, substr_count( (string) $pretty, "\n" ) + 2 ) );

		echo '<p><label for="arv-adv-json-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label></p>';
		echo '<textarea class="large-text code" spellcheck="false" rows="' . (int) $rows . '" id="arv-adv-json-' . esc_attr( $key ) . '" name="arv_adv_json[' . esc_attr( $key ) . ']">' . esc_textarea( (string) $pretty ) . '</textarea>';

		if ( 'photos' === $key && is_array( $val ) ) {
			$list = array();
			foreach ( array( 'hero', 'intro', 'form' ) as $slot ) {
				if ( ! empty( $val[ $slot ]['id'] ) ) {
					$list[] = array( $slot, (int) $val[ $slot ]['id'] );
				}
			}
			foreach ( (array) ( isset( $val['gallery'] ) ? $val['gallery'] : array() ) as $i => $g ) {
				if ( ! empty( $g['id'] ) ) {
					$list[] = array( 'gallery ' . ( $i + 1 ), (int) $g['id'] );
				}
			}
			echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:8px 0 16px">';
			foreach ( $list as $item ) {
				echo '<figure style="margin:0;text-align:center;font-size:11px">' . wp_get_attachment_image( $item[1], array( 96, 96 ) ) . '<figcaption>' . esc_html( $item[0] . ' #' . $item[1] ) . '</figcaption></figure>';
			}
			echo '</div>';
		}
	}
}

/**
 * Save. Scalars are sanitized text; JSON sections must parse, or they are
 * skipped and reported.
 *
 * @param int $post_id Post ID.
 */
function arv_adv_admin_save( $post_id ) {
	if ( ! isset( $_POST['arv_adv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['arv_adv_nonce'] ) ), 'arv_adv_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = arv_adv_fields();
	$long   = array( 'tagline', 'difficulty_note', 'note', 'fineprint' );
	$in     = isset( $_POST['arv_adv'] ) && is_array( $_POST['arv_adv'] ) ? wp_unslash( $_POST['arv_adv'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.

	foreach ( array_keys( $fields['scalar'] ) as $key ) {
		if ( ! isset( $in[ $key ] ) ) {
			continue;
		}
		$v = in_array( $key, $long, true ) ? sanitize_textarea_field( $in[ $key ] ) : sanitize_text_field( $in[ $key ] );
		update_post_meta( $post_id, '_arv_adv_' . $key, wp_slash( $v ) );
	}

	$json   = isset( $_POST['arv_adv_json'] ) && is_array( $_POST['arv_adv_json'] ) ? wp_unslash( $_POST['arv_adv_json'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated as JSON below.
	$errors = array();
	foreach ( array_keys( $fields['json'] ) as $key ) {
		if ( ! isset( $json[ $key ] ) ) {
			continue;
		}
		$raw = trim( (string) $json[ $key ] );
		if ( '' === $raw ) {
			$raw = 'race' === $key || 'photos' === $key ? '{}' : '[]';
		}
		$val = json_decode( $raw, true );
		if ( ! is_array( $val ) ) {
			$errors[] = $key . ': ' . ( JSON_ERROR_NONE !== json_last_error() ? json_last_error_msg() : 'expected a list or an object' );
			continue;
		}
		$val = arv_adv_admin_clean( $val );
		update_post_meta( $post_id, '_arv_adv_' . $key, wp_slash( wp_json_encode( $val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
	}

	if ( $errors ) {
		set_transient( 'arv_adv_errors_' . get_current_user_id(), $errors, 120 );
	}
}
add_action( 'save_post_adventure', 'arv_adv_admin_save' );

/**
 * Strip markup from every string in a decoded JSON section. Pages print
 * these escaped anyway; this keeps what is stored plain.
 *
 * @param mixed $val Value.
 * @return mixed
 */
function arv_adv_admin_clean( $val ) {
	if ( is_array( $val ) ) {
		return array_map( 'arv_adv_admin_clean', $val );
	}
	if ( is_string( $val ) ) {
		return sanitize_textarea_field( $val );
	}
	return $val;
}

/**
 * Report sections that did not save.
 */
function arv_adv_admin_notice() {
	$key    = 'arv_adv_errors_' . get_current_user_id();
	$errors = get_transient( $key );
	if ( ! $errors ) {
		return;
	}
	delete_transient( $key );
	echo '<div class="notice notice-error"><p><strong>Some trip content was not saved, the previous version is still live:</strong></p><ul>';
	foreach ( (array) $errors as $e ) {
		echo '<li>' . esc_html( $e ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'arv_adv_admin_notice' );

/**
 * List columns: dates and type.
 *
 * @param array $cols Columns.
 * @return array
 */
function arv_adv_admin_columns( $cols ) {
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['arv_adv_dates'] = 'Dates';
			$out['arv_adv_type']  = 'Type';
		}
	}
	return $out;
}
add_filter( 'manage_adventure_posts_columns', 'arv_adv_admin_columns' );

/**
 * @param string $col     Column.
 * @param int    $post_id Post ID.
 */
function arv_adv_admin_column( $col, $post_id ) {
	if ( 'arv_adv_dates' === $col ) {
		echo esc_html( (string) get_post_meta( $post_id, '_arv_adv_dates', true ) );
	} elseif ( 'arv_adv_type' === $col ) {
		echo esc_html( (string) get_post_meta( $post_id, '_arv_adv_type', true ) );
	}
}
add_action( 'manage_adventure_posts_custom_column', 'arv_adv_admin_column', 10, 2 );
