#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.frankenphp.yml"

read_config_value() {
  local key="$1"
  local value="${!key:-}"

  if [ -n "$value" ]; then
    printf '%s\n' "$value"
    return 0
  fi

  if [ ! -f "${ROOT_DIR}/.env" ]; then
    return 0
  fi

  value="$(grep -E "^${key}=" "${ROOT_DIR}/.env" | tail -n1 | cut -d= -f2- || true)"
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
  docker compose -f "${COMPOSE_FILE}" run --rm -T app php -r '
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

if [ ! -f "${ROOT_DIR}/.env" ] && [ -f "${ROOT_DIR}/.env.example" ]; then
  cp "${ROOT_DIR}/.env.example" "${ROOT_DIR}/.env"
fi

"${ROOT_DIR}/scripts/install-simulator.sh"

if needs_simulator; then
  docker compose -f "${COMPOSE_FILE}" up -d simulator >/dev/null
  wait_for_simulator
fi

if [ -t 0 ] && [ -t 1 ]; then
  docker compose -f "${COMPOSE_FILE}" run --rm app php artisan creem-agent:heartbeat "$@"
else
  docker compose -f "${COMPOSE_FILE}" run --rm -T app php artisan creem-agent:heartbeat "$@"
fi