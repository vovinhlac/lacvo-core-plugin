# Contributing

This repository is a focused WordPress/WooCommerce operations portfolio. Changes should be issue-driven, small enough to review, and safe around order/customer data.

## Git workflow

1. Start with an issue describing the problem, scope, risks, and acceptance criteria.
2. Branch from `main` using `feat/`, `fix/`, `refactor/`, `perf/`, `security/`, `ci/`, `docs/`, or `chore/`.
3. Keep commits atomic and use clear Conventional Commit-style subjects.
4. Open a pull request that links the issue and documents validation and operational impact.
5. Merge only after required CI checks pass.

## Local checks

```bash
composer install
composer validate --strict --no-check-lock
composer syntax
composer lint:wpcs
```

## WooCommerce/PHP expectations

- Keep fulfilment idempotent because WooCommerce hooks may run more than once.
- Treat inventory assignment as a concurrency-sensitive operation.
- Use `$wpdb->prepare()` for dynamic SQL values and deliberate indexes for operational queries.
- Validate and sanitize input before persistence; escape output at the rendering boundary.
- Require capability and nonce checks for privileged mutations.
- Do not log or commit plaintext license inventory, customer/order data, credentials, payment secrets, salts, database exports, or production logs.
- Document schema, migration, currency, or compatibility impact in the pull request.
