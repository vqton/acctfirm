<?php // Màn hình: Sổ chi tiết công nợ phải thu khách hàng
$title = 'Sổ chi tiết công nợ phải thu'; $activeMenu = 'ar_statement'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ chi tiết công nợ khách hàng</h5>
    <div><select class="form-select form-select-sm d-inline-block w-auto" id="stmtCust"><option value="">Chọn KH...</option></select>
    <button class="btn btn-outline-primary btn-sm ms-2" onclick="loadData()"><i class="bi bi-search"></i> Xem</button></div>
</div>
<div class="card-table"><table class="table table-hover table-sm">
    <thead><tr><th>Hóa đơn</th><th>Ngày</th><th>Hạn</th><th class="text-end">Tổng</th><th class="text-end">Đã thu</th><th class="text-end">Còn lại</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
$.get('/api/ar/customers', function(l){var o='<option value="">Chọn KH...</option>';l.forEach(function(s){o+='<option value="'+esc(s.id)+'">'+esc(s.name)+'</option>';});$('#stmtCust').html(o);});
function loadData(){var sid=$('#stmtCust').val();if(!sid)return;
    $.get('/api/ar/customers/'+sid+'/statement', function(data){
        var tbody=$('#dataBody');tbody.empty();
        data.forEach(function(r){var badge=r.status==='paid'?'badge-active':(r.status==='prepayment'?'badge-inactive':'badge-warning');
            tbody.append('<tr><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.invoice_date)+'</td><td>'+esc(r.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.gross_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.paid_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td></tr>');
        });
    });
}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
