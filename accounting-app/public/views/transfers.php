<?php // Màn hình: Điều chuyển hàng giữa các kho
// API: GET /api/transfers, GET /api/transfers/items, GET /api/transfers/warehouses, POST /api/transfers
// Nghiệp vụ: Điều chuyển kho — Nợ 156 (kho đích)/Có 156 (kho nguồn) — không ảnh hưởng KQKD
// Rủi ro: Chọn kho nguồn = kho đích sẽ không làm thay đổi tồn kho — đã có validation frontend
$title = 'Điều chuyển kho'; $activeMenu = 'transfers'; ob_start(); ?>
<div class="toolbar">
    <h5>Điều chuyển kho</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filterItem">
            <option value="">-- Tất cả vật tư --</option>
        </select>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-plus-lg"></i> Tạo phiếu điều chuyển</button>
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

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form id="transferForm">
<div class="modal-header"><h5 class="modal-title">Phiếu điều chuyển kho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
        <label>Kho nguồn</label>
        <select class="form-select" id="fromWarehouseId">
            <option value="">-- Kho tổng hợp --</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Kho đích</label>
        <select class="form-select" id="toWarehouseId" required><option value="">-- Chọn --</option></select>
    </div>
    <div class="mb-3">
        <label>Số chứng từ</label>
        <input type="text" class="form-control" id="reference" placeholder="DC-2026-...">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lưu & Điều chuyển</button>
</div>
</form>
</div></div>
</div>

<script>
function loadData() {
    $.get('/api/transfers', function(data) {
        var tbody = $('#dataBody'), search = $('#searchInput').val().toLowerCase();
        tbody.empty();
        var filtered = data.filter(function(r) {
            return !search || r.reference.toLowerCase().includes(search) || r.description.toLowerCase().includes(search);
        });
        $('#recordCount').text(filtered.length + ' bản ghi');
        if (filtered.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center text-muted py-4">Chưa có phiếu điều chuyển nào</td></tr>');
            return;
        }
        filtered.forEach(function(r) {
            tbody.append('<tr><td>' + esc(r.reference) + '</td><td>' + esc(r.description) + '</td><td><span class="badge-status badge-active">' + esc(r.status) + '</span></td><td>' + esc(r.created_at) + '</td></tr>');
        });
    });
}

// Load items and warehouses for dropdowns
$.get('/api/transfers/items', function(items) {
    items.forEach(function(it) {
        $('#itemId').append('<option value="' + esc(it.id) + '">' + esc(it.code) + ' - ' + esc(it.name) + '</option>');
    });
});
$.get('/api/transfers/warehouses', function(warehouses) {
    warehouses.forEach(function(w) {
        $('#fromWarehouseId').append('<option value="' + esc(w.id) + '">' + esc(w.code) + ' - ' + esc(w.name) + '</option>');
        $('#toWarehouseId').append('<option value="' + esc(w.id) + '">' + esc(w.code) + ' - ' + esc(w.name) + '</option>');
    });
});

// Ngăn chọn kho nguồn = kho đích — validation frontend bắt buộc
// RỦI RO: Nếu backend không kiểm tra, điều chuyển cùng kho sẽ không làm thay đổi tồn kho
$('#toWarehouseId').change(function() {
    var from = $('#fromWarehouseId').val();
    var to = $(this).val();
    if (from && to && from === to) {
        showToast('Kho xuất và kho nhập phải khác nhau.', 'error');
        $(this).val('');
    }
});
$('#fromWarehouseId').change(function() {
    var from = $(this).val();
    var to = $('#toWarehouseId').val();
    if (from && to && from === to) {
        showToast('Kho xuất và kho nhập phải khác nhau.', 'error');
        $(this).val('');
    }
});

// Submit phiếu điều chuyển kho — POST /api/transfers
// Validate: item_id bắt buộc, to_warehouse_id bắt buộc, không được chuyển cùng kho
// Nghiệp vụ: Nợ 156 (kho đích)/Có 156 (kho nguồn) — chỉ thay đổi vị trí, không thay đổi giá trị
$('#transferForm').submit(function(e) {
    e.preventDefault();
    var data = {
        item_id: $('#itemId').val(),
        qty: parseFloat($('#qty').val()),
        from_warehouse_id: $('#fromWarehouseId').val() || null,
        to_warehouse_id: $('#toWarehouseId').val(),
        reference: $('#reference').val() || undefined
    };
    if (!data.item_id) { showToast('Vui lòng chọn mã vật tư/hàng hóa.', 'error'); return; }
    if (!data.to_warehouse_id) { showToast('Vui lòng chọn kho nhận hàng.', 'error'); return; }
    if (data.from_warehouse_id === data.to_warehouse_id) { showToast('Kho xuất và kho nhập phải khác nhau.', 'error'); return; }
    $.ajax({
        url: '/api/transfers',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function() {
            $('#transferModal').modal('hide');
            $('#transferForm')[0].reset();
            showToast('Đã điều chuyển hàng hóa thành công.', 'success');
            loadData();
        },
        error: function(xhr) {
            var msg = 'Lỗi điều chuyển';
            try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
            showToast(msg, 'error');
        }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
