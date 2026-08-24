# Lacvo Core

A WordPress/WooCommerce operations project focused on secure digital-product fulfilment, protected license inventory, and multi-currency order handling.

> This repository is a focused public engineering snapshot for code review, not the full production distribution package. Production credentials, customer/order data, private license inventory, backups, and site-specific integrations are intentionally excluded.

## Engineering examples in this snapshot

- AES-256-GCM encryption for license values stored at rest
- Keyed HMAC fingerprints for duplicate detection without plaintext lookup
- Indexed custom WordPress table for digital license inventory
- MySQL advisory locking plus transactions and `FOR UPDATE` allocation
- Idempotent WooCommerce order fulfilment on processing/completed status hooks
- Ownership-aware display of assigned digital codes
- Multi-currency product conversion with nonce-protected currency switching
- Immutable order exchange-rate/base-total snapshots for historical accuracy
- WooCommerce HPOS and Cart/Checkout Blocks compatibility declaration
- GitHub Actions PHP 8.0, 8.2, and 8.4 syntax matrix

## Tech stack

PHP 8+ · WordPress · WooCommerce · MySQL · WordPress/WooCommerce hooks · GitHub Actions

## Production project scope

The larger private project also contains store administration, anti-spam, promotions, newsletters/email delivery, product fields, analytics, and additional customer workflows. Those production-only services and data are intentionally not all published here.

## Architecture

See [`docs/architecture.md`](docs/architecture.md) for the reliability and security decisions behind inventory allocation and order fulfilment.

## My contribution

I owned the operational requirements and architecture direction, WooCommerce integration decisions, iterative implementation, security priorities, debugging, QA, and store-workflow logic for this portfolio project.

## License

GPL-2.0-or-later.
