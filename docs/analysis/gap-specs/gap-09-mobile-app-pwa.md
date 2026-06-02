# Gap 9: Ứng dụng Di động (PWA) — Parity Specification

**Priority:** P2 (Nice-to-have)
**Phase 1 (PWA):** 3-5 ngày
**Phase 2 (Native):** 10-14 ngày (chỉ khi có nhu cầu thị trường)

---

## 1. Business Context

### 1.1 Vấn đề

Bookwise hiện là web app desktop-only. Sidebar 250px fixed, bảng dữ liệu rộng, font nhỏ (13px) — không dùng được trên màn hình < 768px. Giám đốc/Kế toán trưởng muốn xem tổng quan, duyệt chứng từ trên điện thoại nhưng không thể.

### 1.2 Đối thủ

| Đối thủ | Mobile | Ghi chú |
|---|---|---|
| MISA AMIS | ✅ iOS + Android native | Đầy đủ: duyệt, dashboard, báo cáo, push notification |
| Fast Accounting | ✅ Web responsive | PWA-like, không cần cài app |
| 1C:Enterprise | ✅ Native | Offline-first, sync khi có mạng |
| MISA SME | ✅ Web responsive | Bootstrap-based, tương tự Bookwise |

### 1.3 Use Cases (từ 10-gaps analysis)

- **UC 9.1:** Dashboard mobile (read-only) — doanh thu, chi phí, lợi nhuận, công nợ đến hạn, tồn kho thấp, dòng tiền
- **UC 9.2:** Duyệt chứng từ mobile — nhận thông báo → xem chi tiết → duyệt/từ chối
- **UC 9.3:** Tra cứu nhanh — tìm chứng từ theo số CT, tên nhà cung cấp, khách hàng

### 1.4 Target Users (Mobile)

| Role | Need | Frequency |
|---|---|---|
| Giám đốc | Duyệt chứng từ nhanh, xem KPI | Hàng ngày |
| Kế toán trưởng | Kiểm tra số dư, duyệt bút toán lớn | Hàng ngày |
| Chủ doanh nghiệp | Dashboard tổng quan, dòng tiền | Hàng tuần |
| Kế toán viên | Tra cứu chứng từ khi đi công tác | Không thường xuyên |

---

## 2. Architecture Decision: PWA-first

### 2.1 Lý do

1. **Zero build tooling** — không cần thêm Webpack/Vite/Babel vào stack hiện tại
2. **Same PHP backend** — không cần REST API mới (đã có JSON endpoints)
3. **Bootstrap 5 đã responsive** — chỉ cần thêm CSS/JS overlay, không cần rewrite
4. **Không cần App Store** — deploy qua HTTPS là có ngay
5. **ROI thấp** — P2 feature, không đầu tư lớn

### 2.2 Quyết định

```
Phase 1 (PWA) — ưu tiên thực hiện nếu có thời gian:
  └─ manifest.json + Service Worker
  └─ Responsive CSS overlay (không sửa view hiện tại)
  └─ Dashboard mobile (read-only KPI cards)
  └─ Approval queue mobile (duyệt/từ chối)
  └─ Notification polling (periodic API poll → browser notification)
  └─ Quick Search (modal tìm kiếm toàn hệ thống)

Phase 2 (Native) — chỉ khi có > 10 khách hàng yêu cầu:
  └─ Flutter codebase riêng
  └─ Push notification qua Firebase
  └─ Offline-first (local SQLite)
  └─ Biometric auth
```

### 2.3 Constraints (Không thay đổi)

| Constraint | Lý do |
|---|---|
| Không thêm Composer/npm package | Giữ zero-dependency policy |
| Không thêm PHP backend endpoint mới | Dùng API có sẵn / thêm nhẹ nếu cần |
| Không rewrite view hiện tại | Thêm CSS/JS overlay cho mobile |
| Không thêm DB table mới | Dùng dữ liệu có sẵn |

---

## 3. PWA Components

### 3.1 manifest.json

**File:** `public/manifest.json`

