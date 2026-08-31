# Scenario 1: Full OpenClaw + Laravel Agent

Read this scenario in two phases:

1. simulator-first validation
2. real Creem with OpenClaw attached

## Purpose

This scenario is for a Debian or Ubuntu VM or VPS where `sudo` is available. It installs OpenClaw through `openclaw/openclaw-ansible`, deploys the Laravel demo app, configures notifications, exposes webhooks through Dockerized ngrok, and verifies the full flow end-to-end.

The important sequencing rule is that OpenClaw does not replace the simulator-first phase. It sits on top of it.

## Outcome

At the end of this scenario you will have:

- OpenClaw installed and onboarded
- Laravel demo app running
- Creem SDK, CLI, and agent packages installed
- Telegram and Dockerized ngrok configured
- Telegram and Dockerized ngrok configured
- Public webhook URL reachable from Creem
- A working demo flow with heartbeat, chat, and webhook processing

## 1. Prepare Host or VirtualBox VM

Recommended environment:

- Ubuntu Server 24.04 LTS
- 2 vCPU
- 4 GB RAM minimum
- 30 GB disk
- If using VirtualBox:
  - Host `2222` -> Guest `22`
  - Host `8000` -> Guest `8000`
  - Host `5173` -> Guest `5173` (only if you run Vite on the host)
  - Host `4040` -> Guest `4040` (optional; ngrok inspector is not published by default)

Base packages:

```bash
sudo apt update
sudo apt install -y git curl unzip zip jq sqlite3 ansible composer php-cli php-mbstring php-xml php-curl php-sqlite3 php-bcmath
```

VirtualBox host installation and setup:

```bash
sudo apt update
sudo apt install -y virtualbox
virtualbox
```

In the VirtualBox UI:

1. Click `New`
2. Create Ubuntu Server VM with 2 CPU, 4 GB RAM, 30 GB disk
3. Settings -> Network -> Adapter 1 -> NAT
4. Settings -> Network -> Adapter 1 -> Advanced -> Port Forwarding
5. Add `2222->22` and `8000->80`. Forwarding `5173` or `4040` is optional and only needed for local host access to those services.

## 2. Get External Accounts and Tokens

### 2.1 Creem API key and webhook secret

1. Sign in to Creem dashboard.
2. Open API Keys.
3. Create or copy a test key starting with `creem_test_`.
4. Open webhook settings.
5. Create a webhook endpoint later after the `ngrok` container is running (read the public URL from the ngrok container output).
6. Copy the webhook signing secret, usually `whsec_...`.

You will need:

- `CREEM_API_KEY`
- `CREEM_WEBHOOK_SECRET`

### 2.2 Telegram bot token

1. Open Telegram.
2. Search for `@BotFather`.
3. Run `/newbot`.
4. Give the bot a display name.
5. Give the bot a username ending with `bot`.
6. BotFather returns a token like `123456789:AA...`.

Save it as:

- `OPENCLAW_TELEGRAM_BOT_TOKEN`

For the OpenClaw scenario, do not pre-configure a Laravel chat ID. Telegram ownership belongs to OpenClaw gateway, which uses native pairing or allowlists as described in `https://docs.openclaw.ai/channels/telegram`.

### 2.4 ngrok authtoken

1. Sign up or sign in at ngrok dashboard.
2. Open the Auth section.
3. Copy your authtoken.

Save it as:

- `NGROK_AUTHTOKEN`

<!-- Slack and Discord notification instructions removed -->

## 3. Install OpenClaw with openclaw-ansible

Quick installer:

```bash
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
```

Manual path:

```bash
git clone https://github.com/openclaw/openclaw-ansible.git
cd openclaw-ansible
ansible-galaxy collection install -r requirements.yml
./run-playbook.sh
```

Development mode if you need it:

```bash
git clone https://github.com/openclaw/openclaw-ansible.git
cd openclaw-ansible
./run-playbook.sh -e openclaw_install_mode=development
```

Post-install onboarding:

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

## 4. Install the Laravel Demo Application

```bash
mkdir -p $HOME/php/run
cd $HOME/php/run/laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
```

The demo composer file resolves package dependencies from GitHub VCS repositories over SSH. If the repos are private, configure SSH access first:

```bash
ssh -T git@github.com
```

Install packages and the optional simulator runtime:

```bash
bash scripts/install-simulator.sh
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
```

Current topology note: the checked-in compose file now runs the host Laravel runtime from `app/`, while `simulator/` remains an optional second runtime for deterministic local data. Treat `bash scripts/run-heartbeat.sh` as the user-facing heartbeat path, and use separate package tests only as maintainer-only extra coverage.

## 5. Configure `app/.env`

