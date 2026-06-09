<?php // Màn hình: Xuất hủy hàng tồn kho
$title = 'Xuất hủy hàng'; $activeMenu = 'write_off'; ob_start(); ?>
<div class="toolbar"><h5>Xuất hủy hàng tồn kho</h5></div>
<div class="card-table">
    <div class="card-header-x d-flex justify-content-between">
        <input type="text" id="search" placeholder="Tìm kiếm..." onkeyup="filterTable()">
        <div>
            <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'xoa-so')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
            <button class="btn btn-sm btn-primary" onclick="showWriteOffModal()"><i class="bi bi-plus-lg"></i> Xuất hủy</button>
        </div>
    </div>
    <table class="table" id="writeOffTable">
        <thead><tr><th>Mã</th><th>Hàng hóa</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th><th>Lý do</th><th>Chứng từ</th><th>Ngày</th></tr></thead>
        <tbody id="writeOffList"></tbody>
    </table>
</div>

<div class="modal fade" id="writeOffModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Xuất hủy hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label>Hàng hóa</label><select class="form-select" id="woItem"></select></div>
        <div class="mb-2"><label>Số lượng</label><input type="number" class="form-control" id="woQty" min="1" step="1"></div>
        <div class="mb-2"><label>Lý do</label><select class="form-select" id="woReason">
            <option value="damaged">Hư hỏng</option><option value="expired">Hết hạn</option>
            <option value="obsolete">Lỗi thời</option><option value="lost">Mất</option><option value="other">Khác</option>
        </select></div>
        <div class="mb-2"><label>Tài khoản chi phí</label>
            <select class="form-select" id="woExpense"><option value="632">632 - Giá vốn hàng bán</option><option value="811">811 - Chi phí khác</option></select></div>
        <div class="mb-2"><label>Ghi chú</label><textarea class="form-control" id="woNotes" rows="2"></textarea></div>
        <div class="mb-2"><label>Số chứng từ</label><input type="text" class="form-control" id="woRef" placeholder="Tự động"></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button class="btn btn-danger" onclick="submitWriteOff()"><i class="bi bi-trash"></i> Xuất hủy</button>
    </div>
</div></div></div>

<script>
function loadItems() { $.get('/api/inventory/issue/items', function(d) {
    const sel = $('#woItem'); sel.empty();
    d.forEach(i => sel.append(`<option value="${i.id}">${i.code} - ${i.name} (Tồn: ${i.stock_qty})</option>`));
}); }
function loadList() { $.get('/api/inventory/write-offs', function(d) {
    const tbody = $('#writeOffList'); tbody.empty();
    if (!d.length) { tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">Chưa có phiếu xuất hủy</td></tr>'); return; }
    d.forEach(r => tbody.append(`<tr>
        <td>${r.id.substr(-8)}</td><td>${r.item_code} - ${r.item_name}</td>
        <td class="text-end">${parseFloat(r.qty).toLocaleString()}</td>
        <td class="text-end">${parseFloat(r.unit_cost).toLocaleString()}</td>
        <td class="text-end">${parseFloat(r.total_cost).toLocaleString()}</td>
        <td><span class="badge-status badge-warning">${r.reason}</span></td>
        <td>${r.reference}</td><td>${r.created_at?.substring(0,10)}</td>
    </tr>`));
}); }
function showWriteOffModal() { loadItems(); $('#woRef').val('WOS-' + Date.now().toString(36).toUpperCase()); $('#writeOffModal').modal('show'); }
function submitWriteOff() {
    const data = { item_id: $('#woItem').val(), qty: $('#woQty').val(), reason: $('#woReason').val(),
        expense_account: $('#woExpense').val(), reference: $('#woRef').val(), notes: $('#woNotes').val() };
    if (!data.qty || data.qty <= 0) { alert('Số lượng phải lớn hơn 0'); return; }
    $.post('/api/inventory/write-off', JSON.stringify(data), function() { $('#writeOffModal').modal('hide'); loadList(); })
     .fail(function(x) { alert(x.responseJSON?.error || 'Lỗi'); });
}
function filterTable() { /* basic filter */ }
$(loadList);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>