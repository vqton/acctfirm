<?php
$title = 'Ghi tăng TSCĐ';
$activeMenu = 'fixed_assets';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?></h5></div>
</div>
<div class="card p-4" style="max-width:900px">
<form id="acquisitionForm">
    <div class="row g-3 mb-3">
        <div class="col-3"><label class="form-label">Mã TSCĐ *</label><input type="text" name="code" id="f_code" class="form-control form-control-sm" required></div>
        <div class="col-6"><label class="form-label">Tên TSCĐ *</label><input type="text" name="name" id="f_name" class="form-control form-control-sm" required></div>
        <div class="col-3"><label class="form-label">Số CT (01-TSCĐ)</label><input type="text" name="document_no" id="f_document_no" class="form-control form-control-sm" placeholder="Tự động"></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-4"><label class="form-label">Loại hình ghi tăng *</label>
            <select name="acquisition_type" id="f_acquisition_type" class="form-select form-select-sm">
                <option value="purchase_cash">Mua bằng tiền mặt</option>
                <option value="purchase_bank">Mua bằng chuyển khoản</option>
                <option value="purchase_credit">Mua chịu (công nợ)</option>
                <option value="capital_contribution">Nhận vốn góp</option>
                <option value="gift">Được tặng / biếu</option>
            </select>
        </div>
        <div class="col-4"><label class="form-label">Ngày mua</label><input type="date" name="purchase_date" id="f_purchase_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-4"><label class="form-label">Nguyên giá *</label><input type="number" name="original_cost" id="f_original_cost" class="form-control form-control-sm" step="0.01" required></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-4"><label class="form-label">PP khấu hao</label>
            <select name="depreciation_method" id="f_depreciation_method" class="form-select form-select-sm">
                <option value="straight_line">Đường thẳng</option>
                <option value="declining_balance">Số dư giảm dần</option>
                <option value="sum_of_years">Tổng số năm</option>
                <option value="production">Sản lượng</option>
            </select>
        </div>
        <div class="col-4"><label class="form-label">Thời gian (năm)</label><input type="number" name="useful_life" id="f_useful_life" class="form-control form-control-sm" step="0.1"></div>
        <div class="col-4"><label class="form-label">GT thanh lý</label><input type="number" name="salvage_value" id="f_salvage_value" class="form-control form-control-sm" step="0.01"></div>
    </div>
    <div class="row g-3 mb-3" id="vatRow">
        <div class="col-3"><label class="form-label">Thuế GTGT</label><input type="number" name="vat_amount" id="f_vat_amount" class="form-control form-control-sm" step="0.01"></div>
        <div class="col-3"><label class="form-label">Phân loại</label>
            <select name="fa_category" class="form-select form-select-sm">
                <option value="tangible">Hữu hình (TK 211)</option>
                <option value="intangible">Vô hình (TK 213)</option>
                <option value="finance_lease">Thuê tài chính (TK 212)</option>
            </select>
        </div>
        <div class="col-3"><label class="form-label">Loại TSCĐ</label>
            <select name="fa_type" class="form-select form-select-sm">
                <option value="">-- Chọn --</option>
                <option value="Nha cua vat kien truc">Nhà cửa, vật kiến trúc</option>
                <option value="May moc thiet bi">Máy móc, thiết bị</option>
                <option value="Phuong tien van tai">Phương tiện vận tải</option>
                <option value="Thiet bi quan ly">Thiết bị quản lý</option>
                <option value="CCDC">CCDC</option>
            </select>
        </div>
        <div class="col-3"><label class="form-label">Bộ phận</label>
            <select name="department_id" id="f_department_id" class="form-select form-select-sm">
                <option value="">-- Chọn --</option>
            </select>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-4"><label class="form-label">Vị trí</label><input type="text" name="location" class="form-control form-control-sm"></div>
        <div class="col-4"><label class="form-label">Nhân viên SD</label><input type="text" name="employee_id" class="form-control form-control-sm"></div>
        <div class="col-4"><label class="form-label">Ghi chú</label><input type="text" name="notes" class="form-control form-control-sm"></div>
    </div>

    <div id="journalPreview" class="mb-3 p-3 bg-light rounded d-none">
        <h6 class="mb-2">Dự kiến bút toán</h6>
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>TK</th><th>Tên tài khoản</th><th>Nợ</th><th>Có</th></tr></thead>
            <tbody id="journalLines"></tbody>
            <tfoot id="journalTotal"></tfoot>
        </table>
    </div>

    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Ghi nhận tăng TSCĐ</button>
    <button type="button" id="previewBtn" class="btn btn-outline-info btn-sm ms-2"><i class="bi bi-eye"></i> Xem bút toán</button>
    <a href="/danh-muc/tai-san-co-dinh" class="btn btn-outline-secondary btn-sm ms-2">Hủy</a>
</form>
<div id="result" class="mt-3"></div>
<div id="assetCard" class="mt-3 d-none">
    <div class="card border-success">
        <div class="card-header bg-success text-white py-2"><h6 class="mb-0"><i class="bi bi-check-circle"></i> TSCĐ đã ghi nhận</h6></div>
        <div class="card-body p-3" id="assetCardBody"></div>
    </div>
</div>
</div>

