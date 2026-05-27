<?php // Màn hình: Phân tích tuổi nợ phải thu khách hàng
// API: GET /api/ar/aging
// Nghiệp vụ: Phân tích công nợ 131 theo thời gian quá hạn: current, 1-30, 31-60, 61-90, 90+ ngày
// Mục đích: Đánh giá rủi ro công nợ, trích lập dự phòng phải thu khó đòi (TT 48/2019/TT-BTC)
// Dự phòng: 6-12 tháng: 30%, 12-18 tháng: 50%, 18-36 tháng: 70%, trên 36 tháng: 100%
$title = 'Phân tích tuổi nợ phải thu'; $activeMenu = 'ar_aging'; ob_start(); ?>
<div class="toolbar"><h5>Phân tích tuổi nợ phải thu <span class="stats">(Aging & Dự phòng)</span></h5>
<div>
    <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Xuất Excel"><i class="bi bi-download"></i> Excel</button>
    <button class="btn btn-outline-primary btn-sm ms-1" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Làm mới</button>
</div></div>
<div id="agingContainer"></div>
<script>
function loadData(){
    $.get('/api/ar/aging', function(r){
        var html='',total=0,totalProv=0;
        var bucketOrder=['current','1-30','31-60','61-90','90plus'];
        var bucketStyle={'current':'border-left:4px solid #10b981;','1-30':'border-left:4px solid #f59e0b;','31-60':'border-left:4px solid #f97316;','61-90':'border-left:4px solid #ef4444;','90plus':'border-left:4px solid #991b1b;'};
        bucketOrder.forEach(function(b){
            var label={'current':'Chưa đến hạn','1-30':'1-30 ngày','31-60':'31-60 ngày','61-90':'61-90 ngày','90plus':'Trên 90 ngày'}[b];
            var items=r.buckets[b]||[],sub=r.totals[b]||0;total+=sub;
            var provSum=0;
            items.forEach(function(i){provSum+=parseFloat(i.provision_amount||0);});
            totalProv+=provSum;
            html+='<div class="card p-3 mb-3 border-0 shadow-sm" style="'+bucketStyle[b]+'">';
            html+='<h6 class="mb-2">'+label+' <span class="text-muted small">('+items.length+' hóa đơn - '+parseFloat(sub).toLocaleString()+' VND';
            if(provSum>0)html+=' - DP: <span class="text-danger">'+parseFloat(provSum).toLocaleString()+' VND</span>';
            html+=')</span></h6>';
            if(items.length===0){html+='<p class="text-muted small mb-0">Không có</p>';}
            else{
                html+='<table class="table table-sm mb-0"><thead><tr><th>KH</th><th>Hóa đơn</th><th>Ngày</th><th>Hạn</th><th class="text-end">Số tiền</th><th class="text-end">Quá hạn</th><th class="text-end">TL DP</th><th class="text-end">DP cần trích</th></tr></thead><tbody>';
                items.forEach(function(i){
                    var rowStyle=i.aging_days>365?' class="table-danger"':(i.aging_days>180?' class="table-warning"':'');
                    html+='<tr'+rowStyle+'><td>'+esc(i.customer_name)+'</td><td>'+esc(i.invoice_number)+'</td><td>'+esc(i.invoice_date)+'</td><td>'+esc(i.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(i.balance).toLocaleString()+'</td><td class="text-end font-monospace">'+i.aging_days+' ngày</td><td class="text-end font-monospace">'+i.provision_rate+'%</td><td class="text-end font-monospace">'+parseFloat(i.provision_amount).toLocaleString()+'</td></tr>';
                });
                html+='</tbody></table>';
            }
            html+='</div>';
        });
        html+='<div class="row g-2 mb-3">';
        html+='<div class="col-md-6"><div class="card p-3 border-0 shadow-sm bg-light"><h6>Tổng công nợ: <span class="text-primary">'+parseFloat(total).toLocaleString()+' VND</span></h6></div></div>';
        html+='<div class="col-md-6"><div class="card p-3 border-0 shadow-sm" style="background:#fef2f2;"><h6>Tổng dự phòng cần trích: <span class="text-danger">'+parseFloat(totalProv).toLocaleString()+' VND</span></h6></div></div>';
        html+='</div>';
        $('#agingContainer').html(html);
    });
}
function exportCSV(){
    $.get('/api/ar/aging', function(r){
        var rows=[['Khách hàng','Hóa đơn','Ngày HĐ','Hạn TT','Số tiền','Quá hạn (ngày)','TL DP (%)','DP cần trích','Nhóm']];
        var bucketOrder=['current','1-30','31-60','61-90','90plus'];
        var bucketLabel={'current':'Chưa đến hạn','1-30':'1-30 ngày','31-60':'31-60 ngày','61-90':'61-90 ngày','90plus':'Trên 90 ngày'};
        bucketOrder.forEach(function(b){
            (r.buckets[b]||[]).forEach(function(i){
                rows.push([i.customer_name,i.invoice_number,i.invoice_date,i.due_date,i.balance,i.aging_days,i.provision_rate,i.provision_amount,bucketLabel[b]]);
            });
        });
        var csv='\uFEFF';
        rows.forEach(function(row){
            csv+=row.map(function(v){return'"'+String(v).replace(/"/g,'""')+'"';}).join(',')+'\n';
        });
        var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
        var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='ar_aging_'+new Date().toISOString().slice(0,10)+'.csv';
        document.body.appendChild(a);a.click();document.body.removeChild(a);
    });
}
$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
