---
name: openclaw-laravel-creem-agent-skill
description: Forward OpenClaw requests to a local Laravel Creem Agent HTTP endpoint. Use when OpenClaw should delegate store-status, heartbeat, subscription, transaction, or customer monitoring requests to an already running Laravel Creem Agent service.
metadata: { "openclaw": { "emoji": "🦞", "os": ["linux", "macos"], "requires": { "bins": ["curl"] }, "primaryEnv": "AGENT_URL", "envVars": [{ "name": "AGENT_URL", "required": false, "description": "Laravel Creem Agent chat endpoint. Defaults to http://127.0.0.1:8000/creem-agent/chat." }], "dependencies": [{ "name": "curl", "type": "other", "repository": "https://curl.se/" }], "links": { "homepage": "https://github.com/romansh/laravel-creem-agent-demo", "repository": "https://github.com/romansh/laravel-creem-agent-demo", "documentation": "https://github.com/romansh/laravel-creem-agent-demo/blob/main/openclaw-laravel-creem-agent-skill/README.md" } } }
---

# OpenClaw Laravel Creem Agent Skill

Use this skill when OpenClaw should hand off a user request to a Laravel Creem Agent that is already running locally and exposes an HTTP chat endpoint.

## When to use

Use this skill for requests such as:

- checking store status
- running heartbeat checks
- asking about subscriptions, transactions, products, or customers

Do not use this skill when the Laravel endpoint is unavailable or when the task should be solved directly with OpenClaw's own tools.

## Prerequisites

- A running Laravel app exposing `http://127.0.0.1:8000/creem-agent/chat`, or `AGENT_URL` set to another endpoint
- `curl` installed

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

## Telegram ownership

When `CREEM_AGENT_TELEGRAM_MODE=openclaw`, configure Telegram in OpenClaw gateway using the native channel model from `https://docs.openclaw.ai/channels/telegram`.

When `CREEM_AGENT_TELEGRAM_MODE=laravel`, keep Telegram on Laravel through `/creem-agent/telegram/webhook`.

## Operational notes

- `run.sh` is the minimal stateless bridge for one-shot requests.
- If the endpoint returns structured JSON, OpenClaw should treat that as the authoritative response instead of rephrasing it unless the user asks for summarization.
