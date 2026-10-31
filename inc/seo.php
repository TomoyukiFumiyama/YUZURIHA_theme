<?php
/**
 * SEO 関連の出力: OGP・canonical・パンくず・JSON-LD 構造化データ。
 *
 * JSON-LD の出力ロジックはこのファイルに集約し、テンプレート側には書かない（AGENTS.md 3-3）。
 * 問題が起きたときは functions.php でこのファイルの読み込みを止めれば影響を遮断できる。
 *
 * 全体の流れ:
 * 1. `wp` アクションで yzrh_register_structured_data_schemas() が、設定で有効なスキーマ生成関数を
 *    YZRH_Structured_Data_Generator に登録する。
 * 2. `wp_head`（優先度 5）で yzrh_output_jsonld() が登録済みの関数を順に呼び、
 *    null 以外を返したものだけを <script type="application/ld+json"> として出力する。
 *    各生成関数は「自分が対象とするページか」を自分で判定し、対象外なら null を返す。
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * -------------------------------------------------------------------------
 * JSON-LD 生成の中核クラス
 * -------------------------------------------------------------------------
 */

if ( ! class_exists( 'YZRH_Structured_Data_Generator' ) ) {
	/**
	 * JSON-LD 構造化データの登録・出力と、各スキーマで共通に使う部品を提供するクラス。
	 *
	 * なぜクラスか: 登録済みコールバックの一覧を 1 か所で保持し、URL・日付・著者・画像など
	 * 「同じ意味の情報」をどのスキーマでも同じメソッドから取得して表記を揃えるため（AGENTS.md 3-2）。
	 * 子テーマで丸ごと差し替えられるよう class_exists() で囲んでいる。
	 */
	class YZRH_Structured_Data_Generator {
		/**
		 * 登録済みのスキーマ生成コールバック。
		 *
		 * @var array
		 */
		protected static $schema_callbacks = array();

		/**
		 * スキーマ生成コールバックを登録する。
		 *
		 * なぜ必要か: FAQPage や Product など案件固有のスキーマを、このファイルを書き換えずに
		 * 追加できるようにするため。呼び出せない値と重複登録は無視する。
		 *
		 * @param callable $callback スキーマの配列（対象外のページでは null）を返すコールバック。
		 */
		public static function register_schema( $callback ) {
			if ( ! is_callable( $callback ) ) {
				return;
			}

			if ( ! in_array( $callback, self::$schema_callbacks, true ) ) {
				self::$schema_callbacks[] = $callback;
			}
		}

		/**
		 * 登録済みのスキーマを JSON-LD の <script> タグとして出力する。
		 *
		 * スキーマごとに別々の <script> にしているのは、1 つのスキーマに問題があっても
		 * 他のスキーマの解析に影響させないため。
		 */
		public static function render() {
			if ( is_admin() ) {
				return;
			}

			$schemas = array();
			foreach ( self::get_schema_callbacks() as $callback ) {
				$schema = call_user_func( $callback );
				if ( ! empty( $schema ) ) {
					$schemas[] = $schema;
				}
			}

			if ( empty( $schemas ) ) {
				return;
			}

			foreach ( $schemas as $schema ) {
				// JSON_HEX_TAG / JSON_HEX_AMP は "<" ">" "&" を JSON の unicode エスケープ
				// （<, >, &）で書き出す。これによりフィルターで追加された値も含め、
				// どの値も <script> 要素を閉じたり HTML エンティティとして解釈されたりしない。
				// デコード後の JSON データは変わらない。
				echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * 出力に使うスキーマ生成コールバックの一覧を返す。
		 *
		 * なぜ必要か: `yzrh_structured_data_schema_callbacks` フィルターで、出力直前に
		 * コールバックを追加・削除・並べ替えできるようにするため。
		 *
		 * @return array
		 */
		protected static function get_schema_callbacks() {
			$callbacks = self::$schema_callbacks;

			/**
			 * JSON-LD の生成に使うコールバックの一覧を変更するフィルター。
			 *
			 * @param array $callbacks 登録済みのコールバック。
			 */
			return apply_filters( 'yzrh_structured_data_schema_callbacks', $callbacks );
		}

		/**
		 * 投稿の短い説明文（プレーンテキスト）を返す。
		 *
		 * JSON-LD では、手動で入力された抜粋だけを生の値で使う。自動生成の抜粋を使うと、
		 * 一覧の各投稿について wp_head 内で `the_content` フィルターがすべて実行され、
		 * ショートコードや閲覧数カウンターなどプラグインの副作用が起きるため。
		 * 生成された抜粋を使いたい場面（個別ページの OGP）では $allow_generated = true を渡す。
		 * パスワード保護された投稿は、本文の内容が漏れないよう説明文を一切返さない。
		 *
		 * @param int  $post_id         投稿 ID。
		 * @param bool $allow_generated 手動の抜粋がないとき、WordPress が生成する抜粋を使うか。
		 * @return string プレーンテキスト。説明文がなければ ''。
		 */
		public static function get_post_description( $post_id, $allow_generated = false ) {
			if ( post_password_required( $post_id ) ) {
				return '';
			}

			if ( has_excerpt( $post_id ) ) {
				return self::sanitize_text( get_post_field( 'post_excerpt', $post_id ) );
			}

			if ( ! $allow_generated ) {
				return '';
			}

			// 生成は重いため、同じリクエスト内でリード文などと結果を共有する（inc/helpers.php）。
			return wp_trim_words( self::sanitize_text( yzrh_get_the_excerpt( $post_id ) ), 55 );
		}

		/**
		 * 現在のページの最初の投稿より前に何件あるか（ListItem の position の開始値）を返す。
		 *
		 * なぜ必要か: 2 ページ目以降でも position を通し番号（11, 12, ...）にするため。
		 * position は実際に出力した項目だけを数えて振るので、除外した投稿で番号が飛ばない。
		 * `offset` クエリ変数が明示されている場合はそれを使う（`offset` があると WordPress は
		 * `paged` を無視するため）。先頭ページにだけ付く先頭固定表示の投稿により
		 * 2 ページ目と番号が重なることはあるが、これはクエリ自体の仕様による。
		 *
		 * @param WP_Query $query クエリオブジェクト。
		 * @return int
		 */
		public static function get_list_start_offset( $query ) {
			$explicit_offset = $query->get( 'offset', '' );
			if ( '' !== $explicit_offset && null !== $explicit_offset ) {
				return max( 0, (int) $explicit_offset );
			}

			$paged    = max( 1, (int) $query->get( 'paged', 1 ) );
			$per_page = (int) $query->get( 'posts_per_page', count( $query->posts ) );

			// posts_per_page が -1（全件表示）なら 1 ページしかないので 0 から数える。
			return $per_page > 0 ? ( $paged - 1 ) * $per_page : 0;
		}

		/**
		 * クエリの並び順に対応する schema.org の ItemListOrder を返す。
		 *
		 * なぜ必要か: 並び順を固定値で書くと、インタビュー一覧の `?order=ASC`（pre_get_posts で適用）
		 * や案件ごとの並び替えと食い違うため、クエリ自体から判定する。
		 * ランダム順は「順序なし」として扱う。
		 *
		 * @param WP_Query $query クエリオブジェクト。
		 * @return string schema.org の ItemListOrderType の URL。
		 */
		public static function get_item_list_order( $query ) {
			$orderby = $query->get( 'orderby', '' );
			if ( is_string( $orderby ) && 0 === strpos( strtolower( $orderby ), 'rand' ) ) {
				return 'https://schema.org/ItemListUnordered';
			}

			$order = $query->get( 'order', '' );
			$order = is_string( $order ) ? strtoupper( trim( $order ) ) : '';

			// WP_Query は ASC が指定されない限り DESC で並べる。
			return 'ASC' === $order ? 'https://schema.org/ItemListOrderAscending' : 'https://schema.org/ItemListOrderDescending';
		}

		/**
		 * 任意のテキストを JSON-LD 用のプレーンテキストに正規化する。
		 *
		 * WordPress はサイト名・ターム名・表示名・wptexturize 済みのタイトル（`&#8217;` など）を
		 * エンティティ化した状態で保存・返却することが多い。JSON-LD にはプレーンテキストが必要なので、
		 * すべての文字列をこの 1 つの関数に通す。先に全エンティティをデコードしてからタグを除去するので、
		 * `&lt;script&gt;` のようにエンコードされたマークアップもタグとして残らない（AGENTS.md 3-4）。
		 *
		 * @param string $text 元のテキスト。
		 * @return string
		 */
		public static function sanitize_text( $text ) {
			$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = wp_strip_all_tags( $text );

			return trim( $text );
		}

		/**
		 * パンくずや articleSection に使う、投稿の代表カテゴリーを返す。
		 *
		 * 既定では最初のカテゴリー。SEO プラグインの「メインカテゴリー」などを使いたい案件は
		 * `yzrh_structured_data_primary_category` フィルターで差し替える。
		 *
		 * @param int $post_id 投稿 ID。
		 * @return WP_Term|null
		 */
		public static function get_primary_category( $post_id ) {
			$categories = get_the_category( $post_id );
			if ( empty( $categories ) ) {
				return null;
			}

			$primary = $categories[0];

			/**
			 * 構造化データのパンくずで使う代表カテゴリーを変更するフィルター。
			 *
			 * @param WP_Term $primary 代表カテゴリー。
			 * @param int     $post_id 投稿 ID。
			 */
			return apply_filters( 'yzrh_structured_data_primary_category', $primary, $post_id );
		}

		/**
		 * 著者の Person スキーマを返す（BlogPosting の author と筆者ページで共用）。
		 *
		 * なぜ共用か: 記事の著者と筆者ページの Person で、名前・URL・sameAs の表記を必ず揃えるため。
		 * メールアドレスなど不要な個人情報は含めない（AGENTS.md 3-4）。
		 *
		 * @param int|null $post_id   投稿から著者を求める場合の投稿 ID。
		 * @param int|null $author_id 投稿がない場合（筆者ページ）に指定する著者 ID。
		 * @return array 著者が特定できなければ空配列。
		 */
		public static function get_author_schema( $post_id = null, $author_id = null ) {
			if ( null === $author_id && $post_id ) {
				$author_id = get_post_field( 'post_author', $post_id );
			}

			if ( ! $author_id ) {
				return array();
			}

			$schema = array(
				'@type' => 'Person',
				'name'  => self::sanitize_text( get_the_author_meta( 'display_name', $author_id ) ),
			);

			$profile_url = get_author_posts_url( $author_id );
			if ( $profile_url ) {
				$schema['url'] = $profile_url;
			}

			$bio = get_the_author_meta( 'description', $author_id );
			if ( $bio ) {
				$schema['description'] = self::sanitize_text( $bio );
			}

			/*
			 * `image` は出力しない: get_avatar_url() は通常 Gravatar の URL を返し、その URL には
			 * 著者のメールアドレスのハッシュが含まれるため（AGENTS.md 3-4）。
			 * 著者の写真を自前で用意している案件は、下の `yzrh_structured_data_author`
			 * フィルターで追加できる。
			 */

			$same_as = self::get_author_same_as_profiles( $author_id );
			if ( ! empty( $same_as ) ) {
				$schema['sameAs'] = $same_as;
			}

			return apply_filters( 'yzrh_structured_data_author', $schema, $post_id, $author_id );
		}

		/**
		 * 記事やサービスの発行者（publisher / provider）として使う Organization を返す。
		 *
		 * なぜ必要か: BlogPosting・Blog・Service の発行者表記をすべて同じ内容に揃えるため。
		 *
		 * @return array
		 */
		public static function get_publisher_schema() {
			$schema = array(
				'@type' => 'Organization',
				'name'  => self::sanitize_text( get_bloginfo( 'name' ) ),
				'url'   => home_url( '/' ),
			);

			$logo = self::get_logo_object();
			if ( $logo ) {
				$schema['logo'] = $logo;
			}

			return apply_filters( 'yzrh_structured_data_publisher', $schema );
		}

		/**
		 * サイトのロゴを表す ImageObject を返す。
		 *
		 * カスタムロゴ（setup.php の custom-logo）を優先し、なければサイトアイコンを使う。
		 * どちらもなければ null を返し、呼び出し側で logo を省略させる（存在しない情報は埋めない）。
		 *
		 * @return array|null
		 */
		public static function get_logo_object() {
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				$logo = self::get_attachment_image_object( $logo_id );
				if ( $logo ) {
					return $logo;
				}
			}

			$site_icon = get_site_icon_url();
			if ( $site_icon ) {
				return array(
					'@type' => 'ImageObject',
					'url'   => $site_icon,
				);
			}

			return null;
		}

		/**
		 * 投稿のアイキャッチ画像を表す ImageObject を返す。
		 *
		 * @param int $post_id 投稿 ID。
		 * @return array|null アイキャッチがなければ null（image を省略させる）。
		 */
		public static function get_post_image_object( $post_id ) {
			// パスワード保護中の投稿は、本文と同じくアイキャッチも閲覧者に渡さない。
			if ( post_password_required( $post_id ) ) {
				return null;
			}

			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( ! $thumbnail_id ) {
				return null;
			}

			return self::get_attachment_image_object( $thumbnail_id );
		}

		/**
		 * 添付ファイル（メディア）の原寸画像を表す ImageObject を返す。
		 *
		 * なぜ必要か: ロゴとアイキャッチで同じ組み立て方をするための共通処理。
		 * 幅・高さが取得できない画像（メタデータのない SVG など）では、`null` や 0 を出力せずに
		 * width / height を省略する。
		 *
		 * @param int $attachment_id 添付ファイル ID。
		 * @return array|null
		 */
		public static function get_attachment_image_object( $attachment_id ) {
			$image = wp_get_attachment_image_src( $attachment_id, 'full' );
			if ( ! $image || empty( $image[0] ) ) {
				return null;
			}

			$object = array(
				'@type' => 'ImageObject',
				'url'   => $image[0],
			);

			if ( ! empty( $image[1] ) && ! empty( $image[2] ) ) {
				$object['width']  = (int) $image[1];
				$object['height'] = (int) $image[2];
			}

			return $object;
		}

		/**
		 * 現在のリクエストがブログの一覧・アーカイブを表示しているかを判定する。
		 *
		 * Blog / ItemList スキーマの出力対象を 1 か所で決めるための判定。
		 * 投稿者アーカイブは筆者ページ（Person）として別に扱うので除外する。
		 *
		 * @return bool
		 */
		public static function is_blog_listing_context() {
			if ( is_admin() || is_author() ) {
				return false;
			}

			if ( is_home() || is_post_type_archive( 'post' ) ) {
				return true;
			}

			if ( is_category() || is_tag() || is_date() ) {
				return true;
			}

			return false;
		}

		/**
		 * 現在のページがサービス紹介ページかどうかを判定する（Service スキーマの出力対象）。
		 *
		 * スラッグが `services` の固定ページ、またはテンプレートのファイル名に `service` を含む
		 * 固定ページをサービスページとみなす。案件ごとの判定は
		 * `yzrh_structured_data_is_service_page` フィルターで変更できる。
		 *
		 * @return bool
		 */
		public static function is_service_page() {
			if ( ! is_page() ) {
				return false;
			}

			$post_id = get_queried_object_id();
			if ( ! $post_id ) {
				return false;
			}

			$is_service = false;

			$slug = get_post_field( 'post_name', $post_id );
			if ( 'services' === $slug ) {
				$is_service = true;
			}

			$template = get_page_template_slug( $post_id );
			if ( ! $is_service && $template && false !== strpos( $template, 'service' ) ) {
				$is_service = true;
			}

			/**
			 * 現在のページをサービスページとして扱うかを変更するフィルター。
			 *
			 * @param bool $is_service 判定結果。
			 * @param int  $post_id    固定ページの ID。
			 */
			return (bool) apply_filters( 'yzrh_structured_data_is_service_page', $is_service, $post_id );
		}

		/**
		 * 著者の Web サイトと SNS プロフィールの URL を集め、sameAs 用の配列にする。
		 *
		 * sameAs は「この人物と同一人物のページ」を検索エンジンに伝えるプロパティ。
		 * プロフィールの「サイト」欄と、SNS 系プラグインがよく使うユーザーメタのキーから集める。
		 * 文字列以外のメタ値（配列など）は esc_url_raw() で致命的エラーになるため読み飛ばす。
		 *
		 * @param int $author_id 著者 ID。
		 * @return array
		 */
		protected static function get_author_same_as_profiles( $author_id ) {
			$profiles = array();

			$website = get_the_author_meta( 'user_url', $author_id );
			if ( is_string( $website ) && '' !== $website ) {
				$profiles[] = esc_url_raw( $website );
			}

			$social_keys = array( 'facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'pinterest', 'tiktok' );
			foreach ( $social_keys as $key ) {
				$link = get_user_meta( $author_id, $key, true );
				if ( is_string( $link ) && '' !== $link ) {
					$profiles[] = esc_url_raw( $link );
				}
			}

			// esc_url_raw() で空になった値と重複を除き、JSON の配列になるよう添字を振り直す。
			$profiles = array_filter( $profiles );
			$profiles = array_unique( $profiles );

			return array_values( $profiles );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * クラス外の共通ヘルパー
 *
 * YZRH_Structured_Data_Generator は子テーマで丸ごと差し替えられる（class_exists() の拡張ポイント）。
 * 差し替えたクラスが以前の公開 API で書かれていても致命的エラーにならないよう、
 * クラスの外（スキーマ生成関数など）から呼ぶ新しい共通処理はクラスに追加せず、ここに関数として置く。
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'yzrh_get_blog_url' ) ) {
	/**
	 * ブログ（投稿一覧）の URL を返す。
	 *
	 * なぜ必要か: Blog スキーマの url、BlogPosting の isPartOf（所属する Blog）の url、
	 * 投稿一覧のパンくずの URL を必ず一致させるため（AGENTS.md 3-2）。
	 * 「投稿ページ」が設定されていればそのページ、なければトップページが投稿一覧になる。
	 *
	 * @return string
	 */
	function yzrh_get_blog_url() {
		$posts_page_id = (int) get_option( 'page_for_posts' );
		if ( $posts_page_id ) {
			$url = get_permalink( $posts_page_id );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}
}

/*
 * -------------------------------------------------------------------------
 * スキーマ生成関数
 *
 * 各関数は「対象のページならスキーマの配列、対象外なら null」を返す。
 * 子テーマで差し替えられるよう function_exists() で囲み、出力直前に
 * それぞれのフィルターで案件ごとの値を追加・変更できるようにしている。
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'yzrh_structured_data_schema_website' ) ) {
	/**
	 * サイト全体を表す WebSite スキーマを返す（全ページで出力）。
	 *
	 * 検索エンジンにサイト名と URL を伝える。SearchAction はサイト内検索の URL 形式を示す。
	 * （Google はサイトリンク検索ボックスの表示を終了しているが、schema.org として有効な記述なので残している。
	 * 不要なら `yzrh_structured_data_website` フィルターで potentialAction を削除できる。）
	 *
	 * @return array|null サイト名が空なら null。
	 */
	function yzrh_structured_data_schema_website() {
		$site_name = YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) );
		$site_url  = home_url( '/' );

		if ( empty( $site_name ) ) {
			return null;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => $site_name,
			'url'      => $site_url,
		);

		$search_url                = add_query_arg( 's', '{search_term_string}', $site_url );
		$schema['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => $search_url,
			'query-input' => 'required name=search_term_string',
		);

		return apply_filters( 'yzrh_structured_data_website', $schema );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_organization' ) ) {
	/**
	 * サイトの運営組織を表す Organization スキーマを返す（全ページで出力）。
	 *
	 * 店舗などの案件では `yzrh_structured_data_organization` フィルターで
	 * `@type` を LocalBusiness に変え、住所などを追加する。
	 *
	 * @return array|null サイト名が空なら null。
	 */
	function yzrh_structured_data_schema_organization() {
		$site_name = YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) );
		$site_url  = home_url( '/' );

		if ( empty( $site_name ) ) {
			return null;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => $site_name,
			'url'      => $site_url,
		);

		$logo = YZRH_Structured_Data_Generator::get_logo_object();
		if ( $logo ) {
			$schema['logo'] = $logo;
		}

		return apply_filters( 'yzrh_structured_data_organization', $schema );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_article' ) ) {
	/**
	 * 投稿詳細ページの BlogPosting スキーマを返す（AGENTS.md 4-1）。
	 *
	 * 対象は通常の投稿（post）の詳細ページだけ。カスタム投稿タイプは対象外。
	 * 説明文・カテゴリー・画像・著者は、値がある場合だけ出力する。
	 *
	 * @return array|null
	 */
	function yzrh_structured_data_schema_article() {
		if ( ! is_singular( 'post' ) ) {
			return null;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return null;
		}

		$permalink = get_permalink( $post_id );
		$schema    = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $permalink,
			),
			'headline'         => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post_id ) ),
			'datePublished'    => get_the_date( DATE_W3C, $post_id ),
			'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
			'publisher'        => YZRH_Structured_Data_Generator::get_publisher_schema(),
			'url'              => $permalink,
			// Blog スキーマ（yzrh_structured_data_schema_blog()）と同じ名前・URL にする。
			'isPartOf'         => array(
				'@type' => 'Blog',
				'name'  => YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) ),
				'url'   => yzrh_get_blog_url(),
			),
		);

		// 著者が取得できない場合に "author": [] を出さないよう、値があるときだけ追加する。
		$author = YZRH_Structured_Data_Generator::get_author_schema( $post_id );
		if ( ! empty( $author ) ) {
			$schema['author'] = $author;
		}

		$description = YZRH_Structured_Data_Generator::get_post_description( $post_id );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		$primary_category = YZRH_Structured_Data_Generator::get_primary_category( $post_id );
		if ( $primary_category ) {
			$schema['articleSection'] = YZRH_Structured_Data_Generator::sanitize_text( $primary_category->name );
		}

		$image = YZRH_Structured_Data_Generator::get_post_image_object( $post_id );
		if ( $image ) {
			$schema['image'] = $image;
		}

		return apply_filters( 'yzrh_structured_data_article', $schema, $post_id );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_service' ) ) {
	/**
	 * サービス紹介ページの Service スキーマを返す。
	 *
	 * 対象ページの判定は YZRH_Structured_Data_Generator::is_service_page() を参照。
	 * 価格や対応地域など、テーマが持たない情報は推測で埋めず、案件側で
	 * `yzrh_structured_data_service` フィルターから追加する。
	 *
	 * @return array|null
	 */
	function yzrh_structured_data_schema_service() {
		if ( ! YZRH_Structured_Data_Generator::is_service_page() ) {
			return null;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return null;
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'name'        => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post_id ) ),
			'serviceType' => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post_id ) ),
			'provider'    => YZRH_Structured_Data_Generator::get_publisher_schema(),
		);

		$description = YZRH_Structured_Data_Generator::get_post_description( $post_id );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		$image = YZRH_Structured_Data_Generator::get_post_image_object( $post_id );
		if ( $image ) {
			$schema['image'] = $image;
		}

		return apply_filters( 'yzrh_structured_data_service', $schema, $post_id );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_blog' ) ) {
	/**
	 * ブログ一覧・アーカイブで、ブログそのものを表す Blog スキーマを返す（AGENTS.md 4-2）。
	 *
	 * 一覧の中身（記事の並び）は ItemList が表し、こちらは「どのブログか」を表す。
	 *
	 * @return array|null
	 */
	function yzrh_structured_data_schema_blog() {
		if ( ! YZRH_Structured_Data_Generator::is_blog_listing_context() ) {
			return null;
		}

		$site_name = get_bloginfo( 'name' );
		if ( empty( $site_name ) ) {
			return null;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'Blog',
			'name'       => YZRH_Structured_Data_Generator::sanitize_text( $site_name ),
			'url'        => yzrh_get_blog_url(),
			'publisher'  => YZRH_Structured_Data_Generator::get_publisher_schema(),
			'inLanguage' => get_bloginfo( 'language' ),
		);

		$description = get_bloginfo( 'description' );
		if ( $description ) {
			$schema['description'] = YZRH_Structured_Data_Generator::sanitize_text( $description );
		}

		return apply_filters( 'yzrh_structured_data_blog', $schema );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_blog_itemlist' ) ) {
	/**
	 * ブログ一覧・アーカイブに表示中の記事を ItemList スキーマで返す（AGENTS.md 4-2）。
	 *
	 * メインクエリ（$wp_query）の投稿をそのまま使うので、画面に表示されている記事と
	 * 構造化データの内容が一致する。各記事の URL・タイトルは BlogPosting と同じ関数から取得する。
	 *
	 * @return array|null 対象外のページ、または記事がなければ null。
	 */
	function yzrh_structured_data_schema_blog_itemlist() {
		if ( ! YZRH_Structured_Data_Generator::is_blog_listing_context() ) {
			return null;
		}

		global $wp_query;
		if ( ! $wp_query instanceof WP_Query ) {
			return null;
		}

		if ( empty( $wp_query->posts ) ) {
			return null;
		}

		$items    = array();
		$posts    = $wp_query->posts;
		$paged    = max( 1, (int) $wp_query->get( 'paged', 1 ) );
		$position = YZRH_Structured_Data_Generator::get_list_start_offset( $wp_query );

		foreach ( $posts as $post ) {
			// プラグインなどで他の投稿タイプが混ざっても、ブログ記事だけを列挙する。
			if ( 'post' !== get_post_type( $post ) ) {
				continue;
			}

			$position++;
			$item = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'url'      => get_permalink( $post ),
				'name'     => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post ) ),
			);

			$description = YZRH_Structured_Data_Generator::get_post_description( $post->ID );
			if ( '' !== $description ) {
				$item['description'] = $description;
			}

			$items[] = $item;
		}

		if ( empty( $items ) ) {
			return null;
		}

		$archive_title = '';
		if ( is_home() ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );
			$archive_title = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'yzrh' );
		} else {
			$archive_title = get_the_archive_title();
		}

		$archive_description = is_home() ? get_bloginfo( 'description' ) : get_the_archive_description();

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => YZRH_Structured_Data_Generator::sanitize_text( $archive_title ),
			// JSON 用なので HTML エスケープしない URL（$escape = false）を使う。
			'url'             => get_pagenum_link( $paged, false ),
			'itemListOrder'   => YZRH_Structured_Data_Generator::get_item_list_order( $wp_query ),
			'numberOfItems'   => count( $items ),
			'itemListElement' => array_values( $items ),
		);

		if ( $archive_description ) {
			$schema['description'] = YZRH_Structured_Data_Generator::sanitize_text( $archive_description );
		}

		return apply_filters( 'yzrh_structured_data_blog_itemlist', $schema, $wp_query );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_author_person' ) ) {
	/**
	 * 筆者ページ（投稿者アーカイブ）の Person スキーマを返す（AGENTS.md 4-3）。
	 *
	 * ProfilePage + mainEntity ではなく、Person を単独で出力する方式を採用している。
	 * 中身は BlogPosting の author と同じ get_author_schema() から作るので、表記が一致する。
	 *
	 * @return array|null
	 */
	function yzrh_structured_data_schema_author_person() {
		if ( ! is_author() ) {
			return null;
		}

		$author = get_queried_object();
		if ( ! $author instanceof WP_User ) {
			return null;
		}

		$author_id     = (int) $author->ID;
		$person_schema = YZRH_Structured_Data_Generator::get_author_schema( null, $author_id );
		if ( empty( $person_schema ) || empty( $person_schema['name'] ) ) {
			return null;
		}

		// 単独の JSON-LD として出力するので、先頭に @context を付ける。
		$schema = array_merge(
			array(
				'@context' => 'https://schema.org',
			),
			$person_schema
		);

		return apply_filters( 'yzrh_structured_data_author_person', $schema, $author_id );
	}
}

