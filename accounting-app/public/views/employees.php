<?php
// Màn hình: Danh mục nhân viên
$title = 'Danh mục nhân viên';
$activeMenu = 'employees';
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
        <thead><tr><th>Mã</th><th>Họ tên</th><th>Phòng ban</th><th>Chức vụ</th><th>Điện thoại</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
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
            <div class="col-8"><label>Họ tên *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Phòng ban</label>
                <select name="department_id" id="f_department_id" class="form-select form-select-sm"><option value="">-- Chọn --</option></select>
            </div>
            <div class="col-6"><label>Chức vụ</label><input type="text" name="position" id="f_position" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Điện thoại</label><input type="text" name="phone" id="f_phone" class="form-control form-control-sm"></div>
            <div class="col-6"><label>Email</label><input type="email" name="email" id="f_email" class="form-control form-control-sm"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>
<script>
var API = '/api/employees';
var editId = null;
var deptMap = {};
function loadDeptSelect(selected){
    var opts='<option value="">-- Chọn --</option>';
    $.each(deptMap,function(id,d){
        opts+='<option value="'+id+'"'+(id==selected?' selected':'')+'>'+esc(d.code)+' - '+esc(d.name)+'</option>';
    });
    $('#f_department_id').html(opts);
}
function loadData() {
    fetch('/api/departments').then(function(r){return r.json();}).then(function(deps){
        deptMap={};deps.forEach(function(d){deptMap[d.id]=d;});
        fetch(API).then(function(r){return r.json();}).then(function(data){
            var q=(document.getElementById('searchInput').value||'').toLowerCase();
            var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q)||(i.phone||'').toLowerCase().includes(q);});
            document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
            var rows=f.map(function(i){
                var dept=i.department_id&&deptMap[i.department_id]?esc(deptMap[i.department_id].name):'';
                return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+dept+'</td><td>'+esc(i.position||'')+'</td><td>'+esc(i.phone||'')+'</td><td>'+statusBadge(i.status?'active':'inactive')+'</td>'+
                    '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                    '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
            }).join('');
            document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
        });
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới nhân viên';loadDeptSelect('');$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    loadDeptSelect(i.department_id||'');document.getElementById('f_position').value=i.position||'';document.getElementById('f_phone').value=i.phone||'';
    document.getElementById('f_email').value=i.email||'';
    document.getElementById('modalTitle').textContent='Sửa nhân viên';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    if(!data.department_id)delete data.department_id;
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
