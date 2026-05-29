<?php
$title = 'Thanh lý TSCĐ';
$activeMenu = 'fixed_assets';
ob_start();
?>
<div class="toolbar">
    <div><h5><?= $title ?></h5></div>
</div>
<div class="row g-3">
<div class="col-md-7">
<div class="card p-4">
<form id="disposalForm">
    <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label">Chọn TSCĐ *</label>
            <select name="fixed_asset_id" id="f_fixed_asset_id" class="form-select form-select-sm" required>
                <option value="">-- Chọn TSCĐ --</option>
            </select>
        </div>
        <div class="col-6"><label class="form-label">Loại *</label>
            <select name="disposal_type" id="f_disposal_type" class="form-select form-select-sm">
                <option value="liquidation">Thanh lý (không thu tiền)</option>
                <option value="sale">Nhượng bán (có thu tiền)</option>
            </select>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-4"><label class="form-label">Ngày thanh lý</label><input type="date" name="disposal_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-4"><label class="form-label">Số CT (02-TSCĐ)</label><input type="text" name="document_no" class="form-control form-control-sm" placeholder="Tự động"></div>
    </div>
    <div id="saleFields" style="display:none">
        <div class="row g-3 mb-3">
            <div class="col-4"><label class="form-label">Tiền thu (đã VAT)</label><input type="number" name="proceeds" id="f_proceeds" class="form-control form-control-sm" step="0.01"></div>
            <div class="col-4"><label class="form-label">TK nhận tiền</label>
                <select name="proceeds_account" class="form-select form-select-sm">
                    <option value="111">111 - Tiền mặt</option>
                    <option value="112">112 - Tiền gửi NH</option>
                </select>
            </div>
            <div class="col-4"><label class="form-label">Chi phí thanh lý</label><input type="number" name="costs" class="form-control form-control-sm" step="0.01"></div>
        </div>
    </div>
    <div id="disposalPreview" class="mb-3 p-3 bg-light rounded d-none">
        <h6 class="mb-2">Dự kiến bút toán</h6>
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>TK</th><th>Diễn giải</th><th>Nợ</th><th>Có</th></tr></thead>
            <tbody id="disposalLines"></tbody>
            <tfoot id="disposalTotal"></tfoot>
        </table>
    </div>
    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Ghi nhận thanh lý</button>
    <button type="button" id="previewDisposalBtn" class="btn btn-outline-info btn-sm ms-2"><i class="bi bi-eye"></i> Xem bút toán</button>
    <a href="/danh-muc/tai-san-co-dinh" class="btn btn-outline-secondary btn-sm ms-2">Hủy</a>
</form>
<div id="result" class="mt-3"></div>
</div>
</div>
<div class="col-md-5">
<div id="assetInfoCard" class="card d-none">
    <div class="card-header bg-info text-white py-2"><h6 class="mb-0"><i class="bi bi-info-circle"></i> Thông tin TSCĐ</h6></div>
    <div class="card-body p-3" id="assetInfoBody"></div>
</div>
</div>
</div>