<script>
$(function() {
    fetch('/api/departments').then(function(r){return r.json();}).then(function(data){
        var sel = document.getElementById('f_department_id');
        data.forEach(function(d){ var o=document.createElement('option'); o.value=d.id; o.textContent=d.name; sel.appendChild(o); });
    });

    var acctNames = {purchase_cash:{dr:'111',label:'Tiền mặt'},purchase_bank:{dr:'112',label:'Tiền gửi NH'},purchase_credit:{dr:'331',label:'Phải trả NCC'},capital_contribution:{dr:'411',label:'Vốn góp'},gift:{dr:'711',label:'Thu nhập khác'}};

    function updatePreview() {
        var type = document.getElementById('f_acquisition_type').value;
        var ng = parseFloat(document.getElementById('f_original_cost').value) || 0;
        var vat = parseFloat(document.getElementById('f_vat_amount').value) || 0;
        var acct = acctNames[type] || acctNames.purchase_cash;
        var prev = document.getElementById('journalPreview');
        var tbody = document.getElementById('journalLines');
        var tfoot = document.getElementById('journalTotal');
        if (!ng) { prev.classList.add('d-none'); return; }
        prev.classList.remove('d-none');
        var cat = document.querySelector('[name=fa_category]').value;
        var faAcct = cat === 'intangible' ? '213' : cat === 'finance_lease' ? '212' : '211';
        var faLabel = cat === 'intangible' ? 'TSCĐ vô hình' : cat === 'finance_lease' ? 'TSCĐ thuê TC' : 'TSCĐ hữu hình';
        var lines = '';
        lines += '<tr><td>'+faAcct+'</td><td>'+faLabel+'</td><td class="text-end">'+ng.toLocaleString()+'</td><td></td></tr>';
        if (vat > 0) lines += '<tr><td>1332</td><td>Thuế GTGT TSCĐ</td><td class="text-end">'+vat.toLocaleString()+'</td><td></td></tr>';
        lines += '<tr><td>'+acct.dr+'</td><td>'+acct.label+'</td><td></td><td class="text-end">'+(ng+vat).toLocaleString()+'</td></tr>';
        tbody.innerHTML = lines;
        tfoot.innerHTML = '<tr class="fw-bold"><td colspan="2">Tổng</td><td class="text-end">'+(ng+vat).toLocaleString()+'</td><td class="text-end">'+(ng+vat).toLocaleString()+'</td></tr>';
    }

    document.getElementById('f_acquisition_type').addEventListener('change', function(){
        var vatRow = document.getElementById('vatRow');
        vatRow.style.display = (this.value === 'purchase_cash' || this.value === 'purchase_bank' || this.value === 'purchase_credit') ? '' : 'none';
        updatePreview();
    });
    document.getElementById('f_original_cost').addEventListener('input', function(){
        var ng = parseFloat(this.value) || 0;
        if (!document.getElementById('f_vat_amount').value) {
            document.getElementById('f_vat_amount').value = Math.round(ng * 0.1);
        }
        updatePreview();
    });
    document.getElementById('f_vat_amount').addEventListener('input', updatePreview);
    document.getElementById('previewBtn').addEventListener('click', updatePreview);

    $('#acquisitionForm').on('submit', function(e){
        e.preventDefault();
        var data = Object.fromEntries(new FormData(this));
        data.original_cost = parseFloat(data.original_cost) || 0;
        data.useful_life = parseFloat(data.useful_life) || 0;
        data.salvage_value = parseFloat(data.salvage_value) || 0;
        data.vat_amount = parseFloat(data.vat_amount) || 0;
        var acct = acctNames[data.acquisition_type] || acctNames.purchase_cash;
        data.counterparty_account = acct.dr;

        $('#result').html('<span class="text-muted">Đang xử lý...</span>');
        document.getElementById('assetCard').classList.add('d-none');
        fetch('/api/fixed-assets/acquire', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify(data)
        }).then(function(r){return r.json();}).then(function(d){
            if (d.error) { $('#result').html('<div class="alert alert-danger py-2 mb-0">'+d.error+'</div>'); return; }
            var msg = 'Đã ghi tăng TSCĐ. Mã giao dịch: '+d.transaction_id;
            if (d.reference) msg += ', Số CT: '+d.reference;
            $('#result').html('<div class="alert alert-success py-2 mb-0">'+msg+'</div>');
            document.getElementById('acquisitionForm').reset();
            document.getElementById('f_purchase_date').value = '<?= date('Y-m-d') ?>';
            document.getElementById('journalPreview').classList.add('d-none');
            if (d.fixed_asset) {
                var fa = d.fixed_asset;
                var cardHtml = '<div class="row g-2"><div class="col-md-6"><strong>Mã:</strong> '+esc(fa.code)+'</div>';
                cardHtml += '<div class="col-md-6"><strong>Tên:</strong> '+esc(fa.name)+'</div>';
                cardHtml += '<div class="col-md-4"><strong>Nguyên giá:</strong> '+(fa.original_cost||0).toLocaleString()+'</div>';
                cardHtml += '<div class="col-md-4"><strong>KH tháng:</strong> '+(fa.monthly_depreciation||0).toLocaleString()+'</div>';
                cardHtml += '<div class="col-md-4"><strong>GTCL:</strong> '+(fa.net_book_value||0).toLocaleString()+'</div>';
                if (fa.document_no) cardHtml += '<div class="col-md-6"><strong>Số CT (01-TSCĐ):</strong> '+esc(fa.document_no)+'</div>';
                cardHtml += '<div class="col-md-6"><strong>Trạng thái:</strong> Chờ bàn giao (sẽ tính KH từ tháng sau)</div></div>';
                document.getElementById('assetCardBody').innerHTML = cardHtml;
                document.getElementById('assetCard').classList.remove('d-none');
            }
            showToast('Đã ghi tăng TSCĐ thành công','success');
        }).catch(function(e){$('#result').html('<div class="alert alert-danger py-2 mb-0">Lỗi: '+e.message+'</div>');});
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
