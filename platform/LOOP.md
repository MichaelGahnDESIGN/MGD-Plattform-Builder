# Loop Protocol · MGD-Plattform-Builder

Agent-neutral protocol for repeating work in **small, stateless iterations** to save tokens.
Works in Claude Code and ChatGPT Codex; it only needs Markdown, a shell and a git repository.

## Why

Every request re-sends the whole conversation. A loop that runs inside one growing context gets
more expensive per iteration. The protocol keeps the state **in a file**, so each iteration can
start with a fresh, small context.

## Use a loop only when

- the goal is measurable ("`npm test` exits 0", "audit shows 0 high findings"),
- a deterministic check exists (script, exit code) or a separate reviewer can judge,
- the work can be split into small steps.

Do **not** loop for design decisions, legal/privacy/cost choices, or anything touching production.
Those always stop and ask (see `platform/SKILL.md`, "Ask, don't assume").

A worked example with check script and three iterations: [`platform/loop/EXAMPLE.md`](loop/EXAMPLE.md).

## State file `.loop/STATE.md`

Copy `platform/loop/STATE.example.md`. Keep it under ~60 lines; condense old "Done" entries.
Fields: `goal`, `check`, `status` (running | done | blocked | stopped), `iteration`,
`max_iterations`, `deadline`, `no_progress_count`, `max_no_progress`, `scope`, `forbidden`.

## One iteration

1. Read **only** `.loop/STATE.md`. Do not replay history.
2. Pre-check the stop rules below. On a hit: set `status`, write the reason, end.
3. Do exactly **one** small step from "Next step", inside `scope`.
4. Run the `check` script. Keep only the last lines / error summary, never full logs.
5. Update `STATE.md`: iteration + 1, result, next step, failed approaches.
6. Decide: check exit 0 -> `done`; no progress -> count up; otherwise continue.
7. End the context. The next iteration starts fresh from the file.

## Stop rules (hard, first match wins)

1. `check` exits 0 -> `done`.
2. `iteration >= max_iterations` -> `stopped`.
3. `deadline` passed -> `stopped`.
4. `no_progress_count >= max_no_progress` (same error or same diff) -> `blocked`, ask the human.
5. A step would touch `forbidden` actions or paths outside `scope` -> `blocked`, do not execute.
6. Unclear requirement, possible data loss, or a permission prompt -> `blocked`.
7. After a stop, never restart on your own.

`forbidden` defaults: push, deploy, delete-branch, production config, secrets, payment settings.

## Model choice per step (cost lever)

| Step | Model class |
|---|---|
| Run check, format, docs, release notes | small / cheap |
| Normal implementation step | standard (Sonnet-class) |
| Architecture, threat model, security/licensing design | strongest, only after `blocked` and a human OK |

Set model and effort **before** the loop starts; switching mid-run breaks the prompt cache.

## Running it

**Claude Code (interactive):** `/goal <goal> (scripts/check.sh exits 0) or stop after 10 turns`,
or `/loop` with the prompt "Run platform/LOOP.md". Optional Stop hook that runs `check` and blocks
on failure; it must exit 0 immediately when `stop_hook_active` is true.

**Headless, both agents:** `platform/loop/run-loop.sh` runs one fresh agent call per iteration and
enforces the limits outside the model (iteration counter, wall-clock timeout).
Each call is cut off by `timeout` (macOS: `gtimeout` from coreutils) when the remaining time runs out; without it, a single iteration is not time-limited.

**Codex:** `AGENTS.md` only points here (32 KiB limit); run via `codex exec` in `run-loop.sh`.
`/goal` exists in recent Codex versions with an advisory token budget only. Verify flags with
`codex --help` before productive use.

## Guardrails

- Pilot with `max_iterations: 3` and a branch/read-only scope first; check token use, then raise.
- No push, deploy or production change inside a loop without explicit human approval.
- Deterministic work belongs in scripts/hooks, not in model reasoning.
- Subagents only for independent, noisy sub-tasks; results go into `STATE.md`, not the chat.
  Multi-agent setups can cost several times more tokens.
- Polling: prefer event streams (Monitor tool, CI webhooks) over short intervals.
