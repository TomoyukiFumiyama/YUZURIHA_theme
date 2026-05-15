<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

if (! function_exists( 'yzrh_setup' ) ) :
	/**
	 * テーマの基本的な設定を行います。
	 */
	function yzrh_setup() {
		/*
		 * テーマを翻訳可能にします。
		 * 翻訳ファイルは /languages/ ディレクトリに配置してください。
		 */
		load_theme_textdomain( 'yzrh', get_template_directory(). '/languages' );

		// <title>タグをWordPressに管理させるためにテーマサポートを追加します。
		add_theme_support( 'title-tag' );

		// 投稿と固定ページでアイキャッチ画像を有効にします。
		add_theme_support( 'post-thumbnails' );

		// RSSフィードのリンクを<head>に出力します。
		add_theme_support( 'automatic-feed-links' );

		/*
		 * 検索フォーム、コメントフォーム、コメントリストなどで
		 * 最新のHTML5マークアップを使用できるようにします。
		 */
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
			)
		);

		// このテーマで使用するナビゲーションメニューを登録します。
		register_nav_menus(
			array(
				'primary' => esc_html__( 'Primary Menu', 'yzrh' ),
				'footer'  => esc_html__( 'Footer Menu', 'yzrh' ),
			)
		);
	}
endif;
add_action( 'after_setup_theme', 'yzrh_setup' );

function yzrh_enqueue_assets() {
	// キャッシュクリア用
	$current_time = date('YmdHis');
	// リセットCSS
	wp_enqueue_style( 'yzrh-reset', get_template_directory_uri() . '/css/reset.min.css', array(), $current_time );
	// SwiperのJSファイルとCSSファイルをCDNで読み込み
	wp_enqueue_style( 'yzrh-swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css', array(), '11.0' );
	wp_enqueue_script( 'yzrh-swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js', array(), '11.0', false );

	// 共通スタイルの読み込み
	wp_enqueue_style( 'yzrh-style', get_template_directory_uri() . '/css/style.css', array(), $current_time );
	wp_enqueue_style( 'yzrh-header', get_template_directory_uri() . '/css/header.css', array(), $current_time );
	wp_enqueue_style( 'yzrh-footer', get_template_directory_uri() . '/css/footer.css', array(), $current_time );

	// 条件分岐によるスタイルの読み込み（ここは構成によって変えてください。）
	if (is_front_page()) {
		wp_enqueue_style( 'yzrh-master', get_template_directory_uri() . '/assets/css/master.css', array(), $current_time );
		wp_enqueue_style( 'yzrh-front-page', get_template_directory_uri() . '/assets/css/front-page.css', array( 'yzrh-master' ), $current_time );
	} elseif (is_post_type_archive('members')) {
		wp_enqueue_style( 'yzrh-master', get_template_directory_uri() . '/assets/css/master.css', array(), $current_time );
		wp_enqueue_style( 'yzrh-archive-members', get_template_directory_uri() . '/assets/css/archive-members.css', array(), $current_time );
        } else if (is_page('company')) {
                wp_enqueue_style( 'yzrh-master', get_template_directory_uri() . '/assets/css/master.css', array(), $current_time );
                wp_enqueue_style( 'yzrh-company', get_template_directory_uri() . '/assets/css/page-company.css', array(), $current_time );
        } else if (is_page_template('job-details.php')) {
                wp_enqueue_style( 'yzrh-job-details', get_template_directory_uri() . '/css/job-details.css', array(), $current_time );
        } else if (is_page("thanks")) {
                wp_enqueue_style( 'yzrh-master', get_template_directory_uri() . '/assets/css/master.css', array(), $current_time );
        } else if (is_singular('interview')) {
                wp_enqueue_style( 'yzrh-interview', get_template_directory_uri() . '/css/interview.css', array(), $current_time );
        } else if (is_post_type_archive('interview')) {
                wp_enqueue_style( 'yzrh-interview-archive', get_template_directory_uri() . '/css/interview-archive.css', array(), $current_time );
        }
}
add_action( 'wp_enqueue_scripts', 'yzrh_enqueue_assets' );


/**
 * Applies archive ordering for interview list requests.
 *
 * @param WP_Query $query Query object.
 */
function yzrh_interview_archive_ordering( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'interview' ) ) {
		return;
	}

	$order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
		$order = 'DESC';
	}

	$query->set( 'orderby', 'date' );
	$query->set( 'order', $order );
}
add_action( 'pre_get_posts', 'yzrh_interview_archive_ordering' );

/**
 * ウィジェットエリア（サイドバー）を登録します。
 */
function yzrh_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Main Sidebar', 'yzrh' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here to appear in your sidebar.', 'yzrh' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'yzrh_widgets_init' );

// Core settings used by feature modules.
require_once get_template_directory() . '/features/core/settings/init.php';

// Structured data (JSON-LD) output.
require_once get_template_directory() . '/features/seo/structured-data/init.php';

// Security hardening helpers.
require_once get_template_directory() . '/features/security/init.php';


/**
 * Interview article custom blocks.
 */
function yzrh_register_interview_blocks() {
	$theme_version = wp_get_theme()->get( 'Version' );
	$script_path   = get_template_directory() . '/js/interview-blocks.js';
	$style_path    = get_template_directory() . '/css/interview.css';
	$script_ver    = file_exists( $script_path ) ? filemtime( $script_path ) : $theme_version;
	$style_ver     = file_exists( $style_path ) ? filemtime( $style_path ) : $theme_version;

	wp_register_style(
		'yzrh-interview-blocks',
		get_template_directory_uri() . '/css/interview.css',
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
