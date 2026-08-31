# Scenario 2: Pure Laravel Agent Without OpenClaw

Read this scenario in two phases:

1. simulator-first validation
2. real Creem without OpenClaw

## Purpose

This scenario is for environments without `sudo`, without OpenClaw, or where you want the fastest possible demo. Everything runs from the current user account with the Laravel application as the only runtime.

The practical rule is the same: validate against the simulator first, then move to live Creem and public webhooks.

## Outcome

At the end of this scenario you will have:


## 1. Prepare the Environment

This scenario works on:


Required tools already available to the user:


If you are using VirtualBox, still use these recommended forwards:

  - Host `4040` -> Guest `4040` (optional; inspector is not published by default)

## 2. Get External Accounts and Tokens

### 2.1 Creem API key and webhook secret

1. Open Creem dashboard.
2. Copy a test API key from API Keys.
3. Later, after the `ngrok` container is running, create a webhook endpoint.
4. Copy the webhook signing secret.

Save:


### 2.2 Telegram bot token

1. Open Telegram.
2. Talk to `@BotFather`.
3. Run `/newbot`.
4. Follow the prompts.
5. Save the returned token.

Save:


### 2.3 Telegram chat ID

1. Start the bot.
2. Send it a message.
3. Call:

```bash
curl -s "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/getUpdates" | jq
```

4. Read `message.chat.id`.

Save:


### 2.4 ngrok authtoken

1. Sign in to ngrok.
2. Copy the authtoken from dashboard.

Save:


### 2.5 Optional Slack webhook

1. Create an Incoming Webhook in Slack.
2. Copy the webhook URL.

Save:


### 2.6 Optional Discord webhook

1. Create a Discord webhook on the target channel.
2. Copy the URL.

Save:


## 3. Install the Laravel Demo App

```bash
cd $HOME/php/run/laravel-creem-agent-demo
cp app/.env.example app/.env
bash scripts/install-app.sh
```

The demo composer file resolves package dependencies from GitHub VCS repositories over SSH. If the repos are private, configure SSH access first:

```bash
ssh -T git@github.com
```

Install dependencies:

```bash
bash scripts/install-simulator.sh
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
```

Current topology note: the checked-in compose file now runs the host Laravel runtime from `app/`, while `simulator/` remains optional. Treat `bash scripts/run-heartbeat.sh` as the runnable user-facing heartbeat flow for this repository today.

## 4. Configure `app/.env`

```env
APP_NAME="Creem Agent Demo"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

CREEM_API_KEY=creem_test_YOUR_KEY_HERE
CREEM_WEBHOOK_SECRET=whsec_YOUR_SECRET_HERE
CREEM_TEST_MODE=true

CREEM_AGENT_DEFAULT_STORE=default
CREEM_AGENT_OPENCLAW=false
CREEM_AGENT_TELEGRAM_MODE=laravel

CREEM_AGENT_TELEGRAM_TOKEN=YOUR_TELEGRAM_BOT_TOKEN
CREEM_AGENT_TELEGRAM_CHAT_ID=YOUR_TELEGRAM_CHAT_ID
CREEM_AGENT_SLACK_WEBHOOK=https://hooks.slack.com/services/XXX/YYY/ZZZ
CREEM_AGENT_DISCORD_WEBHOOK=https://discord.com/api/webhooks/XXX/YYY
```

## 5. Start ngrok in Docker

Provide the token to Docker Compose:

```bash
export NGROK_AUTHTOKEN=YOUR_NGROK_AUTHTOKEN
```

If you prefer to keep the token in the app `.env` instead, add `NGROK_AUTHTOKEN=your_token` to `.env` (do not commit) and run:

```bash
docker compose --env-file app/.env up -d ngrok
```

Or load `.env` into the current shell session before starting compose:

```bash
set -o allexport; source app/.env; set +o allexport
docker compose up -d ngrok
```

Start Laravel:

Prerequisite: finish the initial `app/.env` setup and run `bash scripts/install-app.sh` first.

```bash
cd $HOME/php/run/laravel-creem-agent-demo
docker compose up -d app queue scheduler
```

Start the tunnel container and read the public HTTPS URL:

```bash
docker compose up -d ngrok
# Read the public URL from ngrok container output; inspector is not published by default.
```

Take the public HTTPS URL. Example:

```text
https://example-5678.ngrok-free.app
```

Your Creem webhook URL becomes:

```text
https://example-5678.ngrok-free.app/creem/webhook
```

Register the Telegram bot webhook for Laravel-owned Telegram:

```bash
curl -s -X POST "https://api.telegram.org/bot$CREEM_AGENT_TELEGRAM_TOKEN/setWebhook" \
  -d "url=https://example-5678.ngrok-free.app/creem-agent/telegram/webhook"
```

## 6. Register Webhook in Creem

1. Open Creem dashboard.
2. Create webhook endpoint:

```text
https://YOUR-NGROK-URL/creem/webhook
```

3. Subscribe to:


4. Copy the webhook secret and put it into `CREEM_WEBHOOK_SECRET`.

## 7. Start the Agent Processes

Terminal 1:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose up -d app queue scheduler
```

Terminal 2:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose logs -f app queue scheduler
```

Terminal 3:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose up -d ngrok
# The public URL can be read from the ngrok container logs; the inspector is not published by default.
```

Terminal 4:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

## 8. Verify Core Functionality

Store status:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Chat endpoint:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/run-heartbeat.sh --all-stores
```

CLI chat:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose exec app php artisan creem-agent:chat "status"
```

## 8b. Interactive Telegram against simulator-backed data

If you want a real Telegram conversation while still using simulator data, use a dedicated Laravel host app connected to the simulator API.

Set simulator-backed API values in that Laravel host app:

```env
CREEM_TEST_MODE=true
CREEM_TEST_API_URL=http://simulator/api/v1
CREEM_API_URL=http://simulator/api/v1
CREEM_AGENT_TELEGRAM_MODE=laravel
CREEM_AGENT_TELEGRAM_TOKEN=YOUR_TELEGRAM_BOT_TOKEN
CREEM_AGENT_TELEGRAM_CHAT_ID=YOUR_TELEGRAM_CHAT_ID
```

For demo cadence, configure heartbeat in scheduler code, not in `.env`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('creem-agent:heartbeat --all-stores')
    ->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();
```

The Docker `scheduler` service already runs `schedule:run` every 60 seconds, so `everyMinute()` is the only change needed for minute-level demos.

After that, produce fresh simulator changes:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/install-simulator.sh
docker compose exec simulator \
  php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
docker compose exec simulator \
  php artisan simulator:send-webhook payment.failed
```

Then send Telegram messages like:

```text
status
recent transactions
any payment issues?
run heartbeat
```

Those requests are handled by the Laravel agent, while the underlying products, transactions, customers, and failures still come from simulator-backed API responses.

## 9. Verify Notifications

Telegram test:

```bash
curl -s "https://api.telegram.org/bot$CREEM_AGENT_TELEGRAM_TOKEN/sendMessage" \
  -d chat_id="$CREEM_AGENT_TELEGRAM_CHAT_ID" \
  -d text="Pure agent notification test" | jq
```

Agent-triggered test:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

## 10. Verify Webhooks

Internal test:

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Telegram message handling in this scenario stays on Laravel. OpenClaw is not part of the Telegram path.
