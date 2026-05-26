<?php // Màn hình: Báo cáo tổng hợp vốn bằng tiền
// API: GET /api/cash-reports/position, GET /api/cash-reports/concentration
// Nghiệp vụ: Tổng hợp số dư các TK vốn bằng tiền: 111 (tiền mặt) + 112 (tiền gửi) + 113 (tiền đang chuyển)
// Mục đích: Quản lý dòng tiền — hiển thị tổng vốn bằng tiền và chi tiết theo từng tài khoản
$title = 'Báo cáo vốn bằng tiền'; $activeMenu = 'cash_reports'; ob_start(); ?>
<div class="toolbar"><h5>Báo cáo vốn bằng tiền</h5></div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h6 class="text-muted mb-1">Tiền mặt (TK 111)</h6><h4 class="mb-0 font-monospace" id="cashBalance">...</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h6 class="text-muted mb-1">Tiền gửi (TK 112)</h6><h4 class="mb-0 font-monospace" id="bankBalance">...</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h6 class="text-muted mb-1">Đang chuyển (TK 113)</h6><h4 class="mb-0 font-monospace" id="transitBalance">...</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h6 class="text-muted mb-1">Tổng vốn bằng tiền</h6><h4 class="mb-0 font-monospace text-primary" id="totalBalance">...</h4></div></div></div>
</div>

<div class="card-table mt-4"><table class="table table-hover">
    <thead><tr><th>TK</th><th>Tên tài khoản</th><th class="text-end">Số dư Nợ</th><th class="text-end">Số dư Có</th></tr></thead>
    <tbody id="reportBody"></tbody>
</table></div>

<script>
function loadData(){
    $.get('/api/cash-reports/position',function(data){
        if(data.cash_balance!==undefined){$('#cashBalance').text(parseFloat(data.cash_balance).toLocaleString()+' VND');}
        if(data.bank_balance!==undefined){$('#bankBalance').text(parseFloat(data.bank_balance).toLocaleString()+' VND');}
        if(data.transit_balance!==undefined){$('#transitBalance').text(parseFloat(data.transit_balance).toLocaleString()+' VND');}
        var t=0;['cash_balance','bank_balance','transit_balance'].forEach(function(k){if(data[k]!==undefined)t+=parseFloat(data[k]);});
        $('#totalBalance').text(t.toLocaleString()+' VND');
    });
    $.get('/api/cash-reports/concentration',function(data){
        var tbody=$('#reportBody'); tbody.empty();
        if(data&&data.length){data.forEach(function(r){tbody.append('<tr><td>'+esc(r.account_code)+'</td><td>'+esc(r.account_name)+'</td><td class="text-end font-monospace">'+parseFloat(r.debit||0).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.credit||0).toLocaleString()+'</td></tr>');});}
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
