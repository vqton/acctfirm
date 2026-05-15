<?php $title = 'Giấy báo Nợ'; $activeMenu = 'bank_debit'; ob_start(); ?>
<div class="toolbar">
    <h5>Giấy báo Nợ ngân hàng <span class="stats">(TK 112)</span></h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#debitModal"><i class="bi bi-plus-lg"></i> Báo Nợ</button>
    <button class="btn btn-outline-danger btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#chargeModal"><i class="bi bi-currency-dollar"></i> Phí NH</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Số CT</th><th>Loại</th><th>Diễn giải</th><th>Số tiền</th><th>Ngày</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="debitModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="debitForm">
<div class="modal-header"><h5 class="modal-title">Giấy báo Nợ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>TK Nợ (đối ứng)</label><select class="form-select" id="debitAccount" required></select></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Thanh toán qua NH..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<div class="modal fade" id="chargeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="chargeForm">
<div class="modal-header"><h5 class="modal-title">Phí ngân hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Số tiền phí</label><input type="number" class="form-control" id="chgAmount" step="1000" min="1" required></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="chgDescription" placeholder="Phí dịch vụ NH..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-danger">Ghi nhận phí</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.get('/api/bank-transactions',function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var label=r.type==='bank_payment'?'Chi qua NH':(r.type==='charge'?'Phí NH':'Khác');
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(label)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.amount?parseFloat(r.amount).toLocaleString():'')+'</td><td style="font-size:12px">'+esc(r.created_at)+'</td><td><span class="badge-status badge-active">'+esc(r.status)+'</span></td></tr>');
        });
    });
}
function loadAccounts(){
    $.get('/api/cash/accounts?for=payment&_='+Date.now(),function(l){var o='';l.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+'</option>';});$('#debitAccount').html(o);});
}
$('#debitForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank/payment',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#amount').val()),debit_account_code:$('#debitAccount').val(),description:$('#description').val()}),
        success:function(){$('#debitModal').modal('hide');$('#debitForm')[0].reset();showToast('Ghi nhận báo Nợ thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#chargeForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank/charge',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#chgAmount').val()),description:$('#chgDescription').val()}),
        success:function(){$('#chargeModal').modal('hide');$('#chargeForm')[0].reset();showToast('Ghi nhận phí NH thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadAccounts();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
