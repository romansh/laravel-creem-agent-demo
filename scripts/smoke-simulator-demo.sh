#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.frankenphp.yml"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required" >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "docker compose is required" >&2
  exit 1
fi

run_simulator() {
  docker compose -f "$COMPOSE_FILE" run --rm simulator sh -lc "$1"
}

echo "[1/3] Bootstrap simulator container"
run_simulator '
  git config --global --add safe.directory /app &&
  composer install --no-interaction >/tmp/composer.log &&
  test -f .env || cp .env.example .env &&
  php artisan key:generate --force &&
  php artisan migrate:fresh --seed --force
'

echo "[2/3] Run simulator flow tests"
run_simulator '
  php artisan test tests/Feature/SimulatorFlowTest.php tests/Feature/SimulatorMultiStoreFlowTest.php
'

echo "[3/3] Run simulator smoke commands"
run_simulator '
  php artisan simulator:seed-demo --store=default --reset &&
  php artisan simulator:seed-demo --store=secondary --reset &&
  php artisan simulator:advance --store=default --sales=2 --new-customers=1 --past-due=1 &&
  php artisan simulator:advance --store=secondary --sales=1 --new-customers=1 --cancellations=1 &&
  # Webhook may fail if no URL is configured — this is expected in CI, so we handle it explicitly
  CREEM_SIMULATOR_AGENT_WEBHOOK_URL= CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET= php artisan simulator:send-webhook payment.failed --store=default \
    && echo "webhook sent (ok, no url configured)" \
    || echo "webhook skipped (no url — expected)"
'

echo "Simulator-backed demo smoke run completed."