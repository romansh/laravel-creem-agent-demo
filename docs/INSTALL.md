# Installation Guide

## Prerequisites

- PHP 8.2+ with extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath
- Composer 2.x
- Node.js 18+ and npm
- A Creem.io account ([sign up](https://creem.io/dashboard))
- Optional: Telegram for notifications
- Optional: `creem` CLI via Homebrew

### Install VirtualBox on the host (Ubuntu/Debian)

If you need VirtualBox on the host, install it with your package manager and add a shared folder that points at this demo repository (or clone the repo inside the VM). Example commands below assume you will share only the demo repository into the guest.

```bash
sudo apt update
sudo apt install -y virtualbox
VBoxManage sharedfolder add "VM-name" --name demo_repo --hostpath /path/to/laravel-creem-agent-demo --automount
```

Then configure NAT port forwards as needed:

```bash
VBoxManage modifyvm "VM-name" --natpf1 "ssh,tcp,,2222,,22"
VBoxManage modifyvm "VM-name" --natpf1 "http8000,tcp,,8000,,8000"
```

Guest (Ubuntu Server inside VM) — essential steps:

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
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) demo_repo /home/www
```

### Install Docker inside the guest (Ubuntu)

Install Docker and Docker Compose plugin inside the VM so the demo runs fully in the guest:

```bash
# prerequisites
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" |
    sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

After adding your user to `docker` and `vboxsf`, log out and back in (or reboot) to apply group membership.

5. Optional: make the mount permanent by adding an `/etc/fstab` entry for `demo_repo` if desired. If you need multiple package checkouts for maintainer tests, clone those repos inside the guest (for example under `/home/www`) and point `CREEM_AGENT_PACKAGE_REPO` at the local path when running smoke scripts.
- Optional OpenClaw integration: `CREEM_AGENT_OPENCLAW`, `OPENCLAW_ENDPOINT`, `OPENCLAW_WORKSPACE_PATH`, `OPENCLAW_SHARED_SECRET`.
- LLM routing and provider credentials: the `CREEM_AGENT_LLM_*` block plus API keys for OpenAI, Anthropic, Gemini, OpenRouter, and Ollama.
- Creem primary and secondary store credentials: `CREEM_API_*`, `CREEM_WEBHOOK_SECRET`, and `CREEM_SECONDARY_*`.
- Simulator wiring: `CREEM_SIMULATOR_*` variables for internal webhook delivery from the installed simulator package.

Agent app `app/.env`:

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

If you use the simulator, set these in `app/.env`. `bash scripts/install-simulator.sh` will sync them into `simulator/.env`.

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

`docker-compose.yml` now reads `app/.env` for the host runtime and `simulator/.env` for the simulator runtime.


From the host shell:

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/install-app.sh
bash scripts/install-simulator.sh
docker compose build app queue scheduler simulator
docker compose run --rm app php artisan migrate --force
docker compose up -d app queue scheduler simulator
```

For a brand-new folder on a clean VM or host, the short flow is:

```bash
mkdir -p $HOME/php/run
cd $HOME/php/run
git clone https://github.com/romansh/laravel-creem-agent-demo.git
cd laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
bash scripts/install-simulator.sh
docker compose build app queue scheduler simulator
docker compose run --rm app php artisan migrate --force
docker compose up -d app queue scheduler simulator
```

The migration step is intentional. Do not hide it inside container startup. Run it explicitly before `docker compose up -d ...` so the web app, queue, and scheduler can start as normal services without mixing healthchecks with schema changes.
-
Start services and run the supported verification flow:

- `app`, `queue`, and `scheduler` now run the dedicated host runtime from `./app`.
- `simulator` is isolated in `./simulator` and is optional outside simulator-backed demos.
- Use the smoke runner for simulator/webhook verification and for standalone Telegram outbound-send smoke.
- Package-level Telegram send/receive verification from `laravel-creem-agent` is maintainer-only and requires a separate source checkout. End users should not assume that checkout exists inside a VM or shared folder.

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
bash scripts/run-heartbeat.sh --force --all-stores
```

Optional maintainer-only package verification:

```bash
cd /home/www/laravel-creem-agent
php vendor/bin/pest \
    tests/Feature/TelegramWebhookControllerTest.php \
    tests/Feature/TelegramWebhookControllerMoreTest.php \
    tests/Feature/TelegramWebhookControllerFailureTest.php
```

Optional: re-run the integrated webhook smoke

```bash
cd $HOME/php/run/laravel-creem-agent-demo
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

`REPO_MOUNT` in `docker-compose.yml` is now optional and only useful when you want to mount extra local workspace files into the containers for debugging or recording.

### Flow B — Docker inside VirtualBox (recommended for isolated VM)

In the VM the timing is the same as on the host: copy `app/.env.example` to `app/.env`, fill it once with the values you need, then run initialization. The `ngrok` service can start later when you need a public HTTPS callback URL, but its token should already be present in the same file.

Summary: provision a VM, mount or clone this demo repository into the guest (for example at `/home/www`), then run the Docker-based setup inside the VM. This keeps the host clean and simulates a small VPS.

Common VM bootstrap (guest):

```bash
sudo apt update
sudo apt install -y git curl unzip zip docker.io docker-compose-plugin
sudo usermod -aG docker $USER
# log out and back in or reboot
```

Mount or clone this demo repository into the VM (see Appendix), then inside the guest:

```bash
cd /home/www
bash scripts/install-simulator.sh
docker compose build app queue scheduler simulator
docker compose run --rm simulator sh -lc '
    git config --global --add safe.directory /app && composer install --no-interaction &&
    test -f .env || cp .env.example .env &&
    sqlite_db="${DB_DATABASE:-/var/lib/simulator/database.sqlite}" && mkdir -p "$(dirname "$sqlite_db")" && touch "$sqlite_db" &&
    php artisan key:generate &&
    php artisan migrate --seed &&
    php artisan octane:install --server=frankenphp --no-interaction
'
docker compose up -d app queue scheduler simulator
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
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Notes: the demo repository root is not a standalone Laravel app with `artisan`, so the current no-Docker fallback is the smoke runner, not `php artisan creem-agent:*` from the demo root.

## Optional: VirtualBox (shared folder + Guest Additions)

If you prefer an isolated VM, use VirtualBox. Recommended: mount or clone only this demo repository into the guest — do not expose the host's entire parent folder. Mounting only the current repository reduces permission issues and avoids leaking unrelated host files into the VM.

Short steps (details in the Appendix below):

- Preferred: mount or clone this repository into the guest (for example at `/home/www`) and operate from there.

Example (host):

```bash
VBoxManage sharedfolder add "VM-name" --name demo_repo --hostpath /path/to/laravel-creem-agent-demo --automount
```

Example (guest):

```bash
sudo mkdir -p /home/www
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) demo_repo /home/www
```

- Optional (maintainer-only): if you need multiple sibling package checkouts for package-level tests, clone those repositories inside the guest (for example under `/home/www`) and point `CREEM_AGENT_PACKAGE_REPO` at the local path when running the smoke scripts. This is purely optional and intended for maintainers.

## Quick verification checklist

- `bash scripts/smoke-simulator-demo.sh` → simulator + webhook smoke + user-facing heartbeat validation + standalone Telegram outbound-send smoke
- `bash scripts/run-heartbeat.sh --all-stores` → direct demo heartbeat against the current simulator-backed stack
- `CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh` → optional maintainer-only package Telegram coverage
- `docker compose ps -a` → confirm simulator stack state

## Appendix: VirtualBox Guest Additions & Shared Folder (detailed)

If you use VirtualBox, prefer mounting or cloning only this demo repository into the guest rather than exposing the host's entire parent folder. The examples below assume you will share the demo repo into the guest at `/home/www`.

Host (VirtualBox Manager):

- Open VirtualBox, select the VM → Settings → Shared Folders → Add New Shared Folder.
- Set "Folder Path" to the path of this repository on your host and "Folder Name" to a short name such as `demo_repo`.
- (Optional) Check "Make Permanent". Do NOT check "Auto-mount" if you want to control the mount point in the guest.

### Install VirtualBox on the host (Ubuntu/Debian)

If you need to install VirtualBox on the host machine, run:

```bash
sudo apt update
sudo apt install -y virtualbox
```

Then create the shared folder and NAT port forwards (replace "VM-name"):

```bash
# shared folder (share this demo repository only)
VBoxManage sharedfolder add "VM-name" --name demo_repo --hostpath /path/to/laravel-creem-agent-demo --automount
# port forwards: host->guest
VBoxManage modifyvm "VM-name" --natpf1 "ssh,tcp,,2222,,22"
VBoxManage modifyvm "VM-name" --natpf1 "http8000,tcp,,8000,,8000"
```

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

### Install Docker inside the guest (Ubuntu)

Install Docker and Docker Compose plugin inside the VM so the demo runs fully in the guest:

```bash
# prerequisites
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" |
    sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

After adding your user to `docker` and `vboxsf`, log out and back in (or reboot) to apply group membership.


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

- Notes:
- If you mounted the demo repo, it will be available inside the guest at the mount point you chose (for example `/home/www`).
- If you need multiple package checkouts for maintainer tests, clone those repositories inside the guest and point `CREEM_AGENT_PACKAGE_REPO` at the local path when running smoke scripts.
- If you used the Guest Additions ISO, a reboot may be required.
- If permissions look incorrect, verify `uid`/`gid` options in the mount command or `/etc/fstab`.

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh

CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

### 8.6 If sudo is unavailable

If the host does not provide `sudo`, do not try to force `openclaw-ansible`. Upstream expects privilege escalation for firewall, Docker, systemd, and host hardening.

Preferred current-user path if Docker is already available to your user:

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Fallback current-user flow if Docker is unavailable:

```bash
cd $HOME/php/run/laravel-creem-agent-demo
composer install
cp app/.env.example app/.env
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
docker compose --env-file app/.env up -d ngrok

# Extract the public HTTPS URL from the ngrok container logs.
# If this prints nothing, check `.env` for `NGROK_AUTHTOKEN` and inspect `docker compose logs ngrok`.
NGROK_URL="$(docker compose logs --no-color ngrok 2>&1 | grep -Eo 'https://[A-Za-z0-9.-]+\.ngrok(-free)?\.app' | tail -n 1)"
test -n "$NGROK_URL" || { echo "ngrok URL not found; check NGROK_AUTHTOKEN and ngrok logs"; false; }
echo "$NGROK_URL"

# Or load `.env` into the current shell session before starting compose:
set -o allexport; source app/.env; set +o allexport
docker compose up -d ngrok
NGROK_URL="$(docker compose logs --no-color ngrok 2>&1 | grep -Eo 'https://[A-Za-z0-9.-]+\.ngrok(-free)?\.app' | tail -n 1)"
test -n "$NGROK_URL" || { echo "ngrok URL not found; check NGROK_AUTHTOKEN and ngrok logs"; false; }
echo "$NGROK_URL"
```

Take the generated HTTPS URL and configure Creem webhooks to:

```text
https://YOUR-NGROK-URL/creem/webhook
```

Recommended setup combinations:

### VirtualBox guest runs the full Docker stack

1. In VirtualBox, forward host `8000` to guest `8000`. Forwarding host `4040` is optional (ngrok inspector is not published by default).
2. Inside the guest, run `docker compose up -d app queue scheduler ngrok`
3. Read the HTTPS URL from the ngrok container output or logs (inspector not published by default)
4. Put that HTTPS URL into Creem webhook settings

### Local machine runs the full Docker stack

1. Put `NGROK_AUTHTOKEN` in `.env`
2. Run `docker compose --env-file app/.env up -d app queue scheduler ngrok`
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
* * * * * cd /home/www/project && php artisan schedule:run >> /dev/null 2>&1
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
- If you maintain a separate `laravel-creem-agent` source checkout, optionally add package-level Telegram checks with `CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh`
- For user-facing heartbeat checks in this repository, prefer `bash scripts/run-heartbeat.sh --all-stores`
- In this repository, the HTTP agent endpoints are served by the checked-in demo stack itself after `docker compose up -d app queue scheduler simulator`, so use direct checks against `http://127.0.0.1:8000/creem-agent/status` and `http://127.0.0.1:8000/creem-agent/chat` without switching to a separate `laravel-creem-agent` checkout
- If OpenClaw runs on another host, expose the app via Nginx/Caddy and update `APP_URL`

### LLM keys seem valid but chat still falls back
- `CREEM_AGENT_LLM_PROVIDER` and `CREEM_AGENT_LLM_MODEL` only choose the Laravel AI provider/model; the actual credentials must still be present in the standard Laravel AI env keys such as `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, or `OPENROUTER_API_KEY`
- In the checked-in demo, `bash scripts/install-simulator.sh` syncs those values from `app/.env` into `simulator/.env`; re-run that script after editing provider credentials
- Verify the live app sees the key with `docker compose exec app php artisan tinker --execute="dump(filled(config('ai.providers.openai.key')));"` or the equivalent provider key
- If logs show `cURL error 6: Could not resolve host: api.openai.com`, this is a Docker/container DNS problem, not a bad API key. In one broken runtime here, `simulator` resolved `api.openai.com`, but `app` had `/etc/resolv.conf` pointing at `127.0.0.53` instead of Docker's internal `127.0.0.11`
- In that case, recreate the affected service and re-sync env before blaming the key: `bash scripts/install-simulator.sh && docker compose up -d --force-recreate app queue scheduler simulator`
- If `--force-recreate` fails because port `8000` is already in use, free that port first and then recreate the containers so Docker rewrites the resolver config

### Webhooks are not arriving
- Confirm the webhook URL in Creem is `https://YOUR-NGROK-URL/creem/webhook`
- If using VirtualBox, confirm host `8000` is forwarded to guest `8000` (forwarding `4040` is optional for the ngrok inspector).
- Confirm the `ngrok` container is running: `docker compose ps ngrok`
- Check the ngrok container logs or API for the public URL (inspector is not published by default).
- Verify `CREEM_WEBHOOK_SECRET` matches the value configured in Creem

### No sudo on the host
- Use the current-user standard agent mode from section `8.6`
- Do not run `openclaw-ansible` without a supported become path
- Prefer `docker compose up -d app queue scheduler`
- Use `php artisan serve`, `queue:work`, and `schedule:work` only as fallback
