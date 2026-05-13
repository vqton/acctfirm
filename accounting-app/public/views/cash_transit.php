<?php $title = 'Tiền đang chuyển'; $activeMenu = 'cash_transit'; ob_start(); ?>
<div class="toolbar">
    <h5>Tiền đang chuyển <span class="stats">(TK 113)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transitModal"><i class="bi bi-plus-lg"></i> Ghi nhận chuyển tiền</button>
    </div>
</div>
<div class="card-table">
    <table class="table table-hover">
        <thead><tr><th>Mã giao dịch</th><th>Diễn giải</th><th>Số tiền</th><th>Trạng thái</th><th>Ngày chuyển</th><th>Ngày xác nhận</th><th></th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>
<div class="modal fade" id="transitModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="transitForm">
<div class="modal-header"><h5 class="modal-title">Ghi nhận tiền đang chuyển</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="mb-3"><label>Diễn giải</label><input type="text" class="form-control" id="description" placeholder="Nộp tiền cuối ngày..."></div>
    <div class="mb-3"><label>Số chứng từ</label><input type="text" class="form-control" id="reference"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Ghi nhận</button>
</div>
</form>
</div></div></div>
<script>
function loadData() {
    $.get('/api/cash/transit', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">Chưa có giao dịch đang chuyển</td></tr>');return;}
        data.forEach(function(r){
            var actions='';
            if(r.status==='in_transit'){
                actions='<button class="btn btn-sm btn-success me-1" onclick="confirmTransit(\''+esc(r.id)+'\')"><i class="bi bi-check-lg"></i> Xác nhận</button>';
                actions+='<button class="btn btn-sm btn-danger" onclick="reverseTransit(\''+esc(r.id)+'\')"><i class="bi bi-x-lg"></i> Hủy</button>';
            }
            tbody.append('<tr><td>'+esc(r.id)+'</td><td>'+esc(r.description)+'</td><td class="text-end font-monospace">'+parseFloat(r.amount).toLocaleString()+'</td><td><span class="badge-status '+(r.status==='confirmed'?'badge-active':r.status==='reversed'?'badge-danger':'badge-warning')+'">'+esc(r.status)+'</span></td><td>'+esc(r.transit_date)+'</td><td>'+(r.confirm_date||'')+'</td><td>'+actions+'</td></tr>');
        });
    });
}
function confirmTransit(id) {
    if(!confirm('Xác nhận tiền đã vào tài khoản ngân hàng?'))return;
    $.ajax({url:'/api/cash/transit/confirm',method:'POST',contentType:'application/json',data:JSON.stringify({transit_id:id}),success:function(){showToast('Xác nhận thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
}
function reverseTransit(id) {
    if(!confirm('Hủy giao dịch chuyển tiền này?'))return;
    $.ajax({url:'/api/cash/transit/reverse',method:'POST',contentType:'application/json',data:JSON.stringify({transit_id:id}),success:function(){showToast('Đã hủy giao dịch','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
}
$('#transitForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/cash/transit',method:'POST',contentType:'application/json',data:JSON.stringify({amount:parseFloat($('#amount').val()),description:$('#description').val(),reference:$('#reference').val()||undefined}),success:function(){$('#transitModal').modal('hide');$('#transitForm')[0].reset();showToast('Ghi nhận thành công','success');loadData();},error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}});
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
