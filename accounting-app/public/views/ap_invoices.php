<?php // Màn hình: Quản lý công nợ phải trả nhà cung cấp (TK 331)
// API: GET /api/ap/suppliers, GET /api/ap/invoices, POST /api/ap/invoices, POST /api/ap/invoices/{id}/pay, POST /api/ap/invoices/{id}/discount, POST /api/ap/invoices/{id}/return, POST /api/ap/invoices/{id}/write-off, POST /api/ap/prepay
// Nghiệp vụ: Ghi nhận hóa đơn mua hàng (Nợ 156/152/211 + 1331/Có 331), thanh toán (Nợ 331/Có 1111/1121), tạm ứng (Nợ 331/Có 1111)
// Rủi ro: Chọn sai inventory_account sẽ sai tài khoản hàng tồn kho và sai BC01
$title = 'Công nợ phải trả'; $activeMenu = 'ap_invoices'; ob_start(); ?>
<div class="toolbar">
    <h5>Công nợ phải trả nhà cung cấp <span class="stats">(TK 331)</span></h5>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Xuất Excel"><i class="bi bi-download"></i> Excel</button>
        <button class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#invModal"><i class="bi bi-plus-lg"></i> Ghi nhận hóa đơn</button>
        <button class="btn btn-outline-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#prepayModal"><i class="bi bi-credit-card"></i> Tạm ứng</button>
        <button class="btn btn-outline-success btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#importEinvModal"><i class="bi bi-file-earmark-arrow-up"></i> Import HĐĐT</button>
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
    <div class="mb-2"><label>Nhà cung cấp</label><select class="form-select" id="supplierId" data-v-required="Nhà cung cấp" required></select></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Số hóa đơn</label><input class="form-control" id="invoiceNumber" data-v-required="Số hóa đơn" required></div><div class="col-6 mb-2"><label>TK kho</label><select class="form-select" id="invAccount"><option value="152">152 - NVL</option><option value="156">156 - Hàng hóa</option><option value="153">153 - CCDC</option><option value="211">211 - TSCĐ</option><option value="642">642 - CP QLDN</option></select></div></div>
    <div class="row g-2"><div class="col-4 mb-2"><label>Ngày HĐ</label><input type="date" class="form-control" id="invDate" data-v-required="Ngày hóa đơn" data-v-date="Ngày hóa đơn"></div><div class="col-4 mb-2"><label>Hạn thanh toán</label><input type="date" class="form-control" id="dueDate"></div><div class="col-4 mb-2"><label>Thuế GTGT</label><select class="form-select" id="vatRate"></select></div></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Tiền hàng (chưa VAT)</label><input type="number" class="form-control" id="netAmount" step="1000" min="1" data-v-required="Tiền hàng" data-v-number="Tiền hàng" required></div><div class="col-6 mb-2"><label>Tiền VAT</label><input type="number" class="form-control" id="vatAmount" step="1000" min="0"></div></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="invDesc"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button></div>
</form>
</div></div></div>

