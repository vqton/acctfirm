<?php // Màn hình: Dashboard thu hồi công nợ
$title = 'Thu hồi công nợ'; $activeMenu = 'debt_collection'; ob_start(); ?>
<div class="toolbar">
    <h5>Dashboard thu hồi công nợ</h5>
    <div>
        <a href="/thu-hoi-cong-no/hang-doi" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-ul"></i> Hàng đợi</a>
        <a href="/thu-hoi-cong-no/phe-duyet" class="btn btn-outline-primary btn-sm"><i class="bi bi-check-circle"></i> Phê duyệt</a>
    </div>
</div>

<div class="row g-3 mb-3" id="statsCards">
    <div class="col-md-2"><div class="card p-3 text-center shadow-sm"><h6 class="text-muted">Mới</h6><h3 id="stat-new" class="mb-0">-</h3></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow-sm"><h6 class="text-muted">Đang xử lý</h6><h3 id="stat-active" class="mb-0 text-primary">-</h3></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow-sm"><h6 class="text-muted">Tạm dừng</h6><h3 id="stat-hold" class="mb-0 text-warning">-</h3></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow-sm"><h6 class="text-muted">Leo thang</h6><h3 id="stat-escalated" class="mb-0 text-danger">-</h3></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow-sm"><h6 class="text-muted">Đã đóng</h6><h3 id="stat-closed" class="mb-0 text-success">-</h3></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow-sm"><h6 class="text-muted">Tổng</h6><h3 id="stat-total" class="mb-0">-</h3></div></div>
</div>

<div class="card p-3 shadow-sm">
    <h6>Queue gần đây</h6>
    <table class="table table-sm table-hover" id="queueTable">
        <thead><tr><th>KH</th><th>Hóa đơn</th><th>Dư nợ</th><th>Quá hạn</th><th>Status</th><th>Collector</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
</div>

<script>
$(function() {
    function loadStats() {
        $.get('/api/debt-collection/stats', function(r) {
            if (r.data) {
                $('#stat-new').text(r.data.new_count||0);
                $('#stat-active').text(r.data.active_count||0);
                $('#stat-hold').text(r.data.hold_count||0);
                $('#stat-escalated').text(r.data.escalated_count||0);
                $('#stat-closed').text(r.data.closed_count||0);
                $('#stat-total').text(r.data.total||0);
            }
        });
    }
    function loadQueue() {
        $.get('/api/debt-collection/queue', function(r) {
            if (!r.data) return;
            var tbody = $('#queueTable tbody').empty();
            (r.data || []).slice(0, 20).forEach(function(q) {
                tbody.append('<tr><td>'+(q.customer_name||'')+'</td><td>'+(q.invoice_number||'')+'</td><td class="text-end">'+fmt(q.balance)+'</td><td>'+daysOverdue(q.due_date)+' ngày</td><td>'+statusBadge(q.status)+'</td><td>'+(q.assigned_to||'<span class="text-muted">chưa phân</span>')+'</td><td><a href="/thu-hoi-cong-no/hang-doi" class="btn btn-sm btn-outline-secondary">Chi tiết</a></td></tr>');
            });
        });
    }
    function daysOverdue(d) { if (!d) return 0; var diff = Math.floor((new Date() - new Date(d)) / 86400000); return Math.max(0, diff); }
    function statusBadge(s) {
        var m = {'new':'secondary','active':'primary','hold':'warning','escalated':'danger','closed':'success','writeoff':'dark'};
        return '<span class="badge bg-'+ (m[s]||'secondary') +'">'+s+'</span>';
    }
    function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n||0); }
    loadStats(); loadQueue();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
