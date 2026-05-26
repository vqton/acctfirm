<?php $title = 'Tính thuế TNCN'; $activeMenu = 'payroll_tax'; ob_start(); ?>
<div class="toolbar"><div><h5>Tính thuế TNCN 2026</h5></div></div>
<div class="card-table p-3">
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Thu nhập chịu thuế</label><input class="form-control" id="taxGross" value="30000000"></div>
    <div class="col-md-2"><label class="form-label">BH đã đóng</label><input class="form-control" id="taxIns" value="3150000"></div>
    <div class="col-md-2"><label class="form-label">Người phụ thuộc</label><input class="form-control" id="taxDep" value="0" type="number"></div>
  </div>
  <div class="text-muted small mb-2">Giảm trừ bản thân: 15,500,000đ | Giảm trừ NPT: 6,200,000đ/NPT</div>
  <button class="btn btn-primary" onclick="doTaxCalc()"><i class="bi bi-calculator"></i> Tính thuế</button>
  <div id="taxResult" class="mt-3"></div>
</div>
<div class="card-table mt-3">
  <div class="card-header-x">Biểu thuế lũy tiến 2026</div>
  <table class="table"><thead><tr><th>Bậc</th><th>Thu nhập tính thuế</th><th>Thuế suất</th></tr></thead>
    <tbody>
      <tr><td>1</td><td>Đến 20 triệu</td><td>5%</td></tr>
      <tr><td>2</td><td>20 - 40 triệu</td><td>10%</td></tr>
      <tr><td>3</td><td>40 - 70 triệu</td><td>15%</td></tr>
      <tr><td>4</td><td>70 - 100 triệu</td><td>20%</td></tr>
      <tr><td>5</td><td>Trên 100 triệu</td><td>25%</td></tr>
    </tbody>
  </table>
</div>
<script>
function doTaxCalc(){$.get('/api/payroll/calculate/tax',{gross:$('#taxGross').val(),insurance_ee:$('#taxIns').val(),dependent_count:$('#taxDep').val()}).done(function(r){if(r.data===undefined)return;
    var taxable=$('#taxGross').val()-$('#taxIns').val()-15500000-$('#taxDep').val()*6200000;
    var h='<div class="card-table mt-2"><table class="table"><tbody>';
    h+='<tr><td>Thu nhập chịu thuế</td><td class="text-end">'+fmt($('#taxGross').val())+'</td></tr>';
    h+='<tr><td>Bảo hiểm</td><td class="text-end">-'+fmt($('#taxIns').val())+'</td></tr>';
    h+='<tr><td>Giảm trừ bản thân</td><td class="text-end">-15,500,000</td></tr>';
    if(parseInt($('#taxDep').val())>0)h+='<tr><td>Giảm trừ NPT ('+$('#taxDep').val()+' người)</td><td class="text-end">-'+fmt(parseInt($('#taxDep').val())*6200000)+'</td></tr>';
    h+='<tr><td>Thu nhập tính thuế</td><td class="text-end">'+(taxable>0?fmt(taxable):0)+'</td></tr>';
    h+='<tr class="table-primary"><td><strong>Thuế TNCN</strong></td><td class="text-end"><strong>'+fmt(r.data.tax_amount)+'</strong></td></tr>';
    h+='</tbody></table></div>';$('#taxResult').html(h);}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
