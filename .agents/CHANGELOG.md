# CHANGELOG

## 2026-10-18

- インタビュー一覧の CollectionPage JSON-LD から、固定の日本語説明文（マーケティング／デザイン等）を削除。説明文はメタディスクリプション・リードと同じ `yzrh_interview_archive_description()` から取得し、空の場合は `description` を省略する。ヘルパーはテンプレートから `inc/helpers.php` へ移動。
- PR #14 のレビュー対応: インタビュー一覧のトップバーで、`wp_nav_menu()` の現在項目（`aria-current="page"`、`current-menu-item` / `current_page_item` / `current-menu-ancestor`）にも、フォールバックリンクの `.is-active` と同じ強調表示（文字色・下線）を適用。
- PR #14 のレビュー対応: インタビュー一覧の canonical のページ番号部分を、固定の `/page/` ではなく `$wp_rewrite->pagination_base` とパーマリンク設定（`using_permalinks()`）から生成するよう修正。ページ送りのベースを変更しているサイトでも、存在する URL を指す。

## 2026-10-17

- `custom-logo` のテーマサポートを追加（`yzrh_custom_logo_args` で変更可能）。
- 一覧ページの `og:url` を canonical と一致させ、テーマが生成する一覧ページの URL（canonical・`og:url`）を末尾スラッシュなしで統一。
- セキュリティヘッダーの検証を `prepare_header_lines()` に分離し、フィルターが文字列以外や改行を含む値を返しても致命的エラーにならないよう修正。
- インタビュー用ブロックの API バージョンを PHP / JS とも v3 に統一し、JS の編集画面を `useBlockProps()` に移行。`style.css` の `Requires at least` を 6.3 に変更。
- Person スキーマから Gravatar の URL（`image`）を削除。
- `itemListOrder` をクエリの並び順から決めるよう変更（`?order=ASC` で Ascending）。
- `yzrh_disable_emoji_assets` を `wp` アクションで評価するよう変更し、WordPress 6.4+ の `wp_enqueue_emoji_styles` も対象に追加。
- 監査レポートを更新（28 件中 27 件が修正済み。BUG-027 は未使用フォントの同梱をリスク受容）。
- README に「本番へのデプロイ」を追加し、`tests/`（必須）・`.agents/`・`AGENTS.md`・`.git/` を本番に含めないことと、`git archive` で除外して ZIP を作る例を記載。
- 修正後に再監査を行い、`.agents/AUDIT-2026-10-17.md` を最新の状態に書き換え（28 件。うち修正済み 20、一部対応 2、未対応 6）。
- `tests/run.php` / `tests/bootstrap.php` に CLI 判定を追加し、Web から実行できないよう修正（再監査で検出）。
- JSON-LD の説明文を手動で入力した抜粋だけにし、一覧の `wp_head` 内で `the_content` フィルターを実行しないよう変更。OGP は従来どおり生成された抜粋も使用。
- 死蔵コードを削除（`lp/*.php`、空の `page-templates/template-1.php`、未使用の `init()`、重複した `ABSPATH` ガード、未使用の画像サイズ `yzrh-card`）。`inc/customizer.php` と `sidebar.php` にガードを追加。
- SNS アイコン用の `css/component/sns-icons.css` を追加。SNS リンクを `rel="noopener noreferrer"` にし、アイコンに `aria-hidden` を付与。
- Swiper を登録だけにし、`yzrh_use_swiper` フィルターで有効にしたページでだけ、フッターに `defer` 付きで読み込むよう変更（既定では読み込まない）。
- インタビュー一覧のタクソノミーを `interview` に紐づくものから決めるよう変更し、ブログのカテゴリーへのフォールバックを廃止。分野数を全件で数え、「Updated」（現在の年）を最新の公開日「Latest」に置き換え。
- JSON-LD に `JSON_HEX_AMP` を追加。スキーマの登録を、ファイル読み込み時から `wp` アクションへ移動。
- インタビュー一覧の並び順を `inc/admin.php` の `yzrh_get_interview_order()`（`is_string()` で判定してから正規化）に集約し、クエリのフックとテンプレートの両方から呼ぶよう変更。`?order[]=` で致命的エラーになり得た問題を解消。
- インタビュー一覧に、`?order` などを除いたアーカイブ URL を指す canonical を出力（主要 SEO プラグインが有効な場合は出力しない）。
- CollectionPage 内の重複した `breadcrumb`（BreadcrumbList）を削除。
- ItemList / CollectionPage の `position` を、出力した件数で数えるカウンター方式にし、`offset` クエリ変数も反映。
- HSTS の既定値を「HTTPS のときだけ `max-age=31536000`」に変更し、`includeSubDomains` / `preload` は `yzrh_hsts_directives` フィルターで案件ごとに有効化する方式へ変更（従来は 2 年・`includeSubDomains`・`preload` を無条件に送信していた）。
- 404 の `dataLayer` で、パス中のメールアドレス・電話番号・UUID・トークンらしきセグメントを伏せ字にし、外部リファラーは origin だけを送るよう変更。
- 未使用になったニュースレター欄の CSS（`.interview-archive-newsletter*`）を削除。
- アセットの版数を `filemtime()` ベースの共通ヘルパー `yzrh_asset_version()` に統一し、毎リクエスト変わっていた `date('YmdHis')` を廃止。
- `ver` 削除フィルターを、WordPress 本体の版数だけをサイト固有ハッシュに置き換える方式へ変更（版数の秘匿とキャッシュバスティングを両立し、URL を再構築しない）。
- パスワード保護中のインタビュー詳細で、カスタムフィールド・リード・アイキャッチを出力しないよう修正。
- 404 の `dataLayer` に送る URL・リファラーからクエリ文字列とハッシュを除去。
- JSON-LD の文字列を `sanitize_text()`（全エンティティのデコード後にタグ除去）に統一し、`JSON_HEX_TAG` を付与。ItemList の URL を非エスケープで取得。
- インタビュー一覧・詳細の Google Fonts 読み込みを削除し、`fonts/` 内の Noto Sans JP（Source Han Sans）／Noto Serif JP を `@font-face` で利用。
- WordPress なしで実行できる回帰テスト `tests/run.php` を追加。
- テーマ全体の潜在不具合・セキュリティ監査を実施し、結果と `inc/` の改善提案、ベーステーマのひな形整理案を `.agents/AUDIT-2026-10-17.md` に記録。
- ひな形から案件固有の情報を除去: ヘッダー・フッターの存在しないロゴ画像と固定ナビゲーションを、カスタムロゴ／サイト名と `wp_nav_menu()`（primary / footer）に置き換え。
- 固定の `/contact/` リンクを、カスタマイザーの「お問い合わせページの URL」が設定されている場合のみ表示する方式に変更（404・事例詳細・インタビュー一覧）。
- インタビュー一覧の固定ブランド名・固定リンク・サンプル文言を除去し、メールアドレスを GET 送信していた未実装のニュースレターフォームを削除。
- インタビュー詳細のサンプル既定値（数値・目次・所在地など）を削除し、カスタムフィールド入力時のみ表示。
- 求人詳細テンプレートの架空企業の求人情報と外部プレースホルダー画像を削除し、タイトル・抜粋・アイキャッチ・本文による汎用レイアウトに変更。

