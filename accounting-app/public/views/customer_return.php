<?php // Màn hình: Xử lý hàng bán trả lại
// API: GET /api/inventory/customer-returns, GET /api/inventory/customer-return/items, POST /api/inventory/customer-return
// Nghiệp vụ: Hàng bán trả lại — Nợ 156 (nhập lại kho)/Có 632 (giảm giá vốn); đồng thời giảm công nợ KH
// Ảnh hưởng BC02: Giảm doanh thu (511) và giảm giá vốn (632) — cần xử lý đồng bộ
// Rủi ro: Chỉ nhập kho mà không điều chỉnh công nợ sẽ sai TK 131
$title = 'Hàng bán trả lại'; $activeMenu = 'customer_return'; ob_start(); ?>
<div class="toolbar">
    <h5>Hàng bán trả lại</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filterItem">
            <option value="">-- Tất cả vật tư --</option>
        </select>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#returnModal"><i class="bi bi-plus-lg"></i> Nhập hàng trả lại</button>
    </div>
</div>

<div class="card-table">
    <div class="card-header-x">
        <input type="text" id="searchInput" placeholder="🔍 Tìm kiếm..." onkeyup="loadData()">
        <span class="stats ms-auto" id="recordCount">0 bản ghi</span>
    </div>
    <table class="table table-hover">
        <thead><tr>
            <th>Mã chứng từ</th>
            <th>Diễn giải</th>
            <th>Trạng thái</th>
            <th>Ngày tạo</th>
        </tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form id="returnForm">
<div class="modal-header"><h5 class="modal-title">Nhập hàng bán trả lại</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3">
        <label>Vật tư / Hàng hóa</label>
        <select class="form-select" id="itemId" required><option value="">-- Chọn --</option></select>
    </div>
    <div class="mb-3">
        <label>Số lượng</label>
        <input type="number" class="form-control" id="qty" step="0.01" min="0.01" required>
    </div>
    <div class="mb-3">
        <label>Số chứng từ</label>
        <input type="text" class="form-control" id="reference" placeholder="TRA-2026-...">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lưu & Nhập lại</button>
</div>
</form>
</div></div>
</div>

<script>
function loadData() {
    $.get('/api/inventory/customer-returns', function(data) {
        var tbody = $('#dataBody'), search = $('#searchInput').val().toLowerCase();
        tbody.empty();
        var filtered = data.filter(function(r) {
            return !search || r.reference.toLowerCase().includes(search) || r.description.toLowerCase().includes(search);
        });
        $('#recordCount').text(filtered.length + ' bản ghi');
        if (filtered.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center text-muted py-4">Chưa có phiếu trả lại nào</td></tr>');
            return;
        }
        filtered.forEach(function(r) {
            tbody.append('<tr><td>' + esc(r.reference) + '</td><td>' + esc(r.description) + '</td><td><span class="badge-status badge-active">' + esc(r.status) + '</span></td><td>' + esc(r.created_at) + '</td></tr>');
        });
    });
}

$.get('/api/inventory/customer-return/items', function(items) {
    items.forEach(function(it) {
        $('#itemId').append('<option value="' + esc(it.id) + '">' + esc(it.code) + ' - ' + esc(it.name) + '</option>');
    });
});

$('#returnForm').submit(function(e) {
    e.preventDefault();
    var data = {
        item_id: $('#itemId').val(),
        qty: parseFloat($('#qty').val()),
        reference: $('#reference').val() || undefined
    };
    if (!data.item_id) { showToast('Vui lòng chọn mã vật tư/hàng hóa.', 'error'); return; }
    if (!data.qty || data.qty <= 0) { showToast('Số lượng phải lớn hơn 0.', 'error'); return; }
    $.ajax({
        url: '/api/inventory/customer-return',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function() {
            $('#returnModal').modal('hide');
            $('#returnForm')[0].reset();
            showToast('Đã nhập hàng bán trả lại thành công.', 'success');
            loadData();
        },
        error: function(xhr) {
            var msg = 'Lỗi xử lý';
            try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
            showToast(msg, 'error');
        }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
