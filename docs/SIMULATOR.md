# Creem Simulator

## Purpose

`creem-simulator` is a dedicated Laravel application that mimics the subset of the Creem API used by:

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

## Start simulator in Docker

Before starting the simulator, fill `creem-simulator/.env` if you want automatic webhook delivery.
Do this after `ngrok` is running if you need a public URL, or use the internal Docker URL if the agent app is on the same network.

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

If the simulator must call the public app URL instead of the internal Docker hostname, use:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=https://YOUR-NGROK-URL/creem/webhook
```

If `ngrok` is already running and you want the full demo stack from that point, use:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose -f docker-compose.frankenphp.yml build app queue scheduler simulator
 # Примечание: добавлена команда git config, чтобы избежать ошибок Git safe.directory при запуске Composer в контейнере
 docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  mkdir -p database && touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
# Примечание: добавлена команда git config, чтобы избежать ошибок Git safe.directory при запуске Composer в контейнере
docker compose -f docker-compose.frankenphp.yml run --rm app sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  mkdir -p database && touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan vendor:publish --tag=creem-config &&
  php artisan vendor:publish --tag=creem-cli-config &&
  php artisan vendor:publish --tag=creem-agent-config &&
  php artisan vendor:publish --tag=creem-agent-migrations &&
  php artisan migrate &&
  php artisan octane:install --server=frankenphp --no-interaction
'
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler simulator
```

If you only need the simulator itself:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  mkdir -p database &&
  touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
docker compose -f docker-compose.frankenphp.yml up -d simulator

Octane server note

If your demo uses FrankenPHP + Octane, add `OCTANE_SERVER=frankenphp` to the app `.env` (and to `creem-simulator/.env` when the simulator runs Octane) before running `php artisan octane:install`:

```bash
# add to app .env
printf "\nOCTANE_SERVER=frankenphp\n" >> .env
# if simulator needs it
printf "\nOCTANE_SERVER=frankenphp\n" >> creem-simulator/.env
```
```

## Point agent app to simulator

In the Laravel app that hosts `laravel-creem-agent`:

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
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

This works entirely on the internal Docker network.

## Main workflow

```bash
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:seed-demo --reset
php artisan creem-agent:heartbeat
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:advance --sales=3 --new-customers=2 --past-due=1 --cancellations=1
php artisan creem-agent:heartbeat
```

Run the heartbeat command in whichever Laravel application hosts `laravel-creem-agent`. That host app can run locally, in another container, or in a future dedicated demo app.

## Manual webhook test

```bash
docker compose -f docker-compose.frankenphp.yml exec simulator php artisan simulator:send-webhook payment.failed
```