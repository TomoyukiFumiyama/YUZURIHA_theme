<?php
/**
 * フロント・エディター用のアセット（CSS / JS）とインタビュー用ブロック。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * テーマ内ファイルのキャッシュ対策用バージョン文字列を返す。
 *
 * なぜ必要か: `?ver=` にファイルの更新日時を使うと、ファイルが変わったときだけ URL が変わる。
 * そのためブラウザや CDN はデプロイの間キャッシュを使い続けられ、更新後は確実に新しい
 * ファイルを取得する（毎リクエスト変わる日時を使うとキャッシュが効かなくなる）。
 *
 * ファイルがない場合は親テーマ（このテーマ）の Version を使う。wp_get_theme() を引数なしで
 * 呼ぶと子テーマの情報が返るため、get_template() で親テーマを明示する。
 *
 * @param string $relative_path テーマディレクトリからの相対パス（"/" で始める）。
 * @return string
 */
function yzrh_asset_version( $relative_path ) {
	$absolute_path = get_template_directory() . $relative_path;
	if ( file_exists( $absolute_path ) ) {
		return (string) filemtime( $absolute_path );
	}

	return (string) wp_get_theme( get_template() )->get( 'Version' );
}

/**
 * テーマ内のスタイルシートを、ファイルベースのバージョン付きで読み込む。
 *
 * なぜ必要か: URL とバージョンの組み立てを 1 か所にまとめ、各ページの読み込み処理を短くする。
 *
 * @param string $handle        スタイルのハンドル名。
 * @param string $relative_path テーマディレクトリからの相対パス。
 * @param array  $deps          依存するハンドル名。
 */
function yzrh_enqueue_theme_style( $handle, $relative_path, $deps = array() ) {
	wp_enqueue_style( $handle, get_template_directory_uri() . $relative_path, $deps, yzrh_asset_version( $relative_path ) );
}

/**
 * ファイルが存在する場合だけスタイルシートを読み込む。
 *
 * なぜ必要か: assets/css/ 以下の案件固有 CSS はひな形に同梱していないことがあり、
 * 存在しないファイルを読み込むと 404 のリクエストが発生するため。
 *
 * @param string $handle        スタイルのハンドル名。
 * @param string $relative_path テーマディレクトリからの相対パス。
 * @param array  $deps          依存するハンドル名。
 */
function yzrh_enqueue_style_if_exists( $handle, $relative_path, $deps = array() ) {
	if ( ! file_exists( get_template_directory() . $relative_path ) ) {
		return;
	}

	yzrh_enqueue_theme_style( $handle, $relative_path, $deps );
}

/**
 * フロントエンドの CSS / JS を読み込む（wp_enqueue_scripts）。
 *
 * なぜ必要か: header.php に <link> を直書きせず WordPress の仕組みで読み込むことで、
 * 重複読み込みの防止・依存関係の解決・プラグインからの差し替えができるようにする。
 * 全ページ共通の CSS と、条件分岐でページ固有の CSS を読み込む。
 */
