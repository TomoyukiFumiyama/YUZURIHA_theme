# YUZURIHA Theme (WordPress)

WordPress 標準 API を尊重した、拡張可能なオリジナルテーマです。

## ディレクトリ構成

`functions.php` は次の責務別モジュールだけを読み込みます。

- `inc/setup.php`: `title-tag`、HTML5、メニュー、ウィジェットなどのテーマ設定
- `inc/assets.php`: 共通・ページ別 CSS と JavaScript、エディターブロック
- `inc/security.php`: XML-RPC、pingback、セキュリティヘッダー
- `inc/performance.php`: パフォーマンス調整用フック
- `inc/seo.php`: OGP、パンくず、JSON-LD
- `inc/admin.php`: 管理・メインクエリー調整
- `inc/images.php`: 画像サイズ
- `inc/cleanup.php`: 不要な head 出力の整理
- `inc/helpers.php`: 共通設定・ヘルパー
- `inc/customizer.php`: SNS カスタマイザー

CSS は `css/header.css`、`css/footer.css`、`css/reset.min.css`、`css/style.css` を共通のまま維持し、ページ固有 CSS を `css/page/`、複数ページで再利用する部品を `css/component/` に配置します。

再利用する表示部品は、役割が分かる名前で `template-parts/` に配置します。固定ページ用のカスタムテンプレートは `page-templates/` に配置し、ファイル先頭の `Template Name` を固定ページ編集画面から選択して利用します。ファイル名に `page-` 接頭辞を付けて WordPress のスラッグ別テンプレート階層へ自動適用させないため、テンプレートの割り当ては管理画面上で明示的に管理できます。

## 導入時の設定

テンプレートには案件固有のロゴ・リンク・文言を直書きしません。次の WordPress 標準設定から表示されます。

- 動作環境: WordPress 6.3 以上（インタビュー用ブロックが Block API v3 を使うため）
- ロゴ: カスタマイザー →「サイト基本情報」→「ロゴ」で設定（未設定時はサイト名を表示）。推奨サイズは `yzrh_custom_logo_args` フィルターで変更できます
- パーマリンク: 設定 → パーマリンクで、**末尾スラッシュのない構造（例: `/%postname%`）** を選んでください。テーマが出力する一覧ページの canonical と `og:url` は末尾スラッシュなしで統一しています。末尾スラッシュ付きの構造のままだと、WordPress がスラッシュなしの URL をリダイレクトし、canonical がリダイレクトされる URL を指してしまいます
- ナビゲーション: 外観 → メニューで `Primary Menu` / `Footer Menu` を割り当て（未割り当て時は非表示）
- お問い合わせ導線: カスタマイザー「SNSアイコン」の「お問い合わせページのURL」（未設定時は 404・事例詳細のボタンを非表示）
- SNS アイコン: 同セクションの各 URL（フッターに出力）
- プライバシーポリシー: 設定 → プライバシーで指定したページ
- インタビュー詳細: 各要素はカスタムフィールド入力時のみ表示（既定のサンプル値はありません）
- 求人詳細テンプレート: 募集要項・選考フロー・応募導線は固定ページ本文で作成
- インタビュー一覧: カテゴリーのチップは `interview` に紐づくタクソノミー（`interview_category` を優先）だけを使用。紐づくタクソノミーがなければ表示しません
- Swiper: 既定では読み込みません。スライダーを使うページでだけ有効にしてください（フッターに `defer` 付きで読み込みます）

```php
add_filter( 'yzrh_use_swiper', function ( $use ) {
    return is_front_page() ? true : $use;
} );
```

監査結果と改善提案は `.agents/AUDIT-2026-10-17.md` を参照してください。

## SEO 基本機能

