<?php $title = 'Sổ cái'; $activeMenu = 'so_cai'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ cái <span class="stats">(General Ledger)</span></h5>
    <div><button class="btn btn-outline-primary btn-sm" onclick="printLedger()"><i class="bi bi-printer"></i> In sổ</button></div>
</div>
<div class="card p-3 mb-3 border-0 shadow-sm">
    <div class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label small">Tài khoản</label><select class="form-select form-select-sm" id="accountSelect" style="width:250px"></select></div>
        <div class="col-auto"><label class="form-label small">Từ ngày</label><input type="date" class="form-control form-control-sm" id="fromDate"></div>
        <div class="col-auto"><label class="form-label small">Đến ngày</label><input type="date" class="form-control form-control-sm" id="toDate" value="<?=date('Y-m-d')?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" onclick="loadData()"><i class="bi bi-search"></i> Xem</button></div>
    </div>
</div>
<div id="ledgerHeader" class="mb-2"></div>
<div class="card-table"><table class="table table-hover table-sm" id="ledgerTable">
    <thead><tr><th>Ngày</th><th>Số CT</th><th>Diễn giải</th><th>TK ĐƯ</th><th class="text-end">Phát sinh Nợ</th><th class="text-end">Phát sinh Có</th><th class="text-end">Số dư</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
function loadAccounts() {
    $.get('/api/gl/accounts', function(data) {
        data.forEach(function(a) {
            $('#accountSelect').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>');
        });
    });
}

function loadData() {
    var account = $('#accountSelect').val();
    if (!account) return;
    var params = {account: account};
    var f = $('#fromDate').val(); if (f) params.from = f;
    var t = $('#toDate').val(); if (t) params.to = t;

    $.get('/api/gl/ledger', params, function(d) {
        var header = '<div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">';
        header += '<div><strong>'+esc(d.account_code)+' - '+esc(d.account_name)+'</strong> <span class="text-muted small">('+esc(d.account_type)+')</span></div>';
        header += '<div class="text-end"><small class="text-muted">Dư đầu: </small><strong>'+parseFloat(d.opening_balance).toLocaleString()+'</strong>';
        header += ' &nbsp; <small class="text-muted">Dư cuối: </small><strong>'+parseFloat(d.closing_balance).toLocaleString()+'</strong></div>';
        header += '</div>';
        $('#ledgerHeader').html(header);

        var tbody=$('#dataBody'); tbody.empty();
        if (d.entries.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Không có giao dịch trong kỳ</td></tr>');
            return;
        }

        // Opening balance row
        tbody.append('<tr class="table-secondary"><td colspan="4"><strong>Dư đầu kỳ</strong></td><td class="text-end font-monospace"></td><td class="text-end font-monospace"></td><td class="text-end font-monospace fw-bold">'+parseFloat(d.opening_balance).toLocaleString()+'</td></tr>');

        d.entries.forEach(function(r) {
            var dr = r.debit > 0 ? parseFloat(r.debit).toLocaleString() : '';
            var cr = r.credit > 0 ? parseFloat(r.credit).toLocaleString() : '';
            tbody.append('<tr><td>'+esc(r.date)+'</td><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td>'+esc(r.contra_account)+'</td><td class="text-end font-monospace">'+dr+'</td><td class="text-end font-monospace">'+cr+'</td><td class="text-end font-monospace fw-bold">'+parseFloat(r.running_balance).toLocaleString()+'</td></tr>');
        });
    });
}

function printLedger() {
    var account = $('#accountSelect option:selected').text();
    var title = 'SỔ CÁI - ' + account;
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>'+esc(title)+'</title>');
    printWindow.document.write('<style>body{font-family:monospace;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:4px 8px;text-align:left;border-bottom:1px solid #ddd;}th{background:#f0f0f0;}.text-end{text-align:right;}.fw-bold{font-weight:bold;}td:last-child,td:nth-child(5),td:nth-child(6){text-align:right;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2 style="text-align:center;">SỔ CÁI</h2>');
    printWindow.document.write(document.getElementById('ledgerHeader').innerHTML);
    printWindow.document.write(document.getElementById('ledgerTable').outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

$(document).ready(function() {
    $('#fromDate').val('<?=date('Y-m-d', strtotime('-30 days'))?>');
    loadAccounts();
    setTimeout(loadData, 500);
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
