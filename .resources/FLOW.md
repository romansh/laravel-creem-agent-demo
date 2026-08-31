# Demo Flow

Video recording runbook. Each phase builds on the previous.
Simulator runs on port 8000 via Docker. Heartbeat in the checked-in demo now runs through `bash scripts/run-heartbeat.sh`, which auto-detects simulator vs real-store envs.

This flow uses the new SQLite approach:

- local runs can keep `.env.example` as-is with `DB_DATABASE=database/database.sqlite`
- Docker Compose overrides `DB_DATABASE` inside containers and mounts a writable named volume
- the bootstrap commands below create the SQLite file from `DB_DATABASE`, so the setup stays copy-paste friendly

---

## Credentials

Collect before starting:

| What | Where |
|------|-------|
| Creem API key `creem_test_...` | Creem Dashboard → API Keys |
| Creem webhook secret `whsec_...` | Creem Dashboard → Webhooks |
| Telegram bot token | @BotFather → /newbot |
| Telegram chat ID | `curl -s "https://api.telegram.org/bot<TOKEN>/getUpdates" \| jq '.result[0].message.chat.id'` |
| ngrok authtoken | ngrok.com → Dashboard → Auth |

For the simulator-only phases, no real Creem keys needed — defaults (`creem_test_demo_default` / `whsec_demo_default`) work out of the box.

---

## One-time setup

### Environment

```bash
cd /home/www/laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
bash scripts/install-simulator.sh
# For simulator phases, the default demo Creem credentials already work.
# Edit only Telegram/ngrok values if you need Phase 4.
```

### Build and init host app + simulator

```bash
docker compose build
docker compose run --rm app sh -lc '
  git config --global --add safe.directory /app &&
  composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/agent-app/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate --force &&
  php artisan octane:install --server=frankenphp --no-interaction
'
docker compose run --rm simulator sh -lc '
  git config --global --add safe.directory /app &&
  composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/simulator/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
```

Notes:

- `bash scripts/install-app.sh` creates the dedicated host Laravel runtime in `app/`
- `bash scripts/install-simulator.sh` creates the optional Creem simulator runtime in `simulator/`
- when you use `docker-compose.yml`, the container uses `/var/lib/simulator/database.sqlite`
- you do not need to edit `DB_DATABASE` in `.env` for Docker unless you want a different path
- for local non-Docker runs, `database/database.sqlite` remains the default

### Start stack

```bash
docker compose up -d
```

Verify simulator is running:

```bash
curl -s http://localhost:8000/ | jq .
```

---

## Phase 1: Simulator API (2 min)

Seed demo data and explore the API via curl.

### Seed

```bash
docker compose exec simulator \
  php artisan simulator:seed-demo --reset
```

### Products

```bash
curl -s http://localhost:8000/api/v1/products/search \
  -H 'x-api-key: creem_test_demo_default' | jq '.items[:2]'
```

### Customers

```bash
curl -s http://localhost:8000/api/v1/customers/list \
  -H 'x-api-key: creem_test_demo_default' | jq '{total: .total, first: .items[0]}'
```

### Subscriptions

```bash
curl -s 'http://localhost:8000/api/v1/subscriptions?status=active' \
  -H 'x-api-key: creem_test_demo_default' | jq '{total: .total, first: .items[0]}'
```

### Transactions

```bash
curl -s http://localhost:8000/api/v1/transactions/search \
  -H 'x-api-key: creem_test_demo_default' | jq '{total: .total, first: .items[0]}'
```

---

## Phase 2: Heartbeat — first run (2 min)

Run the agent heartbeat against simulator data. First run creates a baseline snapshot.

```bash
cd /home/www/laravel-creem-agent-demo
bash scripts/run-heartbeat.sh --all-stores
```

Expected output:

```
[CreemAgent] Heartbeat for store: default
  First run — initial snapshot created
  Customers: 40
  Active subscriptions: 18
  Trialing: 3
  Past due: 2
  Transactions: 120
```

---

## Phase 3: Advance + detect changes (2 min)

Add new activity in the simulator, then run heartbeat again to show change detection.

### Advance

```bash
cd /home/www/laravel-creem-agent-demo
docker compose exec simulator \
  php artisan simulator:advance \
    --sales=3 --new-customers=2 --past-due=2 --cancellations=1
```

### Verify via curl

```bash
curl -s 'http://localhost:8000/api/v1/subscriptions?status=past_due' \
  -H 'x-api-key: creem_test_demo_default' | jq .total
```

### Heartbeat — detect changes

```bash
cd /home/www/laravel-creem-agent-demo
bash scripts/run-heartbeat.sh --force --all-stores
```

Expected output — changes detected:

```
[CreemAgent] Heartbeat for store: default
  5 change(s) detected:
    ✅ 3 new transactions
    ✅ 2 new customers
    ⚠️ 2 subscriptions moved to past_due
    🚨 1 subscription canceled
    ℹ️ Revenue change: +$87.00
```

---

## Phase 4: Telegram — simulator-backed (3 min)

