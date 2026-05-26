<?php // Màn hình: Đối soát sổ sách kế toán
// API: GET /api/reconciliation/run?type=all
// Nghiệp vụ: So sánh số dư GL (Sổ cái) với số dư sổ chi tiết (subledger) cho các module: AR, AP, Cash, Bank, Inventory, FA
// Rủi ro: Chênh lệch > 1,000 VND cảnh báo — cần điều chỉnh trước khi khóa sổ
$title = 'Đối soát'; $activeMenu = 'reconciliation'; ob_start(); ?>
<div class="toolbar">
    <div><h5>Đối soát sổ sách</h5><span class="stats">Kiểm tra GL vs sổ chi tiết</span></div>
    <button class="btn btn-primary" onclick="runRecon()"><i class="bi bi-arrow-repeat"></i> Chạy đối soát</button>
</div>
<div id="reconResults">
    <div class="text-muted text-center py-5"><i class="bi bi-info-circle"></i> Nhấn "Chạy đối soát" để bắt đầu</div>
</div>

<script>
// Chạy đối soát GL với sổ chi tiết — GET /api/reconciliation/run?type=all
// Hiển thị từng module dạng card: GL balance vs subledger balance + chênh lệch
// Cảnh báo vàng nếu chênh lệch > 1,000 VND (badge warning/danger)
function runRecon() {
    $('#reconResults').html('<div class="text-center py-5"><div class="spinner-border"></div></div>');
    $.get('/api/reconciliation/run?type=all', function(res) {
        var data = res.data || res;
        var html = '<div class="row g-3">';
        var labels = {ar:'AR - Công nợ phải thu', ap:'AP - Công nợ phải trả', cash:'Tiền mặt', bank:'Tiền gửi NH', inventory:'Hàng tồn kho', fa:'TSCĐ'};
        Object.keys(data).forEach(function(k) {
            var r = data[k];
            var badge = r.status === 'matched' ? 'success' : (r.status === 'unmatched' ? 'warning' : 'danger');
            html += '<div class="col-md-6"><div class="card border-' + badge + '"><div class="card-body">'
                + '<h6 class="card-title">' + (labels[k] || k) + ' <span class="badge bg-' + badge + ' float-end">' + r.status + '</span></h6>'
                + '<table class="table table-sm mb-0"><tr><td>GL:</td><td class="text-end">' + fmt(r.gl_balance) + '</td></tr>'
                + '<tr><td>Sổ chi tiết:</td><td class="text-end">' + fmt(r.subledger_balance) + '</td></tr>'
                + '<tr class="' + (Math.abs(r.difference) > 1000 ? 'table-warning' : '') + '"><th>Chênh lệch:</th><th class="text-end">' + fmt(r.difference) + '</th></tr>'
                + '</table></div></div></div>';
        });
        html += '</div>';
        $('#reconResults').html(html);
    }).fail(function(x) { $('#reconResults').html('<div class="alert alert-danger">Lỗi: ' + x.statusText + '</div>'); });
}
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' ₫'; }
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
