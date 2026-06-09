<?php // Màn hình: Điều chỉnh hồi tố (Prior Period Adjustment)
// API: GET /api/periods, GET /api/transactions, POST /api/corrections/supplementary
// Nghiệp vụ: Điều chỉnh sai sót của kỳ trước sau khi đã khóa sổ
// Hạch toán: Nợ/Có TK liên quan — Đối ứng 421 (LN chưa phân phối) nếu ảnh hưởng trọng yếu
// TT 99: Sai sót trọng yếu phải điều chỉnh hồi tố, điều chỉnh số liệu so sánh
$title = 'Điều chỉnh hồi tố'; $activeMenu = 'prior_period_adjustment'; ob_start(); ?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3">Điều chỉnh hồi tố</h1>
    <p class="text-muted">Điều chỉnh sai sót của các kỳ kế toán đã đóng. Ảnh hưởng trọng yếu phải điều chỉnh vào TK 421 (LN chưa phân phối).</p>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Kỳ kế toán đã đóng</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" id="closedPeriodsTable">
                        <thead><tr><th>Kỳ</th><th>Ngày bắt đầu</th><th>Ngày kết thúc</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Bút toán điều chỉnh gần đây</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Ngày</th><th>Diễn giải</th><th>Số tiền</th><th>Trạng thái</th></tr></thead>
                        <tbody id="recentAdjustments"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Tạo bút toán điều chỉnh hồi tố</h6>
        </div>
        <div class="card-body">
            <form id="ppaForm">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Kỳ gốc (kỳ phát sinh sai sót)</label>
                        <select class="form-select" id="ppaPeriod" required></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ngày chứng từ</label>
                        <input type="date" class="form-control" id="ppaDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mức trọng yếu</label>
                        <select class="form-select" id="ppaMateriality">
                            <option value="material">Trọng yếu — điều chỉnh hồi tố (ảnh hưởng TK 421)</option>
                            <option value="immaterial">Không trọng yếu — điều chỉnh trong kỳ hiện tại</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diễn giải</label>
                    <input type="text" class="form-control" id="ppaDesc" placeholder="Mô tả sai sót cần điều chỉnh..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Số chứng từ gốc (nếu có)</label>
                    <input type="text" class="form-control" id="ppaRef" placeholder="CT-...">
                </div>
                <h6 class="mb-2">Định khoản điều chỉnh</h6>
                <table class="table table-sm" id="entryLines">
                    <thead><tr><th>TK Nợ</th><th>TK Có</th><th>Số tiền</th><th></th></tr></thead>
                    <tbody>
                        <tr>
                            <td><input type="text" class="form-control form-control-sm dr-account" placeholder="1111, 331..." style="width:120px"></td>
                            <td><input type="text" class="form-control form-control-sm cr-account" placeholder="1111, 331..." style="width:120px"></td>
                            <td><input type="number" class="form-control form-control-sm entry-amount" step="1000" min="0" style="width:150px"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-x"></i></button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addLine()"><i class="bi bi-plus"></i> Thêm dòng</button>

                <div class="alert alert-warning small" id="ppaWarning" class="d-none">
                    <i class="bi bi-exclamation-triangle"></i> Tổng Nợ phải bằng tổng Có. Vui lòng kiểm tra lại.
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Ghi nhận điều chỉnh hồi tố</button>
            </form>
        </div>
    </div>
</div>

<script>
function addLine() {
    var row = '<tr>' +
        '<td><input type="text" class="form-control form-control-sm dr-account" placeholder="1111, 331..." style="width:120px"></td>' +
        '<td><input type="text" class="form-control form-control-sm cr-account" placeholder="1111, 331..." style="width:120px"></td>' +
        '<td><input type="number" class="form-control form-control-sm entry-amount" step="1000" min="0" style="width:150px"></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x"></i></button></td>' +
        '</tr>';
    $('#entryLines tbody').append(row);
}

function calcTotal() {
    var dr = 0, cr = 0;
    $('#entryLines tbody tr').each(function() {
        var d = $(this).find('.dr-account').val().trim();
        var c = $(this).find('.cr-account').val().trim();
        var a = parseFloat($(this).find('.entry-amount').val()) || 0;
        if (d && a > 0) dr += a;
        if (c && a > 0) cr += a;
    });
    return {dr: dr, cr: cr};
}

