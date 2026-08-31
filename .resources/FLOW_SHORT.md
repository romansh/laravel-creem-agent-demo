# Demo Flow

Canonical single-path runbook for the contest demo video. The VirtualBox VM + Docker-in-VM path below is the only canonical sequence to use for recording; follow it step-by-step inside the guest.

## VirtualBox — canonical video path (host)

Host steps (create shared folder and NAT port forwards exactly as below):

```bash
# Create a shared-folder named 'repo_run' pointing at your host repo root
# (or use the VirtualBox GUI). Example port forwards via VBoxManage:
VBoxManage sharedfolder add "VM-name" --name repo_run --hostpath /home/roman/php/run --automount
VBoxManage modifyvm "VM-name" --natpf1 "ssh,tcp,,2222,,22"
VBoxManage modifyvm "VM-name" --natpf1 "http8000,tcp,,8000,,8000"
```

Notes for host:

- Forward `8000->8000` so `http://localhost:8000` on host reaches the guest app.
- Forward `2222->22` to SSH into the guest via `ssh -p 2222 user@localhost` if desired.

## Docker — operate inside the VM (guest)

All Docker work for the video is performed inside the VirtualBox guest. SSH into the VM or use the VirtualBox console and run the following commands from the shared checkout mounted at `/home/www/laravel-creem-agent-demo`.

Guest quick-start (run these inside the VM):

```bash
# on guest (SSH or VirtualBox console)
cd /home/www/laravel-creem-agent-demo
# install guest tools if not present (one-time inside VM)
sudo apt update
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils
# mount the shared folder (if not auto-mounted)
sudo mkdir -p /home/www
sudo usermod -aG vboxsf $USER
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /home/www || true

# Install Docker inside the VM (one-time)
```bash
# prerequisites and Docker install (Ubuntu)
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

# build images (only when Dockerfile changes)
docker compose build app queue scheduler simulator
# only after Part 1 below completes:
# docker compose up -d app queue scheduler simulator
# docker compose --env-file app/.env up -d ngrok
# docker compose logs -f app
# docker compose exec app bash
# bash scripts/run-heartbeat.sh --force --all-stores

# trigger heartbeat manually from guest after bootstrap
bash scripts/run-heartbeat.sh --force --all-stores
```

Tips:

- Use `docker compose ps` and `docker compose logs` inside the VM to verify services.
- If you change `.env` inside the VM, run `php artisan config:clear` inside the app container and restart the `app` service.
- Keep the host ports forwarded (8000, 2222) so recordings and host checks can reach the guest.
- Avoid running `docker compose` on the host to prevent port conflicts during the recording.

## Part 1: One-time setup (run inside the VM)

Perform the one-time project setup steps from inside the guest (in `/home/www/laravel-creem-agent-demo`):

```bash
cp app/.env.example app/.env
bash scripts/install-app.sh
bash scripts/install-simulator.sh
docker compose run --rm app sh -lc '
  git config --global --add safe.directory /app &&
  composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/agent-app/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate --force &&
  php artisan octane:install --server=frankenphp --no-interaction
'
docker compose run --rm simulator sh -lc '
  git config --global --add safe.directory /app &&
  composer install --no-interaction &&
  test -f .env || cp .env.example .env &&
  sqlite_db="${DB_DATABASE:-/var/lib/simulator/database.sqlite}" &&
  mkdir -p "$(dirname "$sqlite_db")" &&
  touch "$sqlite_db" &&
  php artisan key:generate --force &&
  php artisan migrate --seed &&
  php artisan octane:install --server=frankenphp --no-interaction
'
docker compose up -d simulator
```

## Part 2: Simulator-first verification (inside VM)

```bash
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
docker compose exec simulator php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
bash scripts/run-heartbeat.sh --force --all-stores
```

This proves the checked-in stack works before any public Telegram or OpenClaw wiring is added.

Do not start `app`, `queue`, `scheduler`, `simulator`, or `ngrok` manually until Part 1 has completed.

## Part 3: Public Telegram / ngrok path (inside VM)

For Laravel-owned Telegram (only follow this after the simulator-first checks succeed):

```bash
docker compose up -d app queue scheduler simulator
docker compose --env-file app/.env up -d ngrok

set -o allexport; test -f app/.env && source app/.env || true; set +o allexport
test -n "${NGROK_URL:-}" || { echo "NGROK_URL not set in .env"; false; }

curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/setWebhook" \
  -d "url=${NGROK_URL}/creem-agent/telegram/webhook"
```

Then test via Telegram messages such as `status`, `recent transactions`, and `any payment issues?`.

## Part 4: OpenClaw Telegram path (inside VM)

Use this when the contest demo must show native OpenClaw Telegram ownership. Prereqs: simulator-first checks passed and a VM with `sudo` (OpenClaw installs in the guest).

Install OpenClaw inside the VM:

```bash
sudo apt update && sudo apt install -y ansible git
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
sudo su - openclaw
openclaw onboard --install-daemon
openclaw status
```

Switch the app to OpenClaw mode (inside VM):

```bash
cat >> app/.env <<'EOF'
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw
OPENCLAW_TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/openclaw/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
EOF

bash scripts/install-simulator.sh
docker compose up -d app queue scheduler simulator
docker compose exec app php artisan creem-agent:openclaw-telegram-config
openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

In this mode:

- Telegram belongs to OpenClaw gateway
- Laravel still owns heartbeat, webhook handling, state, and chat endpoints
- the main verification remains `bash scripts/run-heartbeat.sh --force --all-stores`

<!-- For the video flow there are no alternate branches; follow the VirtualBox + VM Docker path above. -->

## Part 5: Webhooks and real Creem (inside VM)

After the simulator-first and Telegram path you need are green:

```bash
docker compose exec simulator php artisan simulator:send-webhook payment.failed
docker compose exec simulator php artisan simulator:send-webhook subscription.canceled
```

Then switch `.env` from simulator values to real Creem keys and webhook secret, keep the same chosen Telegram mode, and rerun:

```bash
bash scripts/run-heartbeat.sh --force --all-stores
docker compose exec app php artisan creem-agent:chat "status"
```

## Part 6: LLM check (inside VM)

`CREEM_AGENT_LLM_PROVIDER` and `CREEM_AGENT_LLM_MODEL` only select the Laravel AI route. The actual provider secret must still be present in the standard Laravel AI env keys such as `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, or `OPENROUTER_API_KEY`.

If chat falls back even though the key is present, inspect the live container instead of the host shell:

```bash
docker compose exec app php artisan tinker --execute="dump(filled(config('ai.providers.openai.key')));"
docker compose exec app php artisan creem-agent:chat "status"
docker compose exec app tail -n 50 storage/logs/laravel.log
```

If logs show `cURL error 6: Could not resolve host: api.openai.com`, the key is not the problem. The `app` container has a Docker DNS issue and must be recreated after fixing any port conflict on `8000`.