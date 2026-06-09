<?php
$title = 'Gửi & Nộp thuế';
$activeMenu = 'tax_submission';
ob_start();
?>
<div class="toolbar">
    <h5>Gửi & Nộp thuế</h5>
    <div>
        <input type="month" id="period" class="form-control d-inline-block" style="width:auto" value="<?= date('Y-m') ?>">
        <button class="btn btn-primary btn-sm" id="btnRefresh"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white py-2"><h6 class="mb-0"><i class="bi bi-file-earmark-text"></i> Thuế GTGT</h6></div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Trạng thái:</span>
                    <span id="vatStatus" class="badge-status badge-warning">Chưa chuẩn bị</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>VAT phải nộp:</span>
                    <span id="vatPayable" class="font-monospace">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>SL hóa đơn đầu vào:</span>
                    <span id="vatInputCount">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>SL hóa đơn đầu ra:</span>
                    <span id="vatOutputCount">0</span>
                </div>
                <hr>
                <a href="/thue/ke-khai-gtgt" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text"></i> Vào kê khai GTGT</a>
                <a href="/thue/bang-ke" class="btn btn-sm btn-outline-info"><i class="bi bi-list-ul"></i> Bảng kê mua/bán</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white py-2"><h6 class="mb-0"><i class="bi bi-calculator"></i> Thuế TNDN</h6></div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Trạng thái:</span>
                    <span id="citStatus" class="badge-status badge-warning">Chưa chuẩn bị</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Thuế TNDN phải nộp:</span>
                    <span id="citPayable" class="font-monospace">0</span>
                </div>
                <hr>
                <a href="/thue/quyet-toan-tndn" class="btn btn-sm btn-outline-success"><i class="bi bi-calculator"></i> Vào quyết toán TNDN</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white py-2"><h6 class="mb-0"><i class="bi bi-person-badge"></i> Thuế TNCN</h6></div>
            <div class="card-body p-3">
                <p class="text-muted small mb-2">Quản lý thuế thu nhập cá nhân cho nhân viên. Khai thuế theo tháng/quý/năm.</p>
                <a href="/tien-luong/thue-tncn" class="btn btn-sm btn-outline-info"><i class="bi bi-person-badge"></i> Tính thuế TNCN</a>
                <a href="/thue/quyet-toan-tncn" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark"></i> Quyết toán TNCN</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark py-2"><h6 class="mb-0"><i class="bi bi-globe"></i> Nhà thầu nước ngoài (FCT)</h6></div>
            <div class="card-body p-3">
                <p class="text-muted small mb-2">Quản lý khấu trừ thuế nhà thầu nước ngoài: VAT + TNDN.</p>
                <a href="/thue/nha-thau-nuoc-ngoai" class="btn btn-sm btn-outline-warning"><i class="bi bi-globe"></i> Vào quản lý FCT</a>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white py-2"><h6 class="mb-0"><i class="bi bi-filetype-xml"></i> Nộp tờ khai điện tử</h6></div>
            <div class="card-body p-3">
                <p class="text-muted small mb-2">Xuất XML tờ khai theo chuẩn TĐT (Tổng cục Thuế) để nộp qua cổng thông tin điện tử của cơ quan thuế.</p>
                <button class="btn btn-sm btn-outline-primary" id="btnExportVatXml"><i class="bi bi-filetype-xml"></i> Xuất XML 01/GTGT</button>
                <button class="btn btn-sm btn-outline-success" id="btnExportCitXml"><i class="bi bi-filetype-xml"></i> Xuất XML 03/TNDN</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadStatus() {
    var period = $('#period').val();

    $.get('/api/vat/declarations', function(data) {
        var vat = data && data.length > 0 ? data[0] : null;
        if (vat) {
            $('#vatStatus').html(statusBadge(vat.status));
            $('#vatPayable').text(parseInt(vat.vat_payable).toLocaleString());
            $('#vatInputCount').text(vat.invoice_count_input || 0);
            $('#vatOutputCount').text(vat.invoice_count_output || 0);
        }
    });

    $.get('/api/cit/calculations', function(data) {
        var cit = data && data.length > 0 ? data[0] : null;
        if (cit) {
            $('#citStatus').html(statusBadge(cit.status));
            $('#citPayable').text(parseInt(cit.cit_payable || cit.income_tax || 0).toLocaleString());
        }
    });
}

$('#btnRefresh').click(function() { loadStatus(); });
$(document).ready(function() { loadStatus(); });

$('#btnExportVatXml').click(function() {
    $.get('/api/vat/declarations', function(data) {
        if (!data || data.length === 0) { showToast('Chưa có tờ khai GTGT để xuất', 'error'); return; }
        var id = data[0].id;
        window.location.href = '/api/vat/declarations/' + id + '/export-htkk-xml';
    });
});

$('#btnExportCitXml').click(function() {
    $.get('/api/cit/calculations', function(data) {
        if (!data || data.length === 0) { showToast('Chưa có quyết toán TNDN để xuất', 'error'); return; }
        var id = data[0].id;
        window.location.href = '/api/cit/declaration/' + id + '/export-xml';
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