```env
APP_NAME="Creem Agent Demo"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

CREEM_API_KEY=creem_test_YOUR_KEY_HERE
CREEM_WEBHOOK_SECRET=whsec_YOUR_SECRET_HERE
CREEM_TEST_MODE=true

CREEM_AGENT_DEFAULT_STORE=default
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw
OPENCLAW_TELEGRAM_BOT_TOKEN=YOUR_TELEGRAM_BOT_TOKEN
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=$HOME/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me

<!-- CREEM_AGENT_SLACK_WEBHOOK and CREEM_AGENT_DISCORD_WEBHOOK removed from examples -->
```

Render the OpenClaw Telegram channel starter config from Laravel:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose exec app php artisan creem-agent:openclaw-telegram-config
```

Add the generated snippet to your OpenClaw gateway config, then start and pair Telegram:

```bash
openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

## 6. Start ngrok in Docker

Provide the token to Docker Compose:

```bash
export NGROK_AUTHTOKEN=YOUR_NGROK_AUTHTOKEN
```

If you prefer to keep the token in the app `.env` instead, add `NGROK_AUTHTOKEN=your_token` to `.env` (do not commit) and either run Docker Compose with that file:

```bash
docker compose --env-file app/.env up -d ngrok
```

or load `.env` into the current shell session:

```bash
set -o allexport; source app/.env; set +o allexport
docker compose up -d ngrok
```

Start the app stack:

Prerequisite: finish the initial `app/.env` setup and run `bash scripts/install-app.sh` plus `bash scripts/install-simulator.sh` first.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose up -d app queue scheduler ngrok
```

Read the public HTTPS URL from the local inspector:

```bash
# The ngrok public URL can be read from the ngrok container logs or the container's API; inspector is not published by default.
```

Copy the public HTTPS URL, for example:

```text
https://example-1234.ngrok-free.app
```

Your Creem webhook URL becomes:

```text
https://example-1234.ngrok-free.app/creem/webhook
```

If running inside VirtualBox, keep host `8000` forwarded to guest `8000`. Forwarding `4040` is optional (ngrok inspector is not published by default).

## 7. Register Webhook in Creem

1. Open Creem dashboard.
2. Open webhooks settings.
3. Create endpoint:

```text
https://YOUR-NGROK-URL/creem/webhook
```

4. Subscribe at minimum to:

- `checkout.completed`
- `subscription.paid`
- `subscription.canceled`
- `subscription.expired`
- `payment.failed`

5. Copy or confirm the webhook secret.
6. Put that secret into `CREEM_WEBHOOK_SECRET`.

## 8. Run the Laravel Agent Services

In terminal 1:

Prerequisite: finish the initial `app/.env` setup and run `bash scripts/install-app.sh` plus `bash scripts/install-simulator.sh` first.

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose up -d app queue scheduler ngrok
```

In terminal 2:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose logs -f app queue scheduler
```

In terminal 3:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
# The ngrok public URL can be read from the ngrok container logs; the inspector is not published by default.
```

In terminal 4:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

## 9. Attach OpenClaw to the Laravel Agent

OpenClaw should use the Laravel app as its execution surface:

- `bash scripts/smoke-simulator-demo.sh`
- `bash scripts/run-heartbeat.sh --all-stores`
- optional maintainer-only extra: `CREEM_AGENT_PACKAGE_REPO=/home/www/laravel-creem-agent bash scripts/smoke-simulator-demo.sh`

Verification:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
```

Telegram verification follows the native OpenClaw flow, not the Laravel webhook flow:

```bash
openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

Optional OpenClaw heartbeat file:

```bash
mkdir -p /home/openclaw/.openclaw/workspace
cat > /home/openclaw/.openclaw/workspace/HEARTBEAT.md <<'EOF'
# OpenClaw Workspace Heartbeat

## Tasks
- Every minute in demo mode: run `php artisan creem-agent:heartbeat --all-stores`
- Every 30 minutes: call `GET /creem-agent/status`
- On human prompt: forward text to `POST /creem-agent/chat`

## Escalation
- If `past_due > 0`, notify immediately
- If 3+ cancellations in one cycle, escalate
- If no changes, stay silent
EOF
```

## 10. Verify Notifications

Telegram test:

```bash
curl -s "https://api.telegram.org/bot$CREEM_AGENT_TELEGRAM_TOKEN/sendMessage" \
  -d chat_id="$CREEM_AGENT_TELEGRAM_CHAT_ID" \
  -d text="Creem Agent test message" | jq
```

Notifications are easiest to verify by triggering the supported verification flow:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

## 11. Verify Webhooks

Local webhook verification:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```
