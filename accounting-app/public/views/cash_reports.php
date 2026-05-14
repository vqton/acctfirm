<?php $title = 'Báo cáo vốn bằng tiền'; $activeMenu = 'cash_reports'; ob_start(); ?>
<div class="toolbar">
    <h5>Báo cáo vốn bằng tiền</h5>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#position">Tình hình tiền</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#ledger">Sổ ngân hàng</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#flow">Dòng tiền</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#concentration">Phân bổ</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#trend">Xu hướng</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="position">
        <div class="row g-3 mb-3" id="posCards"></div>
        <div class="card-table"><table class="table table-hover"><thead><tr><th>TK</th><th>Tên tài khoản</th><th class="text-end">Số dư</th></tr></thead><tbody id="posTable"></tbody></table></div>
    </div>

    <div class="tab-pane fade" id="ledger">
        <div class="row mb-3 g-2">
            <div class="col-auto"><label class="form-label small">Từ</label><input type="date" class="form-control form-control-sm" id="ledgerFrom"></div>
            <div class="col-auto"><label class="form-label small">Đến</label><input type="date" class="form-control form-control-sm" id="ledgerTo"></div>
            <div class="col-auto"><label class="form-label small">TK</label><select class="form-select form-select-sm" id="ledgerAccount"><option value="112">112 - Tiền gửi</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary mt-4" onclick="loadLedger()">Xem</button></div>
        </div>
        <div class="card-table"><table class="table table-hover table-sm"><thead><tr><th>Ngày</th><th>Diễn giải</th><th>Số CT</th><th>TK đối ứng</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th class="text-end">Số dư</th></tr></thead><tbody id="ledgerBody"></tbody></table></div>
    </div>

    <div class="tab-pane fade" id="flow">
        <div class="row mb-3 g-2">
            <div class="col-auto"><label class="form-label small">Từ</label><input type="date" class="form-control form-control-sm" id="flowFrom" value="<?=date('Y-m-d', strtotime('-30 days'))?>"></div>
            <div class="col-auto"><label class="form-label small">Đến</label><input type="date" class="form-control form-control-sm" id="flowTo" value="<?=date('Y-m-d')?>"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary mt-4" onclick="loadFlow()">Xem</button></div>
        </div>
        <div class="card-table"><table class="table table-hover table-sm"><thead><tr><th>Ngày</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th class="text-end">Chênh lệch</th><th class="text-end">Số GD</th></tr></thead><tbody id="flowBody"></tbody></table></div>
    </div>

    <div class="tab-pane fade" id="concentration">
        <div class="card-table"><table class="table table-hover"><thead><tr><th>TK</th><th>Tên tài khoản</th><th class="text-end">Số dư</th><th class="text-end">Tỷ trọng</th></tr></thead><tbody id="concBody"></tbody></table></div>
    </div>

    <div class="tab-pane fade" id="trend">
        <div class="card p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto"><label class="form-label small">Số ngày</label><input type="number" class="form-control form-control-sm" id="trendDays" value="7" min="1" max="90" style="width:80px"></div>
                <div class="col-auto"><button class="btn btn-sm btn-primary" onclick="loadTrend()">Xem</button></div>
            </div>
        </div>
        <div class="card-table"><table class="table table-hover table-sm"><thead><tr><th>Ngày</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th class="text-end">Chênh lệch</th></tr></thead><tbody id="trendBody"></tbody></table></div>
    </div>
</div>

<script>
function loadPosition() {
    $.get('/api/cash-reports/position', function(p) {
        var cards = '';
        cards += '<div class="col-md-3"><div class="card p-3 text-center border-0 shadow-sm"><small class="text-muted">Tiền mặt (111)</small><strong class="fs-4">'+parseFloat(p.cash_balance).toLocaleString()+'</strong></div></div>';
        cards += '<div class="col-md-3"><div class="card p-3 text-center border-0 shadow-sm"><small class="text-muted">Tiền gửi (112)</small><strong class="fs-4">'+parseFloat(p.bank_balance).toLocaleString()+'</strong></div></div>';
        cards += '<div class="col-md-3"><div class="card p-3 text-center border-0 shadow-sm"><small class="text-muted">Tiền đang chuyển (113)</small><strong class="fs-4">'+parseFloat(p.transit_balance).toLocaleString()+'</strong></div></div>';
        cards += '<div class="col-md-3"><div class="card p-3 text-center border-0 shadow-sm"><small class="text-muted">Tổng cộng</small><strong class="fs-4 text-primary">'+parseFloat(p.total).toLocaleString()+'</strong></div></div>';
        $('#posCards').html(cards);

        var tbody=$('#posTable'); tbody.empty();
        p.bank_accounts.forEach(function(a){
            tbody.append('<tr><td>'+esc(a.code)+'</td><td>'+esc(a.name)+'</td><td class="text-end font-monospace">'+parseFloat(a.balance).toLocaleString()+'</td></tr>');
        });
    });
}

