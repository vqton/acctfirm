<?php $title = 'Đối chiếu ngân hàng'; $activeMenu = 'bank_reconciliation'; ob_start(); ?>
<div class="toolbar">
    <h5>Đối chiếu ngân hàng <span class="stats">(TK 112)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#startModal"><i class="bi bi-plus-lg"></i> Tạo đối chiếu</button>
    </div>
</div>

<div id="sessionListView">
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>TK NH</th><th>Ngày đối chiếu</th><th>Số dư sổ sách</th><th>Số dư NH</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr></thead>
        <tbody id="sessionBody"></tbody>
    </table>
</div>
</div>

<div id="sessionDetailView" style="display:none;">
<div class="row mb-3">
    <div class="col-md-3"><div class="card p-3 text-center"><small class="text-muted">Số dư sổ sách</small><strong class="fs-5" id="detailBookBalance">0</strong></div></div>
    <div class="col-md-3"><div class="card p-3 text-center"><small class="text-muted">Số dư ngân hàng</small><strong class="fs-5" id="detailStmtBalance">0</strong></div></div>
    <div class="col-md-3"><div class="card p-3 text-center"><small class="text-muted">Chênh lệch</small><strong class="fs-5" id="detailDiff">0</strong></div></div>
    <div class="col-md-3"><div class="card p-3 text-center"><small class="text-muted">Trạng thái</small><strong class="fs-5" id="detailStatus">0</strong></div></div>
</div>

<div class="mb-3">
    <button class="btn btn-sm btn-outline-secondary me-1" onclick="showSessions()"><i class="bi bi-arrow-left"></i> Quay lại</button>
    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#stmtEntryModal"><i class="bi bi-plus-lg"></i> Thêm giao dịch NH</button>
    <button class="btn btn-sm btn-success me-1" onclick="runAutoMatch()"><i class="bi bi-magic"></i> Tự động đối chiếu</button>
    <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#adjustModal"><i class="bi bi-pencil"></i> Bút toán điều chỉnh</button>
    <button class="btn btn-sm btn-primary" onclick="completeRecon()"><i class="bi bi-check-lg"></i> Hoàn tất</button>
</div>

<ul class="nav nav-tabs mb-3" id="reconTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#bookItems">Giao dịch sổ sách</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#stmtItems">Giao dịch ngân hàng</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#unmatchedItems">Chưa đối chiếu</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="bookItems">
        <div class="card-table"><table class="table table-hover table-sm">
            <thead><tr><th>Ngày</th><th>Diễn giải</th><th>Số CT</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody id="bookItemsBody"></tbody>
        </table></div>
    </div>
    <div class="tab-pane fade" id="stmtItems">
        <div class="card-table"><table class="table table-hover table-sm">
            <thead><tr><th>Ngày</th><th>Diễn giải</th><th>Số CT</th><th class="text-end">Thu</th><th class="text-end">Chi</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody id="stmtItemsBody"></tbody>
        </table></div>
    </div>
    <div class="tab-pane fade" id="unmatchedItems">
        <div class="card-table"><table class="table table-hover table-sm">
            <thead><tr><th>Nguồn</th><th>Ngày</th><th>Diễn giải</th><th class="text-end">Số tiền</th><th>Loại</th><th></th></tr></thead>
            <tbody id="unmatchedBody"></tbody>
        </table></div>
    </div>
</div>
</div>

<div class="modal fade" id="startModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="startForm">
<div class="modal-header"><h5 class="modal-title">Tạo đối chiếu ngân hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Tài khoản ngân hàng</label><select class="form-select" id="bankAccount" required></select></div>
    <div class="mb-3"><label>Ngày đối chiếu</label><input type="date" class="form-control" id="stmtDate" value="<?=date('Y-m-d')?>" required></div>
    <div class="mb-3"><label>Số dư theo ngân hàng</label><input type="number" class="form-control" id="stmtBalance" step="1000" min="0" required></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Bắt đầu</button>
</div>
</form>
</div></div></div>

<div class="modal fade" id="stmtEntryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="stmtEntryForm">
<div class="modal-header"><h5 class="modal-title">Thêm giao dịch ngân hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Loại</label>
        <select class="form-select" id="entryType">
            <option value="receipt">Thu tiền (báo Có)</option>
            <option value="payment">Chi tiền (báo Nợ)</option>
        </select>
    </div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="entryAmount" step="1000" min="1" required></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="entryDesc"></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="entryRef"></div>
    <div class="mb-3"><label>Ngày</label><input type="date" class="form-control" id="entryDate" value="<?=date('Y-m-d')?>"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Thêm</button>
</div>
</form>
</div></div></div>

