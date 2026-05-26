<?php // Màn hình: Nhập và quản lý chứng từ ghi sổ
// API: GET /api/periods, GET /api/transactions, POST /api/journal/draft, POST /api/journal/approve/{id}
// Nghiệp vụ: Định khoản Nợ/Có với nhiều dòng, kiểm tra Dr=Cr, lưu nháp hoặc ghi sổ trực tiếp
// Rủi ro: Bút toán mất cân đối Dr≠Cr sẽ sai báo cáo tài chính — kiểm tra frontend trước khi submit
$title = 'Chứng từ ghi sổ'; $activeMenu = 'journal'; ob_start(); ?>
<div class="toolbar">
    <h5>Chứng từ ghi sổ</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="periodFilter">
            <option value="">-- Chọn kỳ --</option>
        </select>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#entryModal"><i class="bi bi-plus-lg"></i> Nhập bút toán</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Số CT</th><th>Diễn giải</th><th>Ngày</th><th>TK Nợ</th><th>TK Có</th><th>Số tiền</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="entryModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="entryForm">
<div class="modal-header"><h5 class="modal-title">Nhập bút toán</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2 mb-2">
        <div class="col-6"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Nội dung nghiệp vụ" required></div>
        <div class="col-3"><label>Ngày</label><input type="date" class="form-control" id="txnDate"></div>
        <div class="col-3"><label>Số CT</label><input class="form-control" id="reference" placeholder="Để trống = tự động"></div>
    </div>
    <div class="mb-2"><label class="d-flex justify-content-between"><span>Định khoản</span><span id="drCrStatus" class="text-success fw-bold">Nợ = Có (0)</span></label></div>
    <div id="linesContainer">
        <div class="row g-1 mb-1 line-row">
            <div class="col-5"><select class="form-select form-select-sm acc-picker" required><option value="">-- TK --</option></select></div>
            <div class="col-2"><select class="form-select form-select-sm dr-cr"><option value="1">Nợ</option><option value="0">Có</option></select></div>
            <div class="col-4"><input type="number" class="form-control form-control-sm amount-input" step="1" min="1" placeholder="Số tiền"></div>
            <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger add-line"><i class="bi bi-plus-lg"></i></button></div>
        </div>
    </div>
    <div class="row g-1 mt-1 pt-1 border-top">
        <div class="col-5"></div>
        <div class="col-2 text-end fw-bold">Tổng:</div>
        <div class="col-4"><span id="totalAmount" class="fw-bold font-monospace">0</span></div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-outline-primary" id="saveDraftBtn"><i class="bi bi-save"></i> Lưu nháp</button>
</div>
</form>
</div></div></div>

