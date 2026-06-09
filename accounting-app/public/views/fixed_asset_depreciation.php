<?php
// Mẫu 06-TSCĐ — Bảng tính và phân bổ khấu hao tài sản cố định
// Tuân thủ: Thông tư 99/2025/TT-BTC
// API: GET /api/fixed-assets/depreciation/report/:period, POST /api/fixed-assets/depreciation/save-batch
//       GET /api/fixed-assets/depreciation/batch/:period, GET /api/fixed-assets/depreciation/batches
//       POST /api/fixed-assets/depreciate, GET /api/fixed-assets
$title = 'Mẫu 06-TSCĐ — Bảng tính và phân bổ khấu hao TSCĐ';
$activeMenu = 'fixed_assets';
ob_start();
?>
<style>
.mau0601 { font-size:12px; }
.mau0601 .header-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
.mau0601 .company-name { font-weight:700; font-size:13px; }
.mau0601 .report-title { font-weight:700; font-size:14px; text-align:center; margin:8px 0; }
.mau0601 .sub-title { text-align:center; font-size:11px; color:#555; }
.mau0601 .period-info { font-size:11px; }
.toolbar.dep-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 16px; background:#f8f9fa; border-radius:8px; margin-bottom:12px; }
.toolbar.dep-toolbar label { font-size:12px; font-weight:600; color:#1a2a3a; }
.toolbar.dep-toolbar select, .toolbar.dep-toolbar input { border:1px solid #d0d5dd; border-radius:4px; padding:4px 8px; font-size:12px; }
.wrap-table { overflow-x:auto; }
.wrap-table table { min-width:1200px; }
.wrap-table th.col-acc { min-width:70px; }
.wrap-table th.col-total { min-width:100px; }
.row-label { font-weight:600; background:#f0f4f8; }
.row-total { font-weight:700; background:#e8f0fe; border-top:2px solid #ccc; }
.row-subtotal { font-weight:600; background:#f5f7fa; }
.carried-row { font-style:italic; color:#555; }
.num-cell { text-align:right; padding:4px 8px !important; font-variant-numeric:tabular-nums; white-space:nowrap; }
.row-name { position:sticky; left:0; background:inherit; z-index:1; }
.detail-row { display:none; }
.detail-row.show { display:table-row; }
.detail-row td { background:#fafbfc; padding:2px 8px !important; }
.detail-row td:first-child { padding-left:32px !important; }
.expand-btn { cursor:pointer; color:#2563eb; font-size:11px; }
.batch-info { background:#e8f5e9; border:1px solid #c8e6c9; border-radius:6px; padding:8px 14px; font-size:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
.batch-info .label { font-weight:600; color:#2e7d32; }
@media print { .no-print, .toolbar, .batch-info, .btn { display:none !important; } body { font-size:10px; } .wrap-table table { min-width:auto; } }
</style>
<div class="toolbar no-print">
    <div><h5><?= $title ?> <span class="stats" id="batchStatus"></span></h5></div>
</div>
<div class="toolbar dep-toolbar no-print">
    <div><label>Kỳ:</label><input type="month" id="depPeriod" value="<?= date('Y-m') ?>" class="form-control form-control-sm d-inline-block" style="width:160px"></div>
    <div><button class="btn btn-primary btn-sm" onclick="postDepreciation()"><i class="bi bi-calculator"></i> Ghi khấu hao</button></div>
    <div><button class="btn btn-success btn-sm" onclick="generateReport()"><i class="bi bi-file-earmark-bar-graph"></i> Tạo báo cáo 06-TSCĐ</button></div>
    <div><button class="btn btn-outline-success btn-sm" onclick="saveBatch()"><i class="bi bi-save"></i> Lưu batch</button></div>
    <div><button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> In</button></div>
    <div><span id="actionMsg" class="stats"></span></div>
</div>
<div id="batchInfo" class="batch-info" style="display:none"></div>
<div class="wrap-table mau0601" id="reportContainer">
<div class="row d-none" id="reportPlaceholder"><div class="col text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-2 d-block mb-2"></i>Chọn kỳ và nhấn "Tạo báo cáo 06-TSCĐ" để xem bảng tính khấu hao</div></div>
</div>
<div class="card-table mt-3 no-print">
    <div class="card-header-x d-flex justify-content-between align-items-center">
        <span><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm TSCĐ..." style="border:none;background:transparent;margin-left:4px;font-size:13px;width:200px"></span>
        <span class="stats" id="assetCount"></span>
    </div>
    <div style="overflow-x:auto;">
    <table class="table" id="assetTable">
        <thead><tr><th>Mã</th><th>Tên TSCĐ</th><th class="text-end">Nguyên giá</th><th>PP khấu hao</th><th class="text-end">KH tháng</th><th class="text-end">KH lũy kế</th><th class="text-end">GT còn lại</th><th style="width:80px"></th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
    </div>
</div>
<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">Lịch sử khấu hao</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-0">
        <table class="table"><thead><tr><th>Kỳ</th><th class="text-end">Số tiền</th><th class="text-end">KH lũy kế</th><th class="text-end">GT còn lại</th><th>Ngày ghi</th><th>Người ghi</th></tr></thead><tbody id="historyBody"></tbody></table>
    </div>
</div></div></div>
<script>
var API = '/api/fixed-assets';
var currentReport = null;

function generateReport() {
    var period = document.getElementById('depPeriod').value;
    if (!period) return;
    setMsg('Đang tạo báo cáo...');
    fetch('/api/fixed-assets/depreciation/report/' + period).then(function(r){return r.json();}).then(function(d){
        if (d.error) { setMsg('Lỗi: '+d.error, 'danger'); return; }
        currentReport = d.data || d;
        renderReport(currentReport);
        setMsg('Đã tạo báo cáo kỳ ' + period, 'success');
        checkBatch(period);
    }).catch(function(e){setMsg('Lỗi: '+e.message, 'danger');});
}

function renderReport(data) {
    var c = document.getElementById('reportContainer');
    var rows = data.rows || [];
    var batch = data.batch || null;
    var period = data.period || document.getElementById('depPeriod').value;
    var accountCodes = data.account_codes || ['627','641','642','623','241','335','242'];
    var accountNames = {'627':'SXC','641':'Bán hàng','642':'QLDN','623':'XDCB','241':'XDCB (241)','335':'CP phải trả','242':'CP trả trước'};
    var rowLabels = {'I':'I. TSCĐ hữu hình','II':'II. TSCĐ thuê tài chính','III':'III. TSCĐ vô hình','IV':'IV. Tổng cộng'};
    var rowTypes = {'I':'section','II':'section','III':'section','IV':'total'};

    var h = '<table class="table table-bordered"><thead><tr><th style="width:220px;position:sticky;left:0;z-index:2;background:#fff">Chỉ tiêu</th>';
    accountCodes.forEach(function(ac){
        h += '<th class="col-acc text-end">' + esc(ac) + '<br><span style="font-weight:400;font-size:10px">' + (accountNames[ac]||'') + '</span></th>';
    });
    h += '<th class="col-total text-end" style="background:#f0f4ff">Tổng cộng</th></tr></thead><tbody>';

    rows.forEach(function(row, idx){
        var cls = row.row_type === 'total' ? 'row-total' : (row.row_type === 'subtotal' ? 'row-subtotal' : (row.row_type === 'carried' ? 'carried-row' : ''));
        var label = row.label || rowLabels[row.row_key] || row.row_key;
        h += '<tr class="' + cls + '">';
        h += '<td class="row-name">' + esc(label) + '</td>';
        accountCodes.forEach(function(ac){
            var val = (row.accounts && row.accounts[ac]) || 0;
            h += '<td class="num-cell">' + fmt(val) + '</td>';
        });
        h += '<td class="num-cell" style="background:#f0f4ff">' + fmt(row.total || 0) + '</td>';
        h += '</tr>';
    });

    h += '</tbody></table>';

    var headerHtml = '<div class="header-row">';
    headerHtml += '<div><div class="company-name"><span id="companyName">CÔNG TY ABC</span></div>';
    headerHtml += '<div class="period-info">Mẫu số: 06-TSCĐ</div>';
    headerHtml += '<div class="period-info">Ban hành theo TT 99/2025/TT-BTC</div></div>';
    headerHtml += '<div class="text-center"><div class="report-title">BẢNG TÍNH VÀ PHÂN BỔ KHẤU HAO TSCĐ</div>';
    headerHtml += '<div class="sub-title">Tháng ' + period + '</div></div>';
    headerHtml += '<div class="text-end period-info">Đơn vị tính: VNĐ</div></div>';

    c.innerHTML = headerHtml + h;
}

function fmt(v) {
    if (v === null || v === undefined) v = 0;
    if (typeof v === 'number' && v === 0) return '0';
    var n = Number(v);
    return isNaN(n) ? '0' : n.toLocaleString();
}

function checkBatch(period) {
    fetch('/api/fixed-assets/depreciation/batch/' + period).then(function(r){
        if (r.status === 404) { document.getElementById('batchInfo').style.display = 'none'; return; }
        return r.json();
    }).then(function(d){
        if (d && !d.error) {
            var bi = document.getElementById('batchInfo');
            bi.style.display = 'flex';
            bi.innerHTML = '<span class="label">Đã lưu batch:</span> Kỳ ' + (d.period||period) + ' | ' +
                'Số TSCĐ: ' + (d.asset_count||0) + ' | Tổng KH: ' + fmt(d.total_company||0) +
                ' | Ngày tạo: ' + (d.created_at||'') +
                '<span class="badge bg-success ms-2">Đã lưu</span>';
        }
    });
}

function saveBatch() {
    if (!currentReport) { setMsg('Chưa có báo cáo. Tạo báo cáo trước.', 'warning'); return; }
    var period = document.getElementById('depPeriod').value;
    setMsg('Đang lưu...');
    fetch('/api/fixed-assets/depreciation/save-batch', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({period:period})
    }).then(function(r){return r.json();}).then(function(d){
        if (d.error) { setMsg('Lỗi: '+d.error, 'danger'); return; }
        setMsg('Đã lưu batch #' + d.batch_id + ' cho kỳ ' + period, 'success');
        checkBatch(period);
    }).catch(function(e){setMsg('Lỗi: '+e.message, 'danger');});
}

function loadAssets() {
    fetch(API).then(function(r){return r.json();}).then(function(data){
        var q=(document.getElementById('searchInput').value||'').toLowerCase();
        var active = data.filter(function(i){ return i.status === 'in_use' || i.status === '1'; });
        var f=active.filter(function(i){return !q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);});
        document.getElementById('assetCount').textContent='('+f.length+'/'+active.length+' TSCĐ)';
        var rows=f.map(function(i){
            var methodLabels={straight_line:'Đường thẳng',declining_balance:'Số dư giảm dần',sum_of_years:'Tổng số năm',production:'Sản lượng'};
            var ml=methodLabels[i.depreciation_method]||i.depreciation_method;
            return '<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td class="text-end">'+fmt(i.original_cost)+'</td><td>'+ml+'</td>'+
                '<td class="text-end">'+fmt(i.monthly_depreciation)+'</td>'+
                '<td class="text-end">'+fmt(i.accumulated_depreciation)+'</td>'+
                '<td class="text-end">'+fmt(i.net_book_value)+'</td>'+
                '<td>'+
                '<a href="#" class="btn-action me-1" onclick="showSchedule(\''+i.id+'\')" title="Xem lịch"><i class="bi bi-calendar3"></i></a>'+
                '<a href="#" class="btn-action" onclick="showHistory(\''+i.id+'\')" title="Lịch sử"><i class="bi bi-clock-history"></i></a>'+
                '</td></tr>';
        }).join('');
        document.getElementById('dataBody').innerHTML=rows||'<tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i>Không có TSCĐ</td></tr>';
    });
}

function postDepreciation() {
    var period = document.getElementById('depPeriod').value;
    if (!confirm('Ghi khấu hao cho kỳ '+period+'?')) return;
    setMsg('Đang xử lý...');
    fetch('/api/fixed-assets/depreciate', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({period:period})})
    .then(function(r){return r.json();}).then(function(d){
        if (d.error) { setMsg('Lỗi: '+d.error, 'danger'); return; }
        setMsg('Đã ghi '+d.posted+' bút toán khấu hao', 'success');
        loadAssets();
    }).catch(function(e){setMsg('Lỗi: '+e.message, 'danger');});
}

function showSchedule(id) { window.open('/api/fixed-assets/'+id+'/schedule','_blank'); }

function showHistory(id) {
    fetch(API+'/'+id+'/depreciation').then(function(r){return r.json();}).then(function(data){
        var rows = data.map(function(i){
            return '<tr><td>'+esc(i.period_code)+'</td><td class="text-end">'+fmt(i.depreciation_amount)+'</td><td class="text-end">'+fmt(i.accumulated)+'</td><td class="text-end">'+fmt(i.remaining)+'</td><td>'+(i.created_at||'')+'</td><td>'+esc(i.created_by||'')+'</td></tr>';
        }).join('');
        document.getElementById('historyBody').innerHTML = rows || '<tr><td colspan="6" class="empty-state">Chưa có lịch sử</td></tr>';
        $('#historyModal').modal('show');
    });
}

function setMsg(msg, type) {
    var el = document.getElementById('actionMsg');
    if (!type) { el.innerHTML = '<span class="text-muted">'+msg+'</span>'; return; }
    var colors = {success:'#16a34a',danger:'#dc2626',warning:'#d97706'};
    el.innerHTML = '<span style="color:'+(colors[type]||'#555')+'">'+msg+'</span>';
}

$('#searchInput').on('keyup', loadAssets);
loadAssets();
checkBatch(document.getElementById('depPeriod').value);
document.getElementById('depPeriod').addEventListener('change', function(){
    checkBatch(this.value);
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
