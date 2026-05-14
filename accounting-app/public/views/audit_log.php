<?php $title = 'Nhật ký hoạt động'; $activeMenu = 'audit_log'; ob_start(); ?>
<div class="toolbar">
    <h5>Nhật ký hoạt động <span class="stats">(Audit Log)</span></h5>
    <div>
        <button class="btn btn-outline-primary btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>
</div>
<div class="card-table">
    <div class="row p-3 g-2 align-items-end">
        <div class="col-auto"><label class="form-label small">Hành động</label><select class="form-select form-select-sm" id="filterAction"><option value="">Tất cả</option></select></div>
        <div class="col-auto"><label class="form-label small">Đối tượng</label><select class="form-select form-select-sm" id="filterResource"><option value="">Tất cả</option></select></div>
        <div class="col-auto"><label class="form-label small">Người thực hiện</label><input type="text" class="form-control form-control-sm" id="filterActor" placeholder="ID người dùng" style="width:150px"></div>
        <div class="col-auto"><label class="form-label small">Từ ngày</label><input type="date" class="form-control form-control-sm" id="filterFrom"></div>
        <div class="col-auto"><label class="form-label small">Đến ngày</label><input type="date" class="form-control form-control-sm" id="filterTo"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" onclick="loadData()">Lọc</button></div>
    </div>
    <table class="table table-hover table-sm">
        <thead><tr><th>ID</th><th>Hành động</th><th>Đối tượng</th><th>ID đối tượng</th><th>Người thực hiện</th><th>IP</th><th>Thời gian</th><th></th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
    <div class="d-flex justify-content-between p-2">
        <small class="text-muted" id="totalInfo"></small>
        <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
    </div>
</div>
<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Chi tiết audit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="detailBody"></div>
</div></div></div>
<script>
var currentPage = 1;
function loadData() {
    var params = {page: currentPage};
    var a = $('#filterAction').val(); if(a) params.action = a;
    var r = $('#filterResource').val(); if(r) params.resource_type = r;
    var ac = $('#filterActor').val(); if(ac) params.actor_id = ac;
    var f = $('#filterFrom').val(); if(f) params.from = f;
    var t = $('#filterTo').val(); if(t) params.to = t;
    $.get('/api/audit-log', params, function(res) {
        var tbody=$('#dataBody'); tbody.empty();
        $('#totalInfo').text('Tổng: ' + res.total + ' bản ghi');
        if(res.data.length===0){tbody.append('<tr><td colspan="8" class="text-center text-muted py-4">Không có dữ liệu</td></tr>');return;}
        res.data.forEach(function(r){
            var oldV = r.old_values ? JSON.parse(r.old_values) : null;
            var newV = r.new_values ? JSON.parse(r.new_values) : null;
            tbody.append('<tr><td>'+r.id+'</td><td><span class="badge-status badge-active">'+esc(r.action)+'</span></td><td>'+esc(r.resource_type)+'</td><td>'+esc(r.resource_id||'')+'</td><td>'+esc(r.actor_id||'')+'</td><td>'+esc(r.ip_address||'')+'</td><td>'+esc(r.created_at)+'</td><td><button class="btn btn-sm btn-outline-info" onclick="showDetail('+r.id+')">Xem</button></td></tr>');
        });
        var totalPages = Math.ceil(res.total / res.per_page);
        var pg = $('#pagination'); pg.empty();
        for(var i=1;i<=totalPages;i++){
            pg.append('<li class="page-item'+(i===currentPage?' active':'')+'"><a class="page-link" href="#" onclick="currentPage='+i+';loadData();return false">'+i+'</a></li>');
        }
    });
}

function showDetail(id) {
    $.get('/api/audit-log/'+id, function(r) {
        var html = '<table class="table table-sm">';
        html += '<tr><th>ID</th><td>'+r.id+'</td></tr>';
        html += '<tr><th>Hành động</th><td>'+esc(r.action)+'</td></tr>';
        html += '<tr><th>Đối tượng</th><td>'+esc(r.resource_type)+'</td></tr>';
        html += '<tr><th>ID đối tượng</th><td>'+esc(r.resource_id||'')+'</td></tr>';
        html += '<tr><th>Người thực hiện</th><td>'+esc(r.actor_id||'')+'</td></tr>';
        html += '<tr><th>IP</th><td>'+esc(r.ip_address||'')+'</td></tr>';
        html += '<tr><th>Request ID</th><td>'+esc(r.request_id||'')+'</td></tr>';
        html += '<tr><th>Thời gian</th><td>'+esc(r.created_at)+'</td></tr>';
        if(r.old_values) html += '<tr><th>Giá trị cũ</th><td><pre class="mb-0" style="max-height:200px;overflow:auto">'+esc(JSON.stringify(JSON.parse(r.old_values), null, 2))+'</pre></td></tr>';
        if(r.new_values) html += '<tr><th>Giá trị mới</th><td><pre class="mb-0" style="max-height:200px;overflow:auto">'+esc(JSON.stringify(JSON.parse(r.new_values), null, 2))+'</pre></td></tr>';
        html += '</table>';
        $('#detailBody').html(html);
        $('#detailModal').modal('show');
    });
}

$(document).ready(function(){
    // Populate filter dropdowns from existing data
    $.get('/api/audit-log', {page:1, per_page:1000}, function(res){
        var actions = {}, resources = {};
        res.data.forEach(function(r){ actions[r.action]=true; resources[r.resource_type]=true; });
        Object.keys(actions).sort().forEach(function(a){$('#filterAction').append('<option>'+esc(a)+'</option>');});
        Object.keys(resources).sort().forEach(function(r){$('#filterResource').append('<option>'+esc(r)+'</option>');});
    });
    loadData();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
