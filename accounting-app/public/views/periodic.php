<?php // Màn hình: Tính giá xuất kho theo phương pháp định kỳ
$title = 'Kiểm kê định kỳ'; $activeMenu = 'periodic'; ob_start(); ?>
<div class="toolbar">
    <h5>Tính giá xuất kho (Định kỳ)</h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#closeModal"><i class="bi bi-calculator"></i> Tính COGS cuối kỳ</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Kỳ</th><th>Vật tư</th><th>Tồn đầu</th><th>Nhập trong kỳ</th><th>Tồn cuối</th><th>COGS</th><th>Chứng từ</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<div class="modal fade" id="closeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="closeForm">
<div class="modal-header"><h5 class="modal-title">Khóa sổ cuối kỳ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Vật tư</label><select class="form-select" id="itemId" required><option value="">-- Chọn --</option></select></div>
    <div class="mb-3"><label>SL tồn cuối</label><input type="number" class="form-control" id="closingQty" step="0.01" min="0" required></div>
    <div class="mb-3"><label>Đơn giá tồn cuối</label><input type="number" class="form-control" id="closingUnitCost" step="100" min="0" required></div>
    <div class="mb-3"><label>Chứng từ</label><input type="text" class="form-control" id="reference" placeholder="PERIODIC-..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Tính & Kết chuyển</button>
</div>
</form></div></div></div>
<script>
function loadData() {
    $.get('/api/periodic', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Chưa có kỳ kiểm kê nào</td></tr>');return;}
        data.forEach(function(r){tbody.append('<tr><td>'+esc(r.period_start)+' → '+esc(r.period_end)+'</td><td>'+esc(r.item_code)+'</td><td>'+parseFloat(r.closing_qty)+' × '+parseFloat(r.closing_value/r.closing_qty||0).toLocaleString()+'</td><td>'+parseFloat(r.purchases_qty)+'</td><td>'+parseFloat(r.closing_qty)+' × '+parseFloat(r.closing_value/r.closing_qty||0).toLocaleString()+'</td><td><strong>'+parseFloat(r.cogs).toLocaleString()+'</strong></td><td>'+esc(r.reference)+'</td></tr>');});
    });
}
$.get('/api/items', function(items) {
    items.forEach(function(it){$('#itemId').append('<option value="'+esc(it.id)+'">'+esc(it.code)+' - '+esc(it.name)+'</option>');});
});
$('#closeForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/periodic/close',method:'POST',contentType:'application/json',data:JSON.stringify({item_id:$('#itemId').val(),closing_qty:parseFloat($('#closingQty').val()),closing_unit_cost:parseFloat($('#closingUnitCost').val()),reference:$('#reference').val()||undefined}),success:function(){$('#closeModal').modal('hide');$('#closeForm')[0].reset();showToast('Đóng kỳ kiểm kê thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
