#!/bin/sh
# OpenClaw skill runner: forwards text to local Creem agent

AGENT_URL="${AGENT_URL:-http://127.0.0.1:8000/creem-agent/chat}"

if [ -t 0 ] && [ -z "$1" ]; then
  echo "Usage: $0 \"message\"  or  echo \"message\" | $0"
  exit 2
fi

if [ -n "$1" ]; then
  MSG="$1"
else
  MSG=$(cat -)
fi

# Simple JSON escape for double quotes and backslashes
ESC_MSG=$(printf '%s' "$MSG" | sed 's/\\/\\\\/g; s/"/\\"/g')
PAYLOAD="{\"message\":\"${ESC_MSG}\",\"source\":\"openclaw\"}"

curl -s -X POST "$AGENT_URL" -H 'Content-Type: application/json' -d "$PAYLOAD"
