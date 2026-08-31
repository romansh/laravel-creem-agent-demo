# OpenClaw + Creem AI Agent Worker - Bounty Submission

## Summary

Imagine hiring a tireless SaaS operations worker who never takes a break, catches every failed payment before your customers complain, and answers "how's the store doing?" in Telegram at any hour of the night.

That's what this project delivers. Four modular Laravel packages - SDK, CLI, Agent, and Simulator - snap together into a platform that monitors your Creem store 24/7, detects churn and failed transactions as they happen, responds to natural-language questions through Telegram and OpenClaw, and proactively pings you via Telegram, Slack, or email when something needs attention.

Architecture diagram:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic0.png

## Links

- Video demo: https://www.youtube.com/watch?v=A3MJYFus9z8
- Written guide: https://dev.to/USERNAME/ARTICLE_LINK_TBD
- GitHub repo - laravel-creem: https://github.com/romansh/laravel-creem
- GitHub repo - laravel-creem-agent: https://github.com/romansh/laravel-creem-agent
- GitHub repo - laravel-creem-agent-demo: https://github.com/romansh/laravel-creem-agent-demo
- GitHub repo - laravel-creem-cli: https://github.com/romansh/laravel-creem-cli
- OpenClaw skill install command: `openclaw skills install openclaw-laravel-creem-agent-skill`

## Architecture

```text
Entry Points: Telegram / OpenClaw / CLI / curl / Cron
        v
   Agent Manager
   |- CommandParser (regex, zero cost)
   \- LLM fallback (AI, low cost per request)
        v
   Intent Router -> query / heartbeat / cancel / checkout
        v
   CreemCliManager (dual-driver: native CLI or SDK)
        v
   Creem API <-> Creem Simulator (deterministic testing)
```

## Requirements Checklist

- [x] Video 10-15 min - screen recording with the agent reacting to live events end-to-end
- [x] Written guide 1,500-2,500 words - Dev.to article with architecture diagrams, curl examples, and model selection notes
- [x] OpenClaw agent + Creem API - OpenClaw skill on ClawHub plus full Creem SDK integration
- [x] Heartbeat pattern - `HeartbeatRunner` with state persistence, change detection, and severity classification
- [x] 3+ proactive workflows - Failed Payment Recovery, Churn Detection, Revenue Digests, Anomaly Detection, New Customer Welcome
- [x] Notifications beyond console - Telegram Bot API, Slack, and Email via Laravel notification channels
- [x] Natural language queries - two-tier parser: regex rules plus LLM classifier (OpenAI/Anthropic/Ollama)
- [x] Error handling / graceful recovery - auto-retry, timeout handling, LLM bypass mode, dual CLI driver fallback

## Key Differentiators

### 1. Modular Platform, Not a Script

Four independent Composer packages. Use the SDK alone, add the CLI for shell scripting, or compose the full agent for monitoring. Each layer works independently.

### 2. Creem Simulator - No Real API Keys Required

The star of the demo is a full standalone mock of the Creem API running as a Docker service.

Seed a realistic store in seconds, then advance the scenario with surgical precision.

Simulator seeding screenshot:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic1.png

Scenario advance with webhook delivery:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic2.png

Signed webhook loopback fires the complete notification pipeline. The agent does not know it is talking to a simulator. Same HMAC-SHA256 signatures, same payload structure, same workflows.

Telegram real-time sale alert:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic3.png

Complex scenario with multiple event types:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic4.png

Telegram batch alerts:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic5.png

### 3. Two-Tier AI - Smart and Efficient

Rule-based parsing handles about 80% of queries at zero cost. LLM fallback catches free-form natural language. You can disable LLM entirely for pure rule-based mode.

Model selection insight: `gpt-4o-mini` ignored OpenClaw skill instructions completely. `gpt-5.4-mini` was the sweet spot for reliable routing, instruction following, and cost efficiency.

### 4. Talk to It Like a Human

Ask the agent anything in plain English via curl or Telegram. No commands to memorize.

curl status and payment issue queries:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic6.png

Telegram natural-language queries:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic7.png

Heartbeat triggered via natural language:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic10.png

### 5. Multi-Store Native

Configure multiple stores with independent heartbeat frequencies, API profiles, and notification channels. Switch context with "switch to store X" in chat.

### 6. Telegram - Two Paths, Same Experience

- Direct webhook - Laravel handles Telegram messages directly via ngrok
- OpenClaw gateway - Telegram -> OpenClaw -> Skill -> Agent -> reply

Both paths use the same natural-language interface.

### 7. Heartbeat with State Tracking

Per-store JSON state files track subscription transitions, transaction history, and customer counts. The change detector classifies deltas by severity (`good_news`, `warning`, `alert`) and fires workflows accordingly.

## Docker Topology

Five containers, one `docker compose up`.

Docker topology screenshot:
https://raw.githubusercontent.com/romansh/laravel-creem-agent-demo/main/docs/img/pic8.png

## What the Video Shows

1. Docker Compose stack starting inside a VirtualBox VM accessed via SSH
2. Simulator seeding baseline data with configurable parameters
3. Advancing the scenario with new sales, cancellations, and past-due events plus webhook delivery
4. Real-time Telegram alerts appearing within seconds of each event
5. Natural-language queries in Telegram such as "how's the store doing?" and "are there any issues?"
6. Heartbeat triggered through natural language, producing a full change report
7. curl requests to the chat endpoint with JSON responses
8. OpenClaw skill routing queries through the gateway via natural language

## Production Quality Notes

- Webhook security: HMAC-SHA256 signature verification on all inbound webhooks
- Retry logic: HTTP client retries 3 times with 100ms sleep and configurable timeouts
- LLM-agnostic: supports OpenAI, Anthropic, Gemini, and Ollama via Laravel AI SDK
- Docker-ready: FrankenPHP + Octane for the app, dedicated queue worker and scheduler containers
- CI-friendly: smoke test suite validates the full simulator -> agent -> webhook pipeline
