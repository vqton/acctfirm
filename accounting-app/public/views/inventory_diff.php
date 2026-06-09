<?php // Màn hình: Xử lý chênh lệch kiểm kê kho
// API: GET /api/physical-count/sessions, POST /api/physical-count/adjust
// Nghiệp vụ: Lọc các phiên kiểm kê có chênh lệch → xử lý thừa/thiếu
// - Thiếu (SL thực tế < SL sổ sách): Nợ 632 / Có 152,156,...
// - Thừa (SL thực tế > SL sổ sách): Nợ 152,156,... / Có 711
// TT 99: Chênh lệch kiểm kê phải ghi nhận trong kỳ phát hiện
$title = 'Xử lý chênh lệch kiểm kê'; $activeMenu = 'inventory_diff'; ob_start(); ?>
<div class="toolbar">
    <h5>Xử lý chênh lệch kiểm kê</h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal"><i class="bi bi-pencil"></i> Điều chỉnh tồn kho</button>
        <button class="btn btn-outline-primary btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Phiên kiểm kê</th><th>Ngày</th><th>Số mặt hàng</th><th>Chênh lệch SL</th><th>Giá trị chênh lệch</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody id="sessionBody"></tbody>
    </table>
</div>

<!-- Modal điều chỉnh tồn kho -->
<div class="modal fade" id="adjustModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="adjustForm">
<div class="modal-header"><h5 class="modal-title">Điều chỉnh tồn kho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <p class="text-muted small">Nhập số lượng thực tế kiểm đếm. Hệ thống sẽ tự động tính chênh lệch và sinh bút toán tương ứng.</p>
    <div class="mb-3"><label>Vật tư / Hàng hóa</label><select class="form-select" id="adjItemId" required><option value="">-- Chọn --</option></select></div>
    <div class="mb-3"><label>Số lượng thực tế</label><input type="number" class="form-control" id="adjQty" step="0.01" min="0" required></div>
    <div class="mb-3"><label>Ghi chú / Số chứng từ</label><input type="text" class="form-control" id="adjRef" placeholder="ADJ-..."></div>
    <div class="alert alert-info small" id="adjPreview" class="d-none"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Ghi nhận điều chỉnh</button>
</div>
</form></div></div></div>

<script>
function loadData() {
    $.get('/api/physical-count/sessions', function(data) {
        var tbody = $('#sessionBody'); tbody.empty();
        if (data.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Chưa có phiên kiểm kê nào</td></tr>');return;}
        data.forEach(function(s){
            var diff = parseFloat(s.total_diff || 0);
            var cls = diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : '');
            tbody.append('<tr>' +
                '<td><a href="/kho/kiem-ke">'+esc(s.reference||'N/A')+'</a></td>' +
                '<td>'+esc(s.session_date||'')+'</td>' +
                '<td>'+(s.total_items||0)+'</td>' +
                '<td class="'+cls+'">'+(diff>0?'+':'')+diff.toLocaleString()+'</td>' +
                '<td class="'+cls+'">'+(diff>0?'+':'')+parseInt(s.total_value_diff||0).toLocaleString()+'</td>' +
                '<td>'+statusBadge(s.status||'')+'</td>' +
                '<td>'+(diff!==0?'<button class="btn btn-sm btn-outline-primary" onclick="quickAdjust(\''+esc(s.id)+'\')"><i class="bi bi-check2"></i> Xử lý</button>':'')+'</td>' +
                '</tr>');
        });
    });
}

function quickAdjust(sessionId) {
    showToast('Đang mở phiên kiểm kê để xử lý chênh lệch...', 'info');
    window.location.href = '/kho/kiem-ke';
}

$.get('/api/items', function(items) {
    items.forEach(function(it){
        $('#adjItemId').append('<option value="'+esc(it.id)+'">'+esc(it.code)+' - '+esc(it.name)+'</option>');
    });
});

$('#adjItemId').change(function() {
    var val = parseFloat($('#adjQty').val());
    if (val > 0) {
        $('#adjPreview').removeClass('d-none').text('Sẽ tạo bút toán điều chỉnh chênh lệch tồn kho.');
    }
});

$('#adjustForm').submit(function(e){e.preventDefault();
    $.ajax({
        url:'/api/physical-count/adjust',
        method:'POST',
        contentType:'application/json',
        data:JSON.stringify({
            item_id:$('#adjItemId').val(),
            actual_qty:parseFloat($('#adjQty').val()),
            reference:$('#adjRef').val()||undefined
        }),
        success:function(){
            $('#adjustModal').modal('hide');
            $('#adjustForm')[0].reset();
            showToast('Đã điều chỉnh tồn kho thành công.','success');
            loadData();
        },
        error:function(x){
            var m='Lỗi';
            try{m=JSON.parse(x.responseText).error;}catch(e){}
            showToast(m,'error');
        }
    });
});

$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
