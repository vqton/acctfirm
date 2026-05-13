<?php
$title = 'Danh mục khách hàng';
$activeMenu = 'customers';
ob_start();
?>
<div class="toolbar">
    <div>
        <h5><?= $title ?> <span class="stats" id="recordCount"></span></h5>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="openCreate()"><i class="bi bi-plus-lg"></i> Thêm mới</button>
    </div>
</div>

<div class="card-table">
    <div class="card-header-x">
        <i class="bi bi-search text-muted"></i>
        <input type="text" id="searchInput" placeholder="Tìm kiếm theo mã, tên, điện thoại...">
    </div>
    <div style="overflow-x:auto;">
    <table class="table" id="dataTable">
        <thead>
            <tr>
                <th>Mã</th><th>Tên</th><th>MST</th>
                <th>Điện thoại</th><th>Email</th>
                <th class="text-end">Hạn mức</th><th class="text-end">Dư nợ</th><th>Trạng thái</th>
                <th class="text-center" style="width:90px">Thao tác</th>
            </tr>
        </thead>
        <tbody id="dataBody"></tbody>
    </table>
    </div>
    <div class="pagination-bar">
        <span id="paginationInfo"></span>
        <span id="paginationNav"></span>
    </div>
</div>

<div class="modal fade" id="dataModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form id="dataForm">
    <div class="modal-header">
        <h6 class="modal-title" id="modalTitle">Thêm mới</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" name="id" id="dataId">
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Mã *</label><input type="text" name="code" id="f_code" class="form-control form-control-sm" required></div>
            <div class="col-8"><label>Tên *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>MST</label><input type="text" name="tax_code" id="f_tax_code" class="form-control form-control-sm"></div>
            <div class="col-4"><label>Điện thoại</label><input type="text" name="phone" id="f_phone" class="form-control form-control-sm"></div>
            <div class="col-4"><label>Email</label><input type="email" name="email" id="f_email" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Người liên hệ</label><input type="text" name="contact_person" id="f_contact_person" class="form-control form-control-sm"></div>
            <div class="col-6"><label>Điều khoản TT</label><input type="text" name="payment_terms" id="f_payment_terms" class="form-control form-control-sm" placeholder="VD: 30 ngày"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Hạn mức</label><input type="number" name="credit_limit" id="f_credit_limit" class="form-control form-control-sm" step="0.01"></div>
            <div class="col-6"><label>Trạng thái</label>
                <select name="status" id="f_status" class="form-select form-select-sm">
                    <option value="1">Hoạt động</option>
                    <option value="0">Ngừng</option>
                </select>
            </div>
        </div>
        <label>Địa chỉ</label><input type="text" name="address" id="f_address" class="form-control form-control-sm mb-2">
        <label>Ghi chú</label><textarea name="notes" id="f_notes" class="form-control form-control-sm" rows="2"></textarea>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>

<script>
var API = '/api/customers';
var editId = null;

function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q = (document.getElementById('searchInput').value||'').toLowerCase();
        var filtered = data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q)||(i.phone||'').toLowerCase().includes(q)||(i.email||'').toLowerCase().includes(q)||(i.tax_code||'').toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent = '('+filtered.length+'/'+data.length+' bản ghi)';
        var rows = filtered.map(function(i){
            var sc = i.status ? 'badge-active' : 'badge-inactive';
            var st = i.status ? 'Hoạt động' : 'Ngừng';
            return '<tr>' +
                '<td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td>' +
                '<td>'+esc(i.tax_code||'')+'</td>' +
                '<td>'+esc(i.phone||'')+'</td><td>'+esc(i.email||'')+'</td>' +
                '<td class="text-end">'+(i.credit_limit||0).toLocaleString()+'</td>' +
                '<td class="text-end">'+(i.balance||0).toLocaleString()+'</td>' +
                '<td><span class="badge badge-status '+sc+'">'+st+'</span></td>' +
                '<td class="text-center">' +
                    '<a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>' +
                    '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a>' +
                '</td>' +
            '</tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML = rows || '<tr><td colspan="9" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
    });
}

function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới khách hàng';$('#dataModal').modal('show');}
function openEdit(id){
    editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
        document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
        document.getElementById('f_tax_code').value=i.tax_code||'';document.getElementById('f_phone').value=i.phone||'';
        document.getElementById('f_email').value=i.email||'';document.getElementById('f_contact_person').value=i.contact_person||'';
        document.getElementById('f_payment_terms').value=i.payment_terms||'';document.getElementById('f_credit_limit').value=i.credit_limit;
        document.getElementById('f_status').value=i.status?1:0;document.getElementById('f_address').value=i.address||'';
        document.getElementById('f_notes').value=i.notes||'';
        document.getElementById('modalTitle').textContent='Sửa khách hàng';$('#dataModal').modal('show');
    }).catch(function(){showToast('Lỗi tải thông tin','error');});
}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    data.credit_limit=parseFloat(data.credit_limit)||0;
    data.status=data.status=='1'||data.status===true||data.status==='on'?1:0;
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
