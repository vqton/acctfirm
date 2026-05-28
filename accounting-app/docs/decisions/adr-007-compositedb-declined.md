# ADR-007: CompositeDB Integration Declined

## Status
Accepted

## Date
2026-05-28

## Context
CompositeDB (`compositephp/db`, v0.5.0 pre-release) was proposed as a DataMapper/Table Gateway to replace raw PDO in the accounting system.

The project currently has:
- 22 Repository Interfaces + 22 PDO implementations using raw PDO + prepared statements
- 21 services that consume PDO directly for transaction control
- 49 test files (518 tests) proving correctness
- Explicit "No Composer" and "No ORM" constraints (§4.2 AGENTS.md)
- Complex SQL (financial reports across 5+ joined tables, multi-step journal transactions)

## Decision
Do NOT integrate CompositeDB.

## Alternatives Considered
- **Adopt CompositeDB + add Composer:** Would require rewriting 22+ repos, 21 services, adding Composer + Doctrine DBAL dependency. Zero business value gain for accounting precision requirements.
- **Cherry-pick code generation feature only:** A one-time PHP script (50 lines) can generate entity/repo boilerplate without any library dependency.
- **Cherry-pick caching feature only:** A `CacheRepository` decorator pattern (30 lines per repo) is more surgical than adopting a full ORM.
- **Enhance existing migration runner:** `database/migrate.php` (200 lines) already works. Can be extended without replacing.

## Consequences
- No architecture change needed. Raw PDO remains — which is a feature for accounting accuracy.
- Continued compliance with ADR-001 (no dependency hell).
- Maintained backward compatibility for all 518 tests.
- Code generation and caching can be added independently if desired, without library adoption.
