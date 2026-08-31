# Demo Script — Step-by-Step Video Recording Guide

## Pre-Recording Checklist

Recording order:

1. Approach 1: simulator-first
2. Approach 1A: simulator-first + OpenClaw, if needed
3. Approach 2: real Creem

Reference runbooks:

- `.resources/SCENARIO_1_OPENCLAW_FULL.md`
- `.resources/SCENARIO_2_PURE_AGENT_FULL.md`
- `docs/SCENARIO_1_OPENCLAW_SHORT.md`
- `docs/SCENARIO_2_PURE_AGENT_SHORT.md`

- [ ] Simulator smoke runner passes: `bash scripts/smoke-simulator-demo.sh`
- [ ] Host app installed into `./app`: `bash scripts/install-app.sh`
- [ ] Simulator package installed into `./simulator`: `bash scripts/install-simulator.sh`
- [ ] `app/.env` configured with Creem test API key
- [ ] Direct demo heartbeat works: `bash scripts/run-heartbeat.sh --all-stores`
- [ ] Decide demo mode: `OpenClaw + native Telegram gateway` or `current-user Laravel Telegram webhook`
- [ ] If using VirtualBox, VM is up and host port forwards are configured
- [ ] If using real webhooks, the `ngrok` container is running and public URL is ready

Tip: if you prefer storing the token in `app/.env`, add `NGROK_AUTHTOKEN=your_token` there (do not commit). Start the tunnel with:

```bash
docker compose --env-file app/.env up -d ngrok
```
- [ ] Terminal visible with good font size
- [ ] Slack/Telegram channel open in browser
 - [ ] Telegram channel open in browser
- [ ] Delete existing state files for clean demo: `rm -rf storage/creem-agent/*.json`

## Demo Approaches
# Host-side note: VirtualBox should forward 8000 -> guest 8000. Forwarding 4040 is optional if you need the ngrok inspector on the host.
### Approach 1A or 2A: OpenClaw overlay

Use this when a sudo-capable Debian/Ubuntu VM is available.

Show briefly:

```bash
git clone https://github.com/openclaw/openclaw-ansible.git
cd openclaw-ansible
./run-playbook.sh
sudo su - openclaw
openclaw onboard --install-daemon
openclaw status
```

if demonstrating from VirtualBox, also show:

```bash
# Host-side note: VirtualBox should forward 8000 -> guest 8000. Forwarding 4040 is optional for ngrok inspector.
export NGROK_AUTHTOKEN=YOUR_NGROK_AUTHTOKEN
docker compose up -d ngrok
```

Before the live Telegram step, also show:

Prerequisite: by this point the one-time setup must already be finished, meaning `app/` and `simulator/` exist locally and were initialized with `bash scripts/install-app.sh` plus `bash scripts/install-simulator.sh`.

```bash
php artisan creem-agent:openclaw-telegram-config
openclaw gateway
openclaw pairing list telegram
openclaw pairing approve telegram <CODE>
```

### Approach 1: Simulator-first without OpenClaw

Use this on restricted hosts or local shells without `sudo`.

