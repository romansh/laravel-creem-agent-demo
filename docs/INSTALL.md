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

- `docs/SCENARIO_1_OPENCLAW_SHORT.md` — short command sequence for OpenClaw scenario
- `docs/SCENARIO_2_PURE_AGENT_SHORT.md` — short command sequence for pure agent scenario
- `docs/SIMULATOR.md` — local Creem API simulator with SQLite history and internal webhook delivery

Preferred runtime for the Laravel app:

- `Docker + FrankenPHP + Octane` via `docker-compose.frankenphp.yml`
- `php artisan serve` is fallback-only for restricted environments

### Mode A: Full OpenClaw + Laravel agent

- Uses `https://github.com/openclaw/openclaw-ansible`
- Requires Debian/Ubuntu and `sudo` or root access
- Recommended for a dedicated VPS or VM
- Telegram should use native OpenClaw channel ownership with `CREEM_AGENT_TELEGRAM_MODE=openclaw`

### Mode B: Current-user standard agent

- Uses Laravel-owned Telegram via `/creem-agent/telegram/webhook`
- Set `CREEM_AGENT_TELEGRAM_MODE=laravel`

# Installation Guide (streamlined)

This file gives a single, recommended path first (fastest to a working demo), with concise alternatives below.

## Recommended primary flows

Two primary flows are supported. Choose one and follow its short path — alternatives are provided but visually de-emphasized.

- Flow A — Docker on the host (fastest to a working instance).
- Flow B — Docker inside a VirtualBox VM (recommended for an isolated, demo-like environment). Flow B has two main sub-variants: with OpenClaw (privileged install inside the VM) and without OpenClaw (pure agent).

Below are concise step sequences for each primary flow.

### Flow A — Docker on the host (fastest)

From the host shell:

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

Start services and run install:

```bash
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat
curl -s http://127.0.0.1:8000/creem-agent/status | jq
```

Optional: test webhooks

```bash
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem:test-webhook checkout.completed
```

Flow A is the quickest path for development and CI.

GitHub package access

The demo now resolves `romansh/laravel-creem`, `romansh/laravel-creem-cli`, and `romansh/laravel-creem-agent` from GitHub VCS repositories declared in `composer.json` over SSH.

If the repositories are private, configure SSH access before running `composer install`:

```bash
ssh -T git@github.com
```

The machine or container user running Composer must have read access to these repositories:

- `romansh/laravel-creem`
- `romansh/laravel-creem-cli`
- `romansh/laravel-creem-agent`

`REPO_MOUNT` in `docker-compose.frankenphp.yml` is now optional and only useful when you want to mount extra local workspace files into the containers for debugging or recording.

### Flow B — Docker inside VirtualBox (recommended for isolated VM)

Summary: provision a VM, mount the host repo into the guest (for example at `/hove/www`), then run the Docker-based setup inside the VM. This keeps host clean and simulates a small VPS.

Common VM bootstrap (guest):

```bash
sudo apt update
sudo apt install -y git curl unzip zip docker.io docker-compose-plugin
sudo usermod -aG docker $USER
# log out and back in or reboot
```

Mount the host repo into the VM (see Appendix), then inside the guest:

```bash
cd /hove/www/laravel-creem-agent-demo
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

Start services and run install inside the VM:

```bash
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat
curl -s http://127.0.0.1:8000/creem-agent/status | jq
```

Flow B has two sub-variants:

B1 — With OpenClaw (inside VM): if the VM has `sudo`, run the OpenClaw Ansible installer inside the VM and attach it to the local Laravel app. Quick command inside VM:

```bash
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
```

After OpenClaw is provisioned inside the VM, configure `CREEM_AGENT_TELEGRAM_MODE=openclaw`, `OPENCLAW_TELEGRAM_BOT_TOKEN`, `OPENCLAW_ENDPOINT`, `OPENCLAW_WORKSPACE_PATH` in the guest `.env`, generate the Telegram channel snippet with `php artisan creem-agent:openclaw-telegram-config`, then start `openclaw gateway` and approve the first Telegram pairing.

B2 — Without OpenClaw (pure agent): skip the privileged installer and run only the Docker stack inside the guest (commands above). This is the simplest VM-based path when `sudo` is unavailable.

In B2, keep Telegram on Laravel with `CREEM_AGENT_TELEGRAM_MODE=laravel` and register the bot webhook to `https://YOUR-NGROK-URL/creem-agent/telegram/webhook`.

