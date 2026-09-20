# Modules and Plugins

## Goal

Provide WordPress-like extensibility without inheriting the risk of arbitrary executable uploads.

## Module types

- core
- feature
- commercial
- adapter
- theme
- skin

## Safe default

Modules are reviewed and deployed with the application.

Store purchases or plan changes grant **entitlements**, not arbitrary code download.

## Manifest

See `schema/module-manifest.schema.json`.

A manifest may declare:

- id/version
- type
- dependencies
- permissions
- events
- translations
- backoffice integration
- entitlement
- data classification

## Extension points

Prefer explicit extension points:

- dashboard widgets
- navigation sections
- resource tabs
- settings panels
- event subscribers

## Safe mode

A platform should be able to start with:

- core only
- default theme
- optional modules disabled

This is useful after failed updates.

## Deactivation

Disabling a module should not silently delete its data.

Data deletion is a separate, explicit workflow.
