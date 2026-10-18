<?php
/**
 * Archive template for Interview posts.
 *
 * @package YUZURIHA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'yzrh_interview_archive_taxonomy' ) ) {
	/**
	 * Returns the taxonomy used for interview archive chips.
	 *
	 * Only taxonomies actually attached to the interview post type are used,
	 * so blog categories (and their post counts/links) never appear here
	 * unless they were registered for interviews. `interview_category` is
	 * preferred, then the first public taxonomy other than post formats.
	 *
	 * @return string Taxonomy name, or '' when interviews have none.
	 */
	function yzrh_interview_archive_taxonomy() {
		$taxonomies = get_object_taxonomies( 'interview', 'objects' );
		if ( empty( $taxonomies ) ) {
			return '';
		}

		if ( isset( $taxonomies['interview_category'] ) ) {
			return 'interview_category';
		}

		foreach ( $taxonomies as $name => $taxonomy ) {
			if ( 'post_format' !== $name && ! empty( $taxonomy->public ) ) {
				return $name;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'yzrh_interview_archive_description' ) ) {
	/**
	 * Returns the archive lead text.
	 *
	 * @return string
	 */
	function yzrh_interview_archive_description() {
		$description = get_the_archive_description();
		if ( $description ) {
			return wp_strip_all_tags( $description );
		}

		return '';
	}
}

if ( ! function_exists( 'yzrh_interview_archive_terms' ) ) {
	/**
	 * Returns visible category/filter terms for the archive chips.
	 *
	 * @return WP_Term[]
	 */
	function yzrh_interview_archive_terms() {
		$taxonomy = yzrh_interview_archive_taxonomy();
		if ( '' === $taxonomy ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 7,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}
}

if ( ! function_exists( 'yzrh_interview_archive_primary_term' ) ) {
	/**
	 * Returns the primary archive label for an interview post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function yzrh_interview_archive_primary_term( $post_id ) {
		$taxonomy = yzrh_interview_archive_taxonomy();
		$terms    = '' === $taxonomy ? array() : get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return __( 'Interview', 'yzrh' );
		}

		return $terms[0]->name;
	}
}

if ( ! function_exists( 'yzrh_interview_archive_term_count' ) ) {
	/**
	 * Returns the number of non-empty interview terms (not capped like the chips).
	 *
	 * @return int
	 */
	function yzrh_interview_archive_term_count() {
		$taxonomy = yzrh_interview_archive_taxonomy();
		if ( '' === $taxonomy ) {
			return 0;
		}

		$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );

		return is_wp_error( $count ) ? 0 : (int) $count;
	}
}

if ( ! function_exists( 'yzrh_interview_archive_last_published' ) ) {
	/**
	 * Returns the publish date of the newest interview, formatted for display.
	 *
	 * @return string Formatted date, or '' when no interview is published.
	 */
	function yzrh_interview_archive_last_published() {
		$last = get_lastpostdate( 'blog', 'interview' );

		return $last ? mysql2date( 'Y.m.d', $last ) : '';
	}
}

if ( ! function_exists( 'yzrh_interview_archive_read_time' ) ) {
	/**
	 * Returns reading-time text for an interview post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function yzrh_interview_archive_read_time( $post_id ) {
		$read_time = get_post_meta( $post_id, 'interview_read_time', true );
		if ( $read_time ) {
			return sanitize_text_field( $read_time );
		}

		$content = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
		$count   = function_exists( 'mb_strlen' ) ? mb_strlen( $content ) : strlen( $content );
		$minutes = max( 1, (int) ceil( $count / 600 ) );

		return sprintf( _n( '%s min', '%s min', $minutes, 'yzrh' ), number_format_i18n( $minutes ) );
	}
}

if ( ! function_exists( 'yzrh_interview_archive_byline' ) ) {
	/**
	 * Returns byline data for a card.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	function yzrh_interview_archive_byline( $post_id ) {
		$name = get_post_meta( $post_id, 'interview_byline_name', true );
		$role = get_post_meta( $post_id, 'interview_byline_role', true );

		if ( ! $name ) {
			$name = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
		}

		if ( ! $role ) {
			$role = __( 'Interviewer', 'yzrh' );
		}

		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );

		return array(
			'name'    => sanitize_text_field( $name ),
			'role'    => sanitize_text_field( $role ),
			'initial' => sanitize_text_field( strtoupper( $initial ) ),
		);
	}
}

if ( ! function_exists( 'yzrh_interview_archive_card' ) ) {
	/**
	 * Renders one interview archive card.
	 *
	 * @param WP_Post $post  Post object.
	 * @param int     $index One-based index for the current page.
	 * @return void
	 */
	function yzrh_interview_archive_card( $post, $index ) {
		$post_id    = (int) $post->ID;
		$term_label = yzrh_interview_archive_primary_term( $post_id );
		$byline     = yzrh_interview_archive_byline( $post_id );
		$thumb_mod  = ( ( $index - 1 ) % 12 ) + 1;
		?>
		<article <?php post_class( 'interview-archive-card', $post_id ); ?>>
			<div class="interview-archive-card__num"><?php echo esc_html( sprintf( '%02d', $index ) ); ?></div>
			<a class="interview-archive-card__thumb interview-archive-card__thumb--t<?php echo esc_attr( $thumb_mod ); ?>" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
				<span class="interview-archive-card__thumb-inner">
					<?php if ( has_post_thumbnail( $post_id ) ) : ?>
						<?php echo get_the_post_thumbnail( $post_id, 'medium_large' ); ?>
					<?php endif; ?>
					<span class="interview-archive-card__pattern" aria-hidden="true"></span>
				</span>
				<span class="interview-archive-card__thumb-label"><?php echo esc_html( $term_label ); ?></span>
			</a>
			<div class="interview-archive-card__meta">
				<span class="interview-archive-card__cat"><?php echo esc_html( $term_label ); ?></span>
				<time class="interview-archive-card__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post_id ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d', $post_id ) ); ?></time>
				<span class="interview-archive-card__read"><?php echo esc_html( yzrh_interview_archive_read_time( $post_id ) ); ?></span>
			</div>
			<h3 class="interview-archive-card__title"><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
			<p class="interview-archive-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 70 ) ); ?></p>
			<div class="interview-archive-card__byline">
				<div class="interview-archive-card__avatar interview-archive-card__avatar--<?php echo esc_attr( ( $index % 4 ) + 1 ); ?>"><?php echo esc_html( $byline['initial'] ); ?></div>
				<div class="interview-archive-card__name"><?php echo esc_html( $byline['name'] ); ?><small><?php echo esc_html( $byline['role'] ); ?></small></div>
			</div>
		</article>
		<?php
	}
}

global $wp_query;
$archive_posts  = $wp_query instanceof WP_Query ? $wp_query->posts : array();
$featured_post  = ! empty( $archive_posts ) ? $archive_posts[0] : null;
$grid_posts     = $featured_post ? array_slice( $archive_posts, 1 ) : $archive_posts;
$total_posts    = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
$current_page   = max( 1, (int) get_query_var( 'paged' ) );
$posts_per_page = $wp_query instanceof WP_Query ? (int) $wp_query->get( 'posts_per_page' ) : (int) get_option( 'posts_per_page' );
$shown_start    = $total_posts > 0 ? ( ( $current_page - 1 ) * $posts_per_page ) + 1 : 0;
$shown_end      = $total_posts > 0 ? min( $total_posts, $shown_start + count( $archive_posts ) - 1 ) : 0;
$total_pages    = $wp_query instanceof WP_Query ? (int) $wp_query->max_num_pages : 1;
$terms          = yzrh_interview_archive_terms();
$term_count     = yzrh_interview_archive_term_count();
$last_published = yzrh_interview_archive_last_published();
$archive_url    = get_post_type_archive_link( 'interview' );
$description    = yzrh_interview_archive_description();
$archive_title  = post_type_archive_title( '', false );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php if ( $description ) : ?>
		<meta name="description" content="<?php echo esc_attr( $description ); ?>">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'interview-archive-page' ); ?>>