<!-- Modal Import HĐĐT XML -->
<div class="modal fade" id="importEinvModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="importEinvForm">
<div class="modal-header"><h5 class="modal-title">Import hóa đơn điện tử (XML)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label">Chọn file XML hóa đơn từ nhà cung cấp</label>
        <input type="file" class="form-control" id="einvXmlFile" accept=".xml" required>
        <div class="form-text">Chấp nhận file XML theo chuẩn TCT (TT 32/2025/TT-BTC). Hỗ trợ mẫu 01GTKT.</div>
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="autoGoodsReceipt" checked>
        <label class="form-check-label" for="autoGoodsReceipt">Tự động tạo phiếu nhập kho (PNK) từ hóa đơn</label>
    </div>
    <div id="importEinvPreview" class="d-none">
        <hr>
        <h6 class="text-primary mb-3">📋 Thông tin hóa đơn</h6>
        <div class="row g-2">
            <div class="col-md-4"><small>Nhà cung cấp</small><div id="previewSupplier" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-3"><small>MST</small><div id="previewTaxCode" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-2"><small>Số hóa đơn</small><div id="previewInvoiceNo" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-2"><small>Ngày</small><div id="previewDate" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-1"><small>Mặt hàng</small><div id="previewItems" class="fw-semibold fs-6">—</div></div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-3"><small>Tiền hàng</small><div id="previewTotalBeforeVat" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-3"><small>Thuế GTGT</small><div id="previewTotalVat" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-3"><small>Tổng thanh toán</small><div id="previewGrandTotal" class="fw-semibold fs-6">—</div></div>
            <div class="col-md-3"><small>Trạng thái</small><div id="previewStatus" class="fw-semibold fs-6 text-success">Mới</div></div>
        </div>
        <hr>
        <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>STT</th><th>Tên hàng hóa</th><th class="text-end">SL</th><th>ĐVT</th><th class="text-end">Đơn giá</th><th class="text-end">Tiền</th><th class="text-end">Thuế</th></tr></thead><tbody id="previewItemsTable"></tbody></table></div>
        <div class="alert alert-info py-2" style="font-size:13px">
            <i class="bi bi-info-circle"></i> Hạch toán tự động: Nợ 156 (Hàng hóa) <span id="previewDrAmount"></span> / Nợ 1331 (Thuế GTGT) <span id="previewVatAmount"></span> / Có 331 (Phải trả NCC) <span id="previewCrAmount"></span>
        </div>
    </div>
    <div id="importEinvDuplicate" class="alert alert-danger d-none py-2 mt-2"><i class="bi bi-exclamation-triangle"></i> Hóa đơn này đã được import trước đó.</div>
    <div id="importEinvError" class="alert alert-warning d-none py-2 mt-2">—</div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="button" class="btn btn-sm btn-outline-primary" id="btnPreviewXml"><i class="bi bi-eye"></i> Xem trước</button>
    <button type="submit" class="btn btn-sm btn-success" id="btnImportXml" disabled><i class="bi bi-file-earmark-arrow-up"></i> Import</button>
</div>
</form>
</div></div></div>

<div class="modal fade" id="prepayModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="prepayForm">
<div class="modal-header"><h5 class="modal-title">Tạm ứng cho nhà cung cấp</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Nhà cung cấp</label><select class="form-select" id="prepaySupplier" data-v-required="Nhà cung cấp" required></select></div>
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="prepayAmount" step="1000" min="1" data-v-required="Số tiền" data-v-number="Số tiền tạm ứng" required></div>
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
    <div class="mb-2"><label>Số tiền thanh toán</label><input type="number" class="form-control" id="payAmount" step="1000" min="1" data-v-required="Số tiền" data-v-number="Số tiền thanh toán" required></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-success">Thanh toán</button></div>
</form>
</div></div></div>

<div class="modal fade" id="discountModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="discountForm">
<div class="modal-header"><h5 class="modal-title">Chiết khấu thanh toán được hưởng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="discountInvId">
    <p class="text-muted">Công nợ còn lại: <strong id="discountBalance"></strong></p>
    <p class="text-muted" style="font-size:12px">Hạch toán: Nợ 331 / Có 515 (Doanh thu HĐTC)</p>
    <div class="mb-2"><label>Số tiền chiết khấu</label><input type="number" class="form-control" id="discountAmount" step="1000" min="1" data-v-required="Số tiền" data-v-number="Số tiền chiết khấu" required></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-warning">Xác nhận chiết khấu</button></div>
</form>
</div></div></div>

<div class="modal fade" id="returnModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="returnForm">
<div class="modal-header"><h5 class="modal-title">Trả lại hàng mua</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="returnInvId">
    <p class="text-muted">Công nợ còn lại: <strong id="returnBalance"></strong></p>
    <p class="text-muted" style="font-size:12px">Hạch toán: Nợ 331 / Có <span id="returnAccountLabel">152</span> + Có 1331 (thuế GTGT hoàn lại)</p>
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền trả lại</label><input type="number" class="form-control" id="returnAmount" step="1000" min="1" data-v-required="Số tiền" data-v-number="Số tiền trả lại" required></div>
    <div class="col-6 mb-2"><label>TK hàng</label><select class="form-select" id="returnAccount" onchange="$('#returnAccountLabel').text(this.value)"><option value="152">152 - NVL</option><option value="156">156 - Hàng hóa</option><option value="153">153 - CCDC</option></select></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-secondary">Xác nhận trả lại</button></div>