<script>
// Tải danh sách kỳ kế toán từ API, mặc định chọn tháng hiện tại
function loadPeriods(){
    $.get('/api/periods',function(data){
        var sel=$('#periodFilter');sel.html('<option value="">Tất cả kỳ</option>');
        data.forEach(function(p){if(p.period_type==='month')sel.append('<option value="'+esc(p.period_code)+'">'+esc(p.name)+'</option>');});
        var m=('0'+(new Date().getMonth()+1)).slice(-2);
        sel.val(new Date().getFullYear()+'-'+m);loadData();
    });
}
// Tải danh sách bút toán theo kỳ, hiển thị trạng thái (nháp/đã ghi sổ) và nút duyệt
function loadData(){
    var period=$('#periodFilter').val()||'';
    $.get('/api/transactions?period='+period,function(data){
        var tbody=$('#dataBody');tbody.empty();
        if(!data.length){tbody.append('<tr><td colspan="8" class="text-center text-muted py-4">Chưa có bút toán</td></tr>');return;}
        data.forEach(function(r){
            var drLines=r.lines.filter(function(l){return l.is_debit;});
            var crLines=r.lines.filter(function(l){return !l.is_debit;});
            var drStr=drLines.map(function(l){return l.account_code;}).join(', ');
            var crStr=crLines.map(function(l){return l.account_code;}).join(', ');
            var total=r.lines.reduce(function(s,l){return s+parseFloat(l.amount);},0)/2;
            var badge=r.status==='posted'?'<span class="badge-status badge-active">Đã ghi sổ</span>':'<span class="bg-warning text-dark">Nháp</span>';
            var actions='';
            if(r.status==='pending')actions='<button class="btn btn-sm btn-outline-success" onclick="approveEntry(\''+esc(r.id)+'\')"><i class="bi bi-check-lg"></i> Duyệt</button>';
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td style="font-size:12px">'+esc(r.date)+'</td><td>'+esc(drStr)+'</td><td>'+esc(crStr)+'</td><td class="text-end font-monospace">'+parseFloat(total).toLocaleString()+'</td><td>'+badge+'</td><td>'+actions+'</td></tr>');
        });
    });
}
function loadData(){
    var period=$('#periodFilter').val()||'';
    $.get('/api/transactions?period='+period,function(data){
        var tbody=$('#dataBody');tbody.empty();
        if(!data.length){tbody.append('<tr><td colspan="8" class="text-center text-muted py-4">Chưa có bút toán</td></tr>');return;}
        data.forEach(function(r){
            var drLines=r.lines.filter(function(l){return l.is_debit;});
            var crLines=r.lines.filter(function(l){return !l.is_debit;});
            var drStr=drLines.map(function(l){return l.account_code;}).join(', ');
            var crStr=crLines.map(function(l){return l.account_code;}).join(', ');
            var total=r.lines.reduce(function(s,l){return s+parseFloat(l.amount);},0)/2;
            var badge=r.status==='posted'?'<span class="badge-status badge-active">Đã ghi sổ</span>':'<span class="badge bg-warning text-dark">Nháp</span>';
            var actions='';
            if(r.status==='pending')actions='<button class="btn btn-sm btn-outline-success" onclick="approveEntry(\''+esc(r.id)+'\')"><i class="bi bi-check-lg"></i> Duyệt</button>';
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td style="font-size:12px">'+esc(r.date)+'</td><td>'+esc(drStr)+'</td><td>'+esc(crStr)+'</td><td class="text-end font-monospace">'+parseFloat(total).toLocaleString()+'</td><td>'+badge+'</td><td>'+actions+'</td></tr>');
        });
    });
}
// Duyệt và ghi sổ bút toán — gọi POST /api/journal/approve/{id}
// RỦI RO: Sau khi duyệt, bút toán không thể sửa/xóa — cần xác nhận trước
function approveEntry(id){
    if(!confirm('Duyệt bút toán này? Giao dịch sẽ được ghi sổ.'))return;
    $.ajax({url:'/api/journal/approve/'+id,method:'POST',headers:{'X-CSRF-Token':csrf},
        success:function(){showToast('Đã ghi sổ bút toán','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}
function loadAccounts(sel){
    $.get('/api/coa',function(data){
        var o='<option value="">-- TK --</option>';
        data.forEach(function(a){o+='<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+'</option>';});
        sel.html(o);
    });
}
// Tính toán và hiển thị trạng thái cân đối Nợ/Có real-time
// Nghiệp vụ: Tổng Dr phải = Tổng Cr (sai lệch tối đa ±10 VND do làm tròn)
// Nếu lệch > 10 VND → cảnh báo đỏ, không cho submit
function recalcTotal(){
    var totalDr=0,totalCr=0;
    $('#linesContainer .line-row').each(function(){
        var amt=parseFloat($(this).find('.amount-input').val())||0;
        var isDr=parseInt($(this).find('.dr-cr').val());
        if(isDr)totalDr+=amt;else totalCr+=amt;
    });
    var diff=Math.abs(totalDr-totalCr);
    if(diff<10){$('#drCrStatus').text('Nợ = Có ('+totalDr.toLocaleString()+')').removeClass().addClass('text-success fw-bold');}
    else{$('#drCrStatus').text('CHÊNH LỆCH: Nợ '+totalDr.toLocaleString()+' / Có '+totalCr.toLocaleString()).removeClass().addClass('text-danger fw-bold');}
    $('#totalAmount').text(totalDr.toLocaleString());
}
// Thêm dòng định khoản mới — tối thiểu 2 dòng (1 Nợ + 1 Có)
// Nghiệp vụ: Mỗi bút toán cần ít nhất 1 Nợ và 1 Có, tổng tiền bằng nhau
$(document).on('click','.add-line',function(){
    var row=$('.line-row:first').clone();
    row.find('.acc-picker').html('<option value="">-- TK --</option>');row.find('.amount-input').val('');
    row.find('.add-line').removeClass('btn-outline-danger add-line').addClass('btn-outline-secondary remove-line').html('<i class="bi bi-dash-lg"></i>');
    $('#linesContainer').append(row);
    loadAccounts(row.find('.acc-picker'));
    recalcTotal();
});
$(document).on('click','.remove-line',function(){$(this).closest('.line-row').remove();recalcTotal();});
$(document).on('change input','.amount-input,.dr-cr',recalcTotal);
// Submit form tạo bút toán nháp — POST /api/journal/draft
// Validate frontend:
//   1. Ít nhất 2 dòng định khoản
//   2. Tổng Nợ = Tổng Có (dung sai ±10 VND)
//   3. Mỗi dòng phải có tài khoản và số tiền
// RỦI RO: Nếu bỏ qua kiểm tra Dr=Cr, bút toán sẽ làm sai bảng CĐPS
$('#entryForm').submit(function(e){e.preventDefault();
    var lines=[];$('#linesContainer .line-row').each(function(){
        var ac=$(this).find('.acc-picker').val();var amt=$(this).find('.amount-input').val();var dr=$(this).find('.dr-cr').val();
        if(ac&&amt)lines.push({account_code:ac,amount:parseFloat(amt),is_debit:parseInt(dr)?true:false});
    });
    if(lines.length<2){showToast('Cần ít nhất 2 dòng định khoản','error');return;}
    var totalDr=0,totalCr=0;
    lines.forEach(function(l){if(l.is_debit)totalDr+=l.amount;else totalCr+=l.amount;});
    if(Math.abs(totalDr-totalCr)>10){showToast('Nợ và Có không cân bằng','error');return;}
    $.ajax({url:'/api/journal/draft',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},
        data:JSON.stringify({description:$('#description').val(),reference:$('#reference').val(),lines:lines,transaction_date:$('#txnDate').val()}),
        success:function(){$('#entryModal').modal('hide');$('#entryForm')[0].reset();$('#linesContainer .line-row:not(:first)').remove();showToast('Đã lưu bút toán nháp','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){
    loadPeriods();
    $('#periodFilter').on('change',loadData);
    $('.line-row:first').each(function(){loadAccounts($(this).find('.acc-picker'));});
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
