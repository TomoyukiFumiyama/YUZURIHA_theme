<?php
/**
 * テーマの起動ファイル（ブートストラップ）。
 *
 * 機能のコードはすべて /inc に役割ごとに分けて置き、このファイルは読み込むだけにする。
 * こうしておくと、不具合が起きたときに該当ファイルの行をコメントアウトするだけで
 * その機能だけを切り離せる（AGENTS.md「7. 復旧方針」）。
 *
 * @package yzrh
 */

// WordPress を経由しない直接アクセスでは何もしない（全 PHP ファイル共通のガード）。
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 読み込むモジュールの一覧。
 * helpers.php は他のモジュールが使う共通関数（yzrh_get_setting() など）を定義するので先頭に置く。
 * 現状、各モジュールは他モジュールの関数をフック実行時にしか呼ばないため、それ以外の並び順に依存はない。
 */
$yzrh_modules = array(
	'helpers.php',     // 共通設定とヘルパー関数。
	'setup.php',       // テーマサポート・メニュー・ウィジェットの登録。
	'assets.php',      // CSS / JS の読み込みとインタビュー用ブロック。
	'security.php',    // セキュリティヘッダー・XML-RPC 停止・コメント機能停止。
	'performance.php', // 絵文字スクリプト停止などの高速化オプション。
	'seo.php',         // OGP・canonical・パンくず・JSON-LD 構造化データ。
	'admin.php',       // インタビュー一覧の並び順などクエリの調整。
	'images.php',      // 画像サイズを追加するための拡張ポイント。
	'cleanup.php',     // <head> 内の不要なタグの削除。
	'customizer.php',  // カスタマイザーの SNS アイコン設定と出力関数。
);

// get_template_directory() は親テーマのパスを返すので、子テーマから使っても /inc が正しく見つかる。
foreach ( $yzrh_modules as $yzrh_module ) {
	require_once get_template_directory() . '/inc/' . $yzrh_module;
}

// ファイルスコープの変数はグローバル変数になるため、読み込み後に後片付けする。
unset( $yzrh_module, $yzrh_modules );