</form>
</div></div></div>

<script>
var allData=[];
function loadSuppliers(cb){
    $.get('/api/ap/suppliers', function(list){
        var opts='',fopts='<option value="">Tất cả NCC</option>';
        list.forEach(function(s){
            opts+='<option value="'+esc(s.id)+'">'+esc(s.name)+' ('+fmt(s.balance)+')</option>';
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
        var actions='';
        var aging=calcAgingDays(r.due_date);
        var rowClass='';
        if(aging>90){rowClass=' class="table-danger"';}
        else if(aging>30){rowClass=' class="table-warning"';}
        if(r.status!=='paid'&&r.status!=='written_off'&&r.balance>1){
            actions+='<button class="btn btn-sm btn-outline-success me-1" onclick="openPay('+r.id+','+r.balance+')"><i class="bi bi-cash"></i></button>';
            actions+='<button class="btn btn-sm btn-outline-warning me-1" onclick="openDiscount('+r.id+','+r.balance+')" title="Chiết khấu thanh toán"><i class="bi bi-percent"></i></button>';
            actions+='<button class="btn btn-sm btn-outline-secondary me-1" onclick="openReturn('+r.id+','+r.balance+')" title="Trả lại hàng mua"><i class="bi bi-arrow-return-left"></i></button>';
            actions+='<button class="btn btn-sm btn-outline-danger me-1" onclick="openWriteOff('+r.id+')" title="Xóa sổ công nợ"><i class="bi bi-trash3"></i></button>';
        }
        actions+='<button class="btn btn-sm btn-outline-info me-1" onclick="printInvoice(\''+r.id+'\')" title="In hóa đơn"><i class="bi bi-printer"></i></button>';
        var agingLabel=aging>0?aging+' ngày':'';
        tbody.append('<tr'+rowClass+'><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.supplier_name)+'</td><td style="font-size:12px">'+esc(r.invoice_date)+'</td><td style="font-size:12px">'+esc(r.due_date)+'</td><td class="text-end vas-number">'+fmt(r.gross_amount)+'</td><td class="text-end vas-number">'+fmt(r.paid_amount)+'</td><td class="text-end vas-number">'+fmt(r.balance)+'</td><td class="text-end vas-number" style="font-size:11px">'+agingLabel+'</td><td>'+statusBadge(r.status)+'</td><td>'+actions+'</td></tr>');
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
function openPay(id,bal){$('#payInvId').val(id);$('#payBalance').text(fmt(bal));$('#payAmount').val(bal);$('#payModal').modal('show');}
function openDiscount(id,bal){$('#discountInvId').val(id);$('#discountBalance').text(fmt(bal));$('#discountAmount').val(Math.round(bal*0.5/1000)*1000);$('#discountModal').modal('show');}
function openReturn(id,bal){$('#returnInvId').val(id);$('#returnBalance').text(fmt(bal));$('#returnAmount').val(bal);$('#returnAccount').val('152');$('#returnAccountLabel').text('152');$('#returnModal').modal('show');}
function openWriteOff(id){if(!confirm('Bạn có chắc chắn xóa sổ công nợ này? Thao tác này sẽ tạo bút toán Nợ 331 / Có 711 (Thu nhập khác) và không thể hoàn tác.'))return;$.ajax({url:'/api/ap/invoices/'+id+'/write-off',method:'POST',contentType:'application/json',success:function(){FormToast.success('Xóa sổ công nợ thành công');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}});}
async function printInvoice(id){
    try {
        const tplRes = await fetch('/api/print/templates?type=ap_invoice', { headers: { 'X-CSRF-Token': csrf } });
        const tplJson = await tplRes.json();
        const defTpl = (tplJson.data || []).find(t => t.is_default) || (tplJson.data || [])[0];
        if (!defTpl) { FormToast.error('Chưa có mẫu in nào cho hóa đơn mua'); return; }
        const res = await fetch('/api/print/templates/' + defTpl.id + '/render', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ resource_type: 'ap_invoice', resource_id: id })
        });
        const json = await res.json();
        if (!res.ok) { FormToast.error(json.error || 'Lỗi in'); return; }
        const w = window.open('', '_blank');
        w.document.write('<html><head><title>In hóa đơn ' + id + '</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body class="p-4">' + json.html + '</body></html>');
        w.document.close();
        setTimeout(() => w.print(), 300);
    } catch(e) { FormToast.error('Lỗi: ' + e.message); }
}
function calcVat(){var net=parseFloat($('#netAmount').val())||0;var rate=parseFloat($('#vatRate').val())||0;$('#vatAmount').val(Math.round(net*rate/100));}
$('#netAmount,#vatRate').on('input',calcVat);
$('#invForm').submit(function(e){e.preventDefault();
    var v=FormValidation.validate('#invForm');if(!v.valid)return;
    var data={supplier_id:$('#supplierId').val(),invoice_number:$('#invoiceNumber').val(),net_amount:parseFloat($('#netAmount').val()),vat_amount:parseFloat($('#vatAmount').val()),vat_rate:parseFloat($('#vatRate').val()),invoice_date:$('#invDate').val(),due_date:$('#dueDate').val(),inventory_account:$('#invAccount').val(),description:$('#invDesc').val()};
    $.ajax({url:'/api/ap/invoices',method:'POST',contentType:'application/json',data:JSON.stringify(data),
        success:function(){$('#invModal').modal('hide');$('#invForm')[0].reset();FormToast.success('Ghi nhận hóa đơn thành công');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
$('#payForm').submit(function(e){e.preventDefault();
    var v=FormValidation.validate('#payForm');if(!v.valid)return;
    $.ajax({url:'/api/ap/invoices/'+$('#payInvId').val()+'/pay',method:'POST',contentType:'application/json',data:JSON.stringify({amount:parseFloat($('#payAmount').val())}),
        success:function(){$('#payModal').modal('hide');FormToast.success('Thanh toán thành công');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
$('#prepayForm').submit(function(e){e.preventDefault();
    var v=FormValidation.validate('#prepayForm');if(!v.valid)return;
    $.ajax({url:'/api/ap/prepay',method:'POST',contentType:'application/json',data:JSON.stringify({supplier_id:$('#prepaySupplier').val(),amount:parseFloat($('#prepayAmount').val()),description:$('#prepayDesc').val()}),
        success:function(){$('#prepayModal').modal('hide');$('#prepayForm')[0].reset();FormToast.success('Tạm ứng thành công');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
$('#discountForm').submit(function(e){e.preventDefault();
    var v=FormValidation.validate('#discountForm');if(!v.valid)return;
    $.ajax({url:'/api/ap/invoices/'+$('#discountInvId').val()+'/discount',method:'POST',contentType:'application/json',data:JSON.stringify({amount:parseFloat($('#discountAmount').val())}),
        success:function(){$('#discountModal').modal('hide');FormToast.success('Ghi nhận chiết khấu thành công');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
$('#returnForm').submit(function(e){e.preventDefault();
    var v=FormValidation.validate('#returnForm');if(!v.valid)return;
    $.ajax({url:'/api/ap/invoices/'+$('#returnInvId').val()+'/return',method:'POST',contentType:'application/json',data:JSON.stringify({amount:parseFloat($('#returnAmount').val()),inventory_account:$('#returnAccount').val()}),
        success:function(){$('#returnModal').modal('hide');FormToast.success('Ghi nhận trả lại hàng thành công');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
// === Import HĐĐT XML ===
$('#einvXmlFile').on('change', function() {
    $('#importEinvPreview').addClass('d-none');
    $('#importEinvDuplicate').addClass('d-none');
    $('#importEinvError').addClass('d-none');
    $('#btnImportXml').prop('disabled', true);
    $('#btnPreviewXml').prop('disabled', !this.files.length);
});
$('#btnPreviewXml').click(function() {
    const file = $('#einvXmlFile')[0].files[0];
    if (!file) { FormToast.warning('Chọn file XML trước.'); return; }
    const formData = new FormData();
    formData.append('xml_file', file);
    $('#btnPreviewXml').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang đọc...');
    $.ajax({
        url: '/api/einvoice/import/preview',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            const d = res.data;
            if (d.error) { $('#importEinvError').removeClass('d-none').text(d.error); return; }
            $('#previewSupplier').text(d.supplier.name || '—');
            $('#previewTaxCode').text(d.supplier.tax_code || '—');
            $('#previewInvoiceNo').text(d.invoice_number || '—');
            $('#previewDate').text(d.invoice_date || '—');
            $('#previewItems').text(d.items ? d.items.length : 0);
            $('#previewTotalBeforeVat').text(fmt(d.totals.total_before_vat));
            $('#previewTotalVat').text(fmt(d.totals.total_vat));
            $('#previewGrandTotal').text(fmt(d.totals.grand_total));
            $('#previewDrAmount').text(fmt(d.totals.total_before_vat));
            $('#previewVatAmount').text(d.totals.total_vat > 0 ? fmt(d.totals.total_vat) : '0 ₫');
            $('#previewCrAmount').text(fmt(d.totals.grand_total));
            if (d.is_duplicate) {
                $('#previewStatus').text('Đã import').removeClass('text-success').addClass('text-danger');
                $('#importEinvDuplicate').removeClass('d-none');
                $('#btnImportXml').prop('disabled', true);
            } else {
                $('#previewStatus').text('Mới').removeClass('text-danger').addClass('text-success');
                $('#importEinvDuplicate').addClass('d-none');
                $('#btnImportXml').prop('disabled', false);
            }
            // Items table
            var itemsHtml = '';
            if (d.items) {
                d.items.forEach(function(item, i) {
                    itemsHtml += '<tr><td>' + (i+1) + '</td><td>' + escapeHtml(item.product_name || '') + '</td>'
                        + '<td class="text-end">' + (item.quantity || 0) + '</td>'
                        + '<td>' + (item.unit || '') + '</td>'
                        + '<td class="text-end">' + fmt(item.unit_price || 0) + '</td>'
                        + '<td class="text-end">' + fmt(item.total_before_vat || 0) + '</td>'
                        + '<td class="text-end">' + (item.vat_rate !== undefined ? item.vat_rate + '%' : '—') + '</td></tr>';
                });
            }
            $('#previewItemsTable').html(itemsHtml);
            $('#importEinvPreview').removeClass('d-none');
            $('#importEinvError').addClass('d-none');
        },
        error: function(x) {
            var m = 'Lỗi đọc XML';
            try { m = JSON.parse(x.responseText).error; } catch(e) {}
            if (x.status === 422) { $('#importEinvError').removeClass('d-none').text('⚠ ' + m); }
            else { FormToast.error(m); }
        },
        complete: function() {
            $('#btnPreviewXml').prop('disabled', false).html('<i class="bi bi-eye"></i> Xem trước');
        }
    });
});
$('#importEinvForm').submit(function(e) {
    e.preventDefault();
    if ($('#btnImportXml').prop('disabled')) return;
    if (!confirm('Xác nhận import hóa đơn này? Hệ thống sẽ tự động tạo chứng từ mua hàng.')) return;
    const file = $('#einvXmlFile')[0].files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('xml_file', file);
    formData.append('csrf_token', csrf);
    formData.append('auto_goods_receipt', $('#autoGoodsReceipt').is(':checked') ? '1' : '0');
    $('#btnImportXml').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang import...');
    $.ajax({
        url: '/api/einvoice/import',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $('#importEinvModal').modal('hide');
            $('#importEinvForm')[0].reset();
            $('#importEinvPreview').addClass('d-none');
            var msg = 'Import HĐĐT thành công! Đã tạo chứng từ: ' + res.data.description;
            if (res.data.goods_receipt_id) msg += ' & PNK: ' + res.data.goods_receipt_id;
            FormToast.success(msg);
            loadData();
        },
        error: function(x) {
            var m = 'Lỗi import';
            try { m = JSON.parse(x.responseText).error; } catch(e) {}
            FormToast.error(m);
        },
        complete: function() {
            $('#btnImportXml').html('<i class="bi bi-file-earmark-arrow-up"></i> Import');
        }
    });
});
$(document).ready(function(){loadSuppliers();loadData();loadVatRates('#vatRate',10);FormValidation.setup('#invForm');FormValidation.setup('#payForm');FormValidation.setup('#prepayForm');FormValidation.setup('#discountForm');FormValidation.setup('#returnForm');});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
