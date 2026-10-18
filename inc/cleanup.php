<?php
/**
 * <head> 内の不要なタグの削除。
 *
 * サイトの動作に影響しない（安全に消せる）ものだけを対象にする。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// <meta name="generator" content="WordPress x.x"> を削除する。WordPress のバージョンを外部に知らせないため。
// security.php でも同じ処理をしているが、どちらかのファイルを無効化しても残るよう両方に置いている。
remove_action( 'wp_head', 'wp_generator' );
// Windows Live Writer 用のマニフェストリンク。対応ソフトはすでに提供終了しており不要。
remove_action( 'wp_head', 'wlwmanifest_link' );
// 外部ブログエディター用の RSD（Really Simple Discovery）リンク。XML-RPC を停止しているため不要。
remove_action( 'wp_head', 'rsd_link' );