Run the whole demo from the current user:

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
```

Talking point: "If root privileges are unavailable, the Laravel agent still works as a standard current-user process. The OpenClaw daemon layer is optional, not required for the product flow."
Talking point: "In this mode I prove the product through the simulator smoke and package tests first, then move to live Creem only if needed."

## Environment Setup Shot (1 min)

If using VirtualBox, mention:

- Ubuntu guest inside VirtualBox
- Current compose runs the host app from `app/` and keeps `simulator/` as a separate optional runtime
- The recording is structured as two approaches: simulator-first now, real Creem second
- bundled Docker `ngrok` used to expose `/creem/webhook` publicly

If using a local machine without VM, mention that the same Docker Compose stack exposes the local app through the `ngrok` container.

Mention that `app/` is generated locally by `bash scripts/install-app.sh` as the dedicated Laravel host runtime.
Mention that `simulator/` is generated locally by `bash scripts/install-simulator.sh` from the Packagist package `romansh/laravel-creem-store-simulator`.

## Scene 1: Fresh Install (2 min)

```bash
# Show the packages
composer show romansh/*

# Run the current supported checks
cd $HOME/php/run/laravel-creem-agent-demo && bash scripts/smoke-simulator-demo.sh
cd $HOME/php/run/laravel-creem-agent-demo && bash scripts/run-heartbeat.sh --all-stores
```

Before invoking the install wizard in a fresh checkout, ensure the generated host app and simulator are present and synchronized:

```bash
bash scripts/install-app.sh
bash scripts/install-simulator.sh
```

Confirm `config/creem.php` contains a `profiles.default.api_key` entry (or set `CREEM_API_KEY` in `.env`).

Note: Octane server

If the demo uses FrankenPHP/Octane, ensure the app `.env` contains:

```bash
printf "\nOCTANE_SERVER=frankenphp\n" >> app/.env
```


**Talking point**: "This is the setup wizard — it verifies API connectivity, detects whether the native Creem CLI is installed, and configures notification channels."

## Scene 2: First Heartbeat (2 min)

```bash
cd $HOME/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

**Talking point**: "The first heartbeat creates an initial snapshot of the store — customers, subscriptions, transactions. It now runs directly from the checked-in demo stack, without a separate package checkout."

Show the Telegram notification that arrives.

## Scene 3: CLI Verification (1 min)

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

**Talking point**: "The CLI works identically whether using the native brew CLI or our Laravel SDK backend."

## Scene 4: Chat Interface (2 min)

This scene requires a dedicated Laravel host app connected to `http://simulator/api/v1`. The checked-in demo repo alone does not expose the interactive chat endpoints.

```bash
# Ask questions
php artisan creem-agent:chat "how many active subscriptions?"
php artisan creem-agent:chat "any payment issues?"
php artisan creem-agent:chat "recent transactions"
php artisan creem-agent:chat "status"
php artisan creem-agent:chat "help"
```

**Talking point**: "The agent understands natural language queries and routes them to the appropriate handler."

## Scene 5: Multi-Store (1 min)

```bash
# Switch stores
php artisan creem-agent:chat "switch to store secondary"
php artisan creem-agent:chat "status"
php artisan creem-agent:chat "switch to store default"
```

**Talking point**: "Multiple Creem stores can be monitored independently, each with its own heartbeat frequency and notification channels."

## Scene 6: Webhook + Change Detection (2 min)

```bash
cd $HOME/php/run/laravel-creem-agent-demo
docker compose exec simulator \
	php artisan simulator:advance --sales=3 --new-customers=2 --past-due=2 --cancellations=1 --send-webhooks
docker compose exec simulator \
    php artisan simulator:send-webhook payment.failed
bash scripts/smoke-simulator-demo.sh
```

**Talking point**: "Here I add new payments and payment failures directly in the simulator, then the next heartbeat detects them and the Telegram notification reflects those changes."

If a dedicated simulator-backed agent host is available, send Telegram messages right after that heartbeat:

```text
status
recent transactions
any payment issues?
run heartbeat
```

Show notifications in Telegram.

## Scene 7: Agent Status (1 min)

```bash
php artisan creem-agent:status
```

**Talking point**: "The status command shows the complete picture — CLI backend, all stores, last heartbeat time, subscription health."

## Scene 8: Proactive Workflows (2 min)

**Talking point**: "Beyond heartbeats, the agent runs proactive workflows — churn detection, failed payment recovery, revenue digests, and anomaly detection."

```bash
# Show the workflow code briefly in editor
# Then demonstrate with a forced heartbeat that has changes
bash scripts/run-heartbeat.sh --force --all-stores
```

## Scene 9: Daemon Mode (1 min)

```bash
# Show the runtime definition
cat docker-compose.yml
```

**Talking point**: "The scheduler loop already ticks every 60 seconds. For demo mode I set the heartbeat schedule itself to `everyMinute()` in Laravel scheduler code, not in `.env`, so I don't wait 4 hours between cycles."

## Scene 10: OpenClaw Attachment or No-Sudo Fallback (1 min)

### Option A: OpenClaw attached

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
docker compose exec app php artisan creem-agent:openclaw-telegram-config
openclaw gateway
openclaw pairing list telegram
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
```

Talking point: "With OpenClaw installed through openclaw-ansible, Telegram belongs to the native OpenClaw gateway channel, while Laravel remains the execution surface for state, chat, and heartbeat."

### Option B: No-sudo fallback

Talking point: "On hosts without sudo, I keep the exact same business flow using the standard Laravel agent from the current user account, and Telegram is handled through the Laravel webhook endpoint."

## Scene 11: Wrap-Up (1 min)

**Talking point**: "This agent uses the official Creem HEARTBEAT.md spec, supports multiple stores, transparent CLI failover, proactive workflows, and runs autonomously on any VPS."

## Total Runtime: ~15 minutes
