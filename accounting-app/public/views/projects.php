<?php
// Màn hình: Danh mục dự án, công trình
$title = 'Danh mục dự án, công trình';
$activeMenu = 'projects';
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
        <thead><tr><th>Mã</th><th>Tên dự án</th><th>Khách hàng</th><th>Ngày BĐ</th><th>Ngày KT</th><th class="text-end">Ngân sách</th><th>Trạng thái</th><th class="text-center" style="width:90px"></th></tr></thead>
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
            <div class="col-8"><label>Tên dự án *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Khách hàng</label>
                <select name="customer_id" id="f_customer_id" class="form-select form-select-sm"><option value="">-- Chọn --</option></select>
            </div>
            <div class="col-3"><label>Ngày BĐ</label><input type="date" name="start_date" id="f_start_date" class="form-control form-control-sm"></div>
            <div class="col-3"><label>Ngày KT</label><input type="date" name="end_date" id="f_end_date" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Ngân sách</label><input type="number" name="budget" id="f_budget" class="form-control form-control-sm" step="0.01"></div>
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
var API = '/api/projects';
var editId = null;
var customerMap = {};
function loadCustomerSelect(selected){
    var opts='<option value="">-- Chọn --</option>';
    $.each(customerMap,function(id,c){
        opts+='<option value="'+id+'"'+(id==selected?' selected':'')+'>'+esc(c.code)+' - '+esc(c.name)+'</option>';
    });
    $('#f_customer_id').html(opts);
}
function loadData() {
    fetch('/api/customers').then(function(r){return r.json();}).then(function(customers){
        customerMap={};customers.forEach(function(c){customerMap[c.id]=c;});
        fetch(API).then(function(r){return r.json();}).then(function(data){
            var q=(document.getElementById('searchInput').value||'').toLowerCase();
            var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
            document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';
            var rows=f.map(function(i){
                var sc=i.status?'badge-active':'badge-inactive';var st=i.status?'Hoạt động':'Ngừng';
                var cust=i.customer_id&&customerMap[i.customer_id]?esc(customerMap[i.customer_id].name):'';
                return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+cust+'</td><td>'+(i.start_date||'')+'</td><td>'+(i.end_date||'')+'</td><td class="text-end">'+(i.budget||0).toLocaleString()+'</td><td><span class="badge badge-status '+sc+'">'+st+'</span></td>'+
                    '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                    '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
            }).join('');
            document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i>Không có dữ liệu</td></tr>';
        });
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới dự án';loadCustomerSelect('');$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    loadCustomerSelect(i.customer_id||'');document.getElementById('f_start_date').value=i.start_date;document.getElementById('f_end_date').value=i.end_date;
    document.getElementById('f_budget').value=i.budget;document.getElementById('f_notes').value=i.notes||'';
    document.getElementById('modalTitle').textContent='Sửa dự án';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    data.budget=parseFloat(data.budget)||0;
    if(!data.customer_id)delete data.customer_id;
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