<?php wp_body_open(); ?>

<header class="interview-archive-topbar">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="interview-archive-topbar__logo" rel="home"><?php bloginfo( 'name' ); ?><span>.</span></a>
	<nav class="interview-archive-topbar__nav" aria-label="<?php esc_attr_e( 'Primary', 'yzrh' ); ?>">
		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'interview-archive-topbar__menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		<?php else : ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'yzrh' ); ?></a>
			<a href="<?php echo esc_url( $archive_url ); ?>" class="is-active" aria-current="page"><?php echo esc_html( $archive_title ); ?></a>
		<?php endif; ?>
	</nav>
</header>

<nav class="interview-archive-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'yzrh' ); ?>">
	<ol>
		<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'yzrh' ); ?></a></li>
		<li aria-current="page"><?php echo esc_html( $archive_title ); ?></li>
	</ol>
</nav>

<main id="primary" class="interview-archive-main">
	<section class="interview-archive-hero">
		<div class="interview-archive-hero__issue"><?php esc_html_e( 'Archive / All Interviews', 'yzrh' ); ?></div>
		<h1><?php echo esc_html( $archive_title ); ?><span class="dot">.</span></h1>
		<?php if ( $description ) : ?>
			<p class="interview-archive-hero__lead"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<div class="interview-archive-stats">
			<div class="interview-archive-stat">
				<div class="interview-archive-stat__figure"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?><span class="unit"><?php esc_html_e( '記事', 'yzrh' ); ?></span></div>
				<div class="interview-archive-stat__label"><?php esc_html_e( 'Total Interviews', 'yzrh' ); ?></div>
			</div>
			<?php if ( $term_count > 0 ) : ?>
				<div class="interview-archive-stat">
					<div class="interview-archive-stat__figure"><?php echo esc_html( number_format_i18n( $term_count ) ); ?><span class="unit"><?php esc_html_e( '分野', 'yzrh' ); ?></span></div>
					<div class="interview-archive-stat__label"><?php esc_html_e( 'Categories', 'yzrh' ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $last_published ) : ?>
				<div class="interview-archive-stat">
					<div class="interview-archive-stat__figure"><?php echo esc_html( $last_published ); ?></div>
					<div class="interview-archive-stat__label"><?php esc_html_e( 'Latest', 'yzrh' ); ?></div>
				</div>
			<?php endif; ?>
			<div class="interview-archive-stat">
				<div class="interview-archive-stat__figure"><?php echo esc_html( number_format_i18n( count( $archive_posts ) ) ); ?><span class="unit"><?php esc_html_e( '本', 'yzrh' ); ?></span></div>
				<div class="interview-archive-stat__label"><?php esc_html_e( 'This Page', 'yzrh' ); ?></div>
			</div>
		</div>
	</section>

	<section class="interview-archive-filter" aria-label="<?php esc_attr_e( 'Filters', 'yzrh' ); ?>">
		<div class="interview-archive-filter__inner">
			<div class="interview-archive-categories">
				<span class="interview-archive-categories__label"><?php esc_html_e( 'Category', 'yzrh' ); ?></span>
				<a class="interview-archive-chip is-active" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'All', 'yzrh' ); ?> <span class="count"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span></a>
				<?php foreach ( $terms as $term ) : ?>
					<?php $term_link = get_term_link( $term ); ?>
					<?php if ( ! is_wp_error( $term_link ) ) : ?>
						<a class="interview-archive-chip" href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term->name ); ?> <span class="count"><?php echo esc_html( number_format_i18n( (int) $term->count ) ); ?></span></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<form class="interview-archive-sort" method="get" action="<?php echo esc_url( $archive_url ); ?>">
				<label for="interview-archive-sort-select"><?php esc_html_e( 'Sort by', 'yzrh' ); ?></label>
				<select id="interview-archive-sort-select" name="order" aria-label="<?php esc_attr_e( 'Sort interviews', 'yzrh' ); ?>" onchange="this.form.submit()">
					<option value="DESC" <?php selected( yzrh_get_interview_order(), 'DESC' ); ?>><?php esc_html_e( '新着順', 'yzrh' ); ?></option>
					<option value="ASC" <?php selected( yzrh_get_interview_order(), 'ASC' ); ?>><?php esc_html_e( '古い順', 'yzrh' ); ?></option>
				</select>
			</form>
		</div>
	</section>

	<?php if ( $featured_post instanceof WP_Post ) : ?>
		<?php
		$featured_id    = (int) $featured_post->ID;
		$featured_term  = yzrh_interview_archive_primary_term( $featured_id );
		$featured_byline = yzrh_interview_archive_byline( $featured_id );
		?>
		<section class="interview-archive-featured">
			<div class="interview-archive-featured__marker"><?php esc_html_e( 'Featured Story', 'yzrh' ); ?></div>
			<article class="interview-archive-featured-card">
				<a class="interview-archive-featured-card__image" href="<?php echo esc_url( get_permalink( $featured_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $featured_id ) ); ?>">
					<?php if ( has_post_thumbnail( $featured_id ) ) : ?>
						<?php echo get_the_post_thumbnail( $featured_id, 'large' ); ?>
					<?php endif; ?>
					<span class="interview-archive-featured-card__badge"><?php esc_html_e( 'Featured', 'yzrh' ); ?></span>
				</a>
				<div class="interview-archive-featured-card__body">
					<div class="interview-archive-featured-card__meta">
						<span class="cat"><?php echo esc_html( $featured_term ); ?></span>
						<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $featured_id ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d', $featured_id ) ); ?></time>
						<span><?php echo esc_html( yzrh_interview_archive_read_time( $featured_id ) ); ?></span>
					</div>
					<h2><a href="<?php echo esc_url( get_permalink( $featured_id ) ); ?>"><?php echo esc_html( get_the_title( $featured_id ) ); ?></a></h2>
					<p class="interview-archive-featured-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured_id ), 110 ) ); ?></p>
					<div class="interview-archive-featured-card__byline">
						<div class="interview-archive-featured-card__avatar"><?php echo esc_html( $featured_byline['initial'] ); ?></div>
						<div class="interview-archive-featured-card__name"><?php echo esc_html( $featured_byline['name'] ); ?><small><?php echo esc_html( $featured_byline['role'] ); ?></small></div>
					</div>
				</div>
			</article>
		</section>
	<?php endif; ?>

	<section class="interview-archive-grid-wrap">
		<div class="interview-archive-grid-head">
			<h2><?php esc_html_e( 'Latest Interviews', 'yzrh' ); ?><span class="dot">.</span></h2>
			<div class="interview-archive-grid-count">
				<?php
				printf(
					/* translators: 1: first shown post, 2: last shown post, 3: total posts. */
					esc_html__( 'Showing %1$s — %2$s of %3$s', 'yzrh' ),
					esc_html( number_format_i18n( $shown_start ) ),
					esc_html( number_format_i18n( $shown_end ) ),
					esc_html( number_format_i18n( $total_posts ) )
				);
				?>
			</div>
		</div>

		<?php if ( ! empty( $grid_posts ) ) : ?>
			<div class="interview-archive-grid">
				<?php foreach ( $grid_posts as $index => $post_item ) : ?>
					<?php yzrh_interview_archive_card( $post_item, $shown_start + $index + 1 ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="interview-archive-empty"><?php esc_html_e( 'インタビュー記事はまだありません。', 'yzrh' ); ?></p>
		<?php endif; ?>
	</section>

	<?php if ( $total_pages > 1 ) : ?>
		<section class="interview-archive-pagination-wrap">
			<nav class="interview-archive-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'yzrh' ); ?>">
				<div class="interview-archive-pagination__info">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages, 3: first shown post, 4: last shown post, 5: total posts. */
						esc_html__( 'Page %1$s / %2$s — Showing %3$s—%4$s of %5$s', 'yzrh' ),
						esc_html( number_format_i18n( $current_page ) ),
						esc_html( number_format_i18n( $total_pages ) ),
						esc_html( number_format_i18n( $shown_start ) ),
						esc_html( number_format_i18n( $shown_end ) ),
						esc_html( number_format_i18n( $total_posts ) )
					);
					?>
				</div>
				<div class="interview-archive-page-nav">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'total'     => $total_pages,
								'current'   => $current_page,
								'mid_size'  => 2,
								'prev_text' => '<span class="ic">←</span><span>' . esc_html__( 'Prev', 'yzrh' ) . '</span>',
								'next_text' => '<span>' . esc_html__( 'Next', 'yzrh' ) . '</span><span class="ic">→</span>',
							)
						)
					);
					?>
				</div>
			</nav>
		</section>
	<?php endif; ?>

