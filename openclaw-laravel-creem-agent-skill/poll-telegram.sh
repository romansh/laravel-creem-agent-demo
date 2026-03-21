#!/bin/sh
# Long-polling Telegram -> Creem Agent forwarder for OpenClaw
# Requires: curl, jq

SKILL_DIR="$(cd "$(dirname "$0")" && pwd)"
OFFSET_FILE="$SKILL_DIR/.last_update"
TOKEN="${TELEGRAM_BOT_TOKEN:-}" 
AGENT_URL="${AGENT_URL:-http://127.0.0.1:8000/creem-agent/chat}"

if [ -z "$TOKEN" ]; then
  echo "Set TELEGRAM_BOT_TOKEN environment variable before running."
  exit 2
fi

mkdir -p "$SKILL_DIR"
LAST=0
if [ -f "$OFFSET_FILE" ]; then
  LAST=$(cat "$OFFSET_FILE" 2>/dev/null || echo 0)
fi

echo "Starting Telegram poller. Agent URL: $AGENT_URL"
while true; do
  RESP=$(curl -s "https://api.telegram.org/bot${TOKEN}/getUpdates?timeout=30&offset=$((LAST+1))")
  OK=$(echo "$RESP" | jq -r '.ok')
  if [ "$OK" != "true" ]; then
    sleep 2
    continue
  fi

  UPDATES_COUNT=$(echo "$RESP" | jq '.result | length')
  if [ "$UPDATES_COUNT" -gt 0 ]; then
    echo "$RESP" | jq -c '.result[]' | while read -r update; do
      update_id=$(echo "$update" | jq -r '.update_id')
      LAST=$update_id
      echo "$LAST" > "$OFFSET_FILE"

      # extract text and chat id
      text=$(echo "$update" | jq -r '.message.text // .edited_message.text // .channel_post.text // .callback_query.data // empty')
      chat_id=$(echo "$update" | jq -r '.message.chat.id // .edited_message.chat.id // .channel_post.chat.id // .callback_query.from.id // empty')

      if [ -z "$text" ] || [ "$text" = "null" ]; then
        continue
      fi

      # forward to agent
      PAYLOAD=$(jq -n --arg m "$text" --arg c "$chat_id" '{message:$m,source:"openclaw",chat:$c}')
      AGENT_RESP=$(curl -s -X POST "$AGENT_URL" -H 'Content-Type: application/json' -d "$PAYLOAD")

      # try to extract reply text from agent JSON
      reply=$(echo "$AGENT_RESP" | jq -r '.reply // .message // empty' 2>/dev/null)
      if [ -z "$reply" ]; then
        # fallback to plain body
        reply="$AGENT_RESP"
      fi

      # send reply back to Telegram
      if [ -n "$reply" ]; then
        curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="$chat_id" -d text="$reply" >/dev/null
      fi
    done
  fi

  sleep 1
done
