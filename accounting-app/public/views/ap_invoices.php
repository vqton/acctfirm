<?php // Màn hình: Quản lý công nợ phải trả nhà cung cấp (TK 331)
// API: GET /api/ap/suppliers, GET /api/ap/invoices, POST /api/ap/invoices, POST /api/ap/invoices/{id}/pay, POST /api/ap/prepay
// Nghiệp vụ: Ghi nhận hóa đơn mua hàng (Nợ 156/152/211 + 1331/Có 331), thanh toán (Nợ 331/Có 1111/1121), tạm ứng (Nợ 331/Có 1111)
// Rủi ro: Chọn sai inventory_account sẽ sai tài khoản hàng tồn kho và sai BC01
$title = 'Công nợ phải trả'; $activeMenu = 'ap_invoices'; ob_start(); ?>
<div class="toolbar">
    <h5>Công nợ phải trả nhà cung cấp <span class="stats">(TK 331)</span></h5>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Xuất Excel"><i class="bi bi-download"></i> Excel</button>
        <button class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#invModal"><i class="bi bi-plus-lg"></i> Ghi nhận hóa đơn</button>
        <button class="btn btn-outline-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#prepayModal"><i class="bi bi-credit-card"></i> Tạm ứng</button>
    </div>
</div>
<div class="card p-2 mb-3 border-0 shadow-sm bg-white" style="font-size:13px;">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <input class="form-control form-control-sm" id="filterSearch" placeholder="🔍 Tìm số HĐ..." style="width:160px;">
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" id="filterStatus" style="width:140px;">
                <option value="">Tất cả trạng thái</option>
                <option value="unpaid">Chưa thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="written_off">Đã xóa sổ</option>
            </select>
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" id="filterSupplier" style="width:200px;"><option value="">Tất cả NCC</option></select>
        </div>
        <div class="col-auto">
            <input type="date" class="form-control form-control-sm" id="filterDateFrom" title="Từ ngày" style="width:150px;">
        </div>
        <div class="col-auto">
            <input type="date" class="form-control form-control-sm" id="filterDateTo" title="Đến ngày" style="width:150px;">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Lọc</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Hóa đơn</th><th>Nhà cung cấp</th><th>Ngày</th><th>Hạn</th><th class="text-end">Tổng</th><th class="text-end">Đã trả</th><th class="text-end">Còn lại</th><th class="text-end">Quá hạn</th><th>Trạng thái</th><th></th></tr></thead>
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
var allData=[];
function loadSuppliers(cb){
    $.get('/api/ap/suppliers', function(list){
        var opts='',fopts='<option value="">Tất cả NCC</option>';
        list.forEach(function(s){
            opts+='<option value="'+esc(s.id)+'">'+esc(s.name)+' ('+parseFloat(s.balance).toLocaleString()+' VND)</option>';
            fopts+='<option value="'+esc(s.id)+'">'+esc(s.name)+'</option>';
        });
        $('#supplierId, #prepaySupplier').html(opts);
        $('#filterSupplier').html(fopts);
        if(cb)cb();
    });
}
function calcAgingDays(dueDate){
    var parts=dueDate.split('-');
    var due=new Date(parts[0],parts[1]-1,parts[2]);
    var today=new Date();today.setHours(0,0,0,0);
    var diff=Math.floor((today-due)/(86400000));
    return due<today?diff:0;
}
function renderRows(data){
    var tbody=$('#dataBody');tbody.empty();
    data.forEach(function(r){
        var badge=r.status==='paid'?'badge-active':(r.status==='written_off'?'badge-inactive':'badge-warning');
        var actions='';
        var aging=calcAgingDays(r.due_date);
        var rowClass='';
        if(aging>90){rowClass=' class="table-danger"';}
        else if(aging>30){rowClass=' class="table-warning"';}
        if(r.status!=='paid'&&r.status!=='written_off'&&r.balance>1){
            actions+='<button class="btn btn-sm btn-outline-success me-1" onclick="openPay('+r.id+','+r.balance+')"><i class="bi bi-cash"></i></button>';
        }
        var agingLabel=aging>0?aging+' ngày':'';
        tbody.append('<tr'+rowClass+'><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.supplier_name)+'</td><td style="font-size:12px">'+esc(r.invoice_date)+'</td><td style="font-size:12px">'+esc(r.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.gross_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.paid_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td class="text-end font-monospace" style="font-size:11px">'+agingLabel+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+actions+'</td></tr>');
    });
    if(!data.length) tbody.append('<tr><td colspan="10" class="empty-state"><i class="bi bi-inbox"></i> Không có hóa đơn nào</td></tr>');
}
function applyFilters(){
    var s=$('#filterSearch').val().toLowerCase().trim();
    var st=$('#filterStatus').val();
    var su=$('#filterSupplier').val();
    var dFrom=$('#filterDateFrom').val();
    var dTo=$('#filterDateTo').val();
    var filtered=allData.filter(function(r){
        if(s && !r.invoice_number.toLowerCase().includes(s))return false;
        if(st){
            if(st==='unpaid' && (r.status==='paid'||r.status==='written_off'||r.balance<=1))return false;
            else if(st!=='unpaid' && r.status!==st)return false;
        }
        if(su && r.supplier_id!==su)return false;
        if(dFrom && r.invoice_date<dFrom)return false;
        if(dTo && r.invoice_date>dTo)return false;
        return true;
    });
    renderRows(filtered);
}
function clearFilters(){
    $('#filterSearch').val('');
    $('#filterStatus').val('');
    $('#filterSupplier').val('');
    $('#filterDateFrom').val('');
    $('#filterDateTo').val('');
    renderRows(allData);
}
function loadData(){
    $.get('/api/ap/invoices', function(data){
        allData=data;
        renderRows(allData);
    });
}
function exportCSV(){
    var rows=[['Hóa đơn','Nhà cung cấp','Ngày HĐ','Hạn TT','Tổng tiền','Đã trả','Còn lại','Quá hạn (ngày)','Trạng thái']];
    allData.forEach(function(r){
        var aging=calcAgingDays(r.due_date);
        rows.push([r.invoice_number,r.supplier_name,r.invoice_date,r.due_date,r.gross_amount,r.paid_amount,r.balance,aging,r.status]);
    });
    var csv='\uFEFF';
    rows.forEach(function(row){
        csv+=row.map(function(v){return'"'+String(v).replace(/"/g,'""')+'"';}).join(',')+'\n';
    });
    var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='ap_invoices_'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a);a.click();document.body.removeChild(a);
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