if ( ! function_exists( 'yzrh_structured_data_schema_interview_collection' ) ) {
	/**
	 * インタビュー一覧（カスタム投稿タイプ interview のアーカイブ）の CollectionPage スキーマを返す。
	 *
	 * ページ自体を CollectionPage、表示中のインタビューを mainEntity の ItemList で表す。
	 * 名前・URL・説明文は、画面の見出し（archive-interview.php の post_type_archive_title()）、
	 * canonical、メタディスクリプションと同じ取得元を使い、表記を揃える。
	 *
	 * @return array|null
	 */
	function yzrh_structured_data_schema_interview_collection() {
		if ( ! is_post_type_archive( 'interview' ) ) {
			return null;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'CollectionPage',
			// canonical / og:url と同じ URL（並び替えパラメータなし・ページ番号付き）。
			'url'        => yzrh_get_current_list_url(),
			'inLanguage' => get_bloginfo( 'language' ),
			'isPartOf'   => array(
				'@type' => 'WebSite',
				'name'  => YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) ),
				'url'   => home_url( '/' ),
			),
		);

		// 画面の見出しと同じ、投稿タイプのアーカイブ名。空なら省略する。
		$name = YZRH_Structured_Data_Generator::sanitize_text( post_type_archive_title( '', false ) );
		if ( '' !== $name ) {
			$schema['name'] = $name;
		}

		// メタディスクリプション・リード文と同じ取得元。空なら省略する。
		$description = YZRH_Structured_Data_Generator::sanitize_text( yzrh_interview_archive_description() );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		global $wp_query;
		if ( $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
			$items    = array();
			$position = YZRH_Structured_Data_Generator::get_list_start_offset( $wp_query );

			foreach ( $wp_query->posts as $post ) {
				if ( 'interview' !== get_post_type( $post ) ) {
					continue;
				}

				$position++;
				$item = array(
					'@type'    => 'ListItem',
					'position' => $position,
					'url'      => get_permalink( $post ),
					'name'     => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post ) ),
				);

				$description = YZRH_Structured_Data_Generator::get_post_description( $post->ID );
				if ( '' !== $description ) {
					$item['description'] = $description;
				}

				$items[] = $item;
			}

			if ( ! empty( $items ) ) {
				$schema['mainEntity'] = array(
					'@type'           => 'ItemList',
					'itemListOrder'   => YZRH_Structured_Data_Generator::get_item_list_order( $wp_query ),
					'numberOfItems'   => count( $items ),
					'itemListElement' => array_values( $items ),
				);
			}
		}

		return apply_filters( 'yzrh_structured_data_interview_collection', $schema );
	}
}

