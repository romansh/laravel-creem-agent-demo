# Installation Guide

## Prerequisites

- PHP 8.2+ with extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath
- Composer 2.x
- Node.js 18+ and npm
- A Creem.io account ([sign up](https://creem.io/dashboard))
- Optional: Slack/Telegram/Discord for notifications
- Optional: `creem` CLI via Homebrew

## Installation Modes

This project supports two practical installation modes:

Full step-by-step runbooks:

- `docs/SCENARIO_1_OPENCLAW_FULL.md` — privileged install with OpenClaw, ngrok, Telegram, and full webhook flow
- `docs/SCENARIO_2_PURE_AGENT_FULL.md` — current-user Laravel agent only, without OpenClaw
- `docs/SCENARIO_1_OPENCLAW_SHORT.md` — short command sequence for OpenClaw scenario
- `docs/SCENARIO_2_PURE_AGENT_SHORT.md` — short command sequence for pure agent scenario

Preferred runtime for the Laravel app:

- `Docker + FrankenPHP + Octane` via `docker-compose.frankenphp.yml`
- `php artisan serve` is fallback-only for restricted environments

### Mode A: Full OpenClaw + Laravel agent

- Uses `https://github.com/openclaw/openclaw-ansible`
- Requires Debian/Ubuntu and `sudo` or root access
- Recommended for a dedicated VPS or VM

### Mode B: Current-user standard agent

- Does not require `sudo`
- Runs only the Laravel-side agent from the current user
- Recommended for development, quick demos, CI, and restricted shells

## Optional VM Mode: VirtualBox

If you want a clean, reproducible environment that resembles a real VPS, run the whole stack inside VirtualBox.

### Install VirtualBox on the host

On Ubuntu host:

```bash
sudo apt update
sudo apt install -y virtualbox
virtualbox
```

### Create the VM in the VirtualBox UI

1. Click `New`
2. Name the VM, for example `creem-agent-demo-vm`
3. Select `Linux` and `Ubuntu (64-bit)`
4. Assign `4096 MB` RAM minimum
5. Assign `2` CPU cores
6. Create `30 GB` dynamically allocated disk
7. Attach Ubuntu Server ISO
8. Finish and boot the VM

### Configure networking in the VirtualBox UI

1. Open `Settings` for the VM
2. Open `Network`
3. Adapter 1 -> Enable -> `NAT`
4. Adapter 1 -> `Advanced` -> `Port Forwarding`
5. Add:

- `ssh`: host `2222` -> guest `22`
- `http-app`: host `8000` -> guest `80`
- `vite`: host `5173` -> guest `5173`
- `ngrok-inspector`: host `4040` -> guest `4040`

Optional:

1. Adapter 2 -> Enable -> `Host-Only Adapter`
2. Use it when you want direct host-to-guest traffic outside NAT rules

Recommended VM settings:

- Guest OS: Ubuntu Server 24.04 LTS
- CPU: 2 vCPU
- Memory: 4 GB minimum
- Disk: 30 GB dynamically allocated
- Adapter 1: NAT
- Adapter 2: Host-Only Adapter (optional)

Recommended NAT port forwards:

- Host `2222` → Guest `22`
- Host `8000` → Guest `8000`
- Host `5173` → Guest `5173`
- Host `4040` → Guest `4040`

Why these forwards:

- `2222` lets you SSH into the VM
- `8000` exposes Laravel locally to the host
- `5173` exposes the Vite dev server if needed
- `4040` exposes ngrok inspector on the host

Minimal guest bootstrap:

```bash
sudo apt update
sudo apt install -y git curl unzip zip php-cli php-mbstring php-xml php-curl php-sqlite3 composer
```

If the VM user has no `sudo`, skip privileged setup and use the current-user standard agent mode.

## Preferred Runtime: Docker + FrankenPHP

This repository includes:

- `docker/frankenphp/Dockerfile`
- `docker-compose.frankenphp.yml`

One-time initialization:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose -f docker-compose.frankenphp.yml run --rm app sh -lc '
  composer install &&
  test -f .env || cp .env.example .env &&
  mkdir -p database && touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan vendor:publish --tag=creem-config --force &&
  php artisan vendor:publish --tag=creem-cli-config --force &&
  php artisan vendor:publish --tag=creem-agent-config --force &&
  php artisan vendor:publish --tag=creem-agent-migrations --force &&
  php artisan migrate &&
  (php artisan octane:install --server=frankenphp --no-interaction || true)
'
```

Start runtime services:

```bash
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler
```

Use `php artisan serve` only if Docker is unavailable.

## Step 1: Create Laravel App

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
composer create-project laravel/laravel . --prefer-dist
```

Or if already a Laravel project, skip this step.

## Step 2: Install Packages

### Option A: From Packagist (when published)
```bash
composer require romansh/laravel-creem romansh/laravel-creem-cli romansh/laravel-creem-agent
```

### Option B: Local development (path repositories)
Add to `composer.json`:
```json
{
    "repositories": [
        { "type": "path", "url": "../laravel-creem" },
        { "type": "path", "url": "../laravel-creem-cli" },
        { "type": "path", "url": "../laravel-creem-agent" }
    ]
}
```
Then:
```bash
composer require romansh/laravel-creem:@dev romansh/laravel-creem-cli:@dev romansh/laravel-creem-agent:@dev
```

## Step 3: Publish Configuration & Migrations

```bash
php artisan vendor:publish --tag=creem-config
php artisan vendor:publish --tag=creem-cli-config
php artisan vendor:publish --tag=creem-agent-config
php artisan vendor:publish --tag=creem-agent-migrations
php artisan migrate
```

## Step 4: Configure Environment

Add to your `.env`:

```env
# Creem API (get key from https://creem.io/dashboard/api-keys)
CREEM_API_KEY=creem_test_YOUR_KEY_HERE
CREEM_WEBHOOK_SECRET=whsec_YOUR_SECRET_HERE
CREEM_TEST_MODE=true

# Agent settings
CREEM_AGENT_DEFAULT_STORE=default

# Notifications (at least one recommended)
CREEM_AGENT_SLACK_WEBHOOK=https://hooks.slack.com/services/XXX/YYY/ZZZ
# CREEM_AGENT_TELEGRAM_TOKEN=your_bot_token
# CREEM_AGENT_TELEGRAM_CHAT_ID=your_chat_id
# CREEM_AGENT_DISCORD_WEBHOOK=https://discord.com/api/webhooks/XXX/YYY
```

If you plan to receive real external webhooks via ngrok, also keep note of:

- local app URL inside VM or host: `http://127.0.0.1:8000`
- public webhook URL: `https://YOUR-NGROK-URL/creem/webhook`

## Step 5: Run Setup Wizard

```bash
php artisan creem-agent:install
```

Expected output:
```
🤖 Creem Agent Installation Wizard

Step 1: Verifying API connection...
  ✅ API connection successful
Step 2: Detecting CLI backend...
  ℹ️  Using laravel-creem-cli (Artisan) backend
Step 3: Creating state storage...
  ✅ State directory: /path/to/storage/creem-agent
Step 4: Configured stores:
  • default (heartbeat every 4h)
Step 5: Notification channels:
  ✅ Slack
  ⬜ Telegram
  ⬜ Discord
  ✅ Database (always enabled)

Setup complete! Run your first heartbeat:
  php artisan creem-agent:heartbeat
```

## Step 6: First Heartbeat

```bash
php artisan creem-agent:heartbeat
```

This creates an initial snapshot of your store and reports it to configured channels.

## Step 7: Optional — Install Native Creem CLI

```bash
brew tap armitage-labs/creem
brew install creem
creem login --api-key creem_test_YOUR_KEY_HERE
creem whoami  # Verify
```

The agent automatically detects the CLI and uses it (faster for some operations).

## Step 8: Optional — Install and Connect OpenClaw

OpenClaw is optional. The Laravel agent works without it.

For this project, the official installation path is `https://github.com/openclaw/openclaw-ansible`.

Important upstream requirement:

- `openclaw-ansible` is intended for Debian/Ubuntu hosts
- it expects root or `sudo`/become access
- if `sudo` is unavailable, use the fallback current-user standard agent mode described below

### 8.1 Full OpenClaw installation with sudo

Quick start:

```bash
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
```

Manual install:

```bash
sudo apt update && sudo apt install -y ansible git
git clone https://github.com/openclaw/openclaw-ansible.git
cd openclaw-ansible
ansible-galaxy collection install -r requirements.yml
./run-playbook.sh
```

Development mode:

```bash
git clone https://github.com/openclaw/openclaw-ansible.git
cd openclaw-ansible
./run-playbook.sh -e openclaw_install_mode=development
```

After installation:

```bash
sudo su - openclaw
openclaw onboard --install-daemon
openclaw status
openclaw logs
```

Alternative manual onboarding:

```bash
openclaw configure
openclaw providers login
openclaw gateway
openclaw daemon install
openclaw daemon start
openclaw status
```

### 8.2 Project-side environment

Add these values to `.env` if you want to enable the integration path:

```env
CREEM_AGENT_OPENCLAW=true
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/roman/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
```

### 8.3 What OpenClaw should call

OpenClaw can integrate with the Laravel app using these actions:

```bash
# Agent status
curl -s http://127.0.0.1:8000/creem-agent/status | jq

# Agent chat
curl -s -X POST http://127.0.0.1:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"status"}' | jq

# Proactive heartbeat trigger
php artisan creem-agent:heartbeat --all-stores
```

### 8.4 Suggested OpenClaw workspace heartbeat

Create `~/.openclaw/workspace/HEARTBEAT.md`:

```md
# OpenClaw Workspace Heartbeat

## Tasks
- Every 4 hours: run `php artisan creem-agent:heartbeat --all-stores`
- Every 30 minutes: call `GET /creem-agent/status`
- On human prompt: forward text to `POST /creem-agent/chat`

## Escalation
- If `past_due > 0`, notify immediately
- If 3+ cancellations in one cycle, escalate as churn spike
- If no changes, stay silent
```

### 8.5 Verification

```bash
curl -s http://127.0.0.1:8000/creem-agent/status | jq
curl -s -X POST http://127.0.0.1:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"how many active subscriptions?"}' | jq
php artisan creem-agent:heartbeat --all-stores
```

### 8.6 If sudo is unavailable

If the host does not provide `sudo`, do not try to force `openclaw-ansible`. Upstream expects privilege escalation for firewall, Docker, systemd, and host hardening.

Preferred current-user path if Docker is already available to your user:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose -f docker-compose.frankenphp.yml run --rm app sh -lc '
  composer install &&
  test -f .env || cp .env.example .env &&
  mkdir -p database && touch database/database.sqlite &&
  php artisan key:generate &&
  php artisan vendor:publish --tag=creem-config --force &&
  php artisan vendor:publish --tag=creem-cli-config --force &&
  php artisan vendor:publish --tag=creem-agent-config --force &&
  php artisan vendor:publish --tag=creem-agent-migrations --force &&
  php artisan migrate &&
  (php artisan octane:install --server=frankenphp --no-interaction || true)
'
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler
```

Fallback current-user flow if Docker is unavailable:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan creem-agent:install
php artisan serve
```

In separate terminals, still as the current user:

```bash
php artisan queue:work --queue=creem-agent
php artisan schedule:work
```

This mode is enough to demonstrate:

- package installation
- store connection
- heartbeat execution
- chat commands
- webhook handling
- proactive workflows
- notifications

What you do **not** get in no-sudo mode:

- OpenClaw daemon installation
- hardened OpenClaw host setup
- system service management from `openclaw-ansible`

## Step 9: Optional — Install ngrok and expose webhooks

If Creem needs to call your local app or VirtualBox guest, expose the Laravel port using ngrok.

Typical flow:

```bash
# Authenticate ngrok once
ngrok config add-authtoken YOUR_NGROK_TOKEN

# Tunnel Laravel
ngrok http 8000
```

Take the generated HTTPS URL and configure Creem webhooks to:

```text
https://YOUR-NGROK-URL/creem/webhook
```

Recommended setup combinations:

### Host machine runs ngrok, app runs inside VirtualBox

1. In VirtualBox, forward host `8000` to guest `8000`
2. Inside the guest, run `docker compose -f docker-compose.frankenphp.yml up -d app`
3. On the host, run `ngrok http 8000`
4. Put the ngrok HTTPS URL into Creem webhook settings

### VM runs ngrok directly

1. Inside the guest, run `docker compose -f docker-compose.frankenphp.yml up -d app`
2. Inside the guest, run `ngrok http 8000`
3. Put the ngrok HTTPS URL into Creem webhook settings

Verification:

```bash
curl -i http://127.0.0.1:8000/creem/webhook
php artisan creem:test-webhook checkout.completed
```

Note: `GET /creem/webhook` may return `405 Method Not Allowed`, which is fine. The important part is that the app is reachable and `POST` requests can arrive.

## Step 10: Scheduling (Production)

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('creem-agent:heartbeat --all-stores')
    ->everyFourHours()
    ->runInBackground()
    ->withoutOverlapping();
```

Then run:
```bash
php artisan schedule:work
```

For production, use cron:
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Step 11: Test the Chat

```bash
php artisan creem-agent:chat "status"
php artisan creem-agent:chat "how many active subscriptions?"
php artisan creem-agent:chat "any payment issues?"
php artisan creem-agent:chat "help"
```

## Step 12: Multi-Store Setup (Optional)

Edit `config/creem-agent.php`:

```php
'stores' => [
    'default' => [
        'profile' => 'default',
        'heartbeat_frequency' => 4,
        'notifications' => ['slack', 'database'],
    ],
    'secondary' => [
        'profile' => 'secondary',
        'heartbeat_frequency' => 8,
        'notifications' => ['telegram', 'database'],
    ],
],
```

Add the secondary profile to `config/creem.php`:

```php
'profiles' => [
    'default' => [
        'api_key' => env('CREEM_API_KEY'),
        'test_mode' => env('CREEM_TEST_MODE', false),
        'webhook_secret' => env('CREEM_WEBHOOK_SECRET'),
    ],
    'secondary' => [
        'api_key' => env('CREEM_SECONDARY_API_KEY'),
        'test_mode' => true,
        'webhook_secret' => env('CREEM_SECONDARY_WEBHOOK_SECRET'),
    ],
],
```

## Troubleshooting

### API Connection Failed
- Verify `CREEM_API_KEY` is set correctly in `.env`
- Check if using test mode: `CREEM_TEST_MODE=true`
- Try: `php artisan creem:whoami`

### No Notifications
- Check channel configuration in `.env`
- Run `php artisan creem-agent:heartbeat --force` to trigger a report
- Check `storage/logs/laravel.log` for errors

### CLI Not Detected
- Run `which creem` to verify installation
- Run `creem whoami` to verify authentication
- Agent will auto-fallback to SDK — no action needed

### OpenClaw Not Reaching the Agent
- Verify the Laravel app is reachable at `http://127.0.0.1:8000`
- Check `GET /creem-agent/status` manually with `curl`
- Check `POST /creem-agent/chat` manually with `curl`
- If OpenClaw runs on another host, expose the app via Nginx/Caddy and update `APP_URL`

### Webhooks are not arriving
- Confirm the webhook URL in Creem is `https://YOUR-NGROK-URL/creem/webhook`
- If using VirtualBox, confirm host `8000` is forwarded to guest `8000`
- Confirm Laravel is listening on `0.0.0.0:8000` when inside a VM
- Check ngrok inspector on `http://127.0.0.1:4040`
- Verify `CREEM_WEBHOOK_SECRET` matches the value configured in Creem

### No sudo on the host
- Use the current-user standard agent mode from section `8.6`
- Do not run `openclaw-ansible` without a supported become path
- Prefer `docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler`
- Use `php artisan serve`, `queue:work`, and `schedule:work` only as fallback
