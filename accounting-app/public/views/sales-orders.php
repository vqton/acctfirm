<?php $title = 'Đơn đặt hàng'; $activeMenu = 'sales_orders'; ob_start(); ?>
<div class="toolbar">
    <h5>Đơn đặt hàng <span class="stats">(Order-to-Cash)</span></h5>
    <div>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'danh-sach-don-dat-hang')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="location.href='/ban/don-dat-hang/them'"><i class="bi bi-plus-lg"></i> Tạo đơn hàng</button>
        <button class="btn btn-outline-secondary btn-sm ms-1" onclick="exportOrders()"><i class="bi bi-download"></i> Xuất CSV</button>
    </div>
</div>
<div class="card-table">
    <div class="card-header-x">
        <select class="form-select" id="filterStatus" style="width:140px" onchange="loadOrders()">
            <option value="">Tất cả</option>
            <option value="draft">Nháp</option>
            <option value="confirmed">Đã xác nhận</option>
            <option value="pending_stock">Thiếu kho</option>
            <option value="partially_shipped">Xuất 1 phần</option>
            <option value="shipped">Đã xuất kho</option>
            <option value="invoiced">Đã xuất HĐ</option>
            <option value="partially_paid">Thu 1 phần</option>
            <option value="paid">Đã thanh toán</option>
            <option value="completed">Hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
        </select>
        <input type="text" placeholder="Tìm kiếm..." id="searchInput" onkeyup="loadOrders()" style="margin-left:8px;">
    </div>
    <table class="table table-hover">
        <thead><tr>
            <th>Số ĐH</th><th>Ngày</th><th>KH</th><th>Tiền hàng</th><th>Thuế</th><th>Tổng</th><th>Đã thu</th><th>Trạng thái</th><th></th>
        </tr></thead>
        <tbody id="orderBody"><tr><td colspan="9" class="empty-state"><i class="bi bi-inbox"></i>Đang tải...</td></tr></tbody>
    </table>
</div>

<script>
function loadOrders() {
    const status = $('#filterStatus').val();
    const keyword = $('#searchInput').val();
    let url = '/api/sales/orders/search?limit=100';
    if (status) url += '&status=' + status;
    if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
    $.get(url, function(data) {
        const tbody = $('#orderBody');
        if (!data.data || data.data.length === 0) {
            tbody.html('<tr><td colspan="9" class="empty-state"><i class="bi bi-inbox"></i>Không có đơn hàng</td></tr>');
            return;
        }
        let html = '';
        data.data.forEach(function(o) {
            html += '<tr><td><a href="/ban/don-dat-hang/' + o.id + '">' + o.reference + '</a></td>'
                + '<td>' + o.order_date + '</td>'
                + '<td>' + o.customer_id + '</td>'
                + '<td class="text-end">' + number_format(o.total_amount) + '</td>'
                + '<td class="text-end">' + number_format(o.tax_amount) + '</td>'
                + '<td class="text-end"><strong>' + number_format(o.grand_total) + '</strong></td>'
                + '<td class="text-end">' + number_format(o.amount_paid) + '</td>'
                + '<td><span class="badge-status badge-' + statusClass(o.status) + '">' + statusLabel(o.status) + '</span></td>'
                + '<td><a href="/ban/don-dat-hang/' + o.id + '" class="btn-action">Xem</a></td></tr>';
        });
        tbody.html(html);
    });
}
function statusClass(s) {
    const map = {draft:'warning', confirmed:'type', shipped:'active', invoiced:'active', paid:'active', completed:'active', cancelled:'danger', pending_stock:'warning', partially_shipped:'warning', partially_paid:'warning', partially_invoiced:'warning'};
    return map[s] || 'inactive';
}
function statusLabel(s) {
    const map = {draft:'Nháp', confirmed:'Đã xác nhận', pending_stock:'Thiếu kho', partially_shipped:'Xuất 1 phần', shipped:'Đã xuất kho', partially_invoiced:'HĐ 1 phần', invoiced:'Đã xuất HĐ', partially_paid:'Thu 1 phần', paid:'Đã thanh toán', completed:'Hoàn thành', cancelled:'Đã hủy'};
    return map[s] || s;
}
function number_format(n) { return new Intl.NumberFormat('vi-VN').format(n); }
function exportOrders() {
    const s = $('#filterStatus').val();
    window.location.href = '/api/sales/orders/export?format=csv' + (s ? '&status=' + s : '');
}
$(loadOrders);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
