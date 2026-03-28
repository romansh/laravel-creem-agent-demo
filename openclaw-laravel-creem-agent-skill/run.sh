#!/bin/sh
# OpenClaw skill runner: forwards text to local Creem agent

set -eu

AGENT_URL="${AGENT_URL:-http://127.0.0.1:8000/creem-agent/chat}"

json_escape() {
  awk '
    BEGIN {
      ORS = ""
      print "\""
    }
    {
      gsub(/\\/, "\\\\")
      gsub(/"/, "\\\"")
      gsub(/\t/, "\\t")
      gsub(/\r/, "\\r")

      if (NR > 1) {
        printf "\\n"
      }

      printf "%s", $0
    }
    END {
      print "\""
    }
  '
}

if ! command -v curl >/dev/null 2>&1; then
  echo "curl is required but was not found in PATH" >&2
  exit 127
fi

if [ -t 0 ] && [ $# -eq 0 ]; then
  echo "Usage: $0 \"message\"  or  echo \"message\" | $0" >&2
  exit 2
fi

if [ $# -gt 0 ]; then
  MSG="$1"
else
  MSG=$(cat)
fi

PAYLOAD=$(printf '{"message":%s,"source":"openclaw"}' "$(printf '%s' "$MSG" | json_escape)")

curl -fsS -X POST "$AGENT_URL" -H 'Content-Type: application/json' -d "$PAYLOAD"
