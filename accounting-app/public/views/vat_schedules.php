<?php
$title = 'Bảng kê mua / bán';
$activeMenu = 'vat_schedules';
ob_start();
?>
<div class="toolbar">
    <h5>Bảng kê hóa đơn mua vào / bán ra</h5>
    <div>
        <input type="month" id="period" class="form-control d-inline-block" style="width:auto" value="<?= date('Y-m') ?>">
        <button class="btn btn-primary btn-sm" id="btnLoad"><i class="bi bi-search"></i> Tải bảng kê</button>
        <button class="btn btn-outline-success btn-sm" id="btnPrepare"><i class="bi bi-file-earmark-plus"></i> Lấy số liệu tờ khai</button>
    </div>
</div>

<div id="summaryRow" class="row g-2 mb-3 d-none">
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Tổng VAT đầu vào</small><div class="h5 mb-0 font-monospace" id="sumInput">0</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Tổng VAT đầu ra</small><div class="h5 mb-0 font-monospace" id="sumOutput">0</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">VAT phải nộp</small><div class="h5 mb-0 font-monospace" id="sumPayable">0</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Trạng thái</small><div class="h5 mb-0" id="sumStatus">—</div></div></div></div>
</div>

<ul class="nav nav-tabs mb-2" id="scheduleTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabInput"><i class="bi bi-box-arrow-in-right"></i> Hóa đơn mua vào</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabOutput"><i class="bi bi-box-arrow-right"></i> Hóa đơn bán ra</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAll"><i class="bi bi-table"></i> Tất cả tờ khai</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane active" id="tabInput">
        <div class="card-table">
            <table class="table table-hover table-sm">
                <thead><tr><th>Hóa đơn</th><th>Nhà cung cấp</th><th>Ngày</th><th>Thuế suất</th><th class="text-end">Tiền thuế</th><th>Ghi chú</th></tr></thead>
                <tbody id="inputBody"><tr><td colspan="6" class="text-muted text-center">Chưa có dữ liệu</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane" id="tabOutput">
        <div class="card-table">
            <table class="table table-hover table-sm">
                <thead><tr><th>Hóa đơn</th><th>Khách hàng</th><th>Ngày</th><th>Thuế suất</th><th class="text-end">Tiền thuế</th><th>Ghi chú</th></tr></thead>
                <tbody id="outputBody"><tr><td colspan="6" class="text-muted text-center">Chưa có dữ liệu</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane" id="tabAll">
        <div class="card-table">
            <table class="table table-hover table-sm">
                <thead><tr><th>Kỳ</th><th class="text-end">VAT đầu vào</th><th class="text-end">VAT đầu ra</th><th class="text-end">Phải nộp</th><th class="text-end">SL HĐ vào</th><th class="text-end">SL HĐ ra</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody id="allBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
function loadSchedules(period) {
    $.get('/api/vat/declarations', function(declarations) {
        $('#allBody').empty();
        if (!declarations || declarations.length === 0) {
            $('#allBody').html('<tr><td colspan="8" class="text-muted text-center">Chưa có tờ khai nào. Nhấn "Lấy số liệu tờ khai" để tạo mới.</td></tr>');
            $('#summaryRow').addClass('d-none');
            $('#inputBody').html('<tr><td colspan="6" class="text-muted text-center">Chưa có dữ liệu</td></tr>');
            $('#outputBody').html('<tr><td colspan="6" class="text-muted text-center">Chưa có dữ liệu</td></tr>');
            return;
        }

        declarations.forEach(function(d) {
            var payable = d.vat_payable;
            var payableCls = payable >= 0 ? '' : 'text-success';
            $('#allBody').append('<tr><td>' + d.period + '</td><td class="text-end font-monospace">' + parseInt(d.total_vat_input).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(d.total_vat_output).toLocaleString() + '</td><td class="text-end font-monospace ' + payableCls + '">' + parseInt(payable).toLocaleString() + '</td><td class="text-end">' + d.invoice_count_input + '</td><td class="text-end">' + d.invoice_count_output + '</td><td>' + statusBadge(d.status) + '</td><td><button class="btn btn-sm btn-outline-primary" onclick="loadDetail(\'' + d.id + '\',\'' + d.period + '\')"><i class="bi bi-eye"></i></button></td></tr>');
        });

        var latest = declarations[0];
        if (latest.period === period) {
            loadDetail(latest.id, period);
        }
    }).fail(function() {
        $('#allBody').html('<tr><td colspan="8" class="text-danger text-center">Lỗi tải dữ liệu tờ khai</td></tr>');
    });
}

function loadDetail(id, period) {
    $.get('/api/vat/declarations/' + id, function(r) {
        var d = r.data || r;
        var inputTotal = parseFloat(d.total_vat_input) || 0;
        var outputTotal = parseFloat(d.total_vat_output) || 0;

        $('#sumInput').text(inputTotal.toLocaleString());
        $('#sumOutput').text(outputTotal.toLocaleString());
        $('#sumPayable').text((outputTotal - inputTotal).toLocaleString());
        $('#sumStatus').html(statusBadge(d.status));
        $('#summaryRow').removeClass('d-none');

        var ibody = $('#inputBody').empty();
        var obody = $('#outputBody').empty();
        var details = d.details || [];

        if (details.length === 0) {
            ibody.html('<tr><td colspan="6" class="text-muted text-center">Không có hóa đơn mua vào trong kỳ</td></tr>');
            obody.html('<tr><td colspan="6" class="text-muted text-center">Không có hóa đơn bán ra trong kỳ</td></tr>');
        } else {
            var inputCount = 0, outputCount = 0;
            details.forEach(function(det) {
                var row = '<tr><td>' + esc(det.invoice_ref || '') + '</td><td>' + esc(det.supplier_or_customer || '') + '</td><td>' + (det.invoice_date || '') + '</td><td class="text-end">' + parseFloat(det.vat_rate).toFixed(0) + '%</td><td class="text-end font-monospace">' + parseInt(det.vat_amount).toLocaleString() + '</td><td>' + (det.source_table === 'ap_invoices' ? 'Mua vào' : 'Bán ra') + '</td></tr>';
                if (det.line_type === 'input') { ibody.append(row); inputCount++; }
                else { obody.append(row); outputCount++; }
            });
            if (inputCount === 0) ibody.html('<tr><td colspan="6" class="text-muted text-center">Không có hóa đơn mua vào trong kỳ</td></tr>');
            if (outputCount === 0) obody.html('<tr><td colspan="6" class="text-muted text-center">Không có hóa đơn bán ra trong kỳ</td></tr>');
        }
    }).fail(function() {
        showToast('Lỗi tải chi tiết bảng kê', 'error');
    });
}

$('#btnLoad').click(function() {
    loadSchedules($('#period').val());
});

$('#btnPrepare').click(function() {
    var period = $('#period').val();
    var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xử lý...');
    $.ajax({url:'/api/vat/declarations/prepare', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify({period:period}),
        success:function() { showToast('Đã lấy số liệu tờ khai','success'); loadSchedules(period); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); },
        complete:function() { btn.prop('disabled', false).html('<i class="bi bi-file-earmark-plus"></i> Lấy số liệu tờ khai'); }
    });
});

$(document).ready(function() {
    loadSchedules($('#period').val());
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
