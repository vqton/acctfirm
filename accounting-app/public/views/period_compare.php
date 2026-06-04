<?php ob_start(); ?>
<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-bar-chart-steps"></i> So sánh số liệu giữa 2 kỳ (R-7)</h2>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Kỳ A (cũ)</label>
                    <select id="periodA" class="form-select"></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kỳ B (mới)</label>
                    <select id="periodB" class="form-select"></select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary" id="btnCompare">
                        <i class="bi bi-arrow-left-right"></i> So sánh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="result"></div>
</div>

<script>
let allPeriods = [];

async function loadPeriods() {
    try {
        const res = await fetch('/api/periods');
        const json = await res.json();
        allPeriods = json.data || [];
        const opts = allPeriods.map(p =>
            `<option value="${p.period_code}">${p.period_code} — ${p.name} (${p.status})</option>`
        ).join('');
        document.getElementById('periodA').innerHTML = opts;
        document.getElementById('periodB').innerHTML = opts;
        if (allPeriods.length >= 2) {
            document.getElementById('periodA').value = allPeriods[1].period_code;
            document.getElementById('periodB').value = allPeriods[0].period_code;
        }
    } catch (e) {
        document.getElementById('result').innerHTML =
            `<div class="alert alert-danger">Lỗi tải kỳ: ${e.message}</div>`;
    }
}

function fmt(n) {
    if (n == null) return '—';
    return new Intl.NumberFormat('vi-VN').format(Math.round(n));
}

function pctStr(pct) {
    if (pct == null) return '<span class="text-muted">N/A</span>';
    const sign = pct > 0 ? '+' : '';
    const cls = pct > 0 ? 'text-danger' : pct < 0 ? 'text-success' : '';
    return `<span class="${cls}">${sign}${pct.toFixed(2)}%</span>`;
}

function diffStr(d) {
    const sign = d > 0 ? '+' : '';
    const cls = d > 0 ? 'text-danger' : d < 0 ? 'text-success' : '';
    return `<span class="${cls}">${sign}${fmt(d)}</span>`;
}

async function doCompare() {
    const a = document.getElementById('periodA').value;
    const b = document.getElementById('periodB').value;
    if (!a || !b || a === b) {
        alert('Vui lòng chọn 2 kỳ khác nhau');
        return;
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    document.getElementById('result').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border"></div></div>';

    try {
        const res = await fetch(`/api/periods/compare?from=${a}&to=${b}`, {
            headers: { 'X-CSRF-Token': csrf }
        });
        const json = await res.json();
        if (json.error) {
            document.getElementById('result').innerHTML =
                `<div class="alert alert-danger">${json.error}</div>`;
            return;
        }
        render(json.data);
    } catch (e) {
        document.getElementById('result').innerHTML =
            `<div class="alert alert-danger">Lỗi: ${e.message}</div>`;
    }
}

function render(d) {
    const pa = d.period_a, pb = d.period_b, v = d.variance;

    let html = '<div class="row">';

    // Summary cards
    html += '<div class="col-md-6">';
    html += summaryCard('Kỳ A: ' + pa.period_code, pa);
    html += '</div>';
    html += '<div class="col-md-6">';
    html += summaryCard('Kỳ B: ' + pb.period_code, pb);
    html += '</div>';
    html += '</div>';

    // By type variance
    html += '<div class="card mt-3"><div class="card-header">Biến động theo loại tài khoản</div>';
    html += '<div class="table-responsive"><table class="table table-sm mb-0">';
    html += '<thead><tr><th>Loại TK</th><th class="text-end">A: Nợ</th><th class="text-end">B: Nợ</th><th class="text-end">Δ Nợ</th><th class="text-end">% Nợ</th>';
    html += '<th class="text-end">A: Có</th><th class="text-end">B: Có</th><th class="text-end">Δ Có</th><th class="text-end">% Có</th></tr></thead><tbody>';
    for (const [type, x] of Object.entries(v.by_type)) {
        html += `<tr>
            <td><strong>${type}</strong></td>
            <td class="text-end">${fmt(x.a_debit)}</td>
            <td class="text-end">${fmt(x.b_debit)}</td>
            <td class="text-end">${diffStr(x.debit_diff)}</td>
            <td class="text-end">${pctStr(x.debit_pct)}</td>
            <td class="text-end">${fmt(x.a_credit)}</td>
            <td class="text-end">${fmt(x.b_credit)}</td>
            <td class="text-end">${diffStr(x.credit_diff)}</td>
            <td class="text-end">${pctStr(x.credit_pct)}</td>
        </tr>`;
    }
    html += '</tbody></table></div></div>';

    // By account variance
    if (v.by_account_count > 0) {
        html += `<div class="card mt-3"><div class="card-header">Biến động chi tiết theo tài khoản (${v.by_account_count} TK)</div>`;
        html += '<div class="table-responsive"><table class="table table-sm table-striped mb-0">';
        html += '<thead><tr><th>Mã TK</th><th>Tên</th><th>Loại</th>';
        html += '<th class="text-end">A: Nợ</th><th class="text-end">B: Nợ</th><th class="text-end">Δ Nợ</th>';
        html += '<th class="text-end">A: Có</th><th class="text-end">B: Có</th><th class="text-end">Δ Có</th></tr></thead><tbody>';
        for (const a of v.by_account) {
            html += `<tr>
                <td><code>${a.code}</code></td>
                <td>${a.name}</td>
                <td><span class="badge bg-secondary">${a.type}</span></td>
                <td class="text-end">${fmt(a.a_debit)}</td>
                <td class="text-end">${fmt(a.b_debit)}</td>
                <td class="text-end">${diffStr(a.debit_diff)}</td>
                <td class="text-end">${fmt(a.a_credit)}</td>
                <td class="text-end">${fmt(a.b_credit)}</td>
                <td class="text-end">${diffStr(a.credit_diff)}</td>
            </tr>`;
        }
        html += '</tbody></table></div></div>';
    }

    document.getElementById('result').innerHTML = html;
}

function summaryCard(title, p) {
    let html = '<div class="card"><div class="card-header"><strong>' + title + '</strong></div>';
    html += '<div class="card-body">';
    html += `<p class="mb-1">Số bút toán: <strong>${p.txn_count}</strong></p>`;
    html += `<p class="mb-1">Tổng phát sinh Nợ: <strong>${fmt(p.total_debit)}</strong></p>`;
    html += `<p class="mb-2">Tổng phát sinh Có: <strong>${fmt(p.total_credit)}</strong></p>`;
    if (Object.keys(p.by_type).length > 0) {
        html += '<table class="table table-sm mb-0"><thead><tr><th>Loại</th><th class="text-end">Nợ</th><th class="text-end">Có</th></tr></thead><tbody>';
        for (const [t, x] of Object.entries(p.by_type)) {
            html += `<tr><td>${t}</td><td class="text-end">${fmt(x.debit)}</td><td class="text-end">${fmt(x.credit)}</td></tr>`;
        }
        html += '</tbody></table>';
    }
    html += '</div></div>';
    return html;
}

document.getElementById('btnCompare').addEventListener('click', doCompare);
loadPeriods();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
