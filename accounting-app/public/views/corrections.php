<?php
// Màn hình: Điều chỉnh bút toán (Correction Engine) — Article 27 Luật Kế toán
// Ba phương pháp điều chỉnh:
//   1. Bổ sung (supplementary): ghi thêm chênh lệch khi ghi thiếu
//   2. Đảo ngược (negative): đảo bút toán sai, ghi lại đúng
//   3. Điều chỉnh (adjusting): chuyển số dư giữa các tài khoản
// API: POST /api/corrections/supplementary, /negative, /adjusting
//      GET /api/corrections/history/:transactionId
// Audit: Mọi điều chỉnh đều ghi audit trail — ai, khi nào, lý do, giá trị cũ/mới
$title = 'Điều chỉnh bút toán';
$activeMenu = 'corrections';
ob_start(); ?>
<div class="toolbar">
    <h5>Điều chỉnh bút toán</h5>
    <div>
        <input class="form-control form-control-sm d-inline-block w-auto me-2" id="searchTxn" placeholder="Tìm bút toán gốc (mã CT, diễn giải)" style="width:250px">
        <button class="btn btn-primary btn-sm" onclick="loadTransactions()"><i class="bi bi-search"></i> Tìm</button>
    </div>
</div>

<div class="row">
    <div class="col-7">
        <div class="card-table"><table class="table table-hover">
            <thead><tr><th>Mã CT</th><th>Diễn giải</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody id="txnBody"></tbody>
        </table></div>
    </div>
    <div class="col-5">
        <div id="correctionPanel" class="card d-none">
            <div class="card-header bg-warning text-dark"><b>Điều chỉnh bút toán</b></div>
            <div class="card-body">
                <div id="originalInfo" class="mb-3 small"></div>
                <form id="correctionForm">
                    <input type="hidden" id="originalTxnId">
                    <div class="mb-2">
                        <label>Phương pháp điều chỉnh</label>
                        <select class="form-select form-select-sm" id="methodType">
                            <option value="supplementary">Bổ sung (ghi thiếu)</option>
                            <option value="negative">Đảo ngược (ghi sai)</option>
                            <option value="adjusting">Điều chỉnh (sai tài khoản)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Lý do điều chỉnh <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="reason" rows="2" placeholder="Tối thiểu 10 ký tự..." data-v-required="Lý do điều chỉnh"></textarea>
                    </div>
                    <div id="linesSection" class="mb-2">
                        <label class="d-flex justify-content-between">
                            <span>Các dòng điều chỉnh</span>
                            <span id="corrDrCrStatus" class="text-success fw-bold small">Nợ = Có (0)</span>
                        </label>
                        <div id="corrLinesContainer"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="addCorrLine()"><i class="bi bi-plus"></i> Thêm dòng</button>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">Xác nhận điều chỉnh</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Lịch sử điều chỉnh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="historyBody"></div>
</div></div></div>

<script>
let accounts = [];

function loadAccounts() {
    fetch('/api/coa').then(r=>r.json()).then(d=>{ accounts = d.data || []; });
}

function loadTransactions() {
    const q = document.getElementById('searchTxn').value;
    let url = '/api/transactions';
    if (q) url += '?q=' + encodeURIComponent(q);
    fetch(url).then(r=>r.json()).then(d=>{
        const data = d.data || d || [];
        const tbody = document.getElementById('txnBody');
        tbody.innerHTML = data.filter(t => t.status === 'posted').map(t => `
            <tr>
                <td>${t.reference || ''}</td>
                <td>${e(t.description || '')}</td>
                <td>${t.date ? t.date.slice(0,10) : ''}</td>
                <td>${statusBadge('posted')}</td>
                <td><button class="btn btn-outline-warning btn-sm" onclick="selectTxn('${t.id}','${e(t.description||'')}','${t.reference||''}')">Điều chỉnh</button>
                    <button class="btn btn-outline-info btn-sm" onclick="showHistory('${t.id}')">LS</button></td>
            </tr>
        `).join('');
    });
}

function selectTxn(id, desc, ref) {
    document.getElementById('originalTxnId').value = id;
    document.getElementById('originalInfo').innerHTML = `<b>Bút toán gốc:</b> ${ref} — ${desc}`;
    document.getElementById('correctionPanel').classList.remove('d-none');
    document.getElementById('reason').value = '';
    document.getElementById('corrLinesContainer').innerHTML = '';
    updateCorrDrCr();
}

