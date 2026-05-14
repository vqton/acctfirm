<?php $title = 'Báo cáo tình hình tài chính'; $activeMenu = 'fs_bc01'; ob_start(); ?>
<div class="toolbar">
    <h5>Báo cáo tình hình tài chính <span class="stats">(Mẫu B01-DN)</span></h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="periodSelect" onchange="loadData()"></select>
        <button class="btn btn-outline-primary btn-sm ms-2" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>
</div>

<div id="validationAlert" class="alert d-none"></div>

<div class="card-table"><table class="table table-sm table-hover fs-table">
    <thead><tr><th style="width:60px">Mã số</th><th>Chỉ tiêu</th><th class="text-end" style="width:200px">Cuối kỳ</th><th class="text-end" style="width:200px">Đầu kỳ</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
function loadData() {
    var period = $('#periodSelect').val() || '<?=date('Y')?>';
    $.get('/api/fs/bc01?period='+period, function(res) {
        var tbody=$('#dataBody'); tbody.empty();
        if(res.errors && res.errors.length){
            $('#validationAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Lỗi: '+res.errors.join('; '));
        } else if(res.total_assets === res.total_equity){
            $('#validationAlert').removeClass('d-none alert-danger').addClass('alert-success').html('<i class="bi bi-check-circle"></i> Bảng cân đối: Tài sản ('+parseFloat(res.total_assets).toLocaleString()+') = Nguồn vốn ('+parseFloat(res.total_equity).toLocaleString()+')');
        } else {
            $('#validationAlert').removeClass('d-none').addClass('alert-danger').text('Mất cân đối: Tài sản ≠ Nguồn vốn');
        }
        var section = '';
        res.items.forEach(function(r){
            var cls = r.is_total ? 'fw-bold fs-total' : (r.is_control ? 'fw-semibold' : '');
            var indent = r.ma_so.length > 3 ? 'padding-left:24px' : '';
            var val = r.value !== 0 ? parseFloat(r.value).toLocaleString() : '-';
            var prior = '-';
            tbody.append('<tr class="'+cls+'"><td>'+esc(r.ma_so)+'</td><td style="'+indent+'">'+esc(r.name_vi)+'</td><td class="text-end font-monospace">'+val+'</td><td class="text-end font-monospace text-muted">'+prior+'</td></tr>');
        });
    });
}
$(document).ready(function(){
    for(var y=<?=date('Y')?>; y>=2025; y--) $('#periodSelect').append('<option value="'+y+'">Năm '+y+'</option>');
    loadData();
});
</script>
<style>.fs-table td,.fs-table th{vertical-align:middle;padding:4px 12px}</style>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