/*
 * -------------------------------------------------------------------------
 * AGENTS.md「6. 成果物」で定めた関数名の互換レイヤー
 *
 * 実体は上の yzrh_structured_data_schema_* 関数。AGENTS.md / PLANS.md で決めた名前でも
 * 呼び出せるようにし、スキーマ登録（yzrh_register_structured_data_schemas()）もこの名前で行う。
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'yzrh_jsonld_blogposting' ) ) {
	/**
	 * 投稿詳細ページの BlogPosting スキーマを返す。
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_blogposting() {
		return yzrh_structured_data_schema_article();
	}
}

if ( ! function_exists( 'yzrh_jsonld_blog_itemlist' ) ) {
	/**
	 * ブログ一覧・アーカイブの ItemList スキーマを返す。
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_blog_itemlist() {
		return yzrh_structured_data_schema_blog_itemlist();
	}
}

if ( ! function_exists( 'yzrh_jsonld_author_person' ) ) {
	/**
	 * 筆者ページの Person スキーマを返す。
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_author_person() {
		return yzrh_structured_data_schema_author_person();
	}
}

if ( ! function_exists( 'yzrh_output_jsonld' ) ) {
	/**
	 * 登録済みのすべての JSON-LD を出力する（wp_head にフックされる唯一の入口）。
	 *
	 * 出力条件の分岐はすべて各スキーマ生成関数側にあるため、ここでは出力を委ねるだけ。
	 *
	 * @return void
	 */
	function yzrh_output_jsonld() {
		YZRH_Structured_Data_Generator::render();
	}
}

