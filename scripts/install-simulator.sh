#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${ROOT_DIR}/app"
APP_ENV_FILE="${APP_DIR}/.env"
SIMULATOR_DIR="${ROOT_DIR}/simulator"
SIMULATOR_PACKAGE="${CREEM_SIMULATOR_PACKAGE:-romansh/laravel-creem-store-simulator}"
SIMULATOR_VERSION="${CREEM_SIMULATOR_VERSION:-^1.0}"
CREEM_PACKAGE_VERSION="${CREEM_PACKAGE_VERSION:-^1.2}"
CREEM_CLI_PACKAGE_VERSION="${CREEM_CLI_PACKAGE_VERSION:-^1.2}"
CREEM_AGENT_PACKAGE_VERSION="${CREEM_AGENT_PACKAGE_VERSION:-^1.2.13}"
AGENT_CONFIG_TEMPLATE="${ROOT_DIR}/stubs/creem-agent.php"
BOOTSTRAP_APP_TEMPLATE="${ROOT_DIR}/stubs/bootstrap-app.php"
VIEW_CONFIG_TEMPLATE="${ROOT_DIR}/stubs/view.php"
LOCAL_AGENT_REPO="${CREEM_AGENT_PACKAGE_REPO:-${ROOT_DIR}/../laravel-creem-agent}"

ensure_app_installed() {
  "${ROOT_DIR}/scripts/install-app.sh"
}

simulator_ready() {
  [ -f "${SIMULATOR_DIR}/composer.json" ] \
    && grep -q '"name": "romansh/laravel-creem-store-simulator"' "${SIMULATOR_DIR}/composer.json" \
    && [ -f "${SIMULATOR_DIR}/docker/frankenphp/Dockerfile" ]
}

ensure_demo_packages_installed() {
  if grep -q '"romansh/laravel-creem-agent"' "${SIMULATOR_DIR}/composer.json" \
    && grep -q '"romansh/laravel-creem-cli"' "${SIMULATOR_DIR}/composer.json" \
    && grep -q '"romansh/laravel-creem"' "${SIMULATOR_DIR}/composer.json"; then
    return 0
  fi

  composer require \
    --working-dir "${SIMULATOR_DIR}" \
    --no-interaction \
    --prefer-dist \
    "romansh/laravel-creem:${CREEM_PACKAGE_VERSION}" \
    "romansh/laravel-creem-cli:${CREEM_CLI_PACKAGE_VERSION}" \
    "romansh/laravel-creem-agent:${CREEM_AGENT_PACKAGE_VERSION}"
}

upsert_env_value() {
  local file="$1"
  local key="$2"
  local value="$3"
  local tmp_file

  tmp_file="$(mktemp)"

  awk -v key="$key" -v value="$value" -F= '
    BEGIN { updated = 0 }
    $1 == key { print key "=" value; updated = 1; next }
    { print }
    END {
      if (!updated) {
        print key "=" value
      }
    }
  ' "$file" > "$tmp_file"

  mv "$tmp_file" "$file"
}

sync_env_value() {
  local key="$1"
  local value

  if [ ! -f "${APP_ENV_FILE}" ]; then
    return 0
  fi

  value="$(grep -E "^${key}=" "${APP_ENV_FILE}" | tail -n1 | cut -d= -f2- || true)"
  if [ -z "$value" ]; then
    return 0
  fi

  upsert_env_value "${SIMULATOR_DIR}/.env" "$key" "$value"
}

