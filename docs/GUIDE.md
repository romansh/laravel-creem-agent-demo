# Building an Autonomous AI Agent for Creem Store Monitoring

## Introduction

Managing a SaaS business means tracking payments, subscriptions, and customer health around the clock. What if an AI agent could do this for you — monitoring your Creem store, detecting issues before they become problems, and reporting only when something meaningful happens?

This guide walks through building exactly that: an autonomous AI agent for Creem.io that implements the official HEARTBEAT.md specification, supports multiple stores, and runs proactively in the background.

For this repository, the practical reading order is:

1. Start with the simulator-first approach to validate heartbeat logic, webhook delivery, and Telegram behavior deterministically.
2. Then move to the real Creem approach for live keys, ngrok callbacks, and optional OpenClaw attachment.

That split matters because the checked-in demo compose topology is still simulator-centered.

## The Architecture

The system consists of three Laravel packages working together:

**romansh/laravel-creem** — The foundation. A published SDK that wraps every Creem API endpoint: products, checkouts, customers, subscriptions, transactions, discounts, and licenses. It handles webhooks with HMAC-SHA256 signature verification and dispatches Laravel events for each webhook type.

**romansh/laravel-creem-cli** — A CLI layer that mirrors the official `creem` CLI commands as Laravel Artisan commands. Every operation the official CLI supports — listing products, managing subscriptions, creating checkouts — is available through `php artisan creem:*` commands.

**romansh/laravel-creem-agent** — The brain. This package contains the heartbeat engine, proactive workflows, a natural language chat interface, and notification system.

### The CLI Facade Pattern

A key architectural decision was eliminating MCP (Model Context Protocol) in favor of a simpler CLI Facade. The `CreemCli` facade provides a unified interface that transparently routes operations to either:

1. The official `creem` CLI (if installed via Homebrew) — executed as shell commands with `--json` output
2. Our `laravel-creem-cli` Artisan commands — which call the SDK directly in-process

Detection is efficient: the facade checks for the native CLI once, caches the result for 24 hours, and falls back automatically if the CLI disappears. Consumers never know which backend handles their request.

## Implementing the Heartbeat

The heartbeat follows the official Creem HEARTBEAT.md specification — a 6-step cycle that runs periodically to detect store changes.

### Step 1: Load Previous State

Each store maintains a JSON state file at `storage/creem-agent/heartbeat-{store}.json`. On first run, the agent creates defaults and treats everything as new. If the file is corrupted (JSON parse failure), it's deleted and recreated — the next heartbeat becomes a "first run."

### Step 2: Check Transactions

The agent fetches the 20 most recent transactions and compares the newest transaction ID against the stored `lastTransactionId`. New transactions are extracted with amount, product, customer email, and type.

### Step 3: Check Subscription Health

This is the most detailed step. The agent queries subscriptions by each status (active, trialing, past_due, paused, canceled, expired, scheduled_cancel) and tracks individual subscription state transitions using a `knownSubscriptions` map.

Detecting that subscription `sub_abc123` moved from `active` to `past_due` is more actionable than knowing the past_due count increased by one.

### Step 4: Check Customers

A count comparison detects new customer signups.

### Step 5: Update State

State is written atomically — write to a temp file, then rename. This prevents data loss if the process crashes mid-write.

### Step 6: Report

The agent follows strict notification rules: notify on new sales, cancellations, payment failures, and customer signups. Stay silent when nothing changed. First heartbeat reports an initial snapshot summary rather than individual events.

## Multi-Store Support

Most Creem integrations handle a single store. Our agent monitors any number of stores independently, each with its own heartbeat frequency, state file, and notification channels.

Configuration maps store names to Creem SDK profiles. The default store is the first configured, but agents or users can switch stores from the chat interface ("switch to store secondary") or via configuration.

## Proactive Workflows

Beyond the heartbeat, five workflows trigger on detected changes:

1. **Failed Payment Recovery** — Alerts immediately when a subscription moves to `past_due`, with context about the customer and suggested follow-up.

