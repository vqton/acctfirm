# Authentication & Page Loading Flow — Refactor Plan

## 1. Anti-Patterns Detected in Current Flow

| # | Anti-Pattern | Severity | Description |
|---|---|---|---|
| A1 | **CSRF tied to page rendering** | HIGH | CSRF token is generated in layout.php and exposed via `var csrf = ...` in HTML. API tools (curl, Playwright) must load a full HTML page before making state-changing requests. No dedicated CSRF endpoint exists. |
| A2 | **Session lock blocks API concurrency** | HIGH | `session_start()` in index.php locks the PHP session file. AJAX calls from the same session block until the page request finishes. |
| A3 | **Login redirect is fixed** | MEDIUM | `window.location.href='/'` after login always goes to dashboard. If user was on `/thu/quy-tien-mat` before session expired, they lose context. |
| A4 | **Full-page reload navigation** | MEDIUM | Every sidebar click causes a full HTTP request → PHP render → HTML response. No SPA pattern. APIs exist but frontend doesn't consume them for navigation. |
| A5 | **Auth guard in index.php** | MEDIUM | Auth logic is mixed with autoloader, logging, output buffering, and routing dispatch in a single monolithic `index.php`. |
| A6 | **API vs View routes intermixed** | LOW | routes.php mixes API routes and page-view routes in the same flat list with no clear grouping boundary. |
| A7 | **401 redirect for pages** | LOW | When session expires on a page view, user is redirected to `/dang-nhap` without a redirect-back parameter. |

---

## 2. Proposed Request Lifecycle

```
┌─────────────────────────────────────────────────────────┐
│                   1. BOOTSTRAP                           │
│  autoload → logging → ob_start → session_start → guard  │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                 2. ROUTE DISPATCH                         │
│  public:  login page, auth API, static files             │
│  guarded: API routes, view routes                        │
└────────────────────┬────────────────────────────────────┘
                     │
          ┌──────────┴──────────┐
          ▼                     ▼
┌──────────────────┐  ┌──────────────────┐
│  3a. API REQUEST  │  │ 3b. VIEW REQUEST  │
│  JSON in/out      │  │ HTML rendered      │
│  Stateless        │  │ CSRF embedded      │
│  CSRF from header │  │ Session-based      │
└──────────────────┘  └──────────────────┘
```

### Target Lifecycle:

```
┌──────────────────────────────────────────────────────────┐
│ 1. KERNEL BOOTSTRAP                                       │
│    autoload → env → error handler → session → container   │
│    ── pure infrastructure, no business logic              │
└──────────────────────┬───────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────┐
│ 2. ROUTER                                                │
│    match route → apply middleware chain                   │
│    ── separate API middleware vs View middleware          │
└──────┬────────────────────────────────────────┬──────────┘
       │                                        │
       ▼                                        ▼
┌──────────────┐                    ┌──────────────────────┐
│ API ROUTE    │                    │ VIEW ROUTE           │
├──────────────┤                    ├──────────────────────┤
│ CORS headers │                    │ CSRF embed in layout │
│ JSON parse   │                    │ Auth state in meta   │
│ Validate     │                    │ No session lock for  │
│ Process      │                    │ concurrent API calls │
│ JSON respond │                    │                      │
└──────────────┘                    └──────────────────────┘
```

---

## 3. Refactored Sequence Flow

### Current Flow:
```
Browser                     Server
  │                           │
  ├── GET /dashboard ────────►│
  │                           ├── session_start() [LOCK]
  │                           ├── render layout + csrf
  │                           ├── session_write_close()
  │◄──────── HTML ───────────│
  │                           │
  ├── POST /api/auth/login ──►│
  │              [BLOCKS if session still locked from above]
  │                           ├── session_start() [WAIT]
  │                           ├── verify credentials
  │                           ├── session_regenerate_id()
  │◄──────── JSON ───────────│
  │                           │
  ├── GET /thu/quy-tien-mat ─►│
  │                           ├── session_start() [LOCK]
  │                           ├── render view
  │◄──────── HTML ───────────│
  │                           │
  ├── GET /api/cash/accounts ►│
  │              [BLOCKS while session locked from page]
  │                           ├── session_start() [WAIT]
  │                           ├── query DB
  │◄──────── JSON ───────────│
```