function addCorrLine(acct, amt, isDebit) {
    const c = document.getElementById('corrLinesContainer');
    const i = c.children.length;
    const opts = accounts.map(a => `<option value="${a.code}" ${a.code===acct?'selected':''}>${a.code} - ${e(a.name)}</option>`).join('');
    c.innerHTML += `<div class="corr-line row g-1 mb-1" data-idx="${i}">
        <div class="col-5"><select class="form-select form-select-sm acct-select">${opts}</select></div>
        <div class="col-3"><input class="form-control form-control-sm amt-input" type="number" step="1000" value="${amt||''}" placeholder="Số tiền"></div>
        <div class="col-2">
            <select class="form-select form-select-sm drcr-select">
                <option value="1" ${isDebit==='1'||isDebit===true?'selected':''}>Nợ</option>
                <option value="0" ${isDebit==='0'||isDebit===false?'selected':''}>Có</option>
            </select>
        </div>
        <div class="col-2"><button class="btn btn-outline-danger btn-sm" onclick="this.closest('.corr-line').remove();updateCorrDrCr()"><i class="bi bi-x"></i></button></div>
    </div>`;
    updateCorrDrCr();
}

function updateCorrDrCr() {
    const lines = document.querySelectorAll('.corr-line');
    let dr = 0, cr = 0;
    lines.forEach(l => {
        const amt = parseFloat(l.querySelector('.amt-input').value) || 0;
        const isDr = l.querySelector('.drcr-select').value === '1';
        if (isDr) dr += amt; else cr += amt;
    });
    const status = document.getElementById('corrDrCrStatus');
    if (Math.abs(dr - cr) <= 10) {
        status.className = 'text-success fw-bold small';
    } else {
        status.className = 'text-danger fw-bold small';
    }
    status.textContent = `Nợ: ${VAS.fmt(dr)} — Có: ${VAS.fmt(cr)} (Chênh: ${VAS.fmt(dr-cr)})`;
}

document.addEventListener('change', function(e) {
    if (e.target.closest('.corr-line')) updateCorrDrCr();
});

document.addEventListener('input', function(e) {
    if (e.target.closest('.corr-line')) updateCorrDrCr();
});

document.getElementById('correctionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var v=FormValidation.validate('#correctionForm');if(!v.valid)return;
    const id = document.getElementById('originalTxnId').value;
    const reason = document.getElementById('reason').value.trim();
    const method = document.getElementById('methodType').value;

    if (!reason || reason.length < 10) { FormConfirm.alert('Lỗi','Lý do điều chỉnh phải có tối thiểu 10 ký tự.'); return; }

    const lineEls = document.querySelectorAll('.corr-line');
    const lines = [];
    lineEls.forEach(l => {
        const acct = l.querySelector('.acct-select').value;
        const amt = parseFloat(l.querySelector('.amt-input').value) || 0;
        const isDebit = l.querySelector('.drcr-select').value === '1';
        if (acct && amt > 0) lines.push({ account_code: acct, amount: amt, is_debit: isDebit });
    });

    let url, payload = { original_transaction_id: id, reason, lines };
    if (method === 'supplementary') url = '/api/corrections/supplementary';
    else if (method === 'negative') {
        url = '/api/corrections/negative';
        delete payload.lines;
    } else {
        url = '/api/corrections/adjusting';
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.error) { FormConfirm.alert('Lỗi', d.error); return; }
        FormToast.success('Điều chỉnh thành công. Mã CT: ' + (d.data?.reference || d.reference || ''));
        loadTransactions();
        document.getElementById('correctionPanel').classList.add('d-none');
    }).catch(err => FormToast.error('Lỗi: ' + err.message));
});

function showHistory(id) {
    fetch('/api/corrections/history/' + id).then(r=>r.json()).then(d => {
        const data = d.data || d || [];
        const body = document.getElementById('historyBody');
        if (data.length === 0) { body.innerHTML = '<p class="text-muted">Chưa có điều chỉnh nào.</p>'; }
        else {
            body.innerHTML = '<table class="table table-sm"><thead><tr><th>Mã CT</th><th>Ngày</th><th>Phương pháp</th><th>Lý do</th><th>Trạng thái</th></tr></thead><tbody>' +
                data.map(h => `<tr><td>${h.reference||''}</td><td>${h.date||''}</td><td>${h.correction_type||''}</td><td>${e(h.correction_reason||'')}</td><td>${statusBadge(h.status)}</td></tr>`).join('') +
                '</tbody></table>';
        }
        new bootstrap.Modal(document.getElementById('historyModal')).show();
    });
}

loadAccounts();
loadTransactions();
FormValidation.setup('#correctionForm');
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
