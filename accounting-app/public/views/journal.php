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
        <div class="col-4"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Nội dung nghiệp vụ" data-v-required="Diễn giải"></div>
        <div class="col-2"><label>Ngày</label><input type="date" class="form-control" id="txnDate" data-v-required="Ngày chứng từ" data-v-date="Ngày chứng từ"></div>
        <div class="col-3"><label>Số CT</label><input class="form-control" id="reference" placeholder="Để trống = tự động"></div>
        <div class="col-3"><label>Đối tượng</label><input class="form-control partner-picker" id="partnerInput" placeholder="KH/NCC/NV..."></div>
    </div>
    <div class="mb-2"><label class="d-flex justify-content-between"><span>Định khoản</span><span id="drCrStatus" class="text-success fw-bold">Nợ = Có (0)</span></label></div>
    <div id="linesContainer">
        <div class="row g-1 mb-1 line-row">
            <div class="col-5"><select class="form-select form-select-sm acc-picker" required><option value="">-- TK --</option></select><span class="acc-picker-note" style="font-size:10px;color:#6d8aaa;display:block;margin-top:1px;">Gõ mã hoặc tên TK</span></div>
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
    <div class="row g-1 mt-1">
        <div class="col-5"></div>
        <div class="col-2 text-end text-muted" style="font-size:11px;">Bằng chữ:</div>
        <div class="col-5"><span id="amountInWords" class="text-muted" style="font-size:12px;font-style:italic;">—</span></div>
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
            var actions='';
            if(r.status==='pending')actions='<button class="btn btn-sm btn-outline-success" onclick="approveEntry(\''+esc(r.id)+'\')"><i class="bi bi-check-lg"></i> Duyệt</button>';
            else if(r.status==='posted')actions='<button class="btn btn-sm btn-outline-warning" onclick="reverseEntry(\''+esc(r.id)+'\')"><i class="bi bi-arrow-counterclockwise"></i> Đảo ngược</button>';
            tbody.append('<tr><td>'+esc(r.reference)+'</td><td>'+esc(r.description)+'</td><td style="font-size:12px">'+esc(r.date)+'</td><td>'+esc(drStr)+'</td><td>'+esc(crStr)+'</td><td class="text-end vas-number">'+fmtZero(total)+'</td><td>'+statusBadge(r.status)+'</td><td>'+actions+'</td></tr>');
        });
    });
}
// Duyệt và ghi sổ bút toán — gọi POST /api/journal/approve/{id}
// RỦI RO: Sau khi duyệt, bút toán không thể sửa/xóa — cần xác nhận trước
function approveEntry(id){
    FormConfirm.confirm('Duyệt bút toán','Bút toán này sẽ được ghi sổ và không thể sửa/xóa. Tiếp tục?',function(ok){
        if(!ok)return;
        $.ajax({url:'/api/journal/approve/'+id,method:'POST',headers:{'X-CSRF-Token':csrf},
            success:function(){FormToast.success('Đã ghi sổ bút toán thành công.');loadData();},
            error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
        });
    });
}
// R-2: Đảo ngược bút toán đã ghi sổ — tạo bút toán ngược (R-1)
// NGHIỆP VỤ: Khi phát hiện sai sót, tạo bút toán ngược thay vì sửa/xóa (audit trail)
// RỦI RO: Không thể khôi phục sau khi reverse — phải tạo bút toán mới nếu muốn sửa lại
function reverseEntry(id){
    FormConfirm.prompt('Đảo ngược bút toán','Lý do đảo ngược (bắt buộc, ghi vào audit trail):',function(reason){
        if(!reason||!reason.trim())return;
        FormConfirm.confirm('Xác nhận đảo ngược','Hành động này không thể hoàn tác. Tiếp tục?',function(ok){
            if(!ok)return;
            $.ajax({url:'/api/corrections/negative',method:'POST',headers:{'X-CSRF-Token':csrf},
                contentType:'application/json',
                data:JSON.stringify({original_transaction_id:id,reason:reason.trim()}),
                success:function(r){FormToast.success('Đã tạo bút toán ngược: '+r.reference);loadData();},
                error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
            });
        });
    });
}
// Tính toán và hiển thị trạng thái cân đối Nợ/Có real-time
// Nghiệp vụ: Tổng Dr phải = Tổng Cr (sai lệch tối đa ±10 VND do làm tròn)
// Nếu lệch > 10 VND → cảnh báo đỏ, không cho submit
// Amount-in-words: debounced call to /api/utils/to-words
var wordsTimer;
function updateAmountInWords(amount){
    clearTimeout(wordsTimer);
    wordsTimer=setTimeout(function(){
        if(amount<=0){$('#amountInWords').text('—');return;}
        $.get('/api/utils/to-words?amount='+amount,function(r){
            $('#amountInWords').text(r.words||'—');
        }).fail(function(){$('#amountInWords').text('—');});
    },400);
}
function recalcTotal(){
    var totalDr=0,totalCr=0;
    $('#linesContainer .line-row').each(function(){
        var amt=parseFloat($(this).find('.amount-input').val())||0;
        var isDr=parseInt($(this).find('.dr-cr').val());
        if(isDr)totalDr+=amt;else totalCr+=amt;
    });
    var diff=Math.abs(totalDr-totalCr);
    var bal=Math.max(totalDr,totalCr);
    if(diff<10){
        $('#drCrStatus').text('✓ Nợ = Có ('+VAS.fmt(bal)+')').removeClass().addClass('text-success fw-bold');
    }else{
        $('#drCrStatus').html('✗ CHÊNH LỆCH: Nợ '+VAS.fmt(totalDr)+' / Có '+VAS.fmt(totalCr)).removeClass().addClass('text-danger fw-bold');
    }
    $('#totalAmount').text(VAS.fmt(bal));
    updateAmountInWords(bal);
}
// Thêm dòng định khoản mới — tối thiểu 2 dòng (1 Nợ + 1 Có)
// Nghiệp vụ: Mỗi bút toán cần ít nhất 1 Nợ và 1 Có, tổng tiền bằng nhau
$(document).on('click','.add-line',function(){
    var row=$('.line-row:first').clone();
    row.find('.acc-picker').val('');row.find('.amount-input').val('').attr('data-v-required','Số tiền').attr('data-v-number','Số tiền');
    row.find('.add-line').removeClass('btn-outline-danger add-line').addClass('btn-outline-secondary remove-line').html('<i class="bi bi-dash-lg"></i>');
    row.find('.acc-picker-wrapper').remove();
    row.find('.acc-picker').show().data('acc-picker-initialized',false);
    $('#linesContainer').append(row);
    AccountPicker.enhance(row.find('.acc-picker'));
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
    // Validate form fields
    var v=FormValidation.validate('#entryForm');
    if(!v.valid)return;
    var lines=[];$('#linesContainer .line-row').each(function(){
        var ac=$(this).find('.acc-picker').val();var amt=$(this).find('.amount-input').val();var dr=$(this).find('.dr-cr').val();
        if(ac&&amt)lines.push({account_code:ac,amount:parseFloat(amt),is_debit:parseInt(dr)?true:false});
    });
    if(lines.length<2){FormToast.error('Bút toán cần có ít nhất 2 dòng định khoản (Nợ và Có).');return;}
    var totalDr=0,totalCr=0;
    lines.forEach(function(l){if(l.is_debit)totalDr+=l.amount;else totalCr+=l.amount;});
    var drcr=FormValidation.checkDrCr(totalDr,totalCr);
    if(!drcr.valid){FormToast.error(drcr.message);return;}
    var partnerName=$('#partnerInput').val();
    var desc=$('#description').val();
    if(partnerName)desc=(desc?desc+' | ':'')+'ĐT: '+partnerName;
    $.ajax({url:'/api/journal/draft',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},
        data:JSON.stringify({description:desc,reference:$('#reference').val(),lines:lines,transaction_date:$('#txnDate').val()}),
        success:function(){$('#entryModal').modal('hide');$('#entryForm')[0].reset();$('#linesContainer .line-row:not(:first)').remove();FormToast.success('Đã lưu bút toán nháp thành công.');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
$(document).ready(function(){
    loadPeriods();
    $('#periodFilter').on('change',loadData);
    AccountPicker.enhance('.acc-picker');
    PartnerPicker.enhance('.partner-picker');
    FormValidation.setup('#entryForm');
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
