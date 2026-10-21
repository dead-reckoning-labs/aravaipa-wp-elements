<?php
/**
 * Import Aravaipa Adventures from export.mjs output. Run with wp-cli:
 *
 *   wp eval-file import.php <adventures.json> <photo dir>
 *
 * Creates (or updates, matching by slug) one DRAFT adventure post per trip,
 * the /adventures/ landing as a DRAFT page, the CF7 interest form, and the
 * landing option. Photos go to the Media Library once each (matched by the
 * _arv_adv_src meta), so re-running is safe and only touches what it made.
 * Nothing is published.
 *
 * Not shipped in the plugin zip.
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "run with wp eval-file\n";
	return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$json_path = isset( $args[0] ) ? $args[0] : '';
$photo_dir = isset( $args[1] ) ? rtrim( $args[1], '/' ) : '';
$data      = json_decode( (string) file_get_contents( $json_path ), true );
if ( ! is_array( $data ) || empty( $data['trips'] ) ) {
	WP_CLI::error( 'could not read ' . $json_path );
}

$author = 3; // Jamil (admin).
wp_set_current_user( $author );

/**
 * Upload (or find) one photo and return {id, alt, credit}.
 */
$media = static function ( $photo ) use ( $data, $photo_dir, $author ) {
	$key = $photo['key'];
	$rel = isset( $data['files'][ $key ] ) ? $data['files'][ $key ] : '';
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_arv_adv_src',
			'meta_value'  => $key,
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $found ) {
		$id = (int) $found[0];
	} else {
		$src = $photo_dir . '/' . $rel;
		if ( ! $rel || ! file_exists( $src ) ) {
			WP_CLI::error( 'missing file for ' . $key );
		}
		$tmp = wp_tempnam( basename( $src ) );
		copy( $src, $tmp );
		$name = 'aravaipa-adventures-' . str_replace( '/', '-', $key ) . '.' . pathinfo( $src, PATHINFO_EXTENSION );
		$id   = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			0,
			'Aravaipa Adventures: ' . $key,
			array( 'post_author' => $author )
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $key . ': ' . $id->get_error_message() );
		}
		update_post_meta( $id, '_arv_adv_src', $key );
		WP_CLI::log( "media $key -> $id" );
	}
	if ( ! empty( $photo['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $photo['alt'] );
	}
	if ( ! empty( $photo['credit'] ) ) {
		wp_update_post(
			array(
				'ID'           => $id,
				'post_excerpt' => 'Photo: ' . $photo['credit'],
			)
		);
	}
	return array(
		'id'     => (int) $id,
		'alt'    => (string) $photo['alt'],
		'credit' => (string) $photo['credit'],
	);
};

$enc = static function ( $v ) {
	return wp_slash( wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
};

// Trips.
$ids = array();
foreach ( $data['trips'] as $t ) {
	$existing = get_posts(
		array(
			'post_type'   => 'adventure',
			'name'        => $t['slug'],
			'post_status' => 'any',
			'numberposts' => 1,
		)
	);
	$postarr = array(
		'post_type'   => 'adventure',
		'post_title'  => $t['name'],
		'post_name'   => $t['slug'],
		'post_author' => $author,
		'menu_order'  => (int) $t['menu_order'],
	);
	if ( $existing ) {
		$postarr['ID'] = $existing[0]->ID;
		$id            = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$postarr['post_status'] = 'draft';
		$id                     = wp_insert_post( wp_slash( $postarr ), true );
	}
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $t['slug'] . ': ' . $id->get_error_message() );
	}
	foreach ( $t['meta'] as $k => $v ) {
		update_post_meta( $id, '_arv_adv_' . $k, wp_slash( (string) $v ) );
	}
	$photos            = $t['json']['photos'];
	$photos['hero']    = $media( $photos['hero'] );
	$photos['intro']   = $media( $photos['intro'] );
	$photos['form']    = $media( $photos['form'] );
	$photos['gallery'] = array_map( $media, $photos['gallery'] );
	$t['json']['photos'] = $photos;
	foreach ( $t['json'] as $k => $v ) {
		update_post_meta( $id, '_arv_adv_' . $k, $enc( $v ) );
	}
	set_post_thumbnail( $id, $photos['hero']['id'] );
	$ids[ $t['slug'] ] = $id;
	WP_CLI::log( 'trip ' . $t['slug'] . ' -> ' . $id . ' (' . get_post_status( $id ) . ')' );
}

