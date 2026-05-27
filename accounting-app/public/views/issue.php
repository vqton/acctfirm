<?php // Màn hình: Lập phiếu xuất kho
// API: GET /api/inventory/issues, GET /api/inventory/issue/items, POST /api/inventory/issue
// Nghiệp vụ: Xuất kho — Nợ 632 (giá vốn)/Có 156 (hàng hóa); hoặc Nợ 621/622/627 (SX)/Có 152 (NVL)
// Phương pháp tính giá: FIFO hoặc Bình quân gia quyền — xác định tại thời điểm xuất
// Rủi ro: Xuất sai loại (sale/production/construction) sẽ sai TK đối ứng và sai BC02
$title = 'Xuất kho'; $activeMenu = 'issue'; ob_start(); ?>
<div class="toolbar">
    <h5>Xuất kho</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filterItem">
            <option value="">-- Tất cả vật tư --</option>
        </select>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal"><i class="bi bi-plus-lg"></i> Tạo phiếu xuất</button>
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

<!-- Issue Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form id="issueForm">
<div class="modal-header"><h5 class="modal-title">Phiếu xuất kho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
        <label>Loại xuất</label>
        <select class="form-select" id="issueType">
            <option value="sale">Bán hàng (giá vốn)</option>
            <option value="production">Sản xuất (chuyển WIP)</option>
            <option value="construction">XDCB (TK 241)</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Số chứng từ</label>
        <input type="text" class="form-control" id="reference" placeholder="XK-2026-...">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lưu & Xuất kho</button>
</div>
</form>
</div></div>
</div>

<script>
function loadData() {
    $.get('/api/inventory/issues', function(data) {
        var tbody = $('#dataBody'), search = $('#searchInput').val().toLowerCase();
        tbody.empty();
        var filtered = data.filter(function(r) {
            return !search || r.reference.toLowerCase().includes(search) || r.description.toLowerCase().includes(search);
        });
        $('#recordCount').text(filtered.length + ' bản ghi');
        if (filtered.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center text-muted py-4">Chưa có phiếu xuất nào</td></tr>');
            return;
        }
        filtered.forEach(function(r) {
            tbody.append('<tr><td>' + esc(r.reference) + '</td><td>' + esc(r.description) + '</td><td><span class="badge-status badge-active">' + esc(r.status) + '</span></td><td>' + esc(r.created_at) + '</td></tr>');
        });
    });
}

$.get('/api/inventory/issue/items', function(items) {
    items.forEach(function(it) {
        $('#itemId').append('<option value="' + esc(it.id) + '">' + esc(it.code) + ' - ' + esc(it.name) + '</option>');
    });
});

// Submit phiếu xuất kho — POST /api/inventory/issue
// Validate frontend: kiểm tra item_id, qty > 0
// issue_type quyết định TK Nợ:
//   sale → Nợ 632 (giá vốn), production → Nợ 621/622/627, construction → Nợ 241 (XDCB)
$('#issueForm').submit(function(e) {
    e.preventDefault();
    var data = {
        item_id: $('#itemId').val(),
        qty: parseFloat($('#qty').val()),
        issue_type: $('#issueType').val(),
        reference: $('#reference').val() || undefined
    };
    if (!data.item_id) { showToast('Vui lòng chọn mã vật tư/hàng hóa.', 'error'); return; }
    if (!data.qty || data.qty <= 0) { showToast('Số lượng phải lớn hơn 0.', 'error'); return; }
    $.ajax({
        url: '/api/inventory/issue',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function() {
            $('#issueModal').modal('hide');
            $('#issueForm')[0].reset();
            showToast('Đã xuất kho thành công.', 'success');
            loadData();
        },
        error: function(xhr) {
            var msg = 'Lỗi xuất kho';
            try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
            showToast(msg, 'error');
        }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
