<?php
/** Front-end and editor assets. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function yzrh_enqueue_style_if_exists( $handle, $relative_path, $deps = array(), $version = null ) {
	$absolute_path = get_template_directory() . $relative_path;
	if ( ! file_exists( $absolute_path ) ) {
		return;
	}

	wp_enqueue_style( $handle, get_template_directory_uri() . $relative_path, $deps, $version );
}

function yzrh_enqueue_assets() {
	// キャッシュクリア用
	$current_time = date('YmdHis');
	// リセットCSS
	wp_enqueue_style( 'yzrh-reset', get_template_directory_uri() . '/css/reset.min.css', array(), $current_time );
	// SwiperのJSファイルとCSSファイルをCDNで読み込み
	wp_enqueue_style( 'yzrh-swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css', array(), '11.0' );
	wp_enqueue_script( 'yzrh-swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js', array(), '11.0', false );
	wp_enqueue_script( 'yzrh-footer', get_template_directory_uri() . '/js/footerscript.js', array(), $current_time, true );

	// 共通スタイルの読み込み
	wp_enqueue_style( 'yzrh-style', get_template_directory_uri() . '/css/style.css', array(), $current_time );
	wp_enqueue_style( 'yzrh-header', get_template_directory_uri() . '/css/header.css', array(), $current_time );
	wp_enqueue_style( 'yzrh-footer', get_template_directory_uri() . '/css/footer.css', array(), $current_time );

	// 条件分岐によるページ固有スタイルの読み込み。
	if ( is_404() ) {
		wp_enqueue_style( 'yzrh-404', get_template_directory_uri() . '/css/page/404.css', array(), $current_time );
	}

	if (is_front_page()) {
		$master_dep = array();
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css', array(), $current_time );
		if ( file_exists( get_template_directory() . '/assets/css/master.css' ) ) {
			$master_dep = array( 'yzrh-master' );
		}
		yzrh_enqueue_style_if_exists( 'yzrh-front-page', '/assets/css/front-page.css', $master_dep, $current_time );
	} elseif (is_post_type_archive('members')) {
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css', array(), $current_time );
		yzrh_enqueue_style_if_exists( 'yzrh-archive-members', '/assets/css/archive-members.css', array(), $current_time );
        } else if (is_page('company')) {
                yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css', array(), $current_time );
                yzrh_enqueue_style_if_exists( 'yzrh-company', '/assets/css/page-company.css', array(), $current_time );
        } else if (is_page_template('page-templates/job-details.php')) {
                wp_enqueue_style( 'yzrh-job-details', get_template_directory_uri() . '/css/page/job-details.css', array(), $current_time );
        } else if (is_page("thanks")) {
                yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css', array(), $current_time );
        } else if (is_singular('interview')) {
                wp_enqueue_style( 'yzrh-interview', get_template_directory_uri() . '/css/page/interview.css', array(), $current_time );
        } else if (is_post_type_archive('interview')) {
                wp_enqueue_style( 'yzrh-interview-archive', get_template_directory_uri() . '/css/page/interview-archive.css', array(), $current_time );
        }
}
add_action( 'wp_enqueue_scripts', 'yzrh_enqueue_assets' );
/**
 * Interview article custom blocks.
 */
function yzrh_register_interview_blocks() {
	$theme_version = wp_get_theme()->get( 'Version' );
	$script_path   = get_template_directory() . '/js/interview-blocks.js';
	$style_path    = get_template_directory() . '/css/page/interview.css';
	$script_ver    = file_exists( $script_path ) ? filemtime( $script_path ) : $theme_version;
	$style_ver     = file_exists( $style_path ) ? filemtime( $style_path ) : $theme_version;

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
			'api_version'     => 2,
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
			'api_version'     => 2,
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
