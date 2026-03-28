# Creem Simulator

This document describes Approach 1: simulator-first. Use it before any live Creem or OpenClaw demo because it is the only fully deterministic path in the current repository layout.

## Purpose

`creem-simulator` is a generated working directory created by `bash scripts/install-simulator.sh`. It installs the dedicated Laravel application from `romansh/laravel-creem-store-simulator`, which mimics the subset of the Creem API used by:

- `romansh/laravel-creem`
- `romansh/laravel-creem-cli`
- `romansh/laravel-creem-agent`

It exists to make agent demos deterministic: no waiting for real payments, no external ngrok requirement for every flow, and no dependency on a live Creem dashboard while developing heartbeat logic.

The simulator mirrors the Creem list endpoints used by the SDK, including `subscriptions/search`, `transactions/search`, and the `pagination.total_records` response format.

## What it simulates

- products
- customers
- subscriptions and status transitions
- transactions history
- checkout creation
- signed webhook delivery to an agent app

## Why it helps heartbeat testing

The agent heartbeat compares:

- latest transaction id
- total customers
- subscription counts by status
- subscription lifecycle changes

The simulator persists all of this in SQLite, so you can:

1. seed a realistic baseline
2. run the first heartbeat to create the agent snapshot
3. advance the simulator state
4. run heartbeat again and observe meaningful changes

For demo recordings, you can also connect a dedicated Laravel host app to the simulator and talk to that agent through Telegram while all business data still comes from simulator responses.

## Start simulator in Docker

Before starting the simulator, run `bash scripts/install-simulator.sh` once, then configure `CREEM_SIMULATOR_*` values in the root `.env` (preferred) for a unified environment. You can also set them in `creem-simulator/.env` as simulator-local overrides.
If you use `ngrok`, set `CREEM_SIMULATOR_AGENT_WEBHOOK_URL` after ngrok is running; otherwise use the internal Docker URL when agent and simulator are on the same network.

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

Simulator `.env` handling
- The simulator files are installed on demand via `bash scripts/install-simulator.sh` into `./creem-simulator`.
- For local demo and CI runs you do not need to manually create or edit `creem-simulator/.env`. The provided scripts and automation copy `creem-simulator/.env.example` to `creem-simulator/.env` when `.env` is missing and run `php artisan key:generate --force` inside the simulator container so the app has a valid `APP_KEY` for demo use.
- For production or persistent deployments, supply `APP_KEY`, webhook URL/secret, and API keys explicitly via environment variables, Docker/Kubernetes secrets, or your secret manager.


If the simulator must call the public app URL instead of the internal Docker hostname, use:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=https://YOUR-NGROK-URL/creem/webhook
```

If `ngrok` is already running and you want the currently supported demo flow from that point, use:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/install-simulator.sh
docker compose -f docker-compose.frankenphp.yml build app queue scheduler simulator
 # Note: added `git config` command to avoid Git `safe.directory` errors when running Composer inside the container
 docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/creem-simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
bash scripts/smoke-simulator-demo.sh
```

Important: the current compose file does not provide a dedicated Laravel agent app container. `app`, `queue`, and `scheduler` point at `creem-simulator`, so agent-specific `php artisan creem-agent:*` commands should be verified through the standalone Telegram smoke step in `bash scripts/smoke-simulator-demo.sh` and the package tests in `../laravel-creem-agent`, not through `docker compose exec app`.

If you only need the simulator itself:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/install-simulator.sh
docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/creem-simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
docker compose -f docker-compose.frankenphp.yml up -d simulator

Octane server note

If your demo uses FrankenPHP + Octane, add `OCTANE_SERVER=frankenphp` to the app `.env` (and to `creem-simulator/.env` after installation, when the simulator runs Octane) before running `php artisan octane:install`:

```bash
# add to app .env
printf "\nOCTANE_SERVER=frankenphp\n" >> .env
# if simulator needs it
printf "\nOCTANE_SERVER=frankenphp\n" >> creem-simulator/.env
```
```

## Point a separate agent host to simulator

In a separate Laravel app that hosts `laravel-creem-agent`:

```env
CREEM_TEST_MODE=true
CREEM_API_KEY=creem_test_demo_default
CREEM_WEBHOOK_SECRET=whsec_demo_default
CREEM_TEST_API_URL=http://simulator/api/v1
CREEM_API_URL=http://simulator/api/v1
```

If both apps run inside the same Docker Compose network, `http://simulator/api/v1` avoids public tunnels entirely.

## Internal webhook delivery

To let the simulator push webhooks directly into the agent app container:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://agent-app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

This works entirely on the internal Docker network.

## Main workflow

```bash
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:seed-demo --reset
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:send-webhook payment.failed
bash scripts/smoke-simulator-demo.sh
```

Run heartbeat and Telegram verification in whichever Laravel application hosts `laravel-creem-agent`. In this repository today, webhook simulation and standalone Telegram outbound-send smoke live in `bash scripts/smoke-simulator-demo.sh`, while package-level Telegram send/receive coverage lives in `../laravel-creem-agent` tests.

## Demo heartbeat cadence

For simulator demos, do not look for an `.env` variable to make heartbeat run every minute. In the current setup, the cadence is controlled by the Laravel scheduler definition, not by `.env`.

Use this in the Laravel host app that runs `laravel-creem-agent`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('creem-agent:heartbeat --all-stores')
  ->everyMinute()
  ->runInBackground()
  ->withoutOverlapping();
```

The demo `scheduler` container already executes `php artisan schedule:run` every 60 seconds. Changing the scheduled command to `everyMinute()` is what removes the four-hour wait during demos.

## Simulator-backed Telegram loop

If you want to send real Telegram messages and receive simulator-backed answers, connect a dedicated Laravel host app to the simulator API:

```env
CREEM_TEST_MODE=true
CREEM_TEST_API_URL=http://simulator/api/v1
CREEM_API_URL=http://simulator/api/v1
CREEM_AGENT_TELEGRAM_MODE=laravel
CREEM_AGENT_TELEGRAM_TOKEN=YOUR_TELEGRAM_BOT_TOKEN
CREEM_AGENT_TELEGRAM_CHAT_ID=YOUR_TELEGRAM_CHAT_ID
```

Then register the Telegram webhook to that agent host and use messages such as:

```text
status
recent transactions
any payment issues?
run heartbeat
```

After running `simulator:advance` with new sales and `past_due` changes, those Telegram questions will reflect simulator-backed data.

Once this flow is green, move to the real-Creem runbooks for public webhook delivery and live store data.

## Manual webhook test

```bash
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:send-webhook payment.failed
```