<?php // Màn hình: Sổ chi tiết (Subsidiary Ledger)
// API: GET /api/reports/sub-ledger?type=xxx&account_code=xxx&from_date=xxx&to_date=xxx
// API: POST /api/reports/sub-ledger/export (CSV/HTML)
// API: GET /api/reports/sub-ledger/supported
// Nghiệp vụ: Xem và xuất các loại sổ chi tiết: Sổ cái, Sổ quỹ, Sổ kho, Sổ công nợ
// Mỗi loại sổ có bộ lọc riêng phù hợp với nghiệp vụ kế toán
$title = 'Sổ chi tiết';
$activeMenu = 'so_chi_tiet';
ob_start();
?>
<div class="toolbar">
    <h5>Sổ chi tiết <span class="stats">(Subsidiary Ledger)</span></h5>
    <div>
        <button class="btn btn-outline-primary btn-sm" id="exportCsvBtn"><i class="bi bi-download"></i> CSV</button>
        <button class="btn btn-outline-secondary btn-sm" id="exportHtmlBtn"><i class="bi bi-printer"></i> In / PDF</button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Loại sổ</label>
                <select class="form-select form-select-sm" id="reportType">
                    <option value="">-- Chọn loại sổ --</option>
                    <?php foreach ($reports as $r): ?>
                    <option value="<?= $r['type'] ?>" data-icon="<?= $r['icon'] ?>"><?= $r['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3" id="filterAccount">
                <label class="form-label fw-semibold" style="font-size:12px;">Tài khoản</label>
                <select class="form-select form-select-sm" id="accountCode">
                    <option value="">-- Chọn tài khoản --</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['code'] ?>"><?= $a['code'] ?> - <?= $a['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:12px;">Từ ngày</label>
                <input type="date" class="form-control form-control-sm" id="fromDate">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:12px;">Đến ngày</label>
                <input type="date" class="form-control form-control-sm" id="toDate">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-grow-1" id="loadBtn"><i class="bi bi-search"></i> Xem</button>
                <button class="btn btn-outline-secondary btn-sm" id="resetBtn"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
        </div>
        <div class="row g-2 mt-1" id="extraFilters" style="display:none;">
            <div class="col-md-3" id="filterEntity">
                <label class="form-label fw-semibold" style="font-size:12px;" id="entityLabel">Đối tượng</label>
                <select class="form-select form-select-sm" id="entityId"><option value="">-- Tất cả --</option></select>
            </div>
        </div>
    </div>
</div>

<div class="card-table" id="reportContainer">
    <div class="empty-state">
        <i class="bi bi-file-earmark-text"></i>
        <p>Chọn loại sổ và thông số bộ lọc, sau đó nhấn "Xem" để hiển thị dữ liệu.</p>
    </div>
</div>

<script>
var supportedReports = <?= json_encode($reports) ?>;
var accounts = <?= json_encode($accounts) ?>;

// Cập nhật filter fields khi đổi loại sổ
$('#reportType').on('change', function() {
    var type = $(this).val();
    $('#extraFilters').hide();
    $('#filterAccount').show();
    $('#entityId').empty().append('<option value="">-- Tất cả --</option>');

    if (type === 'inventory_ledger') {
        $('#filterAccount').hide();
        loadItems();
        $('#extraFilters').show();
        $('#entityLabel').text('Mặt hàng');
    } else if (type === 'ar_ledger') {
        loadCustomers();
        $('#extraFilters').show();
        $('#entityLabel').text('Khách hàng');
    } else if (type === 'ap_ledger') {
        loadSuppliers();
        $('#extraFilters').show();
        $('#entityLabel').text('Nhà cung cấp');
    }
});

function loadItems() {
    $.get('/api/items', function(data) {
        var sel = $('#entityId').empty().append('<option value="">-- Tất cả --</option>');
        (data || []).forEach(function(i) {
            sel.append('<option value="' + i.id + '">' + (i.code || '') + ' - ' + i.name + '</option>');
        });
    });
}

function loadCustomers() {
    $.get('/api/customers', function(data) {
        var sel = $('#entityId').empty().append('<option value="">-- Tất cả --</option>');
        (data.data || data || []).forEach(function(c) {
            sel.append('<option value="' + c.id + '">' + (c.code || '') + ' - ' + c.name + '</option>');
        });
    });
}

function loadSuppliers() {
    $.get('/api/suppliers', function(data) {
        var sel = $('#entityId').empty().append('<option value="">-- Tất cả --</option>');
        (data.data || data || []).forEach(function(s) {
            sel.append('<option value="' + s.id + '">' + (s.code || '') + ' - ' + s.name + '</option>');
        });
    });
}

// Tải dữ liệu
$('#loadBtn').on('click', function() {
    var type = $('#reportType').val();
    if (!type) { showToast('Vui lòng chọn loại sổ.', 'error'); return; }

    var params = { type: type };
    var ac = $('#accountCode').val();
    if (ac) params.account_code = ac;
    var fd = $('#fromDate').val();
    if (fd) params.from_date = fd;
    var td = $('#toDate').val();
    if (td) params.to_date = td;
    var ent = $('#entityId').val();
    if (ent) {
        if (type === 'inventory_ledger') params.item_id = ent;
        else if (type === 'ar_ledger') params.customer_id = ent;
        else if (type === 'ap_ledger') params.supplier_id = ent;
    }

    $.get('/api/reports/sub-ledger', params, function(resp) {
        renderReport(resp.data);
    }).fail(function(xhr) {
        var msg = 'Có lỗi khi tải báo cáo.';
        try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
        showToast(msg, 'error');
        $('#reportContainer').html('<div class="empty-state"><i class="bi bi-exclamation-triangle"></i><p>' + esc(msg) + '</p></div>');
    });
});

function renderReport(data) {
    if (!data || !data.rows || data.rows.length === 0) {
        $('#reportContainer').html('<div class="empty-state"><i class="bi bi-inbox"></i><p>Không có dữ liệu cho báo cáo này.</p></div>');
        return;
    }

    var html = '<div class="card-header-x d-flex justify-content-between align-items-center">';
    html += '<div><strong>' + esc(data.title) + '</strong> <span class="text-muted ms-2" style="font-size:12px;">' + esc(data.period) + '</span></div>';
    html += '<div style="font-size:12px;">SD đầu: <strong>' + fmtNum(data.opening_balance) + '</strong> | SD cuối: <strong>' + fmtNum(data.closing_balance) + '</strong></div>';
    html += '</div>';
    html += '<table class="table table-hover table-sm"><thead><tr>';

    data.headers.forEach(function(h) {
        html += '<th>' + esc(h) + '</th>';
    });
    html += '</tr></thead><tbody>';

    // Dòng số dư đầu kỳ
    html += '<tr class="table-info fw-semibold"><td colspan="' + (data.headers.length - 1) + '">Số dư đầu kỳ</td>';
    html += '<td class="text-end font-monospace">' + fmtNum(data.opening_balance) + '</td></tr>';

    // Dòng phát sinh
    data.rows.forEach(function(r) {
        html += '<tr>';
        data.headers.forEach(function(h, idx) {
            var val = r[getKeyByIndex(h, data.headers)] ?? '';
            if (typeof val === 'number') {
                html += '<td class="text-end font-monospace">' + fmtNum(val) + '</td>';
            } else {
                html += '<td>' + esc(val) + '</td>';
            }
        });
        html += '</tr>';
    });

    // Dòng tổng cộng
    html += '<tr class="table-secondary fw-semibold"><td colspan="2">Tổng cộng</td>';
    for (var i = 2; i < data.headers.length - 1; i++) {
        var key = getKeyByIndex(data.headers[i], data.headers);
        var total = 0;
        data.rows.forEach(function(r) { total += parseFloat(r[key] || 0); });
        html += '<td class="text-end font-monospace">' + fmtNum(total) + '</td>';
    }
    html += '<td class="text-end font-monospace">' + fmtNum(data.closing_balance) + '</td></tr>';

    html += '</tbody></table>';
    $('#reportContainer').html(html);
}

function getKeyByIndex(header, headers) {
    var map = {
        'Ngày': 'date', 'Số CT': 'reference', 'Diễn giải': 'description',
        'Phát sinh Nợ': 'debit', 'Phát sinh Có': 'credit', 'TK ĐƯ': 'contra_account',
        'Số dư': 'balance', 'TK Đối ứng': 'contra_account',
        'Thu': 'receipt', 'Chi': 'payment', 'Nhận': 'receipt',
        'Nhập SL': 'in_qty', 'Nhập GT': 'in_amount',
        'Xuất SL': 'out_qty', 'Xuất GT': 'out_amount',
        'Tồn SL': 'closing_qty', 'Tồn GT': 'closing_amount',
        'Mã KH': 'code', 'Tên khách hàng': 'name',
        'Mã NCC': 'code', 'Tên nhà cung cấp': 'name',
        'Số dư cuối': 'balance',
        'Phát sinh Nợ (giảm)': 'debit', 'Phát sinh Có (tăng)': 'credit',
    };
    return map[header] || header;
}

function fmtNum(n) {
    return parseFloat(n || 0).toLocaleString('vi-VN', {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

// Export CSV
$('#exportCsvBtn').on('click', function() {
    var type = $('#reportType').val();
    if (!type) { showToast('Vui lòng chọn loại sổ trước khi xuất.', 'error'); return; }
    var params = { type: type, format: 'csv', params: {} };
    var ac = $('#accountCode').val();
    if (ac) params.params.account_code = ac;
    var fd = $('#fromDate').val();
    if (fd) params.params.from_date = fd;
    var td = $('#toDate').val();
    if (td) params.params.to_date = td;
    var ent = $('#entityId').val();
    if (ent) {
        if (type === 'inventory_ledger') params.params.item_id = ent;
        else if (type === 'ar_ledger') params.params.customer_id = ent;
        else if (type === 'ap_ledger') params.params.supplier_id = ent;
    }

    $.ajax({
        url: '/api/reports/sub-ledger/export',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrf },
        data: JSON.stringify(params),
        xhrFields: { responseType: 'blob' },
        success: function(blob) {
            var link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = type + '_' + new Date().toISOString().slice(0,10) + '.csv';
            link.click();
        },
        error: function() { showToast('Có lỗi khi xuất CSV.', 'error'); }
    });
});

// Export HTML (in/PDF)
$('#exportHtmlBtn').on('click', function() {
    var type = $('#reportType').val();
    if (!type) { showToast('Vui lòng chọn loại sổ trước khi in.', 'error'); return; }
    var params = { type: type, format: 'html', params: {} };
    var ac = $('#accountCode').val();
    if (ac) params.params.account_code = ac;
    var fd = $('#fromDate').val();
    if (fd) params.params.from_date = fd;
    var td = $('#toDate').val();
    if (td) params.params.to_date = td;
    var ent = $('#entityId').val();
    if (ent) {
        if (type === 'inventory_ledger') params.params.item_id = ent;
        else if (type === 'ar_ledger') params.params.customer_id = ent;
        else if (type === 'ap_ledger') params.params.supplier_id = ent;
    }

    $.ajax({
        url: '/api/reports/sub-ledger/export',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrf },
        data: JSON.stringify(params),
        success: function(html) {
            var w = window.open('', '_blank');
            w.document.write(html);
            w.document.close();
        },
        error: function() { showToast('Có lỗi khi xuất HTML.', 'error'); }
    });
});

// Reset
$('#resetBtn').on('click', function() {
    $('#reportType').val('');
    $('#accountCode').val('');
    $('#fromDate').val('');
    $('#toDate').val('');
    $('#entityId').empty();
    $('#extraFilters').hide();
    $('#reportContainer').html('<div class="empty-state"><i class="bi bi-file-earmark-text"></i><p>Chọn loại sổ và thông số bộ lọc, sau đó nhấn "Xem" để hiển thị dữ liệu.</p></div>');
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
