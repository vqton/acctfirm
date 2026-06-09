<?php
$title = 'Lịch sử import HĐĐT';
$activeMenu = 'einvoice';
ob_start();
?>
<div class="toolbar">
    <h5>Lịch sử import hóa đơn điện tử đầu vào</h5>
    <div>
        <input type="text" id="filterSupplier" class="form-control d-inline-block" style="width:200px" placeholder="Tìm nhà cung cấp...">
        <select id="filterPayStatus" class="form-select d-inline-block" style="width:auto">
            <option value="">Tất cả TT thanh toán</option>
            <option value="unpaid">Chưa thanh toán</option>
            <option value="partial">Thanh toán một phần</option>
            <option value="paid">Đã thanh toán</option>
        </select>
        <button class="btn btn-primary btn-sm" id="btnFilter"><i class="bi bi-search"></i> Tìm</button>
        <button class="btn btn-outline-info btn-sm" onclick="exportCSV('#importTable', 'einvoice-imports.csv')"><i class="bi bi-download"></i> Excel</button>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover" id="importTable">
        <thead>
            <tr>
                <th>Số HĐ</th>
                <th>Ngày</th>
                <th>Nhà cung cấp</th>
                <th>MST</th>
                <th>Tiền hàng</th>
                <th>Thuế GTGT</th>
                <th>Tổng cộng</th>
                <th>TT Thanh toán</th>
                <th>Đã trả</th>
                <th>PNK</th>
                <th>Lệnh SX</th>
                <th>Ngày import</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="importTableBody">
            <tr><td colspan="13" class="text-muted text-center py-4">Đang tải...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal chi tiết -->
<div class="modal fade" id="detailModal" tabindex="-1"><div class="dialog modal-lg"><div class="modal-content">
<div class="modal-header">
    <h5 class="modal-title">Chi tiết import</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="detailBody">Đang tải...</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
</div>
</div></div></div>

