---
name: openclaw-laravel-creem-agent-skill
description: Forward OpenClaw requests to a local Laravel Creem Agent HTTP endpoint and optionally bridge Telegram polling through the same agent. Use when the user wants OpenClaw to delegate store-status, heartbeat, subscription, transaction, or customer monitoring requests to an already running Laravel Creem Agent service.
homepage: https://github.com/romansh/laravel-creem-agent-demo
metadata: { "openclaw": { "emoji": "🦞", "os": ["linux", "macos"], "requires": { "bins": ["curl"] }, "primaryEnv": "AGENT_URL", "envVars": [{ "name": "AGENT_URL", "required": false, "description": "Laravel Creem Agent chat endpoint. Defaults to http://127.0.0.1:8000/creem-agent/chat." }, { "name": "TELEGRAM_BOT_TOKEN", "required": false, "description": "Required only when running poll-telegram.sh for OpenClaw-managed Telegram polling." }], "dependencies": [{ "name": "curl", "type": "other", "repository": "https://curl.se/" }, { "name": "jq", "type": "other", "repository": "https://jqlang.org/" }], "links": { "homepage": "https://github.com/romansh/laravel-creem-agent-demo", "repository": "https://github.com/romansh/laravel-creem-agent-demo", "documentation": "https://github.com/romansh/laravel-creem-agent-demo/blob/main/openclaw-laravel-creem-agent-skill/README.md" } } }
---

# OpenClaw Laravel Creem Agent Skill

Use this skill when OpenClaw should hand off a user request to a Laravel Creem Agent that is already running locally and exposes an HTTP chat endpoint.

## When to use

Use this skill for requests such as:

- checking store status
- running heartbeat checks
- asking about subscriptions, transactions, products, or customers
- forwarding Telegram messages into the Laravel Creem Agent workflow

Do not use this skill when the Laravel endpoint is unavailable or when the task should be solved directly with OpenClaw's own tools.

## Prerequisites

- A running Laravel app exposing `http://127.0.0.1:8000/creem-agent/chat`, or `AGENT_URL` set to another endpoint
- `curl` installed
- `jq` installed only if you plan to run `poll-telegram.sh`

## How to invoke the agent bridge

Forward a natural-language request by running:

```bash
{baseDir}/run.sh "show current store status"
```

Or pipe text through stdin:

```bash
echo "run heartbeat" | {baseDir}/run.sh
```

The script posts JSON to the Laravel agent endpoint and returns the raw HTTP response body.

## Telegram polling mode

If the user wants OpenClaw-managed Telegram polling instead of Laravel webhooks:

1. Set `TELEGRAM_BOT_TOKEN`
2. Optionally set `AGENT_URL`
3. Run:

```bash
{baseDir}/poll-telegram.sh
```

This poller fetches Telegram updates, forwards message text to the Laravel agent, extracts the reply, and sends it back to Telegram.

## Laravel webhook mode

If Telegram should stay on the Laravel side, configure the bot webhook to hit `/creem-agent/telegram/webhook` instead of running the poller.

## Operational notes

- `run.sh` is the minimal stateless bridge for one-shot requests.
- `poll-telegram.sh` is a long-running helper and should only be used when Telegram ownership belongs to the OpenClaw side.
- If the endpoint returns structured JSON, OpenClaw should treat that as the authoritative response instead of rephrasing it unless the user asks for summarization.
