# Scenario 1 Short: OpenClaw + Docker + FrankenPHP

## Tokens first

- Creem API key: Creem Dashboard -> API Keys
- Creem webhook secret: Creem Dashboard -> Webhooks
- Telegram bot token: Telegram -> `@BotFather` -> `/newbot`
- Telegram chat id: `curl -s "https://api.telegram.org/bot<TOKEN>/getUpdates" | jq`
- ngrok authtoken: ngrok dashboard
- Optional Slack webhook
- Optional Discord webhook

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
- Port Forwarding -> `2222->22`, `8000->80`, `4040->4040`, `5173->5173`

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

## `.env`

```env
APP_URL=http://127.0.0.1:8000
CREEM_API_KEY=creem_test_...
CREEM_WEBHOOK_SECRET=whsec_...
CREEM_TEST_MODE=true
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_DEFAULT_STORE=default
CREEM_AGENT_TELEGRAM_TOKEN=...
CREEM_AGENT_TELEGRAM_CHAT_ID=...
CREEM_AGENT_SLACK_WEBHOOK=https://hooks.slack.com/services/...
CREEM_AGENT_DISCORD_WEBHOOK=https://discord.com/api/webhooks/...
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/openclaw/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
```

## Start stack

```bash
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler
ngrok config add-authtoken YOUR_NGROK_AUTHTOKEN
ngrok http 8000
```

Creem webhook URL:

```text
https://YOUR-NGROK-URL/creem/webhook
```

## Verify

```bash
curl -s http://127.0.0.1:8000/creem-agent/status | jq
curl -s -X POST http://127.0.0.1:8000/creem-agent/chat -H 'Content-Type: application/json' -d '{"message":"status"}' | jq
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem:test-webhook checkout.completed

## Guest Additions & Shared Folder (optional)

If you want the host repo available inside the VM at `/hove/www`, add a VirtualBox shared folder named `repo_run` (pointing to host `/home/roman/php/run`) and mount it in the guest:

```bash
# inside guest
sudo apt update
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils
sudo mkdir -p /hove/www
sudo usermod -aG vboxsf $USER
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /hove/www
```

Create subfolders under `/hove/www` for each package (e.g. `laravel-creem`, `laravel-creem-cli`, `laravel-creem-agent`) and operate on them from the guest.
