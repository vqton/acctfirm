<?php // Màn hình: Sổ chi tiết công nợ nhà cung cấp
$title = 'Sổ chi tiết công nợ'; $activeMenu = 'ap_statement'; ob_start(); ?>
<div class="toolbar">
    <h5>Sổ chi tiết công nợ nhà cung cấp</h5>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Xuất Excel"><i class="bi bi-download"></i> Excel</button>
        <select class="form-select form-select-sm d-inline-block w-auto ms-1" id="stmtSupplier"><option value="">Chọn NCC...</option></select>
        <button class="btn btn-outline-primary btn-sm ms-1" onclick="loadData()"><i class="bi bi-search"></i> Xem</button>
    </div>
</div>
<div class="card-table"><table class="table table-hover table-sm">
    <thead><tr><th>Hóa đơn</th><th>Ngày</th><th>Hạn</th><th class="text-end">Tổng</th><th class="text-end">Đã trả</th><th class="text-end">Còn lại</th><th>Trạng thái</th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>
var stmtData=[];
function loadSuppliers(){
    $.get('/api/ap/suppliers', function(list){
        var opts='<option value="">Chọn NCC...</option>';
        list.forEach(function(s){opts+='<option value="'+esc(s.id)+'">'+esc(s.name)+'</option>';});
        $('#stmtSupplier').html(opts);
    });
}
function loadData(){
    var sid=$('#stmtSupplier').val();
    if(!sid){return;}
    $.get('/api/ap/suppliers/'+sid+'/statement', function(data){
        stmtData=data;
        var tbody=$('#dataBody'); tbody.empty();
        data.forEach(function(r){
            tbody.append('<tr><td>'+esc(r.invoice_number)+'</td><td>'+esc(r.invoice_date)+'</td><td>'+esc(r.due_date)+'</td><td class="text-end font-monospace">'+parseFloat(r.gross_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.paid_amount).toLocaleString()+'</td><td class="text-end font-monospace">'+parseFloat(r.balance).toLocaleString()+'</td><td>'+statusBadge(r.status)+'</td></tr>');
        });
    });
}
function exportCSV(){
    if(!stmtData.length){showToast('Chưa có dữ liệu, vui lòng chọn nhà cung cấp','error');return;}
    var rows=[['Hóa đơn','Ngày HĐ','Hạn TT','Tổng','Đã trả','Còn lại','Trạng thái']];
    stmtData.forEach(function(r){rows.push([r.invoice_number,r.invoice_date,r.due_date,r.gross_amount,r.paid_amount,r.balance,r.status]);});
    var csv='\uFEFF';
    rows.forEach(function(row){csv+=row.map(function(v){return'"'+String(v).replace(/"/g,'""')+'"';}).join(',')+'\n';});
    var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='ap_statement_'+$('#stmtSupplier option:selected').text().trim()+'_'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a);a.click();document.body.removeChild(a);
}
$(document).ready(function(){loadSuppliers();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