2. **Churn Detection** — Flags when multiple cancellations occur in a single heartbeat cycle, indicating a potential churn spike that needs investigation.

3. **Revenue Digest** — Summarizes new transactions with total amounts — "3 new transactions totaling $89.97."

4. **New Customer Welcome** — Celebrates growth by notifying on new customer signups.

5. **Anomaly Detection** — Tracks historical subscription counts and alerts when active subscriptions drop more than 20% between checks.

## Natural Language Chat

The agent parses natural language through a pattern-matching CommandParser that maps messages to intents:

- "how many active subscriptions?" → query subscriptions (status: active)
- "any payment issues?" → query subscriptions (status: past_due)
- "switch to store secondary" → change active store
- "run heartbeat" → execute heartbeat cycle
- "cancel subscription sub_XXX" → cancel action

This works through both a CLI command (`creem-agent:chat`) and an HTTP endpoint for web widgets.

LLM configuration

The demo app exposes agent-side LLM routing through the `.env` file. In the current `.env.example`, the default configuration is `CREEM_AGENT_LLM_PROVIDER=openai` with `CREEM_AGENT_LLM_MODEL=gpt-4o-mini`, and the Gemini alternative is shown as commented lines right below it. Provider credentials are listed in the same file for OpenAI, Anthropic, Gemini, OpenRouter, and Ollama, plus `OPENAI_URL` and `OLLAMA_BASE_URL` for custom endpoints.

## Running as a Daemon

For autonomous operation on a VPS, the agent uses Laravel's scheduler:

```php
Schedule::command('creem-agent:heartbeat --all-stores')
    ->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();
```

For demos this is the recommended cadence so you do not wait 4 hours. It is not driven by `.env`; it is driven by the scheduler definition in the Laravel host app.

Combined with Laravel Octane (FrankenPHP), the application stays warm in memory. Queue workers process webhook-triggered jobs, and Supervisor ensures everything restarts on failure.

Process updates are graceful: `php artisan octane:reload` for zero-downtime code changes, and queue workers self-restart after their `max-time` expires.

## VirtualBox and Webhook Tunneling

For a reliable local setup, a VirtualBox VM is often the cleanest approach. It gives you an isolated Ubuntu environment that behaves like a small VPS while still running on a laptop or workstation.

The practical setup is:

1. Run Ubuntu inside VirtualBox
2. Forward host port `8000` to guest port `8000`
3. (Optional) If you want to access the ngrok inspector from the host, forward host port `4040` to guest port `4040`.
4. Run Laravel in Docker inside the guest
5. Start the bundled `ngrok` container to expose the app publicly
5. Point Creem webhooks to `https://YOUR-NGROK-URL/creem/webhook`

This solves the webhook problem without requiring a public VPS during development.

Treat it as the bridge between the two approaches:

- simulator-first for supported local verification
- real Creem for public callback verification once the dedicated agent host exists

Current repository caveat: the compose file in this repository uses the generated `creem-simulator` app as the shared Laravel runtime. The demo installer adds `laravel-creem-agent` into that app, so heartbeat is runnable directly through `bash scripts/run-heartbeat.sh`. The broader smoke verification flow remains `bash scripts/smoke-simulator-demo.sh`, with maintainers optionally layering package tests on top via `CREEM_AGENT_PACKAGE_REPO`.

If no VM is needed, the same Docker Compose stack can run on the local machine. If `sudo` is unavailable, this still works in current-user standard agent mode as long as Docker is already available to the user.

## OpenClaw Integration

The agent works fully standalone but supports optional OpenClaw integration. When enabled, OpenClaw can trigger heartbeats, route extended natural language queries, and use the agent as a registered tool in its gateway.

What was missing before was the concrete Telegram ownership model. In this project, OpenClaw is treated as an external runtime, while Laravel remains the source of truth for store state, heartbeat execution, and notifications.

### How OpenClaw connects here

Instead of MCP, the integration is intentionally simple:

