<?php
/**
 * WordPress 統合テスト（隔離環境で `wp eval-file` から実行）。
 *
 * 各テストは「安全な状態」を期待値として書く。FAIL は脆弱性（またはその前提条件）が
 * 再現したことを意味し、[F-xx] は .agents/findings.md の ID。
 * 状態を変更したテストは最後に元へ戻す（テーマ mod を退避・復元）。
 */

// WP-CLI の eval-file からだけ実行する（Web からは何もしない）。
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit( 1 );
}

require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';

$fixture = get_option( 'yzrh_sec_fixture' );
$users   = $fixture['users'];
$GLOBALS['yzrh_sec_results'] = array( 'pass' => 0, 'fail' => 0 );

function yzrh_sec_test( $name, callable $fn ) {
	$results = &$GLOBALS['yzrh_sec_results'];
	try {
		$fn();
		$results['pass']++;
		echo "PASS  {$name}\n";
	} catch ( Throwable $e ) {
		$results['fail']++;
		echo "FAIL  {$name}\n      " . str_replace( "\n", "\n      ", $e->getMessage() ) . "\n";
	}
}

function yzrh_sec_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** 管理者から unfiltered_html だけを外す（マルチサイトのサイト管理者 / DISALLOW_UNFILTERED_HTML 相当）。 */
function yzrh_sec_strip_unfiltered_html( $allcaps ) {
	$allcaps['unfiltered_html'] = false;
	return $allcaps;
}

/**
 * 指定した利用者として、カスタマイザーの設定を表示・保存できるかを返す。
 *
 * setting_capability_only は、設定単体の capability だけで判定した結果（参考値）。
 *
 * @return array{visible:bool, saved:bool, setting_capability_only:bool}
 */
function yzrh_sec_try_customizer_save( $user_id, $setting_id, $value, $strip_unfiltered = false ) {
	$before = get_theme_mod( $setting_id, '' );
	wp_set_current_user( $user_id );
	if ( $strip_unfiltered ) {
		add_filter( 'user_has_cap', 'yzrh_sec_strip_unfiltered_html' );
	}

	// カスタマイザー画面（customize.php）と保存処理（customize_save）は、まず 'customize'
	// （= edit_theme_options）を確認する。設定ごとの capability はその後に評価される。
	$can_customize = current_user_can( 'customize' );
	$manager       = new WP_Customize_Manager( array( 'settings_previewed' => false ) );
	do_action( 'customize_register', $manager );
	$setting       = $manager->get_setting( $setting_id );
	$setting_level = $setting && $setting->check_capabilities();
	$visible       = $can_customize && $setting_level;
	$saved         = false;
	if ( $setting && $can_customize ) {
		$manager->set_post_value( $setting_id, $value );
		$setting->save();
		$saved = get_theme_mod( $setting_id, '' ) !== $before;
	}

	remove_filter( 'user_has_cap', 'yzrh_sec_strip_unfiltered_html' );
	set_theme_mod( $setting_id, $before );
	wp_set_current_user( 0 );

	return array( 'visible' => (bool) $visible, 'saved' => (bool) $saved, 'setting_capability_only' => (bool) $setting_level );
}

/* ---------------------------------------------------------------------- */

$actors = array(
	'anonymous'                     => array( 0, false ),
	'member_a (subscriber)'         => array( $users['member_a'], false ),
	'member_b (subscriber)'         => array( $users['member_b'], false ),
	'contrib_a (contributor)'       => array( $users['contrib_a'], false ),
	'author'                        => array( $users['author'], false ),
	'editor'                        => array( $users['editor'], false ),
	'admin'                         => array( $users['admin'], false ),
	'admin without unfiltered_html' => array( $users['admin'], true ),
);

$matrix = array();
foreach ( $actors as $label => $actor ) {
	$matrix[ $label ]['sns_x_url']     = yzrh_sec_try_customizer_save( $actor[0], 'sns_x_url', 'https://x.example.test/probe', $actor[1] );
	$matrix[ $label ]['gtm_head_code'] = yzrh_sec_try_customizer_save( $actor[0], 'gtm_head_code', '<script>/*YZRH-GTM-PROBE*/</script>', $actor[1] );
	$matrix[ $label ]['gtm_body_code'] = yzrh_sec_try_customizer_save( $actor[0], 'gtm_body_code', '<noscript>YZRH-GTM-PROBE</noscript>', $actor[1] );
}
echo 'MATRIX ' . wp_json_encode( $matrix ) . "\n";

yzrh_sec_test( 'customizer: only administrators can save SNS URLs (edit_theme_options)', function () use ( $matrix ) {
	foreach ( $matrix as $label => $row ) {
		$expected = 0 === strpos( $label, 'admin' );
		yzrh_sec_assert( $expected === $row['sns_x_url']['saved'], "{$label}: saved=" . var_export( $row['sns_x_url']['saved'], true ) );
	}
} );

