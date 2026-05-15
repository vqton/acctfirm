# AGENTS.md

## Project

Vietnamese enterprise accounting webapp. PHP 8.4, no framework, no Composer. MySQL/MariaDB via PDO. Bootstrap 5 + jQuery for UI. API returns JSON. Frontend consumes API via jQuery AJAX.

## Quick Commands

```sh
php -S 0.0.0.0:8080 -t public public/index.php   # Start server
php database/migrate.php                           # Run migrations
for f in tests/*.php; do php "$f"; done            # Run all tests
```

## Architecture

```
public/index.php → autoloader → config/services.php (DI container) → config/routes.php
→ Router::dispatch() → Controller → Repository (PDO) → MySQL

JournalService::postEntry() → Transaction + LedgerEntry → Account balance
CashService → JournalService (all cash/bank operations)
ApService/ArService → JournalService + sub-ledger tables
FsService → account balances → BC 01/02
```

- **Autoloader**: custom PSR-4-like. `Accounting\` maps to `src/Accounting/`. No Composer.
- **DI container**: plain array in `$GLOBALS['container']`. All repos/services singletons.
- **Router**: custom regex-based. Accepts `callable|array|string`.
- **Controllers**: instantiated inline in route closures. Echo JSON directly.
- **Models**: plain PHP objects with getters/setters + `toArray()`.
- **Views**: extend `layout.php` via output buffer pattern.
- **Auth**: PHP sessions. Guard in `index.php` blocks all non-login routes.

## Code Patterns

| Pattern | Convention |
|---|---|
| Interfaces | Suffix `Interface` |
| PDO impl | Prefix `PDO` |
| Indentation | 4 spaces, no tabs |
| SQL | PDO prepared statements, `?` placeholders |
| Controllers | `echo json_encode(...)`, never return |
| HTTP status | `http_response_code()` before echo |
| Audit log | `AuditLogger::log(action, resource, id, old, new, actor)` |
| Permissions | `Helpers::requirePermission(module, action)` |

## Critical Gotchas (Code Review Findings)

| Severity | Issue | Location | Fix |
|---|---|---|---|
| **RCE** | `eval()` in formula engine | `FsService.php:166` | Replace with safe arithmetic parser |
| **SQLi** | String interpolation in SQL | `CashService.php:217,243` | Use prepared statements |
| **Session fixation** | No `session_regenerate_id()` after login | `AuthController.php:58` | Add `session_regenerate_id(true)` |
| **Path traversal** | Static file serving doesn't normalize URI | `index.php:7` | Use `realpath()` or reject `..` |
| **Encapsulation** | Reflection to extract private PDO | 8 files (`*Controller::getPdo()`) | Inject PDO via constructor |
| **Transactions** | Multi-step ops not wrapped in DB transactions | `JournalService`, `InventoryService`, `CashService` | Add `beginTransaction/commit/rollback` |
| **Dead code** | `AccountingService` never used, constructor broken | `AccountingService.php` | Delete or fix |
| **CRUD duplication** | 12 identical controllers | `WarehouseController`, `DepartmentController`, etc. | Extract generic CRUD or trait |
| **N+1 queries** | Separate query per transaction for ledger entries | `PDOTransactionRepository::getAll()` | Use JOIN |
| **No CSRF** | All POST/PUT/DELETE accept JSON without token | All controllers | Add session-bound CSRF token |
| **WHERE 1=1** | Fragile SQL construction | `ApService`, `ArService` | Build WHERE array, join with AND |

## To Add a New Entity

1. Migration file in `database/migrations/`
2. Model in `src/Accounting/Domain/Model/`
3. RepositoryInterface in `src/Accounting/Domain/Repository/`
4. PDO repo in `src/Accounting/Infrastructure/Repository/`
5. Controller in `src/Accounting/Interfaces/HTTP/`
6. Routes in `config/routes.php`
7. DI entry in `config/services.php`
8. View in `public/views/` (extends layout.php)
9. Sidebar link in `public/views/layout.php`
10. Tests in `tests/`

## Database

- **Config**: `config/database.php` — dev/123456, accounting_db.
- **Migrations**: 40 files. Runner at `database/migrate.php`. Each returns `fn(PDO $pdo)`.
- **No migration tracking table** — relies on IF NOT EXISTS. Schema changes need manual handling.
- **No rollback** — one-way only.

## Active Modules

| Module | Service | Tests | Status |
|---|---|---|---|
| Cash & Bank | `CashService` | ~100 | ✅ 9 UCs |
| Inventory (10 phases) | `InventoryService` | 77 | ✅ |
| Period Engine | `PeriodService` | 18 | ✅ |
| Financial Statements (BC 01, BC 02) | `FsService` | 18 | ✅ |
| Accounts Payable (TK 331) | `ApService` | 22 | ✅ |
| Accounts Receivable (TK 131) | `ArService` | 19 | ✅ |
| Bank Reconciliation | `BankReconciliationService` | 24 | ✅ |
| RBAC | AuthController + Helpers | — | ✅ |
| Audit Log | `AuditLogger` | — | ✅ |
| Treasury Templates | CashController::transactionTemplates() | — | ✅ |

**Total:** 28 test files, ~400 tests, 0 failures.

## Skill Selection

| Phase | Skill | When |
|---|---|---|
| **Clarify intent** | `interview-me` | Don't know what you actually want |
| **Refine ideas** | `idea-refine` | Have a rough concept, need variants |
| **Define** | `spec-driven-development` | Need acceptance criteria before coding |
| **Plan** | `planning-and-task-breakdown` | Break spec into verifiable tasks |
| **Build — general** | `incremental-implementation` | Default: vertical slices, test each |
| **Build — API** | `api-and-interface-design` | REST endpoints, module contracts |
| **Build — UI** | `frontend-ui-engineering` | Bootstrap 5 views with a11y |
| **Build — verified** | `source-driven-development` | Verify patterns against official docs |
| **Build — adversarial** | `doubt-driven-development` | High-stakes / unfamiliar code |
| **Build — context** | `context-engineering` | Feed agent right files at right time |
| **Test** | `test-driven-development` | Red-green-refactor per behavior |
| **Test — browser** | `browser-testing-with-devtools` | Live DOM/console/network verify |
| **Debug** | `debugging-and-error-recovery` | Reproduce → localize → fix → guard |
| **Review** | `code-review-and-quality` | 5-axis review before merge |
| **Review — security** | `security-and-hardening` | OWASP, input validation, secrets |
| **Review — performance** | `performance-optimization` | Measure first, optimize second |
| **Simplify** | `code-simplification` | Simplify after tests pass |
| **Refactor** | `improve-codebase-architecture` | Deepen modules, extract seams |
| **Git** | `git-workflow-and-versioning` | Atomic commits, clean history |
| **CI/CD** | `ci-cd-and-automation` | Quality gates on every push |
| **Docs** | `documentation-and-adrs` | Capture why, not what |
| **Ship** | `shipping-and-launch` | Pre-launch checklist + rollback |
| **Deprecate** | `deprecation-and-migration` | Remove code safely |
| **Communication** | `caveman` | Terse mode for all output |

**Default:** `karpathy-guidelines` (simplicity first, surgical changes) + `caveman` (terse output).

## SOLID Check

| Principle | Ask |
|---|---|
| S — Single Responsibility | Does this class have one reason to change? |
| O — Open/Closed | Can I extend without modifying? |
| L — Liskov Substitution | Can a subtype replace its parent? |
| I — Interface Segregation | Are interfaces focused? |
| D — Dependency Inversion | Does high-level depend on abstractions? |

Pragmatic SOLID — not abstractions for their own sake.
