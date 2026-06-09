<?php // Hàng mua trả lại nhà cung cấp
// API: GET /api/inventory/supplier-returns, POST /api/inventory/supplier-return
$title = 'Trả lại hàng mua'; $activeMenu = 'supplier_return'; ob_start(); ?>
<div class="toolbar">
    <h5>Trả lại hàng mua</h5>
    <div>
        <button class="btn btn-primary btn-sm" onclick="showReturnModal()"><i class="bi bi-plus-lg"></i> Trả lại NCC</button>
    </div>
</div>
<div class="card-table">
    <div class="card-header-x">
        <input type="text" id="searchInput" placeholder="🔍 Tìm kiếm..." onkeyup="loadData()">
        <span class="stats ms-auto" id="recordCount">0</span>
    </div>
    <table class="table"><thead><tr><th>Ngày</th><th>Nhà cung cấp</th><th>Vật tư</th><th class="text-end">Số lượng</th><th class="text-end">Tiền</th></tr></thead>
        <tbody id="dataBody"><tr><td colspan="5" class="text-center text-muted py-3">Đang tải...</td></tr></tbody>
    </table>
</div>
<script>
function loadData() {
    $.get('/api/inventory/supplier-returns', function(d) {
        var tb = $('#dataBody'); tb.empty();
        var rows = d.data || d || [];
        $('#recordCount').text(rows.length + ' bản ghi');
        if (!rows.length) { tb.append('<tr><td colspan="5" class="text-muted text-center">Chưa có dữ liệu</td></tr>'); return; }
        rows.forEach(function(r) {
            tb.append('<tr><td>' + esc(r.date||r.created_at||'') + '</td><td>' + esc(r.supplier_name||'') + '</td><td>' + esc(r.item_name||'') + '</td><td class="text-end">' + (r.quantity||0) + '</td><td class="text-end font-monospace">' + fmt(r.amount||0) + '</td></tr>');
        });
    });
}
function showReturnModal() { alert('Chức năng đang phát triển'); }
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
