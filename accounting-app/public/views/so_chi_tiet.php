<?php
$title = 'Sổ chi tiết tài khoản';
$activeMenu = 'so_chi_tiet';
ob_start();
?>
<div class="toolbar">
    <h5>Sổ chi tiết tài khoản</h5>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form id="filterForm" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Tài khoản</label>
                <select class="form-select" id="accountCode" style="width:200px"></select>
            </div>
            <div class="col-auto">
                <label class="form-label">Nhóm theo</label>
                <select class="form-select" id="groupBy">
                    <option value="">Không nhóm</option>
                    <option value="customer">Khách hàng (TK 131)</option>
                    <option value="supplier">Nhà cung cấp (TK 331)</option>
                    <option value="employee">Nhân viên (TK 334)</option>
                    <option value="project">Dự án</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Từ ngày</label>
                <input type="date" class="form-control" id="fromDate">
            </div>
            <div class="col-auto">
                <label class="form-label">Đến ngày</label>
                <input type="date" class="form-control" id="toDate" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Xem</button>
            </div>
        </form>
    </div>
</div>

<div id="resultArea" class="d-none">
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-auto"><strong id="accountTitle"></strong></div>
                <div class="col-auto text-muted" id="accountType"></div>
                <div class="col-auto ms-auto font-monospace">SD ĐK: <span id="openingBalance" class="fw-semibold">0</span></div>
                <div class="col-auto font-monospace">SD CK: <span id="closingBalance" class="fw-semibold">0</span></div>
            </div>
        </div>
    </div>

    <div id="objectGroups"></div>

    <div id="detailTableArea" class="card-table d-none">
        <table class="table table-hover table-sm" id="detailTable">
            <thead><tr><th>Ngày</th><th>Tham chiếu</th><th>Diễn giải</th><th class="text-end">Nợ</th><th class="text-end">Có</th><th class="text-end">Số dư</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
function loadAccounts() {
    $.get('/api/gl/accounts', function(data) {
        var o = '<option value="">Chọn tài khoản</option>';
        data.forEach(function(a) {
            o += '<option value="' + a.code + '">' + a.code + ' - ' + a.name + '</option>';
        });
        $('#accountCode').html(o);
    });
}

$('#filterForm').submit(function(e) {
    e.preventDefault();
    var code = $('#accountCode').val();
    if (!code) { showToast('Vui lòng chọn tài khoản', 'error'); return; }
    var from = $('#fromDate').val();
    var to = $('#toDate').val();
    var groupBy = $('#groupBy').val();

    var url = '/api/gl/subsidiary?account=' + code;
    if (from) url += '&from=' + from;
    if (to) url += '&to=' + to;
    if (groupBy) url += '&group_by=' + groupBy;

    $.get(url, function(res) {
        var d = res.data;
        $('#resultArea').removeClass('d-none');
        $('#accountTitle').text(d.account_code + ' - ' + d.account_name);
        $('#accountType').text(d.account_type);
        $('#openingBalance').text(parseInt(d.opening_balance || 0).toLocaleString());
        $('#closingBalance').text(parseInt(d.closing_balance || 0).toLocaleString());

        var groupContainer = $('#objectGroups').empty();

        if (d.objects) {
            $('#detailTableArea').addClass('d-none');
            d.objects.forEach(function(obj) {
                var card = $(
                    '<div class="card mb-2"><div class="card-header py-1 d-flex justify-content-between">' +
                    '<strong>' + esc(obj.object_code || '') + ' - ' + esc(obj.object_name || '') + '</strong>' +
                    '<span class="font-monospace">ĐK: ' + parseInt(obj.opening_balance).toLocaleString() +
                    ' | PS Nợ: ' + parseInt(obj.total_debit).toLocaleString() +
                    ' | PS Có: ' + parseInt(obj.total_credit).toLocaleString() +
                    ' | CK: <strong>' + parseInt(obj.closing_balance).toLocaleString() + '</strong></span></div>' +
                    '<div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>Tham chiếu</th><th>Diễn giải</th><th class="text-end">Nợ</th><th class="text-end">Có</th></tr></thead><tbody></tbody></table></div></div>'
                );
                var tbody = card.find('table tbody');
                (obj.entries || []).forEach(function(e) {
                    tbody.append('<tr><td>' + e.date + '</td><td><code>' + esc(e.reference || '') + '</code></td><td>' + esc(e.description || '') + '</td><td class="text-end font-monospace">' + (e.debit ? parseInt(e.debit).toLocaleString() : '') + '</td><td class="text-end font-monospace">' + (e.credit ? parseInt(e.credit).toLocaleString() : '') + '</td></tr>');
                });
                groupContainer.append(card);
            });
        } else if (d.entries) {
            $('#detailTableArea').removeClass('d-none');
            var tbody = $('#detailTable tbody').empty();
            var running = d.opening_balance || 0;
            d.entries.forEach(function(e) {
                running += e.debit - e.credit;
                tbody.append('<tr><td>' + e.date + '</td><td><code>' + esc(e.reference || '') + '</code></td><td>' + esc(e.description || '') + '</td><td class="text-end font-monospace">' + (e.debit ? parseInt(e.debit).toLocaleString() : '') + '</td><td class="text-end font-monospace">' + (e.credit ? parseInt(e.credit).toLocaleString() : '') + '</td><td class="text-end font-monospace">' + parseInt(running).toLocaleString() + '</td></tr>');
            });
        }
    }).fail(function(x) {
        var msg = 'Lỗi';
        try { msg = JSON.parse(x.responseText).error; } catch(e) {}
        showToast(msg, 'error');
    });
});

$(document).ready(function() { loadAccounts(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
