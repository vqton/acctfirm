<?php
$activeMenu = 'ccdc_allocations';
ob_start();
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3">Phân bổ CCDC (TK 242)</h1>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label for="period" class="form-label">Kỳ phân bổ</label>
                    <input type="month" id="period" class="form-control" value="<?= date('Y-m') ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" id="btnRun"><i class="bi bi-play"></i> Chạy phân bổ</button>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" id="btnPreview"><i class="bi bi-eye"></i> Xem trước</button>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-success" onclick="exportCSV('#resultTable', 'phan-bo-cong-cu-dung-cu')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
                </div>
            </div>
        </div>
    </div>

    <div id="resultArea" class="d-none">
        <div class="card mb-3">
            <div class="card-body">
                <div class="alert" id="summaryAlert" role="alert"></div>
                <table class="table table-sm" id="resultTable">
                    <thead><tr><th>Mã CCDC</th><th>Tên</th><th>Số tiền</th><th>TK Chi phí</th><th>Bút toán</th><th>Trạng thái</th></tr></thead>
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
                <thead><tr><th>Kỳ</th><th>Mã CCDC</th><th>Tên</th><th>Số tiền</th><th>TK CP</th><th>Bút toán</th><th>Trạng thái</th><th>Ngày</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(function() {
    function loadHistory() {
        $.get('/api/inventory/ccdc-allocations/history', function(res) {
            var tbody = $('#historyTable tbody').empty();
            (res.data || []).forEach(function(r) {
                tbody.append(
                    '<tr><td>' + r.period + '</td><td>' + r.code + '</td><td>' + r.name + '</td>' +
                    '<td class="text-end">' + parseInt(r.amount).toLocaleString() + '</td><td>' + r.expense_account + '</td>' +
                    '<td><code>' + (r.transaction_id || '') + '</code></td><td><span class="badge bg-success">' + r.status + '</span></td>' +
                    '<td>' + (r.created_at || '') + '</td></tr>'
                );
            });
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
                alert.addClass('alert-success').text('Hoàn tất: ' + d.success + '/' + d.total + ' CCDC đã được phân bổ.');
                (d.results || []).forEach(function(r) {
                    var cls = r.error ? 'table-danger' : '';
                    tbody.append(
                        '<tr class="' + cls + '"><td>' + r.code + '</td><td>' + r.name + '</td>' +
                        '<td class="text-end">' + (r.amount ? parseInt(r.amount).toLocaleString() : '-') + '</td>' +
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
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
