# ADR-011: Data-Driven Business Rules via business_config

**Status:** Accepted
**Date:** 2026-06-02
**Supersedes:** All hardcoded constants in services
**Related:** ADR-010 (vat-groups data-driven)

## Context

Every Vietnamese accounting system has ~40+ business values that change annually: insurance rates, tax brackets, deduction amounts, fee caps. Previously hardcoded as PHP constants:

```php
private const BHXH_EE = 0.08;    // changes yearly
private const PIT_BRACKETS = [...]; // changes with new Luật TNCN
```

Each change = code deploy. For a system used by SMEs without dedicated IT, this is unacceptable.

## Decision

Store all business rules in a `business_config` table. Provide `ConfigService` with type casting + in-memory cache. Services receive `?ConfigService` via DI (nullable = safe defaults).

## Alternatives Considered

- **PHP Constants file**: Still requires deploy. Rejected.
- **JSON config file**: Not writable via API. Rejected.
- **Environment variables**: Annoying for 40+ values. Rejected.
- **Separate table per domain**: Too many tables. Single `business_config` wins.

## Consequences

- Positive: Change any rate via `UPDATE business_config` — no deploy
- Positive: OCP compliance — new business rules add data row, not code
- Positive: Backward compatible — nullable ConfigService means existing code works unchanged
- Negative: DB read on first access (mitigated by in-memory cache)
- Negative: Schema-less values — no FK validation (mitigated by config_type column + unit tests)
