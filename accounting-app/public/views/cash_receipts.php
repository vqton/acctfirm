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
    <thead><tr><th>Số CT</th><th>Người nộp</th><th>Diễn giải</th><th>Số tiền</th><th>TK Có</th><th>Ngày</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="receiptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="receiptForm">
<div class="modal-header"><h5 class="modal-title">Phiếu thu tiền mặt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2">
        <div class="col-4 mb-2"><label>Ngày</label><input type="date" class="form-control" id="txnDate"></div>
        <div class="col-4 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1" min="1" required></div>
        <div class="col-4 mb-2"><label>TK Có (đối ứng)</label><select class="form-select" id="creditAccount" required></select></div>
    </div>
    <div class="mb-2" id="amountWords" style="font-size:12px;color:#6d7a8a;min-height:20px"></div>
    <div class="mb-2"><label>Người nộp</label>
        <div style="position:relative">
            <input class="form-control" id="payerSearch" placeholder="Gõ tên để tìm khách hàng, NCC, nhân viên..." autocomplete="off">
            <div id="payerResults" style="position:absolute;top:100%;left:0;right:0;z-index:1050;background:#fff;border:1px solid #ddd;max-height:200px;overflow-y:auto;display:none"></div>
        </div>
        <input type="hidden" id="payerId" value="">
        <input type="hidden" id="payerType" value="">
    </div>
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
            var payer=esc(r.payer_name||'');
            var date=esc((r.transaction_date||r.created_at||'').substring(0,10));
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+payer+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.amount?parseFloat(r.amount).toLocaleString():'')+'</td><td>'+esc(r.credit_account||'')+'</td><td style="font-size:12px">'+date+'</td><td><span class="badge-status '+(r.status==='posted'?'badge-active':'badge-warning')+'">'+esc(r.status)+'</span></td></tr>');
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
// Amount in words
$('#amount').on('input',function(){
    var v=parseFloat($(this).val())||0;
    if(v>0){$.get('/api/utils/to-words?amount='+v,function(d){$('#amountWords').text('Bằng chữ: '+d.words);});}else{$('#amountWords').text('');}
});
// Payer search
var searchTimer;
$('#payerSearch').on('input',function(){
    clearTimeout(searchTimer);
    var q=$(this).val().trim();
    if(q.length<1){$('#payerResults').hide();$('#payerId').val('');$('#payerType').val('');return;}
    searchTimer=setTimeout(function(){
        $.get('/api/payers/search?q='+encodeURIComponent(q),function(data){
            if(data.length===0){$('#payerResults').html('<div style="padding:8px;color:#999">Không tìm thấy</div>').show();return;}
            var html='';
            data.forEach(function(p){
                var icon={'customer':'🏢','supplier':'🏭','employee':'👤'}[p.type]||'📄';
                html+='<div class="payer-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee" data-id="'+esc(p.id)+'" data-type="'+esc(p.type)+'" data-name="'+esc(p.name)+'">'+icon+' '+esc(p.name)+' <span style="color:#999;font-size:11px">('+esc(p.type)+')</span></div>';
            });
            $('#payerResults').html(html).show();
        });
    },300);
});
$(document).on('click','.payer-item',function(){
    $('#payerSearch').val($(this).data('name'));
    $('#payerId').val($(this).data('id'));
    $('#payerType').val($(this).data('type'));
    $('#payerResults').hide();
});
$(document).on('click',function(e){if(!$(e.target).closest('#payerSearch,#payerResults').length)$('#payerResults').hide();});
// Submit
$('#receiptForm').submit(function(e){e.preventDefault();
    var data={
        amount: parseFloat($('#amount').val()),
        credit_account_code: $('#creditAccount').val(),
        description: $('#description').val(),
        transaction_date: $('#txnDate').val()||null,
        payer_name: $('#payerSearch').val()||null,
        payer_type: $('#payerType').val()||null,
        payer_id: $('#payerId').val()||null,
    };
    $.ajax({url:'/api/cash/receipts',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify(data),
        success:function(){$('#receiptModal').modal('hide');$('#receiptForm')[0].reset();showToast('Phiếu thu tạo thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadAccounts();loadData();$('#txnDate').val(new Date().toISOString().substring(0,10));});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
