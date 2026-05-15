<?php $title = 'Dashboard'; $activeMenu = 'dashboard'; ob_start(); ?>
<div class="toolbar">
    <h5>Dashboard</h5>
</div>

<div class="row g-3 mb-4" id="kpiCards"></div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="mb-3">Dòng tiền 7 ngày</h6>
            <table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th class="text-end">Thu</th><th class="text-end">Chi</th></tr></thead><tbody id="trendBody"></tbody></table>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="mb-3">Truy cập nhanh</h6>
            <div class="d-grid gap-2">
                <a href="/thu/quy-tien-mat" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-cash me-2"></i>Phiếu thu</a>
                <a href="/chi/quy-tien-mat" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-cash-stack me-2"></i>Phiếu chi</a>
                <a href="/thu/doi-chieu-ngan-hang" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-arrow-repeat me-2"></i>Đối chiếu NH</a>
                <a href="/bao-cao/ket-qua-kinh-doanh" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-bar-chart me-2"></i>Báo cáo KQKD</a>
            </div>
        </div></div>
    </div>
</div>

<script>
function loadKPIs() {
    $.get('/api/dashboard', function(d) {
        var cards = '';
        cards += '<div class="col-md-3"><div class="card border-0 shadow-sm p-3 text-center"><small class="text-muted">Tiền mặt (111)</small><strong class="fs-4">'+parseFloat(d.cash_balance).toLocaleString()+'</strong></div></div>';
        cards += '<div class="col-md-3"><div class="card border-0 shadow-sm p-3 text-center"><small class="text-muted">Tiền gửi (112)</small><strong class="fs-4">'+parseFloat(d.bank_balance).toLocaleString()+'</strong></div></div>';
        cards += '<div class="col-md-3"><div class="card border-0 shadow-sm p-3 text-center"><small class="text-muted">Thu hôm nay</small><strong class="fs-4 text-success">+'+parseFloat(d.today_receipts).toLocaleString()+'</strong></div></div>';
        cards += '<div class="col-md-3"><div class="card border-0 shadow-sm p-3 text-center"><small class="text-muted">Chi hôm nay</small><strong class="fs-4 text-danger">-'+parseFloat(d.today_payments).toLocaleString()+'</strong></div></div>';
        $('#kpiCards').html(cards);

        var tb=$('#trendBody'); tb.empty();
        if(d.trend.length===0){tb.append('<tr><td colspan="3" class="text-muted text-center">Không có dữ liệu</td></tr>');}
        d.trend.forEach(function(r){
            tb.append('<tr><td>'+esc(r.date)+'</td><td class="text-end font-monospace">'+parseFloat(r.receipts).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.payments).toLocaleString()+'</td></tr>');
        });
    });
}
$(document).ready(function(){loadKPIs();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
