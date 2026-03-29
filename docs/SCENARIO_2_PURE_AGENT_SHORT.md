# Scenario 2 Short: Pure Agent

Use this as the default execution order:

1. simulator-first verification
2. optional public webhook exposure with ngrok
3. real Creem only after the simulator-first path is green

## Tokens first

- Creem API key: Creem Dashboard -> API Keys
- Creem webhook secret: Creem Dashboard -> Webhooks
- Telegram bot token: Telegram -> `@BotFather` -> `/newbot`
- Telegram chat id: `curl -s "https://api.telegram.org/bot<TOKEN>/getUpdates" | jq`
- ngrok authtoken: ngrok dashboard

## Preferred runtime: Docker + FrankenPHP

Important: the current compose file runs the dedicated host runtime from `app/`, and `simulator/` remains optional. Heartbeat is available directly through `bash scripts/run-heartbeat.sh`.

```bash
cd $HOME/php/run/laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
bash scripts/install-simulator.sh
 docker compose build app queue scheduler simulator
  # Note: added `git config` command to avoid Git `safe.directory` errors when running Composer inside the container
 docker compose run --rm app sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/agent-app/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
  php artisan key:generate &&
  php artisan migrate --force &&
  php artisan octane:install --server=frankenphp --no-interaction
'
 docker compose run --rm simulator sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
bash scripts/smoke-simulator-demo.sh
```

Run heartbeat directly in the demo stack:

```bash
bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
```

Optional maintainer-only package verification:

```bash
CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

Copy `app/.env.example` to `app/.env` before initialization and do a one-time setup of the values you plan to use. Put Telegram settings, secondary-store credentials, and `NGROK_AUTHTOKEN` into that same file, even if you will start the `ngrok` container only later when public webhook delivery is needed.

## `.env`

```env
APP_URL=http://127.0.0.1:8000
CREEM_API_KEY=creem_test_...
CREEM_WEBHOOK_SECRET=whsec_...
CREEM_TEST_MODE=true
CREEM_AGENT_OPENCLAW=false
CREEM_AGENT_TELEGRAM_MODE=laravel
CREEM_AGENT_DEFAULT_STORE=default
CREEM_SECONDARY_API_KEY=creem_test_...
CREEM_SECONDARY_WEBHOOK_SECRET=whsec_...
CREEM_SECONDARY_API_URL=http://simulator/api/v1
CREEM_AGENT_TELEGRAM_TOKEN=...
CREEM_AGENT_TELEGRAM_CHAT_ID=...
```

If you also run the simulator, configure these variables in `app/.env`. `bash scripts/install-simulator.sh` will sync them into `simulator/.env`:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

Docker Compose reads the host runtime from `app/.env` and the simulator runtime from `simulator/.env`.

## Start stack

```bash
docker compose up -d app queue scheduler simulator
```

## Start ngrok later, if needed

Only do this when you need public webhook delivery from Creem or Telegram. The recommended approach is to provision the public callback URL as part of your configuration and put it into `.env` as `NGROK_URL`. This lets webhook setup commands reference a stable URL instead of attempting to parse container logs at runtime.

```bash
# Start ngrok container (it still needs the auth token in .env)
docker compose --env-file app/.env up -d ngrok

# Load .env and validate required values
set -o allexport; test -f app/.env && source app/.env || true; set +o allexport
test -n "${NGROK_URL:-}" || { echo "NGROK_URL not set in app/.env; set NGROK_URL=https://your-id.ngrok.app"; false; }
echo "Using public URL: $NGROK_URL"
```

Creem webhook URL (use the `NGROK_URL` value from your `.env`):

```text
${NGROK_URL}/creem/webhook
```

The same base URL is used for the Telegram webhook and for simulator callbacks when you want the simulator to call the public app URL. Load `.env` in your shell before running the webhook commands so both `CREEM_AGENT_TELEGRAM_TOKEN` and `NGROK_URL` are available.

Telegram webhook URL:

```bash
Before running the command below, load `app/.env` into your shell so the token and `NGROK_URL` variables are available, or export them manually. Example:

```bash
# Load variables from the demo app env (if present)
set -o allexport; test -f app/.env && source app/.env || true; set +o allexport

# Fail fast if required vars are missing
test -n "${CREEM_AGENT_TELEGRAM_TOKEN:-}" || { echo "CREEM_AGENT_TELEGRAM_TOKEN not set in app/.env"; false; }
test -n "${NGROK_URL:-}" || { echo "NGROK_URL not set in app/.env; set NGROK_URL=https://your-id.ngrok.app"; false; }

curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/setWebhook" \
  -d "url=${NGROK_URL}/creem-agent/telegram/webhook"
```

## Verify simulator-first approach

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Optional maintainer-only package verification:

```bash
CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

## Optional: interactive Telegram on top of simulator

For a real Telegram conversation against simulator-backed data, you can use the same app service or a dedicated Laravel host app with:

```php
Schedule::command('creem-agent:heartbeat --all-stores')
    ->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();
```

This cadence is configured in scheduler code, not in `.env`.

Then point that agent host to `http://simulator/api/v1`, register the Telegram webhook, and use messages such as:

```text
status
recent transactions
any payment issues?
run heartbeat
```

Before asking those questions, create fresh simulator changes:

```bash
docker compose exec simulator \
  php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
docker compose exec simulator \
  php artisan simulator:send-webhook payment.failed
```

## Move to real Creem after simulator-first passes

Once the smoke runner and package tests pass, continue with the real-Creem sections in `FLOW.md` and `.resources/SCENARIO_2_PURE_AGENT_FULL.md`.

## Fallback if Docker is unavailable

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

## Guest Additions & Shared Folder (optional)

If you need a VM, prefer mounting or cloning only this demo repository into the guest (for example at `/home/www/laravel-creem-agent-demo`). Mounting the entire host parent folder is not required and is discouraged.

Example (host):

```bash
VBoxManage sharedfolder add "VM-name" --name demo_repo --hostpath /path/to/laravel-creem-agent-demo --automount
```

Example (guest):

```bash
sudo mkdir -p /home/www/laravel-creem-agent-demo
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) demo_repo /home/www/laravel-creem-agent-demo
```

If you are a maintainer and need multiple sibling package checkouts for tests, clone those repositories inside the guest (for example under `/home/www`) and point `CREEM_AGENT_PACKAGE_REPO` at the local path when running smoke scripts.

If these packages are private, use SSH agent forwarding so the key remains on the host:

```bash
# On host:
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_rsa
ssh -A user@vm_ip

# Inside VM:
ssh git@github.com
```

This method requires SSH login to the VM (`ssh -A`) and won't work through the VirtualBox GUI.

Make the mount permanent (optional) — append a line to `/etc/fstab` using `tee` (replace `1000`/`1000` with your `id -u`/`id -g`):

```bash
echo 'repo_run /home/www vboxsf uid=1000,gid=1000,auto 0 0' | sudo tee -a /etc/fstab
```

Test without reboot:

```bash
sudo mount -a
mount | grep vboxsf
ls -la /home/www
lsmod | grep vboxsf || sudo modprobe vboxsf
```
```