<style>
.status-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
.status-unpaid { background: #fff3cd; color: #856404; }
.status-partial { background: #cce5ff; color: #004085; }
.status-paid { background: #d4edda; color: #155724; }
</style>

<script>
let allImports = [];

function loadImports() {
    $('#importTableBody').html('<tr><td colspan="13" class="text-center py-4"><i class="bi bi-hourglass-split"></i> Đang tải...</td></tr>');
    $.get('/api/einvoice/imports', function(res) {
        allImports = Array.isArray(res) ? res : (res.data || []);
        renderImports();
    }).fail(function() {
        $('#importTableBody').html('<tr><td colspan="13" class="text-danger text-center py-4">Lỗi tải dữ liệu.</td></tr>');
    });
}

function renderImports() {
    var supplier = ($('#filterSupplier').val() || '').toLowerCase();
    var payStatus = $('#filterPayStatus').val();

    var filtered = allImports.filter(function(r) {
        if (supplier && (r.supplier_name || '').toLowerCase().indexOf(supplier) < 0) return false;
        if (payStatus && r.payment_status !== payStatus) return false;
        return true;
    });

    if (filtered.length === 0) {
        $('#importTableBody').html('<tr><td colspan="13" class="text-muted text-center py-4">Không có dữ liệu.</td></tr>');
        return;
    }

    var html = '';
    filtered.forEach(function(r) {
        var payBadge = '';
        if (r.payment_status === 'paid') payBadge = '<span class="status-badge status-paid">Đã trả</span>';
        else if (r.payment_status === 'partial') payBadge = '<span class="status-badge status-partial">Một phần</span>';
        else payBadge = '<span class="status-badge status-unpaid">Chưa trả</span>';

        var grLink = r.goods_receipt_id ? '<a href="/kho/nhap-kho" class="text-success" title="Đã tạo PNK"><i class="bi bi-check-circle"></i></a>' : '<span class="text-muted">–</span>';
        var poLink = r.production_order_id ? '<span class="text-info">' + r.production_order_id + '</span>' : '<span class="text-muted">–</span>';

        html += '<tr>' +
            '<td><a href="#" class="text-primary view-detail" data-id="' + r.id + '">' + e(r.invoice_number) + '</a></td>' +
            '<td class="text-nowrap">' + (r.invoice_date || '–') + '</td>' +
            '<td>' + e(r.supplier_name || '–') + '</td>' +
            '<td>' + e(r.supplier_tax_code || '–') + '</td>' +
            '<td class="text-end">' + VAS.fmt(r.total_before_vat) + '</td>' +
            '<td class="text-end">' + VAS.fmt(r.total_vat) + '</td>' +
            '<td class="text-end fw-bold">' + VAS.fmt(r.grand_total) + '</td>' +
            '<td>' + payBadge + '</td>' +
            '<td class="text-end">' + VAS.fmt(r.paid_amount || 0) + '</td>' +
            '<td class="text-center">' + grLink + '</td>' +
            '<td>' + poLink + '</td>' +
            '<td class="text-nowrap small">' + (r.created_at ? r.created_at.substring(0, 10) : '–') + '</td>' +
            '<td><button class="btn btn-sm btn-outline-secondary view-detail" data-id="' + r.id + '" title="Xem chi tiết"><i class="bi bi-eye"></i></button></td>' +
        '</tr>';
    });
    $('#importTableBody').html(html);
}

$(document).ready(function() {
    loadImports();
    $('#btnFilter').on('click', renderImports);
    $('#filterSupplier, #filterPayStatus').on('change', renderImports);

    $(document).on('click', '.view-detail', function() {
        var id = $(this).data('id');
        $('#detailBody').html('Đang tải...');
        $('#detailModal').modal('show');
        $.get('/api/einvoice/imports/' + id, function(r) {
            var d = r.data || r;
            var items = d.items || [];
            var itemRows = '';
            items.forEach(function(it, i) {
                itemRows += '<tr><td>' + (i+1) + '</td><td>' + e(it.name) + '</td><td>' + e(it.unit) + '</td><td class="text-end">' + (it.quantity || 0) + '</td><td class="text-end">' + VAS.fmt(it.unit_price || 0) + '</td><td class="text-end">' + VAS.fmt(it.total || 0) + '</td></tr>';
            });

            var payInfo = '<p><strong>TT Thanh toán:</strong> ' + (d.payment_status === 'paid' ? 'Đã thanh toán' : d.payment_status === 'partial' ? 'Thanh toán một phần' : 'Chưa thanh toán') + '</p>';
            if (d.paid_amount > 0) payInfo += '<p><strong>Đã thanh toán:</strong> ' + VAS.fmt(d.paid_amount) + '</p>';
            if (d.prepay_amount > 0) payInfo += '<p><strong>Đã tạm ứng:</strong> ' + VAS.fmt(d.prepay_amount) + '</p>';
            if (d.production_order_id) payInfo += '<p><strong>Lệnh sản xuất:</strong> ' + e(d.production_order_id) + ' (' + e(d.cost_category) + ')</p>';

            $('#detailBody').html(
                '<div class="row mb-3"><div class="col-md-6"><p><strong>Số HĐ:</strong> ' + e(d.invoice_number) + '</p><p><strong>Ngày:</strong> ' + (d.invoice_date || '–') + '</p><p><strong>Mã số thuế NCC:</strong> ' + e(d.supplier_tax_code || '–') + '</p><p><strong>Nhà cung cấp:</strong> ' + e(d.supplier_name || '–') + '</p></div><div class="col-md-6"><p><strong>Tổng tiền hàng:</strong> ' + VAS.fmt(d.total_before_vat) + '</p><p><strong>Thuế GTGT:</strong> ' + VAS.fmt(d.total_vat) + '</p><p><strong>Tổng thanh toán:</strong> ' + VAS.fmt(d.grand_total) + '</p>' + payInfo + '</div></div>' +
                (items.length ? '<h6>Chi tiết hàng hóa</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Hàng hóa</th><th>ĐVT</th><th class="text-end">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead><tbody>' + itemRows + '</tbody></table></div>' : '') +
                (d.goods_receipt_id ? '<p class="text-success"><i class="bi bi-check-circle"></i> Đã tạo phiếu nhập kho: <code>' + e(d.goods_receipt_id) + '</code></p>' : '')
            );
        }).fail(function() {
            $('#detailBody').html('<p class="text-danger">Lỗi tải chi tiết.</p>');
        });
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
