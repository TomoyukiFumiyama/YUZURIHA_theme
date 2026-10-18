<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

status_header( 404 );
nocache_headers();

get_header();
?>

<script>
	(function () {
		/*
		 * 計測ツールへ PII（個人を特定できる情報）を送らないための整形。
		 * - クエリ文字列・ハッシュは送らない（メールアドレスやトークンが入り得る）。
		 * - パスの各セグメントのうち、メールアドレス・電話番号・UUID・長いトークンらしきものは伏せ字にする。
		 * - 外部サイトからのリファラーは origin だけを送る（他サイトのパスは制御できないため）。
		 */
		var REDACTED = '[redacted]';
		var PII_PATTERNS = [
			/[^\s@]+@[^\s@]+\.[^\s@]+/, // メールアドレス
			/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i, // UUID
			/^[A-Za-z0-9_+=]{20,}$/, // 区切りのない長い英数字（トークン・ハッシュ・Base64）
			/^[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}$/ // JWT
		];

		// 電話番号: 数字・区切り記号だけで構成され、数字が 10 桁以上（日付 2026-10-17 などは対象外）。
		function looksLikePhoneNumber(value) {
			return /^\+?[0-9()\- ]+$/.test(value) && value.replace(/[^0-9]/g, '').length >= 10;
		}

		function redactSegment(segment) {
			var decoded = segment;
			try {
				decoded = decodeURIComponent(segment);
			} catch (e) {
				return REDACTED;
			}
			if (looksLikePhoneNumber(decoded)) {
				return REDACTED;
			}
			for (var i = 0; i < PII_PATTERNS.length; i++) {
				if (PII_PATTERNS[i].test(decoded)) {
					return REDACTED;
				}
			}
			return segment;
		}

		function redactPath(pathname) {
			return String(pathname || '/').split('/').map(redactSegment).join('/');
		}

		function sanitizeUrl(url, keepPath) {
			if (!url) {
				return '';
			}
			try {
				var parsed = new URL(url);
				return parsed.origin + (keepPath ? redactPath(parsed.pathname) : '/');
			} catch (e) {
				return '';
			}
		}

		var referrerIsInternal = false;
		try {
			referrerIsInternal = !!document.referrer && new URL(document.referrer).origin === window.location.origin;
		} catch (e) {
			referrerIsInternal = false;
		}

		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: 'page_not_found',
			http_status: 404,
			requested_url: sanitizeUrl(window.location.href, true),
			requested_path: redactPath(window.location.pathname),
			referrer: sanitizeUrl(document.referrer, referrerIsInternal),
			page_title: document.title || ''
		});
	}());
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
				<?php $yzrh_contact_url = get_theme_mod( 'sns_contact_url' ); ?>
				<?php if ( $yzrh_contact_url ) : ?>
					<a class="site-404__button" href="<?php echo esc_url( $yzrh_contact_url ); ?>"><?php esc_html_e( 'お問い合わせ', 'yzrh' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
	</div>
</main>

<?php get_footer(); ?>
