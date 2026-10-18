<?php
/**
 * 管理・クエリ制御。
 *
 * メインクエリの並び順など、テンプレートより前（pre_get_posts）で決める必要がある処理をまとめる。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * インタビュー一覧の並び順の値を "ASC" か "DESC" に正規化する。
 *
 * なぜ必要か: URL の `?order=` は利用者が自由に書き換えられるため、想定外の値をそのまま
 * WP_Query に渡さないよう、許可した 2 値以外は既定値に置き換える。
 * 文字列以外は受け付けない。`?order[]=x` は配列で届き、PHP 8 では文字列関数に配列を渡すと
 * TypeError（致命的エラー）になるため。
 *
 * @param mixed  $value   生の値。
 * @param string $default 値がない・不正なときに使う値。
 * @return string "ASC" または "DESC"。
 */
function yzrh_normalize_interview_order( $value, $default = 'DESC' ) {
	if ( ! is_string( $value ) ) {
		return $default;
	}

	$order = strtoupper( trim( $value ) );

	return in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : $default;
}

/**
 * `?order=` で指定されたインタビュー一覧の並び順を返す。
 *
 * なぜ必要か: クエリのフック（下の yzrh_interview_archive_ordering()）と、一覧テンプレートの
 * 並び替えセレクト（archive-interview.php）が同じ関数を使うことで、
 * 「実際の並び順」と「画面で選択中の表示」が必ず一致する。
 *
 * @return string "ASC" または "DESC"。
 */
function yzrh_get_interview_order() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示順を切り替えるだけの読み取り専用パラメータ。
	$raw = isset( $_GET['order'] ) ? wp_unslash( $_GET['order'] ) : null;

	return yzrh_normalize_interview_order( $raw );
}

/**
 * インタビュー一覧（interview のアーカイブ）のメインクエリに並び順を適用する。
 *
 * なぜ必要か: テンプレート内で並び替えると件数やページ送りと矛盾するため、
 * クエリ実行前の pre_get_posts で公開日順と昇順／降順を指定する。
 * 管理画面・サブクエリ・他のページには影響させない。
 *
 * @param WP_Query $query クエリオブジェクト。
 */
function yzrh_interview_archive_ordering( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'interview' ) ) {
		return;
	}

	$query->set( 'orderby', 'date' );
	$query->set( 'order', yzrh_get_interview_order() );
}
add_action( 'pre_get_posts', 'yzrh_interview_archive_ordering' );
