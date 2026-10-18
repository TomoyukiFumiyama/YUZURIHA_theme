<?php
/** Theme setup and widget registration. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
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

		// カスタマイザーの「サイト基本情報」でロゴを設定できるようにします。
		// header.php / footer.php は has_custom_logo() で表示を切り替え、未設定時はサイト名を表示します。
		add_theme_support(
			'custom-logo',
			apply_filters(
				'yzrh_custom_logo_args',
				array(
					'height'               => 80,
					'width'                => 240,
					'flex-height'          => true,
					'flex-width'           => true,
					'unlink-homepage-logo' => false,
				)
			)
		);

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
