<?php
$title = 'Hệ thống tài khoản (Circular 99)';
$activeMenu = 'coa';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?> <span class="stats" id="recordCount"></span></h5></div>
    <div>
        <span style="font-size:12px;color:#888;margin-right:8px">
            <span class="badge badge-active">Hoạt động</span>
            <span class="badge badge-inactive">Ngừng</span>
            <span class="badge" style="background:#fff3cd;color:#856404">Khóa</span>
        </span>
        <button class="btn btn-outline-secondary btn-sm" onclick="seedCOA()"><i class="bi bi-download"></i> Nạp mẫu</button>
        <button class="btn btn-primary btn-sm ms-1" onclick="openCreate()"><i class="bi bi-plus-lg"></i> Thêm mới</button>
    </div>
</div>

<div class="row g-1 mb-2">
    <div class="col-auto"><input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Tìm kiếm mã/tên..." style="width:250px"></div>
    <div class="col-auto">
        <select id="filterType" class="form-select form-select-sm" onchange="loadData()">
            <option value="">Tất cả loại</option>
            <option value="asset">Tài sản</option>
            <option value="liability">Nợ phải trả</option>
            <option value="equity">Vốn CSH</option>
            <option value="revenue">Doanh thu</option>
            <option value="expense">Chi phí</option>
        </select>
    </div>
    <div class="col-auto">
        <select id="filterStatus" class="form-select form-select-sm" onchange="loadData()">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Hoạt động</option>
            <option value="inactive">Ngừng hoạt động</option>
            <option value="locked">Đã khóa</option>
        </select>
    </div>
</div>

<div class="card-table">
    <div style="overflow-x:auto;">
    <table class="table table-sm" style="font-size:13px">
        <thead><tr>
            <th>Số hiệu</th><th>Tên tài khoản</th><th>Loại</th><th>Dư Nợ/Có</th>
            <th>BCTC</th><th>Số dư</th><th>Trạng thái</th>
            <th class="text-center" style="width:130px">Thao tác</th>
        </tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
    </div>
</div>

<div class="modal fade" id="dataModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
<form id="dataForm">
    <div class="modal-header"><h6 class="modal-title" id="modalTitle">Thêm tài khoản</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" name="id" id="dataId">
        <div class="row g-2 mb-2">
            <div class="col-3"><label>Số hiệu *</label><input type="text" name="code" id="f_code" class="form-control form-control-sm" required></div>
            <div class="col-7"><label>Tên tài khoản *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
            <div class="col-2"><label>TK cha</label><input type="text" name="parent_id" id="f_parent" class="form-control form-control-sm" placeholder="Mã"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-3"><label>Loại</label>
                <select name="type" id="f_type" class="form-select form-select-sm">
                    <option value="asset">Tài sản</option>
                    <option value="liability">Nợ phải trả</option>
                    <option value="equity">Vốn chủ sở hữu</option>
                    <option value="revenue">Doanh thu</option>
                    <option value="expense">Chi phí</option>
                </select>
            </div>
            <div class="col-2"><label>Phân loại</label>
                <select name="account_class" id="f_class" class="form-select form-select-sm">
                    <option value="">--</option>
                    <?php foreach (range(1,9) as $i): ?><option value="<?=$i?>"><?=$i?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-2"><label>Dư Nợ/Có</label>
                <select name="normal_balance" id="f_nb" class="form-select form-select-sm">
                    <option value="D">Dư Nợ</option><option value="C">Dư Có</option>
                </select>
            </div>
            <div class="col-3"><label>Ánh xạ BCTC</label>
                <select name="fs_mapping_type" id="f_fs_type" class="form-select form-select-sm" onchange="toggleFsCode()">
                    <option value="">--</option>
                    <option value="balance_sheet">BC01 (CĐKT)</option>
                    <option value="income_statement">BC02 (KQKD)</option>
                    <option value="cash_flow">BC03 (LCTT)</option>
                    <option value="tax">Thuế</option>
                </select>
            </div>
            <div class="col-2"><label>Mã chỉ tiêu</label><input type="text" name="fs_mapping_code" id="f_fs_code" class="form-control form-control-sm" placeholder="VD: BC01_110"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-3"><label>Mã cũ (TT 200)</label><input type="text" name="alternative_code" id="f_alt" class="form-control form-control-sm"></div>
            <div class="col-3"><label>Theo dõi chi tiết</label>
                <select name="detail_by" id="f_detail" class="form-select form-select-sm">
                    <option value="">--</option>
                    <option value="customer">Khách hàng</option>
                    <option value="supplier">Nhà cung cấp</option>
                    <option value="employee">Nhân viên</option>
                    <option value="project">Dự án</option>
                    <option value="contract">Hợp đồng</option>
                </select>
            </div>
            <div class="col-2"><label>TK hệ thống</label>
                <select name="is_system" id="f_system" class="form-select form-select-sm">
                    <option value="0">Không</option><option value="1">Có</option>
                </select>
            </div>
        </div>
        <label>Ghi chú</label><textarea name="description" id="f_description" class="form-control form-control-sm" rows="2"></textarea>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
    </div>
