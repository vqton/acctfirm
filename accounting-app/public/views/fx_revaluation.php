<?php $title = 'Đánh giá lại ngoại tệ'; $activeMenu = 'fx_revaluation'; ob_start(); ?>
<div class="toolbar">
    <h5>Đánh giá lại ngoại tệ <span class="stats">(VAS 10)</span></h5>
    <button class="btn btn-primary btn-sm" id="revalueBtn"><i class="bi bi-arrow-repeat"></i> Đánh giá lại</button>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>TK</th><th>Loại tiền</th><th class="text-end">SD ngoại tệ</th><th class="text-end">Tỷ giá SS</th><th class="text-end">Tỷ giá ĐG</th><th class="text-end">CL tăng</th><th class="text-end">CL giảm</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="row g-3 mt-2"><div class="col-md-4"><label>Tỷ giá đánh giá lại</label><input type="number" class="form-control" id="closingRate" step="1" min="0" placeholder="Ví dụ: 25500"></div>
<div class="col-md-4"><label>Tài khoản</label><select class="form-select" id="fxAccount"><option value="1112">1121 - USD</option><option value="1122">1122 - USD</option></select></div></div>

<script>
function loadData(){
    var ac=$('#fxAccount').val();
    $.get('/api/fx/balances',{account_code:ac},function(data){
        var tbody=$('#dataBody'); tbody.empty();
        if(data&&data.length){data.forEach(function(r){
            var diff=parseFloat(r.fc_balance)*(parseFloat(r.closing_rate||0)-parseFloat(r.rate||0));
            var gain=diff>0?diff:0,loss=diff<0?Math.abs(diff):0;
            tbody.append('<tr><td>'+esc(r.account_code)+'</td><td>'+esc(r.currency_code)+'</td><td class="text-end font-monospace">'+parseFloat(r.fc_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+(r.rate?parseFloat(r.rate).toLocaleString():'-')+'</td><td class="text-end font-monospace">'+(r.closing_rate?parseFloat(r.closing_rate).toLocaleString():'-')+'</td><td class="text-end font-monospace text-success">'+gain.toLocaleString()+'</td><td class="text-end font-monospace text-danger">'+loss.toLocaleString()+'</td></tr>');
        });}else{tbody.append('<tr><td colspan="7" class="text-center text-muted">Không có số dư ngoại tệ</td></tr>');}
    });
}
$('#revalueBtn').click(function(){
    var rate=parseFloat($('#closingRate').val());if(!rate||rate<=0){showToast('Nhập tỷ giá đánh giá lại','error');return;}
    $.ajax({url:'/api/fx/revalue',method:'POST',contentType:'application/json',headers:{'X-CSRF-Token':csrf},data:JSON.stringify({account_code:$('#fxAccount').val(),currency_code:'USD',closing_rate:rate}),
        success:function(){showToast('Đánh giá lại thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});
$('#fxAccount').change(loadData);
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
