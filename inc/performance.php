<?php
/**
 * パフォーマンス関連の拡張ポイント。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 絵文字用のスクリプト・スタイルを停止する（既定で停止。案件側の設定で戻せる）。
 *
 * なぜ必要か: WordPress は古いブラウザ向けに絵文字変換スクリプトを全ページへ出力する。
 * 現在のブラウザは絵文字を自前で表示できるため、既定では停止して読み込みを軽くする。
 * 絵文字の画像化が必要なサイトでは、テーマのコードを書き換えずに元へ戻せる:
 *
 *     add_filter( 'yzrh_disable_emoji_assets', '__return_false' );
 *
 * ファイル読み込み時ではなく `wp` アクション（wp_head より前。JSON-LD のスキーマ登録と同じ）で
 * 判定するため、プラグイン・子テーマ・`after_setup_theme`・`init`・`wp`（優先度 10 未満）の
 * どこでフィルターを追加しても効き、条件分岐タグ（is_front_page() など）も使える。
 * フィードとメールの絵文字の画像化（wp_staticize_emoji）は、表示速度に関係しないため残す。
 */
function yzrh_maybe_disable_emoji_assets() {
	if ( ! apply_filters( 'yzrh_disable_emoji_assets', true ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	// WordPress は互換性のため print_emoji_styles も登録しており、上の wp_enqueue_emoji_styles が
	// 実行時にこれを外している。wp_enqueue_emoji_styles を止めると残ってしまうため、こちらも外す。
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	// 埋め込み表示（/embed/）用。
	remove_action( 'embed_head', 'print_emoji_detection_script' );
	remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
}
add_action( 'wp', 'yzrh_maybe_disable_emoji_assets' );