```json
{
    "name": "BookWise Kế toán",
    "short_name": "BookWise",
    "description": "Hệ thống kế toán doanh nghiệp Việt Nam",
    "start_url": "/?source=pwa",
    "display": "standalone",
    "orientation": "portrait",
    "theme_color": "#1e2a3a",
    "background_color": "#f5f6fa",
    "icons": [
        { "src": "/assets/images/pwa-192.png", "sizes": "192x192", "type": "image/png" },
        { "src": "/assets/images/pwa-512.png", "sizes": "512x512", "type": "image/png" },
        { "src": "/assets/images/pwa-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
    ],
    "categories": ["finance", "business"],
    "lang": "vi-VN",
    "dir": "ltr"
}
```

**Icons cần tạo:** SVG source → export 192x192 + 512x512 PNG. Dùng logo Bookwise hiện tại (`bookwise-icon.svg` làm base, thêm background solid `#1e2a3a`).

**Link trong layout.php:** Thêm vào `<head>`:

```php
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="BookWise">
<link rel="apple-touch-icon" href="/assets/images/pwa-192.png">
```

### 3.2 Service Worker

**File:** `public/sw.js`

**Strategy:** Cache-first cho static assets, Network-first cho API.

```javascript
// sw.js — Service Worker cho BookWise PWA
// Cache strategy:
//   - Static assets (CSS, JS, fonts, icons): Cache-first
//   - API responses (/api/*): Network-first, fallback to cache
//   - HTML pages (view routes): Network-only (luôn lấy mới nhất)
//   - manifest.json, sw.js: Network-only

const CACHE_STATIC = 'bookwise-static-v1';
const CACHE_API = 'bookwise-api-v1';

self.addEventListener('install', function(e) {
    e.waitUntil(
        caches.open(CACHE_STATIC).then(function(cache) {
            return cache.addAll([
                '/assets/css/bootstrap.min.css',
                '/assets/css/bootstrap-icons.css',
                '/assets/js/jquery-3.7.1.min.js',
                '/assets/js/bootstrap.bundle.min.js',
                '/assets/images/bookwise-logo.svg',
                '/assets/images/bookwise-icon.svg',
                '/assets/images/pwa-192.png',
                '/assets/images/pwa-512.png'
            ]);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function(e) {
    e.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) {
                    return k !== CACHE_STATIC && k !== CACHE_API;
                }).map(function(k) { return caches.delete(k); })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function(e) {
    var url = new URL(e.request.url);
    // API requests — network-first
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(networkFirst(e.request, CACHE_API));
        return;
    }
    // Static assets — cache-first
    if (url.pathname.match(/\.(css|js|png|svg|ico|woff2?)$/)) {
        e.respondWith(cacheFirst(e.request));
        return;
    }
    // Everything else — network-only
    e.respondWith(fetch(e.request));
});

function cacheFirst(request) {
    return caches.match(request).then(function(resp) {
        return resp || fetch(request);
    });
}

function networkFirst(request, cacheName) {
    return fetch(request).then(function(resp) {
        if (resp.ok) {
            var clone = resp.clone();
            caches.open(cacheName).then(function(cache) {
                cache.put(request, clone);
            });
        }
        return resp;
    }).catch(function() {
        return caches.match(request);
    });
}
```

**Registration trong layout.php:**

```php
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js');
    });
}
</script>
```

### 3.3 HTTPS Requirement

PWA yêu cầu HTTPS (trừ localhost cho development). Cần:

- [ ] Cấu hình HTTPS trên production server
- [ ] Let's Encrypt cert cho domain
- [ ] HTTP→301→HTTPS redirect trong `.htaccess` hoặc Nginx config
- [ ] Dev: `php -S` vẫn dùng HTTP → SW chỉ register trên localhost

### 3.4 iOS Safari Compatibility Notes

iOS Safari hỗ trợ PWA có hạn chế:

| Feature | iOS Support | Workaround |
|---|---|---|
| Service Worker | ✅ iOS 11.3+ | Không vấn đề |
| manifest.json | ✅ iOS 11.3+ | Cần `<meta name="apple-mobile-web-app-capable">` |
| Push Notification | ❌ Không hỗ trợ | Dùng periodic API poll thay thế |
| Cache | ✅ | Giới hạn ~50MB |
| Orientation lock | ❌ | Bỏ qua, dùng auto-rotate |
| Badge | ❌ | Không dùng |
| Swipe to navigate | ⚠️ Có thể conflict | Thêm `overscroll-behavior: none` trên body |

