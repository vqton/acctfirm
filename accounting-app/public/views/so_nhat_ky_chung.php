<?php // Màn hình: Sổ nhật ký chung (S03a-DN)
// API: GET /api/general-journal?from=&to=
// Nghiệp vụ: Sổ NKC — ghi chép tất cả bút toán theo thứ tự thời gian, mỗi bút toán ghi 2 dòng
// Tuân thủ: Mẫu S03a-DN theo Thông tư 200 — nguyên tắc: Tổng PS Nợ = Tổng PS Có
// Rủi ro: Nếu có bút toán lẻ (single entry) sẽ break nguyên tắc double-entry
$title = 'Sổ Nhật ký chung'; $activeMenu = 'general_journal'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ Nhật ký chung <span class="stats">(General Journal — Mẫu S03a-DN)</span></h5>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" onclick="printLedger()"><i class="bi bi-printer"></i> In sổ</button>
    </div>
</div>
<div class="card p-3 mb-3 border-0 shadow-sm">
    <div class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label small">Từ ngày</label><input type="date" class="form-control form-control-sm" id="fromDate"></div>
        <div class="col-auto"><label class="form-label small">Đến ngày</label><input type="date" class="form-control form-control-sm" id="toDate" value="<?=date('Y-m-d')?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" onclick="loadData()"><i class="bi bi-search"></i> Xem</button></div>
    </div>
</div>
<div id="journalHeader" class="mb-2"></div>
<div class="card-table"><table class="table table-hover table-sm" id="journalTable">
    <thead id="tableHead"></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
function loadData() {
    var params = {};
    var f = $('#fromDate').val(); if (f) params.from = f;
    var t = $('#toDate').val(); if (t) params.to = t;

    $.get('/api/general-journal', params, function(d) {
        renderJournal(d);
    }).fail(function(xhr) {
        var msg = 'Lỗi tải dữ liệu';
        try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
        $('#journalHeader').html('<div class="alert alert-danger py-2 mb-2">' + esc(msg) + '</div>');
        $('#tableHead').html('<tr><th>Ngày</th><th>Số CT</th><th>Diễn giải</th><th>TK</th><th>TK ĐƯ</th><th class="text-end">Số tiền Nợ</th><th class="text-end">Số tiền Có</th></tr>');
    });
}

function renderJournal(d) {
    var header = '<div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">';
    header += '<div><strong>SỔ NHẬT KÝ CHUNG</strong> <span class="text-muted small">(Mẫu S03a-DN)</span></div>';
    header += '<div class="text-end"><small class="text-muted">Tổng PS Nợ: </small><strong>' + parseFloat(d.total_debit).toLocaleString() + '</strong>';
    header += ' &nbsp; <small class="text-muted">Tổng PS Có: </small><strong>' + parseFloat(d.total_credit).toLocaleString() + '</strong></div>';
    header += '</div>';
    $('#journalHeader').html(header);

    $('#tableHead').html('<tr><th>Ngày</th><th>Số CT</th><th>Diễn giải</th><th>TK</th><th>TK ĐƯ</th><th class="text-end">Số tiền Nợ</th><th class="text-end">Số tiền Có</th></tr>');
    var tbody=$('#dataBody'); tbody.empty();
    if (d.entries.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Không có giao dịch trong kỳ</td></tr>');
        return;
    }
    d.entries.forEach(function(r) {
        var dr = r.debit > 0 ? parseFloat(r.debit).toLocaleString() : '';
        var cr = r.credit > 0 ? parseFloat(r.credit).toLocaleString() : '';
        var descClass = r.description ? '' : ' text-muted';
        tbody.append('<tr><td class="text-nowrap">'+esc(r.date)+'</td>'
            +'<td class="text-nowrap">'+esc(r.reference)+'</td>'
            +'<td'+descClass+'>'+esc(r.description || '↳ '+esc(r.account_code)+' - '+esc(r.account_name))+'</td>'
            +'<td>'+esc(r.account_code)+'</td>'
            +'<td>'+esc(r.contra_account)+'</td>'
            +'<td class="text-end font-monospace">'+dr+'</td>'
            +'<td class="text-end font-monospace">'+cr+'</td></tr>');
    });
}

function printLedger() {
    var today = new Date();
    var dateStr = 'Ngày ' + today.getDate() + ' tháng ' + (today.getMonth()+1) + ' năm ' + today.getFullYear();

    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Sổ Nhật ký chung</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body{font-family:"Times New Roman",serif;font-size:12px;padding:40px;}');
    printWindow.document.write('table{width:100%;border-collapse:collapse;}');
    printWindow.document.write('th,td{padding:5px 6px;text-align:left;border:1px solid #000;vertical-align:top;}');
    printWindow.document.write('th{background:#f0f0f0;text-align:center;font-size:11px;}');
    printWindow.document.write('.text-end{text-align:right;}');
    printWindow.document.write('.fw-bold{font-weight:bold;}');
    printWindow.document.write('.text-center{text-align:center;}');
    printWindow.document.write('.signatures{display:flex;justify-content:space-between;margin-top:40px;}');
    printWindow.document.write('.signatures>div{text-align:center;width:30%;}');
    printWindow.document.write('.header-info{text-align:center;margin-bottom:20px;}');
    printWindow.document.write('.header-info h2{margin:0;font-size:16px;text-transform:uppercase;letter-spacing:1px;}');
    printWindow.document.write('.header-info .sub{margin:4px 0 0;font-size:11px;color:#555;}');
    printWindow.document.write('.company-info{margin-bottom:12px;font-size:11px;}');
    printWindow.document.write('.font-monospace{font-family:monospace;}');
    printWindow.document.write('.sig-line{border-top:1px solid #000;display:inline-block;width:160px;margin-top:32px;padding-top:4px;font-size:11px;}');
    printWindow.document.write('.text-nowrap{white-space:nowrap;}');
    printWindow.document.write('.totals-row td{border-top:2px solid #000;font-weight:bold;}');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');

    printWindow.document.write('<div class="header-info">');
    printWindow.document.write('<h2>SỔ NHẬT KÝ CHUNG</h2>');
    printWindow.document.write('<div class="sub">Mẫu số S03a-DN<br>(Dùng cho hình thức Nhật ký chung)</div>');
    printWindow.document.write('</div>');

    printWindow.document.write('<div id="printTable"></div>');

    printWindow.document.write('<div class="signatures">');
    printWindow.document.write('<div><div class="sig-line">Người ghi sổ<br>(Ký, họ tên)</div></div>');
    printWindow.document.write('<div><div class="sig-line">Kế toán trưởng<br>(Ký, họ tên)</div></div>');
    printWindow.document.write('<div><div class="sig-line">Người đại diện theo pháp luật<br>(Ký, họ tên)</div></div>');
    printWindow.document.write('</div>');

    printWindow.document.write('</body></html>');
    printWindow.document.close();

    var printDoc = printWindow.document;
    var tableHtml = document.getElementById('journalTable').outerHTML;
    printDoc.getElementById('printTable').innerHTML = tableHtml;
    printWindow.print();
}

$(document).ready(function() {
    $('#fromDate').val('<?=date('Y-01-01')?>');
    setTimeout(loadData, 300);
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
