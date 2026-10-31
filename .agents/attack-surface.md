# 外部入力と攻撃対象（attack surface）

検査日: 2026-10-31。対象: テーマ `main`（`afbf227`）。行番号はこのコミット時点。

## 1. WordPress 固有の入口の有無

依頼された入口の種類ごとに、テーマ内のコードを検索した結果（`register_rest_route`・`wp_ajax_`・`admin_post_`・`add_shortcode`・`template_redirect`・`init`・`$wpdb`・`wp_remote_*`・`$_GET/$_POST/$_REQUEST/$_COOKIE/$_SERVER/$_FILES`・アップロード関数・`wp_schedule_*`・`unserialize`・`eval` など）。

| 入口 | テーマ内の実装 | 備考 |
|---|---|---|
| `register_rest_route`（独自 REST API） | なし | REST は WordPress 本体のルートだけ |
| `wp_ajax_*` / `wp_ajax_nopriv_*` | なし | |
| `admin_post_*` / `admin_post_nopriv_*` | なし | |
| ショートコード | なし | |
| `template_redirect` | なし（修正案で F-01 用に 1 つ追加） | |
| `init` 内の独自処理 | `inc/assets.php:203`（ブロック登録）、`inc/security.php:45`（コメント機能の停止） | どちらも入力を受け取らない |
| 独自ログイン・会員登録 | なし | |
| ファイルアップロード | なし | |
| Webhook | なし | |
| 独自 DB 操作（`$wpdb`・SQL） | なし | すべて WordPress API（`WP_Query`・`get_post_meta` など） |
| Cron | なし | |
| 外部通信（SSRF の起点） | なし | `wp_remote_*`・`file_get_contents( URL )`・`curl` なし |
| 危険な関数 | なし | `eval`・`unserialize`・`extract`・`base64_decode`・動的 `include` はない（`tests/` の eval はテスト用で、CLI 以外では動かない） |

独自の書き込み系エンドポイントがないため、CSRF・SSRF・ファイル処理による RCE の経路は、テーマには存在しない。テーマの攻撃面は「テーマが読み取って出力するデータ」と「カスタマイザーの設定」に集中する。

## 2. 入口の一覧

| # | 入口 | 認証 | 権限チェック | 入力値 | 更新対象 | 実装箇所 | 検査結果 |
|---|---|---|---|---|---|---|---|
| E-01 | `?order=`（インタビュー一覧） | 不要 | なし（読み取り専用） | 任意の型 | なし（並び順のみ） | `inc/admin.php:42-66`、`archive-interview.php:316-317` | 安全（文字列以外を拒否し、ASC / DESC に正規化） |
| E-02 | 検索語 `?s=` | 不要 | なし | 文字列 | なし | `search.php:18`、`inc/seo.php:1064`（パンくず） | 安全（`esc_html`。JSON-LD は HEX エスケープ） |
| E-03 | リクエストパス（404・一覧の og:url・パンくず） | 不要 | なし | パス | なし | `inc/seo.php:1066`、`inc/seo.php:1277-1286`、`404.php:12-84`（dataLayer） | 安全（`esc_url` / `esc_attr`。dataLayer は PII を伏せ字にする） |
| E-04 | `?paged=` / `/page/N` | 不要 | なし | 整数 | なし | `archive-interview.php:216`、`inc/seo.php:1251` | 安全（`(int)` に変換） |
| E-05 | `?author=ID` / `/author/{スラッグ}` | 不要 | **なし（記事の有無を見ない）** | 利用者 ID | なし | `inc/seo.php:820-845`、`archive.php:13` | **F-01** |
| E-06 | 投稿・固定ページ・インタビューの URL（パスワード保護を含む） | 不要 | 本文は本体が判定。**付随情報はテンプレートごとに不統一** | — | なし | `inc/seo.php:336`・`1166`、各テンプレートのアイキャッチ、`archive-interview.php:127-150` | **F-02** |
| E-07 | 子ページの URL（親が非公開・下書き） | 不要 | **親の公開状態を見ない** | — | なし | `inc/seo.php:1031-1034` | **F-03** |
| E-08 | テーマディレクトリ内のファイル | 不要 | Web サーバー任せ | パス | なし | （テーマ直下に `.htaccess` なし） | **F-04** |
| E-09 | インタビューのカスタムフィールド（`interview_*` 18 キー） | 必要（寄稿者以上） | 本体の `edit_post_meta`（自分の投稿だけ） | 任意の文字列 | `wp_postmeta` | 書き込みは本体。出力は `single-interview.php:24-189`、`archive-interview.php:127-166` | 安全（出力時にエスケープ／kses） |
| E-10 | インタビューブロックの属性 | 必要（寄稿者以上） | 本体の `edit_post` | JSON 属性 | `post_content` | `inc/assets.php:215-281` | 安全（kses・`sanitize_html_class`・許可リスト） |
| E-11 | カスタマイザー: SNS URL 9 件・RSS 表示 | 必要 | `edit_theme_options`（管理者） | URL・真偽値 | `theme_mods_{テーマ}` | `inc/customizer.php:46-98`、出力 111-151 | 安全（`esc_url_raw` で保存、`esc_url` で出力） |
| E-12 | カスタマイザー: GTM コード 2 件 | 必要 | `customize` かつ `unfiltered_html` | 生の HTML | `theme_mods_{テーマ}` | `inc/customizer.php:153-269` | 設計どおり（管理者のみ。I-05） |
| E-13 | 利用者プロフィール（表示名・自己紹介・サイト URL・SNS 系メタ） | 必要（購読者以上、本人） | 本体 | 文字列・URL | `wp_users` / `wp_usermeta` | 出力は `inc/seo.php:241-278`・`448-469`、`single.php:43-44`、`archive.php:13` | 出力のエスケープは安全。公開範囲は F-01 |
| E-14 | `yzrh_settings` オプション | 必要（管理画面の UI なし） | — | 配列 | `wp_options` | `inc/helpers.php:49-90` | 既定値にあるキーだけを採用 |
| E-15 | 本体の XML-RPC | 不要 | — | XML | — | `inc/security.php:35-37` で停止 | 安全（`wp.*` メソッドなし、ピンバック拒否） |
| E-16 | 本体のコメント投稿（REST・フォーム） | 不要 | — | — | `wp_comments` | `inc/security.php:39-41` で停止 | 安全（401 / 403）。既存コメントは I-02 |

## 3. 信頼境界

```
未ログインの閲覧者 ──(読み取り)──▶ テンプレート / JSON-LD / OGP ◀── 投稿・メタ・利用者プロフィール
                                                      ▲
寄稿者・投稿者 ──(本体の権限で書き込み)──────────────────┘  … 出力時のエスケープが境界（E-09, E-10）
管理者（unfiltered_html） ──(カスタマイザー)──▶ GTM の生 HTML ──▶ 全ページの <head> / <body>（E-12）
GTM アカウント ──(外部)──▶ GTM コンテナのスクリプト ──▶ 全ページ（テーマの外の信頼境界）
```

見つかった 4 件はいずれも「読み取りの境界」の欠陥で、本体が非公開と判断しているデータ（パスワード保護、非公開・下書き、投稿のない利用者）を、テーマの出力が別経路で公開していた。
