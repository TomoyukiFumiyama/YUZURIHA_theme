<?php
/** Front-end and editor assets. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Returns a cache-busting version for a theme file.
 *
 * The version changes only when the file changes, so browsers and CDNs can
 * cache assets between deployments and still pick up every update.
 *
 * @param string $relative_path Path relative to the theme directory, starting with "/".
 * @return string
 */
function yzrh_asset_version( $relative_path ) {
	$absolute_path = get_template_directory() . $relative_path;
	if ( file_exists( $absolute_path ) ) {
		return (string) filemtime( $absolute_path );
	}

	return (string) wp_get_theme()->get( 'Version' );
}

/**
 * Enqueues a theme stylesheet with a file-based version.
 *
 * @param string $handle        Style handle.
 * @param string $relative_path Path relative to the theme directory.
 * @param array  $deps          Dependencies.
 */
function yzrh_enqueue_theme_style( $handle, $relative_path, $deps = array() ) {
	wp_enqueue_style( $handle, get_template_directory_uri() . $relative_path, $deps, yzrh_asset_version( $relative_path ) );
}

function yzrh_enqueue_style_if_exists( $handle, $relative_path, $deps = array() ) {
	if ( ! file_exists( get_template_directory() . $relative_path ) ) {
		return;
	}

	yzrh_enqueue_theme_style( $handle, $relative_path, $deps );
}

function yzrh_enqueue_assets() {
	// リセットCSS
	yzrh_enqueue_theme_style( 'yzrh-reset', '/css/reset.min.css' );
	// Swiper（CDN・URL 自体がバージョン固定）は登録だけ行い、使うページでのみ読み込む。
	// テーマ自身は Swiper を使っていないため既定では読み込まない。
	wp_register_style( 'yzrh-swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css', array(), '11.0.5' );
	wp_register_script(
		'yzrh-swiper',
		'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js',
		array(),
		'11.0.5',
		array(
			'in_footer' => true,
			'strategy'  => 'defer', // WordPress 6.3+。それ以前は in_footer のみ有効。
		)
	);

	/**
	 * Filters whether Swiper is loaded on the current page.
	 *
	 * Return true on pages that contain a slider, e.g.
	 * add_filter( 'yzrh_use_swiper', 'is_front_page' );
	 * Scripts that call `new Swiper()` should declare 'yzrh-swiper' as a
	 * dependency (or run on DOMContentLoaded) because it is deferred.
	 *
	 * @param bool $use_swiper Default false.
	 */
	if ( apply_filters( 'yzrh_use_swiper', false ) ) {
		wp_enqueue_style( 'yzrh-swiper' );
		wp_enqueue_script( 'yzrh-swiper' );
	}
	wp_enqueue_script( 'yzrh-footer', get_template_directory_uri() . '/js/footerscript.js', array(), yzrh_asset_version( '/js/footerscript.js' ), true );

	// 共通スタイルの読み込み
	yzrh_enqueue_theme_style( 'yzrh-style', '/css/style.css' );
	yzrh_enqueue_theme_style( 'yzrh-header', '/css/header.css' );
	yzrh_enqueue_theme_style( 'yzrh-footer', '/css/footer.css' );
	yzrh_enqueue_theme_style( 'yzrh-sns-icons', '/css/component/sns-icons.css' );

	// 条件分岐によるページ固有スタイルの読み込み。
	if ( is_404() ) {
		yzrh_enqueue_theme_style( 'yzrh-404', '/css/page/404.css' );
	}

	if (is_front_page()) {
		$master_dep = array();
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
		if ( file_exists( get_template_directory() . '/assets/css/master.css' ) ) {
			$master_dep = array( 'yzrh-master' );
		}
		yzrh_enqueue_style_if_exists( 'yzrh-front-page', '/assets/css/front-page.css', $master_dep );
	} elseif (is_post_type_archive('members')) {
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
		yzrh_enqueue_style_if_exists( 'yzrh-archive-members', '/assets/css/archive-members.css' );
        } else if (is_page('company')) {
                yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
                yzrh_enqueue_style_if_exists( 'yzrh-company', '/assets/css/page-company.css' );
        } else if (is_page_template('page-templates/job-details.php')) {
                yzrh_enqueue_theme_style( 'yzrh-job-details', '/css/page/job-details.css' );
        } else if (is_page("thanks")) {
                yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
        } else if (is_singular('interview')) {
                yzrh_enqueue_theme_style( 'yzrh-interview', '/css/page/interview.css' );
        } else if (is_post_type_archive('interview')) {
                yzrh_enqueue_theme_style( 'yzrh-interview-archive', '/css/page/interview-archive.css' );
        }
}
add_action( 'wp_enqueue_scripts', 'yzrh_enqueue_assets' );
/**
 * Interview article custom blocks.
 */