<div class="modal fade" id="adjustModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="adjustForm">
<div class="modal-header"><h5 class="modal-title">Bút toán điều chỉnh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <p class="text-muted small">Ghi nhận các khoản ngân hàng đã hạch toán nhưng chưa có trong sổ sách (phí, lãi,...)</p>
    <div class="mb-3"><label>Ghi Nợ TK</label><select class="form-select" id="adjDebit"></select></div>
    <div class="mb-3"><label>Ghi Có TK</label><select class="form-select" id="adjCredit"></select></div>
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="adjAmount" step="1000" min="1" required></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="adjDesc" placeholder="Phí ngân hàng tháng..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-warning">Ghi nhận điều chỉnh</button>
</div>
</form>
</div></div></div>

<script>
var currentSessionId = null;

function loadAccounts() {
    $.get('/api/cash/accounts', function(accounts) {
        accounts.forEach(function(a){
            if (a.code.substr(0,3) === '112') {
                $('#bankAccount').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>');
            }
        });
        $('#adjDebit, #adjCredit').empty();
        accounts.forEach(function(a){
            if (a.code !== '111' && a.code !== '112') {
                $('#adjDebit, #adjCredit').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+'</option>');
            }
        });
    });
}

function loadSessions() {
    $.get('/api/bank-reconciliation/sessions', function(data) {
        var tbody=$('#sessionBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Chưa có phiên đối chiếu</td></tr>');return;}
        data.forEach(function(s){
            var badge = s.status === 'completed' ? 'badge-active' : 'badge-warning';
            tbody.append('<tr onclick="openSession('+s.id+')" style="cursor:pointer"><td>'+esc(s.bank_account_code)+'</td><td>'+esc(s.statement_date)+'</td><td class="text-end font-monospace">'+parseFloat(s.book_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(s.statement_balance).toLocaleString()+'</td><td><span class="badge-status '+badge+'">'+esc(s.status)+'</span></td><td>'+esc(s.created_at)+'</td><td>'+(s.status==='in_progress'?'<button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();openSession('+s.id+')">Mở</button>':'')+'</td></tr>');
        });
    });
}

function openSession(id) {
    currentSessionId = id;
    $('#sessionListView').hide();
    $('#sessionDetailView').show();
    loadSessionDetail();
}

function showSessions() {
    currentSessionId = null;
    $('#sessionDetailView').hide();
    $('#sessionListView').show();
    loadSessions();
}

function loadSessionDetail() {
    $.get('/api/bank-reconciliation/'+currentSessionId+'/session', function(s) {
        $('#detailBookBalance').text(parseFloat(s.book_balance).toLocaleString()+' VND');
        $('#detailStmtBalance').text(parseFloat(s.statement_balance).toLocaleString()+' VND');
        var diff = s.statement_balance - s.book_balance;
        $('#detailDiff').text((diff >= 0 ? '+':'')+parseFloat(diff).toLocaleString()+' VND').css('color', Math.abs(diff) < 1000 ? 'green' : 'red');
        $('#detailStatus').text(s.status === 'completed' ? 'Hoàn tất' : 'Đang xử lý');
    });
    loadBookItems();
    loadStmtItems();
    loadUnmatched();
}

