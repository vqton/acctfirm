<?php // Màn hình: Phân quyền người dùng hệ thống
$title = 'Vai trò & Phân quyền'; $activeMenu = 'roles'; ob_start(); ?>
<div class="toolbar">
    <h5>Vai trò & Phân quyền</h5>
    <div><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#roleModal"><i class="bi bi-plus-lg"></i> Thêm vai trò</button></div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Mã</th><th>Tên vai trò</th><th>Mô tả</th><th>Hệ thống</th><th></th></tr></thead>
    <tbody id="roleBody"></tbody>
</table></div>

<div class="modal fade" id="roleModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="roleForm">
<div class="modal-header"><h5 class="modal-title">Thêm vai trò</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Mã vai trò</label><input type="text" class="form-control" id="roleId" required></div>
    <div class="mb-3"><label>Tên vai trò</label><input type="text" class="form-control" id="roleName" required></div>
    <div class="mb-3"><label>Mô tả</label><textarea class="form-control" id="roleDesc" rows="2"></textarea></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
</div>
</form>
</div></div></div>

<div class="modal fade" id="permModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Phân quyền: <span id="permRoleName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="permRoleId">
    <table class="table table-sm table-bordered">
        <thead><tr><th>Module</th><th>Xem</th><th>Thêm</th><th>Sửa</th><th>Xóa</th><th>Ghi sổ</th><th>In</th></tr></thead>
        <tbody id="permBody"></tbody>
    </table>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button class="btn btn-sm btn-primary" onclick="savePerms()">Lưu quyền</button>
</div>
</div></div></div>

<script>
var modules = ['cash','bank','gl','master_data','inventory','reconciliation','report','audit','system'];
var moduleNames = {'cash':'Vốn bằng tiền','bank':'Ngân hàng','gl':'Kế toán tổng hợp','master_data':'Danh mục','inventory':'Hàng tồn kho','reconciliation':'Đối chiếu NH','report':'Báo cáo','audit':'Nhật ký','system':'Hệ thống'};
var actions = ['can_view','can_create','can_edit','can_delete','can_post','can_print'];
var actionNames = {'can_view':'Xem','can_create':'Thêm','can_edit':'Sửa','can_delete':'Xóa','can_post':'Ghi sổ','can_print':'In'};

function loadRoles() {
    $.get('/api/roles', function(data) {
        var tbody=$('#roleBody'); tbody.empty();
        data.forEach(function(r){
            var systemBadge = r.is_system ? '<span class="badge-status badge-active">Hệ thống</span>' : '';
            tbody.append('<tr><td>'+esc(r.id)+'</td><td>'+esc(r.name)+'</td><td>'+esc(r.description||'')+'</td><td>'+systemBadge+'</td><td><button class="btn btn-sm btn-outline-primary me-1" onclick="openPerms(\''+esc(r.id)+'\',\''+esc(r.name)+'\')"><i class="bi bi-shield"></i> Phân quyền</button>'+(r.is_system?'':'<button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(\''+esc(r.id)+'\',\'/api/roles\',\''+esc(r.name)+'\')">Xóa</button>')+'</td></tr>');
        });
    });
}

function openPerms(id, name) {
    $('#permRoleId').val(id);
    $('#permRoleName').text(name);
    $.get('/api/roles/'+id+'/permissions', function(perms) {
        var html='';
        modules.forEach(function(m){
            var p = perms[m] || {};
            html += '<tr><td><strong>'+esc(moduleNames[m])+'</strong></td>';
            actions.forEach(function(a){
                var checked = p[a] ? 'checked' : '';
                html += '<td class="text-center"><input type="checkbox" class="perm-cb" data-module="'+esc(m)+'" data-action="'+esc(a)+'" '+checked+'></td>';
            });
            html += '</tr>';
        });
        $('#permBody').html(html);
        $('#permModal').modal('show');
    });
}

function savePerms() {
    var id = $('#permRoleId').val();
    var data = {};
    modules.forEach(function(m){
        data[m] = {};
        actions.forEach(function(a){
            data[m][a] = $('#permBody .perm-cb[data-module="'+m+'"][data-action="'+a+'"]').is(':checked') ? 1 : 0;
        });
    });
    $.ajax({url:'/api/roles/'+id+'/permissions',method:'PUT',contentType:'application/json',data:JSON.stringify(data),
        success:function(){$('#permModal').modal('hide');showToast('Đã lưu quyền','success');},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

$('#roleForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/roles',method:'POST',contentType:'application/json',data:JSON.stringify({id:$('#roleId').val(),name:$('#roleName').val(),description:$('#roleDesc').val()}),
        success:function(){$('#roleModal').modal('hide');$('#roleForm')[0].reset();showToast('Thêm vai trò thành công','success');loadRoles();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

$(document).ready(function(){loadRoles();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