function loadLedger() {
    var params = {};
    var f = $('#ledgerFrom').val(); if(f) params.from = f;
    var t = $('#ledgerTo').val(); if(t) params.to = t;
    params.account = $('#ledgerAccount').val();

    $.get('/api/cash-reports/bank-ledger', params, function(data) {
        var tbody=$('#ledgerBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Không có dữ liệu</td></tr>');return;}
        data.forEach(function(r){
            var receipt = r.type==='receipt' ? parseFloat(r.amount).toLocaleString() : '';
            var payment = r.type==='payment' ? parseFloat(r.amount).toLocaleString() : '';
            tbody.append('<tr><td>'+esc(r.date.substring(0,10))+'</td><td>'+esc(r.description)+'</td><td>'+esc(r.reference)+'</td><td>'+esc(r.account_code)+'</td><td class="text-end font-monospace">'+receipt+'</td><td class="text-end font-monospace">'+payment+'</td><td class="text-end font-monospace fw-bold">'+parseFloat(r.running_balance).toLocaleString()+'</td></tr>');
        });
    });
}

function loadFlow() {
    $.get('/api/cash-reports/daily-flow', {from:$('#flowFrom').val(),to:$('#flowTo').val()}, function(data) {
        var tbody=$('#flowBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="5" class="text-center text-muted py-3">Không có dữ liệu</td></tr>');return;}
        var totalReceipts=0,totalPayments=0;
        data.forEach(function(r){
            var net = parseFloat(r.receipts) - parseFloat(r.payments);
            totalReceipts+=parseFloat(r.receipts);totalPayments+=parseFloat(r.payments);
            tbody.append('<tr><td>'+esc(r.date)+'</td><td class="text-end font-monospace">'+parseFloat(r.receipts).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.payments).toLocaleString()+'</td><td class="text-end font-monospace '+(net>=0?'text-success':'text-danger')+'">'+(net>=0?'+':'')+parseFloat(net).toLocaleString()+'</td><td class="text-end">'+r.transaction_count+'</td></tr>');
        });
        tbody.append('<tr class="fw-bold"><td>Tổng</td><td class="text-end font-monospace">'+parseFloat(totalReceipts).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(totalPayments).toLocaleString()+'</td><td class="text-end font-monospace '+(totalReceipts-totalPayments>=0?'text-success':'text-danger')+'">'+(totalReceipts-totalPayments>=0?'+':'')+parseFloat(totalReceipts-totalPayments).toLocaleString()+'</td><td></td></tr>');
    });
}

function loadConcentration() {
    $.get('/api/cash-reports/concentration', function(d) {
        var tbody=$('#concBody'); tbody.empty();
        d.accounts.forEach(function(a){
            tbody.append('<tr><td>'+esc(a.code)+'</td><td>'+esc(a.name)+'</td><td class="text-end font-monospace">'+parseFloat(a.balance).toLocaleString()+'</td><td class="text-end">'+a.pct+'%</td></tr>');
        });
        tbody.append('<tr class="fw-bold"><td colspan="2">Tổng</td><td class="text-end font-monospace">'+parseFloat(d.total).toLocaleString()+'</td><td class="text-end">100%</td></tr>');
    });
}

function loadTrend() {
    var days = $('#trendDays').val() || 7;
    $.get('/api/cash-reports/trend', {days:days}, function(data) {
        var tbody=$('#trendBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="4" class="text-center text-muted py-3">Không có dữ liệu</td></tr>');return;}
        data.forEach(function(r){
            var net = parseFloat(r.receipts) - parseFloat(r.payments);
            tbody.append('<tr><td>'+esc(r.date)+'</td><td class="text-end font-monospace">'+parseFloat(r.receipts).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.payments).toLocaleString()+'</td><td class="text-end font-monospace '+(net>=0?'text-success':'text-danger')+'">'+(net>=0?'+':'')+parseFloat(net).toLocaleString()+'</td></tr>');
        });
    });
}

$(document).ready(function(){
    $('#ledgerFrom').val('<?=date('Y-m-d', strtotime('-30 days'))?>');
    $('#ledgerTo').val('<?=date('Y-m-d')?>');
    loadPosition();
    loadLedger();
    loadFlow();
    loadConcentration();
    loadTrend();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
