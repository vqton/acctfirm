<?php header('Cache-Control: no-cache, no-store, must-revalidate'); header('Pragma: no-cache'); header('Expires: 0');
$title = 'Phiếu thu'; $activeMenu = 'cash_receipts'; ob_start(); ?>
<div class="toolbar">
    <h5>Phiếu thu tiền mặt <span class="stats">(TK 111)</span></h5>
    <div>
        <span id="loadStatus" class="badge bg-secondary">Đang tải...</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#receiptModal"><i class="bi bi-plus-lg"></i> Phiếu thu</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Số CT</th><th>Diễn giải</th><th>Số tiền</th><th>TK Có</th><th>Ngày</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="receiptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="receiptForm">
<div class="modal-header"><h5 class="modal-title">Phiếu thu tiền mặt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>TK Có (đối ứng)</label><select class="form-select" id="creditAccount" required></select></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Thu tiền..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.ajax({url:'/api/cash/receipts',headers:{'X-CSRF-Token':csrf},success:function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.amount?parseFloat(r.amount).toLocaleString():'')+'</td><td>'+esc(r.credit_account||'')+'</td><td style="font-size:12px">'+esc(r.created_at)+'</td><td><span class="badge-status '+(r.status==='posted'?'badge-active':'badge-warning')+'">'+esc(r.status)+'</span></td></tr>');
        });
    }});
}
function loadAccounts(){
    console.log('[Cash] loadAccounts START', new Date().toISOString());
    var timedOut=false;var timer=setTimeout(function(){console.log('[Cash] TIMEOUT fired');timedOut=true;$('#loadStatus').text('TIMEOUT - Kiểm tra server').css('color','red');$('#creditAccount').html('<option>Không phản hồi (>5s)</option>');},5000);
    $('#creditAccount').html('<option>Đang tải...</option>');
    $.get('/api/cash/accounts?for=receipt&_='+Date.now(),function(l){
        if(timedOut)return;clearTimeout(timer);
        var o='<option value="">-- Chọn tài khoản --</option>';
        l.forEach(function(a){
            o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>';
        });
        $('#creditAccount').html(o);
        $('#loadStatus').text('OK: '+l.length+' tài khoản').css('color','');
    }).fail(function(x){
        if(timedOut)return;clearTimeout(timer);
        $('#creditAccount').html('<option>Lỗi tải: '+x.status+'</option>');
        $('#loadStatus').text('LỖI: '+x.status).css('color','red');
    });
}
$('#receiptForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/cash/receipts',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#amount').val()),credit_account_code:$('#creditAccount').val(),description:$('#description').val()}),
        success:function(){$('#receiptModal').modal('hide');$('#receiptForm')[0].reset();showToast('Phiếu thu tạo thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadAccounts();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
