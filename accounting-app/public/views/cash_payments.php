<?php $title = 'Phiếu chi'; $activeMenu = 'cash_payments'; ob_start(); ?>
<div class="toolbar">
    <h5>Phiếu chi tiền mặt <span class="stats">(TK 111)</span></h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="bi bi-plus-lg"></i> Phiếu chi</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Số CT</th><th>Diễn giải</th><th>Số tiền</th><th>TK Nợ</th><th>Ngày</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="paymentForm">
<div class="modal-header"><h5 class="modal-title">Phiếu chi tiền mặt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>TK Nợ (đối ứng)</label><select class="form-select" id="debitAccount" required></select></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Chi tiền..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.ajax({url:'/api/cash/payments',headers:{'X-CSRF-Token':csrf},success:function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.amount?parseFloat(r.amount).toLocaleString():'')+'</td><td>'+esc(r.debit_account||'')+'</td><td style="font-size:12px">'+esc(r.created_at)+'</td><td><span class="badge-status '+(r.status==='posted'?'badge-active':'badge-warning')+'">'+esc(r.status)+'</span></td></tr>');
        });
    }});
}
function loadAccounts(){
    $.get('/api/cash/accounts?for=payment&_='+Date.now(),function(l){var o='';l.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>';});$('#debitAccount').html(o);});
}
$('#paymentForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/cash/payments',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#amount').val()),debit_account_code:$('#debitAccount').val(),description:$('#description').val()}),
        success:function(){$('#paymentModal').modal('hide');$('#paymentForm')[0].reset();showToast('Phiếu chi tạo thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadAccounts();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