</main>

<footer class="interview-archive-footer">
	<div class="interview-archive-footer__top">
		<div>
			<div class="interview-archive-footer__logo"><?php bloginfo( 'name' ); ?><span>.</span></div>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<p class="interview-archive-footer__tagline"><?php bloginfo( 'description' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="interview-archive-footer__col">
			<h2><?php esc_html_e( 'Sections', 'yzrh' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( $archive_title ); ?></a></li>
				<?php foreach ( $terms as $term ) : ?>
					<?php $term_link = get_term_link( $term ); ?>
					<?php if ( ! is_wp_error( $term_link ) ) : ?>
						<li><a href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<div class="interview-archive-footer__col">
				<h2><?php esc_html_e( 'About', 'yzrh' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			</div>
		<?php endif; ?>
		<div class="interview-archive-footer__col">
			<h2><?php esc_html_e( 'Social', 'yzrh' ); ?></h2>
			<?php yzrh_output_sns_icons(); ?>
		</div>
	</div>
	<div class="interview-archive-footer__bottom">
		<div>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></div>
		<?php $privacy_link = get_the_privacy_policy_link(); ?>
		<?php if ( $privacy_link ) : ?>
			<div><?php echo wp_kses_post( $privacy_link ); ?></div>
		<?php endif; ?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
