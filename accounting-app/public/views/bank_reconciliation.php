<?php // Màn hình: Đối chiếu số dư ngân hàng với sổ sách
// API: GET /api/bank-reconciliation/sessions, GET /api/bank-reconciliation/bank-accounts, POST /api/bank-reconciliation/start
// Nghiệp vụ: Đối chiếu số dư TK 112 trên sổ kế toán với sao kê ngân hàng
// Quy trình: Tạo phiên → nhập số dư NH → so khớp từng giao dịch → xử lý chênh lệch
// Rủi ro: Chênh lệch không được xử lý sẽ dẫn đến sai số dư tiền gửi trên BC01
$title = 'Đối chiếu ngân hàng'; $activeMenu = 'bank_reconciliation'; ob_start(); ?>
<div class="toolbar">
    <h5>Đối chiếu ngân hàng</h5>
    <?php if (!isset($_GET['session'])): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#startModal"><i class="bi bi-plus-lg"></i> Bắt đầu đối chiếu</button>
    <?php else: ?>
    <a href="/thu/doi-chieu-ngan-hang" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Quay lại</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['session'])): $sessionId = (int)$_GET['session']; ?>
<div id="sessionDetail">
    <div class="row g-2 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Số dư sổ sách</small><div class="h5 mb-0 font-monospace" id="bookBalance">—</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Số dư sao kê</small><div class="h5 mb-0 font-monospace" id="statementBalance">—</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Chênh lệch</small><div class="h5 mb-0 font-monospace" id="diffBalance">—</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Trạng thái</small><div class="h5 mb-0" id="statusDisplay">—</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Nhập sao kê ngân hàng</span>
            <div>
                <form id="csvUploadForm" style="display:inline">
                    <input type="file" id="csvFile" accept=".csv" style="display:none">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="$('#csvFile').click()"><i class="bi bi-upload"></i> Import CSV</button>
                </form>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#entryModal" onclick="$('#entrySessionId').val(<?=$sessionId?>)"><i class="bi bi-plus"></i> Nhập tay</button>
            </div>
        </div>
        <div id="csvStatus" class="px-3 pt-2 d-none"></div>
    </div>

    <ul class="nav nav-tabs mb-2" id="reconTabs">
        <li class="nav-item"><a class="nav-link active" data-tab="statement">Sao kê NH</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="book">Sổ sách</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="matched">Đã khớp</a></li>
    </ul>

    <div class="card-table"><table class="table table-hover table-sm" id="reconTable">
        <thead><tr><th>Nguồn</th><th>Ngày</th><th>Tham chiếu</th><th>Diễn giải</th><th class="text-end">Số tiền</th><th>Loại</th><th>Trạng thái</th></tr></thead>
        <tbody id="reconBody"></tbody>
    </table></div>
</div>

<script>
var sessionId = <?=$sessionId?>;

function loadSession() {
    $.get('/api/bank-reconciliation/' + sessionId + '/session', function(r) {
        var d = r.data;
        $('#bookBalance').text(parseFloat(d.book_balance).toLocaleString());
        $('#statementBalance').text(parseFloat(d.statement_balance).toLocaleString());
        var diff = parseFloat(d.statement_balance) - parseFloat(d.book_balance);
        $('#diffBalance').text(diff.toLocaleString());
        var badge = d.status === 'completed' ? 'badge-active' : 'badge-warning';
        $('#statusDisplay').html('<span class="badge-status ' + badge + '">' + d.status + '</span>');
    });
}

function loadItems() {
    $.get('/api/bank-reconciliation/' + sessionId + '/items', function(res) {
        var tbody = $('#reconBody').empty();
        var activeTab = $('#reconTabs .active').data('tab');
        (res.data || res).forEach(function(r) {
            if (activeTab === 'statement' && r.source !== 'statement') return;
            if (activeTab === 'book' && r.source !== 'book') return;
            if (activeTab === 'matched' && r.match_status !== 'matched') return;
            var cls = r.match_status === 'matched' ? '' : (r.source === 'statement' ? 'table-info' : '');
            var badge = r.match_status === 'matched' ? '<span class="badge bg-success">Đã khớp</span>' : '<span class="badge bg-warning">Chưa khớp</span>';
            tbody.append('<tr class="' + cls + '"><td>' + r.source + '</td><td>' + r.transaction_date + '</td><td><code>' + esc(r.reference || '') + '</code></td><td>' + esc(r.description || '') + '</td><td class="text-end font-monospace">' + parseFloat(r.amount).toLocaleString() + '</td><td>' + r.type + '</td><td>' + badge + '</td></tr>');
        });
    });
}

