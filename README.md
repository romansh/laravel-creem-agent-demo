
# Laravel Creem Agent Demo

This demo package for `laravel-creem-agent` contains configuration, prompts, and scenarios to run and test agent workflows locally.

## Repository contents
- `docker/` — Dockerfile and related artifacts (frankenphp, etc.).
- `docker-compose.frankenphp.yml` — example Docker Compose file for running the demo services, including optional `ngrok` tunneling.
- `creem-simulator/` — standalone Laravel app that simulates the Creem API with SQLite-backed demo history.
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
If you are starting from the point where `ngrok` is already up, continue with these commands:

```bash
cd laravel-creem-agent-demo
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
	docker compose -f docker-compose.frankenphp.yml run --rm app sh -lc '
	git config --global --add safe.directory /app && composer install --no-interaction &&
	test -f .env || cp .env.example .env &&
	mkdir -p database && touch database/database.sqlite &&
	php artisan key:generate &&
	php artisan vendor:publish --tag=creem-config --force &&
	php artisan vendor:publish --tag=creem-cli-config --force &&
	php artisan vendor:publish --tag=creem-agent-config --force &&
	php artisan vendor:publish --tag=creem-agent-migrations --force &&
	php artisan migrate &&
	php artisan octane:install --server=frankenphp --no-interaction
'
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler simulator
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat
```

After that, read the current public tunnel URL from the `ngrok` inspector and use it for Creem or Telegram webhooks.

## Public webhook tunnel with Docker

When you need a public HTTPS URL for real Creem or Telegram webhooks, run the bundled `ngrok` service instead of installing `ngrok` on the host:

```bash
export NGROK_AUTHTOKEN=YOUR_NGROK_AUTHTOKEN
docker compose -f docker-compose.frankenphp.yml up -d ngrok
curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[] | select(.proto == "https") | .public_url'
```

If you prefer to keep the token in your application `.env` file instead of exporting it every time, add the line `NGROK_AUTHTOKEN=your_token` to your `.env` (do not commit this file). Then start the tunnel with Docker Compose loading that file:

```bash
docker compose --env-file .env -f docker-compose.frankenphp.yml up -d ngrok
curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[] | select(.proto == "https") | .public_url'
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

Before running `php artisan creem-agent:install` you should publish the package configuration files and migrations into the demo app. This ensures `config/creem.php`, `config/creem-cli.php` and `config/creem-agent.php` are present and editable.

Run these commands inside the app container or from the app project root:

```bash
php artisan vendor:publish --provider="Romansh\LaravelCreem\CreemServiceProvider" --tag=creem-config
php artisan vendor:publish --provider="Romansh\LaravelCreemCli\CreemCliServiceProvider" --tag=creem-cli-config
php artisan vendor:publish --provider="Romansh\LaravelCreemAgent\CreemAgentServiceProvider" --tag=creem-agent-config
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

If you want deterministic local testing without waiting for real payments or public webhooks, start with `creem-simulator/` and `docs/SIMULATOR.md`.



## Useful commands
- Build and start containers: `docker compose -f docker-compose.frankenphp.yml up --build`
- Stop containers: `docker compose -f docker-compose.frankenphp.yml down`
- Follow logs: `docker compose -f docker-compose.frankenphp.yml logs -f`
- Start bundled ngrok: `export NGROK_AUTHTOKEN=... && docker compose -f docker-compose.frankenphp.yml up -d ngrok`
- Read current public URL: `curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[] | select(.proto == "https") | .public_url'`

## Troubleshooting & tips
 - If the agent is unresponsive, check container logs and environment variable configuration.
 - For repeatable tests, use the scenario files in `docs/`.

## Contributing
Contributions are welcome. Please open a pull request describing your changes and include a short reproduction or test scenario.

---
For authoring and support, see the main `laravel-creem-agent` repository for full documentation and requirements.
