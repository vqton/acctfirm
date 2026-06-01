<?php
// Màn hình: Ngân sách mua hàng theo phòng ban
// API: GET /api/purchase/budgets, POST /api/purchase/budgets, GET /api/purchase/budgets/check, GET /api/departments
// Nghiệp vụ: Thiết lập và theo dõi ngân sách mua hàng theo phòng ban/tháng
// Ngưỡng: < 80% xanh, 80-95% vàng, >= 95% đỏ (chặn tạo PR)
// Rủi ro: Không có ngân sách → chi tiêu không kiểm soát → vượt dự toán
$title = 'Ngân sách mua hàng'; $activeMenu = 'purchase_budget'; ob_start(); ?>
<div class="toolbar">
    <h5>Ngân sách mua hàng</h5>
    <div></div>
</div>

<div class="card-table mb-4">
    <div class="card-header-x"><span>Thiết lập ngân sách</span></div>
    <div class="p-3">
        <form id="budgetForm">
            <div class="row g-2 align-items-end">
                <div class="col-4">
                    <label>Phòng ban *</label>
                    <select class="form-select" id="f_department_id" required>
                        <option value="">-- Chọn phòng ban --</option>
                    </select>
                </div>
                <div class="col-3">
                    <label>Kỳ (MM/YYYY)</label>
                    <input class="form-control" id="f_period" placeholder="2026-06" value="<?= date('Y-m') ?>">
                </div>
                <div class="col-3">
                    <label>Số tiền ngân sách *</label>
                    <input type="number" class="form-control" id="f_amount" step="100000" min="1" required>
                </div>
                <div class="col-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Lưu ngân sách</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-table mb-4">
    <div class="card-header-x"><span>Báo cáo ngân sách</span></div>
    <table class="table table-hover">
        <thead><tr><th>Phòng ban</th><th>Kỳ</th><th class="text-end">Ngân sách</th><th class="text-end">Đã cam kết</th><th class="text-end">Đã thực hiện</th><th class="text-end">Còn lại</th><th>Tỷ lệ sử dụng</th></tr></thead>
        <tbody id="reportBody"></tbody>
    </table>
</div>

<div class="card-table">
    <div class="card-header-x"><span>Kiểm tra ngân sách</span></div>
    <div class="p-3">
        <div class="row g-2 align-items-end mb-3">
            <div class="col-3">
                <label>Phòng ban</label>
                <select class="form-select" id="check_department_id">
                    <option value="">-- Chọn --</option>
                </select>
            </div>
            <div class="col-3">
                <label>Kỳ</label>
                <input class="form-control" id="check_period" placeholder="2026-06" value="<?= date('Y-m') ?>">
            </div>
            <div class="col-3">
                <label>Số tiền dự kiến</label>
                <input type="number" class="form-control" id="check_amount" step="100000" min="1">
            </div>
            <div class="col-3">
                <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="checkBudget()"><i class="bi bi-search"></i> Kiểm tra</button>
            </div>
        </div>
        <div id="checkResult" style="display:none"></div>
    </div>
</div>

<script>
var departments = [];

function loadDepartments(targetIds) {
    fetch('/api/departments')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        departments = data || [];
        var ids = targetIds || ['f_department_id', 'check_department_id'];
        ids.forEach(function(id) {
            var sel = document.getElementById(id);
            sel.innerHTML = '<option value="">-- Chọn phòng ban --</option>';
            departments.forEach(function(d) {
                sel.innerHTML += '<option value="' + esc(d.id) + '">' + esc(d.code + ' - ' + d.name) + '</option>';
            });
        });
    })
    .catch(function(err) { showToast('Lỗi tải danh sách phòng ban: ' + err.message, 'error'); });
}