### Target Flow:
```
Browser                     Server
  │                           │
  │  ╔══════════════════════════════════════╗
  │  ║  STEP 1: AUTH BOOTSTRAP              ║
  │  ║  No page load needed for API testing ║
  │  ╚══════════════════════════════════════╝
  │                           │
  ├── GET /api/auth/csrf ────►│
  │                           ├── generate token
  │                           ├── store in session
  │                           ├── session_write_close()
  │◄── {token: "abc123"} ────│
  │                           │
  ├── POST /api/auth/login ──►│
  │  headers: X-CSRF-Token    │
  │  body: {user, pass}       │
  │                           ├── session_start()
  │                           ├── validate CSRF
  │                           ├── verify credentials
  │                           ├── session_regenerate_id()
  │                           ├── set session data
  │                           ├── session_write_close()
  │◄── {user, roles, csrf} ──│
  │                           │
  │  ╔══════════════════════════════════════╗
  │  ║  STEP 2: APPLICATION SHELL           ║
  │  ║  Single full-page load               ║
  │  ╚══════════════════════════════════════╝
  │                           │
  ├── GET /app ───────────────►│
  │  Cookie: PHPSESSID=xxx    │
  │                           ├── session_start()
  │                           ├── auth guard check
  │                           ├── render shell HTML
  │                           │   (sidebar, header, router,
  │                           │    CSRF token, no content)
  │                           ├── session_write_close()
  │◄── HTML shell ───────────│
  │                           │
  │  ╔══════════════════════════════════════╗
  │  ║  STEP 3: SPA-LIKE API CONSUMPTION   ║
  │  ║  APIs called independently from      ║
  │  ║  page rendering, no session lock     ║
  │  ╚══════════════════════════════════════╝
  │                           │
  ├── GET /api/cash/accounts ►│
  │  No session_write_close   │  ← session already closed
  │  needed, no blocking      │
  │                           ├── session_start()
  │                           ├── auth check (fast)
  │                           ├── session_write_close()
  │                           ├── query DB
  │◄── JSON accounts ────────│
  │                           │
  ├── POST /api/cash/receipts►│
  │  X-CSRF-Token: abc123    │
  │                           ├── session_start()
  │                           ├── validate CSRF
  │                           ├── process receipt
  │                           ├── session_write_close()
  │◄── JSON result ──────────│
```

---

## 4. Frontend / Backend Responsibilities

### Backend Responsibilities

| Layer | Component | Responsibility |
|---|---|---|
| **Kernel** | `public/index.php` | Bootstrap only: autoload, env, error handler, request logging. No session, no auth, no routing. |
| **Session** | `SessionMiddleware` | Start session, check auth, write-close after read. Separate middleware for API vs View. |
| **Auth** | `AuthController` | Login/logout/me/csrf-token. Returns JSON. No HTML. |
| **CSRF** | `Helpers::csrfToken()` | Generates on demand. Exposed via `GET /api/auth/csrf`. |
| **Router** | `Router` | Route matching + middleware pipeline execution. |
| **API** | All `*Controller` | JSON in, JSON out. No session lock held during business logic. |
| **View** | `Layout` + page views | HTML rendering only. CSRF injected at render time. |

### Frontend Responsibilities

| Layer | Component | Responsibility |
|---|---|---|
| **Auth** | `auth.js` | CSRF fetch → login → store session → redirect. Handles 401 by redirecting to login with return URL. |
| **Shell** | `app.js` | Load sidebar, header, client-side router. One-time full-page load. |
| **Modules** | per-view JS | API calls via centralized client (CSRF injected, base URL). Independent initialization. |

### API Contract Changes

| New Endpoint | Method | Purpose |
|---|---|---|
| `GET /api/auth/csrf` | Public | Returns `{token: "..."}`. No auth required. Stores token in session. |
| `GET /api/auth/me` | Authenticated | Returns `{user, roles, permissions}`. Session check endpoint. |
| `POST /api/auth/login` | Public | Returns `{user, roles, csrf}`. Includes new CSRF token for subsequent requests. |

### Backward Compatible Changes (existing unchanged)

| Existing Endpoint | Method | Notes |
|---|---|---|
| `POST /api/auth/login` | Same | Enhanced to return `csrf` field. Old clients unaffected. |
| `/dang-nhap` | GET | Still works. No change. |
| `var csrf = ...` in layout | Same | Still injected. No change. |
| `X-CSRF-Token` header | Same | Checked by `Helpers::checkCsrf()`. No change. |

---

## 5. Session Lock Mitigation Strategy

### Current Problem
PHP locks the session file on `session_start()` until script end or `session_write_close()`. Parallel AJAX calls from the same browser block on this lock.

### Solution: Read-Close pattern for API routes

