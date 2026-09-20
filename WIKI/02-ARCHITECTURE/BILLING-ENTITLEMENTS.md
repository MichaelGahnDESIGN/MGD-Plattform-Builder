# Billing and Entitlements

## Separate payment from permission

A successful payment does not directly become authorization logic.

Use:

1. product/plan
2. verified payment/subscription event
3. entitlement grant
4. capability/feature check

## Typical entities

- products
- prices
- plans
- subscriptions
- entitlements
- entitlement grants
- payment provider references
- webhook/event ledger

## Webhooks

Treat payment provider server events as authoritative only after signature verification.

Store provider event IDs with uniqueness to prevent duplicate/replay processing.

## Privacy

Store only the payment metadata required for product, support, accounting and legal obligations.

Avoid storing full payment instrument details.

## Support

Support roles should receive only the minimum billing metadata needed for a concrete case.
