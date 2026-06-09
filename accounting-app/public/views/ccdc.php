<?php
// Màn hình: Danh mục công cụ dụng cụ
$title = 'Công cụ dụng cụ';
$activeMenu = 'ccdc';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?> <span class="stats" id="recordCount"></span></h5></div>
    <div><button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'danh-sach-cong-cu-dung-cu')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button><button class="btn btn-primary btn-sm" onclick="openCreate()"><i class="bi bi-plus-lg"></i> Thêm mới</button></div>
</div>
<div class="card-table">
    <div class="card-header-x"><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm..."></div>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead><tr><th>Mã</th><th>Tên</th><th>ĐVT</th><th>Số lượng</th><th>Loại phân bổ</th><th class="text-end">Tổng CP</th><th class="text-end">Đã phân bổ</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
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
            <div class="col-8"><label>Tên *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>ĐVT</label><input type="text" name="unit" id="f_unit" class="form-control form-control-sm"></div>
            <div class="col-4"><label>Số lượng</label><input type="number" name="quantity" id="f_quantity" class="form-control form-control-sm" step="0.01"></div>
            <div class="col-4"><label>Loại phân bổ</label>
                <select name="allocation_type" id="f_allocation_type" class="form-select form-select-sm">
                    <option value="straight_line">Đường thẳng</option>
                    <option value="one_time">Một lần</option>
                </select>
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Tổng CP</label><input type="number" name="total_cost" id="f_total_cost" class="form-control form-control-sm" step="0.01"></div>
            <div class="col-6"><label>Đã phân bổ</label><input type="number" name="allocated" id="f_allocated" class="form-control form-control-sm" step="0.01"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>
<script>
var API = '/api/ccdc';
var editId = null;
function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
        var rows=f.map(function(i){
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+esc(i.unit||'')+'</td><td>'+(i.quantity||0)+'</td><td>'+esc(i.allocation_type||'')+'</td><td class="text-end">'+(i.total_cost||0).toLocaleString()+'</td><td class="text-end">'+(i.allocated||0).toLocaleString()+'</td><td>'+statusBadge(i.status?'active':'inactive')+'</td>'+
                '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="9" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới CCDC';$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    document.getElementById('f_unit').value=i.unit||'';document.getElementById('f_quantity').value=i.quantity;
    document.getElementById('f_allocation_type').value=i.allocation_type||'straight_line';document.getElementById('f_total_cost').value=i.total_cost;
    document.getElementById('f_allocated').value=i.allocated;
    document.getElementById('modalTitle').textContent='Sửa CCDC';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    ['quantity','total_cost','allocated'].forEach(function(k){data[k]=parseFloat(data[k])||0;});
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
