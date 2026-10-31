<?php
/**
 * テーマ共通の設定とヘルパー関数。
 *
 * 他のモジュールから使われるため、functions.php で最初に読み込む。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * テーマ設定の既定値を返す。
 *
 * なぜ必要か: 設定項目と既定値の一覧をここに集約し、どの機能を ON/OFF できるかを
 * 1 か所で確認できるようにする。seo_jsonld_* は JSON-LD の種類ごとの出力スイッチで、
 * inc/seo.php の yzrh_register_structured_data_schemas() がこのキーを見て出力を決める。
 * 案件ごとの変更は `yzrh_core_default_settings` フィルターで行える。
 *
 * @return array<string, mixed>
 */
function yzrh_core_get_default_settings() {
	$defaults = array(
		'display_show_sidebar_post'       => true, // 投稿詳細のサイドバー（single.php）。
		'display_show_sidebar_archive'    => true, // アーカイブのサイドバー（archive.php）。
		'display_show_author_box'         => true, // 投稿詳細の著者ボックス（single.php）。
		'seo_jsonld_enabled'              => true, // JSON-LD 全体の出力。
		'seo_jsonld_website'              => true, // WebSite。
		'seo_jsonld_organization'         => true, // Organization。
		'seo_jsonld_breadcrumbs'          => true, // BreadcrumbList。
		'seo_jsonld_blogposting'          => true, // 投稿詳細の BlogPosting。
		'seo_jsonld_service'              => true, // サービスページの Service。
		'seo_jsonld_blog'                 => true, // ブログ一覧の Blog。
		'seo_jsonld_blog_itemlist'        => true, // ブログ一覧の ItemList。
		'seo_jsonld_author'               => true, // 筆者ページの Person。
		'seo_jsonld_interview_collection' => true, // インタビュー一覧の CollectionPage。
	);
	return apply_filters( 'yzrh_core_default_settings', $defaults );
}

/**
 * 既定値と保存済みの値（`yzrh_settings` オプション）を合わせた設定を返す。
 *
 * なぜ必要か: オプションが未保存・壊れている（配列でない）場合でも、常に全キーがそろった
 * 配列を返し、呼び出し側で存在チェックを書かなくて済むようにする。
 * 既定値にないキーは array_intersect_key() で捨て、不要な値が混ざらないようにする。
 *
 * @return array<string, mixed>
 */
function yzrh_core_get_settings() {
	$defaults = yzrh_core_get_default_settings();
	$stored   = get_option( 'yzrh_settings', array() );
	$stored   = is_array( $stored ) ? array_intersect_key( $stored, $defaults ) : array();
	return wp_parse_args( $stored, $defaults );
}

if ( ! function_exists( 'yzrh_interview_archive_description' ) ) {
	/**
	 * インタビュー一覧の説明文をプレーンテキストで返す。
	 *
	 * なぜ必要か: 一覧ページのメタディスクリプション・ヒーローのリード文（archive-interview.php）・
	 * CollectionPage の JSON-LD（inc/seo.php）が同じ説明文を使うための唯一の取得元。
	 * 説明文が空のときはどこでも省略し、案件固有の文言で埋めないようにする。
	 * 子テーマで差し替えられるよう function_exists() で囲んでいる。
	 *
	 * @return string プレーンテキスト。説明文がなければ ''。
	 */
	function yzrh_interview_archive_description() {
		$description = get_the_archive_description();
		if ( $description ) {
			return trim( wp_strip_all_tags( $description ) );
		}

		return '';
	}
}

/**
 * 投稿の抜粋（get_the_excerpt() の結果）を返す。同じリクエストの中では 2 回目以降に結果を使い回す。
 *
 * なぜ必要か: 手動の抜粋がない投稿では、get_the_excerpt() が本文全体に `the_content` フィルター
 * （ブロックの描画・ショートコードなど）をかけて抜粋を作るため重い。インタビュー詳細では
 * リード文（single-interview.php）と OGP の説明文（inc/seo.php）が同じ抜粋を使うため、1 回で済ませる。
 * 保存先はリクエストの間だけ有効なキャッシュグループ（永続オブジェクトキャッシュにも保存しない）なので、
 * 投稿を更新しても古い抜粋が残ることはなく、パスワード入力の有無による違いも閲覧者ごとに正しく扱われる。
 *
 * @param int $post_id 投稿 ID。
 * @return string
 */
function yzrh_get_the_excerpt( $post_id ) {
	wp_cache_add_non_persistent_groups( 'yzrh_excerpts' );

	$post_id = (int) $post_id;
	$found   = false;
	$excerpt = wp_cache_get( $post_id, 'yzrh_excerpts', false, $found );
	if ( ! $found ) {
		$excerpt = (string) get_the_excerpt( $post_id );
		wp_cache_set( $post_id, $excerpt, 'yzrh_excerpts' );
	}

	return $excerpt;
}

/**
 * テーマ設定の値を 1 つ返す。
 *
 * なぜ必要か: 各機能が設定配列の構造を知らなくても、キーだけで値を取り出せるようにする。
 * get_option() は WordPress がキャッシュするため、何度呼んでも DB への問い合わせは増えない。
 *
 * @param string $key     設定キー。
 * @param mixed  $default キーが存在しないときに返す値。
 * @return mixed
 */
function yzrh_get_setting( $key, $default = null ) {
	$settings = yzrh_core_get_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}
