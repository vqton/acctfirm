<?php // Màn hình: Phê duyệt xóa nợ
$title = 'Phê duyệt xóa nợ'; $activeMenu = 'debt_collection'; ob_start(); ?>
<div class="toolbar">
    <h5>Phê duyệt xóa nợ phải thu</h5>
    <div>
        <a href="/thu-hoi-cong-no" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>
</div>

<div class="card p-3 shadow-sm">
    <table class="table table-sm table-hover" id="approvalTable">
        <thead><tr><th>KH</th><th>Hóa đơn</th><th>Số tiền</th><th>Loại</th><th>Ngày yêu cầu</th><th>Người yêu cầu</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
</div>

<script>
$(function() {
    function loadApprovals() {
        $.get('/api/debt-collection/approvals', function(r) {
            var tbody = $('#approvalTable tbody').empty();
            (r.data||[]).forEach(function(a) {
                tbody.append('<tr>' +
                    '<td>'+(a.customer_name||'')+'</td>' +
                    '<td>'+(a.invoice_number||'')+'</td>' +
                    '<td class="text-end">'+fmt(a.amount)+'</td>' +
                    '<td>'+statusBadge(a.approval_type,'secondary')+'</td>' +
                    '<td>'+(a.requested_at||'').substring(0,10)+'</td>' +
                    '<td>'+a.requested_by+'</td>' +
                    '<td>'+statusBadge(a.overall_status,'primary')+'</td>' +
                    '<td class="text-end">' +
                        '<button class="btn btn-sm btn-outline-success" onclick="approve('+a.id+')"><i class="bi bi-check"></i></button> ' +
                        '<button class="btn btn-sm btn-outline-danger" onclick="reject('+a.id+')"><i class="bi bi-x"></i></button>' +
                    '</td></tr>');
            });
        });
    }

    function approve(id) {
        var note = prompt('Ghi chú phê duyệt (không bắt buộc):');
        $.ajax({url:'/api/debt-collection/approvals/'+id+'/approve', method:'PUT', contentType:'application/json',
            data:JSON.stringify({approver_id:'user', note:note||null}),
            success:function(r){alert('Đã phê duyệt');loadApprovals();},
            error:function(x){alert(x.responseJSON?.error||'Lỗi');}});
    }

    function reject(id) {
        var reason = prompt('Lý do từ chối (bắt buộc):');
        if (!reason) return;
        $.ajax({url:'/api/debt-collection/approvals/'+id+'/reject', method:'PUT', contentType:'application/json',
            data:JSON.stringify({approver_id:'user', note:reason}),
            success:function(){alert('Đã từ chối');loadApprovals();},
            error:function(x){alert(x.responseJSON?.error||'Lỗi');}});
    }

    function statusBadge(s,t) { return '<span class="badge bg-'+(t||'secondary')+'">'+s+'</span>'; }
    function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n||0); }

    loadApprovals();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
