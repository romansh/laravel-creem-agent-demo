# Scenario 1 Short: OpenClaw + Docker + FrankenPHP

Use this as an overlay on top of the two-approach model:

1. complete the simulator-first checks
2. attach OpenClaw
3. only then move to real Creem callbacks if needed

## Tokens first

- Creem API key: Creem Dashboard -> API Keys
- Creem webhook secret: Creem Dashboard -> Webhooks
- Telegram bot token: Telegram -> `@BotFather` -> `/newbot`
- ngrok authtoken: ngrok dashboard

## VirtualBox on Ubuntu host

```bash
sudo apt update
sudo apt install -y virtualbox
virtualbox
```

VirtualBox UI:

- New -> Ubuntu Server 24.04 LTS
- 2 CPU, 4 GB RAM, 30 GB disk
- Network -> NAT
- Port Forwarding -> `2222->22`, `8000->80` (forwarding `5173` or `4040` is optional and only needed for local host access to Vite or the ngrok inspector)

## OpenClaw

```bash
sudo apt update && sudo apt install -y ansible git
git clone https://github.com/openclaw/openclaw-ansible.git
cd openclaw-ansible
ansible-galaxy collection install -r requirements.yml
./run-playbook.sh
sudo su - openclaw
openclaw onboard --install-daemon
openclaw status
```

## App init

Important: the current compose file runs the dedicated host runtime from `app/`, and `simulator/` remains optional. Heartbeat is available directly through `bash scripts/run-heartbeat.sh`.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
	bash scripts/install-simulator.sh
	docker compose build app queue scheduler simulator
	# Note: added `git config` command to avoid Git `safe.directory` errors when running Composer inside the container
	docker compose run --rm simulator sh -lc '
	git config --global --add safe.directory /app && composer install --no-interaction &&
	test -f .env || cp .env.example .env &&
	sqlite_db="${DB_DATABASE:-/var/lib/simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
	php artisan key:generate &&
	php artisan migrate --seed &&
	php artisan octane:install --server=frankenphp --no-interaction
'
bash scripts/smoke-simulator-demo.sh

bash scripts/run-heartbeat.sh --all-stores

CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

Copy `app/.env.example` to `app/.env` before initialization and do a one-time setup of the values you plan to use. Put OpenClaw options, secondary-store credentials, and `NGROK_AUTHTOKEN` into that same file, even if you will start the `ngrok` container only later when public webhook delivery is needed.

## `.env`

```env
APP_URL=http://127.0.0.1:8000
CREEM_API_KEY=creem_test_...
CREEM_WEBHOOK_SECRET=whsec_...
CREEM_TEST_MODE=true
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw
CREEM_AGENT_DEFAULT_STORE=default
CREEM_SECONDARY_API_KEY=creem_test_...
CREEM_SECONDARY_WEBHOOK_SECRET=whsec_...
CREEM_SECONDARY_API_URL=http://simulator/api/v1
OPENCLAW_TELEGRAM_BOT_TOKEN=...
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/openclaw/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
```

If you also run the simulator, configure these variables in `app/.env`. `bash scripts/install-simulator.sh` will sync them into `simulator/.env`:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

Docker Compose reads the host runtime from `app/.env` and the simulator runtime from `simulator/.env`.

## OpenClaw Telegram channel

```bash
cd /path/to/your-agent-host-app
php artisan creem-agent:openclaw-telegram-config
openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

## Start stack

```bash
docker compose up -d app queue scheduler simulator
```

## Start ngrok later, if needed

Only do this when you need public webhook delivery from Creem or Telegram. We expect the public callback URL to be configured up-front in `.env` as `NGROK_URL` so webhook commands can reference it directly.

```bash
# Start ngrok container (it still needs the auth token in .env)
docker compose --env-file app/.env up -d ngrok

# Load .env and fail fast if NGROK_URL is not configured
set -o allexport; test -f app/.env && source app/.env || true; set +o allexport
test -n "${NGROK_URL:-}" || { echo "NGROK_URL not set in app/.env; set NGROK_URL=https://your-id.ngrok.app"; false; }
echo "Using public URL: $NGROK_URL"
```

Creem webhook URL (use the `NGROK_URL` value from your `.env`):

```text
${NGROK_URL}/creem/webhook
```

If `NGROK_URL` is empty or the ngrok logs show `ERR_NGROK_105`, set a valid `NGROK_AUTHTOKEN` and `NGROK_URL` in `app/.env` and re-run the steps above.

After you get `YOUR-NGROK-URL`, use it anywhere a public callback is required, especially the Creem webhook endpoint.

The same `app/.env` file can be loaded into your shell before running compose if you prefer not to pass `--env-file` explicitly.

## Verify simulator-first approach

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh

CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
openclaw gateway
openclaw pairing list telegram
```

## Optional: simulator-backed Telegram plus OpenClaw cadence

For demo mode, set heartbeat cadence in scheduler code, not in `.env`:

```php
Schedule::command('creem-agent:heartbeat --all-stores')
		->everyMinute()
		->runInBackground()
		->withoutOverlapping();
```

Before asking for status or payment issues through Telegram or OpenClaw, create fresh simulator changes:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose exec simulator \
	php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
docker compose exec simulator \
	php artisan simulator:send-webhook payment.failed
bash scripts/smoke-simulator-demo.sh
```

Then the dedicated simulator-backed agent host can answer messages such as `status`, `recent transactions`, `any payment issues?`, and `run heartbeat`.

## Move to real Creem after simulator-first passes

After the commands above are green, continue with the real-Creem sections in `FLOW.md` and `.resources/SCENARIO_1_OPENCLAW_FULL.md`.

## Guest Additions & Shared Folder (optional)

If you want the host repo available inside the VM at `/home/www`, add a VirtualBox shared folder named `repo_run` (pointing to host `/home/roman/php/run`) and mount it in the guest:

```bash
# inside guest
sudo apt update
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils
sudo mkdir -p /home/www
sudo usermod -aG vboxsf $USER
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /home/www
```

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

Create subfolders under `/home/www` for each package (e.g. `laravel-creem`, `laravel-creem-cli`, `laravel-creem-agent`) and operate on them from the guest.

If these packages are private, use SSH agent forwarding so your private key stays on the host:

```bash
# On host:
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_rsa
ssh -A user@vm_ip

# Inside VM:
ssh git@github.com
```

This requires SSH login to the VM (`ssh -A`), it does not work via the VirtualBox GUI.
