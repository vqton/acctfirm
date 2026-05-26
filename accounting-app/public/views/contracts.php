<?php
// Màn hình: Danh mục hợp đồng kinh tế
$title = 'Danh mục hợp đồng';
$activeMenu = 'contracts';
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
        <thead><tr><th>Mã</th><th>Tên hợp đồng</th><th>Loại</th><th>Đối tác</th><th>Ngày HĐ</th><th class="text-end">Giá trị</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
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
            <div class="col-8"><label>Tên hợp đồng *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Loại</label>
                <select name="contract_type" id="f_contract_type" class="form-select form-select-sm">
                    <option value="purchase">Mua hàng</option>
                    <option value="sale">Bán hàng</option>
                    <option value="service">Dịch vụ</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <div class="col-4"><label>Mã đối tác</label><input type="text" name="party_id" id="f_party_id" class="form-control form-control-sm"></div>
            <div class="col-4"><label>Tên đối tác</label><input type="text" name="party_name" id="f_party_name" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Ngày HĐ</label><input type="date" name="contract_date" id="f_contract_date" class="form-control form-control-sm"></div>
            <div class="col-4"><label>Giá trị</label><input type="number" name="total_amount" id="f_total_amount" class="form-control form-control-sm" step="0.01"></div>
            <div class="col-4"><label>Loại tiền</label>
                <select name="currency" id="f_currency" class="form-select form-select-sm">
                    <option value="VND">VND</option>
                    <option value="USD">USD</option>
                </select>
            </div>
        </div>
        <label>Ghi chú</label><textarea name="notes" id="f_notes" class="form-control form-control-sm" rows="2"></textarea>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>
<script>
var API = '/api/contracts';
var editId = null;
function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q)||(i.party_name||'').toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
        var rows=f.map(function(i){
            var sc=i.status?'badge-active':'badge-inactive';var st=i.status?'Hoạt động':'Ngừng';
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+esc(i.contract_type||'')+'</td><td>'+esc(i.party_name||'')+'</td><td>'+(i.contract_date||'')+'</td><td class="text-end">'+(i.total_amount||0).toLocaleString()+'</td><td><span class="badge badge-status '+sc+'">'+st+'</span></td>'+
                '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới hợp đồng';$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    document.getElementById('f_contract_type').value=i.contract_type||'purchase';document.getElementById('f_party_id').value=i.party_id||'';
    document.getElementById('f_party_name').value=i.party_name||'';document.getElementById('f_contract_date').value=i.contract_date;
    document.getElementById('f_total_amount').value=i.total_amount;document.getElementById('f_currency').value=i.currency||'VND';
    document.getElementById('f_notes').value=i.notes||'';
    document.getElementById('modalTitle').textContent='Sửa hợp đồng';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    data.total_amount=parseFloat(data.total_amount)||0;
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
