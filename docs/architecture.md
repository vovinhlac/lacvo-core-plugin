# Architecture notes

Lacvo Core keeps operational responsibilities in focused service classes rather than a single large plugin bootstrap.

## Boundaries

- **Inventory security:** encryption/fingerprinting and database allocation are isolated from presentation.
- **Order delivery:** WooCommerce status hooks orchestrate fulfilment but delegate inventory storage to a repository.
- **Currency:** current rates are separate from immutable order snapshots.
- **Storefront/UI:** public rendering receives already-validated domain data where possible.
- **Administration:** privileged mutations require WordPress capability and nonce checks.

## Reliability decisions

License assignment must be idempotent because WooCommerce status hooks can fire more than once. Allocation therefore checks existing assignments first, obtains a short database advisory lock per order item, starts a transaction, selects available inventory with `FOR UPDATE`, and only commits after every required code is reserved.

Sensitive license values are encrypted at rest. Duplicate detection uses a keyed HMAC fingerprint so uniqueness checks do not require plaintext storage.

## Public snapshot

This repository intentionally publishes selected engineering components rather than production credentials, customer/order data, private license inventory, or deployment configuration.