1. OpenClaw reads current store state from `GET /creem-agent/status`
2. OpenClaw forwards human prompts to `POST /creem-agent/chat`
3. OpenClaw triggers proactive monitoring by running `php artisan creem-agent:heartbeat --all-stores`
4. OpenClaw owns Telegram through its native gateway channel when `CREEM_AGENT_TELEGRAM_MODE=openclaw`
5. OpenClaw can keep its own orchestration note in `~/.openclaw/workspace/HEARTBEAT.md`

This gives us a stable contract even if OpenClaw's own installation method changes.

In the current repository, treat this as the target contract for Approach 2. Approach 1 is now simulator smoke plus direct demo heartbeat verification, with optional maintainer package checks layered on top.

### What to install

Because this repository does not vendor OpenClaw itself, the operator installs OpenClaw separately. For this project, the intended privileged installation path is `openclaw/openclaw-ansible`, which provisions Docker, Node.js, pnpm, host hardening, and the OpenClaw daemon on Debian/Ubuntu.

That path requires root or `sudo`. When `sudo` is unavailable, the correct fallback is not to emulate the full host installer, but to run the Laravel agent directly from the current user account.

On the Laravel side, these environment variables are used when OpenClaw is present:

```env
CREEM_AGENT_OPENCLAW=true
CREEM_AGENT_TELEGRAM_MODE=openclaw
OPENCLAW_TELEGRAM_BOT_TOKEN=123:abc
OPENCLAW_ENDPOINT=http://127.0.0.1:4110
OPENCLAW_WORKSPACE_PATH=/home/roman/.openclaw/workspace
OPENCLAW_SHARED_SECRET=change-me
```

Then render a starter Telegram channel snippet from Laravel:

```bash
php artisan creem-agent:openclaw-telegram-config
```

Add that snippet to the OpenClaw gateway config, run `openclaw gateway`, and approve the first Telegram DM with `openclaw pairing approve telegram <CODE>`.

If OpenClaw is not present, switch to the Laravel-owned Telegram path instead:

```env
CREEM_AGENT_OPENCLAW=false
CREEM_AGENT_TELEGRAM_MODE=laravel
CREEM_AGENT_TELEGRAM_TOKEN=123:abc
CREEM_AGENT_TELEGRAM_CHAT_ID=123456789
```

In that mode Telegram should point to `/creem-agent/telegram/webhook`, not to OpenClaw.

### Minimal verification

```bash
cd /home/roman/php/run/laravel-creem-agent-demo
bash scripts/smoke-simulator-demo.sh
bash scripts/run-heartbeat.sh --all-stores
```

Optional maintainer-only package verification:

```bash
CREEM_AGENT_PACKAGE_REPO=/path/to/laravel-creem-agent bash scripts/smoke-simulator-demo.sh
```

If the smoke runner and direct heartbeat command pass, simulator delivery, direct heartbeat execution, and Telegram outbound-send smoke are covered. Maintainers can layer package-level Telegram send/receive checks on top when a separate `laravel-creem-agent` checkout is available. A future dedicated `agent-app` service can then add isolated HTTP E2E on top.

## Edge Cases That Matter

- **Rate limiting**: Creem's API is generous, but heartbeats are capped at hourly. Each cycle makes 5-8 API calls.
- **API outages**: Failed API calls are caught per-step — if transactions can't be fetched, the heartbeat continues with subscriptions and customers.
- **CLI failover**: If the native CLI is uninstalled between checks, the facade invalidates its cache and transparently switches to the SDK backend.
- **Quiet hours**: Non-critical notifications are suppressed between 11 PM and 7 AM, configurable per deployment.
- **Duplicate alerts**: The same issue isn't re-alerted within 6 hours.

## Conclusion

An AI agent doesn't need complex protocol layers to be effective. By building on the Creem SDK, following the official HEARTBEAT.md spec, and using Laravel's notification and scheduling systems, we get an autonomous monitor that just works — detecting store changes, alerting on issues, and answering questions in natural language.

The complete source is in three packages: `romansh/laravel-creem-cli`, `romansh/laravel-creem-agent`, and the demo at `romansh/laravel-creem-agent-demo`.