yzrh_sec_test( 'customizer: only administrators with unfiltered_html can see or save GTM code', function () use ( $matrix ) {
	foreach ( $matrix as $label => $row ) {
		$expected = 'admin' === $label;
		foreach ( array( 'gtm_head_code', 'gtm_body_code' ) as $id ) {
			yzrh_sec_assert( $expected === $row[ $id ]['visible'], "{$label} {$id}: visible=" . var_export( $row[ $id ]['visible'], true ) );
			yzrh_sec_assert( $expected === $row[ $id ]['saved'], "{$label} {$id}: saved=" . var_export( $row[ $id ]['saved'], true ) );
		}
	}
} );

yzrh_sec_test( 'customizer: a published changeset cannot carry GTM code for an admin without unfiltered_html', function () use ( $users ) {
	$before = get_theme_mod( 'gtm_head_code', '' );
	wp_set_current_user( $users['admin'] );
	add_filter( 'user_has_cap', 'yzrh_sec_strip_unfiltered_html' );
	$manager = new WP_Customize_Manager( array( 'changeset_uuid' => wp_generate_uuid4(), 'settings_previewed' => false ) );
	do_action( 'customize_register', $manager );
	$result = $manager->save_changeset_post(
		array(
			'data'   => array( 'gtm_head_code' => array( 'value' => '<script>/*YZRH-CHANGESET-PROBE*/</script>' ) ),
			'status' => 'publish',
		)
	);
	remove_filter( 'user_has_cap', 'yzrh_sec_strip_unfiltered_html' );
	$after = get_theme_mod( 'gtm_head_code', '' );
	set_theme_mod( 'gtm_head_code', $before );
	wp_set_current_user( 0 );
	yzrh_sec_assert( false === strpos( (string) $after, 'YZRH-CHANGESET-PROBE' ), 'changeset wrote the GTM code: ' . ( is_wp_error( $result ) ? $result->get_error_code() : 'ok' ) );
} );

yzrh_sec_test( 'customizer: GTM sanitize callback rejects non-strings and users without unfiltered_html', function () use ( $users ) {
	wp_set_current_user( $users['admin'] );
	foreach ( array( array( 'x' ), 1, null, true, new stdClass() ) as $value ) {
		yzrh_sec_assert( '' === yzrh_sanitize_tag_manager_code( $value ), 'accepted ' . gettype( $value ) );
	}
	wp_set_current_user( $users['editor'] );
	yzrh_sec_assert( current_user_can( 'unfiltered_html' ), 'precondition: editors have unfiltered_html on single sites' );
	wp_set_current_user( $users['author'] );
	yzrh_sec_assert( '' === yzrh_sanitize_tag_manager_code( '<script></script>' ), 'author value accepted' );
	wp_set_current_user( 0 );
} );

yzrh_sec_test( 'precondition: a contributor can write every interview_* custom field of their own post (attacker input source)', function () use ( $users, $fixture ) {
	wp_set_current_user( $users['contrib_a'] );
	$denied = array();
	foreach ( array( 'interview_hero_title_html', 'interview_lead', 'interview_byline_meta', 'interview_closing_url', 'interview_toc' ) as $key ) {
		if ( ! current_user_can( 'edit_post_meta', $fixture['interview_xss'], $key ) ) {
			// 公開済み投稿は寄稿者が編集できないため、下書きの段階で書けるかを確かめる。
			$draft = wp_insert_post( array( 'post_type' => 'interview', 'post_title' => 'tmp', 'post_status' => 'draft', 'post_author' => $users['contrib_a'] ) );
			if ( ! current_user_can( 'edit_post_meta', $draft, $key ) ) {
				$denied[] = $key;
			}
			wp_delete_post( $draft, true );
		}
	}
	wp_set_current_user( 0 );
	yzrh_sec_assert( empty( $denied ), 'contributor cannot edit: ' . implode( ', ', $denied ) );
} );

yzrh_sec_test( 'control: contributor B cannot edit contributor A\'s interview fields (core authorization unchanged by theme)', function () use ( $users ) {
	$draft = wp_insert_post( array( 'post_type' => 'interview', 'post_title' => 'tmp', 'post_status' => 'draft', 'post_author' => $users['contrib_a'] ) );
	wp_set_current_user( $users['contrib_b'] );
	$can = current_user_can( 'edit_post_meta', $draft, 'interview_lead' ) || current_user_can( 'edit_post', $draft );
	wp_set_current_user( 0 );
	wp_delete_post( $draft, true );
	yzrh_sec_assert( ! $can, 'contrib_b can edit contrib_a draft' );
} );

/* --------------------------- property-based --------------------------- */

mt_srand( 20261031 );
$tokens = array( '<', '>', '"', "'", '&', '&lt;', '&#60;', '&#x3c;', '\\u003c', 'script', '</script>', '<script>', 'javascript:', 'JaVaScRiPt:', 'data:text/html,', 'onerror=', ' onload=', 'onmouseover', '=', ' ', "\t", "\n", "\0", '/', 'svg', 'img src=x', '<!--', '-->', ']]>', '`', '(', ')', 'alert', 'a', 'Z', '日本', "\u{202E}", '%3C', '\\', 'style=', 'expression(' );
$random  = function ( $max ) use ( $tokens ) {
	$out = '';
	for ( $i = 0, $n = mt_rand( 0, $max ); $i < $n; $i++ ) {
		$out .= $tokens[ mt_rand( 0, count( $tokens ) - 1 ) ];
	}
	return $out;
};