-- End of recommended flows --

## Alternative: no-Docker (fallback)

If Docker isn't available on your host, use the current-user path below. It's slightly more manual but works on most Linux systems.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan vendor:publish --tag=creem-config
php artisan vendor:publish --tag=creem-cli-config
php artisan vendor:publish --tag=creem-agent-config
php artisan vendor:publish --tag=creem-agent-migrations
php artisan migrate
php artisan creem-agent:install
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --queue=creem-agent
php artisan schedule:work
```

Notes: this mode is suitable for development, CI, or restricted hosts. It assumes PHP, Composer and required PHP extensions are installed.

## Optional: VirtualBox (shared folder + Guest Additions)

If you prefer an isolated VM, use VirtualBox. The recommended setup is to run the demo inside the VM and share your host repository via a shared folder mounted inside the guest (for example at `/hove/www`).

Short steps (details in the Appendix below):

- Create a shared folder on the host (e.g. host `/home/roman/php/run`, name `repo_run`) via VirtualBox GUI or `VBoxManage`.
- In the guest install guest additions or the distro guest packages and mount the shared folder, then either symlink or mount it at `/hove/www`.

Example mount (guest):

```bash
sudo apt update
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils
sudo mkdir -p /hove/www
sudo usermod -aG vboxsf $USER
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /hove/www
```

After that, create per-package folders under `/hove/www` (e.g. `laravel-creem`, `laravel-creem-cli`, `laravel-creem-agent`) and operate from the guest.

## Quick verification checklist

- `curl -s http://127.0.0.1:8000/creem-agent/status | jq` → status JSON
- `docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install` → wizard
- `docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat` → heartbeat output

## Appendix: VirtualBox Guest Additions & Shared Folder (detailed)

To make the repo files available inside the guest at `/hove/www` (so each package can be a separate folder there), add a VirtualBox shared folder on the host and mount it manually in the guest. Steps below assume the host folder with repositories is `/home/roman/php/run` and the shared-folder name will be `repo_run`.

Host (VirtualBox Manager):

- Open VirtualBox, select the VM → Settings → Shared Folders → Add New Shared Folder.
- Set "Folder Path" to `/home/roman/php/run` and "Folder Name" to `repo_run`.
- (Optional) Check "Make Permanent". Do NOT check "Auto-mount" if you want to control the mount point (`/hove/www`) in the guest.

Guest (Ubuntu Server inside VM):

1. Install build tools / headers (needed if you use the Guest Additions ISO):

```bash
sudo apt update
sudo apt install -y build-essential dkms linux-headers-$(uname -r)
```

2. Preferred: install distro guest packages (simpler):

```bash
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils
```

If you prefer the Oracle Guest Additions ISO, insert it from the VirtualBox menu (Devices → Insert Guest Additions CD image...), then run:

```bash
sudo mkdir -p /mnt/cdrom
sudo mount /dev/cdrom /mnt/cdrom || true
sudo sh /mnt/cdrom/VBoxLinuxAdditions.run || true
```

3. Create the mount point and add your user to the `vboxsf` group:

```bash
sudo mkdir -p /hove/www
sudo usermod -aG vboxsf $USER
# Log out and back in (or reboot) to apply group membership
```

4. Mount the shared folder manually (replace UID/GID if needed):

```bash
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /hove/www
```

5. Make the mount permanent (optional) — edit `/etc/fstab` and add one line (replace `1000:1000` with your `uid:gid` if different):

```
repo_run /hove/www vboxsf uid=1000,gid=1000,auto 0 0
```

Notes:
- After mounting, the host's `/home/roman/php/run` will appear in the guest at `/hove/www` — create separate subfolders there for each package and install/clone packages into those directories.
- If you used the Guest Additions ISO, a reboot may be required.
- If permissions look incorrect, verify `uid`/`gid` options in the mount command or `/etc/fstab`.

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
