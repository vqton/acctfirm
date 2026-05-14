<?php $title = 'Đánh giá lại ngoại tệ'; $activeMenu = 'fx_revaluation'; ob_start(); ?>
<div class="toolbar">
    <h5>Đánh giá lại ngoại tệ <span class="stats">(VAS 10)</span></h5>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card p-3 border-0 shadow-sm">
            <h6 class="mb-3">Số dư ngoại tệ</h6>
            <div class="card-table"><table class="table table-hover table-sm"><thead><tr><th>TK</th><th>Loại tiền</th><th class="text-end">Số dư NT</th><th class="text-end">Số dư VND</th><th class="text-end">Tỷ giá sổ sách</th></tr></thead><tbody id="balanceBody"></tbody></table></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 border-0 shadow-sm">
            <h6 class="mb-3">Đánh giá lại</h6>
            <div class="mb-3"><label class="form-label">Tài khoản</label>
                <select class="form-select" id="revalAccount"><option value="112">112 - Tiền gửi</option></select>
            </div>
            <div class="mb-3"><label class="form-label">Loại tiền</label>
                <select class="form-select" id="revalCurrency"><option value="USD">USD</option><option value="EUR">EUR</option></select>
            </div>
            <div class="mb-3"><label class="form-label">Tỷ giá cuối kỳ</label>
                <input type="number" class="form-control" id="revalRate" step="1" min="1" placeholder="VD: 25800">
            </div>
            <div class="mb-3"><label class="form-label">Ngày</label>
                <input type="date" class="form-control" id="revalDate" value="<?=date('Y-m-d')?>">
            </div>
            <button class="btn btn-primary" onclick="runRevaluation()"><i class="bi bi-calculator"></i> Thực hiện đánh giá</button>
        </div>
    </div>
</div>

<div id="resultBox" class="d-none">
    <div class="card p-3 border-0 shadow-sm">
        <h6 class="mb-3">Kết quả</h6>
        <div id="resultBody"></div>
    </div>
</div>

<script>
function loadBalances() {
    $.get('/api/fx/balances', function(data) {
        var tbody=$('#balanceBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="5" class="text-center text-muted py-3">Không có số dư ngoại tệ</td></tr>');return;}
        data.forEach(function(b){
            tbody.append('<tr><td>'+esc(b.account)+'</td><td>'+esc(b.currency)+'</td><td class="text-end font-monospace">'+parseFloat(b.fc_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(b.vnd_balance).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(b.avg_rate).toLocaleString()+'</td></tr>');
        });
    });
}

function runRevaluation() {
    var rate = parseFloat($('#revalRate').val());
    if(!rate || rate<=0){showToast('Nhập tỷ giá cuối kỳ','error');return;}
    $.ajax({
        url:'/api/fx/revalue', method:'POST', contentType:'application/json',
        data:JSON.stringify({
            account_code:$('#revalAccount').val(),
            currency_code:$('#revalCurrency').val(),
            closing_rate:rate,
            as_of_date:$('#revalDate').val()
        }),
        success:function(r){
            $('#resultBox').removeClass('d-none');
            var html='';
            if(r.gain_loss===0){
                html='<p class="text-muted">Không có chênh lệch tỷ giá.</p>';
            } else {
                var sign = r.gain_loss > 0 ? '+' : '';
                html+='<table class="table table-sm"><tr><th>Lãi/lỗ</th><td class="text-end font-monospace '+(r.gain_loss>0?'text-success':'text-danger')+'">'+sign+parseFloat(r.gain_loss).toLocaleString()+' VND</td></tr>';
                html+='<tr><th>Giao dịch</th><td>'+esc(r.transaction_id)+'</td></tr>';
                html+='<tr><th>Tỷ giá sổ sách</th><td class="text-end font-monospace">'+parseFloat(r.book_rate).toLocaleString()+'</td></tr>';
                html+='<tr><th>Tỷ giá cuối kỳ</th><td class="text-end font-monospace">'+parseFloat(r.closing_rate).toLocaleString()+'</td></tr>';
                html+='<tr><th>Số dư ngoại tệ</th><td class="text-end font-monospace">'+parseFloat(r.fc_balance).toLocaleString()+'</td></tr></table>';
            }
            $('#resultBody').html(html);
            loadBalances();
            showToast('Đã đánh giá lại ngoại tệ','success');
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

$(document).ready(function(){loadBalances();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
