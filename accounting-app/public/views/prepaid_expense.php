<?php // Màn hình: Phân bổ chi phí trả trước (TK 242)
// API: GET /api/inventory/ccdc-allocations/history, POST /api/inventory/ccdc-allocations/run
// Nghiệp vụ: Chi phí trả trước ngắn hạn (thuê nhà, bảo hiểm, công cụ...) ghi nhận vào 242
// và phân bổ dần vào chi phí theo thời gian sử dụng.
// TT 99: Phân bổ chi phí trả trước tối đa 12 tháng (ngắn hạn) hoặc trên 12 tháng (dài hạn)
$title = 'Phân bổ chi phí trả trước'; $activeMenu = 'prepaid_expense'; ob_start(); ?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3">Phân bổ chi phí trả trước (TK 242)</h1>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Kỳ phân bổ</h6>
                    <div class="input-group">
                        <input type="month" id="period" class="form-control" value="<?= date('Y-m') ?>">
                        <button class="btn btn-primary" id="btnRun"><i class="bi bi-play"></i> Chạy</button>
                        <button class="btn btn-outline-secondary" id="btnPreview"><i class="bi bi-eye"></i> Xem trước</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col"><small class="text-muted">Tổng CP chờ phân bổ</small><br><strong id="totalPending">0</strong></div>
                        <div class="col"><small class="text-muted">Đã phân bổ kỳ này</small><br><strong id="totalAllocated">0</strong></div>
                        <div class="col"><small class="text-muted">Còn lại</small><br><strong id="remainingBalance">0</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="resultArea" class="d-none">
        <div class="card mb-3">
            <div class="card-body">
                <div class="alert" id="summaryAlert" role="alert"></div>
                <table class="table table-sm" id="resultTable">
                    <thead><tr><th>Mã</th><th>Tên</th><th>Số tiền</th><th>TK Chi phí</th><th>Bút toán</th><th>Trạng thái</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Lịch sử phân bổ</h5>
        </div>
        <div class="card-body">
            <table class="table table-sm table-hover" id="historyTable">
                <thead><tr><th>Kỳ</th><th>Mã</th><th>Tên</th><th>Số tiền</th><th>TK CP</th><th>Bút toán</th><th>Trạng thái</th><th>Ngày</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(function() {
    function fmt(n) { return parseInt(n).toLocaleString('vi-VN'); }

    function loadHistory() {
        $.get('/api/inventory/ccdc-allocations/history', function(res) {
            var tbody = $('#historyTable tbody').empty();
            var total = 0, allocated = 0, remaining = 0;
            (res.data || []).forEach(function(r) {
                tbody.append(
                    '<tr><td>' + r.period + '</td><td>' + r.code + '</td><td>' + r.name + '</td>' +
                    '<td class="text-end">' + fmt(r.amount) + '</td><td>' + (r.expense_account || '-') + '</td>' +
                    '<td><code>' + (r.transaction_id || '') + '</code></td>' +
                    '<td><span class="badge bg-success">' + r.status + '</span></td>' +
                    '<td>' + (r.created_at || '') + '</td></tr>'
                );
                total += parseFloat(r.amount||0);
                if (r.status === 'posted') allocated += parseFloat(r.amount||0);
            });
            remaining = total - allocated;
            $('#totalPending').text(fmt(total));
            $('#totalAllocated').text(fmt(allocated));
            $('#remainingBalance').text(fmt(Math.max(0, remaining)));
        });
    }

    $('#btnRun').click(function() {
        var period = $('#period').val();
        var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xử lý...');
        $.post('/api/inventory/ccdc-allocations/run', JSON.stringify({period: period}), function(res) {
            var d = res.data;
            var alert = $('#summaryAlert').removeClass('alert-success alert-danger');
            var tbody = $('#resultTable tbody').empty();
            if (d.error) {
                alert.addClass('alert-danger').text('Lỗi: ' + d.error);
            } else {
                alert.addClass('alert-success').text('Hoàn tất: ' + d.success + '/' + d.total + ' khoản đã được phân bổ.');
                (d.results || []).forEach(function(r) {
                    var cls = r.error ? 'table-danger' : '';
                    tbody.append(
                        '<tr class="' + cls + '"><td>' + r.code + '</td><td>' + r.name + '</td>' +
                        '<td class="text-end">' + fmt(r.amount) + '</td>' +
                        '<td>' + (r.expense_account || '-') + '</td>' +
                        '<td><code>' + (r.transaction_id || '-') + '</code></td>' +
                        '<td>' + (r.error ? '<span class="text-danger">' + r.error + '</span>' : '<span class="badge bg-success">Đã post</span>') + '</td></tr>'
                    );
                });
            }
            $('#resultArea').removeClass('d-none');
            btn.prop('disabled', false).html('<i class="bi bi-play"></i> Chạy phân bổ');
            loadHistory();
        }).fail(function(xhr) {
            var err = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Lỗi máy chủ';
            $('#summaryAlert').removeClass('alert-success').addClass('alert-danger').text('Lỗi: ' + err);
            $('#resultArea').removeClass('d-none');
            btn.prop('disabled', false).html('<i class="bi bi-play"></i> Chạy phân bổ');
        });
    });

    loadHistory();
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