/*
 * -------------------------------------------------------------------------
 * パンくずリスト（HTML と BreadcrumbList JSON-LD で同じデータを使う）
 * -------------------------------------------------------------------------
 */

/**
 * パンくずの項目一覧を作る（HTML と JSON-LD 共通の唯一のデータ源）。
 *
 * なぜ共通化するか: 画面のパンくずと BreadcrumbList の順序・名称・URL がずれると、
 * 検索エンジンに誤った階層を伝えてしまうため。
 * 対応: トップ・固定ページ（親階層付き）・投稿（投稿ページ／代表カテゴリー）・カスタム投稿・
 * カテゴリー／タグ／カスタムタクソノミー（親階層付き）・カスタム投稿アーカイブ・検索・404・投稿一覧。
 * 案件ごとの変更は `yzrh_breadcrumb_items` フィルターで行う。
 *
 * @return array<int,array{name:string,url:string}>
 */
function yzrh_get_breadcrumb_items() {
	$items = array( array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );

	if ( is_front_page() ) {
		return apply_filters( 'yzrh_breadcrumb_items', $items );
	}

	if ( is_singular() ) {
		$post_id   = get_queried_object_id();
		$post_type = get_post_type( $post_id );
		if ( 'post' === $post_type ) {
			// 投稿: 「投稿ページ」（設定されていれば）→ 代表カテゴリー → 記事。
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page ) {
				$items[] = array( 'name' => get_the_title( $posts_page ), 'url' => get_permalink( $posts_page ) );
			}
			$category = YZRH_Structured_Data_Generator::get_primary_category( $post_id );
			if ( $category ) {
				$link = get_term_link( $category );
				if ( ! is_wp_error( $link ) ) {
					$items[] = array( 'name' => $category->name, 'url' => $link );
				}
			}
		} elseif ( 'page' !== $post_type ) {
			// カスタム投稿: アーカイブがある投稿タイプなら、そのアーカイブを間に挟む。
			$object = get_post_type_object( $post_type );
			$link   = get_post_type_archive_link( $post_type );
			if ( $object && $link ) {
				$items[] = array( 'name' => $object->labels->name, 'url' => $link );
			}
		}
		// 階層のある投稿タイプ（固定ページなど）は、最上位の親から順に挟む。
		// 非公開・下書き・ゴミ箱の親は、タイトルと URL を公開しないよう読み飛ばす。
		foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $ancestor_id ) {
			if ( ! is_post_publicly_viewable( $ancestor_id ) ) {
				continue;
			}
			$items[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => get_permalink( $ancestor_id ) );
		}
		$items[] = array( 'name' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ) );
	} elseif ( is_category() || is_tax() || is_tag() ) {
		// ターム: 最上位の親タームから順に並べ、最後に現在のターム。
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				$link     = get_term_link( $ancestor );
				if ( ! is_wp_error( $link ) ) {
					$items[] = array( 'name' => $ancestor->name, 'url' => $link );
				}
			}
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array( 'name' => $term->name, 'url' => $link );
			}
		}
	} elseif ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		$object    = get_post_type_object( $post_type );
		$link      = get_post_type_archive_link( $post_type );
		// アーカイブ URL が取得できない場合に、URL が空の項目を作らない。
		if ( $object && $link ) {
			$items[] = array( 'name' => $object->labels->name, 'url' => $link );
		}
	} elseif ( is_search() ) {
		// get_search_query() は既定で HTML エスケープ済みの値を返すため、生の値（false）を取得し、
		// 出力側（esc_html() / sanitize_text()）でそれぞれエスケープする。
		$items[] = array( 'name' => sprintf( __( 'Search results for "%s"', 'yzrh' ), get_search_query( false ) ), 'url' => get_search_link() );
	} elseif ( is_404() ) {
		$items[] = array( 'name' => __( 'Page not found', 'yzrh' ), 'url' => home_url( add_query_arg( array(), $GLOBALS['wp']->request ) ) );
	} elseif ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		$items[]    = array( 'name' => $posts_page ? get_the_title( $posts_page ) : __( 'Blog', 'yzrh' ), 'url' => yzrh_get_blog_url() );
	}

	return apply_filters( 'yzrh_breadcrumb_items', $items );
}

