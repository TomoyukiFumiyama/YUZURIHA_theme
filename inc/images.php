<?php
/**
 * 画像設定の拡張ポイント。
 *
 * ひな形自体は独自の画像サイズを持たない。案件ごとに必要なサイズだけをここに追加する。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * 案件固有の画像サイズは、ここで `after_setup_theme` にフックして登録する:
 *
 *     add_action( 'after_setup_theme', function () {
 *         add_image_size( 'project-card', 720, 480, true );
 *     } );
 *
 * 登録したサイズはアップロードのたびに全画像分が生成され、サーバー容量と
 * アップロード時間を消費する。テンプレートで実際に使うサイズだけを追加すること。
 */
