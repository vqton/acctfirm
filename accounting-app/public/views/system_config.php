<?php
$title = 'Cấu hình hệ thống';
$activeMenu = 'system_config';
ob_start();
?>
<div class="toolbar">
    <h5>Cấu hình hệ thống</h5>
    <button class="btn btn-primary btn-sm" id="btnSave"><i class="bi bi-floppy"></i> Lưu cấu hình</button>
</div>

<div class="card-table p-3">
    <form id="configForm">
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Thông tin doanh nghiệp</h6>
                <div class="mb-3"><label class="form-label">Tên doanh nghiệp</label><input type="text" class="form-control" id="companyName" value="CÔNG TY TNHH ABC"></div>
                <div class="mb-3"><label class="form-label">Mã số thuế</label><input type="text" class="form-control" id="taxCode" value="0123456789"></div>
                <div class="mb-3"><label class="form-label">Địa chỉ</label><input type="text" class="form-control" id="companyAddress" value="Hà Nội"></div>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Kế toán</h6>
                <div class="mb-3"><label class="form-label">Phương pháp tính thuế GTGT</label>
                    <select class="form-select" id="vatMethod"><option value="deduction" selected>Khấu trừ</option><option value="direct">Trực tiếp</option></select>
                </div>
                <div class="mb-3"><label class="form-label">Kỳ kế toán hiện tại</label>
                    <input type="month" class="form-control" id="currentPeriod" value="<?= date('Y-m') ?>">
                </div>
                <div class="mb-3"><label class="form-label">Đơn vị tiền tệ</label>
                    <select class="form-select" id="currency"><option value="VND" selected>VND - Việt Nam Đồng</option></select>
                </div>
            </div>
            <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3">Cấu hình khác</h6>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label">Hạn mức duyệt chi (VND)</label><input type="text" class="form-control" id="approvalLimit" value="50000000"></div>
                    <div class="col-md-3"><label class="form-label">Ngưỡng cảnh báo ngân sách</label><input type="text" class="form-control" id="budgetWarn" value="90"></div>
                    <div class="col-md-3"><label class="form-label">Tỷ lệ dự phòng phải thu</label><input type="text" class="form-control" id="provisionRate" value="5"></div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$('#btnSave').click(function() {
    var data = {
        company_name: $('#companyName').val(),
        tax_code: $('#taxCode').val(),
        company_address: $('#companyAddress').val(),
        vat_method: $('#vatMethod').val(),
        current_period: $('#currentPeriod').val(),
        currency: $('#currency').val(),
        approval_limit: parseInt($('#approvalLimit').val()) || 0,
        budget_warn_pct: parseInt($('#budgetWarn').val()) || 90,
    };
    $.ajax({url:'/api/system/config', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify(data),
        success:function() { showToast('Đã lưu cấu hình hệ thống','success'); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
