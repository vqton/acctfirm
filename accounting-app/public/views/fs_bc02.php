<?php // Màn hình: Báo cáo kết quả hoạt động kinh doanh (B02-DN)
$title = 'Báo cáo KQ HĐKD'; $activeMenu = 'fs_bc02'; ob_start(); ?>
<div class="toolbar">
    <h5>Báo cáo kết quả hoạt động kinh doanh <span class="stats">(Mẫu B02-DN)</span></h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="periodSelect" onchange="loadData()"></select>
        <button class="btn btn-outline-primary btn-sm ms-2" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
        <a class="btn btn-outline-success btn-sm ms-2" id="xbrlLink" href="#" target="_blank"><i class="bi bi-download"></i> Xuất XBRL (GDT)</a>
    </div>
</div>
<div id="validationAlert" class="alert d-none"></div>
<div class="card-table"><table class="table table-sm table-hover fs-table">
    <thead><tr><th style="width:60px">Mã số</th><th>Chỉ tiêu</th><th class="text-end" style="width:200px">Năm nay</th><th class="text-end" style="width:200px">Năm trước</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
function loadData() {
    var period = $('#periodSelect').val() || '<?=date('Y')?>';
    $('#xbrlLink').attr('href', '/api/fs/xbrl/bc02?period='+period);
    $.get('/api/fs/bc02?period='+period, function(res) {
        var tbody=$('#dataBody'); tbody.empty();
        if(res.errors && res.errors.length){
            $('#validationAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Lỗi: '+res.errors.join('; '));
        } else {
            $('#validationAlert').addClass('d-none');
        }
        res.items.forEach(function(r){
            var cls = r.is_total ? 'fw-bold fs-total' : (r.is_control ? 'fw-semibold' : '');
            var val = r.value !== 0 ? parseFloat(r.value).toLocaleString() : '-';
            tbody.append('<tr class="'+cls+'"><td>'+esc(r.ma_so)+'</td><td>'+esc(r.name_vi)+'</td><td class="text-end font-monospace">'+val+'</td><td class="text-end font-monospace text-muted">-</td></tr>');
        });
    });
}
$(document).ready(function(){
    for(var y=<?=date('Y')?>; y>=2025; y--) $('#periodSelect').append('<option value="'+y+'">Năm '+y+'</option>');
    loadData();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