**Test requirement:** Phải test trên Safari iOS (physical device, không simulator) — đây là browser mặc định của iPhone, cũng là browser duy nhất hỗ trợ Add to Home Screen.

---

## 4. Responsive UI Changes

### 4.1 Sidebar → Hamburger Menu

**Hiện tại:** `position:fixed; width:250px; left:0` — chiếm 80% màn hình phone.

**Thay đổi:** Media query `@media (max-width: 768px)`:

```css
/* === MOBILE RESPONSIVE OVERLAY === */
/* KHÔNG sửa layout.php gốc — thêm file CSS riêng */
/* File: public/assets/css/mobile.css */

/* Mobile sidebar: off-canvas overlay */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        width: 280px;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .sidebar-backdrop {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
    .sidebar-backdrop.show {
        display: block;
    }
    .content-area {
        margin-left: 0;
    }
    .topbar {
        padding: 8px 12px;
    }
    .hamburger-btn {
        display: inline-block !important;
        font-size: 24px;
        cursor: pointer;
        background: none;
        border: none;
        color: #1a2a3a;
        padding: 4px 8px;
    }
    .page-content {
        padding: 12px;
    }
}
.hamburger-btn {
    display: none;
}

/* Bottom tab bar for mobile navigation */
@media (max-width: 768px) {
    .mobile-tab-bar {
        display: flex;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #fff;
        border-top: 1px solid #e2e6ef;
        z-index: 1001;
        padding: 4px 0;
        justify-content: space-around;
    }
    .mobile-tab-bar a {
        text-align: center;
        color: #6d7a8a;
        text-decoration: none;
        font-size: 10px;
        padding: 4px 0;
    }
    .mobile-tab-bar a i {
        font-size: 20px;
        display: block;
        margin-bottom: 2px;
    }
    .mobile-tab-bar a.active {
        color: #4f6ef7;
    }
    .page-content {
        padding-bottom: 56px; /* Space for tab bar */
    }
}
.mobile-tab-bar {
    display: none;
}
```

**Layout change in `layout.php`:** Thêm hamburger button trong `.topbar`:

```php
<div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    <!-- existing content -->
</div>
<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>
```

```javascript
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.querySelector('.sidebar-backdrop').classList.toggle('show');
}
```

### 4.2 Data Tables: Responsive Scroll

**Hiện tại:** Bảng dữ liệu rộng, không scroll trên mobile.

**Thay đổi:** Thêm wrapper cho bảng — scroll ngang khi cần.

```css
@media (max-width: 768px) {
    .table-responsive-mobile {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-responsive-mobile .table {
        min-width: 600px; /* force scroll when narrower */
    }
}
```

**Cách dùng trong view:** Bọc `.card-table` bằng `.table-responsive-mobile`:

```php
<div class="card-table table-responsive-mobile">
    <table class="table"> ... </table>
</div>
```

**Lưu ý:** Không sửa từng view — thêm một helper CSS class và cập nhật views khi có thời gian. Phase 1 chỉ cần dashboard + approvals.

### 4.3 Touch-Friendly Controls

**Hiện tại:** `font-size: 13px`, button padding `2px 8px`, table cells `8px 12px`.

**Yêu cầu mobile (Material Design touch targets):**

| Element | Min Size | Bookwise hiện tại |
|---|---|---|
| Button (touch) | 44x44px | ~24px — ❌ |
| Link (touch) | 44x44px | ~16px — ❌ |
| Table cell touch | 44px height | ~32px — ❌ |
| Form input | 44px height | ~30px — ❌ |
| Font size (body) | 16px | 13px — ❌ |