- `title-tag` は WordPress のテーマサポートを利用します。
- canonical は、個別ページでは WordPress コアの `rel_canonical()` に任せます。コアが canonical を出さないインタビュー一覧だけは、テーマが `?order` などのパラメータを除いたアーカイブ URL（ページ番号付き）を出力します。主要な SEO プラグイン（Yoast SEO、Rank Math、All in One SEO、SEOPress）が有効な場合は出力しません。フィルター `yzrh_output_archive_canonical` で出力の有無を、`yzrh_interview_archive_canonical` で URL を変更できます。
- 一覧ページの `og:url` は canonical と同じ URL（末尾スラッシュなし、クエリなし）です。個別ページの `og:url` は `get_permalink()` を使います。
- `ItemList` の `itemListOrder` は、クエリの並び順（`order` / `orderby`）から決まります（`?order=ASC` なら Ascending）。
- `yzrh_ogp_tags` フィルターで OGP 値を案件ごとに変更できます。
- `yzrh_breadcrumb()` は固定ページ、投稿、カテゴリ、カスタム投稿、カスタムタクソノミー、検索、404 に対応します。同じ `yzrh_get_breadcrumb_items()` を `BreadcrumbList` も利用するため表示と構造化データが一致します。
- 各主要テンプレートは一つのページ見出し (`h1`) と、記事・セクション見出し (`h2` 以下) の階層を使用します。
- 404 テンプレートは 404 ステータスと no-cache ヘッダーを明示します。
- 一覧、アーカイブ、検索結果には WordPress 標準ページネーションを使用します。

テンプレート内でパンくずを表示する場合:

```php
<?php yzrh_breadcrumb(); ?>
```

## JSON-LD と案件別拡張

標準では `WebSite`、`Organization`、`BreadcrumbList`、`BlogPosting` (`Article` 系)、`Service`、`Blog`、`ItemList`、`Person` を必要なページだけに出力します。値は PHP 配列から `wp_json_encode()` で生成されます。

`yzrh_jsonld_schema_map` は案件別スキーマ登録用、`yzrh_structured_data_schema_callbacks` は最終的なコールバック調整用です。`LocalBusiness`、`Product`、`FAQPage` などはテーマ本体を変更せず追加できます。

スキーマの登録は `wp` アクション（優先度 10）で行います。そのため、これらのフィルターはプラグイン、子テーマ、`after_setup_theme`、`init` のいずれで登録しても有効です。

JSON-LD の `description` には、手動で入力した抜粋だけを使います（入力がなければ省略します）。抜粋を自動生成すると、一覧の投稿ごとに `the_content` フィルターが `wp_head` 内で実行されてしまうためです。パスワード保護された投稿の説明文は出力しません。値は `JSON_HEX_TAG | JSON_HEX_AMP` でエンコードするため、拡張フィルターから渡した文字列でも `<script>` 要素を閉じることはできません。

著者の `Person` には `image` を出力しません。`get_avatar_url()` が返す Gravatar の URL には、メールアドレスのハッシュが含まれるためです。自前でホストしている著者写真を使う場合は、`yzrh_structured_data_author` フィルターで追加してください。

```php
add_filter( 'yzrh_structured_data_schema_callbacks', function ( $callbacks ) {
    $callbacks[] = 'project_schema_product';
    return $callbacks;
} );

function project_schema_product() {
    if ( ! is_singular( 'product' ) ) {
        return null;
    }
    return array(
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => wp_strip_all_tags( get_the_title() ),
        'url'      => get_permalink(),
    );
}
```

`Organization` を `LocalBusiness` に変更する場合は `yzrh_structured_data_organization` フィルターを利用できます。同じ登録方式で `FAQPage` も追加してください。存在しない価格、住所、画像、SNS、FAQ 回答などを推測して出力しないでください。

## 検証

1. 投稿、一覧、筆者、対象の各ページの HTML ソースを確認します。
2. `<head>` の OGP と `<script type="application/ld+json">` を確認します。
3. 表示パンくずと `BreadcrumbList.itemListElement` の順序・名称・URLが一致するか確認します。
4. Google リッチリザルトテストと Schema.org Validator で公開 URL を検証します。

## フォント

