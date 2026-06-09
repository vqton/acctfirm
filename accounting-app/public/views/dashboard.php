<?php // Trang tổng quan hệ thống — KPI tài chính thời gian thực
$title = 'Tổng quan'; $activeMenu = 'dashboard'; ob_start(); ?>
<div class="toolbar">
    <h5><i class="bi bi-speedometer2 me-2"></i>Tổng quan</h5>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4" id="kpiCards"></div>

<!-- Charts + Recent Transactions -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-graph-up me-2"></i>Dòng tiền 7 ngày qua</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr>
                    <th>Ngày</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th class="text-end">Chênh lệch</th>
                </tr></thead><tbody id="trendBody"></tbody></table>
            </div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-pie-chart me-2"></i>Trạng thái giao dịch</h6>
            <div id="statusChart"></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Giao dịch gần đây</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr>
                    <th>Số CT</th><th>Ngày</th><th>Diễn giải</th><th>Trạng thái</th>
                </tr></thead><tbody id="recentTxns"></tbody></table>
            </div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-lightning me-2"></i>Truy cập nhanh</h6>
            <div class="d-grid gap-2">
                <a href="/tong-hop/chung-tu-ghi-so" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-journal-text me-2"></i>Chứng từ ghi sổ</a>
                <a href="/thu/quy-tien-mat" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-cash me-2"></i>Phiếu thu</a>
                <a href="/chi/quy-tien-mat" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-cash-stack me-2"></i>Phiếu chi</a>
                <a href="/tong-hop/phe-duyet" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-check-circle me-2"></i>Phê duyệt</a>
                <a href="/bao-cao/ket-qua-kinh-doanh" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-bar-chart me-2"></i>Báo cáo KQKD</a>
                <a href="/bao-cao/tinh-hinh-tai-chinh" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-file-earmark-text me-2"></i>Bảng CĐKT</a>
            </div>
        </div></div>
    </div>
</div>

<script>
function fmt(n) { return VAS.fmt(n); }
function badge(s) { return statusBadge(s); }

