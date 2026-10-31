# 脆弱性一覧と再現証拠（findings）

- 検査日: 2026-10-31
- 対象: YUZURIHA テーマ `main`（`afbf227`）。行番号はこのコミット時点
- 再現環境: 隔離した Docker 環境（WordPress 7.1.3 / PHP 8.2.34 / Apache 2.4.68 / MariaDB 11.4、外部ネットワークなし、ダミーデータのみ）。構成は [wordpress-inventory.md](wordpress-inventory.md)
- 再現テスト: [security-tests/](security-tests/)（`bash .agents/security-tests/run-all.sh`）。修正前の結果は `security-tests/results/current/`、修正案を当てたコピーの結果は `security-tests/results/proposed-fix/`

## 対応状況（2026-10-31 更新）

F-01〜F-04 は、ブランチ `fix/security-2026-10-31` で修正済み（**未マージ**）。修正内容は `security-tests/proposed-fix.patch` と同じ。加えて、テーマの回帰テスト（`tests/run.php`）に F-01〜F-04 の 6 件を追加し、README を更新した。

| 確認 | 結果 |
|---|---|
| 隔離環境の HTTP テスト（ブランチの作業ツリーをマウント。`security-tests/results/fix-branch/`） | 17 成功・0 失敗・参考 3（F-01〜F-04 の再現テストと、対照テスト 5 件がすべて成功） |
| 隔離環境の統合テスト | 10 成功・0 失敗 |
| `php tests/run.php` | 73 成功（追加の 6 件は、修正前のコードではすべて失敗することを確認） |

参考情報 I-01〜I-05 は、サイトの方針に合わせて判断する項目のため、このブランチでは変更していない。

## 分類と件数

| 状態 | 件数 | 内容 |
|---|---|---|
| CONFIRMED | 4 | F-01〜F-04（隔離環境で再現。修正案で解消することも確認） |
| CONFIRMED（参考情報） | 3 | I-01〜I-03（WordPress 本体の仕様による。テーマの不具合としては数えない） |
| POSSIBLE | 2 | I-04、I-05（強化策。悪用経路は確認していない） |
| FALSE POSITIVE | 11 | 下表「検証して否定した仮説」 |
| NOT TESTED | — | [coverage-matrix.md](coverage-matrix.md) |

重大度は CVSS 3.1 の基本値。4 件とも「未ログインの閲覧者が、公開されていないはずの情報を読める」種類で、改ざん・権限昇格・コード実行につながるものは見つかっていない。

| ID | 重大度 | 状態 | CWE | 概要 |
|---|---|---|---|---|
| F-01 | Medium 5.3 | CONFIRMED | CWE-359 / CWE-200 | 記事のない利用者（会員など）の筆者ページで、自己紹介・サイト URL・表示名が公開される |
| F-02 | Medium 5.3 | CONFIRMED | CWE-200 / CWE-863 | パスワード保護した投稿のアイキャッチ、インタビューのカスタムフィールド・本文の長さが漏れる |
| F-03 | Medium 5.3 | CONFIRMED | CWE-200 | 公開ページのパンくず（JSON-LD）に、非公開・下書きの親ページのタイトルと ID が出る |
| F-04 | Medium 5.3（条件付き） | CONFIRMED | CWE-538 / CWE-527 | リポジトリのままテーマを配置すると、`.git/`・`.agents/`（本報告書を含む）・`AGENTS.md` が Web から読める |

---

## F-01 記事のない利用者の筆者ページで、プロフィールが公開される

