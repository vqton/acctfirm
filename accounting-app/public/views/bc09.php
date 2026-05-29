<?php
$title = 'Thuyết minh Báo cáo tài chính (BC 09)';
$activeMenu = 'fs_bc09';
ob_start();
?>
<style>
.bc09-section { background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.06); margin-bottom:16px; overflow:hidden; }
.bc09-section h6 { background:#f8f9fc; padding:10px 16px; margin:0; font-weight:600; font-size:14px; border-bottom:1px solid #e2e6ef; }
.bc09-section .bc09-body { padding:12px 16px; }
.bc09-section table { font-size:13px; margin:0; }
.bc09-section table th { font-weight:600; color:#374151; border-bottom:1px solid #e2e6ef; padding:6px 8px; }
.bc09-section table td { padding:6px 8px; border-bottom:1px solid #f0f0f5; }
.info-row { display:flex; padding:6px 0; border-bottom:1px solid #f9fafb; }
.info-label { width:200px; font-weight:500; color:#374151; font-size:13px; }
.info-value { color:#1a2a3a; font-size:13px; }
</style>

<div class="toolbar">
    <h5>Thuyết minh Báo cáo tài chính (BC 09)</h5>
    <div>
        <select id="year" class="form-select d-inline-block" style="width:auto;display:inline">
            <option value="<?= date('Y') ?>" selected><?= date('Y') ?></option>
        </select>
        <button class="btn btn-primary btn-sm" id="btnLoad"><i class="bi bi-arrow-repeat"></i> Tải dữ liệu</button>
        <button class="btn btn-success btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> In</button>
    </div>
</div>

<div id="bc09Content">
    <div class="text-center text-muted py-5">Vui lòng chọn năm và nhấn "Tải dữ liệu"</div>
</div>

<script>
function loadData() {
    var year = $('#year').val();
    $('#bc09Content').html('<div class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Đang tải dữ liệu...</div>');
    $.get('/api/fs/tt99?period=' + year, function(data) {
        var d = data;
        var h = '';
        h += '<div class="bc09-section">';
        h += '<h6>I. Thông tin chung về doanh nghiệp</h6>';
        h += '<div class="bc09-body">';
        h += '<div class="info-row"><span class="info-label">Chế độ kế toán áp dụng</span><span class="info-value">' + esc(d.accounting_policy) + '</span></div>';
        h += '<div class="info-row"><span class="info-label">Đơn vị tiền tệ</span><span class="info-value">' + esc(d.currency) + '</span></div>';
        h += '<div class="info-row"><span class="info-label">Năm tài chính</span><span class="info-value">' + esc(d.fiscal_year) + '</span></div>';
        h += '<div class="info-row"><span class="info-label">Phương pháp tính giá xuất kho</span><span class="info-value">' + esc(d.inventory_method) + '</span></div>';
        h += '<div class="info-row"><span class="info-label">Phương pháp khấu hao TSCĐ</span><span class="info-value">' + esc(d.depreciation_method) + '</span></div>';
        h += '<div class="info-row"><span class="info-label">Phương pháp tính thuế GTGT</span><span class="info-value">' + esc(d.vat_method) + '</span></div>';
        h += '</div></div>';

        h += '<div class="bc09-section">';
        h += '<h6>II. Doanh thu bán hàng và cung cấp dịch vụ (TK 511)</h6>';
        h += '<div class="bc09-body">';
        h += '<table class="table"><thead><tr><th>Tháng</th><th class="text-end">Doanh thu</th></tr></thead><tbody>';
        var totalRev = 0;
        (d.revenue_breakdown || []).forEach(function(item) {
            totalRev += parseFloat(item.amount) || 0;
            h += '<tr><td>Tháng ' + (item.month || '') + '</td><td class="text-end font-monospace">' + parseInt(item.amount).toLocaleString() + '</td></tr>';
        });
        h += '<tr class="fw-bold"><td>Tổng cộng</td><td class="text-end font-monospace">' + parseInt(totalRev).toLocaleString() + '</td></tr>';
        h += '</tbody></table></div></div>';

        h += '<div class="bc09-section">';
        h += '<h6>III. Chi phí theo yếu tố</h6>';
        h += '<div class="bc09-body">';
        h += '<table class="table"><thead><tr><th>Yếu tố chi phí</th><th class="text-end">Số tiền</th></tr></thead><tbody>';
        (d.expense_by_nature || []).forEach(function(item) {
            h += '<tr><td>' + esc(item.name) + '</td><td class="text-end font-monospace">' + parseInt(item.amount).toLocaleString() + '</td></tr>';
        });
        h += '</tbody></table></div></div>';

        h += '<div class="bc09-section">';
        h += '<h6>IV. Tình hình tăng, giảm TSCĐ</h6>';
        h += '<div class="bc09-body">';
        if ((d.asset_movements || []).length > 0) {
            h += '<table class="table"><thead><tr><th>Chỉ tiêu</th><th class="text-end">Nguyên giá</th><th class="text-end">Hao mòn lũy kế</th></tr></thead><tbody>';
            (d.asset_movements || []).forEach(function(item) {
                h += '<tr><td>' + esc(item.name) + '</td><td class="text-end font-monospace">' + parseInt(item.original_cost).toLocaleString() + '</td><td class="text-end font-monospace">' + parseInt(item.accumulated_depreciation).toLocaleString() + '</td></tr>';
            });
            h += '</tbody></table>';
        } else {
            h += '<p class="text-muted mb-0">Chưa có dữ liệu TSCĐ.</p>';
        }
        h += '</div></div>';

        h += '<div class="bc09-section">';
        h += '<h6>V. Giao dịch với các bên liên quan</h6>';
        h += '<div class="bc09-body"><p class="text-muted mb-0">Không có giao dịch trọng yếu với các bên liên quan.</p></div></div>';

        h += '<div class="bc09-section">';
        h += '<h6>VI. Các khoản nợ tiềm tàng, cam kết và các thông tin khác</h6>';
        h += '<div class="bc09-body"><p class="text-muted mb-0">Không có khoản nợ tiềm tàng hoặc cam kết trọng yếu.</p></div></div>';

        $('#bc09Content').html(h);
    }).fail(function() {
        $('#bc09Content').html('<div class="text-center py-5 text-danger">Không thể tải dữ liệu. Vui lòng thử lại sau.</div>');
    });
}

$('#btnLoad').click(loadData);
$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
