# OpenClaw + CREEM Agent Overview

This document contains the design notes, architecture, and implementation details for the OpenClaw + CREEM agent worker used in the demo. It describes the heartbeat patterns, CLI facade, and workflow requirements used by the demo environment.

<!-- Content migrated from MASTER_PROMPT_OpenClaw_Creem_Agent.md -->

## CONTEXT

You are an expert AI systems architect building a production-ready contest entry: "OpenClaw AI Agent Worker for Creem" — an autonomous AI employee that monitors SaaS payment operations 24/7 using heartbeat patterns, CLI facade, and proactive workflows.

**Key architectural decision**: No MCP layer. The agent communicates with Creem through a **CLI Facade** that transparently uses either the official `creem` CLI (installed via Homebrew) or our own `romansh/laravel-creem-cli` package (which wraps the `romansh/laravel-creem` SDK). The agent itself is the `romansh/laravel-creem-agent` package.

---

## CONTEST REQUIREMENTS

**Prize**: $3,000
**Deadline**: March 31, 2026
**Judging Criteria**:
- Creativity & Innovation: 40%
- Technical Accuracy: 30%
- Production Quality: 20%
- Completeness: 10%

**Deliverables**:
1. 10–15 minute video showing the agent running as a background worker *(video not created by us — architecture must not block simple video recording)*
2. Written guide (1,500–2,500 words)
3. OpenClaw agent with Creem API + webhook integration
4. **Heartbeat pattern implementation per https://www.creem.io/HEARTBEAT.md**
5. At least 3 proactive workflows (failed payment alerts, churn detection, revenue digests)
6. Notifications to Telegram
7. Natural language queries support
8. Error handling and graceful recovery

---

## REPOSITORIES & WORKSPACE

Three packages, all under `/home/roman/php/run/`:

- `romansh/laravel-creem-agent` — `/home/roman/php/run/laravel-creem-agent` — Core agent module: heartbeat, proactive workflows, state machine, notifications
- `romansh/laravel-creem-cli` — `/home/roman/php/run/laravel-creem-cli` — Laravel Artisan CLI that mirrors the official `creem` CLI commands via `romansh/laravel-creem` SDK
- `romansh/laravel-creem-agent-demo` — `/home/roman/php/run/laravel-creem-agent-demo` — Demo Laravel app installing all packages, with admin widget, chat UI, full flow showcase

... (migrated content preserved) ...
