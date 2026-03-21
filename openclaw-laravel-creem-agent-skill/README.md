# OpenClaw Laravel Creem Agent Skill

This folder now follows the real OpenClaw skill model: a skill is a directory with a `SKILL.md` file plus optional supporting scripts.

What changed

- Removed the custom `skill.yaml` manifest. OpenClaw does not use it.
- The canonical entrypoint is now `SKILL.md`.
- Installation target defaults to `~/.openclaw/workspace/skills/openclaw-laravel-creem-agent-skill`, matching current OpenClaw docs.

Install

- Manual:
  - Copy this folder to `~/.openclaw/workspace/skills/openclaw-laravel-creem-agent-skill`
  - Make scripts executable if needed
- Helper:
  - Run `./install.sh`

After install

- Restart OpenClaw or refresh skills so it reindexes `SKILL.md`
- Check visibility with `openclaw skills list` or `openclaw skills info openclaw-laravel-creem-agent-skill`

Local script usage

```sh
echo "status" | ~/.openclaw/workspace/skills/openclaw-laravel-creem-agent-skill/run.sh
~/.openclaw/workspace/skills/openclaw-laravel-creem-agent-skill/run.sh "what is the status?"
```

Configuration

- `AGENT_URL` defaults to `http://127.0.0.1:8000/creem-agent/chat`

Telegram options

- OpenClaw mode: configure Telegram natively in OpenClaw gateway via `https://docs.openclaw.ai/channels/telegram`
- Pure Laravel mode: use `/creem-agent/telegram/webhook`

Render OpenClaw Telegram config from Laravel

```sh
php artisan creem-agent:openclaw-telegram-config
```