```css
@media (max-width: 768px) {
    body {
        font-size: 15px;
    }
    .btn {
        min-height: 44px;
        padding: 10px 16px;
        font-size: 15px;
    }
    .btn-sm {
        min-height: 44px;
        padding: 8px 14px;
        font-size: 14px;
    }
    .table tbody td {
        padding: 10px 8px;
        min-height: 44px;
    }
    .form-control, .form-select {
        min-height: 44px;
        font-size: 16px; /* iOS prevents zoom on focus */
    }
    .nav-link-s {
        min-height: 48px;
        padding: 12px 20px;
        font-size: 15px;
    }
    .badge-status {
        font-size: 12px;
        padding: 4px 12px;
    }
}
```

**iOS Safari zoom prevention:** `<input>` font-size < 16px triggers auto-zoom on focus. Set `font-size: 16px` trên mobile.

### 4.4 Bottom Tab Bar (Mobile Navigation)

**Hiện tại:** Sidebar với nhiều menu mục — không dùng được trên mobile.

**Thiết kế:** Bottom tab bar với 4-5 mục chính, các mục phụ trong hamburger menu.

```html
<div class="mobile-tab-bar">
    <a href="/" class="<?= $activeMenu==='dashboard'?'active':'' ?>">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
    </a>
    <a href="#" onclick="toggleSidebar()">
        <i class="bi bi-grid"></i><span>Chức năng</span>
    </a>
    <a href="/tong-hop/phe-duyet" class="<?= $activeMenu==='approvals'?'active':'' ?>">
        <i class="bi bi-check-circle"></i><span>Duyệt</span>
    </a>
    <a href="#" onclick="openSearch()">
        <i class="bi bi-search"></i><span>Tìm kiếm</span>
    </a>
    <a href="#" onclick="alert('Đang phát triển')">
        <i class="bi bi-bell"></i><span>Thông báo</span>
    </a>
</div>
```

**Tab bar items:**

| Tab | Icon | Route | Note |
|---|---|---|---|
| Dashboard | `speedometer2` | `/` | Trang chủ |
| Chức năng | `grid` | `#` | Mở sidebar để chọn module |
| Duyệt | `check-circle` | `/tong-hop/phe-duyet` | Approval queue |
| Tìm kiếm | `search` | Mở modal | Quick search |
| Thông báo | `bell` | `#` | Notification center (Phase 1) |

### 4.5 Swipe Gestures

**Quyết định:** Không implement swipe gestures trong Phase 1. Lý do:

- Tăng complexity (touchstart/touchend handlers, conflict với scroll)
- P2 feature trên P2 gap
- Click-based là đủ trên approval queue

**Phase 1:** Click-based actions (nút Duyệt/Từ chối to bản mobile-friendly).

**Phase 2 (Native):** Swipe-left = Duyệt, Swipe-right = Từ chối.

---

## 5. Mobile-Optimized Features (Phase 1)

### 5.1 Dashboard Mobile (Read-only KPI Cards)

**Backend:** API `/api/dashboard` đã có — trả về JSON với `cash_balance`, `bank_balance`, `today_receipts`, `today_payments`, `trend`.

**Không cần thêm backend.** Chỉ cần mobile-optimized view.

**File mới:** `public/views/mobile/dashboard.php` — hoặc detect mobile trong `public/index.php` và render view khác.

**Cách tiếp cận đơn giản nhất:** Thêm CSS class `mobile-layout` khi detect mobile → hide bảng trend, chỉ show KPI cards.

```php
// Trong layout.php — detect mobile bằng CSS media query, không cần PHP
// hoặc dùng User-Agent check đơn giản:
$isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod/', $_SERVER['HTTP_USER_AGENT'] ?? '');
```

**Mobile dashboard card design:**

```
┌─────────────────────┐
│  Tiền mặt (111)     │
│  ₫125,000,000       │  ← font-size: 24px, bold
└─────────────────────┘
┌─────────────────────┐
│  Tiền gửi (112)     │
│  ₫890,000,000       │
└─────────────────────┘
┌─────────────────────┐
│  Thu hôm nay        │
│  +₫45,000,000 (xanh)│
└─────────────────────┘
┌─────────────────────┐
│  Chi hôm nay        │
│  -₫12,000,000 (đỏ)  │
└─────────────────────┘
```

**CSS card mobile:**

