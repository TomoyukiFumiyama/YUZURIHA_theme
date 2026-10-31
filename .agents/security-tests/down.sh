#!/usr/bin/env bash
# run-all.sh を KEEP=1 で実行したときに残した検証環境（コンテナ・ボリューム・ネットワーク）を破棄する。
set -euo pipefail
export MSYS_NO_PATHCONV=1
env_dir="$(cd "$(dirname "$0")" && (pwd -W 2>/dev/null || pwd))/env"
# compose.yml が DB パスワードを必須にしているため、破棄だけのときはダミー値で読み込ませる。
[ -f "$env_dir/.env" ] || printf 'YZRH_SEC_DB_PASSWORD=teardown\n' > "$env_dir/.env"
docker compose --project-directory "$env_dir" -f "$env_dir/compose.yml" --env-file "$env_dir/.env" --profile cli down -v --remove-orphans
rm -f "$env_dir/.env"
