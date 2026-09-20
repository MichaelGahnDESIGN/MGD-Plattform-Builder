# Internationalization and Translations

## Separate language availability from market launch

A project may prepare English while only operating commercially in one country.

## Translation keys

Avoid scattering user-facing text across source files.

Use stable keys such as:

- `nav.settings`
- `support.ticket.status.open`
- `privacy.export.title`

## Translation states

Useful states:

- missing
- draft
- machine
- reviewed
- approved

## Admin editor

A mature backoffice may provide:

- search
- locale filters
- namespace filters
- missing strings
- side-by-side comparison
- import/export
- history
- approval

## AI translations

AI may propose translations, but legal/safety-critical texts should have an explicit review process.

## Placeholders

Validate placeholders during import.

A translation must not silently remove required variables such as `{count}` or `{name}`.
