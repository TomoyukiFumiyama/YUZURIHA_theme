<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

status_header( 404 );
nocache_headers();

get_header();
?>

<script>
	window.dataLayer = window.dataLayer || [];
	window.dataLayer.push({
		event: 'page_not_found',
		http_status: 404,
		requested_url: window.location.href,
		requested_path: window.location.pathname + window.location.search,
		referrer: document.referrer || '',
		page_title: document.title || ''
	});
</script>

<main id="primary" class="site-main site-404" role="main">
	<div class="site-404__inner">
		<header class="site-404__header">
			<p class="site-404__code" aria-hidden="true">404</p>
			<h1 class="site-404__title"><?php esc_html_e( 'ページが見つかりませんでした', 'yzrh' ); ?></h1>
			<p class="site-404__lead"><?php esc_html_e( 'URLが間違っているか、ページが移動・削除された可能性があります。', 'yzrh' ); ?></p>
		</header>

		<section class="site-404__actions" aria-label="<?php esc_attr_e( '次の操作', 'yzrh' ); ?>">
			<?php get_search_form(); ?>
			<div class="site-404__buttons">
				<a class="site-404__button site-404__button--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'トップへ戻る', 'yzrh' ); ?></a>
				<a class="site-404__button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'yzrh' ); ?></a>
			</div>
		</section>
	</div>
</main>

<?php get_footer(); ?>
