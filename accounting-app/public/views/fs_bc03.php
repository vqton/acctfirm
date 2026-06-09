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
        <button class="btn btn-outline-success btn-sm ms-1" id="saveManualBtn" onclick="saveManual()" style="display:none"><i class="bi bi-save"></i> Lưu giá trị nhập tay</button>
        <button class="btn btn-outline-success btn-sm ms-1" onclick="exportCsv()"><i class="bi bi-file-earmark-excel"></i> CSV</button>
        <button class="btn btn-outline-primary btn-sm ms-1" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
        <a class="btn btn-outline-success btn-sm ms-1" id="xbrlLink" href="#" target="_blank"><i class="bi bi-download"></i> Xuất XBRL (GDT)</a>
    </div>
</div>

<div id="validationAlert" class="alert d-none"></div>

<div class="card-table"><table class="table table-sm table-hover fs-table">
    <thead><tr><th style="width:60px">Mã số</th><th>Chỉ tiêu</th><th class="text-end" style="width:220px">Năm nay</th><th class="text-end" style="width:200px">Năm trước</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
<script>
var manualValues = {};
var manualDirty = {};

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
        manualValues = {};
        manualDirty = {};
        $('#saveManualBtn').hide();
        var prior = res.prior || {};
        res.items.forEach(function(r){
            var cls = r.is_total ? 'fw-bold fs-total' : (r.is_control ? 'fw-semibold' : '');
            if (getMethod() === 'indirect' && r.is_manual) {
                var manVal = r.value !== 0 ? r.value : '';
                manualValues[r.ma_so] = r.value;
                var pVal = VAS.fmt(prior[r.ma_so]);
                var inputHtml = '<input type="text" class="form-control form-control-sm text-end font-monospace manual-input" data-maso="'+esc(r.ma_so)+'" value="'+manVal+'" placeholder="0" style="width:200px">';
                tbody.append('<tr class="'+cls+'"><td>'+esc(r.ma_so)+'</td><td>'+esc(r.name_vi)+'</td><td class="text-end">'+inputHtml+'</td><td class="text-end font-monospace text-muted vas-number">'+pVal+'</td></tr>');
            } else {
                var val = VAS.fmt(r.value || 0);
                var pVal = VAS.fmt(prior[r.ma_so]);
                var row = '<tr class="'+cls+'"><td>'+esc(r.ma_so)+'</td><td>'+esc(r.name_vi)+'</td><td class="text-end vas-number">'+val+'</td>';
                if (getMethod() === 'indirect') {
                    row += '<td class="text-end vas-number text-muted">'+pVal+'</td>';
                } else {
                    row += '<td class="text-end vas-number text-muted"></td>';
                }
                row += '</tr>';
                tbody.append(row);
            }
        });
        $('.manual-input').off('input').on('input', function(){
            var maso = $(this).data('maso');
            var raw = $(this).val().replace(/,/g, '');
            var num = parseFloat(raw);
            if (!isNaN(num)) {
                manualDirty[maso] = num;
            } else {
                delete manualDirty[maso];
            }
            $('#saveManualBtn').toggle(Object.keys(manualDirty).length > 0);
        });
    });
}

function saveManual() {
    var period = $('#periodSelect').val() || '<?=date('Y')?>';
    var values = {};
    for (var k in manualDirty) { values[k] = manualDirty[k]; }
    $.ajax({
        url: '/api/fs/bc03/manual-values',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ statement: 'BC03', period: period, values: values }),
        success: function(res) {
            manualDirty = {};
            $('#saveManualBtn').hide();
            loadData();
        },
        error: function(xhr) { alert('Lỗi lưu: ' + (xhr.responseJSON ? xhr.responseJSON.error : xhr.statusText)); }
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
