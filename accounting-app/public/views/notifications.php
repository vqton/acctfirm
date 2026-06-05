<?php ob_start(); ?>
<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-bell"></i> Thông báo (R-12)</h2>

    <div class="d-flex justify-content-between mb-3">
        <div>
            <button class="btn btn-sm btn-outline-primary" id="btnRefresh">
                <i class="bi bi-arrow-clockwise"></i> Tải lại
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnUnreadOnly">
                <i class="bi bi-eye"></i> Chỉ chưa đọc
            </button>
        </div>
        <button class="btn btn-sm btn-primary" id="btnMarkAll">
            <i class="bi bi-check2-all"></i> Đánh dấu tất cả đã đọc
        </button>
    </div>

    <div id="notifList"></div>
</div>

<script>
let unreadOnly = false;

function severityBadge(s) {
    const map = {
        'info': 'bg-info',
        'warn': 'bg-warning text-dark',
        'critical': 'bg-danger'
    };
    return `<span class="badge ${map[s] || 'bg-secondary'}">${s}</span>`;
}

function timeAgo(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60) return diff + 's trước';
    if (diff < 3600) return Math.floor(diff / 60) + 'm trước';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h trước';
    return Math.floor(diff / 86400) + 'd trước';
}

async function load() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const url = '/api/notifications' + (unreadOnly ? '?unread=1' : '');
    try {
        const res = await fetch(url, { headers: { 'X-CSRF-Token': csrf } });
        const json = await res.json();
        render(json.data?.items || [], json.data?.unread_count || 0);
    } catch (e) {
        document.getElementById('notifList').innerHTML =
            `<div class="alert alert-danger">Lỗi: ${e.message}</div>`;
    }
}

function render(items, unreadCount) {
    if (items.length === 0) {
        document.getElementById('notifList').innerHTML =
            '<div class="alert alert-info">Không có thông báo nào</div>';
        return;
    }
    let html = '<div class="list-group">';
    for (const n of items) {
        const isUnread = !n.is_read || n.is_read == 0;
        const link = n.link ? `<a href="${n.link}" class="btn btn-sm btn-link">Mở →</a>` : '';
        html += `<div class="list-group-item ${isUnread ? 'list-group-item-primary' : ''}">
            <div class="d-flex justify-content-between">
                <div>
                    ${severityBadge(n.severity)}
                    <strong>${escape(n.title)}</strong>
                    <small class="text-muted ms-2">${escape(n.type)}</small>
                </div>
                <small class="text-muted">${timeAgo(n.created_at)}</small>
            </div>
            <p class="mb-1 mt-1">${escape(n.message)}</p>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">${n.created_by ? 'bởi ' + escape(n.created_by) : ''}</small>
                <div>${link}${isUnread ? `<button class="btn btn-sm btn-outline-success ms-2" onclick="markRead('${n.id}')">Đã đọc</button>` : ''}</div>
            </div>
        </div>`;
    }
    html += '</div>';
    document.getElementById('notifList').innerHTML = html;
}

function escape(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

async function markRead(id) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        await fetch(`/api/notifications/${id}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf }
        });
        load();
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

document.getElementById('btnRefresh').addEventListener('click', load);
document.getElementById('btnUnreadOnly').addEventListener('click', () => {
    unreadOnly = !unreadOnly;
    load();
});
document.getElementById('btnMarkAll').addEventListener('click', async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!confirm('Đánh dấu tất cả thông báo là đã đọc?')) return;
    try {
        await fetch('/api/notifications/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf }
        });
        load();
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
});

load();
// Auto-refresh mỗi 30s
setInterval(load, 30000);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
