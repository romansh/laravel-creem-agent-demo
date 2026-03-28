# Installation Guide

## Prerequisites

- PHP 8.2+ with extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath
- Composer 2.x
- Node.js 18+ and npm
- A Creem.io account ([sign up](https://creem.io/dashboard))
- Optional: Telegram for notifications
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

Read the documentation as two approaches, not as one continuous setup:

- Approach 1 — simulator-first. This is the supported path for the current repository and should be completed first.
- Approach 2 — real Creem. This is the next-stage path for public webhooks, live Creem data, and optional OpenClaw on top of a dedicated Laravel host.

Operationally, you can run either approach on:

- Flow A — Docker on the host.
- Flow B — Docker inside a VirtualBox VM.

OpenClaw is an overlay on either path, not a replacement for the simulator-first validation.

Below are concise step sequences for each primary flow.

### Flow A — Docker on the host (fastest)

When to fill tokens and variables: copy `.env.example` to `.env` before the init/install commands below and do a one-time setup of the values you plan to use. Put real tokens, Telegram settings, secondary-store credentials, and `NGROK_AUTHTOKEN` into that same `.env` so the whole stack uses one consistent configuration file.

`test -f .env || cp .env.example .env` in scripts is a fallback only; if you already have custom values, the command should not overwrite them. If you run automation with no `.env`, this fallback ensures the file exists.

Simulator `.env` handling for demo runs
- The simulator is no longer checked into this repository. Install it into `./creem-simulator` with `bash scripts/install-simulator.sh` before Docker builds or smoke runs.
- After installation, you do not need to manually create or edit `creem-simulator/.env` during local demo or smoke runs. The provided automation and `scripts/smoke-simulator-demo.sh` will copy `creem-simulator/.env.example` to `creem-simulator/.env` when `.env` is missing and will generate an application key inside the simulator container (`php artisan key:generate --force`).
- This automatic copy-and-generate behavior is intended only for local/demo/CI use. For production or long-running environments, provide explicit secrets (`APP_KEY`, webhook URL/secret, API keys) via environment variables, Docker secrets, or your orchestration platform. Do not commit real secrets to the repository.

If you plan to use multiple Creem stores (multi-store demo), add the secondary store variables to `.env` during that same setup step and then verify that the published `config/creem.php` maps those variables to the `secondary` profile. Look for `CREEM_SECONDARY_API_KEY`, `CREEM_SECONDARY_WEBHOOK_SECRET`, and `CREEM_SECONDARY_API_URL` in the project `.env.example`.

### Laravel AI / LLM configuration
The demo `.env.example` already includes the full Laravel AI block. Configure the agent-facing provider and model in `.env` like this:

```env
CREEM_AGENT_LLM_ENABLED=true
CREEM_AGENT_LLM_PROVIDER=openai
CREEM_AGENT_LLM_MODEL=gpt-4o-mini
#CREEM_AGENT_LLM_PROVIDER=gemini
#CREEM_AGENT_LLM_MODEL=gemini-2.5-flash-lite
CREEM_AGENT_LLM_TIMEOUT=30
CREEM_AGENT_LLM_FALLBACK_TO_RULES=true

OPENAI_API_KEY=sk-...
OPENAI_URL=https://api.openai.com/v1
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
OPENROUTER_API_KEY=
OLLAMA_API_KEY=
OLLAMA_BASE_URL=http://localhost:11434
```

Switch providers by changing `CREEM_AGENT_LLM_PROVIDER`; switch models by changing `CREEM_AGENT_LLM_MODEL`. The example file keeps Gemini lines commented out so you can quickly switch the agent to `gemini-2.5-flash-lite` without rewriting the section. If you want to customize Laravel AI's provider registry itself, publish `config/ai.php` with `php artisan vendor:publish --tag=ai-config`.

What the demo `.env.example` covers
- Core Laravel runtime: `APP_*`, locale, mail, queue, cache, session, and `OCTANE_SERVER`.
- Database: SQLite by default via `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite`.
- Notification channels: Telegram, Slack, and Discord webhook variables.
- Optional OpenClaw integration: `CREEM_AGENT_OPENCLAW`, `OPENCLAW_ENDPOINT`, `OPENCLAW_WORKSPACE_PATH`, `OPENCLAW_SHARED_SECRET`.
- LLM routing and provider credentials: the `CREEM_AGENT_LLM_*` block plus API keys for OpenAI, Anthropic, Gemini, OpenRouter, and Ollama.
- Creem primary and secondary store credentials: `CREEM_API_*`, `CREEM_WEBHOOK_SECRET`, and `CREEM_SECONDARY_*`.
- Simulator wiring: `CREEM_SIMULATOR_*` variables for internal webhook delivery from the installed simulator package.

Agent app `.env`:

```env
APP_URL=http://127.0.0.1:8000
CREEM_API_KEY=creem_test_...
CREEM_WEBHOOK_SECRET=whsec_...
CREEM_TEST_MODE=true
CREEM_AGENT_DEFAULT_STORE=default
CREEM_AGENT_TELEGRAM_MODE=laravel
CREEM_AGENT_TELEGRAM_TOKEN=...
CREEM_AGENT_TELEGRAM_CHAT_ID=...
OCTANE_SERVER=frankenphp
```

If you use the simulator, set these in `./.env` (preferred for unified configuration). You can also set them in `creem-simulator/.env` after running `bash scripts/install-simulator.sh`.

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

Optionally, keep the values in the root `.env` and let docker-compose inject them into the simulator container via `docker-compose.frankenphp.yml`.


From the host shell:

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
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler simulator
```

Do not initialize `app` with `run --rm app ...` in the current repository state. That service points at `creem-simulator`, not at a dedicated agent-hosting Laravel app.

Start services and run the supported verification flow:

Current topology note:

- The checked-in `docker-compose.frankenphp.yml` runs the generated `creem-simulator` app as the shared Laravel runtime for `app`, `queue`, and `scheduler`.
- `bash scripts/install-simulator.sh` now installs `romansh/laravel-creem`, `romansh/laravel-creem-cli`, and `romansh/laravel-creem-agent` into that generated app.
- Use the smoke runner for simulator/webhook verification and for standalone Telegram outbound-send smoke.
- Package-level Telegram send/receive verification from `laravel-creem-agent` is maintainer-only and requires a separate source checkout. End users should not assume that checkout exists inside a VM or shared folder.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
```

Optional maintainer-only package verification:

```bash
cd /path/to/laravel-creem-agent
php vendor/bin/pest \
    tests/Feature/TelegramWebhookControllerTest.php \
    tests/Feature/TelegramWebhookControllerMoreTest.php \
    tests/Feature/TelegramWebhookControllerFailureTest.php
```

Optional: re-run the integrated webhook smoke

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Flow A is the quickest path for development and CI.

Optional maintainer-only package checks inside the VM, if you intentionally mounted a separate `laravel-creem-agent` source checkout:

```bash
CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```
In B2, keep Telegram on Laravel with `CREEM_AGENT_TELEGRAM_MODE=laravel`. The checked-in demo stack now supports direct heartbeat runs through `bash scripts/run-heartbeat.sh`, while maintainers can still add package tests on top by pointing `CREEM_AGENT_PACKAGE_REPO` at a separate `laravel-creem-agent` checkout.
`bash scripts/run-heartbeat.sh` auto-detects whether your current env points at the local simulator or a real Creem API URL. In simulator mode it starts `simulator` and waits for readiness; in real-store or OpenClaw mode it skips that startup and just runs the heartbeat command.
Notes: the demo repository root is not a standalone Laravel app with `artisan`, so the current no-Docker fallback is the smoke runner. Direct package verification from `laravel-creem-agent` is optional maintainer-only coverage, not a user prerequisite.

GitHub package access

The demo now resolves `romansh/laravel-creem`, `romansh/laravel-creem-cli`, and `romansh/laravel-creem-agent` from GitHub VCS repositories declared in `composer.json` over SSH.

If the repositories are private, configure SSH access before running `composer install`:

```bash
ssh -T git@github.com
```

If your packages are not public, use SSH agent forwarding so the private key stays on the host:

```bash
# On host:
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_rsa
# Connect to VM with agent forwarding
ssh -A user@vm_ip

# Inside VM you can then run:
ssh git@github.com
```

Note: this only works when you SSH into the VM (`ssh -A`), not when using the VirtualBox GUI.

The machine or container user running Composer must have read access to these repositories:

- `romansh/laravel-creem`
- `romansh/laravel-creem-cli`
- `romansh/laravel-creem-agent`

`REPO_MOUNT` in `docker-compose.frankenphp.yml` is now optional and only useful when you want to mount extra local workspace files into the containers for debugging or recording.

### Flow B — Docker inside VirtualBox (recommended for isolated VM)

In the VM the timing is the same as on the host: copy `.env.example` to `.env`, fill it once with the values you need, then run initialization. The `ngrok` service can start later when you need a public HTTPS callback URL, but its token should already be present in the same `.env` file.

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
cd /home/www/laravel-creem-agent-demo
bash scripts/install-simulator.sh
docker compose -f docker-compose.frankenphp.yml build app queue scheduler simulator
docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
    git config --global --add safe.directory /app && composer install --no-interaction &&
    test -f .env || cp .env.example .env &&
    sqlite_db="${DB_DATABASE:-/var/lib/creem-simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
    php artisan key:generate &&
    php artisan migrate --seed &&
    php artisan octane:install --server=frankenphp --no-interaction
'
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler simulator
```

Then run the supported checks inside the VM:

```bash
bash scripts/smoke-simulator-demo.sh

CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh


Flow B has two sub-variants:

B1 — With OpenClaw (inside VM): if the VM has `sudo`, run the OpenClaw Ansible installer inside the VM and attach it to the local Laravel app. Quick command inside VM:

```bash
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
```

After OpenClaw is provisioned inside the VM, configure `CREEM_AGENT_TELEGRAM_MODE=openclaw`, `OPENCLAW_TELEGRAM_BOT_TOKEN`, `OPENCLAW_ENDPOINT`, `OPENCLAW_WORKSPACE_PATH` in the guest `.env`, generate the Telegram channel snippet with `php artisan creem-agent:openclaw-telegram-config`, then start `openclaw gateway` and approve the first Telegram pairing.

B2 — Without OpenClaw (pure agent): skip the privileged installer and run only the Docker stack inside the guest (commands above). This is the simplest VM-based path when `sudo` is unavailable.

In B2, keep Telegram on Laravel with `CREEM_AGENT_TELEGRAM_MODE=laravel`, but note that Telegram is currently verified through the standalone smoke step inside `bash scripts/smoke-simulator-demo.sh`. Maintainers can add package tests on top by pointing `CREEM_AGENT_PACKAGE_REPO` at a separate `laravel-creem-agent` checkout, rather than through a dedicated checked-in HTTP app container.

-- End of recommended flows --

## Alternative: no-Docker (fallback)

If Docker isn't available on your host, use the current-user path below. It's slightly more manual but works on most Linux systems.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Notes: the demo repository root is not a standalone Laravel app with `artisan`, so the current no-Docker fallback is the smoke runner, not `php artisan creem-agent:*` from the demo root.

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

- `bash scripts/smoke-simulator-demo.sh` → simulator + webhook smoke + user-facing heartbeat validation + standalone Telegram outbound-send smoke
- `bash scripts/run-heartbeat.sh --all-stores` → direct demo heartbeat against the current simulator-backed stack
- `CREEM_AGENT_PACKAGE_REPO=/path/to/laravel-creem-agent bash scripts/smoke-simulator-demo.sh` → optional maintainer-only package Telegram coverage
- `docker compose -f docker-compose.frankenphp.yml ps -a` → confirm simulator stack state

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
sudo mkdir -p /home/www
sudo usermod -aG vboxsf $USER
# Log out and back in (or reboot) to apply group membership
```

4. Mount the shared folder manually (replace UID/GID if needed):

```bash
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /home/www
```


5. Make the mount permanent (optional) — append a line to `/etc/fstab` using `tee`. Replace `1000`/`1000` with your `id -u`/`id -g` if different.

Example (append safely as root):

```bash
echo 'repo_run /home/www vboxsf uid=1000,gid=1000,auto 0 0' | sudo tee -a /etc/fstab
```

Then test without rebooting:

```bash
sudo mount -a
mount | grep vboxsf
```

If the mount is empty after a reboot, ensure the kernel module is loaded and the `vboxsf` service is running:

```bash
lsmod | grep vboxsf || sudo modprobe vboxsf
systemctl status vboxservice || true
```

Notes:
- After mounting, the host's `/home/roman/php/run` will appear in the guest at `/hove/www` — create separate subfolders there for each package and install/clone packages into those directories.
- If you used the Guest Additions ISO, a reboot may be required.
- If permissions look incorrect, verify `uid`/`gid` options in the mount command or `/etc/fstab`.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh

CREEM_AGENT_PACKAGE_REPO=/path/to/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

### 8.6 If sudo is unavailable

If the host does not provide `sudo`, do not try to force `openclaw-ansible`. Upstream expects privilege escalation for firewall, Docker, systemd, and host hardening.

Preferred current-user path if Docker is already available to your user:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
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

## Step 9: Optional — Start ngrok in Docker and expose webhooks

If Creem needs to call your local app or VirtualBox guest, expose the Laravel app through the bundled `ngrok` container.

Typical flow:

```bash
# Start the ngrok container using the token already stored in `.env`
docker compose --env-file .env -f docker-compose.frankenphp.yml up -d ngrok

# Read the public HTTPS URL from the ngrok container logs or API; the local inspector is not published by default.

# Or load `.env` into the current shell session before starting compose:
set -o allexport; source .env; set +o allexport
docker compose -f docker-compose.frankenphp.yml up -d ngrok
# The public URL can be read from the ngrok container logs; the inspector is not published by default.
```

Take the generated HTTPS URL and configure Creem webhooks to:

```text
https://YOUR-NGROK-URL/creem/webhook
```

Recommended setup combinations:

### VirtualBox guest runs the full Docker stack

1. In VirtualBox, forward host `8000` to guest `8000`. Forwarding host `4040` is optional (ngrok inspector is not published by default).
2. Inside the guest, run `docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler ngrok`
3. Read the HTTPS URL from the ngrok container output or logs (inspector not published by default)
4. Put that HTTPS URL into Creem webhook settings

### Local machine runs the full Docker stack

1. Put `NGROK_AUTHTOKEN` in `.env`
2. Run `docker compose --env-file .env -f docker-compose.frankenphp.yml up -d app queue scheduler ngrok`
3. Read the HTTPS URL from the ngrok container output or logs (inspector not published by default)
4. Put that HTTPS URL into Creem webhook settings

Verification:

```bash
curl -i http://127.0.0.1:8000/creem/webhook
php artisan creem:test-webhook checkout.completed
```

Note: `GET /creem/webhook` may return `405 Method Not Allowed`, which is fine. The important part is that the app is reachable and `POST` requests can arrive.

## Step 10: Scheduling (Production)

For demo and simulator runs, use `everyMinute()` so you do not wait 4 hours between heartbeats. This cadence is not controlled by `.env` in the current docs; it is controlled by the Laravel scheduler definition.

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('creem-agent:heartbeat --all-stores')
    ->everyMinute()
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

`heartbeat_frequency` in `config/creem-agent.php` is store metadata in hours. It is not the switch that makes the demo run every minute; for demo cadence, change the scheduler definition above.

Edit `config/creem-agent.php`:

```php
'stores' => [
    'default' => [
        'profile' => 'default',
        'heartbeat_frequency' => 4,
        'notifications' => ['telegram', 'database'],
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

After running `php artisan vendor:publish`, verify that `config/creem.php` in your application contains the `secondary` profile and that it references the `CREEM_SECONDARY_*` environment variables. If not, copy the snippet above into `config/creem.php`.
```

## Troubleshooting

### API Connection Failed
- Verify `CREEM_API_KEY` is set correctly in `.env`
- Check if using test mode: `CREEM_TEST_MODE=true`
- Try: `php artisan creem:whoami`

### No Notifications
- Check channel configuration in `.env`
- Run `bash scripts/run-heartbeat.sh --force --all-stores` to trigger a report in the checked-in demo stack
- Check `storage/logs/laravel.log` for errors

### CLI Not Detected
- Run `which creem` to verify installation
- Run `creem whoami` to verify authentication
- Agent will auto-fallback to SDK — no action needed

### OpenClaw Not Reaching the Agent
- For the current repository, first confirm the simulator-first checks are green: `bash scripts/smoke-simulator-demo.sh`
- If you maintain a separate `laravel-creem-agent` source checkout, optionally add package-level Telegram checks with `CREEM_AGENT_PACKAGE_REPO=/path/to/laravel-creem-agent bash scripts/smoke-simulator-demo.sh`
- For user-facing heartbeat checks in this repository, prefer `bash scripts/run-heartbeat.sh --all-stores`
- Only use direct checks against `http://127.0.0.1:8000`, `GET /creem-agent/status`, and `POST /creem-agent/chat` when you are already on the real-Creem approach with a dedicated Laravel agent host
- If OpenClaw runs on another host, expose the app via Nginx/Caddy and update `APP_URL`

### Webhooks are not arriving
- Confirm the webhook URL in Creem is `https://YOUR-NGROK-URL/creem/webhook`
- If using VirtualBox, confirm host `8000` is forwarded to guest `8000` (forwarding `4040` is optional for the ngrok inspector).
- Confirm the `ngrok` container is running: `docker compose -f docker-compose.frankenphp.yml ps ngrok`
- Check the ngrok container logs or API for the public URL (inspector is not published by default).
- Verify `CREEM_WEBHOOK_SECRET` matches the value configured in Creem

### No sudo on the host
- Use the current-user standard agent mode from section `8.6`
- Do not run `openclaw-ansible` without a supported become path
- Prefer `docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler`
- Use `php artisan serve`, `queue:work`, and `schedule:work` only as fallback
