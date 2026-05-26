<?php // Màn hình: Quản lý người dùng hệ thống
$title = 'Người dùng'; $activeMenu = 'users'; ob_start(); ?>
<div class="toolbar">
    <h5>Quản lý người dùng</h5>
    <div><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-plus-lg"></i> Thêm người dùng</button></div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Username</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Lần cuối</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="userForm">
<div class="modal-header"><h5 class="modal-title">Thêm/Sửa người dùng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="editId">
    <div class="mb-3"><label>Tên đăng nhập</label><input type="text" class="form-control" id="username" required></div>
    <div class="mb-3"><label>Mật khẩu</label><input type="password" class="form-control" id="password" placeholder="Để trống nếu không đổi"></div>
    <div class="mb-3"><label>Họ tên</label><input type="text" class="form-control" id="fullName" required></div>
    <div class="mb-3"><label>Email</label><input type="email" class="form-control" id="email"></div>
    <div class="mb-3"><label>Vai trò</label>
        <div id="roleCheckboxes" class="border rounded p-2" style="max-height:200px;overflow-y:auto"></div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
</div>
</form>
</div></div></div>

<script>
function loadData() {
    $.get('/api/users', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(u){
            var badge = u.status==='active'?'badge-active':'badge-danger';
            tbody.append('<tr><td>'+esc(u.username)+'</td><td>'+esc(u.full_name)+'</td><td>'+esc(u.email||'')+'</td><td>'+esc(u.role_names||'')+'</td><td><span class="badge-status '+badge+'">'+esc(u.status)+'</span></td><td style="font-size:12px">'+esc(u.last_login||'')+'</td><td><button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(\''+esc(u.id)+'\')">Sửa</button><button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(\''+esc(u.id)+'\',\'/api/users\',\''+esc(u.full_name)+'\')">Xóa</button></td></tr>');
        });
    });
}
function loadRoles() {
    $.get('/api/roles', function(roles) {
        var html='';
        roles.forEach(function(r){html+='<div class="form-check"><input class="form-check-input" type="checkbox" value="'+esc(r.id)+'" id="role_'+esc(r.id)+'"><label class="form-check-label" for="role_'+esc(r.id)+'">'+esc(r.name)+'</label></div>';});
        $('#roleCheckboxes').html(html);
    });
}
function editUser(id) {
    $.get('/api/users', function(users) {
        var u = users.find(function(x){return x.id===id;});
        if(!u)return;
        $('#editId').val(u.id);
        $('#username').val(u.username);
        $('#password').val('');
        $('#fullName').val(u.full_name);
        $('#email').val(u.email||'');
        loadRoles();
        // Check user's roles after loading
        $.get('/api/roles', function(roles){
            roles.forEach(function(r){
                if(u.role_names && u.role_names.indexOf(r.name)>=0) $('#role_'+r.id).prop('checked',true);
            });
        });
        $('#userModal').modal('show');
    });
}
$('#userForm').submit(function(e){e.preventDefault();
    var editId=$('#editId').val();
    var roleIds=[]; $('#roleCheckboxes input:checked').each(function(){roleIds.push($(this).val());});
    var payload={username:$('#username').val(),password:$('#password').val(),full_name:$('#fullName').val(),email:$('#email').val(),role_ids:roleIds};
    var url=editId?'/api/users/'+editId:'/api/users';
    var method=editId?'PUT':'POST';
    if(!editId && !payload.password){showToast('Mật khẩu là bắt buộc','error');return;}
    $.ajax({url:url,method:method,contentType:'application/json',data:JSON.stringify(payload),
        success:function(){$('#userModal').modal('hide');$('#userForm')[0].reset();$('#editId').val('');showToast('Thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadData();loadRoles();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
