<?php
/**
 * Template for single Interview posts.
 *
 * Custom fields (all optional) can override the editorial chrome:
 * interview_issue, interview_kicker, interview_hero_title_html, interview_lead,
 * interview_byline_name, interview_byline_role, interview_byline_meta,
 * interview_cover_caption, interview_cover_number, interview_toc,
 * interview_marginalia, interview_closing_label, interview_closing_title,
 * interview_closing_text, interview_closing_url, interview_closing_link_text.
 *
 * interview_toc format: one item per line, "anchor-id|label".
 * interview_marginalia format: one item per line, "label|headline/stat|description".
 * The body column is normal post content; use the Interview Speech Bubble and
 * Interview Pull Quote blocks for dialogue and pull quotes.
 *
 * @package YUZURIHA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'yzrh_interview_meta' ) ) {
	function yzrh_interview_meta( $key, $default = '' ) {
		$value = get_post_meta( get_the_ID(), $key, true );
		return '' !== $value ? $value : $default;
	}
}

if ( ! function_exists( 'yzrh_interview_lines' ) ) {
	function yzrh_interview_lines( $key, $fallback ) {
		$raw   = yzrh_interview_meta( $key, $fallback );
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}
}

$post_id = get_queried_object_id();
$issue   = yzrh_interview_meta( 'interview_issue', __( 'In-depth Interview / No. 042', 'yzrh' ) );
$kicker  = yzrh_interview_meta( 'interview_kicker', __( '— マーケティングの現場で、いま起きていること。', 'yzrh' ) );
$lead    = yzrh_interview_meta( 'interview_lead', get_the_excerpt( $post_id ) );

$default_title_html = esc_html( get_the_title( $post_id ) );
$title_html         = yzrh_interview_meta( 'interview_hero_title_html', $default_title_html );

$toc_fallback = "ch1|広告だけの集客に限界を感じて\nch2|導入から提案までのスピード\nch3|数字で語れる、改善の確かさ\nch4|なぜAIを作ろうと思ったのか\nch5|人とAI、それぞれの役割\nch6|Webデータは未来のお宝";
$marginalia_fallback = "KEY FACT|262%|スマホ版トップページからの新規会員登録数の伸び。たった1度のファーストビュー改修によって達成された数字。\nWHAT IS|AIアナリスト|Googleアナリティクスやサーチコンソールに連携するだけで、サイト改善の提案を出してくれる人工知能ツール。\nTIME|1hr|導入時、Googleアナリティクスと連携するためにかかった時間。";
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..900;1,9..144,300..900&family=Noto+Serif+JP:wght@300;400;500;700;900&family=Zen+Kaku+Gothic+New:wght@300;400;500;700;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'single-interview' ); ?>>
<?php wp_body_open(); ?>
<div class="interview-progress-bar" id="interview-progress"></div>

<header class="interview-topbar">
	<a class="interview-topbar__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( yzrh_interview_meta( 'interview_logo_text', 'Editorial' ) ); ?><span>.</span></a>
	<div class="interview-topbar__meta"><?php echo esc_html( yzrh_interview_meta( 'interview_volume_meta', 'Vol. 042 — Spring 2026 — Interview' ) ); ?></div>
</header>

<?php while ( have_posts() ) : the_post(); ?>
	<main id="primary" class="interview-main">
		<section class="interview-hero" aria-labelledby="interview-title-<?php the_ID(); ?>">
			<div class="interview-hero__issue"><?php echo esc_html( $issue ); ?></div>
			<?php if ( $kicker ) : ?>
				<p class="interview-hero__kicker"><?php echo esc_html( $kicker ); ?></p>
			<?php endif; ?>
			<h1 id="interview-title-<?php the_ID(); ?>" class="interview-hero__title">
				<?php echo wp_kses( $title_html, array( 'br' => array(), 'em' => array(), 'mark' => array( 'class' => true ), 'span' => array( 'class' => true ) ) ); ?>
			</h1>

			<div class="interview-hero__grid">
				<?php if ( $lead ) : ?>
					<p class="interview-hero__lead"><?php echo wp_kses_post( $lead ); ?></p>
				<?php endif; ?>
				<div class="interview-byline-card">
					<div class="interview-byline-card__name"><?php echo esc_html( yzrh_interview_meta( 'interview_byline_name', get_the_author() ) ); ?></div>
					<div class="interview-byline-card__role"><?php echo esc_html( yzrh_interview_meta( 'interview_byline_role', __( 'Interviewer', 'yzrh' ) ) ); ?></div>
					<div class="interview-byline-card__meta">
						<?php echo wp_kses_post( yzrh_interview_meta( 'interview_byline_meta', '📍 <strong>Tokyo</strong> <span>⏱ <strong>12 min read</strong></span> <span>📅 <strong>' . esc_html( get_the_date( 'Y.m' ) ) . '</strong></span>' ) ); ?>
					</div>
				</div>
			</div>
		</section>

		<div class="interview-cover">
			<div class="interview-cover__frame">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'large' ); ?>
				<?php endif; ?>
			</div>
			<div class="interview-cover__caption">
				<span><?php echo esc_html( yzrh_interview_meta( 'interview_cover_caption', __( 'Photography — Studio Editorial', 'yzrh' ) ) ); ?></span>
				<span class="interview-cover__num"><?php echo esc_html( yzrh_interview_meta( 'interview_cover_number', __( 'Cover / 01', 'yzrh' ) ) ); ?></span>
			</div>
		</div>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'interview-article' ); ?>>
			<aside class="interview-index" aria-label="<?php esc_attr_e( 'Interview contents', 'yzrh' ); ?>">
				<div class="interview-index__label"><?php esc_html_e( 'CONTENTS', 'yzrh' ); ?></div>
				<ol>
					<?php foreach ( yzrh_interview_lines( 'interview_toc', $toc_fallback ) as $line ) : ?>
						<?php list( $anchor, $label ) = array_pad( array_map( 'trim', explode( '|', $line, 2 ) ), 2, '' ); ?>
						<?php if ( $anchor && $label ) : ?>
							<li><a href="#<?php echo esc_attr( sanitize_html_class( $anchor ) ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ol>
			</aside>

			<div class="interview-body entry-content">
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

			<aside class="interview-marginalia" aria-label="<?php esc_attr_e( 'Interview notes', 'yzrh' ); ?>">
				<?php foreach ( yzrh_interview_lines( 'interview_marginalia', $marginalia_fallback ) as $line ) : ?>
					<?php list( $label, $headline, $description ) = array_pad( array_map( 'trim', explode( '|', $line, 3 ) ), 3, '' ); ?>
					<?php if ( $label && $headline ) : ?>
						<div class="interview-margin-note">
							<span class="interview-margin-note__label"><?php echo esc_html( $label ); ?></span>
							<div class="<?php echo preg_match( '/^[0-9,.%]+/', $headline ) ? 'interview-margin-note__stat' : 'interview-margin-note__title'; ?>"><?php echo esc_html( $headline ); ?></div>
							<?php if ( $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</aside>
		</article>

		<section class="interview-closing">
			<div class="interview-closing__wrap">
				<div class="interview-closing__label"><?php echo esc_html( yzrh_interview_meta( 'interview_closing_label', __( 'A NOTE FROM THE EDITOR', 'yzrh' ) ) ); ?></div>
				<h2><?php echo wp_kses_post( yzrh_interview_meta( 'interview_closing_title', __( '機械と人間の<em>仕事の在り方</em>は、これから変わっていく。', 'yzrh' ) ) ); ?></h2>
				<p><?php echo wp_kses_post( yzrh_interview_meta( 'interview_closing_text', __( 'インタビュー本文の補足や編集後記をカスタムフィールド interview_closing_text から表示できます。', 'yzrh' ) ) ); ?></p>
				<a class="interview-closing__cta" href="<?php echo esc_url( yzrh_interview_meta( 'interview_closing_url', home_url( '/' ) ) ); ?>">
					<?php echo esc_html( yzrh_interview_meta( 'interview_closing_link_text', __( '詳しく見る', 'yzrh' ) ) ); ?> <span aria-hidden="true">→</span>
				</a>
			</div>
		</section>
	</main>
<?php endwhile; ?>

<footer class="interview-footer">
	<div class="interview-footer__logo"><?php echo esc_html( yzrh_interview_meta( 'interview_logo_text', 'Editorial' ) ); ?>.</div>
	<div><?php echo esc_html( yzrh_interview_meta( 'interview_footer_text', 'INTERVIEW SERIES — © ' . gmdate( 'Y' ) . ' ALL RIGHTS RESERVED' ) ); ?></div>
</footer>

<script>
(function () {
	const progress = document.getElementById('interview-progress');
	function updateProgress() {
		const h = document.documentElement;
		const max = h.scrollHeight - h.clientHeight;
		const pct = max > 0 ? (h.scrollTop / max) * 100 : 0;
		if (progress) progress.style.width = pct + '%';
	}
	window.addEventListener('scroll', updateProgress, { passive: true });
	updateProgress();

	if (!('IntersectionObserver' in window)) {
		document.querySelectorAll('.yzrh-interview-bubble-row').forEach((el) => el.classList.add('is-visible'));
		return;
	}
	const io = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				entry.target.classList.add('is-visible');
				io.unobserve(entry.target);
			}
		});
	}, { threshold: 0.15 });
	document.querySelectorAll('.yzrh-interview-bubble-row').forEach((el) => io.observe(el));
}());
</script>
<?php wp_footer(); ?>
</body>
</html>
