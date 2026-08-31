# Laravel Creem Agent Demo — Printable Script

This is a print-friendly reading version of the demo script.
It removes most screen-direction noise and keeps only the spoken text,
with light emphasis for important points.

---

## Intro

Hi. In this video I'm going to show you a Laravel-based AI agent that monitors a Creem payment store around the clock.

This video is part of my submission for the **OpenClaw plus Creem AI Agent Worker bounty**.

Before we go into the terminal, let me give quick context on **Creem.io**.

Creem is a **Merchant of Record** platform.
That means Creem can handle the commercial side of selling for you: **payments, invoicing, tax handling, and the customer-facing transaction record**.

Creem also supports **revenue splits**.
This is useful when one payment needs to be shared across partners, creators, collaborators, or internal stakeholders.

And it includes a **built-in affiliate program**,
so you can track referrals and reward affiliates without stitching together a separate external system.

For an AI agent, this makes Creem a very interesting platform, because the store is not just about raw payments.
It also has **subscription state, partner payouts, and acquisition workflows** that can be monitored automatically.

---

## Entering the Demo Environment

Now let's enter the demo environment.

Everything you see in the terminal is running inside a **VirtualBox virtual machine**.
I'm connected to it over **SSH** from my local machine.

The project uses **four modular Laravel packages**: an SDK, a CLI, the agent itself, and a payment simulator that lets us test everything without real API keys.

---

## Docker Stack

Let's look at what's running.

We have five Docker containers.
The main app is powered by **FrankenPHP and Laravel Octane**.
There's a queue worker for async jobs, a scheduler for the heartbeat cron, a simulator that mocks the entire Creem API, and an ngrok tunnel so Telegram webhooks can reach us.

All of this starts with a single **docker compose up** command.

---

## Simulator Seeding

First, we seed the simulator with test data.
I can choose exactly how many products, customers, subscriptions, and transactions to create.

Here I'm running it twice with different parameters.
Six products, forty customers, and a hundred twenty transactions in the first run.
Then a smaller set: two products, ten customers, and twenty-five transactions.

The **reset** flag clears everything before seeding, so we always start from a known state.

---

## Advancing the Scenario

Now let's simulate what happens in a real store.
The advance command creates new events: a sale and a past-due subscription, and sends signed webhooks back to the agent app.

Watch the bottom of the screen.
The webhook was delivered, status two hundred, message: **webhook processed**.
That means the agent received it and triggered the notification pipeline.

---

## Telegram Alert

And here it is in Telegram.
The agent sent a **real-time alert**: new sale, product name, amount, and customer email.
This happened within seconds of the simulator firing the webhook.

---

## Complex Scenario

Let's push harder.

First advance: one sale and one past-due.
Second advance: two sales, a new customer, one past-due, and two cancellations.
All with webhooks enabled.

Four webhook deliveries in the second batch: two checkouts and two cancellations.
Each one triggers a separate notification.

---

## Telegram Batch Alerts

And Telegram lights up.

Three new sale alerts with product names and amounts.
Two subscription cancellation alerts with subscription IDs and customer emails.
All arriving in real time, in the order they happened.

---

## Curl API

You don't need Telegram to talk to the agent.
A simple **curl** request works just as well.

Here I'm asking for the store status, and I get back the last heartbeat time, customer count, transaction count, and subscription breakdown.

Then I ask, **any payment issues?** and the agent checks for past-due subscriptions.

---

## Telegram Natural Language

The same thing works in Telegram with natural language.

I type, **how's the store doing?** and the agent gives me a full status report.
I type, **are there any issues?** and it tells me about past-due subscriptions.

This works the same way whether the message comes through the direct Laravel webhook path or through OpenClaw.
There's no separate mode to switch. Just talk to it.

---

## Heartbeat via Telegram

Now let me show the heartbeat.

I'll advance the simulator with five new sales, but without sending webhooks this time.
The agent doesn't know about these sales yet.

Then I go to Telegram and ask the agent to run the heartbeat.
Notice I'm using plain English: **there was a bug in heartbeat, run it again**.

The agent understands the intent, runs the heartbeat check, and comes back with a full change report.

Five new sales detected, with product names, amounts, and customer emails.
One new customer added.
All discovered by comparing the current state against the last saved snapshot.

---

## Closing

That's the system.

Four modular packages, a deterministic simulator, a two-tier AI classifier, and real-time notifications in Telegram.

Everything runs in Docker.
Everything is tested with the simulator.
No real payment API keys are needed for the demo.

The source code is on GitHub.
Links are in the description.

Thanks for watching.