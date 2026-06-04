<?php // Màn hình: Ghi nhận giấy báo Nợ từ ngân hàng (TK 112)
// API: GET /api/bank-transactions, GET /api/cash/templates?type=payment, GET /api/cash/accounts?for=payment, POST /api/bank/payment, POST /api/bank/charge
// Nghiệp vụ: Chi tiền qua ngân hàng — Nợ TK đối ứng/Có 1121; hoặc ghi nhận phí NH — Nợ 635/Có 1121
// Rủi ro: Phí ngân hàng nếu hạch toán sai sẽ ảnh hưởng đến chi phí tài chính (TK 635)
$title = 'Giấy báo Nợ'; $activeMenu = 'bank_debit'; ob_start(); ?>
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
    <div class="row g-2"><div class="col-4 mb-2"><label>Loại chi</label><select class="form-select" id="paymentType"><option value="">-- Chọn loại --</option></select></div><div class="col-4 mb-2"><label>TK Nợ (đối ứng)</label><select class="form-select" id="debitAccount" required></select></div><div class="col-4 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1" min="1" required></div></div>
    <div class="row g-2"><div class="col-2 mb-2" id="vatRateGroup" style="display:none"><label>VAT %</label><select class="form-select" id="vatRate"></select></div><div class="col-2 mb-2" id="vatAmountGroup" style="display:none"><label>Tiền VAT</label><input type="number" class="form-control" id="vatAmount" readonly step="1" style="background:#f5f5f5"></div><div class="col-4 mb-2" id="netAmountGroup" style="display:none"><label>Tiền chưa thuế</label><input type="number" class="form-control" id="netAmount" readonly step="1" style="background:#f5f5f5"></div></div>
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
function loadTemplates(){
    $.get('/api/cash/templates?type=payment&_='+Date.now(),function(tpls){
        var sel=$('#paymentType');sel.html('<option value="">-- Chọn loại --</option>');
        tpls.forEach(function(t){sel.append('<option value="'+esc(t.id)+'" data-account="'+esc(t.default_account)+'" data-vat="'+t.has_vat+'" data-vat-rate="'+t.vat_rate+'">'+esc(t.name)+'</option>');});
    });
    $.get('/api/cash/accounts?for=payment&_='+Date.now(),function(l){var o='';l.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+'</option>';});$('#debitAccount').html(o);});
}
$('#paymentType').on('change',function(){
    var opt=$(this).find(':selected');
    var hasVat=opt.data('vat')===true;
    $('#vatRateGroup,#vatAmountGroup,#netAmountGroup').toggle(hasVat);
    if(opt.data('account')){$('#debitAccount').val(opt.data('account'));}
    if(hasVat){$('#vatRate').val(opt.data('vat-rate')||10);calcVAT();}
});
function calcVAT(){
    var total=parseFloat($('#amount').val())||0;
    var rate=parseInt($('#vatRate').val())||0;
    if(rate>0){var vat=Math.round(total*rate/(100+rate));$('#vatAmount').val(vat);$('#netAmount').val(total-vat);}
    else{$('#vatAmount').val(0);$('#netAmount').val(total);}
}
$('#amount,#vatRate').on('input',function(){if($('#vatRateGroup').is(':visible'))calcVAT();});
// Submit ghi nhận báo Nợ — POST /api/bank/payment
// Nghiệp vụ: Nợ debit_account_code/Có 1121 (ví dụ thanh toán NCC: Nợ 331/Có 1121)
// Nếu có VAT: Nợ 1331 (thuế GTGT đầu vào)
$('#debitForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank/payment',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#amount').val()),debit_account_code:$('#debitAccount').val(),description:$('#description').val(),vat_amount:$('#vatRateGroup').is(':visible')?parseInt($('#vatAmount').val())||0:0,vat_rate:$('#vatRateGroup').is(':visible')?parseInt($('#vatRate').val())||0:0,}),
        success:function(){$('#debitModal').modal('hide');$('#debitForm')[0].reset();showToast('Đã ghi nhận giấy báo Nợ thành công.','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
// Submit ghi nhận phí ngân hàng — POST /api/bank/charge
// Nghiệp vụ: Nợ 635 (Chi phí tài chính)/Có 1121 (tiền gửi ngân hàng)
$('#chargeForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank/charge',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#chgAmount').val()),description:$('#chgDescription').val()}),
        success:function(){$('#chargeModal').modal('hide');$('#chargeForm')[0].reset();showToast('Đã ghi nhận phí ngân hàng thành công.','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadTemplates();loadData();loadVatRates('#vatRate',10);});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
