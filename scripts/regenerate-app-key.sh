#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE=(docker compose --project-directory "${ROOT_DIR}")

"${ROOT_DIR}/scripts/install-app.sh"

if [ ! -f "${ROOT_DIR}/app/.env" ]; then
  echo "app/.env is missing. Create it first from app/.env.example." >&2
  exit 1
fi

"${COMPOSE[@]}" run --rm -T app php artisan key:generate --force >/dev/null

if [ -d "${ROOT_DIR}/simulator" ]; then
  "${ROOT_DIR}/scripts/install-simulator.sh" >/dev/null
fi

echo "APP_KEY regenerated in app/.env"

if [ -f "${ROOT_DIR}/simulator/.env" ]; then
  echo "APP_KEY synchronized to simulator/.env"
fi