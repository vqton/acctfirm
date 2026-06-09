<?php
// Màn hình: Danh mục phòng ban
$title = 'Danh mục phòng ban';
$activeMenu = 'departments';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?> <span class="stats" id="recordCount"></span></h5></div>
    <div><button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'danh-sach-phong-ban')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button><button class="btn btn-primary btn-sm" onclick="openCreate()"><i class="bi bi-plus-lg"></i> Thêm mới</button></div>
</div>
<div class="card-table">
    <div class="card-header-x"><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm..."></div>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead><tr><th>Mã</th><th>Tên phòng ban</th><th>Phòng ban cha</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
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
        <div class="mb-2"><label>Mã *</label><input type="text" name="code" id="f_code" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label>Tên phòng ban *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label>Phòng ban cha</label>
            <select name="parent_id" id="f_parent" class="form-select form-select-sm"><option value="">-- Không có --</option></select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>
<script>
var API = '/api/departments';
var editId = null;
var deptMap = {};
function loadParentSelect(selected){
    var opts='<option value="">-- Không có --</option>';
    $.each(deptMap,function(id,d){
        if(d.id!==editId)opts+='<option value="'+id+'"'+(id===selected?' selected':'')+'>'+esc(d.code)+' - '+esc(d.name)+'</option>';
    });
    $('#f_parent').html(opts);
}
function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        data.forEach(function(d){deptMap[d.id]=d;});
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
        var rows=f.map(function(i){
            var parent=i.parent_id&&deptMap[i.parent_id]?esc(deptMap[i.parent_id].name):'';
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+parent+'</td><td>'+statusBadge(i.status?'active':'inactive')+'</td>'+
                '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="5" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm phòng ban';loadParentSelect('');$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    loadParentSelect(i.parent_id||'');document.getElementById('modalTitle').textContent='Sửa phòng ban';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    if(!data.parent_id)delete data.parent_id;
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
