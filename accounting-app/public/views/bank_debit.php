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
    <div class="mb-3"><label>Loại giao dịch</label><select class="form-select" id="txType"><option value="">Chọn loại...</option></select></div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Ghi Nợ TK</label><select class="form-select" id="accountCode"></select></div>
    <div class="col-6 mb-2" id="vatField"><label>Thuế GTGT</label><div class="input-group"><input type="number" class="form-control" id="vatAmount" step="100" min="0" placeholder="0"><span class="input-group-text">VND</span></div></div></div>
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
var accounts = [];
var templates = [];

$.get('/api/cash/accounts?for=payment', function(a) { accounts = a; });
$.get('/api/cash/templates?type=payment', function(t) {
    var bankTemplates = [
        {id:'supplier_payment',name:'Thanh toán nhà cung cấp',default_account:'331',has_vat:false},
        {id:'expense',name:'Chi phí SXKD',default_account:'642',has_vat:true,vat_rate:10},
        {id:'inventory',name:'Mua hàng tồn kho',default_account:'152',has_vat:true,vat_rate:10},
        {id:'fixed_asset',name:'Mua TSCĐ',default_account:'211',has_vat:true,vat_rate:10},
        {id:'salary',name:'Trả lương',default_account:'334',has_vat:false},
        {id:'tax',name:'Nộp thuế',default_account:'333',has_vat:false},
        {id:'loan',name:'Trả vay',default_account:'341',has_vat:false},
        {id:'investment',name:'Mua đầu tư tài chính',default_account:'121',has_vat:false},
        {id:'finance_cost',name:'Phí ngân hàng',default_account:'635',has_vat:false},
        {id:'withdrawal_to_cash',name:'Rút tiền mặt',default_account:'111',has_vat:false},
        {id:'escrow',name:'Ký quỹ, ký cược',default_account:'244',has_vat:false},
        {id:'dividend',name:'Trả cổ tức',default_account:'332',has_vat:false},
    ];
    templates = bankTemplates;
    bankTemplates.forEach(function(tm) { $('#txType').append('<option value="'+tm.id+'">'+esc(tm.name)+'</option>'); });
});

$('#txType').change(function() {
    var t = templates.find(function(tm) { return tm.id === $(this).val(); }.bind(this));
    if (!t) { $('#accountCode').val(''); $('#vatField').hide(); return; }
    $('#accountCode').val(t.default_account);
    if (t.has_vat) { $('#vatField').show(); calcVat(t.vat_rate); }
    else { $('#vatField').hide(); $('#vatAmount').val(0); }
});

function calcVat(rate) {
    var amt = parseFloat($('#amount').val()) || 0;
    var r = rate || 10;
    $('#vatAmount').val(Math.round(amt * r / (100 + r)));
}

$('#amount').on('input', function() {
    var sel = $('#txType').val();
    var t = templates.find(function(tm) { return tm.id === sel; });
    if (t && t.has_vat) calcVat(t.vat_rate);
});

$('#debitForm').submit(function(e){e.preventDefault();
    var sel = $('#txType').val();
    var t = templates.find(function(tm) { return tm.id === sel; });
    var url, payload = {amount:parseFloat($('#amount').val()),description:$('#description').val(),reference:$('#reference').val()||undefined};
    if (sel === 'withdrawal_to_cash') { url='/api/bank/withdrawal'; }
    else if (sel === 'finance_cost') { url='/api/bank/charge'; }
    else {
        url = '/api/bank/payment';
        payload.debit_account_code = $('#accountCode').val();
        if (t && t.has_vat) { payload.vat_amount = parseFloat($('#vatAmount').val()) || 0; payload.vat_rate = t.vat_rate; }
    }
    $.ajax({url:url,method:'POST',contentType:'application/json',data:JSON.stringify(payload),success:function(){$('#debitModal').modal('hide');$('#debitForm')[0].reset();showToast('Tạo báo Nợ thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
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
