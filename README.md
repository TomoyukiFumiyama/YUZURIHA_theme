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

## SEO 基本機能

- `title-tag` は WordPress のテーマサポートを利用します。
- canonical は WordPress コアの `rel_canonical()` とアーカイブの canonical を尊重し、テーマで重複出力しません。
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

## JavaScript とセキュリティ

- テーマ内 JavaScript は jQuery に依存しないバニラ JavaScriptです。ブロックエディター用コードは WordPress が提供する `window.wp` API のみを利用します。
- XML-RPC、pingback、WordPress の generator バージョン出力を停止します。
- コメント受付を閉じ、コメント・トラックバック対応を全投稿タイプから外します。管理メニュー、管理バー、ダッシュボードにもコメント項目を表示しません。
- `tel:` リンクは CSS とバニラ JavaScriptの両方で制御し、幅767px以下のSP表示でのみ発信動作を許可します。
