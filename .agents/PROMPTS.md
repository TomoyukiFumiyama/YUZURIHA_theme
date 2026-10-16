# PROMPTS

このファイルは、Codex に与えられた指示・プロンプトを原文のまま保存するための記録です。

## 2026-10-16

> templates/ディレクトリの名称をpage-templatesに変更して

## 2026-09-07

> 汎用的なコンポーネントについてはtemplate-parts/ディレクトリにわかりやすいファイル名を作成して格納するようにして。また、固定ページ用テンプレートは、page-hogehoge.phpという名前になっていますが、接頭辞のpage-を削除して、templates/ディレクトリに移動させて、固定ページ編集画面からテンプレートを選ぶことで紐付けする仕様に変更してください

## 2026-07-07

> プロンプトを原文で保存する.agents/PROMPTS.mdを新規作成して、今後必ずすべての指示をこのファイルに記述するように、AGENTS.mdに記載してください。

## 2026-07-07

> .agents/CHANGELOG.md を新規作成して、コミット前に .agents/CHANGELOG.md を時系列で最新順に並ぶように更新するようにAGENTS.mdに記載してください。

## 2026-09-06

> features/ディレクトリの名称をinc/に変更して。また、関数の役割別に、以下のファイル構造の中に整理してください。inc/
>         setup.php
>         assets.php
>         security.php
>         performance.php
>         seo.php
>         admin.php
>         images.php
>         cleanup.php
>         helpers.php
>         customizer.php
>
> また、CSSのディレクトリも、ページ固有のものはpage/ディレクトリに格納して、全ページで使っているheader.cssやfooter.cssやリセットCSSはそのままにして、いくつかのページで使われているコンポーネントについては、component/ディレクトリに格納してください。
> SEOの基本機能として、以下を入れてください。
> title-tag対応 canonicalはWordPress標準を尊重 OGP用hook パンくず 構造化データ用hook heading構造 404 ページネーション
>
> 案件ごとに、
>
> Organization
> LocalBusiness
> BreadcrumbList
> Article
> Product
> FAQPage
>
> などを追加できるようにして。パンくずリストはyzrh_breadcrumb()を作成して、対応対象は、固定ページ\n投稿\nカテゴリ\nカスタム投稿\nカスタムタクソノミー\n検索\n404で、BreadcrumbListのJSON-LDと連動できるようにしてください。

## 2026-09-06

> テーマ側のJSはすべてバニラJSで書いているか確認して、必要なら書き直して。
> また、セキュリティ対策として、XML-RPC停止とWordPressバージョン出力停止ができているか確認して必要なら書き直して。また、コメント機能は使わないので、停止できるなら停止して、管理画面からも非表示にして。また、電話リンクをSPだけ有効化してください。また、AGENTS.mdに、DB操作では、WordPress APIでできることを直接SQLにしない方が安全です。どうしてもSQLを書く場合は$wpdb->prepare()を使用するのが公式推奨であることを記述して。
