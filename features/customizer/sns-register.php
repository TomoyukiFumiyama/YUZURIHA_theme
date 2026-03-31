<?php
/**
 * SNSアイコン用のカスタマイザー設定
 */
function yzrh_customize_register_sns( $wp_customize ) {

	// セクション追加
	$wp_customize->add_section(
		'sns_icons_section',
		array(
			'title'       => 'SNSアイコン',
			'priority'    => 160,
			'description' => "SNSアイコンを表示できます。\nボタンを表示したいSNSのページURLを入力してください。\n空欄の場合、そのボタンは表示されません。",
		)
	);

	// 対応させたいSNSの一覧
	$sns_list = array(
		'line'      => array( 'label' => 'LINE URL' ),
		'instagram' => array( 'label' => 'Instagram URL' ),
		'tiktok'    => array( 'label' => 'TikTok URL' ),
		'x'         => array( 'label' => 'X URL' ),
		'facebook'  => array( 'label' => 'Facebook URL' ),
		'pinterest' => array( 'label' => 'Pinterest URL' ),
		'youtube'   => array( 'label' => 'YouTube URL' ),
		'note'      => array( 'label' => 'note URL' ),
		'contact'   => array( 'label' => 'お問い合わせページのURL（mailto:も利用可）' ),
		'rss'       => array( 'label' => 'RSSを表示する', 'checkbox' => true ),
	);

	foreach ( $sns_list as $key => $args ) {

		$setting_id = "sns_{$key}_url";

		// RSSだけチェックボックスにする
		if ( isset( $args['checkbox'] ) && $args['checkbox'] ) {

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
					'label'   => $args['label'],
					'section' => 'sns_icons_section',
					'type'    => 'checkbox',
				)
			);

		} else {

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
					'label'   => $args['label'],
					'section' => 'sns_icons_section',
					'type'    => 'url',
				)
			);
		}
	}
}
add_action( 'customize_register', 'yzrh_customize_register_sns' );

/**
 * SNSアイコンを出力する
 * 好きなテンプレートで <?php yzrh_output_sns_icons(); ?> と呼び出す
 */
function yzrh_output_sns_icons() {

	$sns_list = array(
		'line'      => array( 'label' => 'LINE',      'icon_class' => 'icon-line' ),
		'instagram' => array( 'label' => 'Instagram', 'icon_class' => 'icon-instagram' ),
		'tiktok'    => array( 'label' => 'TikTok',    'icon_class' => 'icon-tiktok' ),
		'x'         => array( 'label' => 'X',         'icon_class' => 'icon-x' ),
		'facebook'  => array( 'label' => 'Facebook',  'icon_class' => 'icon-facebook' ),
		'pinterest' => array( 'label' => 'Pinterest', 'icon_class' => 'icon-pinterest' ),
		'youtube'   => array( 'label' => 'YouTube',   'icon_class' => 'icon-youtube' ),
		'note'      => array( 'label' => 'note',      'icon_class' => 'icon-note' ),
		'contact'   => array( 'label' => 'Contact',   'icon_class' => 'icon-mail' ),
	);

	$has_output = false;

	ob_start();
	?>
	<ul class="sns-icons">
		<?php foreach ( $sns_list as $key => $args ) : ?>
			<?php
			$url = get_theme_mod( "sns_{$key}_url" );
			if ( ! $url ) {
				continue;
			}
			$has_output = true;
			?>
			<li class="sns-icons__item sns-icons__item--<?php echo esc_attr( $key ); ?>">
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
					<span class="sns-icons__icon <?php echo esc_attr( $args['icon_class'] ); ?>"></span>
					<span class="sns-icons__label"><?php echo esc_html( $args['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>

		<?php if ( get_theme_mod( 'sns_rss_enable' ) ) : ?>
			<li class="sns-icons__item sns-icons__item--rss">
				<a href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>" target="_blank" rel="noopener">
					<span class="sns-icons__icon icon-rss"></span>
					<span class="sns-icons__label">RSS</span>
				</a>
			</li>
		<?php endif; ?>
	</ul>
	<?php
	$html = ob_get_clean();

	if ( $has_output || get_theme_mod( 'sns_rss_enable' ) ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

