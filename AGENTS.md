# AGENTS.md

## Project

Vietnamese enterprise accounting webapp. PHP 8.4, no framework, no Composer. MySQL/MariaDB via PDO. Bootstrap 5 + jQuery for UI. API returns JSON. Frontend consumes API via jQuery AJAX.

## Entry point

`public/index.php` is the single entry point. PHP built-in server:

```sh
php -S 0.0.0.0:8080 -t public public/index.php
```

## Architecture

```
public/index.php → autoloader → config/services.php (DI container) → config/routes.php → Router::dispatch() → Controller → Repository (PDO) → MySQL
```

- **Autoloader**: custom PSR-4-like. Namespace `Accounting\` maps to `src/Accounting/`. No Composer autoload.
- **DI container**: a plain array in `$GLOBALS['container']`. Created by `config/services.php`. All repos are singletons created once.
- **Router**: custom regex-based. Route `{id}` params become function args. Router accepts `callable|array|string` as handler (not strict `callable`).
- **Controllers**: instantiated inline in route closures. Injected with the one repo they need from `$GLOBALS['container']`. Controllers echo JSON directly — no return value.
- **Models**: plain PHP objects with getters/setters + `toArray()`. No ORM.
- **Error handling**: `HttpError` helper (json/html), global exception handler in `index.php` catches all uncaught `Throwable` → JSON 500
- **Layout**: all frontend views extend `public/views/layout.php` via output buffer pattern: `ob_start()` → content → `$content = ob_get_clean(); require __DIR__ . '/layout.php'`
- **Content-Type**: API (`/api/*`) → `application/json` (set in `index.php`), HTML pages → `text/html` (set in `layout.php`)

## Database

- **Config**: `config/database.php` — credentials: `dev` / `123456`, db: `accounting_db`. Only place credentials exist.
- **Migrations**: `php database/migrate.php` runs all `database/migrations/*.php` in sorted order.
- **Each migration file** returns a `fn(PDO $pdo) { ... }` closure. No DB connection in migration files.
- **Schema**: 19 tables — accounts, transactions, ledger_entries, items, customers, suppliers, warehouses, departments, employees, uoms, ccdc, bank_accounts, exchange_rates, tax_rates, fixed_assets, valuation_methods, contracts, projects, depreciation_policies.

## Routes

Defined in `config/routes.php`. Two styles:
- **Frontend**: closures that `require` a PHP view file. These render Bootstrap HTML via `layout.php`.
- **API**: closures that instantiate controller + call method. These echo JSON.

Active endpoints:
```
GET  /                          → views/dashboard.php + layout.php
GET  /danh-muc/vat-tu           → items CRUD page
GET  /danh-muc/khach-hang       → customers CRUD page
GET  /danh-muc/nha-cung-cap     → suppliers CRUD page

GET/POST    /api/items[/{id}]
PUT/DELETE  /api/items/{id}
... same pattern for /api/customers, /api/suppliers
```

To add a new entity: (1) migration file, (2) Model, (3) RepositoryInterface, (4) PDO repository, (5) Controller, (6) route closure in `config/routes.php`, (7) DI entry in `config/services.php`, (8) view file using layout.php, (9) sidebar link in `layout.php`.

## Style conventions

- 4-space indentation in PHP. No tabs.
- Namespace: `Accounting\{Domain|Infrastructure|Interfaces}\...`
- Interfaces suffix `Interface`, PDO impl prefix `PDO`, e.g. `ItemRepositoryInterface` / `PDOItemRepository`.
- All SQL uses PDO prepared statements with positional `?` placeholders.
- Controllers never return — they `echo json_encode(...)` directly.
- HTTP status codes set manually via `http_response_code()`.
- Views extend `layout.php` via output buffer pattern — only content HTML in the view file.
- JS `esc(str)` function available in all views for XSS-safe string output.
- All views use Bootstrap 5 + Bootstrap Icons + jQuery 3 via CDN (included once in `layout.php`).
- Error responses use `HttpError::json()` for API or `HttpError::html()` for full-page error.

## Skill selection (4WH)

Before starting any task, use 4WH to match the right skill to the task type:

| Dimension | What to ask |
|---|---|
| **What** | What kind of work is this? (new feature / bug fix / refactor / test) |
| **Why** | What outcome matters? (correctness / speed / maintainability / clarity) |
| **Where** | What part of the codebase does it touch? (new code / existing code / config) |
| **When** | How urgent? How large? (single file / multi-step / multi-session) |
| **Who** | Who benefits? (developer / end user / auditor) |
| **How** | Which skill fits best? |

Available skills and their fit:
- **karpathy-guidelines**: new features, simple tasks, any change where keeping scope small matters (default choice)
- **diagnose**: bugs, regressions, performance issues, unexpected behavior
- **improve-codebase-architecture**: refactoring, consolidation, extracting seams for testability
- **tdd**: complex logic, critical financial calculations, regression-prone areas
- **caveman**: communication mode only (not an implementation skill)

When in doubt, default to **karpathy-guidelines** (simplicity first, surgical changes). Use multiple skills when a task spans categories.

## SOLID check for new features

When implementing any new feature, review whether SOLID principles apply. Do not force abstractions — but check each principle for a natural fit:

| Principle | What to ask |
|---|---|
| **S** — Single Responsibility | Does this class/module have one reason to change? Can I split it? |
| **O** — Open/Closed | Will I need to modify this code to add a new variant, or can I extend it? |
| **L** — Liskov Substitution | If using inheritance or interfaces, can a subtype replace its parent without breaking callers? |
| **I** — Interface Segregation | Are interfaces focused, or do they force implementors to define unused methods? |
| **D** — Dependency Inversion | Does the high-level policy depend on abstractions, not concrete implementations? |

The goal is pragmatic SOLID — not abstractions for their own sake. A plain function is better than a one-implementation interface. An interface earns its keep when a second implementation appears or when you need a test seam.

## Gotchas

- Router's `callable` typehint was removed — `[ClassName::class, 'method']` won't autoload the class at route definition time. Use closures that instantiate the controller instead.
- `config/services.php` writes `$GLOBALS['container']`. `config/routes.php` writes `$GLOBALS['router']`. Order matters: services first, then routes.
- Migration runner auto-creates the database if it doesn't exist.
- All views use Bootstrap 5 + Bootstrap Icons + jQuery 3 via CDN — no local assets.
- `Content-Type` is set globally: `application/json` in `index.php`, overridden to `text/html` in `layout.php` for HTML pages.
- Controllers must set `http_response_code()` before `echo` — the global exception handler catches any uncaught `Throwable` as JSON 500.
