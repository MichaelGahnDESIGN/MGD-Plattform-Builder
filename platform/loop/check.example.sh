#!/usr/bin/env bash
# Deterministic check for the loop protocol. Exit 0 = goal reached.
# Replace the command with a real one (tests, linter, audit). Print short output only.
set -u
npx --no-install mgd-platform audit . 2>&1 | tail -n 15
exit "${PIPESTATUS[0]}"