/**
 * アクセシブルな HTML のパンくずリストを出力する。
 *
 * テンプレートで <?php yzrh_breadcrumb(); ?> と呼び出す。トップページだけ（1 項目）のときは出力しない。
 * nav に aria-label を付け、現在のページはリンクにせず aria-current="page" を付ける。
 */
function yzrh_breadcrumb() {
	$items = yzrh_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return;
	}
	echo '<nav class="yzrh-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'yzrh' ) . '"><ol>';
	foreach ( $items as $index => $item ) {
		$is_last = count( $items ) - 1 === $index;
		echo '<li>';
		if ( $is_last ) {
			echo '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>';
		} else {
			echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
		}
		echo '</li>';
	}
	echo '</ol></nav>';
}

/**
 * HTML パンくずと同じデータから BreadcrumbList スキーマを返す。
 *
 * HTML のパンくず（yzrh_breadcrumb()）と同じく、トップページだけ（1 項目）のときは出力しない。
 * 階層のないパンくずは検索結果での意味がなく、画面表示とも食い違うため。
 *
 * @return array|null
 */
function yzrh_structured_data_schema_breadcrumbs() {
	$items = yzrh_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return null;
	}

	$list = array();
	foreach ( array_values( $items ) as $index => $item ) {
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'name'     => YZRH_Structured_Data_Generator::sanitize_text( $item['name'] ),
			'item'     => esc_url_raw( $item['url'] ),
		);
	}
	return array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list );
}

