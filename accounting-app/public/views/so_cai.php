<?php // Màn hình: Sổ cái tài khoản (S05-DN)
// API: GET /api/gl/accounts, GET /api/gl/ledger?account=&mode=&from=&to=
// Nghiệp vụ: Sổ cái chi tiết tài khoản — hiển thị phát sinh Nợ/Có, số dư đầu kỳ, cuối kỳ
// Chế độ: detail (chi tiết theo ngày) hoặc monthly (theo tháng - mẫu S05-DN)
// Tuân thủ: Mẫu S05-DN theo Thông tư 200 — dùng cho hình thức Nhật ký chung
$title = 'Sổ cái'; $activeMenu = 'so_cai'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ cái <span class="stats">(General Ledger — Mẫu S05-DN)</span></h5>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" onclick="printLedger()"><i class="bi bi-printer"></i> In sổ</button>
    </div>
</div>
<div class="card p-3 mb-3 border-0 shadow-sm">
    <div class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label small">Tài khoản</label><select class="form-select form-select-sm" id="accountSelect" style="width:280px"></select></div>
        <div class="col-auto"><label class="form-label small">Từ ngày</label><input type="date" class="form-control form-control-sm" id="fromDate"></div>
        <div class="col-auto"><label class="form-label small">Đến ngày</label><input type="date" class="form-control form-control-sm" id="toDate" value="<?=date('Y-m-d')?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" onclick="loadData()"><i class="bi bi-search"></i> Xem</button></div>
        <div class="col-auto ms-3">
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="modeToggle" id="modeDetail" value="detail" checked>
                <label class="btn btn-outline-secondary" for="modeDetail">Chi tiết</label>
                <input type="radio" class="btn-check" name="modeToggle" id="modeMonthly" value="monthly">
                <label class="btn btn-outline-secondary" for="modeMonthly">Theo tháng (S05-DN)</label>
            </div>
        </div>
    </div>
</div>
<div id="ledgerHeader" class="mb-2"></div>
<div class="card-table"><table class="table table-hover table-sm" id="ledgerTable">
    <thead id="tableHead"></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
var currentMode = 'detail';

function loadAccounts() {
    $.get('/api/gl/accounts', function(data) {
        data.forEach(function(a) {
            $('#accountSelect').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>');
        });
    });
}

function getMode() {
    return $('input[name="modeToggle"]:checked').val() || 'detail';
}

function renderDetail(d) {
    var header = '<div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">';
    header += '<div><strong>'+esc(d.account_code)+' - '+esc(d.account_name)+'</strong> <span class="text-muted small">('+esc(d.account_type)+')</span></div>';
    header += '<div class="text-end"><small class="text-muted">Dư đầu: </small><strong>'+parseFloat(d.opening_balance).toLocaleString()+'</strong>';
    header += ' &nbsp; <small class="text-muted">Dư cuối: </small><strong>'+parseFloat(d.closing_balance).toLocaleString()+'</strong></div>';
    header += '</div>';
    $('#ledgerHeader').html(header);

    $('#tableHead').html('<tr><th>Ngày</th><th>Số CT</th><th>Diễn giải</th><th>TK ĐƯ</th><th class="text-end">Phát sinh Nợ</th><th class="text-end">Phát sinh Có</th><th class="text-end">Số dư</th></tr>');
    var tbody=$('#dataBody'); tbody.empty();
    if (d.entries.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Không có giao dịch trong kỳ</td></tr>');
        return;
    }
    tbody.append('<tr class="table-secondary"><td colspan="4"><strong>Dư đầu kỳ</strong></td><td class="text-end font-monospace"></td><td class="text-end font-monospace"></td><td class="text-end font-monospace fw-bold">'+parseFloat(d.opening_balance).toLocaleString()+'</td></tr>');
    d.entries.forEach(function(r) {
        var dr = r.debit > 0 ? parseFloat(r.debit).toLocaleString() : '';
        var cr = r.credit > 0 ? parseFloat(r.credit).toLocaleString() : '';
        tbody.append('<tr><td>'+esc(r.date)+'</td><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td>'+esc(r.contra_account)+'</td><td class="text-end font-monospace">'+dr+'</td><td class="text-end font-monospace">'+cr+'</td><td class="text-end font-monospace fw-bold">'+parseFloat(r.running_balance).toLocaleString()+'</td></tr>');
    });
}

