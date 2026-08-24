# Lacvo Core

A WordPress/WooCommerce operations plugin for digital-product delivery, licensing, multi-currency, anti-spam, promotions, newsletters, email delivery, and custom store workflows.

> This public repository is a focused engineering snapshot for code review, not the full production distribution package. Production credentials, customer/order data, private license inventory, backups, and site-specific configuration are intentionally excluded.

## What I built

I designed the operational requirements and plugin architecture, implemented and iterated focused PHP classes around WooCommerce/WordPress workflows, and handled testing, security hardening, debugging, and integration decisions.

## Highlights

- Digital-product delivery and WooCommerce account integrations
- Protected license inventory and allocation workflows
- Multi-currency conversion with order exchange-rate snapshots
- Anti-spam validation and rate-limiting patterns
- Promotions and store operations workflows
- WordPress/WooCommerce hooks, validation, sanitization, and capability-aware admin logic

## Engineering examples in this snapshot

- AES-256-GCM encryption for license values at rest
- HMAC fingerprints for duplicate detection without plaintext lookup
- Transaction + database advisory locking for license allocation
- Idempotent WooCommerce order fulfilment
- Rate snapshots so historic orders do not change when current FX rates change
- Nonce/capability-aware WordPress integration patterns

## Tech stack

PHP 8+ · WordPress · WooCommerce · MySQL · JavaScript · CSS · REST/AJAX integrations

## My role

I owned the requirements, plugin architecture decisions, WooCommerce integration, iterative implementation, custom admin workflows, security priorities, QA, and operational logic for this portfolio project.

## License

GPL-2.0-or-later.
