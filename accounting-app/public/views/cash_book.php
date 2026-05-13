<?php $title = 'Sổ quỹ tiền mặt'; $activeMenu = 'cash_book'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ quỹ tiền mặt <span class="stats">(TK 111)</span></h5>
    <div>
        <button class="btn btn-outline-primary btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover table-sm">
        <thead><tr><th>Ngày</th><th>Số CT</th><th>Diễn giải</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th class="text-end">Tồn quỹ</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<script>
function loadData() {
    $.get('/api/cash-book', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted py-4">Chưa có giao dịch</td></tr>');return;}
        data.forEach(function(r){
            tbody.append('<tr><td>'+esc(r.date.substring(0,10))+'</td><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.receipt_amount?parseFloat(r.receipt_amount).toLocaleString():'')+'</td><td class="text-end font-monospace">'+(r.payment_amount?parseFloat(r.payment_amount).toLocaleString():'')+'</td><td class="text-end font-monospace fw-bold">'+parseFloat(r.balance).toLocaleString()+'</td></tr>');
        });
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
