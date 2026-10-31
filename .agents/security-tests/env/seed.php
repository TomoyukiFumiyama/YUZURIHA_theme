<?php
/**
 * 検証環境にダミーデータを投入する（wp eval-file で実行）。
 *
 * 投入するのはダミーの利用者・投稿だけ。パスワードはランダムに生成し、どこにも出力しない
 * （テストは認証クッキーではなく wp_set_current_user() とログアウト状態の HTTP で行う）。
 * 投入した ID は wp option `yzrh_sec_fixture` に保存し、テストから参照する。
 *
 * 目印の文字列（SECRET-* / MEMBER-* / YZRHXSS）は、レスポンスに漏れていないかを
 * 判定するためのもので、実データではない。
 */

// WP-CLI の eval-file からだけ実行する（Web からは何もしない）。
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit( 1 );
}

$fixture = array();

// --- 利用者（未ログインはユーザー ID 0 として扱う） ---
$roles = array(
	'admin'     => 'administrator',
	'editor'    => 'editor',
	'author'    => 'author',
	'contrib_a' => 'contributor',
	'contrib_b' => 'contributor',
	'member_a'  => 'subscriber',
	'member_b'  => 'subscriber',
);
foreach ( $roles as $login => $role ) {
	$user = get_user_by( 'login', $login );
	$id   = $user ? $user->ID : wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password( 32, true, true ),
			'user_email'   => $login . '@example.test',
			'role'         => $role,
			'display_name' => 'Dummy ' . $login,
		)
	);
	$fixture['users'][ $login ] = (int) $id;
}

// 投稿のない会員 A のプロフィール（公開されるべきでない想定の値）。
wp_update_user(
	array(
		'ID'           => $fixture['users']['member_a'],
		'display_name' => 'MEMBER-A-DISPLAY',
		'description'  => 'MEMBER-A-BIO private note',
		'user_url'     => 'https://member-a.example.test/profile',
	)
);

wp_set_current_user( $fixture['users']['admin'] );

/**
 * 実ファイルのない添付ファイルを作り、アイキャッチ用の ID を返す。
 */
$make_attachment = function ( $name ) {
	$id = wp_insert_attachment(
		array(
			'post_title'     => $name,
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		),
		false
	);
	update_post_meta( $id, '_wp_attached_file', '2026/10/' . $name . '.jpg' );
	wp_update_attachment_metadata( $id, array( 'width' => 1200, 'height' => 630, 'file' => '2026/10/' . $name . '.jpg' ) );
	return (int) $id;
};

// --- 通常の公開投稿 ---
$fixture['post_public'] = wp_insert_post(
	array(
		'post_title'   => 'Public dummy post',
		'post_content' => 'Public body.',
		'post_excerpt' => 'Public excerpt.',
		'post_status'  => 'publish',
		'post_author'  => $fixture['users']['author'],
	)
);
set_post_thumbnail( $fixture['post_public'], $make_attachment( 'public-post-cover' ) );

// --- パスワード保護された投稿（アイキャッチ・手動抜粋あり） ---
$fixture['post_protected'] = wp_insert_post(
	array(
		'post_title'    => 'Protected dummy post',
		'post_content'  => 'SECRET-BODY-POST',
		'post_excerpt'  => 'SECRET-EXCERPT-POST',
		'post_status'   => 'publish',
		'post_password' => 'dummy-' . wp_generate_password( 12, false ),
		'post_author'   => $fixture['users']['author'],
	)
);
set_post_thumbnail( $fixture['post_protected'], $make_attachment( 'secret-protected-post-cover' ) );

// --- パスワード保護されたインタビュー（カスタムフィールド・長い本文あり） ---
$fixture['interview_protected'] = wp_insert_post(
	array(
		'post_type'     => 'interview',
		'post_title'    => 'Protected dummy interview',
		'post_content'  => str_repeat( 'S', 6000 ),
		'post_status'   => 'publish',
		'post_password' => 'dummy-' . wp_generate_password( 12, false ),
		'post_author'   => $fixture['users']['author'],
		'post_date'     => '2026-10-01 10:00:00',
	)
);
update_post_meta( $fixture['interview_protected'], 'interview_byline_name', 'SECRET-BYLINE-NAME' );
update_post_meta( $fixture['interview_protected'], 'interview_byline_role', 'SECRET-BYLINE-ROLE' );
update_post_meta( $fixture['interview_protected'], 'interview_kicker', 'SECRET-KICKER' );
set_post_thumbnail( $fixture['interview_protected'], $make_attachment( 'secret-protected-interview-cover' ) );

