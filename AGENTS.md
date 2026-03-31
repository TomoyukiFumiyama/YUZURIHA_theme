# AGENTS: オリジナルテーマ内での JSON-LD 構造化データ実装

この文書は、WordPress オリジナルテーマ内で JSON-LD 形式の構造化データ  
（ブログ記事・ブログ一覧・筆者ページ）を実装するプロジェクトにおいて、  
Codex エージェントが守るべき行動原則・役割・ゴールを定義する。

Codex は本 AGENTS.md と PLANS.md に従い、  
設計・実装・改修・調整を自律的に行うこと。

---

## 1. プロジェクトの目的（Mission）

オリジナル WordPress テーマ内で、以下 3 種類の JSON-LD 構造化データを  
正しく・過不足なく出力できる状態を作る。

1. ブログ記事ページ（単一投稿）  
   → `BlogPosting` スキーマで構造化データを出力する
2. ブログ一覧ページ（ブログトップ・アーカイブ）  
   → `ItemList`（＋必要に応じて `Blog`）で記事一覧を構造化する
3. 筆者ページ（author.php など）  
   → `Person`（または `ProfilePage`＋`mainEntity`）を出力する

目的は「検索エンジンに対して、構造化された意味情報を一貫して提供すること」であり、  
SEO の強化とともに、テーマのコードベースを整理された状態に保つことも含まれる。

---

## 2. Codex の役割（Roles）

Codex はこのプロジェクトにおいて、次の 3 つの役割を持つ。

### 2-1. Architect（設計者）

- JSON-LD の種類と出力条件（どのページで何を出すか）を整理する
- テーマ構造（`single.php` / `archive.php` / `author.php` / `functions.php` など）を前提に、
  どこにどのロジックを集約するかを決める
- 汎用性の高い構造（関数分割、専用ファイルなど）を設計する

### 2-2. Engineer（実装者）

- JSON-LD を生成する PHP 関数を実装する
- `wp_head` フック等から適切な条件分岐で JSON-LD を出力するコードを書く
- `json_encode()` を用いた安全な JSON 出力を行う
- 直アクセス防止・テーマとの整合性など、基本的な安全対策を守る

### 2-3. Documenter（記録者）

- どのページでどのスキーマ／プロパティを出しているかを書面化する
- 新しい開発者が見ても理解できるコメントと簡易ドキュメントを残す
- テスト手順（構造化データテストツールでの確認方法）をまとめる

---

## 3. 行動原則（Principles）

Codex は以下の原則に従って行動する。

### 3-1. 正確性（Correctness）

- schema.org の定義に沿った JSON-LD を出力する
- 不要なプロパティを乱用しない
- テーマ側に存在しない情報は無理に埋めない（例：画像がなければ `image` を省略するなど）

### 3-2. 一貫性（Consistency）

- 同じ意味の情報は、ページごとにバラバラな表現にしない
- URL・日付・著者情報は WordPress API から取得し、テンプレート直書きを避ける
- `BlogPosting` と `ItemList` で同一記事の URL やタイトルがズレないようにする

### 3-3. 集約（Centralization）

- JSON-LD 出力ロジックは、できる限り専用ファイル（例：`inc/jsonld.php`）や
  専用の関数群に集約し、テンプレート側では「呼び出すだけ」にする
- 条件分岐もなるべく 1 箇所（例：`yzrh_output_jsonld()`）で管理する

### 3-4. 安全性（Safety）

- JSON-LD は PHP 配列を `json_encode()` して出力し、  
  文字列連結で無理やり JSON を組み立てない
- タイトルや抜粋は `wp_strip_all_tags()` 等で HTML を除去し、
  構造化データに不要なタグが入らないようにする
- 不要な個人情報（メールアドレスなど）は JSON-LD に含めない

### 3-5. 拡張性（Extensibility）

- 後で `FAQPage` や `BreadcrumbList` などを追加できるような設計にする
- 関数名やファイル名、名前空間に余地を残しておく



### 3-6. 継続的セキュリティ改善（Continuous Security Improvement）

- WordPress 推奨のセキュリティ対策に反する実装（非推奨ヘッダー、危険な出力、未サニタイズ値など）を見つけた場合は、
  JSON-LD 改修と同時でも都度修正する
- 修正時は後方互換性を考慮しつつ、理由を README またはコミットメッセージで明示する

---

## 4. スキーマ定義の方針（Schema Policy）

Codex は以下のスキーマを採用する。

### 4-1. ブログ記事ページ（single post）

- `@type`: `BlogPosting`
- 主なプロパティ：
  - `@context`
  - `@type`
  - `mainEntityOfPage`
  - `headline`
  - `description`
  - `image`
  - `author`（`Person`）
  - `publisher`（`Organization`）
  - `datePublished`
  - `dateModified`
  - `url`

### 4-2. ブログ一覧ページ（ブログトップ／アーカイブ）

- `@type`: `ItemList`
  - `itemListElement` に `ListItem` を配列で含める
  - 各 `ListItem` に `position`, `url`, `name` を持たせる
- 必要に応じて同時に `Blog` も出力：
  - `name`
  - `description`
  - `url`

### 4-3. 筆者ページ（author.php）

- `@type`: `Person`
  - `name`
  - `description`（自己紹介）
  - `image`
  - `url`
  - `sameAs`（SNS 等があれば）

必要に応じて、`ProfilePage` を `@type` とし、  
その `mainEntity` として `Person` を含める方針も検討可。  
どちらを採用したかは PLANS.md およびコードコメントに明記する。

---

## 5. 技術的制約（Technical Constraints）

- テーマは WordPress 標準のテンプレート階層を前提とする
- PHP バージョンは 7 以上を想定
- 外部ライブラリは使わず、標準 PHP + WordPress 関数のみで実装する
- JSON-LD の `<script type="application/ld+json">` は `wp_head` 内に出力する

---

## 6. 成果物（Deliverables）

Codex は最終的に次の成果物を提供する。

1. JSON-LD 実装用の PHP ファイル
   - 例：`inc/jsonld.php`
   - もしくは `functions.php` 内のセクションとして明確に分離

2. グローバル関数群
   - `yzrh_output_jsonld()`
   - `yzrh_jsonld_blogposting()`
   - `yzrh_jsonld_blog_itemlist()`
   - `yzrh_jsonld_author_person()`
   （関数名はプロジェクト方針に応じて変更可）

3. 実装仕様書
   - どのページで、どの関数が呼ばれ、どの JSON-LD を出力しているか
   - テスト手順（構造化データテストツールの使い方）

---

## 7. 復旧方針（Recovery Policy）

- JSON-LD を出力するコードは単独ファイルにまとめるため、
  問題があればそのファイルを一時的に無効化するだけで影響を遮断できる
- テーマ更新時に変更が失われないよう、バージョン管理（Git）を利用することを推奨する
- JSON-LD の仕様変更や拡張が必要になった場合は、
  AGENTS.md と PLANS.md を更新してから実装を変更する

