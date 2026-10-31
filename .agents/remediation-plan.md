# 修正方法と優先順位（remediation plan）

検査日: 2026-10-31。**本書は提案であり、テーマのコードはまだ変更していない。** AGENTS.md と依頼の方針に従い、承認後に専用ブランチで反映する。

修正案の全体は [security-tests/proposed-fix.patch](security-tests/proposed-fix.patch)（10 ファイル、64 行追加・10 行削除）。`main`（`afbf227`）に `git apply --check` で衝突なく当たることを確認した。修正案を当てたコピーでは、次のとおりすべて成功した。

| テスト | 修正前（`results/current/`） | 修正案（`results/proposed-fix/`） |
|---|---|---|
| HTTP（未ログイン） | 11 成功・6 失敗・参考 3 | 17 成功・0 失敗・参考 3 |
| 統合（WordPress 内） | 10 成功・0 失敗 | 10 成功・0 失敗 |
| テーマの回帰テスト `tests/run.php` | 67 成功 | 67 成功 |

## 優先順位

| 順位 | ID | 対応 | 理由 | 規模 |
|---|---|---|---|---|
| 1 | F-04 | 本番のテーマディレクトリに `.git/`・`.agents/`・`AGENTS.md`・`tests/` がないか、**今すぐ確認する**（コード修正の前に、運用で対応できる） | 該当すると、本報告書を含む内部資料が読め、F-01〜F-03 の悪用の手引きになる | 確認のみ |
| 2 | F-04 | `.gitattributes`（`export-ignore`）とテーマの `.htaccess` を追加。Nginx の場合はサーバー設定 | 配置を誤っても配信されないようにする | 小 |
| 3 | F-01 | 記事のない投稿者アーカイブを 404 にする | 未ログインで全利用者を列挙でき、個人情報が対象になる | 小 |
| 4 | F-02 | 保護中の投稿で、アイキャッチ・カスタムフィールド・本文の長さを出さない | 限定公開のつもりの情報が漏れる | 小（8 ファイル） |
| 5 | F-03 | パンくずから非公開・下書きの親を除く | 公開前の情報が漏れる | 小（1 行） |
| 6 | I-01〜I-03 | サーバー側のヘッダー、既存コメントの整理、利用者列挙の制限 | 本体の仕様による。サイトの方針に合わせて判断 | 運用 |
| 7 | I-04、I-05 | CSP の段階的な導入、GTM アカウントの保護 | 多層防御 | 中 |

## 修正内容

### F-04 開発用ファイルを配信しない

1. `.gitattributes`（新規）: `/.agents`・`/tests`・`/AGENTS.md`・`/.gitattributes` を `export-ignore` にする。README の `git archive` の例は、除外の指定がなくても同じ結果になる。
2. `.htaccess`（新規、Apache 2.4）: ドットで始まるファイルと `*.md`・`*.lock`・`*.sh`・`*.yml` などを `Require all denied` にする。`.git/`・`.agents/`・`tests/` を `RedirectMatch 404` にする。
3. Nginx の場合（`.htaccess` は効かない）:

```nginx
location ~* /wp-content/themes/[^/]+/(\.git|\.agents|tests)(/|$) { return 404; }
location ~* /wp-content/themes/[^/]+/.*\.(md|lock|sh|ya?ml)$   { return 404; }
location ~ /\.                                                  { deny all; }
```

4. README の「本番へのデプロイ」に、`.gitattributes` によって自動的に除外されることと、Nginx の設定例を追記する。

後方互換性: CSS・JS・フォント・画像は従来どおり配信される（対照テストで確認）。

### F-01 記事のない投稿者アーカイブを 404 にする

`inc/security.php` の `YZRH_Security_Hardening` に、`template_redirect` で動く次の処理を追加する。

```php
public static function disable_empty_author_archives() {
	if ( ! is_author() || have_posts() || ! apply_filters( 'yzrh_404_empty_author_archives', true ) ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
```

後方互換性: 記事のない筆者ページを意図して見せている案件では、`add_filter( 'yzrh_404_empty_author_archives', '__return_false' );` で従来の挙動に戻せる。記事のある投稿者のページと Person の JSON-LD は変わらない（対照テストで確認）。

### F-02 パスワード保護中は付随情報も出さない

| ファイル | 変更 |
|---|---|
| `inc/seo.php` `get_post_image_object()` | 保護中なら `null`（BlogPosting・Service の `image` を省略） |
| `inc/seo.php` `yzrh_get_ogp_tags()` | 保護中は `og:image` を出さない |
| `single.php`・`single-news.php`・`single-casestudy.php`・`page-templates/job-details.php`・`template-parts/post-list-item.php` | `has_post_thumbnail() && ! post_password_required()` |
| `archive-interview.php` | 一覧・注目記事のアイキャッチ、バイライン（カスタムフィールド）、読了時間を、保護中は出さない |

後方互換性: 保護していない投稿の表示は変わらない。保護中の投稿では、一覧のバイラインが投稿者の表示名と既定の肩書きに戻り、読了時間は空になる。

### F-03 パンくずに非公開・下書きの親を入れない

`inc/seo.php` の `yzrh_get_breadcrumb_items()` で、親ページのループに `if ( ! is_post_publicly_viewable( $ancestor_id ) ) { continue; }` を追加する。後方互換性: 公開の親ページは従来どおり表示される。

### 参考情報への対応（任意）

- I-01: Web サーバーまたは CDN で `Referrer-Policy`・`X-Frame-Options`・`Permissions-Policy` を全レスポンスに付ける。テーマで行う場合は `rest_post_dispatch` でヘッダーを追加する。
- I-02: 管理画面で既存コメントを削除（またはスパムに）する。コメントを今後も使わないなら、`rest_endpoints` フィルターで `/wp/v2/comments` を外す。
- I-03: 会員サイトでは、`rest_endpoints` で `/wp/v2/users` を未ログインに閉じることを検討する。
- I-04: `Content-Security-Policy-Report-Only` から始める。インラインスクリプト（`404.php`・`single-interview.php`）は外部ファイル化するか nonce を付ける。
- I-05: GTM アカウントの 2 段階認証、公開権限の最小化、変更の承認フロー。

## 反映手順（承認後）

```bash
git switch -c fix/security-2026-10-31
git apply .agents/security-tests/proposed-fix.patch
php tests/run.php
bash .agents/security-tests/run-all.sh
```

続けて、README・`.agents/CHANGELOG.md` を更新し、修正の理由（本書の Finding ID）をコミットメッセージに記載する（AGENTS.md 3-6）。
