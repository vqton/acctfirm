<?php
$title = 'Hóa đơn điện tử';
$activeMenu = 'einvoice';
ob_start();
?>
<div class="toolbar">
    <h5>Hóa đơn điện tử</h5>
    <div>
        <select id="filterStatus" class="form-select d-inline-block" style="width:auto">
            <option value="">Tất cả trạng thái</option>
            <option value="draft">Nháp</option>
            <option value="pending">Chờ phát hành</option>
            <option value="issued">Đã phát hành</option>
            <option value="cancelled">Đã hủy</option>
            <option value="error">Lỗi</option>
        </select>
        <input type="date" id="filterFrom" class="form-control d-inline-block" style="width:auto" placeholder="Từ ngày">
        <input type="date" id="filterTo" class="form-control d-inline-block" style="width:auto" placeholder="Đến ngày">
        <button class="btn btn-primary btn-sm" id="btnFilter"><i class="bi bi-search"></i> Tìm</button>
        <a href="/thue/ke-khai-gtgt" class="btn btn-outline-info btn-sm"><i class="bi bi-file-earmark-text"></i> Kê khai GTGT</a>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Số hóa đơn</th><th>Ngày</th><th>Khách hàng</th><th class="text-end">Giá trị</th><th class="text-end">Thuế</th><th>Mẫu số</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody id="dataBody"><tr><td colspan="8" class="text-muted text-center">Đang tải...</td></tr></tbody>
    </table>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chi tiết hóa đơn</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailBody">Đang tải...</div>
    <div class="modal-footer">
        <button class="btn btn-sm btn-outline-danger" id="btnCancelInv"><i class="bi bi-x-circle"></i> Hủy hóa đơn</button>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
    </div>
</div></div></div>

<script>
var currentInvId = null;

function loadData() {
    var status = $('#filterStatus').val();
    var from = $('#filterFrom').val();
    var to = $('#filterTo').val();
    var params = [];
    if (status) params.push('status=' + encodeURIComponent(status));
    if (from) params.push('from=' + encodeURIComponent(from));
    if (to) params.push('to=' + encodeURIComponent(to));
    var qs = params.length ? '?' + params.join('&') : '';

    $.get('/api/einvoice' + qs, function(data) {
        var tbody = $('#dataBody').empty();
        if (!data || !data.length) {
            tbody.html('<tr><td colspan="8" class="text-muted text-center">Không có hóa đơn</td></tr>');
            return;
        }
        data.forEach(function(inv) {
            tbody.append('<tr><td>' + esc(inv.invoice_number || '') + '</td><td>' + (inv.invoice_date || '') + '</td><td>' + esc(inv.customer_name || '') + '</td><td class="text-end font-monospace">' + parseInt(inv.total_amount || 0).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(inv.vat_amount || 0).toLocaleString() + '</td><td>' + esc(inv.template_code || '') + '</td><td>' + statusBadge(inv.status) + '</td><td><button class="btn btn-sm btn-outline-primary" onclick="viewDetail(\'' + inv.id + '\')"><i class="bi bi-eye"></i></button></td></tr>');
        });
    }).fail(function() {
        $('#dataBody').html('<tr><td colspan="8" class="text-danger text-center">Lỗi tải dữ liệu</td></tr>');
    });
}

function viewDetail(id) {
    currentInvId = id;
    $('#detailBody').html('Đang tải...');
    $('#detailModal').modal('show');
    $.get('/api/einvoice/' + id, function(inv) {
        var h = '<div class="row g-2"><div class="col-6"><strong>Số HĐ:</strong> ' + esc(inv.invoice_number || '') + '</div>';
        h += '<div class="col-6"><strong>Ngày:</strong> ' + (inv.invoice_date || '') + '</div>';
        h += '<div class="col-6"><strong>Khách hàng:</strong> ' + esc(inv.customer_name || '') + '</div>';
        h += '<div class="col-6"><strong>MST:</strong> ' + esc(inv.customer_tax_code || '') + '</div>';
        h += '<div class="col-6"><strong>Mẫu số:</strong> ' + esc(inv.template_code || '') + '</div>';
        h += '<div class="col-6"><strong>Ký hiệu:</strong> ' + esc(inv.serial_prefix || '') + '</div>';
        h += '<div class="col-6"><strong>Giá trị:</strong> ' + parseInt(inv.total_amount || 0).toLocaleString() + '</div>';
        h += '<div class="col-6"><strong>Thuế GTGT:</strong> ' + parseInt(inv.vat_amount || 0).toLocaleString() + '</div></div>';
        h += '<hr><h6>Chi tiết hàng hóa</h6><table class="table table-sm"><thead><tr><th>Mặt hàng</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>';
        if (inv.items && inv.items.length) {
            inv.items.forEach(function(item) {
                h += '<tr><td>' + esc(item.description || '') + '</td><td>' + parseFloat(item.qty || 0) + '</td><td class="text-end font-monospace">' + parseInt(item.unit_price || 0).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(item.line_total || 0).toLocaleString() + '</td></tr>';
            });
        } else {
            h += '<tr><td colspan="4" class="text-muted text-center">Không có dữ liệu chi tiết</td></tr>';
        }
        h += '</tbody></table>';
        if (inv.status === 'issued' && inv.xml_signed) {
            h += '<a href="/api/einvoice/download/' + id + '" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-xml"></i> Tải XML</a>';
        }
        $('#detailBody').html(h);
        if (inv.status === 'issued' || inv.status === 'draft') {
            $('#btnCancelInv').show();
        } else {
            $('#btnCancelInv').hide();
        }
    }).fail(function() {
        $('#detailBody').html('<div class="text-danger">Lỗi tải chi tiết hóa đơn</div>');
    });
}

$('#btnCancelInv').click(function() {
    if (!currentInvId || !confirm('Xác nhận hủy hóa đơn này?')) return;
    $.ajax({url:'/api/einvoice/cancel', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify({id:currentInvId, reason:'Hủy theo yêu cầu'}),
        success:function() { showToast('Đã hủy hóa đơn','success'); $('#detailModal').modal('hide'); loadData(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$('#btnFilter').click(function() { loadData(); });
$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
