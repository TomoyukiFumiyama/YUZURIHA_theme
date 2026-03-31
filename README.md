# YOZURIHA Theme (WordPress)

WordPress オリジナルテーマのベース実装です。  
このテーマは「通常の投稿テンプレート + LP テンプレート + セキュリティ + SEO 構造化データ」を一体で管理する構成になっています。

## このテーマの特徴

- 投稿・固定ページ・アーカイブなど WordPress 標準テンプレート階層に対応
- CSS/JS をテーマ内で管理し、必要ページごとに条件分岐で読み込み
- SNS URL をカスタマイザー連携で管理（入力済み項目のみ出力）
- 命名規則は原則として `yzrh-`（ハンドル） / `yzrh_`（PHP関数）プレフィックスで統一
- `features/security/` でハードニング処理を分離
- `features/seo/structured-data/` で JSON-LD を機能別に分離
  - 単一投稿: `BlogPosting`
  - ブログ一覧: `ItemList` + `Blog`
  - 筆者ページ: `Person`

---

## ディレクトリ構成と役割

### ルート直下

- `functions.php`  
  テーマ初期化、アセット読み込み、各 feature の読み込み。
- `header.php` / `footer.php` / `sidebar.php`  
  共通レイアウト。
- `single.php` / `archive.php` / `index.php` / `page.php` / `search.php` / `404.php`  
  標準テンプレート。
- `.agents/PLANS.md`  
  JSON-LD 実装計画と完了条件。
- `AGENTS.md`  
  本テーマ作業時の実装方針。

### `features/`

- `features/seo/structured-data/`  
  JSON-LD 構造化データ生成の中核。
  - `init.php`: 各スキーマ登録と `wp_head` フック
  - `class-structured-data-generator.php`: 共通ユーティリティ / 出力エンジン
  - `schema-article.php`: `BlogPosting`
  - `schema-blog.php`: `ItemList` / `Blog`
  - `schema-author.php`: `Person`
  - `jsonld-functions.php`: 互換用の公開関数 (`yzrh_output_jsonld()` など)
- `features/security/`  
  セキュリティ関連機能の初期化・実装。
- `features/customizer/`  
  カスタマイザー（SNS 設定など）。

### `template-parts/`, `templates/`, `lp/`

- `template-parts/`  
  再利用ブロック（アーカイブ項目など）。
- `templates/`, `lp/`  
  固定ページ用テンプレート / LP 用テンプレート。

### `assets/`, `css/`, `js/`, `fonts/`

- `assets/images/` 画像アセット
- `css/` ページ・部位ごとのスタイル
- `js/` フロント側スクリプト
- `fonts/` テーマ同梱フォント

### `classes/data/`

- テーマで使うデータ定義、初期値、共通配列の保持場所。

---

## JSON-LD の動作仕様（運用メモ）

### 出力トリガー

`functions.php` → `features/seo/structured-data/init.php` を読み込み、  
`wp_head` で `yzrh_output_jsonld()` が実行されます。

### 出力される主スキーマ

- 単一投稿 (`is_singular('post')`): `BlogPosting`
- ブログ一覧 (`is_home()`, カテゴリ, タグ, 日付アーカイブ等): `ItemList` + `Blog`
- 筆者ページ (`is_author()`): `Person`

### 拡張方法

1. `features/seo/structured-data/` に新しい `schema-*.php` を追加
2. `init.php` で `require_once` と `register_schema()` を追加
3. 必要なら `class-structured-data-generator.php` に共通処理を追加
4. 実ページ + リッチリザルトテストで確認

---

## 検証手順（最低限）

1. 投稿ページ、一覧ページ、筆者ページの HTML ソースを開く
2. `<head>` 内に `<script type="application/ld+json">` があるか確認
3. Google リッチリザルトテストで URL を検証
4. エラー時は対象 `schema-*.php` の出力条件と値を修正

---

## 既存のSNS出力ヘルパー

以下のように呼び出すと、カスタマイザーで URL を設定した SNS のみ出力されます。

```php
<?php yzrh_output_sns_icons(); ?>
```
