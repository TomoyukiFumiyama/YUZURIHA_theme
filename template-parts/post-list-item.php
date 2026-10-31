<?php
/**
 * Reusable post card for archive and search result lists.
 *
 * @package YUZURIHA_Theme
 *
 * @var array $args Optional CSS classes for the card elements.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'article_class' => 'post-list-item',
		'thumbnail_class' => 'post-list-thumb',
		'title_class'   => 'post-list-title',
		'excerpt_class' => 'post-list-excerpt',
	)
);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( sanitize_html_class( $args['article_class'] ) ); ?>>
	<?php if ( has_post_thumbnail() && ! post_password_required() ) : ?>
		<a class="<?php echo esc_attr( $args['thumbnail_class'] ); ?>" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
	<?php endif; ?>

	<h2 class="<?php echo esc_attr( $args['title_class'] ); ?>">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>
	<div class="<?php echo esc_attr( $args['excerpt_class'] ); ?>"><?php the_excerpt(); ?></div>
</article>
