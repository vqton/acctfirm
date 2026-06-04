<?php // Màn hình: Quản lý công nợ phải thu khách hàng (TK 131)
// API: GET /api/ar/customers, GET /api/ar/invoices, POST /api/ar/invoices, POST /api/ar/invoices/{id}/pay, POST /api/ar/prepay
// Nghiệp vụ: Ghi nhận hóa đơn bán hàng (Nợ 131/Có 511+3331), thu tiền (Nợ 1111/Có 131), nhận tạm ứng (Nợ 1111/Có 131)
// Rủi ro: Thu tiền vượt quá số dư phải thu sẽ dẫn đến số dư âm TK 131
$title = 'Công nợ phải thu'; $activeMenu = 'ar_invoices'; ob_start(); ?>
<div class="toolbar">
    <h5>Công nợ phải thu khách hàng <span class="stats">(TK 131)</span></h5>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Xuất Excel"><i class="bi bi-download"></i> Excel</button>
        <button class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#invModal"><i class="bi bi-plus-lg"></i> Hóa đơn bán hàng</button>
        <button class="btn btn-outline-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#prepayModal"><i class="bi bi-credit-card"></i> Nhận tạm ứng</button>
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
            <select class="form-select form-select-sm" id="filterCustomer" style="width:200px;"><option value="">Tất cả KH</option></select>
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
    <thead><tr><th>Hóa đơn</th><th>Khách hàng</th><th>Ngày</th><th>Hạn</th><th class="text-end">Tổng</th><th class="text-end">Đã thu</th><th class="text-end">Còn lại</th><th class="text-end">Quá hạn</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="invModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="invForm">
<div class="modal-header"><h5 class="modal-title">Hóa đơn bán hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Khách hàng</label><select class="form-select" id="customerId" required></select></div>
    <div class="mb-2"><label>Số hóa đơn</label><input class="form-control" id="invoiceNumber" required></div>
    <div class="row g-2"><div class="col-4 mb-2"><label>Ngày HĐ</label><input type="date" class="form-control" id="invDate" value="<?=date('Y-m-d')?>"></div><div class="col-4 mb-2"><label>Hạn TT</label><input type="date" class="form-control" id="dueDate" value="<?=date('Y-m-d', strtotime('+30 days'))?>"></div><div class="col-4 mb-2"><label>Thuế GTGT</label><select class="form-select" id="vatRate"></select></div></div>
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
var allData=[];
function loadCustomers(cb){
    $.get('/api/ar/customers', function(l){
        var o='',fo='<option value="">Tất cả KH</option>';
        l.forEach(function(s){
            o+='<option value="'+esc(s.id)+'">'+esc(s.name)+' ('+parseFloat(s.balance).toLocaleString()+' VND)</option>';
            fo+='<option value="'+esc(s.id)+'">'+esc(s.name)+'</option>';
        });
        $('#customerId,#prepayCust').html(o);
        $('#filterCustomer').html(fo);
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
        var acts='';
        var aging=calcAgingDays(r.due_date);
        var rowClass='';
        if(aging>90){rowClass=' class="table-danger"';}
        else if(aging>30){rowClass=' class="table-warning"';}
        if(r.status!=='paid'&&r.status!=='written_off'&&r.balance>1) acts+='<button class="btn btn-sm btn-outline-success me-1" onclick="openPay('+r.id+','+r.balance+')"><i class="bi bi-cash"></i></button>';
        var agingLabel=aging>0?aging+' ngày':'';
        tbody.append('<tr'+rowClass+'><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.customer_name)+'</td><td style="font-size:12px">'+esc(r.invoice_date)+'</td><td style="font-size:12px">'+esc(r.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.gross_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.paid_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td class="text-end font-monospace" style="font-size:11px">'+agingLabel+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+acts+'</td></tr>');
    });
    if(!data.length) tbody.append('<tr><td colspan="10" class="empty-state"><i class="bi bi-inbox"></i> Không có hóa đơn nào</td></tr>');
}
function applyFilters(){
    var s=$('#filterSearch').val().toLowerCase().trim();
    var st=$('#filterStatus').val();
    var cu=$('#filterCustomer').val();
    var dFrom=$('#filterDateFrom').val();
    var dTo=$('#filterDateTo').val();
    var filtered=allData.filter(function(r){
        if(s && !r.invoice_number.toLowerCase().includes(s))return false;
        if(st){
            if(st==='unpaid' && (r.status==='paid'||r.status==='written_off'||r.balance<=1))return false;
            else if(st!=='unpaid' && r.status!==st)return false;
        }
        if(cu && r.customer_id!==cu)return false;
        if(dFrom && r.invoice_date<dFrom)return false;
        if(dTo && r.invoice_date>dTo)return false;
        return true;
    });
    renderRows(filtered);
}
function clearFilters(){
    $('#filterSearch').val('');
    $('#filterStatus').val('');
    $('#filterCustomer').val('');
    $('#filterDateFrom').val('');
    $('#filterDateTo').val('');
    renderRows(allData);
}
function loadData(){
    $.get('/api/ar/invoices', function(data){
        allData=data;
        renderRows(allData);
    });
}
function exportCSV(){
    var rows=[['Hóa đơn','Khách hàng','Ngày HĐ','Hạn TT','Tổng tiền','Đã thu','Còn lại','Quá hạn (ngày)','Trạng thái']];
    allData.forEach(function(r){
        var aging=calcAgingDays(r.due_date);
        rows.push([r.invoice_number,r.customer_name,r.invoice_date,r.due_date,r.gross_amount,r.paid_amount,r.balance,aging,r.status]);
    });
    var csv='\uFEFF';
    rows.forEach(function(row){
        csv+=row.map(function(v){return'"'+String(v).replace(/"/g,'""')+'"';}).join(',')+'\n';
    });
    var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='ar_invoices_'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a);a.click();document.body.removeChild(a);
}
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
$(document).ready(function(){loadCustomers();loadData();loadVatRates('#vatRate',10);});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
