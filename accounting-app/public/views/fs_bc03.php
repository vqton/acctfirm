<?php // Màn hình: Báo cáo lưu chuyển tiền tệ (B03-DN)
$title = 'Báo cáo lưu chuyển tiền tệ'; $activeMenu = 'fs_bc03'; ob_start(); ?>
<div class="toolbar">
    <h5>Báo cáo lưu chuyển tiền tệ <span class="stats">(Mẫu B03-DN)</span></h5>
    <div>
        <div class="btn-group btn-group-sm me-2" role="group">
            <input type="radio" class="btn-check" name="methodRadio" id="methodIndirect" value="indirect" checked onchange="loadData()">
            <label class="btn btn-outline-secondary" for="methodIndirect">Gián tiếp</label>
            <input type="radio" class="btn-check" name="methodRadio" id="methodDirect" value="direct" onchange="loadData()">
            <label class="btn btn-outline-secondary" for="methodDirect">Trực tiếp</label>
        </div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="periodSelect" onchange="loadData()"></select>
        <button class="btn btn-outline-success btn-sm ms-1" onclick="exportCsv()"><i class="bi bi-file-earmark-excel"></i> CSV</button>
        <button class="btn btn-outline-primary btn-sm ms-1" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
        <a class="btn btn-outline-success btn-sm ms-1" id="xbrlLink" href="#" target="_blank"><i class="bi bi-download"></i> Xuất XBRL (GDT)</a>
    </div>
</div>

<div id="validationAlert" class="alert d-none"></div>

<div class="card-table"><table class="table table-sm table-hover fs-table">
    <thead><tr><th style="width:60px">Mã số</th><th>Chỉ tiêu</th><th class="text-end" style="width:200px">Năm nay</th><th class="text-end" style="width:200px">Năm trước</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
function getMethod() {
    return document.querySelector('input[name="methodRadio"]:checked').value;
}

function getEndpoint() {
    return getMethod() === 'direct' ? '/api/fs/bc03-direct' : '/api/fs/bc03';
}

function loadData() {
    var period = $('#periodSelect').val() || '<?=date('Y')?>';
    $('#xbrlLink').attr('href', '/api/fs/xbrl/bc03?period='+period);
    var endpoint = getEndpoint();
    $.get(endpoint+'?period='+period, function(res) {
        var tbody=$('#dataBody'); tbody.empty();
        if(res.errors && res.errors.length){
            $('#validationAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Lỗi: '+res.errors.join('; '));
        } else {
            $('#validationAlert').addClass('d-none');
        }
        var prior = res.prior || {};
        res.items.forEach(function(r){
            var cls = r.is_total ? 'fw-bold fs-total' : (r.is_control ? 'fw-semibold' : '');
            var val = r.value !== 0 ? parseFloat(r.value).toLocaleString() : '-';
            var pVal = prior[r.ma_so] !== undefined && prior[r.ma_so] !== 0 ? parseFloat(prior[r.ma_so]).toLocaleString() : '-';
            var row = '<tr class="'+cls+'"><td>'+esc(r.ma_so)+'</td><td>'+esc(r.name_vi)+'</td><td class="text-end font-monospace">'+val+'</td>';
            if (getMethod() === 'indirect') {
                row += '<td class="text-end font-monospace text-muted">'+pVal+'</td>';
            } else {
                row += '<td class="text-end font-monospace text-muted"></td>';
            }
            row += '</tr>';
            tbody.append(row);
        });
    });
}

function exportCsv() {
    var period = $('#periodSelect').val() || '<?=date('Y')?>';
    var method = getMethod();
    window.location = '/api/export/csv/bc03?period=' + encodeURIComponent(period) + '&method=' + encodeURIComponent(method);
}

$(document).ready(function(){
    for(var y=<?=date('Y')?>; y>=2025; y--) $('#periodSelect').append('<option value="'+y+'">Năm '+y+'</option>');
    loadData();
});
</script>
<style>.fs-table td,.fs-table th{vertical-align:middle;padding:4px 12px}</style>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
