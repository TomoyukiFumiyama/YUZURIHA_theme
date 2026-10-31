#!/usr/bin/env bash
# 隔離環境を作り、ダミーデータを投入し、統合テストと HTTP テストを実行する。
#
#   bash .agents/security-tests/run-all.sh          # 実行後に環境を破棄する
#   KEEP=1 bash .agents/security-tests/run-all.sh   # 調査用に環境を残す（後で down.sh で破棄）
#
# 前提: Docker（ローカルに wordpress:php8.2-apache / wordpress:cli-php8.2 / mariadb:11.4）。
# ネットワークは internal（外部へ出られない）で、ホストにポートを公開しない。
# 結果は .agents/security-tests/results/ に保存する（機密情報は含まない）。
set -euo pipefail
# Git Bash（Windows）がコンテナ内のパス（/sec/...）をホストのパスに書き換えないようにする。
export MSYS_NO_PATHCONV=1

# Windows（Git Bash）では docker に渡せる C:/ 形式のパスにする。
here="$(cd "$(dirname "$0")" && (pwd -W 2>/dev/null || pwd))"
env_dir="$here/env"
# YZRH_SEC_LABEL で結果の保存先を分ける（例: 修正前 = current、修正案 = proposed-fix）。
results="$here/results/${YZRH_SEC_LABEL:-current}"
mkdir -p "$results"

# DB パスワードは毎回ランダムに作り、env/.env（Git 管理外）にだけ置く。表示しない。
if [ ! -f "$env_dir/.env" ]; then
	printf 'YZRH_SEC_DB_PASSWORD=%s\n' "$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')" > "$env_dir/.env"
fi

dc() { docker compose --project-directory "$env_dir" -f "$env_dir/compose.yml" --env-file "$env_dir/.env" "$@"; }
wp() { dc run --rm -T cli wp "$@"; }

cleanup() {
	if [ "${KEEP:-0}" != "1" ]; then
		dc --profile cli down -v --remove-orphans >/dev/null 2>&1 || true
		rm -f "$env_dir/.env"
	fi
}
trap cleanup EXIT

dc up -d --wait db >/dev/null
dc up -d wp >/dev/null
for _ in $(seq 1 60); do
	if dc exec -T wp test -f /var/www/html/wp-includes/version.php 2>/dev/null; then break; fi
	sleep 2
done

if ! wp core is-installed 2>/dev/null; then
	wp core install --url=http://yzrh.test --title='YZRH Security Lab' --admin_user=lab_installer \
		--admin_password="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')" \
		--admin_email=installer@example.test --skip-email >/dev/null
	wp theme activate yzrh >/dev/null
	wp rewrite structure '/%postname%' >/dev/null
	wp eval 'file_put_contents( ABSPATH . ".htaccess", "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n" );' >/dev/null
	wp eval-file /sec/env/seed.php
fi

{
	echo "# environment"
	wp core version --extra
	wp eval 'echo "PHP " . PHP_VERSION . "\n";'
	wp theme list --fields=name,status,version
	wp plugin list --fields=name,status,version
	wp eval 'echo "mu-plugins: " . implode( ", ", array_keys( get_mu_plugins() ) ) . "\n";'
	wp option get permalink_structure
} > "$results/environment.txt" 2>&1

status=0
wp eval-file /sec/integration/run.php > "$results/integration.txt" 2>&1 || status=1
fixture="$(wp option get yzrh_sec_fixture --format=json)"
dc run --rm -T -e YZRH_SEC_FIXTURE="$fixture" --entrypoint php cli /sec/http/run.php > "$results/http.txt" 2>&1 || status=1

tail -n 3 "$results/integration.txt" "$results/http.txt"
exit $status
