# Laravel Creem Agent Demo

This repository now uses a clear two-runtime layout:

- `app/` — generated Laravel host application that runs `laravel-creem`, `laravel-creem-cli`, and `laravel-creem-agent`
- `simulator/` — generated Laravel Creem API simulator used only when you want deterministic local testing

The demo app is expected to work without the simulator. If `app/.env` points to a real Creem API, only the `app`, `queue`, and `scheduler` services are required.

## Repository contents

- `app/.env.example` — tracked template for the host app environment
- `docker-compose.yml` — Docker Compose topology for `app`, `queue`, `scheduler`, optional `simulator`, and optional `ngrok`
- `scripts/install-app.sh` — creates or refreshes the generated Laravel host app in `./app`
- `scripts/install-simulator.sh` — creates or refreshes the generated simulator in `./simulator`
- `docs/` — user-facing setup guides and short scenarios
- `.resources/` — internal runbooks and recording flow documents

## Requirements

- Docker and Docker Compose for the supported runtime path
- PHP 8.2+ and Composer if you want to generate runtimes locally before using Docker
- `NGROK_AUTHTOKEN` only when you need a public tunnel

## Environment layout

The source of truth for runtime configuration is now `app/.env`.

Initialize it once:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
cp app/.env.example app/.env
```

Fill in the values you need there:

- Creem API credentials
- Telegram settings
- optional OpenClaw settings
- optional `NGROK_AUTHTOKEN` and `NGROK_URL`
- LLM provider keys

`scripts/install-simulator.sh` syncs the simulator-facing Creem and LLM settings from `app/.env` into `simulator/.env` when the simulator is installed.

## Quick start

### App only

Use this when you want the host app to work without the simulator:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
docker compose build app queue scheduler
docker compose up -d app queue scheduler
```

### App + simulator

Use this when you want deterministic local data and internal webhook delivery:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
bash scripts/install-simulator.sh
docker compose build app queue scheduler simulator
docker compose up -d app queue scheduler simulator
bash scripts/smoke-simulator-demo.sh
```

Run heartbeat directly in the host app:

```bash
bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
```

## ngrok

When you need public webhook delivery, keep the token and URL in `app/.env` and start `ngrok` with:

```bash
docker compose --env-file app/.env up -d ngrok
```

Or load the same env into your shell:

```bash
set -o allexport; source app/.env; set +o allexport
docker compose up -d ngrok
```

Use the resulting public base URL for:

- `https://YOUR-NGROK-URL/creem/webhook`
- `https://YOUR-NGROK-URL/creem-agent/telegram/webhook`

## Useful commands

- Build services: `docker compose build app queue scheduler simulator`
- Start app only: `docker compose up -d app queue scheduler`
- Start full stack: `docker compose up -d app queue scheduler simulator`
- Follow logs: `docker compose logs -f app`
- Run heartbeat: `bash scripts/run-heartbeat.sh --all-stores`
- Run simulator smoke flow: `bash scripts/smoke-simulator-demo.sh`

## Notes

- The host Laravel runtime lives in `app/`, not in the repository root.
- The simulator is optional and isolated in `simulator/`.
- `docker-compose.yml` mounts separate SQLite volumes for the app and the simulator.
- The scheduler cadence is still controlled by Laravel scheduling code, not by `.env`.

## Scenarios

See the short scenario files in `docs/`:

- `docs/SCENARIO_1_OPENCLAW_SHORT.md`
- `docs/SCENARIO_2_PURE_AGENT_SHORT.md`
- `docs/SIMULATOR.md`

For authoring and support, see the main `laravel-creem-agent` repository.