Connect real Telegram to the agent. The agent queries simulator data and replies via Telegram.

### Start agent HTTP server

Prerequisite: complete the one-time setup first so `app/` and `simulator/` both exist and have been bootstrapped.

```bash
cd /home/www/laravel-creem-agent-demo
docker compose up -d app queue scheduler simulator
```

Verify:

```bash
curl -s http://localhost:8000/creem-agent/status | jq .
```

### Start ngrok tunnel

```bash
cd /home/www/laravel-creem-agent-demo
docker compose --env-file app/.env up -d ngrok
sleep 3
NGROK_URL="$(docker compose logs --no-color ngrok 2>&1 | grep -Eo 'https://[A-Za-z0-9.-]+\.ngrok(-free)?\.app' | tail -n 1)"
test -n "$NGROK_URL" || { echo "ngrok URL not found; check NGROK_AUTHTOKEN in .env and ngrok logs"; false; }
echo "$NGROK_URL"
```

### Register Telegram webhook

Before running the `curl` command below, load the demo `.env` into your shell so `CREEM_AGENT_TELEGRAM_TOKEN` is available, or export the token manually.

```bash
# Load variables from the demo .env (if present)
set -o allexport; test -f app/.env && source app/.env || true; set +o allexport

# Or set the token manually:
# export CREEM_AGENT_TELEGRAM_TOKEN=YOUR_BOT_TOKEN
```

```bash
curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/setWebhook" \
  -d "url=${NGROK_URL}/creem-agent/telegram/webhook"
```

### Send Telegram messages

Open Telegram, send messages to the bot:

```
status
recent transactions
any payment issues?
```

Each message hits the agent → agent queries simulator API → replies in Telegram.

### Test heartbeat with Telegram notifications

```bash
cd /home/www/laravel-creem-agent-demo
docker compose exec simulator \
  php artisan simulator:advance --sales=2 --past-due=1 --send-webhooks

cd /home/www/laravel-creem-agent-demo
bash scripts/run-heartbeat.sh --force --all-stores
```

Check Telegram — the heartbeat notification arrives showing the new changes.

### Cleanup

```bash
# Ensure token is available before deleting webhook
set -o allexport; test -f app/.env && source app/.env || true; set +o allexport

curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/deleteWebhook"
cd /home/www/laravel-creem-agent-demo
docker compose stop ngrok
```

Keep `app`, `queue`, `scheduler`, and `simulator` running for the next webhook step.

---

## Phase 4B: OpenClaw Telegram gateway overlay (contest path, 3 min)

Use this phase when the demo must show native OpenClaw Telegram ownership instead of the Laravel webhook path from Phase 4.

Important:

- complete Phases 1-4 first so simulator, heartbeat, and agent HTTP are already green
- this path assumes a Debian or Ubuntu VM or VPS with `sudo` available
- Telegram belongs to OpenClaw in this mode, so do not set a Laravel webhook or rely on `CREEM_AGENT_TELEGRAM_CHAT_ID`

### Install OpenClaw

```bash
sudo apt update && sudo apt install -y ansible git
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
sudo su - openclaw
openclaw onboard --install-daemon
openclaw status
```

### Switch the demo to OpenClaw Telegram ownership

```bash
cd /home/www/laravel-creem-agent-demo
cat >> app/.env <<'EOF'
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw
OPENCLAW_TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/openclaw/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
EOF

bash scripts/install-simulator.sh
docker compose up -d app queue scheduler simulator
```

### Render the Telegram channel config and pair the bot

```bash
cd /home/www/laravel-creem-agent-demo
docker compose exec app php artisan creem-agent:openclaw-telegram-config

openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

### Verify the OpenClaw path

```bash
cd /home/www/laravel-creem-agent-demo
curl -s http://127.0.0.1:8000/creem-agent/status | jq .
bash scripts/run-heartbeat.sh --force --all-stores
docker compose exec simulator php artisan simulator:advance --sales=2 --past-due=1 --send-webhooks
```

Expected result:

- Telegram messages arrive through OpenClaw gateway, not through `/creem-agent/telegram/webhook`
- Laravel remains the execution surface for heartbeat, webhooks, and store state
- OpenClaw acts as the Telegram/runtime overlay required by the contest scenario

### OpenClaw: trigger heartbeat securely

When OpenClaw should trigger the heartbeat scheduler, use the dedicated endpoint exposed by the agent. This endpoint requires the shared secret `OPENCLAW_SHARED_SECRET` (set in the VM's `.env`). Example:

```bash
curl -s -X POST http://127.0.0.1:8000/creem-agent/openclaw/heartbeat \
  -H "X-OpenClaw-Shared-Secret: ${OPENCLAW_SHARED_SECRET}"
```

Use this endpoint from the OpenClaw gateway or a scheduled job inside the OpenClaw VM. Do not invoke other heartbeat endpoints directly from OpenClaw — use this route only.

If `sudo` is unavailable, skip this phase and stay on the Laravel-owned Telegram path from Phase 4.

---

## Phase 5: Webhooks (1 min)

Show simulator sending signed webhooks.

```bash
cd /home/www/laravel-creem-agent-demo

