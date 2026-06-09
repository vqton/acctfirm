<?php // Màn hình: Quản lý hàng gửi đi bán (TK 157)
$title = 'Hàng gửi đi bán'; $activeMenu = 'consignment'; ob_start(); ?>
<div class="toolbar">
    <h5>Hàng gửi đi bán <span class="stats">(TK 157)</span></h5>
    <div>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'danh-sach-hang-gui-di-ban')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#consignModal"><i class="bi bi-plus-lg"></i> Gửi hàng</button>
    </div>
</div>

<div class="card-table">
    <div class="card-header-x">
        <span class="stats ms-auto" id="recordCount">0 bản ghi</span>
    </div>
    <table class="table table-hover">
        <thead><tr>
            <th>Vật tư</th><th>SL</th><th>Đơn giá</th><th>Đại lý</th><th>Chứng từ</th><th>Ngày tạo</th><th>Thao tác</th>
        </tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<!-- Consign Modal -->
<div class="modal fade" id="consignModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form id="consignForm">
<div class="modal-header"><h5 class="modal-title">Gửi hàng bán đại lý</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Vật tư</label><select class="form-select" id="itemId" required><option value="">-- Chọn --</option></select></div>
    <div class="mb-3"><label>Số lượng</label><input type="number" class="form-control" id="qty" step="0.01" min="0.01" required></div>
    <div class="mb-3"><label>Đại lý</label><input type="text" class="form-control" id="consignee" required></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="reference" placeholder="CSN-..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Gửi hàng</button>
</div>
</form>
</div></div>
</div>

<!-- Action modals -->
<div class="modal fade" id="sellModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="sellForm">
<div class="modal-header"><h5 class="modal-title">Bán hàng gửi đại lý</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="sellConsignmentId">
    <div class="mb-3"><label>SL bán</label><input type="number" class="form-control" id="sellQty" step="0.01" min="0.01" required><small class="text-muted" id="sellMaxHint"></small></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-success">Xác nhận bán</button>
</div>
</form></div></div></div>

<div class="modal fade" id="returnModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="returnForm">
<div class="modal-header"><h5 class="modal-title">Trả lại hàng gửi đại lý</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="returnConsignmentId">
    <div class="mb-3"><label>SL trả</label><input type="number" class="form-control" id="returnQty" step="0.01" min="0.01" required><small class="text-muted" id="returnMaxHint"></small></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-warning">Nhập lại kho</button>
</div>
</form></div></div></div>

<script>
function loadData() {
    $.get('/api/consignments', function(data) {
        var tbody = $('#dataBody');
        tbody.empty();
        $('#recordCount').text(data.length + ' bản ghi');
        if (data.length === 0) { tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Chưa có hàng gửi bán</td></tr>'); return; }
        data.forEach(function(r) {
            tbody.append('<tr><td>' + esc(r.item_code) + ' - ' + esc(r.item_name) + '</td><td>' + parseFloat(r.qty) + '</td><td>' + esc(r.unit_cost) + '</td><td>' + esc(r.consignee) + '</td><td>' + esc(r.reference) + '</td><td>' + esc(r.created_at) + '</td><td><button class="btn-action btn-sm" onclick="openSell(\'' + r.id + '\', ' + parseFloat(r.qty) + ')"><i class="bi bi-cart"></i> Bán</button> <button class="btn-action btn-sm btn-action-danger" onclick="openReturn(\'' + r.id + '\', ' + parseFloat(r.qty) + ')"><i class="bi bi-arrow-return-left"></i> Trả</button></td></tr>');
        });
    });
}
function openSell(id, maxQty) { $('#sellConsignmentId').val(id); $('#sellQty').val(maxQty); $('#sellMaxHint').text('Tối đa: ' + maxQty); $('#sellModal').modal('show'); }
function openReturn(id, maxQty) { $('#returnConsignmentId').val(id); $('#returnQty').val(maxQty); $('#returnMaxHint').text('Tối đa: ' + maxQty); $('#returnModal').modal('show'); }

$.get('/api/items', function(items) { items.forEach(function(it) { $('#itemId').append('<option value="' + esc(it.id) + '">' + esc(it.code) + ' - ' + esc(it.name) + '</option>'); }); });

$('#consignForm').submit(function(e) { e.preventDefault();
    $.ajax({url:'/api/consignments',method:'POST',contentType:'application/json',data:JSON.stringify({item_id:$('#itemId').val(),qty:parseFloat($('#qty').val()),consignee:$('#consignee').val(),reference:$('#reference').val()||undefined}),success:function(){$('#consignModal').modal('hide');$('#consignForm')[0].reset();showToast('Gửi hàng thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$('#sellForm').submit(function(e) { e.preventDefault();
    $.ajax({url:'/api/consignments/sell',method:'POST',contentType:'application/json',data:JSON.stringify({consignment_id:$('#sellConsignmentId').val(),qty:parseFloat($('#sellQty').val())}),success:function(){$('#sellModal').modal('hide');showToast('Bán hàng gửi đại lý thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$('#returnForm').submit(function(e) { e.preventDefault();
    $.ajax({url:'/api/consignments/return',method:'POST',contentType:'application/json',data:JSON.stringify({consignment_id:$('#returnConsignmentId').val(),qty:parseFloat($('#returnQty').val())}),success:function(){$('#returnModal').modal('hide');showToast('Nhập lại kho thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
