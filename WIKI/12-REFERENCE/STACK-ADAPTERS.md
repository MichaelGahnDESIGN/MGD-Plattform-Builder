# Stack Adapters

The foundation is stack-neutral.

Adapters may provide concrete implementation patterns for:

- PHP + MariaDB
- Symfony
- Laravel
- Node + PostgreSQL
- Python + PostgreSQL
- Go
- Godot backends/clients
- Flutter clients
- native mobile apps

## Adapter responsibilities

An adapter should map foundation concepts to stack-native mechanisms:

- dependency injection
- routing
- migrations
- policies
- background jobs
- configuration
- testing
- Docker

## Adapter rule

Do not change the core security/privacy meaning just because a framework has a convenient shortcut.

For example, client-side route guards are not a replacement for server-side authorization.
