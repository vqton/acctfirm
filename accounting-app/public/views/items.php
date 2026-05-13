<?php
$title = 'Danh mục vật tư, hàng hóa';
$activeMenu = 'items';
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
        <input type="text" id="searchInput" placeholder="Tìm kiếm theo mã, tên...">
    </div>
    <div style="overflow-x:auto;">
    <table class="table" id="dataTable">
        <thead>
            <tr>
                <th>Mã</th><th>Tên vật tư</th><th>Loại</th><th>ĐVT</th>
                <th class="text-end">Giá mua</th><th class="text-end">Giá bán</th><th class="text-end">Tồn</th><th>Trạng thái</th>
                <th class="text-center" style="width:90px"></th>
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
            <div class="col-4"><label>Loại</label>
                <select name="item_type" id="f_item_type" class="form-select form-select-sm">
                    <option value="material">Nguyên vật liệu</option>
                    <option value="tool">Công cụ dụng cụ</option>
                    <option value="product">Sản phẩm</option>
                    <option value="merchandise">Hàng hóa</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <div class="col-4"><label>ĐVT</label><input type="text" name="unit" id="f_unit" class="form-control form-control-sm" value="cai"></div>
            <div class="col-4"><label>Tồn tối thiểu</label><input type="number" name="min_stock" id="f_min_stock" class="form-control form-control-sm" step="0.01"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Giá mua</label><input type="number" name="purchase_price" id="f_purchase_price" class="form-control form-control-sm" step="0.01"></div>
            <div class="col-6"><label>Giá bán</label><input type="number" name="sale_price" id="f_sale_price" class="form-control form-control-sm" step="0.01"></div>
        </div>
        <label>Ghi chú</label><textarea name="description" id="f_description" class="form-control form-control-sm" rows="2"></textarea>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>

<script>
var API = '/api/items';
var editId = null;

function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q = (document.getElementById('searchInput').value||'').toLowerCase();
        var filtered = data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent = '('+filtered.length+'/'+data.length+' bản ghi)';
        var rows = filtered.map(function(i){
            var sc = i.status ? 'badge-active' : 'badge-inactive';
            var st = i.status ? 'Hoạt động' : 'Ngừng';
            return '<tr>' +
                '<td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td>' +
                '<td><span class="badge badge-status badge-type">'+esc(i.item_type)+'</span></td>' +
                '<td>'+esc(i.unit)+'</td>' +
                '<td class="text-end">'+(i.purchase_price||0).toLocaleString()+'</td>' +
                '<td class="text-end">'+(i.sale_price||0).toLocaleString()+'</td>' +
                '<td class="text-end">'+i.stock_qty+'</td>' +
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

function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm mới vật tư';$('#dataModal').modal('show');}
function openEdit(id){
    editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
        document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
        document.getElementById('f_item_type').value=i.item_type;document.getElementById('f_unit').value=i.unit;
        document.getElementById('f_purchase_price').value=i.purchase_price;document.getElementById('f_sale_price').value=i.sale_price;
        document.getElementById('f_min_stock').value=i.min_stock;document.getElementById('f_description').value=i.description||'';
        document.getElementById('modalTitle').textContent='Sửa vật tư';$('#dataModal').modal('show');
    }).catch(function(){showToast('Lỗi tải thông tin','error');});
}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    ['purchase_price','sale_price','min_stock'].forEach(function(k){data[k]=parseFloat(data[k])||0;});
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
