#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE=(docker compose --project-directory "${ROOT_DIR}")
APP_ENV_FILE="${ROOT_DIR}/app/.env"

app_ready() {
  [ -f "${ROOT_DIR}/app/artisan" ] && [ -f "${ROOT_DIR}/app/vendor/autoload.php" ]
}

simulator_ready() {
  [ -f "${ROOT_DIR}/simulator/artisan" ] && [ -f "${ROOT_DIR}/simulator/vendor/autoload.php" ]
}

service_container_id() {
  "${COMPOSE[@]}" ps -q "$1" 2>/dev/null | head -n1
}

service_running() {
  local container_id

  container_id="$(service_container_id "$1")"

  if [ -z "$container_id" ]; then
    return 1
  fi

  [ "$(docker inspect -f '{{.State.Running}}' "$container_id" 2>/dev/null || true)" = "true" ]
}

ensure_service_running() {
  if service_running "$1"; then
    return 0
  fi

  "${COMPOSE[@]}" up -d "$1" >/dev/null
}

read_config_value() {
  local key="$1"
  local value="${!key:-}"

  if [ -n "$value" ]; then
    printf '%s\n' "$value"
    return 0
  fi

  if [ ! -f "${APP_ENV_FILE}" ]; then
    return 0
  fi

  value="$(grep -E "^${key}=" "${APP_ENV_FILE}" | tail -n1 | cut -d= -f2- || true)"
  value="${value%\"}"
  value="${value#\"}"
  printf '%s\n' "$value"
}

needs_simulator() {
  local url

  for key in CREEM_API_URL CREEM_TEST_API_URL CREEM_SECONDARY_API_URL; do
    url="$(read_config_value "$key")"
    case "$url" in
      *://simulator/*|http://simulator|http://simulator/*|http://localhost:8000/*|http://127.0.0.1:8000/*)
        return 0
        ;;
    esac
  done

  return 1
}

wait_for_simulator() {
  "${COMPOSE[@]}" exec -T app php -r '
    $ready = false;
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

if ! app_ready; then
  echo "App runtime is not prepared yet." >&2
  echo "Run 'bash scripts/install-app.sh' first, then retry heartbeat." >&2
  exit 1
fi

if needs_simulator; then
  if ! simulator_ready; then
    echo "Simulator runtime is required by the current app/.env but is not prepared yet." >&2
    echo "Run 'bash scripts/install-simulator.sh' first, then retry heartbeat." >&2
    exit 1
  fi
  ensure_service_running app
  ensure_service_running simulator
  wait_for_simulator
else
  ensure_service_running app
fi

if [ -t 0 ] && [ -t 1 ]; then
  "${COMPOSE[@]}" exec app php artisan creem-agent:heartbeat "$@"
else
  "${COMPOSE[@]}" exec -T app php artisan creem-agent:heartbeat "$@"
fi