// --- 投稿者（寄稿者）が入力した想定の攻撃文字列を持つインタビュー ---
// 寄稿者の権限で保存し（kses が働く）、公開は管理者が行う（レビュー後の公開を再現）。
wp_set_current_user( $fixture['users']['contrib_a'] );
$xss       = '"><svg/onload=YZRHXSS(1)><script>YZRHXSS(2)</script><img src=x onerror=YZRHXSS(3)>';
$speech    = array(
	'content'         => '<p>ok</p><script>YZRHXSS(10)</script><a href="javascript:YZRHXSS(11)">x</a><img src=x onerror=YZRHXSS(12)>',
	'speakerInitials' => '<script>YZRHXSS(13)</script>',
	'speakerTag'      => '"><img src=x onerror=YZRHXSS(14)>',
	'alignment'       => 'right" onmouseover="YZRHXSS(15)',
	'tone'            => 'answer" onclick="YZRHXSS(16)',
	'speakerStyle'    => 'guest" onfocus="YZRHXSS(17)" autofocus x="',
);
$pullquote = array(
	'quote'       => '<script>YZRHXSS(20)</script><b onclick="YZRHXSS(21)">q</b>',
	'attribution' => '<img src=x onerror=YZRHXSS(22)>',
);
$fixture['interview_xss'] = wp_insert_post(
	array(
		'post_type'    => 'interview',
		'post_title'   => 'XSS probe interview',
		'post_content' => '<!-- wp:yzrh/interview-speech ' . wp_json_encode( $speech ) . ' /-->' . "\n" .
			'<!-- wp:yzrh/interview-pullquote ' . wp_json_encode( $pullquote ) . ' /-->',
		'post_status'  => 'pending',
		'post_author'  => $fixture['users']['contrib_a'],
		'post_date'    => '2026-10-02 10:00:00',
	)
);
$meta_keys = array(
	'interview_issue', 'interview_kicker', 'interview_hero_title_html', 'interview_lead', 'interview_byline_name',
	'interview_byline_role', 'interview_byline_meta', 'interview_cover_caption', 'interview_cover_number',
	'interview_closing_label', 'interview_closing_title', 'interview_closing_text', 'interview_closing_link_text',
	'interview_logo_text', 'interview_volume_meta', 'interview_footer_text', 'interview_read_time',
);
foreach ( $meta_keys as $key ) {
	// 寄稿者は自分の投稿のカスタムフィールド（保護されていないキー）を編集できる。
	if ( current_user_can( 'edit_post_meta', $fixture['interview_xss'], $key ) ) {
		update_post_meta( $fixture['interview_xss'], $key, $xss );
	}
}
update_post_meta( $fixture['interview_xss'], 'interview_closing_url', 'javascript:YZRHXSS(30)' );
update_post_meta( $fixture['interview_xss'], 'interview_toc', "\"><img src=x onerror=YZRHXSS(31)>|label\nok|<script>YZRHXSS(32)</script>" );
update_post_meta( $fixture['interview_xss'], 'interview_marginalia', "<script>YZRHXSS(33)</script>|1\"><img src=x onerror=YZRHXSS(34)>|desc" );

wp_set_current_user( $fixture['users']['admin'] );
wp_publish_post( $fixture['interview_xss'] );

// --- 公開の親ページと、その下の公開ページ（対照） ---
$fixture['page_public_parent'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'PUBLIC-PARENT',
		'post_name'   => 'public-parent',
		'post_status' => 'publish',
	)
);
$fixture['page_child_of_public'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'Child of public parent',
		'post_name'   => 'child-of-public',
		'post_status' => 'publish',
		'post_parent' => $fixture['page_public_parent'],
	)
);

// --- 非公開の親ページと、その下の公開ページ ---
$fixture['page_private_parent'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'SECRET-PRIVATE-PARENT',
		'post_name'   => 'internal-parent',
		'post_status' => 'private',
	)
);
$fixture['page_child_of_private'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'Public child page',
		'post_name'   => 'public-child',
		'post_status' => 'publish',
		'post_parent' => $fixture['page_private_parent'],
	)
);
$fixture['page_draft_parent'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'SECRET-DRAFT-PARENT',
		'post_name'   => 'draft-parent',
		'post_status' => 'draft',
	)
);
$fixture['page_child_of_draft'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'Public child of draft',
		'post_name'   => 'child-of-draft',
		'post_status' => 'publish',
		'post_parent' => $fixture['page_draft_parent'],
	)
);

// --- コメント機能停止前から残っている承認済みコメント（既存データの扱いを確認する） ---
$fixture['legacy_comment'] = wp_insert_comment(
	array(
		'comment_post_ID'      => $fixture['post_public'],
		'comment_content'      => 'LEGACY-COMMENT',
		'comment_author'       => 'LEGACY-COMMENTER',
		'comment_author_email' => 'legacy-commenter@example.test',
		'comment_approved'     => 1,
	)
);

// --- GTM のコード（出力位置の確認用。実在しない ID） ---
set_theme_mod( 'gtm_head_code', "<!-- YZRH-GTM-HEAD -->\n<script>window.yzrhGtmHead=1;</script>" );
set_theme_mod( 'gtm_body_code', '<noscript><!-- YZRH-GTM-BODY --></noscript>' );

update_option( 'yzrh_sec_fixture', $fixture, false );
WP_CLI::success( 'fixture ids: ' . wp_json_encode( $fixture ) );