| 項目 | 内容 |
|---|---|
| CWE | CWE-359（個人情報の公開）、CWE-200 |
| CVSS 3.1 | 5.3 `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |
| 必要な権限 | なし（未ログイン） |
| 入口 | `GET /?author={ID}`、`GET /author/{スラッグ}` |
| 対象ファイル | `inc/seo.php:820-845`（`yzrh_structured_data_schema_author_person()`）、`inc/seo.php:241-278`（`get_author_schema()`。説明文 262 行、sameAs 272 行）、`archive.php:13`（`the_archive_description()`） |
| 再現条件 | 公開記事が 0 件の利用者（購読者など）が、プロフィールに自己紹介またはサイト URL を入力している |
| 期待結果 | 記事のない利用者のページは存在しない扱い（404）にする。プロフィールは出力しない |
| 実際の結果 | 200 を返し、`Person` の JSON-LD に `name`・`description`（自己紹介）・`url`（`/author/{ログイン名由来のスラッグ}`）・`sameAs`（サイト URL）を出力する。本文にも自己紹介が表示される |

**証拠**（`results/current/http.txt`。値はすべてダミー）

```
FAIL  [F-01] author archive of a member without posts does not publish profile data
      Person JSON-LD: [{"@type":"Person","name":"MEMBER-A-DISPLAY","url":"http://yzrh.test/author/member_a",
        "description":"MEMBER-A-BIO private note","sameAs":["https://member-a.example.test/profile"]}]
      … <h1 class="page-title">Author: <span>MEMBER-A-DISPLAY</span></h1><div class="archive-description">MEMBER-A-BIO private note</div>
```

**原因**: WordPress は、そのサイトの利用者であれば記事が 0 件でも投稿者アーカイブに 200 を返す。テーマは `is_author()` だけを条件に Person を出力し、記事の有無を確認していない。ID を 1 から順に指定すれば、全利用者のスラッグ（既定ではログイン名）と、入力済みのプロフィールを集められる。

**修正案**: `template_redirect` で「投稿者アーカイブかつ記事なし」を 404 にする（`YZRH_Security_Hardening::disable_empty_author_archives()`。`yzrh_404_empty_author_archives` フィルターで無効化可）。404 になると `is_author()` が false になるため、JSON-LD も本文も出力されない。修正案で再現テストが PASS になり、記事のある投稿者のページと Person は残ることを対照テストで確認した。

---

## F-02 パスワード保護した投稿の内容が一部漏れる

| 項目 | 内容 |
|---|---|
| CWE | CWE-200、CWE-863（同じデータへの認可判定が画面によって異なる） |
| CVSS 3.1 | 5.3 `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |
| 必要な権限 | なし（パスワードを知らない閲覧者） |
| 入口 | 保護された投稿の URL、インタビュー一覧 `/interview` |
| 対象ファイル | `inc/seo.php:336-343`（`get_post_image_object()`。BlogPosting 641 行・Service 683 行から呼ばれる）、`inc/seo.php:1166`（`og:image`）、`single.php:23`、`single-news.php:18`、`single-casestudy.php:17`、`page-templates/job-details.php:30`、`template-parts/post-list-item.php:25`、`archive-interview.php:127-138`（読了時間）、`archive-interview.php:148-150`（バイライン）、`archive-interview.php:188`・`333`（一覧のアイキャッチ） |
| 再現条件 | 投稿にパスワードを設定し、アイキャッチまたはインタビューのカスタムフィールドを入力している |
| 期待結果 | パスワードを入力するまで、本文と同じく付随情報（アイキャッチ・カスタムフィールド・本文の長さ）も出さない。インタビュー詳細（`single-interview.php`）は既にそうしている |
| 実際の結果 | 次の 3 か所で漏れる |

**証拠**（`results/current/http.txt`）

```
FAIL  [F-02] password-protected post …
      secret-protected-post-cover (3x)
        … "image":{"@type":"ImageObject","url":"http://yzrh.test/wp-content/uploads/2026/10/secret-protected-post-cover.jpg", …}}</script>
        … <meta property="og:image" content="http://yzrh.test/wp-content/uploads/2026/10/secret-protected-post-cover.jpg">
        … <img … src="http://yzrh.test/wp-content/uploads/2026/10/secret-protected-post-cover.jpg" class="attachment-large …
FAIL  [F-02] password-protected interview: single page hides custom fields and cover
        … <meta property="og:image" content="http://yzrh.test/wp-content/uploads/2026/10/secret-protected-interview-cover.jpg">
FAIL  [F-02] interview archive does not reveal protected interviews' custom fields, cover or length
        … <div class="interview-archive-card__name">SECRET-BYLINE-NAME<small>SECRET-BYLINE-ROLE</small></div>
        … <img width="768" height="403" src="…/secret-protected-interview-cover.jpg" …
        … <span class="interview-archive-card__read">10 min</span>
```

