<?php // Màn hình: Phân tích tuổi nợ phải trả nhà cung cấp
// API: GET /api/ap/aging
// Nghiệp vụ: Phân tích công nợ 331 theo thời gian quá hạn: current, 1-30, 31-60, 61-90, 90+ ngày
// Mục đích: Quản lý dòng tiền, ưu tiên thanh toán các khoản gần quá hạn
$title = 'Phân tích tuổi nợ'; $activeMenu = 'ap_aging'; ob_start(); ?>
<div class="toolbar">
    <h5>Phân tích tuổi nợ phải trả <span class="stats">(Aging)</span></h5>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Xuất Excel"><i class="bi bi-download"></i> Excel</button>
        <button class="btn btn-outline-primary btn-sm ms-1" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
    </div>
</div>
<div id="agingContainer"></div>
<script>
function loadData(){
    $.get('/api/ap/aging', function(r){
        var html='',total=0;
        var bucketOrder=['current','1-30','31-60','61-90','90plus'];
        var bucketStyle={'current':'border-left:4px solid #10b981;','1-30':'border-left:4px solid #f59e0b;','31-60':'border-left:4px solid #f97316;','61-90':'border-left:4px solid #ef4444;','90plus':'border-left:4px solid #991b1b;'};
        bucketOrder.forEach(function(bucket){
            var label={'current':'Chưa đến hạn','1-30':'1-30 ngày','31-60':'31-60 ngày','61-90':'61-90 ngày','90plus':'Trên 90 ngày'}[bucket];
            var items=r.buckets[bucket]||[],subtotal=r.totals[bucket]||0;total+=subtotal;
            html+='<div class="card p-3 mb-3 border-0 shadow-sm" style="'+bucketStyle[bucket]+'">';
            html+='<h6 class="mb-2">'+label+' <span class="text-muted small">('+items.length+' hóa đơn - '+parseFloat(subtotal).toLocaleString()+' VND)</span></h6>';
            if(items.length===0){html+='<p class="text-muted small mb-0">Không có</p>';}
            else{
                html+='<table class="table table-sm mb-0"><thead><tr><th>NCC</th><th>Hóa đơn</th><th>Ngày HĐ</th><th>Hạn</th><th class="text-end">Số tiền</th><th class="text-end">Quá hạn</th></tr></thead><tbody>';
                items.forEach(function(i){
                    var rowStyle=i.aging_days>90?' class="table-danger"':(i.aging_days>30?' class="table-warning"':'');
                    html+='<tr'+rowStyle+'><td>'+esc(i.supplier_name)+'</td><td>'+esc(i.invoice_number)+'</td><td>'+esc(i.invoice_date)+'</td><td>'+esc(i.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(i.balance).toLocaleString()+'</td><td class="text-end font-monospace">'+i.aging_days+' ngày</td></tr>';
                });
                html+='</tbody></table>';
            }
            html+='</div>';
        });
        html+='<div class="card p-3 border-0 shadow-sm bg-light"><h6>Tổng công nợ: <span class="text-primary">'+parseFloat(total).toLocaleString()+' VND</span></h6></div>';
        $('#agingContainer').html(html);
    });
}
function exportCSV(){
    $.get('/api/ap/aging', function(r){
        var rows=[['Nhà cung cấp','Hóa đơn','Ngày HĐ','Hạn TT','Số tiền','Quá hạn (ngày)','Nhóm']];
        var bucketOrder=['current','1-30','31-60','61-90','90plus'];
        var bucketLabel={'current':'Chưa đến hạn','1-30':'1-30 ngày','31-60':'31-60 ngày','61-90':'61-90 ngày','90plus':'Trên 90 ngày'};
        bucketOrder.forEach(function(b){
            (r.buckets[b]||[]).forEach(function(i){
                rows.push([i.supplier_name,i.invoice_number,i.invoice_date,i.due_date,i.balance,i.aging_days,bucketLabel[b]]);
            });
        });
        var csv='\uFEFF';
        rows.forEach(function(row){
            csv+=row.map(function(v){return'"'+String(v).replace(/"/g,'""')+'"';}).join(',')+'\n';
        });
        var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
        var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='ap_aging_'+new Date().toISOString().slice(0,10)+'.csv';
        document.body.appendChild(a);a.click();document.body.removeChild(a);
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
