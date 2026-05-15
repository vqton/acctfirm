<?php $title = 'Giấy báo Có'; $activeMenu = 'bank_credit'; ob_start(); ?>
<div class="toolbar">
    <h5>Giấy báo Có ngân hàng <span class="stats">(TK 112)</span></h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#creditModal"><i class="bi bi-plus-lg"></i> Báo Có</button>
    <button class="btn btn-outline-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#interestModal"><i class="bi bi-piggy-bank"></i> Lãi NH</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Số CT</th><th>Loại</th><th>Diễn giải</th><th>Số tiền</th><th>Ngày</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="creditModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="creditForm">
<div class="modal-header"><h5 class="modal-title">Giấy báo Có</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>TK Có (đối ứng)</label><select class="form-select" id="creditAccount" required></select></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Khách hàng chuyển khoản..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<div class="modal fade" id="interestModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="interestForm">
<div class="modal-header"><h5 class="modal-title">Lãi ngân hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Số tiền lãi</label><input type="number" class="form-control" id="intAmount" step="1000" min="1" required></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="intDescription" placeholder="Lãi tài khoản..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-success">Ghi nhận lãi</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.get('/api/bank-transactions',function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var label=r.type==='bank_receipt'?'Thu qua NH':(r.type==='interest'?'Lãi NH':'Khác');
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(label)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.amount?parseFloat(r.amount).toLocaleString():'')+'</td><td style="font-size:12px">'+esc(r.created_at)+'</td><td><span class="badge-status badge-active">'+esc(r.status)+'</span></td></tr>');
        });
    });
}
function loadAccounts(){
    $.get('/api/cash/accounts?for=receipt&_='+Date.now(),function(l){var o='';l.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+'</option>';});$('#creditAccount').html(o);});
}
$('#creditForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank/receipt',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#amount').val()),credit_account_code:$('#creditAccount').val(),description:$('#description').val()}),
        success:function(){$('#creditModal').modal('hide');$('#creditForm')[0].reset();showToast('Ghi nhận báo Có thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#interestForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank/interest',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#intAmount').val()),description:$('#intDescription').val()}),
        success:function(){$('#interestModal').modal('hide');$('#interestForm')[0].reset();showToast('Ghi nhận lãi NH thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadAccounts();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
