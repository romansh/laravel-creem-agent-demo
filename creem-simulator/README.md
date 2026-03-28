# Creem Simulator

Laravel application that simulates the subset of the Creem API used by:

- `romansh/laravel-creem`
- `romansh/laravel-creem-cli`
- `romansh/laravel-creem-agent`

The goal is deterministic local testing for agent demos and heartbeat logic.

## Capabilities

- Creem-like HTTP endpoints under `/api/v1`
- search endpoints such as `/subscriptions/search` and `/transactions/search`
- multi-store resolution by `x-api-key`
- SQLite-backed products, customers, subscriptions, transactions, and checkouts
- demo seeders and scenario commands
- optional signed webhook delivery into an agent app
- FrankenPHP-friendly runtime

List responses mirror Creem pagination and include `items`, `pagination`, and a compatibility `total` alias.

## Quick start

```bash
cd /home/roman/php/run/laravel-creem-agent-demo/creem-simulator
cp .env.example .env
sqlite_db="${DB_DATABASE:-database/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db"
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=80  # simulator runs on container port 80; publish to host only if needed
```

Point your agent app to the simulator:

```env
CREEM_TEST_MODE=true
CREEM_API_KEY=creem_test_demo_default
CREEM_WEBHOOK_SECRET=whsec_demo_default
CREEM_TEST_API_URL=http://simulator:80/api/v1  # internal Docker network URL
CREEM_API_URL=http://simulator:80/api/v1
```

## Main commands

- `php artisan simulator:seed-demo` — create a realistic baseline dataset
- `php artisan simulator:advance` — append fresh activity for the next heartbeat cycle
- `php artisan simulator:send-webhook payment.failed` — push a signed webhook to the configured agent app

## Docker

The parent demo repository includes a dedicated FrankenPHP `simulator` service.

See `../docs/SIMULATOR.md` for the full Docker and integration workflow.
