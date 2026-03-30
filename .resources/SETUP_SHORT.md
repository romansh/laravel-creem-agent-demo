# Demo Flow

## Credentials

Collect before starting:

| What | Where |
|------|-------|
| Creem API key `creem_test_...` | Creem Dashboard → API Keys |
| Creem webhook secret `whsec_...` | Creem Dashboard → Webhooks |
| Telegram bot token | @BotFather → /newbot |
| Telegram private chat ID | `curl -s "https://api.telegram.org/bot<TOKEN>/getUpdates" \| jq '.result[0].message.chat.id'` |
| ngrok authtoken | ngrok.com → Dashboard → Auth |
| OpenAI API key (optional, for LLM) | platform.openai.com → API keys |

In a direct private chat with the bot, Telegram returns the same numeric ID in both `message.chat.id` and `message.from.id`, so OpenClaw `allowFrom` can reuse `CREEM_AGENT_TELEGRAM_CHAT_ID`. Do not assume that for groups or channels.

For the simulator-only phases, no real Creem keys needed — defaults (`creem_test_demo_default` / `whsec_demo_default`) work out of the box.

## VirtualBox host setup

Create a shared folder named `repo_run` pointing to the host repo root and forward the ports below:

```bash
VBoxManage sharedfolder add "VM-name" --name repo_run --hostpath "$HOME/php/run" --automount
VBoxManage modifyvm "VM-name" --natpf1 "ssh,tcp,,2222,,22"
VBoxManage modifyvm "VM-name" --natpf1 "http8000,tcp,,8000,,8000"
```

Host notes:

- `8000 -> 8000` lets `http://localhost:8000` on the host reach the guest app.
- `2222 -> 22` lets you SSH into the guest with `ssh -p 2222 user@localhost`.

## Guest one-time VM prep

Run this inside the Ubuntu guest:

```bash
sudo apt update
sudo apt install -y virtualbox-guest-dkms virtualbox-guest-utils ca-certificates curl gnupg lsb-release git
sudo mkdir -p /home/www
sudo usermod -aG vboxsf $USER
sudo mount -t vboxsf -o uid=$(id -u),gid=$(id -g) repo_run /home/www || true

sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

Log out and back in after adding the user to the `docker` and `vboxsf` groups.

## Part 1: Bootstrap from the shared checkout

Everything below runs inside the guest from `/home/www`.

```bash
cd /home/www
cp app/.env.example app/.env
bash scripts/install-app.sh
bash scripts/install-simulator.sh
docker compose build app queue scheduler simulator
docker compose run --rm app php artisan migrate --force
docker compose up -d simulator
```

Rules for this flow:

- `app/.env` is the source of truth.
- If shared app/simulator values change, rerun `bash scripts/install-simulator.sh`.
- Run migrations explicitly before `docker compose up -d ...`.
- Do not start `app`, `queue`, `scheduler`, or `ngrok` before Part 1 completes.

## Part 2: Simulator-first verification

```bash
cd /home/www
bash scripts/smoke-simulator-demo.sh
docker compose exec simulator php artisan simulator:seed-demo --reset
bash scripts/run-heartbeat.sh --all-stores
docker compose exec simulator php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
bash scripts/run-heartbeat.sh --force --all-stores
```

This proves the checked-in stack works before public Telegram or OpenClaw wiring is added. In this phase, `--send-webhooks` exercises the internal simulator -> app webhook path; it does not require Telegram yet.

## Part 3: Laravel Telegram + ngrok path

```bash
cd /home/www
docker compose up -d app queue scheduler simulator ngrok

set -o allexport
source app/.env
set +o allexport

curl -s -X POST "https://api.telegram.org/bot${CREEM_AGENT_TELEGRAM_TOKEN}/setWebhook" \
	-d "url=${NGROK_URL}/creem-agent/telegram/webhook"

docker compose exec simulator php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
bash scripts/run-heartbeat.sh --force --all-stores
```

Telegram checks:

- `status`
- `recent transactions`
- `any payment issues?`
- after `simulator:advance --send-webhooks`, confirm that Telegram receives the expected notification path

## Part 4: OpenClaw Telegram path

Install OpenClaw inside the guest:

```bash
sudo apt update
sudo apt install -y ansible git
curl -fsSL https://raw.githubusercontent.com/openclaw/openclaw-ansible/main/install.sh | bash
sudo su - openclaw
openclaw onboard --install-daemon
openclaw status
exit
```

### Install the skill

```bash
sudo su - openclaw
openclaw skills install openclaw-laravel-creem-agent-skill
```

The current checked-in version of the skill bundle in this demo repo lives at `/home/www/openclaw-laravel-creem-agent-skill`.

### Configure OpenClaw gateway

Edit `~/.openclaw/openclaw.json`:

```json5
{
  gateway: {
    sharedSecret: "change-me"
  },
  channels: {
    telegram: {
      botToken: "YOUR_BOT_TOKEN",
      allowFrom: ["YOUR_TELEGRAM_CHAT_ID"]
    }
  },
  skills: {
    entries: {
      "openclaw-laravel-creem-agent-skill": {
        enabled: true
      }
    }
  }
}
```

For the checked-in local demo stack, the skill is fixed to `http://127.0.0.1:8000/creem-agent/chat` and does not require extra skill env configuration.

Set `gateway.sharedSecret` to the same value you use for `OPENCLAW_SHARED_SECRET` in `app/.env`.

If the file already contains the placeholder values shown above, you can replace them automatically from `app/.env` with one command:

