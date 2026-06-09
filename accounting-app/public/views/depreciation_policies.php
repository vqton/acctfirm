<?php
// Màn hình: Chính sách khấu hao tài sản cố định
$title = 'Chính sách khấu hao';
$activeMenu = 'depreciation_policies';
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
        <thead><tr><th>Mã</th><th>Tên CS</th><th>PP khấu hao</th><th class="text-end">TG sử dụng</th><th class="text-end">TL còn lại</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
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
            <div class="col-8"><label>Tên CS *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>PP khấu hao</label>
                <select name="method" id="f_method" class="form-select form-select-sm">
                    <option value="straight_line">Đường thẳng</option>
                    <option value="declining_balance">Số dư giảm dần</option>
                    <option value="sum_of_years">Tổng số năm</option>
                    <option value="production">Sản lượng</option>
                </select>
            </div>
            <div class="col-4"><label>TG sử dụng</label><input type="number" name="default_life" id="f_default_life" class="form-control form-control-sm" step="0.1"></div>
            <div class="col-4"><label>TL còn lại (%)</label><input type="number" name="default_salvage_rate" id="f_default_salvage_rate" class="form-control form-control-sm" step="0.01"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>
<script>
var API = '/api/depreciation-policies';
var editId = null;
function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
        var rows=f.map(function(i){
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+esc(i.method||'')+'</td><td class="text-end">'+(i.default_life||'')+'</td><td class="text-end">'+(i.default_salvage_rate||0)+'%</td><td>'+statusBadge(i.status?'active':'inactive')+'</td>'+
                '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới CS khấu hao';$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    document.getElementById('f_method').value=i.method||'straight_line';document.getElementById('f_default_life').value=i.default_life;
    document.getElementById('f_default_salvage_rate').value=i.default_salvage_rate;
    document.getElementById('modalTitle').textContent='Sửa CS khấu hao';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    ['default_life','default_salvage_rate'].forEach(function(k){data[k]=parseFloat(data[k])||0;});
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