```css
@media (max-width: 768px) {
    .kpi-card {
        padding: 16px;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,.08);
    }
    .kpi-card .amount {
        font-size: 24px;
        font-weight: 700;
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        letter-spacing: -0.5px;
    }
}
```

### 5.2 Approval Queue Mobile

**Backend:** API `/api/approvals/pending` đã có. POST `/api/approvals/{id}/approve` và `/api/approvals/{id}/reject` đã có.

**File hiện tại:** `public/views/approvals.php` — bảng rộng, nút nhỏ, không mobile-friendly.

**Mobile view (Phase 1):** Thêm mobile CSS class + card layout thay vì table.

```css
@media (max-width: 768px) {
    .approval-mobile-card {
        background: #fff;
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .approval-mobile-card .ref {
        font-weight: 600;
        font-size: 14px;
    }
    .approval-mobile-card .desc {
        font-size: 13px;
        color: #6b7280;
        margin: 4px 0;
    }
    .approval-mobile-card .amount {
        font-size: 18px;
        font-weight: 700;
        text-align: right;
    }
    .approval-mobile-card .actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }
    .approval-mobile-card .actions .btn {
        flex: 1;
    }
}
```

**Cấu trúc HTML cho mobile approval card:**

```html
<div class="approval-mobile-card">
    <div style="display:flex;justify-content:space-between;">
        <div>
            <div class="ref">PC2026-000123</div>
            <div class="desc">Thanh toán tiền điện tháng 5</div>
            <small class="text-muted">Nguyễn Văn A • 02/06/2026</small>
        </div>
        <div class="amount text-danger">₫5,000,000</div>
    </div>
    <div class="actions">
        <button class="btn btn-success" onclick="approve('...')">
            <i class="bi bi-check-lg"></i> Duyệt
        </button>
        <button class="btn btn-outline-danger" onclick="reject('...')">
            <i class="bi bi-x-lg"></i> Từ chối
        </button>
    </div>
</div>
```

### 5.3 Notifications (Periodic API Poll)

**Backend:** API endpoint mới — `GET /api/notifications/pending`.

**Trả về:**

```json
{
    "data": [
        {
            "id": "notif_abc",
            "type": "approval_request",
            "title": "Phiếu chi chờ duyệt",
            "message": "Phiếu chi PC2026-000123 (5,000,000₫) của Nguyễn Văn A chờ duyệt",
            "reference": "PC2026-000123",
            "url": "/tong-hop/phe-duyet",
            "created_at": "2026-06-02 09:30:00",
            "read": false
        }
    ],
    "unread_count": 3
}
```

**Frontend:** Periodic poll mỗi 60 giây, + Browser Notification API nếu có permission.

```javascript
// Poll notifications — mỗi 60 giây
function pollNotifications() {
    if ('Notification' in window && Notification.permission === 'granted') {
        $.get('/api/notifications/pending', function(res) {
            var data = res.data || [];
            var unread = data.filter(function(n) { return !n.read; });
            // Update badge
            updateBadge(unread.length);
            // Show browser notification for new ones (simplified: show first)
            if (unread.length > 0) {
                var n = unread[0];
                new Notification('BookWise - ' + n.title, {
                    body: n.message,
                    icon: '/assets/images/pwa-192.png'
                });
            }
        });
    }
}

// Request permission on first visit
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Update badge count
function updateBadge(count) {
    if (navigator.setAppBadge) {
        navigator.setAppBadge(count);
    }
    // Fallback: update DOM badge
    var badge = document.getElementById('notifBadge');
    if (badge) {
        badge.textContent = count > 0 ? count : '';
        badge.style.display = count > 0 ? 'inline' : 'none';
    }
}

// Start polling after page load
$(document).ready(function() {
    requestNotificationPermission();
    setInterval(pollNotifications, 60000); // 60s
});
```

**DB migration (nếu cần — optional, Phase 1 có thể skip):**

```php
// database/migrations/090_create_notifications_table.php
<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id VARCHAR(36) PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL COMMENT 'approval_request|payment_due|low_stock|system',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        reference VARCHAR(50) COMMENT 'Số chứng từ liên quan',
        url VARCHAR(255) COMMENT 'Link đến trang chi tiết',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
```

