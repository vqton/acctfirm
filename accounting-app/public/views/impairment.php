<?php // Màn hình: Dự phòng giảm giá hàng tồn kho (TK 229)
// API: GET /api/impairments, GET /api/items, POST /api/impairments
// Nghiệp vụ: Dự phòng giảm giá HTK — Nợ 632 (giá vốn)/Có 229 (dự phòng)
// Tuân thủ: Thông tư 200 — trích lập dự phòng nếu giá thị trường < giá ghi sổ
// Rủi ro: Không hoàn nhập dự phòng khi giá phục hồi sẽ làm sai BC02
$title = 'Dự phòng giảm giá hàng tồn kho'; $activeMenu = 'impairment'; ob_start(); ?>
<div class="toolbar">
    <h5>Dự phòng giảm giá HTK <span class="stats">(TK 229)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#impairModal"><i class="bi bi-plus-lg"></i> Ghi nhận dự phòng</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Vật tư</th><th>Số tiền dự phòng</th><th>Còn lại</th><th>Chứng từ</th><th>Ghi chú</th><th>Ngày</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<div class="modal fade" id="impairModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="impairForm">
<div class="modal-header"><h5 class="modal-title">Ghi nhận dự phòng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Vật tư</label><select class="form-select" id="itemId" required></select></div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="mb-3"><label>Ghi chú</label><input type="text" class="form-control" id="notes" placeholder="Lý do giảm giá..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button>
</div>
</form></div></div></div>
<script>
function loadData() {
    $.get('/api/impairments', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted py-4">Không có dự phòng</td></tr>');return;}
        data.forEach(function(r){tbody.append('<tr><td>'+esc(r.item_code)+'</td><td>'+parseFloat(r.provision_amount).toLocaleString()+'</td><td>'+parseFloat(r.remaining_amount).toLocaleString()+'</td><td>'+esc(r.reference)+'</td><td>'+esc(r.notes)+'</td><td>'+esc(r.created_at)+'</td></tr>');});
    });
}
$.get('/api/items', function(items) {
    items.forEach(function(it){$('#itemId').append('<option value="'+esc(it.id)+'">'+esc(it.code)+' - '+esc(it.name)+'</option>');});
});
$('#impairForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/impairments',method:'POST',contentType:'application/json',data:JSON.stringify({item_id:$('#itemId').val(),amount:parseFloat($('#amount').val()),notes:$('#notes').val()}),success:function(){$('#impairModal').modal('hide');$('#impairForm')[0].reset();showToast('Ghi nhận dự phòng thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
