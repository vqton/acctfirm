<?php // Màn hình: Danh mục tài khoản ngân hàng
$title = 'TK ngân hàng'; $activeMenu = 'bank_accounts'; ob_start(); ?>
<div class="toolbar">
    <h5>Tài khoản ngân hàng</h5>
    <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'danh-sach-tai-khoan-ngan-hang')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#baModal"><i class="bi bi-plus-lg"></i> Thêm</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Mã</th><th>Ngân hàng</th><th>Số TK</th><th>Chủ TK</th><th>Chi nhánh</th><th>Tiền tệ</th><th class="text-end">SD đầu</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="baModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="baForm">
<input type="hidden" id="baId">
<div class="modal-header"><h5 class="modal-title">Tài khoản ngân hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-4 mb-2"><label>Mã</label><input class="form-control" id="baCode" required></div><div class="col-8 mb-2"><label>Ngân hàng</label><input class="form-control" id="baBankName" required></div></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Số TK</label><input class="form-control" id="baAccountNumber" required></div><div class="col-6 mb-2"><label>Chủ TK</label><input class="form-control" id="baAccountHolder" required></div></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Chi nhánh</label><input class="form-control" id="baBranch"></div><div class="col-3 mb-2"><label>Tiền tệ</label><select class="form-select" id="baCurrency"><option value="VND">VND</option><option value="USD">USD</option></select></div><div class="col-3 mb-2"><label>SD đầu</label><input type="number" class="form-control" id="baOpening" step="1000" value="0"></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Lưu</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.ajax({url:'/api/bank-accounts',headers:{'X-CSRF-Token':csrf},success:function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            tbody.append('<tr><td>'+esc(r.code)+'</td><td>'+esc(r.bank_name)+'</td><td>'+esc(r.account_number)+'</td><td>'+esc(r.account_holder)+'</td><td>'+esc(r.branch||'')+'</td><td>'+esc(r.currency)+'</td><td class="text-end font-monospace">'+parseFloat(r.opening_balance).toLocaleString()+'</td><td><button class="btn btn-sm btn-outline-primary" onclick="editBA(\''+r.id+'\')"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger" onclick="deleteBA(\''+r.id+'\',\''+esc(r.code)+'\')"><i class="bi bi-trash"></i></button></td></tr>');
        });
    }});
}
function editBA(id){
    $.get('/api/bank-accounts/'+id,function(r){
        $('#baId').val(r.id);$('#baCode').val(r.code);$('#baBankName').val(r.bank_name);$('#baAccountNumber').val(r.account_number);$('#baAccountHolder').val(r.account_holder);$('#baBranch').val(r.branch||'');$('#baCurrency').val(r.currency);$('#baOpening').val(r.opening_balance);
        $('#baModal').modal('show');
    });
}
$('#baForm').submit(function(e){e.preventDefault();
    var id=$('#baId').val(),url=id?'/api/bank-accounts/'+id:'/api/bank-accounts',method=id?'PUT':'POST';
    $.ajax({url:url,method:method,contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({code:$('#baCode').val(),bank_name:$('#baBankName').val(),account_number:$('#baAccountNumber').val(),account_holder:$('#baAccountHolder').val(),branch:$('#baBranch').val(),currency:$('#baCurrency').val(),opening_balance:parseFloat($('#baOpening').val())}),
        success:function(){$('#baModal').modal('hide');$('#baForm')[0].reset();$('#baId').val('');showToast('Đã lưu','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
function deleteBA(id,code){confirmDelete(id,'/api/bank-accounts',code);}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
