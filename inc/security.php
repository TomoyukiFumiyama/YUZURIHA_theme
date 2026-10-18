<?php
/**
 * テーマ全体のセキュリティ強化。
 *
 * バージョン情報の秘匿、XML-RPC・ピンバックの停止、コメント機能の停止、
 * セキュリティ関連の HTTP ヘッダー送信をまとめて行う。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * セキュリティ強化のユーティリティクラス。
 *
 * 関数ではなくクラスの静的メソッドにしているのは、フックに登録するコールバック名を
 * クラス名で名前空間化し、他のテーマ・プラグインの関数名と衝突させないため。
 */
class YZRH_Security_Hardening {
	/**
	 * 各対策をフックに登録する（ファイル末尾で 1 回だけ呼ばれる）。
	 */
	public static function init() {
		// WordPress のバージョンを外部に出さない（既知の脆弱性を狙った攻撃の手がかりを減らす）。
		// <head> の generator メタタグ（cleanup.php と同じ処理。片方を無効化しても残るよう両方に置く）。
		remove_action( 'wp_head', 'wp_generator' );
		// フィードなど <head> 以外に出力される generator も空にする。
		add_filter( 'the_generator', '__return_empty_string' );
		// CSS / JS の URL の `?ver=` に含まれる本体バージョンを隠す。
		add_filter( 'style_loader_src', array( __CLASS__, 'remove_asset_version' ), 10, 2 );
		add_filter( 'script_loader_src', array( __CLASS__, 'remove_asset_version' ), 10, 2 );

		// XML-RPC はブルートフォース攻撃や DDoS の踏み台に使われやすいため停止する。
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( __CLASS__, 'remove_x_pingback_header' ) );
		// コメント機能は使わない方針のため、ピンバック・コメントの受付と表示を止める。
		add_filter( 'pings_open', '__return_false' );
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );

		add_action( 'send_headers', array( __CLASS__, 'send_security_headers' ) );
		// 優先度 100: カスタム投稿タイプの登録（通常は init の優先度 10）が終わった後に実行する。
		add_action( 'init', array( __CLASS__, 'disable_comment_support' ), 100 );
		add_action( 'admin_init', array( __CLASS__, 'disable_comment_support' ) );
		// コメント関連の管理画面 UI を非表示にする（使わない機能を編集者に見せない）。
		add_action( 'admin_menu', array( __CLASS__, 'hide_comment_admin_menu' ), 100 );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'hide_comment_admin_bar' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'hide_comment_dashboard_widget' ) );
	}

	/**
	 * 登録済みのすべての投稿タイプから、コメントとトラックバックのサポートを外す。
	 *
	 * なぜ必要か: 投稿編集画面の「ディスカッション」欄やコメント数の表示をなくし、
	 * コメント機能が残っているように見えないようにするため。
	 */
	public static function disable_comment_support() {
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
			}
			if ( post_type_supports( $post_type, 'trackbacks' ) ) {
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/** 管理メニューから「コメント」画面を削除する。 */
	public static function hide_comment_admin_menu() {
		remove_menu_page( 'edit-comments.php' );
	}

	/** 管理バー（ツールバー）からコメントのショートカットを削除する。 */
	public static function hide_comment_admin_bar() {
		global $wp_admin_bar;
		if ( $wp_admin_bar ) {
			$wp_admin_bar->remove_menu( 'comments' );
		}
	}

	/** ダッシュボードの「最近のコメント」ウィジェットを削除する。 */
	public static function hide_comment_dashboard_widget() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * 読み込むアセットの URL に含まれる WordPress 本体のバージョンを隠す。
	 *
	 * なぜこの方式か: `ver` を削除すると、本体を更新してもブラウザが古いキャッシュを使い続ける。
	 * そこで、本体のバージョンと同じ値の `ver` だけを、サイト固有のハッシュに置き換える。
	 * 本体を更新するとハッシュも変わるので、キャッシュ対策は機能し続ける。
	 * テーマ・プラグイン独自のバージョンには触れず、URL の他の部分も組み立て直さない
	 * （Google Fonts の `family` のような重複パラメータも壊さない）。
	 *
	 * @param string $src    アセットの URL。
	 * @param string $handle アセットのハンドル名（未使用。フィルターの引数として受け取るだけ）。
	 * @return string
	 */
	public static function remove_asset_version( $src, $handle ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_string( $src ) || false === strpos( $src, 'ver=' ) ) {
			return $src;
		}

		$core_version = get_bloginfo( 'version' );
		if ( '' === $core_version ) {
			return $src;
		}

		// `?ver=6.6.2` / `&ver=6.6.2`（HTML エスケープ済みの `&#038;` `&amp;` も含む）の値部分だけに一致させる。
		$pattern = '/([?&]|&#038;|&amp;)ver=' . preg_quote( rawurlencode( $core_version ), '/' ) . '(?=&|#|$)/';

		return preg_replace( $pattern, '${1}ver=' . self::get_masked_core_version( $core_version ), $src );
	}

	/**
	 * 本体バージョンの代わりに使う、サイト固有で元に戻せない文字列を返す。
	 *
	 * なぜ必要か: 単純なハッシュ（md5( '6.6.2' ) など）では全サイト共通の値になり、
	 * 対応表を作ればバージョンが分かってしまう。wp_hash() はサイト固有のソルトを使うため推測できない。
	 * 同じリクエスト内で何度も計算しないよう、静的変数にキャッシュする。
	 *
	 * @param string $core_version 実行中の WordPress のバージョン。
	 * @return string
	 */
	protected static function get_masked_core_version( $core_version ) {
		static $masked = array();

		if ( ! isset( $masked[ $core_version ] ) ) {
			$masked[ $core_version ] = substr( wp_hash( 'yzrh-asset-' . $core_version ), 0, 10 );
		}

		return $masked[ $core_version ];
	}

	/**
	 * レスポンスから X-Pingback ヘッダーを削除する。
	 *
	 * なぜ必要か: XML-RPC を停止しているのに、ピンバック用の xmlrpc.php の場所を案内しないため。
	 *
	 * @param array $headers レスポンスヘッダー。
	 * @return array
	 */
	public static function remove_x_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
	}

	/**
	 * 推奨されるセキュリティヘッダーをレスポンスごとに送信する（send_headers）。
	 *
	 * 案件ごとの追加・変更・削除は {@see 'yzrh_security_headers'} フィルターで行える
	 * （例: Content-Security-Policy の追加。値を空にすると送信しない）。
	 */
	public static function send_security_headers() {
		// 既に出力が始まっているとヘッダーを送れず警告になるため、何もしない。
		if ( headers_sent() ) {
			return;
		}

		$headers = apply_filters( 'yzrh_security_headers', self::get_default_security_headers() );

		foreach ( self::prepare_header_lines( $headers ) as $line ) {
			header( $line, true );
		}
	}

	/**
	 * フィルター後のヘッダーを検証し、"Name: value" 形式の行に変換する。
	 *
	 * なぜ必要か: フィルターはどんな値でも返せるため、不正な項目は致命的エラーにせず読み飛ばす。
	 * 名前は HTTP の token 文字だけ、値はスカラー（文字列に変換）で CR / LF / NUL を含まないものに限る。
	 * 改行を許すとヘッダーインジェクション（任意のヘッダーの追加）につながるため。
	 * 空の値は「送信しない」の意味として捨てる。
	 *
	 * @param mixed $headers フィルター後のヘッダー（名前 => 値）。
	 * @return string[]
	 */
	public static function prepare_header_lines( $headers ) {
		if ( ! is_array( $headers ) ) {
			return array();
		}

		$lines = array();
		foreach ( $headers as $name => $value ) {
			if ( ! is_string( $name ) || ! preg_match( '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name ) ) {
				continue;
			}

			// true は "1" になってしまい意図した値ではないため、真偽値も除外する。
			if ( is_bool( $value ) || ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );
			if ( '' === $value || preg_match( '/[\r\n\0]/', $value ) ) {
				continue;
			}

			$lines[] = $name . ': ' . $value;
		}

		return $lines;
	}

	/**
	 * {@see self::send_security_headers()} が使う既定のセキュリティヘッダーを返す。
	 *
	 * - X-Frame-Options: 他サイトの iframe への埋め込みを禁止する（クリックジャッキング対策）。
	 * - X-Content-Type-Options: Content-Type の推測（MIME スニッフィング）を禁止する。
	 * - X-Permitted-Cross-Domain-Policies: Flash / PDF のクロスドメインポリシー読み込みを禁止する。
	 * - Referrer-Policy: 他サイトへのリンク時はオリジンだけを送り、URL のパスやクエリを漏らさない。
	 * - Permissions-Policy: サイトで使わないカメラ・位置情報などのブラウザ機能を無効にする。
	 *
	 * @return array<string, string>
	 */
	protected static function get_default_security_headers() {
		$headers = array();

		$hsts = self::get_hsts_header_value();
		if ( '' !== $hsts ) {
			$headers['Strict-Transport-Security'] = $hsts;
		}

		return $headers + array(
			'X-Frame-Options'                   => 'SAMEORIGIN',
			'X-Content-Type-Options'            => 'nosniff',
			'X-Permitted-Cross-Domain-Policies' => 'none',
			'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
			'Permissions-Policy'                => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
		);
	}

	/**
	 * Strict-Transport-Security（HSTS）ヘッダーの値を組み立てる。
	 *
	 * HSTS は「このサイトには今後 HTTPS でだけ接続する」とブラウザに記憶させ、
	 * HTTP への格下げ攻撃を防ぐ。HTTPS のときだけ送信する。
	 * テーマの既定値は `max-age=31536000`（1 年）で、`includeSubDomains` / `preload` は付けない。
	 * ブラウザは max-age の間ドメインごとに HSTS を記憶し続けるため、これらは
	 * 「全サブドメインが HTTPS 対応済み」「hstspreload.org への登録」を確認したうえで、
	 * サイトごとに明示的に有効にする必要がある。
	 *
	 * サイトごとに {@see 'yzrh_hsts_directives'} で有効にする:
	 *
	 *     add_filter( 'yzrh_hsts_directives', function ( $directives ) {
	 *         $directives['includeSubDomains'] = true;
	 *         $directives['preload']           = true; // includeSubDomains が必要
	 *         return $directives;
	 *     } );
	 *
	 * フィルターで false を返すとヘッダーを送信しない（Web サーバーや CDN が既に送っている場合など）。
	 * max-age に 0 を指定すると `max-age=0` を送り、以前の HSTS をブラウザに忘れさせる。
	 *
	 * TLS を終端するリバースプロキシの背後では、WordPress が転送されたプロトコルを信頼する設定に
	 * なっているときだけ is_ssl() が true になる。
	 *
	 * @return string ヘッダーの値。送信しない場合は ''。
	 */
	public static function get_hsts_header_value() {
		if ( ! is_ssl() ) {
			return '';
		}

		$defaults = array(
			'max-age'           => 31536000,
			'includeSubDomains' => false,
			'preload'           => false,
		);

		$directives = apply_filters( 'yzrh_hsts_directives', $defaults );
		if ( false === $directives ) {
			return '';
		}
		$directives = is_array( $directives ) ? array_merge( $defaults, $directives ) : $defaults;

		$max_age = absint( $directives['max-age'] );
		$value   = 'max-age=' . $max_age;

		if ( 0 === $max_age ) {
			return $value;
		}

		if ( ! empty( $directives['includeSubDomains'] ) ) {
			$value .= '; includeSubDomains';

			// preload は includeSubDomains と一緒のときだけ有効。
			if ( ! empty( $directives['preload'] ) ) {
				$value .= '; preload';
			}
		}

		return $value;
	}
}

YZRH_Security_Hardening::init();
