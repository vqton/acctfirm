<?php // Màn hình: Quản lý tiền đang chuyển (TK 113)
// API: GET /api/cash/transit, POST /api/cash/transit, POST /api/cash/transit/confirm, POST /api/cash/transit/reverse
// Nghiệp vụ: Nộp tiền mặt vào ngân hàng — Nợ 113/Có 1111 (khi chuyển), Nợ 1121/Có 113 (khi NH xác nhận)
// Rủi ro: Tiền đang chuyển chưa ghi nhận vào TK 112 — nếu không confirm kịp thời sẽ sai số dư NH
$title = 'Tiền đang chuyển'; $activeMenu = 'cash_transit'; ob_start(); ?>
<div class="toolbar">
    <h5>Tiền đang chuyển <span class="stats">(TK 113)</span></h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transitModal"><i class="bi bi-plus-lg"></i> Chuyển tiền</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>ID</th><th>Số tiền</th><th>Diễn giải</th><th>Ngày chuyển</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="transitModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="transitForm">
<div class="modal-header"><h5 class="modal-title">Chuyển tiền qua ngân hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="amount" step="1000" min="1" required></div>
    <div class="mb-2"><label>Diễn giải</label><input class="form-control" id="description" placeholder="Chuyển tiền vào tài khoản..."></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Ghi nhận chuyển</button></div>
</form>
</div></div></div>

<script>
function loadData(){
    $.get('/api/cash/transit',function(data){
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            var badge=r.status==='confirmed'?'badge-active':(r.status==='reversed'?'badge-danger':'badge-warning');
            var acts='';
            if(r.status==='pending') acts+='<button class="btn btn-sm btn-outline-success me-1" onclick="confirmTransit('+r.id+')"><i class="bi bi-check-lg"></i></button><button class="btn btn-sm btn-outline-danger" onclick="reverseTransit('+r.id+')"><i class="bi bi-x-lg"></i></button>';
            tbody.append('<tr><td>'+esc(r.id)+'</td><td class="text-end font-monospace">'+parseFloat(r.amount).toLocaleString()+'</td><td>'+esc(r.description)+'</td><td style="font-size:12px">'+esc(r.created_at)+'</td><td><span class="badge-status '+badge+'">'+esc(r.status)+'</span></td><td>'+acts+'</td></tr>');
        });
    });
}
// Xác nhận tiền đã về tài khoản ngân hàng — POST /api/cash/transit/confirm
// Nghiệp vụ: Nợ 1121/Có 113 — chuyển từ tiền đang chuyển sang tiền gửi NH
function confirmTransit(id){
    $.ajax({url:'/api/cash/transit/confirm',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({id:id}),
        success:function(){showToast('Đã xác nhận tiền về NH','success');loadData();},
        error:function(x){showToast('Lỗi','error');}
    });
}
// Hủy giao dịch chuyển tiền — POST /api/cash/transit/reverse
// Nghiệp vụ: Nợ 1111/Có 113 — hoàn nhập, tiền chưa về đến NH
function reverseTransit(id){
    if(!confirm('Hủy giao dịch chuyển tiền này?'))return;
    $.ajax({url:'/api/cash/transit/reverse',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({id:id}),
        success:function(){showToast('Đã hủy chuyển tiền','success');loadData();},
        error:function(x){showToast('Lỗi','error');}
    });
}
// Submit ghi nhận chuyển tiền — POST /api/cash/transit
// Nghiệp vụ: Nợ 113/Có 1111 — rút tiền mặt để nộp vào ngân hàng
$('#transitForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/cash/transit',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({amount:parseFloat($('#amount').val()),description:$('#description').val()}),
        success:function(){$('#transitModal').modal('hide');$('#transitForm')[0].reset();showToast('Ghi nhận chuyển tiền thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