/*
 * -------------------------------------------------------------------------
 * OGP と canonical
 * -------------------------------------------------------------------------
 */

/**
 * canonical を自前で出力する主要な SEO プラグインが有効かどうかを返す。
 *
 * なぜ必要か: テーマとプラグインが同じタグを二重に出力すると、検索エンジンが
 * どちらを使うか不定になるため。対象: Yoast SEO・Rank Math・All in One SEO・SEOPress。
 * OGP の判定には使わない（OGP はプラグインの設定で無効にできるため、実際の出力で判定する。
 * yzrh_output_ogp() 参照）。
 *
 * @return bool
 */
function yzrh_is_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * 出力前の OGP の値（プロパティ => 値）を返す。
 *
 * なぜ値の取得と出力を分けるか: `yzrh_ogp_tags` フィルターで案件ごとに値を追加・変更でき、
 * テストからも値を確認できるようにするため。空の値は array_filter() で除外する。
 *
 * @return array<string, string>
 */
function yzrh_get_ogp_tags() {
	$post_id = get_queried_object_id();
	$tags    = array(
		'og:site_name' => get_bloginfo( 'name' ),
		'og:title'     => wp_get_document_title(),
		'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
		'og:url'       => is_singular() ? get_permalink( $post_id ) : yzrh_get_current_list_url(),
		// OGP の og:locale は "ja_JP" のようなアンダースコア区切り（WordPress のロケールと同じ形式）。
		'og:locale'    => get_locale(),
	);
	if ( is_singular() ) {
		$tags['og:description'] = YZRH_Structured_Data_Generator::get_post_description( $post_id, true );
		// パスワード保護中の投稿は、本文と同じくアイキャッチも閲覧者に渡さない。
		$image                  = post_password_required( $post_id ) ? '' : get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			$tags['og:image'] = $image;
		}
	}
	return apply_filters( 'yzrh_ogp_tags', array_filter( $tags ) );
}

