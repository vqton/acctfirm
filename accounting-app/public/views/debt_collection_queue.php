<?php // Màn hình: Hàng đợi đòi nợ chi tiết
$title = 'Hàng đợi thu hồi nợ'; $activeMenu = 'debt_collection'; ob_start(); ?>
<div class="toolbar">
    <h5>Hàng đợi thu hồi nợ</h5>
    <div>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'no-thu')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-outline-primary btn-sm" onclick="generateQueue()"><i class="bi bi-arrow-repeat"></i> Sinh hàng đợi</button>
        <a href="/thu-hoi-cong-no" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>
</div>

<div class="card p-2 mb-3 shadow-sm bg-white">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <select class="form-select form-select-sm" id="filterStatus">
                <option value="">Tất cả</option>
                <option value="new">Mới</option>
                <option value="active">Đang xử lý</option>
                <option value="hold">Tạm dừng</option>
                <option value="escalated">Leo thang</option>
                <option value="closed">Đã đóng</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary btn-sm" onclick="loadQueue()"><i class="bi bi-search"></i> Lọc</button></div>
    </div>
</div>

<div class="card p-0 shadow-sm">
    <div style="max-height:70vh;overflow-y:auto">
    <table class="table table-sm table-hover mb-0" id="queueTable">
        <thead class="sticky-top bg-white"><tr>
            <th>KH</th><th>Hóa đơn</th><th>Dư nợ</th><th>Đến hạn</th><th>Quá hạn</th><th>Priority</th><th>Esc</th><th>Status</th><th>Collector</th><th></th>
        </tr></thead>
        <tbody></tbody>
    </table>
    </div>
</div>

<script>
var queueData = [];
$(function() { loadQueue(); });

function loadQueue() {
    var params = {status: $('#filterStatus').val()};
    $.get('/api/debt-collection/queue', params, function(r) {
        queueData = r.data || [];
        var tbody = $('#queueTable tbody').empty();
        queueData.forEach(function(q) {
            var od = daysOverdue(q.due_date);
            var escBadge = q.escalation_level > 0 ? '<span class="badge bg-danger">L'+q.escalation_level+'</span>' : '-';
            tbody.append('<tr>' +
                '<td><a href="#" onclick="showDetail('+q.id+')">'+(q.customer_name||'')+'</a></td>' +
                '<td>'+(q.invoice_number||'')+'</td>' +
                '<td class="text-end">'+fmt(q.balance)+'</td>' +
                '<td>'+(q.due_date||'')+'</td>' +
                '<td class="'+(od>90?'text-danger fw-bold':'')+'">'+od+' ngày</td>' +
                '<td>'+priorityBadge(q.priority)+'</td>' +
                '<td>'+escBadge+'</td>' +
                '<td>'+statusBadge(q.status)+'</td>' +
                '<td>'+(q.assigned_to||'<button class="btn btn-xs btn-outline-secondary" onclick="assign('+q.id+')">Gán</button>')+'</td>' +
                '<td class="text-end">' +
                    (q.status=='active' ? '<button class="btn btn-sm btn-outline-warning" onclick="hold('+q.id+')">Hold</button> ' : '') +
                    (q.status=='hold' ? '<button class="btn btn-sm btn-outline-success" onclick="release('+q.id+')">Release</button> ' : '') +
                    '<button class="btn btn-sm btn-outline-info" onclick="showDetail('+q.id+')">Xem</button>' +
                '</td></tr>');
        });
    });
}

function generateQueue() {
    $.post('/api/debt-collection/queue/generate', JSON.stringify({created_by:'user'}), function(r) {
        if (r.data) { alert('Đã tạo '+r.data.created+' queue entries'); loadQueue(); }
    }).fail(function(x) { alert(x.responseJSON?.error || 'Lỗi'); });
}

function assign(id) {
    var c = prompt('Nhập mã nhân viên:');
    if (!c) return;
    $.ajax({url:'/api/debt-collection/queue/'+id+'/assign', method:'PUT', contentType:'application/json', data:JSON.stringify({collector_id:c}),
        success:function(){loadQueue();}, error:function(x){alert(x.responseJSON?.error||'Lỗi');}});
}

function hold(id) {
    var reason = prompt('Lý do tạm dừng:');
    if (!reason) return;
    $.ajax({url:'/api/debt-collection/queue/'+id+'/hold', method:'PUT', contentType:'application/json', data:JSON.stringify({reason:reason}),
        success:function(){loadQueue();}, error:function(x){alert(x.responseJSON?.error||'Lỗi');}});
}

function release(id) {
    if (!confirm('Tiếp tục theo dõi queue entry này?')) return;
    $.ajax({url:'/api/debt-collection/queue/'+id+'/release', method:'PUT', contentType:'application/json', data:JSON.stringify({}),
        success:function(){loadQueue();}, error:function(x){alert(x.responseJSON?.error||'Lỗi');}});
}

var detailModal;
function showDetail(id) {
    $.get('/api/debt-collection/queue/'+id, function(r) {
        if (!r.data) { alert('Không tìm thấy'); return; }
        var q = r.data;
        var html = '<h6>KH: '+(q.customer_name||'')+' | HĐ: '+(q.invoice_number||'')+' | Dư nợ: '+fmt(q.balance)+'</h6>';
        html += '<hr><h6>Hoạt động</h6><ul>';
        (q.activities||[]).forEach(function(a) {
            html += '<li><small>['+a.created_at+'] <b>'+a.activity_type+'</b>: '+a.summary+'</small></li>';
        });
        html += '</ul>';
        if (q.promises && q.promises.length) {
            html += '<h6>Cam kết</h6><ul>';
            q.promises.forEach(function(p) {
                html += '<li><small>'+p.promise_date+' - '+fmt(p.promise_amount)+' VND - <b>'+p.status+'</b></small></li>';
            });
            html += '</ul>';
        }
        if (!detailModal) {
            detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        }
        $('#detailModal .modal-body').html(html);
        detailModal.show();
    });
}

function daysOverdue(d) { if (!d) return 0; var diff = Math.floor((new Date() - new Date(d)) / 86400000); return Math.max(0, diff); }
function statusBadge(s) { var m={'new':'secondary','active':'primary','hold':'warning','escalated':'danger','closed':'success','writeoff':'dark'}; return '<span class="badge bg-'+ (m[s]||'secondary') +'">'+s+'</span>'; }
function priorityBadge(p) { if (p >= 8) return '<span class="badge bg-danger">'+p+'</span>'; if (p >= 5) return '<span class="badge bg-warning">'+p+'</span>'; return '<span class="badge bg-secondary">'+p+'</span>'; }
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n||0); }
</script>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chi tiết queue</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
