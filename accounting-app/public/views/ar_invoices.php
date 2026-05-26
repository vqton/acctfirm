<?php // Màn hình: Quản lý công nợ phải thu khách hàng (TK 131)
// API: GET /api/ar/customers, GET /api/ar/invoices, POST /api/ar/invoices, POST /api/ar/invoices/{id}/pay, POST /api/ar/prepay
// Nghiệp vụ: Ghi nhận hóa đơn bán hàng (Nợ 131/Có 511+3331), thu tiền (Nợ 1111/Có 131), nhận tạm ứng (Nợ 1111/Có 131)
// Rủi ro: Thu tiền vượt quá số dư phải thu sẽ dẫn đến số dư âm TK 131
$title = 'Công nợ phải thu'; $activeMenu = 'ar_invoices'; ob_start(); ?>
<div class="toolbar">
    <h5>Công nợ phải thu khách hàng <span class="stats">(TK 131)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#invModal"><i class="bi bi-plus-lg"></i> Hóa đơn bán hàng</button>
        <button class="btn btn-outline-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#prepayModal"><i class="bi bi-credit-card"></i> Nhận tạm ứng</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Hóa đơn</th><th>Khách hàng</th><th>Ngày</th><th>Hạn</th><th class="text-end">Tổng</th><th class="text-end">Đã thu</th><th class="text-end">Còn lại</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="invModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="invForm">
<div class="modal-header"><h5 class="modal-title">Hóa đơn bán hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Khách hàng</label><select class="form-select" id="customerId" required></select></div>
    <div class="mb-2"><label>Số hóa đơn</label><input class="form-control" id="invoiceNumber" required></div>
    <div class="row g-2"><div class="col-4 mb-2"><label>Ngày HĐ</label><input type="date" class="form-control" id="invDate" value="<?=date('Y-m-d')?>"></div><div class="col-4 mb-2"><label>Hạn TT</label><input type="date" class="form-control" id="dueDate" value="<?=date('Y-m-d', strtotime('+30 days'))?>"></div><div class="col-4 mb-2"><label>Thuế GTGT (%)</label><input type="number" class="form-control" id="vatRate" value="10" step="0.5"></div></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Tiền hàng (chưa VAT)</label><input type="number" class="form-control" id="netAmount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>Tiền VAT</label><input type="number" class="form-control" id="vatAmount" step="1000" min="0"></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="invDesc"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<div class="modal fade" id="prepayModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="prepayForm">
<div class="modal-header"><h5 class="modal-title">Nhận tạm ứng khách hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Khách hàng</label><select class="form-select" id="prepayCust" required></select></div>
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="prepayAmount" step="1000" min="1" required></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="prepayDesc" placeholder="Tạm ứng theo hợp đồng..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="payForm">
<div class="modal-header"><h5 class="modal-title">Thu tiền</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="payInvId"><p>Còn phải thu: <strong id="payBalance"></strong></p>
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="payAmount" step="1000" min="1" required></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-success">Ghi nhận thu tiền</button></div>
</form>
</div></div></div>

<script>
function loadCustomers(cb){
    $.get('/api/ar/customers', function(l){
        var o=''; l.forEach(function(s){o+='<option value="'+esc(s.id)+'">'+esc(s.name)+' ('+parseFloat(s.balance).toLocaleString()+' VND)</option>';});
        $('#customerId,#prepayCust').html(o); if(cb)cb();
    });
}
// Tải danh sách hóa đơn phải thu — GET /api/ar/invoices
// Hiển thị: số HĐ, KH, ngày, hạn, tổng, đã thu, còn lại, trạng thái
// Nút thu tiền chỉ hiển thị khi HĐ chưa thanh toán và balance > 1
function loadData(){
    $.get('/api/ar/invoices', function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var badge=r.status==='paid'?'badge-active':(r.status==='written_off'?'badge-inactive':'badge-warning');
            var acts='';
            if(r.status!=='paid'&&r.status!=='written_off'&&r.balance>1) acts+='<button class="btn btn-sm btn-outline-success me-1" onclick="openPay('+r.id+','+r.balance+')"><i class="bi bi-cash"></i></button>';
            tbody.append('<tr><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.customer_name)+'</td><td style="font-size:12px">'+esc(r.invoice_date)+'</td><td style="font-size:12px">'+esc(r.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.gross_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.paid_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+acts+'</td></tr>');
        });
    });
}
// Mở modal thu tiền — tự động điền số dư còn lại làm mặc định
function openPay(id,bal){$('#payInvId').val(id);$('#payBalance').text(parseFloat(bal).toLocaleString());$('#payAmount').val(bal);$('#payModal').modal('show');}
function calcVat(){var n=parseFloat($('#netAmount').val())||0;var r=parseFloat($('#vatRate').val())||0;$('#vatAmount').val(Math.round(n*r/100));}
$('#netAmount,#vatRate').on('input',calcVat);
$('#invForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/ar/invoices',method:'POST',contentType:'application/json',data:JSON.stringify({customer_id:$('#customerId').val(),invoice_number:$('#invoiceNumber').val(),net_amount:parseFloat($('#netAmount').val()),vat_amount:parseFloat($('#vatAmount').val()),vat_rate:parseFloat($('#vatRate').val()),invoice_date:$('#invDate').val(),due_date:$('#dueDate').val(),description:$('#invDesc').val()}),
        success:function(){$('#invModal').modal('hide');$('#invForm')[0].reset();showToast('Ghi nhận hóa đơn thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#payForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/ar/invoices/'+$('#payInvId').val()+'/pay',method:'POST',contentType:'application/json',data:JSON.stringify({amount:parseFloat($('#payAmount').val())}),
        success:function(){$('#payModal').modal('hide');showToast('Thu tiền thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#prepayForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/ar/prepay',method:'POST',contentType:'application/json',data:JSON.stringify({customer_id:$('#prepayCust').val(),amount:parseFloat($('#prepayAmount').val()),description:$('#prepayDesc').val()}),
        success:function(){$('#prepayModal').modal('hide');$('#prepayForm')[0].reset();showToast('Ghi nhận tạm ứng thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadCustomers();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