外部フォントサービスは使用せず、`fonts/` 内のフォントを `@font-face` で読み込みます。

- `NotoSansJP`: `fonts/SourceHanSansJP-VF.otf.woff2`（共通 CSS とインタビュー CSS）
- `NotoSerifJP`: `fonts/NotoSerifJP-VariableFont_wght.woff2`（インタビュー CSS の明朝・見出し）
- 等幅: OS 標準の等幅フォント

日本語フォントはサブセット化していないため、1 ファイルあたり数 MB あります。公開前に、使用する文字だけを含むサブセットや `unicode-range` で分割したファイルを用意することを推奨します。

## アセットの版数

テーマ内の CSS / JS の版数は `yzrh_asset_version( '/css/style.css' )` のように `filemtime()` から生成します。ファイルを更新したときだけ URL が変わるので、ブラウザや CDN のキャッシュを活かしたまま更新を反映できます。WordPress 本体の版数はサイト固有のハッシュに置き換えて出力します。

## テスト

WordPress 本体がなくても、PHP（と、404 のテストには Node.js）があれば実行できます。テストファイルは CLI 以外から実行すると 404 を返して終了しますが、本番の配布物には `tests/` を含めないでください（「本番へのデプロイ」を参照）。

```bash
php tests/run.php
```

## 本番へのデプロイ

**本番環境には `tests/` を含めないでください。** `tests/` は開発用の回帰テストです。WordPress 関数のスタブ定義、`shell_exec()` による Node.js の実行、一時ファイルの書き込みを含みます。CLI 以外から実行されると 404 を返して終了するようにしていますが、本番に置く必要はありません。

本番に含めないファイル:

| パス | 理由 |
|---|---|
| `tests/` | 開発用のテスト（**必ず除外**） |
| `.agents/`、`AGENTS.md` | 開発用のドキュメント・指示ログ |
| `.git/` | バージョン管理用 |

Git からテーマの ZIP を作る例:

```bash
git archive --format=zip -o yuzuriha-theme.zip HEAD -- . ':!tests' ':!.agents' ':!AGENTS.md'
```

FTP やデプロイツールでアップロードする場合も、上の表のパスを除外対象に設定してください。

## JavaScript とセキュリティ

- テーマ内 JavaScript は jQuery に依存しないバニラ JavaScriptです。ブロックエディター用コードは WordPress が提供する `window.wp` API のみを利用します。
- XML-RPC、pingback、WordPress の generator バージョン出力を停止します。
- `yzrh_security_headers` フィルターの戻り値は検証してから送ります。不正な名前、スカラー以外の値、改行を含む値は送らないため、フィルターの不具合でページが致命的エラーになることはありません。
- 絵文字アセットは `add_filter( 'yzrh_disable_emoji_assets', '__return_true' );` で停止できます（`wp` アクションで評価するため、`init` などで登録しても有効です）。
- HSTS（`Strict-Transport-Security`）は HTTPS のときだけ `max-age=31536000` を送ります。`includeSubDomains` と `preload` は既定では付けません。全サブドメインの HTTPS 化を確認した案件だけ、`yzrh_hsts_directives` フィルターで有効にしてください。サーバーや CDN で設定する場合は、フィルターで `false` を返してテーマからの送信を止めます。

```php
add_filter( 'yzrh_hsts_directives', function ( $directives ) {
    $directives['includeSubDomains'] = true;
    $directives['preload']           = true; // includeSubDomains が前提
    return $directives;
} );
```

- 404 ページの `dataLayer` には、クエリ文字列を除き、メールアドレス・電話番号・トークンなどに見えるパスのセグメントを伏せ字にした URL を送ります。外部サイトからのリファラーは origin だけを送ります。
- コメント受付を閉じ、コメント・トラックバック対応を全投稿タイプから外します。管理メニュー、管理バー、ダッシュボードにもコメント項目を表示しません。
- `tel:` リンクは CSS とバニラ JavaScriptの両方で制御し、幅767px以下のSP表示でのみ発信動作を許可します。