/**
 * wp_head の出力をバッファに取り始める（wp_head 優先度 -9999）。
 *
 * なぜ必要か: SEO プラグイン（Yoast SEO・Rank Math・AIOSEO・SEOPress・Jetpack など）が
 * OGP を出力するかどうかは、プラグインが有効かどうかではなく各プラグインの設定やコードで決まる
 * （有効でも OGP 機能を無効にできる）。そこで「プラグインがあるか」ではなく、
 * wp_head に実際に OGP の <meta> が出力されたかを yzrh_output_ogp() で調べられるようにする。
 * バッファの階層を記録しておき、途中で他のコードがバッファを開閉した場合は判定しない。
 */
function yzrh_start_ogp_detection() {
	ob_start();
	$GLOBALS['yzrh_ogp_buffer_level'] = ob_get_level();
}
add_action( 'wp_head', 'yzrh_start_ogp_detection', -9999 );

/**
 * HTML に OGP の <meta property="og:..."> が含まれているかどうかを返す。
 *
 * @param string $html 調べる HTML。
 * @return bool
 */
function yzrh_has_ogp_markup( $html ) {
	return 1 === preg_match( '/<meta\s[^>]*property\s*=\s*["\']?og:/i', (string) $html );
}

/**
 * OGP の <meta> タグを出力する（wp_head 優先度 9999）。
 *
 * wp_head の他の出力がすべて終わった後に実行し、yzrh_start_ogp_detection() で取っておいた
 * 出力をそのまま書き出してから、その中に OGP がまだなければテーマの OGP を出力する。
 * プラグインが OGP を出していればテーマは出さないので二重にならず、プラグインの OGP 機能が
 * 無効なら（プラグインが有効でも）テーマの OGP が出るので、SNS のプレビューが消えない。
 *
 * `yzrh_output_ogp` フィルターで出力の有無を変更できる（既定値は「他の OGP が見つからなかったか」）。
 */