</form>
</div></div></div>

<!-- Lock modal -->
<div class="modal fade" id="lockModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form id="lockForm">
    <div class="modal-header"><h6 class="modal-title">Khóa tài khoản</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="lockId">
        <div class="mb-2"><label>Lý do khóa *</label><textarea id="lockReason" class="form-control form-control-sm" rows="2" required></textarea></div>
        <div class="form-check"><input type="checkbox" class="form-check-input" id="lockOverride">
            <label class="form-check-label">CFO override (cho phép khóa khi còn số dư)</label></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-danger">Xác nhận khóa</button>
    </div>
</form>
</div></div></div>

<!-- Lock info modal -->
<div class="modal fade" id="lockInfoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">Chi tiết khóa</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div id="lockInfoBody"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
</div></div></div>

<script>
var API = '/api/coa';
var editId = null;
function esc(s){return String(s||'').replace(/[&<>"']/g,function(m){if(m==='&')return'&amp;';if(m==='<')return'&lt;';if(m==='>')return'&gt;';if(m==='"')return'&quot;';return'&#39;';});}
function showToast(m,t){var el=document.getElementById('toastMsg');if(!el){el=document.createElement('div');el.id='toastMsg';el.style.cssText='position:fixed;top:20px;right:20px;z-index:9999;padding:12px 24px;border-radius:6px;color:#fff;font-size:14px';document.body.appendChild(el);}el.style.background=t==='error'?'#d32f2f':'#2e7d32';el.textContent=m;el.style.display='block';setTimeout(function(){el.style.display='none';},3000);}

function flattenTree(nodes, depth) {
    var result = [];
    (nodes||[]).forEach(function(n) {
        var children = n.children || [];
        delete n.children;
        n._depth = depth || 0;
        n._hasChildren = children.length > 0;
        result.push(n);
        if (children.length > 0) {
            result = result.concat(flattenTree(children, (depth||0) + 1));
        }
    });
    return result;
}

function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(tree){
        var flat = flattenTree(tree, 0);
        var q = (document.getElementById('searchInput').value||'').toLowerCase();
        var ft = document.getElementById('filterType').value;
        var fs = document.getElementById('filterStatus').value;

        var filtered = flat.filter(function(i){
            if (q && !i.name.toLowerCase().includes(q) && !i.code.toLowerCase().includes(q)) return false;
            if (ft && i.type !== ft) return false;
            if (fs === 'active' && !i.status) return false;
            if (fs === 'inactive' && i.status) return false;
            if (fs === 'locked' && !i.is_locked) return false;
            return true;
        });

        document.getElementById('recordCount').textContent = '('+filtered.length+'/'+flat.length+' bản ghi)';

        var rows = '';
        filtered.forEach(function(i){
            var pad = i._depth * 16 + 12;
            var bold = i._depth === 0 ? 'font-weight:600;font-size:14px;' : 'font-size:13px;';
            var indent = i._depth === 0 ? '' : ' '.repeat(i._depth * 2);
            var nb = i.normal_balance === 'D' ? 'Dư Nợ' : 'Dư Có';
            var bal = parseFloat(i.balance) || 0;
            var balDisplay = bal !== 0 ? Math.abs(bal).toLocaleString() + ' ' + (bal > 0 ? 'Nợ' : 'Có') : '0';

            var statusHtml = '';
            if (i.is_locked) statusHtml = '<span class="badge" style="background:#fff3cd;color:#856404">Khóa</span>';
            else if (i.status) statusHtml = '<span class="badge badge-active">Hoạt động</span>';
            else statusHtml = '<span class="badge badge-inactive">Ngừng</span>';

            var fsDisplay = i.fs_mapping_code ? esc(i.fs_mapping_code) : '<span class="text-muted">--</span>';

            var actions = '';
            if (!i.is_locked) {
                if (i.status) actions += '<a href="#" class="btn-action me-1" onclick="deactivateAccount(\''+i.id+'\')" title="Vô hiệu hóa"><i class="bi bi-pause-circle"></i></a>';
                else actions += '<a href="#" class="btn-action me-1" onclick="activateAccount(\''+i.id+'\')" title="Kích hoạt"><i class="bi bi-play-circle"></i></a>';
                actions += '<a href="#" class="btn-action me-1" onclick="openLock(\''+i.id+'\')" title="Khóa"><i class="bi bi-lock"></i></a>';
            } else {
                actions += '<a href="#" class="btn-action me-1" onclick="unlockAccount(\''+i.id+'\')" title="Mở khóa"><i class="bi bi-unlock"></i></a>';
            }
            actions += '<a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')" title="Sửa"><i class="bi bi-pencil"></i></a>';
            if (!i.is_system) actions += '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+esc(i.name)+'\')" title="Xóa"><i class="bi bi-trash"></i></a>';

            rows += '<tr style="'+bold+'">'+
                '<td style="padding-left:'+pad+'px"><strong>'+esc(indent+i.code)+'</strong></td>'+
                '<td>'+esc(i.name)+'</td>'+
                '<td>'+esc(i.type)+'</td><td>'+nb+'</td>'+
                '<td style="font-size:12px">'+fsDisplay+'</td>'+
                '<td class="text-end font-monospace">'+balDisplay+'</td>'+
                '<td>'+statusHtml+'</td>'+
                '<td class="text-center" style="white-space:nowrap">'+actions+'</td></tr>';
        });
        document.getElementById('dataBody').innerHTML = rows || '<tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i>Chưa có dữ liệu. Nhấn "Nạp mẫu" để tạo danh mục chuẩn.</td></tr>';
    });
}

