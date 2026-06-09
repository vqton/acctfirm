<?php // Màn hình: Quản lý tạm ứng nhân viên (TK 141)
// API: GET /api/petty-cash/funds, GET /api/employees, POST /api/petty-cash/funds, POST /api/petty-cash/disburse, POST /api/petty-cash/replenish, POST /api/petty-cash/close
// Nghiệp vụ: TK 141 — Tạm ứng cho nhân viên (lập quỹ → tạm ứng → hoàn ứng → đóng quỹ)
// Hạch toán: Lập quỹ (Nợ 141/Có 1111), Tạm ứng (Nợ 141/Có 1111), Hoàn ứng (Nợ 1111/Có 141)
// Rủi ro: Hoàn ứng vượt quá số dư quỹ sẽ dẫn đến số dư âm TK 141
$title = 'Tạm ứng'; $activeMenu = 'petty_cash'; ob_start(); ?>
<div class="toolbar">
    <h5>Tạm ứng <span class="stats">(TK 141)</span></h5>
    <div>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'tam-ung')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fundModal"><i class="bi bi-plus-lg"></i> Lập quỹ</button>
        <button class="btn btn-outline-success btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#disburseModal"><i class="bi bi-cash"></i> Tạm ứng</button>
        <button class="btn btn-outline-warning btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#repModal"><i class="bi bi-arrow-repeat"></i> Hoàn ứng</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Quỹ</th><th>Nhân viên</th><th>Số tiền</th><th>Số dư</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="fundModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="fundForm">
<div class="modal-header"><h5 class="modal-title">Lập quỹ tạm ứng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="fundId"><div class="mb-2"><label>Nhân viên</label><select class="form-select" id="fundEmployee" required></select></div>
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="fundAmount" step="1000" min="1" required></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Lập quỹ</button></div>
</form>
</div></div></div>

<div class="modal fade" id="disburseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="disburseForm">
<div class="modal-header"><h5 class="modal-title">Tạm ứng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Quỹ</label><select class="form-select" id="dFund" required></select></div>
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="dAmount" step="1000" min="1" required></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="dDesc"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-success">Tạm ứng</button></div>
</form>
</div></div></div>

<div class="modal fade" id="repModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="repForm">
<div class="modal-header"><h5 class="modal-title">Hoàn ứng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Quỹ</label><select class="form-select" id="rFund" required></select></div>
    <div class="mb-2"><label>Số tiền hoàn</label><input type="number" class="form-control" id="rAmount" step="1000" min="1" required></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-warning">Hoàn ứng</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.get('/api/petty-cash/funds',function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var acts=r.status==='active'?'<button class="btn btn-sm btn-outline-danger" onclick="closeFund('+r.id+')"><i class="bi bi-lock"></i></button>':'';acts+='<a href="#" class="btn-action me-1" onclick="printTransaction(\'Phiếu tạm ứng\',\'/api/petty-cash/\'+r.id,{reference:\'Số CT\',transaction_date:\'Ngày\',description:\'Diễn giải\',amount:\'Số tiền\',fund_name:\'Quỹ\'})" title="In chứng từ"><i class="bi bi-printer"></i></a>';
            tbody.append('<tr><td>'+esc(r.fund_name||r.id)+'</td><td>'+esc(r.employee_name||'')+'</td><td class="text-end font-monospace">'+parseFloat(r.total_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td style="font-size:12px">'+esc(r.created_at)+'</td><td>'+statusBadge(r.status)+'</td><td>'+acts+'</td></tr>');
        });
    });
}
function loadFunds(){$.get('/api/petty-cash/funds',function(l){var o='<option value="">-- Chọn quỹ --</option>';l.forEach(function(r){o+='<option value="'+r.id+'">'+esc(r.employee_name||r.id)+' ('+parseFloat(r.balance).toLocaleString()+')</option>';});$('#dFund,#rFund').html(o);});}
function loadEmployees(){$.get('/api/employees',function(l){var o='';l.forEach(function(e){o+='<option value="'+esc(e.id)+'">'+esc(e.name)+'</option>';});$('#fundEmployee').html(o);});}
// Đóng quỹ tạm ứng — POST /api/petty-cash/close
// RỦI RO: Sau khi đóng, không thể tạm ứng thêm; số dư còn lại phải được hoàn ứng trước
function closeFund(id){if(!confirm('Đóng quỹ này?'))return;
    $.ajax({url:'/api/petty-cash/close',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({fund_id:id}),
        success:function(){showToast('Đã đóng quỹ tạm ứng thành công.','success');loadData();loadFunds();},
        error:function(x){showToast('Lỗi','error');}
    });
}
// Submit lập quỹ tạm ứng — POST /api/petty-cash/funds
// Nghiệp vụ: Nợ 141 (TK tạm ứng)/Có 1111 (tiền mặt) — cấp quỹ cho nhân viên
$('#fundForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/petty-cash/funds',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({employee_id:$('#fundEmployee').val(),amount:parseFloat($('#fundAmount').val())}),
        success:function(){$('#fundModal').modal('hide');$('#fundForm')[0].reset();showToast('Đã lập quỹ tạm ứng thành công.','success');loadData();loadFunds();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
// Submit tạm ứng cho nhân viên — POST /api/petty-cash/disburse
// Nghiệp vụ: Nợ 141/Có 1111 — trích quỹ tạm ứng, giảm số dư quỹ
$('#disburseForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/petty-cash/disburse',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({fund_id:$('#dFund').val(),amount:parseFloat($('#dAmount').val()),description:$('#dDesc').val()}),
        success:function(){$('#disburseModal').modal('hide');$('#disburseForm')[0].reset();showToast('Đã ghi nhận tạm ứng thành công.','success');loadData();loadFunds();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
// Submit hoàn ứng — POST /api/petty-cash/replenish
// Nghiệp vụ: Nợ 1111/Có 141 — nhân viên hoàn lại tiền tạm ứng chưa sử dụng
$('#repForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/petty-cash/replenish',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({fund_id:$('#rFund').val(),amount:parseFloat($('#rAmount').val())}),
        success:function(){$('#repModal').modal('hide');$('#repForm')[0].reset();showToast('Đã ghi nhận hoàn ứng thành công.','success');loadData();loadFunds();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadEmployees();loadFunds();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
