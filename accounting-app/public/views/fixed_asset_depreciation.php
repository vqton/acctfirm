<?php
// Màn hình: Tính và phân bổ khấu hao tài sản cố định
// API: GET /api/fixed-assets, POST /api/fixed-assets/depreciate, GET /api/fixed-assets/{id}/depreciation
// Nghiệp vụ: Tính khấu hao TSCĐ theo tháng — Nợ 641/642/627/Có 214 (hao mòn lũy kế)
// Phương pháp: straight_line (đường thẳng), declining_balance (số dư giảm dần), production (sản lượng)
// Tuân thủ: Thông tư 200 — khấu hao được ghi nhận hàng tháng, kể cả khi TSCĐ chưa sử dụng
// Rủi ro: Không ghi khấu hao đúng kỳ sẽ làm sai BC02 (chi phí QLDN/SXC)
$title = 'Tính khấu hao TSCĐ';
$activeMenu = 'fixed_assets';
ob_start();
?>
<style>
.dep-period { background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.06); padding:16px 20px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.dep-period label { font-size:13px; font-weight:600; color:#1a2a3a; margin-right:4px; }
.dep-period input { border:1px solid #d0d5dd; border-radius:4px; padding:5px 8px; font-size:13px; }
</style>
<div class="toolbar">
    <div><h5><?= $title ?> <span class="stats" id="recordCount"></span></h5></div>
</div>
<div class="dep-period mb-3">
    <div><label>Kỳ:</label><input type="month" id="depPeriod" value="<?= date('Y-m') ?>" class="form-control form-control-sm d-inline-block" style="width:180px"></div>
    <div><button class="btn btn-primary btn-sm" onclick="postDepreciation()"><i class="bi bi-calculator"></i> Ghi khấu hao</button></div>
    <div><span id="postResult" class="stats"></span></div>
</div>
<div class="card-table">
    <div class="card-header-x"><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm..."></div>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead><tr><th>Mã</th><th>Tên TSCĐ</th><th class="text-end">Nguyên giá</th><th>PP khấu hao</th><th class="text-end">KH tháng</th><th class="text-end">KH lũy kế</th><th class="text-end">GT còn lại</th><th style="width:200px"></th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
    </div>
</div>

<!-- Depreciation History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">Lịch sử khấu hao</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-0">
        <table class="table"><thead><tr><th>Kỳ</th><th class="text-end">Số tiền</th><th class="text-end">KH lũy kế</th><th class="text-end">GT còn lại</th><th>Ngày ghi</th><th>Người ghi</th></tr></thead><tbody id="historyBody"></tbody></table>
    </div>
</div></div></div>

<script>
var API = '/api/fixed-assets';

function loadData() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var active = data.filter(function(i){ return i.status === 'in_use' || i.status === '1'; });
        var f=active.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('recordCount').textContent='('+f.length+'/'+active.length+' TSCĐ đang sử dụng)';
        $('#postResult').text('');
        var rows=f.map(function(i){
            var methodLabels={straight_line:'Đường thẳng',declining_balance:'Số dư giảm dần',sum_of_years:'Tổng số năm',production:'Sản lượng'};
            var ml=methodLabels[i.depreciation_method]||i.depreciation_method;
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td class="text-end">'+(i.original_cost||0).toLocaleString()+'</td><td>'+ml+'</td>'+
                '<td class="text-end">'+(i.monthly_depreciation||0).toLocaleString()+'</td>'+
                '<td class="text-end">'+(i.accumulated_depreciation||0).toLocaleString()+'</td>'+
                '<td class="text-end">'+(i.net_book_value||0).toLocaleString()+'</td>'+
                '<td>'+
                '<a href="#" class="btn-action me-1" onclick="showSchedule(\''+i.id+'\')" title="Xem lịch"><i class="bi bi-calendar3"></i></a>'+
                '<a href="#" class="btn-action me-1" onclick="showHistory(\''+i.id+'\')" title="Lịch sử"><i class="bi bi-clock-history"></i></a>'+
                '</td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i>Không có TSCĐ đang sử dụng</td></tr>';
    });
}

function postDepreciation() {
    var period = document.getElementById('depPeriod').value;
    if (!confirm('Ghi khấu hao cho kỳ '+period+'?')) return;
    $('#postResult').text('Đang xử lý...');
    fetch('/api/fixed-assets/depreciate', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({period:period})})
    .then(function(r){return r.json();}).then(function(d){
        if (d.error) { $('#postResult').text('Lỗi: '+d.error); return; }
        $('#postResult').text('Đã ghi '+d.posted+' bút toán khấu hao');
        loadData();
        showToast('Đã ghi '+d.posted+' bút toán','success');
    }).catch(function(e){$('#postResult').text('Lỗi: '+e.message);});
}

function showSchedule(id) {
    window.open('/api/fixed-assets/'+id+'/schedule','_blank');
}

function showHistory(id) {
    fetch(API+'/'+id+'/depreciation').then(function(r){return r.json();}).then(function(data){
        var rows = data.map(function(i){
            return '<tr><td>'+esc(i.period_code)+'</td><td class="text-end">'+(i.depreciation_amount||0).toLocaleString()+'</td><td class="text-end">'+(i.accumulated||0).toLocaleString()+'</td><td class="text-end">'+(i.remaining||0).toLocaleString()+'</td><td>'+(i.created_at||'')+'</td><td>'+esc(i.created_by||'')+'</td></tr>';
        }).join('');
        document.getElementById('historyBody').innerHTML = rows || '<tr><td colspan="6" class="empty-state">Chưa có lịch sử</td></tr>';
        $('#historyModal').modal('show');
    });
}

$('#searchInput').on('keyup', loadData);
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
