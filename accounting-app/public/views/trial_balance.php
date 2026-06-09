<?php // Màn hình: Bảng cân đối số phát sinh tài khoản
// API: GET /api/trial-balance
// Nghiệp vụ: Hiển thị tổng Nợ và tổng Có của tất cả tài khoản — kiểm tra Dr=Cr toàn hệ thống
// Tuân thủ: Tổng Nợ phải = Tổng Có — nếu không, hệ thống mất cân đối nghiêm trọng
$title = 'Bảng cân đối số phát sinh'; $activeMenu = 'trial_balance'; ob_start(); ?>
<div class="toolbar">
    <h5>Bảng cân đối số phát sinh</h5>
    <a href="/api/export/csv/trial-balance" class="btn btn-outline-success btn-sm me-1"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</a>
    <button class="btn btn-outline-primary btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
</div>
<div class="card-table"><table class="table table-hover table-sm">
    <thead><tr><th>TK</th><th>Tên tài khoản</th><th class="text-end">Nợ</th><th class="text-end">Có</th></tr></thead>
    <tbody id="dataBody"></tbody>
    <tfoot id="totalRow" class="table-active fw-bold"></tfoot>
</table></div>

<script>
// Tải bảng CĐPS — GET /api/trial-balance
// Kiểm tra nguyên tắc kế toán cơ bản: Tổng số dư Nợ = Tổng số dư Có
// Nếu mất cân đối → hiển thị cảnh báo đỏ — cần kiểm tra ngay
function loadData(){
    $.get('/api/trial-balance',function(d){
        var tbody=$('#dataBody');tbody.empty();
        d.accounts.forEach(function(a){
            tbody.append('<tr><td>'+esc(a.code)+'</td><td>'+esc(a.name)+'</td><td class="text-end font-monospace">'+(a.debit?parseFloat(a.debit).toLocaleString():'')+'</td><td class="text-end font-monospace">'+(a.credit?parseFloat(a.credit).toLocaleString():'')+'</td></tr>');
        });
        var bal=d.balanced?'<span class="text-success">Cân bằng</span>':'<span class="text-danger">Mất cân đối!</span>';
        $('#totalRow').html('<tr><td colspan="2">Tổng cộng</td><td class="text-end font-monospace">'+parseFloat(d.total_debit).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(d.total_credit).toLocaleString()+'</td></tr><tr class="text-center"><td colspan="4">'+bal+'</td></tr>');
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
