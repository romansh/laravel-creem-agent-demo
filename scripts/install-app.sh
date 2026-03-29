#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${ROOT_DIR}/app"
APP_PACKAGE="${CREEM_DEMO_APP_PACKAGE:-laravel/laravel}"
APP_VERSION="${CREEM_DEMO_APP_VERSION:-^12.0}"
CREEM_PACKAGE_VERSION="${CREEM_PACKAGE_VERSION:-^1.2}"
CREEM_CLI_PACKAGE_VERSION="${CREEM_CLI_PACKAGE_VERSION:-^1.2}"
CREEM_AGENT_PACKAGE_VERSION="${CREEM_AGENT_PACKAGE_VERSION:-^1.2}"
OCTANE_PACKAGE_VERSION="${OCTANE_PACKAGE_VERSION:-^2.5}"
ENV_TEMPLATE="${ROOT_DIR}/stubs/app.env.example"
AGENT_CONFIG_TEMPLATE="${ROOT_DIR}/stubs/creem-agent.php"
BOOTSTRAP_APP_TEMPLATE="${ROOT_DIR}/stubs/bootstrap-app.php"
VIEW_CONFIG_TEMPLATE="${ROOT_DIR}/stubs/view.php"
LOCAL_AGENT_REPO="${CREEM_AGENT_PACKAGE_REPO:-${ROOT_DIR}/../laravel-creem-agent}"
LEGACY_ROOT_ENV_FILE="${ROOT_DIR}/.env"

app_ready() {
  [ -f "${APP_DIR}/artisan" ] && [ -f "${APP_DIR}/composer.json" ]
}

ensure_app_env() {
  if [ ! -f "${APP_DIR}/.env" ] && [ -f "${LEGACY_ROOT_ENV_FILE}" ]; then
    mkdir -p "${APP_DIR}"
    mv "${LEGACY_ROOT_ENV_FILE}" "${APP_DIR}/.env"
  fi

  if [ ! -f "${APP_DIR}/.env" ] && [ -f "${ENV_TEMPLATE}" ]; then
    cp "${ENV_TEMPLATE}" "${APP_DIR}/.env"
  fi
}

ensure_app_dependencies() {
  local require_args=()

  if ! grep -q '"laravel/octane"' "${APP_DIR}/composer.json"; then
    require_args+=("laravel/octane:${OCTANE_PACKAGE_VERSION}")
  fi
  if ! grep -q '"romansh/laravel-creem"' "${APP_DIR}/composer.json"; then
    require_args+=("romansh/laravel-creem:${CREEM_PACKAGE_VERSION}")
  fi
  if ! grep -q '"romansh/laravel-creem-cli"' "${APP_DIR}/composer.json"; then
    require_args+=("romansh/laravel-creem-cli:${CREEM_CLI_PACKAGE_VERSION}")
  fi
  if ! grep -q '"romansh/laravel-creem-agent"' "${APP_DIR}/composer.json"; then
    require_args+=("romansh/laravel-creem-agent:${CREEM_AGENT_PACKAGE_VERSION}")
  fi

  if [ "${#require_args[@]}" -gt 0 ]; then
    composer require --working-dir "${APP_DIR}" --no-interaction --prefer-dist "${require_args[@]}"
  fi
}

