# Video Script — Laravel Creem Agent Demo

> Voiceover script for a 10–15 minute screen recording.
> All console work shown in the video happens inside a VirtualBox VM accessed via SSH.
> Read each line slowly and clearly — this is written for non-native English speakers.

---

## Scene 1 — Intro

**[Show: title card, project name, maybe Creem.io homepage or logo]**

> Hi. In this video I'm going to show you a Laravel-based AI agent
> that monitors a Creem payment store around the clock.
>
> This video is part of my submission for the OpenClaw plus Creem
> AI Agent Worker bounty.
>
> Before we go into the terminal, let me give quick context on Creem.io.
> Creem is a Merchant of Record platform.
> That means Creem can handle the commercial side of selling for you —
> payments, invoicing, tax handling, and the customer-facing transaction record.
>
> Creem also supports revenue splits,
> which is useful when one payment needs to be shared across partners,
> creators, collaborators, or internal stakeholders.
>
> And it includes a built-in affiliate program,
> so you can track referrals and reward affiliates without stitching together
> a separate external system.
>
> For an AI agent, this makes Creem a very interesting platform,
> because the store is not just about raw payments.
> It also has subscription state, partner payouts,
> and acquisition workflows that can be monitored automatically.

---

## Scene 2 — Entering the VM (show terminal with SSH connection)

**[Show: SSH into VirtualBox VM]**

> Now let's enter the demo environment.
>
> Everything you see in the terminal is running inside a VirtualBox virtual machine.
> I'm connected to it over SSH from my local machine.
>
> The project uses four modular Laravel packages — an SDK, a CLI, the agent itself,
> and a payment simulator that lets us test everything without real API keys.

---

## Scene 3 — Docker Stack (show `docker ps`)

**[Show: pic8.png — docker ps output with 5 containers]**

> Let's look at what's running.
> We have five Docker containers.
> The main app is powered by FrankenPHP and Laravel Octane.
> There's a queue worker for async jobs,
> a scheduler for the heartbeat cron,
> a simulator that mocks the entire Creem API,
> and an ngrok tunnel so Telegram webhooks can reach us.
>
> All of this starts with a single docker compose up command.

---

## Scene 4 — Simulator Seeding (show seed-demo command)

**[Show: pic1.png — two seed-demo commands with different configs]**

> First, we seed the simulator with test data.
> I can choose exactly how many products, customers,
> subscriptions, and transactions to create.
>
> Here I'm running it twice with different parameters.
> Six products, forty customers, and a hundred twenty transactions in the first run.
> Then a smaller set — two products, ten customers, twenty-five transactions.
>
> The reset flag clears everything before seeding,
> so we always start from a known state.

---

## Scene 5 — Advancing the Scenario (show advance command)

**[Show: pic2.png — simulator:advance with --send-webhooks]**

> Now let's simulate what happens in a real store.
> The advance command creates new events — a sale, a past-due subscription —
> and sends signed webhooks back to the agent app.
>
> Watch the bottom of the screen.
> The webhook was delivered, status two hundred, message "webhook processed."
> That means the agent received it and triggered the notification pipeline.

---

## Scene 6 — Telegram Alert (show first Telegram notification)

**[Show: pic3.png — single "New sale" alert in Telegram]**

> And here it is in Telegram.
> The agent sent a real-time alert — new sale, product name, amount, customer email.
> This happened within seconds of the simulator firing the webhook.

---

## Scene 7 — Complex Scenario (show multiple advances)

**[Show: pic4.png — two advance commands with sales, cancellations, past-due]**

> Let's push harder.
> First advance — one sale, one past-due.
> Second advance — two sales, a new customer, one past-due, two cancellations.
> All with webhooks enabled.
>
> Four webhook deliveries in the second batch — two checkouts, two cancellations.
> Each one triggers a separate notification.

---

## Scene 8 — Telegram Batch Alerts (show multiple alerts)

**[Show: pic5.png — 3 sales + 2 cancellations in Telegram]**

> And Telegram lights up.
> Three new sale alerts with product names and amounts.
> Two subscription cancellation alerts with subscription IDs and customer emails.
> All arriving in real time, in the order they happened.

---

## Scene 9 — curl API (show curl requests)

**[Show: pic6.png — curl status and payment issues queries]**

> You don't need Telegram to talk to the agent.
> A simple curl request works just as well.
>
> Here I'm asking for the store status — and I get back
> the last heartbeat time, customer count, transaction count,
> and subscription breakdown.
>
> Then I ask "any payment issues?" — and the agent checks for past-due subscriptions.

---

## Scene 10 — Telegram Natural Language (show NL queries)

**[Show: pic7.png — "how's the store doing?" and "are there any issues?"]**

> The same thing works in Telegram with natural language.
> I type "how's the store doing?" — and the agent gives me a full status report.
> I type "are there any issues?" — and it tells me about past-due subscriptions.
>
> This works the same way whether the message comes through
> the direct Laravel webhook path or through OpenClaw.
> There's no separate mode to switch — just talk to it.

---

## Scene 11 — Heartbeat via Telegram (show heartbeat trigger)

**[Show: pic9.png — advance 5 sales without webhooks]**

> Now let me show the heartbeat.
> I'll advance the simulator with five new sales but without sending webhooks this time.
> The agent doesn't know about these sales yet.

**[Show: pic10.png — "There was a bug in heartbeat, run it again" → heartbeat report]**

> Then I go to Telegram and ask the agent to run the heartbeat.
> Notice I'm using plain English — "there was a bug in heartbeat, run it again."
> The agent understands the intent, runs the heartbeat check,
> and comes back with a full change report.
>
> Five new sales detected, with product names, amounts, and customer emails.
> One new customer added. All discovered by comparing the current state
> against the last saved snapshot.

---

## Scene 12 — Closing

> That's the system.
> Four modular packages, a deterministic simulator,
> a two-tier AI classifier, and real-time notifications in Telegram.
>
> Everything runs in Docker. Everything is tested with the simulator.
> No real payment API keys needed for the demo.
>
> The source code is on GitHub. Links are in the description.
> Thanks for watching.

---

## Image-to-Scene Reference

| Scene | Image | What's on screen |
|-------|-------|------------------|
| 3 | pic8.png | `docker ps` — 5 containers |
| 4 | pic1.png | `simulator:seed-demo` — two configs |
| 5 | pic2.png | `simulator:advance` — webhooks delivered |
| 6 | pic3.png | Telegram — single new sale alert |
| 7 | pic4.png | Two `simulator:advance` commands — sales + cancellations |
| 8 | pic5.png | Telegram — batch alerts (3 sales, 2 cancellations) |
| 9 | pic6.png | Terminal — curl requests with JSON responses |
| 10 | pic7.png | Telegram — natural language queries |
| 11a | pic9.png | `simulator:advance --sales=5` (no webhooks) |
| 11b | pic10.png | Telegram — heartbeat triggered by NL, change report |
