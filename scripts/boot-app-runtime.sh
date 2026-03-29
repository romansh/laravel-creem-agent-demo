#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-/app}"
DB_PATH="${DB_DATABASE:-/var/lib/agent-app/database.sqlite}"
DB_DIR="$(dirname "${DB_PATH}")"
LOCK_DIR="${CREEM_DEMO_BOOTSTRAP_LOCK_DIR:-${DB_DIR}/.bootstrap-lock}"
READY_FILE="${CREEM_DEMO_BOOTSTRAP_READY_FILE:-${DB_DIR}/.bootstrap-ready}"

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

run_migrations() {
  php artisan migrate --force --no-interaction
  touch "${READY_FILE}"
}

wait_for_bootstrap() {
  local attempt=0

  while [ -d "${LOCK_DIR}" ] && [ "$attempt" -lt 120 ]; do
    sleep 1
    attempt=$((attempt + 1))
  done
}

if mkdir "${LOCK_DIR}" 2>/dev/null; then
  trap 'rmdir "${LOCK_DIR}"' EXIT
  run_migrations
else
  wait_for_bootstrap

  if [ ! -f "${READY_FILE}" ]; then
    run_migrations
  fi
fi

exec "$@"