<script>
$(function() {
    var assetData = {};

    fetch('/api/fixed-assets').then(function(r){return r.json();}).then(function(data){
        var sel = document.getElementById('f_fixed_asset_id');
        data.forEach(function(i){
            if (i.status === 'in_use' || i.status === '1') {
                var o = document.createElement('option');
                o.value = i.id;
                o.textContent = i.code+' - '+i.name+' (GTCL: '+(i.net_book_value||0).toLocaleString()+')';
                o.dataset.nbv = i.net_book_value || 0;
                o.dataset.ng = i.original_cost || 0;
                o.dataset.accum = i.accumulated_depreciation || 0;
                o.dataset.dept = i.department_id || '';
                o.dataset.date = i.purchase_date || '';
                o.dataset.method = i.depreciation_method || '';
                o.dataset.life = i.useful_life || 0;
                sel.appendChild(o);
                assetData[i.id] = i;
            }
        });
    });

    document.getElementById('f_fixed_asset_id').addEventListener('change', function(){
        var sel = this;
        var infoCard = document.getElementById('assetInfoCard');
        var body = document.getElementById('assetInfoBody');
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) { infoCard.classList.add('d-none'); return; }
        var fa = assetData[opt.value];
        if (!fa) { infoCard.classList.add('d-none'); return; }
        var ng = fa.original_cost || 0;
        var accum = fa.accumulated_depreciation || 0;
        var nbv = fa.net_book_value || 0;
        var life = fa.useful_life || 0;
        var monthly = life ? (ng / (life * 12)) : 0;
        body.innerHTML = '<div class="row g-2">'
            +'<div class="col-6"><strong>Mã:</strong> '+esc(fa.code)+'</div>'
            +'<div class="col-6"><strong>Tên:</strong> '+esc(fa.name)+'</div>'
            +'<div class="col-6"><strong>Nguyên giá:</strong> '+ng.toLocaleString()+'</div>'
            +'<div class="col-6"><strong>HM lũy kế:</strong> '+accum.toLocaleString()+'</div>'
            +'<div class="col-6"><strong>GTCL:</strong> <span class="text-danger fw-bold">'+nbv.toLocaleString()+'</span></div>'
            +'<div class="col-6"><strong>KH/tháng:</strong> '+Math.round(monthly).toLocaleString()+'</div>'
            +'<div class="col-6"><strong>Ngày mua:</strong> '+esc(fa.purchase_date||'')+'</div>'
            +'<div class="col-6"><strong>PP khấu hao:</strong> '+esc(fa.depreciation_method||'')+'</div>'
            +'<div class="col-6"><strong>TGSD:</strong> '+life+' năm</div>'
            +'<div class="col-6"><strong>Loại:</strong> '+esc(fa.fa_type||'')+'</div>'
            +'<div class="col-12 mt-2"><span class="badge bg-'+(nbv>0?'warning':'secondary')+'">'+(nbv>0?'Còn GTCL':'Đã hết KH')+'</span></div>'
            +'</div>';
        infoCard.classList.remove('d-none');
        updateDisposalPreview();
    });

    function updateDisposalPreview() {
        var sel = document.getElementById('f_fixed_asset_id');
        var opt = sel.options[sel.selectedIndex];
        var prev = document.getElementById('disposalPreview');
        var tbody = document.getElementById('disposalLines');
        var tfoot = document.getElementById('disposalTotal');
        if (!opt || !opt.value) { prev.classList.add('d-none'); return; }
        var ng = parseFloat(opt.dataset.ng) || 0;
        var accum = parseFloat(opt.dataset.accum) || 0;
        var nbv = ng - accum;
        var type = document.getElementById('f_disposal_type').value;
        var proceeds = parseFloat(document.getElementById('f_proceeds').value) || 0;
        var costs = parseFloat(document.getElementsByName('costs')[0].value) || 0;
        if (!ng) { prev.classList.add('d-none'); return; }
        prev.classList.remove('d-none');
        var lines = '';
        var totalDr = 0, totalCr = 0;
        // Step 1: Remove asset
        lines += '<tr><td>2141</td><td>HM lũy kế TSCĐ</td><td class="text-end">'+accum.toLocaleString()+'</td><td></td></tr>';
        totalDr += accum;
        lines += '<tr><td>2112</td><td>Nguyên giá TSCĐ</td><td></td><td class="text-end">'+ng.toLocaleString()+'</td></tr>';
        totalCr += ng;
        if (nbv > 0) {
            if (type === 'sale') {
                var proceedsExVat = Math.round(proceeds / 1.1);
                var gain = proceedsExVat - nbv;
                if (gain >= 0) {
                    lines += '<tr><td>811</td><td>Lỗ thanh lý (NBV)</td><td class="text-end">'+nbv.toLocaleString()+'</td><td></td></tr>';
                    lines += '<tr><td>711</td><td>Lãi thanh lý</td><td></td><td class="text-end">'+gain.toLocaleString()+'</td></tr>';
                    totalDr += nbv; totalCr += gain;
                } else {
                    var loss = nbv - proceedsExVat;
                    lines += '<tr><td>811</td><td>Lỗ thanh lý</td><td class="text-end">'+loss.toLocaleString()+'</td><td></td></tr>';
                    totalDr += loss;
                }
            } else {
                // Liquidation: expense NBV + costs
                var loss = nbv + costs;
                lines += '<tr><td>811</td><td>Lỗ thanh lý</td><td class="text-end">'+loss.toLocaleString()+'</td><td></td></tr>';
                totalDr += loss;
            }
        }
        tbody.innerHTML = lines;
        tfoot.innerHTML = '<tr class="fw-bold"><td colspan="2">Tổng</td><td class="text-end">'+totalDr.toLocaleString()+'</td><td class="text-end">'+totalCr.toLocaleString()+'</td></tr>';
    }

    document.getElementById('f_disposal_type').addEventListener('change', function(){
        document.getElementById('saleFields').style.display = (this.value === 'sale') ? '' : 'none';
        updateDisposalPreview();
    });
    document.getElementById('f_proceeds').addEventListener('input', updateDisposalPreview);
    document.getElementsByName('costs')[0].addEventListener('input', updateDisposalPreview);
    document.getElementById('previewDisposalBtn').addEventListener('click', updateDisposalPreview);

    $('#disposalForm').on('submit', function(e){
        e.preventDefault();
        if (!confirm('Xác nhận thanh lý TSCĐ này?')) return;
        var data = Object.fromEntries(new FormData(this));
        data.proceeds = parseFloat(data.proceeds) || 0;
        data.costs = parseFloat(data.costs) || 0;

        $('#result').html('<span class="text-muted">Đang xử lý...</span>');
        fetch('/api/fixed-assets/dispose', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify(data)
        }).then(function(r){return r.json();}).then(function(d){
            if (d.error) { $('#result').html('<div class="alert alert-danger py-2 mb-0">'+d.error+'</div>'); return; }
            var msg = 'Đã ghi nhận thanh lý. Mã giao dịch: '+d.transaction_id;
            if (d.reference) msg += ', Số CT: '+d.reference;
            if (d.gain_loss !== undefined) msg += ', Lãi/lỗ: '+d.gain_loss.toLocaleString();
            $('#result').html('<div class="alert alert-success py-2 mb-0">'+msg+'</div>');
            document.getElementById('f_fixed_asset_id').querySelector('option[value="'+data.fixed_asset_id+'"]').remove();
            document.getElementById('assetInfoCard').classList.add('d-none');
            showToast('Đã ghi nhận thanh lý TSCĐ','success');
        }).catch(function(e){$('#result').html('<div class="alert alert-danger py-2 mb-0">Lỗi: '+e.message+'</div>');});
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