function yzrh_output_ogp() {
	$head  = '';
	$level = isset( $GLOBALS['yzrh_ogp_buffer_level'] ) ? (int) $GLOBALS['yzrh_ogp_buffer_level'] : 0;
	unset( $GLOBALS['yzrh_ogp_buffer_level'] );

	// 自分が開いたバッファが一番内側に残っている場合だけ取り出す。階層がずれていたら触らない
	// （残ったバッファは WordPress が shutdown 時にまとめて出力する）。
	if ( $level > 0 && ob_get_level() === $level ) {
		$head = (string) ob_get_clean();
		echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 他のフックが出力済みの HTML をそのまま戻すだけ。
	}

	if ( ! apply_filters( 'yzrh_output_ogp', ! yzrh_has_ogp_markup( $head ) ) ) {
		return;
	}

	foreach ( yzrh_get_ogp_tags() as $property => $content ) {
		printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $content ) );
	}
}
add_action( 'wp_head', 'yzrh_output_ogp', 9999 );

/**
 * インタビュー一覧の canonical URL を返す。
 *
 * 一覧は `?order=ASC|DESC` を受け付け、同じ投稿を別の順序で表示する。canonical は常に、
 * 現在のページ番号付きのパラメータなしのアーカイブ URL を指し、並び替え違いや
 * 計測用パラメータ付きの URL が別ページとしてインデックスされないようにする。
 * テーマが生成する他の一覧 URL と同じく末尾スラッシュなし（yzrh_get_current_list_url() 参照）。
 *
 * @return string canonical URL。出力しない場合は ''。
 */
function yzrh_get_interview_archive_canonical() {
	if ( ! is_post_type_archive( 'interview' ) ) {
		return '';
	}

	$url = get_post_type_archive_link( 'interview' );
	if ( ! $url ) {
		return '';
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged > 1 ) {
		global $wp_rewrite;

		// get_pagenum_link() と同じ組み立て方: パーマリンク設定が有効なら設定済みのページ送りベース
		// （`$wp_rewrite->pagination_base`。"page" や翻訳されたスラッグ）、無効なら `?paged=`。
		if ( $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			$url = trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
		} else {
			$url = add_query_arg( 'paged', $paged, untrailingslashit( $url ) );
		}
	}

	return (string) apply_filters( 'yzrh_interview_archive_canonical', untrailingslashit( $url ) );
}

/**
 * 現在の一覧ページ（個別ページ以外）の URL を返す（og:url・CollectionPage の url）。
 *
 * インタビュー一覧は canonical と常に一致させるため、canonical をそのまま使う。
 * その他の一覧はクエリ文字列を除いたリクエストパスを使う。テーマは一覧の URL を
 * 末尾スラッシュなしで書き出すので、WordPress にリダイレクトされないよう、パーマリンク設定も
 * 末尾スラッシュなし（例: `/%postname%`）にすること。
 *
 * @return string
 */
function yzrh_get_current_list_url() {
	$canonical = yzrh_get_interview_archive_canonical();
	if ( '' !== $canonical ) {
		return $canonical;
	}

	$request = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) ? (string) $GLOBALS['wp']->request : '';

	return untrailingslashit( home_url( $request ) );
}

/**
 * インタビュー一覧の canonical リンクを出力する（wp_head 優先度 4）。
 *
 * WordPress 本体は個別ページにしか canonical を出力しないため、このアーカイブだけテーマが補う。
 * 主要な SEO プラグインが canonical を管理している場合は出力しない。
 * `yzrh_output_archive_canonical` フィルターで出力の有無を変更できる。
 */
function yzrh_output_interview_archive_canonical() {
	if ( apply_filters( 'yzrh_output_archive_canonical', ! yzrh_is_seo_plugin_active() ) ) {
		$url = yzrh_get_interview_archive_canonical();
		if ( '' !== $url ) {
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
		}
	}
}
add_action( 'wp_head', 'yzrh_output_interview_archive_canonical', 4 );

/* title タグ・個別ページの canonical・404 のステータス・ページ送りのリンクは WordPress 本体に任せる。 */

/*
 * -------------------------------------------------------------------------
 * JSON-LD スキーマの登録
 * -------------------------------------------------------------------------
 */

/**
 * 現在のリクエストで使う JSON-LD スキーマのコールバックを登録する。
 *
 * 設定キー（inc/helpers.php の seo_jsonld_*）と生成関数の対応表に従い、
 * 設定で有効なものだけを登録する。案件固有のスキーマ（FAQPage・Product など）は
 * `yzrh_jsonld_schema_map` フィルターで対応表に追加する。
 *
 * ファイル読み込み時ではなく `wp` アクション（メインクエリの確定後、`template_redirect` と
 * `wp_head` の前）で実行する。そのため `yzrh_jsonld_schema_map`・`yzrh_core_default_settings`・
 * 保存済み設定を、プラグイン・子テーマ・`after_setup_theme`・`init`・`wp`（優先度 10 未満）の
 * どこから変更しても反映され、テーマより後に定義されたコールバックも is_callable() の判定を通る。
 */
function yzrh_register_structured_data_schemas() {
	$schema_map = apply_filters(
		'yzrh_jsonld_schema_map',
		array(
			'seo_jsonld_website'              => 'yzrh_structured_data_schema_website',
			'seo_jsonld_organization'         => 'yzrh_structured_data_schema_organization',
			'seo_jsonld_breadcrumbs'          => 'yzrh_structured_data_schema_breadcrumbs',
			'seo_jsonld_blogposting'          => 'yzrh_jsonld_blogposting',
			'seo_jsonld_service'              => 'yzrh_structured_data_schema_service',
			'seo_jsonld_blog'                 => 'yzrh_structured_data_schema_blog',
			'seo_jsonld_blog_itemlist'        => 'yzrh_jsonld_blog_itemlist',
			'seo_jsonld_author'               => 'yzrh_jsonld_author_person',
			'seo_jsonld_interview_collection' => 'yzrh_structured_data_schema_interview_collection',
		)
	);

	foreach ( (array) $schema_map as $setting => $callback ) {
		// 既定値にない（案件で追加した）設定キーは、未設定なら有効として扱う。
		if ( yzrh_get_setting( $setting, true ) ) {
			YZRH_Structured_Data_Generator::register_schema( $callback );
		}
	}

	if ( yzrh_get_setting( 'seo_jsonld_enabled', true ) ) {
		add_action( 'wp_head', 'yzrh_output_jsonld', 5 );
	}
}
add_action( 'wp', 'yzrh_register_structured_data_schemas' );
