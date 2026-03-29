#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-/app}"
DB_PATH="${DB_DATABASE:-/var/lib/agent-app/database.sqlite}"
DB_DIR="$(dirname "${DB_PATH}")"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"

mkdir -p \
  "${APP_DIR}/bootstrap/cache" \
  "${APP_DIR}/database" \
  "${APP_DIR}/storage/framework/cache" \
  "${APP_DIR}/storage/framework/sessions" \
  "${APP_DIR}/storage/framework/testing" \
  "${APP_DIR}/storage/framework/views" \
  "${APP_DIR}/storage/logs" \
  "${DB_DIR}"

touch "${DB_PATH}"

if [ "${RUN_MIGRATIONS}" = "1" ]; then
  php artisan migrate --force --no-interaction
fi

exec "$@"