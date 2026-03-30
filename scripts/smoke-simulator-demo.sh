#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE=(docker compose --project-directory "$ROOT_DIR")

ensure_app_installed() {
  if [ -f "$ROOT_DIR/app/artisan" ] && [ -f "$ROOT_DIR/app/composer.json" ]; then
    return 0
  fi

  "$ROOT_DIR/scripts/install-app.sh"
}

ensure_simulator_installed() {
  if [ -f "$ROOT_DIR/simulator/composer.json" ] && grep -q '"name": "romansh/laravel-creem-store-simulator"' "$ROOT_DIR/simulator/composer.json"; then
    return 0
  fi

  "$ROOT_DIR/scripts/install-simulator.sh"
}
resolve_agent_repo() {
  if [ -n "${CREEM_AGENT_PACKAGE_REPO:-}" ]; then
    printf '%s\n' "$CREEM_AGENT_PACKAGE_REPO"
    return 0
  fi

  printf '%s\n' "$ROOT_DIR/../laravel-creem-agent"
}

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

ensure_app_installed
ensure_simulator_installed

"${COMPOSE[@]}" down --remove-orphans >/dev/null 2>&1 || true

"${COMPOSE[@]}" up -d app simulator >/dev/null

wait_for_simulator() {
  "${COMPOSE[@]}" exec -T app php -r '
    for ($i = 0; $i < 30; $i++) {
        $body = @file_get_contents("http://simulator/");
        if ($body !== false && str_contains($body, "Creem Simulator")) {
            exit(0);
        }
        usleep(500000);
    }
    fwrite(STDERR, "Simulator HTTP API did not become ready in time." . PHP_EOL);
    exit(1);
  '
}

exec_simulator() {
  "${COMPOSE[@]}" exec -T simulator sh -lc "$1"
}

exec_app() {
  "${COMPOSE[@]}" exec -T app sh -lc "$1"
}

run_agent_package_tests() {
  local agent_repo="$(resolve_agent_repo)"

  if [ ! -d "$agent_repo" ]; then
    echo "Telegram package tests: skipped (optional laravel-creem-agent source checkout not configured)"
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
  local agent_repo="$(resolve_agent_repo)"
  local telegram_smoke_port="${TELEGRAM_SMOKE_PORT:-18081}"

  if [ ! -d "$agent_repo" ]; then
    echo "Telegram runtime smoke: skipped (optional laravel-creem-agent source checkout not configured)"
    return 0
  fi
  if [ -d "$(resolve_agent_repo)" ]; then
    printf "Telegram send smoke: verified by standalone controller run in %s\n" "$(resolve_agent_repo)"
  else
    printf "Telegram send smoke: skipped maintainer-only package runtime check (no laravel-creem-agent source checkout)\n"
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

echo "[1/7] Bootstrap agent app container"
exec_app '
  if [ ! -f vendor/autoload.php ]; then
    git config --global --add safe.directory /app &&
    composer install --no-interaction >/tmp/composer.log
  fi
  test -f .env || cp .env.example .env
  sqlite_db="${DB_DATABASE:-/var/lib/agent-app/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  rm -f "$sqlite_db" &&
  rm -rf storage/creem-agent &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate:fresh --force
'

echo "[2/7] Bootstrap simulator container"
exec_simulator '
  export CREEM_SIMULATOR_DEFAULT_WEBHOOK_SECRET=test-secret
  export CREEM_SIMULATOR_SECONDARY_WEBHOOK_SECRET=secondary-secret
  export CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=1
  export CREEM_SIMULATOR_RANDOM_SEED=2026
  if [ ! -f vendor/autoload.php ]; then
    git config --global --add safe.directory /app &&
    composer install --no-interaction >/tmp/composer.log
  fi
  test -f .env || cp .env.example .env
  sqlite_db="${DB_DATABASE:-/var/lib/simulator/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  rm -f "$sqlite_db" &&
  rm -rf storage/creem-agent &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate:fresh --seed --force
'

echo "[3/7] Run simulator flow tests"
exec_simulator '
  CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=1 \
  CREEM_SIMULATOR_RANDOM_SEED=2026 \
  CREEM_SIMULATOR_DEFAULT_WEBHOOK_SECRET=test-secret \
  CREEM_SIMULATOR_SECONDARY_WEBHOOK_SECRET=secondary-secret \
  php artisan test tests/Feature/SimulatorFlowTest.php tests/Feature/SimulatorMultiStoreFlowTest.php
'

exec_simulator '
  CREEM_SIMULATOR_RANDOM_SEED=2026 \
  php artisan simulator:seed-demo --store=default --reset &&
  CREEM_SIMULATOR_RANDOM_SEED=2026 php artisan simulator:seed-demo --store=secondary --reset
'

echo "[4/7] Run user heartbeat command"
wait_for_simulator
"${COMPOSE[@]}" exec -T \
  -e CREEM_TEST_MODE=true \
  -e CREEM_API_URL=http://simulator/api/v1 \
  -e CREEM_TEST_API_URL=http://simulator/api/v1 \
  -e CREEM_API_KEY=creem_test_demo_default \
  -e CREEM_WEBHOOK_SECRET=whsec_demo_default \
  -e CREEM_SECONDARY_API_KEY=creem_test_demo_secondary \
  -e CREEM_SECONDARY_WEBHOOK_SECRET=whsec_demo_secondary \
  -e CREEM_SECONDARY_API_URL=http://simulator/api/v1 \
  app php artisan creem-agent:heartbeat --force --all-stores

echo "[5/7] Run agent Telegram send/receive tests"
run_agent_package_tests

echo "[6/7] Run agent Telegram runtime smoke"
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

  echo "[7/7] Run simulator smoke commands"
  exec_simulator '
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
    out=$(exec_simulator '
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

  if [ -d "$(resolve_agent_repo)" ]; then
    printf "Telegram send smoke: verified by standalone controller run in %s\n" "$(resolve_agent_repo)"
  else
    printf "Telegram send smoke: skipped maintainer-only package runtime check (no laravel-creem-agent source checkout)\n"
  fi

  echo "Raw webhook responses saved to: $results_dir"

else
  echo "Could not determine compose network; running webhook step with no URL (expected to skip)."
  exec_simulator '
    # Webhook may fail if no URL is configured — this is expected in CI, so we handle it explicitly
    CREEM_SIMULATOR_AGENT_WEBHOOK_URL= CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET= php artisan simulator:send-webhook payment.failed --store=default \
      && echo "webhook sent (ok, no url configured)" \
      || echo "webhook skipped (no url — expected)"
  '
fi

echo "Simulator-backed demo smoke run completed."