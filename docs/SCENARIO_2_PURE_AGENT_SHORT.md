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

Important: the current compose file uses the generated `creem-simulator` app as the shared Laravel runtime. The demo installer now adds `laravel-creem-agent` into that app, so heartbeat is available directly through `bash scripts/run-heartbeat.sh`.

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

Run heartbeat directly in the demo stack:

```bash
bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
```

Optional maintainer-only package verification:

```bash
CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

Copy `.env.example` to `.env` before initialization and do a one-time setup of the values you plan to use. Put Telegram settings, secondary-store credentials, and `NGROK_AUTHTOKEN` into that same file, even if you will start the `ngrok` container only later when public webhook delivery is needed.

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

If you also run the simulator, configure these variables in `./.env` (recommended) and/or in `creem-simulator/.env` after running `bash scripts/install-simulator.sh`:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

Docker Compose maps the `./.env` values into `simulator` when the service starts.

## Start stack

```bash
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler simulator
```

## Start ngrok later, if needed

Only do this when you need public webhook delivery from Creem or Telegram:

```bash
docker compose --env-file .env -f docker-compose.frankenphp.yml up -d ngrok
NGROK_URL="$(docker compose -f docker-compose.frankenphp.yml logs --no-color ngrok 2>&1 | grep -Eo 'https://[A-Za-z0-9.-]+\.ngrok(-free)?\.app' | tail -n 1)"
test -n "$NGROK_URL" || { echo "ngrok URL not found; check NGROK_AUTHTOKEN in .env and ngrok logs"; false; }
echo "$NGROK_URL"
```

Creem webhook URL:

```text
https://YOUR-NGROK-URL/creem/webhook
```

The same base URL is used for the Telegram webhook:

After you get `YOUR-NGROK-URL`, use it for every public callback:

- Creem webhook: `https://YOUR-NGROK-URL/creem/webhook`
- Telegram webhook: part of the intended target architecture; today end users should rely on the smoke runner, while maintainers may add package tests from a separate `laravel-creem-agent` checkout
- Simulator callback, if you want simulator to call the public app URL

The same `.env` file can be loaded into your shell with `set -o allexport; source .env; set +o allexport` before running compose if you prefer not to pass `--env-file` explicitly. If `NGROK_URL` is empty or logs show `ERR_NGROK_105`, fix `NGROK_AUTHTOKEN` in `.env` first.

Telegram webhook URL:

```bash
Before running the command below, load the demo `.env` into your shell so the token variable is available, or export the token manually:

```bash
# Load variables from the demo .env (if present)
set -o allexport; test -f .env && source .env || true; set +o allexport

# Or set the token manually:
# export CREEM_AGENT_TELEGRAM_TOKEN=YOUR_BOT_TOKEN
```

```bash
curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/setWebhook" \
  -d "url=https://YOUR-NGROK-URL/creem-agent/telegram/webhook"
```

## Verify simulator-first approach

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
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
docker compose -f docker-compose.frankenphp.yml exec simulator \
  php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
docker compose -f docker-compose.frankenphp.yml exec simulator \
  php artisan simulator:send-webhook payment.failed
```

## Move to real Creem after simulator-first passes

Once the smoke runner and package tests pass, continue with the real-Creem sections in `.resources/FLOW.md` and `.resources/SCENARIO_2_PURE_AGENT_FULL.md`.

## Fallback if Docker is unavailable

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

## Guest Additions & Shared Folder (optional)

To make the host repo available inside the VM at `/home/www`, add a VirtualBox shared folder (e.g. name `repo_run` pointing to `/home/roman/php/run`) and mount it in the guest:

```bash
# inside guest
sudo apt update
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils
sudo mkdir -p /home/www
sudo usermod -aG vboxsf $USER
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /home/www
```

Then create per-package subfolders under `/home/www` and install/clone packages there.

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
