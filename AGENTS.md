# AGENTS.md

## Project

Vietnamese enterprise accounting webapp. PHP 8.4, no framework, no Composer. MySQL/MariaDB via PDO. Bootstrap 5 + jQuery for UI. API returns JSON. Frontend consumes API via jQuery AJAX.

## Quick Commands

```sh
php -S 0.0.0.0:8080 -t public public/index.php   # Start server
php database/migrate.php                           # Run migrations
for f in tests/*.php; do php "$f"; done            # Run all tests
```

## Directory Structure

```
├── config/            services.php (DI), routes.php, database.php
├── data/              Static data files (coa_circular_99.json)
├── database/          migrate.php runner + migrations/*.php
├── public/            index.php (entry + auth guard), views/ (layout.php + *.php)
├── src/Accounting/
│   ├── Domain/
│   │   ├── Model/        Plain PHP objects, getters/setters/toArray()
│   │   ├── Repository/   Interfaces (suffix Interface)
│   │   └── Service/      Business logic (JournalService, CashService, PettyCashService, GlService...)
│   ├── Infrastructure/
│   │   ├── Database/     DB.php (PDO helper), AuditLogger.php
│   │   ├── Auth.php        isAuthenticated, hasPermission, requirePermission, csrfToken, checkCsrf
│   │   ├── Helpers.php     fmt, e, isValidAccountCode, nextVoucherNo, paginate (delegates json/auth/VnWords)
│   │   ├── JsonResponse.php ok($data,$code), error($message,$code)
│   │   ├── Logging/        Logger.php, LoggingPDO.php, ActionJournal.php (request/SQL log + action journal)
│   │   ├── SessionMiddleware.php  open/close/authGuard, session_write_close for API
│   │   ├── VnWords.php     toWords($amount)
│   │   └── Repository/     PDO* implementations
│   └── Interfaces/HTTP/  Controllers
├── docs/
│   ├── specs/            10 use case specifications
│   ├── roadmaps/         7 implementation roadmaps
│   └── research/         3 supporting docs
└── tests/                29 files, ~410 tests

## Architecture

```
public/index.php → autoloader → config/services.php (DI container) → config/routes.php
→ Router::dispatch() → Controller → Repository (PDO) → MySQL

JournalService::postEntry() → Transaction + LedgerEntry → Account balance
CashService → JournalService (all cash/bank operations)
ApService/ArService → JournalService + sub-ledger tables
FsService → account balances → BC 01/02
GlService → ledger entries (date, ref, Dr, Cr, running balance, contra account)
```

- **Autoloader**: custom PSR-4-like. `Accounting\` maps to `src/Accounting/`. No Composer.
- **DI container**: plain array in `$GLOBALS['container']`. All repos/services singletons.
- **Router**: custom regex-based. Accepts `callable|array|string`.
- **Controllers**: instantiated inline in route closures. `JsonResponse::ok/error(...)` for output.
- **Models**: plain PHP objects with getters/setters + `toArray()`.
- **Views**: extend `layout.php` via output buffer pattern.
- **Auth**: PHP sessions. `SessionMiddleware` manages open/close + session_write_close for API.
- **Logging**: `Logger` + `LoggingPDO` wrap PDO for Django-style request/SQL logging.

## Code Patterns

| Pattern | Convention |
|---|---|
| Interfaces | Suffix `Interface` |
| PDO impl | Prefix `PDO` |
| Indentation | 4 spaces, no tabs |
| SQL | PDO prepared statements, `?` placeholders |
| Controllers | `JsonResponse::ok/error(...)`, never return |
| HTTP status | Pass code as 2nd arg to `JsonResponse::ok/error` |
| Audit log | `AuditLogger::log(action, resource, id, old, new, actor)` |
| Permissions | `Auth::requirePermission(module, action)` |
| Auth/CSRF | `Auth::csrfToken()`, `Auth::checkCsrf()` |
| VN words | `VnWords::toWords(float)` |

## Key Patterns

```
// Route: $router->get/post/put/delete($path, callable)
$router->get('/api/cash/accounts', function () {
    Auth::requirePermission('cash', 'read');
    JsonResponse::ok((new CashController(...))->getAccounts());
});

// DI: $GLOBALS['container']['key'] = fn($c) => new Service($c['dep']);
$GLOBALS['container'][JournalService::class] = fn($c) =>
    new JournalService($c[AccountRepo::class], $c[TransactionRepo::class]);

// Session: SessionMiddleware::open() / close() / authGuard()
SessionMiddleware::authGuard();      // Opens session, checks auth, closes lock
SessionMiddleware::close();           // session_write_close() — release lock for API

// Logging: Logger + LoggingPDO
Logger::printRequest($method, $uri, $status, $duration, $size);  // Django-style HTTP
Logger::printSQL($sql, $params, $ms);                             // Django-style SQL

// Action journal: record user actions to JSON Lines files
ActionJournal::record($method, $uri, $status, $reqBody, $resBody, $ms, $userId);
ActionJournal::setAction('auth.login');  // Override auto-generated action name

// Test: global assertEq/assertTrue with counter
assertEq($result['closing_balance'], 7000000, '111 closing = 7M');
assertTrue(count($entries) >= 2, 'At least 2 entries');
// Run: php tests/GlTest.php → "=== Results: 12 tests, 0 failed ==="

// View: ob_start extends layout.php
<?php ob_start(); ?>
<div class="container">...content...</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>

