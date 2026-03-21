#!/bin/sh
# Install helper: copies this skill into the OpenClaw workspace skills directory

set -eu

SKILL_NAME="openclaw-laravel-creem-agent-skill"
DEST_DIR="${OPENCLAW_SKILLS_DIR:-$HOME/.openclaw/workspace/skills}"
TARGET_DIR="$DEST_DIR/$SKILL_NAME"

echo "Installing $SKILL_NAME to $TARGET_DIR"
mkdir -p "$DEST_DIR"
rm -rf "$TARGET_DIR"
cp -r "$(dirname "$0")" "$TARGET_DIR"
chmod +x "$TARGET_DIR/run.sh" || true
chmod +x "$TARGET_DIR/install.sh" || true

echo "Installed."
echo "Restart OpenClaw or refresh skills so it reindexes $TARGET_DIR/SKILL.md"
echo "Quick test:"
echo "  echo 'status' | $TARGET_DIR/run.sh"
