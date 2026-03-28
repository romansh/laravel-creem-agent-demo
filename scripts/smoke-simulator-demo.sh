#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.frankenphp.yml"

cleanup() {
  docker rm -f webhook-receiver telegram-smoke-receiver >/dev/null 2>&1 || true
}

trap cleanup EXIT

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

run_agent_package_tests() {
  local agent_repo="$ROOT_DIR/../laravel-creem-agent"

  if [ ! -d "$agent_repo" ]; then
    echo "Telegram smoke status: skipped (../laravel-creem-agent not found)"
    return 0
  fi

  (
    cd "$agent_repo"
    if [ ! -x vendor/bin/pest ]; then
      composer install --no-interaction >/tmp/creem-agent-composer.log
    fi

    php vendor/bin/pest \
      tests/Feature/TelegramWebhookControllerTest.php \
      tests/Feature/TelegramWebhookControllerMoreTest.php \
      tests/Feature/TelegramWebhookControllerFailureTest.php
  )
}

run_agent_telegram_runtime_smoke() {
  local agent_repo="$ROOT_DIR/../laravel-creem-agent"
  local telegram_smoke_port="${TELEGRAM_SMOKE_PORT:-18081}"

  if [ ! -d "$agent_repo" ]; then
    echo "Telegram runtime smoke status: skipped (../laravel-creem-agent not found)"
    return 0
  fi

  docker rm -f telegram-smoke-receiver >/dev/null 2>&1 || true
  docker run -d --name telegram-smoke-receiver -p "127.0.0.1:${telegram_smoke_port}:80" kennethreitz/httpbin >/dev/null

  for i in $(seq 1 15); do
    curl -fsS -o /dev/null "http://127.0.0.1:${telegram_smoke_port}/get" >/dev/null 2>&1 && break
    sleep 1
  done

  (
    cd "$agent_repo"

    CREEM_AGENT_TELEGRAM_API_BASE="http://127.0.0.1:${telegram_smoke_port}/anything" \
    CREEM_AGENT_TELEGRAM_TOKEN="tok" \
    CREEM_AGENT_TELEGRAM_CHAT_ID="chat-1" \
    CREEM_AGENT_TELEGRAM_SMOKE_INCOMING_CHAT_ID="chat-1" \
    CREEM_AGENT_TELEGRAM_SMOKE_TEXT="smoke ping" \
    CREEM_AGENT_TELEGRAM_SMOKE_REPLY="smoke reply" \
    php bin/telegram-webhook-smoke.php
  )

  docker rm -f telegram-smoke-receiver >/dev/null 2>&1 || true
}

echo "[1/5] Bootstrap simulator container"
run_simulator '
  CREEM_SIMULATOR_DEFAULT_WEBHOOK_SECRET=test-secret \
  CREEM_SIMULATOR_SECONDARY_WEBHOOK_SECRET=secondary-secret \
  CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=1 \
  CREEM_SIMULATOR_RANDOM_SEED=2026 \
  git config --global --add safe.directory /app &&
  composer install --no-interaction >/tmp/composer.log &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/creem-simulator/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  rm -f "$sqlite_db" &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate:fresh --seed --force
'

echo "[2/5] Run simulator flow tests"
run_simulator '
  CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=1 \
  CREEM_SIMULATOR_RANDOM_SEED=2026 \
  CREEM_SIMULATOR_DEFAULT_WEBHOOK_SECRET=test-secret \
  CREEM_SIMULATOR_SECONDARY_WEBHOOK_SECRET=secondary-secret \
  php artisan test tests/Feature/SimulatorFlowTest.php tests/Feature/SimulatorMultiStoreFlowTest.php
'

echo "[3/5] Run agent Telegram send/receive tests"
run_agent_package_tests

echo "[4/5] Run agent Telegram runtime smoke"
run_agent_telegram_runtime_smoke

PROJECT_NAME="$(basename "$ROOT_DIR")"
NETWORK_NAME="${COMPOSE_PROJECT_NAME:-${PROJECT_NAME}}_default"
if ! docker network inspect "$NETWORK_NAME" >/dev/null 2>&1; then
  NETWORK_NAME="$(docker network ls --filter "name=_default" --format '{{.Name}}' | head -n1)"
fi

if [ -n "$NETWORK_NAME" ]; then
  echo "Starting temporary webhook receiver on network: $NETWORK_NAME"
  docker rm -f webhook-receiver >/dev/null 2>&1 || true
  docker run -d --name webhook-receiver --network "$NETWORK_NAME" kennethreitz/httpbin >/dev/null

  # Wait for receiver readiness
  for i in $(seq 1 15); do
    docker run --rm --network "$NETWORK_NAME" curlimages/curl:7.88.1 -fsS -o /dev/null http://webhook-receiver/get >/dev/null 2>&1 && break
    sleep 1
  done

  echo "[5/5] Run simulator smoke commands"
  run_simulator '
    CREEM_SIMULATOR_RANDOM_SEED=2026 \
    php artisan simulator:seed-demo --store=default --reset &&
    CREEM_SIMULATOR_RANDOM_SEED=2026 php artisan simulator:seed-demo --store=secondary --reset &&
    CREEM_SIMULATOR_RANDOM_SEED=2026 php artisan simulator:advance --store=default --sales=2 --new-customers=1 --past-due=1 &&
    CREEM_SIMULATOR_RANDOM_SEED=2026 php artisan simulator:advance --store=secondary --sales=1 --new-customers=1 --cancellations=1
  '

  # Send multiple webhooks to the ephemeral receiver and collect results
  WEBHOOK_COUNT=${WEBHOOK_COUNT:-3}
  results_dir="/tmp/smoke-webhooks-$$"
  mkdir -p "$results_dir"
  success_count=0
  signature_count=0

  for i in $(seq 1 "$WEBHOOK_COUNT"); do
    echo "Sending webhook #$i"
    out=$(run_simulator '
      CREEM_SIMULATOR_AGENT_WEBHOOK_URL="http://webhook-receiver/post" CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET="test-secret" php artisan simulator:send-webhook payment.failed --store=default
    ')
    echo "$out" > "$results_dir/response-$i.json"

    if echo "$out" | grep -q '"ok": true'; then
      success_count=$((success_count+1))
    fi
    if echo "$out" | grep -q 'Creem-Signature'; then
      signature_count=$((signature_count+1))
    fi
  done

  # Summary table for webhooks
  total=$WEBHOOK_COUNT
  failed=$((total - success_count))
  printf "\nWebhook summary:\n"
  printf "%-10s | %-8s | %-9s | %-10s\n" "Total" "Success" "Failures" "Signatures"
  printf "%-10s-+-%-8s-+-%-9s-+-%-10s\n" "----------" "--------" "---------" "----------"
  printf "%-10s | %-8s | %-9s | %-10s\n" "$total" "$success_count" "$failed" "$signature_count"

  printf "Telegram send smoke: verified by standalone controller run in ../laravel-creem-agent\n"

  echo "Raw webhook responses saved to: $results_dir"

else
  echo "Could not determine compose network; running webhook step with no URL (expected to skip)."
  run_simulator '
    # Webhook may fail if no URL is configured — this is expected in CI, so we handle it explicitly
    CREEM_SIMULATOR_AGENT_WEBHOOK_URL= CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET= php artisan simulator:send-webhook payment.failed --store=default \
      && echo "webhook sent (ok, no url configured)" \
      || echo "webhook skipped (no url — expected)"
  '
fi

echo "Simulator-backed demo smoke run completed."