// API response: JsonResponse::ok($data, $code) / JsonResponse::error($msg, $code)
JsonResponse::ok($data, $code);   // http_response_code + echo json_encode (JSON_UNESCAPED_UNICODE)
JsonResponse::error($msg, $code); // same with ['error' => $msg] structure
```

## Critical Gotchas (Code Review Findings)

| Severity | Issue | Location | Fix |
|---|---|---|---|
| ~~RCE~~ | ~~`eval()` in formula engine~~ | ~~FsService.php~~ | ✅ Safe recursive descent parser |
| ~~SQLi~~ | ~~String interpolation in SQL~~ | ~~CashService.php~~ | ✅ Prepared statements |
| ~~Session fixation~~ | ~~No `session_regenerate_id()` after login~~ | ~~AuthController.php~~ | ✅ `session_regenerate_id(true)` |
| ~~Path traversal~~ | ~~Static file serving doesn't normalize URI~~ | ~~index.php~~ | ✅ `realpath()` check |
| ~~Encapsulation~~ | ~~Reflection to extract private PDO~~ | ~~8 controllers + InventoryService~~ | ✅ Constructor injection |
| ~~Transactions~~ | ~~Multi-step ops not wrapped throughout~~ | ~~InventoryService~~ | ✅ All 16 InventoryService methods wrapped in `beginTransaction/commit/rollback` + JournalService handles nested via `inTransaction()` |
| ~~Dead code~~ | ~~AccountingService, TransactionController~~ | ~~2 files~~ | ✅ Deleted |
| ~~CRUD duplication~~ | ~~12 identical controllers~~ | ~~Master data controllers~~ | ✅ `CrudControllerTrait` |
| ~~N+1 queries~~ | ~~Separate query per transaction~~ | ~~PDOTransactionRepository::getAll()~~ | ✅ Single JOIN |
| **No CSRF** | POST/PUT/DELETE accept JSON without token | All controllers | ✅ CSRF token in layout + login response + `/api/auth/csrf` endpoint |
| **Session lock** | Concurrent AJAX blocked by session | `index.php`, all API routes | ✅ `SessionMiddleware::close()` releases lock after auth check |
| **Missing Content-Type** | JSON APIs missing header | `CashController` | ✅ `Content-Type: application/json` on all endpoints |
| **Circular 99 fields** | Missing date, payer, amount-in-words | cash forms, transactions table | ✅ Migration 041 added `transaction_date`, `payer_name/type/id` |
| ~~Dual PDO~~ | ~~`DB::` static vs container PDO~~ | ~~no prod usage~~ | ✅ Deleted `DB.php` references; all PDO via constructor |
| ~~Dead code~~ | ~~ValuationService never wired~~ | ~~Domain/Service/~~ | ✅ Deleted |
| ~~No migration tracking~~ | ~~Every migration runs every time~~ | ~~migrate.php~~ | ✅ `_migrations` table tracks executed migrations |
| ~~COA seed in controller~~ | ~~209-line inline array in AccountController~~ | ~~AccountController.php~~ | ✅ Extracted to `data/coa_circular_99.json` |
| ~~God object CashController~~ | ~~602 lines, 9 responsibilities~~ | ~~CashController/CashService~~ | ✅ PettyCash extracted (126/130 lines removed). More to do. |
| ~~No shared test bootstrap~~ | ~~Autoloader + asserts duplicated 29x~~ | ~~tests/~~ | ✅ `tests/bootstrap.php` created, HelpersTest uses it |
| **Login page in layout** | Login page renders inside sidebar | `login.php` | ✅ Standalone HTML, no layout inclusion |
| **Logout session persist** | Session file not destroyed after logout | `index.php`, `AuthController`, `layout.php` | ✅ session_write_close removed, manual unlink, form POST instead of AJAX |
| ~~Root path publicly accessible~~ | ~~`/` in `$publicPaths` allows unauthenticated access to dashboard~~ | ~~index.php~~ | ✅ `/` removed from `$publicPaths` → redirects to `/dang-nhap` |
| ~~No session GC / timeout~~ | ~~`session.gc_probability=0` → old session files persist forever; browser PHPSESSID cookie restores old admin session in new tabs~~ | ~~SessionMiddleware.php, AuthController.php~~ | ✅ `session_set_cookie_params(httpOnly, SameSite=Lax)` + inactivity timeout (8h) + `SessionMiddleware::destroy()` extracted |

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
10. Tests in `tests/` (use `tests/bootstrap.php`)

## Database

- **Config**: `config/database.php` — dev/123456, accounting_db.
- **Migrations**: 41 files. Runner at `database/migrate.php`. Each returns `fn(PDO $pdo)`.
- **Migration tracking** — `_migrations` table tracks executed migrations. Already-run migrations are skipped.
- **No rollback** — one-way only. Schema changes need manual handling.

## Test Bootstrap

`tests/bootstrap.php` provides shared autoloader + assert helpers. Use in any test file:

```php
<?php
require __DIR__ . '/bootstrap.php';
// ... tests ...
results();
```

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
| RBAC | AuthController + Auth | — | ✅ |
| Audit Log | `AuditLogger` | — | ✅ |
| Treasury Templates | CashController::transactionTemplates() | — | ✅ |
| Petty Cash (Tạm ứng) | `PettyCashService` | 6 | ✅ |
| General Ledger (Sổ Cái) | `GlService` | 12 | ✅ |
| Action Journal | `ActionJournal` | — | ✅ |

**Total:** 29 test files, ~410 tests, 0 failures.

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
