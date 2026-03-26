# Scenario 1 Short: OpenClaw + Docker + FrankenPHP

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
docker compose -f docker-compose.frankenphp.yml build app queue scheduler simulator
	# Примечание: добавлена команда git config, чтобы избежать ошибок Git safe.directory при запуске Composer в контейнере
	docker compose -f docker-compose.frankenphp.yml run --rm simulator sh -lc '
	git config --global --add safe.directory /app && composer install --no-interaction &&
	test -f .env || cp .env.example .env &&
	mkdir -p database && touch database/database.sqlite &&
	php artisan key:generate &&
	php artisan migrate --seed &&
	php artisan octane:install --server=frankenphp --no-interaction
'
	# Примечание: добавлена команда git config, чтобы избежать ошибок Git safe.directory при запуске Composer в контейнере
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

This assumes `ngrok` is already running.

Set all tokens and variables now, before `creem-agent:install`, OpenClaw Telegram pairing, and heartbeat.
This is the right point because `YOUR-NGROK-URL` is already known.

## `.env`

```env
APP_URL=http://127.0.0.1:8000
CREEM_API_KEY=creem_test_...
CREEM_WEBHOOK_SECRET=whsec_...
CREEM_TEST_MODE=true
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw
CREEM_AGENT_DEFAULT_STORE=default
OPENCLAW_TELEGRAM_BOT_TOKEN=...
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/openclaw/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
```

If you also run the simulator, fill `creem-simulator/.env` before starting `simulator`:

```env
CREEM_SIMULATOR_AGENT_WEBHOOK_URL=http://app/creem/webhook
CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET=whsec_demo_default
CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS=true
```

## OpenClaw Telegram channel

```bash
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:openclaw-telegram-config
openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

## Start stack

```bash
export NGROK_AUTHTOKEN=YOUR_NGROK_AUTHTOKEN
docker compose -f docker-compose.frankenphp.yml up -d app queue scheduler ngrok
curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[] | select(.proto == "https") | .public_url'
```

Creem webhook URL:

```text
https://YOUR-NGROK-URL/creem/webhook
```

The `YOUR-NGROK-URL` value comes from the Dockerized `ngrok` inspector on `http://127.0.0.1:4040`.

After you get `YOUR-NGROK-URL`, use it anywhere a public callback is required, especially the Creem webhook endpoint.

If you prefer to store the token in `.env`, add `NGROK_AUTHTOKEN=your_token` to `.env` (do not commit) and run:

```bash
docker compose --env-file .env -f docker-compose.frankenphp.yml up -d ngrok
curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[] | select(.proto == "https") | .public_url'
```

## Verify

```bash
curl -s http://127.0.0.1:8000/creem-agent/status | jq
curl -s -X POST http://127.0.0.1:8000/creem-agent/chat -H 'Content-Type: application/json' -d '{"message":"status"}' | jq
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:install
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem-agent:heartbeat
docker compose -f docker-compose.frankenphp.yml exec app php artisan creem:test-webhook checkout.completed
openclaw gateway
openclaw pairing list telegram
```

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
