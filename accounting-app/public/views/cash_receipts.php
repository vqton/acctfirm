<?php // Màn hình: Lập và quản lý phiếu thu tiền mặt
// API: GET /api/cash/receipts, GET /api/cash/templates?type=receipt, GET /api/cash/accounts?for=receipt, POST /api/cash/receipts, GET /api/payers/search, GET /api/utils/to-words
// Nghiệp vụ: Thu tiền mặt — Nợ 1111/Có TK đối ứng (131, 511, 338...)
// Tuân thủ: Chỉ post vào TK con 1111 (không post vào TK tổng hợp 111)
// Rủi ro: Chọn sai TK Có sẽ ảnh hưởng đến doanh thu/công nợ — kiểm tra loại thu trước khi ghi nhận
header('Cache-Control: no-cache, no-store, must-revalidate'); header('Pragma: no-cache'); header('Expires: 0');
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
        <div class="col-4 mb-2"><label>Loại thu</label><select class="form-select" id="receiptType"><option value="">-- Chọn loại --</option></select></div>
        <div class="col-4 mb-2"><label>TK Có (đối ứng)</label><select class="form-select" id="creditAccount" required></select></div>
    </div>
    <div class="row g-2">
        <div class="col-4 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1" min="1" required></div>
        <div class="col-2 mb-2" id="vatRateGroup" style="display:none"><label>VAT %</label><select class="form-select" id="vatRate"></select></div>
        <div class="col-2 mb-2" id="vatAmountGroup" style="display:none"><label>Tiền VAT</label><input type="number" class="form-control" id="vatAmount" readonly step="1" style="background:#f5f5f5"></div>
        <div class="col-4 mb-2" id="netAmountGroup" style="display:none"><label>Tiền chưa thuế</label><input type="number" class="form-control" id="netAmount" readonly step="1" style="background:#f5f5f5"></div>
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
// Tải danh sách phiếu thu — GET /api/cash/receipts
// Hiển thị thông tin: số CT, người nộp, diễn giải, số tiền, TK Có, ngày, trạng thái
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
function loadTemplates(){
    $.get('/api/cash/templates?type=receipt&_='+Date.now(),function(tpls){
        var sel=$('#receiptType');sel.html('<option value="">-- Chọn loại thu --</option>');
        tpls.forEach(function(t){sel.append('<option value="'+esc(t.id)+'" data-account="'+esc(t.default_account)+'" data-vat="'+t.has_vat+'" data-vat-rate="'+t.vat_rate+'">'+esc(t.name)+'</option>');});
    });
    $.get('/api/cash/accounts?for=receipt&_='+Date.now(),function(l){
        var o='<option value="">-- Chọn tài khoản --</option>';
        l.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>';});
        $('#creditAccount').html(o);
        $('#loadStatus').text('OK: '+l.length+' tài khoản').css('color','');
    }).fail(function(x){$('#creditAccount').html('<option>Lỗi: '+x.status+'</option>');$('#loadStatus').text('LỖI: '+x.status).css('color','red');});
}
// Khi chọn loại thu — tự động điền TK Có mặc định và hiển thị VAT nếu có
// Nghiệp vụ: Nếu loại thu có VAT (ví dụ thu tiền bán hàng), hiển thị trường VAT
// Công thức: Tiền chưa thuế = Tổng / (1 + VAT%), Tiền VAT = Tổng - Tiền chưa thuế
$('#receiptType').on('change',function(){
    var opt=$(this).find(':selected');
    var hasVat=opt.data('vat')===true;
    $('#vatRateGroup,#vatAmountGroup,#netAmountGroup').toggle(hasVat);
    if(opt.data('account')){$('#creditAccount').val(opt.data('account'));}
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
// Tra cứu người nộp tiền — GET /api/payers/search?q=...
// Tìm kiếm trong 3 loại đối tượng: khách hàng (customer), nhà cung cấp (supplier), nhân viên (employee)
// Debounce 300ms — tránh gọi API liên tục khi người dùng gõ
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
// Chọn đối tượng từ kết quả tìm kiếm — lưu ID và type vào hidden field
$(document).on('click','.payer-item',function(){
    $('#payerSearch').val($(this).data('name'));
    $('#payerId').val($(this).data('id'));
    $('#payerType').val($(this).data('type'));
    $('#payerResults').hide();
});
$(document).on('click',function(e){if(!$(e.target).closest('#payerSearch,#payerResults').length)$('#payerResults').hide();});
// Submit tạo phiếu thu — POST /api/cash/receipts
// Nghiệp vụ: Nợ 1111/Có credit_account_code (ví dụ 131, 511, 3388)
// Nếu có VAT: ghi nhận thêm Nợ 1331/Có 33311 (thuế GTGT đầu ra)
// RỦI RO: Nếu không nhập đúng payer_type, công nợ chi tiết sẽ không được cập nhật
$('#receiptForm').submit(function(e){e.preventDefault();
    var data={
        amount: parseFloat($('#amount').val()),
        credit_account_code: $('#creditAccount').val(),
        description: $('#description').val(),
        transaction_date: $('#txnDate').val()||null,
        payer_name: $('#payerSearch').val()||null,
        payer_type: $('#payerType').val()||null,
        payer_id: $('#payerId').val()||null,
        vat_amount: $('#vatRateGroup').is(':visible')?parseInt($('#vatAmount').val())||0:0,
        vat_rate: $('#vatRateGroup').is(':visible')?parseInt($('#vatRate').val())||0:0,
    };
    $.ajax({url:'/api/cash/receipts',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify(data),
        success:function(){$('#receiptModal').modal('hide');$('#receiptForm')[0].reset();showToast('Đã tạo phiếu thu thành công. Số phiếu thu sẽ được hệ thống tự động cập nhật.','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadTemplates();loadData();$('#txnDate').val(new Date().toISOString().substring(0,10));loadVatRates('#vatRate',10);});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