function loadKPIs() {
    $.get('/api/dashboard', function(d) {
        var cards = '';

        // 1. Tổng tiền
        cards += '<div class="col-md-4 col-lg-2"><div class="card border-0 shadow-sm p-3 text-center h-100">' +
            '<small class="text-muted">Tổng tiền</small>' +
            '<strong class="fs-5">'+fmt(d.total_cash)+'</strong>' +
            '<small class="text-muted mt-1">TM: '+fmt(d.cash_balance)+' | NH: '+fmt(d.bank_balance)+'</small></div></div>';

        // 2. Doanh thu YTD
        var profitClass = d.profit_ytd >= 0 ? 'text-success' : 'text-danger';
        cards += '<div class="col-md-4 col-lg-2"><div class="card border-0 shadow-sm p-3 text-center h-100">' +
            '<small class="text-muted">Doanh thu YTD</small>' +
            '<strong class="fs-5">'+fmt(d.revenue_ytd)+'</strong>' +
            '<small class="'+profitClass+' mt-1">LN: '+fmt(d.profit_ytd)+'</small></div></div>';

        // 3. Chi phí YTD
        cards += '<div class="col-md-4 col-lg-2"><div class="card border-0 shadow-sm p-3 text-center h-100">' +
            '<small class="text-muted">Chi phí YTD</small>' +
            '<strong class="fs-5 text-danger">'+fmt(d.expense_ytd)+'</strong>' +
            '<small class="text-muted mt-1">Tỷ lệ '+(d.revenue_ytd>0 ? (d.expense_ytd/d.revenue_ytd*100).toFixed(1) : '0')+'%</small></div></div>';

        // 4. Chờ duyệt
        var pendingBadge = d.pending_approvals > 0 ? '<span class="badge bg-danger ms-1">'+d.pending_approvals+'</span>' : '';
        cards += '<div class="col-md-4 col-lg-2"><div class="card border-0 shadow-sm p-3 text-center h-100">' +
            '<small class="text-muted">Chờ duyệt</small>' +
            '<strong class="fs-5">'+d.pending_approvals+pendingBadge+'</strong>' +
            '<small class="text-muted mt-1">/'+d.total_transactions+' giao dịch</small></div></div>';

        // 5. Cân đối
        var tbIcon = d.trial_balance.balanced ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>';
        cards += '<div class="col-md-4 col-lg-2"><div class="card border-0 shadow-sm p-3 text-center h-100">' +
            '<small class="text-muted">Cân đối</small>' +
            '<div>'+tbIcon+'</div>' +
            '<small class="text-muted mt-1">Dr: '+fmt(d.trial_balance.total_dr)+' | Cr: '+fmt(d.trial_balance.total_cr)+'</small></div></div>';

        // 6. Kỳ hiện tại
        var periodStatus = d.current_period && d.current_period.status === 'open' ?
            '<i class="bi bi-circle-fill text-success" style="font-size:10px"></i> Mở' :
            '<i class="bi bi-circle-fill text-secondary" style="font-size:10px"></i> Đóng';
        cards += '<div class="col-md-4 col-lg-2"><div class="card border-0 shadow-sm p-3 text-center h-100">' +
            '<small class="text-muted">Kỳ hiện tại</small>' +
            '<strong class="fs-6">'+(d.current_period ? d.current_period.name : '---')+'</strong>' +
            '<small class="text-muted mt-1">'+periodStatus+'</small></div></div>';

        $('#kpiCards').html(cards);

        // === TREND TABLE ===
        var tb=$('#trendBody'); tb.empty();
        if(d.trend.length===0){tb.append('<tr><td colspan="4" class="text-muted text-center">Không có dữ liệu</td></tr>');}
        d.trend.forEach(function(r){
            var diff = parseFloat(r.receipts)-parseFloat(r.payments);
            var diffCls = diff>=0 ? 'text-success' : 'text-danger';
            tb.append('<tr><td>'+esc(r.date)+'</td><td class="text-end vas-number">'+fmt(r.receipts)+'</td><td class="text-end vas-number">'+fmt(r.payments)+'</td><td class="text-end vas-number '+diffCls+'">'+(diff>=0?'+':'')+fmt(Math.abs(diff))+'</td></tr>');
        });

        // === STATUS CHART (simple bar) ===
        var sc=$('#statusChart'); sc.empty();
        var bars = ''; var colors = {draft:'secondary',submitted:'info',approved:'primary',posted:'success',reversed:'warning',pending:'secondary'};
        var labels = {draft:'Nháp',submitted:'Chờ duyệt',approved:'Đã duyệt',posted:'Đã ghi sổ',reversed:'Đã đảo',pending:'Chờ'};
        d.status_breakdown = d.status_breakdown || {};
        var total = d.total_transactions || 1;
        Object.keys(colors).forEach(function(k){
            var cnt = parseInt(d.status_breakdown[k]) || 0;
            if(cnt===0) return;
            var pct = (cnt/total*100).toFixed(1);
            bars += '<div class="d-flex align-items-center mb-2"><span class="me-2" style="width:100px">'+(labels[k]||k)+'</span>' +
                '<div class="progress flex-grow-1" style="height:20px"><div class="progress-bar bg-'+colors[k]+'" style="width:'+pct+'%">'+cnt+'</div></div>' +
                '<span class="ms-2 small text-muted">'+pct+'%</span></div>';
        });
        sc.html(bars || '<span class="text-muted">Không có dữ liệu</span>');

        // === RECENT TRANSACTIONS ===
        var rt=$('#recentTxns'); rt.empty();
        if(!d.recent_transactions || d.recent_transactions.length===0){rt.append('<tr><td colspan="4" class="text-muted text-center">Không có giao dịch</td></tr>');}
        else {
            (d.recent_transactions||[]).forEach(function(t){
                rt.append('<tr><td class="font-monospace small">'+esc(t.reference||'')+'</td><td>'+esc(t.transaction_date||'')+'</td><td>'+esc((t.description||'').substring(0,50))+'</td><td>'+badge(t.status)+'</td></tr>');
            });
        }
    }).fail(function(){ $('#kpiCards').html('<div class="col-12"><div class="alert alert-danger">Không thể tải dữ liệu tổng quan</div></div>'); });
}
$(document).ready(function(){loadKPIs();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