function yzrh_register_interview_blocks() {
	$script_ver = yzrh_asset_version( '/js/interview-blocks.js' );
	$style_ver  = yzrh_asset_version( '/css/page/interview.css' );

	wp_register_style(
		'yzrh-interview-blocks',
		get_template_directory_uri() . '/css/page/interview.css',
		array(),
		$style_ver
	);

	wp_register_script(
		'yzrh-interview-blocks',
		get_template_directory_uri() . '/js/interview-blocks.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
		$script_ver,
		true
	);

	register_block_type(
		'yzrh/interview-speech',
		array(
			'api_version'     => 3, // Block API v3 (WordPress 6.3+). Must match API_VERSION in js/interview-blocks.js.
			'editor_script'   => 'yzrh-interview-blocks',
			'editor_style'    => 'yzrh-interview-blocks',
			'render_callback' => 'yzrh_render_interview_speech_block',
			'attributes'      => array(
				'content'         => array( 'type' => 'string', 'default' => '' ),
				'speakerInitials' => array( 'type' => 'string', 'default' => 'S' ),
				'speakerTag'      => array( 'type' => 'string', 'default' => 'interviewer' ),
				'alignment'       => array( 'type' => 'string', 'default' => 'left' ),
				'tone'            => array( 'type' => 'string', 'default' => 'question' ),
				'speakerStyle'    => array( 'type' => 'string', 'default' => 'host' ),
			),
		)
	);

	register_block_type(
		'yzrh/interview-pullquote',
		array(
			'api_version'     => 3, // Block API v3 (WordPress 6.3+). Must match API_VERSION in js/interview-blocks.js.
			'editor_script'   => 'yzrh-interview-blocks',
			'editor_style'    => 'yzrh-interview-blocks',
			'render_callback' => 'yzrh_render_interview_pullquote_block',
			'attributes'      => array(
				'quote'       => array( 'type' => 'string', 'default' => '' ),
				'attribution' => array( 'type' => 'string', 'default' => '' ),
			),
		)
	);
}
add_action( 'init', 'yzrh_register_interview_blocks' );

function yzrh_render_interview_speech_block( $attributes ) {
	$content          = isset( $attributes['content'] ) ? wp_kses_post( $attributes['content'] ) : '';
	$speaker_initials = isset( $attributes['speakerInitials'] ) ? sanitize_text_field( $attributes['speakerInitials'] ) : '';
	$speaker_tag      = isset( $attributes['speakerTag'] ) ? sanitize_text_field( $attributes['speakerTag'] ) : '';
	$alignment        = isset( $attributes['alignment'] ) && 'right' === $attributes['alignment'] ? 'right' : 'left';
	$tone             = isset( $attributes['tone'] ) && 'answer' === $attributes['tone'] ? 'answer' : 'question';
	$speaker_style    = isset( $attributes['speakerStyle'] ) ? sanitize_html_class( $attributes['speakerStyle'] ) : 'host';

	$row_classes = array( 'wp-block-yzrh-interview-speech', 'yzrh-interview-bubble-row' );
	if ( 'right' === $alignment ) {
		$row_classes[] = 'is-right';
	}

	$bubble_classes = array( 'yzrh-interview-bubble', 'is-' . $tone );
	if ( 'guest-b' === $speaker_style ) {
		$bubble_classes[] = 'is-guest-b';
	}

	$speaker_classes = array( 'yzrh-interview-speaker', 'is-' . $speaker_style );

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
		<div class="<?php echo esc_attr( implode( ' ', $speaker_classes ) ); ?>">
			<?php echo esc_html( $speaker_initials ); ?>
			<?php if ( $speaker_tag ) : ?>
				<div class="yzrh-interview-speaker__tag"><?php echo esc_html( $speaker_tag ); ?></div>
			<?php endif; ?>
		</div>
		<div class="<?php echo esc_attr( implode( ' ', $bubble_classes ) ); ?>">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function yzrh_render_interview_pullquote_block( $attributes ) {
	$quote       = isset( $attributes['quote'] ) ? wp_kses_post( $attributes['quote'] ) : '';
	$attribution = isset( $attributes['attribution'] ) ? sanitize_text_field( $attributes['attribution'] ) : '';

	ob_start();
	?>
	<aside class="wp-block-yzrh-interview-pullquote yzrh-interview-pullquote">
		<?php if ( $quote ) : ?>
			<p class="yzrh-interview-pullquote__text"><?php echo $quote; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<?php if ( $attribution ) : ?>
			<div class="yzrh-interview-pullquote__attr"><?php echo esc_html( $attribution ); ?></div>
		<?php endif; ?>
	</aside>
	<?php
	return ob_get_clean();
}
