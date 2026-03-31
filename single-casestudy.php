<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main single-casestudy-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-casestudy-article' ); ?>>
			<header class="entry-header">
				<p class="entry-label"><?php esc_html_e( 'Case Study', 'yzrh' ); ?></p>
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="entry-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<nav class="page-links">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<footer class="entry-footer">
				<p class="case-cta-text"><?php esc_html_e( 'この事例に関するご相談はお問い合わせからご連絡ください。', 'yzrh' ); ?></p>
				<a class="case-cta-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'yzrh' ); ?></a>
			</footer>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_sidebar();
get_footer();
