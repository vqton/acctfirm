<?php $title = 'Phiếu thu'; $activeMenu = 'cash_receipts'; ob_start(); ?>
<div class="toolbar">
    <h5>Phiếu thu <span class="stats">(TK 111)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#receiptModal"><i class="bi bi-plus-lg"></i> Tạo phiếu thu</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Số CT</th><th>Diễn giải</th><th>Số tiền</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<div class="modal fade" id="receiptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="receiptForm">
<div class="modal-header"><h5 class="modal-title">Phiếu thu tiền mặt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Loại giao dịch</label><select class="form-select" id="txType"><option value="">Chọn loại...</option></select></div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Ghi Có TK</label><select class="form-select" id="creditAccount" required></select></div>
    <div class="col-6 mb-2" id="vatField"><label>Thuế GTGT</label>
        <div class="input-group"><input type="number" class="form-control" id="vatAmount" step="100" min="0" placeholder="0"><span class="input-group-text">VND</span></div>
    </div></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="description" placeholder="Thu tiền của khách hàng..."></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="reference" placeholder="PT-..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Ghi nhận thu tiền</button>
</div>
</form>
</div></div></div>
<script>
var accounts = [];
var templates = [];

$.get('/api/cash/accounts?for=receipt', function(a) { accounts = a; });
$.get('/api/cash/templates?type=receipt', function(t) {
    templates = t;
    t.forEach(function(tm) { $('#txType').append('<option value="'+tm.id+'">'+esc(tm.name)+'</option>'); });
});

$('#txType').change(function() {
    var t = templates.find(function(tm) { return tm.id === $(this).val(); }.bind(this));
    if (!t) { $('#creditAccount').val(''); $('#vatField').hide(); return; }
    $('#creditAccount').val(t.default_account);
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

function loadData() {
    $.get('/api/cash/receipts', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="5" class="text-center text-muted py-4">Chưa có phiếu thu</td></tr>');return;}
        data.forEach(function(r){tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+parseFloat(r.amount||0).toLocaleString()+'</td><td><span class="badge-status badge-active">'+esc(r.status)+'</span></td><td>'+esc(r.created_at)+'</td></tr>');});
    });
}

$('#receiptForm').submit(function(e){e.preventDefault();
    var payload = {
        amount: parseFloat($('#amount').val()),
        credit_account_code: $('#creditAccount').val(),
        description: $('#description').val(),
        reference: $('#reference').val() || undefined
    };
    // If VAT type, also send VAT info
    var sel = $('#txType').val();
    var t = templates.find(function(tm) { return tm.id === sel; });
    if (t && t.has_vat) {
        payload.vat_amount = parseFloat($('#vatAmount').val()) || 0;
        payload.vat_rate = t.vat_rate;
    }
    $.ajax({url:'/api/cash/receipts',method:'POST',contentType:'application/json',data:JSON.stringify(payload),
        success:function(){$('#receiptModal').modal('hide');$('#receiptForm')[0].reset();showToast('Tạo phiếu thu thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

$(document).ready(function(){loadData();$('#vatField').hide();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
