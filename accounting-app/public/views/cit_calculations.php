<?php
$title = 'Quyết toán thuế TNDN';
$activeMenu = 'cit_calculation';
ob_start();
?>
<div class="toolbar">
    <h5>Quyết toán thuế TNDN</h5>
    <div>
        <input type="month" id="period" class="form-control d-inline-block" style="width:auto;display:inline" value="<?= date('Y-m') ?>">
        <button class="btn btn-primary btn-sm" id="btnPrepare"><i class="bi bi-calculator"></i> Chuẩn bị quyết toán TNDN</button>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Kỳ</th>
                <th class="text-end">Doanh thu</th>
                <th class="text-end">Giá vốn</th>
                <th class="text-end">Chi phí QLDN</th>
                <th class="text-end">CPBH</th>
                <th class="text-end">Thu nhập chịu thuế</th>
                <th class="text-end">Thuế TNDN</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chi tiết quyết toán TNDN</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Doanh thu (511)</small><div class="h6 mb-0 font-monospace" id="detRevenue">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Giá vốn (632)</small><div class="h6 mb-0 font-monospace" id="detCostOfSales">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">CPBH (641)</small><div class="h6 mb-0 font-monospace" id="detSellingExpense">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">CPQLDN (642)</small><div class="h6 mb-0 font-monospace" id="detAdminExpense">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">CPTC (635)</small><div class="h6 mb-0 font-monospace" id="detFinancialExpense">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">DTTC (515)</small><div class="h6 mb-0 font-monospace" id="detFinancialIncome">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">TN khác (711)</small><div class="h6 mb-0 font-monospace" id="detOtherIncome">0</div></div></div></div>
            <div class="col-3"><div class="card"><div class="card-body py-2"><small class="text-muted">CP khác (811)</small><div class="h6 mb-0 font-monospace" id="detOtherExpense">0</div></div></div></div>
        </div>
        <hr>
        <div class="row g-2">
            <div class="col-4"><strong>Thu nhập chịu thuế:</strong> <span class="font-monospace" id="detTaxableIncome">0</span></div>
            <div class="col-2"><strong>Thuế suất:</strong> <span class="font-monospace" id="detCitRate">20</span>%</div>
            <div class="col-3"><strong>Thuế TNDN phải nộp:</strong> <span class="font-monospace fw-bold text-danger" id="detCitAmount">0</span></div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-sm btn-outline-success" id="btnFinalise"><i class="bi bi-lock"></i> Khóa quyết toán</button>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
    </div>
</div></div></div>

<script>
function loadData() {
    $.get('/api/cit/calculations', function(data) {
        var tbody = $('#dataBody').empty();
        data.forEach(function(r) {
            var badge = r.status === 'finalised' ? 'badge-active' : (r.status === 'draft' ? 'badge-warning' : 'badge-inactive');
            tbody.append('<tr>' +
                '<td>' + r.period + '</td>' +
                '<td class="text-end font-monospace">' + parseInt(r.revenue).toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + parseInt(r.cost_of_sales).toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + parseInt(r.admin_expense).toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + parseInt(r.selling_expense).toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + parseInt(r.taxable_income).toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + parseInt(r.cit_amount).toLocaleString() + '</td>' +
                '<td><span class="badge-status ' + badge + '">' + esc(r.status) + '</span></td>' +
                '<td><button class="btn btn-sm btn-outline-primary" onclick="viewDetail(\'' + r.id + '\')"><i class="bi bi-eye"></i></button></td>' +
                '</tr>');
        });
    });
}

var currentDetailId = null;

function viewDetail(id) {
    currentDetailId = id;
    $.get('/api/cit/calculations/' + id, function(r) {
        $('#detRevenue').text(parseInt(r.revenue).toLocaleString());
        $('#detCostOfSales').text(parseInt(r.cost_of_sales).toLocaleString());
        $('#detSellingExpense').text(parseInt(r.selling_expense).toLocaleString());
        $('#detAdminExpense').text(parseInt(r.admin_expense).toLocaleString());
        $('#detFinancialExpense').text(parseInt(r.financial_expense).toLocaleString());
        $('#detFinancialIncome').text(parseInt(r.financial_income).toLocaleString());
        $('#detOtherIncome').text(parseInt(r.other_income).toLocaleString());
        $('#detOtherExpense').text(parseInt(r.other_expense).toLocaleString());
        $('#detTaxableIncome').text(parseInt(r.taxable_income).toLocaleString());
        $('#detCitRate').text(parseFloat(r.cit_rate).toFixed(0));
        $('#detCitAmount').text(parseInt(r.cit_amount).toLocaleString());
        if (r.status === 'finalised') { $('#btnFinalise').prop('disabled', true).text('Đã khóa'); }
        else { $('#btnFinalise').prop('disabled', false).text('Khóa quyết toán'); }
        $('#detailModal').modal('show');
    });
}

$('#btnFinalise').click(function() {
    if (!currentDetailId || !confirm('Xác nhận khóa quyết toán TNDN? Sau khi khóa không thể sửa.')) return;
    $.ajax({url:'/api/cit/calculations/' + currentDetailId + '/finalise', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Đã khóa quyết toán TNDN thành công','success'); $('#detailModal').modal('hide'); loadData(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$('#btnPrepare').click(function() {
    var period = $('#period').val();
    var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xử lý...');
    $.ajax({url:'/api/cit/calculations/prepare', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify({period:period}),
        success:function() { showToast('Đã chuẩn bị quyết toán TNDN','success'); loadData(); btn.prop('disabled',false).html('<i class="bi bi-calculator"></i> Chuẩn bị quyết toán TNDN'); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); btn.prop('disabled',false).html('<i class="bi bi-calculator"></i> Chuẩn bị quyết toán TNDN'); }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