function yzrh_enqueue_assets() {
	// リセット CSS。
	yzrh_enqueue_theme_style( 'yzrh-reset', '/css/reset.min.css' );
	// Swiper（CDN・URL 自体がバージョン固定）は登録だけ行い、使うページでのみ読み込む。
	// テーマ自身は Swiper を使っていないため既定では読み込まない。
	wp_register_style( 'yzrh-swiper', 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css', array(), '11.0.5' );
	wp_register_script(
		'yzrh-swiper',
		'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js',
		array(),
		'11.0.5',
		array(
			'in_footer' => true,
			'strategy'  => 'defer', // WordPress 6.3+。それ以前は in_footer のみ有効。
		)
	);

	/**
	 * 現在のページで Swiper を読み込むかどうかを決めるフィルター。
	 *
	 * スライダーがあるページで true を返す。例:
	 * add_filter( 'yzrh_use_swiper', 'is_front_page' );
	 * Swiper は defer で読み込まれるため、`new Swiper()` を呼ぶスクリプトは
	 * 'yzrh-swiper' を依存に指定する（または DOMContentLoaded 後に実行する）こと。
	 *
	 * @param bool $use_swiper 既定値は false。
	 */
	if ( apply_filters( 'yzrh_use_swiper', false ) ) {
		wp_enqueue_style( 'yzrh-swiper' );
		wp_enqueue_script( 'yzrh-swiper' );
	}
	// フッターで実行する共通スクリプト（SP のみ電話リンクを有効にする処理など）。
	wp_enqueue_script( 'yzrh-footer', get_template_directory_uri() . '/js/footerscript.js', array(), yzrh_asset_version( '/js/footerscript.js' ), true );

	// 共通スタイルの読み込み。
	yzrh_enqueue_theme_style( 'yzrh-style', '/css/style.css' );
	yzrh_enqueue_theme_style( 'yzrh-header', '/css/header.css' );
	yzrh_enqueue_theme_style( 'yzrh-footer', '/css/footer.css' );
	yzrh_enqueue_theme_style( 'yzrh-sns-icons', '/css/component/sns-icons.css' );

	// 条件分岐によるページ固有スタイルの読み込み（そのページでしか使わない CSS を他で読まないため）。
	if ( is_404() ) {
		yzrh_enqueue_theme_style( 'yzrh-404', '/css/page/404.css' );
	}

	// assets/css/ 以下は案件ごとに追加する CSS。ひな形にはないことがあるため存在確認してから読み込む。
	if ( is_front_page() ) {
		$master_dep = array();
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
		if ( file_exists( get_template_directory() . '/assets/css/master.css' ) ) {
			$master_dep = array( 'yzrh-master' );
		}
		yzrh_enqueue_style_if_exists( 'yzrh-front-page', '/assets/css/front-page.css', $master_dep );
	} elseif ( is_post_type_archive( 'members' ) ) {
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
		yzrh_enqueue_style_if_exists( 'yzrh-archive-members', '/assets/css/archive-members.css' );
	} elseif ( is_page( 'company' ) ) {
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
		yzrh_enqueue_style_if_exists( 'yzrh-company', '/assets/css/page-company.css' );
	} elseif ( is_page_template( 'page-templates/job-details.php' ) ) {
		yzrh_enqueue_theme_style( 'yzrh-job-details', '/css/page/job-details.css' );
	} elseif ( is_page( 'thanks' ) ) {
		yzrh_enqueue_style_if_exists( 'yzrh-master', '/assets/css/master.css' );
	} elseif ( is_singular( 'interview' ) ) {
		yzrh_enqueue_theme_style( 'yzrh-interview', '/css/page/interview.css' );
	} elseif ( is_post_type_archive( 'interview' ) ) {
		yzrh_enqueue_theme_style( 'yzrh-interview-archive', '/css/page/interview-archive.css' );
	}
}
add_action( 'wp_enqueue_scripts', 'yzrh_enqueue_assets' );

/**
 * インタビュー記事用のカスタムブロック（会話の吹き出し・プルクオート）を登録する。
 *
 * なぜ必要か: インタビュー記事の「話者アイコン＋吹き出し」「引用」を、編集者が HTML を
 * 書かずにブロックエディターから入力できるようにするため。
 * 編集画面は js/interview-blocks.js、フロントの HTML は下の render_callback（PHP）で出力する
 * 「ダイナミックブロック」にしている。こうすると保存済みの記事を直さなくても、
 * PHP 側の修正だけでマークアップを一括で変更できる。
 */
function yzrh_register_interview_blocks() {
	$script_ver = yzrh_asset_version( '/js/interview-blocks.js' );
	$style_ver  = yzrh_asset_version( '/css/page/interview.css' );

	// エディター内でもフロントと同じ見た目にするため、フロント用の interview.css をエディター用にも登録する。
	wp_register_style(
		'yzrh-interview-blocks',
		get_template_directory_uri() . '/css/page/interview.css',
		array(),
		$style_ver
	);

	wp_register_script(
		'yzrh-interview-blocks',
		get_template_directory_uri() . '/js/interview-blocks.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
		$script_ver,
		true
	);

	// 会話の吹き出しブロック。属性の既定値は JS 側の定義と揃える。
	register_block_type(
		'yzrh/interview-speech',
		array(
			'api_version'     => 3, // Block API v3（WordPress 6.3+）。js/interview-blocks.js の API_VERSION と一致させること。
			'editor_script'   => 'yzrh-interview-blocks',
			'editor_style'    => 'yzrh-interview-blocks',
			'render_callback' => 'yzrh_render_interview_speech_block',
			'attributes'      => array(
				'content'         => array( 'type' => 'string', 'default' => '' ),
				'speakerInitials' => array( 'type' => 'string', 'default' => 'S' ),
				'speakerTag'      => array( 'type' => 'string', 'default' => 'interviewer' ),
				'alignment'       => array( 'type' => 'string', 'default' => 'left' ),
				'tone'            => array( 'type' => 'string', 'default' => 'question' ),
				'speakerStyle'    => array( 'type' => 'string', 'default' => 'host' ),
			),
		)
	);

	// プルクオート（記事中で強調する引用）ブロック。
	register_block_type(
		'yzrh/interview-pullquote',
		array(
			'api_version'     => 3, // Block API v3（WordPress 6.3+）。js/interview-blocks.js の API_VERSION と一致させること。
			'editor_script'   => 'yzrh-interview-blocks',
			'editor_style'    => 'yzrh-interview-blocks',
			'render_callback' => 'yzrh_render_interview_pullquote_block',
			'attributes'      => array(
				'quote'       => array( 'type' => 'string', 'default' => '' ),
				'attribution' => array( 'type' => 'string', 'default' => '' ),
			),
		)
	);
}
add_action( 'init', 'yzrh_register_interview_blocks' );

/**
 * 会話の吹き出しブロックのフロント HTML を返す（render_callback）。
 *
 * なぜ必要か: ブロックの属性は投稿本文のコメントに保存され、権限次第で任意の値を入れられる。
 * そのため出力直前に、本文は wp_kses_post()、テキストはサニタイズ、クラス名に使う値は
 * 許可した値（またはクラス名として安全な文字）だけに絞ってから HTML を組み立てる。
 *
 * @param array $attributes ブロック属性。
 * @return string HTML。
 */
function yzrh_render_interview_speech_block( $attributes ) {
	$content          = isset( $attributes['content'] ) ? wp_kses_post( $attributes['content'] ) : '';
	$speaker_initials = isset( $attributes['speakerInitials'] ) ? sanitize_text_field( $attributes['speakerInitials'] ) : '';
	$speaker_tag      = isset( $attributes['speakerTag'] ) ? sanitize_text_field( $attributes['speakerTag'] ) : '';
	$alignment        = isset( $attributes['alignment'] ) && 'right' === $attributes['alignment'] ? 'right' : 'left';
	$tone             = isset( $attributes['tone'] ) && 'answer' === $attributes['tone'] ? 'answer' : 'question';
	$speaker_style    = isset( $attributes['speakerStyle'] ) ? sanitize_html_class( $attributes['speakerStyle'] ) : '';
	// サニタイズで空になった場合に "is-" だけのクラスにならないよう、既定値に戻す。
	if ( '' === $speaker_style ) {
		$speaker_style = 'host';
	}

	$row_classes = array( 'wp-block-yzrh-interview-speech', 'yzrh-interview-bubble-row' );
	if ( 'right' === $alignment ) {
		$row_classes[] = 'is-right';
	}

	$bubble_classes = array( 'yzrh-interview-bubble', 'is-' . $tone );
	if ( 'guest-b' === $speaker_style ) {
		$bubble_classes[] = 'is-guest-b';
	}

	$speaker_classes = array( 'yzrh-interview-speaker', 'is-' . $speaker_style );

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
		<div class="<?php echo esc_attr( implode( ' ', $speaker_classes ) ); ?>">
			<?php echo esc_html( $speaker_initials ); ?>
			<?php if ( $speaker_tag ) : ?>
				<div class="yzrh-interview-speaker__tag"><?php echo esc_html( $speaker_tag ); ?></div>
			<?php endif; ?>
		</div>
		<div class="<?php echo esc_attr( implode( ' ', $bubble_classes ) ); ?>">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 上で wp_kses_post() 済み。 ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * プルクオートブロックのフロント HTML を返す（render_callback）。
 *
 * なぜ必要か: 吹き出しブロックと同じく、保存された属性を出力直前にサニタイズする。
 * 引用文・出典が空の要素は出力しない。
 *
 * @param array $attributes ブロック属性。
 * @return string HTML。
 */
function yzrh_render_interview_pullquote_block( $attributes ) {
	$quote       = isset( $attributes['quote'] ) ? wp_kses_post( $attributes['quote'] ) : '';
	$attribution = isset( $attributes['attribution'] ) ? sanitize_text_field( $attributes['attribution'] ) : '';

	ob_start();
	?>
	<aside class="wp-block-yzrh-interview-pullquote yzrh-interview-pullquote">
		<?php if ( $quote ) : ?>
			<p class="yzrh-interview-pullquote__text"><?php echo $quote; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 上で wp_kses_post() 済み。 ?></p>
		<?php endif; ?>
		<?php if ( $attribution ) : ?>
			<div class="yzrh-interview-pullquote__attr"><?php echo esc_html( $attribution ); ?></div>
		<?php endif; ?>
	</aside>
	<?php
	return ob_get_clean();
}