$(function() {
    // Load closed periods
    $.get('/api/periods', function(periods) {
        var sel = $('#ppaPeriod');
        var tbl = $('#closedPeriodsTable tbody');
        (periods || []).forEach(function(p) {
            if (p.status === 'closed') {
                sel.append('<option value="'+p.id+'">'+p.name+'</option>');
                tbl.append('<tr><td>'+p.name+'</td><td>'+p.start_date+'</td><td>'+p.end_date+'</td>' +
                    '<td><button class="btn btn-sm btn-outline-secondary" onclick="$(\'#ppaPeriod\').val(\''+p.id+'\')"><i class="bi bi-arrow-right"></i> Chọn</button></td></tr>');
            }
        });
        if (sel.find('option').length === 0) {
            tbl.append('<tr><td colspan="4" class="text-muted text-center py-3">Chưa có kỳ nào đã đóng</td></tr>');
        }
    });

    // Load recent adjustments (corrections with retro flag)
    $.get('/api/transactions', {status: 'posted', limit: 10}, function(data) {
        var tbody = $('#recentAdjustments');
        (data.data || []).forEach(function(t) {
            if (t.description && t.description.toLowerCase().includes('điều chỉnh hồi tố')) {
                tbody.append('<tr><td>'+t.transaction_date+'</td><td>'+t.description+'</td>' +
                    '<td class="text-end">'+parseInt(t.total_amount||0).toLocaleString()+'</td>' +
                    '<td><span class="badge bg-success">posted</span></td></tr>');
            }
        });
        if (tbody.find('tr').length === 0) {
            tbody.append('<tr><td colspan="4" class="text-muted text-center py-3">Chưa có điều chỉnh hồi tố nào</td></tr>');
        }
    });

    // Validate Dr = Cr
    $('#entryLines').on('input', function() {
        var t = calcTotal();
        if (t.dr > 0 || t.cr > 0) {
            if (Math.abs(t.dr - t.cr) > 10) {
                $('#ppaWarning').removeClass('d-none').text('⚠️ Tổng Nợ: ' + t.dr.toLocaleString() + ' — Tổng Có: ' + t.cr.toLocaleString() + '. Chênh lệch: ' + Math.abs(t.dr - t.cr).toLocaleString());
            } else {
                $('#ppaWarning').addClass('d-none');
            }
        }
    });

    $('#ppaForm').submit(function(e){e.preventDefault();
        var t = calcTotal();
        if (Math.abs(t.dr - t.cr) > 10) {
            showToast('Tổng Nợ không khớp tổng Có. Vui lòng kiểm tra lại.', 'error');
            return;
        }

        var lines = [];
        $('#entryLines tbody tr').each(function() {
            var d = $(this).find('.dr-account').val().trim();
            var c = $(this).find('.cr-account').val().trim();
            var a = parseFloat($(this).find('.entry-amount').val()) || 0;
            if (d && a > 0) lines.push({account_code: d, amount: a, is_debit: true});
            if (c && a > 0) lines.push({account_code: c, amount: a, is_debit: false});
        });

        var data = {
            description: '[Điều chỉnh hồi tố] ' + $('#ppaDesc').val(),
            reference: $('#ppaRef').val() || 'PPA-' + Date.now(),
            lines: lines,
            period_id: $('#ppaPeriod').val(),
            materiality: $('#ppaMateriality').val(),
            transaction_date: $('#ppaDate').val(),
        };

        $.ajax({
            url: '/api/journal/draft',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(res) {
                showToast('Đã tạo bút toán điều chỉnh hồi tố. Vui lòng vào Phê duyệt để duyệt.', 'success');
                $('#ppaForm')[0].reset();
                $('#entryLines tbody').html('<tr>' + $('#entryLines tbody').html() + '</tr>');
            },
            error: function(x) {
                var m = 'Lỗi';
                try { m = JSON.parse(x.responseText).error; } catch(e) {}
                showToast(m, 'error');
            }
        });
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
