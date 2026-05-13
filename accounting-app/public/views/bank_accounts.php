<?php
$title = 'Tài khoản ngân hàng';
$activeMenu = 'bank_accounts';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?> <span class="stats" id="recordCount"></span></h5></div>
    <div><button class="btn btn-primary btn-sm" onclick="openCreate()"><i class="bi bi-plus-lg"></i> Thêm mới</button></div>
</div>
<div class="card-table">
    <div class="card-header-x"><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm..."></div>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead><tr><th>Mã</th><th>Ngân hàng</th><th>Số TK</th><th>Chủ TK</th><th>Chi nhánh</th><th>Loại tiền</th><th class="text-end">Số dư</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
    </div>
    <div class="pagination-bar"><span id="paginationInfo"></span></div>
</div>
<div class="modal fade" id="dataModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form id="dataForm">
    <div class="modal-header"><h6 class="modal-title" id="modalTitle">Thêm mới</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" name="id" id="dataId">
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Mã *</label><input type="text" name="code" id="f_code" class="form-control form-control-sm" required></div>
            <div class="col-8"><label>Ngân hàng *</label><input type="text" name="bank_name" id="f_bank_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Số TK</label><input type="text" name="account_number" id="f_account_number" class="form-control form-control-sm"></div>
            <div class="col-6"><label>Chủ TK</label><input type="text" name="account_holder" id="f_account_holder" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Chi nhánh</label><input type="text" name="branch" id="f_branch" class="form-control form-control-sm"></div>
            <div class="col-4"><label>Loại tiền</label>
                <select name="currency" id="f_currency" class="form-select form-select-sm">
                    <option value="VND">VND</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
            <div class="col-4"><label>Số dư</label><input type="number" name="opening_balance" id="f_opening_balance" class="form-control form-control-sm" step="0.01"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>
<script>
var API = '/api/bank-accounts';
var editId = null;
function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q)||(i.bank_name||'').toLowerCase().includes(q)||(i.account_number||'').toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
        var rows=f.map(function(i){
            var sc=i.status?'badge-active':'badge-inactive';var st=i.status?'Hoạt động':'Ngừng';
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.bank_name)+'</td><td>'+esc(i.account_number||'')+'</td><td>'+esc(i.account_holder||'')+'</td><td>'+esc(i.branch||'')+'</td><td>'+esc(i.currency||'')+'</td><td class="text-end">'+(i.opening_balance||0).toLocaleString()+'</td><td><span class="badge badge-status '+sc+'">'+st+'</span></td>'+
                '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.bank_name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="9" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới TK ngân hàng';$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_bank_name').value=i.bank_name;
    document.getElementById('f_account_number').value=i.account_number||'';document.getElementById('f_account_holder').value=i.account_holder||'';
    document.getElementById('f_branch').value=i.branch||'';document.getElementById('f_currency').value=i.currency||'VND';
    document.getElementById('f_opening_balance').value=i.opening_balance;
    document.getElementById('modalTitle').textContent='Sửa TK ngân hàng';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    data.opening_balance=parseFloat(data.opening_balance)||0;
    var url=editId?API+'/'+editId:API;var method=editId?'PUT':'POST';
    fetch(url,{method:method,headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(function(r){if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        $('#dataModal').modal('hide');loadData();showToast(editId?'Đã cập nhật':'Đã thêm mới','success');
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
});
$('#searchInput').on('keyup',loadData);
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
