# Credits

Every project has a **Credits** page, reachable publicly and as a menu item under Settings. It is
edited in the backoffice like the legal pages.

## 1. People and roles (first)

| Field | Notes |
|---|---|
| Name | person or team |
| Role | e.g. Game Design, Development, Art, Music, QA, AI supervision |
| Link | optional website/profile |
| Order | manual sort order |

An intro text is stored as CMS page `credits` (with revisions).

## 2. Used components (second)

AI systems, tools, plugins, libraries, frameworks, fonts, icons, images, sounds and services.

| Field | Required | Notes |
|---|---|---|
| Logo / icon | recommended | **local file only**, never hotlinked |
| Name | yes | |
| Category | yes | ai, tool, library, framework, plugin, font, icon, image, sound, service, other |
| Description | yes | what it is used for in this project |
| Provider information | if required | attribution text the license or provider demands |
| Links | recommended | website, GitHub, license, documentation |
| License | yes | SPDX identifier where possible (MIT, OFL-1.1, Apache-2.0, GPL-2.0-or-later, commercial) |
| Tags | yes | e.g. `MIT`, `Kommerziell erlaubt`, `Attribution erforderlich`, `Lokal eingebettet` |
| Commercial use | yes | yes / no / restricted / unknown |
| Attribution required | yes | boolean |
| Locally embedded | yes | must be true for fonts, icons and libraries |
| Version | recommended | |

## Rules

- **Everything local.** Fonts, icons, editors and libraries are embedded in the app. No Google
  Fonts CDN, no icon CDNs. If a CDN is chosen deliberately (e.g. editor delivery), list it in the
  credits *and* the privacy policy; the validator warns.
- AI systems used to create code, texts, images, music or voices are listed with their role –
  this complements the **AI philosophy** page.
- License obligations (attribution, license text) are fulfilled on this page.
- Profile: `credits.enabled`, `people`, `components`, `editable_in_backoffice`, `local_assets_only`.
