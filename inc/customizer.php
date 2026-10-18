<?php
/**
 * SNS アイコン用のカスタマイザー設定と出力関数。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 対応する SNS（とお問い合わせリンク）の一覧を返す。
 *
 * なぜ必要か: カスタマイザーの入力欄と、フロントのアイコン出力が同じ一覧を使うことで、
 * SNS を追加・削除するときに 1 か所の修正で済み、両者がずれないようにする。
 * キーは設定 ID（sns_{キー}_url）と CSS クラス（sns-icons__item--{キー}）に使う。
 *
 * - setting_label: カスタマイザーの入力欄に表示するラベル
 * - label:         フロントのリンクに表示するラベル（スクリーンリーダーでも読まれる）
 * - icon_class:    アイコン画像を割り当てる CSS クラス（css/component/sns-icons.css）
 *
 * @return array<string, array{setting_label:string, label:string, icon_class:string}>
 */
function yzrh_get_sns_services() {
	return array(
		'line'      => array( 'setting_label' => 'LINE URL',      'label' => 'LINE',      'icon_class' => 'icon-line' ),
		'instagram' => array( 'setting_label' => 'Instagram URL', 'label' => 'Instagram', 'icon_class' => 'icon-instagram' ),
		'tiktok'    => array( 'setting_label' => 'TikTok URL',    'label' => 'TikTok',    'icon_class' => 'icon-tiktok' ),
		'x'         => array( 'setting_label' => 'X URL',         'label' => 'X',         'icon_class' => 'icon-x' ),
		'facebook'  => array( 'setting_label' => 'Facebook URL',  'label' => 'Facebook',  'icon_class' => 'icon-facebook' ),
		'pinterest' => array( 'setting_label' => 'Pinterest URL', 'label' => 'Pinterest', 'icon_class' => 'icon-pinterest' ),
		'youtube'   => array( 'setting_label' => 'YouTube URL',   'label' => 'YouTube',   'icon_class' => 'icon-youtube' ),
		'note'      => array( 'setting_label' => 'note URL',      'label' => 'note',      'icon_class' => 'icon-note' ),
		'contact'   => array( 'setting_label' => 'お問い合わせページのURL（mailto:も利用可）', 'label' => 'Contact', 'icon_class' => 'icon-mail' ),
	);
}

/**
 * カスタマイザーに「SNSアイコン」セクションと入力欄を追加する（customize_register）。
 *
 * なぜ必要か: SNS の URL をテーマのコードではなく管理画面から設定できるようにし、
 * 案件ごとにテンプレートを書き換えなくて済むようにする。
 * URL は esc_url_raw() で保存時にサニタイズし、javascript: などの危険なスキームを除去する
 * （mailto: は WordPress の許可プロトコルに含まれるため保存できる）。
 *
 * @param WP_Customize_Manager $wp_customize カスタマイザーのマネージャー。
 */
function yzrh_customize_register_sns( $wp_customize ) {

	// セクション追加。
	$wp_customize->add_section(
		'sns_icons_section',
		array(
			'title'       => 'SNSアイコン',
			'priority'    => 160,
			'description' => 'SNSアイコンを表示できます。<br>ボタンを表示したいSNSのページURLを入力してください。<br>空欄の場合、そのボタンは表示されません。',
		)
	);

	// 各 SNS の URL 入力欄。
	foreach ( yzrh_get_sns_services() as $key => $args ) {
		$setting_id = "sns_{$key}_url";

		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		$wp_customize->add_control(
			$setting_id,
			array(
				'label'   => $args['setting_label'],
				'section' => 'sns_icons_section',
				'type'    => 'url',
			)
		);
	}

	// RSS は URL が自動で決まるため、表示する／しないのチェックボックスだけにする。
	$wp_customize->add_setting(
		'sns_rss_enable',
		array(
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	$wp_customize->add_control(
		'sns_rss_enable',
		array(
			'label'   => 'RSSを表示する',
			'section' => 'sns_icons_section',
			'type'    => 'checkbox',
		)
	);
}
add_action( 'customize_register', 'yzrh_customize_register_sns' );

/**
 * カスタマイザーで設定された SNS アイコンのリストを出力する。
 *
 * 好きなテンプレートで <?php yzrh_output_sns_icons(); ?> と呼び出す（現在は footer.php と
 * archive-interview.php で使用）。
 *
 * なぜこの形か: URL が 1 件も設定されていないとき空の <ul> を出さないよう、
 * いったんバッファに書き出してから、表示する項目があった場合だけ出力する。
 * 外部サイトは新しいタブで開き、rel="noopener noreferrer" で開いた先からの
 * window.opener 経由の操作とリファラー送信を防ぐ。
 */
function yzrh_output_sns_icons() {
	$rss_enabled = (bool) get_theme_mod( 'sns_rss_enable' );
	$has_output  = $rss_enabled;

	ob_start();
	?>
	<ul class="sns-icons">
		<?php foreach ( yzrh_get_sns_services() as $key => $args ) : ?>
			<?php
			$url = get_theme_mod( "sns_{$key}_url" );
			if ( ! $url ) {
				continue;
			}
			$has_output = true;
			// mailto: はメールソフトを起動するだけなので、空のタブが残る target="_blank" を付けない。
			$is_mailto = 0 === stripos( $url, 'mailto:' );
			?>
			<li class="sns-icons__item sns-icons__item--<?php echo esc_attr( $key ); ?>">
				<a href="<?php echo esc_url( $url ); ?>"<?php echo $is_mailto ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>>
					<span class="sns-icons__icon <?php echo esc_attr( $args['icon_class'] ); ?>" aria-hidden="true"></span>
					<span class="sns-icons__label"><?php echo esc_html( $args['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>

		<?php if ( $rss_enabled ) : ?>
			<li class="sns-icons__item sns-icons__item--rss">
				<a href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<span class="sns-icons__icon icon-rss" aria-hidden="true"></span>
					<span class="sns-icons__label">RSS</span>
				</a>
			</li>
		<?php endif; ?>
	</ul>
	<?php
	$html = ob_get_clean();

	if ( $has_output ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 各値は上で esc_url() / esc_attr() / esc_html() 済み。
	}
}