function renderMonthly(d) {
    var header = '<div class="p-2 bg-light rounded">';
    header += '<div class="d-flex justify-content-between"><div><strong>'+esc(d.account_code)+' - '+esc(d.account_name)+'</strong> <span class="text-muted small">('+esc(d.account_type)+')</span></div>';
    header += '<div class="text-end"><small class="text-muted">Dư đầu năm: </small><strong>'+parseFloat(d.opening_balance).toLocaleString()+'</strong>';
    header += ' &nbsp; <small class="text-muted">Dư cuối năm: </small><strong>'+parseFloat(d.closing_balance).toLocaleString()+'</strong></div></div>';
    header += '<div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> Mẫu số S05-DN — Sổ cái (dùng cho hình thức Nhật ký - Chứng từ)</div>';
    header += '</div>';
    $('#ledgerHeader').html(header);

    $('#tableHead').html('<tr><th>Tháng</th><th class="text-end">SDĐK</th><th class="text-end">PS Nợ</th><th class="text-end">PS Có</th><th class="text-end">SDCK</th><th>Chi tiết Nợ theo TK ĐƯ</th></tr>');
    var tbody=$('#dataBody'); tbody.empty();
    if (d.entries.length === 0) {
        tbody.append('<tr><td colspan="6" class="text-center text-muted py-3">Không có dữ liệu</td></tr>');
        return;
    }
    d.entries.forEach(function(r) {
        var hasItems = r.contra_debit_items && r.contra_debit_items.length > 0;
        var contraHtml = hasItems ? r.contra_debit_items.map(function(c) {
            return esc(c.contra_account_code) + ': ' + parseFloat(c.amount).toLocaleString();
        }).join('<br>') : '<span class="text-muted">—</span>';
        var activeClass = (r.total_debit > 0 || r.total_credit > 0) ? '' : ' text-muted';
        tbody.append('<tr class="'+activeClass+'"><td><strong>'+esc(r.period)+'</strong></td>'
            +'<td class="text-end font-monospace">'+parseFloat(r.opening_balance).toLocaleString()+'</td>'
            +'<td class="text-end font-monospace">'+parseFloat(r.total_debit).toLocaleString()+'</td>'
            +'<td class="text-end font-monospace">'+parseFloat(r.total_credit).toLocaleString()+'</td>'
            +'<td class="text-end font-monospace fw-bold">'+parseFloat(r.closing_balance).toLocaleString()+'</td>'
            +'<td class="small">'+contraHtml+'</td></tr>');
    });
}

// Tải sổ cái — GET /api/gl/ledger?account=&mode=&from=&to=
// detail: hiển thị từng giao dịch với số dư running balance
// monthly: hiển thị tổng hợp theo tháng (mẫu S05-DN) + chi tiết Nợ theo TK ĐƯ
function loadData() {
    var account = $('#accountSelect').val();
    if (!account) return;
    var mode = getMode();
    currentMode = mode;
    var params = {account: account, mode: mode};
    var f = $('#fromDate').val(); if (f) params.from = f;
    var t = $('#toDate').val(); if (t) params.to = t;

    $.get('/api/gl/ledger', params, function(d) {
        if (mode === 'monthly') {
            renderMonthly(d);
        } else {
            renderDetail(d);
        }
    });
}

