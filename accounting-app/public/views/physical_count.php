<?php // Màn hình: Kiểm kê hàng tồn kho thực tế
// API: GET /api/physical-count/sessions, GET /api/items, POST /api/physical-count/adjust, POST /api/physical-count/sessions
// Nghiệp vụ: Kiểm kê thực tế — so sánh SL hệ thống vs SL thực tế → điều chỉnh (Nợ/Có 152/156/Có 632)
// Tuân thủ: Chênh lệch kiểm kê phải có biên bản và được duyệt trước khi điều chỉnh
// Rủi ro: Điều chỉnh sai làm thay đổi giá vốn và BC02
$title = 'Kiểm kê kho'; $activeMenu = 'physical_count'; ob_start(); ?>
<div class="toolbar">
    <h5>Kiểm kê kho</h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal"><i class="bi bi-pencil"></i> Điều chỉnh tồn kho</button>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#sessionModal"><i class="bi bi-plus-lg"></i> Tạo phiên kiểm kê</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Phiên kiểm kê</th><th>Ngày</th><th>Số mặt hàng</th><th>Chênh lệch</th><th>Trạng thái</th></tr></thead>
        <tbody id="sessionBody"></tbody>
    </table>
</div>
<div class="modal fade" id="adjustModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="adjustForm">
<div class="modal-header"><h5 class="modal-title">Điều chỉnh tồn kho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Vật tư</label><select class="form-select" id="adjItemId" required><option value="">-- Chọn --</option></select></div>
    <div class="mb-3"><label>SL thực tế</label><input type="number" class="form-control" id="adjQty" step="0.01" min="0" required></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="adjRef" placeholder="ADJ-..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Điều chỉnh</button>
</div>
</form></div></div></div>
<div class="modal fade" id="sessionModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="sessionForm">
<div class="modal-header"><h5 class="modal-title">Phiên kiểm kê</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Mô tả</label><input type="text" class="form-control" id="sessionNotes"></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="sessionRef"></div>
    <table class="table table-sm"><thead><tr><th>Vật tư</th><th>SL hệ thống</th><th>SL thực tế</th></tr></thead>
    <tbody id="sessionLines"></tbody></table>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSessionLine()"><i class="bi bi-plus"></i> Thêm dòng</button>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-success">Lưu phiên</button>
</div>
</form></div></div></div>
<script>
function loadData() {
    $.get('/api/physical-count/sessions', function(data) {
        var tbody = $('#sessionBody'); tbody.empty();
        if (data.length===0){tbody.append('<tr><td colspan="5" class="text-center text-muted py-4">Chưa có phiên kiểm kê</td></tr>');return;}
        data.forEach(function(s){tbody.append('<tr><td>'+esc(s.reference)+'</td><td>'+esc(s.session_date)+'</td><td>'+s.total_items+'</td><td>'+parseFloat(s.total_diff).toLocaleString()+'</td><td><span class="badge-status badge-active">'+esc(s.status)+'</span></td></tr>');});
    });
}
function addSessionLine() {
    var idx = $('#sessionLines tr').length;
    $('#sessionLines').append('<tr><td><select class="form-select form-select-sm sl-item" required><option value="">-- Chọn --</option></select></td><td class="sys-qty text-muted">?</td><td><input type="number" class="form-control form-control-sm sl-actual" step="0.01" min="0" required></td></tr>');
    $.get('/api/items', function(items) {
        items.forEach(function(it){$('.sl-item:last').append('<option value="'+esc(it.id)+'">'+esc(it.code)+' - '+esc(it.name)+'</option>');});
    });
}
$.get('/api/items', function(items) {
    items.forEach(function(it){
        $('#adjItemId').append('<option value="'+esc(it.id)+'">'+esc(it.code)+' - '+esc(it.name)+'</option>');
    });
});
$('#adjustForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/physical-count/adjust',method:'POST',contentType:'application/json',data:JSON.stringify({item_id:$('#adjItemId').val(),actual_qty:parseFloat($('#adjQty').val()),reference:$('#adjRef').val()||undefined}),success:function(){$('#adjustModal').modal('hide');$('#adjustForm')[0].reset();showToast('Đã điều chỉnh tồn kho thành công.','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$('#sessionForm').submit(function(e){e.preventDefault();
    var lines=[];$('#sessionLines tr').each(function(){lines.push({item_id:$(this).find('.sl-item').val(),actual_qty:parseFloat($(this).find('.sl-actual').val())});});
    $.ajax({url:'/api/physical-count/sessions',method:'POST',contentType:'application/json',data:JSON.stringify({lines:lines,reference:$('#sessionRef').val()||undefined,notes:$('#sessionNotes').val()}),success:function(){$('#sessionModal').modal('hide');showToast('Đã tạo phiên kiểm kê thành công.','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
