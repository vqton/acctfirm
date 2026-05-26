<?php // Màn hình: Hàng mua đang đi đường (TK 151)
$title = 'Hàng mua đang đi đường'; $activeMenu = 'transit'; ob_start(); ?>
<div class="toolbar">
    <h5>Hàng mua đang đi đường <span class="stats">(TK 151)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transitModal"><i class="bi bi-plus-lg"></i> Ghi nhận hàng đi đường</button>
    </div>
</div>

<div class="card-table">
    <div class="card-header-x">
        <span class="stats ms-auto" id="recordCount">0 bản ghi</span>
    </div>
    <table class="table table-hover">
        <thead><tr>
            <th>Vật tư</th>
            <th>Số lượng</th>
            <th>Đơn giá</th>
            <th>Phí bổ sung / ĐV</th>
            <th>Chứng từ</th>
            <th>Ngày tạo</th>
            <th>Thao tác</th>
        </tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<!-- Record Transit Modal -->
<div class="modal fade" id="transitModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form id="transitForm">
<div class="modal-header"><h5 class="modal-title">Ghi nhận hàng đi đường</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3">
        <label>Vật tư</label>
        <select class="form-select" id="itemId" required><option value="">-- Chọn --</option></select>
    </div>
    <div class="mb-3">
        <label>Số lượng</label>
        <input type="number" class="form-control" id="qty" step="0.01" min="0.01" required>
    </div>
    <div class="mb-3">
        <label>Đơn giá</label>
        <input type="number" class="form-control" id="unitPrice" step="1000" min="0" required>
    </div>
    <div class="mb-3">
        <label>Số chứng từ</label>
        <input type="text" class="form-control" id="reference" placeholder="PO-...">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button>
</div>
</form>
</div></div>
</div>

<!-- Receive from Transit Modal -->
<div class="modal fade" id="receiveModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form id="receiveForm">
<div class="modal-header"><h5 class="modal-title">Nhập kho từ hàng đi đường</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="receiveTransitId">
    <div class="mb-3">
        <label>Số lượng nhập</label>
        <input type="number" class="form-control" id="receiveQty" step="0.01" min="0.01" required>
        <small class="text-muted" id="receiveMaxHint"></small>
    </div>
    <div class="mb-3">
        <label>Số chứng từ nhập</label>
        <input type="text" class="form-control" id="receiveRef" placeholder="RECV-...">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-success">Nhập kho</button>
</div>
</form>
</div></div>
</div>

<script>
function loadData() {
    $.get('/api/inventory-transit', function(data) {
        var tbody = $('#dataBody');
        tbody.empty();
        $('#recordCount').text(data.length + ' bản ghi');
        if (data.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Không có hàng đang đi đường</td></tr>');
            return;
        }
        data.forEach(function(r) {
            tbody.append('<tr><td>' + esc(r.item_code) + ' - ' + esc(r.item_name) + '</td><td>' + parseFloat(r.qty) + '</td><td>' + esc(r.unit_cost) + '</td><td>' + esc(r.addon_per_unit) + '</td><td>' + esc(r.reference) + '</td><td>' + esc(r.created_at) + '</td><td><button class="btn-action btn-sm" onclick="openReceive(\'' + r.id + '\', ' + parseFloat(r.qty) + ')"><i class="bi bi-box-arrow-in-down"></i> Nhập kho</button></td></tr>');
        });
    });
}

function openReceive(id, maxQty) {
    $('#receiveTransitId').val(id);
    $('#receiveQty').val(maxQty);
    $('#receiveMaxHint').text('Tối đa: ' + maxQty);
    $('#receiveModal').modal('show');
}

$.get('/api/items', function(items) {
    items.forEach(function(it) {
        $('#itemId').append('<option value="' + esc(it.id) + '">' + esc(it.code) + ' - ' + esc(it.name) + '</option>');
    });
});

$('#transitForm').submit(function(e) {
    e.preventDefault();
    $.ajax({
        url: '/api/inventory-transit',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            item_id: $('#itemId').val(),
            qty: parseFloat($('#qty').val()),
            unit_price: parseFloat($('#unitPrice').val()),
            reference: $('#reference').val() || undefined
        }),
        success: function() {
            $('#transitModal').modal('hide');
            $('#transitForm')[0].reset();
            showToast('Ghi nhận hàng đi đường thành công', 'success');
            loadData();
        },
        error: function(xhr) {
            var msg = 'Lỗi';
            try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
            showToast(msg, 'error');
        }
    });
});

$('#receiveForm').submit(function(e) {
    e.preventDefault();
    $.ajax({
        url: '/api/inventory-transit/receive',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            transit_id: $('#receiveTransitId').val(),
            qty: parseFloat($('#receiveQty').val()),
            reference: $('#receiveRef').val() || undefined
        }),
        success: function() {
            $('#receiveModal').modal('hide');
            $('#receiveForm')[0].reset();
            showToast('Nhập kho thành công', 'success');
            loadData();
        },
        error: function(xhr) {
            var msg = 'Lỗi';
            try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
            showToast(msg, 'error');
        }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
