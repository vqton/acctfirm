<?php $title = 'Chi tiết bảng lương'; $activeMenu = 'payroll_entries'; ob_start(); ?>
<div class="toolbar">
    <h5>Chi tiết bảng lương</h5>
    <div>
        <a href="/tien-luong/bang-luong" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
    </div>
</div>
<div id="entryDetail">
    <p class="text-muted text-center py-5">Đang tải...</p>
</div>
<script>
$(document).ready(function(){
    var id = window.location.pathname.split('/').pop();
    $.get('/api/payroll/entries/' + id, function(d) {
        var e = d.data || d;
        var html = '<div class="card border-0 shadow-sm"><div class="card-body">';
        html += '<table class="table table-bordered"><tr><td style="width:200px">Mã nhân viên</td><td>' + esc(e.employee_code||'') + '</td></tr>';
        html += '<tr><td>Họ tên</td><td>' + esc(e.full_name||'') + '</td></tr>';
        html += '<tr><td>Kỳ lương</td><td>' + esc(e.period_name||'') + '</td></tr>';
        html += '<tr><td>Lương gross</td><td class="font-monospace text-end">' + fmt(e.gross_salary||0) + '</td></tr>';
        html += '<tr><td>Bảo hiểm</td><td class="font-monospace text-end">' + fmt(e.insurance_deduction||0) + '</td></tr>';
        html += '<tr><td>Thuế TNCN</td><td class="font-monospace text-end">' + fmt(e.tax_deduction||0) + '</td></tr>';
        html += '<tr><td>Thực nhận</td><td class="font-monospace text-end fw-bold">' + fmt(e.net_pay||0) + '</td></tr>';
        html += '<tr><td>Trạng thái</td><td>' + (e.status||'') + '</td></tr>';
        html += '</table></div></div>';
        $('#entryDetail').html(html);
    }).fail(function(){ $('#entryDetail').html('<div class="alert alert-danger">Không tìm thấy bảng lương</div>'); });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