**Cách tạo notification:** Tích hợp sẵn vào service khi tạo approval request.

```php
// Trong ApprovalService — sau khi tạo approval request:
NotificationService::notify(
    userId: $approverId,
    type: 'approval_request',
    title: 'Phiếu chi chờ duyệt',
    message: "Phiếu chi {$reference} ({$amount}₫) của {$creator} chờ duyệt",
    reference: $reference,
    url: '/tong-hop/phe-duyet'
);
```

### 5.4 Quick Search (Modal)

**Modal tìm kiếm toàn hệ thống — gọi `Ctrl+K` hoặc tap search tab.**

**Backend:** API mới — `GET /api/search?q=keyword`.

**Trả về kết quả từ nhiều module:**

```json
{
    "data": {
        "transactions": [{ "id": "txn_abc", "reference": "PC2026-000123", "description": "...", "amount": 5000000 }],
        "suppliers": [{ "id": 1, "name": "Công ty ABC", "tax_code": "0123456789" }],
        "customers": [],
        "items": [],
        "fixed_assets": []
    },
    "total": 5
}
```

**Frontend — modal + search input:**

```html
<div class="modal fade" id="searchModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content" style="border-radius:16px;">
    <div class="modal-body p-0">
        <input type="text" id="searchInput" class="form-control form-control-lg border-0"
               placeholder="Tìm kiếm chứng từ, nhà cung cấp, khách hàng..."
               style="font-size:16px;min-height:56px;border-radius:16px 16px 0 0;"
               autofocus>
        <div id="searchResults" style="max-height:400px;overflow-y:auto;"></div>
    </div>
</div></div></div>
```

```javascript
// Quick search — debounced input → API call
var searchTimer;
$('#searchInput').on('input', function() {
    clearTimeout(searchTimer);
    var q = $(this).val().trim();
    if (q.length < 2) { $('#searchResults').html(''); return; }
    searchTimer = setTimeout(function() {
        $.get('/api/search?q=' + encodeURIComponent(q), function(res) {
            renderSearchResults(res.data || {});
        });
    }, 300);
});

function renderSearchResults(data) {
    var html = '';
    // Transactions
    if (data.transactions && data.transactions.length) {
        html += '<div class="px-3 pt-3 pb-1"><small class="text-muted text-uppercase fw-bold">Chứng từ</small></div>';
        data.transactions.forEach(function(t) {
            html += '<a href="/chi-tiet/' + t.id + '" class="d-flex px-3 py-2 text-decoration-none text-dark hover-bg-light">'
                + '<div><strong>' + esc(t.reference) + '</strong><br><small>' + esc(t.description) + '</small></div>'
                + '<div class="ms-auto fw-bold">' + fmt(t.amount) + '</div></a>';
        });
    }
    // Suppliers
    if (data.suppliers && data.suppliers.length) {
        html += '<div class="px-3 pt-2 pb-1"><small class="text-muted text-uppercase fw-bold">Nhà cung cấp</small></div>';
        data.suppliers.forEach(function(s) {
            html += '<a href="/danh-muc/nha-cung-cap" class="d-block px-3 py-2 text-decoration-none text-dark hover-bg-light">'
                + esc(s.name) + ' <small class="text-muted">(' + esc(s.tax_code) + ')</small></a>';
        });
    }
    if (!html) html = '<div class="p-4 text-center text-muted">Không tìm thấy kết quả</div>';
    $('#searchResults').html(html);
}

// Keyboard shortcut: Ctrl+K / Cmd+K
$(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        $('#searchModal').modal('show');
        setTimeout(function() { $('#searchInput').focus(); }, 300);
    }
});
```

---

## 6. Phase 2: Native App (Flutter)

**Chỉ thực hiện khi có nhu cầu thị trường rõ ràng (> 10 khách hàng yêu cầu).**

### 6.1 Technology Decision