function openCreate() {
    editId = null;
    document.getElementById('dataForm').reset();
    document.getElementById('modalTitle').textContent = 'Thêm tài khoản';
    $('#dataModal').modal('show');
}

function openEdit(id) {
    editId = id;
    fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
        document.getElementById('dataId').value = i.id;
        document.getElementById('f_code').value = i.code;
        document.getElementById('f_name').value = i.name;
        document.getElementById('f_type').value = i.type;
        document.getElementById('f_class').value = i.account_class||'';
        document.getElementById('f_nb').value = i.normal_balance;
        document.getElementById('f_parent').value = i.parent_id||'';
        document.getElementById('f_fs_code').value = i.fs_mapping_code||'';
        document.getElementById('f_fs_type').value = i.fs_mapping_type||'';
        document.getElementById('f_alt').value = i.alternative_code||'';
        document.getElementById('f_detail').value = i.detail_by||'';
        document.getElementById('f_system').value = i.is_system ? '1' : '0';
        document.getElementById('f_description').value = i.description||'';
        document.getElementById('modalTitle').textContent = 'Sửa tài khoản';
        toggleFsCode();
        $('#dataModal').modal('show');
    }).catch(function(){showToast('Lỗi tải thông tin','error');});
}

function toggleFsCode() {
    var t = document.getElementById('f_fs_type').value;
    document.getElementById('f_fs_code').disabled = !t;
}

$('#dataForm').on('submit', function(e){
    e.preventDefault();
    var data = Object.fromEntries(new FormData(this));
    if (!data.parent_id) delete data.parent_id;
    if (!data.account_class) delete data.account_class;
    if (!data.fs_mapping_code) { delete data.fs_mapping_code; delete data.fs_mapping_type; }
    if (!data.alternative_code) delete data.alternative_code;
    if (!data.detail_by) delete data.detail_by;
    if (!data.description) delete data.description;
    data.is_system = parseInt(data.is_system) ? true : false;

    var url = editId ? API+'/'+editId : API;
    var method = editId ? 'PUT' : 'POST';
    fetch(url, {method:method, headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)})
    .then(function(r){if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        $('#dataModal').modal('hide'); loadData(); showToast(editId?'Đã cập nhật':'Đã thêm mới','success');
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
});

function seedCOA() {
    fetch(API+'/seed', {method:'POST'}).then(function(r){return r.json();}).then(function(d){
        showToast('Đã nạp mẫu: '+d.count+' mới, '+d.updated+' cập nhật','success');
        loadData();
    }).catch(function(){showToast('Lỗi khi nạp mẫu','error');});
}

function activateAccount(id) {
    fetch(API+'/'+id+'/activate', {method:'POST'}).then(function(r){
        if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        showToast('Đã kích hoạt tài khoản','success'); loadData();
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
}

function deactivateAccount(id) {
    if(!confirm('Vô hiệu hóa tài khoản này?')) return;
    fetch(API+'/'+id+'/deactivate', {method:'POST'}).then(function(r){
        if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        showToast('Đã vô hiệu hóa tài khoản','success'); loadData();
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
}

function openLock(id) {
    document.getElementById('lockId').value = id;
    document.getElementById('lockReason').value = '';
    document.getElementById('lockOverride').checked = false;
    $('#lockModal').modal('show');
}

$('#lockForm').on('submit', function(e){
    e.preventDefault();
    var id = document.getElementById('lockId').value;
    var data = {
        locked_reason: document.getElementById('lockReason').value,
        cf_override: document.getElementById('lockOverride').checked
    };
    fetch(API+'/'+id+'/lock', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)})
    .then(function(r){
        if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        $('#lockModal').modal('hide'); showToast('Đã khóa tài khoản','success'); loadData();
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
});

function unlockAccount(id) {
    if(!confirm('Mở khóa tài khoản này?')) return;
    fetch(API+'/'+id+'/unlock', {method:'POST'}).then(function(r){
        if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        showToast('Đã mở khóa tài khoản','success'); loadData();
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
}

function confirmDelete(id, name) {
    if(!confirm('Xóa tài khoản "'+name+'"?')) return;
    fetch(API+'/'+id, {method:'DELETE'}).then(function(r){
        if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        showToast('Đã xóa tài khoản','success'); loadData();
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
}

$('#searchInput').on('keyup', loadData);
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