```bash
cd /home/www
set -a && source app/.env && set +a && sudo su - openclaw -c 'OPENCLAW_TELEGRAM_BOT_TOKEN="$OPENCLAW_TELEGRAM_BOT_TOKEN" OPENCLAW_SHARED_SECRET="$OPENCLAW_SHARED_SECRET" CREEM_AGENT_TELEGRAM_CHAT_ID="$CREEM_AGENT_TELEGRAM_CHAT_ID" perl -0pi -e '\''s|botToken:\s*"YOUR_BOT_TOKEN"|qq{botToken: "$ENV{OPENCLAW_TELEGRAM_BOT_TOKEN}"}|ge; s|allowFrom:\s*\["YOUR_TELEGRAM_CHAT_ID"\]|qq{allowFrom: ["$ENV{CREEM_AGENT_TELEGRAM_CHAT_ID}"]}|ge; s|sharedSecret:\s*"change-me"|qq{sharedSecret: "$ENV{OPENCLAW_SHARED_SECRET}"}|ge'\'' ~/.openclaw/openclaw.json'
```

This only replaces the placeholder strings from the example block. If you already edited `~/.openclaw/openclaw.json` manually to other values, update it by hand instead.

### Switch the app into OpenClaw mode

All OpenClaw keys are already present in `app/.env` (copied from `app/.env.example`). You only need to flip the two flags and fill in your real values:

```bash
exit  # back to normal user
cd /home/www
```

Open `app/.env` and update these lines:

```dotenv
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw

# Set to your real Telegram bot token (same as CREEM_AGENT_TELEGRAM_TOKEN if you use one bot)
OPENCLAW_TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN
# Secret must match the value in openclaw.json → gateway.sharedSecret
OPENCLAW_SHARED_SECRET=change-me
```

OpenClaw `allowFrom` should use the same private-chat value as `CREEM_AGENT_TELEGRAM_CHAT_ID`.

`OPENCLAW_ENDPOINT` and `OPENCLAW_WORKSPACE_PATH` already point to the correct defaults and only need adjusting if you installed OpenClaw in a non-standard location.

After editing `.env`, restart the agent services (no rebuild needed):

```bash
docker compose up -d app queue scheduler
```

### Start gateway and verify

```bash
sudo su - openclaw
openclaw gateway
# In another terminal:
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

Test from Telegram:
- `status` — should return store overview
- `how many subscriptions?` — should return count
- `how's the store doing?` — should classify as status via LLM fallback and return overview
- `show me products` — should list products
- `which customer paid the most today?` — OpenClaw chains transactions → sorts → answers (multi-step)

Then run the deeper scenario:

```bash
cd /home/www
docker compose exec simulator php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
bash scripts/run-heartbeat.sh --force --all-stores
```

## Part 5: Webhooks and real Creem

After simulator-first and Telegram/OpenClaw checks are green:

```bash
cd /home/www
docker compose exec simulator php artisan simulator:send-webhook payment.failed
docker compose exec simulator php artisan simulator:send-webhook subscription.canceled

bash scripts/run-heartbeat.sh --force --all-stores
docker compose exec app php artisan creem-agent:chat "status"
```

Then replace simulator values in `app/.env` with real Creem keys and the real webhook secret, keeping the same Telegram mode.

## Part 6: LLM check

```bash
cd /home/www
docker compose exec app php artisan tinker --execute="dump(filled(config('ai.providers.openai.key')));"
docker compose exec app php artisan creem-agent:chat "status"
docker compose exec app tail -n 50 storage/logs/laravel.log
```

If logs show `cURL error 6: Could not resolve host: api.openai.com`, recreate the `app` container after fixing the container network problem instead of rotating provider keys.

## LLM Model & Token Budget

The agent uses OpenAI for intent classification when the rule parser returns `unknown`. This consumes tokens but is needed for natural language requests like "how's it going?" or "show me the dashboard".

### Choosing a model (in app/.env)

```bash
# Provider and model for the intent classifier
CREEM_AGENT_LLM_PROVIDER=openai
CREEM_AGENT_LLM_MODEL=gpt-4.1-mini

# OpenAI key used by Laravel AI SDK (set once, used by the classifier)
OPENAI_API_KEY=sk-...
```

**Token-saving tips:**

1. **Use `gpt-4.1-mini`** — cheapest model that handles intent classification well. The classifier prompt is small (~600 tokens) and the response is a single JSON line (~30 tokens).
2. **Standard phrases cost zero tokens** — when the rule parser matches (e.g. `status`, `help`, `recent transactions`), no LLM call is made. Teach users the standard phrases.
3. **Disable LLM entirely** — set `CREEM_AGENT_LLM_ENABLED=false` in `app/.env` to use only the rule parser. Natural-language rephrasing will return "I didn't understand that" but standard phrases will still work.
4. **Timeout** — `CREEM_AGENT_LLM_TIMEOUT=15` (seconds). Lower values save money on hanging requests.

### How it works

```
User message
    ↓
CommandParser (regex rules, zero cost)
    ↓ if intent == unknown
LlmCommandParser (OpenAI API call, ~630 tokens)
    ↓ if intent == unknown
"I didn't understand that. Type 'help' to see what I can do."
```

The rule parser handles: `status`, `help`, `how many subscriptions`, `payment issues`, `recent transactions`, `products`, `how many customers`, `switch to store X`, `cancel sub_XXX`, `create checkout for prod_XXX`, `heartbeat`, `run heartbeat`.

Only truly free-form phrases like "how's the store doing?" or "what's going on with payments?" hit the LLM.

### Per-request cost estimate (gpt-4.1-mini)

- Input: ~630 tokens × $0.40/1M = $0.00025
- Output: ~30 tokens × $1.60/1M = $0.00005
- **Total: ~$0.0003 per LLM-classified request**
