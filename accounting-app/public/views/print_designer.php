<?php
use Accounting\Infrastructure\Auth;
$pageTitle = 'Thiết kế mẫu in';
$currentPage = 'print_designer';
$csrfToken = Auth::csrfToken();
ob_start();
?>

<div class="container-fluid py-4">
    <h2 class="mb-4"><i class="bi bi-printer"></i> Thiết kế mẫu in</h2>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <strong>Danh sách mẫu in</strong>
                    <button class="btn btn-sm btn-primary float-end" onclick="newTemplate()">
                        <i class="bi bi-plus"></i> Tạo mới
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="templateList">
                        <div class="text-center text-muted p-3">Đang tải...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card" id="editorCard" style="display:none">
                <div class="card-header">
                    <strong id="editorTitle">Chỉnh sửa mẫu in</strong>
                    <div class="float-end">
                        <button class="btn btn-sm btn-success" onclick="saveTemplate()">
                            <i class="bi bi-save"></i> Lưu
                        </button>
                        <button class="btn btn-sm btn-info" onclick="renderPreview()">
                            <i class="bi bi-eye"></i> Xem trước
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="loadSample()">
                            <i class="bi bi-magic"></i> Mẫu dữ liệu
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small">Loại mẫu</label>
                            <select class="form-select form-select-sm" id="tplType">
                                <option value="ap_invoice">Hóa đơn mua hàng</option>
                                <option value="ar_invoice">Hóa đơn bán hàng</option>
                                <option value="sales_order">Đơn bán hàng</option>
                                <option value="payment">Phiếu chi</option>
                                <option value="receipt">Phiếu thu</option>
                                <option value="financial_report">Báo cáo tài chính</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Mã mẫu</label>
                            <input type="text" class="form-control form-control-sm" id="tplCode" placeholder="vd: ap_invoice_v2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Tên mẫu</label>
                            <input type="text" class="form-control form-control-sm" id="tplName" placeholder="Tên gợi nhớ">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Mô tả</label>
                        <input type="text" class="form-control form-control-sm" id="tplDesc" placeholder="Mô tả ngắn (không bắt buộc)">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">Mã HTML (với cú pháp <code>{{var}}</code>, <code>{{#if}}</code>, <code>{{#each}}</code>)</label>
                            <textarea class="form-control form-control-sm" id="tplContent" rows="18" style="font-family: monospace; font-size: 12px"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Xem trước (rendered HTML)</label>
                            <div class="border p-2" id="previewArea" style="min-height: 320px; max-height: 500px; overflow: auto; background: #fff">
                                <em class="text-muted">Bấm "Xem trước" để render với dữ liệu mẫu</em>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <strong class="small">Cú pháp:</strong>
                        <code class="small">{{var}}</code> thay thế (escape HTML) ·
                        <code class="small">{{{{raw}}}}</code> thô ·
                        <code class="small">{{#if var}}...{{/if}}</code> điều kiện ·
                        <code class="small">{{#each list}}...{{else}}...{{/each}}</code> lặp
                    </div>
                </div>
            </div>

            <div class="card" id="emptyCard">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-cursor" style="font-size: 48px"></i>
                    <p class="mt-2">Chọn mẫu in bên trái hoặc tạo mới</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';
let currentTplId = null;
const samples = {
    ap_invoice: { reference: 'AP-2026-001', transaction_date: '2026-06-05', supplier_name: 'Cty TNHH ABC', supplier_tax_code: '0123456789', lines: [{description:'Hàng hóa A',quantity:10,unit_price:'100.000',amount:'1.000.000'}], total_amount:'1.000.000', vat_amount:'100.000', vat_rate:'10', grand_total:'1.100.000', print_date:'2026-06-05 14:30' },
    ar_invoice: { reference: 'AR-2026-001', transaction_date: '2026-06-05', customer_name: 'Cty XYZ', lines: [{description:'SP B',quantity:5,unit_price:'200.000',amount:'1.000.000'}], total_amount:'1.000.000', vat_amount:'', grand_total:'1.000.000' },
    sales_order: { order_no: 'SO-001', order_date: '2026-06-05', customer_name: 'KH A', notes: 'Giao hàng trước 15/06', items: [{line_no:1,item_name:'SP 1',quantity:2,unit_price:'500.000',amount:'1.000.000'}], total:'1.000.000' }
};

async function loadList() {
    const r = await fetch('/api/print/templates', { headers: { 'X-CSRF-Token': CSRF } });
    const json = await r.json();
    const list = json.data || [];
    const html = list.length === 0
        ? '<div class="text-muted p-3 text-center">Chưa có mẫu in nào</div>'
        : list.map(t => `
            <a href="#" class="list-group-item list-group-item-action ${t.id === currentTplId ? 'active' : ''}" onclick="loadTemplate('${t.id}'); return false">
                <div class="d-flex justify-content-between">
                    <strong>${t.name}</strong>
                    ${t.is_default ? '<span class="badge bg-primary">Mặc định</span>' : ''}
                </div>
                <small class="text-muted">${t.template_type} · ${t.code}</small>
            </a>
        `).join('');
    document.getElementById('templateList').innerHTML = html;
}

async function loadTemplate(id) {
    currentTplId = id;
    const r = await fetch('/api/print/templates/' + id, { headers: { 'X-CSRF-Token': CSRF } });
    if (!r.ok) { alert('Lỗi tải mẫu'); return; }
    const t = await r.json();
    document.getElementById('editorCard').style.display = 'block';
    document.getElementById('emptyCard').style.display = 'none';
    document.getElementById('editorTitle').textContent = 'Chỉnh sửa: ' + t.name;
    document.getElementById('tplType').value = t.template_type;
    document.getElementById('tplCode').value = t.code;
    document.getElementById('tplName').value = t.name;
    document.getElementById('tplDesc').value = t.description || '';
    document.getElementById('tplContent').value = t.content;
    loadList();
}

function newTemplate() {
    currentTplId = null;
    document.getElementById('editorCard').style.display = 'block';
    document.getElementById('emptyCard').style.display = 'none';
    document.getElementById('editorTitle').textContent = 'Tạo mẫu in mới';
    document.getElementById('tplType').value = 'ap_invoice';
    document.getElementById('tplCode').value = '';
    document.getElementById('tplName').value = '';
    document.getElementById('tplDesc').value = '';
    document.getElementById('tplContent').value = '<h1>{{reference}}</h1>\n<p>Ngày: {{transaction_date}}</p>\n<table border="1">\n{{#each lines}}\n<tr><td>{{description}}</td><td>{{amount}}</td></tr>\n{{/each}}\n</table>';
    document.getElementById('previewArea').innerHTML = '<em class="text-muted">Bấm "Xem trước" để render</em>';
}

async function saveTemplate() {
    const body = {
        template_type: document.getElementById('tplType').value,
        code: document.getElementById('tplCode').value,
        name: document.getElementById('tplName').value,
        description: document.getElementById('tplDesc').value,
        content: document.getElementById('tplContent').value,
    };
    if (currentTplId) body.id = currentTplId;
    const r = await fetch('/api/print/templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(body)
    });
    const json = await r.json();
    if (!r.ok) { alert('Lỗi: ' + (json.error || 'không xác định')); return; }
    currentTplId = json.id;
    alert('Đã lưu mẫu in');
    loadList();
}

function loadSample() {
    const type = document.getElementById('tplType').value;
    if (samples[type]) return samples[type];
    return {};
}

async function renderPreview() {
    if (!currentTplId) {
        if (!confirm('Cần lưu mẫu trước khi xem trước. Lưu ngay?')) return;
        await saveTemplate();
        if (!currentTplId) return;
    }
    const sample = loadSample();
    const r = await fetch('/api/print/templates/' + currentTplId + '/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ sample })
    });
    const json = await r.json();
    if (!r.ok) { alert('Lỗi: ' + (json.error || '')); return; }
    document.getElementById('previewArea').innerHTML = json.html;
}

loadList();
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
