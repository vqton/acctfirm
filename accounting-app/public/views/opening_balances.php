<?php
$title = 'Số dư đầu kỳ';
$activeMenu = 'opening_balances';
ob_start();
?>
<div class="toolbar">
    <h5>Số dư đầu kỳ</h5>
    <div>
        <select id="period" class="form-select d-inline-block" style="width:auto;display:inline">
            <option value="">-- Tất cả kỳ --</option>
            <option value="<?= date('Y-m') ?>" selected><?= date('Y-m') ?></option>
        </select>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'so-du-dau-ky')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-outline-primary btn-sm me-1" onclick="importFromExcel('opening_balance')"><i class="bi bi-upload"></i> Nhập Excel</button>
        <button class="btn btn-primary btn-sm" id="btnAdd"><i class="bi bi-plus-circle"></i> Thêm số dư</button>
        <button class="btn btn-success btn-sm" id="btnConvert"><i class="bi bi-journal-check"></i> Chuyển thành bút toán mở sổ</button>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>TK</th>
                <th>Tên tài khoản</th>
                <th>Loại</th>
                <th class="text-end">Số dư Nợ</th>
                <th class="text-end">Số dư Có</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nhập số dư đầu kỳ</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2">
            <label>Tài khoản</label>
            <select class="form-control" id="editAccountCode">
                <option value="">-- Chọn tài khoản --</option>
            </select>
        </div>
        <div class="mb-2">
            <label>Kỳ</label>
            <input type="month" class="form-control" id="editPeriod" value="<?= date('Y-m') ?>">
        </div>
        <div class="row g-2">
            <div class="col-6">
                <label>Số dư Nợ</label>
                <input type="number" class="form-control" id="editDebitBalance" value="0" step="0.01">
            </div>
            <div class="col-6">
                <label>Số dư Có</label>
                <input type="number" class="form-control" id="editCreditBalance" value="0" step="0.01">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-sm btn-primary" id="btnSaveOB"><i class="bi bi-check"></i> Lưu</button>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    </div>
</div></div></div>

<script>
function loadData() {
    var period = $('#period').val();
    var url = '/api/opening-balances';
    if (period) url += '?period=' + encodeURIComponent(period);
    $.get(url, function(data) {
        var tbody = $('#dataBody').empty();
        data.forEach(function(r) {
            var db = parseFloat(r.debit_balance);
            var cb = parseFloat(r.credit_balance);
            tbody.append('<tr>' +
                '<td class="fw-bold">' + esc(r.account_code) + '</td>' +
                '<td>' + esc(r.account_name) + '</td>' +
                '<td>' + esc(r.account_type) + '</td>' +
                '<td class="text-end font-monospace">' + (db > 0 ? parseInt(db).toLocaleString() : '-') + '</td>' +
                '<td class="text-end font-monospace">' + (cb > 0 ? parseInt(cb).toLocaleString() : '-') + '</td>' +
                '<td>' + statusBadge(r.is_verified ? 'verified' : 'unverified') + '</td>' +
                '<td>' +
                    (r.is_verified ? '' : '<button class="btn btn-sm btn-outline-success me-1" onclick="verifyOB(\'' + esc(r.account_code) + '\',\'' + esc(r.period) + '\')"><i class="bi bi-check-lg"></i></button>') +
                    '<button class="btn btn-sm btn-outline-secondary" onclick="editOB(\'' + esc(r.account_code) + '\',\'' + esc(r.period) + '\',' + db + ',' + cb + ')"><i class="bi bi-pencil"></i></button>' +
                '</td>' +
                '</tr>');
        });
    });
}

function verifyOB(code, period) {
    if (!confirm('Xác nhận đối chiếu số dư đầu kỳ cho tài khoản ' + code + '?')) return;
    $.ajax({url:'/api/opening-balances/' + encodeURIComponent(code) + '/' + encodeURIComponent(period) + '/verify', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Đã đối chiếu số dư đầu kỳ','success'); loadData(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

var currentEditCode = null;

function editOB(code, period, db, cb) {
    currentEditCode = code;
    $('#editAccountCode').val(code);
    $('#editPeriod').val(period);
    $('#editDebitBalance').val(db);
    $('#editCreditBalance').val(cb);
    $('#editModal').modal('show');
}

$('#btnAdd').click(function() {
    currentEditCode = null;
    $('#editAccountCode').val('');
    $('#editPeriod').val($('#period').val() || '<?= date('Y-m') ?>');
    $('#editDebitBalance').val(0);
    $('#editCreditBalance').val(0);
    $('#editModal').modal('show');
});

$('#btnSaveOB').click(function() {
    var data = {
        account_code: $('#editAccountCode').val(),
        period: $('#editPeriod').val(),
        debit_balance: parseFloat($('#editDebitBalance').val()) || 0,
        credit_balance: parseFloat($('#editCreditBalance').val()) || 0,
    };
    if (!data.account_code) { showToast('Vui lòng chọn tài khoản','error'); return; }
    $.ajax({url:'/api/opening-balances/set', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify(data),
        success:function() { showToast('Đã lưu số dư đầu kỳ','success'); $('#editModal').modal('hide'); loadData(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$('#btnConvert').click(function() {
    var period = prompt('Nhập kỳ cần chuyển thành bút toán mở sổ (YYYY-MM):', '<?= date('Y-m') ?>');
    if (!period) return;
    if (!confirm('Xác nhận tạo bút toán mở sổ cho kỳ ' + period + '?')) return;
    var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xử lý...');
    $.ajax({url:'/api/opening-balances/convert', method:'POST', contentType:'application/json', headers:{'X-CSRF-Token':csrf}, data:JSON.stringify({period:period}),
        success:function(r) { showToast(r.message || 'Đã tạo bút toán mở sổ','success'); loadData(); btn.prop('disabled',false).html('<i class="bi bi-journal-check"></i> Chuyển thành bút toán mở sổ'); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); btn.prop('disabled',false).html('<i class="bi bi-journal-check"></i> Chuyển thành bút toán mở sổ'); }
    });
});

function loadAccounts() {
    $.get('/api/coa', function(data) {
        var sel = $('#editAccountCode').empty().append('<option value="">-- Chọn tài khoản --</option>');
        (data.data || data || []).forEach(function(a) {
            sel.append('<option value="' + esc(a.code) + '">' + esc(a.code) + ' - ' + esc(a.name) + '</option>');
        });
    });
}

$(document).ready(function() { loadData(); loadAccounts(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