```php
// In API middleware (not view routes):
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit;
}
session_write_close(); // ← RELEASE LOCK immediately
// Business logic runs WITHOUT session lock
// API can process while other requests come in
```

### When to use session_write_close:
- API GET endpoints (read-only after auth check)
- API POST endpoints that don't write to session
- Long-running operations (reports, exports)

### When NOT to use session_write_close:
- Login/logout (modifies session)
- CSRF token generation (modifies session)
- Any endpoint that writes session data

### Implementation: SessionMiddleware

```php
class SessionMiddleware
{
    public static function open(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function close(): void
    {
        session_write_close();
    }

    public static function authGuard(): void
    {
        self::open();
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Chưa đăng nhập']);
            exit;
        }
        self::close(); // Release lock immediately after auth check
    }
}
```

---

## 6. CSRF Decoupling Strategy

### Current: CSRF embedded in HTML layout
```php
// layout.php
var csrf = <?= json_encode(Helpers::csrfToken()) ?>;
```
Problem: API-only consumers must load a full HTML page to get a token.

### Target: CSRF via dedicated endpoint + HTML fallback

```php
// New route
$router->get('/api/auth/csrf', function() {
    echo json_encode(['token' => Helpers::csrfToken()]);
});
```

### Frontend flow for SPA/API clients:
```javascript
// 1. Get CSRF token via API
const {token} = await fetch('/api/auth/csrf').then(r => r.json());

// 2. Use in subsequent requests
fetch('/api/cash/receipts', {
    method: 'POST',
    headers: {'X-CSRF-Token': token, 'Content-Type': 'application/json'},
    body: JSON.stringify(data)
});
```

### Backward compatibility:
- `layout.php` continues to embed `var csrf = ...` for traditional page loads
- `Helpers::checkCsrf()` checks `X-CSRF-Token` header unchanged
- Existing page-based JS continues to work without modification

---

## 7. Login Redirect with Context Preservation

### Current:
```javascript
// login.php
success: function() { window.location.href = '/'; }
```

### Target:
```javascript
// login.php  
success: function() {
    const redirect = new URLSearchParams(location.search).get('return') || '/';
    window.location.href = redirect;
}
```

### Backend: Auth guard stores original URL
```php
// index.php
if (!isset($_SESSION['user']) && !in_array($uri, $publicPaths)) {
    $return = urlencode($uri);
    header("Location: /dang-nhap?return={$return}");
    exit;
}
```

### Login page preserves return URL:
```php
// login.php
$return = htmlspecialchars($_GET['return'] ?? '/');
// In JS:
// success: window.location.href = '<?= $return ?>';
```

---

## 8. Migration Strategy

### Phase 1 — Quick Wins (Backward Compatible, No Breakage)

```
Week 1:
  [ ] Add GET /api/auth/csrf endpoint
  [ ] Add session_write_close() to GET API routes
  [ ] Add return URL parameter to login redirect (auth guard)
  [ ] Update login.js to redirect to return URL

Week 2:
  [ ] Add session middleware class
  [ ] Update API controllers to call SessionMiddleware::close()
  [ ] Verify all existing tests still pass
  [ ] Add tests for CSRF endpoint
```

### Phase 2 — Architecture Shift

```
Week 3-4:
  [ ] Create API middleware pipeline in Router
  [ ] Split routes.php into api.php and views.php
  [ ] Extract session_start + auth guard from index.php into middleware
  [ ] Strip index.php down to pure bootstrap
  [ ] Add session_write_close() to POST API routes that don't write session
```

### Phase 3 — SPA Optional

```
Week 5+:
  [ ] Build application shell page (sidebar + header + content div)
  [ ] Client-side router intercepts sidebar clicks
  [ ] Fetch page content via AJAX instead of full reload
  [ ] PushState for URL management
  [ ] Responsive shell with loading states
```

---

## 9. Example Pseudo Code

### index.php (refactored bootstrap):
```php
<?php
// 1. Bootstrap (no session, no auth)
spl_autoload_register(/* ... */);
set_error_handler(/* ... */);
Logger::init();

// 2. Capture request
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 3. Static files — bypass everything
if (isStaticFile($uri)) { serveStatic($uri); return; }

// 4. Load DI container
require __DIR__ . '/../config/services.php';

// 5. Load routes
$router = new Router();
require __DIR__ . '/../config/routes.php';

// 6. Dispatch (middleware chain handles session/auth)
$router->dispatch();
```