本文・手動の抜粋・詳細ページのカスタムフィールドは漏れていない（同じテストの対照部分で確認）。

**原因**: 2026-10-17 の監査（BUG-007）では、インタビュー詳細だけに `post_password_required()` を追加した。同じデータを出す JSON-LD・OGP・一覧カード・他の詳細テンプレートには判定がなく、画面によって保護の扱いが異なる。読了時間は本文の文字数から計算するため、本文の長さ（6000 文字 → 「10 min」）も分かる。

**修正案**: 投稿の付随情報を出す箇所で、本文と同じく `post_password_required( $post_id )` を確認する（`get_post_image_object()`、`og:image`、各テンプレートのアイキャッチ、インタビュー一覧のバイラインと読了時間）。WordPress 標準テーマ（Twenty Twenty-One 以降）も、保護中の投稿ではアイキャッチを出さない。修正案で再現テストが PASS になり、公開記事のアイキャッチ（HTML・OGP・JSON-LD の 3 か所）は残ることを対照テストで確認した。

---

## F-03 パンくずの JSON-LD に、非公開・下書きの親ページが出る

| 項目 | 内容 |
|---|---|
| CWE | CWE-200 |
| CVSS 3.1 | 5.3 `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |
| 必要な権限 | なし |
| 入口 | 親ページが非公開・下書きになっている公開ページ |
| 対象ファイル | `inc/seo.php:1031-1034`（`yzrh_get_breadcrumb_items()` の親ページのループ）。`BreadcrumbList`（`inc/seo.php:1108`）として全ページの `<head>` に出力される。HTML のパンくず `yzrh_breadcrumb()` は現在どのテンプレートからも呼ばれていないが、呼べば同じ値を表示する |
| 再現条件 | 親ページを非公開・下書きにしたまま、子ページを公開している（親を後から非公開にした場合も同じ） |
| 期待結果 | 閲覧者が見られない親ページは、パンくずに含めない |
| 実際の結果 | 親ページのタイトル（非公開なら「Private: 」付き）と、`?page_id=` 形式の URL（ID）が出る |

**証拠**（`results/current/http.txt`）

```
FAIL  [F-03] breadcrumbs of a public page do not reveal a private or draft parent
      page_child_of_private (200): …{"@type":"ListItem","position":2,"name":"Private: SECRET-PRIVATE-PARENT","item":"http://yzrh.test/?page_id=11"}…
      page_child_of_draft (200): …{"@type":"ListItem","position":2,"name":"SECRET-DRAFT-PARENT","item":"http://yzrh.test/?page_id=13"}…