function getMonthLabel(period) {
    var parts = period.split('-');
    var names = ['', 'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
    return names[parseInt(parts[1])] + ' năm ' + parts[0];
}

function printLedger() {
    var account = $('#accountSelect option:selected').text();
    var mode = currentMode;
    var title = 'SỔ CÁI - ' + account;
    var today = new Date();
    var dateStr = 'Ngày ' + today.getDate() + ' tháng ' + (today.getMonth()+1) + ' năm ' + today.getFullYear();

    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>'+esc(title)+'</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body{font-family:"Times New Roman",serif;font-size:12px;padding:40px;}');
    printWindow.document.write('table{width:100%;border-collapse:collapse;}');
    printWindow.document.write('th,td{padding:6px 8px;text-align:left;border:1px solid #000;vertical-align:top;}');
    printWindow.document.write('th{background:#f0f0f0;text-align:center;}');
    printWindow.document.write('.text-end{text-align:right;}');
    printWindow.document.write('.fw-bold{font-weight:bold;}');
    printWindow.document.write('.text-center{text-align:center;}');
    printWindow.document.write('.signatures{display:flex;justify-content:space-between;margin-top:40px;}');
    printWindow.document.write('.signatures>div{text-align:center;width:30%;}');
    printWindow.document.write('.header-info{text-align:center;margin-bottom:24px;}');
    printWindow.document.write('.header-info h2{margin:0;font-size:18px;text-transform:uppercase;letter-spacing:2px;}');
    printWindow.document.write('.header-info .sub{margin:4px 0 0;font-size:11px;color:#555;}');
    printWindow.document.write('.company-info{margin-bottom:16px;font-size:11px;}');
    printWindow.document.write('.page-number{text-align:center;font-size:10px;margin-top:16px;color:#888;}');
    printWindow.document.write('.font-monospace{font-family:monospace;}');
    printWindow.document.write('.small{font-size:10px;}');
    printWindow.document.write('.sig-line{border-top:1px solid #000;display:inline-block;width:160px;margin-top:32px;padding-top:4px;font-size:11px;}');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');

    // Company header — get from page or use placeholder
    var coName = $('#accountSelect').length > 0 ? 'CÔNG TY TNHH ABC' : '';
    printWindow.document.write('<div class="company-info">'+esc(coName)+'</div>');
    printWindow.document.write('<div class="header-info">');
    printWindow.document.write('<h2>SỔ CÁI</h2>');
    printWindow.document.write('<div class="sub">');
    if (mode === 'monthly') {
        printWindow.document.write('Mẫu số S05-DN<br>(Dùng cho hình thức Nhật ký - Chứng từ)');
    } else {
        printWindow.document.write('(Dùng cho hình thức Nhật ký chung / Nhật ký - Sổ Cái)');
    }
    printWindow.document.write('</div></div>');

    printWindow.document.write('<div id="printHeader"></div>');
    printWindow.document.write('<div id="printTable"></div>');

    printWindow.document.write('<div class="signatures">');
    printWindow.document.write('<div><div class="sig-line">Người ghi sổ<br>(Ký, họ tên)</div></div>');
    printWindow.document.write('<div><div class="sig-line">Kế toán trưởng<br>(Ký, họ tên)</div></div>');
    printWindow.document.write('<div><div class="sig-line">Người đại diện theo pháp luật<br>(Ký, họ tên)</div></div>');
    printWindow.document.write('</div>');

    printWindow.document.write('<div class="page-number">Trang số: 1</div>');

    printWindow.document.write('</body></html>');
    printWindow.document.close();

    // Populate content after page is ready
    var printDoc = printWindow.document;
    var headerHtml = document.getElementById('ledgerHeader').innerHTML;
    var tableHtml = document.getElementById('ledgerTable').outerHTML;
    printDoc.getElementById('printHeader').innerHTML = headerHtml;
    printDoc.getElementById('printTable').innerHTML = tableHtml;

    // Style print-only header
    var printHeaderDiv = printDoc.getElementById('printHeader');
    printHeaderDiv.style.marginBottom = '16px';

    printWindow.print();
}

$(document).ready(function() {
    $('#fromDate').val('<?=date('Y-01-01')?>');
    loadAccounts();
    // Reload when mode changes
    $('input[name="modeToggle"]').on('change', function() {
        loadData();
    });
    setTimeout(loadData, 500);
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>