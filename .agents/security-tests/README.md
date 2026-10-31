# セキュリティ再現・回帰テスト

[../findings.md](../findings.md) の各 Finding を再現し、修正後の回帰を防ぐためのテスト。テストは「安全な状態」を期待値として書いている。**修正前のテーマでは F-01〜F-04 のテストが失敗し、修正後は成功する。**

## 構成

| パス | 内容 |
|---|---|
| `run-all.sh` | 隔離環境の作成 → WordPress のインストール → ダミーデータの投入 → 統合テスト・HTTP テスト → 破棄 |
| `down.sh` | `KEEP=1` で残した環境を破棄する |
| `env/compose.yml` | WordPress（Apache）・MariaDB・WP-CLI。internal ネットワーク（外部へ出られない）で、ホストにポートを公開しない |
| `env/seed.php` | ダミーの利用者 7 人（全権限）・投稿・保護投稿・インタビュー・階層ページ・コメント。パスワードはランダムで、出力しない |
| `env/fixtures/mu-plugins/` | 検証用に `interview` 投稿タイプとタクソノミーを登録（本番には置かない） |
| `integration/run.php` | WordPress 内で実行する統合テスト（権限ごとのカスタマイザーの保存、プロパティベーステスト、境界値） |
| `http/run.php` | 未ログインの HTTP テスト（Finding の再現、XSS・XML-RPC・コメントの否定確認、対照テスト） |
| `proposed-fix.patch` | 修正案（[../remediation-plan.md](../remediation-plan.md)）。ブランチ `fix/security-2026-10-31` で適用済み |
| `results/current/` | 修正前のテーマでの実行結果（2026-10-31） |
| `results/proposed-fix/` | 修正案を当てたコピーでの実行結果（2026-10-31） |
| `results/fix-branch/` | ブランチ `fix/security-2026-10-31` の作業ツリーでの実行結果（2026-10-31） |

## 実行方法

前提: Docker（ローカルに `wordpress:php8.2-apache`・`wordpress:cli-php8.2`・`mariadb:11.4` のイメージ）。初回はイメージのダウンロードが必要になる場合がある。

```bash
bash .agents/security-tests/run-all.sh
```

修正案を当てたテーマで確かめる場合は、別の場所に作ったコピーを指定する。

```bash
YZRH_SEC_LABEL=proposed-fix YZRH_SEC_THEME_DIR=/path/to/patched-theme bash .agents/security-tests/run-all.sh
```

調査のために環境を残す場合は `KEEP=1` を付け、終わったら `bash .agents/security-tests/down.sh` で破棄する。

## 安全のための制約

- 対象はネットワーク内の `http://yzrh.test` だけ。外部ホスト・本番・ステージングには接続しない（ネットワーク自体が外部に出られない）
- ダミーデータのみ。DB は tmpfs で、破棄すると消える
- 総当たり・大量リクエストなし（1 回の実行で HTTP 約 55 件、0.1 秒間隔）。POST は拒否されることを確かめる最小限のものだけ
- `.git/config` など取得したファイルの内容は記録せず、ステータスとサイズだけを記録する
- DB パスワードは `env/.env`（Git 管理外、実行後に削除）にだけ置く

## 結果の読み方

- `PASS`: 安全な状態
- `FAIL [F-xx]`: Finding が再現した（証拠付き）
- `INFO`: WordPress 本体の仕様による観察結果（テーマの不具合としては数えない）
- 統合テストの `MATRIX` 行: 利用者ごとのカスタマイザー設定の保存可否（[../authorization-matrix.md](../authorization-matrix.md)）
