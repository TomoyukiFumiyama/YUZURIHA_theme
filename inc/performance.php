<?php
/**
 * パフォーマンス関連の拡張ポイント。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 絵文字用のスクリプト・スタイルを、案件側の設定で停止できるようにする。
 *
 * なぜ必要か: WordPress は古いブラウザ向けに絵文字変換スクリプトを全ページへ出力する。
 * 不要なサイトでは停止すると読み込みが軽くなるが、必要なサイトもあるため既定では停止せず、
 * フィルターで有効にしたときだけ停止する。テーマのコードを書き換えずに切り替えられる:
 *
 *     add_filter( 'yzrh_disable_emoji_assets', '__return_true' );
 *
 * ファイル読み込み時ではなく `wp` アクション（wp_head より前。JSON-LD のスキーマ登録と同じ）で
 * 判定するため、プラグイン・子テーマ・`after_setup_theme`・`init`・`wp`（優先度 10 未満）の
 * どこでフィルターを追加しても効き、条件分岐タグ（is_front_page() など）も使える。
 */
function yzrh_maybe_disable_emoji_assets() {
	if ( ! apply_filters( 'yzrh_disable_emoji_assets', false ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' ); // WordPress 6.4 未満。
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' ); // WordPress 6.4 以降。
}
add_action( 'wp', 'yzrh_maybe_disable_emoji_assets' );
