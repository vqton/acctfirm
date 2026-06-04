<?php // Màn hình: Lập và quản lý phiếu chi tiền mặt
// API: GET /api/cash/payments, GET /api/cash/templates?type=payment, GET /api/cash/accounts?for=payment, POST /api/cash/payments, GET /api/payers/search, GET /api/utils/to-words
// Nghiệp vụ: Chi tiền mặt — Nợ TK đối ứng (131, 331, 641, 642...)/Có 1111
// Tuân thủ: Chỉ post vào TK con 1111; kiểm tra số dư quỹ trước khi chi (nếu có)
// Rủi ro: Chi vượt quá số dư tiền mặt sẽ dẫn đến số dư âm — backend cần kiểm tra
header('Cache-Control: no-cache, no-store, must-revalidate'); header('Pragma: no-cache'); header('Expires: 0');
$title = 'Phiếu chi'; $activeMenu = 'cash_payments'; ob_start(); ?>
<div class="toolbar">
    <h5>Phiếu chi tiền mặt <span class="stats">(TK 111)</span></h5>
    <div>
        <span id="loadStatus" class="badge bg-secondary">Đang tải...</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="bi bi-plus-lg"></i> Phiếu chi</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Số CT</th><th>Người nhận</th><th>Diễn giải</th><th>Số tiền</th><th>TK Nợ</th><th>Ngày</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="paymentForm">
<div class="modal-header"><h5 class="modal-title">Phiếu chi tiền mặt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2">
        <div class="col-4 mb-2"><label>Ngày</label><input type="date" class="form-control" id="txnDate"></div>
        <div class="col-4 mb-2"><label>Loại chi</label><select class="form-select" id="paymentType"><option value="">-- Chọn loại --</option></select></div>
        <div class="col-4 mb-2"><label>TK Nợ (đối ứng)</label><select class="form-select" id="debitAccount" required></select></div>
    </div>
    <div class="row g-2">
        <div class="col-4 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1" min="1" required></div>
        <div class="col-2 mb-2" id="vatRateGroup" style="display:none"><label>VAT %</label><select class="form-select" id="vatRate"></select></div>
        <div class="col-2 mb-2" id="vatAmountGroup" style="display:none"><label>Tiền VAT</label><input type="number" class="form-control" id="vatAmount" readonly step="1" style="background:#f5f5f5"></div>
        <div class="col-4 mb-2" id="netAmountGroup" style="display:none"><label>Tiền chưa thuế</label><input type="number" class="form-control" id="netAmount" readonly step="1" style="background:#f5f5f5"></div>
    </div>
    <div class="mb-2" id="amountWords" style="font-size:12px;color:#6d7a8a;min-height:20px"></div>
    <div class="mb-2"><label>Người nhận</label>
        <div style="position:relative">
            <input class="form-control" id="payerSearch" placeholder="Gõ tên để tìm khách hàng, NCC, nhân viên..." autocomplete="off">
            <div id="payerResults" style="position:absolute;top:100%;left:0;right:0;z-index:1050;background:#fff;border:1px solid #ddd;max-height:200px;overflow-y:auto;display:none"></div>
        </div>
        <input type="hidden" id="payerId" value="">
        <input type="hidden" id="payerType" value="">
    </div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Chi tiền..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<script>
// Tải danh sách phiếu chi — GET /api/cash/payments
// Hiển thị: số CT, người nhận, diễn giải, số tiền, TK Nợ, ngày, trạng thái
function loadData(){
    $.ajax({url:'/api/cash/payments',headers:{'X-CSRF-Token':csrf},success:function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var payer=esc(r.payer_name||'');
            var date=esc((r.transaction_date||r.created_at||'').substring(0,10));
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+payer+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+(r.amount?parseFloat(r.amount).toLocaleString():'')+'</td><td>'+esc(r.debit_account||'')+'</td><td style="font-size:12px">'+date+'</td><td><span class="badge-status '+(r.status==='posted'?'badge-active':'badge-warning')+'">'+esc(r.status)+'</span></td></tr>');
        });
    }});
}
function loadTemplates(){
    $.get('/api/cash/templates?type=payment&_='+Date.now(),function(tpls){
        var sel=$('#paymentType');sel.html('<option value="">-- Chọn loại chi --</option>');
        tpls.forEach(function(t){sel.append('<option value="'+esc(t.id)+'" data-account="'+esc(t.default_account)+'" data-vat="'+t.has_vat+'" data-vat-rate="'+t.vat_rate+'">'+esc(t.name)+'</option>');});
    });
    $.get('/api/cash/accounts?for=payment&_='+Date.now(),function(l){
        var o='<option value="">-- Chọn tài khoản --</option>';
        l.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>';});
        $('#debitAccount').html(o);
        $('#loadStatus').text('OK: '+l.length+' tài khoản').css('color','');
    }).fail(function(x){$('#debitAccount').html('<option>Lỗi: '+x.status+'</option>');$('#loadStatus').text('LỖI: '+x.status).css('color','red');});
}
// Khi chọn loại chi — tự động điền TK Nợ mặc định và hiển thị VAT nếu có
// Nghiệp vụ: Nếu loại chi có VAT (ví dụ mua hàng), phân tách tiền hàng và VAT đầu vào
// Hạch toán: Nợ 156 (tiền hàng) + Nợ 1331 (VAT)/Có 1111 (tổng)
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
// Submit tạo phiếu chi — POST /api/cash/payments
// Nghiệp vụ: Nợ debit_account_code (ví dụ 331, 641, 642)/Có 1111
// Nếu có VAT: Nợ 1331 (thuế GTGT đầu vào được khấu trừ)
// RỦI RO: Chi tiền cho NCC phải ghi nhận đúng supplier_id để theo dõi công nợ 331
$('#paymentForm').submit(function(e){e.preventDefault();
    var data={
        amount: parseFloat($('#amount').val()),
        debit_account_code: $('#debitAccount').val(),
        description: $('#description').val(),
        transaction_date: $('#txnDate').val()||null,
        payer_name: $('#payerSearch').val()||null,
        payer_type: $('#payerType').val()||null,
        payer_id: $('#payerId').val()||null,
        vat_amount: $('#vatRateGroup').is(':visible')?parseInt($('#vatAmount').val())||0:0,
        vat_rate: $('#vatRateGroup').is(':visible')?parseInt($('#vatRate').val())||0:0,
    };
    $.ajax({url:'/api/cash/payments',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify(data),
        success:function(){$('#paymentModal').modal('hide');$('#paymentForm')[0].reset();showToast('Đã tạo phiếu chi thành công.','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadTemplates();loadData();$('#txnDate').val(new Date().toISOString().substring(0,10));loadVatRates('#vatRate',10);});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
