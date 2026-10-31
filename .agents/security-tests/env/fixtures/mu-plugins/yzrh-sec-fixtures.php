<?php
/**
 * 検証環境専用のフィクスチャ（本番には配置しない）。
 *
 * テーマが前提とするカスタム投稿タイプ interview とタクソノミー interview_category は
 * テーマ外（プラグイン等）で登録される想定のため、検証環境ではここで登録する。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'interview',
			array(
				'label'       => 'Interviews',
				'public'      => true,
				'has_archive' => 'interview',
				'rewrite'     => array( 'slug' => 'interview' ),
				'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'author' ),
				'show_in_rest' => true,
			)
		);
		register_taxonomy( 'interview_category', 'interview', array( 'label' => 'Interview Categories', 'public' => true, 'show_in_rest' => true ) );
	}
);