| Option | Pros | Cons |
|---|---|---|
| **Flutter** | Single codebase, performance gần native, hot reload | Dart learning curve, bundle size ~10MB |
| React Native | JS ecosystem, reuse web components | Performance kém Flutter, bridge overhead |
| **Chọn Flutter** | Vì: 1 codebase cho iOS + Android, performant, UI đẹp | |

### 6.2 Architecture

```
┌─────────────────────────────────────┐
│            Flutter App              │
├─────────────────────────────────────┤
│  UI Layer (Material 3 widgets)      │
│  State Management (Riverpod)        │
│  Repository Layer (API + local DB)  │
│  Offline Sync (SQLite via drift)    │
│  Push Notification (Firebase)       │
│  Biometric Auth (local_auth)        │
└──────────┬──────────────────────────┘
           │ HTTPS (REST API)
┌──────────▼──────────────────────────┐
│      PHP Backend (existing)         │
│  + Firebase Cloud Messaging         │
│  + API token auth (JWT or API key)  │
└─────────────────────────────────────┘
```

### 6.3 Key Features (Native-only)

| Feature | Description |
|---|---|
| Push Notification | Firebase Cloud Messaging — real-time khi có approval request |
| Offline-first | Local SQLite cache, sync khi có mạng |
| Biometric auth | Fingerprint / Face ID thay vì mật khẩu |
| Background sync | Đồng bộ dữ liệu khi app background |
| Swipe gestures | Swipe left/right trên approval card |
| Haptic feedback | Rung khi duyệt/từ chối thành công |
| Widget (iOS) | Quick action: xem KPI trên Home Screen |

### 6.4 Additional Backend Requirements

| Endpoint | Method | Purpose | Có sẵn? |
|---|---|---|---|
| `/api/auth/token` | POST | Exchange session token for API token | ❌ Cần thêm |
| `/api/fcm/register` | POST | Register device token | ❌ Cần thêm |
| `/api/sync/transactions` | GET | Get transactions updated since timestamp | ❌ Cần thêm |
| `/api/sync/accounts` | GET | Get account list for offline lookup | ❌ Cần thêm |

### 6.5 Offline-First Strategy

```
App mở → hiển thị dữ liệu từ cache ngay lập tức
       → gọi API nền → cập nhật cache + UI
       → nếu không có mạng → dùng cache, hiển thị "dữ liệu cũ"
       → nếu có thay đổi offline → xếp hàng đợi → sync khi có mạng
```

### 6.6 Effort Breakdown (Native)

| Task | Days | Ghi chú |
|---|---|---|
| Flutter project setup + auth | 2 | Login screen, session token management |
| Dashboard screen | 1 | KPI cards, biểu đồ |
| Approval queue + push | 2 | Danh sách + detail + FCM |
| Offline sync engine | 3 | SQLite schema, sync logic |
| Search | 1 | Quick search + recent searches |
| Biometric auth | 0.5 | Thay thế mật khẩu bằng vân tay |
| Testing + store submission | 2.5 | iOS TestFlight + Google Play |
| **Total** | **12** | |

---

## 7. Implementation Checklist

### Phase 1 — PWA (3-5 days)

```
[ ] Tạo icon PWA (192x192, 512x512)
[ ] Tạo public/manifest.json
[ ] Tạo public/sw.js (Service Worker)
[ ] Link manifest + service worker trong layout.php
[ ] CSS: public/assets/css/mobile.css (mobile overlay)
[ ] Sidebar: hamburger toggle + backdrop
[ ] Bottom tab bar: layout.php + mobile CSS
[ ] Touch-friendly: min-height 44px cho button/input/link
[ ] Data table: responsive scroll (table-responsive-mobile class)
[ ] Dashboard mobile: CSS card layout
[ ] Approval queue mobile: card layout + large buttons
[ ] Notification: GET /api/notifications/pending (backend)
[ ] Notification: periodic poll + browser Notification API (frontend)
[ ] Quick Search: GET /api/search (backend — UNION search across tables)
[ ] Quick Search: modal + Ctrl+K (frontend)
[ ] Test coverage: 
    [ ] manifest.json validation (Lighthouse)
    [ ] Service Worker registration + caching
    [ ] Offline: verify KPI cards show cached data
    [ ] iPhone Safari: Add to Home Screen works
    [ ] Touch targets: all > 44px
[ ] Lighthouse audit: PWA score > 90
```

