# 構成情報（WordPress inventory）

検査日: 2026-10-31。秘密情報（`wp-config.php` の値、DB パスワード、認証用のソルトなど）は記載しない。

## 調査できた範囲

本番・ステージングの URL、サーバー、DB は依頼文で指定されていなかった（`［対象URL］` などが未記入）。そのため、実環境の構成は調査していない。以下は「リポジトリ」と「検証のために作った隔離環境」の構成である。

## 1. リポジトリ（テーマ本体）

| 項目 | 内容 |
|---|---|
| 種別 | WordPress オリジナルテーマ（`style.css` の Theme Name: YUZURIHA、Version 1.0.0） |
| コミット | `main` / `afbf227`（74 コミット） |
| 必須バージョン | WordPress 6.3 以上（`style.css` の Requires at least。ブロック API v3 のため） |
| PHP | 7 以上を想定（AGENTS.md 5）。`tests/` は PHP 8.5 で実行できる |
| 構成 | `functions.php` から `inc/` の 10 ファイルを読み込む。テンプレート 15、`template-parts/` 1、`page-templates/` 2、JS 2（`js/footerscript.js`、`js/interview-blocks.js`）、CSS 10、フォント |
| 外部ライブラリ | Swiper 11.0.5（cdnjs。登録のみで、既定では読み込まない） |
| プラグイン・MU プラグイン | リポジトリには含まれない。テーマが前提とするカスタム投稿タイプ `interview` / `news` / `casestudy` / `members` とタクソノミーの登録元は、テーマの外（不明） |
| 秘密情報 | 追跡ファイルに `wp-config.php`・`.env`・`*.sql`・鍵はない。全履歴の差分から、パスワード・トークンらしき代入を検索して 0 件 |
| 公開されると問題のあるファイル | `.git/`、`.agents/`（監査・本報告書）、`AGENTS.md`、`tests/`（CLI 以外では 404） → F-04 |

### `inc/` の役割

| ファイル | 役割 | 外部入力との関係 |
|---|---|---|
| `helpers.php` | 設定値（`yzrh_settings` オプション）とヘルパー | 管理画面の UI はない |
| `setup.php` | テーマサポート・メニュー・ウィジェット | なし |
| `assets.php` | CSS / JS の読み込み、インタビュー用ダイナミックブロック 2 種 | ブロック属性（投稿本文に保存） |
| `security.php` | バージョン秘匿、XML-RPC・コメント停止、セキュリティヘッダー | HTTP |
| `performance.php` | 絵文字スクリプトの停止（フィルターで有効化） | なし |
| `seo.php` | OGP・canonical・パンくず・JSON-LD | クエリ変数、投稿・利用者・タームのデータ |
| `admin.php` | インタビュー一覧の並び順（`pre_get_posts`） | `$_GET['order']` |
| `images.php` / `cleanup.php` | 画像サイズの拡張ポイント、`<head>` の整理 | なし |
| `customizer.php` | SNS URL と GTM コードのカスタマイザー設定と出力 | カスタマイザーの保存値 |

## 2. 隔離した検証環境（`security-tests/env/compose.yml`）

| 項目 | 内容 |
|---|---|
| WordPress | 7.1.3（公式イメージ `wordpress:php8.2-apache`。DB リビジョン 61833、言語 en_US） |
| PHP / Web サーバー | PHP 8.2.34 / Apache 2.4.68（Debian）。`AllowOverride All`（`.htaccess` が有効） |
| DB | MariaDB 11.4（tmpfs。停止と同時に消える） |
| WP-CLI | 2.12.0（`wordpress:cli-php8.2`） |
| ネットワーク | Docker の internal ネットワーク（外部へ出られない）。ホストにポートを公開しない。サイトの URL は `http://yzrh.test`（ネットワーク内の別名） |
| wp-config の追加設定 | `WP_HTTP_BLOCK_EXTERNAL`、`DISABLE_WP_CRON`、`AUTOMATIC_UPDATER_DISABLED`、`WP_ENVIRONMENT_TYPE=local` |
| テーマ | リポジトリ全体を読み取り専用でマウント（Git のまま配置した状態を再現）。修正案の検証では、修正を当てたコピーをマウント |
| プラグイン | Akismet 5.7.2・Hello Dolly 1.7.2（同梱・無効）。MU プラグイン `yzrh-sec-fixtures.php`（検証用に `interview` と `interview_category` を登録） |
| パーマリンク | `/%postname%`（README の推奨どおり、末尾スラッシュなし） |
| 利用者 | 管理者・編集者・投稿者・寄稿者 A/B・購読者（会員）A/B。パスワードはランダムに生成し、どこにも出力していない |
| データ | ダミーの投稿・固定ページ・インタビュー・コメント（`security-tests/env/seed.php`）。目印の文字列（`SECRET-*`・`MEMBER-*`）は漏洩の判定用 |
| 後片付け | 実行後にコンテナ・ボリューム・ネットワークを削除済み（`docker ps -a` と `docker volume ls` で残りがないことを確認） |

## 3. 本番の確認に使えるコマンド（未実施）

実環境で構成を確認する場合の例。いずれも読み取りのみ。出力に秘密情報を含めないこと。

```bash
wp core version --extra
wp core verify-checksums
wp plugin list --fields=name,status,version,update
wp theme list --fields=name,status,version
wp eval 'echo PHP_VERSION, PHP_EOL;'
wp option get permalink_structure
wp config list --fields=name,type --format=table   # 値は出さない
wp user list --role=administrator --fields=ID,user_login
```
