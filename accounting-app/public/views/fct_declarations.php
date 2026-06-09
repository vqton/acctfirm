<?php
$title = 'Thuế nhà thầu nước ngoài (FCT)';
$activeMenu = 'fct';
ob_start();
?>
<style>
.service-type-badge { font-size: 0.8rem; padding: 2px 8px; border-radius: 4px; }
.st-services { background: #e3f2fd; color: #1565c0; }
.st-services_with_goods { background: #f3e5f5; color: #7b1fa2; }
.st-trading { background: #e8f5e9; color: #2e7d32; }
.st-leasing { background: #fff3e0; color: #e65100; }
.st-other { background: #f5f5f5; color: #616161; }
</style>

<div class="toolbar">
    <h5><i class="bi bi-globe"></i> Thuế nhà thầu nước ngoài (FCT)</h5>
    <div>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Thêm hợp đồng</button>
        <input type="month" id="fctPeriod" class="form-control d-inline-block" style="width:auto;display:inline" value="<?= date('Y-m') ?>">
        <button class="btn btn-primary btn-sm" id="btnPrepareFct"><i class="bi bi-file-earmark-plus"></i> Chuẩn bị tờ khai</button>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabContracts">Hợp đồng</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabDeclarations">Tờ khai</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane active" id="tabContracts">
        <div class="card-table">
            <table class="table table-hover">
                <thead><tr><th>Số HĐ</th><th>Nhà thầu</th><th>Quốc gia</th><th>Loại dịch vụ</th><th class="text-end">Giá trị HĐ</th><th class="text-end">VAT khấu trừ</th><th class="text-end">TNDN khấu trừ</th><th class="text-end">Thanh toán ròng</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody id="contractBody"></tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane" id="tabDeclarations">
        <div class="card-table">
            <table class="table table-hover">
                <thead><tr><th>Kỳ</th><th class="text-end">Tổng giá trị HĐ</th><th class="text-end">Tổng VAT khấu trừ</th><th class="text-end">Tổng TNDN khấu trừ</th><th class="text-end">Số HĐ</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody id="declBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Thêm hợp đồng nhà thầu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label">Số hợp đồng</label><input type="text" class="form-control" id="fContractNo"></div>
        <div class="mb-2"><label class="form-label">Tên nhà thầu nước ngoài</label><input type="text" class="form-control" id="fContractorName"></div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Quốc gia</label><input type="text" class="form-control" id="fCountry" placeholder="VD: Singapore"></div>
            <div class="col-6"><label class="form-label">Loại dịch vụ</label>
                <select class="form-select" id="fServiceType">
                    <option value="services">Dịch vụ + Cho thuê máy móc (VAT 5% + CIT 5%)</option>
                    <option value="services_with_goods">Dịch vụ kèm hàng hóa (VAT 3% + CIT 2%)</option>
                    <option value="trading">Phân phối, cung ứng (VAT 1% + CIT 1%)</option>
                    <option value="leasing">Cho thuê máy móc thiết bị (VAT 5% + CIT 5%)</option>
                    <option value="other">Kinh doanh khác (VAT 2% + CIT 2%)</option>
                </select>
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Giá trị hợp đồng (gồm VAT)</label><input type="number" class="form-control" id="fContractValue" step="1000"></div>
            <div class="col-6"><label class="form-label">Tiền tệ</label>
                <select class="form-select" id="fCurrency"><option value="VND">VND</option><option value="USD">USD</option><option value="EUR">EUR</option></select>
            </div>
        </div>
        <div class="mb-2"><label class="form-label">Ghi chú</label><textarea class="form-control" id="fNotes" rows="2"></textarea></div>
        <div class="card bg-light p-2 mb-2" id="calcPreview" style="display:none">
            <small class="text-muted">Xem trước khấu trừ:</small>
            <div class="row g-1 mt-1">
                <div class="col-4"><small>VAT: </small><span class="fw-bold" id="previewVat">0</span></div>
                <div class="col-4"><small>TNDN: </small><span class="fw-bold" id="previewCit">0</span></div>
                <div class="col-4"><small>Ròng: </small><span class="fw-bold" id="previewNet">0</span></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button class="btn btn-sm btn-primary" id="btnSave"><i class="bi bi-check-lg"></i> Ghi nhận</button>
    </div>
</div></div></div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chi tiết tờ khai FCT</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-4"><div class="card"><div class="card-body py-2"><small class="text-muted">VAT khấu trừ</small><div class="h5 mb-0 font-monospace" id="detVat">0</div></div></div></div>
            <div class="col-4"><div class="card"><div class="card-body py-2"><small class="text-muted">TNDN khấu trừ</small><div class="h5 mb-0 font-monospace" id="detCit">0</div></div></div></div>
            <div class="col-4"><div class="card"><div class="card-body py-2"><small class="text-muted">Số HĐ</small><div class="h5 mb-0 font-monospace" id="detCount">0</div></div></div></div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-sm btn-outline-success" id="btnFinaliseFct"><i class="bi bi-lock"></i> Khóa tờ khai</button>
        <button class="btn btn-sm btn-outline-primary" id="btnExportFctCsv"><i class="bi bi-download"></i> Xuất CSV</button>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
    </div>
</div></div></div>

<script>
var currentFctId = null;

function loadContracts() {
    $.get('/api/fct/contracts', function(d) {
        var tbody = $('#contractBody').empty();
        d.forEach(function(r) {
            var cls = r.service_type ? 'st-' + r.service_type : '';
            tbody.append('<tr><td>' + esc(r.contract_no) + '</td><td>' + esc(r.contractor_name) + '</td><td>' + esc(r.contractor_country) + '</td><td><span class="service-type-badge ' + cls + '">' + esc(r.service_type) + '</span></td><td class="text-end font-monospace">' + parseInt(r.contract_value).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(r.vat_withholding).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(r.cit_withholding).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(r.net_payment).toLocaleString() + '</td><td>' + statusBadge(r.status) + '</td><td>' + (r.status === 'draft' ? '<button class="btn btn-sm btn-outline-danger" onclick="cancelContract(\'' + r.id + '\')"><i class="bi bi-x-circle"></i></button>' : '') + '</td></tr>');
        });
    });
}

function loadDeclarations() {
    $.get('/api/fct/declarations', function(d) {
        var tbody = $('#declBody').empty();
        d.forEach(function(r) {
            tbody.append('<tr><td>' + r.period + '</td><td class="text-end font-monospace">' + parseInt(r.total_contract_value).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(r.total_vat_withholding).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(r.total_cit_withholding).toLocaleString() + '</td><td class="text-end">' + r.contract_count + '</td><td>' + statusBadge(r.status) + '</td><td><button class="btn btn-sm btn-outline-primary" onclick="viewFctDetail(\'' + r.id + '\')"><i class="bi bi-eye"></i></button> <button class="btn btn-sm btn-outline-success" onclick="exportFctCsv(\'' + r.id + '\')"><i class="bi bi-download"></i></button></td></tr>');
        });
    });
}

function cancelContract(id) {
    if (!confirm('Hủy hợp đồng nhà thầu này?')) return;
    $.ajax({url:'/api/fct/contracts/' + id + '/cancel', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Đã hủy hợp đồng','success'); loadContracts(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

var currentFctDetailId = null;

function viewFctDetail(id) {
    currentFctDetailId = id;
    $.get('/api/fct/declarations/' + id, function(r) {
        var d = r.data;
        $('#detVat').text(parseInt(d.total_vat_withholding).toLocaleString());
        $('#detCit').text(parseInt(d.total_cit_withholding).toLocaleString());
        $('#detCount').text(d.contract_count);
        if (d.status === 'finalised') { $('#btnFinaliseFct').prop('disabled', true).text('Đã khóa'); }
        else { $('#btnFinaliseFct').prop('disabled', false).text('Khóa tờ khai'); }
        $('#detailModal').modal('show');
    });
}

function exportFctCsv(id) {
    window.location.href = '/api/fct/declarations/' + id + '/export';
}

$('#btnExportFctCsv').click(function() { if (currentFctDetailId) exportFctCsv(currentFctDetailId); });

$('#btnFinaliseFct').click(function() {
    if (!currentFctDetailId || !confirm('Xác nhận khóa tờ khai FCT?')) return;
    $.ajax({url:'/api/fct/declarations/' + currentFctDetailId + '/finalise', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Đã khóa tờ khai thành công','success'); $('#detailModal').modal('hide'); loadDeclarations(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$('#btnPrepareFct').click(function() {
    var period = $('#fctPeriod').val();
    var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xử lý...');
    $.ajax({url:'/api/fct/declarations/prepare', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify({period:period}),
        success:function() { showToast('Đã chuẩn bị tờ khai FCT','success'); loadDeclarations(); btn.prop('disabled',false).html('<i class="bi bi-file-earmark-plus"></i> Chuẩn bị tờ khai'); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); btn.prop('disabled',false).html('<i class="bi bi-file-earmark-plus"></i> Chuẩn bị tờ khai'); }
    });
});

$('#fServiceType, #fContractValue').on('change keyup', function() {
    var serviceType = $('#fServiceType').val();
    var value = parseFloat($('#fContractValue').val()) || 0;
    if (value > 0) {
        $.post('/api/fct/calculate', JSON.stringify({service_type: serviceType, contract_value: value}),
            function(r) {
                $('#previewVat').text(parseInt(r.vat_withholding).toLocaleString());
                $('#previewCit').text(parseInt(r.cit_withholding).toLocaleString());
                $('#previewNet').text(parseInt(r.net_payment).toLocaleString());
                $('#calcPreview').show();
            }
        );
    } else { $('#calcPreview').hide(); }
});

$('#btnSave').click(function() {
    var data = {
        contract_no: $('#fContractNo').val(),
        contractor_name: $('#fContractorName').val(),
        contractor_country: $('#fCountry').val(),
        service_type: $('#fServiceType').val(),
        contract_value: parseFloat($('#fContractValue').val()) || 0,
        currency: $('#fCurrency').val(),
        notes: $('#fNotes').val()
    };
    if (!data.contract_no || !data.contractor_name || !data.contract_value) { showToast('Vui lòng nhập đầy đủ thông tin','error'); return; }
    var btn = $('#btnSave').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({url:'/api/fct/contracts', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify(data),
        success:function() { showToast('Đã ghi nhận hợp đồng','success'); $('#addModal').modal('hide'); loadContracts(); btn.prop('disabled',false).html('<i class="bi bi-check-lg"></i> Ghi nhận'); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); btn.prop('disabled',false).html('<i class="bi bi-check-lg"></i> Ghi nhận'); }
    });
});

$(document).ready(function() { loadContracts(); loadDeclarations(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
