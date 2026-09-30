---
goal: "Example: audit reports 0 high findings"
check: "./.loop/check.sh"          # exit 0 = goal reached
status: running                    # running | done | blocked | stopped
iteration: 0
max_iterations: 3                  # pilot value, raise after review
started_at: 2026-01-01T10:00:00Z
deadline: 2026-01-01T12:00:00Z
no_progress_count: 0
max_no_progress: 2
scope: ["docs/**", "MGD_PLATFORM.yml"]
forbidden: ["push", "deploy", "delete-branch", "prod-config", "secrets", "payment-settings"]
---
## Last result
(none yet)
## Next step
(one small step)
## Done
-
## Failed approaches
-
## Decisions / open questions
-
