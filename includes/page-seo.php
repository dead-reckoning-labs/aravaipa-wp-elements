<?php
/**
 * A written meta description for an ordinary page.
 *
 * includes/seo.php deliberately writes a description for the front page and
 * nothing else, on the grounds that a templated "X | Aravaipa Running" across
 * 178 pages reads as filler. That reasoning holds for a generated string. It
 * does not hold for one somebody sat down and wrote, which is what this adds:
 * a field on the page editor, used only when it has been filled in.
 *
 * So the rule for a page is still "no description unless there is something
 * worth saying", with the difference that an editor can now decide a given
 * page has something worth saying.
 *
 * Scope is pages, and only pages nothing else already describes. Races carry
 * their own description and Event schema, posts are handled by
 * includes/media-seo.php, and the media, live and broadcast pages each write
 * their own. Those are left alone rather than overridden: they already say
 * something specific, and printing a second description is the exact problem
 * arv_seo_handled_elsewhere() exists to avoid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The meta key. Underscored, so it stays out of the custom fields box.
 */
const ARV_PAGE_SEO_META = '_arv_meta_description';

/**
 * True when some other part of this plugin already describes this page.
 *
 * Asks each module the same question it asks itself, rather than keeping a
 * second list of slugs here that would drift the first time a page is renamed.
 *
 * @return bool
 */
function arv_page_seo_claimed() {
	if ( defined( 'ARV_RACE_POST_TYPE' ) && is_singular( ARV_RACE_POST_TYPE ) ) {
		return true;
	}

	if ( function_exists( 'arv_media_seo_page' ) && '' !== arv_media_seo_page() ) {
		return true;
	}

	if ( function_exists( 'arv_live_seo_context' ) && null !== arv_live_seo_context() ) {
		return true;
	}

	if ( function_exists( 'arv_watch_seo_context' ) && null !== arv_watch_seo_context() ) {
		return true;
	}

	return false;
}

/**
 * The written description for the page being viewed, if there is one.
 *
 * @return string Empty when unset, or when something else owns this page.
 */
function arv_page_seo_description() {
	if ( ! is_page() || is_front_page() || arv_page_seo_claimed() ) {
		return '';
	}

	$id = get_queried_object_id();

	if ( ! $id ) {
		return '';
	}

	return trim( (string) get_post_meta( $id, ARV_PAGE_SEO_META, true ) );
}

/**
 * Print the description.
 *
 * Priority 1 to sit alongside arv_seo_meta_description(), which handles the
 * front page. The two never both fire: that one is front page only, this one
 * excludes it.
 */
function arv_page_seo_head() {
	if ( arv_seo_handled_elsewhere() ) {
		return;
	}

	$description = arv_page_seo_description();

	if ( '' === $description ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'arv_page_seo_head', 1 );

/**
 * Point Jetpack's og:description at the same text.
 *
 * Jetpack already writes the Open Graph tags for these pages, from the page
 * content. Filtering what it produces keeps that single source of truth: the
 * alternative, printing our own og:description, would leave two of them on
 * the page and let a crawler pick.
 *
 * @param array $tags
 * @return array
 */
function arv_page_seo_open_graph( $tags ) {
	$description = arv_page_seo_description();

	if ( '' === $description ) {
		return $tags;
	}

	$tags['og:description']      = $description;
	$tags['twitter:description'] = $description;

	return $tags;
}
add_filter( 'jetpack_open_graph_tags', 'arv_page_seo_open_graph' );

/**
 * The field on the page editor.
 */
function arv_page_seo_meta_box() {
	add_meta_box(
		'arv-page-seo',
		__( 'Search description', 'aravaipa-elements' ),
		'arv_page_seo_meta_box_render',
		'page',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'arv_page_seo_meta_box' );

/**
 * Render the field.
 *
 * The character count is advisory and deliberately not enforced. Google
 * truncates around 160 but has never treated it as a limit, and a description
 * cut off mid-sentence by a maxlength would be worse than a long one.
 *
 * @param WP_Post $post
 */
function arv_page_seo_meta_box_render( $post ) {
	wp_nonce_field( 'arv_page_seo', 'arv_page_seo_nonce' );

	$value = (string) get_post_meta( $post->ID, ARV_PAGE_SEO_META, true );

	printf(
		'<textarea id="%1$s" name="%1$s" rows="3" class="large-text">%2$s</textarea>',
		esc_attr( ARV_PAGE_SEO_META ),
		esc_textarea( $value )
	);

	echo '<p class="description">';
	echo esc_html__(
		'Shown under the page title in search results. Around 160 characters reads best. Leave this empty and the page has no description, which is better than a generic one.',
		'aravaipa-elements'
	);
	echo '</p>';
}

/**
 * Save the field.
 *
 * @param int $post_id
 */
function arv_page_seo_save( $post_id ) {
	if ( ! isset( $_POST['arv_page_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['arv_page_seo_nonce'] ), 'arv_page_seo' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST[ ARV_PAGE_SEO_META ] ) ) {
		return;
	}

	// sanitize_textarea_field rather than sanitize_text_field: an editor
	// pasting a description with a line break in it should not have the rest
	// of the sentence silently dropped.
	$value = trim( sanitize_textarea_field( wp_unslash( $_POST[ ARV_PAGE_SEO_META ] ) ) );

	if ( '' === $value ) {
		delete_post_meta( $post_id, ARV_PAGE_SEO_META );
		return;
	}

	update_post_meta( $post_id, ARV_PAGE_SEO_META, $value );
}
add_action( 'save_post_page', 'arv_page_seo_save' );
