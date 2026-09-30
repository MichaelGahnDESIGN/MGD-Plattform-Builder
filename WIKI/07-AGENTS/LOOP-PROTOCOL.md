# Loop Protocol

Token-saving way to repeat agent work in Claude Code and ChatGPT Codex. Normative text:
[`platform/LOOP.md`](../../platform/LOOP.md).

Core ideas:

- state lives in `.loop/STATE.md`, not in the chat context
- one small step per iteration, then a fresh context
- goal + deterministic check + hard stop rules (iterations, deadline, no progress, scope)
- cheap models for checks/docs, strong models only after `blocked` and human approval
- never loop over legal, privacy, cost or production decisions

Sources and confidence: the protocol is derived from vendor documentation for `/loop`, `/goal`,
Stop hooks, prompt caching and subagents (Claude Code) and secondary sources for Codex
(`codex exec`, AGENTS.md, `/goal`). Codex details must be verified with `codex --help`.
