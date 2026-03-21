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
- `TELEGRAM_BOT_TOKEN` is required only for `poll-telegram.sh`

Telegram options

- Laravel webhook mode: use `/creem-agent/telegram/webhook`
- OpenClaw-managed polling mode: run `poll-telegram.sh`, which forwards updates to the Laravel agent and posts replies back to Telegram

Run the poller

```sh
export TELEGRAM_BOT_TOKEN=your_bot_token_here
export AGENT_URL=http://127.0.0.1:8000/creem-agent/chat
~/.openclaw/workspace/skills/openclaw-laravel-creem-agent-skill/poll-telegram.sh
```