### SessionMiddleware:
```php
class SessionMiddleware
{
    public static function open(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function authGuard(): array
    {
        self::open();
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Chưa đăng nhập']);
            exit;
        }
        $user = $_SESSION['user'];
        $permissions = $_SESSION['permissions'] ?? [];
        self::close();
        return ['user' => $user, 'permissions' => $permissions];
    }
}
```

### API Controller (refactored):
```php
public function accounts(): void
{
    header('Cache-Control: no-cache');
    SessionMiddleware::authGuard(); // Opens session, checks auth, closes lock
    
    $all = $this->accountRepo->findAll();
    $result = [];
    foreach ($all as $a) {
        if ($a->isControl()) continue;
        $result[] = ['code' => $a->getCode(), 'name' => $a->getName()];
    }
    echo json_encode($result);
}
```

### Router with Middleware (refactored):
```php
class Router
{
    private array $routes = [];

    public function addRoute(string $method, string $pattern, $handler, array $middleware = []): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'middleware');
    }

    public function api(string $method, string $pattern, $handler): void
    {
        $this->addRoute($method, $pattern, $handler, ['api']);
    }

    public function view(string $method, string $pattern, $handler): void
    {
        $this->addRoute($method, $pattern, $handler, ['view']);
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            $regex = $this->patternToRegex($route['pattern']);
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $this->runMiddleware($route['middleware']);
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        HttpError::notFound();
    }

    private function runMiddleware(array $chain): void
    {
        if (in_array('api', $chain)) {
            SessionMiddleware::authGuard(); // Opens + closes session
        }
        if (in_array('view', $chain)) {
            SessionMiddleware::open(); // Opens, keeps open for render
            // View middleware renders full page, closes at script end
        }
    }
}
```

### frontend-api-client.js (centralized CSRF + session):
```javascript
class ApiClient {
    constructor() {
        this.csrf = null;
        this.base = '';
    }

    async init() {
        // Get CSRF token without page load
        const res = await fetch('/api/auth/csrf');
        const data = await res.json();
        this.csrf = data.token;
        return this;
    }

    async request(method, path, body = null) {
        const headers = {
            'X-CSRF-Token': this.csrf,
            'Content-Type': 'application/json',
        };
        const res = await fetch(this.base + path, {
            method,
            headers,
            body: body ? JSON.stringify(body) : null,
        });
        if (res.status === 401) {
            window.location.href = '/dang-nhap?return=' + encodeURIComponent(window.location.pathname);
            return;
        }
        const data = await res.json();
        // Update CSRF from response if provided
        if (data.csrf) this.csrf = data.csrf;
        return data;
    }

    get(path) { return this.request('GET', path); }
    post(path, body) { return this.request('POST', path, body); }
}
```

### Receipt module initialization (independent):
```javascript
// cash_receipts.js — no page load dependency
const api = new ApiClient();

async function initReceiptModule() {
    await api.init();
    const accounts = await api.get('/api/cash/accounts');
    const receipts = await api.get('/api/cash/receipts');
    renderAccounts(accounts);
    renderReceipts(receipts);
}

function renderAccounts(list) {
    const select = document.getElementById('creditAccount');
    select.innerHTML = '<option value="">-- Chọn --</option>' +
        list.map(a => `<option value="${a.code}">${a.code} - ${a.name}</option>`).join('');
}

document.addEventListener('DOMContentLoaded', initReceiptModule);
```

---

## 10. Testability Improvements

| Tool / Scenario | Current | Target |
|---|---|---|
| **Playwright test** | Must load dashboard page first to get CSRF | Call `GET /api/auth/csrf` directly, then login, then test |
| **curl API test** | Must parse HTML to extract CSRF | Call `/api/auth/csrf` → token in JSON response |
| **Unit test controller** | Requires session mocking | `SessionMiddleware::authGuard()` returns `$user` array for assertions |
| **Concurrent API calls** | Blocked by session lock | Session lock released after auth check |
| **Session expiry** | 401 with no context | 401 + redirect URL in response body |

---

## 11. Key Metrics

| Metric | Before | After |
|---|---|---|
| API routes without CSRF endpoint | 0 | 1 (`GET /api/auth/csrf`) |
| Session lock held during business logic | All requests | Read-only API: released after auth |
| Full page loads per module init | 1 (CSRF) + 1 (module) | 0 (CSRF via API) + 1 (module) |
| curl commands to test an API | 2 (load page + API) | 2 (get CSRF + API) still but no HTML parsing |
| Login redirect URL preserved | No | Yes |
| Middleware pattern | None | API/View middleware chains |
