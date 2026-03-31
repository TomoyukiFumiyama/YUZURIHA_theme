<?php
/**
 * The template for displaying search results pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main">
	<?php if ( have_posts() ) : ?>
		<header class="page-header">
			<h1 class="page-title">
				<?php
				printf( esc_html__( 'Search Results for: %s', 'yzrh' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
				?>
			</h1>
		</header>

		<div class="search-results-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'search-result-item' ); ?>>
					<header class="entry-header">
						<?php
						the_title(
							sprintf( '<h2 class="entry-title"><a href="%s">', esc_url( get_permalink() ) ),
							'</a></h2>'
						);
						?>
					</header>

					<div class="entry-summary">
						<?php the_excerpt(); ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<?php the_posts_navigation(); ?>
	<?php else : ?>
		<section class="no-results not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'yzrh' ); ?></h1>
			</header>
			<div class="page-content">
				<p><?php esc_html_e( '申し訳ありません。条件に一致する投稿が見つかりませんでした。', 'yzrh' ); ?></p>
			</div>
		</section>
	<?php endif; ?>
</main>

<?php
get_sidebar();
get_footer();
