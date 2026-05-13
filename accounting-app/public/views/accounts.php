<?php
$title = 'Hệ thống tài khoản';
$activeMenu = 'coa';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?> <span class="stats" id="recordCount"></span></h5></div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="seedCOA()"><i class="bi bi-download"></i> Nạp mẫu</button>
        <button class="btn btn-primary btn-sm ms-1" onclick="openCreate()"><i class="bi bi-plus-lg"></i> Thêm mới</button>
    </div>
</div>
<div class="card-table">
    <div class="card-header-x"><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm..."></div>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead><tr><th>Số hiệu</th><th>Tên tài khoản</th><th>Dư Nợ/Có</th><th>Số dư</th><th>Trạng thái</th><th class="text-center" style="width:90px">Thao tác</th></tr></thead>
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
            <div class="col-4"><label>Số hiệu *</label><input type="text" name="code" id="f_code" class="form-control form-control-sm" required></div>
            <div class="col-8"><label>Tên *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Loại</label>
                <select name="type" id="f_type" class="form-select form-select-sm">
                    <option value="asset">Tài sản</option>
                    <option value="liability">Nợ phải trả</option>
                    <option value="equity">Vốn chủ sở hữu</option>
                    <option value="revenue">Doanh thu</option>
                    <option value="expense">Chi phí</option>
                </select>
            </div>
            <div class="col-3"><label>Class</label>
                <select name="account_class" id="f_class" class="form-select form-select-sm">
                    <option value="">--</option><option value="1">1</option><option value="2">2</option>
                    <option value="3">3</option><option value="4">4</option><option value="5">5</option>
                    <option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option>
                </select>
            </div>
            <div class="col-3"><label>Dư Nợ/Có</label>
                <select name="normal_balance" id="f_nb" class="form-select form-select-sm">
                    <option value="D">Dư Nợ</option><option value="C">Dư Có</option>
                </select>
            </div>
            <div class="col-2"><label>TK cha</label><input type="text" name="parent_id" id="f_parent" class="form-control form-control-sm"></div>
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
var API = '/api/coa';
var editId = null;
function esc(s){return String(s).replace(/[&<>"']/g,function(m){if(m==='&')return'&amp;';if(m==='<')return'&lt;';if(m==='>')return'&gt;';if(m==='"')return'&quot;';return'&#39;';});}
function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var f=data.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+data.length+' bản ghi)';

        var typeMap={'asset':'TÀI SẢN','liability':'NỢ PHẢI TRẢ','equity':'VỐN CHỦ SỞ HỮU',
            'revenue':'DOANH THU','expense':'CHI PHÍ'};
        var typeColor={'asset':'#e3f2fd','liability':'#fff3e0','equity':'#e8f5e9',
            'revenue':'#fce4ec','expense':'#f3e5f5'};
        var typeText={'asset':'#1565c0','liability':'#e65100','equity':'#2e7d32',
            'revenue':'#c62828','expense':'#6a1b9a'};

        var grouped={};
        f.forEach(function(i){
            var t=i.type||'other';
            if(!grouped[t])grouped[t]=[];
            grouped[t].push(i);
        });
        // Sort each group by code
        Object.keys(grouped).forEach(function(k){grouped[k].sort(function(a,b){return a.code.localeCompare(b.code);});});

        var order=['asset','liability','equity','revenue','expense'];
        var rows='';
        order.forEach(function(t){
            if(!grouped[t])return;
            rows+='<tr style="background:'+typeColor[t]+';font-weight:600;color:'+typeText[t]+'">'+
                '<td colspan="5" style="padding:6px 12px;font-size:13px;letter-spacing:0.5px;">'+typeMap[t]+'</td></tr>';
            grouped[t].forEach(function(i){
                var sc=i.status?'badge-active':'badge-inactive';
                var st=i.status?'Hoạt động':'Ngừng';
                var nb=i.normal_balance==='D'?'Dư Nợ':'Dư Có';
                var isParent=i.code.length<=3;
                    var indent=isParent?'':'    ';
                    var fw=isParent?'font-weight:600;font-size:14px;':'font-size:13px;';
                    var vnType=typeMap[i.type]||i.type;
                    var bal=parseFloat(i.balance)||0;
                    var balDisplay=bal!==0?Math.abs(bal).toLocaleString()+' '+(bal>0?'Nợ':'Có'):'0';
                    rows+='<tr style="'+fw+'">'+
                        '<td style="padding-left:'+(isParent?'12px':'28px')+'"><strong>'+esc(indent+i.code)+'</strong></td>'+
                        '<td>'+esc(i.name)+'</td><td>'+nb+'</td>'+
                        '<td class="text-end font-monospace">'+balDisplay+'</td>'+
                        '<td><span class="badge badge-status '+sc+'">'+st+'</span></td>'+
                    '<td class="text-center"><a href="#" class="btn-action me-1" onclick="openEdit(\''+i.id+'\')"><i class="bi bi-pencil"></i></a>'+
                    '<a href="#" class="btn-action btn-action-danger" onclick="confirmDelete(\''+i.id+'\',\''+API+'\',\''+esc(i.name)+'\')"><i class="bi bi-trash"></i></a></td></tr>';
            });
        });
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="6" class="empty-state"><i class="bi bi-inbox"></i>Chưa có dữ liệu. Nhấn "Nạp mẫu" để tạo danh mục chuẩn.</td></tr>';
    });
}
function openCreate(){editId=null;document.getElementById('dataForm').reset();document.getElementById('modalTitle').textContent='Thêm tài khoản';$('#dataModal').modal('show');}
function openEdit(id){editId=id;fetch(API+'/'+id).then(function(r){return r.json();}).then(function(i){
    document.getElementById('dataId').value=i.id;document.getElementById('f_code').value=i.code;document.getElementById('f_name').value=i.name;
    document.getElementById('f_type').value=i.type;document.getElementById('f_class').value=i.account_class||'';
    document.getElementById('f_nb').value=i.normal_balance;document.getElementById('f_parent').value=i.parent_id||'';
    document.getElementById('f_description').value=i.description||'';
    document.getElementById('modalTitle').textContent='Sửa tài khoản';$('#dataModal').modal('show');
}).catch(function(){showToast('Lỗi tải thông tin','error');});}
$('#dataForm').on('submit',function(e){
    e.preventDefault();var data=Object.fromEntries(new FormData(this));
    if(!data.parent_id)delete data.parent_id;if(!data.account_class)delete data.account_class;
    var url=editId?API+'/'+editId:API;var method=editId?'PUT':'POST';
    fetch(url,{method:method,headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(function(r){if(!r.ok)return r.json().then(function(j){throw new Error(j.error);});
        $('#dataModal').modal('hide');loadData();showToast(editId?'Đã cập nhật':'Đã thêm mới','success');
    }).catch(function(e){showToast(e.message||'Lỗi','error');});
});
function seedCOA(){fetch(API+'/seed',{method:'POST'}).then(function(r){return r.json();}).then(function(d){
    showToast('Đã nạp '+d.count+' tài khoản mẫu','success');loadData();
}).catch(function(){showToast('Lỗi khi nạp mẫu','error');});}
$('#searchInput').on('keyup',loadData);
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
