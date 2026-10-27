# Licensing, "powered by" Label, White-label and Modules

Since 0.6.0 the MGD-Plattform-Builder is **dual licensed** (see [`LICENSING.md`](../../LICENSING.md)):

- **MIT:** CLI, validator, schemas, registries, scripts, documentation, profile templates.
- **MGD License** ([`MGD-Lizenz.md`](../../MGD-Lizenz.md), `LicenseRef-MGD`): starter templates, the reference
  platform, the "powered by" label and its logos, and every project built from them.

Versions up to 0.5.1 were released under MIT and remain MIT.

## Mandatory label

Every installation shows **"powered by: Michael Gahn DESIGN"** with the local logo (light and dark variant),
linking to `https://michael-gahn.de` in a new tab (`rel="noopener"`), at:

| Location | Starter implementation |
|---|---|
| public footer | `SiteLayout::footer()` |
| public landing page | `SiteLayout::render(landing: true)` |
| private landing page (backoffice dashboard) | `DashboardController` |
| login pages (incl. installer) | `AdminLayout::bare()` |
| backoffice footer | `AdminLayout::render()` |
| settings | `SettingsController` |
| Settings › License | `LicenseController` |

The only design option is the alignment (`license.powered_by_align`: left, center, right). Light/dark switching is
automatic. The label is rendered by `src/Core/License/PoweredBy.php` and styled by `public/assets/css/powered-by.css`.

## License page

**Backoffice › Settings › License** (`/admin/license`) shows the label, the complete `MGD-Lizenz.md`, the current
domain, the white-label status and the integrity status. Admins can enter or remove a white-label key.

## Integrity check

`LicenseIntegrity` compares SHA-256 hashes of `MGD-Lizenz.md`, `PoweredBy.php`, `powered-by.css` and both logos. If
something was changed without a valid white-label key, admins see a warning on every backoffice page.
`mgd-platform template check` (CI) fails if a template's license copy differs from the root license or a hash does
not match. Maintainers refresh hashes with `node scripts/update-license-hashes.js`.

Open source code can always be modified – the check provides transparency and evidence, the license provides the rule.

## White-label license

- EUR 500, one-time, per project and its domains.
- Proven by a signed key: `MGD1.<base64url payload>.<base64url Ed25519 signature>`.
- Payload: `type` (`whitelabel`), `project_id`, `domains` (exact or `*.example.org`), `licensee`, `issued_at`,
  optional `expires_at`.
- The public key is built into `LicenseKey::PUBLIC_KEY`; only the licensor holds the private key.
- The key is valid for the host of `app.base_url` (or the request host if `base_url` is empty).
- Stored in the `licenses` table via the license page, or in `config.php` under `license.whitelabel_key`.

## Modules (free and paid)

Modules live in `modules/<id>/` with a `module.json` ([schema](../../schema/module-package.schema.json)), an entry
file returning `function (Router $router, App $app, ModuleManifest $module)`, optional migrations (scope
`module/<id>`) and optional backoffice menu entries. They are deployed via FTP/Git – uploading executable code
through the web is intentionally not supported.

- `license: "free"` – can be enabled by admins under **Settings › Modules**.
- `license: "paid"` – requires a signed module key (`type: "module"`, `module_id`, domains).
- A broken module is logged and skipped; the site keeps running.

Paid modules and templates are maintained in a private repository and are not part of this repository.