## 2026-10-16

- 固定ページ用カスタムテンプレートのディレクトリを `templates/` から `page-templates/` に改名し、Job Details テンプレート判定と README を追従。

## 2026-09-07

- 投稿一覧カードを `template-parts/post-list-item.php` に集約し、ブログ一覧とアーカイブで再利用。
- 固定ページ用カスタムテンプレートを `templates/` に移し、管理画面で明示的に選択する構成へ変更。
- 移動後の Job Details テンプレート判定、ディレクトリ運用ドキュメント、指示ログを更新。

## 2026-09-06

- テーマ JavaScript が jQuery 等に依存しないバニラ JavaScript であることを確認し、SP 以外で電話リンクの遷移を抑止。
- XML-RPC と WordPress バージョン出力の停止を再確認・強化。
- コメント投稿を停止し、投稿タイプ、管理メニュー、管理バー、ダッシュボードからコメント機能を非表示化。
- WordPress API を優先し、直接 SQL が不可避な場合は `$wpdb->prepare()` を使う方針を `AGENTS.md` に追加。
- `features/` を廃止し、テーマ機能を責務別の `inc/*.php` 10 ファイルへ再編。
- ページ固有 CSS を `css/page/` に移し、共通 CSS と再利用コンポーネント用ディレクトリを分離。
- OGP フック、HTML/JSON-LD 共通パンくず、案件別スキーマ登録フックを追加。
- 検索結果を番号付き WordPress 標準ページネーションへ統一。
- SEO 構成、拡張方法、検証手順と今回の指示ログを更新。

このファイルは、Codex による変更履歴を時系列の最新順で記録するためのログです。

## 2026-07-07

- `.agents/CHANGELOG.md` を新規作成。
- コミット前に `.agents/CHANGELOG.md` を最新順で更新する運用を `AGENTS.md` に追記。
- 今回の追加指示を `.agents/PROMPTS.md` に原文で追記。
