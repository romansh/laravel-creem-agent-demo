# Scenario 2 Short: Pure Agent

## Tokens first

- Creem API key: Creem Dashboard -> API Keys
- Creem webhook secret: Creem Dashboard -> Webhooks
- Telegram bot token: Telegram -> `@BotFather` -> `/newbot`
- Telegram chat id: `curl -s "https://api.telegram.org/bot<TOKEN>/getUpdates" | jq`
- ngrok authtoken: ngrok dashboard

## Preferred runtime: Docker + FrankenPHP

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
 docker compose -f docker-compose.frankenphp.yml build app queue scheduler simulator
  # Note: added `git config` command to avoid Git `safe.directory` errors when running Composer inside the container
 docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
  git config --global --add safe.directory /app && composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  mkdir -p database && touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
# Note: added `git config` command to avoid Git `safe.directory` errors when running Composer inside the container
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

If you also run the simulator, configure these variables in `./.env` (recommended) and/or in `creem-simulator/.env` before starting `simulator`:

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
# The public URL can be read from the ngrok container logs; the inspector is not published by default.
```

Creem webhook URL:

```text
https://YOUR-NGROK-URL/creem/webhook
```

The same base URL is used for the Telegram webhook:

After you get `YOUR-NGROK-URL`, use it for every public callback:

- Creem webhook: `https://YOUR-NGROK-URL/creem/webhook`
- Telegram webhook: `https://YOUR-NGROK-URL/creem-agent/telegram/webhook`
- Simulator callback, if you want simulator to call the public app URL

The same `.env` file can be loaded into your shell with `set -o allexport; source .env; set +o allexport` before running compose if you prefer not to pass `--env-file` explicitly.

Telegram webhook URL:

```bash
curl -s -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://YOUR-NGROK-URL/creem-agent/telegram/webhook"
```

## Verify

```bash
curl -s http://127.0.0.1:8000/creem-agent/status | jq
curl -s -X POST http://127.0.0.1:8000/creem-agent/chat -H 'Content-Type: application/json' -d '{"message":"status"}' | jq
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem:test-webhook payment.failed
```

## Fallback if Docker is unavailable

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan creem-agent:install
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --queue=creem-agent
php artisan schedule:work
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
