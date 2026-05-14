<?php $title = 'Tạm ứng'; $activeMenu = 'petty_cash'; ob_start(); ?>
<div class="toolbar">
    <h5>Quỹ tạm ứng <span class="stats">(Petty Cash)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fundModal"><i class="bi bi-plus-lg"></i> Lập quỹ</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Tên quỹ</th><th>Hạn mức</th><th>Số dư</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr></thead>
        <tbody id="fundBody"></tbody>
    </table>
    <div id="txSection" style="display:none;">
        <div class="card-header-x px-3 py-2 mt-3"><strong><i class="bi bi-list-ul"></i> Giao dịch</strong> <span id="txFundName" class="text-muted ms-2"></span></div>
        <div id="txBody" class="p-3"></div>
    </div>
</div>
<div class="modal fade" id="fundModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="fundForm">
<div class="modal-header"><h5 class="modal-title">Lập quỹ tạm ứng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Tên quỹ</label><input type="text" class="form-control" id="fundName" placeholder="Quỹ tạm ứng văn phòng" required></div>
    <div class="mb-3"><label>Hạn mức</label><input type="number" class="form-control" id="imprestAmount" step="1000" min="1" required></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lập quỹ</button>
</div>
</form>
</div></div></div>
<div class="modal fade" id="disburseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="disburseForm">
<div class="modal-header"><h5 class="modal-title">Chi tạm ứng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="disburseAmount" step="1000" min="1" required></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="disburseDesc" placeholder="Mua văn phòng phẩm..."></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="disburseRef"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-danger">Chi tiền</button>
</div>
</form>
</div></div></div>
<div class="modal fade" id="replenishModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="replenishForm">
<div class="modal-header"><h5 class="modal-title">Cấp bù quỹ tạm ứng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Ghi Nợ TK</label><select class="form-select" id="replenishAccount"></select></div>
    <div class="mb-3"><label>Tổng số tiền</label><input type="number" class="form-control" id="replenishAmount" step="1000" min="1" required></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="replenishDesc" placeholder="Cấp bù quỹ tạm ứng..."></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Cấp bù</button>
</div>
</form>
</div></div></div>
<script>
var currentFundId = null;

$.get('/api/cash/accounts?for=payment', function(accounts) {
    accounts.forEach(function(a){if(a.code!=='111'&&a.code!=='112')$('#replenishAccount').append('<option value="'+esc(a.code)+'">'+esc(a.code)+' - '+esc(a.name)+' ('+parseFloat(a.balance).toLocaleString()+' VND)</option>');});
});

function loadFunds() {
    $.get('/api/petty-cash/funds', function(data) {
        var tbody=$('#fundBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="6" class="text-center text-muted py-4">Chưa có quỹ tạm ứng</td></tr>');return;}
        data.forEach(function(f){
            var actions='';
            if(f.status==='active'){
                actions='<button class="btn btn-sm btn-outline-warning me-1" onclick="openDisburse(\''+esc(f.id)+'\')"><i class="bi bi-cash"></i> Chi</button>';
                actions+='<button class="btn btn-sm btn-outline-success me-1" onclick="openReplenish(\''+esc(f.id)+'\')"><i class="bi bi-arrow-clockwise"></i> Cấp bù</button>';
                actions+='<button class="btn btn-sm btn-outline-secondary" onclick="closeFund(\''+esc(f.id)+'\')"><i class="bi bi-x-circle"></i> Đóng</button>';
            }
            tbody.append('<tr onclick="showTx(\''+esc(f.id)+'\',\''+esc(f.fund_name)+'\')" style="cursor:pointer"><td>'+esc(f.fund_name)+'</td><td class="text-end font-monospace">'+parseFloat(f.imprest_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(f.current_balance).toLocaleString()+'</td><td><span class="badge-status '+(f.status==='active'?'badge-active':'badge-inactive')+'">'+esc(f.status)+'</span></td><td>'+esc(f.created_at)+'</td><td>'+actions+'</td></tr>');
        });
    });
}

function showTx(fundId, fundName) {
    currentFundId = fundId;
    $('#txSection').show();
    $('#txFundName').text(fundName);
    $.get('/api/petty-cash/'+fundId+'/transactions', function(data) {
        var html='<table class="table table-sm"><thead><tr><th>Loại</th><th>Số tiền</th><th>Diễn giải</th><th>TK CP</th><th>Ngày</th></tr></thead><tbody>';
        if(data.length===0){html+='<tr><td colspan="5" class="text-muted text-center">Chưa có giao dịch</td></tr>';}
        data.forEach(function(t){html+='<tr><td>'+esc(t.type)+'</td><td class="text-end font-monospace">'+parseFloat(t.amount).toLocaleString()+'</td><td>'+esc(t.description)+'</td><td>'+(t.expense_account||'')+'</td><td>'+esc(t.created_at)+'</td></tr>';});
        html+='</tbody></table>';
        $('#txBody').html(html);
    });
}

function openDisburse(fundId){currentFundId=fundId;$('#disburseModal').modal('show');}
function openReplenish(fundId){currentFundId=fundId;$('#replenishModal').modal('show');}
function closeFund(fundId){
    if(!confirm('Đóng quỹ tạm ứng này? Số dư còn lại sẽ được hoàn trả.'))return;
    $.ajax({url:'/api/petty-cash/close',method:'POST',contentType:'application/json',data:JSON.stringify({fund_id:fundId,return_amount:0}),success:function(){showToast('Đã đóng quỹ','success');loadFunds();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
}

$('#fundForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/petty-cash/funds',method:'POST',contentType:'application/json',data:JSON.stringify({fund_name:$('#fundName').val(),imprest_amount:parseFloat($('#imprestAmount').val())}),success:function(){$('#fundModal').modal('hide');$('#fundForm')[0].reset();showToast('Lập quỹ thành công','success');loadFunds();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$('#disburseForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/petty-cash/disburse',method:'POST',contentType:'application/json',data:JSON.stringify({fund_id:currentFundId,amount:parseFloat($('#disburseAmount').val()),description:$('#disburseDesc').val(),reference:$('#disburseRef').val()||undefined}),success:function(){$('#disburseModal').modal('hide');$('#disburseForm')[0].reset();showToast('Chi tiền thành công','success');loadFunds();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$('#replenishForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/petty-cash/replenish',method:'POST',contentType:'application/json',data:JSON.stringify({fund_id:currentFundId,expense_account:$('#replenishAccount').val(),total_amount:parseFloat($('#replenishAmount').val()),description:$('#replenishDesc').val()}),success:function(){$('#replenishModal').modal('hide');$('#replenishForm')[0].reset();showToast('Cấp bù thành công','success');loadFunds();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadFunds();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
