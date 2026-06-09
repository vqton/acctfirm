<?php
// BÁO CÁO KẾT QUẢ HOẠT ĐỘNG KINH DOANH — Mẫu B02-DN (TT 99/2025/TT-BTC)
// VAS-compliant: Intl.NumberFormat('vi-VN'), tabular-nums, semantic colors, zero→hyphen
$title = 'Báo cáo KQKD'; $activeMenu = 'fs'; ob_start(); ?>
<div class="vas-toolbar no-print">
    <h5>Báo cáo Kết quả Hoạt động Kinh doanh <span class="text-muted" style="font-size:13px;font-weight:400">(Mẫu B02-DN)</span></h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="periodSelect">
            <option value="2025">Năm 2025</option>
            <option value="2026" selected>Năm 2026</option>
        </select>
        <button class="btn btn-outline-primary btn-sm ms-1" id="btnExport"><i class="bi bi-download"></i> Xuất XBRL</button>
        <button class="btn btn-outline-secondary btn-sm ms-1" onclick="window.print()"><i class="bi bi-printer"></i> In</button>
        <button class="btn btn-outline-warning btn-sm ms-1" id="btnSaveManual"><i class="bi bi-save"></i> Lưu</button>
    </div>
</div>

<div class="vas-table-wrap">
    <table class="vas-table" id="bc02Table">
        <thead>
            <tr>
                <th style="width:38%">Chỉ tiêu</th>
                <th class="text-center" style="width:8%">Mã số</th>
                <th class="text-center" style="width:10%">Thuyết minh</th>
                <th class="text-end" style="width:22%">Kỳ này</th>
                <th class="text-end" style="width:22%">Kỳ trước</th>
            </tr>
        </thead>
        <tbody id="bc02Body">
            <tr><td colspan="5" class="text-center text-muted py-4">Đang tải dữ liệu…</td></tr>
        </tbody>
    </table>
</div>

<div class="row mt-3 no-print">
    <div class="col-md-4">
        <div class="card p-3 small" id="warnCard" style="display:none;border-left:4px solid #f59e0b">
            <div class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Cảnh báo</div>
            <div id="warnMsg" class="mt-1"></div>
        </div>
    </div>
</div>

<div class="vstack gap-1 mt-3 no-print" id="warningSection" style="display:none">
    <div class="alert alert-warning py-2 small mb-0"><strong><i class="bi bi-exclamation-triangle"></i> Cảnh báo:</strong> <span id="warningsList"></span></div>
</div>

<script>
var csrf = window.csrf || '';
var currentData = [];

function loadReport(period) {
    $('#bc02Body').html('<tr><td colspan="5" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Đang tải…</td></tr>');
    $.getJSON('/api/fs/bc02?period=' + period, function (res) {
        var data = res.data || res || [];
        currentData = data;
        renderTable(data);
        loadWarnings(data);
    }).fail(function () {
        $('#bc02Body').html('<tr><td colspan="5" class="text-center text-danger py-3"><i class="bi bi-exclamation-circle"></i> Lỗi tải dữ liệu</td></tr>');
    });
}

function renderTable(data) {
    var html = '';
    var hasManual = false;
    $.each(data, function (_, r) {
        var bold = r.is_bold ? ' class="fw-bold"' : '';
        var indent = r.is_sub ? ' style="padding-left:24px"' : '';
        var warnCls = (r.gross_loss || r.loss) ? ' class="vas-debit"' : '';
        var thisVal = VAS.fmt(r.thisYear ?? r.current ?? r.this_period);
        var lastVal = VAS.fmt(r.lastYear ?? r.previous ?? r.last_period);
        if (r._is_manual !== undefined) hasManual = true;
        html += '<tr' + bold + '>' +
            '<td' + indent + '>' + esc(r.name || '') + '</td>' +
            '<td class="text-center vas-maso">' + esc(r.ma_so || '') + '</td>' +
            '<td class="text-center small">' + esc(r.notes || r.note || '') + '</td>' +
            '<td class="text-end vas-number"' + warnCls + '>' + thisVal + '</td>' +
            '<td class="text-end vas-number"' + warnCls + '>' + lastVal + '</td>' +
            '</tr>';
    });
    $('#bc02Body').html(html);
    if (hasManual) {
        $('#btnSaveManual').show();
    } else {
        $('#btnSaveManual').hide();
    }
}

function loadWarnings(data) {
    var warnings = [];
    $.each(data, function (_, r) {
        if (r.gross_loss) warnings.push('Lợi nhuận gộp âm (MS 21). Kiểm tra giá vốn.');
        if (r.loss) warnings.push('Lợi nhuận sau thuế âm (MS 60).');
    });
    if (warnings.length) {
        $('#warningsList').text(warnings.join('; '));
        $('#warningSection').show();
    } else {
        $('#warningSection').hide();
    }
}

$(document).ready(function () {
    loadReport($('#periodSelect').val());
    $('#periodSelect').change(function () { loadReport($(this).val()); });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
