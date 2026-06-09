<?php
// Mẫu số 02-VT (Phiếu xuất kho) theo Thông tư 99/2025/TT-BTC
// API: GET /api/inventory/issues/list (danh sách PXK)
//      POST /api/inventory/issues/draft (tạo nháp)
//      GET /api/inventory/issues/{id} (chi tiết)
//      POST /api/inventory/issues/{id}/post (ghi sổ)
//      POST /api/inventory/issues/{id}/cancel (hủy)
// Nghiệp vụ: Xuất kho — Nợ 632 (giá vốn)/Có 152,156 (hàng hóa)
//            hoặc Nợ 621/622/627/Có 152 (sản xuất)
//            hoặc Nợ 241/Có 152 (XDCB)
// Phương pháp tính giá: FIFO hoặc Bình quân gia quyền
// Rủi ro: Sai loại xuất → sai TK đối ứng → sai BC02
$title = 'Phiếu xuất kho (Mẫu 02-VT)';
$activeMenu = 'issue';
ob_start(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
.tt99-grid { border: 1px solid #dee2e6; }
.tt99-grid th { background: #f8f9fa; font-size: 12px; text-align: center; vertical-align: middle; padding: 6px 4px; border: 1px solid #dee2e6; }
.tt99-grid td { border: 1px solid #dee2e6; padding: 4px; vertical-align: middle; }
.tt99-grid .line-remove { color: #dc3545; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 4px; }
.tt99-grid .line-remove:hover { color: #a71d2a; }
.tt99-header { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 16px; }
.tt99-signature { border-top: 1px solid #dee2e6; margin-top: 24px; padding-top: 16px; }
.tt99-signature .sig-col { text-align: center; font-size: 12px; }
.tt99-signature .sig-col .sig-line { border-top: 1px solid #333; width: 80%; margin: 40px auto 4px; padding-top: 4px; }
.badge-draft { background: #fff3cd; color: #856404; }
.badge-posted { background: #d4edda; color: #155724; }
.badge-cancelled { background: #f8d7da; color: #721c24; }
</style>

<div class="toolbar">
    <h5><i class="bi bi-box-arrow-right me-1"></i> Phiếu xuất kho (Mẫu 02-VT)</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filterStatus">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="draft">Nháp</option>
            <option value="posted">Đã ghi sổ</option>
            <option value="cancelled">Đã hủy</option>
        </select>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'phieu-xuat-kho')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" onclick="showCreateForm()"><i class="bi bi-plus-lg"></i> Tạo PXK mới</button>
    </div>
</div>

<div id="listView">
    <div class="card-table">
        <div class="card-header-x">
            <input type="text" id="searchInput" placeholder="🔍 Tìm kiếm..." onkeyup="filterList()">
            <span class="stats ms-auto" id="recordCount">0 bản ghi</span>
        </div>
        <table class="table table-hover">
            <thead><tr>
                <th>Số PXK</th>
                <th>Ngày</th>
                <th>Loại</th>
                <th>Người nhận</th>
                <th class="text-end">Tổng tiền</th>
                <th>Trạng thái</th>
                <th></th>
            </tr></thead>
            <tbody id="dataBody"></tbody>
        </table>
    </div>
</div>

<div id="formView" style="display:none">
    <div class="card">
        <div class="card-header bg-white tt99-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0" id="formTitle">Tạo phiếu xuất kho</h5>
                <small class="text-muted" id="formSubtitle">Mẫu số 02-VT (Kèm theo TT 99/2025/TT-BTC)</small>
            </div>
            <div>
                <button class="btn btn-outline-secondary btn-sm" onclick="showList()"><i class="bi bi-arrow-left"></i> Quay lại</button>
                <button class="btn btn-success btn-sm d-none" id="btnPrint" onclick="printPXK()"><i class="bi bi-printer"></i> In PXK</button>
            </div>
        </div>
        <div class="card-body">
            <form id="issueForm">
                <input type="hidden" id="issueId">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Số PXK</label>
                        <input type="text" class="form-control form-control-sm" id="issueNumber" readonly placeholder="(tự động)">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Ngày xuất <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm datepicker" id="issueDate" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Kho xuất</label>
                        <select class="form-select form-select-sm" id="warehouseId">
                            <option value="">-- Chọn kho --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Loại xuất <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="issueType">
                            <option value="sale">Bán hàng (Nợ 632)</option>
                            <option value="production">Sản xuất (Nợ 621/622/627)</option>
                            <option value="construction">XDCB (Nợ 241)</option>
                            <option value="internal">Nội bộ</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Người nhận hàng <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="receiverName" placeholder="Họ tên người nhận">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Bộ phận / Địa chỉ</label>
                        <input type="text" class="form-control form-control-sm" id="receiverDepartment" placeholder="Bộ phận sử dụng">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Số CT gốc kèm theo</label>
                        <input type="text" class="form-control form-control-sm" id="reference" placeholder="HĐ/mẫu CT liên quan">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Lý do xuất <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="issueReason" placeholder="VD: Xuất bán hàng cho KH..., Xuất NVL sản xuất...">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Ghi chú</label>
                    <input type="text" class="form-control form-control-sm" id="notes" placeholder="Ghi chú thêm (nếu có)">
                </div>

                <h6 class="border-bottom pb-2 mb-2">
                    <i class="bi bi-list-columns-reverse me-1"></i> Danh sách vật tư, hàng hóa
                    <span class="text-muted small fw-normal ms-2">Cột 1: Yêu cầu, Cột 2: Thực xuất, Cột 3: Đơn giá, Cột 4: Thành tiền</span>
                </h6>

                <div class="tt99-grid mb-2">
                    <table class="table mb-0" id="linesGrid">
                        <thead>
                            <tr>
                                <th width="4%">STT</th>
                                <th width="26%">Tên vật tư, hàng hóa</th>
                                <th width="8%">Mã số</th>
                                <th width="7%">ĐVT</th>
                                <th width="9%">SL Yêu cầu<br><small>(1)</small></th>
                                <th width="9%">SL Thực xuất<br><small>(2)</small></th>
                                <th width="12%">Đơn giá<br><small>(3)</small></th>
                                <th width="15%">Thành tiền<br><small>(4=2x3)</small></th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="linesBody"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addLine()"><i class="bi bi-plus"></i> Thêm dòng</button>

                <div class="row g-2 mb-3">
                    <div class="col-md-4 offset-md-8">
                        <table class="table table-sm mb-0">
                            <tr><td class="text-end fw-bold">Cộng:</td><td class="text-end fw-bold font-monospace" id="totalAmountDisplay">0</td></tr>
                            <tr><td class="text-end fw-bold">Bằng chữ:</td><td id="totalInWords" class="text-muted small">Không</td></tr>
                        </table>
                    </div>
                </div>

                <div class="tt99-signature row g-2">
                    <div class="col sig-col"><div class="sig-line">Người lập phiếu</div><small>(Ký, họ tên)</small></div>
                    <div class="col sig-col"><div class="sig-line">Người nhận hàng</div><small>(Ký, họ tên)</small></div>
                    <div class="col sig-col"><div class="sig-line">Thủ kho</div><small>(Ký, họ tên)</small></div>
                    <div class="col sig-col"><div class="sig-line">Kế toán trưởng</div><small>(Ký, họ tên)</small></div>
                    <div class="col sig-col"><div class="sig-line">Giám đốc</div><small>(Ký, họ tên)</small></div>
                </div>

                <div class="mt-3 d-flex gap-2" id="formActions">
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSaveDraft"><i class="bi bi-save"></i> Lưu nháp</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="btnPost" onclick="postIssue()"><i class="bi bi-check-lg"></i> Ghi sổ</button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="btnCancel" onclick="cancelIssue()"><i class="bi bi-x-lg"></i> Hủy PXK</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var items = [], warehouses = [], editingId = null;

// Hàm tiện ích
function esc(s){ return $('<span>').text(s).html(); }
function badge(s){ return statusBadge(s); }
function fmt(n){ return VAS.fmt(n||0); }
function typeLabel(t){ var m={sale:'Bán hàng',production:'SX',construction:'XDCB',internal:'Nội bộ',other:'Khác'}; return m[t]||t; }

// Tải danh mục
function loadItems(){ $.get('/api/inventory/issue/items',function(d){ items=d.data||d||[]; }); }
function loadWarehouses(){
    $.get('/api/transfers/warehouses',function(d){
        var data=d.data||d||[];
        data.forEach(function(w){ $('#warehouseId').append('<option value="'+esc(w.id)+'">'+esc(w.code||w.name)+' - '+esc(w.name)+'</option>'); });
        warehouses=data;
    });
}

// Danh sách PXK
function loadData(){
    var status=$('#filterStatus').val();
    var url='/api/inventory/issues/list'+(status?'?status='+status:'');
    $.get(url,function(data){
        var tbody=$('#dataBody'), search=$('#searchInput').val().toLowerCase();
        tbody.empty();
        var filtered=data.filter(function(r){return !search||r.issue_number.toLowerCase().includes(search)||(r.receiver_name||'').toLowerCase().includes(search);});
        $('#recordCount').text(filtered.length+' bản ghi');
        if(filtered.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Chưa có phiếu xuất kho</td></tr>');return;}
        filtered.forEach(function(r){
            tbody.append('<tr><td class="fw-bold">'+esc(r.issue_number)+'</td><td>'+(r.issue_date||'')+'</td><td>'+typeLabel(r.issue_type)+'</td><td>'+esc(r.receiver_name||'')+'</td><td class="text-end font-monospace">'+fmt(r.total_amount)+'</td><td>'+badge(r.status)+'</td><td><button class="btn btn-sm btn-outline-primary" onclick="viewIssue(\''+r.id+'\')"><i class="bi bi-eye"></i></button></td></tr>');
        });
    });
}

// Xem chi tiết PXK
function viewIssue(id){
    $.get('/api/inventory/issues/'+id,function(r){
        var d=r.data||r;
        editingId=d.id;
        $('#issueId').val(d.id);
        $('#issueNumber').val(d.issue_number);
        $('#issueDate').val(d.issue_date);
        $('#warehouseId').val(d.warehouse_id||'');
        $('#issueType').val(d.issue_type);
        $('#receiverName').val(d.receiver_name||'');
        $('#receiverDepartment').val(d.receiver_department||'');
        $('#issueReason').val(d.issue_reason||'');
        $('#reference').val(d.reference||'');
        $('#notes').val(d.notes||'');
        $('#totalAmountDisplay').text(fmt(d.total_amount));
        $('#totalInWords').text(d.total_amount>0?amountToWords(d.total_amount)+' đồng':'Không');
        $('#formTitle').text('Phiếu xuất kho: '+d.issue_number);
        $('#btnPrint').removeClass('d-none');

        $('#linesBody').empty();
        (d.lines||[]).forEach(function(l){addLineRow(l);});
        updateTotal();

        var st=d.status;
        $('#btnSaveDraft').toggle(st==='draft').prop('disabled',st!=='draft');
        $('#btnPost').toggleClass('d-none',st!=='draft');
        $('#btnCancel').toggleClass('d-none',st!=='draft');
        $('.datepicker, #warehouseId, #issueType, #receiverName, #receiverDepartment, #issueReason, #reference, #notes').prop('disabled',st!=='draft');

        if(st==='draft'){$('#formTitle').text('Sửa phiếu xuất kho: '+d.issue_number);$('#formSubtitle').text('Mẫu số 02-VT - Nháp');}
        else if(st==='posted'){$('#formSubtitle').text('Mẫu số 02-VT - Đã ghi sổ');}
        else {$('#formSubtitle').text('Mẫu số 02-VT - Đã hủy');}

        $('#listView').hide();
        $('#formView').show();
    });
}

// Tạo PXK mới — form trống
function showCreateForm(){
    editingId=null;
    $('#issueForm')[0].reset();
    $('#issueId').val('');
    $('#issueNumber').val('');
    $('#issueDate').val(new Date().toISOString().slice(0,10));
    $('#totalAmountDisplay').text('0');
    $('#totalInWords').text('Không');
    $('#linesBody').empty();
    addLine();
    $('#btnSaveDraft').text('Lưu nháp').prop('disabled',false).show();
    $('#btnPost').addClass('d-none');
    $('#btnCancel').addClass('d-none');
    $('#btnPrint').addClass('d-none');
    $('.datepicker, #warehouseId, #issueType, #receiverName, #receiverDepartment, #issueReason, #reference, #notes').prop('disabled',false);
    $('#formTitle').text('Tạo phiếu xuất kho mới');
    $('#formSubtitle').text('Mẫu số 02-VT (Kèm theo TT 99/2025/TT-BTC)');
    $('#listView').hide();
    $('#formView').show();
}

function showList(){
    $('#formView').hide();
    $('#listView').show();
    loadData();
}

// Dòng vật tư
function addLineRow(line){
    var i=$('#linesBody tr').length+1;
    var opts='<option value="">-- Chọn --</option>';
    items.forEach(function(it){opts+='<option value="'+esc(it.id)+'" data-code="'+esc(it.code||'')+'" data-uom="'+esc(it.uom||'')+'" data-name="'+esc(it.name)+'" '+(line&&line.item_id===it.id?'selected':'')+'>'+esc(it.code)+' - '+esc(it.name)+'</option>';});
    var qtyReq=line?line.requested_qty:'';
    var qtyAct=line?line.actual_qty:'';
    var price=line?line.unit_price:'';
    var amount=line?line.total_amount:'';
    $('#linesBody').append('<tr data-idx="'+i+'">'+
        '<td class="text-center">'+i+'</td>'+
        '<td><select class="form-select form-select-sm item-select" onchange="onItemChange(this)">'+opts+'</select></td>'+
        '<td class="text-center item-code">'+(line?esc(line.item_code):'')+'</td>'+
        '<td class="text-center item-uom">'+(line?esc(line.uom):'')+'</td>'+
        '<td><input type="number" class="form-control form-control-sm qty-req" step="0.01" min="0" value="'+qtyReq+'" oninput="updateLineTotal(this)"></td>'+
        '<td><input type="number" class="form-control form-control-sm qty-act" step="0.01" min="0" value="'+qtyAct+'" oninput="updateLineTotal(this)"></td>'+
        '<td><input type="text" class="form-control form-control-sm unit-price text-end" value="'+price+'" readonly style="background:#f5f5f5"></td>'+
        '<td><input type="text" class="form-control form-control-sm line-total text-end fw-bold" value="'+fmt(amount)+'" readonly style="background:#f5f5f5"></td>'+
        '<td class="text-center"><span class="line-remove" onclick="removeLine(this)">×</span></td>'+
    '</tr>');
}

function addLine(){ addLineRow(null); }

function onItemChange(el){
    var opt=$(el).find(':selected');
    var row=$(el).closest('tr');
    row.find('.item-code').text(opt.data('code')||'');
    row.find('.item-uom').text(opt.data('uom')||'');
}

function updateLineTotal(el){
    var row=$(el).closest('tr');
    var qty=parseFloat(row.find('.qty-act').val())||0;
    // Chỉ tính tiền nếu đã post (có unit_price)
    // Khi ở draft, thành tiền = 0 (chưa biết đơn giá)
    updateTotal();
}

function updateTotal(){
    var total=0;
    $('#linesBody tr').each(function(){
        var amt=$(this).find('.line-total').val();
        var n=parseFloat(amt.replace(/[^0-9\-\.]/g,''))||0;
        total+=n;
    });
    $('#totalAmountDisplay').text(fmt(total));
    $('#totalInWords').text(total>0?amountToWords(total)+' đồng':'Không');
}

// Convert số thành chữ (Việt Nam)
function amountToWords(n){
    if(!n||n===0)return 'Không';
    var digits=['không','một','hai','ba','bốn','năm','sáu','bảy','tám','chín'];
    var units=['','nghìn','triệu','tỷ'];
    var s=Math.round(n).toString();
    var result=[];
    var groups=[];
    while(s.length>3){groups.unshift(s.slice(-3));s=s.slice(0,-3);}
    if(s)groups.unshift(s);
    for(var i=0;i<groups.length;i++){
        var g=groups[i],gi=groups.length-1-i;
        if(parseInt(g)===0)continue;
        var h=parseInt(g[0]),t=parseInt(g[1]),o=parseInt(g[2]);
        if(h>0)result.push(digits[h]+' trăm');
        if(t>0){if(t===1)result.push('mười');else result.push(digits[t]+' mươi');}
        else if(h>0&&o>0)result.push('lẻ');
        if(o>0){
            if(t>1&&o===1)result.push('mốt');
            else if(o===5&&t>0)result.push('lăm');
            else result.push(digits[o]);
        }
        if(gi<units.length)result.push(units[gi]);
    }
    return result.join(' ').charAt(0).toUpperCase()+result.join(' ').slice(1);
}

// Submit tạo nháp
$('#issueForm').submit(function(e){
    e.preventDefault();
    if(!$('#issueReason').val().trim()){showToast('Vui lòng nhập lý do xuất kho.','error');return;}
    if(!$('#receiverName').val().trim()){showToast('Vui lòng nhập người nhận hàng.','error');return;}
    var lines=[];
    $('#linesBody tr').each(function(){
        var itemId=$(this).find('.item-select').val();
        var qtyReq=parseFloat($(this).find('.qty-req').val())||0;
        var qtyAct=parseFloat($(this).find('.qty-act').val())||qtyReq;
        if(itemId&&qtyAct>0)lines.push({item_id:itemId,requested_qty:qtyReq,actual_qty:qtyAct});
    });
    if(lines.length===0){showToast('Vui lòng thêm ít nhất một dòng vật tư với số lượng > 0.','error');return;}

    var payload={
        issue_date: $('#issueDate').val(),
        warehouse_id: $('#warehouseId').val()||null,
        issue_type: $('#issueType').val(),
        receiver_name: $('#receiverName').val().trim(),
        receiver_department: $('#receiverDepartment').val().trim()||null,
        issue_reason: $('#issueReason').val().trim(),
        reference: $('#reference').val().trim()||null,
        notes: $('#notes').val().trim()||null,
        lines: lines
    };

    $.ajax({
        url: '/api/inventory/issues/draft',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(r){
            var d=r.data||r;
            showToast('Đã tạo phiếu xuất kho: '+d.issue_number,'success');
            viewIssue(d.id);
        },
        error: function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

// Ghi sổ
function postIssue(){
    var id=$('#issueId').val();
    if(!id||!confirm('Xác nhận ghi sổ phiếu xuất kho? Hệ thống sẽ tạo bút toán kế toán và giảm tồn kho.'))return;
    $.ajax({
        url:'/api/inventory/issues/'+id+'/post',
        method:'POST',
        success:function(r){
            showToast('Đã ghi sổ phiếu xuất kho thành công.','success');
            viewIssue(id);
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

// Hủy
function cancelIssue(){
    var id=$('#issueId').val();
    if(!id||!confirm('Xác nhận hủy phiếu xuất kho này?'))return;
    $.ajax({
        url:'/api/inventory/issues/'+id+'/cancel',
        method:'POST',
        success:function(){showToast('Đã hủy phiếu xuất kho.','success');showList();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

// Xóa dòng
function removeLine(el){$(el).closest('tr').remove();updateTotal();}

// Lọc danh sách
function filterList(){loadData();}
$('#filterStatus').on('change',function(){loadData();});

// In — mở print dialog
function printPXK(){
    window.print();
}

// Flatpickr
$(document).ready(function(){
    flatpickr('.datepicker',{dateFormat:'Y-m-d',locale:'vn'});
    loadItems();loadWarehouses();loadData();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
