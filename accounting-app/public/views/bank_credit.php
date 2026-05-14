<?php $title = 'Giấy báo Có'; $activeMenu = 'bank_credit'; ob_start(); ?>
<div class="toolbar">
    <h5>Giấy báo Có <span class="stats">(TK 112)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#creditModal"><i class="bi bi-plus-lg"></i> Tạo báo Có</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Số CT</th><th>Diễn giải</th><th>Số tiền</th><th>Loại</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<div class="modal fade" id="creditModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="creditForm">
<div class="modal-header"><h5 class="modal-title">Giấy báo Có</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Loại giao dịch</label>
        <select class="form-select" id="txType">
            <option value="receipt">Khách hàng thanh toán</option>
            <option value="deposit">Nộp tiền vào tài khoản</option>
            <option value="interest">Lãi ngân hàng</option>
        </select>
    </div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="mb-3" id="accountField"><label>Ghi Có TK</label><select class="form-select" id="accountCode"></select></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="description"></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="reference"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button>
</div>
</form>
</div></div></div>
<script>
function loadData() {
    $.get('/api/bank-transactions', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted py-4">Chưa có giao dịch</td></tr>');return;}
        var creditTypes=['Bank deposit:','Bank receipt:','Bank interest:'];
        data.filter(function(r){return creditTypes.some(function(p){return r.description.indexOf(p)===0;});}).forEach(function(r){
            var type=r.description.split(':')[0];
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+parseFloat(r.debit_total||0).toLocaleString()+'</td><td>'+esc(type)+'</td><td><span class="badge-status badge-active">'+esc(r.status)+'</span></td><td>'+esc(r.created_at)+'</td></tr>');
        });
    });
}
$('#txType').change(function(){
    var v=$(this).val();
    if(v==='interest'){$('#accountField').hide();}else{$('#accountField').show();}
});
$.get('/api/cash/accounts?for=receipt', function(accounts) {
    accounts.forEach(function(a){if(a.code!=='111'&&a.code!=='112')$('#accountCode').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>');});
});
$('#creditForm').submit(function(e){e.preventDefault();
    var type=$('#txType').val();
    var url,payload={amount:parseFloat($('#amount').val()),description:$('#description').val(),reference:$('#reference').val()||undefined};
    if(type==='receipt'){url='/api/bank/receipt';payload.credit_account_code=$('#accountCode').val();}
    else if(type==='deposit'){url='/api/bank/deposit';}
    else{url='/api/bank/interest';}
    $.ajax({url:url,method:'POST',contentType:'application/json',data:JSON.stringify(payload),success:function(){$('#creditModal').modal('hide');$('#creditForm')[0].reset();showToast('Tạo báo Có thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