$('#csvFile').change(function() {
    var file = this.files[0];
    if (!file) return;
    var fd = new FormData();
    fd.append('file', file);
    var statusDiv = $('#csvStatus').removeClass('d-none alert-success alert-danger').addClass('alert alert-info').html('<span class="spinner-border spinner-border-sm"></span> Đang import...');
    $.ajax({
        url: '/api/bank-reconciliation/' + sessionId + '/import-csv',
        method: 'POST', data: fd, processData: false, contentType: false,
        headers: { 'X-CSRF-Token': csrf },
        success: function(r) {
            statusDiv.removeClass('alert-info').addClass('alert-success').html('Đã import ' + r.data.imported + ' giao dịch' + (r.data.errors && r.data.errors.length ? '. Lỗi: ' + r.data.errors.join('; ') : ''));
            $('#csvFile').val('');
            loadItems();
        },
        error: function(x) {
            var msg = 'Lỗi import CSV';
            try { msg = JSON.parse(x.responseText).error; } catch(e) {}
            statusDiv.removeClass('alert-info').addClass('alert-danger').html(msg);
        }
    });
});

$('#reconTabs a').click(function(e) {
    e.preventDefault();
    $('#reconTabs a').removeClass('active');
    $(this).addClass('active');
    loadItems();
});

$(document).ready(function() { loadSession(); loadItems(); });
</script>

<?php else: ?>

<div class="card-table"><table class="table table-hover">
    <thead><tr><th>TK NH</th><th>Ngày đối chiếu</th><th class="text-end">SD NH</th><th class="text-end">SD Sổ</th><th class="text-end">Chênh lệch</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="startModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="startForm">
<div class="modal-header"><h5 class="modal-title">Bắt đầu đối chiếu</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Tài khoản ngân hàng</label><select class="form-select" id="bankAccount" required></select></div>
    <div class="row g-2"><div class="col-6 mb-2"><label>Ngày đối chiếu</label><input type="date" class="form-control" id="statementDate" value="<?=date('Y-m-d')?>"></div><div class="col-6 mb-2"><label>Số dư NH</label><input type="number" class="form-control" id="statementBalance" step="1000" min="0" required></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Bắt đầu</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.get('/api/bank-reconciliation/sessions',function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var diff=parseFloat(r.statement_balance)-parseFloat(r.book_balance);
            var badge=r.status==='completed'?'badge-active':(r.status==='in_progress'?'badge-warning':'badge-inactive');
            tbody.append('<tr><td>'+esc(r.bank_account_code)+'</td><td>'+esc(r.statement_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.statement_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.book_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+diff.toLocaleString()+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+(r.status==='in_progress'?'<button class="btn btn-sm btn-outline-primary" onclick="viewSession('+r.id+')"><i class="bi bi-eye"></i></button>':'')+'</td></tr>');
        });
    });
}
function loadBankAccounts(){
    $.get('/api/bank-reconciliation/bank-accounts',function(l){var o='';l.forEach(function(a){o+='<option value="'+a.id+'">'+esc(a.code)+' - '+esc(a.account_number)+'</option>';});$('#bankAccount').html(o);});
}
function viewSession(id){
    window.location='/thu/doi-chieu-ngan-hang?session='+id;
}
$('#startForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/bank-reconciliation/start',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({bank_account_code:$('#bankAccount').val(),statement_date:$('#statementDate').val(),statement_balance:parseFloat($('#statementBalance').val())}),
        success:function(r){$('#startModal').modal('hide');showToast('Đã tạo phiên đối chiếu ngân hàng thành công.','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadBankAccounts();loadData();});
</script>

<?php endif; ?>

<div class="modal fade" id="entryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="entryForm">
<input type="hidden" id="entrySessionId">
<div class="modal-header"><h5 class="modal-title">Thêm giao dịch NH</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-2"><div class="col-6 mb-2"><label>Số tiền</label><input type="number" class="form-control" id="entryAmount" step="1000" min="1" required></div><div class="col-6 mb-2"><label>Loại</label><select class="form-select" id="entryType"><option value="receipt">Thu</option><option value="payment">Chi</option></select></div></div>
    <div class="mb-2"><label>Mô tả</label><input class="form-control" id="entryDesc"></div>
    <div class="mb-2"><label>Tham chiếu</label><input class="form-control" id="entryRef"></div>
    <div class="mb-2"><label>Ngày</label><input type="date" class="form-control" id="entryDate" value="<?=date('Y-m-d')?>"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Thêm</button></div>
</form>
</div></div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
