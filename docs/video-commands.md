# Video Demo — Commands in Order

All commands are run inside the VirtualBox VM over SSH.
Working directory: `/home/www` (or wherever the repo is cloned in the VM).

---

## Step 0 — Connect to the VM (run on host machine)

```bash
ssh -p 2222 user@127.0.0.1
```

---

## Step 1 — Check running containers

```bash
docker ps
```

*Expected: 5 containers — app, queue, scheduler, simulator, ngrok — all Up and healthy.*

---

## Step 2 — Seed the simulator (run twice to show flexibility)

First run — large dataset:

```bash
docker compose exec simulator php artisan simulator:seed-demo \
  --store=default \
  --days=45 \
  --products=6 \
  --customers=40 \
  --subscriptions=24 \
  --transactions=120 \
  --reset
```

Second run — smaller dataset (resets again):

```bash
docker compose exec simulator php artisan simulator:seed-demo \
  --store=default \
  --days=45 \
  --products=2 \
  --customers=10 \
  --subscriptions=24 \
  --transactions=25 \
  --reset
```

---

## Step 3 — Advance the scenario: 1 sale + 1 past-due + webhook

```bash
docker compose exec simulator php artisan simulator:advance \
  --store=default \
  --sales=1 \
  --past-due=1 \
  --send-webhooks
```

*Expected output: 1 new transaction, checkout.completed event, webhook delivery status 200.*
*Switch to Telegram — new sale alert appears.*

---

## Step 4 — Advance again: multiple events

```bash
docker compose exec simulator php artisan simulator:advance \
  --store=default \
  --sales=2 \
  --new-customers=1 \
  --past-due=1 \
  --cancellations=2 \
  --send-webhooks
```

*Expected: 4 webhook deliveries — 2 checkouts, 2 cancellations.*
*Switch to Telegram — 3 new sale alerts + 2 cancellation alerts appear.*

---

## Step 5 — Query the agent via curl

Store status:

```bash
curl -s -X POST http://localhost:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"status","source":"api"}'
```

Payment issues:

```bash
curl -s -X POST http://localhost:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"any payment issues?","source":"api"}'
```

---

## Step 6 — Natural language in Telegram

Send to the Telegram bot (manually, on phone or desktop):

```
how's the store doing?
```

```
are there any issues?
```

*Both work the same way — direct Laravel webhook path or OpenClaw, no difference.*

---

## Step 7 — Advance without webhooks (for heartbeat demo)

```bash
docker compose exec simulator php artisan simulator:advance \
  --store=default \
  --sales=5
```

*No `--send-webhooks` — the agent does not know about these sales yet.*

---

## Step 8 — Trigger heartbeat via natural language in Telegram

Send to the Telegram bot:

```
There was a bug in heartbeat, run it again
```

*Agent understands the intent, runs heartbeat, responds with a full change report:
5 new sales detected, customer count updated.*

---

## Quick reference — all terminal commands in sequence

```bash
# 0. connect
ssh -p 2222 user@127.0.0.1

# 1. check stack
docker ps

# 2a. seed large
docker compose exec simulator php artisan simulator:seed-demo \
  --store=default --days=45 --products=6 --customers=40 \
  --subscriptions=24 --transactions=120 --reset

# 2b. seed small
docker compose exec simulator php artisan simulator:seed-demo \
  --store=default --days=45 --products=2 --customers=10 \
  --subscriptions=24 --transactions=25 --reset

# 3. advance: 1 sale + 1 past-due
docker compose exec simulator php artisan simulator:advance \
  --store=default --sales=1 --past-due=1 --send-webhooks

# 4. advance: 2 sales + cancellations
docker compose exec simulator php artisan simulator:advance \
  --store=default --sales=2 --new-customers=1 \
  --past-due=1 --cancellations=2 --send-webhooks

# 5a. curl: status
curl -s -X POST http://localhost:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"status","source":"api"}'

# 5b. curl: payment issues
curl -s -X POST http://localhost:8000/creem-agent/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"any payment issues?","source":"api"}'

# 7. advance without webhooks (heartbeat demo setup)
docker compose exec simulator php artisan simulator:advance \
  --store=default --sales=5
```

---

## Telegram messages in order

| # | Message to send | Expected agent response |
|---|-----------------|------------------------|
| After step 3 | *(webhook auto-sent)* | ✅ New sale alert |
| After step 4 | *(webhook auto-sent)* | 3 sales + 2 cancellations |
| Step 6a | `how's the store doing?` | Full store status |
| Step 6b | `are there any issues?` | Past-due subscription count |
| Step 8 | `There was a bug in heartbeat, run it again` | Heartbeat report — 5 new sales |
