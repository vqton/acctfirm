<?php // Màn hình: Đối chiếu số dư ngân hàng với sổ sách
// API: GET /api/bank-reconciliation/sessions, GET /api/bank-reconciliation/bank-accounts, POST /api/bank-reconciliation/start
// Nghiệp vụ: Đối chiếu số dư TK 112 trên sổ kế toán với sao kê ngân hàng
// Quy trình: Tạo phiên → nhập số dư NH → so khớp từng giao dịch → xử lý chênh lệch
// Rủi ro: Chênh lệch không được xử lý sẽ dẫn đến sai số dư tiền gửi trên BC01
$title = 'Đối chiếu ngân hàng'; $activeMenu = 'bank_reconciliation'; ob_start(); ?>
<div class="toolbar">
    <h5>Đối chiếu ngân hàng</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#startModal"><i class="bi bi-plus-lg"></i> Bắt đầu đối chiếu</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>TK NH</th><th>Ngày đối chiếu</th><th class="text-end">SD NH</th><th class="text-end">SD Sổ</th><th class="text-end">Chênh lệch</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="startModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="startForm">
<div class="modal-header"><h5 class="modal-title">Bắt đầu đối chiếu</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Tài khoản ngân hàng</label><select class="form-select" id="bankAccount" required></select></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Ngày đối chiếu</label><input type="date" class="form-control" id="statementDate" value="<?=date('Y-m-d')?>"></div><div class="col-6 mb-2"><label>Số dư NH</label><input type="number" class="form-control" id="statementBalance" step="1000" min="0" required></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Bắt đầu</button></div>
</form>
</div></div></div>

<div class="modal fade" id="entryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="entryForm">
<input type="hidden" id="entrySessionId">
<div class="modal-header"><h5 class="modal-title">Thêm giao dịch NH</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="entryAmount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>Loại</label><select class="form-select" id="entryType"><option value="credit">Thu</option><option value="debit">Chi</option></select></div></div>
    <div class="mb-2"><label>Mô tả</label><input class="form-control" id="entryDesc"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Thêm</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.get('/api/bank-reconciliation/sessions',function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var diff=parseFloat(r.statement_balance)-parseFloat(r.book_balance);
            var badge=r.status==='completed'?'badge-active':(r.status==='in_progress'?'badge-warning':'badge-inactive');
            tbody.append('<tr><td>'+esc(r.bank_account_code)+'</td><td>'+esc(r.statement_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.statement_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.book_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+diff.toLocaleString()+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+(r.status==='in_progress'?'<button class="btn btn-sm btn-outline-primary" onclick="viewSession('+r.id+')"><i class="bi bi-eye"></i></button>':'')+'</td></tr>');
        });
    });
}
function loadBankAccounts(){
    $.get('/api/bank-reconciliation/bank-accounts',function(l){var o='';l.forEach(function(a){o+='<option value="'+a.id+'">'+esc(a.code)+' - '+esc(a.account_number)+'</option>';});$('#bankAccount').html(o);});
}
function viewSession(id){
    window.location='/thu/doi-chieu-ngan-hang?session='+id;
}
// Submit tạo phiên đối chiếu mới — POST /api/bank-reconciliation/start
// Người dùng nhập số dư từ sao kê NH, hệ thống tự động tính chênh lệch với sổ sách
$('#startForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank-reconciliation/start',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({bank_account_code:$('#bankAccount').val(),statement_date:$('#statementDate').val(),statement_balance:parseFloat($('#statementBalance').val())}),
        success:function(r){$('#startModal').modal('hide');showToast('Đã tạo phiên đối chiếu','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadBankAccounts();loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
