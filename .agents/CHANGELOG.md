# CHANGELOG

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
