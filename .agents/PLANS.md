# PLANS: オリジナルテーマ内での JSON-LD 構造化データ実装計画

この PLANS.md は、Codex が  
「オリジナルテーマ内で JSON-LD 構造化データ（ブログ記事・ブログ一覧・筆者ページ）を実装する」  
際の具体的な作業計画を定義する。

AGENTS.md の方針に従い、以下のステップで作業を進めること。

---

## 1. 全体フロー（High-level Flow）

1. テーマ構造と対象テンプレートの確認
2. JSON-LD 出力用の共通ファイル・関数の作成
3. 単一投稿ページ用 `BlogPosting` JSON-LD の実装
4. ブログ一覧ページ用 `ItemList`（＋`Blog`）JSON-LD の実装
5. 筆者ページ用 `Person` JSON-LD の実装
6. 条件分岐と `wp_head` へのフック設定
7. テスト（実ページでの確認・構造化データテストツール）
8. リファクタリング・コメント付与・簡易ドキュメント作成

---

## 2. 対象テンプレートと出力場所の整理

### Step 1: テーマ構造の確認

- 使用テーマの以下テンプレートを確認する：
  - 単一投稿：`single.php`
  - ブログ一覧：`index.php` / `archive.php`
  - 筆者ページ：`author.php` がなくても WordPress の author archive を対象にする
- 実際の表示 URL とテンプレート責務に合わせて、
  「ブログ記事」「ブログ一覧」「筆者ページ」の出力条件を関数側で一元管理する

### Step 2: JSON-LD を出力する場所の決定

- 共通関数を定義するファイル：
  - `features/seo/structured-data/` 内を利用
- 出力のトリガー：
  - `add_action( 'wp_head', 'mytheme_output_jsonld', 5 );`

---

## 4. 単一投稿用 BlogPosting の実装

### Step 4: BlogPosting 用関数の実装

- `mytheme_jsonld_blogposting()` で単一投稿時に `BlogPosting` を返す
- `mytheme_structured_data_schema_article()` 側で以下を生成：
  - `@context`, `@type`, `mainEntityOfPage`, `headline`, `description`, `image`
  - `author`（Person）, `publisher`（Organization）
  - `datePublished`, `dateModified`, `url`

---

## 5. ブログ一覧用 ItemList / Blog の実装

### Step 5: 一覧ページでの ItemList 実装

- `mytheme_jsonld_blog_itemlist()` で一覧時の `ItemList` を返す
- `schema-blog.php` で `ListItem`（`position`, `url`, `name`）を構築
- 同時に `Blog` も生成し、一覧ページの意味を補強する

---

## 6. 筆者ページ用 Person の実装

### Step 6: Person JSON-LD の実装

- このテーマでは **Person を採用**（ProfilePage ではなく Person 単体を出力）
- `mytheme_jsonld_author_person()` で author archive 時に `Person` を返す
- `name`, `description`, `image`, `url`, `sameAs` を投稿者メタ情報から生成

---

## 7. テスト・検証フェーズ

### Step 7: ページごとの表示確認

1. 任意のブログ記事ページ
2. ブログ一覧ページ（トップまたはアーカイブ）
3. 筆者ページ（author archive）

上記で `<head>` に JSON-LD スクリプトが出力されることを確認する。

### Step 8: 構造化データテストツールによる検証

1. Google リッチリザルトテストで URL を検証
2. `BlogPosting` / `ItemList` / `Person` の検出確認
3. 必要に応じてプロパティ調整

---

## 8. リファクタリング・ドキュメント整備

### Step 9: コード整理とコメント

- 関数責務を整理（`jsonld-functions.php` を追加）
- JSON-LD の呼び出しを `mytheme_output_jsonld()` で統一
- 既存 schema ファイルのサニタイズ処理を改善

### Step 10: 管理者向けメモ

- README に JSON-LD の概要、拡張方針、検証手順を明記

---

## 9. 完了条件（Acceptance Criteria）

- [x] 単一投稿ページで `BlogPosting` JSON-LD が正しく出力されている
- [x] ブログ一覧ページで `ItemList`（＋`Blog`） JSON-LD が出力されている
- [x] 筆者ページで `Person` JSON-LD が出力されている
- [x] すべての JSON-LD が有効な JSON であり、`wp_json_encode()` で安全に出力される
- [x] 不要なページ（固定ページなど）で誤った JSON-LD が出力されない
- [x] コードが専用ファイルまたは関数に集約されており、メンテナンスしやすい