```

**原因**: `get_post_ancestors()` は親の公開状態を問わず ID を返す。テーマは公開状態を確認せずに、タイトルとパーマリンクを取得している。

**修正案**: ループ内で `is_post_publicly_viewable( $ancestor_id )`（WordPress 5.7 以降。テーマの必須バージョンは 6.3）が false の親を読み飛ばす。修正案で PASS になり、公開の親ページは残ることを対照テストで確認した。

---

## F-04 リポジトリのまま配置すると、開発用ファイルが Web から読める

| 項目 | 内容 |
|---|---|
| CWE | CWE-538（外部から読める場所へのファイル配置）、CWE-527（バージョン管理リポジトリの公開） |
| CVSS 3.1 | 5.3 `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N`（`git clone` などリポジトリのまま配置した場合。README の手順どおり除外していれば該当しない） |
| 必要な権限 | なし |
| 入口 | `/wp-content/themes/{テーマ}/.git/…`、`/.agents/*.md`、`/AGENTS.md`、`/README.md` |
| 対象 | テーマ直下（`.htaccess` も `.gitattributes` もない） |
| 再現条件 | Apache（既定設定）で、テーマディレクトリにリポジトリをそのまま置く |
| 期待結果 | 開発用ファイルは配信されない |
| 実際の結果 | すべて 200 で配信される |

**証拠**（`results/current/http.txt`。内容は記録せず、ステータスとサイズだけ）

```
/.agents/AUDIT-2026-10-17.md → 200, 36688 bytes, text/markdown
/.agents/PROMPTS.md → 200, 33654 bytes, text/markdown
/AGENTS.md → 200, 8746 bytes, text/markdown
/.git/HEAD → 200, 21 bytes
/.git/config → 200, 313 bytes
/README.md → 200, 14408 bytes, text/markdown
```

**影響**: `.git/` が読めると、公開されている Git 用ツールで全ソースとコミット履歴を復元できる。`.agents/` には監査レポートと、**今回の報告書（未修正の脆弱性と再現手順）**が含まれる。履歴に秘密情報らしき値がないことは確認済み（74 コミットを検索して該当 0 件。`wp-config.php`・`.env`・鍵ファイルの追跡もなし）。そのため機密性への影響は Low とした。

**原因**: README の「本番へのデプロイ」に除外対象は書かれているが、手順に頼るだけで、配置を誤ったときの技術的な防御がない。

**修正案**: ① `.gitattributes` に `export-ignore` を設定し、`git archive` で作る ZIP から自動的に除外する。② テーマに Apache 用の `.htaccess` を置き、ドットファイル・Markdown・`.git/`・`.agents/`・`tests/` へのアクセスを拒否する（多層防御）。③ Nginx などではサーバー側に同等の設定を追加する。修正案で PASS になり、CSS・JS は引き続き配信されることを対照テストで確認した。

---

## 参考情報（WordPress 本体の仕様・強化策）

| ID | 状態 | 内容 | 証拠・根拠 | 推奨 |
|---|---|---|---|---|
| I-01 | CONFIRMED（参考） | REST API（`/wp-json/`）の応答に、テーマのセキュリティヘッダーが付かない。`nosniff` は本体が付けるが、`Referrer-Policy`・`X-Frame-Options` はない。テーマのヘッダーは `send_headers`（`inc/security.php:43`）で送るが、REST は `parse_request` の段階で応答して終了するため | `results/current/http.txt` の INFO | サーバー（Apache / Nginx / CDN）でヘッダーを付ける。または `rest_post_dispatch` で追加する |
| I-02 | CONFIRMED（参考） | コメント機能の停止前からある承認済みコメントが、`GET /wp-json/wp/v2/comments` で読める（投稿者名・本文）。テーマはテンプレートの表示だけを止めている（`inc/security.php:40-41`）。新規投稿は REST が 401、フォームが 403 で拒否される | 同上 | 既存コメントを削除またはスパムにする。または `rest_endpoints` でコメントのルートを外す |
| I-03 | CONFIRMED（参考） | 利用者の列挙: `/wp-json/wp/v2/users` が記事のある利用者のスラッグを返す（本体の仕様）。F-01 の修正で、記事のない利用者は `/?author=ID` で列挙できなくなる | 同上 | 必要なら `rest_endpoints` で `/wp/v2/users` を未ログインに閉じる。スラッグがログイン名と同じにならないよう運用する |
| I-04 | POSSIBLE（強化策） | Content-Security-Policy がない。インラインスクリプト（`404.php`、`single-interview.php`、GTM）があるため、nonce ベースの CSP の導入には改修が必要 | コード確認 | 段階的に `Content-Security-Policy-Report-Only` から導入する |
| I-05 | POSSIBLE（強化策） | GTM の入力欄は生の HTML を保存・出力する。`unfiltered_html` を持つ管理者だけが使える設計で、権限の確認は統合テストで裏付けた（下表）。GTM のコンテナ自体が、サイト全体に任意のスクリプトを配信できる外部の信頼境界になる | `results/current/integration.txt` | GTM アカウントの 2 段階認証と権限管理。CSP で読み込み元を制限する |

## 検証して否定した仮説（FALSE POSITIVE）

| 仮説 | 検証方法 | 結果 |
|---|---|---|
| 検索語・`?order`・`?paged`・404 のパス・`author_name`・カテゴリー名による反射型 XSS | 7 種類の URL に攻撃文字列を入れ、DOM を解析して、実行可能な要素・イベント属性・`javascript:` URL を探す | 実行可能な形での出力なし。JSON-LD 内の値は `<` にエスケープされ、`</script>` で抜け出せない（初回は検出器が JSON-LD を誤検知したため、JSON として解析できることを条件に除外して再判定） |
| 寄稿者が入力したインタビューのカスタムフィールド（全 17 キー＋目次・欄外メモ・リンク URL）による保存型 XSS | 寄稿者の権限で攻撃文字列を保存し、管理者が公開した後に、詳細ページと一覧ページを DOM で検査 | 実行可能な出力なし（各値は `esc_html` / `wp_kses` / `esc_url` を通る） |
| インタビューブロックの属性による保存型 XSS・クラス属性への注入 | 寄稿者として保存した攻撃用の属性（HTTP）と、ランダムな属性 400 件（プロパティベーステスト） | `<script>`・イベント属性・危険なスキーム・クラス属性の不正文字のいずれも出力されない |
| `?order` による SQL インジェクション・型の取り違え（配列・オブジェクト・巨大な文字列・NUL 文字） | 境界値とランダム入力 315 件 | 常に `ASC` / `DESC` のどちらかになる |
| GTM 入力欄の権限昇格（編集者・投稿者・`unfiltered_html` のない管理者） | 8 種類の利用者ごとに、`WP_Customize_Manager` 経由の保存と、公開する変更セットでの保存を試す | 保存できるのは `unfiltered_html` を持つ管理者だけ（下表） |
| SNS URL 設定の権限昇格 | 同上 | 保存できるのは管理者（`edit_theme_options`）だけ |
| 寄稿者 B が寄稿者 A の投稿のカスタムフィールドを変更できる | `current_user_can( 'edit_post_meta' / 'edit_post' )` | できない（テーマは本体の判定を変更していない） |
| XML-RPC の `system.multicall` によるパスワード総当たりの増幅 | `system.listMethods` と、`multicall` 経由での `wp.getUsersBlogs` 呼び出し（誤ったダミーの認証情報で 1 回だけ） | IXR 自体の `system.*` 3 種しか残っておらず、`wp.*` には届かない。ピンバックも拒否される。初回のテストは `system.*` の存在だけで失敗にしていたため誤検知 |
| コメント停止の迂回（REST・`wp-comments-post.php`） | 未ログインで 1 回ずつ POST | REST は 401、フォームは 403 |
| テーマの PHP ファイルへの直接アクセス | `functions.php`・`inc/*.php`・テンプレートを直接 GET | `ABSPATH` ガードで何も出力しない |
| `tests/` の Web 実行 | `tests/run.php`・`tests/bootstrap.php` を GET | CLI 判定で 404（2026-10-17 の BUG-023 の修正が有効） |

## GTM・SNS 設定の権限（統合テストの結果）

S = 保存できた、- = 表示も保存もできない。`/cap` は「設定単体の capability だけなら通る」ことを示す参考値（実際は `customize` 権限の確認が先に行われるため、保存できない）。

| 利用者 | SNS URL | GTM（head） | GTM（body） |
|---|---|---|---|
| 未ログイン | - | - | - |
| 会員 A / 会員 B（購読者） | - | - | - |
| 寄稿者 | - | - | - |
| 投稿者 | - | - | - |
| 編集者 | - | -（/cap） | -（/cap） |
| 管理者 | S | S | S |
| `unfiltered_html` のない管理者 | S | - | - |

編集者は単一サイトでは `unfiltered_html` を持つため、設定単体の権限は通る。プラグインなどで編集者に `edit_theme_options` を与えた場合は、GTM も編集できるようになる。これは「生の HTML を書ける利用者だけ」という設計どおりの挙動。