function loadBookItems() {
    $.get('/api/bank-reconciliation/'+currentSessionId+'/items', function(data) {
        var tbody=$('#bookItemsBody'); tbody.empty();
        var items = data.filter(function(i){return i.source==='book';});
        if(items.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Không có giao dịch</td></tr>');return;}
        items.forEach(function(i){
            var badge = i.match_status === 'matched' ? 'badge-active' : 'badge-warning';
            var receipt = i.type === 'receipt' ? parseFloat(i.amount).toLocaleString() : '';
            var payment = i.type === 'payment' ? parseFloat(i.amount).toLocaleString() : '';
            tbody.append('<tr><td>'+esc(i.transaction_date)+'</td><td>'+esc(i.description)+'</td><td>'+esc(i.reference)+'</td><td class="text-end font-monospace">'+receipt+'</td><td class="text-end font-monospace">'+payment+'</td><td><span class="badge-status '+badge+'">'+esc(i.match_status)+'</span></td><td>'+(i.match_status==='unmatched'?'<button class="btn btn-sm btn-outline-primary" onclick="manualMatchPrompt('+i.id+')">Ghép</button>':'')+'</td></tr>');
        });
    });
}

function loadStmtItems() {
    $.get('/api/bank-reconciliation/'+currentSessionId+'/items', function(data) {
        var tbody=$('#stmtItemsBody'); tbody.empty();
        var items = data.filter(function(i){return i.source==='statement';});
        if(items.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Chưa nhập giao dịch ngân hàng</td></tr>');return;}
        items.forEach(function(i){
            var badge = i.match_status === 'matched' ? 'badge-active' : 'badge-warning';
            var receipt = i.type === 'receipt' ? parseFloat(i.amount).toLocaleString() : '';
            var payment = i.type === 'payment' ? parseFloat(i.amount).toLocaleString() : '';
            tbody.append('<tr><td>'+esc(i.transaction_date)+'</td><td>'+esc(i.description)+'</td><td>'+esc(i.reference)+'</td><td class="text-end font-monospace">'+receipt+'</td><td class="text-end font-monospace">'+payment+'</td><td><span class="badge-status '+badge+'">'+esc(i.match_status)+'</span></td><td>'+(i.match_status==='unmatched'?'<button class="btn btn-sm btn-outline-primary" onclick="manualMatchPrompt('+i.id+')">Ghép</button>':'')+'</td></tr>');
        });
    });
}

function loadUnmatched() {
    $.get('/api/bank-reconciliation/'+currentSessionId+'/unmatched', function(data) {
        var tbody=$('#unmatchedBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted py-3">Tất cả đã được đối chiếu</td></tr>');return;}
        data.forEach(function(i){
            tbody.append('<tr><td>'+esc(i.source)+'</td><td>'+esc(i.transaction_date)+'</td><td>'+esc(i.description)+'</td><td class="text-end font-monospace">'+parseFloat(i.amount).toLocaleString()+'</td><td>'+esc(i.type)+'</td><td><button class="btn btn-sm btn-outline-primary" onclick="manualMatchPrompt('+i.id+')">Ghép thủ công</button></td></tr>');
        });
    });
}

var pendingMatchItemId = null;
function manualMatchPrompt(itemId) {
    pendingMatchItemId = itemId;
    var source = prompt('Nhập ID giao dịch cần ghép (xem trong tab Giao dịch sổ sách hoặc NH):');
    if (source) {
        var bookId = parseInt(source);
        if (isNaN(bookId)) return;
        $.ajax({
            url:'/api/bank-reconciliation/'+currentSessionId+'/manual-match',
            method:'POST', contentType:'application/json',
            data:JSON.stringify({statement_item_id: pendingMatchItemId, book_item_id: bookId}),
            success:function(){showToast('Đã ghép thủ công','success');loadSessionDetail();},
            error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
        });
    }
}

function runAutoMatch() {
    $.ajax({
        url:'/api/bank-reconciliation/'+currentSessionId+'/auto-match',
        method:'POST', contentType:'application/json',
        success:function(r){showToast('Đã đối chiếu: '+r.matched+' cặp, còn '+r.unmatched+' chưa khớp','success');loadSessionDetail();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

function completeRecon() {
    if(!confirm('Hoàn tất đối chiếu? Sẽ khóa phiên đối chiếu này.'))return;
    $.ajax({
        url:'/api/bank-reconciliation/'+currentSessionId+'/complete',
        method:'POST', contentType:'application/json',
        success:function(r){
            if(r.balanced){showToast('Đối chiếu hoàn tất. Chênh lệch: '+parseFloat(r.statement_balance - r.adjusted_book).toLocaleString()+' VND','success');}
            loadSessionDetail();
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

$('#startForm').submit(function(e){e.preventDefault();
    $.ajax({
        url:'/api/bank-reconciliation/start', method:'POST', contentType:'application/json',
        data:JSON.stringify({
            bank_account_code:$('#bankAccount').val(),
            statement_date:$('#stmtDate').val(),
            statement_balance:parseFloat($('#stmtBalance').val())
        }),
        success:function(r){$('#startModal').modal('hide');$('#startForm')[0].reset();showToast('Tạo phiên đối chiếu thành công','success');openSession(r.id);},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

$('#stmtEntryForm').submit(function(e){e.preventDefault();
    $.ajax({
        url:'/api/bank-reconciliation/'+currentSessionId+'/statement-entry', method:'POST', contentType:'application/json',
        data:JSON.stringify({
            amount:parseFloat($('#entryAmount').val()),
            type:$('#entryType').val(),
            description:$('#entryDesc').val(),
            reference:$('#entryRef').val(),
            date:$('#entryDate').val()
        }),
        success:function(){$('#stmtEntryModal').modal('hide');$('#stmtEntryForm')[0].reset();showToast('Thêm giao dịch thành công','success');loadSessionDetail();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

$('#adjustForm').submit(function(e){e.preventDefault();
    $.ajax({
        url:'/api/bank-reconciliation/'+currentSessionId+'/adjust', method:'POST', contentType:'application/json',
        data:JSON.stringify({
            debit_account:$('#adjDebit').val(),
            credit_account:$('#adjCredit').val(),
            amount:parseFloat($('#adjAmount').val()),
            description:$('#adjDesc').val()
        }),
        success:function(){$('#adjustModal').modal('hide');$('#adjustForm')[0].reset();showToast('Ghi nhận điều chỉnh thành công','success');loadSessionDetail();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

$(document).ready(function(){loadAccounts();loadSessions();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
