# Laravel Creem Agent — OpenClaw Skill

A single-file OpenClaw skill that connects the OpenClaw assistant to a running Laravel Creem Agent endpoint. Only `SKILL.md` is needed — no shell scripts required.

## Install

Copy the `SKILL.md` file into your OpenClaw workspace skills directory:

```bash
mkdir -p ~/.openclaw/workspace/skills/laravel-creem-agent
cp SKILL.md ~/.openclaw/workspace/skills/laravel-creem-agent/SKILL.md
```

Or use the OpenClaw CLI:

```bash
# If published to ClawHub:
openclaw skills install laravel-creem-agent
```

## Configure

Set the `AGENT_URL` environment variable in `~/.openclaw/openclaw.json`:

```json5
{
  skills: {
    entries: {
      "laravel-creem-agent": {
        enabled: true,
        env: {
          AGENT_URL: "http://127.0.0.1:8000/creem-agent/chat"
        }
      }
    }
  }
}
```

Or export it in your shell before starting the gateway:

```bash
export AGENT_URL="http://127.0.0.1:8000/creem-agent/chat"
```

## Telegram setup

Configure Telegram natively in OpenClaw (`~/.openclaw/openclaw.json`):

```json5
{
  channels: {
    telegram: {
      botToken: "YOUR_BOT_TOKEN",
      allowFrom: ["YOUR_TELEGRAM_USER_ID"]
    }
  }
}
```

See https://docs.openclaw.ai/channels/telegram for details.

## Verify

Send a test message via OpenClaw (Telegram, WebChat, or CLI):

```
what is the store status?
```

The skill teaches the OpenClaw agent to forward your message to the Laravel endpoint and relay the response back.