### Phase 2 — Native (10-14 days, conditional)

```
[ ] Flutter project init
[ ] Login + token management
[ ] Dashboard screen
[ ] Approval queue screen
[ ] Push notification (FCM)
[ ] Offline sync engine
[ ] Search screen
[ ] Biometric auth
[ ] iOS TestFlight
[ ] Google Play release
```

---

## 8. Effort Estimate

### Phase 1 — PWA (3-5 days)

| Item | Giờ | Phụ thuộc |
|---|---|---|
| Icon + manifest.json + SW | 2 | — |
| Mobile CSS overlay | 4 | — |
| Sidebar hamburger + tab bar | 3 | Mobile CSS |
| Touch-friendly sizing | 2 | Mobile CSS |
| Dashboard mobile optimization | 1 | — |
| Approval queue mobile layout | 3 | — |
| Notification (backend + frontend) | 6 | DB migration |
| Quick Search (backend + frontend) | 6 | — |
| Testing + Lighthouse audit | 3 | All above |
| **Total** | **30 giờ (~4 ngày)** | |

### Phase 2 — Native (10-14 days)

| Item | Giờ | Phụ thuộc |
|---|---|---|
| Flutter init + auth | 16 | — |
| Dashboard + biểu đồ | 8 | Flutter init |
| Approval + push notification | 16 | FCM setup |
| Offline sync | 24 | Backend sync API |
| Search | 8 | Offline sync |
| Biometric auth | 4 | Flutter init |
| Testing + release | 20 | All above |
| **Total** | **96 giờ (~12 ngày)** | |

### Total: 15-19 days (PWA + Native)

---

## 9. Risk Assessment

| ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| M01 | iOS Safari SW không hoạt động đúng | Medium | Medium | Test physical device, fallback graceful degradation |
| M02 | Backend không handle concurrent mobile requests | Low | High | SessionMiddleware.close() đã có |
| M03 | Notification poll làm tăng DB load | Medium | Low | Poll interval 60s, cache result 30s |
| M04 | Search query full-text scan chậm | Medium | Medium | Thêm FULLTEXT index cho bảng chính |
| M05 | User không biết PWA có thể Add to Home Screen | High | Low | Thêm install banner (beforeinstallprompt event) |
| M06 | Touch target size không đạt yêu cầu WCAG | Low | Medium | Audit với Lighthouse |

---

## 10. Acceptance Criteria

```
Phase 1 — PWA:
[ ] Lighthouse PWA score ≥ 90
[ ] manifest.json valid, icons hiển thị đúng
[ ] Service Worker caches static assets
[ ] Offline: dashboard KPI cards hiển thị từ cache
[ ] Sidebar hamburger hoạt động trên màn hình < 768px
[ ] Bottom tab bar hiển thị 5 tab chính
[ ] Nút Duyệt/Từ chối tối thiểu 44px trên mobile
[ ] Bảng dữ liệu scroll ngang trên mobile
[ ] Notification: poll API mỗi 60s, hiển thị browser notification
[ ] Quick Search: Ctrl+K mở modal, kết quả hiển thị trong 300ms
[ ] Add to Home Screen: iOS Safari "Add to Home Screen" hoạt động
[ ] iPhone physical device test: mọi chức năng hoạt động
[ ] Không phá vỡ desktop experience

Phase 2 — Native (conditional):
[ ] iOS + Android build thành công
[ ] Đăng nhập bằng biometric (fingerprint/face)
[ ] Push notification nhận được khi app đóng
[ ] Offline: xem dữ liệu cũ khi không có mạng
[ ] Sync: dữ liệu mới nhất khi có mạng
```

---

> **Ghi chú:** Gap 9 là P2 duy nhất trong 10 gaps. Khuyến nghị thực hiện Phase 1 (PWA) vào cuối roadmap, sau khi tất cả Gap P0 và P1 đã hoàn thành. Phase 2 chỉ thực hiện khi có xác nhận từ Product Owner về nhu cầu thị trường.