docker compose exec simulator \
  php artisan simulator:send-webhook subscription.canceled

docker compose exec simulator \
  php artisan simulator:send-webhook payment.failed
```

Each command shows the signed webhook payload and delivery result.

### Cleanup after webhook step

```bash
cd /home/www/laravel-creem-agent-demo
docker compose stop app queue scheduler simulator
```

---

## Phase 6: Tests (1 min)

Run the automated test suites.

### Simulator tests

```bash
cd /home/www/laravel-creem-agent-demo/simulator
php artisan test
```

If `app/` or `simulator/` is missing because you are in a fresh checkout, run `cd /home/www/laravel-creem-agent-demo && bash scripts/install-app.sh && bash scripts/install-simulator.sh` first.

If you want to run the same suite inside the Dockerized simulator:

```bash
cd /home/www/laravel-creem-agent-demo
docker compose exec simulator php artisan test
```

### Agent package tests

```bash
CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

### Smoke suite

```bash
cd /home/www/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

---

## Phase 7: Real Creem (3 min)

Switch from simulator to real Creem API.

### Update app env

```bash
cd /home/www/laravel-creem-agent-demo
cat > app/.env <<'EOF'
CACHE_DRIVER=file
DB_CONNECTION=sqlite
CREEM_TEST_MODE=true
CREEM_API_KEY=creem_test_YOUR_REAL_KEY
CREEM_WEBHOOK_SECRET=whsec_YOUR_REAL_SECRET
CREEM_API_URL=https://api.creem.io/v1
CREEM_AGENT_DEFAULT_STORE=default
CREEM_AGENT_TELEGRAM_MODE=laravel
CREEM_AGENT_TELEGRAM_TOKEN=YOUR_BOT_TOKEN
CREEM_AGENT_TELEGRAM_CHAT_ID=YOUR_CHAT_ID
CREEM_AGENT_LLM_ENABLED=false
EOF
```

### Heartbeat — real data

```bash
bash scripts/run-heartbeat.sh --force --all-stores
```

Shows real products, customers, subscriptions, transactions from Creem.

### Chat — real data

```bash
docker compose exec app php artisan creem-agent:chat "show me subscription status"
docker compose exec app php artisan creem-agent:chat "any failed payments?"
```

### Telegram — real data

Same as Phase 4 but with real Creem data:

```bash
cd /home/www/laravel-creem-agent-demo
docker compose up -d app queue scheduler
docker compose --env-file app/.env up -d ngrok
sleep 2
NGROK_URL="$(docker compose logs --no-color ngrok 2>&1 | grep -Eo 'https://[A-Za-z0-9.-]+\.ngrok(-free)?\.app' | tail -n 1)"
test -n "$NGROK_URL" || { echo "ngrok URL not found; check NGROK_AUTHTOKEN in .env and ngrok logs"; false; }

curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/setWebhook" \
  -d "url=${NGROK_URL}/creem-agent/telegram/webhook"
```

Send messages in Telegram — now responses contain live Creem store data.

### Register webhook in Creem Dashboard

1. Creem Dashboard → Webhooks → Create endpoint
2. URL: `${NGROK_URL}/creem/webhook`
3. Events: `checkout.completed`, `subscription.paid`, `subscription.canceled`, `payment.failed`
4. Copy webhook secret → update `CREEM_WEBHOOK_SECRET` in `.env`

### Cleanup

```bash
curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/deleteWebhook"
cd /home/www/laravel-creem-agent-demo
docker compose stop ngrok app queue scheduler
```

---

## Quick reference

### Simulator commands

```bash
EXEC="docker compose exec simulator"

$EXEC php artisan simulator:seed-demo --reset
$EXEC php artisan simulator:advance --sales=3 --new-customers=2 --past-due=1 --cancellations=1 --send-webhooks
$EXEC php artisan simulator:send-webhook payment.failed
$EXEC php artisan simulator:send-webhook subscription.canceled
```

### Agent commands (via demo stack)

```bash
cd /home/www/laravel-creem-agent-demo

bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
docker compose exec app php artisan creem-agent:chat "status"
docker compose exec app php artisan creem-agent:chat "recent transactions"
docker compose exec app php artisan creem-agent:status
```

### Curl — simulator API

```bash
API=http://localhost:8000/api/v1
KEY=creem_test_demo_default

curl -s "$API/products/search" -H "x-api-key: $KEY" | jq .
curl -s "$API/customers/list" -H "x-api-key: $KEY" | jq .
curl -s "$API/subscriptions?status=active" -H "x-api-key: $KEY" | jq .
curl -s "$API/transactions/search" -H "x-api-key: $KEY" | jq .
```

### Agent HTTP endpoints (from the demo stack)

```bash
curl -s http://localhost:8000/creem-agent/status | jq .
curl -s -X POST http://localhost:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"status"}' | jq .
```
