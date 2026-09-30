#!/usr/bin/env bash
# Headless loop: one fresh agent call per iteration, limits enforced outside the model.
# Usage: run-loop.sh claude|codex [max_iterations] [timeout_seconds]
# Verify CLI flags with `claude --help` / `codex --help` before productive use.
set -u
AGENT="${1:?claude|codex}"; MAX="${2:-3}"; LIMIT="${3:-3600}"
STATE=".loop/STATE.md"; START=$(date +%s)
PROMPT="Follow platform/LOOP.md. Read only $STATE, do one small step, run the check, update $STATE."
[ -f "$STATE" ] || { echo "missing $STATE (copy platform/loop/STATE.example.md)"; exit 2; }
for i in $(seq 1 "$MAX"); do
  grep -q '^status: running' "$STATE" || { echo "stop: $(grep '^status:' "$STATE")"; exit 0; }
  [ $(( $(date +%s) - START )) -lt "$LIMIT" ] || { echo "stop: wall-clock limit"; exit 1; }
  case "$AGENT" in
    claude) claude -p "$PROMPT" ;;
    codex)  codex exec --sandbox workspace-write "$PROMPT" ;;
    *) echo "unknown agent"; exit 2 ;;
  esac
done
echo "stop: max iterations ($MAX) reached"; exit 1