function loadData() {
    fetch('/api/purchase/budgets')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var tbody = document.getElementById('reportBody');
        var list = data || [];
        tbody.innerHTML = list.map(function(r) {
            var budget = parseFloat(r.budget_amount || 0);
            var committed = parseFloat(r.committed_amount || 0);
            var actual = parseFloat(r.actual_amount || 0);
            var remaining = budget - committed - actual;
            var usage = budget > 0 ? ((committed + actual) / budget * 100) : 0;
            var barClass = usage >= 95 ? 'bg-danger' : (usage >= 80 ? 'bg-warning' : 'bg-success');
            return '<tr>' +
                '<td>' + esc(r.department_name || r.department_id || '') + '</td>' +
                '<td>' + esc(r.period || '') + '</td>' +
                '<td class="text-end font-monospace">' + budget.toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + committed.toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + actual.toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + remaining.toLocaleString() + '</td>' +
                '<td style="min-width:150px">' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<div class="progress flex-grow-1" style="height:8px;">' +
                            '<div class="progress-bar ' + barClass + '" style="width:' + Math.min(usage, 100) + '%"></div>' +
                        '</div>' +
                        '<small class="text-muted" style="font-size:11px">' + usage.toFixed(1) + '%</small>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        if (!list.length) tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i>Chưa có ngân sách nào được thiết lập</td></tr>';
    })
    .catch(function(err) { showToast('Lỗi tải báo cáo: ' + err.message, 'error'); });
}

function checkBudget() {
    var deptId = document.getElementById('check_department_id').value;
    var period = document.getElementById('check_period').value.trim();
    var amount = parseFloat(document.getElementById('check_amount').value) || 0;

    if (!deptId) { showToast('Vui lòng chọn phòng ban', 'error'); return; }
    if (amount <= 0) { showToast('Vui lòng nhập số tiền dự kiến', 'error'); return; }

    var resultDiv = document.getElementById('checkResult');
    resultDiv.style.display = 'none';
    resultDiv.innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Đang kiểm tra...</div>';
    resultDiv.style.display = 'block';

    fetch('/api/purchase/budgets/check?department_id=' + encodeURIComponent(deptId) + '&amount=' + amount + '&period=' + encodeURIComponent(period))
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var icon, cls, msg;
        if (result.allowed) {
            icon = 'bi-check-circle-fill';
            cls = 'alert-success';
        } else {
            icon = 'bi-x-circle-fill';
            cls = 'alert-danger';
        }
        msg = '<i class="bi ' + icon + ' me-2"></i><strong>' + (result.allowed ? 'Được phép' : 'Bị chặn') + '</strong>';
        if (result.warning) msg += '<br><small>' + esc(result.warning) + '</small>';
        if (result.remaining !== null && result.remaining !== undefined) {
            msg += '<br><small>Số dư còn lại: <strong>' + parseFloat(result.remaining).toLocaleString() + ' VND</strong></small>';
        }
        if (result.usage_rate !== null && result.usage_rate !== undefined) {
            var ur = parseFloat(result.usage_rate);
            var barClass = ur >= 95 ? 'bg-danger' : (ur >= 80 ? 'bg-warning' : 'bg-success');
            msg += '<div class="mt-2 d-flex align-items-center gap-2">' +
                '<div class="progress flex-grow-1" style="height:8px;">' +
                    '<div class="progress-bar ' + barClass + '" style="width:' + Math.min(ur, 100) + '%"></div>' +
                '</div>' +
                '<small>' + ur.toFixed(1) + '%</small></div>';
        }
        resultDiv.innerHTML = '<div class="alert ' + cls + ' mb-0">' + msg + '</div>';
    })
    .catch(function(err) {
        resultDiv.innerHTML = '<div class="alert alert-danger mb-0">Lỗi kiểm tra ngân sách: ' + esc(err.message) + '</div>';
    });
}

document.getElementById('budgetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var deptId = document.getElementById('f_department_id').value;
    var period = document.getElementById('f_period').value.trim();
    var amount = parseFloat(document.getElementById('f_amount').value) || 0;

    if (!deptId) { showToast('Vui lòng chọn phòng ban', 'error'); return; }
    if (amount <= 0) { showToast('Vui lòng nhập số tiền ngân sách', 'error'); return; }

    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch('/api/purchase/budgets', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ department_id: deptId, period: period, amount: amount })
    })
    .then(function(r) {
        if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Lỗi thiết lập ngân sách'); });
        document.getElementById('budgetForm').reset();
        document.getElementById('f_period').value = document.getElementById('check_period').value || '<?= date('Y-m') ?>';
        showToast('Thiết lập ngân sách thành công', 'success');
        loadData();
    })
    .catch(function(err) { showToast(err.message, 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = 'Lưu ngân sách'; });
});

loadDepartments();
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
