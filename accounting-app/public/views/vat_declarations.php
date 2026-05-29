<?php
$title = 'Kê khai thuế GTGT';
$activeMenu = 'vat_declaration';
ob_start();
?>
<div class="toolbar">
    <h5>Kê khai thuế GTGT</h5>
    <div>
        <input type="month" id="period" class="form-control d-inline-block" style="width:auto;display:inline" value="<?= date('Y-m') ?>">
        <button class="btn btn-primary btn-sm" id="btnPrepare"><i class="bi bi-file-earmark-plus"></i> Chuẩn bị tờ khai</button>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Kỳ</th><th class="text-end">VAT đầu vào</th><th class="text-end">VAT đầu ra</th><th class="text-end">VAT phải nộp</th><th class="text-end">SL HĐ vào</th><th class="text-end">SL HĐ ra</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chi tiết tờ khai</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-4"><div class="card"><div class="card-body py-2"><small class="text-muted">VAT đầu vào</small><div class="h5 mb-0 font-monospace" id="detInput">0</div></div></div></div>
            <div class="col-4"><div class="card"><div class="card-body py-2"><small class="text-muted">VAT đầu ra</small><div class="h5 mb-0 font-monospace" id="detOutput">0</div></div></div></div>
            <div class="col-4"><div class="card"><div class="card-body py-2"><small class="text-muted">VAT phải nộp</small><div class="h5 mb-0 font-monospace" id="detPayable">0</div></div></div></div>
        </div>
        <ul class="nav nav-tabs mb-2">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabInput">Đầu vào</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabOutput">Đầu ra</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="tabInput"><table class="table table-sm"><thead><tr><th>Hóa đơn</th><th>Nhà cung cấp</th><th>Ngày</th><th class="text-end">Thuế suất</th><th class="text-end">Tiền thuế</th></tr></thead><tbody id="detInputBody"></tbody></table></div>
            <div class="tab-pane" id="tabOutput"><table class="table table-sm"><thead><tr><th>Hóa đơn</th><th>Khách hàng</th><th>Ngày</th><th class="text-end">Thuế suất</th><th class="text-end">Tiền thuế</th></tr></thead><tbody id="detOutputBody"></tbody></table></div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-sm btn-outline-success" id="btnFinalise"><i class="bi bi-lock"></i> Khóa tờ khai</button>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
    </div>
</div></div></div>

<script>
function loadData() {
    $.get('/api/vat/declarations', function(data) {
        var tbody = $('#dataBody').empty();
        data.forEach(function(r) {
            var badge = r.status === 'finalised' ? 'badge-active' : (r.status === 'draft' ? 'badge-warning' : 'badge-inactive');
            var payable = r.vat_payable;
            var payableCls = payable >= 0 ? '' : 'text-success';
            tbody.append('<tr><td>' + r.period + '</td><td class="text-end font-monospace">' + parseInt(r.total_vat_input).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(r.total_vat_output).toLocaleString() + '</td><td class="text-end font-monospace ' + payableCls + '">' + parseInt(payable).toLocaleString() + '</td><td class="text-end">' + r.invoice_count_input + '</td><td class="text-end">' + r.invoice_count_output + '</td><td><span class="badge-status ' + badge + '">' + esc(r.status) + '</span></td><td><button class="btn btn-sm btn-outline-primary" onclick="viewDetail(\'' + r.id + '\')"><i class="bi bi-eye"></i></button></td></tr>');
        });
    });
}

var currentDetailId = null;

function viewDetail(id) {
    currentDetailId = id;
    $.get('/api/vat/declarations/' + id, function(r) {
        var d = r.data;
        $('#detInput').text(parseInt(d.total_vat_input).toLocaleString());
        $('#detOutput').text(parseInt(d.total_vat_output).toLocaleString());
        $('#detPayable').text(parseInt(d.vat_payable).toLocaleString());
        var ibody = $('#detInputBody').empty();
        var obody = $('#detOutputBody').empty();
        (d.details || []).forEach(function(det) {
            var row = '<tr><td>' + esc(det.invoice_ref || '') + '</td><td>' + esc(det.supplier_or_customer || '') + '</td><td>' + (det.invoice_date || '') + '</td><td class="text-end">' + parseFloat(det.vat_rate).toFixed(0) + '%</td><td class="text-end font-monospace">' + parseInt(det.vat_amount).toLocaleString() + '</td></tr>';
            if (det.line_type === 'input') ibody.append(row); else obody.append(row);
        });
        if (d.status === 'finalised') { $('#btnFinalise').prop('disabled', true).text('Đã khóa'); }
        else { $('#btnFinalise').prop('disabled', false).text('Khóa tờ khai'); }
        $('#detailModal').modal('show');
    });
}

$('#btnFinalise').click(function() {
    if (!currentDetailId || !confirm('Xác nhận khóa tờ khai? Sau khi khóa không thể sửa.')) return;
    $.ajax({url:'/api/vat/declarations/' + currentDetailId + '/finalise', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Đã khóa tờ khai thành công','success'); $('#detailModal').modal('hide'); loadData(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$('#btnPrepare').click(function() {
    var period = $('#period').val();
    var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xử lý...');
    $.ajax({url:'/api/vat/declarations/prepare', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify({period:period}),
        success:function() { showToast('Đã chuẩn bị tờ khai VAT','success'); loadData(); btn.prop('disabled',false).html('<i class="bi bi-file-earmark-plus"></i> Chuẩn bị tờ khai'); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); btn.prop('disabled',false).html('<i class="bi bi-file-earmark-plus"></i> Chuẩn bị tờ khai'); }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
