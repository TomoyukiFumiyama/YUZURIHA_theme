<?php
/**
 * テーマの初期設定とウィジェットエリアの登録。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'yzrh_setup' ) ) :
	/**
	 * テーマの基本的な設定を行う（after_setup_theme）。
	 *
	 * なぜ必要か: title タグ・アイキャッチ・ロゴ・メニューなどの機能は、テーマが
	 * add_theme_support() で宣言しないと WordPress 側で有効にならないため。
	 * after_setup_theme は init より前に実行されるので、ここで宣言するのが WordPress の推奨。
	 * 子テーマで丸ごと差し替えられるよう function_exists() で囲んでいる。
	 */
	function yzrh_setup() {
		/*
		 * テーマを翻訳可能にします。
		 * 翻訳ファイルは /languages/ ディレクトリに配置してください。
		 */
		load_theme_textdomain( 'yzrh', get_template_directory() . '/languages' );

		// <title> タグを WordPress に管理させる（header.php に <title> を直書きしない）。
		add_theme_support( 'title-tag' );

		// 投稿と固定ページでアイキャッチ画像を有効にします（OGP 画像・JSON-LD の image にも使う）。
		add_theme_support( 'post-thumbnails' );

		// カスタマイザーの「サイト基本情報」でロゴを設定できるようにします。
		// header.php / footer.php は has_custom_logo() で表示を切り替え、未設定時はサイト名を表示します。
		// JSON-LD の Organization.logo にも使われます。
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

		// RSS フィードのリンクを <head> に出力します。
		add_theme_support( 'automatic-feed-links' );

		/*
		 * 検索フォーム、コメントフォーム、コメントリストなどで
		 * 最新の HTML5 マークアップを使用できるようにします。
		 * style / script を指定すると、<style> <script> に不要な type 属性が付かなくなります。
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

		// このテーマで使用するナビゲーションメニューを登録します（header.php / footer.php の wp_nav_menu()）。
		register_nav_menus(
			array(
				'primary' => esc_html__( 'Primary Menu', 'yzrh' ),
				'footer'  => esc_html__( 'Footer Menu', 'yzrh' ),
			)
		);
	}
endif;
add_action( 'after_setup_theme', 'yzrh_setup' );

/*
 * ブロックのスタイルを、ページで実際に使われているブロックの分だけ読み込む。
 * ブロックテーマではない（クラシック）テーマでは、WordPress の既定値が false のため、
 * 使っていないブロックも含めたスタイルシート（wp-block-library）を全ページで丸ごと読み込んでしまう。
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * ウィジェットエリア（サイドバー）を登録する（widgets_init）。
 *
 * なぜ必要か: 管理画面の「ウィジェット」からサイドバーの内容を編集できるようにするため。
 * sidebar.php の dynamic_sidebar( 'sidebar-1' ) がここで登録した ID を参照する。
 * 見出しは h2 にして、本文の見出し構造（h1 → h2）を崩さないようにしている。
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
