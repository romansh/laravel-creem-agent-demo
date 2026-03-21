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

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
  composer install &&
  test -f .env || cp .env.example .env &&
  mkdir -p database &&
  touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  (php artisan octane:install --server=frankenphp --no-interaction || true)
'
docker compose -f docker-compose.frankenphp.yml up -d simulator
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