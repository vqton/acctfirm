<?php $title = 'Sổ quỹ tiền mặt'; $activeMenu = 'cash_book'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ quỹ tiền mặt <span class="stats">(TK 111)</span></h5>
    <div><input type="date" class="form-control form-control-sm d-inline-block" style="width:160px" id="fromDate" value="<?=date('Y-m-01')?>"><span class="mx-1">→</span><input type="date" class="form-control form-control-sm d-inline-block" style="width:160px" id="toDate" value="<?=date('Y-m-d')?>">
    <button class="btn btn-sm btn-outline-primary ms-1" onclick="loadData()"><i class="bi bi-search"></i></button></div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Ngày</th><th>Số CT</th><th>Diễn giải</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th class="text-end">Tồn quỹ</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table>
<div class="pagination-bar" id="summaryRow"><span id="summaryText">Đang tải...</span></div>
</div>

<script>
function loadData(){
    var p={from_date:$('#fromDate').val(),to_date:$('#toDate').val()};
    $.get('/api/cash-book',p,function(data){
        var tbody=$('#dataBody'); tbody.empty();
        if(!data.entries||data.entries.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted">Không có dữ liệu</td></tr>');return;}
        data.entries.forEach(function(r){
            var cls=r.type==='opening_balance'?'fw-bold':(r.type==='closing_balance'?'fw-bold border-top':'');
            tbody.append('<tr class="'+cls+'"><td style="font-size:12px">'+esc(r.date)+'</td><td>'+esc(r.reference||'')+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.debit?parseFloat(r.debit).toLocaleString():'-')+'</td><td class="text-end font-monospace">'+(r.credit?parseFloat(r.credit).toLocaleString():'-')+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td></tr>');
        });
        if(data.summary) $('#summaryText').text('Tồn đầu: '+parseFloat(data.summary.opening||0).toLocaleString()+' VND | Thu: '+parseFloat(data.summary.total_debit||0).toLocaleString()+' | Chi: '+parseFloat(data.summary.total_credit||0).toLocaleString()+' | Tồn cuối: '+parseFloat(data.summary.closing||0).toLocaleString()+' VND');
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
