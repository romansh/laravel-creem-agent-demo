
# Laravel Creem Agent Demo

This demo package for `laravel-creem-agent` contains configuration, prompts, and scenarios to run and test agent workflows locally.

## Repository contents
- `docker/` — Dockerfile and related artifacts (frankenphp, etc.).
- `docker-compose.frankenphp.yml` — example Docker Compose file for running the demo services, including optional `ngrok` tunneling.
- `scripts/install-simulator.sh` — installs `romansh/laravel-creem-store-simulator` into `./creem-simulator` when the simulator app is needed.
- `docs/` — user-facing setup guides and short scenarios.
- `.resources/` — internal recording runbooks, full demo scenarios, and planning materials.

## Requirements
- Docker and Docker Compose (recommended) for quick containerized setup.
- Locally: PHP 8.1+, Composer if you prefer to run parts of the demo natively.
- `NGROK_AUTHTOKEN` if you want to receive real Creem or Telegram webhooks through the bundled `ngrok` container.

Note about Octane server

If you use FrankenPHP / Laravel Octane in the demo, set the Octane server in the app `.env`:

```bash
# in the demo project root
printf "\nOCTANE_SERVER=frankenphp\n" >> .env
```

This ensures `php artisan octane:install --server=frankenphp` and runtime expectations match the demo containers.

## Quick start (Docker)
Before you start: copy `.env.example` to `.env` and do a one-time setup of the values you plan to use, including API keys, Telegram settings, secondary-store credentials, and `NGROK_AUTHTOKEN`. The `ngrok` container itself can be started later when public webhook delivery is needed, but its token should live in the same `.env` alongside the rest of the configuration.

Recommended demo order:

1. run `bash scripts/smoke-simulator-demo.sh` to bootstrap the simulator-backed stack and verify the demo path end-to-end
2. run `bash scripts/run-heartbeat.sh --all-stores` to create the baseline heartbeat directly from the checked-in demo stack
3. add new simulator sales and failures with `simulator:advance` and `simulator:send-webhook payment.failed`
4. run `bash scripts/run-heartbeat.sh --force --all-stores` so the heartbeat shows the delta without waiting for the next cadence window

For demo cadence, do not look for a dedicated `.env` switch. Use `everyMinute()` in the Laravel scheduler definition; the Docker `scheduler` loop already runs `schedule:run` every 60 seconds.

If you run multiple Creem stores (multi-store demo), set the secondary store env vars (`CREEM_SECONDARY_API_KEY`, `CREEM_SECONDARY_WEBHOOK_SECRET`, `CREEM_SECONDARY_API_URL`) in that same `.env` setup step, then verify that the published `config/creem.php` maps them to the `secondary` profile.

If you are starting from the point where `ngrok` is already up, continue with these commands:

```bash
cd laravel-creem-agent-demo
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
	docker compose -f docker-compose.frankenphp.yml run --rm app sh -lc '
	git config --global --add safe.directory /app && composer install --no-interaction &&
	test -f .env || cp .env.example .env &&
	sqlite_db="${DB_DATABASE:-/var/lib/creem-simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
	php artisan key:generate &&
	php artisan vendor:publish --tag=creem-config --force &&
	php artisan vendor:publish --tag=creem-cli-config --force &&
	php artisan vendor:publish --tag=creem-agent-config --force &&
	php artisan vendor:publish --tag=creem-agent-migrations --force &&
	php artisan migrate &&
	php artisan octane:install --server=frankenphp --no-interaction
'
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler simulator
bash scripts/smoke-simulator-demo.sh
```

After that, read the current public tunnel URL from the `ngrok` inspector and use it for Creem or Telegram webhooks.

The checked-in demo now installs `romansh/laravel-creem`, `romansh/laravel-creem-cli`, and `romansh/laravel-creem-agent` into the generated `creem-simulator` app. Heartbeat is available directly in the demo stack through `bash scripts/run-heartbeat.sh` or `docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat`. The wrapper auto-detects whether the current env points at `http://simulator/...` or at a real Creem API URL, so it works for both simulator-backed demos and real-store/OpenClaw setups.

## Public webhook tunnel with Docker

When you need a public HTTPS URL for real Creem or Telegram webhooks, run the bundled `ngrok` service instead of installing `ngrok` on the host:

```bash
docker compose --env-file .env -f docker-compose.frankenphp.yml up -d ngrok
# Read the public URL from the ngrok container logs; the inspector is not published by default.
```

Alternatively, load `.env` into your shell for the current session (bash/zsh):

```bash
set -o allexport; source .env; set +o allexport
docker compose -f docker-compose.frankenphp.yml up -d ngrok
```

Use the returned HTTPS URL as the base for:

- `https://YOUR-NGROK-URL/creem/webhook`
- `https://YOUR-NGROK-URL/creem-agent/telegram/webhook`

Once `ngrok` is already running, initialize and start the rest of the stack with the commands from the quick start block above.

## Publish package configs

The installer already syncs the demo-specific `creem-agent` configuration into `creem-simulator/config/creem-agent.php` and copies the package migration used for agent logs. If you need to re-run the package setup manually inside the app container, use:

```bash
php artisan vendor:publish --provider="Romansh\LaravelCreemAgent\CreemAgentServiceProvider" --tag=creem-agent-migrations
php artisan migrate
```

Example `config/creem.php` snippet you should verify (profile → api_key mapping):

```php
'profiles' => [
	'default' => [
		'api_key' => env('CREEM_API_KEY'),
		'test_mode' => env('CREEM_TEST_MODE', true),
	],
],
```

And in your `.env` set the API key used by that profile:

```env
CREEM_API_KEY=creem_test_...
```

## Running locally (without Docker)
1. If you want to run parts of the demo on your machine:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Note: Several demo scripts assume the frankenphp container environment; local execution may require additional configuration.

## Scenarios and prompts
Review the scenario documents:
- `docs/SCENARIO_*.md` — user-facing short scenario walkthroughs.

Use these files as input for manual testing or when wiring the demo into a test environment.

If you want deterministic local testing without waiting for real payments or public webhooks, install the simulator with `bash scripts/install-simulator.sh` and then follow `docs/SIMULATOR.md`.

If you want a direct demo heartbeat command, use:

```bash
bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
```

If you want simulator-backed Telegram interaction with scheduled heartbeats, use:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('creem-agent:heartbeat --all-stores')
	->everyMinute()
	->runInBackground()
	->withoutOverlapping();
```

That interactive Telegram stage is separate from the checked-in demo compose topology.



## Useful commands
- Build and start containers: `docker compose -f docker-compose.frankenphp.yml up --build`
- Stop containers: `docker compose -f docker-compose.frankenphp.yml down`
- Follow logs: `docker compose -f docker-compose.frankenphp.yml logs -f`
- Start bundled ngrok: `docker compose --env-file .env -f docker-compose.frankenphp.yml up -d ngrok`
<!-- ngrok web UI is not published by default in this configuration -->

The compose file uses normal Docker `ports:` publishing. Start the stack with:

```bash
docker compose -f docker-compose.frankenphp.yml up -d --build
```

## Troubleshooting & tips
 - If the agent is unresponsive, check container logs and environment variable configuration.
 - For repeatable tests, use the scenario files in `docs/`.

## Contributing
Contributions are welcome. Please open a pull request describing your changes and include a short reproduction or test scenario.

---
For authoring and support, see the main `laravel-creem-agent` repository for full documentation and requirements.
