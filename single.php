<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main single-post-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post-article' ); ?>>
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				<div class="entry-meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<span class="entry-meta-separator">/</span>
					<time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date() ); ?></time>
					<span class="entry-meta-separator">/</span>
					<span class="entry-author"><?php the_author_posts_link(); ?></span>
				</div>
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

			<?php if ( yzrh_get_setting( 'display_show_author_box', true ) ) : ?>
				<footer class="entry-footer author-box">
					<?php echo get_avatar( get_the_author_meta( 'ID' ), 96 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="author-box-content">
						<h2 class="author-name"><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></h2>
						<p class="author-description"><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
					</div>
				</footer>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_sidebar();
get_footer();
