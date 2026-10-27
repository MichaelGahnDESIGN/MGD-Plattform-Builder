# Example: API migration with the loop

A worked example for [`platform/LOOP.md`](../LOOP.md). Nothing here is magic: a goal, a check
script that says yes or no, and one small file (`.loop/STATE.md`) that carries the state from
one iteration to the next.

**Task:** replace every `apiV1(` call in `src/` with `apiV2(`. The tests must stay green.

## 1. The check script

`.loop/check.sh` answers one question: "Are we done?" Exit code 0 means yes. It prints only a
few lines, so the agent never has to read long logs.

```bash
#!/usr/bin/env bash
# exit 0 only when no apiV1( is left and the tests pass
set -u
left=$(grep -rF 'apiV1(' src/ | wc -l | tr -d ' ')
echo "apiV1( left: $left"
[ "$left" -eq 0 ] || exit 1
npm test --silent 2>&1 | tail -n 15
exit "${PIPESTATUS[0]}"
```

## 2. The start state

Copy `platform/loop/STATE.example.md` to `.loop/STATE.md` and fill it in. Start small:
`max_iterations: 3` is the pilot value.

```markdown
---
goal: "No apiV1( call left in src/, tests green"
check: "./.loop/check.sh"
status: running
iteration: 0
max_iterations: 3
started_at: 2026-10-27T09:00:00Z
deadline: 2026-10-27T11:00:00Z
no_progress_count: 0
max_no_progress: 2
scope: ["src/**", "test/**"]
forbidden: ["push", "deploy", "delete-branch", "prod-config", "secrets", "payment-settings"]
---
## Last result
check: apiV1( left: 42
## Next step
Migrate src/auth/ and its tests.
## Done
-
## Failed approaches
-
## Decisions / open questions
-
```

## 3. What one iteration looks like

Every iteration starts with an empty context. The agent reads only `.loop/STATE.md`, then:

1. checks the stop rules (iteration limit, deadline, no progress, scope),
2. does the one step from "Next step",
3. runs the check script and keeps only the last lines,
4. rewrites `STATE.md` and ends.

| Iteration | Step | Check afterwards | Next step in STATE.md |
|---|---|---|---|
| 1 | migrate `src/auth/` and its tests | 28 left, tests green | `src/payment/` |
| 2 | migrate `src/payment/` and its tests | 8 left, tests green | `src/utils/` |
| 3 | migrate `src/utils/` and its tests | 0 left, tests green, exit code 0 | none |

After iteration 3 the agent sets `status: done`. The next iteration finds `status` is not
`running` and does nothing.

State after iteration 2 (this is all the agent reads in iteration 3):

```markdown
---
status: running
iteration: 2
no_progress_count: 0
---
## Last result
check: apiV1( left: 8, tests green
## Next step
Migrate src/utils/ and its tests.
## Done
- src/auth/ migrated (iteration 1)
- src/payment/ migrated (iteration 2)
## Failed approaches
-
```

(The real file keeps all fields; they are shortened here.)

## 4. When it goes wrong

The loop stops on purpose instead of trying forever:

| What happens | Rule | Result |
|---|---|---|
| The same test fails twice in a row, no new diff | `no_progress_count >= max_no_progress` | `status: blocked`, the agent writes why and asks a human |
| The next step would touch `package.json` or a deploy script | outside `scope` / in `forbidden` | `status: blocked`, nothing is changed |
| Iteration 3 ends and the check is still red | `iteration >= max_iterations` | `status: stopped`, a human decides whether to raise the limit |

After a stop the agent never restarts on its own.

## 5. Running it

Interactive: start the agent and say "Run platform/LOOP.md". Headless, both agents:

```bash
platform/loop/run-loop.sh claude 3 1800   # or: codex  (3 rounds, 30 minutes at most)
```

Check the token use after the pilot run. Only then raise `max_iterations`.
