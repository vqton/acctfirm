<?php $title = 'Giấy báo Nợ'; $activeMenu = 'bank_debit'; ob_start(); ?>
<div class="toolbar">
    <h5>Giấy báo Nợ <span class="stats">(TK 112)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#debitModal"><i class="bi bi-plus-lg"></i> Tạo báo Nợ</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Số CT</th><th>Diễn giải</th><th>Số tiền</th><th>Loại</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<div class="modal fade" id="debitModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="debitForm">
<div class="modal-header"><h5 class="modal-title">Giấy báo Nợ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Loại giao dịch</label>
        <select class="form-select" id="txType">
            <option value="payment">Thanh toán nhà cung cấp</option>
            <option value="withdrawal">Rút tiền từ tài khoản</option>
            <option value="charge">Phí ngân hàng</option>
        </select>
    </div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="mb-3" id="accountField"><label>Ghi Nợ TK</label><select class="form-select" id="accountCode"></select></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="description"></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="reference"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-danger">Ghi nhận</button>
</div>
</form>
</div></div></div>
<script>
function loadData() {
    $.get('/api/bank-transactions', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted py-4">Chưa có giao dịch</td></tr>');return;}
        var debitTypes=['Bank payment:','Bank withdrawal:','Bank charge:'];
        data.filter(function(r){return debitTypes.some(function(p){return r.description.indexOf(p)===0;});}).forEach(function(r){
            var type=r.description.split(':')[0];
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+parseFloat(r.credit_total||0).toLocaleString()+'</td><td>'+esc(type)+'</td><td><span class="badge-status badge-active">'+esc(r.status)+'</span></td><td>'+esc(r.created_at)+'</td></tr>');
        });
    });
}
$('#txType').change(function(){
    var v=$(this).val();
    if(v==='charge'){$('#accountField').hide();}else{$('#accountField').show();}
});
$.get('/api/cash/accounts', function(accounts) {
    accounts.forEach(function(a){if(a.code!=='111'&&a.code!=='112')$('#accountCode').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+'</option>');});
});
$('#debitForm').submit(function(e){e.preventDefault();
    var type=$('#txType').val();
    var url,payload={amount:parseFloat($('#amount').val()),description:$('#description').val(),reference:$('#reference').val()||undefined};
    if(type==='payment'){url='/api/bank/payment';payload.debit_account_code=$('#accountCode').val();}
    else if(type==='withdrawal'){url='/api/bank/withdrawal';}
    else{url='/api/bank/charge';}
    $.ajax({url:url,method:'POST',contentType:'application/json',data:JSON.stringify(payload),success:function(){$('#debitModal').modal('hide');$('#debitForm')[0].reset();showToast('Tạo báo Nợ thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
