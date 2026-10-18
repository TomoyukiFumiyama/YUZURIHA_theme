<?php
/**
 * Template Name: Job Details
 * Template Post Type: page
 *
 * 求人詳細ページ用のレイアウト。募集要項・選考フロー・FAQ・応募導線などの
 * 求人固有の情報は、固定ページ本文（ブロックエディター）で入力する。
 * テンプレートには特定企業の求人情報や外部リンクを直書きしない。
 *
 * @package yzrh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">
    <?php while ( have_posts() ) : the_post(); ?>
        <section class="hero">
            <div class="container hero__container">
                <div class="hero__content">
                    <?php the_title( '<h1 class="hero__title">', '</h1>' ); ?>
                    <?php if ( has_excerpt() ) : ?>
                        <p class="hero__summary"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="hero__image-wrapper">
                        <?php the_post_thumbnail( 'large', array( 'class' => 'hero__image' ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="job-details">
            <div class="container job-details__container">
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'job-details__main entry-content' ); ?>>
                    <?php the_content(); ?>
                </article>
            </div>
        </section>
    <?php endwhile; ?>
</main>

<?php
get_footer();