sync_local_agent_override() {
  if [ -d "${LOCAL_AGENT_REPO}/src" ] && [ -d "${APP_DIR}/vendor/romansh/laravel-creem-agent/src" ]; then
    mkdir -p \
      "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Heartbeat" \
      "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Listeners" \
      "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications" \
      "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Support"

    cp "${LOCAL_AGENT_REPO}/src/CreemAgentServiceProvider.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/CreemAgentServiceProvider.php"
    cp "${LOCAL_AGENT_REPO}/src/Heartbeat/Reporter.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Heartbeat/Reporter.php"
    cp "${LOCAL_AGENT_REPO}/src/Listeners/CreemWebhookTelegramNotifier.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Listeners/CreemWebhookTelegramNotifier.php"
    cp "${LOCAL_AGENT_REPO}/src/Notifications/FirstHeartbeat.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications/FirstHeartbeat.php"
    cp "${LOCAL_AGENT_REPO}/src/Notifications/HeartbeatAlert.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications/HeartbeatAlert.php"
    cp "${LOCAL_AGENT_REPO}/src/Notifications/HeartbeatSummary.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications/HeartbeatSummary.php"
    cp "${LOCAL_AGENT_REPO}/src/Support/TelegramMessageSender.php" "${APP_DIR}/vendor/romansh/laravel-creem-agent/src/Support/TelegramMessageSender.php"
  fi
}

sync_demo_runtime_files() {
  mkdir -p \
    "${APP_DIR}/bootstrap/cache" \
    "${APP_DIR}/database" \
    "${APP_DIR}/routes" \
    "${APP_DIR}/storage/framework/cache" \
    "${APP_DIR}/storage/framework/sessions" \
    "${APP_DIR}/storage/framework/testing" \
    "${APP_DIR}/storage/framework/views" \
    "${APP_DIR}/storage/logs"

  cp "${ENV_TEMPLATE}" "${APP_DIR}/.env.example"
  ensure_app_env

  if [ ! -f "${APP_DIR}/routes/api.php" ]; then
    cat > "${APP_DIR}/routes/api.php" <<'EOF'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['ok' => true]);
EOF
  fi

  if [ -f "${BOOTSTRAP_APP_TEMPLATE}" ]; then
    cp "${BOOTSTRAP_APP_TEMPLATE}" "${APP_DIR}/bootstrap/app.php"
  fi

  (cd "${APP_DIR}" && php artisan vendor:publish --tag=creem-config --force)
  (cd "${APP_DIR}" && php artisan vendor:publish --tag=creem-cli-config --force)
  (cd "${APP_DIR}" && php artisan vendor:publish --tag=creem-agent-config --force)
  (cd "${APP_DIR}" && php artisan vendor:publish --tag=creem-agent-migrations --force)
  (cd "${APP_DIR}" && php artisan octane:install --server=frankenphp --no-interaction)

  if [ -f "${AGENT_CONFIG_TEMPLATE}" ]; then
    cp "${AGENT_CONFIG_TEMPLATE}" "${APP_DIR}/config/creem-agent.php"
  fi
  if [ -f "${VIEW_CONFIG_TEMPLATE}" ]; then
    cp "${VIEW_CONFIG_TEMPLATE}" "${APP_DIR}/config/view.php"
  fi

  sync_local_agent_override
}

create_app() {
  local tmp_dir
  local existing_env_backup

  tmp_dir="$(mktemp -d)"
  existing_env_backup=""

  if [ -f "${APP_DIR}/.env" ]; then
    existing_env_backup="$(mktemp)"
    cp "${APP_DIR}/.env" "$existing_env_backup"
  fi

  composer create-project --no-interaction --prefer-dist "${APP_PACKAGE}" "$tmp_dir" "${APP_VERSION}"
  find "${APP_DIR}" -mindepth 1 -maxdepth 1 \
    ! -name '.env' \
    ! -name '.env.example' \
    ! -name 'README.md' \
    ! -name '.gitignore' \
    -exec rm -rf {} +
  cp -a "$tmp_dir"/. "${APP_DIR}"/
  rm -rf "$tmp_dir"
  cp "${ENV_TEMPLATE}" "${APP_DIR}/.env.example"

  if [ -n "$existing_env_backup" ] && [ -f "$existing_env_backup" ]; then
    mv "$existing_env_backup" "${APP_DIR}/.env"
  fi
}

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required to install the demo app" >&2
  exit 1
fi

if ! app_ready; then
  create_app
fi

ensure_app_dependencies
sync_demo_runtime_files

echo "Demo app is ready at ${APP_DIR}"