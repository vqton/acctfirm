<?php $title = 'Công nợ phải trả'; $activeMenu = 'ap_invoices'; ob_start(); ?>
<div class="toolbar">
    <h5>Công nợ phải trả nhà cung cấp <span class="stats">(TK 331)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#invModal"><i class="bi bi-plus-lg"></i> Ghi nhận hóa đơn</button>
        <button class="btn btn-outline-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#prepayModal"><i class="bi bi-credit-card"></i> Tạm ứng</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Hóa đơn</th><th>Nhà cung cấp</th><th>Ngày</th><th>Hạn</th><th class="text-end">Tổng</th><th class="text-end">Đã trả</th><th class="text-end">Còn lại</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="invModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="invForm">
<div class="modal-header"><h5 class="modal-title">Ghi nhận hóa đơn mua hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Nhà cung cấp</label><select class="form-select" id="supplierId" required></select></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Số hóa đơn</label><input class="form-control" id="invoiceNumber" required></div><div class="col-6 mb-2"><label>TK kho</label><select class="form-select" id="invAccount"><option value="152">152 - NVL</option><option value="156">156 - Hàng hóa</option><option value="153">153 - CCDC</option><option value="211">211 - TSCĐ</option><option value="642">642 - CP QLDN</option></select></div></div>
    <div class="row g-2"><div class="col-4 mb-2"><label>Ngày HĐ</label><input type="date" class="form-control" id="invDate" value="<?=date('Y-m-d')?>"></div><div class="col-4 mb-2"><label>Hạn thanh toán</label><input type="date" class="form-control" id="dueDate" value="<?=date('Y-m-d', strtotime('+30 days'))?>"></div><div class="col-4 mb-2"><label>Thuế GTGT (%)</label><input type="number" class="form-control" id="vatRate" value="10" step="0.5" min="0"></div></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Tiền hàng (chưa VAT)</label><input type="number" class="form-control" id="netAmount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>Tiền VAT</label><input type="number" class="form-control" id="vatAmount" step="1000" min="0"></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="invDesc"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<div class="modal fade" id="prepayModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="prepayForm">
<div class="modal-header"><h5 class="modal-title">Tạm ứng cho nhà cung cấp</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Nhà cung cấp</label><select class="form-select" id="prepaySupplier" required></select></div>
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="prepayAmount" step="1000" min="1" required></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="prepayDesc" placeholder="Tạm ứng theo hợp đồng..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Tạm ứng</button></div>
</form>
</div></div></div>

<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="payForm">
<div class="modal-header"><h5 class="modal-title">Thanh toán</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="payInvId">
    <p class="text-muted">Còn phải trả: <strong id="payBalance"></strong></p>
    <div class="mb-2"><label>Số tiền thanh toán</label><input type="number" class="form-control" id="payAmount" step="1000" min="1" required></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-success">Thanh toán</button></div>
</form>
</div></div></div>

<script>
function loadSuppliers(cb){
    $.get('/api/ap/suppliers', function(list){
        var opts=''; list.forEach(function(s){opts+='<option value="'+esc(s.id)+'">'+esc(s.name)+' ('+parseFloat(s.balance).toLocaleString()+' VND)</option>';});
        $('#supplierId, #prepaySupplier').html(opts);
        if(cb)cb();
    });
}
function loadData(){
    $.get('/api/ap/invoices', function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var badge=r.status==='paid'?'badge-active':(r.status==='written_off'?'badge-inactive':'badge-warning');
            var actions='';
            if(r.status!=='paid'&&r.status!=='written_off'&&r.balance>1){
                actions+='<button class="btn btn-sm btn-outline-success me-1" onclick="openPay('+r.id+','+r.balance+')"><i class="bi bi-cash"></i></button>';
            }
            tbody.append('<tr><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.supplier_name)+'</td><td style="font-size:12px">'+esc(r.invoice_date)+'</td><td style="font-size:12px">'+esc(r.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.gross_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.paid_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+actions+'</td></tr>');
        });
    });
}
function openPay(id,bal){$('#payInvId').val(id);$('#payBalance').text(parseFloat(bal).toLocaleString());$('#payAmount').val(bal);$('#payModal').modal('show');}
function calcVat(){var net=parseFloat($('#netAmount').val())||0;var rate=parseFloat($('#vatRate').val())||0;$('#vatAmount').val(Math.round(net*rate/100));}
$('#netAmount,#vatRate').on('input',calcVat);
$('#invForm').submit(function(e){e.preventDefault();
    var data={supplier_id:$('#supplierId').val(),invoice_number:$('#invoiceNumber').val(),net_amount:parseFloat($('#netAmount').val()),vat_amount:parseFloat($('#vatAmount').val()),vat_rate:parseFloat($('#vatRate').val()),invoice_date:$('#invDate').val(),due_date:$('#dueDate').val(),inventory_account:$('#invAccount').val(),description:$('#invDesc').val()};
    $.ajax({url:'/api/ap/invoices',method:'POST',contentType:'application/json',data:JSON.stringify(data),
        success:function(){$('#invModal').modal('hide');$('#invForm')[0].reset();showToast('Ghi nhận hóa đơn thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#payForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/ap/invoices/'+$('#payInvId').val()+'/pay',method:'POST',contentType:'application/json',data:JSON.stringify({amount:parseFloat($('#payAmount').val())}),
        success:function(){$('#payModal').modal('hide');showToast('Thanh toán thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#prepayForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/ap/prepay',method:'POST',contentType:'application/json',data:JSON.stringify({supplier_id:$('#prepaySupplier').val(),amount:parseFloat($('#prepayAmount').val()),description:$('#prepayDesc').val()}),
        success:function(){$('#prepayModal').modal('hide');$('#prepayForm')[0].reset();showToast('Tạm ứng thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadSuppliers();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