// Landing config.
$l = $data['landing'];
foreach ( array( 'hero', 'pano', 'who', 'form', 'partner_photo', 'partner_logo' ) as $k ) {
	$l[ $k ] = $media( $l[ $k ] );
}
foreach ( $l['credits'] as $i => $c ) {
	$l['credits'][ $i ]['photo'] = $media( $c['photo'] );
}
update_option( 'arv_adventures_landing', wp_json_encode( $l, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), false );

// Landing page.
$landing_id = (int) get_option( 'arv_adventures_landing_id' );
if ( ! $landing_id || ! get_post( $landing_id ) ) {
	$landing_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => 'Adventures',
			'post_name'    => 'adventures',
			'post_author'  => $author,
			'post_content' => '[arv_adventures]',
		),
		true
	);
	if ( is_wp_error( $landing_id ) ) {
		WP_CLI::error( 'landing: ' . $landing_id->get_error_message() );
	}
	update_post_meta( $landing_id, '_wp_page_template', 'template-blank-4.php' );
	update_option( 'arv_adventures_landing_id', $landing_id, false );
}
set_post_thumbnail( $landing_id, $l['hero']['id'] );
WP_CLI::log( 'landing -> ' . $landing_id . ' (' . get_post_status( $landing_id ) . ')' );

// Interest form (Contact Form 7).
$form_id = (int) get_option( 'arv_adventures_form_id' );
if ( function_exists( 'wpcf7_contact_form' ) && ( ! $form_id || ! wpcf7_contact_form( $form_id ) ) ) {
	$form = WPCF7_ContactForm::get_template( array( 'title' => 'Aravaipa Adventures Interest' ) );
	$form->set_properties(
		array(
			'form'     => '<div class="aa-interest-form"><div><label for="aa-f-name">Full Name</label>[text adv-name id:aa-f-name autocomplete:name placeholder "Type your name"]</div><div><label for="aa-f-email">Email<span class="aa-req">*</span></label>[email* adv-email id:aa-f-email autocomplete:email placeholder "Type your email"]</div><div class="aa-full"><label for="aa-f-trip">Trip</label>[select adv-trip id:aa-f-trip "Any trip, keep me posted"]</div><div class="aa-full">[turnstile]</div><div class="aa-full aa-submit">[submit "Submit"]</div></div>',
			'mail'     => array(
				'active'             => true,
				'subject'            => 'Adventures interest: [adv-trip] ([adv-email])',
				'sender'             => 'Aravaipa Running <wordpress@aravaiparunning.com>',
				'recipient'          => 'info@aravaiparunning.com',
				'body'               => "New Aravaipa Adventures interest list signup.\n\nName: [adv-name]\nEmail: [adv-email]\nTrip: [adv-trip]\n\nSent from [_url] on [_date] at [_time].\nAll signups are also kept in WP Admin, Flamingo, Inbound Messages.",
				'additional_headers' => 'Reply-To: [adv-email]',
				'attachments'        => '',
				'use_html'           => false,
				'exclude_blank'      => false,
			),
			'mail_2'   => array( 'active' => false ),
			'messages' => array_merge(
				wpcf7_messages() ? array_map(
					static function ( $m ) {
						return $m['default'];
					},
					wpcf7_messages()
				) : array(),
				array( 'mail_sent_ok' => 'Thanks, you are on the list. We will reach out first when registration opens.' )
			),
		)
	);
	$form->save();
	$form_id = (int) $form->id();
	update_option( 'arv_adventures_form_id', $form_id, false );
	WP_CLI::log( 'form -> ' . $form_id );
} else {
	WP_CLI::log( 'form exists -> ' . $form_id );
}

WP_CLI::success( count( $ids ) . ' trips, landing ' . $landing_id . ', form ' . $form_id );
