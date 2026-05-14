<?php $title = 'Phân tích tuổi nợ'; $activeMenu = 'ap_aging'; ob_start(); ?>
<div class="toolbar">
    <h5>Phân tích tuổi nợ phải trả <span class="stats">(Aging)</span></h5>
    <div><button class="btn btn-outline-primary btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button></div>
</div>
<div id="agingContainer"></div>
<script>
function loadData(){
    $.get('/api/ap/aging', function(r){
        var html='';
        var total=0;
        ['current','1-30','31-60','61-90','90plus'].forEach(function(bucket){
            var label={'current':'Chưa đến hạn','1-30':'1-30 ngày','31-60':'31-60 ngày','61-90':'61-90 ngày','90plus':'Trên 90 ngày'}[bucket];
            var items=r.buckets[bucket]||[];
            var subtotal=r.totals[bucket]||0;
            total+=subtotal;
            html+='<div class="card p-3 mb-3 border-0 shadow-sm">';
            html+='<h6 class="mb-2">'+label+' <span class="text-muted small">('+items.length+' hóa đơn - '+parseFloat(subtotal).toLocaleString()+' VND)</span></h6>';
            if(items.length===0){html+='<p class="text-muted small mb-0">Không có</p>';}
            else{
                html+='<table class="table table-sm mb-0"><thead><tr><th>NCC</th><th>Hóa đơn</th><th>Ngày HĐ</th><th>Hạn</th><th class="text-end">Số tiền</th><th class="text-end">Quá hạn</th></tr></thead><tbody>';
                items.forEach(function(i){
                    html+='<tr><td>'+esc(i.supplier_name)+'</td><td>'+esc(i.invoice_number)+'</td><td>'+esc(i.invoice_date)+'</td><td>'+esc(i.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(i.balance).toLocaleString()+'</td><td class="text-end font-monospace">'+i.aging_days+' ngày</td></tr>';
                });
                html+='</tbody></table>';
            }
            html+='</div>';
        });
        html+='<div class="card p-3 border-0 shadow-sm bg-light"><h6>Tổng công nợ: <span class="text-primary">'+parseFloat(total).toLocaleString()+' VND</span></h6></div>';
        $('#agingContainer').html(html);
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