/** HTML 片を DOM として解析し、スクリプト実行につながる要素・属性を列挙する。 */
function yzrh_sec_dangerous_dom( $html ) {
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$problems = array();
	foreach ( ( new DOMXPath( $dom ) )->query( '//*' ) as $el ) {
		if ( in_array( strtolower( $el->nodeName ), array( 'script', 'iframe', 'object', 'embed', 'svg', 'math', 'style', 'base', 'form' ), true ) ) {
			$problems[] = 'element ' . $el->nodeName;
		}
		foreach ( $el->attributes as $attr ) {
			$name = strtolower( $attr->name );
			if ( 0 === strpos( $name, 'on' ) || 'style' === $name || 'srcdoc' === $name ) {
				$problems[] = "attribute {$name}";
			}
			if ( in_array( $name, array( 'href', 'src', 'action', 'formaction', 'xlink:href' ), true ) && preg_match( '/^\s*(javascript|data|vbscript):/i', html_entity_decode( $attr->value ) ) ) {
				$problems[] = "{$name}={$attr->value}";
			}
			if ( 'class' === $name && preg_match( '/[^A-Za-z0-9_\- ]/', $attr->value ) ) {
				$problems[] = "class={$attr->value}";
			}
		}
	}
	return $problems;
}

yzrh_sec_test( 'property: interview block render callbacks never emit script-capable markup (400 random attribute sets, contributor context)', function () use ( $random, $users ) {
	wp_set_current_user( $users['contrib_a'] );
	for ( $i = 0; $i < 400; $i++ ) {
		$speech = array(
			'content'         => $random( 12 ),
			'speakerInitials' => $random( 6 ),
			'speakerTag'      => $random( 6 ),
			'alignment'       => mt_rand( 0, 3 ) ? $random( 4 ) : 'right',
			'tone'            => mt_rand( 0, 3 ) ? $random( 4 ) : 'answer',
			'speakerStyle'    => mt_rand( 0, 3 ) ? $random( 6 ) : 'guest-b',
		);
		$quote  = array( 'quote' => $random( 12 ), 'attribution' => $random( 6 ) );
		foreach ( array( yzrh_render_interview_speech_block( $speech ), yzrh_render_interview_pullquote_block( $quote ) ) as $html ) {
			$problems = yzrh_sec_dangerous_dom( $html );
			yzrh_sec_assert( empty( $problems ), "case {$i}: " . implode( '; ', $problems ) . "\ninput: " . wp_json_encode( array( $speech, $quote ) ) );
		}
	}
	wp_set_current_user( 0 );
} );

yzrh_sec_test( 'property: JSON-LD strings never contain raw < > & after sanitize_text + render flags (400 cases)', function () use ( $random ) {
	for ( $i = 0; $i < 400; $i++ ) {
		$text = YZRH_Structured_Data_Generator::sanitize_text( $random( 16 ) );
		$json = wp_json_encode( array( 'name' => $text ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		yzrh_sec_assert( false === strpbrk( (string) $json, '<>&' ), "case {$i}: {$json}" );
		yzrh_sec_assert( false === strpos( $text, '<script' ), "case {$i}: tag survived: {$text}" );
	}
} );

yzrh_sec_test( 'property/boundary: ?order normalization only yields ASC or DESC for any type', function () use ( $random ) {
	$inputs = array( null, '', ' asc ', "ASC\0", 'DESC;DROP TABLE wp_posts', 'ａｓｃ', 'Asc', array( 'ASC' ), array( 'order' => 'ASC' ), 1, 1.5, true, false, new stdClass(), str_repeat( 'A', 100000 ) );
	for ( $i = 0; $i < 300; $i++ ) {
		$inputs[] = $random( 8 );
	}
	foreach ( $inputs as $input ) {
		$out = yzrh_normalize_interview_order( $input );
		yzrh_sec_assert( in_array( $out, array( 'ASC', 'DESC' ), true ), 'unexpected ' . var_export( $out, true ) );
	}
} );

yzrh_sec_test( 'precondition: subscribers cannot set the sameAs user meta keys through core profile fields', function () {
	$methods = wp_get_user_contact_methods();
	$overlap = array_intersect( array_keys( $methods ), array( 'facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'pinterest', 'tiktok' ) );
	yzrh_sec_assert( empty( $overlap ), 'core contact methods expose: ' . implode( ', ', $overlap ) );
} );

$results = $GLOBALS['yzrh_sec_results'];
echo "\n{$results['pass']} passed, {$results['fail']} failed\n";
if ( $results['fail'] > 0 ) {
	exit( 1 );
}
