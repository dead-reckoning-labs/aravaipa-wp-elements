<?php
/**
 * Single trip (adventure post): the theme's header and footer around the
 * plugin's trip body. Same frame as the X theme's "Blank - No Container |
 * Header, Footer" page template, which is what the race pages use, so the
 * masthead, breadcrumbs and footer are the site's real ones.
 *
 * @package Aravaipa_Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

  <div id="x-main" class="x-main full" role="main" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php echo arv_adv_render_trip( arv_adv_trip( get_post() ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside the renderer. ?>
		</article>
	<?php endwhile; ?>
  </div>

<?php
get_footer();
