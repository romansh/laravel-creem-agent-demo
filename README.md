
# Laravel Creem Agent Demo

This demo package for `laravel-creem-agent` contains configuration, prompts, and scenarios to run and test agent workflows locally.

## Repository contents
- `docker/` — Dockerfile and related artifacts (frankenphp, etc.).
- `docker-compose.frankenphp.yml` — example Docker Compose file for running the demo service.
 - `docs/` — step-by-step scenarios and guides (SCENARIO_*.md, GUIDE.md, etc.).

## Requirements
- Docker and Docker Compose (recommended) for quick containerized setup.
- Locally: PHP 8.1+, Composer if you prefer to run parts of the demo natively.

## Quick start (Docker)
1. Change into the demo directory:

```bash
cd laravel-creem-agent-demo
```
2. Build and start the demo services with Docker Compose:

```bash
docker compose -f docker-compose.frankenphp.yml up --build
```

After the containers start, check container logs and the `docs/` directory for scenario-specific instructions.

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
- `docs/SCENARIO_*.md` — scenario walkthroughs used to demonstrate agent capabilities.

Use these files as input for manual testing or when wiring the demo into a test environment.



## Useful commands
- Build and start containers: `docker compose -f docker-compose.frankenphp.yml up --build`
- Stop containers: `docker compose -f docker-compose.frankenphp.yml down`
- Follow logs: `docker compose -f docker-compose.frankenphp.yml logs -f`

## Troubleshooting & tips
 - If the agent is unresponsive, check container logs and environment variable configuration.
 - For repeatable tests, use the scenario files in `docs/`.

## Contributing
Contributions are welcome. Please open a pull request describing your changes and include a short reproduction or test scenario.

---
For authoring and support, see the main `laravel-creem-agent` repository for full documentation and requirements.