sync_demo_runtime_files() {
  local migration_src="${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/database/migrations/2025_01_01_000001_create_creem_agent_logs_table.php"
  local migration_dst="${SIMULATOR_DIR}/database/migrations/2025_01_01_000001_create_creem_agent_logs_table.php"

  test -f "${SIMULATOR_DIR}/.env" || cp "${SIMULATOR_DIR}/.env.example" "${SIMULATOR_DIR}/.env"
  mkdir -p \
    "${SIMULATOR_DIR}/bootstrap/cache" \
    "${SIMULATOR_DIR}/storage/framework/cache" \
    "${SIMULATOR_DIR}/storage/framework/sessions" \
    "${SIMULATOR_DIR}/storage/framework/testing" \
    "${SIMULATOR_DIR}/storage/framework/views" \
    "${SIMULATOR_DIR}/storage/logs"

  if [ -f "${AGENT_CONFIG_TEMPLATE}" ]; then
    cp "${AGENT_CONFIG_TEMPLATE}" "${SIMULATOR_DIR}/config/creem-agent.php"
  fi

  if [ -f "${BOOTSTRAP_APP_TEMPLATE}" ]; then
    cp "${BOOTSTRAP_APP_TEMPLATE}" "${SIMULATOR_DIR}/bootstrap/app.php"
  fi

  if [ -f "${VIEW_CONFIG_TEMPLATE}" ]; then
    cp "${VIEW_CONFIG_TEMPLATE}" "${SIMULATOR_DIR}/config/view.php"
  fi

  if [ -f "$migration_src" ]; then
    cp "$migration_src" "$migration_dst"
  fi

  sync_env_value CREEM_TEST_MODE
  sync_env_value APP_KEY
  sync_env_value CREEM_API_KEY
  sync_env_value CREEM_WEBHOOK_SECRET
  sync_env_value CREEM_API_URL
  sync_env_value CREEM_TEST_API_URL
  sync_env_value CREEM_SIMULATOR_DEFAULT_API_KEY
  sync_env_value CREEM_SIMULATOR_DEFAULT_WEBHOOK_SECRET
  sync_env_value CREEM_SECONDARY_API_KEY
  sync_env_value CREEM_SECONDARY_WEBHOOK_SECRET
  sync_env_value CREEM_SECONDARY_API_URL
  sync_env_value CREEM_SIMULATOR_SECONDARY_API_KEY
  sync_env_value CREEM_SIMULATOR_SECONDARY_WEBHOOK_SECRET
  sync_env_value CREEM_SIMULATOR_AGENT_WEBHOOK_URL
  sync_env_value CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET
  sync_env_value CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS
  sync_env_value CREEM_AGENT_DEFAULT_STORE
  sync_env_value CREEM_AGENT_TELEGRAM_MODE
  sync_env_value CREEM_AGENT_TELEGRAM_TOKEN
  sync_env_value CREEM_AGENT_TELEGRAM_CHAT_ID
  sync_env_value CREEM_AGENT_SLACK_WEBHOOK
  sync_env_value CREEM_AGENT_DISCORD_WEBHOOK
  sync_env_value CREEM_AGENT_OPENCLAW
  sync_env_value OPENCLAW_ENDPOINT
  sync_env_value OPENCLAW_WORKSPACE_PATH
  sync_env_value OPENCLAW_SHARED_SECRET
  sync_env_value CREEM_AGENT_LLM_ENABLED
  sync_env_value CREEM_AGENT_LLM_PROVIDER
  sync_env_value CREEM_AGENT_LLM_MODEL
  sync_env_value CREEM_AGENT_LLM_TIMEOUT
  sync_env_value CREEM_AGENT_LLM_FALLBACK_TO_RULES
  sync_env_value OPENAI_API_KEY
  sync_env_value OPENAI_URL
  sync_env_value ANTHROPIC_API_KEY
  sync_env_value GEMINI_API_KEY
  sync_env_value OPENROUTER_API_KEY
  sync_env_value OLLAMA_API_KEY
  sync_env_value OLLAMA_BASE_URL

  if [ -d "${LOCAL_AGENT_REPO}/src" ] && [ -d "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src" ]; then
    mkdir -p \
      "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Agent" \
      "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Heartbeat" \
      "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Listeners" \
      "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications" \
      "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Support"

    cp "${LOCAL_AGENT_REPO}/src/CreemAgentServiceProvider.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/CreemAgentServiceProvider.php"
    cp "${LOCAL_AGENT_REPO}/src/Agent/AgentManager.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Agent/AgentManager.php"
    cp "${LOCAL_AGENT_REPO}/src/Agent/CommandParser.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Agent/CommandParser.php"
    cp "${LOCAL_AGENT_REPO}/src/Agent/IntentClassifierAgent.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Agent/IntentClassifierAgent.php"
    cp "${LOCAL_AGENT_REPO}/src/Heartbeat/ChangeDetector.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Heartbeat/ChangeDetector.php"
    cp "${LOCAL_AGENT_REPO}/src/Heartbeat/Reporter.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Heartbeat/Reporter.php"
    cp "${LOCAL_AGENT_REPO}/src/Listeners/CreemWebhookTelegramNotifier.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Listeners/CreemWebhookTelegramNotifier.php"
    cp "${LOCAL_AGENT_REPO}/src/Notifications/FirstHeartbeat.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications/FirstHeartbeat.php"
    cp "${LOCAL_AGENT_REPO}/src/Notifications/HeartbeatAlert.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications/HeartbeatAlert.php"
    cp "${LOCAL_AGENT_REPO}/src/Notifications/HeartbeatSummary.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Notifications/HeartbeatSummary.php"
    cp "${LOCAL_AGENT_REPO}/src/Support/TelegramMessageSender.php" "${SIMULATOR_DIR}/vendor/romansh/laravel-creem-agent/src/Support/TelegramMessageSender.php"
  fi
}

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required to install ${SIMULATOR_PACKAGE}" >&2
  exit 1
fi

ensure_app_installed

if simulator_ready; then
  ensure_demo_packages_installed
  sync_demo_runtime_files
  echo "Simulator already installed at ${SIMULATOR_DIR}"
  echo "Demo packages and heartbeat config are synchronized"
  exit 0
fi

if [ -e "${SIMULATOR_DIR}" ] && [ -n "$(find "${SIMULATOR_DIR}" -mindepth 1 -maxdepth 1 2>/dev/null)" ]; then
  echo "${SIMULATOR_DIR} already exists and is not a generated laravel-creem-store-simulator install." >&2
  echo "Remove it first or move it aside, then rerun this script." >&2
  exit 1
fi

rm -rf "${SIMULATOR_DIR}"

composer create-project \
  --no-interaction \
  --prefer-dist \
  "${SIMULATOR_PACKAGE}" \
  "${SIMULATOR_DIR}" \
  "${SIMULATOR_VERSION}"

ensure_demo_packages_installed
sync_demo_runtime_files

echo "Installed ${SIMULATOR_PACKAGE} into ${SIMULATOR_DIR}"
echo "Installed laravel-creem, laravel-creem-cli, and laravel-creem-agent into the simulator"