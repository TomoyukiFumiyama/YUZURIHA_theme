<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main single-news-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-news-article' ); ?>>
			<header class="entry-header">
				<p class="entry-label"><?php esc_html_e( 'News', 'yzrh' ); ?></p>
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="entry-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="entry-content"><?php the_content(); ?></div>

			<nav class="post-navigation" aria-label="<?php esc_attr_e( 'News navigation', 'yzrh' ); ?>">
				<div class="nav-previous"><?php previous_post_link( '%link', esc_html__( '前の記事', 'yzrh' ) ); ?></div>
				<div class="nav-next"><?php next_post_link( '%link', esc_html__( '次の記事', 'yzrh' ) ); ?></div>
			</nav>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_sidebar();
get_footer();
