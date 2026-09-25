# Versioning and Release Notes

## Version scheme

```text
1.2.3 STATUS
│ │ └─ PATCH: patches and updates of existing functions (game, editor, platform)
│ └─── MINOR: new functions in game, editor or platform
└───── MAJOR: release line, starts at 1 with the first real release
```

Every project starts at **`0.0.1 Pre-Alpha`**. The foundation itself is at **`0.5.1 Pre-Alpha`**.

Status values: `pre-alpha`, `alpha`, `beta`, `pre-release`, `release`, `stable`, `staging`,
`hotfix`, `lts`, `deprecated`. Displayed as `Pre-Alpha`, `Alpha`, `Beta`, `Pre-Release`, ...

## Single source of truth: `version.json`

```json
{
  "version": "0.0.1",
  "status": "pre-alpha",
  "released_at": "2026-09-25",
  "sync_targets": ["VERSION", "package.json"]
}
```

Schema: [`schema/version.schema.json`](../../schema/version.schema.json). Applications read this
file at runtime (the PHP reference and the starter template do). Other files are only synchronized
copies:

```bash
mgd-platform version                         # prints "0.0.1 Pre-Alpha"
mgd-platform version --bump patch            # 0.0.2, syncs VERSION/package.json/...
mgd-platform version --bump minor --status alpha
mgd-platform version --check                 # fails if a sync target drifted (CI)
mgd-platform version --bump patch \
  --note "Neue Rangliste" --audience frontend,backoffice --type feature
```

Supported sync targets: `VERSION`, `package.json`, `composer.json`, `template.json`.

## Where the version is shown

Configured in the backoffice under **Settings → Versionsnummern** and declared in the profile:

```yaml
versioning:
  display:
    enabled: true
    show_status: true
    audience: "both"          # public | private | both
    locations: ["login", "settings", "game_settings", "backoffice_footer",
                "public_footer", "public_header", "landing_public", "landing_private", "about"]
```

The validator warns if `login` and `settings`/`game_settings` are missing. The briefing always asks
for the locations and the audience.

## Release notes

File source: `release-notes.json` ([schema](../../schema/release-notes.schema.json)). Each deploy
adds at least one entry.

```json
{
  "entries": [
    {
      "version": "0.1.0",
      "status": "alpha",
      "date": "2026-10-01",
      "audience": ["frontend", "backoffice"],
      "type": "feature",
      "title": "Neue Rangliste",
      "items": ["Wöchentliche Rangliste", "Filter nach Freunden"]
    }
  ]
}
```

- `audience`: `frontend`, `backoffice`, `editor`, `platform`, `game`, `api`
- `type`: `feature`, `patch`, `fix`, `security`, `breaking`, `deploy`

**Visibility rule:** the public frontend shows only entries whose audience contains `frontend`
(configurable via `versioning.release_notes.public_audiences`). Editor, admin and moderation
backoffices show **all** entries with an audience filter. In the backoffice the timeline is a
menu item under **Settings → Release Notes**.

Applications import the file into their database (starter: button "Aus release-notes.json
synchronisieren" and `scripts/sync-release-notes.php`), upserting by version + title.
